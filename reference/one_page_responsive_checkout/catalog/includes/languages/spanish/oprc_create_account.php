<?php
// greeting salutation
define('EMAIL_SUBJECT', 'Bienvenido a ' . STORE_NAME);
define('EMAIL_GREET_MR', 'Estimado %s,' . "\n\n");
define('EMAIL_GREET_MS', 'Querida Ms. %s,' . "\n\n");
define('EMAIL_GREET_NONE', 'Estimado %s' . "\n\n");

// First line of the greeting
define('EMAIL_WELCOME', 'Deseamos darle la bienvenida a <strong>' . STORE_NAME . '</strong>.');
define('EMAIL_SEPARATOR', '--------------------');
define('EMAIL_COUPON_INCENTIVE_HEADER', '&#161;Felicidades&#33; Para hacer de su pr&#243;xima visita a nuestra tienda una experiencia m&aacute;s gratificante, a continuaci&#243;n se detallan los detalles de un Cup&#243;n de descuento creado solo para usted.&#33;' . "\n\n");
// your Discount Coupon Description will be inserted before this next define
define('EMAIL_COUPON_REDEEM', 'Para usar el cup&#243;n de descuento, ingrese el ' . TEXT_GV_REDEEM . ' c&#243;digo durante el pago: <strong>%s</strong>' . "\n\n");

define('EMAIL_GV_INCENTIVE_HEADER', 'Solo por pasarte hoy, te hemos enviado un ' . TEXT_GV_NAME . ' para %s&#33;' . "\n");
define('EMAIL_GV_REDEEM', 'O ' . TEXT_GV_NAME . ' ' . TEXT_GV_REDEEM . ' es: %s ' . "\n\n" . 'Puedes ingresar al ' . TEXT_GV_REDEEM . ' el pago, despu&#233;s de hacer sus selecciones en la tienda. ');
define('EMAIL_GV_LINK', ' O bien, puede canjearlo ahora siguiendo este enlace: ' . "\n");
// GV link will automatically be included before this line

define('EMAIL_GV_LINK_OTHER','Una vez que haya agregado el ' . TEXT_GV_NAME . ' a su cuenta, puede utilizar el ' . TEXT_GV_NAME . ' por ti mismo, o env&#237;alo a un amigo&#33;' . "\n\n");

define('EMAIL_TEXT', 'Con tu cuenta, ahora puedes participar en el <strong>varios servicios</strong> tenemos que ofrecerte. Algunos de estos servicios incluyen:' . "\n\n" . '<li><strong>Carro Permanente</strong> - Todos los productos que se agreguen a su carrito en l&#237;nea permanecer&aacute;n all&#237; hasta que los elimine o los revise.' . "\n\n" . '<li><strong>Direcciones</strong> - Podemos entregar sus productos a otra direcci&#243;n que no sea la suya. Esto es perfecto para enviar regalos de cumplea&#241;os directamente a la persona de cumplea&#241;os.' . "\n\n" . '<li><strong>Historial de pedidos</strong> - Vea su historial de compras que ha realizado con nosotros.' . "\n\n" . '<li><strong>Revisiones de productos</strong> - Comparte tus opiniones sobre productos con nuestros otros clientes.' . "\n\n");
define('EMAIL_CONTACT', 'Para obtener ayuda con cualquiera de nuestros servicios en l&#237;nea, env&#237;e un correo electr&#243;nico al propietario de la tienda: <a href="mailto:' . STORE_OWNER_EMAIL_ADDRESS . '">'. STORE_OWNER_EMAIL_ADDRESS ." </a>\n\n");
define('EMAIL_GV_CLOSURE','Sinceramente,' . "\n\n" . STORE_OWNER . "\nStore Owner\n\n". '<a href="' . HTTP_SERVER . DIR_WS_CATALOG . '">'.HTTP_SERVER . DIR_WS_CATALOG ."</a>\n\n");

define('ENTRY_SECURITY_CHECK', 'Control de seguridad:');
define('ENTRY_SECURITY_CHECK_ERROR', 'El c&#243;digo de control de seguridad no se escribi&#243; correctamente. Int&#233;ntalo de nuevo.');

define('ENTRY_STATE_ERROR_INPUT', 'Por favor proporcione un nombre de estado/provincia v&aacute;lido.'); 

// email disclaimer - this disclaimer is separate from all other email disclaimers
define('EMAIL_DISCLAIMER_NEW_CUSTOMER', 'Esta direcci&#243;n de correo electr&#243;nico nos la proporcion&#243; usted o uno de nuestros clientes. Si no se registr&#243; para una cuenta, o cree que ha recibido este correo electr&#243;nico por error, env&#237;e un correo electr&#243;nico a %s ');
// eof