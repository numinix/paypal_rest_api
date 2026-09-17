<?php
/**
 * Admin rebill: charge a PayPal Advanced Checkout vaulted/saved card.
 *
 * Replaces Payflow Manager-style reference rebills for REST vault tokens.
 *
 * @copyright Copyright 2025-2026 Numinix / Zen Cart Development Team
 * @license http://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */

require 'includes/application_top.php';

// Self-register under Customers if the module upgrade path has not run yet.
if (function_exists('zen_page_key_exists') && function_exists('zen_register_admin_page') && !zen_page_key_exists('paypalacRebill')) {
    zen_register_admin_page(
        'paypalacRebill',
        'BOX_PAYPALAC_REBILL',
        'FILENAME_PAYPALAC_REBILL',
        '',
        'customers',
        'Y',
        12
    );
}

$autoloaderPath = DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/paypal/PayPalAdvancedCheckout/Compatibility/LanguageAutoloader.php';
if (is_file($autoloaderPath)) {
    require_once $autoloaderPath;
    \PayPalAdvancedCheckout\Compatibility\LanguageAutoloader::register();
}

require_once DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/paypal/ppacAutoload.php';

if (file_exists(DIR_FS_CATALOG . DIR_WS_CLASSES . 'paypalacSavedCardRecurring.php')) {
    require_once DIR_FS_CATALOG . DIR_WS_CLASSES . 'paypalacSavedCardRecurring.php';
}

use PayPalAdvancedCheckout\Common\SavedCreditCardsManager;
use PayPalAdvancedCheckout\Common\VaultManager;

if (class_exists(SavedCreditCardsManager::class)) {
    SavedCreditCardsManager::ensureSchema();
}
if (class_exists(VaultManager::class)) {
    VaultManager::ensureSchema();
}

define('FILENAME_PAYPALAC_REBILL', basename(__FILE__));

require DIR_WS_LANGUAGES . $_SESSION['language'] . '/paypalac_rebill.php';

$action = isset($_REQUEST['action']) ? trim((string)$_REQUEST['action']) : '';
$customers_id = isset($_REQUEST['customers_id']) ? (int)$_REQUEST['customers_id'] : 0;
$saved_credit_card_id = isset($_REQUEST['saved_credit_card_id']) ? (int)$_REQUEST['saved_credit_card_id'] : 0;
$search = isset($_GET['search']) ? trim((string)$_GET['search']) : '';

/**
 * @return list<array<string,mixed>>
 */
function paypalac_rebill_load_customer_cards(int $customers_id): array
{
    global $db;
    if ($customers_id <= 0 || !defined('TABLE_SAVED_CREDIT_CARDS')) {
        return [];
    }

    $cards = [];
    $sql = "SELECT saved_credit_card_id, customers_id, type, last_digits, expiry_month, expiry_year,
                   holder_name, vault_id, paypal_transaction_id, api_type, is_deleted, date_added
              FROM " . TABLE_SAVED_CREDIT_CARDS . "
             WHERE customers_id = " . $customers_id . "
               AND (is_deleted = 0 OR is_deleted IS NULL)
          ORDER BY saved_credit_card_id DESC";
    $result = $db->Execute($sql);
    while (!$result->EOF) {
        $row = $result->fields;
        $vaultId = trim((string)($row['vault_id'] ?? ''));
        $apiType = strtolower(trim((string)($row['api_type'] ?? '')));
        if ($vaultId === '' && !in_array($apiType, ['paypalwpp', 'payflow', 'wpp'], true)) {
            // Post-#221 rows may store vault token only in paypal_transaction_id.
            $vaultId = trim((string)($row['paypal_transaction_id'] ?? ''));
        }
        if ($vaultId !== '') {
            $row['effective_vault_id'] = $vaultId;
            $cards[] = $row;
        }
        $result->MoveNext();
    }
    return $cards;
}

/**
 * @return list<array<string,mixed>>
 */
