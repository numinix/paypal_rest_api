<?php
if (!function_exists('zen_paypal_subscription_profile_value')) {
    function zen_paypal_subscription_profile_value($profile, array $path)
    {
        $value = $profile;
        foreach ($path as $segment) {
            if (is_array($value) && array_key_exists($segment, $value)) {
                $value = $value[$segment];
            } else {
                return null;
            }
        }
        return $value;
    }
}

if (!function_exists('zen_paypal_subscription_profile_find')) {
    function zen_paypal_subscription_profile_find($profile, array $paths)
    {
        foreach ($paths as $path) {
            $value = zen_paypal_subscription_profile_value($profile, $path);
            if ($value !== null && $value !== '') {
                return $value;
            }
        }
        return null;
    }
}

if (!function_exists('zen_paypal_subscription_profile_date')) {
    function zen_paypal_subscription_profile_date($profile, array $paths)
    {
        $raw = zen_paypal_subscription_profile_find($profile, $paths);
        if (!is_string($raw) || strlen($raw) === 0) {
            return '';
        }
        $raw = str_replace('Z', '', $raw);
        $parts = explode('T', $raw);
        return $parts[0];
    }
}

if (!function_exists('zen_paypal_subscription_profile_amount')) {
    function zen_paypal_subscription_profile_amount($profile, array $paths)
    {
        $raw = zen_paypal_subscription_profile_find($profile, $paths);
        if (is_array($raw) && array_key_exists('value', $raw)) {
            return $raw['value'];
        }
        if (is_scalar($raw)) {
            return (string) $raw;
        }
        return '';
    }
}

if (!function_exists('zen_paypal_subscription_environment')) {
    function zen_paypal_subscription_environment()
    {
        $environment = '';

        if (defined('MODULE_PAYMENT_PAYPALR_ENVIRONMENT')) {
            $environment = MODULE_PAYMENT_PAYPALR_ENVIRONMENT;
        } elseif (defined('MODULE_PAYMENT_PAYPALR_MODE')) {
            $environment = MODULE_PAYMENT_PAYPALR_MODE;
        } elseif (defined('MODULE_PAYMENT_PAYPALWPP_SERVER')) {
            $environment = MODULE_PAYMENT_PAYPALWPP_SERVER;
        }

        $environment = strtolower(trim((string) $environment));

        if ($environment === '') {
            return 'sandbox';
        }

        return $environment;
    }
}

if (!function_exists('zen_paypal_subscription_cache_policy')) {
    function zen_paypal_subscription_cache_policy()
    {
        static $policy = null;

        if ($policy !== null) {
            return $policy;
        }

        $environment = zen_paypal_subscription_environment();

        $defaults = array(
            'ttl_seconds' => 900,
            'cleanup_seconds' => 604800,
        );

        $policies = array(
            'sandbox' => array('ttl_seconds' => 300, 'cleanup_seconds' => 172800),
            'development' => array('ttl_seconds' => 300, 'cleanup_seconds' => 172800),
            'dev' => array('ttl_seconds' => 300, 'cleanup_seconds' => 172800),
            'test' => array('ttl_seconds' => 300, 'cleanup_seconds' => 172800),
            'staging' => array('ttl_seconds' => 600, 'cleanup_seconds' => 345600),
            'qa' => array('ttl_seconds' => 600, 'cleanup_seconds' => 345600),
            'beta' => array('ttl_seconds' => 600, 'cleanup_seconds' => 345600),
            'live' => array('ttl_seconds' => 1800, 'cleanup_seconds' => 604800),
            'production' => array('ttl_seconds' => 1800, 'cleanup_seconds' => 604800),
            'prod' => array('ttl_seconds' => 1800, 'cleanup_seconds' => 604800),
        );

        if (isset($policies[$environment])) {
            $policy = array_merge($defaults, $policies[$environment]);
        } else {
            $policy = $defaults;
        }

        if (!isset($policy['ttl_seconds']) || $policy['ttl_seconds'] < 0) {
            $policy['ttl_seconds'] = 0;
        }

        if (!isset($policy['cleanup_seconds']) || $policy['cleanup_seconds'] <= 0) {
            $policy['cleanup_seconds'] = ($policy['ttl_seconds'] > 0)
                ? max($policy['ttl_seconds'] * 10, 86400)
                : 604800;
        }

        return $policy;
    }
}

if (!defined('ZEN_PAYPAL_SUBSCRIPTION_PROFILE_CACHE_TTL')) {
    $cachePolicy = zen_paypal_subscription_cache_policy();
    define('ZEN_PAYPAL_SUBSCRIPTION_PROFILE_CACHE_TTL', (int) $cachePolicy['ttl_seconds']);
}

if (!function_exists('zen_paypal_subscription_profile_cache_ttl')) {
    function zen_paypal_subscription_profile_cache_ttl()
    {
        $policy = zen_paypal_subscription_cache_policy();

        if (defined('ZEN_PAYPAL_SUBSCRIPTION_PROFILE_CACHE_TTL') && (int) ZEN_PAYPAL_SUBSCRIPTION_PROFILE_CACHE_TTL >= 0) {
            return (int) ZEN_PAYPAL_SUBSCRIPTION_PROFILE_CACHE_TTL;
        }

        return (int) $policy['ttl_seconds'];
    }
}

if (!function_exists('zen_paypal_subscription_cache_cleanup_ttl')) {
    function zen_paypal_subscription_cache_cleanup_ttl()
    {
        $policy = zen_paypal_subscription_cache_policy();
        return isset($policy['cleanup_seconds']) ? (int) $policy['cleanup_seconds'] : 604800;
    }
}

if (!function_exists('zen_paypal_subscription_cache_table_name')) {
    function zen_paypal_subscription_cache_table_name()
    {
        if (!defined('TABLE_PAYPAL_RECURRING_PROFILE_CACHE')) {
            $tableName = (defined('DB_PREFIX') ? DB_PREFIX : '') . 'paypal_recurring_profile_cache';
            define('TABLE_PAYPAL_RECURRING_PROFILE_CACHE', $tableName);
        }

        return TABLE_PAYPAL_RECURRING_PROFILE_CACHE;
    }
}

if (!function_exists('zen_paypal_subscription_refresh_queue_table_name')) {
    function zen_paypal_subscription_refresh_queue_table_name()
    {
        if (!defined('TABLE_PAYPAL_RECURRING_REFRESH_QUEUE')) {
            $tableName = (defined('DB_PREFIX') ? DB_PREFIX : '') . 'paypal_recurring_refresh_queue';
            define('TABLE_PAYPAL_RECURRING_REFRESH_QUEUE', $tableName);
        }

        return TABLE_PAYPAL_RECURRING_REFRESH_QUEUE;
    }
}

