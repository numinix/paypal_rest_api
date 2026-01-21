<?php
if (!function_exists('oprc_initialize_shipping_quotes_cache')) {
    function oprc_initialize_shipping_quotes_cache() {
        if (!isset($_SESSION['shipping_quotes']) || !is_array($_SESSION['shipping_quotes'])) {
            $_SESSION['shipping_quotes'] = [];
        }
        if (!isset($_SESSION['shipping_quotes']['hashes']) || !is_array($_SESSION['shipping_quotes']['hashes'])) {
            $_SESSION['shipping_quotes']['hashes'] = [];
        }
    }
}

if (!function_exists('oprc_normalize_shipping_quotes')) {
    function oprc_normalize_shipping_quotes($quotes) {
        if (!is_array($quotes)) {
            return $quotes;
        }

        foreach ($quotes as &$quote_entry) {
            if (!is_array($quote_entry) || !isset($quote_entry['methods']) || !is_array($quote_entry['methods'])) {
                continue;
            }

            $unique_methods = [];
            $normalized_methods = [];

            foreach ($quote_entry['methods'] as $method) {
                if (!is_array($method)) {
                    $normalized_methods[] = $method;
                    continue;
                }

                $method_id = isset($method['id']) ? $method['id'] : null;
                if ($method_id === null) {
                    $normalized_methods[] = $method;
                    continue;
                }

                if (isset($unique_methods[$method_id])) {
                    continue;
                }

                $unique_methods[$method_id] = true;
                $normalized_methods[] = $method;
            }

            $quote_entry['methods'] = array_values($normalized_methods);
        }

        unset($quote_entry);

        return array_values($quotes);
    }
}

if (!function_exists('oprc_get_shipping_cache_module_classes')) {
    function oprc_get_shipping_cache_module_classes($shipping_modules, $module = '') {
        $modules = [];

        if (!empty($module)) {
            $modules[] = $module;
        } elseif (method_exists($shipping_modules, 'getInitializedModules')) {
            $modules = $shipping_modules->getInitializedModules();
        } elseif (isset($shipping_modules->modules) && is_array($shipping_modules->modules)) {
            foreach ($shipping_modules->modules as $value) {
                if (!is_string($value)) {
                    continue;
                }
                if (strpos($value, '.') !== false) {
                    $modules[] = substr($value, 0, strrpos($value, '.'));
                } else {
                    $modules[] = $value;
                }
            }
        }

        $modules = array_unique(array_filter($modules));

        $enabled_modules = [];
        foreach ($modules as $module_class) {
            if (!isset($GLOBALS[$module_class]) || !is_object($GLOBALS[$module_class])) {
                continue;
            }
            if (property_exists($GLOBALS[$module_class], 'enabled') && $GLOBALS[$module_class]->enabled === false) {
                continue;
            }
            $enabled_modules[] = $module_class;
        }

        return $enabled_modules;
    }
}

if (!function_exists('oprc_calculate_shipping_cache_hash')) {
    function oprc_calculate_shipping_cache_hash($module_class, $method) {
        global $order;

        if (empty($module_class) || !isset($GLOBALS[$module_class]) || !is_object($GLOBALS[$module_class])) {
            return null;
        }

        $configs = [];
        if (method_exists($GLOBALS[$module_class], 'keys')) {
            $keys = $GLOBALS[$module_class]->keys();
            if (is_array($keys)) {
                foreach ($keys as $key) {
                    if (defined($key)) {
                        $configs[$key] = constant($key);
                    }
                }
            }
        }

        $hash_source = $module_class . $method . json_encode($order) . json_encode($configs);

        return md5($hash_source);
    }
}

