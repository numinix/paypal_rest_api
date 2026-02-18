<?php
/**
 * PayPal Webhook Logs Report
 *
 * Displays all incoming webhook requests with pagination,
 * search, and clear functionality.
 *
 * @copyright Copyright 2003-2025 Zen Cart Development Team
 * @license http://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 * @version $Id: v1.3.10 $
 */

require 'includes/application_top.php';

// Load PayPal REST API autoloader
require_once DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/paypal/pprAutoload.php';

if (!defined('HEADING_TITLE')) {
    define('HEADING_TITLE', 'PayPal Webhook Logs');
}

// Language constants with defaults
$langDefaults = [
    'TEXT_PANEL_SEARCH' => 'Search Webhook Logs',
    'TEXT_FILTER_WEBHOOK_ID' => 'Webhook ID',
    'TEXT_FILTER_EVENT_TYPE' => 'Event Type',
    'TEXT_FILTER_EVENT_TYPE_ALL' => '-- All Event Types --',
    'TEXT_FILTER_BODY' => 'Body Content',
    'TEXT_SEARCH_HELP_WEBHOOK_ID' => 'Enter webhook ID...',
    'TEXT_SEARCH_HELP_BODY' => 'Search body content...',
    'TEXT_BUTTON_SEARCH' => 'Search',
    'TEXT_BUTTON_RESET' => 'Reset',
    'TEXT_BUTTON_CLEAR_LOGS' => 'Clear All Logs',
    'TEXT_CONFIRM_CLEAR' => 'Are you sure you want to delete ALL webhook logs? This action cannot be undone.',
    'TEXT_LOGS_CLEARED' => 'All webhook logs have been cleared.',
    'TEXT_NO_LOGS' => 'No webhook logs found.',
    'TEXT_PANEL_RESULTS' => 'Webhook Logs',
    'TABLE_HEADING_ID' => 'ID',
    'TABLE_HEADING_WEBHOOK_ID' => 'Webhook ID',
    'TABLE_HEADING_EVENT_TYPE' => 'Event Type',
    'TABLE_HEADING_REQUEST_METHOD' => 'Method',
    'TABLE_HEADING_STATUS' => 'Status',
    'TABLE_HEADING_CREATED_AT' => 'Date/Time',
    'TABLE_HEADING_ACTION' => 'Action',
    'TEXT_VIEW_DETAILS' => 'View',
    'TEXT_PANEL_DETAIL' => 'Webhook Detail',
    'TEXT_LABEL_WEBHOOK_ID' => 'Webhook ID',
    'TEXT_LABEL_EVENT_TYPE' => 'Event Type',
    'TEXT_LABEL_REQUEST_METHOD' => 'Request Method',
    'TEXT_LABEL_USER_AGENT' => 'User Agent',
    'TEXT_LABEL_VERIFICATION_STATUS' => 'Verification Status',
    'TEXT_LABEL_CREATED_AT' => 'Created At',
    'TEXT_LABEL_REQUEST_HEADERS' => 'Request Headers',
    'TEXT_LABEL_BODY' => 'Body',
    'TEXT_BUTTON_BACK' => '&laquo; Back to Logs',
    'TEXT_DISPLAYING' => 'Displaying %d to %d of %d entries',
];

foreach ($langDefaults as $key => $value) {
    if (!defined($key)) {
        define($key, $value);
    }
}

defined('TABLE_PAYPAL_WEBHOOKS') or define('TABLE_PAYPAL_WEBHOOKS', DB_PREFIX . 'paypal_webhooks');

// ---- Handle actions ----
$action = isset($_GET['action']) ? trim((string)$_GET['action']) : '';

// Clear logs
if ($action === 'clear' && isset($_POST['confirm_clear']) && $_POST['confirm_clear'] === 'yes') {
    $sessionToken = $_SESSION['securityToken'] ?? '';
    $requestToken = $_POST['securityToken'] ?? '';
    if ($sessionToken !== '' && $requestToken === $sessionToken) {
        $db->Execute("DELETE FROM " . TABLE_PAYPAL_WEBHOOKS);
        $messageStack->add_session(TEXT_LOGS_CLEARED, 'success');
    }
    zen_redirect(zen_href_link(FILENAME_PAYPALR_WEBHOOK_LOGS));
}

// Detail view
$detail_id = isset($_GET['detail']) ? (int)$_GET['detail'] : 0;

if ($detail_id > 0) {
    $detailResult = $db->Execute(
        "SELECT id, webhook_id, event_type, body, created_at, user_agent, request_method, request_headers, verification_status
           FROM " . TABLE_PAYPAL_WEBHOOKS . " WHERE id = " . (int)$detail_id . " LIMIT 1"
    );
    $detailRecord = $detailResult->EOF ? null : $detailResult->fields;
}

