<?php
use PayPalRestful\Api\PayPalRestfulApi;
use PayPalRestful\Common\VaultManager;

if (!defined('FILENAME_ACCOUNT_SAVED_CREDIT_CARDS')) {
    define('FILENAME_ACCOUNT_SAVED_CREDIT_CARDS', 'account_saved_credit_cards');
}

if (!function_exists('paypalr_format_vault_expiry')) {
    function paypalr_format_vault_expiry(string $rawExpiry): string
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

if (!function_exists('paypalr_format_vault_date')) {
    function paypalr_format_vault_date(?string $rawDate): string
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

if (!function_exists('paypalr_format_vault_address')) {
    /**
     * @return string[]
     */
    function paypalr_format_vault_address(array $billingAddress): array
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

if (!function_exists('paypalr_split_vault_expiry')) {
    /**
     * @return array{month:string,year:string}
     */
    function paypalr_split_vault_expiry(string $rawExpiry): array
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

if (!function_exists('paypalr_lookup_country_iso2')) {
    function paypalr_lookup_country_iso2(int $countryId): string
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

if (!function_exists('paypalr_lookup_country_id_by_iso2')) {
    function paypalr_lookup_country_id_by_iso2(string $iso2): ?int
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

if (!function_exists('paypalr_lookup_zone_code')) {
    function paypalr_lookup_zone_code(int $zoneId): string
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

if (!function_exists('paypalr_filter_paypal_address_fields')) {
    function paypalr_filter_paypal_address_fields(array $address): array
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

if (!function_exists('paypalr_build_paypal_address_from_book')) {
    function paypalr_build_paypal_address_from_book(array $entry): array
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
            $zoneCode = paypalr_lookup_zone_code($zoneId);
            if ($zoneCode !== '') {
                $state = $zoneCode;
            }
        }
        if ($state !== '') {
            $address['admin_area_1'] = $state;
        }

        $countryId = (int)($entry['entry_country_id'] ?? 0);
        $countryCode = paypalr_lookup_country_iso2($countryId);
        if ($countryCode !== '') {
            $address['country_code'] = $countryCode;
        }

        return paypalr_filter_paypal_address_fields($address);
    }
}

if (!function_exists('paypalr_build_paypal_address_from_form')) {
    function paypalr_build_paypal_address_from_form(array $addressForm): array
    {
        $countryId = (int)($addressForm['country_id'] ?? 0);
        $countryCode = paypalr_lookup_country_iso2($countryId);

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

        return paypalr_filter_paypal_address_fields($address);
    }
}

if (!function_exists('paypalr_fetch_customer_addresses')) {
    /**
     * @return array<int,array{id:int,label:string}>
     */
    function paypalr_fetch_customer_addresses(int $customers_id): array
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

if (!function_exists('paypalr_get_address_book_entry')) {
    /**
     * @return array<string,mixed>|null
     */
    function paypalr_get_address_book_entry(int $customers_id, int $address_book_id): ?array
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

if (!function_exists('paypalr_build_patch_operation')) {
    /**
     * @param mixed $value
     *
     * @return array<string,mixed>
     */
    function paypalr_build_patch_operation(string $path, $value, string $operation = 'replace'): array
    {
        return [
            'op' => $operation,
            'path' => $path,
            'value' => $value,
        ];
    }
}

if (!function_exists('paypalr_get_vault_status_map')) {
    /**
     * @return array<string,array{0:string,1:string}>
     */
    function paypalr_get_vault_status_map(): array
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

if (!function_exists('paypalr_normalize_vault_card')) {
    /**
     * @param array<string,mixed> $record
     * @param array<string,array{0:string,1:string}> $statusMap
     *
     * @return array<string,mixed>
     */
    function paypalr_normalize_vault_card(array $record, array $statusMap): array
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
            'expiry' => paypalr_format_vault_expiry((string)($record['expiry'] ?? '')),
            'cardholder_name' => (string)($record['cardholder_name'] ?? ''),
            'status_label' => $statusLabel,
            'status_class' => $statusClass,
            'status_raw' => $statusKey,
            'last_used' => paypalr_format_vault_date($record['last_used'] ?? ''),
            'updated' => paypalr_format_vault_date($record['last_modified'] ?? ($record['update_time'] ?? '')),
            'created' => paypalr_format_vault_date($record['date_added'] ?? ''),
            'billing_address' => paypalr_format_vault_address($record['billing_address'] ?? []),
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

$zco_notifier->notify('NOTIFY_HEADER_START_ACCOUNT_SAVED_CREDIT_CARDS');

if (empty($_SESSION['customer_id'])) {
    $_SESSION['navigation']->set_snapshot();
    zen_redirect(zen_href_link(FILENAME_LOGIN, '', 'SSL'));
}

require(DIR_WS_MODULES . zen_get_module_directory('require_languages.php'));

$customers_id = (int)$_SESSION['customer_id'];
$hide_saved_cards_page = true;
if (defined('MODULE_PAYMENT_PAYPALR_STATUS') && MODULE_PAYMENT_PAYPALR_STATUS === 'True') {
    $hide_saved_cards_page = false;
}

$saved_credit_cards = [];
$delete_card = null;
$edit_card = null;
$edit_card_errors = [];
$edit_form_values = [];
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
    require_once DIR_FS_CATALOG . 'includes/modules/payment/paypal/pprAutoload.php';
    require_once DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/paypalr.php';

    $statusMap = paypalr_get_vault_status_map();
    $address_book_options = paypalr_fetch_customer_addresses($customers_id);

    global $db;
    $countryRecords = $db->Execute(
        "SELECT countries_id, countries_name" .
        "   FROM " . TABLE_COUNTRIES .
        "  ORDER BY countries_name"
    );
    if (is_object($countryRecords)) {
        while (!$countryRecords->EOF) {
            $country_dropdown[] = [
                'id' => (int)$countryRecords->fields['countries_id'],
                'text' => zen_output_string_protected($countryRecords->fields['countries_name']),
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
                        $api = new PayPalRestfulApi(MODULE_PAYMENT_PAYPALR_SERVER);
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
                                $addressEntry = paypalr_get_address_book_entry($customers_id, $formValues['address_book_id']);
                                if ($addressEntry === null) {
                                    $validationErrors[] = TEXT_EDIT_CARD_ERROR_ADDRESS_SELECTION;
                                } else {
                                    $billingAddress = paypalr_build_paypal_address_from_book($addressEntry);
                                }
                            }
                        } else {
                            $billingAddress = paypalr_build_paypal_address_from_form($formValues['new_address']);
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

                            $cardholderOp = paypalr_build_patch_operation(
                                '/payment_source/card/name',
                                $formValues['cardholder_name'],
                                ($rawCard['cardholder_name'] ?? '') === '' ? 'add' : 'replace'
                            );
                            if ($formValues['cardholder_name'] !== (string)($rawCard['cardholder_name'] ?? '')) {
                                $patchOperations[] = $cardholderOp;
                            }

                            $expiryValue = $expiryYear . '-' . $expiryMonth;
                            if ($expiryValue !== (string)($rawCard['expiry'] ?? '')) {
                                $patchOperations[] = paypalr_build_patch_operation('/payment_source/card/expiry', $expiryValue, 'replace');
                            }

                            if ($formValues['security_code'] !== '') {
                                $patchOperations[] = paypalr_build_patch_operation('/payment_source/card/security_code', $formValues['security_code'], 'add');
                            }

                            if (!empty($billingAddress)) {
                                $existingBilling = $rawCard['billing_address'] ?? [];
                                $existingBillingFiltered = paypalr_filter_paypal_address_fields($existingBilling);
                                if ($billingAddress !== $existingBillingFiltered) {
                                    $patchOperations[] = paypalr_build_patch_operation('/payment_source/card/billing_address', $billingAddress, empty($existingBillingFiltered) ? 'add' : 'replace');
                                }
                            }

                            if (empty($patchOperations)) {
                                $messageStack->add('saved_credit_cards', TEXT_EDIT_CARD_NO_CHANGES, 'warning');
                            } else {
                                $api = new PayPalRestfulApi(MODULE_PAYMENT_PAYPALR_SERVER);
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
        }
    }

    if (isset($_GET['delete'])) {
        $requested_id = (int)zen_db_prepare_input($_GET['delete']);
        if ($requested_id > 0) {
            $rawDeleteCard = VaultManager::getCustomerVaultCard($customers_id, $requested_id);
            if ($rawDeleteCard !== null) {
                $delete_card = paypalr_normalize_vault_card($rawDeleteCard, $statusMap);
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
            $edit_card = paypalr_normalize_vault_card($rawEditCard, $statusMap);
            $cardData = $rawEditCard['card_data'] ?? [];
            if (!is_array($cardData)) {
                $cardData = [];
            }

            if (empty($edit_form_values)) {
                $expiryParts = paypalr_split_vault_expiry((string)($rawEditCard['expiry'] ?? ''));
                $billingAddress = $rawEditCard['billing_address'] ?? [];
                if (!is_array($billingAddress) && isset($cardData['billing_address'])) {
                    $billingAddress = $cardData['billing_address'];
                }
                if (!is_array($billingAddress)) {
                    $billingAddress = [];
                }

                $defaultCountryId = null;
                if (!empty($billingAddress['country_code'] ?? '')) {
                    $defaultCountryId = paypalr_lookup_country_id_by_iso2((string)$billingAddress['country_code']);
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

    $rawCards = VaultManager::getCustomerVaultedCards($customers_id, false);
    foreach ($rawCards as $rawCard) {
        $normalized = paypalr_normalize_vault_card($rawCard, $statusMap);
        if ($normalized['paypal_vault_id'] > 0) {
            $saved_credit_cards[] = $normalized;
        }
    }
}

$breadcrumb->add(NAVBAR_TITLE_1, zen_href_link(FILENAME_ACCOUNT, '', 'SSL'));
$breadcrumb->add(NAVBAR_TITLE_2, zen_href_link(FILENAME_ACCOUNT_SAVED_CREDIT_CARDS, '', 'SSL'));

$zco_notifier->notify('NOTIFY_HEADER_END_ACCOUNT_SAVED_CREDIT_CARDS');
