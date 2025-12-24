<?php
/**
 * Page Template
 *
 * Loaded automatically by index.php?main_page=create_account.<br />
 * Displays Create Account form.
 *
 * @package templateSystem
 * @copyright Copyright 2003-2007 Zen Cart Development Team
 * @copyright Portions Copyright 2003 osCommerce
 * @license http://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 * @version $Id: J_Schilz for Integrated COWOA - 14 April 2007
 */
?>
<div class="centerColumn nmx-wrapper nmx nmx-plugin nmx-plugin--oprc nmx-cf" id="onePageCheckout">
  <div id="onePageCheckoutContent" class="">
    <h1 id="secureCheckout"><?php echo HEADING_TITLE; ?></h1>
<?php 
    if (OPRC_ORDER_STEPS == 'true') require($template->get_template_dir('tpl_modules_oprc_order_steps.php',DIR_WS_TEMPLATE, $current_page_base,'templates/one_page_checkout'). '/tpl_modules_oprc_order_steps.php');  
?>

<?php
  if ($down_for_maintenance == true) {
?>
    <div class="forward"><?php echo zen_image(DIR_WS_TEMPLATE_IMAGES . OTHER_IMAGE_DOWN_FOR_MAINTENANCE, OTHER_DOWN_FOR_MAINTENANCE_ALT); ?></div>
    <h2 id="maintenanceDefaultMainContent"><?php echo OPRC_DOWN_FOR_MAINTENANCE_TEXT_INFORMATION; ?></h2>
    <br class="clearBoth" />
    <div class="buttonRow forward"><?php echo OPRC_DOWN_FOR_MAINTENANCE_STATUS_TEXT; ?></div>
    <br class="clearBoth" />
    <div class="buttonRow forward"><a href="<?php echo zen_href_link(FILENAME_ONE_PAGE_CHECKOUT); ?>"><?php echo zen_image_button(BUTTON_IMAGE_CONTINUE, BUTTON_CONTINUE_ALT); ?></a></div>
    <br class="clearBoth" />
<?php
  } else { 
?>
    <div class="nmx-cf stickem-container">
      <div id="oprcLeft">
        <div id="messageStackErrors"><?php if ($messageStack->size('checkout') > 0) echo $messageStack->output('checkout'); ?><?php if ($messageStack->size('one_page_checkout') > 0) echo $messageStack->output('one_page_checkout'); ?> <?php if ($messageStack->size('no_account') > 0) echo $messageStack->output('no_account'); ?></div>
        <?php 
          if (isset($_SESSION['customer_id'])) {
            echo zen_draw_form('checkout_payment', zen_href_link(FILENAME_ONE_PAGE_CONFIRMATION, 'oprcaction=process', 'SSL'), 'post', 'id="checkout_payment"');
            require($template->get_template_dir('tpl_modules_oprc_step_3.php',DIR_WS_TEMPLATE, $current_page_base,'templates/one_page_checkout'). '/tpl_modules_oprc_step_3.php');
            echo '</form>';
          } else {
            $hideRegistration = true; // modified for FEAC 3
            require($template->get_template_dir('tpl_modules_oprc_step_1.php',DIR_WS_TEMPLATE, $current_page_base,'templates/one_page_checkout'). '/tpl_modules_oprc_step_1.php');
            require($template->get_template_dir('tpl_modules_oprc_step_2.php',DIR_WS_TEMPLATE, $current_page_base,'templates/one_page_checkout'). '/tpl_modules_oprc_step_2.php');
          }
        ?>
      </div>
      <?php require($template->get_template_dir('tpl_modules_oprc_right_column.php',DIR_WS_TEMPLATE, $current_page_base,'templates/one_page_checkout'). '/tpl_modules_oprc_right_column.php'); ?>
    </div>	
  </div>
</div>
<?php //if (isset($_REQUEST['request']) && $_REQUEST['request'] == 'ajax') exit(); ?>

<?php 
  require($template->get_template_dir('tpl_modules_oprc_confidence.php',DIR_WS_TEMPLATE, $current_page_base,'templates/one_page_checkout'). '/tpl_modules_oprc_confidence.php'); 
?>

<?php
    if (OPRC_QUICK_CHECKOUT_UPSELL_STATUS == 'true') {
      require($template->get_template_dir('tpl_modules_oprc_upsell.php',DIR_WS_TEMPLATE, $current_page_base,'templates/one_page_checkout'). '/tpl_modules_oprc_upsell.php');
    }
  } 
?> 