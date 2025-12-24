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
//  $Id: class.virtual_guest.php 3 2012-07-08 21:11:34Z numinix $
//

if (!class_exists('virtualGuestObserver')) {
class virtualGuestObserver extends base {
        public function __construct() {
                global $zco_notifier;
                $zco_notifier->attach($this, array('NOTIFY_CHECKOUT_PROCESS_AFTER_SEND_ORDER_EMAIL'));
        }

        public function update(&$class, $eventID, $paramsArray) {
          global $messageStack, $db;
      if (OPRC_STATUS == 'true' && isset($_SESSION['COWOA']) && $_SESSION['COWOA'] == true && $_SESSION['cart']->get_content_type() != 'physical' && OPRC_NOACCOUNT_VIRTUAL == 'false') {
        unset($_SESSION['COWOA']);
        //convert to full account
        include_once(zen_get_file_directory(DIR_WS_LANGUAGES . $_SESSION['language'] . '/lang.', FILENAME_PASSWORD_FORGOTTEN, 'false'));
        $new_password = zen_create_random_value( (ENTRY_PASSWORD_MIN_LENGTH > 0 ? ENTRY_PASSWORD_MIN_LENGTH : 5) );
        $crypted_password = zen_encrypt_password($new_password);
        $check_customer_query = "SELECT customers_firstname, customers_lastname, customers_password, customers_email_address
                                 FROM " . TABLE_CUSTOMERS . "
                                 WHERE customers_id = " . $_SESSION['customer_id'] . " LIMIT 1;";

        $check_customer_query = $db->bindVars($check_customer_query, ':emailAddress', $email_address, 'string');
        $check_customer = $db->Execute($check_customer_query);
        $sql = "UPDATE " . TABLE_CUSTOMERS . "
                SET customers_password = :password,
                COWOA_account = 0
                WHERE customers_id = " . $_SESSION['customer_id'] . " LIMIT 1;";

        $sql = $db->bindVars($sql, ':password', $crypted_password, 'string');
        $sql = $db->bindVars($sql, ':customersID', $_SESSION['customer_id'], 'integer');
        $db->Execute($sql);

        $html_msg['EMAIL_CUSTOMERS_NAME'] = $check_customer->fields['customers_firstname'] . ' ' . $check_customer->fields['customers_lastname'];
        $html_msg['EMAIL_MESSAGE_HTML'] = sprintf(EMAIL_PASSWORD_REMINDER_BODY, $new_password);

        // send the email
        zen_mail($check_customer->fields['customers_firstname'] . ' ' . $check_customer->fields['customers_lastname'], $check_customer->fields['customers_email_address'], EMAIL_PASSWORD_REMINDER_SUBJECT, sprintf(EMAIL_PASSWORD_REMINDER_BODY, $new_password), STORE_NAME, EMAIL_FROM, $html_msg,'password_forgotten');

        $messageStack->add_session('header', SUCCESS_PASSWORD_CREATED, 'success');
      }
        }
}
}
// eof