if (!function_exists('zen_paypal_subscription_refresh_queue_ensure_schema')) {
    function zen_paypal_subscription_refresh_queue_ensure_schema()
    {
        global $db;

        $tableName = zen_paypal_subscription_refresh_queue_table_name();
        $db->Execute(
            'CREATE TABLE IF NOT EXISTS ' . $tableName . ' (
                queue_id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
                customers_id INT(10) UNSIGNED NOT NULL,
                profile_id VARCHAR(64) NOT NULL,
                available_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                attempts INT(10) UNSIGNED NOT NULL DEFAULT 0,
                last_error TEXT DEFAULT NULL,
                context TEXT DEFAULT NULL,
                locked_at DATETIME DEFAULT NULL,
                locked_by VARCHAR(64) DEFAULT NULL,
                last_run_at DATETIME DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (queue_id),
                UNIQUE KEY idx_paypal_refresh_queue_unique (customers_id, profile_id),
                KEY idx_paypal_refresh_queue_available (available_at),
                KEY idx_paypal_refresh_queue_locked (locked_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8'
        );
    }
}

if (!function_exists('zen_paypal_subscription_refresh_queue_enqueue')) {
    function zen_paypal_subscription_refresh_queue_enqueue($customerId, $profileId, array $options = array())
    {
        global $db;

        $customerId = (int) $customerId;
        $profileId = trim((string) $profileId);

        if ($customerId <= 0 || $profileId === '') {
            return false;
        }

        $availableAt = isset($options['available_at']) && $options['available_at'] !== ''
            ? $options['available_at']
            : date('Y-m-d H:i:s');
        $context = array();
        if (isset($options['context']) && is_array($options['context'])) {
            $context = $options['context'];
        }

        $contextJson = '';
        if (!empty($context)) {
            $encodedContext = json_encode($context);
            if ($encodedContext !== false) {
                $contextJson = $encodedContext;
            }
        }

        $contextSql = 'NULL';
        if ($contextJson !== '') {
            $contextSql = "'" . zen_db_input($contextJson) . "'";
        }

        $tableName = zen_paypal_subscription_refresh_queue_table_name();
        $db->Execute(
            "INSERT INTO " . $tableName
            . " (customers_id, profile_id, available_at, context, created_at, updated_at) VALUES ("
            . $customerId . ", '" . zen_db_input($profileId) . "',"
            . " '" . zen_db_input($availableAt) . "',"
            . ' ' . $contextSql . ","
            . " NOW(), NOW())"
            . " ON DUPLICATE KEY UPDATE"
            . " available_at = LEAST(VALUES(available_at), available_at),"
            . " context = VALUES(context),"
            . " attempts = 0,"
            . " last_error = NULL,"
            . " locked_at = NULL,"
            . " locked_by = NULL,"
            . " updated_at = NOW();"
        );

        return true;
    }
}

if (!function_exists('zen_paypal_subscription_queue_cancel')) {
    function zen_paypal_subscription_queue_cancel($customerId, $profileId, array $options = array())
    {
        $result = zen_paypal_subscription_cancel_immediately($customerId, $profileId, $options);

        if (is_array($result)) {
            return !empty($result['success']);
        }

        return (bool) $result;
    }
}

if (!function_exists('zen_paypal_subscription_cancel_context')) {
    function zen_paypal_subscription_cancel_context($customerId, $profileId, array $subscription = array(), array $options = array())
    {
        $context = array('operation' => 'cancel');

        if (isset($options['note']) && $options['note'] !== '') {
            $context['note'] = (string) $options['note'];
        }

        if (isset($options['source']) && $options['source'] !== '') {
            $context['source'] = (string) $options['source'];
        }

        if (isset($subscription['preferred_gateway']) && $subscription['preferred_gateway'] !== '') {
            $context['preferred_gateway'] = strtolower(trim((string) $subscription['preferred_gateway']));
        }

        if (isset($subscription['profile_source']) && $subscription['profile_source'] !== '') {
            $context['profile_source'] = strtolower(trim((string) $subscription['profile_source']));
        }

        if (!isset($context['preferred_gateway'])) {
            if (isset($subscription['gateway_hint']) && $subscription['gateway_hint'] !== '') {
                $context['preferred_gateway'] = strtolower(trim((string) $subscription['gateway_hint']));
            } elseif (isset($subscription['profile_source']) && $subscription['profile_source'] !== '') {
                $context['preferred_gateway'] = strtolower(trim((string) $subscription['profile_source']));
            }
        }

        if ((!isset($context['preferred_gateway']) || $context['preferred_gateway'] === '')
            && isset($context['profile_source']) && $context['profile_source'] !== ''
        ) {
            $context['preferred_gateway'] = $context['profile_source'];
        }

        if ((!isset($context['preferred_gateway']) || $context['preferred_gateway'] === '')
            || (!isset($context['profile_source']) || $context['profile_source'] === '')) {
            $cacheRow = zen_paypal_subscription_cache_lookup($customerId, $profileId);
            if (is_array($cacheRow)) {
                if ((!isset($context['preferred_gateway']) || $context['preferred_gateway'] === '')
                    && isset($cacheRow['preferred_gateway']) && $cacheRow['preferred_gateway'] !== ''
                ) {
                    $context['preferred_gateway'] = strtolower(trim((string) $cacheRow['preferred_gateway']));
                }
                if ((!isset($context['profile_source']) || $context['profile_source'] === '')
                    && isset($cacheRow['profile_source']) && $cacheRow['profile_source'] !== ''
                ) {
                    $context['profile_source'] = strtolower(trim((string) $cacheRow['profile_source']));
                }
            }
        }

        if (isset($context['preferred_gateway'])) {
            $context['preferred_gateway'] = strtolower(trim((string) $context['preferred_gateway']));
        }

        if (isset($context['profile_source'])) {
            $context['profile_source'] = strtolower(trim((string) $context['profile_source']));
        }

        return $context;
    }
}

if (!function_exists('zen_paypal_subscription_load_profile_manager')) {
    function zen_paypal_subscription_load_profile_manager(array $options = array())
    {
        if (!class_exists('PayPalProfileManager')) {
            if (defined('DIR_FS_CATALOG') && defined('DIR_WS_CLASSES')) {
                $managerPath = DIR_FS_CATALOG . DIR_WS_CLASSES . 'paypal/PayPalProfileManager.php';
            } else {
                $managerPath = __DIR__ . '/../../../classes/paypal/PayPalProfileManager.php';
            }
            if (file_exists($managerPath)) {
                require_once $managerPath;
            }
        }

        if (class_exists('PayPalProfileManager')
            && isset($options['profile_manager'])
            && $options['profile_manager'] instanceof PayPalProfileManager
        ) {
            return $options['profile_manager'];
        }

        $savedCard = null;
        if (isset($options['saved_card_recurring']) && is_object($options['saved_card_recurring'])) {
            $savedCard = $options['saved_card_recurring'];
        } else {
            if (!class_exists('paypalSavedCardRecurring')) {
                if (defined('DIR_FS_CATALOG') && defined('DIR_WS_CLASSES')) {
                    $savedCardPath = DIR_FS_CATALOG . DIR_WS_CLASSES . 'paypalSavedCardRecurring.php';
                } else {
                    $savedCardPath = __DIR__ . '/../../../classes/paypalSavedCardRecurring.php';
                }
                if (file_exists($savedCardPath)) {
                    require_once $savedCardPath;
                }
            }
            if (class_exists('paypalSavedCardRecurring')) {
                $savedCard = new paypalSavedCardRecurring();
            }
        }

        $restClient = null;
        if ($savedCard && method_exists($savedCard, 'get_paypal_rest_client')) {
            $restClient = $savedCard->get_paypal_rest_client();
        }

        $legacyClient = null;
        if ($savedCard && method_exists($savedCard, 'get_paypal_legacy_client')) {
            $legacyClient = $savedCard->get_paypal_legacy_client();
        }

        if (class_exists('PayPalProfileManager')) {
            return PayPalProfileManager::create($restClient, $legacyClient);
        }

        return null;
    }
}



if (!function_exists('zen_paypal_subscription_cancel_immediately')) {
    function zen_paypal_subscription_cancel_immediately($customerId, $profileId, array $options = array())
    {
        global $db;

        $customerId = (int) $customerId;
        $profileId = trim((string) $profileId);

        if ($customerId <= 0 || $profileId === '') {
            return array('success' => false, 'message' => 'Invalid cancellation request.');
        }

        $subscription = array();
        if (isset($options['subscription']) && is_array($options['subscription'])) {
            $subscription = $options['subscription'];
        }

        if (!isset($subscription['profile_id']) || (string) $subscription['profile_id'] === '') {
            if (!defined('TABLE_PAYPAL_RECURRING')) {
                return array('success' => false, 'message' => 'Subscriptions table is not defined.');
            }

            $lookup = $db->Execute(
                'SELECT *'
                . '  FROM ' . TABLE_PAYPAL_RECURRING
                . ' WHERE customers_id = ' . $customerId
                . "   AND profile_id = '" . zen_db_input($profileId) . "'"
                . ' LIMIT 1;'
            );

            if ($lookup && !$lookup->EOF) {
                $subscription = $lookup->fields;
            }
        }

        if (empty($subscription)) {
            return array('success' => false, 'message' => 'Subscription could not be located.');
        }

        $subscription['profile_id'] = isset($subscription['profile_id']) ? (string) $subscription['profile_id'] : $profileId;
        $note = isset($options['note']) ? (string) $options['note'] : '';
        $context = zen_paypal_subscription_cancel_context($customerId, $subscription['profile_id'], $subscription, $options);

        $profileManager = zen_paypal_subscription_load_profile_manager($options);
        if (!class_exists('PayPalProfileManager') || !($profileManager instanceof PayPalProfileManager)) {
            return array('success' => false, 'message' => 'Unable to initialize PayPal profile manager.');
        }

        try {
            $result = $profileManager->cancelProfile($subscription, $note, $context);
        } catch (Exception $exception) {
            $result = array('success' => false, 'message' => $exception->getMessage());
        }

        if (empty($result['success'])) {
            $message = isset($result['message']) && $result['message'] !== ''
                ? (string) $result['message']
                : 'The cancellation request was declined by PayPal.';

            return array('success' => false, 'message' => $message, 'result' => $result);
        }

        if (defined('TABLE_PAYPAL_RECURRING') && isset($subscription['subscription_id'])) {
            $db->Execute(
                'UPDATE ' . TABLE_PAYPAL_RECURRING
                . "   SET status = 'Cancelled'"
                . ' WHERE subscription_id = ' . (int) $subscription['subscription_id']
                . ' LIMIT 1;'
            );
        }

        $savedCard = null;
        if (isset($options['saved_card_recurring']) && is_object($options['saved_card_recurring'])) {
            $savedCard = $options['saved_card_recurring'];
        }

        if (!$savedCard && class_exists('paypalSavedCardRecurring')) {
            $savedCard = new paypalSavedCardRecurring();
        }

        $productId = isset($subscription['products_id']) ? (int) $subscription['products_id'] : 0;
        if ($savedCard && method_exists($savedCard, 'remove_group_pricing') && $productId > 0) {
            $savedCard->remove_group_pricing($customerId, $productId);
        }

        zen_paypal_subscription_cache_invalidate($customerId, $subscription['profile_id']);

        return array('success' => true, 'message' => '', 'result' => $result);
    }
}

if (!function_exists('zen_paypal_subscription_refresh_events_table_name')) {
    function zen_paypal_subscription_refresh_events_table_name()
    {
        static $tableName = null;

        if ($tableName === null) {
            $prefix = defined('DB_PREFIX') ? DB_PREFIX : '';
            $tableName = $prefix . 'paypal_recurring_refresh_events';
        }

        return $tableName;
    }
}

if (!function_exists('zen_paypal_subscription_refresh_events_ensure_schema')) {
    function zen_paypal_subscription_refresh_events_ensure_schema()
    {
        global $db;

        static $ensured = false;

        if ($ensured) {
            return true;
        }

        $tableName = zen_paypal_subscription_refresh_events_table_name();

        $db->Execute(
            'CREATE TABLE IF NOT EXISTS ' . $tableName . ' ('
            . '  event_id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,'
            . '  customers_id INT(10) UNSIGNED NOT NULL DEFAULT 0,'
            . '  profile_id VARCHAR(64) NOT NULL,'
            . '  source VARCHAR(32) NOT NULL,'
            . '  actor_type VARCHAR(16) NOT NULL,'
            . '  actor_id INT(10) UNSIGNED DEFAULT NULL,'
            . '  context TEXT DEFAULT NULL,'
            . '  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,'
            . '  PRIMARY KEY (event_id),'
            . '  KEY idx_refresh_events_customer (customers_id),'
            . '  KEY idx_refresh_events_profile (profile_id),'
            . '  KEY idx_refresh_events_source (source),'
            . '  KEY idx_refresh_events_created (created_at)'
            . ') ENGINE=InnoDB DEFAULT CHARSET=utf8'
        );

        $ensured = true;

        return true;
    }
}

if (!function_exists('zen_paypal_subscription_refresh_events_log')) {
    function zen_paypal_subscription_refresh_events_log($customersId, $profileId, array $options = array())
    {
        global $db;

        $customersId = (int) $customersId;
        $profileId = trim((string) $profileId);

        if ($profileId === '') {
            return false;
        }

        zen_paypal_subscription_refresh_events_ensure_schema();

        $source = isset($options['source']) && is_string($options['source'])
            ? substr(strtolower(trim($options['source'])), 0, 32)
            : 'manual';
        if ($source === '') {
            $source = 'manual';
        }

        $actorType = isset($options['actor_type']) && is_string($options['actor_type'])
            ? substr(strtolower(trim($options['actor_type'])), 0, 16)
            : 'unknown';
        if ($actorType === '') {
            $actorType = 'unknown';
        }

        $actorId = isset($options['actor_id']) ? (int) $options['actor_id'] : null;

        $contextJson = '';
        if (isset($options['context']) && is_array($options['context']) && !empty($options['context'])) {
            $encoded = json_encode($options['context']);
            if ($encoded !== false) {
                $contextJson = $encoded;
            }
        }

        $sql = 'INSERT INTO ' . zen_paypal_subscription_refresh_events_table_name()
            . ' (customers_id, profile_id, source, actor_type, actor_id, context, created_at) VALUES ('
            . (int) $customersId . ', '
            . "'" . zen_db_input($profileId) . "', "
            . "'" . zen_db_input($source) . "', "
            . "'" . zen_db_input($actorType) . "', "
            . ($actorId !== null ? (int) $actorId : 'NULL') . ', '
            . ($contextJson !== '' ? "'" . zen_db_input($contextJson) . "'" : 'NULL') . ', '
            . 'NOW()'
            . ');';

        $db->Execute($sql);

        return true;
    }
}

if (!function_exists('zen_paypal_subscription_build_snapshot')) {
    function zen_paypal_subscription_build_snapshot(array $subscription, array $options = array())
    {
        global $currencies;

        $profileData = array();
        if (isset($subscription['profile']) && is_array($subscription['profile'])) {
            $profileData = $subscription['profile'];
        }

        if (empty($profileData) && isset($subscription['profile_data']) && $subscription['profile_data'] !== null) {
            $rawProfile = $subscription['profile_data'];
            if (is_string($rawProfile) && $rawProfile !== '') {
                $decoded = json_decode($rawProfile, true);
                if (is_array($decoded)) {
                    $profileData = $decoded;
                }
            }
        }

        $profileId = '';
        if (isset($subscription['profile_id'])) {
            $profileId = $subscription['profile_id'];
        } elseif (isset($profileData['PROFILEID'])) {
            $profileId = $profileData['PROFILEID'];
        } elseif (isset($profileData['id'])) {
            $profileId = $profileData['id'];
        } elseif (isset($profileData['profile_id'])) {
            $profileId = $profileData['profile_id'];
        }
        if (is_array($profileId)) {
            $profileId = '';
        }
        $profileId = trim((string) $profileId);

        $subscriptionId = isset($subscription['subscription_id']) ? (int) $subscription['subscription_id'] : 0;

        $currencyCode = '';
        $currencyPaths = array(
            array('CURRENCYCODE'),
            array('currency'),
            array('currency_code'),
            array('currencyCode'),
            array('amount', 'currency_code'),
            array('amount', 'currency'),
            array('billing_info', 'last_payment', 'amount', 'currency_code'),
            array('billing_info', 'outstanding_balance', 'currency_code'),
            array('billing_info', 'cycle_executions', 0, 'total_amount', 'currency_code'),
            array('plan_overview', 'billing_cycles', 0, 'pricing_scheme', 'fixed_price', 'currency_code'),
            array('plan', 'billing_cycles', 0, 'pricing_scheme', 'fixed_price', 'currency_code'),
        );
        if (!empty($profileData)) {
            foreach ($currencyPaths as $path) {
                $value = zen_paypal_subscription_profile_value($profileData, $path);
                if (is_array($value) && isset($value['currency_code'])) {
                    $value = $value['currency_code'];
                }
                if (is_scalar($value)) {
                    $candidate = strtoupper(trim((string) $value));
                    if ($candidate !== '') {
                        $currencyCode = $candidate;
                        break;
                    }
                }
            }
        }
        if ($currencyCode === '' && isset($subscription['currencycode']) && $subscription['currencycode'] !== '') {
            $currencyCode = strtoupper(trim((string) $subscription['currencycode']));
        }

        $amountPaths = array(
            array('AMT'),
            array('REGULARAMT'),
            array('amount', 'value'),
            array('regular_amount'),
            array('billing_info', 'last_payment', 'amount', 'value'),
            array('billing_info', 'cycle_executions', 0, 'amount', 'value'),
            array('billing_info', 'cycle_executions', 0, 'total_amount', 'value'),
            array('plan_overview', 'billing_cycles', 0, 'pricing_scheme', 'fixed_price', 'value'),
            array('plan', 'billing_cycles', 0, 'pricing_scheme', 'fixed_price', 'value'),
        );
        $amountValue = '';
        if (!empty($profileData)) {
            $amountValue = zen_paypal_subscription_profile_amount($profileData, $amountPaths);
        }
        if ($amountValue === '' && isset($subscription['amount']) && $subscription['amount'] !== '') {
            $amountValue = (string) $subscription['amount'];
        }

        $startDate = '';
        if (!empty($profileData)) {
            $startDate = zen_paypal_subscription_profile_date($profileData, array(
                array('PROFILESTARTDATE'),
                array('start_time'),
                array('billing_info', 'cycle_executions', 0, 'time'),
                array('billing_info', 'last_payment', 'time'),
                array('create_time'),
            ));
        }
        if ($startDate === '' && isset($subscription['profilestartdate']) && $subscription['profilestartdate'] !== '') {
            $startDate = zen_paypal_normalize_subscription_date($subscription['profilestartdate']);
        } elseif ($startDate === '' && isset($subscription['date_added']) && $subscription['date_added'] !== '') {
            $startDate = zen_paypal_normalize_subscription_date($subscription['date_added']);
        }

        $nextDate = '';
        if (!empty($profileData)) {
            $nextDate = zen_paypal_subscription_profile_date($profileData, array(
                array('NEXTBILLINGDATE'),
                array('billing_info', 'next_billing_time'),
                array('billing_info', 'cycle_executions', 0, 'next_billing_time'),
                array('billing_info', 'next_payment', 'time'),
                array('next_billing_time'),
                array('next_payment_date'),
            ));
        }
        if ($nextDate === '' && isset($subscription['next_payment_date']) && $subscription['next_payment_date'] !== '') {
            $nextDate = zen_paypal_normalize_subscription_date($subscription['next_payment_date']);
        }

        $status = '';
        if (isset($subscription['cache_status']) && $subscription['cache_status'] !== '') {
            $status = $subscription['cache_status'];
        } elseif (!empty($profileData) && isset($profileData['STATUS']) && $profileData['STATUS'] !== '') {
            $status = $profileData['STATUS'];
        } elseif (!empty($profileData) && isset($profileData['status']) && $profileData['status'] !== '') {
            $status = $profileData['status'];
        } elseif (isset($subscription['status']) && $subscription['status'] !== '') {
            $status = $subscription['status'];
        }
        $status = is_string($status) ? trim($status) : '';

        $profileSource = '';
        if (isset($subscription['cache_profile_source']) && $subscription['cache_profile_source'] !== '') {
            $profileSource = strtolower(trim((string) $subscription['cache_profile_source']));
        } elseif (!empty($profileData) && isset($profileData['profile_source'])) {
            $profileSource = strtolower(trim((string) $profileData['profile_source']));
        }
        if ($profileSource === '' && !empty($profileData) && isset($profileData['plan_id'])) {
            $profileSource = 'rest';
        }

        $isRestProfile = ($profileSource === 'rest');
        if (!$isRestProfile && !empty($profileData) && isset($profileData['plan_id'])) {
            $isRestProfile = true;
        }

        $paymentMethod = '';
        if (!empty($profileData) && isset($profileData['CREDITCARDTYPE']) && isset($profileData['ACCT'])) {
            $paymentMethod = $profileData['CREDITCARDTYPE'] . ' ' . $profileData['ACCT'];
        } elseif (!empty($profileData) && isset($profileData['payment_source']['card']['brand']) && isset($profileData['payment_source']['card']['last_digits'])) {
            $paymentMethod = $profileData['payment_source']['card']['brand'] . ' ' . $profileData['payment_source']['card']['last_digits'];
        } elseif (isset($subscription['payment_method']) && trim((string) $subscription['payment_method']) !== '') {
            $paymentMethod = trim((string) $subscription['payment_method']);
        }
        if ($paymentMethod === '') {
            $paymentMethod = 'PayPal';
        }

        $planStatusClass = '';
        $normalizedStatus = strtolower($status);
        if ($status !== '' && zen_paypal_subscription_is_cancelled_status($status)) {
            $planStatusClass = 'cancelled_plan';
        }

        $refreshedAt = '';
        if (isset($subscription['cache_refreshed_at']) && $subscription['cache_refreshed_at'] !== null && $subscription['cache_refreshed_at'] !== '') {
            $refreshedAt = $subscription['cache_refreshed_at'];
        } elseif (isset($subscription['classification_refreshed_at']) && $subscription['classification_refreshed_at'] !== null && $subscription['classification_refreshed_at'] !== '') {
            $refreshedAt = $subscription['classification_refreshed_at'];
        } elseif (isset($subscription['refreshed_at']) && $subscription['refreshed_at'] !== null && $subscription['refreshed_at'] !== '') {
            $refreshedAt = $subscription['refreshed_at'];
        }

        $refreshEligible = ($profileId !== '' && !zen_paypal_subscription_is_cancelled_status($status));

        $formattedPrice = '';
        if ($amountValue !== '' && is_object($currencies)) {
            $formattedPrice = $currencies->format($amountValue, true, $currencyCode);
        }

        return array(
            'profile_id' => $profileId,
            'subscription_id' => $subscriptionId,
            'status' => $status,
            'start_date' => $startDate,
            'next_date' => $nextDate,
            'payment_method' => $paymentMethod,
            'price' => $amountValue,
            'formatted_price' => $formattedPrice,
            'currencycode' => $currencyCode,
            'plan_status_class' => $planStatusClass,
            'refresh_eligible' => $refreshEligible,
            'profile_source' => $profileSource,
            'is_rest_profile' => $isRestProfile,
            'refreshed_at' => $refreshedAt,
            'classification_refreshed_at' => $refreshedAt,
            'refresh_pending' => !empty($subscription['refresh_pending']),
            'refresh_pending_reason' => isset($subscription['refresh_pending_reason']) ? $subscription['refresh_pending_reason'] : '',
            'refresh_pending_message' => isset($subscription['refresh_pending_message']) ? $subscription['refresh_pending_message'] : '',
        );
    }
}

if (!function_exists('zen_paypal_subscription_refresh_queue_enqueue_many')) {
    function zen_paypal_subscription_refresh_queue_enqueue_many(array $profiles, array $options = array())
    {
        $enqueued = 0;
        foreach ($profiles as $profileContext) {
            if (!is_array($profileContext)) {
                continue;
            }

            $customerId = isset($profileContext['customers_id']) ? (int) $profileContext['customers_id'] : 0;
            $profileId = isset($profileContext['profile_id']) ? trim((string) $profileContext['profile_id']) : '';

            if ($customerId <= 0 || $profileId === '') {
                continue;
            }

            if (zen_paypal_subscription_refresh_queue_enqueue($customerId, $profileId, $options)) {
                $enqueued++;
            }
        }

        return $enqueued;
    }
}

if (!function_exists('zen_paypal_subscription_refresh_queue_claim')) {
    function zen_paypal_subscription_refresh_queue_claim($limit = 10, array $options = array())
    {
        global $db;

        $limit = (int) $limit;
        if ($limit <= 0) {
            return array();
        }

        $lockSeconds = isset($options['lock_timeout']) ? (int) $options['lock_timeout'] : 300;
        if ($lockSeconds <= 0) {
            $lockSeconds = 300;
        }

        $workerId = '';
        if (isset($options['worker_id']) && is_string($options['worker_id'])) {
            $workerId = substr(trim($options['worker_id']), 0, 64);
        }
        if ($workerId === '') {
            $workerId = php_uname('n') . '-' . getmypid();
        }

        $tableName = zen_paypal_subscription_refresh_queue_table_name();
        $candidates = $db->Execute(
            'SELECT queue_id'
            . ' FROM ' . $tableName
            . ' WHERE available_at <= NOW()'
            . '   AND (locked_at IS NULL OR locked_at < DATE_SUB(NOW(), INTERVAL ' . (int) $lockSeconds . ' SECOND))'
            . ' ORDER BY available_at ASC, queue_id ASC'
            . ' LIMIT ' . $limit . ';'
        );

        $queueIds = array();
        while (!$candidates->EOF) {
            $queueIds[] = (int) $candidates->fields['queue_id'];
            $candidates->MoveNext();
        }

        if (empty($queueIds)) {
            return array();
        }

        $idList = implode(',', $queueIds);
        $db->Execute(
            'UPDATE ' . $tableName
            . " SET locked_at = NOW(), locked_by = '" . zen_db_input($workerId) . "',"
            . '     attempts = attempts + 1,'
            . '     last_run_at = NOW()'
            . ' WHERE queue_id IN (' . $idList . ')'
            . '   AND available_at <= NOW()'
            . '   AND (locked_at IS NULL OR locked_at < DATE_SUB(NOW(), INTERVAL ' . (int) $lockSeconds . ' SECOND));'
        );

        $claimed = $db->Execute(
            'SELECT queue_id, customers_id, profile_id, available_at, attempts, last_error, context, locked_at, locked_by, last_run_at'
            . ' FROM ' . $tableName
            . ' WHERE queue_id IN (' . $idList . ')'
            . "   AND locked_by = '" . zen_db_input($workerId) . "';"
        );

        $jobs = array();
        while (!$claimed->EOF) {
            $jobs[] = $claimed->fields;
            $claimed->MoveNext();
        }

        return $jobs;
    }
}

if (!function_exists('zen_paypal_subscription_refresh_queue_complete')) {
    function zen_paypal_subscription_refresh_queue_complete($queueId)
    {
        global $db;

        $queueId = (int) $queueId;
        if ($queueId <= 0) {
            return false;
        }

        $tableName = zen_paypal_subscription_refresh_queue_table_name();
        $db->Execute('DELETE FROM ' . $tableName . ' WHERE queue_id = ' . $queueId . ' LIMIT 1;');

        return true;
    }
}

if (!function_exists('zen_paypal_subscription_refresh_queue_fail')) {
    function zen_paypal_subscription_refresh_queue_fail($queueId, $errorMessage = '', $retrySeconds = 300)
    {
        global $db;

        $queueId = (int) $queueId;
        if ($queueId <= 0) {
            return false;
        }

        $retrySeconds = (int) $retrySeconds;
        if ($retrySeconds < 60) {
            $retrySeconds = 60;
        }

        if (is_string($errorMessage)) {
            $errorMessage = substr($errorMessage, 0, 255);
        } else {
            $errorMessage = '';
        }

        $tableName = zen_paypal_subscription_refresh_queue_table_name();
        $db->Execute(
            'UPDATE ' . $tableName
            . " SET locked_at = NULL, locked_by = NULL,"
            . '     available_at = DATE_ADD(NOW(), INTERVAL ' . $retrySeconds . ' SECOND),'
            . "     last_error = " . ($errorMessage !== '' ? "'" . zen_db_input($errorMessage) . "'" : 'NULL') . ', '
            . '     updated_at = NOW()'
            . ' WHERE queue_id = ' . $queueId . ' LIMIT 1;'
        );

        return true;
    }
}

if (!function_exists('zen_paypal_subscription_refresh_queue_metrics')) {
    function zen_paypal_subscription_refresh_queue_metrics()
    {
        global $db;

        $tableName = zen_paypal_subscription_refresh_queue_table_name();
        $counts = $db->Execute(
            'SELECT'
            . ' COUNT(*) AS total_jobs,'
            . ' SUM(CASE WHEN locked_at IS NULL THEN 1 ELSE 0 END) AS pending_jobs,'
            . ' SUM(CASE WHEN locked_at IS NOT NULL THEN 1 ELSE 0 END) AS locked_jobs'
            . ' FROM ' . $tableName . ';'
        );

        $metrics = array(
            'total' => 0,
            'pending' => 0,
            'locked' => 0,
            'oldest_available' => null,
        );

        if (!$counts->EOF) {
            $metrics['total'] = (int) $counts->fields['total_jobs'];
            $metrics['pending'] = (int) $counts->fields['pending_jobs'];
            $metrics['locked'] = (int) $counts->fields['locked_jobs'];
        }

        $oldest = $db->Execute(
            'SELECT available_at'
            . ' FROM ' . $tableName
            . ' WHERE locked_at IS NULL'
            . ' ORDER BY available_at ASC'
            . ' LIMIT 1;'
        );

        if (!$oldest->EOF) {
            $metrics['oldest_available'] = $oldest->fields['available_at'];
        }

        return $metrics;
    }
}

if (!function_exists('zen_paypal_subscription_refresh_queue_customer_metrics')) {
    function zen_paypal_subscription_refresh_queue_customer_metrics($customerId)
    {
        global $db;

        $metrics = array(
            'total' => 0,
            'pending' => 0,
            'locked' => 0,
            'oldest_available' => null,
        );

        $customerId = (int) $customerId;
        if ($customerId <= 0) {
            return $metrics;
        }

        $tableName = zen_paypal_subscription_refresh_queue_table_name();
        $counts = $db->Execute(
            'SELECT'
            . ' COUNT(*) AS total_jobs,'
            . ' SUM(CASE WHEN locked_at IS NULL THEN 1 ELSE 0 END) AS pending_jobs,'
            . ' SUM(CASE WHEN locked_at IS NOT NULL THEN 1 ELSE 0 END) AS locked_jobs'
            . ' FROM ' . $tableName
            . ' WHERE customers_id = ' . (int) $customerId . ';'
        );

        if (!$counts->EOF) {
            $metrics['total'] = (int) $counts->fields['total_jobs'];
            $metrics['pending'] = (int) $counts->fields['pending_jobs'];
            $metrics['locked'] = (int) $counts->fields['locked_jobs'];
        }

        $oldest = $db->Execute(
            'SELECT available_at'
            . ' FROM ' . $tableName
            . ' WHERE customers_id = ' . (int) $customerId
            . '   AND locked_at IS NULL'
            . ' ORDER BY available_at ASC'
            . ' LIMIT 1;'
        );

        if (!$oldest->EOF) {
            $metrics['oldest_available'] = $oldest->fields['available_at'];
        }

        return $metrics;
    }
}

if (!function_exists('zen_paypal_subscription_refresh_subscription_table_columns')) {
    function zen_paypal_subscription_refresh_subscription_table_columns()
    {
        global $db;

        static $columns = null;

        if ($columns !== null) {
            return $columns;
        }

        $columns = array();

        if (!defined('TABLE_PAYPAL_RECURRING')) {
            return $columns;
        }

        $query = $db->Execute('SHOW COLUMNS FROM ' . TABLE_PAYPAL_RECURRING . ';');
        while (!$query->EOF) {
            if (isset($query->fields['Field'])) {
                $columns[] = $query->fields['Field'];
            }
            $query->MoveNext();
        }

        return $columns;
    }
}

if (!function_exists('zen_paypal_subscription_refresh_subscription_record')) {
    function zen_paypal_subscription_refresh_subscription_record(array $subscription, array $classification)
    {
        global $db;

        if (!defined('TABLE_PAYPAL_RECURRING')) {
            return false;
        }

        $subscriptionId = isset($subscription['subscription_id']) ? (int) $subscription['subscription_id'] : 0;
        $customerId = isset($subscription['customers_id']) ? (int) $subscription['customers_id'] : 0;

        if ($subscriptionId <= 0 || $customerId <= 0) {
            return false;
        }

        $columns = zen_paypal_subscription_refresh_subscription_table_columns();
        if (empty($columns)) {
            return false;
        }

        $profile = array();
        if (isset($classification['profile']) && is_array($classification['profile'])) {
            $profile = $classification['profile'];
        }

        $updates = array();

        if (in_array('status', $columns, true)) {
            $status = '';
            if (isset($classification['status']) && is_string($classification['status'])) {
                $status = trim($classification['status']);
            } elseif (isset($profile['STATUS']) && is_scalar($profile['STATUS'])) {
                $status = trim((string) $profile['STATUS']);
            } elseif (isset($profile['status']) && is_scalar($profile['status'])) {
                $status = trim((string) $profile['status']);
            }
            if ($status !== '') {
                $updates['status'] = $status;
            }
        }

        if (in_array('profile_source', $columns, true)) {
            $profileSource = '';
            if (isset($classification['profile_source']) && is_string($classification['profile_source'])) {
                $profileSource = strtolower(trim($classification['profile_source']));
            } elseif (isset($profile['profile_source']) && is_scalar($profile['profile_source'])) {
                $profileSource = strtolower(trim((string) $profile['profile_source']));
            }
            if ($profileSource !== '') {
                $updates['profile_source'] = $profileSource;
            }
        }

        if (in_array('preferred_gateway', $columns, true)) {
            $preferredGateway = '';
            if (isset($classification['preferred_gateway']) && is_string($classification['preferred_gateway'])) {
                $preferredGateway = strtolower(trim($classification['preferred_gateway']));
            }
            if ($preferredGateway === '' && isset($profile['preferred_gateway'])) {
                $preferredGateway = strtolower(trim((string) $profile['preferred_gateway']));
            }
            if ($preferredGateway !== '') {
                $updates['preferred_gateway'] = substr($preferredGateway, 0, 32);
            }
        }

        if (in_array('start_date', $columns, true)) {
            $startDate = zen_paypal_subscription_profile_date($profile, array(
                array('PROFILESTARTDATE'),
                array('start_time'),
                array('billing_info', 'cycle_executions', 0, 'time'),
                array('billing_info', 'last_payment', 'time'),
                array('create_time'),
            ));
            if ($startDate !== '') {
                $updates['start_date'] = $startDate;
            }
        }

        if (in_array('next_payment_date', $columns, true)) {
            $nextDate = zen_paypal_subscription_profile_date($profile, array(
                array('NEXTBILLINGDATE'),
                array('billing_info', 'next_billing_time'),
                array('billing_info', 'cycle_executions', 0, 'next_billing_time'),
                array('billing_info', 'next_payment', 'time'),
                array('next_billing_time'),
                array('next_payment_date'),
            ));
            if ($nextDate === '' && isset($subscription['next_payment_date'])) {
                $nextDate = zen_paypal_normalize_subscription_date($subscription['next_payment_date']);
            }
            if ($nextDate !== '') {
                $updates['next_payment_date'] = $nextDate;
            }
        }

        if (in_array('amount', $columns, true)) {
            $amount = zen_paypal_subscription_profile_amount($profile, array(
                array('AMT'),
                array('REGULARAMT'),
                array('amount', 'value'),
                array('billing_info', 'last_payment', 'amount', 'value'),
                array('plan_overview', 'billing_cycles', 0, 'pricing_scheme', 'fixed_price', 'value'),
            ));
            if ($amount === '' && isset($subscription['amount'])) {
                $amount = (string) $subscription['amount'];
            }
            if ($amount !== '') {
                $updates['amount'] = $amount;
            }
        }

        if (in_array('classification_refreshed_at', $columns, true)) {
            $refreshedAt = null;
            if (isset($classification['refreshed_at']) && $classification['refreshed_at'] !== null) {
                $refreshedAt = $classification['refreshed_at'];
            }
            if ($refreshedAt === null) {
                $refreshedAt = date('Y-m-d H:i:s');
            }
            $updates['classification_refreshed_at'] = $refreshedAt;
        }

        if (empty($updates)) {
            return false;
        }

        $setClauses = array();
        foreach ($updates as $column => $value) {
            if ($value === null || $value === '') {
                $setClauses[] = $column . ' = NULL';
            } else {
                $setClauses[] = $column . " = '" . zen_db_input($value) . "'";
            }
        }

        if (empty($setClauses)) {
            return false;
        }

        $db->Execute(
            'UPDATE ' . TABLE_PAYPAL_RECURRING
            . ' SET ' . implode(', ', $setClauses)
            . ' WHERE subscription_id = ' . $subscriptionId
            . '   AND customers_id = ' . $customerId
            . ' LIMIT 1;'
        );

        return true;
    }
}

if (!function_exists('zen_paypal_subscription_refresh_process_job')) {
    function zen_paypal_subscription_refresh_process_job(array $job, PayPalProfileManager $manager, array $options = array())
    {
        global $db;

        $customerId = isset($job['customers_id']) ? (int) $job['customers_id'] : 0;
        $profileId = isset($job['profile_id']) ? trim((string) $job['profile_id']) : '';

        if ($customerId <= 0 || $profileId === '') {
            return array('success' => false, 'error' => 'invalid_job');
        }

        if (!defined('TABLE_PAYPAL_RECURRING')) {
            return array('success' => false, 'error' => 'missing_table');
        }

        $subscriptionQuery = $db->Execute(
            'SELECT * FROM ' . TABLE_PAYPAL_RECURRING
            . ' WHERE customers_id = ' . $customerId
            . "   AND profile_id = '" . zen_db_input($profileId) . "'"
            . ' LIMIT 1;'
        );

        if ($subscriptionQuery->EOF) {
            return array('success' => true, 'status' => 'missing_subscription');
        }

        $subscription = $subscriptionQuery->fields;

        $context = array();
        if (isset($job['context']) && is_string($job['context']) && $job['context'] !== '') {
            $decodedContext = json_decode($job['context'], true);
            if (is_array($decodedContext)) {
                $context = $decodedContext;
            }
        }

        $operation = isset($context['operation']) ? strtolower(trim((string) $context['operation'])) : 'refresh';
        $gatewayContext = array();
        if (isset($context['preferred_gateway']) && $context['preferred_gateway'] !== '') {
            $gatewayContext['preferred_gateway'] = strtolower(trim((string) $context['preferred_gateway']));
        }
        if (isset($context['confidence']) && $context['confidence'] !== '') {
            $gatewayContext['confidence'] = strtolower(trim((string) $context['confidence']));
        }
        if (!isset($gatewayContext['preferred_gateway']) && isset($context['profile_source']) && $context['profile_source'] !== '') {
            $gatewayContext['preferred_gateway'] = strtolower(trim((string) $context['profile_source']));
        }

        if ($operation === 'cancel') {
            $note = isset($context['note']) && $context['note'] !== '' ? (string) $context['note'] : 'Cancelled by request.';
            $cancelResult = $manager->cancelProfile($subscription, $note, $gatewayContext);
            if (!is_array($cancelResult)) {
                $cancelResult = array('success' => (bool) $cancelResult);
            }
            if (empty($cancelResult['success'])) {
                $error = 'cancel_failed';
                if (isset($cancelResult['message']) && $cancelResult['message'] !== '') {
                    $error .= ':' . $cancelResult['message'];
                }
                $retrySeconds = isset($cancelResult['retry']) && $cancelResult['retry'] ? 300 : 0;
                if (isset($context['retry_seconds'])) {
                    $retrySeconds = (int) $context['retry_seconds'];
                }
                if ($retrySeconds <= 0) {
                    $retrySeconds = 300;
                }
                return array('success' => false, 'error' => $error, 'retry_seconds' => $retrySeconds);
            }

            $db->Execute(
                'UPDATE ' . TABLE_PAYPAL_RECURRING
                . "   SET status = 'Cancelled'"
                . ' WHERE subscription_id = ' . (int) $subscription['subscription_id']
                . ' LIMIT 1;'
            );

            if (isset($options['saved_card_recurring']) && is_object($options['saved_card_recurring'])
                && method_exists($options['saved_card_recurring'], 'remove_group_pricing')
            ) {
                $productId = isset($subscription['products_id']) ? (int) $subscription['products_id'] : 0;
                if ($productId > 0) {
                    $options['saved_card_recurring']->remove_group_pricing($customerId, $productId);
                }
            }

            zen_paypal_subscription_cache_invalidate($customerId, $profileId);

            $subscription['status'] = 'Cancelled';
        }

        $classificationOptions = array('force_refresh' => true);
        if (isset($options['cache_ttl'])) {
            $classificationOptions['cache_ttl'] = (int) $options['cache_ttl'];
        }

        $classification = zen_paypal_subscription_classify_profile($subscription, $manager, $classificationOptions);
        if (!is_array($classification)) {
            $classification = array();
        }

        $cachePayload = $classification;
        if (!isset($cachePayload['refreshed_at']) || $cachePayload['refreshed_at'] === null) {
            $cachePayload['refreshed_at'] = date('Y-m-d H:i:s');
        }

        zen_paypal_subscription_cache_store($customerId, $profileId, $cachePayload);
        zen_paypal_subscription_refresh_subscription_record($subscription, $classification);

        $status = 'refreshed';
        if (isset($classification['from_cache']) && !empty($classification['from_cache'])) {
            $status = 'cached';
        }

        if ($operation === 'cancel') {
            $status = 'cancelled';
        }

        return array('success' => true, 'status' => $status, 'classification' => $classification);
    }
}

if (!function_exists('zen_paypal_subscription_cache_ensure_schema')) {
    function zen_paypal_subscription_cache_ensure_schema()
    {
        global $db;

        $tableName = zen_paypal_subscription_cache_table_name();
        if ($tableName === '' || !isset($db) || !is_object($db) || !method_exists($db, 'Execute')) {
            return;
        }

        $columnQuery = $db->Execute("SHOW COLUMNS FROM " . $tableName . " LIKE 'preferred_gateway'");
        if ($columnQuery && $columnQuery->EOF) {
            $db->Execute('ALTER TABLE ' . $tableName . ' ADD COLUMN preferred_gateway VARCHAR(32) DEFAULT NULL AFTER profile_source');
        }
    }
}

if (!function_exists('zen_paypal_subscription_cache_memory_storage')) {
    function &zen_paypal_subscription_cache_memory_storage()
    {
        static $storage = array();
        return $storage;
    }
}

if (!function_exists('zen_paypal_subscription_cache_memory_forget')) {
    function zen_paypal_subscription_cache_memory_forget($customerId = null, $profileId = null)
    {
        $memory =& zen_paypal_subscription_cache_memory_storage();

        if ($customerId === null && $profileId === null) {
            $memory = array();
            return;
        }

        $customerId = (int) $customerId;
        $profileId = $profileId !== null ? trim((string) $profileId) : null;

        foreach ($memory as $cacheKey => $row) {
            if ($customerId > 0 && strpos($cacheKey, $customerId . ':') !== 0) {
                continue;
            }
            if ($profileId !== null) {
                $parts = explode(':', $cacheKey, 2);
                if (count($parts) === 2 && $parts[1] !== $profileId) {
                    continue;
                }
            }
            unset($memory[$cacheKey]);
        }
    }
}

if (!function_exists('zen_paypal_subscription_cache_invalidate')) {
    function zen_paypal_subscription_cache_invalidate($customerId, $profileId)
    {
        global $db;

        $customerId = (int) $customerId;
        $profileId = trim((string) $profileId);

        if ($customerId <= 0 || $profileId === '') {
            return false;
        }

        $tableName = zen_paypal_subscription_cache_table_name();
        if ($tableName === '') {
            return false;
        }

        $sql = 'DELETE FROM ' . $tableName
            . ' WHERE customers_id = ' . $customerId
            . "   AND profile_id = '" . zen_db_input($profileId) . "'"
            . ' LIMIT 1;';
        $db->Execute($sql);

        zen_paypal_subscription_cache_memory_forget($customerId, $profileId);

        return true;
    }
}

if (!function_exists('zen_paypal_subscription_cache_prefetch')) {
    function zen_paypal_subscription_cache_prefetch($customerId, array $profileIds)
    {
        global $db;

        $customerId = (int) $customerId;
        if ($customerId <= 0 || empty($profileIds)) {
            return array();
        }

        $tableName = zen_paypal_subscription_cache_table_name();
        if ($tableName === '') {
            return array();
        }

        $profileIds = array_values(array_filter(array_map(function ($value) {
            $value = trim((string) $value);
            return $value !== '' ? $value : null;
        }, $profileIds)));

        if (empty($profileIds)) {
            return array();
        }

        $prefetched = array();
        $memory =& zen_paypal_subscription_cache_memory_storage();

        $chunks = array_chunk(array_unique($profileIds), 50);
        foreach ($chunks as $chunk) {
            $quoted = array();
            foreach ($chunk as $profileId) {
                $quoted[] = "'" . zen_db_input($profileId) . "'";
            }
            if (empty($quoted)) {
                continue;
            }

            $query = $db->Execute(
                'SELECT customers_id, profile_id, status, profile_source, preferred_gateway, profile_data, refreshed_at'
                . ' FROM ' . $tableName
                . ' WHERE customers_id = ' . $customerId
                . '   AND profile_id IN (' . implode(',', $quoted) . ')'
            );

            while (!$query->EOF) {
                $row = $query->fields;
                $profileId = isset($row['profile_id']) ? trim((string) $row['profile_id']) : '';
                if ($profileId === '') {
                    $query->MoveNext();
                    continue;
                }
                $cacheKey = $customerId . ':' . $profileId;
                $memory[$cacheKey] = $row;
                $prefetched[$cacheKey] = $row;
                $query->MoveNext();
            }
        }

        return $prefetched;
    }
}

if (!function_exists('zen_paypal_subscription_cache_prefetch_subscriptions')) {
    function zen_paypal_subscription_cache_prefetch_subscriptions(array $subscriptions)
    {
        if (empty($subscriptions)) {
            return array();
        }

        $prefetchGroups = array();

        foreach ($subscriptions as $subscription) {
            $customerId = zen_paypal_subscription_extract_customer_id($subscription);
            if ($customerId <= 0) {
                continue;
            }

            $profileId = '';
            if (isset($subscription['profile_id'])) {
                $profileId = $subscription['profile_id'];
            } elseif (isset($subscription['profile']['PROFILEID'])) {
                $profileId = $subscription['profile']['PROFILEID'];
            } elseif (isset($subscription['profile']['profile_id'])) {
                $profileId = $subscription['profile']['profile_id'];
            } elseif (isset($subscription['profile']['id'])) {
                $profileId = $subscription['profile']['id'];
            }

            $profileId = trim((string) $profileId);
            if ($profileId === '') {
                continue;
            }

            if (!isset($prefetchGroups[$customerId])) {
                $prefetchGroups[$customerId] = array();
            }

            $prefetchGroups[$customerId][$profileId] = $profileId;
        }

        foreach ($prefetchGroups as $prefetchCustomerId => $profileIdMap) {
            $profileIds = array_values($profileIdMap);
            if (!empty($profileIds)) {
                zen_paypal_subscription_cache_prefetch($prefetchCustomerId, $profileIds);
            }
        }

        return $prefetchGroups;
    }
}

if (!function_exists('zen_paypal_subscription_cache_cleanup_stale')) {
    function zen_paypal_subscription_cache_cleanup_stale($customerId = null, $ttlSeconds = null)
    {
        global $db;

        $tableName = zen_paypal_subscription_cache_table_name();
        if ($tableName === '') {
            return false;
        }

        if ($ttlSeconds === null) {
            $ttlSeconds = zen_paypal_subscription_cache_cleanup_ttl();
        }

        $ttlSeconds = (int) $ttlSeconds;
        if ($ttlSeconds <= 0) {
            return false;
        }

        $threshold = date('Y-m-d H:i:s', time() - $ttlSeconds);
        $conditions = array("refreshed_at < '" . zen_db_input($threshold) . "'");

        if ($customerId !== null && (int) $customerId > 0) {
            $conditions[] = 'customers_id = ' . (int) $customerId;
        }

        $db->Execute(
            'DELETE FROM ' . $tableName
            . ' WHERE ' . implode(' AND ', $conditions)
        );

        if ($customerId === null) {
            zen_paypal_subscription_cache_memory_forget();
        } else {
            zen_paypal_subscription_cache_memory_forget((int) $customerId);
        }

        return true;
    }
}

if (!function_exists('zen_paypal_subscription_cache_refresh_profiles')) {
    function zen_paypal_subscription_cache_refresh_profiles(array $profiles, PayPalProfileManager $manager, array $options = array())
    {
        $refreshed = array();
        $logCallback = isset($options['log_callback']) && is_callable($options['log_callback']) ? $options['log_callback'] : null;
        $cacheTtl = isset($options['cache_ttl']) ? (int) $options['cache_ttl'] : null;

        foreach ($profiles as $profileContext) {
            if (!is_array($profileContext)) {
                continue;
            }

            $customerId = isset($profileContext['customers_id']) ? (int) $profileContext['customers_id'] : 0;
            $profileId = isset($profileContext['profile_id']) ? trim((string) $profileContext['profile_id']) : '';

            if ($customerId <= 0 || $profileId === '') {
                continue;
            }

            $subscriptionStub = array(
                'customers_id' => $customerId,
                'customer_id' => $customerId,
                'profile_id' => $profileId,
                'profile' => array('PROFILEID' => $profileId, 'id' => $profileId),
            );

            if (isset($profileContext['status'])) {
                $subscriptionStub['status'] = $profileContext['status'];
                $subscriptionStub['profile']['STATUS'] = $profileContext['status'];
            }

            $classificationOptions = array('force_refresh' => true);
            if ($cacheTtl !== null) {
                $classificationOptions['cache_ttl'] = $cacheTtl;
            }

            $result = zen_paypal_subscription_classify_profile($subscriptionStub, $manager, $classificationOptions);
            $refreshed[] = array(
                'customers_id' => $customerId,
                'profile_id' => $profileId,
                'success' => is_array($result) && !empty($result),
            );

            if ($logCallback) {
                call_user_func($logCallback, $subscriptionStub, $result);
            }
        }

        return $refreshed;
    }
}

if (!function_exists('zen_paypal_subscription_cache_lookup')) {
    function zen_paypal_subscription_cache_lookup($customerId, $profileId)
    {
        global $db;

        $customerId = (int) $customerId;
        $profileId = trim((string) $profileId);

        if ($customerId <= 0 || $profileId === '') {
            return false;
        }

        $tableName = zen_paypal_subscription_cache_table_name();
        if ($tableName === '') {
            return false;
        }

        $cacheKey = $customerId . ':' . $profileId;
        $memory =& zen_paypal_subscription_cache_memory_storage();
        if (array_key_exists($cacheKey, $memory)) {
            return $memory[$cacheKey];
        }

        $query = $db->Execute(
            'SELECT customers_id, profile_id, status, profile_source, preferred_gateway, profile_data, refreshed_at'
            . ' FROM ' . $tableName
            . ' WHERE customers_id = ' . $customerId
            . "   AND profile_id = '" . zen_db_input($profileId) . "'"
            . ' LIMIT 1;'
        );

        if ($query->EOF) {
            $memory[$cacheKey] = false;
            return false;
        }

        $memory[$cacheKey] = $query->fields;
        return $memory[$cacheKey];
    }
}

if (!function_exists('zen_paypal_subscription_cache_is_expired')) {
    function zen_paypal_subscription_cache_is_expired($cacheRow, $ttlSeconds = null)
    {
        if (!is_array($cacheRow) || empty($cacheRow)) {
            return true;
        }

        if ($ttlSeconds === null) {
            $ttlSeconds = zen_paypal_subscription_profile_cache_ttl();
        }
        $ttlSeconds = (int) $ttlSeconds;
        if ($ttlSeconds <= 0) {
            return false;
        }

        $refreshedAt = isset($cacheRow['refreshed_at']) ? strtotime($cacheRow['refreshed_at']) : 0;
        if ($refreshedAt <= 0) {
            return true;
        }

        return ($refreshedAt + $ttlSeconds) < time();
    }
}

if (!function_exists('zen_paypal_subscription_cache_decode_profile')) {
    function zen_paypal_subscription_cache_decode_profile($encodedProfile)
    {
        if (!is_string($encodedProfile) || $encodedProfile === '') {
            return array();
        }

        $decoded = json_decode($encodedProfile, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            return array();
        }

        return $decoded;
    }
}

if (!function_exists('zen_paypal_subscription_cache_build_result')) {
    function zen_paypal_subscription_cache_build_result($cacheRow)
    {
        if (!is_array($cacheRow) || empty($cacheRow)) {
            return null;
        }

        $profileSource = '';
        if (isset($cacheRow['profile_source']) && is_string($cacheRow['profile_source'])) {
            $profileSource = strtolower(trim($cacheRow['profile_source']));
        }

        $status = '';
        if (isset($cacheRow['status']) && is_string($cacheRow['status'])) {
            $status = (string) $cacheRow['status'];
        }

        $profile = array();
        if (isset($cacheRow['profile_data'])) {
            $profile = zen_paypal_subscription_cache_decode_profile($cacheRow['profile_data']);
        }

        $preferredGateway = '';
        if (isset($cacheRow['preferred_gateway']) && is_string($cacheRow['preferred_gateway'])) {
            $preferredGateway = strtolower(trim($cacheRow['preferred_gateway']));
        }

        $result = array(
            'is_rest' => ($profileSource === 'rest'),
            'profile_source' => $profileSource,
            'profile' => $profile,
            'status' => $status,
            'refreshed_at' => isset($cacheRow['refreshed_at']) ? $cacheRow['refreshed_at'] : null,
            'from_cache' => true,
            'preferred_gateway' => $preferredGateway,
            'preferred_gateway_source' => ($preferredGateway !== '' ? 'cache' : ''),
            'preferred_gateway_confidence' => ($preferredGateway !== '' ? 'cache' : '')
        );

        return $result;
    }
}

if (!function_exists('zen_paypal_subscription_consume_cache_for_subscription')) {
    function zen_paypal_subscription_consume_cache_for_subscription(array &$subscription, array $options = array())
    {
        $profileId = '';
        if (isset($subscription['profile_id'])) {
            $profileId = $subscription['profile_id'];
        } elseif (isset($subscription['profile']['PROFILEID'])) {
            $profileId = $subscription['profile']['PROFILEID'];
        } elseif (isset($subscription['profile']['id'])) {
            $profileId = $subscription['profile']['id'];
        } elseif (isset($subscription['profile']['profile_id'])) {
            $profileId = $subscription['profile']['profile_id'];
        }

        if (is_array($profileId)) {
            $profileId = '';
        }

        $profileId = trim((string) $profileId);
        if ($profileId === '') {
            return array(
                'profile_id' => '',
                'customer_id' => 0,
                'cache_row' => null,
                'cache_result' => null,
                'cache_expired' => false,
                'queued' => false,
                'refresh_pending' => false,
            );
        }

        $customerId = zen_paypal_subscription_extract_customer_id($subscription);

        $cacheLookup = isset($options['cache_lookup']) ? $options['cache_lookup'] : 'zen_paypal_subscription_cache_lookup';
        $cacheBuilder = isset($options['cache_build']) ? $options['cache_build'] : 'zen_paypal_subscription_cache_build_result';
        $cacheIsExpired = isset($options['cache_is_expired']) ? $options['cache_is_expired'] : 'zen_paypal_subscription_cache_is_expired';

        $cacheRow = null;
        if (is_callable($cacheLookup)) {
            $cacheRow = call_user_func($cacheLookup, $customerId, $profileId);
        }

        $cacheResult = null;
        if (is_array($cacheRow) && is_callable($cacheBuilder)) {
            $cacheResult = call_user_func($cacheBuilder, $cacheRow);
        }

        $cacheTtl = isset($options['cache_ttl']) ? $options['cache_ttl'] : null;
        $isExpired = false;
        if (is_array($cacheRow) && is_callable($cacheIsExpired)) {
            if ($cacheTtl !== null) {
                $isExpired = (bool) call_user_func($cacheIsExpired, $cacheRow, $cacheTtl);
            } else {
                $isExpired = (bool) call_user_func($cacheIsExpired, $cacheRow);
            }
        }

        if (is_array($cacheResult)) {
            zen_paypal_subscription_apply_classification($subscription, $cacheResult);
            if (!empty($cacheResult['refreshed_at'])) {
                $subscription['refreshed_at'] = $cacheResult['refreshed_at'];
                if (!isset($subscription['classification_refreshed_at'])) {
                    $subscription['classification_refreshed_at'] = $cacheResult['refreshed_at'];
                }
            }
        }

        $needsRefresh = !is_array($cacheRow) || $isExpired;
        $queued = false;

        if ($needsRefresh) {
            $subscription['refresh_pending'] = true;
            if (!empty($options['refresh_pending_message'])) {
                $subscription['refresh_pending_message'] = $options['refresh_pending_message'];
            }
            $subscription['refresh_pending_reason'] = is_array($cacheRow) ? 'stale_cache' : 'missing_cache';
        } else {
            $subscription['refresh_pending'] = false;
        }

        return array(
            'profile_id' => $profileId,
            'customer_id' => $customerId,
            'cache_row' => $cacheRow,
            'cache_result' => $cacheResult,
            'cache_expired' => $isExpired,
            'queued' => $queued,
            'refresh_pending' => $needsRefresh,
        );
    }
}

if (!function_exists('zen_paypal_subscription_cache_store')) {
    function zen_paypal_subscription_cache_store($customerId, $profileId, array $classification)
    {
        global $db;

        $customerId = (int) $customerId;
        $profileId = trim((string) $profileId);
        if ($customerId <= 0 || $profileId === '') {
            return false;
        }

        $tableName = zen_paypal_subscription_cache_table_name();
        if ($tableName === '') {
            return false;
        }

        $status = '';
        if (isset($classification['status']) && is_string($classification['status'])) {
            $status = substr(trim($classification['status']), 0, 64);
        }

        $profileSource = '';
        if (isset($classification['profile_source']) && is_string($classification['profile_source'])) {
            $profileSource = substr(strtolower(trim($classification['profile_source'])), 0, 16);
        }

        $profileData = '';
        if (isset($classification['profile']) && is_array($classification['profile']) && !empty($classification['profile'])) {
            $encoded = json_encode($classification['profile']);
            if ($encoded !== false) {
                $profileData = $encoded;
            }
        }

        $preferredGateway = '';
        if (isset($classification['preferred_gateway']) && is_string($classification['preferred_gateway'])) {
            $preferredGateway = substr(strtolower(trim($classification['preferred_gateway'])), 0, 32);
        }

        $refreshedAt = isset($classification['refreshed_at']) && $classification['refreshed_at'] !== null
            ? $classification['refreshed_at']
            : date('Y-m-d H:i:s');

        $sql = "INSERT INTO " . $tableName
            . " (customers_id, profile_id, status, profile_source, preferred_gateway, profile_data, refreshed_at) VALUES ("
            . $customerId . ", '" . zen_db_input($profileId) . "',"
            . ($status !== '' ? " '" . zen_db_input($status) . "'" : " NULL") . ','
            . ($profileSource !== '' ? " '" . zen_db_input($profileSource) . "'" : " NULL") . ','
            . ($preferredGateway !== '' ? " '" . zen_db_input($preferredGateway) . "'" : " NULL") . ','
            . ($profileData !== '' ? " '" . zen_db_input($profileData) . "'" : " NULL") . ","
            . " '" . zen_db_input($refreshedAt) . "')"
            . " ON DUPLICATE KEY UPDATE"
            . " status = VALUES(status),"
            . " profile_source = VALUES(profile_source),"
            . " preferred_gateway = VALUES(preferred_gateway),"
            . " profile_data = VALUES(profile_data),"
            . " refreshed_at = VALUES(refreshed_at);";

        $db->Execute($sql);

        $memory =& zen_paypal_subscription_cache_memory_storage();
        $memory[$customerId . ':' . $profileId] = array(
            'customers_id' => $customerId,
            'profile_id' => $profileId,
            'status' => ($status !== '' ? $status : null),
            'profile_source' => ($profileSource !== '' ? $profileSource : null),
            'preferred_gateway' => ($preferredGateway !== '' ? $preferredGateway : null),
            'profile_data' => ($profileData !== '' ? $profileData : null),
            'refreshed_at' => $refreshedAt
        );

        return true;
    }
}

if (!function_exists('zen_paypal_subscription_extract_customer_id')) {
    function zen_paypal_subscription_extract_customer_id(array $subscription)
    {
        if (isset($subscription['customers_id']) && (int) $subscription['customers_id'] > 0) {
            return (int) $subscription['customers_id'];
        }
        if (isset($subscription['customer_id']) && (int) $subscription['customer_id'] > 0) {
            return (int) $subscription['customer_id'];
        }
        if (isset($_SESSION['customer_id']) && (int) $_SESSION['customer_id'] > 0) {
            return (int) $_SESSION['customer_id'];
        }

        return 0;
    }
}

if (!function_exists('zen_paypal_subscription_admin_resolve_cached_profile')) {
    function zen_paypal_subscription_admin_resolve_cached_profile(array $subscription, array $options = array())
    {
        $customerId = zen_paypal_subscription_extract_customer_id($subscription);

        $profileId = '';
        if (isset($subscription['profile_id'])) {
            $profileId = $subscription['profile_id'];
        } elseif (isset($subscription['profile']['PROFILEID'])) {
            $profileId = $subscription['profile']['PROFILEID'];
        } elseif (isset($subscription['profile']['profile_id'])) {
            $profileId = $subscription['profile']['profile_id'];
        } elseif (isset($subscription['profile']['id'])) {
            $profileId = $subscription['profile']['id'];
        }

        if (is_array($profileId)) {
            $profileId = '';
        }

        $profileId = trim((string) $profileId);

        $profileData = array(
            'profile' => array(),
            'status' => '',
            'profile_source' => '',
            'profile_id' => $profileId,
            'errors' => array(),
        );
        $refreshPending = false;

        $cacheTtl = isset($options['cache_ttl']) ? (int) $options['cache_ttl'] : null;
        if ($cacheTtl !== null && $cacheTtl < 0) {
            $cacheTtl = 0;
        }

        $cacheRow = false;
        $cacheResult = null;
        if ($customerId > 0 && $profileId !== '' && function_exists('zen_paypal_subscription_cache_lookup')) {
            $cacheRow = zen_paypal_subscription_cache_lookup($customerId, $profileId);
            if (is_array($cacheRow)) {
                $cacheResult = zen_paypal_subscription_cache_build_result($cacheRow);
                if (isset($cacheRow['status'])) {
                    $subscription['cache_status'] = $cacheRow['status'];
                }
                if (isset($cacheRow['profile_source'])) {
                    $subscription['cache_profile_source'] = $cacheRow['profile_source'];
                }
                if (isset($cacheRow['profile_data'])) {
                    $subscription['profile_data'] = $cacheRow['profile_data'];
                }
                if (isset($cacheRow['refreshed_at'])) {
                    $subscription['cache_refreshed_at'] = $cacheRow['refreshed_at'];
                }

                if ($cacheTtl === null) {
                    $refreshPending = zen_paypal_subscription_cache_is_expired($cacheRow);
                } else {
                    $refreshPending = zen_paypal_subscription_cache_is_expired($cacheRow, $cacheTtl);
                }
            } else {
                $refreshPending = true;
            }
        }

        if (is_array($cacheResult)) {
            if (isset($cacheResult['profile']) && is_array($cacheResult['profile'])) {
                $profileData['profile'] = $cacheResult['profile'];
            }
            if (isset($cacheResult['status']) && is_string($cacheResult['status'])) {
                $profileData['status'] = $cacheResult['status'];
            }
            if (isset($cacheResult['profile_source']) && is_string($cacheResult['profile_source'])) {
                $profileData['profile_source'] = $cacheResult['profile_source'];
            }
            if (isset($cacheResult['refreshed_at']) && $cacheResult['refreshed_at'] !== null) {
                $subscription['cache_refreshed_at'] = $cacheResult['refreshed_at'];
            }
        }

        if ($profileData['profile_id'] === '' && $profileId !== '') {
            $profileData['profile_id'] = $profileId;
        }
        if ($profileData['status'] === '' && isset($subscription['status'])) {
            $profileData['status'] = (string) $subscription['status'];
        }
        if (!empty($profileData['profile'])) {
            $subscription['profile'] = $profileData['profile'];
        }

        if ($refreshPending) {
            $subscription['refresh_pending'] = true;
            $subscription['refresh_pending_reason'] = is_array($cacheRow) ? 'stale_cache' : 'missing_cache';
        } else {
            $subscription['refresh_pending'] = false;
            if (isset($subscription['refresh_pending_reason'])) {
                unset($subscription['refresh_pending_reason']);
            }
        }

        return array(
            'subscription' => $subscription,
            'profile_data' => $profileData,
            'refresh_pending' => $refreshPending,
        );
    }
}

if (!function_exists('zen_paypal_subscription_apply_classification')) {
    function zen_paypal_subscription_apply_classification(array &$subscription, array $classification)
    {
        if (!is_array($classification)) {
            return;
        }

        if (!isset($subscription['profile']) || !is_array($subscription['profile'])) {
            $subscription['profile'] = array();
        }

        if (!empty($classification['profile']) && is_array($classification['profile'])) {
            $subscription['profile'] = array_merge($subscription['profile'], $classification['profile']);
        }

        if (isset($classification['status']) && is_string($classification['status']) && $classification['status'] !== '') {
            $subscription['status'] = $classification['status'];
            $subscription['profile']['STATUS'] = $classification['status'];
            $subscription['profile']['status'] = $classification['status'];
        }

        if (isset($classification['profile_source']) && is_string($classification['profile_source'])) {
            $source = strtolower(trim($classification['profile_source']));
            $subscription['profile_source'] = $source;
            $subscription['profile']['profile_source'] = $source;
        }

        $subscription['is_rest_profile'] = !empty($classification['is_rest']);

        if (isset($classification['refreshed_at'])) {
            $subscription['classification_refreshed_at'] = $classification['refreshed_at'];
        }

        if (isset($classification['preferred_gateway']) && is_string($classification['preferred_gateway'])) {
            $subscription['preferred_gateway'] = strtolower(trim($classification['preferred_gateway']));
        }

        if (isset($classification['preferred_gateway_confidence']) && is_string($classification['preferred_gateway_confidence'])) {
            $subscription['preferred_gateway_confidence'] = strtolower(trim($classification['preferred_gateway_confidence']));
        }

        if (isset($classification['preferred_gateway_source']) && is_string($classification['preferred_gateway_source'])) {
            $subscription['preferred_gateway_source'] = strtolower(trim($classification['preferred_gateway_source']));
        }
    }
}

if (!function_exists('zen_my_subscriptions_is_ajax_request')) {
    function zen_my_subscriptions_is_ajax_request()
    {
        if (isset($_GET['ajax'])) {
            $flag = strtolower((string) $_GET['ajax']);
            if ($flag === '1' || $flag === 'true' || $flag === 'yes') {
                return true;
            }
        }

        if (isset($_POST['ajax'])) {
            $flag = strtolower((string) $_POST['ajax']);
            if ($flag === '1' || $flag === 'true' || $flag === 'yes') {
                return true;
            }
        }

        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            return true;
        }

        if (isset($_SERVER['HTTP_ACCEPT']) && stripos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) {
            return true;
        }

        return false;
    }
}

