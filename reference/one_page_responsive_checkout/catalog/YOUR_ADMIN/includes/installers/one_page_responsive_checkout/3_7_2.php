<?php
$groupId = isset($configuration_group_id) ? (int)$configuration_group_id : 0;
if ($groupId <= 0) {
    $groupLookup = $db->Execute("SELECT configuration_group_id FROM " . TABLE_CONFIGURATION_GROUP . " WHERE configuration_group_title = 'One Page Responsive Checkout' LIMIT 1");
    if (!$groupLookup->EOF) {
        $groupId = (int)$groupLookup->fields['configuration_group_id'];
    }
}

$groupCondition = $groupId > 0 ? ' AND configuration_group_id = ' . (int)$groupId : '';

$tabAssignments = array(
    'General' => array(
        'OPRC_STATUS',
        'OPRC_ONE_PAGE',
        'OPRC_AJAX_CONFIRMATION_STATUS',
    ),
    'Maintenance' => array(
        'OPRC_MAINTENANCE',
        'OPRC_MAINTENANCE_SCHEDULE',
        'OPRC_MAINTENANCE_SCHEDULE_OFFSET',
        'OPRC_MAINTENANCE_SCHEDULE_SUNDAY_START',
        'OPRC_MAINTENANCE_SCHEDULE_SUNDAY_END',
        'OPRC_MAINTENANCE_SCHEDULE_MONDAY_START',
        'OPRC_MAINTENANCE_SCHEDULE_MONDAY_END',
        'OPRC_MAINTENANCE_SCHEDULE_TUESDAY_START',
        'OPRC_MAINTENANCE_SCHEDULE_TUESDAY_END',
        'OPRC_MAINTENANCE_SCHEDULE_WEDNESDAY_START',
        'OPRC_MAINTENANCE_SCHEDULE_WEDNESDAY_END',
        'OPRC_MAINTENANCE_SCHEDULE_THURSDAY_START',
        'OPRC_MAINTENANCE_SCHEDULE_THURSDAY_END',
        'OPRC_MAINTENANCE_SCHEDULE_FRIDAY_START',
        'OPRC_MAINTENANCE_SCHEDULE_FRIDAY_END',
        'OPRC_MAINTENANCE_SCHEDULE_SATURDAY_START',
        'OPRC_MAINTENANCE_SCHEDULE_SATURDAY_END',
    ),
    'Layout' => array(
        'OPRC_PROCESSING_TEXT',
        'OPRC_BLOCK_TEXT',
        'OPRC_NOT_REQUIRED_BLOCK_TEXT',
        'OPRC_REGISTER_BLOCK_TEXT',
        'OPRC_MESSAGE_BACKGROUND_COLOR',
        'OPRC_MESSAGE_TEXT_COLOR',
        'OPRC_MESSAGE_OPACITY',
        'OPRC_MESSAGE_OVERLAY_COLOR',
        'OPRC_MESSAGE_OVERLAY_TEXT_COLOR',
        'OPRC_MESSAGE_OVERLAY_OPACITY',
        'OPRC_COPYBILLING_BACKGROUND_COLOR',
        'OPRC_COPYBILLING_TEXT_COLOR',
        'OPRC_COPYBILLING_OPACITY',
        'OPRC_PAYPAL_EXPRESS_STATUS',
        'OPRC_GOOGLECHECKOUT_STATUS',
        'OPRC_HIDE_REGISTRATION',
        'OPRC_STACKED',
        'OPRC_CHECKOUT_SHOPPING_CART_DISPLAY_DEFAULT',
        'OPRC_CSS_BUTTONS',
        'OPRC_SHOW_PRODUCT_IMAGES',
        'OPRC_CONTACT_POSITION',
        'OPRC_ORDER_TOTAL_POSITION',
        'OPRC_ORDER_STEPS',
        'OPRC_CREDIT_POSITION',
        'OPRC_CONFIDENCE',
        'OPRC_CONFIDENCE_HTML',
        'OPRC_CONFIRM_EMAIL',
        'OPRC_SHIPPING_ADDRESS',
        'OPRC_SIMPLIFIED_HEADER_ENABLED',
    ),
    'Features' => array(
        'OPRC_WELCOME_MESSAGE',
        'OPRC_HIDE_WELCOME',
        'OPRC_REMOVE_CHECKOUT',
        'OPRC_DROP_DOWN',
        'OPRC_DROP_DOWN_LIST',
        'OPRC_CHECKBOX',
        'OPRC_CHANGE_ADDRESS_POPUP_WIDTH',
        'OPRC_GIFT_WRAPPING_SWITCH',
        'OPRC_GIFT_MESSAGE',
        'OPRC_NOACCOUNT_SWITCH',
        'OPRC_NOACCOUNT_ONLY_SWITCH',
        'OPRC_NOACCOUNT_DEFAULT',
        'OPRC_COWOA_FIELD_TYPE',
        'OPRC_NOACCOUNT_COMBINE',
        'OPRC_NOACCOUNT_VIRTUAL',
        'OPRC_NOACCOUNT_HIDEEMAIL',
        'OPRC_NOACCOUNT_ALWAYS',
        'OPRC_NOACCOUNT_DISABLE_GV',
        'OPRC_MASTER_PASSWORD',
        'OPRC_RECAPTCHA_STATUS',
        'OPRC_RECAPTCHA_THEME',
        'OPRC_RECAPTCHA_KEY',
        'OPRC_RECAPTCHA_SECRET',
        'OPRC_FORGOTTEN_PASSWORD_POPUP_WIDTH',
        'OPRC_HIDEEMAIL_ALL',
        'OPRC_DEFAULT_BILLING_FOR_SHIPPING',
        'OPRC_AJAX_SHIPPING_QUOTES',
        'OPRC_FORCE_SHIPPING_ADDRESS_TO_BILLING',
        'OPRC_SHOW_SHIPPING_METHOD_GROUP',
        'OPRC_DISPLAY_PAYPAL_BUTTON_ON_CHECKOUT',
        'OPRC_FORCE_GUEST_ACCOUNT_SUBSCRIPTION',
        'SHOW_SHOPPING_CART_COMBINED',
    ),
    'Advanced' => array(
        'OPRC_ADDRESS_LOOKUP_PROVIDER',
        'OPRC_ADDRESS_LOOKUP_API_KEY',
        'OPRC_ADDRESS_LOOKUP_MAX_RESULTS',
        'OPRC_REMOVE_CHECKOUT_REFRESH_SELECTORS',
        'OPRC_REMOVE_CHECKOUT_REMOVE_CALLBACK',
        'OPRC_AJAX_ERRORS',
        'OPRC_CHECKOUT_SUBMIT_CALLBACK',
        'OPRC_CHECKOUT_LOGIN_REGISTRATION_REFRESH_SELECTORS',
        'OPRC_CHANGE_ADDRESS_CALLBACK',
        'OPRC_REFRESH_PAYMENT',
        'OPRC_COLLAPSE_DISCOUNTS',
        'OPRC_SHIPPING_INFO',
        'OPRC_EASY_SIGNUP_AUTOMATIC_LOGIN',
        'OPRC_GA_ENABLED',
        'OPRC_GA_METHOD',
        'OPRC_EXPAND_GC',
    ),
);

foreach ($tabAssignments as $tabName => $configurationKeys) {
    if (empty($configurationKeys)) {
        continue;
    }

    $quotedKeys = array();
    foreach ($configurationKeys as $configurationKey) {
        $quotedKeys[] = "'" . zen_db_input($configurationKey) . "'";
    }

    $db->Execute(
        "UPDATE " . TABLE_CONFIGURATION .
        " SET configuration_tab = '" . zen_db_input($tabName) . "'" .
        " WHERE configuration_key IN (" . implode(', ', $quotedKeys) . ")" .
        $groupCondition .
        " AND (configuration_tab = '' OR configuration_tab IS NULL OR LOWER(configuration_tab) = 'both')"
    );
}

if ($groupId > 0) {
    $db->Execute(
        "UPDATE " . TABLE_CONFIGURATION .
        " SET configuration_tab = '" . zen_db_input('General') . "'" .
        " WHERE configuration_group_id = " . (int)$groupId .
        " AND (configuration_tab = '' OR configuration_tab IS NULL OR LOWER(configuration_tab) = 'both')"
    );
}
