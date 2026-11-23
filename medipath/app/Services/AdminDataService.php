<?php

namespace App\Services;

use App\Enums\AccountStatus;
use App\Models\BackupSnapshot;
use App\Models\Doctor;
use App\Models\InventoryLedger;
use App\Models\Invoice;
use App\Models\Medicine;
use App\Models\Patient;
use App\Models\User;
use App\Support\ActivityActions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Data Management: exports, backups, two-step import, danger-zone actions (PLAN 3.10). */
class AdminDataService
{
    public function __construct(private readonly ActivityLogService $activity) {}

    public function exportCsv(string $type): string
    {
        $rows = match ($type) {
            'patients' => Patient::query()->with('user:id,status')->orderBy('patient_code')->get()
                ->map(fn (Patient $patient): array => [
                    $patient->patient_code, $patient->name, $patient->phone, $patient->age, $patient->gender,
                    $patient->blood_group, $patient->condition, $patient->user?->status?->value,
                    $patient->is_walk_in ? 'walk-in' : 'registered', $patient->created_at?->toIso8601String(),
                ])->all(),
            'doctors' => Doctor::query()->with(['user:id,name,email,status,archived_at', 'specialty:id,name'])->orderBy('doctor_code')->get()
                ->map(fn (Doctor $doctor): array => [
                    $doctor->doctor_code, $doctor->user?->name, $doctor->user?->email, $doctor->specialty?->name,
                    $doctor->license_number, $doctor->license_authority, $doctor->verification_status?->value,
                    $doctor->fee, $doctor->archived_at?->toIso8601String(),
                ])->all(),
            'invoices' => Invoice::query()->with('patient:id,patient_code,name')->latest()->get()
                ->map(fn (Invoice $invoice): array => [
                    $invoice->invoice_code, $invoice->patient?->patient_code, $invoice->patient?->name,
                    $invoice->net_total, $invoice->currency_code, $invoice->payment_status?->value,
                    $invoice->payment_method, $invoice->paid_at?->toIso8601String(),
                ])->all(),
            'inventory' => InventoryLedger::query()->with('medicine:id,name')->latest('id')->limit(5000)->get()
                ->map(fn (InventoryLedger $entry): array => [
                    $entry->id, $entry->medicine?->name, $entry->type?->value, $entry->quantity_change,
                    $entry->previous_qty, $entry->new_qty, $entry->reference, $entry->created_at?->toIso8601String(),
                ])->all(),
            default => throw ValidationException::withMessages(['type' => 'Unknown export type.']),
        };

        $headers = match ($type) {
            'patients' => ['patient_code', 'name', 'phone', 'age', 'gender', 'blood_group', 'condition', 'account_status', 'workflow', 'created_at'],
            'doctors' => ['doctor_code', 'name', 'email', 'specialty', 'license_number', 'license_authority', 'verification_status', 'fee', 'archived_at'],
            'invoices' => ['invoice_code', 'patient_code', 'patient_name', 'net_total', 'currency_code', 'payment_status', 'payment_method', 'paid_at'],
            'inventory' => ['ledger_id', 'medicine', 'type', 'quantity_change', 'previous_qty', 'new_qty', 'reference', 'created_at'],
        };

        return $this->csv($headers, $rows);
    }

    public function createBackup(User $actor, string $label = ''): BackupSnapshot
    {
        $payload = [
            'generated_at' => now()->toIso8601String(),
            'database' => config('database.connections.mysql.database'),
            'tables' => collect(['users', 'patients', 'doctors', 'appointments', 'prescriptions', 'invoices', 'medicines', 'activity_log'])
                ->mapWithKeys(fn (string $table): array => [$table => DB::table($table)->count()])
                ->all(),
        ];

        $path = 'snapshots/'.now()->format('Ymd_His').'_'.Str::random(6).'.json';
        $contents = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        Storage::disk('backups')->put($path, $contents);

        $snapshot = BackupSnapshot::query()->create([
            'label' => $label !== '' ? $label : 'Manual metadata snapshot',
            'disk' => 'backups',
            'path' => $path,
            'size_bytes' => strlen($contents),
            'status' => 'ready',
            'created_by' => $actor->id,
        ]);

        $this->activity->record(ActivityActions::BACKUP_CREATED, $snapshot, [
            'label' => $snapshot->label,
            'size_bytes' => $snapshot->size_bytes,
            'kind' => 'metadata',
        ], $actor);

        return $snapshot;
    }

    public function verifyBackup(BackupSnapshot $snapshot, User $actor): BackupSnapshot
    {
        $exists = Storage::disk($snapshot->disk)->exists($snapshot->path);
        $snapshot->forceFill(['status' => $exists ? 'verified' : 'failed'])->save();

        $this->activity->record(ActivityActions::BACKUP_CREATED, $snapshot, [
            'event' => 'restore_drill',
            'file_present' => $exists,
        ], $actor);

        return $snapshot->fresh();
    }

    public function deleteBackup(BackupSnapshot $snapshot, User $actor): void
    {
        Storage::disk($snapshot->disk)->delete($snapshot->path);
        $this->activity->record(ActivityActions::BACKUP_DELETED, $snapshot, [
            'label' => $snapshot->label,
            'path' => $snapshot->path,
        ], $actor);
        $snapshot->delete();
    }