if (!function_exists('zen_my_subscriptions_send_json_response')) {
    function zen_my_subscriptions_send_json_response(array $payload, $statusCode = 200)
    {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
            if (function_exists('http_response_code')) {
                http_response_code($statusCode);
            }
        }

        echo json_encode($payload);
    }
}

if (!function_exists('zen_paypal_subscription_is_cancelled_status')) {
    function zen_paypal_subscription_is_cancelled_status($status)
    {
        $normalized = is_string($status) ? strtolower(trim($status)) : '';
        return in_array($normalized, array('cancelled', 'canceled'), true);
    }
}

if (!function_exists('zen_paypal_normalize_subscription_date')) {
    function zen_paypal_normalize_subscription_date($rawDate)
    {
        if (!is_string($rawDate)) {
            return '';
        }

        $rawDate = trim($rawDate);
        if ($rawDate === '') {
            return '';
        }

        $rawDate = str_replace('Z', '', $rawDate);
        if (strpos($rawDate, 'T') !== false) {
            $parts = explode('T', $rawDate);
            if (strlen($parts[0]) > 0) {
                return $parts[0];
            }
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $rawDate)) {
            return $rawDate;
        }

        $timestamp = strtotime($rawDate);
        if ($timestamp !== false) {
            return date('Y-m-d', $timestamp);
        }

        return $rawDate;
    }
}

