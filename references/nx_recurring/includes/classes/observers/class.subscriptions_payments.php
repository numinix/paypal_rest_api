<?php
//
// +----------------------------------------------------------------------+
// |zen-cart Open Source E-commerce                                       |
// +----------------------------------------------------------------------+
// | Copyright (c) 2007-2008 Numinix Technology http://www.numinix.com    |
// |                                                                      |
// | Portions Copyright (c) 2003-2006 Zen Cart Development Team           |
// | http://www.zen-cart.com/index.php                                    |
// |                                                                      |
// | Portions Copyright (c) 2003 osCommerce                               |
// +----------------------------------------------------------------------+
// | This source file is subject to version 2.0 of the GPL license,       |
// | that is bundled with this package in the file LICENSE, and is        |
// | available through the world-wide-web at the following url:           |
// | http://www.zen-cart.com/license/2_0.txt.                             |
// | If you did not receive a copy of the zen-cart license and are unable |
// | to obtain it through the world-wide-web, please send a note to       |
// | license@zen-cart.com so we can mail you a copy immediately.          |
// +----------------------------------------------------------------------+
//  $Id: class.subscriptions_payments.php 12 2010-09-03 20:44:47Z numinix $
//
class subscriptionsPaymentsObserver extends base
{
        /**
         * Holds a normalized REST payload prepared by the REST observer so the
         * update handler can reuse the same data without recalculating it.
         *
         * @var array|null
         */
        protected $pendingRestNotification = null;

        public function __construct() {
                global $zco_notifier;
                $zco_notifier->attach($this, array(
                        'NOTIFY_PAYPAL_WPP_RECURRING_PAYMENT_RECEIVED',
                        'NOTIFY_PAYPALR_FUNDS_CAPTURED'
                ));
        }

        public function update(&$class, $eventID, $paramsArray)
        {
                $normalized = $this->normalizeNotification($eventID, $paramsArray);
                if (!is_array($normalized)) {
                        return;
                }

                $this->processStoreCreditAndGroups($normalized);
        }

        /**
         * Allows the REST-specific observer to prepare a normalized payload for
         * reuse when the main observer processes the same notification.
         *
         * @param array $normalized
         */
        public function setPendingRestNotification(array $normalized)
        {
                $this->pendingRestNotification = $normalized;
        }

        /**
         * Normalizes the notification payload for both WPP and REST events.
         *
         * @param string $eventID
         * @param array  $paramsArray
         * @return array|null
         */
        protected function normalizeNotification($eventID, $paramsArray)
        {
                if ($eventID === 'NOTIFY_PAYPALR_FUNDS_CAPTURED') {
                        if (is_array($this->pendingRestNotification)) {
                                $normalized = $this->pendingRestNotification;
                                $this->pendingRestNotification = null;
                        } else {
                                $normalized = $this->normalizeRestPayload($paramsArray);
                        }
                        return $normalized;
                }

                return $this->normalizeWppPayload($paramsArray);
        }

        /**
         * Ensures the legacy WPP notification contains the fields the handler expects.
         *
         * @param array $paramsArray
         * @return array|null
         */
        protected function normalizeWppPayload($paramsArray)
        {
                if (!is_array($paramsArray) || !isset($paramsArray['paypal_wpp_recurring']) || !is_object($paramsArray['paypal_wpp_recurring'])) {
                        return null;
                }

                if (!isset($paramsArray['zf_insert_id']) || !isset($paramsArray['customers_id'])) {
                        return null;
                }

                return $paramsArray;
        }

