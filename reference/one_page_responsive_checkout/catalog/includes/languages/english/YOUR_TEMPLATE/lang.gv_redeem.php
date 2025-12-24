<?php
//
// +----------------------------------------------------------------------+
// |zen-cart Open Source E-commerce                                       |
// +----------------------------------------------------------------------+
// | Copyright (c) 2003 The zen-cart developers                           |
// |                                                                      |
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
//   * @version $Id: J_Schilz for Integrated COWOA - 14 April 2007
//
$define = [
    'NAVBAR_TITLE' => 'Redeem ' . TEXT_GV_NAME,
    'HEADING_TITLE' => 'Redeem ' . TEXT_GV_NAME,
    'TEXT_INFORMATION' => 'For more information regarding ' . TEXT_GV_NAME . ', please see our <a href="' . zen_href_link(FILENAME_GV_FAQ, '', 'NONSSL').'">' . GV_FAQ . '.</a>',
    'TEXT_INVALID_GV' => 'The ' . TEXT_GV_NAME . ' number %s may be invalid or has already been redeemed. To contact the shop owner please use the Contact Page',
    'TEXT_VALID_GV' => 'Congratulations, you have redeemed a ' . TEXT_GV_NAME . ' worth %s.',
    'ERROR_GV_CREATE_ACCOUNT' => 'To redeem a ' . TEXT_GV_NAME . ' you must create an account.',
    'ERROR_GV_COWOA' => 'To redeem a ' . TEXT_GV_NAME . ' you must create an account.  You may not enter a ' . TEXT_GV_NAME . ' once you have begun checking out without an account. If you would like to use a ' . TEXT_GV_NAME . ', you may <a href="' . zen_href_link(FILENAME_LOGOFF, '', 'SSL', false) . '">click here</a> to end your session, empty your cart, and begin again.'
];

$zc158 = (PROJECT_VERSION_MAJOR > 1 || (PROJECT_VERSION_MAJOR == 1 && substr(PROJECT_VERSION_MINOR, 0, 3) >= '5.8'));
if ($zc158) {
    return $define;
} else {
    nmx_create_defines($define);
}
//eof;