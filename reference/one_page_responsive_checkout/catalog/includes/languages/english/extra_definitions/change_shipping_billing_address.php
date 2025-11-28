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
define('TEXT_ADDRESS_NEW', 'Add to address book?');
define('TEXT_ADDRESS_BOOK_FULL', '<p><strong>Your address book is full</strong>.</p><p>You may add up to ' . MAX_ADDRESS_BOOK_ENTRIES . ' entries to your address book.  <a href="' . zen_href_link(FILENAME_ADDRESS_BOOK, '', 'NONSSL') . '">Visit the account page to modify your existing entries</a>.</p>');
define('TITLE_PLEASE_SELECT', 'Add New Address');
define('ENTRY_ADDRESS_TITLE', 'Address Label (i.e. work):');
define('ENTRY_TELEPHONE', 'Phone:'); 
define('ENTRY_TELEPHONE_TEXT', '*');
define('ENTRY_POST_CODE', 'Post/zip code:');
define('ENTRY_POST_CODE_ERROR', 'Your post/zip code must be a minimum of ' . ENTRY_POSTCODE_MIN_LENGTH . ' characters.');
define('ERROR_DELETE_SELECTED_ADDRESS', 'Sorry, you cannot remove your currently selected address used for billing or shipping.');
define('ERROR_DELETE_PRIMARY_ADDRESS', 'Sorry, you cannot remove your current primary address.');
define('ERROR_DELETE_DEFAULT_ADDRESS', ERROR_DELETE_PRIMARY_ADDRESS);
define('SUCCESS_DELETE_SELECTED_ADDRESS', 'Address deleted.');
// eof