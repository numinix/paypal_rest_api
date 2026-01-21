<?php
/**
 * @package languageDefines
 * @copyright Copyright 2003-2007 Zen Cart Development Team
 * @copyright Portions Copyright 2003 osCommerce
 * @license http://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 * @version $Id: credit_cards.php 6119 2007-04-05 08:20:16Z drbyte $
 */
/*

The credit card define statements match the actual records in the configuration table.

For example for Visa:
TEXT_CC_ENABLED_VISA or IMAGE_CC_ENABLED_VISA is used for CC_ENABLED_VISA that is stored in the configuration table

If there is a new credit card added but there is not a matching define it cannot be used by the function zen_get_cc_enabled()

To obtain a list of accepted credit cards use the function zen_get_cc_enabled()

Example:

echo TEXT_ACCEPTED_CREDIT_CARDS . zen_get_cc_enabled();

*/
global $template, $current_page_base;
$define = [
    'TEXT_ACCEPTED_CREDIT_CARDS' => '<strong>We accept:</strong> ',

    // cc enabled text
    'TEXT_CC_ENABLED_VISA' => 'Visa',
    'TEXT_CC_ENABLED_MC' => 'MC',
    'TEXT_CC_ENABLED_AMEX' => 'AmEx',
    'TEXT_CC_ENABLED_DINERS_CLUB' => 'Diners Club',
    'TEXT_CC_ENABLED_DISCOVER' => 'Discover',
    'TEXT_CC_ENABLED_JCB' => 'JCB',
    'TEXT_CC_ENABLED_AUSTRALIAN_BANKCARD' => 'Australian Bankcard',
    'TEXT_CC_ENABLED_SOLO' => 'Solo',
    'TEXT_CC_ENABLED_SWITCH' => 'Switch',
    'TEXT_CC_ENABLED_MAESTRO' => 'Maestro',

    // for images define these as:
    // 'IMAGE_CC_ENABLED_VISA',zen_image(DIR_WS_IMAGES . 'filename.jpg');
    // use the function
    // echo zen_get_cc_enabled('IMAGE_');

    // cc enabled image
    'IMAGE_CC_ENABLED_VISA' => zen_image($template->get_template_dir('cc1.png', DIR_WS_TEMPLATE, $current_page_base,'images/icons'). '/' . 'cc1.png'),
    'IMAGE_CC_ENABLED_MC' => zen_image($template->get_template_dir('cc2.png', DIR_WS_TEMPLATE, $current_page_base,'images/icons'). '/' . 'cc2.png'),
    'IMAGE_CC_ENABLED_AMEX' => zen_image($template->get_template_dir('cc3.png', DIR_WS_TEMPLATE, $current_page_base,'images/icons'). '/' . 'cc3.png'),
    'IMAGE_CC_ENABLED_DINERS_CLUB' => zen_image($template->get_template_dir('cc4.png', DIR_WS_TEMPLATE, $current_page_base,'images/icons'). '/' . 'cc4.png'),
    'IMAGE_CC_ENABLED_DISCOVER' => zen_image($template->get_template_dir('cc5.png', DIR_WS_TEMPLATE, $current_page_base,'images/icons'). '/' . 'cc5.png'),
    'IMAGE_CC_ENABLED_JCB' => zen_image($template->get_template_dir('cc6.png', DIR_WS_TEMPLATE, $current_page_base,'images/icons'). '/' . 'cc6.png'),
    'IMAGE_CC_ENABLED_AUSTRALIAN_BANKCARD' => zen_image($template->get_template_dir('cc7.png', DIR_WS_TEMPLATE, $current_page_base,'images/icons'). '/' . 'cc7.png'),
    'IMAGE_CC_ENABLED_SOLO' => zen_image($template->get_template_dir('cc8.png', DIR_WS_TEMPLATE, $current_page_base,'images/icons'). '/' . 'cc8.png'),
    'IMAGE_CC_ENABLED_SWITCH' => zen_image($template->get_template_dir('cc9.png', DIR_WS_TEMPLATE, $current_page_base,'images/icons'). '/' . 'cc9.png'),
    'IMAGE_CC_ENABLED_MAESTRO' => zen_image($template->get_template_dir('cc10.png', DIR_WS_TEMPLATE, $current_page_base,'images/icons'). '/' . 'cc10.png'),
];

$zc158 = (PROJECT_VERSION_MAJOR > 1 || (PROJECT_VERSION_MAJOR == 1 && substr(PROJECT_VERSION_MINOR, 0, 3) >= '5.8'));
if ($zc158) {
    return $define;
} else {
    nmx_create_defines($define);
}
?>