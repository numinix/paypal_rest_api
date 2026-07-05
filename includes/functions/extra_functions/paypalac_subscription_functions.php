<?php
/**
 * Generic PayPal Advanced Checkout subscription helpers.
 *
 * Stores may override notification copy by defining paypalac_notify_subscription_cancelled()
 * before this file is loaded.
 */

if (!function_exists('paypalac_cancel_rest_subscription')) {
    /**
     * Mark a paypal_subscriptions row cancelled and optionally notify customer/admin.
     *
     * @param int  $subscriptionId Internal paypal_subscription_id.
     * @param bool $notify         Send emails on first transition to cancelled.
     * @param bool $forceNotify    Send even if already cancelled (race with webhook).
     */
    function paypalac_cancel_rest_subscription(int $subscriptionId, bool $notify = true, bool $forceNotify = false): bool
    {
        global $db;

        $subscriptionId = (int) $subscriptionId;
        if ($subscriptionId <= 0 || !defined('TABLE_PAYPAL_SUBSCRIPTIONS')) {
            return false;
        }

        $result = $db->Execute(
            'SELECT ps.*, c.customers_firstname, c.customers_lastname, c.customers_email_address'
            . ' FROM ' . TABLE_PAYPAL_SUBSCRIPTIONS . ' ps'
            . ' LEFT JOIN ' . TABLE_CUSTOMERS . ' c ON c.customers_id = ps.customers_id'
            . ' WHERE ps.paypal_subscription_id = ' . $subscriptionId
            . ' LIMIT 1'
        );

        if (!is_object($result) || $result->EOF) {
            return false;
        }

        $row = $result->fields;
        $previousStatus = strtolower(trim((string) ($row['status'] ?? '')));
        $wasAlreadyCancelled = in_array($previousStatus, ['cancelled', 'canceled'], true);

        $update = [
            'status' => 'cancelled',
            'last_modified' => date('Y-m-d H:i:s'),
        ];
        if (empty($row['date_cancelled'])) {
            $update['date_cancelled'] = date('Y-m-d H:i:s');
        }

        zen_db_perform(
            TABLE_PAYPAL_SUBSCRIPTIONS,
            $update,
            'update',
            'paypal_subscription_id = ' . $subscriptionId
        );

        if ($notify && (!$wasAlreadyCancelled || $forceNotify) && function_exists('paypalac_notify_subscription_cancelled')) {
            paypalac_notify_subscription_cancelled($row);
        }

        return true;
    }
}

if (!function_exists('paypalac_cancel_customer_subscription')) {
    /**
     * Cancel from My Account, including legacy saved-card recurring rows.
     *
     * @param array<string,mixed> $subscriptionRecord
     */
    function paypalac_cancel_customer_subscription(array $subscriptionRecord, bool $notify = true): bool
    {
        $subscriptionId = (int) ($subscriptionRecord['paypal_subscription_id'] ?? 0);
        if ($subscriptionId <= 0) {
            return false;
        }

        $legacyId = (int) ($subscriptionRecord['legacy_subscription_id'] ?? 0);
        if ($legacyId > 0 && defined('TABLE_SAVED_CREDIT_CARDS_RECURRING')) {
            if (!class_exists('paypalacSavedCardRecurring')) {
                $recurringPath = DIR_FS_CATALOG . DIR_WS_CLASSES . 'paypalacSavedCardRecurring.php';
                if (file_exists($recurringPath)) {
                    require_once $recurringPath;
                }
            }
            if (class_exists('paypalacSavedCardRecurring')) {
                $recurring = new paypalacSavedCardRecurring();
                if (method_exists($recurring, 'update_payment_status')) {
                    $recurring->update_payment_status($legacyId, 'cancelled', ' Cancelled by customer from My Account.');
                }
            }
        }

        return paypalac_cancel_rest_subscription($subscriptionId, $notify, true);
    }
}