if (!function_exists('zen_paypal_subscription_normalize_gateway_name')) {
    function zen_paypal_subscription_normalize_gateway_name($value)
    {
        if (!is_string($value)) {
            return '';
        }

        $normalized = strtolower(trim($value));
        if ($normalized === '') {
            return '';
        }

        if ($normalized === 'paypalr' || $normalized === 'rest' || strpos($normalized, 'rest') !== false) {
            return 'rest';
        }

        if (in_array($normalized, array('legacy', 'nvp', 'classic', 'express'), true) || strpos($normalized, 'legacy') !== false) {
            return 'legacy';
        }

        return preg_replace('/[^a-z0-9_-]/', '', $normalized);
    }
}

if (!function_exists('zen_paypal_subscription_determine_gateway_hint')) {
    function zen_paypal_subscription_determine_gateway_hint(array $subscription, $cachedResult = null)
    {
        $candidates = array();

        if (isset($subscription['preferred_gateway'])) {
            $candidates[] = array(
                'value' => $subscription['preferred_gateway'],
                'confidence' => 'high',
                'source' => 'subscription.preferred_gateway'
            );
        }

        if (is_array($cachedResult)) {
            if (isset($cachedResult['preferred_gateway'])) {
                $candidates[] = array(
                    'value' => $cachedResult['preferred_gateway'],
                    'confidence' => 'high',
                    'source' => 'cache.preferred_gateway'
                );
            }
            if (isset($cachedResult['profile_source'])) {
                $candidates[] = array(
                    'value' => $cachedResult['profile_source'],
                    'confidence' => 'high',
                    'source' => 'cache.profile_source'
                );
            }
        }

        if (isset($subscription['profile_source'])) {
            $candidates[] = array(
                'value' => $subscription['profile_source'],
                'confidence' => 'high',
                'source' => 'subscription.profile_source'
            );
        }

        if (isset($subscription['profile']) && is_array($subscription['profile'])) {
            $profileSource = zen_paypal_subscription_profile_find($subscription['profile'], array(
                array('profile_source'),
                array('PROFILE_SOURCE')
            ));
            if ($profileSource !== null) {
                $candidates[] = array(
                    'value' => $profileSource,
                    'confidence' => 'high',
                    'source' => 'profile.profile_source'
                );
            }
        }

        if (isset($subscription['api_type'])) {
            $candidates[] = array(
                'value' => $subscription['api_type'],
                'confidence' => 'medium',
                'source' => 'subscription.api_type'
            );
        }

        $profileData = array();
        if (isset($subscription['profile']) && is_array($subscription['profile'])) {
            $profileData = $subscription['profile'];
        } elseif (is_array($cachedResult) && isset($cachedResult['profile']) && is_array($cachedResult['profile'])) {
            $profileData = $cachedResult['profile'];
        }

        if (!empty($profileData)) {
            $planId = zen_paypal_subscription_profile_find($profileData, array(
                array('plan_id'),
                array('plan', 'id'),
                array('PLAN', 'ID')
            ));
            if ($planId !== null && $planId !== '') {
                $candidates[] = array(
                    'value' => 'rest',
                    'confidence' => 'medium',
                    'source' => 'profile.plan_id'
                );
            }
        }

        foreach ($candidates as $candidate) {
            $gateway = zen_paypal_subscription_normalize_gateway_name($candidate['value']);
            if ($gateway !== '') {
                return array(
                    'gateway' => $gateway,
                    'confidence' => $candidate['confidence'],
                    'source' => $candidate['source']
                );
            }
        }

        return null;
    }
}

