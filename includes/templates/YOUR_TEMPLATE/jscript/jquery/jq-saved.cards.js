(function($) {
  'use strict';

  if (typeof $ === 'undefined') {
    return;
  }

  $(function() {
    $('[data-saved-card-toggle]').each(function() {
      var $toggle = $(this);
      var targetId = $toggle.data('target');
      var $target = $('#' + targetId);

      if ($target.length === 0) {
        return;
      }

      $toggle.on('click', function(event) {
        event.preventDefault();

        var isExpanded = $toggle.attr('aria-expanded') === 'true';
        var labelCollapsed = $toggle.data('label-collapsed');
        var labelExpanded = $toggle.data('label-expanded');

        $toggle.attr('aria-expanded', (!isExpanded).toString());
        $toggle.text(isExpanded ? labelCollapsed : labelExpanded);
        $target.toggleClass('is-collapsed', isExpanded);
      });
    });

    var addressToggleSelector = '[data-address-toggle]';
    if ($(addressToggleSelector).length) {
      var refreshAddressSections = function() {
        var selected = $(addressToggleSelector + ':checked');
        if (!selected.length) {
          selected = $(addressToggleSelector + ':not(:disabled)').first();
        }
        var activeValue = selected.data('address-toggle');

        $('[data-address-target]').each(function() {
          var $section = $(this);
          var targetValue = $section.data('address-target');
          if (targetValue === activeValue) {
            $section.removeClass('d-none');
          } else {
            $section.addClass('d-none');
          }
        });
      };

      $(document).on('change', addressToggleSelector, refreshAddressSections);

      refreshAddressSections();
    }

    // Handle add card form address toggles
    var addAddressToggleSelector = '[data-add-address-toggle]';
    if ($(addAddressToggleSelector).length) {
      var refreshAddCardAddressSections = function() {
        var selected = $(addAddressToggleSelector + ':checked');
        if (!selected.length) {
          selected = $(addAddressToggleSelector + ':not(:disabled)').first();
        }
        var activeValue = selected.data('add-address-toggle');

        $('[data-add-address-target]').each(function() {
          var $section = $(this);
          var targetValue = $section.data('add-address-target');
          if (targetValue === activeValue) {
            $section.removeClass('d-none');
          } else {
            $section.addClass('d-none');
          }
        });
      };

      $(document).on('change', addAddressToggleSelector, refreshAddCardAddressSections);

      refreshAddCardAddressSections();
    }

    // Handle PayPal Advanced Card Fields for adding cards
    var $addCardForm = $('#add-card-form');
    if ($addCardForm.length && typeof paypal !== 'undefined') {
      var initializeCardFields = function() {
        var $fieldsContainer = $('#card-fields-container');
        var $submitBtn = $('#submit-card-btn');
        var $setupTokenInput = $('#setup_token_id');
        var $loadingMsg = $('#card-fields-loading');

        // Get PayPal client ID from the module configuration
        var clientId = window.PAYPAL_CLIENT_ID || '';
        if (!clientId) {
          $loadingMsg.text('PayPal configuration error. Please contact support.');
          return;
        }

        // Initialize PayPal Card Fields
        if (!paypal.CardFields) {
          $loadingMsg.text('PayPal Card Fields not available. Please refresh the page.');
          return;
        }

        var cardField = paypal.CardFields({
          style: {
            'input': {
              'font-size': '16px',
              'font-family': '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif',
              'color': '#495057'
            },
            ':focus': {
              'color': '#212529'
            }
          },
          createVaultSetupToken: function(data, actions) {
            // Get billing address from form
            var billingAddress = getBillingAddressFromForm();
            if (!billingAddress) {
              alert('Please fill in the billing address.');
              return Promise.reject(new Error('Billing address required'));
            }

            // Create setup token via AJAX
            return fetch('ppr_add_card.php', {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json'
              },
              body: JSON.stringify({
                action: 'create_setup_token',
                billing_address: billingAddress
              })
            })
            .then(function(response) {
              return response.json();
            })
            .then(function(data) {
              if (data.success && data.setup_token_id) {
                return data.setup_token_id;
              }
              throw new Error(data.message || 'Failed to create setup token');
            });
          },
          onApprove: function(data) {
            // Store the setup token ID and submit the form
            $setupTokenInput.val(data.vaultSetupToken);
            $addCardForm.off('submit').submit();
          },
          onError: function(err) {
            console.error('PayPal Card Fields error:', err);
            alert('An error occurred while processing your card. Please try again.');
            $submitBtn.prop('disabled', false).text($submitBtn.data('original-text'));
          }
        });

        // Check if card fields are eligible
        if (cardField.isEligible()) {
          // Clear loading message
          $fieldsContainer.html('');

          // Create container for card number
          var $cardNumberContainer = $('<div class="mb-3"></div>');
          $cardNumberContainer.append('<label class="form-label">Card Number</label>');
          var $cardNumberField = $('<div id="card-number-field" class="form-control" style="height: auto; padding: 8px;"></div>');
          $cardNumberContainer.append($cardNumberField);
          $fieldsContainer.append($cardNumberContainer);

          // Create container for expiry
          var $expiryContainer = $('<div class="row g-2 mb-3"></div>');
          var $expiryCol = $('<div class="col-md-6"></div>');
          $expiryCol.append('<label class="form-label">Expiration Date</label>');
          var $expiryField = $('<div id="card-expiry-field" class="form-control" style="height: auto; padding: 8px;"></div>');
          $expiryCol.append($expiryField);
          
          // Create container for CVV
          var $cvvCol = $('<div class="col-md-6"></div>');
          $cvvCol.append('<label class="form-label">Security Code (CVV)</label>');
          var $cvvField = $('<div id="card-cvv-field" class="form-control" style="height: auto; padding: 8px;"></div>');
          $cvvCol.append($cvvField);
          
          $expiryContainer.append($expiryCol).append($cvvCol);
          $fieldsContainer.append($expiryContainer);

          // Create container for cardholder name
          var $nameContainer = $('<div class="mb-3"></div>');
          $nameContainer.append('<label class="form-label">Name on Card</label>');
          var $nameField = $('<div id="card-name-field" class="form-control" style="height: auto; padding: 8px;"></div>');
          $nameContainer.append($nameField);
          $fieldsContainer.append($nameContainer);

          // Render the card fields
          cardField.NameField().render('#card-name-field');
          cardField.NumberField().render('#card-number-field');
          cardField.ExpiryField().render('#card-expiry-field');
          cardField.CVVField().render('#card-cvv-field');

          // Enable submit button
          $submitBtn.prop('disabled', false);
          $submitBtn.data('original-text', $submitBtn.text());
        } else {
          $loadingMsg.text('Card fields are not available. Please contact support.');
        }

        // Handle form submission
        $addCardForm.on('submit', function(event) {
          // If setup token is already set, allow normal submission
          if ($setupTokenInput.val()) {
            return true;
          }

          // Prevent default and trigger PayPal card submission
          event.preventDefault();
          $submitBtn.prop('disabled', true).text('Processing...');
          
          cardField.submit()
            .catch(function(err) {
              console.error('Card submission error:', err);
              $submitBtn.prop('disabled', false).text($submitBtn.data('original-text'));
            });
        });
      };

      // Helper function to get billing address from form
      var getBillingAddressFromForm = function() {
        var addressMode = $('input[name="add_address_mode"]:checked').val();
        var billingAddress = {};

        if (addressMode === 'existing') {
          var addressBookId = $('#add_address_book_id').val();
          if (!addressBookId) {
            return null;
          }
          // Need to get the address data from the selected address book entry
          // For simplicity, we'll use the new address fields approach
          // In production, you'd fetch the address via AJAX or embed it in the page
          return null; // This will trigger the new address path
        }

        // Get new address fields
        var street1 = $('#add_new_street_address').val().trim();
        var street2 = $('#add_new_street_address_2').val().trim();
        var city = $('#add_new_city').val().trim();
        var state = $('#add_new_state').val().trim();
        var postcode = $('#add_new_postcode').val().trim();
        var countryId = $('#add_new_country').val();

        if (!street1 || !city || !postcode || !countryId) {
          return null;
        }

        // Get country code from the select option
        var countryCode = $('#add_new_country option:selected').attr('data-iso2');
        if (!countryCode) {
          // Fallback: try to get from a data attribute or default to US
          countryCode = 'US';
        }

        billingAddress = {
          address_line_1: street1,
          admin_area_2: city,
          postal_code: postcode,
          country_code: countryCode
        };

        if (street2) {
          billingAddress.address_line_2 = street2;
        }
        if (state) {
          billingAddress.admin_area_1 = state;
        }

        return billingAddress;
      };

      // Initialize when PayPal SDK is ready
      if (typeof paypal !== 'undefined' && paypal.CardFields) {
        initializeCardFields();
      } else {
        $('#card-fields-loading').text('Loading PayPal card fields...');
        // Wait for PayPal SDK to load
        var checkPayPal = setInterval(function() {
          if (typeof paypal !== 'undefined' && paypal.CardFields) {
            clearInterval(checkPayPal);
            initializeCardFields();
          }
        }, 100);
        // Timeout after 10 seconds
        setTimeout(function() {
          clearInterval(checkPayPal);
          if (!paypal || !paypal.CardFields) {
            $('#card-fields-loading').text('Failed to load PayPal. Please refresh the page.');
          }
        }, 10000);
      }
    }
  });
})(window.jQuery);