if (!function_exists('paypalac_notify_subscription_cancelled')) {
    /**
     * Default storefront cancellation emails (override in the catalog for custom copy).
     *
     * @param array<string,mixed> $subscriptionRow
     */
    function paypalac_notify_subscription_cancelled(array $subscriptionRow): void
    {
        $customerId = (int) ($subscriptionRow['customers_id'] ?? 0);
        $subscriptionId = (int) ($subscriptionRow['paypal_subscription_id'] ?? 0);
        $firstName = trim((string) ($subscriptionRow['customers_firstname'] ?? ''));
        $lastName = trim((string) ($subscriptionRow['customers_lastname'] ?? ''));
        $customerEmail = trim((string) ($subscriptionRow['customers_email_address'] ?? ''));
        $customerName = trim($firstName . ' ' . $lastName);
        $productName = trim((string) ($subscriptionRow['products_name'] ?? ''));
        $storeName = defined('STORE_NAME') ? STORE_NAME : '';
        $storeUrl = defined('HTTPS_SERVER') ? HTTPS_SERVER : (defined('HTTP_SERVER') ? HTTP_SERVER : '');
        $salutation = $firstName !== '' ? $firstName : $customerName;

        $memberSubject = defined('TEXT_SUBSCRIPTION_CANCEL_EMAIL_MEMBER_SUBJECT')
            ? TEXT_SUBSCRIPTION_CANCEL_EMAIL_MEMBER_SUBJECT
            : ($storeName !== '' ? $storeName . ' - Subscription cancelled' : 'Subscription cancelled');

        $memberMessage = defined('TEXT_SUBSCRIPTION_CANCEL_EMAIL_MEMBER_GREETING')
            ? sprintf(TEXT_SUBSCRIPTION_CANCEL_EMAIL_MEMBER_GREETING, $salutation)
            : ('Dear ' . $salutation . ",\n\n");
        if (defined('TEXT_SUBSCRIPTION_CANCEL_EMAIL_MEMBER_BODY')) {
            $memberMessage .= sprintf(TEXT_SUBSCRIPTION_CANCEL_EMAIL_MEMBER_BODY, $productName !== '' ? $productName : 'your subscription');
        } else {
            $memberMessage .= 'Your subscription';
            if ($productName !== '') {
                $memberMessage .= ' for "' . $productName . '"';
            }
            $memberMessage .= " has been cancelled. You will not be charged for future billing cycles.\n\n";
            if ($storeUrl !== '') {
                $memberMessage .= 'You may log in to your account at ' . $storeUrl . " to review your subscription status.\n\n";
            }
            $memberMessage .= "If you did not request this change, please contact us.\n";
        }

        $adminSubject = defined('TEXT_SUBSCRIPTION_CANCEL_EMAIL_ADMIN_SUBJECT')
            ? sprintf(TEXT_SUBSCRIPTION_CANCEL_EMAIL_ADMIN_SUBJECT, $customerName !== '' ? $customerName : ('Customer #' . $customerId))
            : ('Subscription cancelled for ' . ($customerName !== '' ? $customerName : ('Customer #' . $customerId)));

        $adminMessage = 'Customer #:' . $customerId . "\n";
        $adminMessage .= 'Customer name: ' . $customerName . "\n";
        $adminMessage .= 'Subscription #' . $subscriptionId . "\n";
        $adminMessage .= 'Product: ' . $productName . "\n";
        $adminMessage .= 'New Status: cancelled' . "\n";

        $adminTo = '';
        if (defined('MODULE_PAYMENT_PAYPALSAVEDCARD_ERROR_NOTIFICATION_EMAIL')) {
            $adminTo = trim((string) constant('MODULE_PAYMENT_PAYPALSAVEDCARD_ERROR_NOTIFICATION_EMAIL'));
        }
        if ($adminTo === '' && defined('STORE_OWNER_EMAIL_ADDRESS')) {
            $adminTo = trim((string) STORE_OWNER_EMAIL_ADDRESS);
        }

        if ($customerEmail !== '') {
            zen_mail(
                $customerName,
                $customerEmail,
                $memberSubject,
                $memberMessage,
                $storeName,
                defined('EMAIL_FROM') ? EMAIL_FROM : '',
                ['EMAIL_MESSAGE_HTML' => nl2br($memberMessage)],
                'default'
            );
        }

        if ($adminTo !== '') {
            zen_mail(
                $adminTo,
                $adminTo,
                $adminSubject,
                $adminMessage,
                $storeName,
                defined('EMAIL_FROM') ? EMAIL_FROM : '',
                ['EMAIL_MESSAGE_HTML' => nl2br($adminMessage)],
                'default'
            );
        }
    }
}

