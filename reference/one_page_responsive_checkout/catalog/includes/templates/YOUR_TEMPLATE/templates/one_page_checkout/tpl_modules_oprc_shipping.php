<?php 
if ($_SESSION['cart']->count_contents() > 0 && isset($_SESSION['customer_id'])) {  
?>  
  <div id="shippingMethodContainer">
    <h3><?php echo TABLE_HEADING_SHIPPING_METHOD; ?></h3>
    <div class="nmx-box">
      <?php 
        if ($messageStack->size('checkout_shipping') > 0) {
          echo '<div class="disablejAlert">';
          echo $messageStack->output('checkout_shipping');
          echo '</div>';
        } 
      ?> 
      <div id="shippingMethods" class="<?php echo (OPRC_SHOW_SHIPPING_METHOD_GROUP == 'true' ? '' : 'no-shipping-group' ); ?>">
        <?php if (OPRC_AJAX_SHIPPING_QUOTES != 'true') require($template->get_template_dir('tpl_modules_oprc_shipping_quotes.php',DIR_WS_TEMPLATE, $current_page_base,'templates/one_page_checkout'). '/tpl_modules_oprc_shipping_quotes.php'); ?>
      </div>
    </div>
  </div>
<?php 
} 
?>