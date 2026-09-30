<?php
/**
 * Admin: PayPal Advanced Checkout orphan capture reservations (orders_id = 0).
 *
 * List / refund via PayPal API / dismiss (delete row only). Not tied to Zen order refund UI.
 */

require 'includes/application_top.php';

// Self-register under Customers if module upgrade path has not run yet.
if (function_exists('zen_page_key_exists') && function_exists('zen_register_admin_page') && !zen_page_key_exists('paypalacOrphanCaptures')) {
    zen_register_admin_page(
        'paypalacOrphanCaptures',
        'BOX_PAYPALAC_ORPHAN_CAPTURES',
        'FILENAME_PAYPALAC_ORPHAN_CAPTURES',
        '',
        'customers',
        'Y',
        13
    );
}

require_once DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/paypal/ppacAutoload.php';
require_once DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/paypal/paypal_common.php';

use PayPalAdvancedCheckout\Api\PayPalAdvancedCheckoutApi;
use PayPalAdvancedCheckout\Common\Logger;

if (!defined('FILENAME_PAYPALAC_ORPHAN_CAPTURES')) {
    define('FILENAME_PAYPALAC_ORPHAN_CAPTURES', 'paypalac_orphan_captures');
}

require DIR_WS_LANGUAGES . $_SESSION['language'] . '/' . FILENAME_PAYPALAC_ORPHAN_CAPTURES . '.php';

$reservation_table = (defined('DB_PREFIX') ? DB_PREFIX : '') . 'paypal_ac_capture_reservation';

$paymentStub = new class {
    public $code = 'paypalac_orphan_admin';
    public $log;
};
$paymentStub->log = new Logger();
$paypalCommon = new PayPalCommon($paymentStub);
$paypalCommon->ensureCaptureCheckoutReservationTable();

/**
 * @return PayPalAdvancedCheckoutApi|null
 */
function paypalac_orphan_captures_api(): ?PayPalAdvancedCheckoutApi
{
    if (!class_exists('paypalac', false)) {
        $modulePath = DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/paypalac.php';
        if (!is_file($modulePath)) {
            return null;
        }
        require_once $modulePath;
    }
    if (!class_exists('paypalac', false) || !method_exists('paypalac', 'getEnvironmentInfo')) {
        return null;
    }
    if (!defined('MODULE_PAYMENT_PAYPALAC_SERVER')) {
        return null;
    }

    [$clientId, $secret] = \paypalac::getEnvironmentInfo();
    if ($clientId === '' || $secret === '') {
        return null;
    }

    return new PayPalAdvancedCheckoutApi(MODULE_PAYMENT_PAYPALAC_SERVER, $clientId, $secret);
}

/**
 * Delete orphan reservation row (orders_id must still be 0).
 */
function paypalac_orphan_captures_delete_row(string $capture_resource_id): bool
{
    global $db, $reservation_table;

    $capture_resource_id = trim($capture_resource_id);
    if ($capture_resource_id === '') {
        return false;
    }

    $esc = zen_db_input($capture_resource_id);
    $db->Execute(
        "DELETE FROM " . $reservation_table . "
          WHERE capture_resource_id = '" . $esc . "'
            AND orders_id = 0
          LIMIT 1"
    );

    return $db->affectedRows() > 0;
}