// ---- Search & Pagination ----
$webhookId = isset($_GET['webhook_id']) ? trim((string)$_GET['webhook_id']) : '';
$eventType = isset($_GET['event_type']) ? trim((string)$_GET['event_type']) : '';
$searchTerm = isset($_GET['search']) ? trim((string)$_GET['search']) : '';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Build event type dropdown options from existing data
$eventTypesResult = $db->Execute(
    "SELECT DISTINCT event_type FROM " . TABLE_PAYPAL_WEBHOOKS . " ORDER BY event_type"
);
$eventTypes = [];
while (!$eventTypesResult->EOF) {
    $eventTypes[] = $eventTypesResult->fields['event_type'];
    $eventTypesResult->MoveNext();
}

$whereParts = [];
if ($webhookId !== '') {
    $whereParts[] = "webhook_id LIKE '%" . zen_db_input($webhookId) . "%'";
}
if ($eventType !== '') {
    $whereParts[] = "event_type = '" . zen_db_input($eventType) . "'";
}
if ($searchTerm !== '') {
    $whereParts[] = "body LIKE '%" . zen_db_input($searchTerm) . "%'";
}
$whereClause = !empty($whereParts) ? ' WHERE ' . implode(' AND ', $whereParts) : '';

// Get total count
$countResult = $db->Execute("SELECT COUNT(*) AS total FROM " . TABLE_PAYPAL_WEBHOOKS . $whereClause);
$totalRecords = (int)$countResult->fields['total'];
$totalPages = ($totalRecords > 0) ? (int)ceil($totalRecords / $perPage) : 1;
if ($page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $perPage;
}

// Fetch logs
$logsResult = $db->Execute(
    "SELECT id, webhook_id, event_type, request_method, verification_status, created_at
       FROM " . TABLE_PAYPAL_WEBHOOKS . $whereClause . "
      ORDER BY created_at DESC, id DESC
      LIMIT " . (int)$perPage . " OFFSET " . (int)$offset
);

$logs = [];
while (!$logsResult->EOF) {
    $logs[] = $logsResult->fields;
    $logsResult->MoveNext();
}

$displayStart = $totalRecords > 0 ? $offset + 1 : 0;
$displayEnd = min($offset + $perPage, $totalRecords);

/**
 * Build pagination URL preserving search params
 */
function whl_page_url(int $pageNum, string $webhookId = '', string $eventType = '', string $search = ''): string
{
    $params = 'page=' . $pageNum;
    if ($webhookId !== '') {
        $params .= '&webhook_id=' . urlencode($webhookId);
    }
    if ($eventType !== '') {
        $params .= '&event_type=' . urlencode($eventType);
    }
    if ($search !== '') {
        $params .= '&search=' . urlencode($search);
    }
    return zen_href_link(FILENAME_PAYPALR_WEBHOOK_LOGS, $params);
}

/**
 * Pretty-print JSON string for display
 */
