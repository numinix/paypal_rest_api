<?php
global $sniffer;

if (!$sniffer->field_exists(TABLE_ORDERS, 'dropdown')) $db->Execute("ALTER TABLE " . TABLE_ORDERS . " ADD dropdown varchar(50) NULL default NULL;");  
if (!$sniffer->field_exists(TABLE_ORDERS, 'gift_message')) $db->Execute("ALTER TABLE " . TABLE_ORDERS . " ADD gift_message varchar(255) NULL default NULL after dropdown;"); 
if (!$sniffer->field_exists(TABLE_ORDERS, 'checkbox')) $db->Execute("ALTER TABLE " . TABLE_ORDERS . " ADD checkbox integer(1) NULL default NULL after gift_message;");
if (!$sniffer->field_exists(TABLE_ORDERS, 'COWOA_order')) $db->Execute("ALTER TABLE " . TABLE_ORDERS . " ADD COWOA_order tinyint(1) NOT NULL default 0;");
if (!$sniffer->field_exists(TABLE_ORDERS, 'shipping_telephone')) $db->Execute("ALTER TABLE " . TABLE_ORDERS . " ADD shipping_telephone varchar(50) NULL default NULL;"); 

if (!$sniffer->field_exists(TABLE_COUPONS, 'manufacturer_ids')) $db->Execute("ALTER TABLE " . TABLE_COUPONS . " ADD manufacturer_ids varchar(100) NULL default 0;");
if (!$sniffer->field_exists(TABLE_COUPONS, 'sales_eligible')) $db->Execute("ALTER TABLE " . TABLE_COUPONS . " ADD sales_eligible tinyint(1) NULL default 0;");
if (!$sniffer->field_exists(TABLE_COUPONS, 'specials_eligible')) $db->Execute("ALTER TABLE " . TABLE_COUPONS . " ADD specials_eligible tinyint(1) NULL default 0;"); 

if (!$sniffer->field_exists(TABLE_ADDRESS_BOOK, 'entry_telephone')) $db->Execute("ALTER TABLE " . TABLE_ADDRESS_BOOK . " ADD entry_telephone varchar(50) NULL default NULL;");
if (!$sniffer->field_exists(TABLE_ADDRESS_BOOK, 'address_title')) $db->Execute("ALTER TABLE " . TABLE_ADDRESS_BOOK . " ADD address_title varchar(32) NULL default NULL after customers_id;");

if (!$sniffer->field_exists(TABLE_CUSTOMERS, 'COWOA_account')) $db->Execute("ALTER TABLE " . TABLE_CUSTOMERS . " ADD COWOA_account tinyint(1) NOT NULL default 0;"); 
if (!$sniffer->field_exists(TABLE_CUSTOMERS, 'customers_default_shipping_address_id')) $db->Execute("ALTER TABLE " . TABLE_CUSTOMERS . " ADD customers_default_shipping_address_id int(11) NOT NULL default 0;");

if (!$sniffer->field_exists(TABLE_ORDERS, 'customers_browser')) $db->Execute("ALTER TABLE " . TABLE_ORDERS . " ADD customers_browser varchar(100) NULL default NULL;");  
if (!$sniffer->field_exists(TABLE_CUSTOMERS, 'customers_browser')) $db->Execute("ALTER TABLE " . TABLE_CUSTOMERS . " ADD customers_browser varchar(100) NULL default NULL;");  

