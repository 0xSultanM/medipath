<?php

namespace App\Services;

use App\Models\RevenueConfig;
use App\Models\User;
use App\Support\ActivityActions;
use Illuminate\Support\Facades\DB;

/** Global settings + effective-dated revenue configuration (PLAN 3.3/3.9). */
class AdminSettingsService
{
    public function __construct(
        private readonly SettingsService $settings,
        private readonly ActivityLogService $activity,
        private readonly CurrencyFormatter $money,
    ) {}

    /** @param array<string, mixed> $data */
    public function update(array $data, User $actor): void
    {
        $keys = [
            'clinic_name', 'clinic_address', 'clinic_phone', 'pdf_header', 'pdf_footer',
            'currency_code', 'locale', 'timezone', 'date_format',
            'patient_auto_approve', 'cancellation_cutoff_hours', 'ai_enabled', 'ai_model',
        ];

        DB::transaction(function () use ($data, $keys, $actor): void {
            foreach ($keys as $key) {
                if (! array_key_exists($key, $data)) {
                    continue;
                }

                $value = $data[$key];
                $value = match ($key) {
                    'patient_auto_approve', 'ai_enabled' => (bool) $value,
                    'cancellation_cutoff_hours' => (int) $value,
                    'currency_code' => strtoupper((string) $value),
                    default => $value,
                };

                $this->settings->set($key, $value, $actor);
            }

            $this->activity->record(ActivityActions::SETTINGS_UPDATED, null, [
                'fields' => array_values(array_intersect($keys, array_keys($data))),
            ], $actor);
        });
    }

    public function updateRevenue(float $percentage, string $effectiveFrom, string $note, User $actor): RevenueConfig
    {
        $old = $this->settings->revenuePercentage();

        return DB::transaction(function () use ($percentage, $effectiveFrom, $note, $old, $actor): RevenueConfig {
            $config = RevenueConfig::query()->create([
                'percentage' => $percentage,
                'effective_from' => $effectiveFrom,
                'note' => $note !== '' ? $note : null,
                'created_by' => $actor->id,
            ]);

            $this->activity->record(ActivityActions::REVENUE_CONFIGURED, $config, [
                'old_percentage' => $old,
                'new_percentage' => $percentage,
                'effective_from' => $effectiveFrom,
                'note' => $note !== '' ? $note : null,
            ], $actor);

            return $config;
        });
    }

    /** @return array<string, mixed> */
    public function impactPreview(int $days = 30): array
    {
        $from = now()->subDays($days)->startOfDay();

        $paid = (float) \App\Models\Invoice::query()
            ->where('payment_status', 'paid')
            ->whereBetween('paid_at', [$from, now()->endOfDay()])
            ->sum('net_total');

        $current = $this->settings->revenuePercentage();
        $commission = round($paid * ($current / 100), 2);

        return [
            'paidFormatted' => $this->money->format($paid),
            'commissionFormatted' => $this->money->format($commission),
            'percentage' => $current,
            'configured' => $this->settings->revenueConfigured(),
            'invoiceCount' => \App\Models\Invoice::query()
                ->where('payment_status', 'paid')
                ->whereBetween('paid_at', [$from, now()->endOfDay()])
                ->count(),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function revenueHistory(): array
    {
        return RevenueConfig::query()
            ->with('createdBy:id,name')
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->limit(12)
            ->get()
            ->map(fn (RevenueConfig $config): array => [
                'id' => $config->id,
                'percentage' => (float) $config->percentage,
                'effectiveFrom' => $config->effective_from?->toDateString(),
                'note' => $config->note,
                'createdBy' => $config->createdBy?->name,
                'createdAt' => $config->created_at?->toIso8601String(),
            ])
            ->all();
    }

    /** @return array<int, array<string, mixed>> */
    public function settingRows(): array
    {
        return \App\Models\SystemSetting::query()
            ->orderBy('group')
            ->orderBy('key')
            ->get()
            ->filter(fn (\App\Models\SystemSetting $setting): bool => ! in_array($setting->key, ['admin_idle_minutes'], true))
            ->map(fn (\App\Models\SystemSetting $setting): array => [
                'key' => $setting->key,
                'label' => $setting->label,
                'value' => $setting->value,
                'group' => $setting->group,
                'type' => $setting->type,
                'updatedBy' => $setting->updatedBy?->name,
                'updatedAt' => $setting->updated_at?->toIso8601String(),
            ])
            ->values()
            ->all();
    }

}