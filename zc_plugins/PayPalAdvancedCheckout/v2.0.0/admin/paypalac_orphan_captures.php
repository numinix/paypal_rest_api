<?php
/**
 * Admin: PayPal Advanced Checkout orphan capture reservations (orders_id = 0).
 *
 * List / refund or void via PayPal API / dismiss (delete row only). Not tied to Zen order refund UI.
 * Only rows older than MIN_AGE_MINUTES are listed or actionable (same cutoff as alert cron).
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

// Page language is loaded by application_top / LanguageLoader — do not re-require.

/** Match cron/paypalac_orphan_capture_alerts.php in-flight skip. */
const PAYPALAC_ORPHAN_ADMIN_MIN_AGE_MINUTES = 5;

/** Stale admin claims (crashed request) become reclaimable after this many minutes. */
const PAYPALAC_ORPHAN_ADMIN_CLAIM_TTL_MINUTES = 10;

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
 * Age + still-orphan SQL fragment (orders_id = 0 and older than min age).
 */
function paypalac_orphan_captures_aged_sql(int $min_age_minutes): string
{
    return "orders_id = 0
            AND created_at < DATE_SUB(NOW(), INTERVAL " . (int)$min_age_minutes . " MINUTE)";
}

/**
 * Atomically claim an aged orphan row for admin action.
 * Uses admin_claim_token only — never touches alerted_at (email cooldown).
 *
 * @return array{token:string,row:array<string,mixed>}|null
 */
function paypalac_orphan_captures_claim_aged_row(string $capture_resource_id, int $min_age_minutes): ?array
{
    global $db, $reservation_table;

    $capture_resource_id = trim($capture_resource_id);
    if ($capture_resource_id === '') {
        return null;
    }

    $token = bin2hex(random_bytes(16));
    $esc = zen_db_input($capture_resource_id);
    $esc_token = zen_db_input($token);
    $aged = paypalac_orphan_captures_aged_sql($min_age_minutes);
    $ttl = (int)PAYPALAC_ORPHAN_ADMIN_CLAIM_TTL_MINUTES;

    // One winner: free claim, or stale claim past TTL (crashed admin request).
    $db->Execute(
        "UPDATE " . $reservation_table . "
            SET admin_claim_token = '" . $esc_token . "',
                admin_claimed_at = NOW()
          WHERE capture_resource_id = '" . $esc . "'
            AND " . $aged . "
            AND (
                admin_claim_token = ''
                OR admin_claimed_at IS NULL
                OR admin_claimed_at < DATE_SUB(NOW(), INTERVAL " . $ttl . " MINUTE)
            )
          LIMIT 1"
    );
    if ($db->affectedRows() < 1) {
        return null;
    }

    $chk = $db->Execute(
        "SELECT capture_resource_id, resource_type, customers_id, paypal_order_id, created_at, alerted_at,
                admin_claim_token, admin_claimed_at
           FROM " . $reservation_table . "
          WHERE capture_resource_id = '" . $esc . "'
            AND admin_claim_token = '" . $esc_token . "'
            AND " . $aged . "
          LIMIT 1"
    );
    if ($chk->EOF) {
        return null;
    }

    return ['token' => $token, 'row' => $chk->fields];
}

/**
 * Confirm claim still holds and row is still an aged orphan (no second UPDATE).
 *
 * @return array<string,mixed>|null
 */
function paypalac_orphan_captures_recheck_claim(
    string $capture_resource_id,
    string $claim_token,
    int $min_age_minutes
): ?array {
    global $db, $reservation_table;

    $capture_resource_id = trim($capture_resource_id);
    $claim_token = trim($claim_token);
    if ($capture_resource_id === '' || $claim_token === '') {
        return null;
    }

    $esc = zen_db_input($capture_resource_id);
    $esc_token = zen_db_input($claim_token);
    $chk = $db->Execute(
        "SELECT capture_resource_id, resource_type, customers_id, paypal_order_id, created_at, alerted_at
           FROM " . $reservation_table . "
          WHERE capture_resource_id = '" . $esc . "'
            AND admin_claim_token = '" . $esc_token . "'
            AND " . paypalac_orphan_captures_aged_sql($min_age_minutes) . "
          LIMIT 1"
    );
    if ($chk->EOF) {
        return null;
    }

    return $chk->fields;
}

/**
 * Release admin claim without touching alerted_at (failed refund/void / abort).
 */
function paypalac_orphan_captures_release_claim(string $capture_resource_id, string $claim_token): void
{
    global $db, $reservation_table;

    $capture_resource_id = trim($capture_resource_id);
    $claim_token = trim($claim_token);
    if ($capture_resource_id === '' || $claim_token === '') {
        return;
    }

    $esc = zen_db_input($capture_resource_id);
    $esc_token = zen_db_input($claim_token);
    $db->Execute(
        "UPDATE " . $reservation_table . "
            SET admin_claim_token = '',
                admin_claimed_at = NULL
          WHERE capture_resource_id = '" . $esc . "'
            AND admin_claim_token = '" . $esc_token . "'
          LIMIT 1"
    );
}