$db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_tab, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, last_modified, date_added, use_function, set_function) VALUES
            ('One Page Responsive Checkout', '', 'OPRC_STATUS', 'true', 'Activate One Page Responsive Checkout?', " . $configuration_group_id . ", 20, NOW(), NOW(), NULL, 'zen_cfg_select_option(array(\"true\", \"false\"),'),
            ('One Page Checkout', '', 'OPRC_ONE_PAGE', 'true', 'Activate One Page Checkout?<br />Default = false (enables confirmation page)', " . $configuration_group_id . ", 20, NOW(), NOW(), NULL, 'zen_cfg_select_option(array(\"true\", \"false\"),'),
            ('Use AJAX on Checkout Submit', '', 'OPRC_AJAX_CONFIRMATION_STATUS', 'true', 'Should OPRC use Ajax to submit the final confirmation? (Note: if having issues, try setting this to false)', " . $configuration_group_id . ", 20, NOW(), NOW(), NULL, 'zen_cfg_select_option(array(\"true\", \"false\"),'),             
            
            ('Down for Maintenance', 'Maintenance', 'OPRC_MAINTENANCE', 'false', 'Put the checkout down for maintenance?  The website will still be usable.', " . $configuration_group_id . ", 1, NOW(), NOW(), NULL, 'zen_cfg_select_option(array(\"true\", \"false\"),'),
            ('Down for Maintenance Schedule', 'Maintenance', 'OPRC_MAINTENANCE_SCHEDULE', 'false', 'Put the checkout down for maintenance on a regular schedule?  The website will still be usable during this time.', " . $configuration_group_id . ", 2, NOW(), NOW(), NULL, 'zen_cfg_select_option(array(\"true\", \"false\"),'),
            ('Down for Maintenance Schedule Offset', 'Maintenance', 'OPRC_MAINTENANCE_SCHEDULE_OFFSET', '-2', 'Enter a positive or negative number to offset the time of your server to match your store\'s local time', " . $configuration_group_id . ", 3, NOW(), NOW(), NULL, NULL),
            ('Down for Maintenance Schedule Sunday START', 'Maintenance', 'OPRC_MAINTENANCE_SCHEDULE_SUNDAY_START', '00', 'Using a 24 hour clock, set the start time for maintenance.', " . $configuration_group_id . ", 4, NOW(), NOW(), NULL, NULL),
            ('Down for Maintenance Schedule Sunday END', 'Maintenance', 'OPRC_MAINTENANCE_SCHEDULE_SUNDAY_END', '00', 'Using a 24 hour clock, set the end time for maintenance.', " . $configuration_group_id . ", 5, NOW(), NOW(), NULL, NULL),
            ('Down for Maintenance Schedule Monday START', 'Maintenance', 'OPRC_MAINTENANCE_SCHEDULE_MONDAY_START', '00', 'Using a 24 hour clock, set the start time for maintenance.', " . $configuration_group_id . ", 6, NOW(), NOW(), NULL, NULL),
            ('Down for Maintenance Schedule Monday END', 'Maintenance', 'OPRC_MAINTENANCE_SCHEDULE_MONDAY_END', '00', 'Using a 24 hour clock, set the end time for maintenance.', " . $configuration_group_id . ", 7, NOW(), NOW(), NULL, NULL),
            ('Down for Maintenance Schedule Tuesday START', 'Maintenance', 'OPRC_MAINTENANCE_SCHEDULE_TUESDAY_START', '00', 'Using a 24 hour clock, set the start time for maintenance.', " . $configuration_group_id . ", 8, NOW(), NOW(), NULL, NULL),
            ('Down for Maintenance Schedule Tuesday END', 'Maintenance', 'OPRC_MAINTENANCE_SCHEDULE_TUESDAY_END', '00', 'Using a 24 hour clock, set the end time for maintenance.', " . $configuration_group_id . ", 9, NOW(), NOW(), NULL, NULL),
            ('Down for Maintenance Schedule Wednesday START', 'Maintenance', 'OPRC_MAINTENANCE_SCHEDULE_WEDNESDAY_START', '00', 'Using a 24 hour clock, set the start time for maintenance.', " . $configuration_group_id . ", 10, NOW(), NOW(), NULL, NULL),
            ('Down for Maintenance Schedule Wednesday END', 'Maintenance', 'OPRC_MAINTENANCE_SCHEDULE_WEDNESDAY_END', '00', 'Using a 24 hour clock, set the end time for maintenance.', " . $configuration_group_id . ", 11, NOW(), NOW(), NULL, NULL),
            ('Down for Maintenance Schedule Thursday START', 'Maintenance', 'OPRC_MAINTENANCE_SCHEDULE_THURSDAY_START', '00', 'Using a 24 hour clock, set the start time for maintenance.', " . $configuration_group_id . ", 12, NOW(), NOW(), NULL, NULL),
            ('Down for Maintenance Schedule Thursday END', 'Maintenance', 'OPRC_MAINTENANCE_SCHEDULE_THURSDAY_END', '00', 'Using a 24 hour clock, set the end time for maintenance.', " . $configuration_group_id . ", 13, NOW(), NOW(), NULL, NULL),
            ('Down for Maintenance Schedule Friday START', 'Maintenance', 'OPRC_MAINTENANCE_SCHEDULE_FRIDAY_START', '00', 'Using a 24 hour clock, set the start time for maintenance.', " . $configuration_group_id . ", 14, NOW(), NOW(), NULL, NULL),
            ('Down for Maintenance Schedule Friday END', 'Maintenance', 'OPRC_MAINTENANCE_SCHEDULE_FRIDAY_END', '00', 'Using a 24 hour clock, set the end time for maintenance.', " . $configuration_group_id . ", 15, NOW(), NOW(), NULL, NULL),
            ('Down for Maintenance Schedule Saturday START', 'Maintenance', 'OPRC_MAINTENANCE_SCHEDULE_SATURDAY_START', '00', 'Using a 24 hour clock, set the start time for maintenance.', " . $configuration_group_id . ", 16, NOW(), NOW(), NULL, NULL),
            ('Down for Maintenance Schedule Saturday END', 'Maintenance', 'OPRC_MAINTENANCE_SCHEDULE_SATURDAY_END', '00', 'Using a 24 hour clock, set the end time for maintenance.', " . $configuration_group_id . ", 17, NOW(), NOW(), NULL, NULL),
    
            ('Processing Text', 'Layout', 'OPRC_PROCESSING_TEXT', 'Processing...', 'Text to display when processing changes on the page:', " . $configuration_group_id . ", 21, NOW(), NOW(), NULL, NULL),
            ('Copy Billing Block Text', 'Layout', 'OPRC_BLOCK_TEXT', 'Click here to enter a shipping address', 'Text to display when blocking the shipping address:', " . $configuration_group_id . ", 22, NOW(), NOW(), NULL, NULL),
            ('Not Required Block Text', 'Layout', 'OPRC_NOT_REQUIRED_BLOCK_TEXT', 'Not Required', 'Text to display when blocking part of the page:', " . $configuration_group_id . ", 22, NOW(), NOW(), NULL, NULL),
            ('Click to Register Block Text', 'Layout', 'OPRC_REGISTER_BLOCK_TEXT', 'Click to Register', 'Text to display when blocking the registration:', " . $configuration_group_id . ", 22, NOW(), NOW(), NULL, NULL),
            ('Processing Message Background Color', 'Layout', 'OPRC_MESSAGE_BACKGROUND_COLOR', '#000', 'Enter the hex or color name for the message background color:', " . $configuration_group_id . ", 22, NOW(), NOW(), NULL, NULL),
            ('Processing Message Text Color', 'Layout', 'OPRC_MESSAGE_TEXT_COLOR', '#FFF', 'Enter the hex or color name for the message text color:', " . $configuration_group_id . ", 22, NOW(), NOW(), NULL, NULL),
            ('Processing Message Opacity', 'Layout', 'OPRC_MESSAGE_OPACITY', '0.5', 'Enter the opacity for the block message:', " . $configuration_group_id . ", 22, NOW(), NOW(), NULL, NULL),
            ('Block Overlay Color', 'Layout', 'OPRC_MESSAGE_OVERLAY_COLOR', '#FFF', 'Enter the hex or color name for the overlay background color:', " . $configuration_group_id . ", 23, NOW(), NOW(), NULL, NULL),
            ('Block Overlay Text Color', 'Layout', 'OPRC_MESSAGE_OVERLAY_TEXT_COLOR', '#000', 'Enter the hex or color name for the overlay text color:', " . $configuration_group_id . ", 23, NOW(), NOW(), NULL, NULL),
            ('Block Overlay Opacity', 'Layout', 'OPRC_MESSAGE_OVERLAY_OPACITY', '0.4', 'Enter the opacity for the block overlay:', " . $configuration_group_id . ", 23, NOW(), NOW(), NULL, NULL),
            ('Copy Billing Background Color', 'Layout', 'OPRC_COPYBILLING_BACKGROUND_COLOR', '#000', 'Enter the hex or color name for the message background color:', " . $configuration_group_id . ", 24, NOW(), NOW(), NULL, NULL),
            ('Copy Billing Text Color', 'Layout', 'OPRC_COPYBILLING_TEXT_COLOR', '#FFF', 'Enter the hex or color name for the message text color:', " . $configuration_group_id . ", 24, NOW(), NOW(), NULL, NULL),
            ('Copy Billing Opacity', 'Layout', 'OPRC_COPYBILLING_OPACITY', '0.5', 'Enter the opacity for the block message:', " . $configuration_group_id . ", 24, NOW(), NOW(), NULL, NULL),
            ('Hide PayPal Express', 'Layout', 'OPRC_PAYPAL_EXPRESS_STATUS', 'false', 'Hide PayPal Express during the checkout?', " . $configuration_group_id . ", 25, NOW(), NOW(), NULL, 'zen_cfg_select_option(array(\"true\", \"false\"),'),
            ('Hide Google Checkout', 'Layout', 'OPRC_GOOGLECHECKOUT_STATUS', 'true', 'Hide Google Checkout during the checkout?', " . $configuration_group_id . ", 25, NOW(), NOW(), NULL, 'zen_cfg_select_option(array(\"true\", \"false\"),'), 
            ('Hide Registration', 'Layout', 'OPRC_HIDE_REGISTRATION', 'true', 'Hide the billing/shipping address columns unless the customer clicks to register (COWOA Only must be disabled)?', " . $configuration_group_id . ", 25, NOW(), NOW(), NULL, 'zen_cfg_select_option(array(\"true\", \"false\"),'),
            ('Stacked Layout', 'Layout', 'OPRC_STACKED', 'false', 'Display the checkout screen (shipping/payment) in stacked mode? (note: set to false to display in columns)', " . $configuration_group_id . ", 27, NOW(), NOW(), NULL, 'zen_cfg_select_option(array(\"true\", \"false\"),'),
            ('Shopping Cart Display Default', 'Layout', 'OPRC_CHECKOUT_SHOPPING_CART_DISPLAY_DEFAULT', 'collapsed', 'Display the shopping cart collapsed, partially expanded, or fully expanded?', " . $configuration_group_id . ", 30, NOW(), NOW(), NULL, 'zen_cfg_select_option(array(\"collapsed\", \"partially expanded\", \"fully expanded\"),'),
            ('Use CSS Buttons', 'Layout', 'OPRC_CSS_BUTTONS', 'false', 'Use CSS buttons in the checkout? (Note: this setting overrides the global setting)', " . $configuration_group_id . ", 29, NOW(), NOW(), NULL, 'zen_cfg_select_option(array(\"true\", \"false\"),'),            
            ('Show Product Images', 'Layout', 'OPRC_SHOW_PRODUCT_IMAGES', 'true', 'Display product images in the order total section?', " . $configuration_group_id . ", 30, NOW(), NOW(), NULL, 'zen_cfg_select_option(array(\"true\", \"false\"),'),
			('Contact Details Position', 'Layout', 'OPRC_CONTACT_POSITION', 'login', 'Display the contact details above the login or below the billing address?', " . $configuration_group_id . ", 30, NOW(), NOW(), NULL, 'zen_cfg_select_option(array(\"login\", \"billing\"),'),
            ('Order Total Position', 'Layout', 'OPRC_ORDER_TOTAL_POSITION', 'column', 'Select the position of the order total module:', " . $configuration_group_id . ", 30, NOW(), NOW(), NULL, 'zen_cfg_select_option(array(\"column\", \"top\"),'),
            ('Display Order Steps', 'Layout', 'OPRC_ORDER_STEPS', 'false', 'Display order steps above the page title?', " . $configuration_group_id . ", 30, NOW(), NOW(), NULL, 'zen_cfg_select_option(array(\"true\", \"false\"),'),
            ('Display Credit Modules Position', 'Layout', 'OPRC_CREDIT_POSITION', '3', 'Display the credit modules in column 1 or 3 (if only two columns, selecting 3 will use column 2)?', " . $configuration_group_id . ", 30, NOW(), NOW(), NULL, 'zen_cfg_select_option(array(\"1\", \"3\"),'),
            ('Display Confidence Box', 'Layout', 'OPRC_CONFIDENCE', 'false', 'Display the \"Shop With Confidence\" sidebox on login?', " . $configuration_group_id . ", 41, NOW(), NOW(), NULL, 'zen_cfg_select_option(array(\"true\", \"false\"),'),
            ('Shop With Confidence HTML', 'Layout', 'OPRC_CONFIDENCE_HTML', '', 'Define the HTML to be outputted in the confidence box:', " . $configuration_group_id . ", 41, NOW(), NOW(), NULL, 'zen_cfg_textarea('),
            ('Confirm Email', 'Layout', 'OPRC_CONFIRM_EMAIL', 'false', 'Require user to enter email twice for confirmation?', " . $configuration_group_id . ", 41, NOW(), NOW(), NULL, 'zen_cfg_select_option(array(\"true\", \"false\"),'),
            ('Shipping Address', 'Layout', 'OPRC_SHIPPING_ADDRESS', 'true', 'Display the shipping address form on the login and COWOA pages?', " . $configuration_group_id . ", 41, NOW(), NOW(), NULL, 'zen_cfg_select_option(array(\"true\", \"false\"),'),
                        
            ('Enable Welcome Message', 'Features', 'OPRC_WELCOME_MESSAGE', 'true', 'Should OPRC send a welcome message?', " . $configuration_group_id . ", 25, NOW(), NOW(), NULL, 'zen_cfg_select_option(array(\"true\", \"false\"),'),
			('Remove Items From Checkout', 'Features', 'OPRC_REMOVE_CHECKOUT', 'button', 'Enter \"button\" to use your templates delete button, enter the word to use in a text link (i.e. \"Remove\"), or leave blank to disable this feature.', " . $configuration_group_id . ", 28, NOW(), NOW(), NULL, NULL),
            ('Activate Drop Down List', 'Features', 'OPRC_DROP_DOWN', 'false', 'Activate drop down list to appear on checkout page?', " . $configuration_group_id . ", 30, NOW(), NOW(), NULL, 'zen_cfg_select_option(array(\"true\", \"false\"),'),
            ('Gift Wrapping Module Switch', 'Features', 'OPRC_GIFT_WRAPPING_SWITCH', 'false', 'If the gift wrapping module is installed, set to true to activate', " . $configuration_group_id . ", 30, NOW(), NOW(), NULL, 'zen_cfg_select_option(array(\"true\", \"false\"),'),
            ('Activate Gift Message Field', 'Features', 'OPRC_GIFT_MESSAGE', 'false', 'Activate gift message field to appear on checkout page?', " . $configuration_group_id . ", 30, NOW(), NOW(), NULL, 'zen_cfg_select_option(array(\"true\", \"false\"),'),
            ('Drop Down List Options', 'Features', 'OPRC_DROP_DOWN_LIST', 'Option 1,Option 2,Option 3,Option 4,Option 5', 'Enter each option separated by commas:', " . $configuration_group_id . ", 30, NOW(), NOW(), NULL, NULL),
            ('Activate Checkbox Field', 'Features', 'OPRC_CHECKBOX', 'false', 'Activate checkbox field to appear on checkout page?', " . $configuration_group_id . ", 30, NOW(), NOW(), NULL, 'zen_cfg_select_option(array(\"true\", \"false\"),'),
            ('Change Address Pop-Up Width', 'Features', 'OPRC_CHANGE_ADDRESS_POPUP_WIDTH', '425', 'Set the width for the change address pop-up:', " . $configuration_group_id . ", 30, NOW(), NOW(), NULL, NULL),
            ('Guest Checkout', 'Features', 'OPRC_NOACCOUNT_SWITCH', 'true', 'Enable guest checkout?', " . $configuration_group_id . ", 50, NOW(), NOW(), NULL, 'zen_cfg_select_option(array(\"true\", \"false\"),'),
            ('Guest Checkout Only', 'Features', 'OPRC_NOACCOUNT_ONLY_SWITCH', 'false', 'Disable regular login/registration and force guest checkout?', " . $configuration_group_id . ", 51, NOW(), NOW(), NULL, 'zen_cfg_select_option(array(\"true\", \"false\"),'),
            ('Guest Checkout Default', 'Features', 'OPRC_NOACCOUNT_DEFAULT', 'false', 'Make guest checkout the default option (customer will need to click to register an account)', " . $configuration_group_id . ", 51, NOW(), NOW(), NULL, 'zen_cfg_select_option(array(\"true\", \"false\"),'),
            ('Guest Checkout Selection Method', 'Features', 'OPRC_COWOA_FIELD_TYPE', 'checkbox', 'Display the guest checkout option as a button, checkbox, or radio', " . $configuration_group_id . ", 51, NOW(), NOW(), NULL, 'zen_cfg_select_option(array(\"button\", \"checkbox\", \"radio\"),'),
            ('Combine Guest Checkout Accounts', 'Features', 'OPRC_NOACCOUNT_COMBINE', 'false', 'Combine guest checkout accounts so that guest checkout customers can access their orders and other account features (note this will only work on future registrations)?', " . $configuration_group_id . ", 51, NOW(), NOW(), NULL, 'zen_cfg_select_option(array(\"true\", \"false\"),'),
            ('Guest Checkout - Virtual Products', 'Features', 'OPRC_NOACCOUNT_VIRTUAL', 'false', 'Enable guest checkout when cart contains virtual products?', " . $configuration_group_id . ", 51, NOW(), NOW(), NULL, 'zen_cfg_select_option(array(\"true\", \"false\"),'),
            ('Hide Email Options For Guest Checkout', 'Features', 'OPRC_NOACCOUNT_HIDEEMAIL', 'true', 'Hide \"HTML/TEXT-Only\" for checkout without account?', " . $configuration_group_id . ", 51, NOW(), NOW(), NULL, 'zen_cfg_select_option(array(\"true\", \"false\"),'),
            ('Always Allow Guest Checkout', 'Features', 'OPRC_NOACCOUNT_ALWAYS', 'true', 'Should permanent account holders be able to use guest checkout?', " . $configuration_group_id . ", 51, NOW(), NOW(), NULL, 'zen_cfg_select_option(array(\"true\", \"false\"),'),
            ('Disable Gift Voucher On Guest Checkout', 'Features', 'OPRC_NOACCOUNT_DISABLE_GV', 'true', 'Should the gift vouchers module be disabled when a customer uses guest checkout?', " . $configuration_group_id . ", 51, NOW(), NOW(), NULL, 'zen_cfg_select_option(array(\"true\", \"false\"),'),
        	('Master Password', 'Features', 'OPRC_MASTER_PASSWORD', 'false', 'Allow login to customer account using master password??', " . $configuration_group_id . ", 41, NOW(), NOW(), NULL, 'zen_cfg_select_option(array(\"true\", \"false\"),'),
            ('Enable reCaptcha Form', 'Features', 'OPRC_RECAPTCHA_STATUS', 'false', 'Disply reCAPTCHA on registration page?', " . $configuration_group_id . ", 45, NOW(), NOW(), NULL, 'zen_cfg_select_option(array(\"true\", \"false\"),'),
            ('reCAPTCHA Theme', 'Features', 'OPRC_RECAPTCHA_THEME', 'white', 'Choose a theme option for the widget.', " . $configuration_group_id . ", 46, NOW(), NOW(), NULL, 'zen_cfg_select_option(array(\"red\", \"white\", \"blackglass\", \"clean\", \"custom\"),'),
            ('Forgotten Password Pop-Up Width', 'Features', 'OPRC_FORGOTTEN_PASSWORD_POPUP_WIDTH', '425', 'Set the width for the forgotten password pop-up:', " . $configuration_group_id . ", 30, NOW(), NOW(), NULL, NULL),                          
            ('Remove Items Refresh Selectors', 'Advanced', 'OPRC_REMOVE_CHECKOUT_REFRESH_SELECTORS', '', 'Add selectors separated by commas (no spaces) that need to be refreshed when removing a product inside the checkout.  This can include things like your shopping cart sidebox.', " . $configuration_group_id . ", 28, NOW(), NOW(), NULL, NULL),
            ('Remove Items Callback', 'Advanced', 'OPRC_REMOVE_CHECKOUT_REMOVE_CALLBACK', '', 'Add JavaScript to be executed as a callback function when a product is removed:', " . $configuration_group_id . ", 28, NOW(), NOW(), NULL, 'zen_cfg_textarea('),
            ('Display AJAX Errors', 'Advanced', 'OPRC_AJAX_ERRORS', 'true', 'Should AJAX errors be displayed in an alert box or fail silently?', " . $configuration_group_id . ", 29, NOW(), NOW(), NULL, 'zen_cfg_select_option(array(\"true\", \"false\"),'),
            ('Checkout Submit Callback', 'Advanced', 'OPRC_CHECKOUT_SUBMIT_CALLBACK', '', 'Add JavaScript to be executed on checkout submit:', " . $configuration_group_id . ", 49, NOW(), NOW(), NULL, 'zen_cfg_textarea('),
            ('Login/Registration Refresh Selectors', 'Advanced', 'OPRC_CHECKOUT_LOGIN_REGISTRATION_REFRESH_SELECTORS', '', 'Enter selectors separated by commas that should be refreshed via AJAX when logging in or registering during checkout.', " . $configuration_group_id . ", 29, NOW(), NOW(), NULL, 'zen_cfg_textarea('),
            ('Change Address Callback', 'Advanced', 'OPRC_CHANGE_ADDRESS_CALLBACK', '', 'Add JavaScript to be executed as a callback function when the change address pop-up opens:', " . $configuration_group_id . ", 30, NOW(), NOW(), NULL, 'zen_cfg_textarea('),      
            ('Refresh Payment Modules', 'Advanced', 'OPRC_REFRESH_PAYMENT', 'false', 'On the execution of the updateForm function, should the payment modules section refresh (enable if using modules like Ship2Pay)?', " . $configuration_group_id . ", 30, NOW(), NOW(), NULL, 'zen_cfg_select_option(array(\"true\", \"false\"),'),
            ('Collapse Discount Boxes', 'Advanced', 'OPRC_COLLAPSE_DISCOUNTS', 'true', 'Should the discount boxes display collapsed?', " . $configuration_group_id . ", 30, NOW(), NOW(), NULL, 'zen_cfg_select_option(array(\"true\", \"false\"),'),
            ('Enable Shipping Info', 'Advanced', 'OPRC_SHIPPING_INFO', 'false', 'Enable a collapsable info section to each shipping method?  (Note: this requires each shipping method to be modified, see documentation)', " . $configuration_group_id . ", 30, NOW(), NOW(), NULL, 'zen_cfg_select_option(array(\"true\", \"false\"),'),
            ('Enable Automatic Login', 'Advanced', 'OPRC_EASY_SIGNUP_AUTOMATIC_LOGIN', 'false', 'Allow user to automatically login?', " . $configuration_group_id . ", 41, NOW(), NOW(), NULL, 'zen_cfg_select_option(array(\"true\", \"false\"),'),
            ('Google Analytics Status', 'Advanced', 'OPRC_GA_ENABLED', 'false', 'Is Google Analytics Installed?', " . $configuration_group_id . ", 47, NOW(), NOW(), NULL, 'zen_cfg_select_option(array(\"true\", \"false\"),'),
            ('Google Analytics Method', 'Advanced', 'OPRC_GA_METHOD', 'default', 'If Google Analytics is installed, which tracking method does it use?', " . $configuration_group_id . ", 48, NOW(), NOW(), NULL, 'zen_cfg_select_option(array(\"default\", \"asynchronous\"),');");