if (!function_exists('paypalac_materialize_accept_automatic_renewal_order_attributes')) {
    /**
     * Materialize subscription billing attributes from an "Automatic Renewal: Accept"
     * order line attribute before the recurring observer reads order attributes.
     *
     * Observer notify order is hash-keyed in Zen Cart, so a site-specific observer that
     * adds billingperiod/billingfrequency may run after this plugin's recurring observer.
     */
    function paypalac_materialize_accept_automatic_renewal_order_attributes(int $ordersId): void
    {
        if ($ordersId <= 0 || !defined('TABLE_ORDERS_PRODUCTS') || !defined('TABLE_ORDERS_PRODUCTS_ATTRIBUTES')) {
            return;
        }

        global $db;

        $products = $db->Execute(
            'SELECT orders_products_id FROM ' . TABLE_ORDERS_PRODUCTS . ' WHERE orders_id = ' . $ordersId
        );
        if (!($products instanceof queryFactoryResult) || $products->EOF) {
            return;
        }

        while (!$products->EOF) {
            paypalac_materialize_accept_automatic_renewal_product_attributes(
                $ordersId,
                (int) $products->fields['orders_products_id']
            );
            $products->MoveNext();
        }
    }
}

if (!function_exists('paypalac_materialize_accept_automatic_renewal_product_attributes')) {
    function paypalac_materialize_accept_automatic_renewal_product_attributes(int $ordersId, int $ordersProductsId): void
    {
        if ($ordersId <= 0 || $ordersProductsId <= 0 || !defined('TABLE_ORDERS_PRODUCTS_ATTRIBUTES')) {
            return;
        }

        global $db;

        $result = $db->Execute(
            'SELECT products_options, products_options_values'
            . ' FROM ' . TABLE_ORDERS_PRODUCTS_ATTRIBUTES
            . ' WHERE orders_products_id = ' . $ordersProductsId
        );

        $acceptRenewalValue = null;
        $existingAttributeKeys = [];
        $valuesByNormKey = [];

        while ($result instanceof queryFactoryResult && !$result->EOF) {
            $optionName = strtolower(trim((string) $result->fields['products_options']));
            $optionValue = trim((string) $result->fields['products_options_values']);
            $optionName = substr($optionName, 0, 100);
            $normalizedName = preg_replace('/[^a-z0-9]/', '', $optionName) ?? '';

            $valuesByNormKey[$normalizedName] = $optionValue;
            $existingAttributeKeys[$normalizedName] = true;

            if ($normalizedName === 'acceptautomaticrenewal' || $normalizedName === 'automaticrenewal') {
                $acceptRenewalValue = $optionValue;
            }

            $result->MoveNext();
        }

        if ($acceptRenewalValue === null || !paypalac_renewal_attribute_value_is_present_and_accepted($acceptRenewalValue)) {
            return;
        }

        $period = '';
        $frequency = '';
        $cycles = '';

        $parts = explode('|', $acceptRenewalValue);
        if (count($parts) >= 2) {
            $period = trim($parts[0]);
            $frequency = trim($parts[1]);
            $cycles = count($parts) >= 3 ? trim($parts[2]) : '0';

            if ($period === '' || !is_numeric($frequency) || (int) $frequency <= 0) {
                return;
            }
            if ($cycles !== '' && !is_numeric($cycles)) {
                return;
            }
        } else {
            $period = 'Year';
            $frequency = '1';
            $cycles = (string) paypalac_infer_total_billing_cycles_from_order_attributes($valuesByNormKey);
        }

        $attributesToAdd = [];
        if (!isset($existingAttributeKeys['billingperiod'])) {
            $attributesToAdd[] = ['name' => 'billingperiod', 'value' => $period];
        }
        if (!isset($existingAttributeKeys['billingfrequency'])) {
            $attributesToAdd[] = ['name' => 'billingfrequency', 'value' => $frequency];
        }
        if (!isset($existingAttributeKeys['totalbillingcycles'])) {
            $attributesToAdd[] = ['name' => 'totalbillingcycles', 'value' => $cycles];
        }

        foreach ($attributesToAdd as $attr) {
            paypalac_insert_order_product_subscription_attribute(
                $ordersId,
                $ordersProductsId,
                $attr['name'],
                $attr['value']
            );
        }
    }
}

