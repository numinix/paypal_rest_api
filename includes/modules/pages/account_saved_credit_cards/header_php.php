<?php
use PayPalAdvancedCheckout\Api\PayPalAdvancedCheckoutApi;
use PayPalAdvancedCheckout\Common\VaultManager;

if (!defined('FILENAME_ACCOUNT_SAVED_CREDIT_CARDS')) {
    define('FILENAME_ACCOUNT_SAVED_CREDIT_CARDS', 'account_saved_credit_cards');
}

if (!function_exists('paypalac_format_vault_expiry')) {
    function paypalac_format_vault_expiry(string $rawExpiry): string
    {
        $rawExpiry = trim($rawExpiry);
        if ($rawExpiry === '') {
            return '';
        }

        if (preg_match('/^(\d{4})-(\d{2})$/', $rawExpiry, $matches) === 1) {
            return $matches[2] . '/' . $matches[1];
        }

        if (preg_match('/^(\d{2})(\d{2})$/', $rawExpiry, $matches) === 1) {
            return $matches[1] . '/20' . $matches[2];
        }

        return $rawExpiry;
    }
}

if (!function_exists('paypalac_format_vault_date')) {
    function paypalac_format_vault_date(?string $rawDate): string
    {
        if ($rawDate === null || $rawDate === '') {
            return '';
        }

        $datePortion = substr($rawDate, 0, 10);
        if ($datePortion === false || $datePortion === '') {
            return '';
        }

        return zen_date_short($datePortion);
    }
}

if (!function_exists('paypalac_format_vault_address')) {
    /**
     * @return string[]
     */
    function paypalac_format_vault_address(array $billingAddress): array
    {
        $lines = [];

        foreach (['address_line_1', 'address_line_2', 'address_line_3'] as $field) {
            if (!empty($billingAddress[$field])) {
                $lines[] = $billingAddress[$field];
            }
        }

        $cityLine = '';
        if (!empty($billingAddress['admin_area_2'])) {
            $cityLine = $billingAddress['admin_area_2'];
        }
        if (!empty($billingAddress['admin_area_1'])) {
            $cityLine .= ($cityLine === '' ? '' : ', ') . $billingAddress['admin_area_1'];
        }
        if (!empty($billingAddress['postal_code'])) {
            $cityLine .= ($cityLine === '' ? '' : ' ') . $billingAddress['postal_code'];
        }
        if ($cityLine !== '') {
            $lines[] = $cityLine;
        }

        if (!empty($billingAddress['country_code'])) {
            $lines[] = $billingAddress['country_code'];
        }

        return array_map('zen_output_string_protected', $lines);
    }
}

if (!function_exists('paypalac_split_vault_expiry')) {
    /**
     * @return array{month:string,year:string}
     */
    function paypalac_split_vault_expiry(string $rawExpiry): array
    {
        $month = '';
        $year = '';

        $rawExpiry = trim($rawExpiry);
        if ($rawExpiry === '') {
            return ['month' => $month, 'year' => $year];
        }

        if (preg_match('/^(\d{4})-(\d{2})$/', $rawExpiry, $matches) === 1) {
            $year = $matches[1];
            $month = $matches[2];
        } elseif (preg_match('/^(\d{2})[\/-](\d{2,4})$/', $rawExpiry, $matches) === 1) {
            $month = $matches[1];
            $year = $matches[2];
            if (strlen($year) === 2) {
                $year = '20' . $year;
            }
        }

        return [
            'month' => str_pad(substr($month, -2), 2, '0', STR_PAD_LEFT),
            'year' => substr($year, -4),
        ];
    }
}

if (!function_exists('paypalac_lookup_country_iso2')) {
    function paypalac_lookup_country_iso2(int $countryId): string
    {
        if ($countryId <= 0) {
            return '';
        }

        static $cache = [];
        if (isset($cache[$countryId])) {
            return $cache[$countryId];
        }

        global $db;

        $record = $db->Execute(
            "SELECT countries_iso_code_2" .
            "   FROM " . TABLE_COUNTRIES .
            "  WHERE countries_id = " . (int)$countryId .
            "  LIMIT 1"
        );

        if (!is_object($record) || $record->EOF) {
            $cache[$countryId] = '';
            return '';
        }

        $code = strtoupper((string)$record->fields['countries_iso_code_2']);
        $cache[$countryId] = $code;

        return $code;
    }
}

if (!function_exists('paypalac_lookup_country_id_by_iso2')) {
    function paypalac_lookup_country_id_by_iso2(string $iso2): ?int
    {
        $iso2 = strtoupper(trim($iso2));
        if ($iso2 === '') {
            return null;
        }

        static $cache = [];
        if (array_key_exists($iso2, $cache)) {
            return $cache[$iso2];
        }

        global $db;

        $record = $db->Execute(
            "SELECT countries_id" .
            "   FROM " . TABLE_COUNTRIES .
            "  WHERE countries_iso_code_2 = '" . zen_db_input($iso2) . "'" .
            "  LIMIT 1"
        );

        if (!is_object($record) || $record->EOF) {
            $cache[$iso2] = null;
            return null;
        }

        $countryId = (int)$record->fields['countries_id'];
        $cache[$iso2] = $countryId;

        return $countryId;
    }
}

if (!function_exists('paypalac_lookup_zone_code')) {
    function paypalac_lookup_zone_code(int $zoneId): string
    {
        if ($zoneId <= 0) {
            return '';
        }

        static $cache = [];
        if (isset($cache[$zoneId])) {
            return $cache[$zoneId];
        }

        global $db;

        $record = $db->Execute(
            "SELECT zone_code, zone_name" .
            "   FROM " . TABLE_ZONES .
            "  WHERE zone_id = " . (int)$zoneId .
            "  LIMIT 1"
        );

        if (!is_object($record) || $record->EOF) {
            $cache[$zoneId] = '';
            return '';
        }

        $zoneCode = trim((string)$record->fields['zone_code']);
        if ($zoneCode === '') {
            $zoneCode = trim((string)$record->fields['zone_name']);
        }

        $cache[$zoneId] = $zoneCode;

        return $zoneCode;
    }
}

if (!function_exists('paypalac_filter_paypal_address_fields')) {
    function paypalac_filter_paypal_address_fields(array $address): array
    {
        $allowed = [
            'address_line_1',
            'address_line_2',
            'address_line_3',
            'admin_area_1',
            'admin_area_2',
            'postal_code',
            'country_code',
        ];

        $filtered = [];
        foreach ($allowed as $field) {
            if (!empty($address[$field])) {
                $filtered[$field] = $address[$field];
            }
        }

        return $filtered;
    }
}

if (!function_exists('paypalac_build_paypal_address_from_book')) {
    function paypalac_build_paypal_address_from_book(array $entry): array
    {
        $address = [
            'address_line_1' => trim((string)($entry['entry_street_address'] ?? '')),
            'address_line_2' => trim((string)($entry['entry_suburb'] ?? '')),
            'admin_area_2' => trim((string)($entry['entry_city'] ?? '')),
            'postal_code' => trim((string)($entry['entry_postcode'] ?? '')),
        ];

        $zoneId = (int)($entry['entry_zone_id'] ?? 0);
        $state = trim((string)($entry['entry_state'] ?? ''));
        if ($zoneId > 0) {
            $zoneCode = paypalac_lookup_zone_code($zoneId);
            if ($zoneCode !== '') {
                $state = $zoneCode;
            }
        }
        if ($state !== '') {
            $address['admin_area_1'] = $state;
        }

        $countryId = (int)($entry['entry_country_id'] ?? 0);
        $countryCode = paypalac_lookup_country_iso2($countryId);
        if ($countryCode !== '') {
            $address['country_code'] = $countryCode;
        }

        return paypalac_filter_paypal_address_fields($address);
    }
}