if (!function_exists('zen_paypal_subscription_classify_profile')) {
    function zen_paypal_subscription_classify_profile(array $subscription, PayPalProfileManager $manager, array $options = array())
    {
        static $runtimeCache = array();

        $profileId = '';
        if (isset($subscription['profile_id'])) {
            $profileId = $subscription['profile_id'];
        } elseif (isset($subscription['profile']['PROFILEID'])) {
            $profileId = $subscription['profile']['PROFILEID'];
        } elseif (isset($subscription['profile']['id'])) {
            $profileId = $subscription['profile']['id'];
        } elseif (isset($subscription['profile']['profile_id'])) {
            $profileId = $subscription['profile']['profile_id'];
        }

        if (is_array($profileId)) {
            $profileId = '';
        }

        $profileId = trim((string) $profileId);
        if ($profileId === '') {
            zen_my_subscriptions_debug('paypal-profile:classify:skip', array('reason' => 'missing_profile_id'));
            return array(
                'is_rest' => false,
                'profile_source' => '',
                'profile' => array(),
                'status' => '',
                'preferred_gateway' => '',
                'preferred_gateway_confidence' => '',
                'preferred_gateway_source' => ''
            );
        }

        $customerId = zen_paypal_subscription_extract_customer_id($subscription);
        $forceRefresh = !empty($options['force_refresh']);
        $cacheTtl = isset($options['cache_ttl']) ? (int) $options['cache_ttl'] : zen_paypal_subscription_profile_cache_ttl();
        if ($cacheTtl < 0) {
            $cacheTtl = 0;
        }

        $profileIdHash = substr(hash('sha256', $profileId), 0, 16);
        $classificationStart = microtime(true);
        zen_my_subscriptions_debug('paypal-profile:classify:start', array(
            'profile_id_hash' => $profileIdHash,
            'force_refresh' => $forceRefresh,
            'customer_id' => $customerId > 0 ? $customerId : null
        ));

        $runtimeKey = $customerId . ':' . $profileId;
        if (!$forceRefresh && array_key_exists($runtimeKey, $runtimeCache)) {
            zen_my_subscriptions_debug('paypal-profile:classify:cache-hit', array(
                'profile_id_hash' => $profileIdHash,
                'source' => 'runtime',
                'elapsed' => microtime(true) - $classificationStart
            ));
            return $runtimeCache[$runtimeKey];
        }

        $persistentRow = zen_paypal_subscription_cache_lookup($customerId, $profileId);
        $cachedResult = null;
        if (is_array($persistentRow)) {
            $cachedResult = zen_paypal_subscription_cache_build_result($persistentRow);
        }

        if (!$forceRefresh && is_array($persistentRow) && !zen_paypal_subscription_cache_is_expired($persistentRow, $cacheTtl) && is_array($cachedResult)) {
            $runtimeCache[$runtimeKey] = $cachedResult;
            zen_my_subscriptions_debug('paypal-profile:classify:cache-hit', array(
                'profile_id_hash' => $profileIdHash,
                'source' => 'persistent',
                'elapsed' => microtime(true) - $classificationStart
            ));
            return $cachedResult;
        }

        if (is_array($persistentRow) && zen_paypal_subscription_cache_is_expired($persistentRow, $cacheTtl)) {
            $age = isset($persistentRow['refreshed_at']) ? time() - strtotime($persistentRow['refreshed_at']) : null;
            zen_my_subscriptions_debug('paypal-profile:classify:cache-stale', array(
                'profile_id_hash' => $profileIdHash,
                'age_seconds' => $age
            ));
        }

        $result = array(
            'is_rest' => false,
            'profile_source' => '',
            'profile' => array(),
            'status' => '',
            'preferred_gateway' => '',
            'preferred_gateway_confidence' => '',
            'preferred_gateway_source' => ''
        );

        if (is_array($cachedResult)) {
            if (isset($cachedResult['preferred_gateway']) && is_string($cachedResult['preferred_gateway']) && $cachedResult['preferred_gateway'] !== '') {
                $result['preferred_gateway'] = $cachedResult['preferred_gateway'];
                $result['preferred_gateway_confidence'] = isset($cachedResult['preferred_gateway_confidence']) ? $cachedResult['preferred_gateway_confidence'] : 'cache';
                $result['preferred_gateway_source'] = isset($cachedResult['preferred_gateway_source']) ? $cachedResult['preferred_gateway_source'] : 'cache';
            }
        }

        $gatewayHint = zen_paypal_subscription_determine_gateway_hint($subscription, $cachedResult);
        if (is_array($gatewayHint)) {
            $result['preferred_gateway'] = $gatewayHint['gateway'];
            $result['preferred_gateway_confidence'] = $gatewayHint['confidence'];
            $result['preferred_gateway_source'] = $gatewayHint['source'];
            zen_my_subscriptions_debug('paypal-profile:classify:hint', array(
                'profile_id_hash' => $profileIdHash,
                'gateway' => $gatewayHint['gateway'],
                'confidence' => $gatewayHint['confidence'],
                'source' => $gatewayHint['source']
            ));
        }

        if (isset($subscription['profile_source']) && is_string($subscription['profile_source'])) {
            $result['profile_source'] = strtolower(trim($subscription['profile_source']));
        } elseif (isset($subscription['profile']['profile_source']) && is_string($subscription['profile']['profile_source'])) {
            $result['profile_source'] = strtolower(trim($subscription['profile']['profile_source']));
        }

        if (isset($subscription['profile']) && is_array($subscription['profile']) && empty($result['profile'])) {
            $result['profile'] = $subscription['profile'];
        }

        $requestSubscription = $subscription;
        $gatewayContext = array();
        if (is_array($gatewayHint)) {
            $requestSubscription['gateway_hint'] = $gatewayHint['gateway'];
            $requestSubscription['gateway_confidence'] = $gatewayHint['confidence'];
            $gatewayContext = array(
                'preferred_gateway' => $gatewayHint['gateway'],
                'confidence' => $gatewayHint['confidence'],
                'hint_source' => $gatewayHint['source']
            );
        }

        $statusStart = microtime(true);
        $statusResult = $manager->getProfileStatus($requestSubscription, $gatewayContext);
        $statusResultLog = is_array($statusResult) ? $statusResult : array();
        $statusLogContext = array(
            'profile_id_hash' => $profileIdHash,
            'elapsed' => microtime(true) - $statusStart,
            'success' => isset($statusResultLog['success']) ? $statusResultLog['success'] : null,
            'retry' => isset($statusResultLog['retry']) ? $statusResultLog['retry'] : null,
            'message' => isset($statusResultLog['message']) ? $statusResultLog['message'] : null,
        );
        if (isset($gatewayContext['preferred_gateway'])) {
            $statusLogContext['preferred_gateway'] = $gatewayContext['preferred_gateway'];
        }
        if (isset($gatewayContext['confidence'])) {
            $statusLogContext['gateway_confidence'] = $gatewayContext['confidence'];
        }
        if (isset($statusResultLog['gateway'])) {
            $statusLogContext['gateway'] = $statusResultLog['gateway'];
        }
        zen_my_subscriptions_debug('paypal-profile:classify:get-status', $statusLogContext);

        $apiSucceeded = false;
        if (is_array($statusResult)) {
            if (isset($statusResult['profile']) && is_array($statusResult['profile'])) {
                $result['profile'] = $statusResult['profile'];
            }
            if (isset($statusResult['status']) && is_string($statusResult['status'])) {
                $result['status'] = $statusResult['status'];
            }
            if (isset($statusResult['profile_source']) && is_string($statusResult['profile_source'])) {
                $result['profile_source'] = strtolower(trim($statusResult['profile_source']));
            }
            if (isset($statusResult['gateway']) && is_string($statusResult['gateway'])) {
                $result['preferred_gateway'] = strtolower(trim($statusResult['gateway']));
                if (!empty($statusResult['success'])) {
                    $result['preferred_gateway_confidence'] = 'confirmed';
                    $result['preferred_gateway_source'] = 'manager';
                } elseif ($result['preferred_gateway_confidence'] === '' && is_array($gatewayHint)) {
                    $result['preferred_gateway_confidence'] = $gatewayHint['confidence'];
                    $result['preferred_gateway_source'] = $gatewayHint['source'];
                }
            }
            if (isset($statusResult['success'])) {
                $apiSucceeded = (bool) $statusResult['success'];
            } elseif (!empty($result['status']) || !empty($result['profile'])) {
                $apiSucceeded = true;
            }
        }

        if (is_array($cachedResult)) {
            if (empty($result['profile']) && !empty($cachedResult['profile'])) {
                $result['profile'] = $cachedResult['profile'];
            }
            if ($result['status'] === '' && !empty($cachedResult['status'])) {
                $result['status'] = $cachedResult['status'];
            }
            if ($result['profile_source'] === '' && !empty($cachedResult['profile_source'])) {
                $result['profile_source'] = $cachedResult['profile_source'];
            }
            if ($result['preferred_gateway'] === '' && !empty($cachedResult['preferred_gateway'])) {
                $result['preferred_gateway'] = $cachedResult['preferred_gateway'];
                if ($result['preferred_gateway_confidence'] === '') {
                    $result['preferred_gateway_confidence'] = isset($cachedResult['preferred_gateway_confidence']) ? $cachedResult['preferred_gateway_confidence'] : 'cache';
                }
                if ($result['preferred_gateway_source'] === '') {
                    $result['preferred_gateway_source'] = isset($cachedResult['preferred_gateway_source']) ? $cachedResult['preferred_gateway_source'] : 'cache';
                }
            }
        }

        if ($result['profile_source'] === '' && isset($result['profile']['plan_id'])) {
            $result['profile_source'] = 'rest';
        }

        if ($result['profile_source'] === '' && isset($subscription['api_type']) && is_string($subscription['api_type'])) {
            $apiType = strtolower(trim($subscription['api_type']));
            if ($apiType === 'paypalr' || $apiType === 'rest') {
                $result['profile_source'] = 'rest';
            }
        }

        if ($result['preferred_gateway'] === '' && $result['profile_source'] !== '') {
            $result['preferred_gateway'] = $result['profile_source'];
            if ($result['preferred_gateway_confidence'] === '') {
                $result['preferred_gateway_confidence'] = 'high';
            }
            if ($result['preferred_gateway_source'] === '') {
                $result['preferred_gateway_source'] = 'profile_source';
            }
        }

        $result['is_rest'] = ($result['profile_source'] === 'rest');
        $result['refreshed_at'] = date('Y-m-d H:i:s');
        $result['from_cache'] = false;

        if (!$apiSucceeded && is_array($cachedResult)) {
            $result = array_merge($cachedResult, array(
                'refreshed_at' => $result['refreshed_at'],
                'is_rest' => ($cachedResult['profile_source'] === 'rest'),
                'from_cache' => true
            ));
            if ($result['preferred_gateway'] === '' && is_array($gatewayHint)) {
                $result['preferred_gateway'] = $gatewayHint['gateway'];
                if ($result['preferred_gateway_confidence'] === '') {
                    $result['preferred_gateway_confidence'] = $gatewayHint['confidence'];
                }
                if ($result['preferred_gateway_source'] === '') {
                    $result['preferred_gateway_source'] = $gatewayHint['source'];
                }
            }
            if ($customerId > 0) {
                zen_paypal_subscription_cache_store($customerId, $profileId, $result);
            }
            $runtimeCache[$runtimeKey] = $result;
            zen_my_subscriptions_debug('paypal-profile:classify:fallback-cache', array(
                'profile_id_hash' => $profileIdHash,
                'elapsed' => microtime(true) - $classificationStart
            ));
            return $result;
        }

        if ($result['preferred_gateway'] === '' && is_array($gatewayHint)) {
            $result['preferred_gateway'] = $gatewayHint['gateway'];
            if ($result['preferred_gateway_confidence'] === '') {
                $result['preferred_gateway_confidence'] = $gatewayHint['confidence'];
            }
            if ($result['preferred_gateway_source'] === '') {
                $result['preferred_gateway_source'] = $gatewayHint['source'];
            }
        }

        if ($customerId > 0) {
            zen_paypal_subscription_cache_store($customerId, $profileId, $result);
        }

        $runtimeCache[$runtimeKey] = $result;

        zen_my_subscriptions_debug('paypal-profile:classify:complete', array(
            'profile_id_hash' => $profileIdHash,
            'elapsed' => microtime(true) - $classificationStart,
            'profile_source' => $result['profile_source'],
            'status' => $result['status'],
            'api_success' => $apiSucceeded,
            'preferred_gateway' => $result['preferred_gateway']
        ));

        return $result;
    }
}

