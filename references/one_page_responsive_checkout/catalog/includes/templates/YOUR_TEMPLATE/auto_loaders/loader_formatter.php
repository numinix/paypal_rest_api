<?php
/*
 * Loader for formatter.js from http://firstopinion.github.io/formatter.js/demos.html
 */

$loaders[] = array(
    'name' => 'Formatter - Masked Input Helpers',
    'conditions' => array(
        'pages' => array(
            FILENAME_LOGIN,
            FILENAME_CREATE_ACCOUNT,
            FILENAME_ACCOUNT_EDIT,
            FILENAME_NO_ACCOUNT,
            FILENAME_ONE_PAGE_CHECKOUT,
            FILENAME_ADDRESS_BOOK,
            FILENAME_ADDRESS_BOOK_PROCESS,
        ),
    ),
    'jscript_files' => array(
        'jquery/jquery.maskedinput.min.js' => array('order' => 10, 'location' => 'header'),
        'jquery/jquery.phone_formatting.php' => array('order' => 11, 'location' => 'header'),
    ),
);

