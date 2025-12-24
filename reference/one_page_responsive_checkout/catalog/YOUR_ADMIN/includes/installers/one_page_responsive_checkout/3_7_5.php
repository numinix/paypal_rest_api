<?php
$keysToRemove = array(
    'OPRC_MESSAGE_BACKGROUND_COLOR',
    'OPRC_MESSAGE_TEXT_COLOR',
    'OPRC_MESSAGE_OPACITY',
    'OPRC_MESSAGE_OVERLAY_COLOR',
    'OPRC_MESSAGE_OVERLAY_TEXT_COLOR',
    'OPRC_MESSAGE_OVERLAY_OPACITY',
);

foreach ($keysToRemove as $configurationKey) {
    $db->Execute(
        "DELETE FROM " . TABLE_CONFIGURATION . " WHERE configuration_key = '" . zen_db_input($configurationKey) . "' LIMIT 1;"
    );
}