if (!function_exists('zen_paypal_subscription_format_error_details_text')) {
    function zen_paypal_subscription_format_error_details_text($details)
    {
        if (!is_array($details)) {
            return '';
        }

        $messages = array();
        foreach ($details as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            if (isset($entry['details']) && is_array($entry['details'])) {
                $nestedMessage = zen_paypal_subscription_format_error_details_text($entry['details']);
                if ($nestedMessage !== '') {
                    $messages[] = $nestedMessage;
                }
            }

            $contextParts = array();
            if (!empty($entry['field'])) {
                $contextParts[] = trim((string) $entry['field']);
            }
            if (!empty($entry['name'])) {
                $contextParts[] = trim((string) $entry['name']);
            }

            $detailParts = array();
            if (!empty($entry['issue'])) {
                $detailParts[] = trim((string) $entry['issue']);
            }
            if (!empty($entry['description'])) {
                $detailParts[] = trim((string) $entry['description']);
            }
            if (!empty($entry['message'])) {
                $detailParts[] = trim((string) $entry['message']);
            }

            $message = '';
            if (!empty($contextParts)) {
                $message = implode(', ', $contextParts);
            }

            if (!empty($detailParts)) {
                $detailText = implode(' - ', $detailParts);
                $message = ($message !== '' ? $message . ': ' : '') . $detailText;
            }

            if ($message !== '') {
                $messages[] = $message;
            }
        }

        $messages = array_filter(array_map('trim', $messages));
        $messages = array_unique($messages);

        return implode(' ', $messages);
    }
}

