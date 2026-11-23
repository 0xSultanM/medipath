<?php

use App\Events\SettingsChanged;
use App\Models\RevenueConfig;
use App\Models\SystemSetting;
use App\Services\CurrencyFormatter;
use App\Services\SettingsService;
use Illuminate\Support\Facades\Event;

it('formats money with the runtime symbol and tabular-friendly grouping', function () {
    $formatter = app(CurrencyFormatter::class);

    expect($formatter->format(1200.5))->toBe('৳ 1,200.50')
        ->and($formatter->format(0))->toBe('৳ 0.00')
        ->and($formatter->format(-450))->toBe('(৳ 450.00)')
        ->and($formatter->formatCompact(1_190_000))->toBe('৳ 1.19M')
        ->and($formatter->plain(1200.5))->toBe('1,200.50');
});

it('derives the symbol from currency_code rather than a UI literal', function () {
    SystemSetting::query()->create([
        'key' => 'currency_code',
        'value' => 'USD',
        'type' => 'currency',
        'group' => 'locale',
        'label' => 'Currency',
        'is_public' => true,
    ]);
    app(SettingsService::class)->flush();

    $output = app(CurrencyFormatter::class)->format(10);

    expect($output)->not->toContain('BDT')
        ->and($output)->toContain('10.00');
});

it('caches settings and invalidates on update', function () {
    $settings = app(SettingsService::class);
    $before = $settings->get('clinic_name');

    SystemSetting::query()->where('key', 'clinic_name')->update(['value' => json_encode('Changed Clinic')]);
    // A direct DB update does not flush the cache; the service still serves the cached value.
    expect($settings->get('clinic_name'))->toBe($before);

    $settings->set('clinic_name', 'Changed Clinic');

    expect($settings->get('clinic_name'))->toBe('Changed Clinic');
});

it('broadcasts a settings change event after an admin update', function () {
    Event::fake([SettingsChanged::class]);

    app(SettingsService::class)->set('currency_code', 'BDT', makeUser(['role' => 'admin']));

    Event::assertDispatched(SettingsChanged::class);
});

it('exposes revenue percentage from the effective configuration', function () {
    RevenueConfig::query()->create([
        'percentage' => 8,
        'effective_from' => now()->subYear()->toDateString(),
    ]);
    RevenueConfig::query()->create([
        'percentage' => 11,
        'effective_from' => now()->addMonth()->toDateString(),
    ]);

    $settings = app(SettingsService::class);

    expect($settings->revenuePercentage())->toBe(8.0)
        ->and($settings->revenuePercentage(now()->addMonths(2)->toDateString()))->toBe(11.0);
});

it('never invents a default revenue percentage when unconfigured', function () {
    expect(app(SettingsService::class)->revenuePercentage())->toBe(0.0);
});
