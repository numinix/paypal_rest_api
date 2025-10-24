<?php
use PayPalRestful\Api\PayPalRestfulApi;
use PayPalRestful\Common\VaultManager;

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

if ($hide_saved_cards_page === false) {
    require_once DIR_FS_CATALOG . 'includes/modules/payment/paypal/pprAutoload.php';
    require_once DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/paypalr.php';

    $statusMap = paypalr_get_vault_status_map();

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