if (!function_exists('zen_paypal_update_savedcard_subscription_status')) {
    function zen_paypal_update_savedcard_subscription_status(paypalSavedCardRecurring $paypalSavedCardRecurring, $savedCardId, $newStatus, $successMessage, $failureAction, $customerId = null, $statusComments = '')
    {
        global $messageStack;

        $savedCardId = (int) $savedCardId;
        $sessionCustomerId = isset($_SESSION['customer_id']) ? (int) $_SESSION['customer_id'] : 0;
        $customerId = $customerId === null ? $sessionCustomerId : (int) $customerId;

        $updateStart = microtime(true);
        $updateContext = array(
            'saved_recurring_id' => $savedCardId,
            'target_status' => $newStatus
        );
        zen_my_subscriptions_debug('savedcard:update-status:start', $updateContext);

        if ($savedCardId <= 0 || $sessionCustomerId <= 0) {
            $messageStack->add_session('my_subscriptions', 'A matching subscription could not be found, please contact us for assistance with ' . $failureAction . ' your subscription.', 'error');
            zen_my_subscriptions_debug('savedcard:update-status:abort', $updateContext + array('reason' => 'invalid identifiers'));
            return false;
        }

        $subscription = $paypalSavedCardRecurring->get_payment_details($savedCardId);
        zen_my_subscriptions_debug('savedcard:lookup:result', $updateContext + array(
            'elapsed' => microtime(true) - $updateStart,
            'found' => is_array($subscription)
        ));
        $subscriptionOwnerId = 0;
        if (is_array($subscription)) {
            if (isset($subscription['saved_card_customer_id']) && (int) $subscription['saved_card_customer_id'] > 0) {
                $subscriptionOwnerId = (int) $subscription['saved_card_customer_id'];
            } elseif (isset($subscription['subscription_customer_id']) && (int) $subscription['subscription_customer_id'] > 0) {
                $subscriptionOwnerId = (int) $subscription['subscription_customer_id'];
            } elseif (isset($subscription['customers_id'])) {
                $subscriptionOwnerId = (int) $subscription['customers_id'];
            }
        }

        if (!is_array($subscription) || $subscriptionOwnerId <= 0 || $subscriptionOwnerId !== $sessionCustomerId) {
            $messageStack->add_session('my_subscriptions', 'A matching subscription could not be found, please contact us for assistance with ' . $failureAction . ' your subscription.', 'error');
            zen_my_subscriptions_debug('savedcard:update-status:abort', $updateContext + array('reason' => 'ownership mismatch'));
            return false;
        }

        if ($customerId <= 0) {
            $customerId = $subscriptionOwnerId;
        }

        if ($customerId !== $subscriptionOwnerId) {
            $messageStack->add_session('my_subscriptions', 'A matching subscription could not be found, please contact us for assistance with ' . $failureAction . ' your subscription.', 'error');
            zen_my_subscriptions_debug('savedcard:update-status:abort', $updateContext + array('reason' => 'customer mismatch'));
            return false;
        }

        $statusComments = trim($statusComments);
        $statusUpdateStart = microtime(true);
        $paypalSavedCardRecurring->update_payment_status($savedCardId, $newStatus, $statusComments, $subscriptionOwnerId);
        zen_my_subscriptions_debug('savedcard:update-status:updated', $updateContext + array(
            'elapsed' => microtime(true) - $statusUpdateStart
        ));

        if ($newStatus === 'cancelled') {
            $subscriptionCustomerId = $subscriptionOwnerId;
            $subscriptionProductId = isset($subscription['products_id']) ? (int) $subscription['products_id'] : 0;
            $subscriptionDate = isset($subscription['date']) ? trim($subscription['date']) : '';

            if ($subscriptionCustomerId > 0 && $subscriptionProductId > 0 && $subscriptionDate !== '') {
                $paypalSavedCardRecurring->schedule_subscription_cancellation($subscriptionCustomerId, $subscriptionDate, $subscriptionProductId);
                zen_my_subscriptions_debug('savedcard:update-status:scheduled-cancellation', $updateContext + array(
                    'product_id' => $subscriptionProductId,
                    'elapsed' => microtime(true) - $statusUpdateStart
                ));
            }
        }

        $messageStack->add_session('my_subscriptions', $successMessage, 'success');
        zen_my_subscriptions_debug('savedcard:update-status:complete', $updateContext + array(
            'elapsed' => microtime(true) - $updateStart
        ));
        return true;
    }
}

if (!function_exists('zen_paypal_cancel_savedcard_subscription')) {
    function zen_paypal_cancel_savedcard_subscription(paypalSavedCardRecurring $paypalSavedCardRecurring, $savedCardId, $customerId = null)
    {
        return zen_paypal_update_savedcard_subscription_status($paypalSavedCardRecurring, $savedCardId, 'cancelled', 'Your subscription has been cancelled.', 'cancelling', $customerId, 'Cancelled by customer.');
    }
}
