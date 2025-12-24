<?php
/**
 * @package languageDefines
 * @copyright Copyright 2003-2006 Zen Cart Development Team
 * @copyright Portions Copyright Joseph Schilz
 * @copyright Portions Copyright 2003 osCommerce
 * @license http://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 * @version $Id: J_Schilz for Integrated COWOA - 14 April 2007
 */

define('NAVBAR_TITLE_1', 'Comprar');
define('NAVBAR_TITLE_1_CHECKOUT', 'Comprar');

define('TABLE_HEADING_BILLING_ADDRESS', 'Direcci&#243;n de facturaci&#243;n');
define('TABLE_HEADING_SHIPPING_ADDRESS', 'Direcci&#243;n de Envio');
define('TABLE_HEADING_SHOPPING_CART', 'Carro de Compra');
define('TABLE_HEADING_PAYMENT_METHOD', 'M&#233;todo de pago');
define('HEADING_STEP_3_COMMENTS', 'Comentarios Acerca de Su Pedido');
define('TABLE_HEADING_SHIPPING_METHOD', 'M&#233;todo de env&#237;o');
define('TABLE_HEADING_SHIPPING_METHOD_NOT_REQUIRED', 'No necesita envio');
define('TABLE_HEADING_CONTACT_DETAILS', 'Detalles de contacto');
define('TABLE_HEADING_CONDITIONS', '<span class="termsconditions">T&#233;rminos y condiciones</span>');
define('TABLE_HEADING_COMMENTS', 'Comentarios Acerca de Su Pedido');

define('HEADING_TITLE', 'Pago seguro');
define('HEADING_NEW_CUSTOMERS', 'Clientes Nuevos');
define('HEADING_COWOA', 'Checkout de invitado');
define('HEADING_RETURNING_CUSTOMER', 'Clientes Registrados');
define('HEADING_RETURNING_CUSTOMER_SPLIT', 'Clientes Registrados');
define('HEADING_CONFIDENCE', 'Compra con confianza');

define('TEXT_RATHER_COWOA', 'Para una experiencia de pago m&#225;s r&#225;pida, ofrecemos la opci&#243;n de pagar como invitado.<br />');
define('TEXT_COWOA_CHECKED', 'Checkout como invitado');
define('TEXT_COWOA_UNCHECKED', 'Desmarcar para registrar una cuenta');
define('COWOA_HEADING', 'Checkout de invitado');
define('TEXT_NEW_CUSTOMER_INTRODUCTION', 'Crear Cuenta');

define('HIDEREGISTRATION_CREATE_ACCOUNT', '<p>Una cuenta te permite:</p>');
define('HIDEREGISTRATION_COWOA', '<p>Contin&#250;e con el proceso de pago y tendr&#225; la oportunidad de crear una cuenta en el siguiente paso.</p>');

define('TEXT_OPRC_LOGIN_INTRO', '');
define('TEXT_COWOA_LOGIN', '<p id="cowoaLogin">Los invitados que hayan pedido anteriormente pueden registrar una cuenta haciendo clic en "¿olvid&#243; su contrase&#241;a?" enlace de arriba.</p>');
define('TEXT_ACCOUNT_BENEFITS', '<ul id="accountBenefits"><li>Checkout m&#225;s r&#225;pido</li><li>Guardar direcciones de env&#237;o</li><li>Rastrea tus pedidos desde tu cuenta</li><li>Revisar pedidos anteriores</li></ul>');
define('TEXT_COWOA_BENEFITS', '<ul id="guestBenefits"><li>Nunca tienes que recordar tu contrase&#241;a</li><li>Rastrear el estado del pedido usando la direcci&#243;n de correo electr&#243;nico y el n&#250;mero de pedido</li><li>Registre una cuenta completa en cualquier momento</li></ul>');
define('REGULAR_HEADING', 'Crear Cuenta');