if (!function_exists('oprc_get_shipping_quotes')) {
    function oprc_get_shipping_quotes($shipping_modules, $method = '', $module = '', $calc_boxes_weight_tare = true, $insurance_exclusions = []) {
        global $uninsurable_value;

        if (!is_object($shipping_modules) || !method_exists($shipping_modules, 'quote')) {
            return [];
        }

        oprc_initialize_shipping_quotes_cache();

        $module_classes = oprc_get_shipping_cache_module_classes($shipping_modules, $module);
        if (empty($module_classes)) {
            return $shipping_modules->quote($method, $module, $calc_boxes_weight_tare, $insurance_exclusions);
        }

        $hashes = [];
        $cached_quotes = [];
        $missing_modules = [];

        foreach ($module_classes as $module_class) {
            if (isset($GLOBALS[$module_class]) && method_exists($GLOBALS[$module_class], 'update_status')) {
                $GLOBALS[$module_class]->update_status();
            }
            if (!isset($GLOBALS[$module_class]) || !is_object($GLOBALS[$module_class]) || (property_exists($GLOBALS[$module_class], 'enabled') && $GLOBALS[$module_class]->enabled === false)) {
                $missing_modules[] = $module_class;
                continue;
            }

            $hash = oprc_calculate_shipping_cache_hash($module_class, $method);
            if ($hash === null) {
                $missing_modules[] = $module_class;
                continue;
            }

            $hashes[$module_class] = $hash;
            if (array_key_exists($hash, $_SESSION['shipping_quotes']['hashes'])) {
                $cached_quotes[$module_class] = $_SESSION['shipping_quotes']['hashes'][$hash];
            } else {
                $missing_modules[] = $module_class;
            }
        }

        if (empty($missing_modules)) {
            if ($calc_boxes_weight_tare && method_exists($shipping_modules, 'calculate_boxes_weight_and_tare')) {
                $shipping_modules->calculate_boxes_weight_and_tare();
            }

            if (method_exists($shipping_modules, 'get_uninsurable_value')) {
                $uninsurable_value = $shipping_modules->get_uninsurable_value($insurance_exclusions);
            } else {
                $uninsurable_value = 0;
            }

            $quotes_array = [];
            foreach ($module_classes as $module_class) {
                $quote_entry = $cached_quotes[$module_class];
                $quotes_array[] = $quote_entry;
            }

            $quotes_array = oprc_normalize_shipping_quotes($quotes_array);

            foreach ($quotes_array as $index => $quote_entry) {
                $module_class = isset($module_classes[$index]) ? $module_classes[$index] : null;
                if ($module_class !== null && isset($GLOBALS[$module_class]) && is_object($GLOBALS[$module_class])) {
                    $GLOBALS[$module_class]->quotes = $quote_entry;
                }
            }

            if (method_exists($shipping_modules, 'notify')) {
                $shipping_modules->notify('NOTIFY_SHIPPING_MODULE_GET_ALL_QUOTES', $quotes_array, $quotes_array);
            }

            return $quotes_array;
        }

        $quotes = $shipping_modules->quote($method, $module, $calc_boxes_weight_tare, $insurance_exclusions);
        if (!is_array($quotes)) {
            return $quotes;
        }

        $quotes = oprc_normalize_shipping_quotes($quotes);

        $quotes_by_id = [];
        foreach ($quotes as $quote_entry) {
            if (is_array($quote_entry) && isset($quote_entry['id']) && $quote_entry['id'] !== '') {
                $quotes_by_id[$quote_entry['id']] = $quote_entry;
            }
        }

        foreach ($module_classes as $index => $module_class) {
            if (!isset($hashes[$module_class])) {
                $hashes[$module_class] = oprc_calculate_shipping_cache_hash($module_class, $method);
            }
            $hash = $hashes[$module_class];
            if ($hash === null) {
                continue;
            }

            $quote_entry = null;
            if (isset($quotes_by_id[$module_class])) {
                $quote_entry = $quotes_by_id[$module_class];
            } elseif (isset($quotes[$index]) && is_array($quotes[$index])) {
                $quote_entry = $quotes[$index];
            }

            if (is_array($quote_entry)) {
                $_SESSION['shipping_quotes']['hashes'][$hash] = $quote_entry;
                if (isset($GLOBALS[$module_class]) && is_object($GLOBALS[$module_class])) {
                    $GLOBALS[$module_class]->quotes = $quote_entry;
                }
            } else {
                unset($_SESSION['shipping_quotes']['hashes'][$hash]);
            }
        }

        foreach ($quotes_by_id as $module_id => $quote_entry) {
            if (in_array($module_id, $module_classes, true)) {
                continue;
            }

            $hash = oprc_calculate_shipping_cache_hash($module_id, $method);
            if ($hash === null) {
                continue;
            }

            $_SESSION['shipping_quotes']['hashes'][$hash] = $quote_entry;
            if (isset($GLOBALS[$module_id]) && is_object($GLOBALS[$module_id])) {
                $GLOBALS[$module_id]->quotes = $quote_entry;
            }
        }

        return $quotes;
    }
}

// eof
