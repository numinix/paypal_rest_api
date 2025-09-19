<?php
/**
 * PayPal RESTful (paypalr) partner integrated sign-up controller.
 *
 * @copyright Copyright 2023-2025 Zen Cart Development Team
 * @license https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 *
 * Boots the admin environment, requests a partner-referral onboarding link
 * and processes the callbacks returned from PayPal.
 */

require 'includes/application_top.php';

// Zen Cart v2.0.2+ exposes zen_admin_check_login(); fall back to the session when
// running on earlier stores so administrators can still reach the onboarding flow.
$paypalrAdminLoggedIn = true;
if (function_exists('zen_admin_check_login')) {
    $paypalrAdminLoggedIn = zen_admin_check_login();
} else {
    $paypalrAdminLoggedIn = (int)($_SESSION['admin_id'] ?? 0) > 0;
}

if (!$paypalrAdminLoggedIn) {
    zen_redirect(zen_href_link(FILENAME_LOGIN, '', 'SSL'));
}

require DIR_WS_MODULES . 'payment/paypal/pprAutoload.php';

use PayPalRestful\Admin\IntegratedSignup;
use PayPalRestful\Api\PayPalRestfulApi;
use PayPalRestful\Common\Logger;

function paypalr_process_onboarding_return(array $params): void
{
    $logger = paypalr_get_isu_logger();
    $sessionData = $_SESSION['paypalr_isu'] ?? [];
    $logger->write('Processing PayPal onboarding return.' . "\n" . Logger::logJSON([
        'params' => $params,
        'session' => $sessionData,
    ]));

    if (empty($sessionData)) {
        paypalr_add_admin_notice('PayPal onboarding session details were not found. Please restart the onboarding process.', 'error');
        $logger->write('Onboarding return received without session data.');
        paypalr_clear_onboarding_session();
        return;
    }

    $trackingId = (string)($sessionData['tracking_id'] ?? '');
    $referralId = (string)($sessionData['referral_id'] ?? '');
    $environment = (string)($sessionData['environment'] ?? 'sandbox');
    $created = (int)($sessionData['created'] ?? 0);

    $returnedTrackingId = trim((string)($params['trackingId'] ?? ''));
    if ($trackingId === '' || $returnedTrackingId === '' || $trackingId !== $returnedTrackingId) {
        paypalr_add_admin_notice('PayPal onboarding could not be verified because the tracking information does not match. Please try again.', 'error');
        $logger->write('Tracking ID mismatch during onboarding return.' . "\n" . Logger::logJSON([
            'expected' => $trackingId,
            'received' => $returnedTrackingId,
        ]));
        paypalr_clear_onboarding_session();
        return;
    }

    $permissionsGranted = paypalr_normalize_boolean($params['permissionsGranted'] ?? '');
    if ($permissionsGranted !== true) {
        paypalr_add_admin_notice('PayPal onboarding was not completed because permissions were not granted.', 'warning');
        $logger->write('Permissions were not granted during onboarding return.');
        paypalr_clear_onboarding_session();
        return;
    }

    $merchantIdInPayPal = trim((string)($params['merchantIdInPayPal'] ?? ''));
    if ($merchantIdInPayPal === '') {
        paypalr_add_admin_notice('PayPal onboarding could not be finalized. The merchant identifier was not supplied by PayPal.', 'error');
        $logger->write('Missing merchantIdInPayPal value in onboarding return.');
        paypalr_clear_onboarding_session();
        return;
    }

    [$clientId, $clientSecret] = \paypalr::getPartnerCredentials($environment);
    if ($clientId === '' || $clientSecret === '') {
        paypalr_add_admin_notice('PayPal onboarding could not be verified because partner credentials are missing. Configure the partner credentials and start the onboarding again.', 'error');
        $logger->write('Partner credentials missing for onboarding verification in environment: ' . $environment);
        paypalr_clear_onboarding_session();
        return;
    }

    $api = new PayPalRestfulApi($environment, $clientId, $clientSecret);

    $partnerReferral = [];
    if ($referralId !== '') {
        $partnerReferral = $api->getPartnerReferral($referralId);
        if ($partnerReferral === false) {
            $logger->write('Unable to retrieve partner referral during onboarding return.' . "\n" . Logger::logJSON($api->getErrorInfo()));
            paypalr_add_admin_notice('PayPal onboarding could not be confirmed. Unable to retrieve the partner referral details. Check the PayPal logs for more information.', 'error');
            paypalr_clear_onboarding_session();
            return;
        }

        $referralTracking = (string)($partnerReferral['tracking_id'] ?? '');
        if ($referralTracking !== '' && $referralTracking !== $trackingId) {
            paypalr_add_admin_notice('PayPal onboarding could not be confirmed because the referral tracking identifier does not match.', 'error');
            $logger->write('Referral tracking mismatch during onboarding return.' . "\n" . Logger::logJSON([
                'referral_tracking' => $referralTracking,
                'session_tracking' => $trackingId,
            ]));
            paypalr_clear_onboarding_session();
            return;
        }
    }

    $partnerId = trim((string)($params['partnerId'] ?? ''));
    if ($partnerId === '') {
        paypalr_add_admin_notice('PayPal onboarding response did not include the partner identifier. Please retry the onboarding process.', 'error');
        $logger->write('Partner identifier missing in onboarding return.');
        paypalr_clear_onboarding_session();
        return;
    }

    $merchantId = trim((string)($params['merchantId'] ?? ''));
    if ($merchantId === '') {
        paypalr_add_admin_notice('PayPal onboarding response did not include the merchant reference identifier. Please retry the onboarding process.', 'error');
        $logger->write('Merchant identifier missing in onboarding return.');
        paypalr_clear_onboarding_session();
        return;
    }

    $merchantIntegration = $api->getMerchantIntegration($merchantId, $partnerId);
    if ($merchantIntegration === false) {
        $logger->write('Unable to retrieve merchant integration details during onboarding return.' . "\n" . Logger::logJSON([
            'partner_id' => $partnerId,
            'merchant_id' => $merchantId,
            'error' => $api->getErrorInfo(),
        ]));
        paypalr_add_admin_notice('PayPal onboarding could not be verified. Unable to retrieve the merchant integration details from PayPal.', 'error');
        paypalr_clear_onboarding_session();
        return;
    }

    $integrationMerchantId = (string)($merchantIntegration['merchant_id_in_paypal'] ?? '');
    if ($integrationMerchantId !== '' && $integrationMerchantId !== $merchantIdInPayPal) {
        paypalr_add_admin_notice('PayPal onboarding data could not be verified because the merchant identifiers do not match.', 'error');
        $logger->write('Merchant identifier mismatch.' . "\n" . Logger::logJSON([
            'integration' => $integrationMerchantId,
            'callback' => $merchantIdInPayPal,
        ]));
        paypalr_clear_onboarding_session();
        return;
    }

    $status = paypalr_build_onboarding_status(
        $environment,
        $created,
        $sessionData,
        $params,
        $partnerReferral,
        $merchantIntegration
    );
    paypalr_store_onboarding_status($status);

    $credentials = paypalr_extract_credentials($merchantIntegration, $partnerReferral);
    $credentialsSaved = paypalr_update_environment_credentials($environment, $credentials);

    $merchantDisplay = $merchantIdInPayPal;
    if (function_exists('zen_output_string_protected')) {
        $merchantDisplay = zen_output_string_protected($merchantDisplay);
    }
    $paymentsReceivable = $merchantIntegration['payments_receivable'] ?? null;
    $paymentsText = '';
    if ($paymentsReceivable !== null) {
        $paymentsText = ' Payments receivable: ' . ($paymentsReceivable ? 'Yes' : 'No') . '.';
    }

    $message = sprintf(
        'PayPal onboarding completed for merchant %s in the %s environment.',
        $merchantDisplay,
        $environment
    ) . $paymentsText;
    if ($credentialsSaved) {
        $message .= ' API credentials have been stored in the module configuration.';
    }

    paypalr_add_admin_notice($message, 'success');
    paypalr_add_admin_notice('Review the PayPal Commerce Platform settings on the Payment Modules page to enable the module when you are ready.', 'warning');

    $logger->write('Onboarding return processed successfully.' . "\n" . Logger::logJSON([
        'status' => $status,
        'credentials_saved' => $credentialsSaved,
    ]));

    paypalr_clear_onboarding_session();
}

