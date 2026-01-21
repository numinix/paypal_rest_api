<?php

//
// +----------------------------------------------------------------------+
// |zen-cart Open Source E-commerce                                       |
// +----------------------------------------------------------------------+
// | Copyright (c) 2007-2025 Numinix Technology http://www.numinix.com    |
// |                                                                      |
// | Portions Copyright (c) 2003-2006 Zen Cart Development Team           |
// | http://www.zen-cart.com/index.php                                    |
// |                                                                      |
// | Portions Copyright (c) 2003 osCommerce                               |
// +----------------------------------------------------------------------+
// | This source file is subject to version 2.0 of the GPL license,       |
// | that is bundled with this package in the file LICENSE, and is        |
// | available through the world-wide-web at the following url:           |
// | http://www.zen-cart.com/license/2_0.txt.                             |
// | If you did not receive a copy of the zen-cart license and are unable |
// | to obtain it through the world-wide-web, please s_END a note to       |
// | license@zen-cart.com so we can mail you a copy immediately.          |
// +----------------------------------------------------------------------+
//  $Id: header_php.php 14 2025-06-30 23:47:08Z numinix $

if (OPRC_STATUS != 'true') {
    zen_redirect(zen_href_link(FILENAME_CHECKOUT_SHIPPING, '', 'SSL'));
}

$flag_disable_left = true;
$flag_disable_right = true;

$zco_notifier->notify('NOTIFY_HEADER_START_OPRC');

$temp = $current_page_base;
$current_page_base = 'lang.' . $current_page_base;

if (!isset($template) || !is_object($template)) {
    if (!class_exists('template_func')) {
        $templateClassPath = DIR_WS_CLASSES . 'template_func.php';
        if (file_exists($templateClassPath)) {
            require_once $templateClassPath;
        }
    }

    if (class_exists('template_func')) {
        $template = new template_func();
    }
}

require(DIR_WS_MODULES . zen_get_module_directory('require_languages.php'));
$current_page_base = $temp;

// begin checkout maintenance
$down_for_maintenance = false;
$exclude_ip = strstr(EXCLUDE_ADMIN_IP_FOR_MAINTENANCE, $_SERVER['REMOTE_ADDR']);
if (OPRC_MAINTENANCE == 'true' && !$exclude_ip) {
    $down_for_maintenance = true;
} else {
    if (OPRC_MAINTENANCE_SCHEDULE == 'true') {
        $today = strtoupper(date('l', time() + OPRC_MAINTENANCE_SCHEDULE_OFFSET * 60 * 60)); // SUNDAY
        $current_hour = date('G') + OPRC_MAINTENANCE_SCHEDULE_OFFSET; // 13
        if (constant('OPRC_MAINTENANCE_SCHEDULE_' . $today . '_END') < constant('OPRC_MAINTENANCE_SCHEDULE_' . $today . '_START')) {
            if (($current_hour >= constant('OPRC_MAINTENANCE_SCHEDULE_' . $today . '_END')) && ($current_hour < constant('OPRC_MAINTENANCE_SCHEDULE_' . $today . '_START'))) {
                $down_for_maintenance = false;
            } elseif (!$exclude_ip) {
                $down_for_maintenance = true;
            }
        } else {
            if (!$exclude_ip && ($current_hour >= constant('OPRC_MAINTENANCE_SCHEDULE_' . $today . '_START')) && ($current_hour < constant('OPRC_MAINTENANCE_SCHEDULE_' . $today . '_END'))) {
                $down_for_maintenance = true;
            }
        }
    }
}

