<?php

namespace App\Services;

/**
 * The ONLY place money becomes a string (G1).
 *
 * - Symbol comes from the active system_settings.currency_code via intl.
 * - No component, API, PDF or CSV may format or prefix money itself.
 * - Tabular numerals are applied in CSS (`.money`), not here.
 * - Negatives are rendered in parentheses per DESIGN-RATIONALE §17.
 */
class CurrencyFormatter
{
    public function __construct(private readonly SettingsService $settings) {}

    public function format(float|int|string|null $amount, ?string $currencyCode = null): string
    {
        $currency = $currencyCode ?: $this->settings->currencyCode();
        $value = (float) ($amount ?? 0);
        $negative = $value < 0;

        $number = $this->decimalFormatter($this->settings->locale())->format(abs($value));
        $formatted = $this->symbol($currency).' '.$number;

        return $negative ? '('.$formatted.')' : $formatted;
    }

    /** Symbol for the given ISO code. Resolved by ICU; code fallback. */
    public function symbol(?string $currencyCode = null): string
    {
        $currency = strtoupper($currencyCode ?: $this->settings->currencyCode());

        if (class_exists(\NumberFormatter::class)) {
            $locale = config("medipath.currency.symbol_locales.{$currency}") ?: $this->settings->locale();

            $formatter = new \NumberFormatter($locale, \NumberFormatter::CURRENCY);
            $formatter->setTextAttribute(\NumberFormatter::CURRENCY_CODE, $currency);
            $symbol = $formatter->getSymbol(\NumberFormatter::CURRENCY_SYMBOL);

            if (is_string($symbol) && $symbol !== '' && $symbol !== $currency) {
                return $symbol;
            }
        }

        // Deterministic fallback: ISO code + space (still never a hardcoded glyph).
        return $currency;
    }

    /** Compact display for KPI cards: "৳ 1.19M". */
    public function formatCompact(float|int|string|null $amount, ?string $currencyCode = null): string
    {
        $currency = $currencyCode ?: $this->settings->currencyCode();
        $value = (float) ($amount ?? 0);
        $abs = abs($value);

        $compact = match (true) {
            $abs >= 1_000_000_000 => $this->trim($abs / 1_000_000_000).'B',
            $abs >= 1_000_000 => $this->trim($abs / 1_000_000).'M',
            $abs >= 1_000 => $this->trim($abs / 1_000).'K',
            default => $this->decimalFormatter($this->settings->locale())->format($abs),
        };

        $formatted = $this->symbol($currency).' '.$compact;

        return $value < 0 ? '('.$formatted.')' : $formatted;
    }

    /** Raw numeric string for CSV cells (no symbol) — still locale-safe. */
    public function plain(float|int|string|null $amount): string
    {
        return $this->decimalFormatter($this->settings->locale(), 2)
            ->format((float) ($amount ?? 0));
    }

    private function decimalFormatter(string $locale, int $digits = 2): \NumberFormatter
    {
        $formatter = new \NumberFormatter($locale, \NumberFormatter::DECIMAL);
        $formatter->setAttribute(\NumberFormatter::MIN_FRACTION_DIGITS, $digits);
        $formatter->setAttribute(\NumberFormatter::MAX_FRACTION_DIGITS, $digits);
        $formatter->setAttribute(\NumberFormatter::GROUPING_USED, 1);

        return $formatter;
    }

    private function trim(float $value): string
    {
        $formatted = number_format($value, 2, '.', '');

        return rtrim(rtrim($formatted, '0'), '.');
    }
}