    /** @return array<string, mixed> */
    public function dryRunImport(UploadedFile $file, User $actor): array
    {
        $handle = fopen($file->getRealPath(), 'r');
        if ($handle === false) {
            throw ValidationException::withMessages(['file' => 'The CSV file could not be read.']);
        }

        $header = fgetcsv($handle);
        if (! is_array($header)) {
            fclose($handle);
            throw ValidationException::withMessages(['file' => 'The CSV needs a header row.']);
        }

        $header = array_map(fn ($value): string => Str::lower(trim((string) $value)), $header);
        $required = ['name', 'generic_name', 'category', 'strength', 'form', 'unit_price', 'stock_quantity', 'min_stock_threshold', 'expiry_date', 'batch_number'];
        $missing = array_diff($required, $header);
        if ($missing !== []) {
            fclose($handle);
            throw ValidationException::withMessages(['file' => 'Missing columns: '.implode(', ', $missing)]);
        }

        $valid = [];
        $invalid = [];
        $updates = 0;
        $rowNumber = 1;

        while (($data = fgetcsv($handle)) !== false) {
            $rowNumber++;
            if (count(array_filter($data, fn ($v): bool => trim((string) $v) !== '')) === 0) {
                continue;
            }

            $row = array_combine($header, array_pad($data, count($header), null));
            $errors = [];

            foreach (['name', 'category', 'strength', 'form', 'expiry_date', 'batch_number'] as $key) {
                if (trim((string) ($row[$key] ?? '')) === '') {
                    $errors[] = "{$key} is required";
                }
            }
            if (! is_numeric($row['unit_price'] ?? null) || (float) $row['unit_price'] < 0) {
                $errors[] = 'unit_price must be ≥ 0';
            }
            foreach (['stock_quantity', 'min_stock_threshold'] as $key) {
                if (! is_numeric($row[$key] ?? null) || (int) $row[$key] < 0) {
                    $errors[] = "{$key} must be ≥ 0";
                }
            }
            if (! strtotime((string) ($row['expiry_date'] ?? ''))) {
                $errors[] = 'expiry_date must be a valid date';
            }

            if ($errors !== []) {
                $invalid[] = ['row' => $rowNumber, 'reason' => implode('; ', $errors)];
                continue;
            }

            $normalized = [
                'name' => trim((string) $row['name']),
                'generic_name' => trim((string) $row['generic_name']),
                'category' => trim((string) $row['category']),
                'strength' => trim((string) $row['strength']),
                'form' => trim((string) $row['form']),
                'unit_price' => (float) $row['unit_price'],
                'stock_quantity' => (int) $row['stock_quantity'],
                'min_stock_threshold' => (int) $row['min_stock_threshold'],
                'expiry_date' => date('Y-m-d', strtotime((string) $row['expiry_date'])),
                'batch_number' => trim((string) $row['batch_number']),
                'is_active' => true,
            ];

            $exists = Medicine::query()
                ->where('name', $normalized['name'])
                ->where('strength', $normalized['strength'])
                ->where('batch_number', $normalized['batch_number'])
                ->exists();

            if ($exists) {
                $updates++;
            }
            $valid[] = $normalized;
        }

        fclose($handle);

        $token = Str::random(32);
        Cache::put('admin.import.'.$actor->id.'.'.$token, $valid, now()->addMinutes(20));

        return [
            'token' => $token,
            'valid' => count($valid),
            'invalid' => count($invalid),
            'updates' => $updates,
            'errors' => array_slice($invalid, 0, 50),
            'fileName' => $file->getClientOriginalName(),
        ];
    }

    public function applyImport(string $token, User $actor): int
    {
        $key = 'admin.import.'.$actor->id.'.'.$token;
        /** @var array<int, array<string, mixed>>|null $rows */
        $rows = Cache::get($key);
        if (! is_array($rows) || $rows === []) {
            throw ValidationException::withMessages(['token' => 'The dry-run preview expired. Run the dry-run again.']);
        }

        $count = DB::transaction(function () use ($rows, $actor, $key): int {
            foreach ($rows as $row) {
                Medicine::query()->updateOrCreate(
                    ['name' => $row['name'], 'strength' => $row['strength'], 'batch_number' => $row['batch_number']],
                    $row,
                );
            }

            Cache::forget($key);

            $this->activity->record(ActivityActions::DATA_IMPORTED, null, [
                'rows' => count($rows),
                'source' => 'medicine_csv',
            ], $actor);

            return count($rows);
        });

        return $count;
    }

    public function purgeArchivedSessions(User $actor): int
    {
        $ids = User::query()->where('status', AccountStatus::Archived)->pluck('id')->all();
        $deleted = $ids === [] ? 0 : DB::table('sessions')->whereIn('user_id', $ids)->delete();

        $this->activity->record(ActivityActions::USER_UPDATED, null, [
            'event' => 'purged_archived_sessions',
            'count' => $deleted,
        ], $actor);

        return $deleted;
    }

    public function deleteFailedBackups(User $actor): int
    {
        $failed = BackupSnapshot::query()->where('status', 'failed')->get();
        foreach ($failed as $snapshot) {
            $this->deleteBackup($snapshot, $actor);
        }

        return $failed->count();
    }

    public function reseedDemo(User $actor): void
    {
        abort_unless(app()->environment(['local', 'testing']), 403, 'Demo reset is local-only.');

        Artisan::call('db:seed', ['--force' => true]);

        $this->activity->record(ActivityActions::SETTINGS_UPDATED, null, [
            'event' => 'demo_reseed',
        ], $actor);
    }

    /** @param array<int, array<int, mixed>> $rows */
    private function csv(array $headers, array $rows): string
    {
        $handle = fopen('php://temp', 'r+');
        $this->putCsvRow($handle, $headers);
        foreach ($rows as $row) {
            $this->putCsvRow($handle, $row);
        }
        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }

    /** @param resource $handle @param array<int, mixed> $row */
    private function putCsvRow($handle, array $row): void
    {
        $safe = array_map(function (mixed $value): string {
            $text = (string) ($value ?? '');
            return preg_match('/^[=+\-@\t\r]/', $text) === 1 ? "'".$text : $text;
        }, $row);

        fputcsv($handle, $safe, ',', '"', '\\');
    }
}