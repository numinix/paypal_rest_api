<?php
/**
 * @package languageDefines
 * @copyright Copyright 2003-2006 Zen Cart Development Team
 * @copyright Portions Copyright 2003 osCommerce
 * @license http://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 * @version $Id: J_Schilz for Integrated COWOA - 14 April 2007
 */

// this is used to display the text link in the "information" or other sidebox
// change address
define('TEXT_ADDRESS_NEW', 'Agregar a la libreta de direcciones?');
define('TEXT_ADDRESS_BOOK_FULL', '<p><strong>Su libreta de direcciones está llena</strong>.</p><p>Usted puede agregar hasta ' . MAX_ADDRESS_BOOK_ENTRIES . ' entradas a su libreta de direcciones. <a href="' . zen_href_link(FILENAME_ADDRESS_BOOK, '', 'NONSSL') . '">Visita la página de la cuenta para modificar tus entradas existentes</a>.</p>');
define('TITLE_PLEASE_SELECT', 'Agregar nueva direcci&#243;n');
define('ENTRY_ADDRESS_TITLE', 'Etiqueta de direcci&#243;n (i.e. trabajo):');
define('ENTRY_TELEPHONE', 'Tel&#233;fono:'); 
define('ENTRY_TELEPHONE_TEXT', '*');
define('ENTRY_POST_CODE', 'C&#243;digo postal:');
define('ENTRY_POST_CODE_ERROR', 'Su c&#243;digo postal debe ser un m&#237;nimo de ' . ENTRY_POSTCODE_MIN_LENGTH . ' caracteres.');
define('ERROR_DELETE_SELECTED_ADDRESS', 'Lo sentimos, no puede eliminar su direcci&#243;n seleccionada actualmente utilizada para facturaci&#243;n o env&#237;o.');
define('ERROR_DELETE_PRIMARY_ADDRESS', 'Lo sentimos, no puedes eliminar tu direcci&#243;n principal actual.');
define('ERROR_DELETE_DEFAULT_ADDRESS', ERROR_DELETE_PRIMARY_ADDRESS);
define('SUCCESS_DELETE_SELECTED_ADDRESS', 'Direcci&#243;n borrada.');
// eof