$action = isset($_POST['action']) ? trim((string)$_POST['action']) : '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($action === 'refund' || $action === 'dismiss')) {
    $sessionToken = $_SESSION['securityToken'] ?? '';
    $requestToken = $_POST['securityToken'] ?? '';
    if ($sessionToken === '' || $requestToken !== $sessionToken) {
        $messageStack->add_session(ERROR_SECURITY_TOKEN, 'error');
        zen_redirect(zen_href_link(FILENAME_PAYPALAC_ORPHAN_CAPTURES));
    }

    $capture_resource_id = isset($_POST['capture_resource_id'])
        ? trim((string)$_POST['capture_resource_id'])
        : '';
    if ($capture_resource_id === '') {
        $messageStack->add_session(ERROR_MISSING_CAPTURE_ID, 'error');
        zen_redirect(zen_href_link(FILENAME_PAYPALAC_ORPHAN_CAPTURES));
    }

    $chk = $db->Execute(
        "SELECT capture_resource_id
           FROM " . $reservation_table . "
          WHERE capture_resource_id = '" . zen_db_input($capture_resource_id) . "'
            AND orders_id = 0
          LIMIT 1"
    );
    if ($chk->EOF) {
        $messageStack->add_session(ERROR_ROW_NOT_FOUND, 'error');
        zen_redirect(zen_href_link(FILENAME_PAYPALAC_ORPHAN_CAPTURES));
    }

    if ($action === 'dismiss') {
        if (paypalac_orphan_captures_delete_row($capture_resource_id)) {
            $messageStack->add_session(
                sprintf(SUCCESS_DISMISS, zen_output_string_protected($capture_resource_id)),
                'success'
            );
        } else {
            $messageStack->add_session(ERROR_ROW_NOT_FOUND, 'error');
        }
        zen_redirect(zen_href_link(FILENAME_PAYPALAC_ORPHAN_CAPTURES));
    }

    // action === refund
    $ppr = paypalac_orphan_captures_api();
    if ($ppr === null) {
        if (!class_exists('paypalac', false)) {
            $messageStack->add_session(ERROR_MODULE_MISSING, 'error');
        } else {
            $messageStack->add_session(ERROR_API_CREDENTIALS, 'error');
        }
        zen_redirect(zen_href_link(FILENAME_PAYPALAC_ORPHAN_CAPTURES));
    }

    $invoice_id = 'PPAC-ORPHAN-ADMIN-' . substr($capture_resource_id, 0, 32);
    $payer_note = 'Admin refund of orphan checkout capture (no Zen Cart order).';
    $refund_response = $ppr->refundCaptureFull($capture_resource_id, $invoice_id, $payer_note);

    $refund_ok = false;
    $refund_id = '';
    if (is_array($refund_response)) {
        $status = strtoupper((string)($refund_response['status'] ?? ''));
        if (in_array($status, ['COMPLETED', 'PENDING'], true)) {
            $refund_ok = true;
            $refund_id = (string)($refund_response['id'] ?? '');
        }
    }

    if ($refund_ok) {
        paypalac_orphan_captures_delete_row($capture_resource_id);
        $messageStack->add_session(
            sprintf(
                SUCCESS_REFUND,
                zen_output_string_protected($capture_resource_id),
                zen_output_string_protected($refund_id !== '' ? $refund_id : 'n/a')
            ),
            'success'
        );
    } else {
        $error_info = method_exists($ppr, 'getErrorInfo') ? $ppr->getErrorInfo() : [];
        $detail = is_array($refund_response)
            ? json_encode($refund_response)
            : json_encode($error_info);
        $messageStack->add_session(
            sprintf(
                ERROR_REFUND_FAILED,
                zen_output_string_protected($capture_resource_id),
                zen_output_string_protected((string)$detail)
            ),
            'error'
        );
    }

    zen_redirect(zen_href_link(FILENAME_PAYPALAC_ORPHAN_CAPTURES));
}

$rows = [];
$result = $db->Execute(
    "SELECT r.capture_resource_id, r.customers_id, r.paypal_order_id, r.created_at, r.alerted_at,
            c.customers_firstname, c.customers_lastname, c.customers_email_address
       FROM " . $reservation_table . " r
  LEFT JOIN " . TABLE_CUSTOMERS . " c ON c.customers_id = r.customers_id
      WHERE r.orders_id = 0
   ORDER BY r.created_at DESC"
);
while (!$result->EOF) {
    $rows[] = $result->fields;
    $result->MoveNext();
}

