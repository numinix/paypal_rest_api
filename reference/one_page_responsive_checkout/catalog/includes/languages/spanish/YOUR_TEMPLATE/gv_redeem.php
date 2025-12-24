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

define('NAVBAR_TITLE', 'Redimir ' . TEXT_GV_NAME);
define('HEADING_TITLE', 'Redimir ' . TEXT_GV_NAME);
define('TEXT_INFORMATION', 'Para más información sobre ' . TEXT_GV_NAME . ', por favor vea nuestro <a href="' . zen_href_link(FILENAME_GV_FAQ, '', 'NONSSL').'">' . GV_FAQ . '.</a>');
define('TEXT_INVALID_GV', 'El ' . TEXT_GV_NAME . ' número %s puede ser inválido o ya ha sido redimido. Para contactar al dueño de la tienda por favor use la página de contacto.');
define('TEXT_VALID_GV', 'Enhorabuena, has redimido un ' . TEXT_GV_NAME . ' valor %s.');

define('ERROR_GV_CREATE_ACCOUNT', 'Para redimir un ' . TEXT_GV_NAME . ' debes crear una cuenta.');
define('ERROR_GV_COWOA', 'Para redimir un ' . TEXT_GV_NAME . ' debes crear una cuenta.  No puedes entrar a ' . TEXT_GV_NAME . ' Una vez que haya comenzado a pagar sin una cuenta. Si desea utilizar un ' . TEXT_GV_NAME . ', puedes <a href="' . zen_href_link(FILENAME_LOGOFF, '', 'SSL', false) . '">haga clic aquí</a> para finalizar su sesión, vaciar su carrito y comenzar de nuevo.');
//eof