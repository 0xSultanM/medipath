<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminSettingsService;
use App\Services\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Global settings + effective-dated revenue percentage (admin/12, Sprints 3.3/3.9). */
class SettingsController extends Controller
{
    public function __construct(
        private readonly SettingsService $settings,
        private readonly AdminSettingsService $adminSettings,
    ) {}

    public function index(\App\Services\CurrencyFormatter $money): Response
    {
        return Inertia::render('Admin/Settings', [
            'settings' => [
                'clinicName' => $this->settings->clinicName(),
                'clinicAddress' => (string) $this->settings->get('clinic_address', ''),
                'clinicPhone' => (string) $this->settings->get('clinic_phone', ''),
                'pdfHeader' => (string) $this->settings->get('pdf_header', ''),
                'pdfFooter' => (string) $this->settings->get('pdf_footer', ''),
                'currencyCode' => $this->settings->currencyCode(),
                'locale' => $this->settings->locale(),
                'timezone' => $this->settings->timezone(),
                'dateFormat' => $this->settings->dateFormat(),
                'patientAutoApprove' => $this->settings->patientAutoApprove(),
                'cancellationCutoffHours' => $this->settings->cancellationCutoffHours(),
                'aiEnabled' => (bool) $this->settings->get('ai_enabled', false),
                'aiModel' => (string) $this->settings->get('ai_model', 'fallback'),
                'revenuePercentage' => $this->settings->revenuePercentage(),
                'revenueConfigured' => $this->settings->revenueConfigured(),
            ],
            'preview' => $this->adminSettings->impactPreview(30),
            'revenueHistory' => $this->adminSettings->revenueHistory(),
            'sampleMoney' => $money->format(1500),
            'settingRows' => $this->adminSettings->settingRows(),
            'options' => [
                'locales' => config('medipath.locale.supported'),
                'timezones' => ['Asia/Dhaka', 'UTC', 'Asia/Kolkata', 'Asia/Dubai', 'Europe/London', 'America/New_York'],
                'dateFormats' => array_keys(config('medipath.locale.date_formats')),
                'currencies' => array_keys(config('medipath.currency.symbol_locales')),
            ],
            'lastKnownAt' => now()->toIso8601String(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->merge(['currency_code' => strtoupper((string) $request->input('currency_code'))]);

        $validated = $request->validate([
            'clinic_name' => ['required', 'string', 'min:2', 'max:160'],
            'clinic_address' => ['nullable', 'string', 'max:255'],
            'clinic_phone' => ['nullable', 'string', 'max:32'],
            'pdf_header' => ['nullable', 'string', 'max:255'],
            'pdf_footer' => ['nullable', 'string', 'max:255'],
            'currency_code' => ['required', 'string', 'size:3', Rule::in(array_keys(config('medipath.currency.symbol_locales')))],
            'locale' => ['required', Rule::in(config('medipath.locale.supported'))],
            'timezone' => ['required', 'timezone:all'],
            'date_format' => ['required', Rule::in(array_keys(config('medipath.locale.date_formats')))],
            'patient_auto_approve' => ['sometimes', 'boolean'],
            'cancellation_cutoff_hours' => ['required', 'integer', 'min:0', 'max:72'],
            'ai_enabled' => ['sometimes', 'boolean'],
            'ai_model' => ['nullable', 'string', 'max:80'],
            'revenue_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'revenue_effective_from' => ['nullable', 'date', 'after_or_equal:today'],
            'revenue_note' => ['nullable', 'string', 'max:500'],
        ]);

        $this->adminSettings->update([
            'clinic_name' => $validated['clinic_name'],
            'clinic_address' => $validated['clinic_address'] ?? '',
            'clinic_phone' => $validated['clinic_phone'] ?? '',
            'pdf_header' => $validated['pdf_header'] ?? '',
            'pdf_footer' => $validated['pdf_footer'] ?? '',
            'currency_code' => $validated['currency_code'],
            'locale' => $validated['locale'],
            'timezone' => $validated['timezone'],
            'date_format' => $validated['date_format'],
            'patient_auto_approve' => (bool) ($validated['patient_auto_approve'] ?? false),
            'cancellation_cutoff_hours' => (int) $validated['cancellation_cutoff_hours'],
            'ai_enabled' => (bool) ($validated['ai_enabled'] ?? false),
            'ai_model' => $validated['ai_model'] ?? 'fallback',
        ], $request->user());

        if (array_key_exists('revenue_percentage', $validated) && $validated['revenue_percentage'] !== null) {
            $this->adminSettings->updateRevenue(
                (float) $validated['revenue_percentage'],
                (string) ($validated['revenue_effective_from'] ?? now()->toDateString()),
                (string) ($validated['revenue_note'] ?? ''),
                $request->user(),
            );
        }

        return back()->with('success', 'Settings saved and broadcast. Revenue changes apply only from their effective date.');
    }
}