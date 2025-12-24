<script type="text/javascript"><!--//
  var oprcAJAXConfirmStatus = '<?php echo OPRC_AJAX_CONFIRMATION_STATUS; ?>';
  var oprcAJAXErrors = '<?php echo OPRC_AJAX_ERRORS; ?>';
  var oprcBlockText = '<?php echo addslashes(OPRC_BLOCK_TEXT); ?>';
  var oprcCatalogFolder = '<?php echo DIR_WS_CATALOG; ?>';
  var oprcChangeAddressPopup = '<?php echo OPRC_CHANGE_ADDRESS_POPUP; ?>';
  var oprcCollapseDiscounts = '<?php echo OPRC_COLLAPSE_DISCOUNTS; ?>';
  var oprcConfirmEmail = '<?php echo OPRC_CONFIRM_EMAIL; ?>';
  var oprcCopyBillingBackgroundColor = '<?php echo OPRC_COPYBILLING_BACKGROUND_COLOR; ?>';
  var oprcCopyBillingOpacity = '<?php echo OPRC_COPYBILLING_OPACITY; ?>';
  var oprcCopyBillingTextColor = '<?php echo OPRC_COPYBILLING_TEXT_COLOR; ?>';
  var oprcEntryEmailAddressErrorExists = '<?php echo ENTRY_EMAIL_ADDRESS_ERROR_EXISTS; ?>';
  var oprcGAEnabled = '<?php echo OPRC_GA_ENABLED; ?>';
  var oprcGAMethod = '<?php echo OPRC_GA_METHOD; ?>';
  var oprcGetContentType = '<?php echo $_SESSION['cart']->get_content_type(); ?>';
  var oprcGuestAccountStatus = '<?php echo OPRC_NOACCOUNT_SWITCH; ?>';
  var oprcGuestAccountOnly = '<?php echo OPRC_NOACCOUNT_ONLY_SWITCH; ?>';
  var oprcGuestFieldType = '<?php echo OPRC_COWOA_FIELD_TYPE; ?>';
  var oprcGuestHideEmail = '<?php echo OPRC_NOACCOUNT_HIDEEMAIL; ?>';
  var oprcGVName = '<?php echo addslashes(TEXT_GV_NAME); ?>';
  var oprcHideRegistration = '<?php echo ($hideRegistration != 'true' || $_GET['hideregistration'] == 'true' ? 'true' : 'false'); ?>';
  var oprcNotRequiredBlockText = '<?php echo addslashes(OPRC_NOT_REQUIRED_BLOCK_TEXT); ?>';
  var oprcMessageBackground = '<?php echo OPRC_MESSAGE_BACKGROUND_COLOR; ?>';
  var oprcMessageOpacity = '<?php echo OPRC_MESSAGE_OPACITY; ?>';
  var oprcMessageOverlayColor = '<?php echo OPRC_MESSAGE_OVERLAY_COLOR; ?>';
  var oprcMessageOverlayOpacity = '<?php echo OPRC_MESSAGE_OVERLAY_OPACITY; ?>';
  var oprcMessageOverlayTextColor = '<?php echo OPRC_MESSAGE_OVERLAY_TEXT_COLOR; ?>';
  var oprcMessageTextColor = '<?php echo OPRC_MESSAGE_TEXT_COLOR; ?>';
  var oprcOnePageStatus = '<?php echo OPRC_ONE_PAGE; ?>';
  var oprcOrderSteps = '<?php echo OPRC_ORDER_STEPS; ?>';
  var oprcPasswordMinLength = '<?php echo ENTRY_PASSWORD_MIN_LENGTH; ?>';
  var oprcProcessingText = '<?php echo addslashes(OPRC_PROCESSING_TEXT); ?>';
  var onePageCheckoutConfirmURL = '<?php echo zen_href_link(FILENAME_OPRC_CONFIRMATION, '', 'SSL'); ?>';
  var onePageCheckoutURL = '<?php echo zen_href_link(FILENAME_ONE_PAGE_CHECKOUT, '', 'SSL'); ?>';
  var oprcRemoveCheckout = '<?php echo OPRC_REMOVE_CHECKOUT; ?>';
  var oprcShippingAddress = '<?php echo OPRC_SHIPPING_ADDRESS; ?>';
  var oprcShippingAddressStatus = 'true';
  var oprcShippingAddressCheck = <?php echo OPRC_SHIPPING_ADDRESS; ?>;
  var oprcShippingInfo = '<?php echo OPRC_SHIPPING_INFO; ?>';
  var oprcTotalOrder = '<?php echo $order->info['total']; ?>';
  var oprcType = '<?php echo $_GET['type']; ?>';
  var oprcZenUserHasGVAccount = '<?php echo zen_user_has_gv_account($_SESSION['customer_id']) ; ?>';
  var oprcRefreshPayment = '<?php echo OPRC_REFRESH_PAYMENT; ?>';
  var oprcLoginValidationErrorMessage = '<?php echo addslashes(OPRC_LOGIN_VALIDATION_ERROR_MESSAGE); ?>';
  var oprcAJaxShippingQuotes = <?php echo OPRC_AJAX_SHIPPING_QUOTES; ?>;
  // BETA, we may need to get rid of the session data if this starts causing issues.  This code did resolve issues for sites that had session issues.
  var ajaxLoginCheckURL = 'ajax/login_check.php<?php if (SESSION_RECREATE != 'True') echo '?' . zen_session_name() . '=' . zen_session_id(); ?>';
  var ajaxOrderTotalURL = 'ajax/order_total.php<?php if (SESSION_RECREATE != 'True') echo '?' . zen_session_name() . '=' . zen_session_id(); ?>';
  var ajaxAccountCheckURL = 'ajax/account_check.php<?php if (SESSION_RECREATE != 'True') echo '?' . zen_session_name() . '=' . zen_session_id(); ?>';
  var ajaxDeleteAddressURL = 'ajax/delete_address.php<?php if (SESSION_RECREATE != 'True') echo '?' . zen_session_name() . '=' . zen_session_id(); ?>';
  var ajaxShippingQuotesURL = 'ajax/shipping_quotes.php<?php if (SESSION_RECREATE != 'True') echo '?' . zen_session_name() . '=' . zen_session_id(); ?>';

  var recalculate_shipping_cost = '<?php echo $recalculate_shipping_cost; ?>'; //if cart contents have changed, the order total box needs an extra refresh to update.

  <?php
    $countries = $db->execute('SELECT countries_id, phone_format from ' . TABLE_COUNTRIES);
    $country_phone_formats = array();

    while(!$countries->EOF) {
     $country_phone_formats[$countries->fields['countries_id']] = $countries->fields['phone_format'];
     if(strlen($countries->fields['phone_format']) == 0) {
       $country_phone_formats[$countries->fields['countries_id']] = "99999999999999"; //clear mask, this country doesn't have one.
     }

     $countries->MoveNext();
   }