if (!function_exists('paypalac_build_paypal_address_from_form')) {
    function paypalac_build_paypal_address_from_form(array $addressForm): array
    {
        $countryId = (int)($addressForm['country_id'] ?? 0);
        $countryCode = paypalac_lookup_country_iso2($countryId);

        $address = [
            'address_line_1' => trim((string)($addressForm['street_address'] ?? '')),
            'address_line_2' => trim((string)($addressForm['street_address_2'] ?? '')),
            'admin_area_2' => trim((string)($addressForm['city'] ?? '')),
            'admin_area_1' => trim((string)($addressForm['state'] ?? '')),
            'postal_code' => trim((string)($addressForm['postcode'] ?? '')),
        ];

        if ($countryCode !== '') {
            $address['country_code'] = $countryCode;
        }

        return paypalac_filter_paypal_address_fields($address);
    }
}

if (!function_exists('paypalac_fetch_customer_addresses')) {
    /**
     * @return array<int,array{id:int,label:string}>
     */
    function paypalac_fetch_customer_addresses(int $customers_id): array
    {
        if ($customers_id <= 0) {
            return [];
        }

        global $db;

        $records = $db->Execute(
            "SELECT address_book_id" .
            "   FROM " . TABLE_ADDRESS_BOOK .
            "  WHERE customers_id = " . (int)$customers_id .
            "  ORDER BY address_book_id"
        );

        $addresses = [];
        if (is_object($records)) {
            while (!$records->EOF) {
                $addressId = (int)$records->fields['address_book_id'];
                $label = zen_address_label($customers_id, $addressId, false, ', ');
                $label = preg_replace('/\s+/', ' ', trim((string)$label));
                $addresses[] = [
                    'id' => $addressId,
                    'label' => zen_output_string_protected($label),
                ];
                $records->MoveNext();
            }
        }

        return $addresses;
    }
}

if (!function_exists('paypalac_get_address_book_entry')) {
    /**
     * @return array<string,mixed>|null
     */
    function paypalac_get_address_book_entry(int $customers_id, int $address_book_id): ?array
    {
        if ($customers_id <= 0 || $address_book_id <= 0) {
            return null;
        }

        global $db;

        $record = $db->Execute(
            "SELECT *" .
            "   FROM " . TABLE_ADDRESS_BOOK .
            "  WHERE customers_id = " . (int)$customers_id .
            "    AND address_book_id = " . (int)$address_book_id .
            "  LIMIT 1"
        );

        if (!is_object($record) || $record->EOF) {
            return null;
        }

        return $record->fields;
    }
}

if (!function_exists('paypalac_build_patch_operation')) {
    /**
     * @param mixed $value
     *
     * @return array<string,mixed>
     */
    function paypalac_build_patch_operation(string $path, $value, string $operation = 'replace'): array
    {
        return [
            'op' => $operation,
            'path' => $path,
            'value' => $value,
        ];
    }
}

if (!function_exists('paypalac_get_vault_status_map')) {
    /**
     * @return array<string,array{0:string,1:string}>
     */
    function paypalac_get_vault_status_map(): array
    {
        return [
            'ACTIVE' => [TEXT_SAVED_CARD_STATUS_ACTIVE, 'is-active'],
            'APPROVED' => [TEXT_SAVED_CARD_STATUS_ACTIVE, 'is-active'],
            'VAULTED' => [TEXT_SAVED_CARD_STATUS_ACTIVE, 'is-active'],
            'INACTIVE' => [TEXT_SAVED_CARD_STATUS_INACTIVE, 'is-inactive'],
            'CANCELLED' => [TEXT_SAVED_CARD_STATUS_CANCELED, 'is-inactive'],
            'CANCELED' => [TEXT_SAVED_CARD_STATUS_CANCELED, 'is-inactive'],
            'DELETED' => [TEXT_SAVED_CARD_STATUS_CANCELED, 'is-inactive'],
            'EXPIRED' => [TEXT_SAVED_CARD_STATUS_EXPIRED, 'is-inactive'],
            'SUSPENDED' => [TEXT_SAVED_CARD_STATUS_SUSPENDED, 'is-warning'],
            'PENDING' => [TEXT_SAVED_CARD_STATUS_PENDING, 'is-warning'],
            'UNKNOWN' => [TEXT_SAVED_CARD_STATUS_PENDING, 'is-warning'],
        ];
    }
}

if (!function_exists('paypalac_normalize_vault_card')) {
    /**
     * @param array<string,mixed> $record
     * @param array<string,array{0:string,1:string}> $statusMap
     *
     * @return array<string,mixed>
     */
    function paypalac_normalize_vault_card(array $record, array $statusMap): array
    {
        $brand = trim((string)($record['brand'] ?? ''));
        if ($brand === '') {
            $brand = trim((string)($record['card_type'] ?? ''));
        }

        $statusKey = strtoupper((string)($record['status'] ?? ''));
        if ($statusKey === '') {
            $statusKey = 'UNKNOWN';
        }
        if (!isset($statusMap[$statusKey])) {
            $statusKey = 'UNKNOWN';
        }
        [$statusLabel, $statusClass] = $statusMap[$statusKey];

        $paypalVaultId = (int)($record['paypal_vault_id'] ?? 0);

        return [
            'paypal_vault_id' => $paypalVaultId,
            'vault_id' => (string)($record['vault_id'] ?? ''),
            'brand' => $brand,
            'last_digits' => (string)($record['last_digits'] ?? ''),
            'expiry' => paypalac_format_vault_expiry((string)($record['expiry'] ?? '')),
            'cardholder_name' => (string)($record['cardholder_name'] ?? ''),
            'status_label' => $statusLabel,
            'status_class' => $statusClass,
            'status_raw' => $statusKey,
            'last_used' => paypalac_format_vault_date($record['last_used'] ?? ''),
            'updated' => paypalac_format_vault_date($record['last_modified'] ?? ($record['update_time'] ?? '')),
            'created' => paypalac_format_vault_date($record['date_added'] ?? ''),
            'billing_address' => paypalac_format_vault_address($record['billing_address'] ?? []),
            'details_id' => 'saved-card-details-' . $paypalVaultId,
            'edit_href' => ($paypalVaultId > 0)
                ? zen_href_link(FILENAME_ACCOUNT_SAVED_CREDIT_CARDS, 'edit=' . $paypalVaultId, 'SSL')
                : '',
            'delete_href' => ($paypalVaultId > 0)
                ? zen_href_link(FILENAME_ACCOUNT_SAVED_CREDIT_CARDS, 'delete=' . $paypalVaultId, 'SSL')
                : '',
        ];
    }
}

// Define Payflow saved cards table if it exists
if (!defined('TABLE_SAVED_CREDIT_CARDS')) {
    define('TABLE_SAVED_CREDIT_CARDS', DB_PREFIX . 'saved_credit_cards');
}

if (!function_exists('paypalac_get_payflow_cards')) {
    /**
     * Retrieve Payflow saved credit cards for a customer
     *
     * @param int $customers_id
     * @return array
     */
    function paypalac_get_payflow_cards(int $customers_id): array
    {
        if ($customers_id <= 0) {
            return [];
        }

        global $db;

        // Check if table exists
        $tableCheck = $db->Execute("SHOW TABLES LIKE '" . TABLE_SAVED_CREDIT_CARDS . "'");
        if ($tableCheck->EOF) {
            return [];
        }

        $sql = "SELECT scc.saved_credit_card_id, scc.type, scc.last_digits, scc.expiry,
                       scc.name_on_card, scc.is_primary, scc.api_type
                FROM " . TABLE_SAVED_CREDIT_CARDS . " scc
                WHERE scc.customers_id = " . (int)$customers_id . "
                  AND scc.is_deleted = '0'
                  AND LAST_DAY(STR_TO_DATE(scc.expiry, '%m%y')) > CURDATE()
                ORDER BY scc.is_primary DESC, scc.saved_credit_card_id DESC";

        $result = $db->Execute($sql);
        $cards = [];

        if (!is_object($result)) {
            return [];
        }

        while (!$result->EOF) {
            $cards[] = [
                'saved_credit_card_id' => (int)$result->fields['saved_credit_card_id'],
                'type' => (string)$result->fields['type'],
                'last_digits' => (string)$result->fields['last_digits'],
                'expiry' => (string)$result->fields['expiry'],
                'name_on_card' => (string)$result->fields['name_on_card'],
                'is_primary' => (int)$result->fields['is_primary'],
                'api_type' => (string)($result->fields['api_type'] ?? 'payflow'),
            ];
            $result->MoveNext();
        }

        return $cards;
    }
}

