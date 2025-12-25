// GLOBAL JAVASCRIPT
var sticky = false;
var widthMin = 1232;
var fancyboxEnabled = false;

jQuery(document).ready(function() {

  jQuery(document).ajaxStop(function() {
    // only unblock ajax if not final submit, used a selector from oprc_confirmation that will most likely always be present on that page
    if (jQuery('#checkoutConfirmDefaultBillingAddress').length == 0) {
      jQuery.unblockUI();
    }
  });

  jQuery("#navBreadCrumb").addClass('nmx-wrapper');
  
  // BEGIN CHECKOUT WIDE EVETNS
  if (oprcAJAXErrors == 'true') {
    jQuery(document).ajaxError(function(e, xhr, settings, exception) {
     if (exception != 'abort') {
        alert('error in: ' + settings.url + ' \n'+'error:\n' + xhr.responseText);
      }
    });
  }
  
  // initial process
  prepareMessages();
  reconfigureSideMenu();
  savedCardsLayout();
  if (oprcAJaxShippingQuotes == true) ajaxLoadShippingQuote(true);
  
  //force an extra refresh on the order total box.  This is nessisary when cart contents have changed.
  if(recalculate_shipping_cost == '1') {
    var url_params = ajaxOrderTotalURL.match(/\?./) ? '&action=refresh' : '?action=refresh';
    jQuery.get(ajaxOrderTotalURL + url_params, function(data) {
      var shopBagWrapper = jQuery(data).find('#shopBagWrapper').html();
      jQuery('#shopBagWrapper').html(shopBagWrapper);
    });
  }
  
  if (oprcRemoveCheckout != '') {  
    // remove products from checkout
    jQuery(document).on('click', 'a.removeProduct', function() {
      blockPage(true, true);
      if (jQuery('a.removeProduct').size() == 1) {
        return true;
      } else {
        var link = jQuery(this).attr('href');
        // remove the product from the cart
        jQuery.get(link, function(data) {
        })
        .done(function(){
          // get the order total and other refreshed page content
          jQuery.get(onePageCheckoutURL, function(data) {
            var shopBagWrapper = jQuery(data).find('#shopBagWrapper').html();
            jQuery('#shopBagWrapper').html(shopBagWrapper);
            if (oprcAJaxShippingQuotes == false) {
              //reconfigureSideMenu();
              var shippingMethods = jQuery(data).find('#shippingMethodContainer').html();
              jQuery('#shippingMethodContainer').html(shippingMethods ? shippingMethods : '');
            }
            // update address section too, in case cart is virtual after removing product
            var oprcAddresses = jQuery(data).find('#oprcAddresses').html();
            jQuery('#oprcAddresses').html(oprcAddresses);
            if (oprcRefreshPayment == 'true') {
              var paymentMethodContainer = jQuery(data).find('#paymentMethodContainer').html();
              jQuery('#paymentMethodContainer').html(paymentMethodContainer);
            }
            var discountsContainer = jQuery(data).find('#discountsContainer').html();
            jQuery('#discountsContainer').html(discountsContainer);
            oprcRemoveCheckoutRefreshSelectors(data);
            if (jQuery('#hideRegistration').length > 0 || jQuery('#easyLogin').length > 0) {
              if (jQuery('#hideRegistration').attr('display') != 'none') {
                var step = 1;
              } else if (jQuery('#easyLogin').attr('display') != 'none') {
                var step = 2;
              }
              var oprcLeft = jQuery(data).find('#oprcLeft').html();
              jQuery('#oprcLeft').html(oprcLeft);
              switch(step) {
                case 1:
                  jQuery('#easyLogin').hide();
                  break;
                case 2:
                  jQuery('#hideRegistration').hide();
                  break;
              }
            }
            displayCreditUpdate();
            oprcGoogleAnalytics('oprc_remove_product');
          });
        });
        return false;
      }
    });
  }
  
  jQuery(document).on('click', '.nmx-panel-head a', function() {
    var orderStepURL = jQuery(this).attr('href');
    var step = orderStepURL.match(/step=([0-9]+)/);
    if (step === null) {
      return true;
    }
    blockPage(false, false);
    jQuery.get(orderStepURL, function(data) {
      // login check
      jQuery.get(ajaxLoginCheckURL, function(loginCheck) {
        if (parseInt(loginCheck) == 1) {
          // step 3           
        } else {
          // step 1 or 2 
          switch(step[1]) {
            case '1':
              var onePageCheckout = jQuery(data).find('#onePageCheckout').html();
              oprcLoginRegistrationRefreshSelectors(data);
              jQuery('#onePageCheckout').replaceWith('<div id="onePageCheckout" class="centerColumn nmx-wrapper nmx nmx-plugin nmx-plugin--oprc nmx-cf">' + onePageCheckout + '</div>');
              jQuery('#easyLogin').hide(function() {
                if (oprcHideRegistration) {
                  reconfigureLogin();
                }
                reconfigureSideMenu();
              });            
              break;
            case '2':
              var onePageCheckout = jQuery(data).find('#onePageCheckout').html();
              oprcLoginRegistrationRefreshSelectors(data);
              jQuery('#onePageCheckout').replaceWith('<div id="onePageCheckout" class="centerColumn nmx-wrapper nmx nmx-plugin nmx-plugin--oprc nmx-cf">' + onePageCheckout + '</div>');
              jQuery('#hideRegistration').hide(function() {
                if (oprcHideRegistration) {
                  reconfigureLogin();
                }
                reconfigureSideMenu();                
              });            
              break;
            default:
              // reload the checkout
              window.location.replace(onePageCheckoutURL);
          }          
        }
      });
    });
    return false;
  });
    
  // END CHECKOUT WIDE EVENTS
  // BEGIN NOT LOGGED IN EVENTS
  // get the URL parameters
  // use:
  // queryParameters['paramaterName'] = 'value';
  // location.search = jQuery.param(queryParameters);
  /*
  var queryParameters = {}, queryString = location.search.substring(1), re = /([^&=]+)=([^&]*)/g, m;

  while (m = re.exec(queryString)) {
    queryParameters[decodeURIComponent(m[1])] = decodeURIComponent(m[2]);
  }
  */
  if (oprcHideRegistration) {
    reconfigureLogin();
  }

  // login
  jQuery(document).on('submit', 'form[name="login"]', function() {
    jQuery('#onePageCheckout input').css('border-color', '');
    jQuery('#onePageCheckout .validation').remove();
    jQuery('.missing').removeClass('missing');
    jQuery('.disablejAlert').remove();
    jQuery('#shippingAddressContainer').unblock();
    // remove error messages
    clearMessages();
    blockPage(true, true);
    if (jQuery('input[name="email_address"]').val() != '' && jQuery('input[name="password"]').val() != '') {
      jQuery.post(jQuery(this).attr('action'), jQuery(this).serialize(), function(data) {
        var onePageCheckout = jQuery(data).find('#onePageCheckout').html();
        if (onePageCheckout == undefined) {
          setTimeout(function(){ window.location=onePageCheckoutURL;}, 3000)
        } else { 
          // check if logged in
          jQuery.get(ajaxLoginCheckURL, function(loginCheck) {
            if (parseInt(loginCheck) == 1) {
              jQuery('#onePageCheckout').html(onePageCheckout);
              reconfigureSideMenu();
              displayCreditUpdate();
              scrollToAddresses();
          
              savedCardsLayout();            
            } else {
              var qc_login = jQuery(data).find('#oprc_login').html();
              jQuery('#oprc_login').html(qc_login);
              reconfigureLogin();
            }
            oprcLoginRegistrationRefreshSelectors(data);
            checkPageErrors();
          });
        }  
      });
    } else {
      if (jQuery('input[name="email_address"]').val() == '') {
        jQuery('input[name="email_address"]').addClass('missing');
      }
      if (jQuery('input[name="password"]').val() == '') {
        jQuery('input[name="password"]').addClass('missing');
      }
      jQuery('input[name="password"]').after('<div class="disablejAlert loginError">' + oprcLoginValidationErrorMessage + '</div>');
      // remove loading
      setTimeout(function() {
        jQuery.unblockUI();
      }, 300);
    }
    return false;
  });  
  
  // registration
  jQuery(document).on('submit', 'form[name="create_account"]', function() {
    // remove error messages
    clearMessages();
    jQuery('#shippingAddressContainer').unblock();
    blockPage(true, true);
    jQuery('[name=create_account] *').removeClass('missing');
    jQuery('[name=create_account] .validation').remove();
    if (check_form_registration("create_account")) {
      jQuery.post(jQuery(this).attr('action'), jQuery(this).serialize(), function(data) {
        // check if logged in
        jQuery.get(ajaxLoginCheckURL, function(loginCheck) {
          if (parseInt(loginCheck) == 1) {
            var onePageCheckout = jQuery(data).find('#onePageCheckout').html();
            jQuery('#onePageCheckout').html(onePageCheckout);
            reconfigureSideMenu();
            displayCreditUpdate();
            scrollToAddresses();
        
            savedCardsLayout();
          } else {
            var easyLogin = jQuery(data).find('#easyLogin').html();
            jQuery('#easyLogin').html(easyLogin);
            // checkGuestByDefault();
            reconfigureLogin(); 
          }
        });
        oprcLoginRegistrationRefreshSelectors(data);
        checkPageErrors();
      });
    } else {
      setTimeout(function() {
        jQuery.unblockUI();
      }, 200);
      reconfigureLogin();
      return false;
    }
    return false;
  });
  
  if (oprcGuestAccountOnly == 'false') {
    jQuery(document).on('submit', 'form[name="hideregistration_register"]', function() {
      jQuery.history.load("hideregistration_register");
      jQuery('#hideRegistration input').css('border-color', '');
      jQuery('#hideRegistration .validation').remove();
      jQuery('.missing').removeClass('missing');
      jQuery('.disablejAlert').remove();      
      jQuery('input[name="cowoa-checkbox"]').val("false");
      // begin validation
      var error = false;
      var email_address_register = jQuery('form[name="hideregistration_register"] input[name="hide_email_address_register"]').val();
      if (!email_address_register.length > 0 || email_address_register.search(/@/) == -1 || email_address_register.search(/\./) == -1) {
        error = true;
        jQuery('form[name="hideregistration_register"] input[name="hide_email_address_register"]').addClass('missing');
        // add message next to label
        jQuery('form[name="hideregistration_register"] input[name="hide_email_address_register"]').after('<div class="disablejAlert alert validation">&nbsp;invalid email</div>'); 
      }
      if (oprcConfirmEmail == 'true') {
        var hide_email_address_confirm = jQuery('form[name="hideregistration_register"] input[name="hide_email_address_confirm"]').val();
        if (!hide_email_address_confirm.length > 0 || hide_email_address_confirm.search(/@/) == -1 || hide_email_address_confirm.search(/\./) == -1) {
          error = true;
          jQuery('form[name="hideregistration_register"] input[name="hide_email_address_confirm"]').addClass('missing');
          // add message next to label
          jQuery('form[name="hideregistration_register"] input[name="hide_email_address_confirm"]').after('<div class="disablejAlert alert validation">&nbsp;invalid email</div>');
        }
        if (email_address_register.length > 0 && hide_email_address_confirm.length > 0 && email_address_register != hide_email_address_confirm) {
          error = true;
          jQuery('form[name="hideregistration_register"] input[name="hide_email_address_register"]').addClass('missing');
          jQuery('form[name="hideregistration_register"] input[name="hide_email_address_confirm"]').addClass('missing');
          // add message next to label
          jQuery('form[name="hideregistration_register"] input[name="hide_email_address_confirm"]').after('<div class="disablejAlert alert validation">&nbsp;email mismatch</div>');
        }
      }
      if (!error) {
        jQuery.post(ajaxAccountCheckURL, jQuery('[name="hideregistration_register"]').serialize(), function(data) {
          if (data == '0') {
            if (oprcConfirmEmail == 'true') {
              var email_address_confirm = jQuery('form[name="hideregistration_register"] input[name="hide_email_address_confirm"]').val();
            }
            /*
            var password_register = jQuery('form[name="hideregistration_register"] input[name="hide_password_register"]').val();
            var password_confirmation = jQuery('form[name="hideregistration_register"] input[name="hide_password_confirmation"]').val();
            */
            jQuery('#hideRegistration').hide(function() {
              jQuery('#easyLogin').show();
              jQuery('#easyLogin').css('visibility', 'visible');
              stickySideMenu();
              jQuery('form[name="create_account"] input[name="email_address_register"]').val(email_address_register);
              if (oprcConfirmEmail == 'true') {
                jQuery('form[name="create_account"] input[name="email_address_confirm"]').val(email_address_confirm);
              }
              //jQuery('form[name="create_account"] input[name="password_register"]').val(password_register);
              //jQuery('form[name="create_account"] input[name="password_confirmation"]').val(password_confirmation);
            });
            if (oprcOrderSteps == 'true') {
              jQuery.get(onePageCheckoutURL + '&hideregistration=true', function(data) {
                updateOrderSteps(data);
              });
            }
          } else {
            jQuery('form[name="hideregistration_register"] input[name="hide_email_address_register"]').addClass('missing');
            // add message below label
            jQuery('form[name="hideregistration_register"] input[name="hide_email_address_register"]').after('<div class="disablejAlert alert validation">&nbsp;' + oprcEntryEmailAddressErrorExists + '</div>');             
      }
        });
      }
      oprcGoogleAnalytics('oprc_hideregistration_register');
      return false;  
    });
    
    jQuery(document).on('submit', 'form[name="hideregistration_guest"]', function() {
      jQuery.history.load("hideregistration_guest");
      jQuery('#hideRegistration input').css('border-color', '');
      jQuery('#hideRegistration .validation').remove();
      // check checkout as guest by default
      // checkGuestByDefault();
      // begin validation
      var error = false;
      if (!error) {
        jQuery('#hideRegistration').fadeOut('fast', function() {
          jQuery('#easyLogin').fadeIn();
          jQuery('#easyLogin').css('visibility', 'visible');
          //reconfigureLogin('true');
          stickySideMenu();
        });
        if (oprcOrderSteps == 'true') {
          jQuery.get(onePageCheckoutURL + '&hideregistration=true', function(data) {
            updateOrderSteps(data);
          });
        }
        scrollToRegistration();
      }
      oprcGoogleAnalytics('oprc_hideregistration_guest');
      return false;
    });

    jQuery(document).on('click', '#hideregistrationBack a', function() {
      if (jQuery('#hideRegistration').length > 0) {
        window.location.hash = '';
        jQuery('#easyLogin').fadeOut('fast', function() {
          jQuery('#hideRegistration').fadeIn();
          stickySideMenu();
          if (oprcOrderSteps == 'true') {
            jQuery.get(onePageCheckoutURL, function(data) {
              updateOrderSteps(data);
            });
          }
        });
        return false;
      } else {
        return true;
      }
    });
  }

  jQuery(document).on('click', '#shippingAddress-checkbox', function() {
    // clear all message stack errors
    clearMessages();
    if(jQuery(this).is(':checked')) {
      jQuery('#shippingField').fadeOut(function() {
        stickySideMenu();
      });
    } else {
      jQuery('#shippingField').fadeIn(function() {
        stickySideMenu();
      });
    }
  });

  if (oprcGuestAccountStatus == 'true') {  
    if (oprcGuestFieldType == 'button') {
      // hide cowoa field
      jQuery('#no_account_switch a').on('click', function() {
        // clear all message stack errors
        clearMessages();

        jQuery('#passwordField').addClass('nmx-hidden');
        if (oprcGuestHideEmail == 'true') {
          jQuery('#emailOptions').fadeOut();
        }
        jQuery('#cowoaOff').fadeOut('fast', function() {
          jQuery('#cowoaOn').fadeIn();
        });
        jQuery('input[name="cowoa-checkbox"]').val('true');
        return false;
      });
      
      //show cowoa field
      jQuery('#register_switch a').on('click', function() {
        // clear all message stack errors
        clearMessages();
        jQuery('#passwordField').removeClass('nmx-hidden');
        if (oprcGuestHideEmail == 'true') { 
          jQuery('#emailOptions').fadeIn();
        }
        jQuery('#cowoaOn').fadeOut('fast', function() {
          jQuery('#cowoaOff').fadeIn();
        });
        jQuery('input[name="cowoa-checkbox"]').val('false');
        return false;
      });

    } else if (oprcGuestFieldType == 'checkbox') {
      // CHECKBOX METHOD
      // checkGuestByDefault();

      jQuery(document).on('click', '#cowoa-checkbox', function() {
        // clear all message stack errors
        clearMessages();
        if (jQuery(this).is( ':checked' )) {
          guestHideShowInfo();
        } else {
          guestHideShowInfo( 'checked' );
        }
      });
      
    } else if (oprcGuestFieldType == 'radio') {
      // RADIO METHOD
      // checkGuestByDefault();

      jQuery(document).on('click', 'input[name="cowoa-radio"]', function() {
        // clear all message stack errors
        clearMessages();
        guestHideShowInfo(jQuery(this).val());
      });
    }
  }

  function guestHideShowInfo(option) {
    var cowoaInput = jQuery( 'input[name="cowoa-checkbox"]' ),
        passwordFields = jQuery( '#passwordField' ),
        newsOptions = jQuery( '#emailOptions' );

    if ( option == "on" || option == "checked" ) {
      cowoaInput.val( 'true' );
      passwordFields.addClass('nmx-hidden');
      if (oprcGuestHideEmail == 'true') {
        newsOptions.fadeOut();
      }
    } else {
      cowoaInput.val( 'false' );
      passwordFields.removeClass('nmx-hidden');
      if (oprcGuestHideEmail == 'true') {
        newsOptions.fadeIn();
      }
    }
  }

  jQuery('#forgottenPasswordLink a').fancybox({
    autoSize    : true,
    closeClick  : true,
    openEfoprct  : 'fade',
    closeEfoprct : 'fade',
    closeClick  : false,
    type        : 'ajax',
    maxWidth    : 878,
    padding     : 0
  });  
  // END NOT LOGGED IN EVENTS

  // BEGIN LOGGED IN EVENTS
  jQuery('.termsdescription a').fancybox({
    autoSize    : true,
    closeClick  : true,
    openEffect  : 'fade',
    closeEffect : 'fade',
    closeClick  : false,
    type        : 'ajax',
    maxWidth    : 878,
    padding     : 0
  });

  jQuery('#privacyPolicy').fancybox({
    autoSize    : true,
    closeClick  : true,
    openEfoprct  : 'elastic',
    closeEfoprct : 'elastic',
    closeClick  : false,
    type        : 'ajax',
    maxWidth    : 878,
    padding     : 0
  });

  jQuery('#conditionsUse').fancybox({
    autoSize    : true,
    closeClick  : true,
    openEfoprct  : 'elastic',
    closeEfoprct : 'elastic',
    closeClick  : false,
    type        : 'ajax',
    maxWidth    : 878,
    padding     : 0
  });

  jQuery(document).on('click', '#js-submit-payment', function(e) {
    checkAddress();
    blockPage(true, false);
    // clear error messages
    clearMessages();
    submitFunction(parseFloat(oprcZenUserHasGVAccount), parseFloat(oprcTotalOrder));
    if (check_payment_form("checkout_payment") && oprcAddressMissing == 'false') { //don't submit form if address is missing
      if (oprcAJAXConfirmStatus == 'false') {
        return true;
      }
      if (jQuery('[name="dc_redeem_code"]').length > 0) { // mod is enabled
        if (jQuery('[name="dc_redeem_code"]').val().length > 0) {
          updateCredit();
          return false;
        } else {
          return submitCheckout();
        }
      } else {
        return submitCheckout();
      }
    } else {
      e.preventDefault();
        //scroll to error message
        if(oprcAddressMissing == 'true') {
          if(jQuery(".messageStackError").length > 0) {
            jQuery('html, body').animate({
              scrollTop: jQuery(".messageStackError").offset().top
            }, 2000);
          } else { //scroll to payment
            jQuery('html, body').animate({
              scrollTop: jQuery("#paymentMethodContainer").offset().top
            }, 2000);
          }
        }

      jQuery.unblockUI();
      return false;
    }
  });
  
  // keypress equivalent for update credit module
  jQuery(document).on('keypress', '.discount input', function(e) {
    if (e.keyCode == 13) {
      updateCredit(jQuery(this).parents('.discount'));
      return false;
    }
  });
  jQuery(document).on('click', '#discountsContainer .updateButton', function() {
    updateCredit(jQuery(this).parents('.discount'));
  });
  jQuery(document).on('click', '.gvRemove', function() {
    jQuery('input[name="cot_gv"]').val(0);
    updateCredit();
    //setTimeout(jQuery('#discountFormdisc-ot_gv h2').after('<div class="disablejAlert"><div class="messageStackSuccess">' + oprcGVName + ' removed.</div></div>'), 5000);
    return false;
  });
  
  jQuery('#shopBagWrapper').on('click', '.couponRemove', function() {
    jQuery('input[name="dc_redeem_code"]').val('remove');
    updateCredit();
    return false;
  });

  displayCreditUpdate();
  
  if (jQuery(window).width() > 980) {
    initiateFancyBox('#linkCheckoutShippingAddr');
    initiateFancyBox('#linkCheckoutPaymentAddr');
    fancyboxEnabled = true;
  }

  jQuery(document).on('click', '.delete-address-button', function() {
    //blockPage(false, false);
    var address_entry = jQuery(this).parents('.addressEntry');
    var data = {
      address_book_id: jQuery(this).attr('address-book-id'),
      default_selected: jQuery(this).attr('default-selected')
    };  
    // check if logged in
    jQuery.get(ajaxLoginCheckURL, function(loginCheck) {
      if (parseInt(loginCheck) == 1) { 
        jQuery.ajax({
          url: ajaxDeleteAddressURL,
          method: 'POST',
          data: data,
          dataType: 'json',
          success: function (response) {
            if(response.success == false){
              jQuery('.alert-message').remove();
              jQuery('#addressBookContainer').prepend('<p class="messageStackError alert-message">' + response.message + '</p>');
            } else if(response.success == true){
              jQuery('.alert-message').remove();
              jQuery('#addressBookContainer').prepend('<p class="messageStackSuccess alert-message">' + response.message + '</p>');
              address_entry.remove();
            }
          }
        }); 
      } else {
        // redirect to checkout
        confirm('Sorry, your session has expired.', 'Time Out', function(r) {
          window.location.replace(fecQuickCheckoutURL);
        });
      }
    }); 
    //jQuery.unblockUI();
    return false;
  });

  jQuery(document).on('submit', 'form[name="checkout_address"]', function() {
    blockPage(true, true);
    // submit the form
    var newAddress = check_new_address('checkout_address');
    if (newAddress) {
      jQuery.post(jQuery('form[name="checkout_address"]').attr('action'), jQuery('form[name="checkout_address"]').serialize(), function() {
        // check if logged in
        jQuery.get(ajaxLoginCheckURL, function(loginCheck) {
          if (parseInt(loginCheck) == 1) {
            // redirect to main checkout page if on the address page
            if (window.location.hash == '#checkoutShipAddressDefault' || window.location.hash == '#checkoutPayAddressDefault') {
              window.location.assign(onePageCheckoutURL);
            } else {
              jQuery.get(onePageCheckoutURL, function(data) {
                checkPageErrors();
                oprcAddressMissing = 'false'; //validation to stop checkout when the user has no address.
                var oprcAddresses = jQuery(data).find('#oprcAddresses').html();
                jQuery('#oprcAddresses').html(oprcAddresses);
                var shippingMethodContainer = jQuery(data).find('#shippingMethodContainer').html();
                jQuery('#shippingMethodContainer').html(shippingMethodContainer);
                if (oprcRefreshPayment == 'true') {
                  var paymentMethodContainer = jQuery(data).find('#paymentMethodContainer').html();
                  jQuery('#paymentMethodContainer').html(paymentMethodContainer);
                }
                var shopBagWrapper = jQuery(data).find('#shopBagWrapper').html();
                jQuery('#shopBagWrapper').html(shopBagWrapper);
                jQuery.fancybox.close(true);
                savedCardsLayout();
              });
            }
          } else {
            // redirect to checkout
            confirm('Sorry, your session has expired.', 'Time Out', function(r) {
              window.location.replace(onePageCheckoutURL);
            });          
          }
        });
      });
    } else {
      jQuery.unblockUI();
    }
    return false;
  });
    
  // END LOGGED IN EVENTS
  
  // Events after the page has completely loaded
  jQuery.history.init(function(hash){
    if(hash == "hideregistration_guest") {
      // initialize your app
      jQuery('form[name="hideregistration_guest"]').submit();
    } 
  },
  { unescape: ",/" });

  scrollToError();
  
  // If the floating shopping cart is going to go further than the footer
  jQuery( "#shoppingBagContainer .back" ).click(function() {
    $borda = jQuery("#footer").offset().top - jQuery("#needHelpHead").offset().top;
    if ($borda < 200) {
      //alert("warning");
      document.getElementById("orderTotal").style.overflowY = "scroll";
      document.getElementById("orderTotal").style.height = "250px";
    }
  });

});