        /**
         * Public so the REST observer can reuse the conversion logic before the
         * main observer runs. Returns null when required data cannot be derived.
         *
         * @param array $paramsArray
         * @return array|null
         */
        public function normalizeRestPayload($paramsArray)
        {
                if (!is_array($paramsArray)) {
                        return null;
                }

                $normalized = array();
                $normalized['original_payload'] = $paramsArray;

                $orderId = $this->extractNestedValue($paramsArray, array('zf_insert_id', 'order_id', 'orders_id', 'orderId', 'zen_order_id'));
                if ($orderId !== null) {
                        $normalized['zf_insert_id'] = (int) $orderId;
                }

                $subscriptionId = $this->extractNestedValue($paramsArray, array('subscription_id', 'subscriptionId', 'paypal_recurring_id', 'recurring_payment_id'));
                $profileId = $this->extractNestedValue($paramsArray, array('profile_id', 'billing_agreement_id', 'billingAgreementId', 'paypal_billing_agreement_id', 'stored_credential_id', 'storedCredentialId', 'paypal_stored_credential_id', 'reference_id', 'referenceId'));

                $subscription = $this->lookupSubscriptionRecord($profileId, $subscriptionId);
                if ($subscription) {
                        $normalized['paypal_wpp_recurring'] = $subscription;
                        if (!isset($normalized['zf_insert_id']) && isset($subscription->fields['orders_id'])) {
                                $normalized['zf_insert_id'] = (int) $subscription->fields['orders_id'];
                        }
                        if (isset($subscription->fields['customers_id'])) {
                                $normalized['customers_id'] = (int) $subscription->fields['customers_id'];
                        }
                        if (!$profileId && isset($subscription->fields['profile_id'])) {
                                $profileId = $subscription->fields['profile_id'];
                        }
                        if (!$subscriptionId && isset($subscription->fields['subscription_id'])) {
                                $subscriptionId = $subscription->fields['subscription_id'];
                        }
                } elseif ($profileId) {
                        $normalized['paypal_wpp_recurring'] = $this->createRecurringStub($profileId, $subscriptionId);
                }

                if (!isset($normalized['paypal_wpp_recurring'])) {
                        return null;
                }

                if (!isset($normalized['customers_id'])) {
                        $customerId = $this->extractNestedValue($paramsArray, array('customers_id', 'customer_id', 'customerId', 'payer_id', 'payerId'));
                        if ($customerId !== null) {
                                $normalized['customers_id'] = (int) $customerId;
                        }
                }

                if (!isset($normalized['customers_id']) && isset($normalized['zf_insert_id'])) {
                        $orderCustomer = $this->lookupOrderCustomerId($normalized['zf_insert_id']);
                        if ($orderCustomer !== null) {
                                $normalized['customers_id'] = $orderCustomer;
                        }
                }

                if (!isset($normalized['zf_insert_id']) && $subscriptionId !== null) {
                        $orderId = $this->lookupOrderIdBySubscription((int) $subscriptionId);
                        if ($orderId !== null) {
                                $normalized['zf_insert_id'] = $orderId;
                        }
                }

                if (!isset($normalized['zf_insert_id'])) {
                        $orderId = $this->extractNestedValue($paramsArray, array('invoice_id', 'invoiceId'));
                        if ($orderId !== null) {
                                $normalized['zf_insert_id'] = (int) $orderId;
                        }
                }

                if (!isset($normalized['zf_insert_id']) && isset($normalized['paypal_wpp_recurring']->fields['orders_id'])) {
                        $normalized['zf_insert_id'] = (int) $normalized['paypal_wpp_recurring']->fields['orders_id'];
                }

                if (!isset($normalized['customers_id']) && isset($normalized['paypal_wpp_recurring']->fields['customers_id'])) {
                        $normalized['customers_id'] = (int) $normalized['paypal_wpp_recurring']->fields['customers_id'];
                }

                if (!isset($normalized['zf_insert_id']) || !isset($normalized['customers_id'])) {
                        return null;
                }

                return $normalized;
        }

        /**
         * Extracts the first non-empty value matching any of the provided keys from
         * nested arrays or objects.
         *
         * @param mixed $source
         * @param array $keys
         * @return mixed|null
         */
        protected function extractNestedValue($source, array $keys)
        {
                if (is_array($source)) {
                        foreach ($keys as $key) {
                                if (array_key_exists($key, $source) && $source[$key] !== '' && $source[$key] !== null) {
                                        return $source[$key];
                                }
                        }
                        foreach ($source as $value) {
                                $result = $this->extractNestedValue($value, $keys);
                                if ($result !== null) {
                                        return $result;
                                }
                        }
                } elseif (is_object($source)) {
                        foreach ($keys as $key) {
                                if (isset($source->$key) && $source->$key !== '' && $source->$key !== null) {
                                        return $source->$key;
                                }
                        }
                        if (isset($source->fields) && is_array($source->fields)) {
                                foreach ($keys as $key) {
                                        if (isset($source->fields[$key]) && $source->fields[$key] !== '' && $source->fields[$key] !== null) {
                                                return $source->fields[$key];
                                        }
                                }
                        }
                        foreach (get_object_vars($source) as $value) {
                                $result = $this->extractNestedValue($value, $keys);
                                if ($result !== null) {
                                        return $result;
                                }
                        }
                }

                return null;
        }

