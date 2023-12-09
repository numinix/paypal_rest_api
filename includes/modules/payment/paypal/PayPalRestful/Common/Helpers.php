<?php
/**
 * A collection of 'helper' methods for the PayPalRestful (paypalr) Payment Module
 *
 * @copyright Copyright 2023 Zen Cart Development Team
 * @license https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 * @version $Id: lat9 2023 Nov 16 Modified in v2.0.0 $
 */

namespace PayPalRestful\Common;

class Helpers
{
    public static function arrayDiffRecursive(array $array1, array $array2): array
    {
        $difference = [];
        foreach ($array1 as $key => $value) {
            if (is_array($value)) {
                if (!isset($array2[$key]) || !is_array($array2[$key])) {
                    $difference[$key] = $value;
                } else {
                    $new_diff = self::arrayDiffRecursive($value, $array2[$key]);
                    if (!empty($new_diff)) {
                        $difference[$key] = $new_diff;
                    }
                }
            } elseif (!array_key_exists($key, $array2) || $array2[$key] !== $value) {
                $difference[$key] = $value;
            }
        }
        return $difference;
    }

    public static function convertPayPalDatePay2Db(string $paypal_date): string
    {
        return convertToLocalTimeZone(trim(preg_replace('/[^0-9-:]/', ' ', $paypal_date)));
    }

    public static function getDaysTo(string $future_date): string
    {
        return (string)ceil((strtotime($future_date) - time()) / 86400);
    }

    public static function getDaysFrom(string $past_date): string
    {
        return (string)ceil((time() - strtotime($past_date)) / 86400);
    }
}