/**
 * Delete claimed aged orphan row.
 */
function paypalac_orphan_captures_delete_claimed_row(
    string $capture_resource_id,
    string $claim_token,
    int $min_age_minutes
): bool {
    global $db, $reservation_table;

    $capture_resource_id = trim($capture_resource_id);
    $claim_token = trim($claim_token);
    if ($capture_resource_id === '' || $claim_token === '') {
        return false;
    }

    $esc = zen_db_input($capture_resource_id);
    $esc_token = zen_db_input($claim_token);
    $db->Execute(
        "DELETE FROM " . $reservation_table . "
          WHERE capture_resource_id = '" . $esc . "'
            AND admin_claim_token = '" . $esc_token . "'
            AND " . paypalac_orphan_captures_aged_sql($min_age_minutes) . "
          LIMIT 1"
    );

    return $db->affectedRows() > 0;
}

/**
 * Shared checkout lock name (same as PayPalCommon::acquireAdvancedCheckoutMysqlOrderLock).
 */
function paypalac_orphan_captures_order_lock_name(string $paypal_order_id): string
{
    return 'ppac_' . md5($paypal_order_id);
}

/**
 * Acquire checkout GET_LOCK for paypal_order_id when present (serialize vs before_process).
 */
function paypalac_orphan_captures_acquire_order_lock(string $paypal_order_id): bool
{
    global $db;

    $paypal_order_id = trim($paypal_order_id);
    if ($paypal_order_id === '') {
        return true;
    }

    $escaped = zen_db_input(paypalac_orphan_captures_order_lock_name($paypal_order_id));
    $result = $db->Execute("SELECT GET_LOCK('" . $escaped . "', 5) AS ppac_orphan_lock");
    $acquired = isset($result->fields['ppac_orphan_lock']) ? (int)$result->fields['ppac_orphan_lock'] : 0;

    return $acquired === 1;
}