jQuery(window).resize(function() {
  stickySideMenu();
  if (jQuery(window).width() > 980 && !fancyboxEnabled) {
    initiateFancyBox('#linkCheckoutShippingAddr');
    initiateFancyBox('#linkCheckoutPaymentAddr');
    fancyboxEnabled = true;
  }  
});

function initiateFancyBox(selector) {
  jQuery(selector).fancybox({
    autoSize      : false,
    autoResize    : true,
    autoCenter    : false,
    closeClick    : true,
    openEffect    : 'fade',
    closeEffect   : 'fade',
    closeClick    : false,
    type          : 'ajax',
    scrollOutside : false,                                                                                                      
    scrolling     : 'auto',
    padding       : 0,
    beforeShow    : function() {
      
      oprcChangeAddressCallback();
      
      // initiate tabs
      // when user clicks on tab, this code will be executed
      var tabs_list = jQuery("#nmx-tabs-nav"),
          tab = tabs_list.find("li"),
          tab_content = jQuery(".nmx-tab-content");

      tab.first().addClass('active');
      tab_content.first().addClass('active');

      if (tab.length === 1) {
        tab.first().addClass('full')
      }

      tab.click(function() {
          // first remove class "active" from currently active tab
          tab.removeClass('active');
   
          // now add class "active" to the selected/clicked tab
          jQuery(this).addClass("active");
          // hide all tab content
          tab_content.removeClass('active');
   
          // here we get the href value of the selected tab
          var selected_tab = jQuery(this).find("a").attr("href");
   
          // show the selected tab content
          jQuery(selected_tab).addClass('active');
   
          // at the end, we add return false so that the click on the link is not executed
          return false;
      });

      // form
      var theForm = jQuery('[name="checkout_address"]');
      jQuery('[name="checkout_address"]').removeAttr('onsubmit');
      update_zone(theForm[0]);      
    }
  });
}