if (!function_exists('paypalac_normalize_payflow_card')) {
    /**
     * Normalize Payflow card to match vault card format for display
     *
     * @param array $payflowCard
     * @return array
     */
    function paypalac_normalize_payflow_card(array $payflowCard): array
    {
        $cardId = (int)($payflowCard['saved_credit_card_id'] ?? 0);
        $brand = (string)($payflowCard['type'] ?? '');
        $lastDigits = (string)($payflowCard['last_digits'] ?? '');
        $expiry = (string)($payflowCard['expiry'] ?? '');
        $cardholderName = (string)($payflowCard['name_on_card'] ?? '');
        $apiType = (string)($payflowCard['api_type'] ?? 'payflow');

        // Format expiry from MMYY to MM/YYYY
        $formattedExpiry = paypalac_format_vault_expiry($expiry);

        return [
            'paypal_vault_id' => 0, // Not a vault card
            'payflow_card_id' => $cardId,
            'source' => 'payflow',
            'vault_id' => '',
            'brand' => $brand,
            'last_digits' => $lastDigits,
            'expiry' => $formattedExpiry,
            'cardholder_name' => $cardholderName,
            'status_label' => TEXT_SAVED_CARD_STATUS_ACTIVE,
            'status_class' => 'is-active',
            'status_raw' => 'ACTIVE',
            'last_used' => '',
            'updated' => '',
            'created' => '',
            'billing_address' => [],
            'details_id' => 'saved-card-details-payflow-' . $cardId,
            'edit_href' => '', // Payflow cards cannot be edited via this page
            'delete_href' => ($cardId > 0)
                ? zen_href_link(FILENAME_ACCOUNT_SAVED_CREDIT_CARDS, 'delete_payflow=' . $cardId, 'SSL')
                : '',
            'api_type' => $apiType,
        ];
    }
}

if (!function_exists('paypalac_delete_payflow_card')) {
    /**
     * Delete a saved credit card on file (originally written for legacy PayFlow
     * cards, hence the name; also handles AC vault cards on storefronts that
     * surface a delete action for them).
     *
     * If the card is currently attached to one or more scheduled recurring
     * subscriptions, the deletion is conditional:
     *   - When the customer has another usable card on file (legacy PayFlow
     *     OR PayPal AC vault), we soft-delete and reassign the subscriptions
     *     to that replacement card.
     *   - When no replacement exists, the deletion is BLOCKED and the
     *     customer is asked to add a new card first, so subscriptions are
     *     never silently orphaned (which previously left them billing a
     *     deleted card and being skipped on every cron run).
     *
     * @param int $customers_id
     * @param int $card_id
     * @return bool true on successful soft-delete, false on validation /
     *              guard / DB failure (an error message is added to the
     *              global messageStack when blocked).
     */
    function paypalac_delete_payflow_card(int $customers_id, int $card_id): bool
    {
        if ($customers_id <= 0 || $card_id <= 0) {
            return false;
        }

        global $db, $messageStack;

        // Check if table exists
        $tableCheck = $db->Execute("SHOW TABLES LIKE '" . TABLE_SAVED_CREDIT_CARDS . "'");
        if ($tableCheck->EOF) {
            return false;
        }

        $activeSubs = paypalac_payflow_card_active_subscription_names($card_id);

        if (!empty($activeSubs)) {
            // Card is the only active payment method protecting these subs.
            // If the customer has no other usable card we block the delete
            // and ask them to add a new card first.
            if (!paypalac_customer_has_other_usable_card($customers_id, $card_id)) {
                if (isset($messageStack) && defined('TEXT_DELETE_CARD_BLOCKED_BY_SUBSCRIPTIONS')) {
                    $subsList = htmlspecialchars(implode(', ', $activeSubs), ENT_QUOTES, 'UTF-8');
                    $messageStack->add(
                        'saved_credit_cards',
                        sprintf(TEXT_DELETE_CARD_BLOCKED_BY_SUBSCRIPTIONS, $subsList),
                        'error'
                    );
                }
                return false;
            }
        }

        // Soft delete the card
        $sql = "UPDATE " . TABLE_SAVED_CREDIT_CARDS . "
                SET is_deleted = '1'
                WHERE saved_credit_card_id = " . (int)$card_id . "
                  AND customers_id = " . (int)$customers_id . "
                LIMIT 1";

        $db->Execute($sql);

        // Reassign any scheduled subscriptions onto the replacement card.
        // We prefer paypalSavedCardRecurring::card_was_deleted() because it
        // also handles cancellation fallback and produces a customer-facing
        // summary, but it lives in the legacy PayFlow plugin which may not
        // be installed.  When it isn't, fall back to the AC plugin's own
        // recurring class (always present here) and do a straight rebind to
        // the replacement card so we never leave subscriptions orphaned.
        if (!empty($activeSubs)) {
            $reassignMessage = '';
            $payflowClassFile = DIR_FS_CATALOG . DIR_WS_CLASSES . 'paypalSavedCardRecurring.php';
            $payflowAvailable = class_exists('paypalSavedCardRecurring');
            if (!$payflowAvailable && file_exists($payflowClassFile)) {
                require_once $payflowClassFile;
                $payflowAvailable = class_exists('paypalSavedCardRecurring');
            }

            if ($payflowAvailable) {
                $recurring = new paypalSavedCardRecurring();
                $reassignMessage = trim((string)$recurring->card_was_deleted($card_id, $customers_id));
            } else {
                // AC-only fallback: rebind active subs to the replacement
                // card directly via this plugin's recurring class.
                $replacementId = paypalac_replacement_card_id($customers_id, $card_id);
                if ($replacementId > 0) {
                    if (!class_exists('paypalacSavedCardRecurring')) {
                        require_once DIR_FS_CATALOG . DIR_WS_CLASSES . 'paypalacSavedCardRecurring.php';
                    }
                    $acRecurring = new paypalacSavedCardRecurring();
                    $rebindSql = "SELECT saved_credit_card_recurring_id"
                        . " FROM " . TABLE_SAVED_CREDIT_CARDS_RECURRING
                        . " WHERE saved_credit_card_id = " . (int)$card_id
                        . "   AND LOWER(TRIM(status)) = 'scheduled'";
                    $rebind = $db->Execute($rebindSql);
                    while (!$rebind->EOF) {
                        $acRecurring->update_payment_info(
                            (int)$rebind->fields['saved_credit_card_recurring_id'],
                            array(
                                'saved_credit_card_id' => $replacementId,
                                'comments' => '  Card replaced after deletion (AC-only fallback path).  '
                            )
                        );
                        $rebind->MoveNext();
                    }
                }
            }
            if ($reassignMessage !== '' && isset($messageStack)) {
                $messageStack->add_session('saved_credit_cards', $reassignMessage, 'success');
            }
        }

        return true;
    }
}

if (!function_exists('paypalac_payflow_card_active_subscription_names')) {
    /**
     * Return product names of currently-scheduled subscriptions billed
     * against the given saved card.  Used to (a) decide whether deletion
     * needs to be blocked, and (b) tell the customer which subscriptions
     * are at stake.
     *
     * @return string[] product names; empty array means the card has no active subs.
     */
    function paypalac_payflow_card_active_subscription_names(int $card_id): array
    {
        global $db;

        if ($card_id <= 0) {
            return [];
        }

        $sql = "SELECT products_name
                FROM " . TABLE_SAVED_CREDIT_CARDS_RECURRING . "
                WHERE saved_credit_card_id = " . (int)$card_id . "
                  AND LOWER(TRIM(status)) = 'scheduled'
                ORDER BY saved_credit_card_recurring_id";

        $result = $db->Execute($sql);
        $names = [];
        while (!$result->EOF) {
            $name = trim((string)$result->fields['products_name']);
            if ($name !== '') {
                $names[] = $name;
            }
            $result->MoveNext();
        }
        return $names;
    }
}

