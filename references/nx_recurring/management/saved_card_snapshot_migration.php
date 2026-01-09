<?php
/**
 * Saved Card Snapshot Migration tool.
 */

require('includes/application_top.php');
require_once(DIR_FS_CATALOG . DIR_WS_CLASSES . 'paypalSavedCardRecurring.php');

if (!defined('FILENAME_SAVED_CARD_SNAPSHOT_MIGRATION')) {
    define('FILENAME_SAVED_CARD_SNAPSHOT_MIGRATION', basename(__FILE__));
}

if (!defined('TABLE_ADMIN_PAGES')) {
    define('TABLE_ADMIN_PAGES', (defined('DB_PREFIX') ? DB_PREFIX : '') . 'admin_pages');
}

$progressConfigurationKey = 'SAVED_CC_RECURRING_SNAPSHOT_PROGRESS';
$logPath = defined('DIR_FS_LOGS')
    ? rtrim(DIR_FS_LOGS, '/\\') . '/saved_credit_cards_recurring_migration.log'
    : DIR_FS_CATALOG . 'includes/modules/pages/my_subscriptions/saved_credit_cards_recurring_migration.log';

$paypalSavedCardRecurring = new paypalSavedCardRecurring();

function sccr_escape_value($value)
{
    if (function_exists('zen_db_input')) {
        return zen_db_input($value);
    }
    return addslashes($value);
}

function sccr_admin_output($value)
{
    if (function_exists('zen_output_string_protected')) {
        return zen_output_string_protected($value);
    }

    return htmlspecialchars((string) $value, ENT_COMPAT, 'UTF-8');
}

function sccr_get_configuration_group_id()
{
    global $db;

    $groupLookup = $db->Execute(
        "SELECT configuration_group_id FROM " . TABLE_CONFIGURATION . " WHERE configuration_key = 'SAVED_CREDIT_CARDS_RECURRING_VERSION' LIMIT 1"
    );

    if ($groupLookup && !$groupLookup->EOF) {
        return (int) $groupLookup->fields['configuration_group_id'];
    }

    $fallback = $db->Execute(
        "SELECT configuration_group_id FROM " . TABLE_CONFIGURATION . " WHERE configuration_key = 'SAVED_CREDIT_CARDS_RECURRING_ENABLED' LIMIT 1"
    );

    if ($fallback && !$fallback->EOF) {
        return (int) $fallback->fields['configuration_group_id'];
    }

    return 0;
}

function sccr_get_progress()
{
    global $db, $progressConfigurationKey;

    $row = $db->Execute(
        'SELECT configuration_value FROM ' . TABLE_CONFIGURATION . " WHERE configuration_key = '" . $progressConfigurationKey . "' LIMIT 1;"
    );

    if ($row && $row->RecordCount() > 0 && isset($row->fields['configuration_value'])) {
        return (int) $row->fields['configuration_value'];
    }

    return 0;
}

function sccr_set_progress($value)
{
    global $db, $progressConfigurationKey;

    $groupId = sccr_get_configuration_group_id();
    $value = (int) $value;

    $db->Execute(
        'INSERT INTO ' . TABLE_CONFIGURATION
        . ' (configuration_group_id, configuration_key, configuration_title, configuration_value, configuration_description, sort_order, date_added)'
        . " VALUES ("
        . (int) $groupId
        . ", '" . $progressConfigurationKey . "', 'Saved Card Snapshot Migration Progress', '"
        . $value
        . "', 'Tracks progress while migrating saved credit card subscription snapshots.', 0, now())"
        . ' ON DUPLICATE KEY UPDATE configuration_value = VALUES(configuration_value), last_modified = now();'
    );
}

function sccr_clear_progress()
{
    global $db, $progressConfigurationKey;

    $db->Execute(
        'DELETE FROM ' . TABLE_CONFIGURATION
        . " WHERE configuration_key = '" . $progressConfigurationKey . "'"
        . ' LIMIT 1;'
    );
}

function sccr_log_issue($message)
{
    global $logPath;

    $directory = dirname($logPath);
    if (!is_dir($directory)) {
        @mkdir($directory, 0777, true);
    }

    $timestamp = date('Y-m-d H:i:s');
    error_log('[' . $timestamp . '] ' . $message . PHP_EOL, 3, $logPath);
}

function sccr_merge_attributes(array $existing, array $snapshot)
{
    foreach ($snapshot as $key => $value) {
        if (!array_key_exists($key, $existing) || $existing[$key] === '' || $existing[$key] === null) {
            $existing[$key] = $value;
        }
    }

    return $existing;
}