function disableFancyBox(selector) {
  jQuery(selector).unbind('click.fb-start');
  //fancyboxEnabled = false;
}

function reconfigureSideMenu() {
  // add expand all Button
  if ((!jQuery.browser.msie || jQuery.browser.version > 7)) {
    setStickColumnWidth();
    stickySideMenu();
  }

}

function stickySideMenu() {
  // only enable for desktop devices
  //if(jQuery(window).width() >= 1000 && (!jQuery.browser.msie || jQuery.browser.version > 7)) {
  if(!isTouchDevice() && (!jQuery.browser.msie || jQuery.browser.version > 7)) {
    
    if (sticky != false) {
      sticky.destroy();
    }
    
    // only exceute if side menu is smaller than left column
    var oprcRight = jQuery('#oprcRight');

    if(oprcRight.length) { 
      var oprcLeftHeight = jQuery('#oprcLeft').outerHeight();
      var oprcRightHeight = oprcRight.outerHeight();
      var rightPosition = oprcRight.position().top + oprcRight.height();
      var leftPosition = jQuery('#oprcLeft').position().top + jQuery('#oprcLeft').height();

      if (oprcLeftHeight > oprcRightHeight) {
        
        sticky = jQuery('#onePageCheckout').stickem({
          onStick: function() {
            setStickColumnWidth();    
          },
          onUnstick: function() {
            oprcRight.css('margin-left', '0');
          }
        });

        if(rightPosition >= leftPosition) {
          oprcRight.removeClass('stickit');
          oprcRight.addClass('stickit-end');
        }            
      }
    }
  }    
}

