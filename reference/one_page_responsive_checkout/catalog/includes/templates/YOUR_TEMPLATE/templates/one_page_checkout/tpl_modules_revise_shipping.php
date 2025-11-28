<?php
  $shipping_telephone_query = $db->Execute("SELECT entry_telephone FROM " . TABLE_ADDRESS_BOOK . " WHERE address_book_id = " . (int)$_SESSION['sendto'] . " AND customers_id = " . (int)$_SESSION['customer_id'] . " LIMIT 1;");
  if ($shipping_telephone_query->RecordCount() > 0) {
    $shipping_telephone = '<br />' . $shipping_telephone_query->fields['entry_telephone'];
  }   
?>
  <div class="address-container">
    <h3><?php echo OPRC_FORCE_SHIPPING_ADDRESS_TO_BILLING == 'false' ? TABLE_HEADING_SHIPPING_ADDRESS : ((OPRC_SHIPPING_ADDRESS == 'false' || $_SESSION['cart']->get_content_type() == 'virtual') ? HEADING_STEP_2_NO_SHIPPING : HEADING_STEP_2); ?></h3>
    
    <?php if (!empty($_SESSION['sendto'])) { ?>
    <address class="checkoutAddress">
      <?php echo html_entity_decode(zen_address_label($_SESSION['customer_id'], $_SESSION['sendto'], true, ' ', '<br />')) . $shipping_telephone; ?>
    </address>
    <?php } ?>
  </div>
  <?php 
  echo '<div class="nmx-buttons"><a id="linkCheckoutShippingAddr" href="' . zen_href_link(FILENAME_OPRC_CHECKOUT_SHIPPING_ADDRESS, '', 'SSL') . ' #checkoutShipAddressDefault' . '">' . BUTTON_CHANGE_ADDRESS_ALT . '</a></div>';
	?>