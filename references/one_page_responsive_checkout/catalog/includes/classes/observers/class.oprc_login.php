<?php
//
// +----------------------------------------------------------------------+
// |zen-cart Open Source E-commerce                                       |
// +----------------------------------------------------------------------+
// | Copyright (c) 2007-2008 Numinix Technology http://www.numinix.com    |
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
// | to obtain it through the world-wide-web, please send a note to       |
// | license@zen-cart.com so we can mail you a copy immediately.          |
// +----------------------------------------------------------------------+
//  $Id: class.oprc_login.php 3 2012-07-08 21:11:34Z numinix $
//
/**
 * Observer class used to redirect to the Easy Sign-up page
 *
 */
if (!class_exists('ESLLoginObserver', false)) {
    class ESLLoginObserver extends base {
        function __construct() {
            global $zco_notifier;
            $zco_notifier->attach($this, array('NOTIFY_LOGIN_SUCCESS'));
            $zco_notifier->attach($this, array('NOTIFY_LOGIN_SUCCESS_VIA_NO_ACCOUNT'));
            $zco_notifier->attach($this, array('NOTIFY_LOGIN_SUCCESS_VIA_OPRC_CREATE_ACCOUNT'));
        }

        function update(&$class, $eventID, $paramsArray) {
            if (OPRC_STATUS == 'true') {
                // get the customers browser and set a session
                global $browser;
                if (!is_object($browser)) {
                    if (file_exists(DIR_FS_CATALOG . 'plugins/riCjLoader/lib/browser.php') && floatval(phpversion()) > 5) {
                        include_once DIR_FS_CATALOG . 'plugins/riCjLoader/lib/browser.php';
                        $browser = new _Browser();
                    }
                }
                if (is_object($browser)) {
                    $_SESSION['browser'] = preg_replace("/[^a-zA-Z0-9s]/", "-", strtolower($browser->getBrowser())) . ' ' . $browser->getVersion();
                    global $db;

                    // Check if customers_browser column exists
                    $column_exists = $db->Execute("SHOW COLUMNS FROM " . TABLE_CUSTOMERS . " LIKE 'customers_browser'");

                    if ($column_exists->EOF) {
                        $db->Execute("ALTER TABLE " . TABLE_CUSTOMERS . " ADD COLUMN customers_browser VARCHAR(255) DEFAULT NULL;");
                    }
                    $db->Execute(
                        "UPDATE " . TABLE_CUSTOMERS . "
                        SET customers_browser = '" . zen_db_input($_SESSION['browser']) . "'
                        WHERE customers_id = " . (int) $_SESSION['customer_id'] . "
                        LIMIT 1;"
                    );
                }
                // check for redirect paramater
                if (isset($_SESSION['redirect_url'])) {
                    $redirect_url = $_SESSION['redirect_url'];
                    unset($_SESSION['redirect_url']);
                    // bof: not require part of contents merge notice
                    // restore cart contents
                    $_SESSION['cart']->restore_contents();
                    // eof: not require part of contents merge notice
                    zen_redirect($redirect_url);
                }
            }
        }
    }
}
// eof
