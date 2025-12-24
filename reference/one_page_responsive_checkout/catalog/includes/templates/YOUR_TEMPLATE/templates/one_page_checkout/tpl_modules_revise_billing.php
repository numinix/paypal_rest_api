 <?php if (isset($_SESSION['billto'])) { ?>
      <?php // ** BEGIN PAYPAL EXPRESS CHECKOUT **
            if (!$payment_modules->in_special_checkout()) {
            // ** END PAYPAL EXPRESS CHECKOUT ** 
      ?>
        <div id="checkoutBillto" class="address-container">
<?php
  $payment_telephone_query = $db->Execute("SELECT entry_telephone FROM " . TABLE_ADDRESS_BOOK . " WHERE address_book_id = " . (int)$_SESSION['billto'] . " AND customers_id = " . (int)$_SESSION['customer_id'] . " LIMIT 1;");
  if ($payment_telephone_query->RecordCount() > 0) {
    $payment_telephone = '<br />' . $payment_telephone_query->fields['entry_telephone'];
  }   
?>
          <h3><?php echo TABLE_HEADING_BILLING_ADDRESS; ?></h3>
          <?php if (!empty($_SESSION['billto'])) { ?>
          <address><?php echo html_entity_decode(zen_address_label($_SESSION['customer_id'], $_SESSION['billto'], true, ' ', '<br />')) . $payment_telephone; ?></address>
          <?php } ?>
        </div>
        
        <?php 
				echo '<div class="nmx-buttons"><a id="linkCheckoutPaymentAddr" href="' . zen_href_link(FILENAME_OPRC_CHECKOUT_BILLING_ADDRESS, '', 'SSL') . ' #checkoutPayAddressDefault' . '">' . BUTTON_CHANGE_ADDRESS_ALT . '</a></div>';
			  ?>

      <?php // ** BEGIN PAYPAL EXPRESS CHECKOUT **
            }
            // ** END PAYPAL EXPRESS CHECKOUT ** 
      ?>
    <?php } ?>