        /**
         * Attempts to load the PayPal recurring row using any identifiers provided
         * by the REST notification.
         *
         * @param string|int|null $profileId
         * @param string|int|null $subscriptionId
         * @return mixed|null
         */
        protected function lookupSubscriptionRecord($profileId, $subscriptionId)
        {
                global $db;

                if (!defined('TABLE_PAYPAL_RECURRING')) {
                        return null;
                }

                $clauses = array();
                if ($profileId !== null && $profileId !== '') {
                        $clauses[] = "profile_id = '" . $this->zenSafeInput($profileId) . "'";
                }
                if ($subscriptionId !== null && (string) $subscriptionId !== '') {
                        $clauses[] = "subscription_id = " . (int) $subscriptionId;
                }

                if (sizeof($clauses) === 0) {
                        return null;
                }

                $sql = "SELECT * FROM " . TABLE_PAYPAL_RECURRING . " WHERE " . implode(' OR ', $clauses) . " ORDER BY subscription_id DESC LIMIT 1;";
                $result = $db->Execute($sql);
                if ($result && $result->RecordCount() > 0) {
                        return $result;
                }

                return null;
        }

        /**
         * Looks up the order's customer id, returning null when unavailable.
         *
         * @param int $orderId
         * @return int|null
         */
        protected function lookupOrderCustomerId($orderId)
        {
                global $db;

                if (!defined('TABLE_ORDERS')) {
                        return null;
                }

                $order = $db->Execute("SELECT customers_id FROM " . TABLE_ORDERS . " WHERE orders_id = " . (int) $orderId . " LIMIT 1;");
                if ($order && $order->RecordCount() > 0) {
                        return (int) $order->fields['customers_id'];
                }

                return null;
        }

        /**
         * Looks up an order id by subscription id if available.
         *
         * @param int $subscriptionId
         * @return int|null
         */
        protected function lookupOrderIdBySubscription($subscriptionId)
        {
                global $db;

                if (!defined('TABLE_PAYPAL_RECURRING')) {
                        return null;
                }

                $record = $db->Execute("SELECT orders_id FROM " . TABLE_PAYPAL_RECURRING . " WHERE subscription_id = " . (int) $subscriptionId . " ORDER BY subscription_id DESC LIMIT 1;");
                if ($record && $record->RecordCount() > 0 && isset($record->fields['orders_id'])) {
                        return (int) $record->fields['orders_id'];
                }

                return null;
        }

        /**
         * Creates a lightweight stub that mimics the queryFactory result used by
         * legacy handlers when a REST notification references a subscription that
         * has not yet been written to the database.
         *
         * @param string|int $profileId
         * @param string|int|null $subscriptionId
         * @return stdClass
         */
        protected function createRecurringStub($profileId, $subscriptionId = null)
        {
                $stub = new stdClass();
                $stub->fields = array('profile_id' => $profileId);
                if ($subscriptionId !== null) {
                        $stub->fields['subscription_id'] = $subscriptionId;
                }
                return $stub;
        }

        /**
         * Ensures input is safely escaped when zen_db_input is unavailable.
         *
         * @param string $value
         * @return string
         */
        protected function zenSafeInput($value)
        {
                if (function_exists('zen_db_input')) {
                        return zen_db_input($value);
                }

                return addslashes($value);
        }

