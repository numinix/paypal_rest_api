<?php
/**
 * A token-caching class for the PayPalRestful (paypalr) Payment Module
 *
 * @copyright Copyright 2023 Zen Cart Development Team
 * @license https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 * @version $Id: lat9 2023 Nov 16 Modified in v2.0.0 $
 */

namespace PayPalRestful\Api\Data;

use PayPalRestful\Common\ErrorInfo;

class Amount extends ErrorInfo
{
    protected const ERR_NAME_INVALID_CURRENCY = 'invalid_currency';

    protected $amount = [
        'currency_code' => '',
        'value' => '',
    ];
    protected static $defaultCurrency = '';
    protected static $supportedCurrencyCodes = [
        'AUD',
        'BRL',
        'CAD', 'CHF', 'CNY', 'CZK',
        'DKK',
        'EUR',
        'GBP',
        'HKD', 'HUF',
        'ILS',
        'JPY',
        'MYR', 'MXN',
        'TWD',
        'NZD', 'NOK',
        'PHP', 'PLN',
        'RUB',
        'SGD', 'SEK',
        'THB',
        'USD',
    ];

    // -----
    // An alias for setDefaultCurrency.
    //
    public function __construct(string $default_currency_code = '')
    {
        if ($default_currency_code !== '') {
            $this->setDefaultCurrency($default_currency_code);
        }
    }

    public function getSupportedCurrencyCodes(): array
    {
        return self::$supportedCurrencyCodes;
    }

    public function get(): array
    {
        return $this->amount;
    }

    public function setDefaultCurrency(string $currency_code): bool
    {
        if (!in_array($currency_code, $this->supportedCurrencyCodes)) {
            return false;
        }
        self::$defaultCurrencyCode = $currency_code;
        return true;
    }

    public function getDefaultCurrency(): string
    {
        return self::$defaultCurrencyCode;
    }

    public function setValue(string $value)
    {
        $this->amount['value'] = $value;
    }
}