function blockPage( reloadShippingQuotes, isCartContentChanged ) {
  var processingText = (typeof oprcProcessingText !== 'undefined' ? oprcProcessingText : '');
  jQuery.blockUI({
    message: processingText, 
    css: { 
      border: 'none', 
      padding: '15px', 
      backgroundColor: oprcMessageBackground, 
      '-webkit-border-radius': '10px', 
      '-moz-border-radius': '10px', 
      opacity: oprcMessageOpacity, 
      color: oprcMessageTextColor 
    },
    overlayCSS: { 
      backgroundColor: oprcMessageOverlayColor,
      color: oprcMessageOverlayTextColor,
      opacity: oprcMessageOverlayOpacity
    },
    onUnblock : function(){
      if ( reloadShippingQuotes == true && oprcAJaxShippingQuotes == true ) {
        ajaxLoadShippingQuote(isCartContentChanged);
      }
    } 
  });
}

function isTouchDevice() {
  if (navigator.userAgent.match(/iPhone/i) || navigator.userAgent.match(/iPod/i) || navigator.userAgent.match(/iPad/i) || navigator.userAgent.match(/Android/i) || navigator.userAgent.match(/webOS/i)) {
    return true;
  } else {
    return false;
  }
}

function updateOrderSteps(data) {
  if (oprcOrderSteps == 'true') {
    var orderSteps = jQuery(data).find('.orderSteps').html();
    jQuery('.orderSteps').html(orderSteps);
  } else {
    return false;
  }
}  
function oprcGoogleAnalytics(pageName) {
  if (oprcGAEnabled == 'true') {
    switch(oprcGAMethod) {
      case 'asynchronous':
        if (typeof _gaq != "undefined") {
          _gaq.push(['_trackPageview', oprcCatalogFolder + pageName]);
        }
        break;
      case 'universal':
        if (typeof ga != "undefined") {
          ga('send', 'pageview', oprcCatalogFolder + pageName);
        }
        break;
      default:
        if (typeof pageTracker != "undefined") {
          pageTracker._trackPageview(oprcCatalogFolder + pageName);
        }
        break;
    }
  }
}

