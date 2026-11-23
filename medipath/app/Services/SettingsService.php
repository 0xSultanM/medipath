<?php

namespace App\Services;

use App\Events\SettingsChanged;
use App\Models\RevenueConfig;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Collection;

/**
 * Single source of truth for global settings (PLAN Sprint 0.3/0.4, G1/G7).
 *
 * Values are cached (Redis in production) and the cache is invalidated +
 * broadcast whenever an Admin updates a setting. Revenue % is never defaulted
 * to a magic 10 — it is read from the effective-dated revenue_configs row.
 */
class SettingsService
{
    private const CACHE_KEY = 'medipath.system_settings.v1';

    private const PUBLIC_CACHE_KEY = 'medipath.system_settings.public.v1';

    public function __construct(private readonly CacheRepository $cache) {}

    /** @return Collection<string, mixed> */
    public function all(): Collection
    {
        return $this->cache->remember(self::CACHE_KEY, now()->addMinutes(30), function (): Collection {
            return SystemSetting::query()->get()
                ->mapWithKeys(fn (SystemSetting $setting) => [$setting->key => $setting->value]);
        });
    }

    /** @return array<string, mixed> */
    public function publicSettings(): array
    {
        return $this->cache->remember(self::PUBLIC_CACHE_KEY, now()->addMinutes(30), function (): array {
            return SystemSetting::query()->where('is_public', true)->get()
                ->mapWithKeys(fn (SystemSetting $setting) => [$setting->key => $setting->value])
                ->all();
        });
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $settings = $this->all();

        return $settings->has($key) ? $settings->get($key) : $default;
    }

    public function set(string $key, mixed $value, ?User $actor = null): SystemSetting
    {
        $setting = SystemSetting::query()->firstOrNew(['key' => $key]);

        if (! $setting->exists) {
            $setting->type = match (true) {
                is_bool($value) => 'bool',
                is_int($value) => 'int',
                is_float($value) => 'decimal',
                is_array($value) => 'json',
                default => 'string',
            };
            $setting->group = 'general';
            $setting->label = str($key)->replace(['.', '_'], ' ')->headline()->toString();
        }

        $setting->value = $value;
        $setting->updated_by = $actor?->id;
        $setting->save();

        $this->flush();

        event(new SettingsChanged($key, $value, $actor?->id));

        return $setting;
    }

    public function flush(): void
    {
        $this->cache->forget(self::CACHE_KEY);
        $this->cache->forget(self::PUBLIC_CACHE_KEY);
    }

    // ------------------------------------------------------------- typed reads
    public function currencyCode(): string
    {
        return (string) $this->get('currency_code', config('medipath.currency.code'));
    }

    public function currencySymbol(): string
    {
        return app(CurrencyFormatter::class)->symbol($this->currencyCode());
    }

    public function locale(): string
    {
        return (string) $this->get('locale', config('medipath.locale.default'));
    }

    public function timezone(): string
    {
        return (string) $this->get('timezone', config('medipath.locale.timezone'));
    }

    public function dateFormat(): string
    {
        return (string) $this->get('date_format', config('medipath.locale.date_format'));
    }

    public function clinicName(): string
    {
        return (string) $this->get('clinic_name', 'MediPath Clinic');
    }

    public function patientAutoApprove(): bool
    {
        return (bool) $this->get('patient_auto_approve', true);
    }

    public function cancellationCutoffHours(): int
    {
        return (int) $this->get('cancellation_cutoff_hours', 6);
    }

    public function adminIdleMinutes(): int
    {
        return max(5, (int) $this->get('admin_idle_minutes', config('medipath.security.admin_idle_minutes', 15)));
    }

    public function themeDefault(): string
    {
        $theme = (string) $this->get('theme_default', 'system');

        return in_array($theme, ['light', 'dark', 'system'], true) ? $theme : 'system';
    }

    /** Revenue % effective today. 0.0 when unconfigured — never a silent 10. */
    public function revenuePercentage(?string $on = null): float
    {
        $config = RevenueConfig::query()->effective($on)->first();

        return $config ? (float) $config->percentage : 0.0;
    }

    public function revenueConfigured(): bool
    {
        return RevenueConfig::query()->effective()->exists();
    }

    /** Snapshot handed to Inertia on every request. */
    public function toSharedArray(): array
    {
        return [
            'currencyCode' => $this->currencyCode(),
            'currencySymbol' => $this->currencySymbol(),
            'locale' => $this->locale(),
            'timezone' => $this->timezone(),
            'dateFormat' => $this->dateFormat(),
            'clinicName' => $this->clinicName(),
            'patientAutoApprove' => $this->patientAutoApprove(),
            'cancellationCutoffHours' => $this->cancellationCutoffHours(),
            'revenuePercentage' => $this->revenuePercentage(),
            'revenueConfigured' => $this->revenueConfigured(),
            'themeDefault' => $this->themeDefault(),
        ];
    }
}