define('TEXT_ENTER_SHIPPING_INFORMATION', 'Este es actualmente el &#250;nico m&#233;todo de env&#237;o disponible para usar en este pedido.'); 
define('TEXT_LEGEND_HEAD', 'Crear Cuenta');
define('TEXT_SELECT_PAYMENT_METHOD', 'Seleccione su tipo de pago');
define('TEXT_CHOOSE_SHIPPING_DESTINATION', '');
define('TEXT_CHOOSE_SHIPPING_METHOD', 'Seleccione el m&#233;todo de env&#237;o preferido para utilizar en este pedido.');
define('TEXT_SELECTED_BILLING_DESTINATION', '');
define('TEXT_PASSWORD_FORGOTTEN', '¿Olvid&#243; su contrase&#241;a?');
define('TEXT_CONDITIONS_DESCRIPTION', '<span class="termsdescription">Por favor, reconozca los t&#233;rminos y condiciones vinculados a este pedido marcando la siguiente casilla. Los t&#233;rminos y condiciones se pueden leer <a href="' . zen_href_link(FILENAME_CONDITIONS, '', 'SSL') . ' #conditions" target="_blank"><span class="pseudolink">aqu&#237;</span></a>.</span>');
define('TEXT_CONDITIONS_CONFIRM', '<span class="termsiagree">He le&#237;do y acepto los t&#233;rminos y condiciones vinculados a este pedido.</span>');
define('TEXT_PRIVACY_CONDITIONS_DESCRIPTION', 'Por favor, reconozca que est&#225; de acuerdo con nuestra declaración de privacidad marcando la siguiente casilla. La declaración de privacidad se puede leer <a href="' . zen_href_link(FILENAME_PRIVACY, '', 'SSL') . ' #privacy" target="blank"><span class="pseudolink">aqu&#237;</span></a>.');
define('TITLE_NO_SHIPPING_AVAILABLE', 'En este momento no esta disponible');
define('TEXT_NO_SHIPPING_AVAILABLE','<span class="alert">Lo sentimos, no estamos enviando a su regi&#243;n en este momento.</span><br /> Por favor, p&#243;ngase en contacto con nosotros para arreglos alternativos.');
define('TEXT_NO_PAYMENT_OPTIONS_AVAILABLE', 'En este momento no esta disponible');
define('TEXT_REQUIRED_INFORMATION_OPRC', '* requerido');
define('TEXT_NEED_HELP', 'Need Help?');
define('TEXT_CONTACT_US_AT', 'Contactanos en ');
define('TEXT_ORDER_TOTAL_DISCLAIMER', 'Gastos de env&#237;o y manipulaci&#243;n y otros cargos pueden aplicar');
define('TEXT_BACKORDERED', '* Este art&#237;culo est&#225; en espera');
// define('TEXT_EXPAND_ALL_PRODUCTS', 'Expand All Products');
define('TEXT_ITEMS_IN_CART', 'Tienes <strong>%s</strong> objetos en tu carro de compra');

define('OPRC_LOGIN_VALIDATION_ERROR_MESSAGE', 'Corrija los campos resaltados');
define('OPRC_NO_ADDRESS_ERROR_MESSAGE', 'Por favor agregue una direcci&#243;n a su pedido.');

define('ENTRY_EMAIL_ADDRESS', 'Email:');
define('ENTRY_EMAIL_ADDRESS_CONFIRM', 'Confirmar email:');

define('ENTRY_SECURITY_CHECK', 'Control de seguridad:');
define('ENTRY_SECURITY_CHECK_ERROR', 'El c&#243;digo de control de seguridad no se escribi&#243; correctamente. Int&#233;ntalo de nuevo.');

define('ERROR_SECURITY_ERROR', 'Se ha producido un error de seguridad al intentar iniciar sesi&#243;n.');

define('ENTRY_AUTOMATIC_LOGIN', 'Mantente conectado');

define('BUTTON_COWOA_ALT', 'Continuar');
define('BUTTON_APPLY_ALT', 'Aplicar');
define('BUTTON_EDIT_CART_SMALL_ALT', 'Editar carro');
define('BUTTON_CHANGE_ADDRESS_ALT', 'Cambiar direcci&#243;n');
define('ENTRY_NEWSLETTER', 'Suscr&#237;bete a nuestro bolet&#237;n');
define('PLEASE_SELECT', 'Seleccionar');
define('ENTRY_DATE_OF_BIRTH_TEXT', '*');