function prepareMessages() {
  if (jQuery('.messageStackError').length > 0 || jQuery('.messageStackCaution').length > 0 || jQuery('.messageStackSuccess').length > 0 || jQuery('.messageStackWarning').length > 0) {
    var messageStackErrors = '';
    jQuery('.messageStackError, .messageStackCaution, .messageStackSuccess, .messageStackWarning').each(function() {
      if (!jQuery(this).parents().hasClass('disablejAlert')) {
        messageStackErrors += jQuery(this).text();
      }
    });
    // update messageStack only
    if (messageStackErrors != '') {
      jQuery('.messageStackErrors').html(messageStackErrors);
    }
    return true;
  } else {
    return false;
  }
}

function clearMessages() {
    jQuery('.messageStackErrors').empty();
    jQuery('.messageStackError, .messageStackCaution, .messageStackSuccess, .messageStackWarning').each(function() {
      jQuery(this).remove();
    });
}

// Convert HTML breaks to newline
String.prototype.br2nl =
function() {
  return this.replace(/<br\s*\/?>/mg,"\n");
};

function reconfigureLogin() { // note: cowoaStatus removed, but checks still left in case we need them in the future
  update_zone(document.create_account);
  if (oprcShippingAddress == 'true') {
    update_zone_shipping(document.create_account);
  }
  if (oprcShippingAddressStatus == true) {
    blockShippingField();
  }
  if (oprcShippingAddressCheck != true || (oprcGetContentType == 'virtual')) {
    blockShippingContainer();
  }
  if (oprcGuestAccountStatus == 'true') {
    if (oprcGuestFieldType == 'button') {
      
    } else if (oprcGuestFieldType == 'checkbox') {
      // CHECKBOX METHOD
      // unhide checkbox
      jQuery('input#cowoa-checkbox').removeClass("hiddenField"); 
      
    } else if (oprcGuestFieldType == 'radio') {
      // RADIO METHOD
      jQuery('input[name=cowoa-radio]').removeClass("hiddenField");
    }
    // disable the links created for non-JavaScript users since JavaScript is enabled
    jQuery(".disableLink").each(function() {
      var linkContents = jQuery(this).contents();
      jQuery(this).replaceWith(linkContents);
    });
  } // end COWOA check ?>
  reconfigureSideMenu();
}
// blocking functions
function blockShippingField() {
  if (jQuery('#shippingAddress-checkbox').is(':checked')) {          
    jQuery('#shippingField').hide();
  }
}