function sccr_load_existing_attributes(array $subscription)
{
    if (!empty($subscription['subscription_attributes_json'])) {
        $decoded = json_decode($subscription['subscription_attributes_json'], true);
        if (is_array($decoded)) {
            return $decoded;
        }
    }

    return array();
}

function sccr_migration_stats()
{
    global $db;

    $totalResult = $db->Execute('SELECT COUNT(*) AS total FROM ' . TABLE_SAVED_CREDIT_CARDS_RECURRING);
    $total = ($totalResult && !$totalResult->EOF) ? (int) $totalResult->fields['total'] : 0;

    $pendingResult = $db->Execute(
        'SELECT COUNT(*) AS pending'
        . ' FROM ' . TABLE_SAVED_CREDIT_CARDS_RECURRING
        . " WHERE (subscription_attributes_json IS NULL OR subscription_attributes_json = '')"
        . " OR products_id IS NULL"
        . " OR products_name IS NULL OR products_name = ''"
        . " OR currency_code IS NULL OR currency_code = ''"
        . " OR billing_period IS NULL OR billing_period = ''"
        . ' OR billing_frequency IS NULL'
        . ' OR total_billing_cycles IS NULL'
    );

    $pending = ($pendingResult && !$pendingResult->EOF) ? (int) $pendingResult->fields['pending'] : 0;

    return array(
        'total' => $total,
        'pending' => $pending,
        'completed' => max(0, $total - $pending),
    );
}

