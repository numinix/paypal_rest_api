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
$define = [
    'TEXT_ADDRESS_NEW' => 'Add to address book?',
    'TEXT_ADDRESS_BOOK_FULL' => '<p><strong>Your address book is full</strong>.</p><p>You may add up to ' . MAX_ADDRESS_BOOK_ENTRIES . ' entries to your address book.  <a href="' . zen_href_link(FILENAME_ADDRESS_BOOK, '', 'NONSSL') . '">Visit the account page to modify your existing entries</a>.</p>',
    'TITLE_PLEASE_SELECT' => 'Add New Address',
    'ENTRY_ADDRESS_TITLE' => 'Address Label (i.e. work):',
    'ENTRY_TELEPHONE' => 'Phone:',
    'ENTRY_TELEPHONE_TEXT' => '*',
    'ENTRY_POST_CODE' => 'Postal/Zip Code:',
    'ENTRY_POST_CODE_ERROR' => 'Please enter a valid postal/zip code, it must be a minimum of ' . ENTRY_POSTCODE_MIN_LENGTH . ' characters.',
    'ERROR_DELETE_SELECTED_ADDRESS' => 'Sorry, you cannot remove your currently selected address used for billing or shipping.',
    'ERROR_DELETE_PRIMARY_ADDRESS' => 'Sorry, you cannot remove your current primary address.',
    'SUCCESS_DELETE_SELECTED_ADDRESS' => 'Address deleted.'
];

$define ['ERROR_DELETE_DEFAULT_ADDRESS'] = $define['ERROR_DELETE_PRIMARY_ADDRESS'];

$zc158 = (PROJECT_VERSION_MAJOR > 1 || (PROJECT_VERSION_MAJOR == 1 && substr(PROJECT_VERSION_MINOR, 0, 3) >= '5.8'));
if ($zc158) {
    return $define;
} else {
    nmx_create_defines($define);
}
// eof