if (!function_exists('paypalac_customer_has_other_usable_card')) {
    /**
     * True when the customer has at least one OTHER saved card that is not
     * soft-deleted and has not yet expired.  Used by the card deletion
     * guard to decide whether subscriptions can safely be reassigned to a
     * replacement card.
     *
     * Two storage models are checked:
     *   1. Legacy PayFlow cards: expiry stored in TABLE_SAVED_CREDIT_CARDS.expiry as 'mmYY'.
     *   2. PayPal AC vault cards: TABLE_SAVED_CREDIT_CARDS.expiry is NULL/empty;
     *      the real expiry lives in TABLE_PAYPAL_VAULT.expiry as 'YYYY-MM'.
     * Without the vault join the deletion guard would incorrectly block
     * customers whose only "other usable card" is a vault card (the new
     * normal post-PayFlow sunset).
     */
    function paypalac_customer_has_other_usable_card(int $customers_id, int $exclude_card_id): bool
    {
        return paypalac_replacement_card_id($customers_id, $exclude_card_id) > 0;
    }
}

if (!function_exists('paypalac_replacement_card_id')) {
    /**
     * Find the customer's "next-best" usable card excluding $exclude_card_id.
     * Returns saved_credit_card_id, or 0 if none qualify.  Vault-aware (see
     * paypalac_customer_has_other_usable_card() docblock).
     */
    function paypalac_replacement_card_id(int $customers_id, int $exclude_card_id): int
    {
        global $db;

        if ($customers_id <= 0) {
            return 0;
        }

        if (defined('TABLE_PAYPAL_VAULT')) {
            $sql = "SELECT scc.saved_credit_card_id
                    FROM " . TABLE_SAVED_CREDIT_CARDS . " scc
                    LEFT JOIN " . TABLE_PAYPAL_VAULT . " pv
                      ON pv.vault_id = scc.vault_id AND pv.customers_id = scc.customers_id
                    WHERE scc.customers_id = " . (int)$customers_id . "
                      AND scc.saved_credit_card_id <> " . (int)$exclude_card_id . "
                      AND scc.is_deleted = '0'
                      AND (
                        (scc.expiry IS NOT NULL AND scc.expiry <> ''
                         AND LAST_DAY(STR_TO_DATE(scc.expiry, '%m%y')) > CURDATE())
                        OR
                        (scc.vault_id IS NOT NULL AND scc.vault_id <> ''
                         AND pv.paypal_vault_id IS NOT NULL
                         AND (pv.expiry IS NULL OR pv.expiry = ''
                              OR LAST_DAY(STR_TO_DATE(pv.expiry, '%Y-%m')) > CURDATE()))
                      )
                    ORDER BY scc.is_primary, scc.saved_credit_card_id DESC
                    LIMIT 1";
        } else {
            $sql = "SELECT saved_credit_card_id
                    FROM " . TABLE_SAVED_CREDIT_CARDS . "
                    WHERE customers_id = " . (int)$customers_id . "
                      AND saved_credit_card_id <> " . (int)$exclude_card_id . "
                      AND is_deleted = '0'
                      AND expiry IS NOT NULL
                      AND expiry <> ''
                      AND LAST_DAY(STR_TO_DATE(expiry, '%m%y')) > CURDATE()
                    ORDER BY is_primary, saved_credit_card_id DESC
                    LIMIT 1";
        }

        $result = $db->Execute($sql);
        if ($result->EOF) {
            return 0;
        }
        return (int) $result->fields['saved_credit_card_id'];
    }
}