function paypalac_rebill_search_customers(string $search): array
{
    global $db;
    $search = trim($search);
    if ($search === '') {
        return [];
    }

    $safe = zen_db_input($search);
    $where = "c.customers_email_address LIKE '%" . $safe . "%'
           OR c.customers_lastname LIKE '%" . $safe . "%'
           OR c.customers_firstname LIKE '%" . $safe . "%'";
    if (ctype_digit($search)) {
        $where .= ' OR c.customers_id = ' . (int)$search;
    }

    $rows = [];
    $result = $db->Execute(
        "SELECT c.customers_id, c.customers_firstname, c.customers_lastname, c.customers_email_address
           FROM " . TABLE_CUSTOMERS . " c
          WHERE " . $where . "
       ORDER BY c.customers_lastname, c.customers_firstname
          LIMIT 25"
    );
    while (!$result->EOF) {
        $rows[] = $result->fields;
        $result->MoveNext();
    }
    return $rows;
}

if ($action === 'charge' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $sessionToken = $_SESSION['securityToken'] ?? '';
    $requestToken = $_POST['securityToken'] ?? '';
    if ($sessionToken === '' || $requestToken !== $sessionToken) {
        $messageStack->add_session(ERROR_SECURITY_TOKEN, 'error');
        zen_redirect(zen_href_link(FILENAME_PAYPALAC_REBILL, 'customers_id=' . $customers_id));
    }

    $amount = isset($_POST['amount']) ? (float)$_POST['amount'] : 0;
    $currency = isset($_POST['currency']) ? trim((string)$_POST['currency']) : (defined('DEFAULT_CURRENCY') ? DEFAULT_CURRENCY : 'USD');
    $comments = isset($_POST['comments']) ? trim((string)$_POST['comments']) : '';
    $existing_orders_id = isset($_POST['orders_id']) ? (int)$_POST['orders_id'] : 0;
    $create_order = !empty($_POST['create_order']);

    if (!class_exists('paypalacSavedCardRecurring')) {
        $messageStack->add_session(ERROR_REBILL_CLASS_MISSING, 'error');
        zen_redirect(zen_href_link(FILENAME_PAYPALAC_REBILL, 'customers_id=' . $customers_id));
    }

    $rebiller = new paypalacSavedCardRecurring();
    $charge = $rebiller->charge_vaulted_card($saved_credit_card_id, $amount, [
        'currency' => $currency,
    ]);

    if (empty($charge['success'])) {
        $messageStack->add_session(
            sprintf(ERROR_REBILL_CHARGE_FAILED, zen_output_string_protected($charge['error'] ?? 'Unknown error')),
            'error'
        );
        zen_redirect(zen_href_link(
            FILENAME_PAYPALAC_REBILL,
            'customers_id=' . $customers_id . '&saved_credit_card_id=' . $saved_credit_card_id
        ));
    }

    $orders_id = 0;
    if ($create_order || $existing_orders_id > 0) {
        $orderResult = $rebiller->create_rebill_order($charge, [
            'customers_id' => $customers_id,
            'amount' => $amount,
            'currency' => $currency,
            'comments' => $comments,
            'orders_id' => $existing_orders_id,
            'product_name' => TEXT_REBILL_PRODUCT_NAME,
        ]);
        if (empty($orderResult['success'])) {
            $messageStack->add_session(
                sprintf(
                    ERROR_REBILL_ORDER_FAILED,
                    zen_output_string_protected($charge['transaction_id'] ?? ''),
                    zen_output_string_protected($orderResult['error'] ?? 'Unknown error')
                ),
                'warning'
            );
            zen_redirect(zen_href_link(FILENAME_PAYPALAC_REBILL, 'customers_id=' . $customers_id));
        }
        $orders_id = (int)$orderResult['orders_id'];
        $messageStack->add_session(
            sprintf(
                SUCCESS_REBILL_WITH_ORDER,
                zen_output_string_protected($charge['transaction_id'] ?? ''),
                $orders_id
            ),
            'success'
        );
    } else {
        $messageStack->add_session(
            sprintf(SUCCESS_REBILL_CHARGE_ONLY, zen_output_string_protected($charge['transaction_id'] ?? '')),
            'success'
        );
    }

    $redirect = 'customers_id=' . $customers_id;
    if ($orders_id > 0) {
        $redirect .= '&last_orders_id=' . $orders_id;
    }
    zen_redirect(zen_href_link(FILENAME_PAYPALAC_REBILL, $redirect));
}

$customer = null;
$cards = [];
if ($customers_id > 0) {
    $customerResult = $db->Execute(
        "SELECT customers_id, customers_firstname, customers_lastname, customers_email_address
           FROM " . TABLE_CUSTOMERS . "
          WHERE customers_id = " . $customers_id . "
          LIMIT 1"
    );
    if (!$customerResult->EOF) {
        $customer = $customerResult->fields;
        $cards = paypalac_rebill_load_customer_cards($customers_id);
    }
}