if (!function_exists('paypalac_infer_total_billing_cycles_from_order_attributes')) {
    /**
     * @param array<string,string> $valuesByNormKey
     */
    function paypalac_infer_total_billing_cycles_from_order_attributes(array $valuesByNormKey): int
    {
        if (isset($valuesByNormKey['totalbillingcycles'])) {
            $raw = trim((string) $valuesByNormKey['totalbillingcycles']);
            if ($raw !== '' && preg_match('/(\d+)/', $raw, $m) && (int) $m[1] > 0) {
                return (int) $m[1];
            }
        }

        return 5;
    }
}

if (!function_exists('paypalac_renewal_attribute_value_is_present_and_accepted')) {
    function paypalac_renewal_attribute_value_is_present_and_accepted(string $value): bool
    {
        $v = strtolower(trim($value));
        if ($v === '' || in_array($v, ['0', 'false', 'off', 'no', 'unchecked'], true)) {
            return false;
        }

        return true;
    }
}

if (!function_exists('paypalac_attribute_map_has_accepted_automatic_renewal')) {
    /**
     * Membership products carry billing period/frequency options for term length.
     * Only create subscriptions when the customer accepted automatic renewal.
     *
     * @param array<string,string> $attributeMap normalized order-product attribute keys
     */
    function paypalac_attribute_map_has_accepted_automatic_renewal(array $attributeMap): bool
    {
        foreach ($attributeMap as $key => $value) {
            $normalizedKey = preg_replace('/[^a-z0-9]/', '', strtolower((string) $key)) ?? '';
            if ($normalizedKey !== 'acceptautomaticrenewal' && $normalizedKey !== 'automaticrenewal') {
                continue;
            }

            return paypalac_renewal_attribute_value_is_present_and_accepted((string) $value);
        }

        return false;
    }
}

if (!function_exists('paypalac_insert_order_product_subscription_attribute')) {
    function paypalac_insert_order_product_subscription_attribute(
        int $ordersId,
        int $ordersProductsId,
        string $optionName,
        string $optionValue
    ): void {
        if ($ordersId <= 0 || $ordersProductsId <= 0) {
            return;
        }

        $sql_data = [
            'orders_id' => $ordersId,
            'orders_products_id' => $ordersProductsId,
            'products_options' => zen_db_input($optionName),
            'products_options_values' => zen_db_input($optionValue),
            'options_values_price' => 0,
            'price_prefix' => '+',
            'product_attribute_is_free' => 0,
            'products_attributes_weight' => 0,
            'products_attributes_weight_prefix' => '+',
            'attributes_discounted' => 1,
            'attributes_price_base_included' => 1,
            'attributes_price_onetime' => 0,
            'attributes_price_factor' => 0,
            'attributes_price_factor_offset' => 0,
            'attributes_price_factor_onetime' => 0,
            'attributes_price_factor_onetime_offset' => 0,
            'attributes_qty_prices' => '',
            'attributes_qty_prices_onetime' => '',
            'attributes_price_words' => 0,
            'attributes_price_words_free' => 0,
            'attributes_price_letters' => 0,
            'attributes_price_letters_free' => 0,
            'products_options_id' => 0,
            'products_options_values_id' => 0,
            'products_prid' => 0,
        ];

        zen_db_perform(TABLE_ORDERS_PRODUCTS_ATTRIBUTES, $sql_data);
    }
}

