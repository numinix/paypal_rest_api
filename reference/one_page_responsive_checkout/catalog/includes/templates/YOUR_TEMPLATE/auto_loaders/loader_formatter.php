<?php
/*
* Loader for formatter.js from http://firstopinion.github.io/formatter.js/demos.html
*/  
 $loaders[] = array('conditions' => array('pages' => array(FILENAME_LOGIN, FILENAME_CREATE_ACCOUNT, FILENAME_ACCOUNT_EDIT,FILENAME_NO_ACCOUNT, FILENAME_ONE_PAGE_CHECKOUT, FILENAME_ADDRESS_BOOK, FILENAME_ADDRESS_BOOK_PROCESS)),
		'jscript_files' => array(
			'jquery/jquery.maskedinput.min.js' => 10,
                        'jquery/jquery.phone_formatting.php' => 11
		)
);
