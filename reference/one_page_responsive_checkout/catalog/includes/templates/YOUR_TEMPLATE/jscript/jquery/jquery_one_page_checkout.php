<?php
$oprcAddressLookupManager = function_exists('oprc_address_lookup_manager')
    ? oprc_address_lookup_manager()
    : null;
$oprcAddressLookupEnabled = (is_object($oprcAddressLookupManager)
    && method_exists($oprcAddressLookupManager, 'isEnabled')
    && $oprcAddressLookupManager->isEnabled());

$oprcAddressLookupUrl = defined('FILENAME_OPRC_ADDRESS_LOOKUP')
    ? FILENAME_OPRC_ADDRESS_LOOKUP
    : 'ajax/oprc_address_lookup.php';

$oprcAddressLookupMessages = [
    'heading' => defined('TEXT_OPRC_ADDRESS_LOOKUP_HEADING') ? TEXT_OPRC_ADDRESS_LOOKUP_HEADING : '',
    'placeholder' => defined('TEXT_OPRC_ADDRESS_LOOKUP_PLACEHOLDER') ? TEXT_OPRC_ADDRESS_LOOKUP_PLACEHOLDER : '',
    'loading' => defined('TEXT_OPRC_ADDRESS_LOOKUP_LOADING') ? TEXT_OPRC_ADDRESS_LOOKUP_LOADING : '',
    'noResults' => defined('TEXT_OPRC_ADDRESS_LOOKUP_NO_RESULTS') ? TEXT_OPRC_ADDRESS_LOOKUP_NO_RESULTS : '',
    'error' => defined('TEXT_OPRC_ADDRESS_LOOKUP_ERROR') ? TEXT_OPRC_ADDRESS_LOOKUP_ERROR : '',
    'applied' => defined('TEXT_OPRC_ADDRESS_LOOKUP_APPLIED') ? TEXT_OPRC_ADDRESS_LOOKUP_APPLIED : '',
    'provider' => defined('TEXT_OPRC_ADDRESS_LOOKUP_PROVIDER') ? TEXT_OPRC_ADDRESS_LOOKUP_PROVIDER : '',
    'missingPostalCode' => defined('TEXT_OPRC_ADDRESS_LOOKUP_MISSING_POSTCODE') ? TEXT_OPRC_ADDRESS_LOOKUP_MISSING_POSTCODE : '',
    'unavailable' => defined('TEXT_OPRC_ADDRESS_LOOKUP_UNAVAILABLE') ? TEXT_OPRC_ADDRESS_LOOKUP_UNAVAILABLE : '',
    'label' => defined('TEXT_OPRC_ADDRESS_LOOKUP_LABEL') ? TEXT_OPRC_ADDRESS_LOOKUP_LABEL : '',
];
?>
<script type="text/javascript"><!--//
  var oprcAJAXConfirmStatus = '<?php echo OPRC_AJAX_CONFIRMATION_STATUS; ?>';
  var oprcAJAXErrors = '<?php echo OPRC_AJAX_ERRORS; ?>';
  var oprcBlockText = '<?php echo addslashes(OPRC_BLOCK_TEXT); ?>';
  var oprcCatalogFolder = '<?php echo DIR_WS_CATALOG; ?>';
  var oprcCollapseDiscounts = '<?php echo OPRC_COLLAPSE_DISCOUNTS; ?>';
  var oprcConfirmEmail = '<?php echo OPRC_CONFIRM_EMAIL; ?>';
  var oprcCopyBillingBackgroundColor = '<?php echo OPRC_COPYBILLING_BACKGROUND_COLOR; ?>';
  var oprcCopyBillingOpacity = '<?php echo OPRC_COPYBILLING_OPACITY; ?>';
  var oprcCopyBillingTextColor = '<?php echo OPRC_COPYBILLING_TEXT_COLOR; ?>';
  var oprcConfirmationLoadErrorMessage = '<?php echo addslashes(defined('OPRC_CONFIRMATION_LOAD_ERROR_MESSAGE') ? OPRC_CONFIRMATION_LOAD_ERROR_MESSAGE : 'We were unable to load the confirmation step. Please refresh the page and try again.'); ?>';
  var oprcChangeAddressLoadErrorMessage = '<?php echo addslashes(defined('OPRC_CHANGE_ADDRESS_LOAD_ERROR_MESSAGE') ? OPRC_CHANGE_ADDRESS_LOAD_ERROR_MESSAGE : 'We were unable to load the address form. Please close this window and try again.'); ?>';
  var oprcEntryEmailAddressErrorExists = '<?php echo addslashes(defined('OPRC_ENTRY_EMAIL_ADDRESS_ERROR_EXISTS') ? OPRC_ENTRY_EMAIL_ADDRESS_ERROR_EXISTS : (defined('ENTRY_EMAIL_ADDRESS_ERROR_EXISTS') ? ENTRY_EMAIL_ADDRESS_ERROR_EXISTS : '')); ?>';
  var oprcGetContentType = '<?php echo $_SESSION['cart']->get_content_type(); ?>';
  var oprcGuestAccountStatus = '<?php echo OPRC_NOACCOUNT_SWITCH; ?>';
  var oprcGuestAccountOnly = '<?php echo OPRC_NOACCOUNT_ONLY_SWITCH; ?>';
  var oprcGuestFieldType = '<?php echo OPRC_COWOA_FIELD_TYPE; ?>';
  var oprcGuestHideEmail = '<?php echo OPRC_NOACCOUNT_HIDEEMAIL; ?>';
  var oprcGVName = '<?php echo addslashes(TEXT_GV_NAME); ?>';
  var oprcHideRegistration = '<?php echo (!isset($hideRegistration) || $hideRegistration != 'true' || (isset($_GET['hideregistration']) && $_GET['hideregistration'] == 'true') ? 'true' : 'false'); ?>';
  var oprcNotRequiredBlockText = '<?php echo addslashes(OPRC_NOT_REQUIRED_BLOCK_TEXT); ?>';
  var oprcNoShippingAvailableMessage = '<?php echo addslashes(defined('TEXT_NO_SHIPPING_AVAILABLE') ? TEXT_NO_SHIPPING_AVAILABLE : ''); ?>';
  var oprcOnePageStatus = '<?php echo OPRC_ONE_PAGE; ?>';
  var oprcOrderSteps = '<?php echo OPRC_ORDER_STEPS; ?>';
  var oprcEmailMinLength = '<?php echo ENTRY_EMAIL_ADDRESS_MIN_LENGTH; ?>';
  var oprcPasswordMinLength = '<?php echo ENTRY_PASSWORD_MIN_LENGTH; ?>';
  var oprcProcessingText = '<?php echo addslashes(OPRC_PROCESSING_TEXT); ?>';
  var onePageCheckoutConfirmURL = '<?php echo zen_href_link(FILENAME_OPRC_CONFIRMATION, '', 'SSL'); ?>';
  var onePageCheckoutURL = '<?php echo zen_href_link(FILENAME_ONE_PAGE_CHECKOUT, '', 'SSL'); ?>';
  var oprcRemoveCheckout = '<?php echo OPRC_REMOVE_CHECKOUT; ?>';
  var oprcShippingAddress = '<?php echo OPRC_SHIPPING_ADDRESS; ?>';
  var oprcShippingAddressStatus = 'true';
  var oprcShippingAddressCheck = <?php echo OPRC_SHIPPING_ADDRESS; ?>;
  var oprcShippingInfo = '<?php echo OPRC_SHIPPING_INFO; ?>';
  var oprcTotalOrder = '<?php echo (isset($order->info['total']) ? $order->info['total'] : 0); ?>';
  var oprcType = '<?php echo (isset($_GET['type']) ? $_GET['type'] : ''); ?>';
  var oprcZenUserHasGVAccount = '<?php echo (isset($_SESSION['customer_id']) ? zen_user_has_gv_account((int)$_SESSION['customer_id']) : '0.00') ; ?>';
  var oprcRefreshPayment = '<?php echo OPRC_REFRESH_PAYMENT; ?>';
  var oprcLoginValidationErrorMessage = '<?php echo (defined('OPRC_LOGIN_VALIDATION_ERROR_MESSAGE') ? addslashes(OPRC_LOGIN_VALIDATION_ERROR_MESSAGE) : ''); ?>';
  var oprcAJaxShippingQuotes = <?php echo OPRC_AJAX_SHIPPING_QUOTES; ?>;
  // BETA, we may need to get rid of the session data if this starts causing issues.  This code did resolve issues for sites that had session issues.
  var ajaxLoginCheckURL = 'ajax/login_check.php<?php if (SESSION_RECREATE != 'True') echo '?' . zen_session_name() . '=' . zen_session_id(); ?>';
  var ajaxOrderTotalURL = 'ajax/order_total.php<?php if (SESSION_RECREATE != 'True') echo '?' . zen_session_name() . '=' . zen_session_id(); ?>';
  var ajaxAccountCheckURL = 'ajax/account_check.php<?php if (SESSION_RECREATE != 'True') echo '?' . zen_session_name() . '=' . zen_session_id(); ?>';
  var ajaxDeleteAddressURL = 'ajax/delete_address.php<?php if (SESSION_RECREATE != 'True') echo '?' . zen_session_name() . '=' . zen_session_id(); ?>';
  var ajaxChangeAddressURL = 'ajax/oprc_change_address.php<?php if (SESSION_RECREATE != 'True') echo '?' . zen_session_name() . '=' . zen_session_id(); ?>';
  var ajaxShippingQuotesURL = 'ajax/shipping_quotes.php<?php if (SESSION_RECREATE != 'True') echo '?' . zen_session_name() . '=' . zen_session_id(); ?>';
  var ajaxUpdateShippingMethodURL = 'ajax/oprc_update_shipping_method.php<?php if (SESSION_RECREATE != 'True') echo '?' . zen_session_name() . '=' . zen_session_id(); ?>';
  var ajaxUpdateCreditURL = 'ajax/oprc_update_credit.php<?php if (SESSION_RECREATE != 'True') echo '?' . zen_session_name() . '=' . zen_session_id(); ?>';
  var ajaxRemoveProductURL = 'ajax/oprc_remove_product.php<?php if (SESSION_RECREATE != 'True') echo '?' . zen_session_name() . '=' . zen_session_id(); ?>';
  <?php
    $isOnePageEnabled = (defined('OPRC_ONE_PAGE') && OPRC_ONE_PAGE === 'true');
    $checkoutProcessTarget = $isOnePageEnabled ? FILENAME_OPRC_CHECKOUT_PROCESS : FILENAME_ONE_PAGE_CONFIRMATION;
    $checkoutProcessParams = $isOnePageEnabled ? 'request=ajax' : '';

    $rawAjaxCheckoutProcessUrl = zen_href_link($checkoutProcessTarget, $checkoutProcessParams, 'SSL');
    $decodedAjaxCheckoutProcessUrl = html_entity_decode(
        $rawAjaxCheckoutProcessUrl,
        ENT_QUOTES,
        defined('CHARSET') ? CHARSET : 'UTF-8'
    );
  ?>
  var ajaxCheckoutProcessURL = <?php echo json_encode($decodedAjaxCheckoutProcessUrl, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
  var ajaxAddressLookupURL = '<?php echo $oprcAddressLookupUrl; ?><?php if (SESSION_RECREATE != 'True') echo '?' . zen_session_name() . '=' . zen_session_id(); ?>';
  var oprcAddressLookupEnabled = '<?php echo ($oprcAddressLookupEnabled ? 'true' : 'false'); ?>';
  var oprcAddressLookupProviderKey = '<?php echo ($oprcAddressLookupEnabled ? addslashes($oprcAddressLookupManager->getProviderKey()) : ''); ?>';
  var oprcAddressLookupProviderTitle = '<?php echo ($oprcAddressLookupEnabled ? addslashes($oprcAddressLookupManager->getProviderTitle()) : ''); ?>';
  var oprcAddressLookupMessages = <?php echo json_encode($oprcAddressLookupMessages, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

  var recalculate_shipping_cost = '<?php echo (!isset($recalculate_shipping_cost) ? 'false' : $recalculate_shipping_cost); ?>';
   //if cart contents have changed, the order total box needs an extra refresh to update.

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
  var oprcAddressMissing = '<?php echo (!isset($_SESSION['customer_default_address_id']) || !$_SESSION['customer_default_address_id'] > 0 ? 'true' : 'false'); ?>';



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
    jQuery(function() {
      jQuery(document).on('click', '.discount h3', function() {
          couponAccordion(jQuery(this));
      });
    });
  }

  if(oprcShippingInfo == 'true') {
    jQuery(function() {
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

  jQuery(document).off('change.oprcShipping', 'input[name="shipping"]').on('change.oprcShipping', 'input[name="shipping"]', function() {
    var selectedValue = jQuery(this).val();
    oprcSubmitShippingUpdate(selectedValue);
  });
//--></script>