function paypalac_orphan_captures_release_order_lock(string $paypal_order_id): void
{
    global $db;

    $paypal_order_id = trim($paypal_order_id);
    if ($paypal_order_id === '') {
        return;
    }

    $escaped = zen_db_input(paypalac_orphan_captures_order_lock_name($paypal_order_id));
    $db->Execute("SELECT RELEASE_LOCK('" . $escaped . "')");
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

    $claimed = paypalac_orphan_captures_claim_aged_row(
        $capture_resource_id,
        PAYPALAC_ORPHAN_ADMIN_MIN_AGE_MINUTES
    );
    if ($claimed === null) {
        $messageStack->add_session(ERROR_ROW_NOT_FOUND, 'error');
        zen_redirect(zen_href_link(FILENAME_PAYPALAC_ORPHAN_CAPTURES));
    }

    $claim_token = $claimed['token'];
    $paypal_order_id = (string)($claimed['row']['paypal_order_id'] ?? '');
    $order_lock_held = false;

    if (!paypalac_orphan_captures_acquire_order_lock($paypal_order_id)) {
        paypalac_orphan_captures_release_claim($capture_resource_id, $claim_token);
        $messageStack->add_session(ERROR_CHECKOUT_LOCK, 'error');
        zen_redirect(zen_href_link(FILENAME_PAYPALAC_ORPHAN_CAPTURES));
    }
    $order_lock_held = ($paypal_order_id !== '');

    // Checkout may have linked orders_id while we waited for GET_LOCK.
    $recheck = paypalac_orphan_captures_recheck_claim(
        $capture_resource_id,
        $claim_token,
        PAYPALAC_ORPHAN_ADMIN_MIN_AGE_MINUTES
    );
    if ($recheck === null) {
        paypalac_orphan_captures_release_claim($capture_resource_id, $claim_token);
        if ($order_lock_held) {
            paypalac_orphan_captures_release_order_lock($paypal_order_id);
        }
        $messageStack->add_session(ERROR_ROW_NOT_FOUND, 'error');
        zen_redirect(zen_href_link(FILENAME_PAYPALAC_ORPHAN_CAPTURES));
    }

    if ($action === 'dismiss') {
        if (paypalac_orphan_captures_delete_claimed_row(
            $capture_resource_id,
            $claim_token,
            PAYPALAC_ORPHAN_ADMIN_MIN_AGE_MINUTES
        )) {
            $messageStack->add_session(
                sprintf(SUCCESS_DISMISS, zen_output_string_protected($capture_resource_id)),
                'success'
            );
        } else {
            paypalac_orphan_captures_release_claim($capture_resource_id, $claim_token);
            $messageStack->add_session(ERROR_ROW_NOT_FOUND, 'error');
        }
        if ($order_lock_held) {
            paypalac_orphan_captures_release_order_lock($paypal_order_id);
        }
        zen_redirect(zen_href_link(FILENAME_PAYPALAC_ORPHAN_CAPTURES));
    }

    // action === refund (capture refund or authorization void)
    $ppr = paypalac_orphan_captures_api();
    if ($ppr === null) {
        paypalac_orphan_captures_release_claim($capture_resource_id, $claim_token);
        if ($order_lock_held) {
            paypalac_orphan_captures_release_order_lock($paypal_order_id);
        }
        if (!class_exists('paypalac', false)) {
            $messageStack->add_session(ERROR_MODULE_MISSING, 'error');
        } else {
            $messageStack->add_session(ERROR_API_CREDENTIALS, 'error');
        }
        zen_redirect(zen_href_link(FILENAME_PAYPALAC_ORPHAN_CAPTURES));
    }

    $resource_type = strtolower(trim((string)($recheck['resource_type'] ?? '')));
    $api_ok = false;
    $result_id = '';
    $used_void = ($resource_type === 'authorization');

    if ($used_void) {
        $void_response = $ppr->voidPayment($capture_resource_id);
        if ($void_response !== false) {
            $api_ok = true;
            $result_id = (string)($void_response['id'] ?? $capture_resource_id);
        } else {
            $error_info = method_exists($ppr, 'getErrorInfo') ? $ppr->getErrorInfo() : [];
            $messageStack->add_session(
                sprintf(
                    ERROR_VOID_FAILED,
                    zen_output_string_protected($capture_resource_id),
                    zen_output_string_protected(json_encode($error_info))
                ),
                'error'
            );
        }
    } else {
        // capture, or unknown legacy rows — try capture refund; auth-shaped unknowns may fail.
        $invoice_id = 'PPAC-ORPHAN-ADMIN-' . substr($capture_resource_id, 0, 32);
        $payer_note = 'Admin refund of orphan checkout capture (no Zen Cart order).';
        $refund_response = $ppr->refundCaptureFull($capture_resource_id, $invoice_id, $payer_note);

        if (is_array($refund_response)) {
            $status = strtoupper((string)($refund_response['status'] ?? ''));
            if (in_array($status, ['COMPLETED', 'PENDING'], true)) {
                $api_ok = true;
                $result_id = (string)($refund_response['id'] ?? '');
            }
        }

        // Unknown type: if capture-refund failed, try void (authorization id).
        if ($api_ok === false && $resource_type === '') {
            $void_response = $ppr->voidPayment($capture_resource_id);
            if ($void_response !== false) {
                $api_ok = true;
                $used_void = true;
                $result_id = (string)($void_response['id'] ?? $capture_resource_id);
            }
        }

        if ($api_ok === false) {
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
    }

    if ($api_ok) {
        paypalac_orphan_captures_delete_claimed_row(
            $capture_resource_id,
            $claim_token,
            PAYPALAC_ORPHAN_ADMIN_MIN_AGE_MINUTES
        );
        if ($used_void) {
            $messageStack->add_session(
                sprintf(SUCCESS_VOID, zen_output_string_protected($capture_resource_id)),
                'success'
            );
        } else {
            $messageStack->add_session(
                sprintf(
                    SUCCESS_REFUND,
                    zen_output_string_protected($capture_resource_id),
                    zen_output_string_protected($result_id !== '' ? $result_id : 'n/a')
                ),
                'success'
            );
        }
    } else {
        // Failed PayPal call: free claim so cron alerts keep working (alerted_at untouched).
        paypalac_orphan_captures_release_claim($capture_resource_id, $claim_token);
    }

    if ($order_lock_held) {
        paypalac_orphan_captures_release_order_lock($paypal_order_id);
    }

    zen_redirect(zen_href_link(FILENAME_PAYPALAC_ORPHAN_CAPTURES));
}

$rows = [];
$result = $db->Execute(
    "SELECT r.capture_resource_id, r.resource_type, r.customers_id, r.paypal_order_id, r.created_at, r.alerted_at,
            c.customers_firstname, c.customers_lastname, c.customers_email_address
       FROM " . $reservation_table . " r
  LEFT JOIN " . TABLE_CUSTOMERS . " c ON c.customers_id = r.customers_id
      WHERE r.orders_id = 0
        AND r.created_at < DATE_SUB(NOW(), INTERVAL " . (int)PAYPALAC_ORPHAN_ADMIN_MIN_AGE_MINUTES . " MINUTE)
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
                <th><?php echo TABLE_HEADING_TYPE; ?></th>
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
                $resource_type = strtolower(trim((string)($row['resource_type'] ?? '')));
                $is_auth = ($resource_type === 'authorization');
                $type_label = TEXT_TYPE_UNKNOWN;
                if ($resource_type === 'capture') {
                    $type_label = TEXT_TYPE_CAPTURE;
                } elseif ($is_auth) {
                    $type_label = TEXT_TYPE_AUTHORIZATION;
                }
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
                $confirm = $is_auth ? TEXT_CONFIRM_VOID : TEXT_CONFIRM_REFUND;
                $action_label = $is_auth ? BUTTON_VOID : BUTTON_REFUND;
                ?>
                <tr>
                    <td class="ppac-orphan-mono"><?php echo zen_output_string_protected($capture_id); ?></td>
                    <td><?php echo zen_output_string_protected($type_label); ?></td>
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
                                    onclick="return confirm(<?php echo json_encode($confirm, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>);">
                                <?php echo $action_label; ?>
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
