<?php
if (!function_exists('zen_my_subscriptions_debug_enabled')) {
    function zen_my_subscriptions_debug_enabled()
    {
        if (defined('MY_SUBSCRIPTIONS_DEBUG')) {
            return (bool) MY_SUBSCRIPTIONS_DEBUG;
        }

        static $cachedValue = null;
        if ($cachedValue === null) {
            $cachedValue = false;
            $value = null;

            if (function_exists('zen_get_configuration_key_value')) {
                $value = zen_get_configuration_key_value('MY_SUBSCRIPTIONS_DEBUG');
            } else {
                global $db;
                if (isset($db) && is_object($db) && method_exists($db, 'Execute')) {
                    $result = $db->Execute(
                        "SELECT configuration_value"
                        . " FROM " . TABLE_CONFIGURATION
                        . " WHERE configuration_key = 'MY_SUBSCRIPTIONS_DEBUG'"
                        . " LIMIT 1"
                    );
                    if ($result && !$result->EOF) {
                        $value = $result->fields['configuration_value'];
                    }
                }
            }

            if ($value !== null) {
                $normalized = strtolower((string) $value);
                $cachedValue = in_array($normalized, array('true', '1', 'on', 'yes'), true);
            }
        }

        return $cachedValue;
    }
}

if (!function_exists('zen_my_subscriptions_debug_mask_string')) {
    function zen_my_subscriptions_debug_mask_string($value, $maskChar = '*', $visible = 4)
    {
        $value = (string) $value;
        $length = strlen($value);
        if ($length <= $visible) {
            return str_repeat($maskChar, $length);
        }
        $visible = min($visible, $length);
        return str_repeat($maskChar, $length - $visible) . substr($value, -$visible);
    }
}

if (!function_exists('zen_my_subscriptions_debug_scrub_value')) {
    function zen_my_subscriptions_debug_scrub_value($value, $keyPath = '')
    {
        if (is_array($value)) {
            $scrubbed = array();
            foreach ($value as $key => $item) {
                $path = $keyPath === '' ? (string) $key : $keyPath . '.' . $key;
                $scrubbed[$key] = zen_my_subscriptions_debug_scrub_value($item, $path);
            }
            return $scrubbed;
        }

        if ($value instanceof JsonSerializable) {
            $value = $value->jsonSerialize();
            return zen_my_subscriptions_debug_scrub_value($value, $keyPath);
        }

        if (is_object($value)) {
            $value = get_object_vars($value);
            return zen_my_subscriptions_debug_scrub_value($value, $keyPath);
        }

        if (!is_scalar($value)) {
            return $value;
        }

        $stringValue = (string) $value;
        $lowerKeyPath = strtolower($keyPath);

        if ($stringValue === '') {
            return $stringValue;
        }

        $redactKeys = array('email', 'card', 'token', 'account', 'acct', 'cvv', 'number');
        foreach ($redactKeys as $redactKey) {
            if ($redactKey !== '' && strpos($lowerKeyPath, $redactKey) !== false) {
                if ($redactKey === 'email') {
                    return '[redacted email]';
                }
                if ($redactKey === 'token') {
                    return '[redacted token]';
                }
                return zen_my_subscriptions_debug_mask_string($stringValue);
            }
        }

        if (filter_var($stringValue, FILTER_VALIDATE_EMAIL)) {
            return '[redacted email]';
        }

        if (preg_match('/\d{6,}/', preg_replace('/\D/', '', $stringValue))) {
            return zen_my_subscriptions_debug_mask_string($stringValue);
        }

        return $stringValue;
    }
}

if (!function_exists('zen_my_subscriptions_debug')) {
    function zen_my_subscriptions_debug($label, array $context = array())
    {
        if (!zen_my_subscriptions_debug_enabled()) {
            return;
        }

        $timestamp = date('Y-m-d H:i:s');
        $scrubbedContext = zen_my_subscriptions_debug_scrub_value($context);
        $encodedContext = '';
        if (!empty($scrubbedContext)) {
            $encodedContext = json_encode($scrubbedContext, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        $line = '[' . $timestamp . '] ' . $label;
        if ($encodedContext !== '' && $encodedContext !== '[]' && $encodedContext !== '{}') {
            $line .= ' ' . $encodedContext;
        }
        $line .= PHP_EOL;

        if (defined('MY_SUBSCRIPTIONS_DEBUG_LOG')) {
            $logFile = MY_SUBSCRIPTIONS_DEBUG_LOG;
        } elseif (defined('DIR_FS_LOGS')) {
            $logFile = rtrim(DIR_FS_LOGS, '/\\') . '/my_subscriptions_debug.log';
        } else {
            $logFile = DIR_FS_CATALOG . 'includes/modules/pages/my_subscriptions/my_subscriptions_debug.log';
        }

        $directory = dirname($logFile);
        if (!is_dir($directory)) {
            @mkdir($directory, 0775, true);
        }

        if (!@error_log($line, 3, $logFile)) {
            error_log($line);
        }
    }
}
