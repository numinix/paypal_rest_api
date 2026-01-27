<?php
use PayPalRestful\Api\PayPalRestfulApi;
use PayPalRestful\Common\SubscriptionManager;
use PayPalRestful\Common\VaultManager;

if (!defined('FILENAME_ACCOUNT_PAYPAL_SUBSCRIPTIONS')) {
    define('FILENAME_ACCOUNT_PAYPAL_SUBSCRIPTIONS', 'account_paypal_subscriptions');
}

if (!function_exists('paypalr_subscription_fetch_record')) {
    function paypalr_subscription_fetch_record(int $customersId, int $subscriptionId): ?array
    {
        if ($customersId <= 0 || $subscriptionId <= 0) {
            return null;
        }

        global $db;

        $record = $db->Execute(
            'SELECT *'
            . ' FROM ' . TABLE_PAYPAL_SUBSCRIPTIONS
            . ' WHERE paypal_subscription_id = ' . (int) $subscriptionId
            . '   AND customers_id = ' . (int) $customersId
            . ' LIMIT 1'
        );

        if (!is_object($record) || $record->EOF) {
            return null;
        }

        return $record->fields;
    }
}

if (!function_exists('paypalr_subscription_decode_attributes')) {
    /**
     * @return array<string,mixed>
     */
    function paypalr_subscription_decode_attributes($rawAttributes): array
    {
        if (is_string($rawAttributes) && trim($rawAttributes) !== '') {
            $decoded = json_decode($rawAttributes, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }
}

if (!function_exists('paypalr_subscription_extract_remote_id')) {
    /**
     * @param array<string,mixed> $record
     * @param array<string,mixed> $attributes
     */
    function paypalr_subscription_extract_remote_id(array $record, array $attributes): string
    {
        if (!empty($record['vault_id']) && is_string($record['vault_id'])) {
            // Some stores reuse the vault_id column for the PayPal subscription identifier
            // when a vaulted card is not yet linked. Fall through to attributes otherwise.
        }

        $candidateKeys = [
            'paypal_subscription_id',
            'paypal_subscription',
            'subscription_id',
            'subscriptionid',
            'paypal_billing_agreement',
            'billing_agreement_id',
        ];

        foreach ($candidateKeys as $key) {
            if (!empty($attributes[$key]) && is_scalar($attributes[$key])) {
                return trim((string) $attributes[$key]);
            }
        }

        if (!empty($record['vault_id']) && is_string($record['vault_id'])) {
            return trim((string) $record['vault_id']);
        }

        return '';
    }
}

if (!function_exists('paypalr_subscription_get_status_map')) {
    /**
     * @return array<string,array{label:string,class:string}>
     */
    function paypalr_subscription_get_status_map(): array
    {
        return [
            'pending' => [
                'label' => TEXT_SUBSCRIPTION_STATUS_PENDING,
                'class' => 'is-warning',
            ],
            'awaiting_vault' => [
                'label' => TEXT_SUBSCRIPTION_STATUS_AWAITING_VAULT,
                'class' => 'is-warning',
            ],
            'scheduled' => [
                'label' => TEXT_SUBSCRIPTION_STATUS_SCHEDULED,
                'class' => 'is-active',
            ],
            'active' => [
                'label' => TEXT_SUBSCRIPTION_STATUS_ACTIVE,
                'class' => 'is-active',
            ],
            'paused' => [
                'label' => TEXT_SUBSCRIPTION_STATUS_PAUSED,
                'class' => 'is-warning',
            ],
            'suspended' => [
                'label' => TEXT_SUBSCRIPTION_STATUS_PAUSED,
                'class' => 'is-warning',
            ],
            'cancelled' => [
                'label' => TEXT_SUBSCRIPTION_STATUS_CANCELLED,
                'class' => 'is-inactive',
            ],
            'canceled' => [
                'label' => TEXT_SUBSCRIPTION_STATUS_CANCELLED,
                'class' => 'is-inactive',
            ],
            'complete' => [
                'label' => TEXT_SUBSCRIPTION_STATUS_COMPLETE,
                'class' => 'is-inactive',
            ],
            'completed' => [
                'label' => TEXT_SUBSCRIPTION_STATUS_COMPLETE,
                'class' => 'is-inactive',
            ],
            'failed' => [
                'label' => TEXT_SUBSCRIPTION_STATUS_FAILED,
                'class' => 'is-inactive',
            ],
        ];
    }
}

if (!function_exists('paypalr_subscription_format_status')) {
    /**
     * @return array{label:string,class:string}
     */
    function paypalr_subscription_format_status(string $status): array
    {
        $status = strtolower(trim($status));
        $map = paypalr_subscription_get_status_map();

        if (isset($map[$status])) {
            return $map[$status];
        }

        $label = ucwords(str_replace(['_', '-'], ' ', $status));
        if ($label === '') {
            $label = TEXT_SUBSCRIPTION_STATUS_UNKNOWN;
        }

        return [
            'label' => $label,
            'class' => 'is-warning',
        ];
    }
}

if (!function_exists('paypalr_subscription_period_labels')) {
    /**
     * @return array<string,array{singular:string,plural:string}>
     */
    function paypalr_subscription_period_labels(): array
    {
        return [
            'DAY' => [
                'singular' => TEXT_SUBSCRIPTION_PERIOD_DAY,
                'plural' => TEXT_SUBSCRIPTION_PERIOD_DAY_PLURAL,
            ],
            'WEEK' => [
                'singular' => TEXT_SUBSCRIPTION_PERIOD_WEEK,
                'plural' => TEXT_SUBSCRIPTION_PERIOD_WEEK_PLURAL,
            ],
            'MONTH' => [
                'singular' => TEXT_SUBSCRIPTION_PERIOD_MONTH,
                'plural' => TEXT_SUBSCRIPTION_PERIOD_MONTH_PLURAL,
            ],
            'YEAR' => [
                'singular' => TEXT_SUBSCRIPTION_PERIOD_YEAR,
                'plural' => TEXT_SUBSCRIPTION_PERIOD_YEAR_PLURAL,
            ],
            'SEMI_MONTH' => [
                'singular' => TEXT_SUBSCRIPTION_PERIOD_SEMI_MONTH,
                'plural' => TEXT_SUBSCRIPTION_PERIOD_SEMI_MONTH_PLURAL,
            ],
        ];
    }
}

if (!function_exists('paypalr_subscription_interval_text')) {
    function paypalr_subscription_interval_text(int $frequency, string $period): string
    {
        $period = strtoupper(trim($period));
        $labels = paypalr_subscription_period_labels();
        $label = $period;

        if (isset($labels[$period])) {
            $label = ($frequency === 1) ? $labels[$period]['singular'] : $labels[$period]['plural'];
        } else {
            $label = strtolower(str_replace('_', ' ', $period));
            $label = ($frequency === 1) ? $label : $label . 's';
        }

        return sprintf(TEXT_SUBSCRIPTION_INTERVAL_TEMPLATE, $frequency, $label);
    }
}

if (!function_exists('paypalr_subscription_schedule_text')) {
    function paypalr_subscription_schedule_text(int $frequency, string $period): string
    {
        if ($frequency <= 0 || $period === '') {
            return '';
        }

        $interval = paypalr_subscription_interval_text($frequency, $period);
        return sprintf(TEXT_SUBSCRIPTION_SCHEDULE_TEMPLATE, $interval);
    }
}

if (!function_exists('paypalr_subscription_total_cycles_text')) {
    function paypalr_subscription_total_cycles_text(int $totalCycles): string
    {
        if ($totalCycles <= 0) {
            return TEXT_SUBSCRIPTION_TOTAL_CYCLES_INFINITE;
        }

        if ($totalCycles === 1) {
            return TEXT_SUBSCRIPTION_TOTAL_CYCLES_SINGLE;
        }

        return sprintf(TEXT_SUBSCRIPTION_TOTAL_CYCLES_PLURAL, $totalCycles);
    }
}

if (!function_exists('paypalr_subscription_trial_text')) {
    function paypalr_subscription_trial_text(int $frequency, string $period, int $totalCycles): string
    {
        if ($frequency <= 0 || $period === '' || $totalCycles <= 0) {
            return '';
        }

        $interval = paypalr_subscription_interval_text($frequency, $period);
        return sprintf(TEXT_SUBSCRIPTION_TRIAL_TEMPLATE, $interval, $totalCycles);
    }
}

if (!function_exists('paypalr_subscription_format_datetime')) {
    function paypalr_subscription_format_datetime(?string $raw): string
    {
        if ($raw === null || trim($raw) === '') {
            return '';
        }

        $timestamp = strtotime($raw);
        if ($timestamp === false) {
            return '';
        }

        return date(TEXT_SUBSCRIPTION_DATETIME_FORMAT, $timestamp);
    }
}

if (!function_exists('paypalr_subscription_format_amount')) {
    function paypalr_subscription_format_amount($amount, string $currencyCode, $currencyValue = null): string
    {
        $amount = (float) $amount;
        $currencyCode = strtoupper(trim($currencyCode));

        global $currencies;
        if (is_object($currencies)) {
            return $currencies->format($amount, true, $currencyCode ?: null, $currencyValue ?? 1.0);
        }

        return ($currencyCode !== '' ? $currencyCode . ' ' : '') . number_format($amount, 2);
    }
}

if (!function_exists('paypalr_subscription_get_vault_status_map')) {
    /**
     * @return array<string,array{label:string,class:string}>
     */
    function paypalr_subscription_get_vault_status_map(): array
    {
        return [
            'ACTIVE' => [TEXT_SUBSCRIPTION_VAULT_STATUS_ACTIVE, 'is-active'],
            'APPROVED' => [TEXT_SUBSCRIPTION_VAULT_STATUS_ACTIVE, 'is-active'],
            'VAULTED' => [TEXT_SUBSCRIPTION_VAULT_STATUS_ACTIVE, 'is-active'],
            'INACTIVE' => [TEXT_SUBSCRIPTION_VAULT_STATUS_INACTIVE, 'is-inactive'],
            'CANCELLED' => [TEXT_SUBSCRIPTION_VAULT_STATUS_CANCELLED, 'is-inactive'],
            'CANCELED' => [TEXT_SUBSCRIPTION_VAULT_STATUS_CANCELLED, 'is-inactive'],
            'DELETED' => [TEXT_SUBSCRIPTION_VAULT_STATUS_CANCELLED, 'is-inactive'],
            'EXPIRED' => [TEXT_SUBSCRIPTION_VAULT_STATUS_EXPIRED, 'is-inactive'],
            'SUSPENDED' => [TEXT_SUBSCRIPTION_VAULT_STATUS_SUSPENDED, 'is-warning'],
            'PENDING' => [TEXT_SUBSCRIPTION_VAULT_STATUS_PENDING, 'is-warning'],
            'UNKNOWN' => [TEXT_SUBSCRIPTION_VAULT_STATUS_UNKNOWN, 'is-warning'],
        ];
    }
}

if (!function_exists('paypalr_subscription_format_vault_summary')) {
    /**
     * @param array<string,mixed>|null $card
     * @return array{label:string,status_label:string,status_class:string}
     */
    function paypalr_subscription_format_vault_summary(?array $card): array
    {
        if ($card === null) {
            return [
                'label' => TEXT_SUBSCRIPTION_PAYMENT_METHOD_NONE_SELECTED,
                'status_label' => TEXT_SUBSCRIPTION_VAULT_STATUS_UNKNOWN,
                'status_class' => 'is-warning',
            ];
        }

        $status = strtoupper(trim((string) ($card['status'] ?? '')));
        $map = paypalr_subscription_get_vault_status_map();
        $statusMeta = $map[$status] ?? $map['UNKNOWN'];

        $labelParts = [];
        if (!empty($card['brand'])) {
            $labelParts[] = $card['brand'];
        }
        if (!empty($card['last_digits'])) {
            $labelParts[] = sprintf(TEXT_SUBSCRIPTION_CARD_ENDING_IN, $card['last_digits']);
        }
        if (!empty($card['expiry'])) {
            $labelParts[] = sprintf(TEXT_SUBSCRIPTION_CARD_EXPIRY, $card['expiry']);
        }

        $label = trim(implode(' · ', $labelParts));
        if ($label === '') {
            $label = TEXT_SUBSCRIPTION_PAYMENT_METHOD_UNKNOWN;
        }

        return [
            'label' => $label,
            'status_label' => $statusMeta['label'],
            'status_class' => $statusMeta['class'],
        ];
    }
}

if (!function_exists('paypalr_subscription_format_api_error')) {
    /**
     * @param array<string,mixed> $errorInfo
     */
    function paypalr_subscription_format_api_error(array $errorInfo): string
    {
        $parts = [];

        foreach (['message', 'errMsg'] as $key) {
            if (!empty($errorInfo[$key]) && is_string($errorInfo[$key])) {
                $parts[] = trim($errorInfo[$key]);
                break;
            }
        }

        if (!empty($errorInfo['details']) && is_array($errorInfo['details'])) {
            foreach ($errorInfo['details'] as $detail) {
                if (is_array($detail) && !empty($detail['description'])) {
                    $parts[] = trim((string) $detail['description']);
                }
            }
        }

        if (!empty($errorInfo['debug_id'])) {
            $parts[] = 'Debug ID: ' . trim((string) $errorInfo['debug_id']);
        }

        $message = trim(implode(' ', array_filter($parts)));
        return ($message !== '') ? $message : TEXT_SUBSCRIPTION_API_GENERIC_ERROR;
    }
}

if (!function_exists('paypalr_subscription_remote_status_text')) {
    function paypalr_subscription_remote_status_text(string $status): string
    {
        $status = strtolower(trim($status));
        if ($status === '') {
            return TEXT_SUBSCRIPTION_STATUS_UNKNOWN;
        }

        $map = paypalr_subscription_get_status_map();
        if (isset($map[$status])) {
            return $map[$status]['label'];
        }

        $status = str_replace(['_', '-'], ' ', $status);
        return ucwords($status);
    }
}

$zco_notifier->notify('NOTIFY_HEADER_START_ACCOUNT_PAYPAL_SUBSCRIPTIONS');

if (empty($_SESSION['customer_id'])) {
    $_SESSION['navigation']->set_snapshot();
    zen_redirect(zen_href_link(FILENAME_LOGIN, '', 'SSL'));
}

require DIR_WS_MODULES . zen_get_module_directory('require_languages.php');

$customersId = (int) $_SESSION['customer_id'];
$hideSubscriptionsPage = true;
if (defined('MODULE_PAYMENT_PAYPALR_STATUS') && MODULE_PAYMENT_PAYPALR_STATUS === 'True') {
    $hideSubscriptionsPage = false;
}

$paypal_subscriptions = [];
$paypal_subscriptions_manage_cards_url = '';
$paypal_subscriptions_saved_cards_available = false;
$paypal_subscriptions_allow_api = false;
$remoteCache = [];

if ($hideSubscriptionsPage === false) {
    require_once DIR_FS_CATALOG . 'includes/modules/payment/paypal/pprAutoload.php';
    require_once DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/paypalr.php';

    SubscriptionManager::ensureSchema();
    VaultManager::ensureSchema();

    $savedCards = VaultManager::getCustomerVaultedCards($customersId, false);
    $vaultOptions = [];
    $vaultCardsById = [];
    foreach ($savedCards as $card) {
        $vaultId = (int) ($card['paypal_vault_id'] ?? 0);
        if ($vaultId <= 0) {
            continue;
        }

        $vaultCardsById[$vaultId] = $card;

        $summary = paypalr_subscription_format_vault_summary($card);
        $vaultOptions[] = [
            'id' => $vaultId,
            'text' => zen_output_string_protected($summary['label']),
        ];
    }

    $paypal_subscriptions_saved_cards_available = !empty($vaultOptions);
    if (defined('FILENAME_ACCOUNT_SAVED_CREDIT_CARDS')) {
        $paypal_subscriptions_manage_cards_url = zen_href_link(FILENAME_ACCOUNT_SAVED_CREDIT_CARDS, '', 'SSL');
    }

    $api = null;
    if (defined('MODULE_PAYMENT_PAYPALR_SERVER')) {
        $paypal_subscriptions_allow_api = true;
        $api = new PayPalRestfulApi(MODULE_PAYMENT_PAYPALR_SERVER);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = trim((string) ($_POST['action'] ?? ''));
        if ($action !== '') {
            if (!isset($_POST['securityToken']) || $_POST['securityToken'] !== $_SESSION['securityToken']) {
                $messageStack->add_session('paypal_subscriptions', ERROR_SECURITY_TOKEN, 'error');
                zen_redirect(zen_href_link(FILENAME_ACCOUNT_PAYPAL_SUBSCRIPTIONS, '', 'SSL'));
            }

            $subscriptionId = (int) zen_db_prepare_input($_POST['paypal_subscription_id'] ?? 0);
            $subscriptionRecord = paypalr_subscription_fetch_record($customersId, $subscriptionId);
            if ($subscriptionRecord === null) {
                $messageStack->add_session('paypal_subscriptions', TEXT_SUBSCRIPTION_NOT_FOUND, 'error');
                zen_redirect(zen_href_link(FILENAME_ACCOUNT_PAYPAL_SUBSCRIPTIONS, '', 'SSL'));
            }

            $attributes = paypalr_subscription_decode_attributes($subscriptionRecord['attributes'] ?? '');
            $remoteId = paypalr_subscription_extract_remote_id($subscriptionRecord, $attributes);
            $redirectUrl = zen_href_link(FILENAME_ACCOUNT_PAYPAL_SUBSCRIPTIONS, '', 'SSL');
            $now = date('Y-m-d H:i:s');

            switch ($action) {
                case 'update-vault':
                    $selectedVaultId = (int) zen_db_prepare_input($_POST['paypal_vault_id'] ?? 0);
                    if ($selectedVaultId > 0) {
                        $vaultCard = VaultManager::getCustomerVaultCard($customersId, $selectedVaultId);
                        if ($vaultCard === null) {
                            $messageStack->add_session('paypal_subscriptions', TEXT_SUBSCRIPTION_PAYMENT_METHOD_ERROR, 'error');
                        } else {
                            $updateData = [
                                'paypal_vault_id' => (int) $vaultCard['paypal_vault_id'],
                                'vault_id' => (string) ($vaultCard['vault_id'] ?? ''),
                                'last_modified' => $now,
                            ];
                            zen_db_perform(
                                TABLE_PAYPAL_SUBSCRIPTIONS,
                                $updateData,
                                'update',
                                'paypal_subscription_id = ' . (int) $subscriptionRecord['paypal_subscription_id']
                            );
                            $messageStack->add_session('paypal_subscriptions', TEXT_SUBSCRIPTION_PAYMENT_METHOD_UPDATED, 'success');
                        }
                    } else {
                        $updateData = [
                            'paypal_vault_id' => 0,
                            'vault_id' => '',
                            'last_modified' => $now,
                        ];
                        zen_db_perform(
                            TABLE_PAYPAL_SUBSCRIPTIONS,
                            $updateData,
                            'update',
                            'paypal_subscription_id = ' . (int) $subscriptionRecord['paypal_subscription_id']
                        );
                        $messageStack->add_session('paypal_subscriptions', TEXT_SUBSCRIPTION_PAYMENT_METHOD_UNLINKED, 'success');
                    }
                    zen_redirect($redirectUrl);
                    break;

                case 'cancel-subscription':
                    if ($api === null || $remoteId === '') {
                        $messageStack->add_session('paypal_subscriptions', TEXT_SUBSCRIPTION_NO_REMOTE_ID, 'error');
                        zen_redirect($redirectUrl);
                    }

                    $result = $api->cancelSubscription($remoteId, TEXT_SUBSCRIPTION_CANCEL_NOTE);
                    if ($result === false) {
                        $message = paypalr_subscription_format_api_error($api->getErrorInfo());
                        $messageStack->add_session('paypal_subscriptions', sprintf(TEXT_SUBSCRIPTION_CANCEL_ERROR, zen_output_string_protected($message)), 'error');
                    } else {
                        zen_db_perform(
                            TABLE_PAYPAL_SUBSCRIPTIONS,
                            [
                                'status' => 'cancelled',
                                'last_modified' => $now,
                            ],
                            'update',
                            'paypal_subscription_id = ' . (int) $subscriptionRecord['paypal_subscription_id']
                        );
                        $messageStack->add_session('paypal_subscriptions', TEXT_SUBSCRIPTION_CANCEL_SUCCESS, 'success');
                    }
                    zen_redirect($redirectUrl);
                    break;

                case 'suspend-subscription':
                    if ($api === null || $remoteId === '') {
                        $messageStack->add_session('paypal_subscriptions', TEXT_SUBSCRIPTION_NO_REMOTE_ID, 'error');
                        zen_redirect($redirectUrl);
                    }

                    $result = $api->suspendSubscription($remoteId, TEXT_SUBSCRIPTION_SUSPEND_NOTE);
                    if ($result === false) {
                        $message = paypalr_subscription_format_api_error($api->getErrorInfo());
                        $messageStack->add_session('paypal_subscriptions', sprintf(TEXT_SUBSCRIPTION_SUSPEND_ERROR, zen_output_string_protected($message)), 'error');
                    } else {
                        zen_db_perform(
                            TABLE_PAYPAL_SUBSCRIPTIONS,
                            [
                                'status' => 'paused',
                                'last_modified' => $now,
                            ],
                            'update',
                            'paypal_subscription_id = ' . (int) $subscriptionRecord['paypal_subscription_id']
                        );
                        $messageStack->add_session('paypal_subscriptions', TEXT_SUBSCRIPTION_SUSPEND_SUCCESS, 'success');
                    }
                    zen_redirect($redirectUrl);
                    break;

                case 'activate-subscription':
                    if ($api === null || $remoteId === '') {
                        $messageStack->add_session('paypal_subscriptions', TEXT_SUBSCRIPTION_NO_REMOTE_ID, 'error');
                        zen_redirect($redirectUrl);
                    }

                    $result = $api->activateSubscription($remoteId, TEXT_SUBSCRIPTION_RESUME_NOTE);
                    if ($result === false) {
                        $message = paypalr_subscription_format_api_error($api->getErrorInfo());
                        $messageStack->add_session('paypal_subscriptions', sprintf(TEXT_SUBSCRIPTION_RESUME_ERROR, zen_output_string_protected($message)), 'error');
                    } else {
                        zen_db_perform(
                            TABLE_PAYPAL_SUBSCRIPTIONS,
                            [
                                'status' => 'active',
                                'last_modified' => $now,
                            ],
                            'update',
                            'paypal_subscription_id = ' . (int) $subscriptionRecord['paypal_subscription_id']
                        );
                        $messageStack->add_session('paypal_subscriptions', TEXT_SUBSCRIPTION_RESUME_SUCCESS, 'success');
                    }
                    zen_redirect($redirectUrl);
                    break;

                case 'refresh-subscription':
                    if ($api === null || $remoteId === '') {
                        $messageStack->add_session('paypal_subscriptions', TEXT_SUBSCRIPTION_NO_REMOTE_ID, 'error');
                        zen_redirect($redirectUrl);
                    }

                    $details = $api->getSubscription($remoteId);
                    if ($details === false) {
                        $message = paypalr_subscription_format_api_error($api->getErrorInfo());
                        $messageStack->add_session('paypal_subscriptions', sprintf(TEXT_SUBSCRIPTION_REFRESH_ERROR, zen_output_string_protected($message)), 'error');
                    } else {
                        $remoteStatus = strtolower((string) ($details['status'] ?? ''));
                        $updateData = [
                            'last_modified' => $now,
                        ];
                        if ($remoteStatus !== '') {
                            $updateData['status'] = $remoteStatus;
                        }
                        zen_db_perform(
                            TABLE_PAYPAL_SUBSCRIPTIONS,
                            $updateData,
                            'update',
                            'paypal_subscription_id = ' . (int) $subscriptionRecord['paypal_subscription_id']
                        );

                        $remoteCache[$remoteId] = [
                            'success' => true,
                            'data' => $details,
                        ];

                        $messageStack->add_session('paypal_subscriptions', TEXT_SUBSCRIPTION_REFRESH_SUCCESS, 'success');
                    }
                    zen_redirect($redirectUrl);
                    break;
            }
        }
    }

    global $db;
    $records = $db->Execute(
        'SELECT *'
        . ' FROM ' . TABLE_PAYPAL_SUBSCRIPTIONS
        . ' WHERE customers_id = ' . (int) $customersId
        . ' ORDER BY date_added DESC, paypal_subscription_id DESC'
    );

    if ($records instanceof queryFactoryResult && $records->RecordCount() > 0) {
        while (!$records->EOF) {
            $record = $records->fields;
            $attributes = paypalr_subscription_decode_attributes($record['attributes'] ?? '');
            $remoteId = paypalr_subscription_extract_remote_id($record, $attributes);

            $statusMeta = paypalr_subscription_format_status((string) ($record['status'] ?? ''));
            $amountDisplay = paypalr_subscription_format_amount(
                $record['amount'] ?? 0,
                $record['currency_code'] ?? '',
                $record['currency_value'] ?? null
            );
            $scheduleText = paypalr_subscription_schedule_text((int) ($record['billing_frequency'] ?? 0), (string) ($record['billing_period'] ?? ''));
            $totalCyclesText = paypalr_subscription_total_cycles_text((int) ($record['total_billing_cycles'] ?? 0));
            $trialText = paypalr_subscription_trial_text(
                (int) ($record['trial_frequency'] ?? 0),
                (string) ($record['trial_period'] ?? ''),
                (int) ($record['trial_total_cycles'] ?? 0)
            );

            $vaultId = (int) ($record['paypal_vault_id'] ?? 0);
            $linkedCard = $vaultCardsById[$vaultId] ?? null;
            $vaultSummary = paypalr_subscription_format_vault_summary($linkedCard);

            $remoteDetails = [
                'id' => $remoteId,
                'status' => '',
                'status_label' => '',
                'next_billing' => '',
                'last_payment' => '',
                'last_payment_amount' => '',
                'cycle_summary' => '',
            ];
            $remoteError = '';

            if ($remoteId !== '') {
                if (!isset($remoteCache[$remoteId]) && $api instanceof PayPalRestfulApi) {
                    $details = $api->getSubscription($remoteId);
                    if ($details === false) {
                        $remoteCache[$remoteId] = [
                            'success' => false,
                            'error' => paypalr_subscription_format_api_error($api->getErrorInfo()),
                        ];
                    } else {
                        $remoteCache[$remoteId] = [
                            'success' => true,
                            'data' => $details,
                        ];
                    }
                }

                if (isset($remoteCache[$remoteId])) {
                    $cacheEntry = $remoteCache[$remoteId];
                    if (!empty($cacheEntry['success'])) {
                        $data = $cacheEntry['data'];
                        $remoteStatus = (string) ($data['status'] ?? '');
                        $remoteDetails['status'] = $remoteStatus;
                        $remoteDetails['status_label'] = paypalr_subscription_remote_status_text($remoteStatus);
                        $remoteDetails['next_billing'] = paypalr_subscription_format_datetime($data['billing_info']['next_billing_time'] ?? '');

                        $lastPayment = $data['billing_info']['last_payment'] ?? [];
                        if (is_array($lastPayment)) {
                            $remoteDetails['last_payment'] = paypalr_subscription_format_datetime($lastPayment['time'] ?? '');
                            if (!empty($lastPayment['amount']['value'])) {
                                $remoteDetails['last_payment_amount'] = paypalr_subscription_format_amount(
                                    $lastPayment['amount']['value'],
                                    $lastPayment['amount']['currency_code'] ?? '',
                                    null
                                );
                            }
                        }

                        $cycleExecutions = $data['billing_info']['cycle_executions'] ?? [];
                        if (is_array($cycleExecutions) && !empty($cycleExecutions)) {
                            $cycleSummaryParts = [];
                            foreach ($cycleExecutions as $cycle) {
                                if (!is_array($cycle)) {
                                    continue;
                                }
                                $cycleLabel = trim((string) ($cycle['tenure_type'] ?? ''));
                                if ($cycleLabel !== '') {
                                    $cycleLabel = ucwords(strtolower(str_replace('_', ' ', $cycleLabel)));
                                }
                                $completed = (int) ($cycle['cycles_completed'] ?? $cycle['completed_cycles'] ?? 0);
                                $total = (int) ($cycle['total_cycles'] ?? 0);
                                if ($cycleLabel === '') {
                                    $cycleLabel = TEXT_SUBSCRIPTION_REMOTE_CYCLE_DEFAULT;
                                }
                                if ($total > 0) {
                                    $cycleSummaryParts[] = sprintf(TEXT_SUBSCRIPTION_REMOTE_CYCLE_SUMMARY, $cycleLabel, $completed, $total);
                                } else {
                                    $cycleSummaryParts[] = sprintf(TEXT_SUBSCRIPTION_REMOTE_CYCLE_SUMMARY_OPEN, $cycleLabel, $completed);
                                }
                            }

                            if (!empty($cycleSummaryParts)) {
                                $remoteDetails['cycle_summary'] = implode(' · ', $cycleSummaryParts);
                            }
                        }
                    } else {
                        $remoteError = (string) ($cacheEntry['error'] ?? TEXT_SUBSCRIPTION_API_GENERIC_ERROR);
                    }
                }
            }

            $actions = [];
            if ($remoteId !== '' && $paypal_subscriptions_allow_api) {
                $statusKey = strtolower((string) ($record['status'] ?? ''));
                if (in_array($statusKey, ['active', 'scheduled'], true)) {
                    $actions[] = [
                        'action' => 'suspend-subscription',
                        'label' => TEXT_SUBSCRIPTION_ACTION_PAUSE,
                        'button_class' => 'btn btn-outline-secondary',
                        'confirm' => TEXT_SUBSCRIPTION_CONFIRM_SUSPEND,
                    ];
                    $actions[] = [
                        'action' => 'cancel-subscription',
                        'label' => TEXT_SUBSCRIPTION_ACTION_CANCEL,
                        'button_class' => 'btn btn-danger',
                        'confirm' => TEXT_SUBSCRIPTION_CONFIRM_CANCEL,
                    ];
                } elseif (in_array($statusKey, ['paused', 'suspended'], true)) {
                    $actions[] = [
                        'action' => 'activate-subscription',
                        'label' => TEXT_SUBSCRIPTION_ACTION_RESUME,
                        'button_class' => 'btn btn-primary',
                        'confirm' => TEXT_SUBSCRIPTION_CONFIRM_RESUME,
                    ];
                    $actions[] = [
                        'action' => 'cancel-subscription',
                        'label' => TEXT_SUBSCRIPTION_ACTION_CANCEL,
                        'button_class' => 'btn btn-danger',
                        'confirm' => TEXT_SUBSCRIPTION_CONFIRM_CANCEL,
                    ];
                }

                $actions[] = [
                    'action' => 'refresh-subscription',
                    'label' => TEXT_SUBSCRIPTION_ACTION_REFRESH,
                    'button_class' => 'btn btn-light',
                    'confirm' => '',
                ];
            }

            $paypal_subscriptions[] = [
                'paypal_subscription_id' => (int) ($record['paypal_subscription_id'] ?? 0),
                'orders_id' => (int) ($record['orders_id'] ?? 0),
                'products_name' => (string) ($record['products_name'] ?? ''),
                'plan_id' => (string) ($record['plan_id'] ?? ''),
                'amount' => $amountDisplay,
                'schedule' => $scheduleText,
                'total_cycles' => $totalCyclesText,
                'trial' => $trialText,
                'status_label' => $statusMeta['label'],
                'status_class' => $statusMeta['class'],
                'status_raw' => (string) ($record['status'] ?? ''),
                'start_date' => paypalr_subscription_format_datetime($record['date_added'] ?? ''),
                'last_modified' => paypalr_subscription_format_datetime($record['last_modified'] ?? ''),
                'vault_summary' => $vaultSummary,
                'vault_id' => $vaultId,
                'vault_options' => $vaultOptions,
                'remote_details' => $remoteDetails,
                'remote_error' => $remoteError,
                'attributes' => $attributes,
                'actions' => $actions,
                'remote_available' => ($remoteId !== '' && $paypal_subscriptions_allow_api),
            ];

            $records->MoveNext();
        }
    }
}

$hide_paypal_subscriptions_page = $hideSubscriptionsPage;
$paypal_subscriptions_allow_api_actions = $paypal_subscriptions_allow_api;

$breadcrumb->add(NAVBAR_TITLE_1, zen_href_link(FILENAME_ACCOUNT, '', 'SSL'));
$breadcrumb->add(NAVBAR_TITLE_2, zen_href_link(FILENAME_ACCOUNT_PAYPAL_SUBSCRIPTIONS, '', 'SSL'));

$zco_notifier->notify('NOTIFY_HEADER_END_ACCOUNT_PAYPAL_SUBSCRIPTIONS');