function blockShippingField() {
  if (jQuery('#shippingAddress-checkbox').is(':checked')) {          
    jQuery('#shippingField').hide();
  }
}

function blockShippingContainer() {
  jQuery('#shippingAddressContainer').hide();
}

function updateForm() {
  blockPage();
  // clear all message stack errors
  clearMessages();
  jQuery.get(ajaxLoginCheckURL, function(loginCheck) {
    if (parseInt(loginCheck) == 1) {
      jQuery.post(onePageCheckoutURL, jQuery("[name=checkout_payment]").serialize() + "&oprcaction=process&request=ajax", function(data) {
        failCheck(data);
        var shopBagWrapper = jQuery(data).find('#shopBagWrapper').html();
        jQuery('#shopBagWrapper').html(shopBagWrapper);
        if (oprcAJaxShippingQuotes == false) {
          //reconfigureSideMenu();
          var shippingMethods = jQuery(data).find('#shippingMethodContainer').html();
          jQuery('#shippingMethodContainer').html(shippingMethods ? shippingMethods : '');
        }
        var discountsContainer = jQuery(data).find('#discountsContainer').html();
        jQuery('#discountsContainer').html(discountsContainer);

         //address messageStack errors should persist (i.e. missing address)
         var oprcAddresses = jQuery(data).find('#oprcAddresses').html();
         jQuery('#oprcAddresses').html(oprcAddresses);

        if (oprcRefreshPayment == 'true') {
          var paymentMethodContainer = jQuery(data).find('#paymentMethodContainer').html();
          jQuery('#paymentMethodContainer').html(paymentMethodContainer);
        } else {
          var url_params = ajaxOrderTotalURL.match(/\?./) ? '&action=credit_check' : '?action=credit_check';
          jQuery.post(ajaxOrderTotalURL + url_params, jQuery("[name=checkout_payment]").serialize(), function(orderTotalCheck) {
            if (orderTotalCheck == 0 || (jQuery('#paymentMethodContainer').length > 0 && !jQuery('#paymentMethodContainer').html().trim())) {
              // order total is zero, refresh the payment methods which should mean no payment is required
              var paymentMethodContainer = jQuery(data).find('#paymentMethodContainer').html();
              jQuery('#paymentMethodContainer').html(paymentMethodContainer);
            } else if (!jQuery('#paymentMethodContainer').length > 0) {
              // we need to reload the page
              window.location.replace(onePageCheckoutURL);
            }          
          });          
        }
        
        jQuery.unblockUI();
        displayCreditUpdate();
        oprcGoogleAnalytics('oprc_update');
      });           
    } else {
      // redirect to checkout
      confirm('Sorry, your session has expired.', 'Time Out', function(r) {
        window.location.replace(onePageCheckoutURL);
      });
    }
  });
}

