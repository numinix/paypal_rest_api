<?php
/**
 * Admin: PayPal Advanced Checkout orphan capture reservations (orders_id = 0).
 *
 * List / refund or void via PayPal API / link an existing Zen order (row kept, orders_id stays 0).
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

// Encapsulated package paths (overlay copies under DIR_FS_CATALOG are purged on install).
$ppacPluginCatalog = dirname(__DIR__) . '/catalog';
require_once $ppacPluginCatalog . '/includes/modules/payment/paypal/ppacAutoload.php';
require_once $ppacPluginCatalog . '/includes/modules/payment/paypal/paypal_common.php';

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
        $modulePath = dirname(__DIR__) . '/catalog/includes/modules/payment/paypalac.php';
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
function paypalac_orphan_captures_amount_label(array $row): string
{
    $amount = $row['amount'] ?? null;
    if ($amount === null || $amount === '') {
        return TEXT_AMOUNT_UNKNOWN;
    }
    $currency = trim((string)($row['currency'] ?? ''));
    $label = number_format((float)$amount, 2, '.', '');
    if ($currency !== '') {
        $label .= ' ' . $currency;
    }
    return $label;
}

function paypalac_orphan_captures_aged_sql(int $min_age_minutes): string
{
    return "orders_id = 0
            AND related_orders_id = 0
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
        "SELECT capture_resource_id, resource_type, customers_id, paypal_order_id, created_at, alerted_at,
                refund_id, refund_status
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
 * Same name as checkout orphan auto-refund: PayPal order id, else capture id.
 */
function paypalac_orphan_captures_order_lock_name(string $paypal_order_id, string $capture_resource_id = ''): string
{
    $paypal_order_id = trim($paypal_order_id);
    if ($paypal_order_id !== '') {
        return 'ppac_' . md5($paypal_order_id);
    }

    return 'ppac_' . md5('cap:' . trim($capture_resource_id));
}

/**
 * Acquire the shared orphan lock (PayPal order id, or capture-id fallback).
 */
function paypalac_orphan_captures_acquire_order_lock(string $paypal_order_id, string $capture_resource_id = ''): bool
{
    global $db;

    if (trim($paypal_order_id) === '' && trim($capture_resource_id) === '') {
        return true;
    }

    $escaped = zen_db_input(paypalac_orphan_captures_order_lock_name($paypal_order_id, $capture_resource_id));
    $result = $db->Execute("SELECT GET_LOCK('" . $escaped . "', 5) AS ppac_orphan_lock");
    $acquired = isset($result->fields['ppac_orphan_lock']) ? (int)$result->fields['ppac_orphan_lock'] : 0;

    return $acquired === 1;
}

function paypalac_orphan_captures_release_order_lock(string $paypal_order_id, string $capture_resource_id = ''): void
{
    global $db;

    if (trim($paypal_order_id) === '' && trim($capture_resource_id) === '') {
        return;
    }

    $escaped = zen_db_input(paypalac_orphan_captures_order_lock_name($paypal_order_id, $capture_resource_id));
    $db->Execute("SELECT RELEASE_LOCK('" . $escaped . "')");
}

/**
 * Load aged orphan row for confirm panel (read-only; no claim).
 *
 * @return array<string,mixed>|null
 */
function paypalac_orphan_captures_load_aged_row(string $capture_resource_id, int $min_age_minutes): ?array
{
    global $db, $reservation_table;

    $capture_resource_id = trim($capture_resource_id);
    if ($capture_resource_id === '') {
        return null;
    }

    $esc = zen_db_input($capture_resource_id);
    $chk = $db->Execute(
        "SELECT r.capture_resource_id, r.resource_type, r.customers_id, r.paypal_order_id, r.created_at, r.alerted_at,
                r.amount, r.currency,
                c.customers_firstname, c.customers_lastname, c.customers_email_address
           FROM " . $reservation_table . " r
      LEFT JOIN " . TABLE_CUSTOMERS . " c ON c.customers_id = r.customers_id
          WHERE r.capture_resource_id = '" . $esc . "'
            AND " . paypalac_orphan_captures_aged_sql($min_age_minutes) . "
          LIMIT 1"
    );
    if ($chk->EOF) {
        return null;
    }

    return $chk->fields;
}

$confirm_panel = null;
$action = isset($_POST['action']) ? trim((string)$_POST['action']) : '';
$confirmed = isset($_POST['confirmed']) && (string)$_POST['confirmed'] === '1';