function paypalr_handle_onboarding_cancel(array $params): void
{
    $logger = paypalr_get_isu_logger();
    $logger->write('PayPal onboarding was cancelled.' . "\n" . Logger::logJSON([
        'params' => $params,
        'session' => $_SESSION['paypalr_isu'] ?? [],
    ]));

    paypalr_add_admin_notice('PayPal onboarding was cancelled. No changes were made.', 'warning');
    paypalr_clear_onboarding_session();
}

function paypalr_handle_onboarding_error(array $params): void
{
    $logger = paypalr_get_isu_logger();
    $logger->write('PayPal onboarding error callback received.' . "\n" . Logger::logJSON([
        'params' => $params,
        'session' => $_SESSION['paypalr_isu'] ?? [],
    ]));

    $message = 'PayPal reported an error while processing the onboarding callback. Check the logs for additional details and restart the onboarding when ready.';
    if (!empty($params['errorMessage'])) {
        $message .= ' Message: ' . trim((string)$params['errorMessage']);
    }

    paypalr_add_admin_notice($message, 'error');
    paypalr_clear_onboarding_session();
}

function paypalr_get_isu_logger(): Logger
{
    static $logger;

    if (!isset($logger)) {
        $logger = new Logger('isu');
        if (defined('MODULE_PAYMENT_PAYPALR_DEBUGGING') && strpos((string)MODULE_PAYMENT_PAYPALR_DEBUGGING, 'Log') !== false) {
            $logger->enableDebug();
        }
    }

    return $logger;
}

