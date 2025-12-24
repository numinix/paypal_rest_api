<?php
if ($_SESSION['cart']->count_contents() > 0 && isset($_SESSION['customer_id'])) {
  $shippingMessagesHtml = '';
  if ($messageStack->size('checkout_shipping') > 0) {
    ob_start();
    echo '<div class="disablejAlert">';
    echo $messageStack->output('checkout_shipping');
    echo '</div>';
    $shippingMessagesHtml = trim(ob_get_clean());
  }

  $shippingQuotesHtml = '';
  if (OPRC_AJAX_SHIPPING_QUOTES != 'true') {
    ob_start();
    require($template->get_template_dir('tpl_modules_oprc_shipping_quotes.php',DIR_WS_TEMPLATE, $current_page_base,'templates/one_page_checkout'). '/tpl_modules_oprc_shipping_quotes.php');
    $shippingQuotesHtml = trim(ob_get_clean());
  }

  $shippingPanelClasses = 'nmx-box oprc-section-panel oprc-shipping-methods-panel';
  if ($shippingMessagesHtml === '' && $shippingQuotesHtml === '') {
    $shippingPanelClasses .= ' is-empty';
  }
?>
  <div id="shippingMethodContainer">
    <h3><?php echo TABLE_HEADING_SHIPPING_METHOD; ?></h3>
    <div class="<?php echo $shippingPanelClasses; ?>">
      <?php echo $shippingMessagesHtml; ?>
      <div id="shippingMethods" class="<?php echo (OPRC_SHOW_SHIPPING_METHOD_GROUP == 'true' ? '' : 'no-shipping-group'); ?>">
        <?php echo $shippingQuotesHtml; ?>
      </div>
    </div>
  </div>
<?php
}
?>
