<?php
/**
 * Lightweight compatibility shim for Zen Cart's currencies class.
 *
 * Provides the minimal surface area required by the PayPal REST webhook
 * bootstrap so that execution can continue in environments where the full
 * storefront stack is unavailable.
 */

if (class_exists('currencies')) {
    return;
}

class currencies
{
    /**
     * Registry of currency codes mapped to their conversion rates.
     *
     * @var array<string, array{value: float}>
     */
    /** @var array */
    protected $currencies = [];
    public function __construct(array $currencies = [])
    {
        foreach ($currencies as $code => $currency) {
            $rate = is_array($currency) ? ($currency['value'] ?? 1.0) : (float) $currency;
            $this->set($code, (float) $rate);
        }

        $this->ensureCurrencyDefined(defined('DEFAULT_CURRENCY') ? (string) DEFAULT_CURRENCY : null);
        $this->ensureCurrencyDefined(defined('MODULE_PAYMENT_PAYPALR_CURRENCY_FALLBACK') ? (string) MODULE_PAYMENT_PAYPALR_CURRENCY_FALLBACK : null);
    }

    /**
     * Store a conversion rate for the supplied currency code.
     */
    public function set(string $code, float $value): void
    {
        $code = strtoupper($code);
        if ($code === '') {
            return;
        }

        $this->currencies[$code] = ['value' => $value === 0.0 ? 1.0 : $value];
    }

    /**
     * Determine whether a currency has been configured.
     */
    public function is_set(string $code): bool
    {
        $code = strtoupper($code);
        if ($code === '') {
            return false;
        }

        $this->ensureCurrencyDefined($code);

        return isset($this->currencies[$code]);
    }

    /**
     * Convert a value into the requested currency.
     */
    public function rateAdjusted($value, bool $use_defaults = true, string $currency_code = ''): float
    {
        $value = (float) $value;
        $currency_code = strtoupper($currency_code);

        if ($currency_code === '' && defined('DEFAULT_CURRENCY')) {
            $currency_code = (string) strtoupper((string) DEFAULT_CURRENCY);
        }

        if ($currency_code !== '' && $this->is_set($currency_code)) {
            $rate = (float) ($this->currencies[$currency_code]['value'] ?? 1.0);
            return $value * ($rate === 0.0 ? 1.0 : $rate);
        }

        if ($use_defaults && defined('MODULE_PAYMENT_PAYPALR_CURRENCY_FALLBACK')) {
            $fallback = strtoupper((string) MODULE_PAYMENT_PAYPALR_CURRENCY_FALLBACK);
            if ($this->is_set($fallback)) {
                $rate = (float) ($this->currencies[$fallback]['value'] ?? 1.0);
                return $value * ($rate === 0.0 ? 1.0 : $rate);
            }
        }

        return $value;
    }

    /**
     * Ensure that the specified currency exists in the registry.
     */
    protected function ensureCurrencyDefined(?string $code): void
    {
        if ($code === null) {
            return;
        }

        $code = strtoupper($code);
        if ($code === '') {
            return;
        }

        $this->currencies += [$code => ['value' => $this->currencies[$code]['value'] ?? 1.0]];
    }
}