if (!function_exists('paypalac_render_card_field_containers')) {
    /**
     * Render PayPal CardFields hosted-field containers and initialisation script
     * for the "Add a new card" form on the saved credit cards account page.
     *
     * The PayPal SDK (loaded with the card-fields component via auto.paypaladvcheckout.php)
     * injects an iframe into each .ppac-card-field container.  On form submit the
     * JavaScript calls cardFields.submit(), which triggers createVaultSetupToken to
     * obtain a setup token (via ppac_add_card.php), collects the card data through
     * the hosted fields, and fires onApprove with the approved setup token ID.
     * The setup_token_id hidden field is then populated and the PHP form is submitted
     * so header_php.php can create the payment token.
     *
     * @param array $expiry_month_options Month options array (kept for API compatibility)
     * @param array $expiry_year_options  Year options array (kept for API compatibility)
     * @param array $add_form_values      Current form values (kept for API compatibility)
     * @return string HTML markup and inline script
     */
    function paypalac_render_card_field_containers(array $expiry_month_options, array $expiry_year_options, array $add_form_values): string
    {
        $addCardAjaxUrl = HTTP_SERVER . DIR_WS_CATALOG . 'ppac_add_card.php';

        $labelName    = defined('TEXT_ADD_CARD_CARDHOLDER')    ? TEXT_ADD_CARD_CARDHOLDER    : 'Name on card';
        $labelNumber  = defined('TEXT_ADD_CARD_CARD_NUMBER')   ? TEXT_ADD_CARD_CARD_NUMBER   : 'Card number';
        $labelExpiry  = defined('TEXT_ADD_CARD_EXPIRY')        ? TEXT_ADD_CARD_EXPIRY        : 'Expiration date';
        $labelCvv     = defined('TEXT_ADD_CARD_SECURITY_CODE') ? TEXT_ADD_CARD_SECURITY_CODE : 'Security code (CVV)';
        $msgError     = defined('TEXT_ADD_CARD_ERROR_GENERAL') ? TEXT_ADD_CARD_ERROR_GENERAL : 'We were unable to save your card. Please try again or contact us for help.';
        $msgProcess   = defined('TEXT_ADD_CARD_PROCESSING')    ? TEXT_ADD_CARD_PROCESSING    : 'Processing...';

        // Fallback SDK URL for templates that never fire NOTIFY_HTML_HEAD_* (so the
        // observer never injects #PayPalJSSDK). CardFields still needs card-fields.
        $sdkClientId = '';
        $sdkIsSandbox = defined('MODULE_PAYMENT_PAYPALAC_SERVER') && MODULE_PAYMENT_PAYPALAC_SERVER === 'sandbox';
        if ($sdkIsSandbox) {
            $sdkClientId = 'sb';
        } elseif (defined('MODULE_PAYMENT_PAYPALAC_CLIENTID_L') && MODULE_PAYMENT_PAYPALAC_CLIENTID_L !== '') {
            $sdkClientId = MODULE_PAYMENT_PAYPALAC_CLIENTID_L;
        } elseif (defined('MODULE_PAYMENT_PAYPALAC_CLIENTID_S') && MODULE_PAYMENT_PAYPALAC_CLIENTID_S !== '') {
            $sdkClientId = MODULE_PAYMENT_PAYPALAC_CLIENTID_S;
        }
        $sdkQuery = [
            'client-id' => $sdkClientId,
            'components' => 'card-fields',
            'integration-date' => '2025-08-01',
        ];
        if ($sdkIsSandbox) {
            $sdkQuery['debug'] = 'true';
            $sdkQuery['buyer-country'] = 'US';
            $sdkQuery['locale'] = 'en_US';
        }
        $sdkUrl = 'https://www.paypal.com/sdk/js?' . str_replace('%2C', ',', http_build_query($sdkQuery));
        $partnerAttributionId = '';
        if (class_exists('\\PayPalAdvancedCheckout\\Api\\PayPalAdvancedCheckoutApi')) {
            $partnerAttributionId = \PayPalAdvancedCheckout\Api\PayPalAdvancedCheckoutApi::PARTNER_ATTRIBUTION_ID;
        }

        ob_start();
        ?>
        <div class="ppac-vault-card-fields row g-3">
          <div class="col-12">
            <div class="ppac-card-field-group">
              <label class="form-label" for="ppac-card-name"><?php echo $labelName; ?></label>
              <div id="ppac-card-name" class="ppac-card-field ppr-creditcard-field"></div>
            </div>
          </div>
          <div class="col-12">
            <div class="ppac-card-field-group">
              <label class="form-label" for="ppac-card-number"><?php echo $labelNumber; ?></label>
              <div id="ppac-card-number" class="ppac-card-field ppr-creditcard-field"></div>
            </div>
          </div>
          <div class="col-12 col-md-6">
            <div class="ppac-card-field-group">
              <label class="form-label" for="ppac-card-expiry"><?php echo $labelExpiry; ?></label>
              <div id="ppac-card-expiry" class="ppac-card-field ppr-creditcard-field"></div>
            </div>
          </div>
          <div class="col-12 col-md-6">
            <div class="ppac-card-field-group">
              <label class="form-label" for="ppac-card-cvv"><?php echo $labelCvv; ?></label>
              <div id="ppac-card-cvv" class="ppac-card-field ppr-creditcard-field"></div>
            </div>
          </div>
        </div>
        <input type="hidden" name="setup_token_id" value="">
        <div id="ppac-add-card-error" class="alert alert-danger mt-3" style="display:none"></div>
        <script>
        (function () {
            'use strict';

            var AJAX_URL   = <?php echo json_encode($addCardAjaxUrl); ?>;
            var MSG_ERROR  = <?php echo json_encode($msgError); ?>;
            var MSG_PROCESS = <?php echo json_encode($msgProcess); ?>;
            var SDK_URL = <?php echo json_encode($sdkUrl); ?>;
            var SDK_PARTNER_ID = <?php echo json_encode($partnerAttributionId); ?>;
            var SDK_CLIENT_ID = <?php echo json_encode($sdkClientId); ?>;

            function showError(message) {
                var el = document.getElementById('ppac-add-card-error');
                if (el) {
                    el.textContent = message || MSG_ERROR;
                    el.style.display = '';
                }
            }

            function hideError() {
                var el = document.getElementById('ppac-add-card-error');
                if (el) {
                    el.style.display = 'none';
                }
            }

            function getBillingAddressPayload() {
                var modeRadio = document.querySelector('input[name="add_address_mode"]:checked');
                if (!modeRadio) {
                    return null;
                }

                if (modeRadio.value === 'existing') {
                    var sel = document.querySelector('[name="add_address_book_id"]');
                    if (!sel || !sel.value) {
                        return null;
                    }
                    return { address_book_id: parseInt(sel.value, 10) };
                }

                var street = (document.querySelector('[name="add_new_street_address"]') || {}).value || '';
                var city   = (document.querySelector('[name="add_new_city"]') || {}).value || '';
                var post   = (document.querySelector('[name="add_new_postcode"]') || {}).value || '';
                var countryEl = document.querySelector('[name="add_new_country_id"]');
                var iso2 = '';
                if (countryEl && countryEl.selectedIndex >= 0) {
                    iso2 = countryEl.options[countryEl.selectedIndex].getAttribute('data-iso2') || '';
                }

                var addr = {
                    address_line_1: street.trim(),
                    admin_area_2:   city.trim(),
                    postal_code:    post.trim(),
                    country_code:   iso2.trim().toUpperCase()
                };

                var street2 = (document.querySelector('[name="add_new_street_address_2"]') || {}).value || '';
                if (street2.trim()) {
                    addr.address_line_2 = street2.trim();
                }

                var state = (document.querySelector('[name="add_new_state"]') || {}).value || '';
                if (state.trim()) {
                    addr.admin_area_1 = state.trim();
                }

                return { billing_address: addr };
            }

            function initCardFields() {
                if (initCardFields.done) {
                    return;
                }
                if (typeof PayPalSDK === 'undefined' || typeof PayPalSDK.CardFields !== 'function') {
                    return;
                }

                var form = document.getElementById('add-card-form');
                if (!form) {
                    return;
                }
                initCardFields.done = true;

                var cardFields = PayPalSDK.CardFields({
                    // PayPal docs: style font/color here; size/border the container in CSS.
                    // Height/padding/line-height on the input fight the iframe layout and
                    // push placeholders onto the bottom border.
                    // Matches the global input[type="text"] look used by the plain HTML
                    // card fields on checkout. PayPal's iframe applies a default
                    // body { padding: 0.375rem } which stacks with any container
                    // padding and misaligns the text — zero body padding here, use 9px
                    // horizontal input padding to match checkout, and keep container padding at 0.
                    // Adjust colors/font-size below to match your theme's text inputs.
                    style: {
                        'body': {
                            'padding': '0',
                            'margin': '0'
                        },
                        'input': {
                            'font-size': '12px',
                            'font-family': 'inherit',
                            'font-weight': '400',
                            'color': '#4d4d4d',
                            'padding': '0 9px',
                            'border': 'none',
                            'outline': 'none',
                            'box-shadow': 'none',
                            'background-color': 'transparent'
                        },
                        ':focus': {
                            'color': '#4d4d4d',
                            'border': 'none',
                            'outline': 'none',
                            'box-shadow': 'none'
                        },
                        '.invalid': {
                            'color': '#c00'
                        }
                    },
                    createVaultSetupToken: function () {
                        var addressPayload = getBillingAddressPayload();
                        if (!addressPayload) {
                            return Promise.reject(new Error('billing_address_required'));
                        }

                        var body = JSON.stringify(Object.assign({ action: 'create_setup_token' }, addressPayload));

                        return fetch(AJAX_URL, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: body
                        })
                        .then(function (response) { return response.json(); })
                        .then(function (data) {
                            if (!data.success || !data.setup_token_id) {
                                return Promise.reject(new Error(data.message || 'setup_token_failed'));
                            }
                            return data.setup_token_id;
                        });
                    },

                    onApprove: function (data) {
                        var tokenInput = form.querySelector('[name="setup_token_id"]');
                        if (tokenInput) {
                            tokenInput.value = data.vaultSetupToken;
                        }
                        form.submit();
                    },

                    onError: function () {
                        showError(MSG_ERROR);
                        restoreSubmit();
                    }
                });

                if (!cardFields.isEligible()) {
                    return;
                }

                cardFields.NameField().render('#ppac-card-name');
                cardFields.NumberField().render('#ppac-card-number');
                cardFields.ExpiryField().render('#ppac-card-expiry');
                cardFields.CVVField().render('#ppac-card-cvv');

                var submitBtn = null;
                var originalText = '';

                function disableSubmit() {
                    submitBtn = form.querySelector('[type="submit"]');
                    if (submitBtn) {
                        originalText = submitBtn.textContent;
                        submitBtn.textContent = MSG_PROCESS;
                        submitBtn.disabled = true;
                    }
                }

                function restoreSubmit() {
                    if (submitBtn) {
                        submitBtn.textContent = originalText;
                        submitBtn.disabled = false;
                        submitBtn = null;
                    }
                }

                form.addEventListener('submit', function (event) {
                    event.preventDefault();
                    hideError();
                    disableSubmit();
                    cardFields.submit().catch(function () {
                        showError(MSG_ERROR);
                        restoreSubmit();
                    });
                });
            }

            function sdkHasCardFields() {
                return typeof PayPalSDK !== 'undefined' && typeof PayPalSDK.CardFields === 'function';
            }

            function waitForCardFields(onReady, onTimeout) {
                var attempts = 0;
                var maxAttempts = 100;
                var interval = setInterval(function () {
                    attempts++;
                    if (sdkHasCardFields()) {
                        clearInterval(interval);
                        onReady();
                    } else if (attempts >= maxAttempts) {
                        clearInterval(interval);
                        if (typeof onTimeout === 'function') {
                            onTimeout();
                        }
                    }
                }, 100);
            }

            function loadCardFieldsSdk(onReady) {
                if (!SDK_CLIENT_ID) {
                    showError(MSG_ERROR);
                    return;
                }

                var existing = document.getElementById('PayPalJSSDK')
                    || document.querySelector('script[data-paypal-sdk="true"]');

                if (existing) {
                    // Header SDK may omit card-fields; only reuse when CardFields is present.
                    if (sdkHasCardFields()) {
                        onReady();
                        return;
                    }
                    if (existing.src && existing.src.indexOf('card-fields') !== -1) {
                        existing.addEventListener('load', onReady);
                        waitForCardFields(onReady, function () { showError(MSG_ERROR); });
                        return;
                    }
                }

                var script = document.createElement('script');
                script.id = 'PayPalJSSDK';
                script.title = 'PayPalSDK';
                script.async = true;
                script.setAttribute('data-paypal-sdk', 'true');
                script.setAttribute('data-namespace', 'PayPalSDK');
                if (SDK_PARTNER_ID) {
                    script.setAttribute('data-partner-attribution-id', SDK_PARTNER_ID);
                }
                script.src = SDK_URL;
                script.addEventListener('load', function () {
                    script.dataset.loaded = 'true';
                    onReady();
                });
                script.addEventListener('error', function () {
                    showError(MSG_ERROR);
                });
                document.head.appendChild(script);
            }

            function ensureCardFieldsReady() {
                if (sdkHasCardFields()) {
                    initCardFields();
                    return;
                }

                var sdkScript = document.getElementById('PayPalJSSDK')
                    || document.querySelector('script[data-paypal-sdk="true"]');

                if (sdkScript && sdkScript.src && sdkScript.src.indexOf('card-fields') !== -1) {
                    if (sdkScript.dataset && sdkScript.dataset.loaded === 'true') {
                        initCardFields();
                    } else {
                        sdkScript.addEventListener('load', initCardFields);
                        waitForCardFields(initCardFields, function () { showError(MSG_ERROR); });
                    }
                    return;
                }

                loadCardFieldsSdk(initCardFields);
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', ensureCardFieldsReady);
            } else {
                ensureCardFieldsReady();
            }
        }());
        </script>
        <?php
        return ob_get_clean();
    }
}