function updateCredit(discountMod) {
  blockPage();
  // clear all message stack errors
  clearMessages();
  jQuery.get(ajaxLoginCheckURL, function(loginCheck) {
    if (parseInt(loginCheck) == 1) {
      jQuery.post(onePageCheckoutURL, jQuery("[name=checkout_payment]").serialize() + "&oprcaction=updateCredit&request=ajax", function(data) {
        failCheck(data);
        var shopBagWrapper = jQuery(data).find('#shopBagWrapper').html();
        var discountsContainer = jQuery(data).find('#discountsContainer').html();
        jQuery('#shopBagWrapper').html(shopBagWrapper);
        if (oprcAJaxShippingQuotes == false) {
          //reconfigureSideMenu();
          var shippingMethods = jQuery(data).find('#shippingMethodContainer').html();
          jQuery('#shippingMethodContainer').html(shippingMethods ? shippingMethods : '');
        }
        jQuery('#discountsContainer').html(discountsContainer);
        // get the current order total
        var url_params = ajaxOrderTotalURL.match(/\?./) ? '&action=credit_check' : '?action=credit_check';
        jQuery.post(ajaxOrderTotalURL + url_params, jQuery("[name=checkout_payment]").serialize(), function(orderTotalCheck) {
          if (orderTotalCheck == 0 || (jQuery('#paymentMethodContainer').length > 0 && !jQuery('#paymentMethodContainer').html().trim())) {
            // order total is zero, refresh the payment methods which should mean no payment is required
            var paymentMethodContainer = jQuery(data).find('#paymentMethodContainer').html();
            jQuery('#paymentMethodContainer').html(paymentMethodContainer);
          } else if (!jQuery('#paymentMethodContainer').length > 0) {
            // we need to reload the page
            window.location.replace(onePageCheckoutURL);
          }
        });
        jQuery.unblockUI();
        savedCardsLayout();
        displayCreditUpdate();
        // display message stack errors
        prepareMessages();
        // if credit box includes an error message, display expanded
        jQuery('#discountsContainer .discount').each(function() {
          if(jQuery(this).find('.messageStackError').length > 0 || jQuery(this).find('.messageStackCaution').length > 0 || jQuery(this).find('.messageStackWarning').length > 0) {
            couponAccordion(jQuery(this).find('h3'));
          }
        });
        oprcGoogleAnalytics('oprc_credit');
      });
    } else {
      // redirect to checkout
      alert('Sorry, your session has expired.');
      window.location.replace(onePageCheckoutURL);
    }
  }); 
}

// display the credit class update button if it exists
function displayCreditUpdate() {
  if (jQuery(".updateButton").length > 0) {
    jQuery(".updateButton").removeAttr("style");
  }
}

function submitCheckout() {
  if ((oprcOnePageStatus == 'true') && (jQuery(window).width() >= widthMin)) {
    var onePageStatus = true;
  } else {
    return true;
  }
  if (oprcAJAXConfirmStatus == 'false') {
    if (jQuery('textarea[name="comments"]').val().length > 0) {
      return true; // in case comments field has special characters, do not use AJAX post if AJAX status is false
    }
  }
  // clear validation errors
  jQuery('.validation').remove();
  jQuery('.missing').removeClass('missing');
  // clear error messages
  clearMessages();
  jQuery.get(ajaxLoginCheckURL, function(loginCheck) {
    if (parseInt(loginCheck) == 1) {
      jQuery.post(onePageCheckoutConfirmURL, jQuery("[name=checkout_payment]").serialize() + '&request=ajax', function(data) {
        var onePageCheckoutContent = jQuery(data).find('#onePageCheckoutContent').html();
        failCheckConfirmation(onePageCheckoutContent);
        jQuery('#onePageCheckout').html('<div id="onePageCheckoutContent">' + onePageCheckoutContent + '</div>');
        oprcCheckoutSubmitCallback();
        var messagesExist = prepareMessages();
        if (!messagesExist && jQuery('[name="checkout_confirmation"]').length > 0) {
          if (onePageStatus == true) {
            jQuery('#onePageCheckout').css('visibility', 'hidden');
            jQuery('[name="checkout_confirmation"]').submit();
            return false;
          } else {
            jQuery.unblockUI();
            return false;
          }
        } else {
          reconfigureSideMenu();
          
          
          displayCreditUpdate();
          setTimeout(function() {scrollToError()}, 500);
        }
        oprcGoogleAnalytics('oprc_confirmation');
      });           
    } else {
      // redirect to checkout
      prompt('Sorry, your session has expired.', 'Time Out', function(r) {
        window.location.replace(onePageCheckoutURL);
      });
    }
  });
  return false;
}