function sccr_process_batch($limit = 25)
{
    global $db, $paypalSavedCardRecurring;

    $limit = (int) $limit;
    if ($limit <= 0) {
        $limit = 25;
    }

    $lastProcessedId = sccr_get_progress();

    $query = $db->Execute(
        'SELECT saved_credit_card_recurring_id, original_orders_products_id, products_id, products_name, products_model, currency_code, billing_period, billing_frequency, total_billing_cycles, domain, subscription_attributes_json'
        . ' FROM ' . TABLE_SAVED_CREDIT_CARDS_RECURRING
        . ' WHERE saved_credit_card_recurring_id > ' . (int) $lastProcessedId
        . ' ORDER BY saved_credit_card_recurring_id ASC'
        . ' LIMIT ' . $limit . ';'
    );

    $processed = 0;
    $issues = array();
    $lastId = $lastProcessedId;

    if ($query->RecordCount() === 0) {
        sccr_clear_progress();
        $stats = sccr_migration_stats();

        return array(
            'processed' => 0,
            'complete' => true,
            'stats' => $stats,
            'issues' => $issues,
            'lastId' => 0,
            'message' => $stats['pending'] === 0
                ? 'All subscriptions have been migrated.'
                : 'Migration finished, but some subscriptions still lack source data. Check the migration log for details.',
        );
    }

    while (!$query->EOF) {
        $subscription = $query->fields;
        $id = (int) $subscription['saved_credit_card_recurring_id'];
        $lastId = $id;
        $processed++;

        $originalOrdersProductsId = isset($subscription['original_orders_products_id'])
            ? (int) $subscription['original_orders_products_id']
            : 0;

        $updates = array();
        $existingAttributes = sccr_load_existing_attributes($subscription);
        $attributeSnapshot = array();

        if ($originalOrdersProductsId > 0) {
            $orderQuery = $db->Execute(
                'SELECT op.products_id, op.products_name, op.products_model, o.currency'
                . ' FROM ' . TABLE_ORDERS_PRODUCTS . ' op'
                . ' LEFT JOIN ' . TABLE_ORDERS . ' o ON o.orders_id = op.orders_id'
                . ' WHERE op.orders_products_id = ' . $originalOrdersProductsId
                . ' LIMIT 1;'
            );

            if ($orderQuery->RecordCount() > 0) {
                if (empty($subscription['products_id']) && isset($orderQuery->fields['products_id'])) {
                    $updates['products_id'] = (int) $orderQuery->fields['products_id'];
                }
                if ((empty($subscription['products_name']) || $subscription['products_name'] === '') && isset($orderQuery->fields['products_name'])) {
                    $updates['products_name'] = $orderQuery->fields['products_name'];
                }
                if ((empty($subscription['products_model']) || $subscription['products_model'] === '') && isset($orderQuery->fields['products_model'])) {
                    $updates['products_model'] = $orderQuery->fields['products_model'];
                }
                if ((empty($subscription['currency_code']) || $subscription['currency_code'] === '') && isset($orderQuery->fields['currency']) && $orderQuery->fields['currency'] !== '') {
                    $updates['currency_code'] = $orderQuery->fields['currency'];
                }
            } else {
                $issues[] = 'Missing orders_products record for subscription #' . $id . ' (orders_products_id ' . $originalOrdersProductsId . ')';
                sccr_log_issue('Missing orders_products record for subscription #' . $id . ' (orders_products_id ' . $originalOrdersProductsId . ')');
            }

            $attributeSnapshot = $paypalSavedCardRecurring->get_attributes($originalOrdersProductsId);
            if (!is_array($attributeSnapshot)) {
                $attributeSnapshot = array();
            }
        } else {
            $issues[] = 'Subscription #' . $id . ' is missing an original_orders_products_id value.';
            sccr_log_issue('Subscription #' . $id . ' is missing an original_orders_products_id value.');
        }

        $mergedAttributes = sccr_merge_attributes($existingAttributes, $attributeSnapshot);

        if (!isset($mergedAttributes['billingperiod']) && isset($subscription['billing_period']) && $subscription['billing_period'] !== '') {
            $mergedAttributes['billingperiod'] = $subscription['billing_period'];
        }
        if (!isset($mergedAttributes['billingfrequency']) && isset($subscription['billing_frequency']) && $subscription['billing_frequency'] !== null) {
            $mergedAttributes['billingfrequency'] = (int) $subscription['billing_frequency'];
        }
        if (!isset($mergedAttributes['totalbillingcycles']) && isset($subscription['total_billing_cycles']) && $subscription['total_billing_cycles'] !== null) {
            $mergedAttributes['totalbillingcycles'] = (int) $subscription['total_billing_cycles'];
        }
        if (!isset($mergedAttributes['domain']) && isset($subscription['domain']) && $subscription['domain'] !== '') {
            $mergedAttributes['domain'] = $subscription['domain'];
        }
        if (!isset($mergedAttributes['currencycode']) && isset($subscription['currency_code']) && $subscription['currency_code'] !== '') {
            $mergedAttributes['currencycode'] = $subscription['currency_code'];
        }

        if (!empty($mergedAttributes)) {
            $encoded = json_encode($mergedAttributes);
            if ($encoded !== false && $encoded !== $subscription['subscription_attributes_json']) {
                $updates['subscription_attributes_json'] = $encoded;
            }
        }

        if (isset($mergedAttributes['billingperiod']) && (empty($subscription['billing_period']) || $subscription['billing_period'] === '')) {
            $updates['billing_period'] = $mergedAttributes['billingperiod'];
        }
        if (isset($mergedAttributes['billingfrequency']) && $subscription['billing_frequency'] === null) {
            $updates['billing_frequency'] = (int) $mergedAttributes['billingfrequency'];
        }
        if (isset($mergedAttributes['totalbillingcycles']) && $subscription['total_billing_cycles'] === null) {
            $updates['total_billing_cycles'] = (int) $mergedAttributes['totalbillingcycles'];
        }
        if (isset($mergedAttributes['domain']) && (empty($subscription['domain']) || $subscription['domain'] === '')) {
            $updates['domain'] = $mergedAttributes['domain'];
        }
        if (isset($mergedAttributes['currencycode']) && (empty($subscription['currency_code']) || $subscription['currency_code'] === '')) {
            $updates['currency_code'] = $mergedAttributes['currencycode'];
        }

        if (!empty($updates)) {
            $setClauses = array();
            foreach ($updates as $column => $value) {
                if (is_int($value)) {
                    $setClauses[] = $column . ' = ' . $value;
                } else {
                    $setClauses[] = $column . " = '" . sccr_escape_value($value) . "'";
                }
            }

            if (!empty($setClauses)) {
                $db->Execute(
                    'UPDATE ' . TABLE_SAVED_CREDIT_CARDS_RECURRING
                    . ' SET ' . implode(', ', $setClauses)
                    . ' WHERE saved_credit_card_recurring_id = ' . $id
                    . ' LIMIT 1;'
                );
            }
        }

        sccr_set_progress($id);
        $query->MoveNext();
    }

    $stats = sccr_migration_stats();

    return array(
        'processed' => $processed,
        'complete' => false,
        'stats' => $stats,
        'issues' => $issues,
        'lastId' => $lastId,
        'message' => $processed . ' subscriptions processed in this batch.',
    );
}

$action = isset($_GET['action']) ? $_GET['action'] : '';
$limit = isset($_POST['limit']) ? (int) $_POST['limit'] : 25;

if ($action === 'process') {
    header('Content-Type: application/json');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');

    $result = sccr_process_batch($limit);

    echo json_encode($result);
    require(DIR_WS_INCLUDES . 'application_bottom.php');
    exit;
}