$zco_notifier->notify('NOTIFY_HEADER_START_ACCOUNT_SAVED_CREDIT_CARDS');

if (empty($_SESSION['customer_id'])) {
    $_SESSION['navigation']->set_snapshot();
    zen_redirect(zen_href_link(FILENAME_LOGIN, '', 'SSL'));
}

require(DIR_WS_MODULES . zen_get_module_directory('require_languages.php'));

$customers_id = (int)$_SESSION['customer_id'];
$hide_saved_cards_page = true;
if (defined('MODULE_PAYMENT_PAYPALAC_STATUS') && MODULE_PAYMENT_PAYPALAC_STATUS === 'True') {
    $hide_saved_cards_page = false;
}

$saved_credit_cards = [];
$delete_card = null;
$edit_card = null;
$edit_card_errors = [];
$edit_form_values = [];
$add_card_mode = false;
$add_card_errors = [];
$add_form_values = [];
$address_book_options = [];
$country_dropdown = [];
$expiry_month_options = [];
$expiry_year_options = [];
$requested_edit_id = 0;

for ($month = 1; $month <= 12; $month++) {
    $value = str_pad((string)$month, 2, '0', STR_PAD_LEFT);
    $expiry_month_options[] = [
        'id' => $value,
        'text' => $value,
    ];
}

$currentYear = (int)date('Y');
for ($offset = 0; $offset <= 15; $offset++) {
    $yearValue = (string)($currentYear + $offset);
    $expiry_year_options[] = [
        'id' => $yearValue,
        'text' => $yearValue,
    ];
}