function paypalr_add_admin_notice(string $message, string $type = 'warning'): void
{
    global $messageStack;

    if (!isset($messageStack)) {
        return;
    }

    $messageStack->add_session('header', $message, $type);
}

function paypalr_clear_onboarding_session(): void
{
    if (isset($_SESSION['paypalr_isu'])) {
        unset($_SESSION['paypalr_isu']);
    }
}

function paypalr_normalize_boolean($value): ?bool
{
    if (is_bool($value)) {
        return $value;
    }

    $value = strtolower(trim((string)$value));
    if ($value === 'true' || $value === '1' || $value === 'yes') {
        return true;
    }
    if ($value === 'false' || $value === '0' || $value === 'no') {
        return false;
    }

    return null;
}

function paypalr_build_onboarding_status(
    string $environment,
    int $created,
    array $sessionData,
    array $params,
    array $partnerReferral,
    array $merchantIntegration
): array {
    $status = [
        'environment' => $environment,
        'tracking_id' => (string)($sessionData['tracking_id'] ?? ''),
        'referral_id' => (string)($sessionData['referral_id'] ?? ''),
        'session_started' => ($created > 0) ? date('c', $created) : null,
        'processed_at' => date('c'),
        'merchant_id_in_paypal' => (string)($params['merchantIdInPayPal'] ?? ($merchantIntegration['merchant_id_in_paypal'] ?? '')),
        'merchant_id' => (string)($params['merchantId'] ?? ($merchantIntegration['merchant_id'] ?? '')),
        'partner_id' => (string)($params['partnerId'] ?? ''),
        'partner_client_id' => (string)($params['partnerClientId'] ?? ''),
        'permissions_granted' => paypalr_normalize_boolean($params['permissionsGranted'] ?? true),
        'consent_status' => (string)($params['consentStatus'] ?? ''),
        'return_message' => (string)($params['returnMessage'] ?? ($params['displayMessage'] ?? '')),
        'payments_receivable' => $merchantIntegration['payments_receivable'] ?? null,
        'primary_email_confirmed' => $merchantIntegration['primary_email_confirmed'] ?? null,
        'status' => (string)($merchantIntegration['status'] ?? ($partnerReferral['status'] ?? '')),
        'products' => $merchantIntegration['products'] ?? ($partnerReferral['products'] ?? []),
        'capabilities' => $merchantIntegration['capabilities'] ?? [],
    ];

    return paypalr_filter_recursive($status);
}