if ($action === 'remove_tool') {
    $response = array('removed' => false);

    if (function_exists('zen_page_key_exists') && function_exists('zen_deregister_admin_pages')) {
        // Older Zen Cart versions may not have zen_deregister_admin_pages; fall back to manual removal below.
    }

    $db->Execute(
        "DELETE FROM " . TABLE_ADMIN_PAGES . " WHERE page_key = 'toolsSccrSnapshotMigration' LIMIT 1;"
    );

    sccr_clear_progress();

    $response['removed'] = true;
    $response['message'] = 'The migration tool has been removed from the admin menu. You may close this page.';

    header('Content-Type: application/json');
    echo json_encode($response);
    require(DIR_WS_INCLUDES . 'application_bottom.php');
    exit;
}

$stats = sccr_migration_stats();
$lastProcessedId = sccr_get_progress();
$issuesPreview = array();

if (file_exists($logPath)) {
    $recent = @file($logPath);
    if (is_array($recent)) {
        $issuesPreview = array_slice(array_reverse(array_filter(array_map('trim', $recent))), 0, 5);
    }
}

?>
<!doctype html>
<html <?php echo HTML_PARAMS; ?>>
<head>
    <?php require DIR_WS_INCLUDES . 'admin_html_head.php'; ?>
    <link rel="stylesheet" type="text/css" href="includes/css/numinix_admin.css" />
    <link rel="stylesheet" type="text/css" href="includes/css/saved_card_snapshot_migration.css" />
</head>
<body>
    <?php require DIR_WS_INCLUDES . 'header.php'; ?>
    <div class="nmx-module">
        <div class="nmx-container">
            <div class="nmx-container-header">
                <h1>Saved Card Snapshot Migration</h1>
                <p class="nmx-container-subtitle">Migrate saved card subscriptions to the latest snapshot format.</p>
            </div>
            <div class="nmx-row">
                <div class="nmx-col-xs-12">
                    <div class="nmx-panel">
                        <div class="nmx-panel-heading">
                            <div class="nmx-panel-title">Migration Progress</div>
                        </div>
                        <div class="nmx-panel-body sccr-migration" id="sccr-migration-root">
                            <p class="sccr-intro">This tool migrates existing saved card subscriptions to the new snapshot format. It may be run multiple times and will resume where it left off if interrupted.</p>
                            <ul class="sccr-stats">
                                <li><span class="label">Total subscriptions</span><span class="value" id="sccr-total-count"><?php echo (int) $stats['total']; ?></span></li>
                                <li><span class="label">Completed</span><span class="value" id="sccr-completed-count"><?php echo (int) $stats['completed']; ?></span></li>
                                <li><span class="label">Pending migration</span><span class="value" id="sccr-pending-count"><?php echo (int) $stats['pending']; ?></span></li>
                                <li><span class="label">Last processed ID</span><span class="value" id="sccr-last-id"><?php echo (int) $lastProcessedId; ?></span></li>
                            </ul>
                            <div id="sccr-migration-messages" class="sccr-messages"></div>
                            <div class="buttonRow nmx-btn-container" id="sccr-action-buttons">
                                <button type="button" id="sccr-start" class="button nmx-btn nmx-btn-primary">
                                    <?php echo $lastProcessedId > 0 ? 'Continue Migration' : 'Start Migration'; ?>
                                </button>
                                <button type="button" id="sccr-remove" class="button nmx-btn nmx-btn-default" style="display: none;">Remove Migration Tool</button>
                            </div>
                            <div id="sccr-issues" class="sccr-issues"<?php echo empty($issuesPreview) ? ' style="display:none;"' : ''; ?>>
                                <h3>Recent migration warnings</h3>
                                <ul>
                                    <?php foreach ($issuesPreview as $issue): ?>
                                        <li><?php echo sccr_admin_output($issue); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                                <p>The full log is stored at <code><?php echo sccr_admin_output($logPath); ?></code>.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php require DIR_WS_INCLUDES . 'footer.php'; ?>
    <script>
        window.nmxSavedCardSnapshotConfig = {
            processUrl: '<?php echo str_replace('&amp;', '&', zen_href_link(FILENAME_SAVED_CARD_SNAPSHOT_MIGRATION, 'action=process')); ?>',
            removeUrl: '<?php echo str_replace('&amp;', '&', zen_href_link(FILENAME_SAVED_CARD_SNAPSHOT_MIGRATION, 'action=remove_tool')); ?>',
            securityToken: <?php echo isset($_SESSION['securityToken']) ? json_encode($_SESSION['securityToken']) : 'null'; ?>
        };
    </script>
    <script src="includes/javascript/saved_card_snapshot_migration.js"></script>
</body>
</html>
<?php
require(DIR_WS_INCLUDES . 'application_bottom.php');