// order steps
define('HEADING_STEP_1', 'Iniciar / Crear Cuenta');
define('HEADING_STEP_1_GUEST', 'Iniciar sesi&#243;n o continuar como invitado');
define('HEADING_STEP_2', 'Direcci&#243;n de Facturaci&#243;n y Env&#237;o');
define('HEADING_STEP_2_NO_SHIPPING', 'Datos de facturaci&#243;n');
define('HEADING_STEP_3', 'M&#233;todo de env&#237;o y pago');
define('HEADING_STEP_3_NO_SHIPPING', 'M&#233;todo de Pago');
define('HEADING_STEP_4', 'Confirmaci&#243;n de pago');
define('HEADING_WELCOME', '&#161;Bienvenido %s&#33;');

// confirmation
define('TITLE_CONFIRM_CHECKOUT', '<em>&#250;ltimo paso</em>'); 
define('TEXT_CONFIRM_CHECKOUT', '- proceder al procesamiento');
define('TITLE_CONTINUE_CHECKOUT_CONFIRMATION', 'Continuar');
define('TEXT_CONTINUE_CHECKOUT_CONFIRMATION', '- confirmar pedido.');
define('TITLE_CONTINUE_CHECKOUT_PROCEDURE', '<em>Continuar a la comprobaci&#243;n</em>');
define('TEXT_CONTINUE_CHECKOUT_PROCEDURE', '- seleccione env&#237;o/pago.');

define('ENTRY_COPYBILLING', 'Igual que la facturación');
define('ENTRY_COPYBILLING_TEXT', '');

// OPRC OPTIONS
define('TABLE_HEADING_DROPDOWN', 'Drop Down Heading');
define('TABLE_HEADING_GIFT_MESSAGE', 'Mensaje de regalo');
define('TABLE_HEADING_OPRC_CHECKBOX', 'Recibo de regalo');
define('TEXT_FIELD_REQUIRED', '<span class="fieldRequired">* requerido</span>');
define('TEXT_DROP_DOWN', 'Seleccione una opcion: ');
define('TEXT_OPRC_CHECKBOX', 'Incluir recibo de regalo (precios no mostrados)');

// Maintenance
define('OPRC_DOWN_FOR_MAINTENANCE_TEXT_INFORMATION', '<p>Nuestro sistema de verificaci&#243;n est&#225; actualmente inactivo por mantenimiento mientras realizamos actualizaciones. Puede continuar navegando por el sitio y volver a consultar en unos minutos cuando finalice el mantenimiento.</p>');
define('OPRC_DOWN_FOR_MAINTENANCE_STATUS_TEXT', 'Haga clic en el bot&#243;n de abajo para verificar si se ha completado el mantenimiento.');

define('OPRC_OPTIONAL', '(Opcional)');
define('TEXT_ITEM', 'item');
define('TEXT_ITEMS', 'items');
define('MODULE_PAYMENT_AUTHORIZENET_AIM_TEXT_POPUP_CVV_LINK', '<span class="nmx-oprc-cvv">N&#250;mero de 3 d&#237;gitos en el reverso de la tarjeta <br> Amex: N&#250;mero de 4 d&#237;gitos en el frente de la tarjeta</span>');
define('MODULE_PAYMENT_AUTHORIZENET_CIM_TEXT_POPUP_CVV_LINK', MODULE_PAYMENT_AUTHORIZENET_AIM_TEXT_POPUP_CVV_LINK);
define('TEXT_PRIVACY_CONDITIONS_DESCRIPTION_OPRC', 'Al hacer clic en el bot&#243;n de abajo, reconozco que he le&#237;do y acepto la <a target="_blank" href="' . zen_href_link(FILENAME_CONDITIONS, '', 'SSL') . '">t&#233;rminos y condiciones</a>.');

// Cart
define('BUTTON_REMOVE_OPRC_REMOVE_CHECKOUT', 'retirar');

// eof

