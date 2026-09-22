<?php
/**
 * A collection of 'helper' methods for the PayPalAdvancedCheckout (paypalac) Payment Module
 *
 * @copyright Copyright 2023-2024 Zen Cart Development Team
 * @license https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 *
 * Last updated: v1.0.3
 */

namespace PayPalAdvancedCheckout\Common;

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

    public static function convertPayPalDatePay2Db(?string $paypal_date): ?string
    {
        if ($paypal_date === null || $paypal_date === '') {
            return null;
        }
        if (!function_exists('convertToLocalTimeZone')) {
            return self::convertToLocalTimeZone(trim(preg_replace('/[^0-9-:]/', ' ', $paypal_date)));
        } else {
            return convertToLocalTimeZone(trim(preg_replace('/[^0-9-:]/', ' ', $paypal_date)));
        }
    }

    // -----
    // Required for zc158a; function was not defined until zc200!
    //
    protected static function convertToLocalTimeZone(string $dateTime, string $fromTz = 'UTC', string $outputFormat = 'Y-m-d H:i:s'): string
    {
        $localDateTime = new \DateTime($dateTime, new \DateTimeZone($fromTz));
        $localDateTime->setTimezone((new \DateTime)->getTimezone());
        return $localDateTime->format($outputFormat);
    }

    public static function getDaysTo(string $future_date): string
    {
        return (string)ceil((strtotime($future_date) - time()) / 86400);
    }

    public static function getDaysFrom(string $past_date): string
    {
        return (string)ceil((time() - strtotime($past_date)) / 86400);
    }

    public static function getCustomerNameSuffix(): string
    {
        $substr_function = (function_exists('mb_substr')) ? 'mb_substr' : 'substr';
        $log_suffix = $substr_function($_SESSION['customer_first_name'] ?? 'na', 0, 3) . $substr_function($_SESSION['customer_last_name'] ?? 'na', 0, 3);
        if (function_exists('mb_ereg_replace')) {
            return mb_ereg_replace('[^a-zA-Z0-9]', '_', $log_suffix);
        }
        return preg_replace('/[^a-zA-Z0-9]/', '_', $log_suffix);
    }

    /**
     * Coerce a string to valid UTF-8 so json_encode() cannot fail and produce an empty PayPal POST body.
     * Personalisation text / cart attributes sometimes contain invalid byte sequences that MySQL
     * later "cleans" on insert, which is why order rows can encode while the live cart request cannot.
     */
    public static function toUtf8($value): string
    {
        if ($value === null) {
            return '';
        }
        if (!is_string($value)) {
            if (is_scalar($value)) {
                $value = (string)$value;
            } else {
                return '';
            }
        }
        if ($value === '') {
            return '';
        }

        if (function_exists('mb_check_encoding') && mb_check_encoding($value, 'UTF-8')) {
            return $value;
        }

        if (function_exists('iconv')) {
            $converted = @iconv('UTF-8', 'UTF-8//IGNORE', $value);
            if ($converted !== false && $converted !== '') {
                return $converted;
            }
            foreach (['Windows-1252', 'ISO-8859-1'] as $from) {
                $converted = @iconv($from, 'UTF-8//IGNORE', $value);
                if ($converted !== false && $converted !== '') {
                    return $converted;
                }
            }
        }

        if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
            $encoded = json_encode($value, JSON_INVALID_UTF8_SUBSTITUTE);
            if ($encoded !== false) {
                $decoded = json_decode($encoded, true);
                if (is_string($decoded)) {
                    return $decoded;
                }
            }
        }

        // Last resort: drop non-ASCII bytes rather than send an unencodable payload.
        return preg_replace('/[^\x09\x0A\x0D\x20-\x7E]/', '?', $value) ?? '';
    }

    /**
     * Truncate to at most $maxChars Unicode characters without splitting a multibyte sequence.
     * PayPal item name max length is 127 characters; byte-oriented substr() can create invalid UTF-8.
     */
    public static function truncateUtf8(string $value, int $maxChars): string
    {
        $value = self::toUtf8($value);
        if ($maxChars < 1) {
            return '';
        }
        if (function_exists('mb_substr')) {
            return mb_substr($value, 0, $maxChars, 'UTF-8');
        }
        return substr($value, 0, $maxChars);
    }

    /**
     * Recursively ensure all strings in a PayPal request payload are valid UTF-8.
     *
     * @param mixed $value
     * @return mixed
     */
    public static function sanitizeForJson($value)
    {
        if (is_string($value)) {
            return self::toUtf8($value);
        }
        if (is_array($value)) {
            $clean = [];
            foreach ($value as $key => $child) {
                $cleanKey = is_string($key) ? self::toUtf8($key) : $key;
                $clean[$cleanKey] = self::sanitizeForJson($child);
            }
            return $clean;
        }
        if (is_object($value)) {
            // Preserve empty stdClass objects used as PayPal placeholders (e.g. google_pay).
            if ($value instanceof \stdClass) {
                $vars = get_object_vars($value);
                if ($vars === []) {
                    return $value;
                }
                $clean = new \stdClass();
                foreach ($vars as $key => $child) {
                    $clean->{self::toUtf8((string)$key)} = self::sanitizeForJson($child);
                }
                return $clean;
            }
            return self::sanitizeForJson((array)$value);
        }
        if (is_float($value) && (!is_finite($value))) {
            return 0.0;
        }
        return $value;
    }

    /**
     * JSON-encode a PayPal API payload. Never returns false for UTF-8 issues.
     * Returns null only if encoding still fails after sanitization (caller should abort the request).
     */
    public static function jsonEncodePayload($value): ?string
    {
        $value = self::sanitizeForJson($value);
        $flags = 0;
        if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
            $flags |= JSON_INVALID_UTF8_SUBSTITUTE;
        }
        if (defined('JSON_PARTIAL_OUTPUT_ON_ERROR')) {
            $flags |= JSON_PARTIAL_OUTPUT_ON_ERROR;
        }
        $encoded = json_encode($value, $flags);
        return ($encoded === false) ? null : $encoded;
    }
}