function scrollToAddresses() {
  if (jQuery("#oprcAddresses").length > 0) {
    jQuery('html, body').animate({
      scrollTop: jQuery("#oprcAddresses").offset().top
    }, 1000);
  }  
}

function scrollToError() {
  if (jQuery('.disablejAlert').length > 0) {
    // scroll to top error message
    jQuery('html, body').animate({
      scrollTop: jQuery(".disablejAlert:first").offset().top
    }, 1000);
  }  
}

function scrollToRegistration() {
  jQuery('html, body').animate({
    scrollTop: jQuery("html, body").offset().top
  }, 1000);
}

function failCheck(data) {
  if (data.length == 0) {
    window.location.replace(onePageCheckoutURL);
  }
}

function failCheckConfirmation(data) {
  if (data == null || data.length == 0) {
    jQuery('form[name="checkout_payment"]').submit();
  }
}

// resizing right column bar
jQuery(function() {
    
    var $columnRight  = jQuery( '#oprcRight' );

    jQuery(window).resize(function(){
        // $columnRight.css('opacity', '0');
        setStickColumnWidth();

    });

});

function setStickColumnWidth() {

    var $columnRow    = jQuery( '.stickem-container' ),  
        $columnLeft   = jQuery( '#oprcLeft' ),
        $columnRight  = jQuery( '#oprcRight' );

    // variables for calculating new margin right
    var $columnRowWidth       = $columnRow.width(),
        $columnLeftWidth      = $columnLeft.outerWidth(),
        $columnRightWidth     = $columnRowWidth - $columnLeftWidth;

    // set new max width
    $columnRight.css('max-width', $columnRightWidth);

    // position it at the right side
    if ($columnRight.hasClass('stickit')) {
        $columnRight.css('margin-left', $columnLeftWidth);
    };

}

function collectsCardDataOnsite(paymentValue)
{
     zcJS.ajax({
      url: "ajax.php?act=ajaxPayment&method=doesCollectsCardDataOnsite",
      data: {paymentValue: paymentValue}
    }).done(function( response ) {
      if (response.data == true) {
       var str = jQuery('form[name="checkout_payment"]').serializeArray();

       zcJS.ajax({
        url: "ajax.php?act=ajaxPayment&method=prepareConfirmation",
        data: str
      }).done(function( response ) {
       jQuery('#checkoutPayment').hide();
       jQuery('#navBreadCrumb').html(response.breadCrumbHtml);
       jQuery('#checkoutPayment').before(response.confirmationHtml);
       jQuery(document).attr('title', response.pageTitle);

       });
      } else {
       jQuery('form[name="checkout_payment"]')[0].submit();
    }
    });
    return false;
}

function savedCardsLayout() {
  // saved cards unselect
  jQuery(function() {

    var saveCardOptions = jQuery('#pmt-authorizenet_saved_cards'),
        saveCards = saveCardOptions.closest('.payment-method'),
        paymentMethod = jQuery('.payment-method'),
        classSavedMethod = 'pmt-authorizenet_saved_cards';

    if (saveCardOptions.length) {
      
        saveCardOptions.closest('.nmx-radio').hide();
        saveCardOptions.addClass(classSavedMethod);
        saveCards.addClass('payment-method--saved-cards');

        paymentMethod.find(".nmx-radio > label > input:first", this).each(function(index, el) {
          jQuery(this).addClass('payment-radio');
        });

        jQuery('.payment-radio').on('change', function() {
          if (!jQuery(this).hasClass(classSavedMethod)) {
            saveCards.find('input').each(function(index, el) {
              jQuery(this).prop('checked', false);
            });
          }
        });

        saveCards.find('input').on('change', function(event) {
          if (!saveCardOptions.is(':checked')) {
            saveCardOptions.trigger('click');
          }
        });

    }

  });
}

function ajaxLoadShippingQuote(isCartContentChanged)
{
  jQuery('#shippingMethods').html('Loading ...').load(ajaxShippingQuotesURL, function( response, status, xhr ) {
    if ( status == "error" ) {
      jQuery('#shippingMethods').html('Error:' + xhr.status + ' ' + xhr.statusText);
    } else {
      if ( !response ) {
        jQuery('#shippingMethodContainer').hide();
        isCartContentChanged = true;
      } else {
        jQuery('#shippingMethodContainer').show();
      }
      if ( isCartContentChanged == true ) {
        // contents need to be loaded again to get refreshed order total
        var url_params = ajaxOrderTotalURL.match(/\?./) ? '&action=refresh' : '?action=refresh';
        jQuery.get(ajaxOrderTotalURL + url_params, function(data) {
          var shopBagWrapper = jQuery(data).find('#shopBagWrapper').html();
          jQuery('#shopBagWrapper').html(shopBagWrapper);
        });
        reconfigureSideMenu();
      }
    }
  });
}

function checkPageErrors() {
    var u = window.location.href;
    jQuery.get(u, function(data) {
      var el = $( '<div></div>' );
      el.html(data)
      if (jQuery('.messageStackError', el).length > 0 || jQuery('.messageStackCaution', el).length > 0 || jQuery('.messageStackWarning', el).length > 0) {
        location.reload(true);
      }
    });
}

// new
jQuery(document).on('click', '.nmx-accordion-title', function(e) {
  jQuery(this).toggleClass( 'is-open' );
  jQuery(this).next().slideToggle();
});
// new
function checkAddress() {
  if (jQuery('#oprcAddresses #checkoutBillto address').length == 0 && jQuery('#ignoreAddressCheck').length == 0) {
    oprcAddressMissing = 'true';
  }
  else {
    oprcAddressMissing = 'false';
  }
}
