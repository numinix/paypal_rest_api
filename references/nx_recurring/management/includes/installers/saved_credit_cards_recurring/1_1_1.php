<?php
/**
 * Register the saved card snapshot migration admin tool.
 */

global $db, $messageStack, $configuration_group_id;

if (!defined('TABLE_ADMIN_PAGES')) {
    define('TABLE_ADMIN_PAGES', (defined('DB_PREFIX') ? DB_PREFIX : '') . 'admin_pages');
}

if (!defined('BOX_TOOLS_SCCR_SNAPSHOT_MIGRATION')) {
    define('BOX_TOOLS_SCCR_SNAPSHOT_MIGRATION', 'Saved Card Snapshot Migration');
}

if (!function_exists('sccr_installer_escape')) {
    function sccr_installer_escape($value)
    {
        if (function_exists('zen_db_input')) {
            return zen_db_input($value);
        }

        return addslashes($value);
    }
}

$pageKey = 'toolsSccrSnapshotMigration';
$pageName = 'BOX_TOOLS_SCCR_SNAPSHOT_MIGRATION';
$pageFile = 'FILENAME_SAVED_CARD_SNAPSHOT_MIGRATION';

$pageExists = false;
if (function_exists('zen_page_key_exists')) {
    $pageExists = zen_page_key_exists($pageKey);
} else {
    $check = $db->Execute(
        "SELECT page_key FROM " . TABLE_ADMIN_PAGES . " WHERE page_key = '" . sccr_installer_escape($pageKey) . "' LIMIT 1;"
    );
    $pageExists = ($check && $check->RecordCount() > 0);
}

if (!$pageExists) {
    if (function_exists('zen_register_admin_page')) {
        zen_register_admin_page(
            $pageKey,
            $pageName,
            $pageFile,
            '',
            'tools',
            'Y',
            (int) $configuration_group_id
        );
    } else {
        $db->Execute(
            "INSERT IGNORE INTO " . TABLE_ADMIN_PAGES
            . " (page_key, language_key, main_page, page_params, menu_key, display_on_menu, sort_order) VALUES ('"
            . sccr_installer_escape($pageKey) . "', '"
            . sccr_installer_escape($pageName) . "', '"
            . sccr_installer_escape($pageFile) . "', '', 'tools', 'Y', "
            . (int) $configuration_group_id . ");"
        );
    }

    if (isset($messageStack) && is_object($messageStack)) {
        $messageStack->add('Saved Card Snapshot Migration tool registered.', 'success');
    }
}