$searchResults = ($search !== '' && $customers_id <= 0) ? paypalac_rebill_search_customers($search) : [];
$last_orders_id = isset($_GET['last_orders_id']) ? (int)$_GET['last_orders_id'] : 0;
$default_currency = defined('DEFAULT_CURRENCY') ? DEFAULT_CURRENCY : 'USD';

?>
<!doctype html>
<html <?php echo HTML_PARAMS; ?>>
<head>
    <?php require DIR_WS_INCLUDES . 'admin_html_head.php'; ?>
    <link rel="stylesheet" href="includes/css/paypalac_subscriptions.css">
    <style>
        .paypalac-rebill-card { max-width: 720px; margin: 1.5rem 0; padding: 1.25rem; border: 1px solid #ddd; background: #fff; }
        .paypalac-rebill-card h2 { margin-top: 0; font-size: 1.25rem; }
        .paypalac-rebill-cards td, .paypalac-rebill-cards th { padding: .4rem .6rem; }
        .paypalac-rebill-help { color: #555; margin-bottom: 1rem; }
        .paypalac-rebill-actions { margin-top: 1rem; }
    </style>
</head>
<body>
<?php require DIR_WS_INCLUDES . 'header.php'; ?>
<div class="container-fluid">
    <h1><?php echo HEADING_TITLE; ?></h1>
    <p class="paypalac-rebill-help"><?php echo TEXT_REBILL_INTRO; ?></p>

    <div class="paypalac-rebill-card">
        <h2><?php echo TEXT_FIND_CUSTOMER; ?></h2>
        <?php echo zen_draw_form('paypalac_rebill_search', FILENAME_PAYPALAC_REBILL, '', 'get', 'class="nmx-form-inline"'); ?>
            <label for="search"><?php echo TEXT_SEARCH_LABEL; ?></label>
            <?php echo zen_draw_input_field('search', $search, 'id="search" class="nmx-form-control" style="min-width:240px" placeholder="' . htmlspecialchars(TEXT_SEARCH_PLACEHOLDER, ENT_QUOTES, 'UTF-8') . '"'); ?>
            <button type="submit" class="nmx-btn nmx-btn-primary"><?php echo BUTTON_SEARCH; ?></button>
            <a class="nmx-btn nmx-btn-default" href="<?php echo zen_href_link(FILENAME_PAYPALAC_REBILL); ?>"><?php echo BUTTON_RESET; ?></a>
        </form>

        <?php if (!empty($searchResults)) { ?>
            <table class="table table-striped" style="margin-top:1rem">
                <thead>
                <tr>
                    <th><?php echo TABLE_HEADING_ID; ?></th>
                    <th><?php echo TABLE_HEADING_NAME; ?></th>
                    <th><?php echo TABLE_HEADING_EMAIL; ?></th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($searchResults as $row) { ?>
                    <tr>
                        <td><?php echo (int)$row['customers_id']; ?></td>
                        <td><?php echo zen_output_string_protected($row['customers_firstname'] . ' ' . $row['customers_lastname']); ?></td>
                        <td><?php echo zen_output_string_protected($row['customers_email_address']); ?></td>
                        <td>
                            <a class="nmx-btn nmx-btn-sm nmx-btn-primary"
                               href="<?php echo zen_href_link(FILENAME_PAYPALAC_REBILL, 'customers_id=' . (int)$row['customers_id']); ?>">
                                <?php echo BUTTON_SELECT; ?>
                            </a>
                        </td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        <?php } elseif ($search !== '' && $customers_id <= 0) { ?>
            <p><?php echo TEXT_NO_CUSTOMERS_FOUND; ?></p>
        <?php } ?>
    </div>

    <?php if (is_array($customer)) { ?>
        <div class="paypalac-rebill-card">
            <h2><?php echo TEXT_CUSTOMER; ?>:
                #<?php echo (int)$customer['customers_id']; ?>
                <?php echo zen_output_string_protected($customer['customers_firstname'] . ' ' . $customer['customers_lastname']); ?>
                (<?php echo zen_output_string_protected($customer['customers_email_address']); ?>)
            </h2>

            <?php if ($last_orders_id > 0) { ?>
                <p>
                    <?php echo TEXT_LAST_ORDER; ?>:
                    <a href="<?php echo zen_href_link(FILENAME_ORDERS, 'oID=' . $last_orders_id . '&action=edit'); ?>">
                        #<?php echo $last_orders_id; ?>
                    </a>
                </p>
            <?php } ?>

            <?php if (empty($cards)) { ?>
                <p><?php echo TEXT_NO_VAULT_CARDS; ?></p>
            <?php } else { ?>
                <?php echo zen_draw_form('paypalac_rebill_charge', FILENAME_PAYPALAC_REBILL, 'action=charge', 'post', 'onsubmit="return confirm(\'' . htmlspecialchars(TEXT_CONFIRM_REBILL, ENT_QUOTES, 'UTF-8') . '\');"'); ?>
                    <?php echo zen_draw_hidden_field('securityToken', $_SESSION['securityToken'] ?? ''); ?>
                    <?php echo zen_draw_hidden_field('customers_id', (string)$customers_id); ?>

                    <table class="table table-bordered paypalac-rebill-cards">
                        <thead>
                        <tr>
                            <th></th>
                            <th><?php echo TABLE_HEADING_CARD; ?></th>
                            <th><?php echo TABLE_HEADING_EXPIRY; ?></th>
                            <th><?php echo TABLE_HEADING_VAULT; ?></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($cards as $index => $card) {
                            $cardId = (int)$card['saved_credit_card_id'];
                            $checked = ($saved_credit_card_id > 0 ? $saved_credit_card_id === $cardId : $index === 0);
                            $label = trim((string)($card['type'] ?? 'Card')) . ' •••• ' . trim((string)($card['last_digits'] ?? '????'));
                            ?>
                            <tr>
                                <td>
                                    <input type="radio" name="saved_credit_card_id" value="<?php echo $cardId; ?>"
                                        <?php echo $checked ? ' checked' : ''; ?> required>
                                </td>
                                <td><?php echo zen_output_string_protected($label); ?></td>
                                <td><?php echo zen_output_string_protected(($card['expiry_month'] ?? '') . '/' . ($card['expiry_year'] ?? '')); ?></td>
                                <td><code><?php echo zen_output_string_protected(substr((string)$card['effective_vault_id'], 0, 24)); ?></code></td>
                            </tr>
                        <?php } ?>
                        </tbody>
                    </table>

                    <div class="form-group">
                        <label for="amount"><?php echo TEXT_AMOUNT; ?></label>
                        <?php echo zen_draw_input_field('amount', '', 'id="amount" class="nmx-form-control" required step="0.01" min="0.01" style="max-width:160px"'); ?>
                    </div>
                    <div class="form-group">
                        <label for="currency"><?php echo TEXT_CURRENCY; ?></label>
                        <?php echo zen_draw_input_field('currency', $default_currency, 'id="currency" class="nmx-form-control" required maxlength="3" style="max-width:100px"'); ?>
                    </div>
                    <div class="form-group">
                        <label for="comments"><?php echo TEXT_COMMENTS; ?></label>
                        <?php echo zen_draw_textarea_field('comments', 'soft', 60, 3, '', 'id="comments" class="nmx-form-control"'); ?>
                    </div>
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="create_order" value="1" checked>
                            <?php echo TEXT_CREATE_ORDER; ?>
                        </label>
                    </div>
                    <div class="form-group">
                        <label for="orders_id"><?php echo TEXT_EXISTING_ORDER; ?></label>
                        <?php echo zen_draw_input_field('orders_id', '', 'id="orders_id" class="nmx-form-control" style="max-width:160px" placeholder="' . htmlspecialchars(TEXT_EXISTING_ORDER_PLACEHOLDER, ENT_QUOTES, 'UTF-8') . '"'); ?>
                    </div>

                    <div class="paypalac-rebill-actions">
                        <button type="submit" class="nmx-btn nmx-btn-success"><?php echo BUTTON_CHARGE; ?></button>
                    </div>
                </form>
            <?php } ?>
        </div>
    <?php } ?>
</div>
<?php require DIR_WS_INCLUDES . 'footer.php'; ?>
</body>
</html>
<?php require DIR_WS_INCLUDES . 'application_bottom.php'; ?>