function paypalr_filter_recursive(array $data): array
{
    foreach ($data as $key => $value) {
        if (is_array($value)) {
            $filtered = paypalr_filter_recursive($value);
            if ($filtered === []) {
                unset($data[$key]);
                continue;
            }
            $data[$key] = $filtered;
            continue;
        }

        if ($value === null || $value === '') {
            unset($data[$key]);
        }
    }

    return $data;
}

function paypalr_store_onboarding_status(array $status): void
{
    global $db;

    $key = 'MODULE_PAYMENT_PAYPALR_ISU_STATUS';
    $encoded = json_encode($status, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($encoded === false) {
        $encoded = serialize($status);
    }

    $result = $db->Execute("SELECT configuration_value FROM " . TABLE_CONFIGURATION . " WHERE configuration_key = '" . $key . "' LIMIT 1");
    if ($result->EOF) {
        zen_db_perform(TABLE_CONFIGURATION, [
            'configuration_key' => $key,
            'configuration_value' => $encoded,
            'configuration_title' => 'PayPal integrated sign-up status',
            'configuration_description' => 'Automatically maintained status details from the PayPal Commerce Platform onboarding flow. This value is read-only and updated after PayPal callbacks.',
            'configuration_group_id' => 6,
            'sort_order' => 0,
            'set_function' => 'zen_cfg_read_only(',
            'date_added' => 'now()',
            'last_modified' => 'now()',
        ]);
    } else {
        zen_db_perform(TABLE_CONFIGURATION, [
            'configuration_value' => $encoded,
            'last_modified' => 'now()',
        ], 'update', "configuration_key='" . $key . "'");
    }
}

function paypalr_extract_credentials(array $merchantIntegration, array $partnerReferral = []): array
{
    $credentials = [];

    $oauthIntegrations = $merchantIntegration['oauth_integrations'] ?? [];
    foreach ($oauthIntegrations as $integration) {
        if (!empty($integration['merchant_client_id']) && empty($credentials['client_id'])) {
            $credentials['client_id'] = (string)$integration['merchant_client_id'];
        }
        if (!empty($integration['merchant_client_secret']) && empty($credentials['client_secret'])) {
            $credentials['client_secret'] = (string)$integration['merchant_client_secret'];
        }
        if (isset($integration['credentials'])) {
            $nested = (array)$integration['credentials'];
            if (!empty($nested['client_id']) && empty($credentials['client_id'])) {
                $credentials['client_id'] = (string)$nested['client_id'];
            }
            if (!empty($nested['client_secret']) && empty($credentials['client_secret'])) {
                $credentials['client_secret'] = (string)$nested['client_secret'];
            }
        }
        if (!empty($credentials['client_id']) && !empty($credentials['client_secret'])) {
            break;
        }
    }

    if (empty($credentials['client_id']) && isset($partnerReferral['links'])) {
        foreach ((array)$partnerReferral['links'] as $link) {
            if (!is_array($link)) {
                continue;
            }
            if (!empty($link['rel']) && $link['rel'] === 'self' && !empty($link['merchant_client_id'])) {
                $credentials['client_id'] = (string)$link['merchant_client_id'];
                break;
            }
        }
    }

    return $credentials;
}

function paypalr_update_environment_credentials(string $environment, array $credentials): bool
{
    if (empty($credentials)) {
        return false;
    }

    $updated = false;

    $env = (strtolower($environment) === 'live') ? 'L' : 'S';
    $clientKey = 'MODULE_PAYMENT_PAYPALR_CLIENTID_' . $env;
    $secretKey = 'MODULE_PAYMENT_PAYPALR_SECRET_' . $env;

    if (!empty($credentials['client_id'])) {
        zen_db_perform(TABLE_CONFIGURATION, [
            'configuration_value' => $credentials['client_id'],
            'last_modified' => 'now()',
        ], 'update', "configuration_key='" . $clientKey . "'");
        $updated = true;
    }

    if (!empty($credentials['client_secret'])) {
        zen_db_perform(TABLE_CONFIGURATION, [
            'configuration_value' => $credentials['client_secret'],
            'last_modified' => 'now()',
        ], 'update', "configuration_key='" . $secretKey . "'");
        $updated = true;
    }

    return $updated;
}

$action = $_GET['action'] ?? '';
$error = [];
$redirectUrl = '';
$modulesUrl = zen_href_link(FILENAME_MODULES, 'set=payment&module=paypalr', 'SSL');

switch ($action) {
    case 'start':
        $environment = defined('MODULE_PAYMENT_PAYPALR_SERVER') ? MODULE_PAYMENT_PAYPALR_SERVER : 'sandbox';
        $helper = new IntegratedSignup($environment);
        if ($helper->createReferral()) {
            $_SESSION['paypalr_isu'] = [
                'tracking_id' => $helper->getTrackingId(),
                'referral_id' => $helper->getReferralId(),
                'environment' => $helper->getEnvironment(),
                'created' => time(),
            ];
            $redirectUrl = $helper->getActionUrl();
        } else {
            $error = $helper->getError();
            if (empty($error['errMsg'])) {
                $error['errMsg'] = 'An unknown error occurred while contacting PayPal.';
            }
        }
        break;

    case 'return':
        paypalr_process_onboarding_return($_GET);
        $redirectUrl = $modulesUrl;
        break;

    case 'cancel':
        paypalr_handle_onboarding_cancel($_GET);
        $redirectUrl = $modulesUrl;
        break;

    case 'error':
        paypalr_handle_onboarding_error($_GET);
        $redirectUrl = $modulesUrl;
        break;

    default:
        $error = [
            'errMsg' => 'Invalid request.',
            'message' => '',
            'details' => [],
            'debug_id' => '',
        ];
        break;
}

if ($redirectUrl !== '') {
    zen_redirect($redirectUrl);
    exit;
}

if (!empty($error)) {
    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');

    $errMsg = zen_output_string_protected($error['errMsg'] ?? 'Unexpected error.');
    $message = zen_output_string_protected($error['message'] ?? '');
    $debugId = zen_output_string_protected($error['debug_id'] ?? '');
    $details = $error['details'] ?? [];
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <title>PayPal Onboarding Error</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 2rem; color: #222; }
            h1 { color: #002b7f; }
            .notice { background: #fbeaea; border: 1px solid #dc1e1e; padding: 1rem 1.5rem; border-radius: 4px; }
            .details { margin-top: 1rem; }
            .details ul { margin: 0.5rem 0 0 1.5rem; }
            .meta { margin-top: 0.75rem; font-size: 0.9rem; color: #555; }
            a { color: #002b7f; }
        </style>
    </head>
    <body>
        <h1>PayPal Onboarding Error</h1>
        <div class="notice">
            <p><?php echo $errMsg; ?></p>
            <?php if ($message !== '') { ?>
                <p class="details"><?php echo $message; ?></p>
            <?php } ?>
            <?php if (!empty($details) && is_array($details)) { ?>
                <div class="details">
                    <p>Additional details:</p>
                    <ul>
                        <?php foreach ($details as $detail) {
                            $issue = zen_output_string_protected($detail['description'] ?? ($detail['issue'] ?? ''));
                            if ($issue === '') {
                                continue;
                            }
                        ?>
                            <li><?php echo $issue; ?></li>
                        <?php } ?>
                    </ul>
                </div>
            <?php } ?>
            <?php if ($debugId !== '') { ?>
                <p class="meta">PayPal debug id: <?php echo $debugId; ?></p>
            <?php } ?>
        </div>
        <p class="meta"><a href="<?php echo $modulesUrl; ?>">Return to module settings</a></p>
    </body>
    </html>
    <?php
}

require DIR_WS_INCLUDES . 'application_bottom.php';
exit;