if (!$down_for_maintenance) {

    $customer_check = (isset($_SESSION['customer_id']) ? true : false);

    switch ($customer_check) {
        case true:
            if ($_SESSION['cart']->count_contents() > 0) {
                // default page for checkout
                if (isset($_POST['oprcaction']) && $_POST['oprcaction'] == 'updateCredit') {
                    $messageStack->reset();
                }
                require(DIR_WS_MODULES . zen_get_module_directory('oprc_updates.php'));
            } else {
                if (!in_array(zen_back_link(true), array(zen_href_link(FILENAME_CHECKOUT_SHIPPING, '', 'SSL'), zen_href_link(FILENAME_CHECKOUT_PAYMENT, '', 'SSL'), zen_href_link(FILENAME_ONE_PAGE_CHECKOUT, '', 'SSL'), zen_href_link(FILENAME_OPRC_CONFIRMATION, '', 'SSL')))) {
                    zen_redirect(zen_href_link(FILENAME_SHOPPING_CART));
                } else {
                    zen_redirect(zen_href_link(FILENAME_ACCOUNT));
                }
            }
            $breadcrumb->add(NAVBAR_TITLE_1_CHECKOUT, zen_href_link(FILENAME_ONE_PAGE_CHECKOUT, '', 'SSL', false));
            break;
        default: // customer is not logged in
            // Auto Login
            if (OPRC_EASY_SIGNUP_AUTOMATIC_LOGIN == 'true' && isset($_COOKIE['email_address']) && isset($_COOKIE['password'])) {
                zen_redirect(zen_href_link(FILENAME_OPRC_LOGIN, 'oprcaction=process&autologin=true', 'SSL'));
            }

            // if guest checkout only, go straight to step 2
            if (isset($_GET['step']) && isset($_GET['type']) && $_GET['step'] != 1 && $_GET['step'] != 2 && $_GET['type'] == 'cowoa' && OPRC_NOACCOUNT_ONLY_SWITCH == 'true') {
                zen_redirect(zen_href_link(FILENAME_ONE_PAGE_CHECKOUT, 'step=2&type=cowoa', 'SSL'));
            }

            // if there is nothing in the cart, redirect to the regular login page
            if (isset($_SESSION['cart']) && $_SESSION['cart']->count_contents() > 0) {
                // BOF Captcha
                if (defined('CAPTCHA_CREATE_ACCOUNT') && CAPTCHA_CREATE_ACCOUNT == 'true' && file_exists(DIR_WS_CLASSES . 'captcha.php')) { // check exists because file is not included with OPRC
                    require(DIR_WS_CLASSES . 'captcha.php');
                    $captcha = new captcha();
                }
                // EOF Captcha

                // ajax check
                // check if shipping address should be displayed
                if (OPRC_SHIPPING_ADDRESS == 'true') {
                    $shippingAddressCheck = true;
                }
                // check if the copybilling checkbox should be checked
                $shippingAddress = true;
                /*
                * Set flags for template use:
                */
                $selected_country = (isset($_SESSION['zone_country_id']) && $_SESSION['zone_country_id'] != '') ? $_SESSION['zone_country_id'] : SHOW_CREATE_ACCOUNT_DEFAULT_COUNTRY;
                $selected_country_shipping = (isset($_SESSION['zone_country_id_shipping']) && $_SESSION['zone_country_id_shipping'] != '') ? $_SESSION['zone_country_id_shipping'] : SHOW_CREATE_ACCOUNT_DEFAULT_COUNTRY;
                $flag_show_pulldown_states = (
                    (
                        (
                            (isset($process) && $process === true) ||
                            (isset($entry_state_has_zones) && $entry_state_has_zones === true)
                        ) &&
                        (isset($zone_name) && $zone_name === '')
                    ) ||
                    ACCOUNT_STATE_DRAW_INITIAL_DROPDOWN === 'true' ||
                    !empty($error_state_input)
                ) ? true : false;
                $flag_show_pulldown_states_shipping = (
                    (
                        (
                            (isset($process_shipping) && $process_shipping === true) ||
                            (isset($entry_state_has_zones_shipping) && $entry_state_has_zones_shipping === true)
                        ) &&
                        (isset($zone_name_shipping) && $zone_name_shipping === '')
                    ) ||
                    ACCOUNT_STATE_DRAW_INITIAL_DROPDOWN === 'true' ||
                    !empty($error_state_input_shipping)
                ) ? true : false;
                $state = isset($_SESSION['state']) ? $_SESSION['state'] : null;
                $state_shipping = isset($_SESSION['state_shipping']) ? $_SESSION['state_shipping'] : null;
                $state_field_label = ($flag_show_pulldown_states) ? '' : ENTRY_STATE;
                $state_field_label_shipping = ($flag_show_pulldown_states_shipping) ? '' : ENTRY_STATE;
                $zone_id = isset($_SESSION['zone_id']) ? $_SESSION['zone_id'] : null;
                $zone_id_shipping = isset($_SESSION['zone_id_shipping']) ? $_SESSION['zone_id_shipping'] : null;

                if (!isset($email_format)) {
                    $email_format = (ACCOUNT_EMAIL_PREFERENCE == '1' ? 'HTML' : 'TEXT');
                }
                if (!isset($newsletter)) {
                    $newsletter = ACCOUNT_NEWSLETTER_STATUS == '1' && OPRC_FORCE_GUEST_ACCOUNT_SUBSCRIPTION == "true" ?  true : false; // NX-5221 :: fix for newsletter checkbox not checked if guest checkout forces it
                }

                require_once(DIR_WS_CLASSES . 'order.php');
                $order = new order();
                require_once(DIR_WS_CLASSES . 'order_total.php');
                $order_total_modules = new order_total();

                $breadcrumb->add(NAVBAR_TITLE_1, zen_href_link(FILENAME_ONE_PAGE_CHECKOUT, '', 'SSL', false));
                // _END registration
            } else {
                zen_redirect(zen_href_link(FILENAME_LOGIN, '', 'SSL'));
            }
            break;
    }
}