?>
<!doctype html>
<html <?php echo HTML_PARAMS; ?>>
<head>
    <?php require DIR_WS_INCLUDES . 'admin_html_head.php'; ?>
    <title><?php echo HEADING_TITLE; ?></title>
    <style>
        .ppac-orphan-intro { max-width: 900px; color: #444; margin: 0 0 1.25rem; }
        .ppac-orphan-table { width: 100%; border-collapse: collapse; background: #fff; }
        .ppac-orphan-table th, .ppac-orphan-table td { padding: .5rem .65rem; border: 1px solid #ddd; vertical-align: top; text-align: left; }
        .ppac-orphan-table th { background: #f5f5f5; }
        .ppac-orphan-actions form { display: inline-block; margin: 0 .25rem .25rem 0; }
        .ppac-orphan-mono { font-family: monospace; font-size: 12px; word-break: break-all; }
        .ppac-orphan-empty { padding: 1rem; color: #666; }
    </style>
</head>
<body>
<?php require DIR_WS_INCLUDES . 'header.php'; ?>
<div class="container-fluid">
    <h1><?php echo HEADING_TITLE; ?></h1>
    <p class="ppac-orphan-intro"><?php echo TEXT_ORPHAN_INTRO; ?></p>

    <?php if ($rows === []) { ?>
        <p class="ppac-orphan-empty"><?php echo TEXT_NO_ORPHANS; ?></p>
    <?php } else { ?>
        <table class="ppac-orphan-table">
            <thead>
            <tr>
                <th><?php echo TABLE_HEADING_CAPTURE; ?></th>
                <th><?php echo TABLE_HEADING_CUSTOMER; ?></th>
                <th><?php echo TABLE_HEADING_PAYPAL_ORDER; ?></th>
                <th><?php echo TABLE_HEADING_CREATED; ?></th>
                <th><?php echo TABLE_HEADING_ALERTED; ?></th>
                <th><?php echo TABLE_HEADING_ACTIONS; ?></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $row) {
                $capture_id = (string)$row['capture_resource_id'];
                $cid = (int)$row['customers_id'];
                $name = trim((string)($row['customers_firstname'] ?? '') . ' ' . (string)($row['customers_lastname'] ?? ''));
                $email = trim((string)($row['customers_email_address'] ?? ''));
                if ($name !== '' || $email !== '') {
                    $customer_label = ($name !== '' ? zen_output_string_protected($name) : '')
                        . ($email !== '' ? ' &lt;' . zen_output_string_protected($email) . '&gt;' : '')
                        . ' (#' . $cid . ')';
                } else {
                    $customer_label = sprintf(TEXT_CUSTOMER_UNKNOWN, $cid);
                }
                $alerted = trim((string)($row['alerted_at'] ?? ''));
                ?>
                <tr>
                    <td class="ppac-orphan-mono"><?php echo zen_output_string_protected($capture_id); ?></td>
                    <td><?php echo $customer_label; ?></td>
                    <td class="ppac-orphan-mono"><?php echo zen_output_string_protected((string)($row['paypal_order_id'] ?? '')); ?></td>
                    <td><?php echo zen_output_string_protected((string)($row['created_at'] ?? '')); ?></td>
                    <td><?php echo $alerted !== '' ? zen_output_string_protected($alerted) : TEXT_NEVER_ALERTED; ?></td>
                    <td class="ppac-orphan-actions">
                        <?php echo zen_draw_form('orphan_refund_' . md5($capture_id), FILENAME_PAYPALAC_ORPHAN_CAPTURES, '', 'post'); ?>
                            <?php echo zen_draw_hidden_field('securityToken', $_SESSION['securityToken'] ?? ''); ?>
                            <?php echo zen_draw_hidden_field('action', 'refund'); ?>
                            <?php echo zen_draw_hidden_field('capture_resource_id', $capture_id); ?>
                            <button type="submit" class="btn btn-warning"
                                    onclick="return confirm(<?php echo json_encode(TEXT_CONFIRM_REFUND, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>);">
                                <?php echo BUTTON_REFUND; ?>
                            </button>
                        </form>
                        <?php echo zen_draw_form('orphan_dismiss_' . md5($capture_id), FILENAME_PAYPALAC_ORPHAN_CAPTURES, '', 'post'); ?>
                            <?php echo zen_draw_hidden_field('securityToken', $_SESSION['securityToken'] ?? ''); ?>
                            <?php echo zen_draw_hidden_field('action', 'dismiss'); ?>
                            <?php echo zen_draw_hidden_field('capture_resource_id', $capture_id); ?>
                            <button type="submit" class="btn btn-default"
                                    onclick="return confirm(<?php echo json_encode(TEXT_CONFIRM_DISMISS, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>);">
                                <?php echo BUTTON_DISMISS; ?>
                            </button>
                        </form>
                    </td>
                </tr>
            <?php } ?>
            </tbody>
        </table>
    <?php } ?>
</div>
<?php require DIR_WS_INCLUDES . 'footer.php'; ?>
</body>
</html>
<?php require DIR_WS_INCLUDES . 'application_bottom.php'; ?>
