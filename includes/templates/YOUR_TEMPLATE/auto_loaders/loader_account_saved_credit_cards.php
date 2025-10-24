<?php
/**
 * @package Pages
 * @copyright Copyright 2008-2010 RubikIntegration.com
 * @author yellow1912
 * @license http://www.zen-cart.com/license/2_0.txt GNU Public License V2.
 */

/**
 * NOTE: You can use php files for both javascript and css.
 *
 * Global variables must be declared global as they are referenced inside the loader class
 *
 * They must be coded like so:
 * Javascript:
 * <script language="javascript" type="text/javascript">
 * <?php // php code goes here ?>
 * </script>
 *
 * CSS:s
 * <style type="text/css">
 * <?php // php code goes here ?>
 * </style>
 */

$loaders[] = array('conditions' => array('pages' => array(FILENAME_ACCOUNT_SAVED_CREDIT_CARDS)),
		'jscript_files' => array(
		'jquery/jquery-1.12.0.min.js' => 1,
		'jquery/jquery-migrate-1.3.0.min.js' => 2,
		'jquery/jq-form.validator.min.js' => 4,
		'jquery/jq-credit.card.validator.min.js' => 5,
		'jquery/js-masker.min.js' => 6,
		'jquery/jq-saved.cards.js' => 7,
		'jquery/jquery_addr_pulldowns.php' => 8
	),
	'css_files' => array(
		'account_saved_credit_cards.css' => 99,
		'auto_loaders/ascc_overrides.css' => 100
	)
);