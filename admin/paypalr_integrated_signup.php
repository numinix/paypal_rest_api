<?php
/**
 * PayPal RESTful (paypalr) partner integrated sign-up controller.
 *
 * @copyright Copyright 2023-2025 Zen Cart Development Team
 * @license https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 *
 * Boots the admin environment, requests a partner-referral onboarding link
 * and redirects the administrator to PayPal when successful.
 */

require 'includes/application_top.php';

if (!zen_admin_check_login()) {
    zen_redirect(zen_href_link(FILENAME_LOGIN, '', 'SSL'));
}

require DIR_WS_MODULES . 'payment/paypal/pprAutoload.php';

use PayPalRestful\Admin\IntegratedSignup;

$action = $_GET['action'] ?? '';
$error = [];

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
            zen_redirect($helper->getActionUrl());
        } else {
            $error = $helper->getError();
            if (empty($error['errMsg'])) {
                $error['errMsg'] = 'An unknown error occurred while contacting PayPal.';
            }
        }
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
    <p class="meta"><a href="<?php echo zen_href_link(FILENAME_MODULES, 'set=payment&module=paypalr', 'SSL'); ?>">Return to module settings</a></p>
</body>
</html>
<?php
require DIR_WS_INCLUDES . 'application_bottom.php';
exit;
