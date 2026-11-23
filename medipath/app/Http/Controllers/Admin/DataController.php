<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BackupSnapshot;
use App\Services\ActivityLogService;
use App\Services\AdminDataService;
use App\Support\ActivityActions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** Data management: exports, backups, two-step import, danger zone (admin/13, Sprint 3.10). */
class DataController extends Controller
{
    public function __construct(
        private readonly AdminDataService $data,
        private readonly ActivityLogService $activity,
    ) {}

    public function index(Request $request): Response
    {
        $snapshots = BackupSnapshot::query()->with('createdBy:id,name')->latest()->limit(12)->get();

        return Inertia::render('Admin/Data', [
            'exports' => [
                ['type' => 'patients', 'label' => 'Patients + workflow status'],
                ['type' => 'doctors', 'label' => 'Doctors, licenses & verification'],
                ['type' => 'invoices', 'label' => 'Invoices + payment status'],
                ['type' => 'inventory', 'label' => 'Stock movements ledger'],
            ],
            'snapshots' => $snapshots->map(fn (BackupSnapshot $snapshot): array => [
                'id' => $snapshot->id,
                'label' => $snapshot->label,
                'status' => $snapshot->status,
                'size' => $this->humanBytes($snapshot->size_bytes),
                'sizeBytes' => $snapshot->size_bytes,
                'path' => $snapshot->path,
                'createdBy' => $snapshot->createdBy?->name,
                'createdAt' => $snapshot->created_at?->toIso8601String(),
            ])->values(),
            'importPreview' => $request->session()->get('import_preview'),
            'canReseed' => app()->environment(['local', 'testing']),
            'counts' => [
                'patients' => \App\Models\Patient::query()->count(),
                'doctors' => \App\Models\Doctor::query()->count(),
                'invoices' => \App\Models\Invoice::query()->count(),
                'medicines' => \App\Models\Medicine::query()->count(),
            ],
            'lastKnownAt' => now()->toIso8601String(),
        ]);
    }

    public function export(Request $request, string $type): HttpResponse
    {
        $csv = $this->data->exportCsv($type);

        $this->activity->record(ActivityActions::DATA_EXPORTED, null, [
            'type' => $type,
            'bytes' => strlen($csv),
        ], $request->user());

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="medipath-'.$type.'-'.now()->format('Ymd-His').'.csv"',
            'Cache-Control' => 'no-store, private',
        ]);
    }

    public function backup(Request $request): RedirectResponse
    {
        $validated = $request->validate(['label' => ['nullable', 'string', 'max:160']]);

        $snapshot = $this->data->createBackup($request->user(), (string) ($validated['label'] ?? ''));

        return back()->with('success', 'Snapshot created: '.$snapshot->label.'.');
    }

    public function verifyBackup(Request $request, BackupSnapshot $snapshot): RedirectResponse
    {
        $updated = $this->data->verifyBackup($snapshot, $request->user());

        return back()->with('success', $updated->status === 'verified' ? 'Restore drill verified the snapshot file.' : 'Snapshot file is missing — marked failed.');
    }

    public function deleteBackup(Request $request, BackupSnapshot $snapshot): RedirectResponse
    {
        if ($snapshot->status !== 'failed') {
            throw ValidationException::withMessages(['backup' => 'Only failed snapshots can be deleted from the danger zone.']);
        }

        $this->data->deleteBackup($snapshot, $request->user());

        return back()->with('success', 'Failed snapshot deleted.');
    }

    public function importDryRun(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ]);

        $preview = $this->data->dryRunImport($request->file('file'), $request->user());

        return back()->with('import_preview', $preview);
    }

    public function importApply(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'size:32'],
            'confirm' => ['accepted'],
        ]);

        $count = $this->data->applyImport((string) $validated['token'], $request->user());

        return back()->with('success', "Imported {$count} medicine rows in one transaction.");
    }

    public function danger(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'action' => ['required', 'in:purge_sessions,delete_failed_backups,reseed_demo'],
            'password' => ['required', 'string'],
            'confirmation' => ['required', 'string', 'max:12'],
        ]);

        if (! Hash::check((string) $validated['password'], (string) $request->user()->password)) {
            throw ValidationException::withMessages(['password' => 'Password confirmation failed.']);
        }

        $phrase = match ($validated['action']) {
            'purge_sessions' => 'PURGE',
            'delete_failed_backups' => 'DELETE',
            'reseed_demo' => 'RESET',
        };

        if (Str::upper((string) $validated['confirmation']) !== $phrase) {
            throw ValidationException::withMessages(['confirmation' => "Type {$phrase} to confirm."]);
        }

        $message = match ($validated['action']) {
            'purge_sessions' => $this->data->purgeArchivedSessions($request->user()).' archived session row(s) purged.',
            'delete_failed_backups' => $this->data->deleteFailedBackups($request->user()).' failed snapshot(s) deleted.',
            'reseed_demo' => 'Demo seeders re-run.',
        };

        if ($validated['action'] === 'reseed_demo') {
            $this->data->reseedDemo($request->user());
        }

        return back()->with('success', $message);
    }

    private function humanBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' B';
        }
        if ($bytes < 1024 * 1024) {
            return round($bytes / 1024, 1).' KB';
        }

        return round($bytes / (1024 * 1024), 1).' MB';
    }
}