function whl_pretty_json(string $raw): string
{
    $decoded = json_decode($raw, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        return json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
    return $raw;
}
?>
<!doctype html>
<html <?php echo HTML_PARAMS; ?>>
<head>
    <?php require DIR_WS_INCLUDES . 'admin_html_head.php'; ?>
    <link rel="stylesheet" href="../includes/modules/payment/paypal/PayPalRestful/numinix_admin.css">
    <title><?php echo HEADING_TITLE; ?></title>
    <style>
        .whl-pre {
            background: var(--nmx-muted, #f2f5f7);
            border: 1px solid var(--nmx-border, #d6e0e8);
            border-radius: 8px;
            padding: 16px;
            font-size: 13px;
            line-height: 1.5;
            overflow-x: auto;
            white-space: pre-wrap;
            word-break: break-word;
            max-height: 500px;
            overflow-y: auto;
        }
        .whl-detail-label {
            font-weight: 700;
            font-size: 13px;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--nmx-dark, #171717);
            margin-bottom: 6px;
        }
        .whl-detail-value {
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
<?php require DIR_WS_INCLUDES . 'header.php'; ?>
<div class="nmx-module">
    <div class="nmx-container">
        <div class="nmx-container-header">
            <h1><?php echo HEADING_TITLE; ?></h1>
        </div>

        <div class="nmx-message-stack">
            <?php
            if (isset($messageStack) && is_object($messageStack)) {
                echo $messageStack->output('header');
            }
            ?>
        </div>

<?php if ($detail_id > 0 && $detailRecord !== null) { ?>
        <!-- Detail View -->
        <div class="nmx-panel">
            <div class="nmx-panel-heading">
                <div class="nmx-panel-title"><?php echo TEXT_PANEL_DETAIL; ?></div>
            </div>
            <div class="nmx-panel-body">
                <div class="whl-detail-label"><?php echo TEXT_LABEL_WEBHOOK_ID; ?></div>
                <div class="whl-detail-value"><?php echo zen_output_string_protected($detailRecord['webhook_id']); ?></div>

                <div class="whl-detail-label"><?php echo TEXT_LABEL_EVENT_TYPE; ?></div>
                <div class="whl-detail-value"><?php echo zen_output_string_protected($detailRecord['event_type']); ?></div>

                <div class="whl-detail-label"><?php echo TEXT_LABEL_REQUEST_METHOD; ?></div>
                <div class="whl-detail-value"><?php echo zen_output_string_protected($detailRecord['request_method']); ?></div>

                <div class="whl-detail-label"><?php echo TEXT_LABEL_USER_AGENT; ?></div>
                <div class="whl-detail-value"><?php echo zen_output_string_protected($detailRecord['user_agent']); ?></div>

                <div class="whl-detail-label"><?php echo TEXT_LABEL_VERIFICATION_STATUS; ?></div>
                <div class="whl-detail-value"><?php echo zen_output_string_protected($detailRecord['verification_status'] ?? 'verified'); ?></div>

                <div class="whl-detail-label"><?php echo TEXT_LABEL_CREATED_AT; ?></div>
                <div class="whl-detail-value"><?php echo zen_output_string_protected($detailRecord['created_at']); ?></div>

                <div class="whl-detail-label"><?php echo TEXT_LABEL_REQUEST_HEADERS; ?></div>
                <pre class="whl-pre"><?php echo zen_output_string_protected(whl_pretty_json($detailRecord['request_headers'] ?? '')); ?></pre>

                <div class="whl-detail-label" style="margin-top:20px"><?php echo TEXT_LABEL_BODY; ?></div>
                <pre class="whl-pre"><?php echo zen_output_string_protected(whl_pretty_json($detailRecord['body'] ?? '')); ?></pre>

                <div style="margin-top: 24px;">
                    <a href="<?php echo zen_href_link(FILENAME_PAYPALR_WEBHOOK_LOGS, zen_get_all_get_params(['detail', 'action'])); ?>" class="nmx-btn nmx-btn-default"><?php echo TEXT_BUTTON_BACK; ?></a>
                </div>
            </div>
        </div>

<?php } else { ?>
        <!-- Search Panel -->
        <div class="nmx-panel">
            <div class="nmx-panel-heading">
                <div class="nmx-panel-title"><?php echo TEXT_PANEL_SEARCH; ?></div>
            </div>
            <div class="nmx-panel-body">
                <?php echo zen_draw_form('paypalr_webhook_search', FILENAME_PAYPALR_WEBHOOK_LOGS, '', 'get', 'class="nmx-form-inline"'); ?>
                    <div class="nmx-form-group">
                        <label for="webhook-id-filter"><?php echo TEXT_FILTER_WEBHOOK_ID; ?></label>
                        <input type="text" name="webhook_id" id="webhook-id-filter" value="<?php echo zen_output_string_protected($webhookId); ?>" class="nmx-form-control" placeholder="<?php echo zen_output_string_protected(TEXT_SEARCH_HELP_WEBHOOK_ID); ?>">
                    </div>
                    <div class="nmx-form-group">
                        <label for="event-type-filter"><?php echo TEXT_FILTER_EVENT_TYPE; ?></label>
                        <select name="event_type" id="event-type-filter" class="nmx-form-control">
                            <option value=""><?php echo TEXT_FILTER_EVENT_TYPE_ALL; ?></option>
                            <?php foreach ($eventTypes as $et) { ?>
                                <option value="<?php echo zen_output_string_protected($et); ?>"<?php echo ($eventType === $et) ? ' selected' : ''; ?>><?php echo zen_output_string_protected($et); ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="nmx-form-group">
                        <label for="search-filter"><?php echo TEXT_FILTER_BODY; ?></label>
                        <input type="text" name="search" id="search-filter" value="<?php echo zen_output_string_protected($searchTerm); ?>" class="nmx-form-control" placeholder="<?php echo zen_output_string_protected(TEXT_SEARCH_HELP_BODY); ?>">
                    </div>
                    <div class="nmx-form-actions">
                        <button type="submit" class="nmx-btn nmx-btn-primary"><?php echo TEXT_BUTTON_SEARCH; ?></button>
                        <a href="<?php echo zen_href_link(FILENAME_PAYPALR_WEBHOOK_LOGS); ?>" class="nmx-btn nmx-btn-default"><?php echo TEXT_BUTTON_RESET; ?></a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Results Panel -->
        <div class="nmx-panel">
            <div class="nmx-panel-heading">
                <div class="nmx-panel-title"><?php echo TEXT_PANEL_RESULTS; ?></div>
            </div>
            <div class="nmx-panel-body">
                <?php if (empty($logs)) { ?>
                    <p><?php echo TEXT_NO_LOGS; ?></p>
                <?php } else { ?>
                    <div class="nmx-table-responsive">
                        <table class="nmx-table nmx-table-striped">
                            <thead>
                                <tr>
                                    <th><?php echo TABLE_HEADING_ID; ?></th>
                                    <th><?php echo TABLE_HEADING_WEBHOOK_ID; ?></th>
                                    <th><?php echo TABLE_HEADING_EVENT_TYPE; ?></th>
                                    <th><?php echo TABLE_HEADING_REQUEST_METHOD; ?></th>
                                    <th><?php echo TABLE_HEADING_STATUS; ?></th>
                                    <th><?php echo TABLE_HEADING_CREATED_AT; ?></th>
                                    <th><?php echo TABLE_HEADING_ACTION; ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($logs as $log) { ?>
                                    <tr>
                                        <td><?php echo (int)$log['id']; ?></td>
                                        <td><?php echo zen_output_string_protected($log['webhook_id']); ?></td>
                                        <td><?php echo zen_output_string_protected($log['event_type']); ?></td>
                                        <td><?php echo zen_output_string_protected($log['request_method']); ?></td>
                                        <td><?php echo zen_output_string_protected($log['verification_status'] ?? 'verified'); ?></td>
                                        <td><?php echo zen_output_string_protected($log['created_at']); ?></td>
                                        <td>
                                            <a href="<?php echo zen_href_link(FILENAME_PAYPALR_WEBHOOK_LOGS, 'detail=' . (int)$log['id'] . '&' . zen_get_all_get_params(['detail', 'action'])); ?>" class="nmx-btn nmx-btn-sm nmx-btn-info"><?php echo TEXT_VIEW_DETAILS; ?></a>
                                        </td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div class="nmx-pagination">
                        <div><?php echo sprintf(TEXT_DISPLAYING, $displayStart, $displayEnd, $totalRecords); ?></div>
                        <?php if ($totalPages > 1) { ?>
                            <ul class="nmx-list-pagination">
                                <li class="<?php echo ($page <= 1) ? 'nmx-disabled' : ''; ?>">
                                    <a href="<?php echo whl_page_url(max(1, $page - 1), $webhookId, $eventType, $searchTerm); ?>">&laquo;</a>
                                </li>
                                <?php
                                $startPage = max(1, $page - 2);
                                $endPage = min($totalPages, $page + 2);
                                for ($p = $startPage; $p <= $endPage; $p++) { ?>
                                    <li class="<?php echo ($p === $page) ? 'nmx-active' : ''; ?>">
                                        <a href="<?php echo whl_page_url($p, $webhookId, $eventType, $searchTerm); ?>"><?php echo $p; ?></a>
                                    </li>
                                <?php } ?>
                                <li class="<?php echo ($page >= $totalPages) ? 'nmx-disabled' : ''; ?>">
                                    <a href="<?php echo whl_page_url(min($totalPages, $page + 1), $webhookId, $eventType, $searchTerm); ?>">&raquo;</a>
                                </li>
                            </ul>
                        <?php } ?>
                    </div>
                <?php } ?>
            </div>
        </div>

        <!-- Clear Logs Panel -->
        <?php if ($totalRecords > 0) { ?>
            <div class="nmx-panel">
                <div class="nmx-panel-body">
                    <?php echo zen_draw_form('paypalr_webhook_clear', FILENAME_PAYPALR_WEBHOOK_LOGS, 'action=clear', 'post', 'onsubmit="return confirm(\'' . htmlspecialchars(TEXT_CONFIRM_CLEAR, ENT_QUOTES, 'UTF-8') . '\');"'); ?>
                        <?php echo zen_draw_hidden_field('confirm_clear', 'yes'); ?>
                        <button type="submit" class="nmx-btn nmx-btn-danger"><?php echo TEXT_BUTTON_CLEAR_LOGS; ?></button>
                    </form>
                </div>
            </div>
        <?php } ?>

<?php } ?>

        <div class="nmx-footer">
            <a href="https://www.numinix.com" target="_blank" rel="noopener noreferrer" class="nmx-footer-logo">
                <img src="images/numinix_logo.png" alt="Numinix">
            </a>
        </div>
    </div>
</div>
<?php require DIR_WS_INCLUDES . 'footer.php'; ?>
</body>
</html>
<?php require DIR_WS_INCLUDES . 'application_bottom.php';