?>
   var country_phone_formats = JSON.parse('<?php echo json_encode($country_phone_formats); ?>');
  var oprcAddressMissing = '<?php echo (!$_SESSION['customer_default_address_id'] > 0) ? 'true' : 'false'; ?>';



  <?php 
    switch(OPRC_CHECKOUT_SHOPPING_CART_DISPLAY_DEFAULT) {
      case 'partially expanded':
        echo "var hideProducts = false;\n
              var expandProducts = false;\n";       
        break;
      case 'fully expanded':
        echo "var hideProducts = false;\n
              var expandProducts = true;\n";
        break; 
      case 'collapsed':
      default:
        echo "var hideProducts = true;\n
              var expandProducts = false;\n";
        break;                    
    }
  ?>
  function oprcRemoveCheckoutCallback() {
  <?php
    if (OPRC_REMOVE_CHECKOUT_REMOVE_CALLBACK != '') {
      echo "\n" . OPRC_REMOVE_CHECKOUT_REMOVE_CALLBACK . "\n";
    }
  ?>
  }
  function oprcChangeAddressCallback() {
  <?php 
    if (OPRC_CHANGE_ADDRESS_CALLBACK != '') {
      echo "\n" . OPRC_CHANGE_ADDRESS_CALLBACK . "\n";
    } 
  ?>
  }
  
  function oprcRemoveCheckoutRefreshSelectors(data) {
  <?php 
    if (OPRC_REMOVE_CHECKOUT_REFRESH_SELECTORS != '') {
      $refresh_selectors = explode(',', OPRC_REMOVE_CHECKOUT_REFRESH_SELECTORS);
      foreach ($refresh_selectors as $refresh_selector) {
  ?>
    jQuery('<?php echo trim($refresh_selector); ?>').html(jQuery(data).find('<?php echo trim($refresh_selector); ?>').html());
  <?php 
      } 
  ?>
    oprcRemoveCheckoutCallback();  
  <?php
    } 
  ?>
  }
  
  function oprcLoginRegistrationRefreshSelectors(data) {
  <?php 
    if (OPRC_CHECKOUT_LOGIN_REGISTRATION_REFRESH_SELECTORS != '') {
      $refresh_selectors = explode(',', OPRC_CHECKOUT_LOGIN_REGISTRATION_REFRESH_SELECTORS);
      foreach ($refresh_selectors as $refresh_selector) {
  ?>
    jQuery('<?php echo trim($refresh_selector); ?>').html(jQuery(data).find('<?php echo trim($refresh_selector); ?>').html()); 
  <?php
      }
    } 
  ?>      
  }
  
  function oprcCheckoutSubmitCallback() {
    <?php
      if (OPRC_CHECKOUT_SUBMIT_CALLBACK != '') {
        echo "\n" . OPRC_CHECKOUT_SUBMIT_CALLBACK . "\n";
      }
    ?>
  }

  
  if(oprcCollapseDiscounts == 'true') {
    document.addEventListener('DOMContentLoaded', function() {
      jQuery(document).on('click', '.discount h3', function() {
          couponAccordion(jQuery(this));
      });
    });
  }

  if(oprcShippingInfo == 'true') {
    document.addEventListener('DOMContentLoaded', function() {
      jQuery(document).on('click', '.shipping-method h4', function(event) {
        var el = jQuery(this);
        if( el.next().hasClass( 'information' ) ) {
          el.toggleClass( 'is-open' );
          el.next().slideToggle();
        }
      });
    });
  }

  function couponAccordion(el) {
    <?php if(OPRC_COLLAPSE_DISCOUNTS == 'true'){ ?>
      if (el.hasClass('is-clickable')) {
        el.toggleClass( 'is-open' );
        el.next().slideToggle();
      }
    <?php } ?>
  }
//--></script>