if (!function_exists('paypalac_product_has_automatic_renewal_accept')) {
    function paypalac_product_has_automatic_renewal_accept(array $product): bool
    {
        if (empty($product['attributes']) || !is_array($product['attributes'])) {
            return false;
        }

        foreach ($product['attributes'] as $optionKey => $attributeValue) {
            $optionName = '';
            $valueName = '';

            if (is_array($attributeValue)) {
                if (isset($attributeValue['option'])) {
                    $optionName = trim((string) $attributeValue['option']);
                } elseif (isset($attributeValue['products_options_name'])) {
                    $optionName = trim((string) $attributeValue['products_options_name']);
                }

                if (isset($attributeValue['value'])) {
                    $valueName = trim(strip_tags((string) $attributeValue['value']));
                } elseif (isset($attributeValue['value_id'])) {
                    $valueName = trim(strip_tags((string) zen_values_name((int) $attributeValue['value_id'])));
                } elseif (isset($attributeValue['products_options_values_name'])) {
                    $valueName = trim((string) $attributeValue['products_options_values_name']);
                }
            } else {
                $optionName = trim((string) zen_options_name((int) $optionKey));
                $valueName = trim((string) zen_values_name((int) $attributeValue));
            }

            if ($optionName === 'Automatic Renewal' && stripos($valueName, 'Accept') === 0) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('paypalac_cart_includes_automatic_renewal_accept')) {
    function paypalac_cart_includes_automatic_renewal_accept(): bool
    {
        if (function_exists('ncrs_cart_includes_automatic_renewal_accept')) {
            return ncrs_cart_includes_automatic_renewal_accept();
        }

        if (isset($_SESSION['cart']) && is_object($_SESSION['cart'])) {
            $products = $_SESSION['cart']->get_products();
            if (is_array($products) && !empty($products)) {
                foreach ($products as $product) {
                    if (paypalac_product_has_automatic_renewal_accept($product)) {
                        return true;
                    }
                }

                return false;
            }
        }

        global $order;
        if (isset($order) && is_object($order) && !empty($order->products) && is_array($order->products)) {
            foreach ($order->products as $product) {
                if (paypalac_product_has_automatic_renewal_accept($product)) {
                    return true;
                }
            }
        }

        return false;
    }
}

if (!function_exists('paypalac_customer_checked_save_card_post')) {
    function paypalac_customer_checked_save_card_post(): bool
    {
        foreach (['paypalac_cc_save_card', 'ppac_cc_save_card'] as $field) {
            if (!array_key_exists($field, $_POST)) {
                continue;
            }

            $value = $_POST[$field];
            if (is_bool($value)) {
                return $value;
            }

            $value = strtolower(trim(strip_tags((string) $value)));
            if ($value === 'on' || $value === '1' || $value === 'yes' || $value === 'true') {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('paypalac_checkout_requires_visible_saved_card')) {
    function paypalac_checkout_requires_visible_saved_card(): bool
    {
        return paypalac_cart_includes_automatic_renewal_accept();
    }
}

if (!function_exists('paypalac_sync_checkout_save_card_session')) {
    function paypalac_sync_checkout_save_card_session(bool $allowSaveCard, bool $usingNewCard): void
    {
        if (!$allowSaveCard || !$usingNewCard) {
            unset($_SESSION['PayPalAdvancedCheckout']['save_card']);
            return;
        }

        if (paypalac_checkout_requires_visible_saved_card() || paypalac_customer_checked_save_card_post()) {
            $_SESSION['PayPalAdvancedCheckout']['save_card'] = true;
            return;
        }

        unset($_SESSION['PayPalAdvancedCheckout']['save_card']);
    }
}

if (!function_exists('paypalac_checkout_vault_card_visible')) {
    function paypalac_checkout_vault_card_visible(): bool
    {
        return !empty($_SESSION['PayPalAdvancedCheckout']['save_card']);
    }
}