        /**
         * Executes the store credit, cancellation and group pricing updates using
         * the normalized payload.
         *
         * @param array $paramsArray
         */
        protected function processStoreCreditAndGroups($paramsArray)
        {
                global $db;

                if (!isset($paramsArray['zf_insert_id'], $paramsArray['customers_id'])) {
                        return;
                }

                $order       = new order($paramsArray['zf_insert_id']);
                $storeCredit = new storeCredit();
                $storeCredit->after_checkout(0, $paramsArray['customers_id']);

                if (!isset($paramsArray['paypal_wpp_recurring']) || !is_object($paramsArray['paypal_wpp_recurring'])) {
                        return;
                }

                $profileId = $this->extractNestedValue($paramsArray['paypal_wpp_recurring'], array('profile_id'));
                if ($profileId === null) {
                        return;
                }

                $subscription = $db->Execute("SELECT * FROM " . TABLE_PAYPAL_RECURRING . " WHERE profile_id = '" . $this->zenSafeInput($profileId) . "' LIMIT 1;");
                if (!$subscription || $subscription->RecordCount() <= 0) {
                        return;
                }

                switch ($subscription->fields['billingperiod'])
                {
                        case 'Day':
                                $seconds = 86400;
                                break;
                        case 'Week':
                                $seconds = 604800;
                                break;
                        case 'SemiMonth':
                                $seconds = 1209600;
                                break;
                        case 'Month':
                                $seconds = 2419200;
                                break;
                        case 'Year':
                                $seconds = 29030400;
                                break;
                        default:
                                $seconds = 0;
                }

                if ($seconds > 0) {
                        $check_store_credit = $db->Execute("SELECT log_id
                                          FROM " . TABLE_SC_REWARD_POINT_LOGS . "
                                          WHERE orders_id = " . (int) $subscription->fields['orders_id'] . "
                                          AND products_id = " . (int) $subscription->fields['products_id'] . "
                                          AND customers_id = " . (int) $paramsArray['customers_id'] . "
                                          LIMIT 1;");
                        if ($check_store_credit && $check_store_credit->RecordCount() > 0)
                        {
                                $now            = time();
                                $credit_expires = $now + ($seconds * (int) $subscription->fields['billingfrequency']);
                                $db->Execute("UPDATE " . TABLE_SC_REWARD_POINT_LOGS . " SET expires_on = " . (int) $credit_expires . " WHERE log_id = " . (int) $check_store_credit->fields['log_id'] . " LIMIT 1;");
                        }
                        require_once(DIR_WS_CATALOG . 'store_credit_cron.php');
                }

                $db->Execute("DELETE FROM " . TABLE_SUBSCRIPTION_CANCELLATIONS . " WHERE customers_id = " . (int)$paramsArray['customers_id'] . ";");

                $paypal_wpp_recurring_info = $db->Execute("SELECT products_id FROM " . TABLE_PAYPAL_WPP_RECURRING . " WHERE customers_id = " . (int)$paramsArray['customers_id'] . " AND profile_id = '" . $this->zenSafeInput($profileId) . "' LIMIT 1;");
                if ($paypal_wpp_recurring_info && $paypal_wpp_recurring_info->RecordCount() > 0)
                {
                        $subsciption_plan_name = zen_get_products_name($paypal_wpp_recurring_info->fields['products_id']);
                        $group_pricing         = $db->Execute("SELECT group_id FROM " . TABLE_GROUP_PRICING . " WHERE group_name = '" . $this->zenSafeInput($subsciption_plan_name) . "' LIMIT 1;");
                        if ($group_pricing && $group_pricing->RecordCount() > 0)
                        {
                                $db->Execute("UPDATE " . TABLE_CUSTOMERS . " SET customers_group_pricing = " . (int) $group_pricing->fields['group_id'] . " WHERE customers_id = " . (int)$paramsArray['customers_id'] . " LIMIT 1;");
                        }
                        $db->Execute("INSERT INTO " . TABLE_SUBSCRIPTION_CANCELLATIONS . " (customers_id, group_name, expiration_date) VALUES (" . (int)$paramsArray['customers_id'] . ", '" . $this->zenSafeInput($subsciption_plan_name) . "', '" . date('Y-m-d', time() + $seconds + 86400) . "')");
                }
        }
}