if ($hide_saved_cards_page === false) {
    require_once DIR_FS_CATALOG . 'includes/modules/payment/paypal/ppacAutoload.php';
    require_once DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/paypalac.php';

    $statusMap = paypalac_get_vault_status_map();
    $address_book_options = paypalac_fetch_customer_addresses($customers_id);

    global $db;
    $countryRecords = $db->Execute(
        "SELECT countries_id, countries_name, countries_iso_code_2" .
        "   FROM " . TABLE_COUNTRIES .
        "  ORDER BY countries_name"
    );
    if (is_object($countryRecords)) {
        while (!$countryRecords->EOF) {
            $country_dropdown[] = [
                'id' => (int)$countryRecords->fields['countries_id'],
                'text' => zen_output_string_protected($countryRecords->fields['countries_name']),
                'iso2' => strtoupper(trim((string)$countryRecords->fields['countries_iso_code_2'])),
            ];
            $countryRecords->MoveNext();
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        if ($action === 'delete-card') {
            if (!isset($_POST['securityToken']) || $_POST['securityToken'] !== $_SESSION['securityToken']) {
                $messageStack->add('saved_credit_cards', ERROR_SECURITY_TOKEN, 'error');
            } else {
                $paypal_vault_id = (int)zen_db_prepare_input($_POST['paypal_vault_id'] ?? 0);
                if ($paypal_vault_id <= 0) {
                    $messageStack->add('saved_credit_cards', TEXT_SAVED_CARD_MISSING, 'error');
                } else {
                    $rawCard = VaultManager::getCustomerVaultCard($customers_id, $paypal_vault_id);
                    if ($rawCard === null) {
                        $messageStack->add('saved_credit_cards', TEXT_SAVED_CARD_MISSING, 'error');
                    } else {
                        $api = new PayPalAdvancedCheckoutApi(MODULE_PAYMENT_PAYPALAC_SERVER);
                        $deleteResponse = $api->deleteVaultPaymentToken((string)$rawCard['vault_id']);
                        $deleteFailed = false;
                        if ($deleteResponse === false) {
                            $errorInfo = $api->getErrorInfo();
                            if (($errorInfo['errNum'] ?? 0) !== 404) {
                                $deleteFailed = true;
                            }
                        }

                        if ($deleteFailed === false) {
                            VaultManager::deleteCustomerVaultCard($customers_id, $paypal_vault_id);
                            $messageStack->add_session('saved_credit_cards', TEXT_DELETE_CARD_SUCCESS, 'success');
                            zen_redirect(zen_href_link(FILENAME_ACCOUNT_SAVED_CREDIT_CARDS, '', 'SSL'));
                        } else {
                            $messageStack->add('saved_credit_cards', TEXT_DELETE_CARD_ERROR, 'error');
                        }
                    }
                }
            }
        } elseif ($action === 'update-card') {
            if (!isset($_POST['securityToken']) || $_POST['securityToken'] !== $_SESSION['securityToken']) {
                $messageStack->add('saved_credit_cards', ERROR_SECURITY_TOKEN, 'error');
            } else {
                $paypal_vault_id = (int)zen_db_prepare_input($_POST['paypal_vault_id'] ?? 0);
                $requested_edit_id = $paypal_vault_id;
                if ($paypal_vault_id <= 0) {
                    $messageStack->add('saved_credit_cards', TEXT_SAVED_CARD_MISSING, 'error');
                } else {
                    $rawCard = VaultManager::getCustomerVaultCard($customers_id, $paypal_vault_id);
                    if ($rawCard === null) {
                        $messageStack->add('saved_credit_cards', TEXT_SAVED_CARD_MISSING, 'error');
                    } else {
                        $formValues = [
                            'cardholder_name' => trim((string)zen_db_prepare_input($_POST['cardholder_name'] ?? '')),
                            'expiry_month' => str_pad(trim((string)zen_db_prepare_input($_POST['expiry_month'] ?? '')), 2, '0', STR_PAD_LEFT),
                            'expiry_year' => substr(trim((string)zen_db_prepare_input($_POST['expiry_year'] ?? '')), -4),
                            'security_code' => trim((string)zen_db_prepare_input($_POST['security_code'] ?? '')),
                            'address_mode' => (string)zen_db_prepare_input($_POST['address_mode'] ?? ''),
                            'address_book_id' => (int)zen_db_prepare_input($_POST['address_book_id'] ?? 0),
                            'new_address' => [
                                'street_address' => trim((string)zen_db_prepare_input($_POST['new_street_address'] ?? '')),
                                'street_address_2' => trim((string)zen_db_prepare_input($_POST['new_street_address_2'] ?? '')),
                                'city' => trim((string)zen_db_prepare_input($_POST['new_city'] ?? '')),
                                'state' => trim((string)zen_db_prepare_input($_POST['new_state'] ?? '')),
                                'postcode' => trim((string)zen_db_prepare_input($_POST['new_postcode'] ?? '')),
                                'country_id' => (int)zen_db_prepare_input($_POST['new_country_id'] ?? 0),
                            ],
                        ];

                        if ($formValues['address_mode'] !== 'existing' && $formValues['address_mode'] !== 'new') {
                            $formValues['address_mode'] = (count($address_book_options) > 0) ? 'existing' : 'new';
                        }

                        $edit_form_values = $formValues;

                        $validationErrors = [];

                        if ($formValues['cardholder_name'] === '') {
                            $validationErrors[] = TEXT_EDIT_CARD_ERROR_CARDHOLDER;
                        }

                        $expiryMonth = $formValues['expiry_month'];
                        $expiryYear = $formValues['expiry_year'];
                        if ($expiryMonth === '' || $expiryYear === '') {
                            $validationErrors[] = TEXT_EDIT_CARD_ERROR_EXPIRY;
                        } elseif (!preg_match('/^(0[1-9]|1[0-2])$/', $expiryMonth) || !preg_match('/^\d{4}$/', $expiryYear)) {
                            $validationErrors[] = TEXT_EDIT_CARD_ERROR_EXPIRY;
                        }

                        if ($formValues['security_code'] !== '' && !preg_match('/^\d{3,4}$/', $formValues['security_code'])) {
                            $validationErrors[] = TEXT_EDIT_CARD_ERROR_SECURITY_CODE;
                        }

                        $billingAddress = [];
                        if ($formValues['address_mode'] === 'existing') {
                            if ($formValues['address_book_id'] <= 0) {
                                $validationErrors[] = TEXT_EDIT_CARD_ERROR_ADDRESS_SELECTION;
                            } else {
                                $addressEntry = paypalac_get_address_book_entry($customers_id, $formValues['address_book_id']);
                                if ($addressEntry === null) {
                                    $validationErrors[] = TEXT_EDIT_CARD_ERROR_ADDRESS_SELECTION;
                                } else {
                                    $billingAddress = paypalac_build_paypal_address_from_book($addressEntry);
                                }
                            }
                        } else {
                            $billingAddress = paypalac_build_paypal_address_from_form($formValues['new_address']);
                            if (empty($billingAddress) || empty($billingAddress['address_line_1'] ?? '') || empty($billingAddress['admin_area_2'] ?? '') || empty($billingAddress['postal_code'] ?? '') || empty($billingAddress['country_code'] ?? '')) {
                                $validationErrors[] = TEXT_EDIT_CARD_ERROR_ADDRESS_NEW;
                            }
                        }

                        if (!empty($validationErrors)) {
                            foreach ($validationErrors as $validationError) {
                                $messageStack->add('saved_credit_cards', $validationError, 'error');
                            }
                            $edit_card_errors = $validationErrors;
                        } else {
                            $patchOperations = [];

                            $cardholderOp = paypalac_build_patch_operation(
                                '/payment_source/card/name',
                                $formValues['cardholder_name'],
                                ($rawCard['cardholder_name'] ?? '') === '' ? 'add' : 'replace'
                            );
                            if ($formValues['cardholder_name'] !== (string)($rawCard['cardholder_name'] ?? '')) {
                                $patchOperations[] = $cardholderOp;
                            }

                            $expiryValue = $expiryYear . '-' . $expiryMonth;
                            if ($expiryValue !== (string)($rawCard['expiry'] ?? '')) {
                                $patchOperations[] = paypalac_build_patch_operation('/payment_source/card/expiry', $expiryValue, 'replace');
                            }

                            if ($formValues['security_code'] !== '') {
                                $patchOperations[] = paypalac_build_patch_operation('/payment_source/card/security_code', $formValues['security_code'], 'add');
                            }

                            if (!empty($billingAddress)) {
                                $existingBilling = $rawCard['billing_address'] ?? [];
                                $existingBillingFiltered = paypalac_filter_paypal_address_fields($existingBilling);
                                if ($billingAddress !== $existingBillingFiltered) {
                                    $patchOperations[] = paypalac_build_patch_operation('/payment_source/card/billing_address', $billingAddress, empty($existingBillingFiltered) ? 'add' : 'replace');
                                }
                            }

                            if (empty($patchOperations)) {
                                $messageStack->add('saved_credit_cards', TEXT_EDIT_CARD_NO_CHANGES, 'warning');
                            } else {
                                $api = new PayPalAdvancedCheckoutApi(MODULE_PAYMENT_PAYPALAC_SERVER);
                                $vaultId = (string)($rawCard['vault_id'] ?? '');
                                $updateResponse = $api->updateVaultPaymentToken($vaultId, $patchOperations);
                                if ($updateResponse === false) {
                                    $messageStack->add('saved_credit_cards', TEXT_EDIT_CARD_ERROR_GENERAL, 'error');
                                } else {
                                    $tokenDetails = $api->getVaultPaymentToken($vaultId);
                                    $updatedCard = null;
                                    if (is_array($tokenDetails)) {
                                        $updatedCard = VaultManager::updateFromVaultToken($customers_id, $paypal_vault_id, $tokenDetails);
                                    }

                                    if ($updatedCard === null) {
                                        $cardSource = $rawCard['card_data'] ?? [];
                                        if (!is_array($cardSource)) {
                                            $cardSource = [];
                                        }

                                        if ($formValues['cardholder_name'] !== '') {
                                            $cardSource['name'] = $formValues['cardholder_name'];
                                        }
                                        $cardSource['expiry'] = $expiryValue;
                                        if (!empty($billingAddress)) {
                                            $cardSource['billing_address'] = $billingAddress;
                                        }
                                        if (!isset($cardSource['brand']) && isset($rawCard['brand'])) {
                                            $cardSource['brand'] = $rawCard['brand'];
                                        }
                                        if (!isset($cardSource['last_digits']) && isset($rawCard['last_digits'])) {
                                            $cardSource['last_digits'] = $rawCard['last_digits'];
                                        }
                                        if (!isset($cardSource['type']) && isset($rawCard['card_type'])) {
                                            $cardSource['type'] = $rawCard['card_type'];
                                        }

                                        $vaultMeta = $rawCard['card_data']['vault'] ?? [];
                                        if (!is_array($vaultMeta)) {
                                            $vaultMeta = [];
                                        }
                                        if (isset($rawCard['vault_id'])) {
                                            $vaultMeta['id'] = $rawCard['vault_id'];
                                        }
                                        if (isset($rawCard['status'])) {
                                            $vaultMeta['status'] = $rawCard['status'];
                                        }

                                        $updatedCard = VaultManager::applyCardUpdate($customers_id, $paypal_vault_id, $cardSource, $vaultMeta);
                                    }

                                    if ($updatedCard === null) {
                                        $messageStack->add('saved_credit_cards', TEXT_EDIT_CARD_ERROR_GENERAL, 'error');
                                    } else {
                                        $messageStack->add_session('saved_credit_cards', TEXT_EDIT_CARD_SUCCESS, 'success');
                                        zen_redirect(zen_href_link(FILENAME_ACCOUNT_SAVED_CREDIT_CARDS, '', 'SSL'));
                                    }
                                }
                            }
                        }
                    }
                }
            }
        } elseif ($action === 'add-card') {
            if (!isset($_POST['securityToken']) || $_POST['securityToken'] !== $_SESSION['securityToken']) {
                $messageStack->add('saved_credit_cards', ERROR_SECURITY_TOKEN, 'error');
            } else {
                $setup_token_id = trim((string)zen_db_prepare_input($_POST['setup_token_id'] ?? ''));
                
                if ($setup_token_id === '') {
                    $messageStack->add('saved_credit_cards', TEXT_ADD_CARD_ERROR_GENERAL, 'error');
                } else {
                    // Create a payment token from the setup token
                    $api = new PayPalAdvancedCheckoutApi(MODULE_PAYMENT_PAYPALAC_SERVER);
                    $paymentTokenResponse = $api->createPaymentTokenFromSetup($setup_token_id);
                    
                    if ($paymentTokenResponse === false) {
                        $errorInfo = $api->getErrorInfo();
                        error_log('PayPal add card error: ' . print_r($errorInfo, true));
                        $messageStack->add('saved_credit_cards', TEXT_ADD_CARD_ERROR_GENERAL, 'error');
                    } else {
                        // Get the vault token details
                        $vaultId = $paymentTokenResponse['id'] ?? '';
                        if ($vaultId !== '') {
                            $tokenDetails = $api->getVaultPaymentToken($vaultId);
                            if (is_array($tokenDetails)) {
                                // Extract card info from payment source
                                $paymentSource = $tokenDetails['payment_source'] ?? [];
                                $cardSource = $paymentSource['card'] ?? [];
                                
                                if (!empty($cardSource)) {
                                    // Add vault metadata
                                    $cardSource['vault'] = [
                                        'id' => $vaultId,
                                        'status' => $tokenDetails['status'] ?? 'APPROVED',
                                    ];
                                    
                                    // Save to our database with orders_id = 0 (no associated order)
                                    $savedCard = VaultManager::saveVaultedCard($customers_id, 0, $cardSource, true);
                                    
                                    if ($savedCard !== null) {
                                        $messageStack->add_session('saved_credit_cards', TEXT_ADD_CARD_SUCCESS, 'success');
                                        zen_redirect(zen_href_link(FILENAME_ACCOUNT_SAVED_CREDIT_CARDS, '', 'SSL'));
                                    } else {
                                        $messageStack->add('saved_credit_cards', TEXT_ADD_CARD_ERROR_GENERAL, 'error');
                                    }
                                } else {
                                    $messageStack->add('saved_credit_cards', TEXT_ADD_CARD_ERROR_GENERAL, 'error');
                                }
                            } else {
                                $messageStack->add('saved_credit_cards', TEXT_ADD_CARD_ERROR_GENERAL, 'error');
                            }
                        } else {
                            $messageStack->add('saved_credit_cards', TEXT_ADD_CARD_ERROR_GENERAL, 'error');
                        }
                    }
                }
            }
        }
    }

    if (isset($_GET['add'])) {
        $add_card_mode = true;
    }

    // Handle Payflow card deletion
    if (isset($_GET['delete_payflow'])) {
        $requested_id = (int)zen_db_prepare_input($_GET['delete_payflow']);
        if ($requested_id > 0) {
            // Get the Payflow card for confirmation
            $payflowCards = paypalac_get_payflow_cards($customers_id);
            foreach ($payflowCards as $payflowCard) {
                if ((int)$payflowCard['saved_credit_card_id'] === $requested_id) {
                    $delete_card = paypalac_normalize_payflow_card($payflowCard);
                    $delete_card['confirm_action'] = 'delete_payflow_confirm';
                    break;
                }
            }
            if ($delete_card === null) {
                $messageStack->add('saved_credit_cards', TEXT_SAVED_CARD_MISSING, 'error');
            }
        }
    }

    // Handle Payflow card deletion confirmation
    if (isset($_POST['action']) && $_POST['action'] === 'delete_payflow_confirm') {
        if (!isset($_POST['securityToken']) || $_POST['securityToken'] !== $_SESSION['securityToken']) {
            $messageStack->add('saved_credit_cards', ERROR_SECURITY_TOKEN, 'error');
        } else {
            $payflow_card_id = (int)zen_db_prepare_input($_POST['payflow_card_id'] ?? 0);
            if ($payflow_card_id <= 0) {
                $messageStack->add('saved_credit_cards', TEXT_SAVED_CARD_MISSING, 'error');
            } else {
                // paypalac_delete_payflow_card() emits its own error messages via
                // $messageStack on failure (e.g. blocked-because-active-subscriptions),
                // so we only need to add the success message and redirect on true.
                if (paypalac_delete_payflow_card($customers_id, $payflow_card_id)) {
                    $messageStack->add_session('saved_credit_cards', TEXT_DELETE_CARD_SUCCESS, 'success');
                    zen_redirect(zen_href_link(FILENAME_ACCOUNT_SAVED_CREDIT_CARDS, '', 'SSL'));
                }
            }
        }
    }

    if (isset($_GET['delete'])) {
        $requested_id = (int)zen_db_prepare_input($_GET['delete']);
        if ($requested_id > 0) {
            $rawDeleteCard = VaultManager::getCustomerVaultCard($customers_id, $requested_id);
            if ($rawDeleteCard !== null) {
                $delete_card = paypalac_normalize_vault_card($rawDeleteCard, $statusMap);
            } else {
                $messageStack->add('saved_credit_cards', TEXT_SAVED_CARD_MISSING, 'error');
            }
        }
    }

    if ($requested_edit_id === 0 && isset($_GET['edit'])) {
        $requested_edit_id = (int)zen_db_prepare_input($_GET['edit']);
    }

    if ($requested_edit_id > 0) {
        $rawEditCard = VaultManager::getCustomerVaultCard($customers_id, $requested_edit_id);
        if ($rawEditCard === null) {
            $messageStack->add('saved_credit_cards', TEXT_SAVED_CARD_MISSING, 'error');
        } else {
            $edit_card = paypalac_normalize_vault_card($rawEditCard, $statusMap);
            $cardData = $rawEditCard['card_data'] ?? [];
            if (!is_array($cardData)) {
                $cardData = [];
            }

            if (empty($edit_form_values)) {
                $expiryParts = paypalac_split_vault_expiry((string)($rawEditCard['expiry'] ?? ''));
                $billingAddress = $rawEditCard['billing_address'] ?? [];
                if (!is_array($billingAddress) && isset($cardData['billing_address'])) {
                    $billingAddress = $cardData['billing_address'];
                }
                if (!is_array($billingAddress)) {
                    $billingAddress = [];
                }

                $defaultCountryId = null;
                if (!empty($billingAddress['country_code'] ?? '')) {
                    $defaultCountryId = paypalac_lookup_country_id_by_iso2((string)$billingAddress['country_code']);
                }

                $edit_form_values = [
                    'cardholder_name' => (string)($rawEditCard['cardholder_name'] ?? ($cardData['name'] ?? '')),
                    'expiry_month' => $expiryParts['month'],
                    'expiry_year' => $expiryParts['year'],
                    'security_code' => '',
                    'address_mode' => (count($address_book_options) > 0) ? 'existing' : 'new',
                    'address_book_id' => 0,
                    'new_address' => [
                        'street_address' => (string)($billingAddress['address_line_1'] ?? ''),
                        'street_address_2' => (string)($billingAddress['address_line_2'] ?? ''),
                        'city' => (string)($billingAddress['admin_area_2'] ?? ''),
                        'state' => (string)($billingAddress['admin_area_1'] ?? ''),
                        'postcode' => (string)($billingAddress['postal_code'] ?? ''),
                        'country_id' => $defaultCountryId ?? 0,
                    ],
                ];
            }
        }
    }

    // Get PayPal Vault cards the customer explicitly saved for reuse.
    $rawCards = VaultManager::getCustomerAccountSavedCards($customers_id);
    foreach ($rawCards as $rawCard) {
        $normalized = paypalac_normalize_vault_card($rawCard, $statusMap);
        if ($normalized['paypal_vault_id'] > 0) {
            $normalized['source'] = 'vault';
            $saved_credit_cards[] = $normalized;
        }
    }

    // Get Payflow cards if they exist
    $payflowCards = paypalac_get_payflow_cards($customers_id);
    foreach ($payflowCards as $payflowCard) {
        $saved_credit_cards[] = paypalac_normalize_payflow_card($payflowCard);
    }
}

$breadcrumb->add(NAVBAR_TITLE_1, zen_href_link(FILENAME_ACCOUNT, '', 'SSL'));
$breadcrumb->add(NAVBAR_TITLE_2, zen_href_link(FILENAME_ACCOUNT_SAVED_CREDIT_CARDS, '', 'SSL'));

$zco_notifier->notify('NOTIFY_HEADER_END_ACCOUNT_SAVED_CREDIT_CARDS');
