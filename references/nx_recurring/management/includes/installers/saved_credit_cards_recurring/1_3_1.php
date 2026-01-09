<?php
/**
 * Move the Active Subscriptions report to the Reports menu.
 */

global $db, $messageStack, $configuration_group_id;

if (!defined('TABLE_ADMIN_PAGES')) {
    $tablePrefix = defined('DB_PREFIX') ? DB_PREFIX : '';
    define('TABLE_ADMIN_PAGES', $tablePrefix . 'admin_pages');
}

if (!defined('FILENAME_ACTIVE_SUBSCRIPTIONS_REPORT')) {
    define('FILENAME_ACTIVE_SUBSCRIPTIONS_REPORT', 'active_subscriptions_report');
}

if (!defined('BOX_REPORTS_ACTIVE_SUBSCRIPTIONS_REPORT')) {
    define('BOX_REPORTS_ACTIVE_SUBSCRIPTIONS_REPORT', 'Active Subscriptions Report');
}

if (!defined('BOX_TOOLS_ACTIVE_SUBSCRIPTIONS_REPORT')) {
    define('BOX_TOOLS_ACTIVE_SUBSCRIPTIONS_REPORT', BOX_REPORTS_ACTIVE_SUBSCRIPTIONS_REPORT);
}

if (!function_exists('asr_installer_escape')) {
    function asr_installer_escape($value)
    {
        if (function_exists('zen_db_input')) {
            return zen_db_input($value);
        }

        return addslashes($value);
    }
}

$legacyPageKey = 'toolsActiveSubscriptionsReport';
$newPageKey = 'reportsActiveSubscriptionsReport';
$menuKey = 'reports';

$legacyExists = false;
$newExists = false;

if (function_exists('zen_page_key_exists')) {
    $legacyExists = zen_page_key_exists($legacyPageKey);
    $newExists = zen_page_key_exists($newPageKey);
} else {
    $legacyCheck = $db->Execute(
        "SELECT page_key FROM " . TABLE_ADMIN_PAGES . " WHERE page_key = '" . asr_installer_escape($legacyPageKey) . "' LIMIT 1;"
    );
    $legacyExists = ($legacyCheck && $legacyCheck->RecordCount() > 0);

    $newCheck = $db->Execute(
        "SELECT page_key FROM " . TABLE_ADMIN_PAGES . " WHERE page_key = '" . asr_installer_escape($newPageKey) . "' LIMIT 1;"
    );
    $newExists = ($newCheck && $newCheck->RecordCount() > 0);
}

$changesApplied = false;

if ($legacyExists) {
    $db->Execute(
        "UPDATE " . TABLE_ADMIN_PAGES
        . " SET page_key = '" . asr_installer_escape($newPageKey) . "',"
        . " language_key = 'BOX_REPORTS_ACTIVE_SUBSCRIPTIONS_REPORT',"
        . " main_page = 'FILENAME_ACTIVE_SUBSCRIPTIONS_REPORT',"
        . " page_params = '',"
        . " menu_key = '" . asr_installer_escape($menuKey) . "',"
        . " display_on_menu = 'Y'"
        . " WHERE page_key = '" . asr_installer_escape($legacyPageKey) . "'"
        . " LIMIT 1;"
    );

    $changesApplied = true;
    $newExists = true;
}

if ($newExists) {
    $db->Execute(
        "UPDATE " . TABLE_ADMIN_PAGES
        . " SET language_key = 'BOX_REPORTS_ACTIVE_SUBSCRIPTIONS_REPORT',"
        . " main_page = 'FILENAME_ACTIVE_SUBSCRIPTIONS_REPORT',"
        . " page_params = '',"
        . " menu_key = '" . asr_installer_escape($menuKey) . "',"
        . " display_on_menu = 'Y'"
        . " WHERE page_key = '" . asr_installer_escape($newPageKey) . "'"
        . " LIMIT 1;"
    );

    $changesApplied = true;
} else {
    if (function_exists('zen_register_admin_page')) {
        zen_register_admin_page(
            $newPageKey,
            'BOX_REPORTS_ACTIVE_SUBSCRIPTIONS_REPORT',
            'FILENAME_ACTIVE_SUBSCRIPTIONS_REPORT',
            '',
            $menuKey,
            'Y',
            (int) $configuration_group_id
        );
    } else {
        $db->Execute(
            "INSERT IGNORE INTO " . TABLE_ADMIN_PAGES
            . " (page_key, language_key, main_page, page_params, menu_key, display_on_menu, sort_order) VALUES ('"
            . asr_installer_escape($newPageKey) . "', 'BOX_REPORTS_ACTIVE_SUBSCRIPTIONS_REPORT', 'FILENAME_ACTIVE_SUBSCRIPTIONS_REPORT', '', '"
            . asr_installer_escape($menuKey) . "', 'Y', " . (int) $configuration_group_id . ");"
        );
    }

    $changesApplied = true;
}

if ($legacyExists) {
    $db->Execute(
        "DELETE FROM " . TABLE_ADMIN_PAGES . " WHERE page_key = '" . asr_installer_escape($legacyPageKey) . "' LIMIT 1;"
    );
}

if ($changesApplied && isset($messageStack) && is_object($messageStack)) {
    $messageStack->add('Active Subscriptions Report has been moved to the Reports menu.', 'success');
}