if (!defined('ACCOUNT_TELEPHONE')) {
  $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) VALUES ('Telephone Number - Billing', 'ACCOUNT_TELEPHONE', 'true', 'Display telephone number field during account creation and with account information', '5', '8', 'zen_cfg_select_option(array(\"true\", \"false\"), ', now());");
}
if (!defined('ACCOUNT_TELEPHONE_SHIPPING')) {
  $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) VALUES ('Telephone Number - Shipping', 'ACCOUNT_TELEPHONE_SHIPPING', 'true', 'Display telephone number field for the shipping address?', '5', '8', 'zen_cfg_select_option(array(\"true\", \"false\"), ', now());");
}
// check if query exists
$query_builder = $db->Execute("SELECT query_id FROM " . TABLE_QUERY_BUILDER . " WHERE query_name = 'Permanent Account Holders Only' LIMIT 1;");
if (!$query_builder->RecordCount() > 0) {
  $db->Execute("INSERT INTO " . TABLE_QUERY_BUILDER . " (  query_category , query_name , query_description , query_string, query_keys_list ) VALUES (  'email,newsletters', 'Permanent Account Holders Only', 'Send email only to permanent account holders ', 'select customers_email_address, customers_firstname, customers_lastname from TABLE_CUSTOMERS where COWOA_account != 1 order by customers_lastname, customers_firstname, customers_email_address', '');");
}

$zc150 = (PROJECT_VERSION_MAJOR > 1 || (PROJECT_VERSION_MAJOR == 1 && substr(PROJECT_VERSION_MINOR, 0, 3) >= 5));
 if ($zc150 && function_exists('zen_page_key_exists') && function_exists('zen_register_admin_page')) // continue Zen Cart 1.5.0
{
	if (!zen_page_key_exists('configOPRC')) {
        if ((int)$configuration_group_id > 0) {
            zen_register_admin_page('configOPRC',
                                    'BOX_CONFIGURATION_OPRC', 
                                    'FILENAME_CONFIGURATION',
                                    'gID=' . $configuration_group_id, 
                                    'configuration', 
                                    'Y',
                                    $configuration_group_id);
          
        $messageStack->add('Enabled One Page Responsive Checkout Configuration menu.', 'success');
      }
    }
}