if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && in_array($action, ['refund_confirm', 'dismiss_confirm', 'refund', 'dismiss'], true)
) {
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

    // Step 1: show confirm panel only (no claim / PayPal / delete).
    if ($action === 'refund_confirm' || $action === 'dismiss_confirm') {
        $row = paypalac_orphan_captures_load_aged_row(
            $capture_resource_id,
            PAYPALAC_ORPHAN_ADMIN_MIN_AGE_MINUTES
        );
        if ($row === null) {
            $messageStack->add_session(ERROR_ROW_NOT_FOUND, 'error');
            zen_redirect(zen_href_link(FILENAME_PAYPALAC_ORPHAN_CAPTURES));
        }
        $confirm_panel = [
            'execute_action' => ($action === 'dismiss_confirm') ? 'dismiss' : 'refund',
            'row' => $row,
        ];
    } elseif (($action === 'refund' || $action === 'dismiss') && !$confirmed) {
        $messageStack->add_session(ERROR_CONFIRM_REQUIRED, 'error');
        zen_redirect(zen_href_link(FILENAME_PAYPALAC_ORPHAN_CAPTURES));
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && ($action === 'refund' || $action === 'dismiss')
    && $confirmed
) {
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

    if (!paypalac_orphan_captures_acquire_order_lock($paypal_order_id, $capture_resource_id)) {
        paypalac_orphan_captures_release_claim($capture_resource_id, $claim_token);
        $messageStack->add_session(ERROR_CHECKOUT_LOCK, 'error');
        zen_redirect(zen_href_link(FILENAME_PAYPALAC_ORPHAN_CAPTURES));
    }
    $order_lock_held = true;

    // Checkout may have linked orders_id while we waited for GET_LOCK.
    $recheck = paypalac_orphan_captures_recheck_claim(
        $capture_resource_id,
        $claim_token,
        PAYPALAC_ORPHAN_ADMIN_MIN_AGE_MINUTES
    );
    if ($recheck === null) {
        paypalac_orphan_captures_release_claim($capture_resource_id, $claim_token);
        if ($order_lock_held) {
            paypalac_orphan_captures_release_order_lock($paypal_order_id, $capture_resource_id);
        }
        $messageStack->add_session(ERROR_ROW_NOT_FOUND, 'error');
        zen_redirect(zen_href_link(FILENAME_PAYPALAC_ORPHAN_CAPTURES));
    }

    if ($action === 'dismiss') {
        $abort_link = function (string $message) use (
            $capture_resource_id,
            $claim_token,
            $order_lock_held,
            $paypal_order_id,
            $messageStack
        ): void {
            paypalac_orphan_captures_release_claim($capture_resource_id, $claim_token);
            if ($order_lock_held) {
                paypalac_orphan_captures_release_order_lock($paypal_order_id, $capture_resource_id);
            }
            $messageStack->add_session($message, 'error');
            zen_redirect(zen_href_link(FILENAME_PAYPALAC_ORPHAN_CAPTURES));
        };

        $refund_status = strtoupper(trim((string)($recheck['refund_status'] ?? '')));
        if ($refund_status !== '') {
            $abort_link(sprintf(ERROR_REFUND_STATUS_BLOCKS_LINK, zen_output_string_protected($refund_status)));
        }
        $related_orders_id = (int)($_POST['related_orders_id'] ?? 0);
        if ($related_orders_id <= 0) {
            $abort_link(ERROR_RELATED_ORDER_REQUIRED);
        }
        $order_exists = $db->Execute(
            "SELECT orders_id, customers_id FROM " . TABLE_ORDERS . " WHERE orders_id = " . $related_orders_id . " LIMIT 1"
        );
        if ($order_exists->EOF) {
            $abort_link(sprintf(ERROR_RELATED_ORDER_NOT_FOUND, $related_orders_id));
        }
        $order_customer_id = (int)($order_exists->fields['customers_id'] ?? 0);
        $reservation_customer_id = (int)($recheck['customers_id'] ?? 0);
        if ($order_customer_id !== $reservation_customer_id) {
            $abort_link(sprintf(
                ERROR_RELATED_ORDER_CUSTOMER,
                $related_orders_id,
                $order_customer_id,
                $reservation_customer_id
            ));
        }
        $esc = zen_db_input($capture_resource_id);
        $esc_token = zen_db_input($claim_token);
        $taken = $db->Execute(
            "SELECT capture_resource_id
               FROM " . $reservation_table . "
              WHERE capture_resource_id != '" . $esc . "'
                AND (orders_id = " . $related_orders_id . " OR related_orders_id = " . $related_orders_id . ")
              LIMIT 1"
        );
        if (!$taken->EOF) {
            $abort_link(sprintf(
                ERROR_RELATED_ORDER_ALREADY_LINKED,
                $related_orders_id,
                zen_output_string_protected((string)$taken->fields['capture_resource_id'])
            ));
        }
        $db->Execute(
            "UPDATE " . $reservation_table . "
                SET related_orders_id = " . $related_orders_id . ",
                    admin_claim_token = '',
                    admin_claimed_at = NULL
              WHERE capture_resource_id = '" . $esc . "'
                AND admin_claim_token = '" . $esc_token . "'
                AND orders_id = 0
                AND related_orders_id = 0
                AND (refund_status = '' OR refund_status IS NULL)
              LIMIT 1"
        );
        if ($db->affectedRows() > 0) {
            $messageStack->add_session(
                sprintf(SUCCESS_LINK_ORDER, zen_output_string_protected($capture_resource_id), $related_orders_id),
                'success'
            );
        } else {
            paypalac_orphan_captures_release_claim($capture_resource_id, $claim_token);
            $messageStack->add_session(ERROR_ROW_NOT_FOUND, 'error');
        }
        if ($order_lock_held) {
            paypalac_orphan_captures_release_order_lock($paypal_order_id, $capture_resource_id);
        }
        zen_redirect(zen_href_link(FILENAME_PAYPALAC_ORPHAN_CAPTURES));
    }

    // action === refund (capture refund or authorization void)
    $ppr = paypalac_orphan_captures_api();
    if ($ppr === null) {
        paypalac_orphan_captures_release_claim($capture_resource_id, $claim_token);
        if ($order_lock_held) {
            paypalac_orphan_captures_release_order_lock($paypal_order_id, $capture_resource_id);
        }
        if (!class_exists('paypalac', false)) {
            $messageStack->add_session(ERROR_MODULE_MISSING, 'error');
        } else {
            $messageStack->add_session(ERROR_API_CREDENTIALS, 'error');
        }
        zen_redirect(zen_href_link(FILENAME_PAYPALAC_ORPHAN_CAPTURES));
    }

    $resource_type = strtolower(trim((string)($recheck['resource_type'] ?? '')));
    $existing_refund_status = strtoupper(trim((string)($recheck['refund_status'] ?? '')));
    $api_ok = false;
    $refund_pending = false;
    $result_id = '';
    $used_void = ($resource_type === 'authorization');

    // Already submitted and still PENDING — do not call PayPal again.
    if (!$used_void && $existing_refund_status === 'PENDING') {
        paypalac_orphan_captures_release_claim($capture_resource_id, $claim_token);
        if ($order_lock_held) {
            paypalac_orphan_captures_release_order_lock($paypal_order_id, $capture_resource_id);
        }
        $messageStack->add_session(
            sprintf(
                SUCCESS_REFUND_PENDING,
                zen_output_string_protected($capture_resource_id),
                zen_output_string_protected((string)($recheck['refund_id'] ?? 'n/a'))
            ),
            'success'
        );
        zen_redirect(zen_href_link(FILENAME_PAYPALAC_ORPHAN_CAPTURES));
    }

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
            if ($status === 'COMPLETED' || $status === 'PENDING') {
                $api_ok = true;
                $refund_pending = ($status === 'PENDING');
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
        if ($used_void || !$refund_pending) {
            // Void success or COMPLETED refund — safe to drop the reservation row.
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
            // PENDING refund: persist id/status so checkout auto-refund will not retry.
            $paypalCommon->markOrphanCaptureRefundPending($capture_resource_id, $result_id);
            paypalac_orphan_captures_release_claim($capture_resource_id, $claim_token);
            $messageStack->add_session(
                sprintf(
                    SUCCESS_REFUND_PENDING,
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
        paypalac_orphan_captures_release_order_lock($paypal_order_id, $capture_resource_id);
    }

    zen_redirect(zen_href_link(FILENAME_PAYPALAC_ORPHAN_CAPTURES));
}

$rows = [];
$result = $db->Execute(
    "SELECT r.capture_resource_id, r.resource_type, r.customers_id, r.paypal_order_id, r.created_at, r.alerted_at,
            r.refund_status, r.amount, r.currency, r.related_orders_id,
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
        .ppac-orphan-confirm {
            max-width: 720px;
            margin: 0 0 1.5rem;
            padding: 1rem 1.25rem;
            border: 1px solid #c9a227;
            background: #fff8e6;
        }
        .ppac-orphan-confirm h2 { margin: 0 0 .75rem; font-size: 1.25rem; }
        .ppac-orphan-confirm dl { margin: 0 0 1rem; }
        .ppac-orphan-confirm dt { font-weight: 600; margin-top: .35rem; }
        .ppac-orphan-confirm dd { margin: 0 0 .25rem; }
        .ppac-orphan-confirm-actions form { display: inline-block; margin: 0 .5rem .25rem 0; }
    </style>
</head>
<body>
<?php require DIR_WS_INCLUDES . 'header.php'; ?>
<div class="container-fluid">
    <h1><?php echo HEADING_TITLE; ?></h1>
    <p class="ppac-orphan-intro"><?php echo TEXT_ORPHAN_INTRO; ?></p>

    <?php if (is_array($confirm_panel)) {
        $crow = $confirm_panel['row'];
        $c_capture = (string)($crow['capture_resource_id'] ?? '');
        $c_type = strtolower(trim((string)($crow['resource_type'] ?? '')));
        $c_is_auth = ($c_type === 'authorization');
        $c_type_label = TEXT_TYPE_UNKNOWN;
        if ($c_type === 'capture') {
            $c_type_label = TEXT_TYPE_CAPTURE;
        } elseif ($c_is_auth) {
            $c_type_label = TEXT_TYPE_AUTHORIZATION;
        }
        $c_cid = (int)($crow['customers_id'] ?? 0);
        $c_name = trim((string)($crow['customers_firstname'] ?? '') . ' ' . (string)($crow['customers_lastname'] ?? ''));
        $c_email = trim((string)($crow['customers_email_address'] ?? ''));
        if ($c_name !== '' || $c_email !== '') {
            $c_customer = ($c_name !== '' ? zen_output_string_protected($c_name) : '')
                . ($c_email !== '' ? ' &lt;' . zen_output_string_protected($c_email) . '&gt;' : '')
                . ' (#' . $c_cid . ')';
        } else {
            $c_customer = sprintf(TEXT_CUSTOMER_UNKNOWN, $c_cid);
        }
        $execute_action = (string)$confirm_panel['execute_action'];
        if ($execute_action === 'dismiss') {
            $confirm_heading = TEXT_CONFIRM_HEADING_LINK;
            $confirm_intro = TEXT_CONFIRM_LINK;
            $confirm_btn_class = 'btn-primary';
            $confirm_btn_label = BUTTON_LINK_ORDER;
        } elseif ($c_is_auth) {
            $confirm_heading = TEXT_CONFIRM_HEADING_VOID;
            $confirm_intro = TEXT_CONFIRM_VOID;
            $confirm_btn_class = 'btn-danger';
            $confirm_btn_label = BUTTON_VOID;
        } else {
            $confirm_heading = TEXT_CONFIRM_HEADING_REFUND;
            $confirm_intro = TEXT_CONFIRM_REFUND;
            $confirm_btn_class = 'btn-danger';
            $confirm_btn_label = BUTTON_REFUND;
        }
        ?>
        <div class="ppac-orphan-confirm">
            <h2><?php echo $confirm_heading; ?></h2>
            <p><?php echo $confirm_intro; ?></p>
            <dl>
                <dt><?php echo TABLE_HEADING_CAPTURE; ?></dt>
                <dd class="ppac-orphan-mono"><?php echo zen_output_string_protected($c_capture); ?></dd>
                <dt><?php echo TABLE_HEADING_TYPE; ?></dt>
                <dd><?php echo zen_output_string_protected($c_type_label); ?></dd>
                <dt><?php echo TABLE_HEADING_CUSTOMER; ?></dt>
                <dd><?php echo $c_customer; ?></dd>
                <dt><?php echo TABLE_HEADING_PAYPAL_ORDER; ?></dt>
                <dd class="ppac-orphan-mono"><?php echo zen_output_string_protected((string)($crow['paypal_order_id'] ?? '')); ?></dd>
                <dt><?php echo TABLE_HEADING_AMOUNT; ?></dt>
                <dd><?php echo zen_output_string_protected(paypalac_orphan_captures_amount_label($crow)); ?></dd>
                <dt><?php echo TABLE_HEADING_CREATED; ?></dt>
                <dd><?php echo zen_output_string_protected((string)($crow['created_at'] ?? '')); ?></dd>
            </dl>
            <?php if ($execute_action === 'dismiss') { ?>
                <label for="ppac-related-orders-id"><?php echo TEXT_RELATED_ORDER_ID; ?></label>
            <?php } ?>
            <div class="ppac-orphan-confirm-actions">
                <?php echo zen_draw_form('orphan_confirm_execute', FILENAME_PAYPALAC_ORPHAN_CAPTURES, '', 'post'); ?>
                    <?php echo zen_draw_hidden_field('securityToken', $_SESSION['securityToken'] ?? ''); ?>
                    <?php echo zen_draw_hidden_field('action', $execute_action); ?>
                    <?php echo zen_draw_hidden_field('confirmed', '1'); ?>
                    <?php echo zen_draw_hidden_field('capture_resource_id', $c_capture); ?>
                    <?php if ($execute_action === 'dismiss') { ?>
                        <input type="number" name="related_orders_id" id="ppac-related-orders-id" min="1" required
                               class="form-control" style="max-width: 12rem; display: inline-block; margin-right: .5rem;">
                    <?php } ?>
                    <button type="submit" class="btn <?php echo $confirm_btn_class; ?>">
                        <?php echo BUTTON_CONFIRM; ?> — <?php echo $confirm_btn_label; ?>
                    </button>
                </form>
                <a class="btn btn-default" href="<?php echo zen_href_link(FILENAME_PAYPALAC_ORPHAN_CAPTURES); ?>">
                    <?php echo BUTTON_CANCEL; ?>
                </a>
            </div>
        </div>
    <?php } ?>

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
                <th><?php echo TABLE_HEADING_AMOUNT; ?></th>
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
                $refund_status = strtoupper(trim((string)($row['refund_status'] ?? '')));
                $related_orders_id = (int)($row['related_orders_id'] ?? 0);
                $action_label = $is_auth ? BUTTON_VOID : BUTTON_REFUND;
                ?>
                <tr>
                    <td class="ppac-orphan-mono"><?php echo zen_output_string_protected($capture_id); ?></td>
                    <td><?php echo zen_output_string_protected($type_label); ?></td>
                    <td><?php echo $customer_label; ?></td>
                    <td class="ppac-orphan-mono"><?php echo zen_output_string_protected((string)($row['paypal_order_id'] ?? '')); ?></td>
                    <td><?php echo zen_output_string_protected(paypalac_orphan_captures_amount_label($row)); ?></td>
                    <td><?php echo zen_output_string_protected((string)($row['created_at'] ?? '')); ?></td>
                    <td><?php echo $alerted !== '' ? zen_output_string_protected($alerted) : TEXT_NEVER_ALERTED; ?></td>
                    <td class="ppac-orphan-actions">
                        <?php if ($related_orders_id > 0) { ?>
                            <?php echo sprintf(TEXT_LINKED_ORDER, $related_orders_id); ?>
                        <?php } else { ?>
                        <?php echo zen_draw_form('orphan_refund_' . md5($capture_id), FILENAME_PAYPALAC_ORPHAN_CAPTURES, '', 'post'); ?>
                            <?php echo zen_draw_hidden_field('securityToken', $_SESSION['securityToken'] ?? ''); ?>
                            <?php echo zen_draw_hidden_field('action', 'refund_confirm'); ?>
                            <?php echo zen_draw_hidden_field('capture_resource_id', $capture_id); ?>
                            <button type="submit" class="btn btn-warning">
                                <?php echo $action_label; ?>
                            </button>
                        </form>
                        <?php if ($refund_status === '') { ?>
                        <?php echo zen_draw_form('orphan_dismiss_' . md5($capture_id), FILENAME_PAYPALAC_ORPHAN_CAPTURES, '', 'post'); ?>
                            <?php echo zen_draw_hidden_field('securityToken', $_SESSION['securityToken'] ?? ''); ?>
                            <?php echo zen_draw_hidden_field('action', 'dismiss_confirm'); ?>
                            <?php echo zen_draw_hidden_field('capture_resource_id', $capture_id); ?>
                            <button type="submit" class="btn btn-default">
                                <?php echo BUTTON_LINK_ORDER; ?>
                            </button>
                        </form>
                        <?php } else { ?>
                            <span><?php echo sprintf(TEXT_LINK_UNAVAILABLE_REFUND, zen_output_string_protected($refund_status)); ?></span>
                        <?php } ?>
                        <?php } ?>
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
