jQuery(document).ready(function() {
    // Flag to prevent interference when selecting sub-radios
    var selectingSubRadio = false;
    
    function hidePprCcFields()
    {
        jQuery('.ppr-cc').each(function() {
            jQuery(this).hide();
            jQuery(this).prev('label').hide();
            jQuery(this).next('br, div.p-2').hide();
        });
        jQuery('#paypalr_collects_onsite').val('');
    }
    function showPprCcFields()
    {
        jQuery('.ppr-cc').each(function() {
            jQuery(this).show();
            jQuery(this).prev('label').show();
            jQuery(this).next('br, div.p-2').show();
        });
        jQuery('#paypalr_collects_onsite').val(1);
    }

    function toggleNewCardFields(show)
    {
        jQuery('.ppr-card-new').each(function() {
            if (show) {
                jQuery(this).show();
                jQuery(this).prev('label').show();
                jQuery(this).next('br, div.p-2').show();
            } else {
                jQuery(this).hide();
                jQuery(this).prev('label').hide();
                jQuery(this).next('br, div.p-2').hide();
            }
        });
    }

    function toggleSavedCardFields(show)
    {
        jQuery('.ppr-card-saved').each(function() {
            if (show) {
                jQuery(this).show();
                jQuery(this).prev('label').show();
                jQuery(this).next('br, div.p-2').show();
            } else {
                jQuery(this).hide();
                jQuery(this).prev('label').hide();
                jQuery(this).next('br, div.p-2').hide();
            }
        });
    }

    function getSavedCardSelection()
    {
        var selected = jQuery('input[name="paypalr_saved_card"]:checked');
        if (selected.length === 0) {
            var first = jQuery('input[name="paypalr_saved_card"]').first();
            if (first.length === 0) {
                return 'new';
            }
            return first.val();
        }
        return selected.val();
    }

    function updateSavedCardVisibility()
    {
        var cardSelected = jQuery('#ppr-card').is(':checked');
        toggleSavedCardFields(cardSelected);
        if (cardSelected) {
            var savedChoice = getSavedCardSelection();
            if (savedChoice !== 'new') {
                toggleNewCardFields(false);
                jQuery('#paypalr_collects_onsite').val('');
                jQuery('#ppr-cc-save-card').prop('disabled', true);
                jQuery('#ppr-cc-sca-always').prop('disabled', true);
            } else {
                toggleNewCardFields(true);
                jQuery('#paypalr_collects_onsite').val(1);
                jQuery('#ppr-cc-save-card').prop('disabled', false);
                jQuery('#ppr-cc-sca-always').prop('disabled', false);
            }
        } else {
            toggleNewCardFields(false);
            jQuery('#paypalr_collects_onsite').val('');
            jQuery('#ppr-cc-save-card').prop('disabled', false);
            jQuery('#ppr-cc-sca-always').prop('disabled', false);
        }
    }

    // Initialize parent radio selection if sub-radios are checked
    if (jQuery('#pmt-paypalr').is(':radio') && jQuery('#pmt-paypalr').is(':not(:checked)')) {
        // Check if any sub-radio is selected
        if (jQuery('#ppr-paypal').is(':checked') || jQuery('#ppr-card').is(':checked')) {
            // If a sub-radio is selected, select the parent radio
            jQuery('#pmt-paypalr').prop('checked', true);
        } else {
            // If no sub-radio is selected, ensure they're unchecked
            jQuery('#ppr-paypal, #ppr-card').prop('checked', false);
        }
    } else if (jQuery('#pmt-paypalr').is(':not(:radio)')) {
        // If pmt-paypalr is not a radio (only payment method), default to PayPal
        jQuery('#ppr-paypal').prop('checked', true);
    }
    
    // Handle initial credit card field visibility
    if (jQuery('#ppr-card').is(':checked')) {
        showPprCcFields();
    } else {
        hidePprCcFields();
    }
    updateSavedCardVisibility();

    jQuery('input[name=payment]').on('change', function() {
        if (jQuery('#pmt-paypalr').is(':not(:checked)')) {
            jQuery('#ppr-paypal, #ppr-card').prop('checked', false);
        } else if (!selectingSubRadio && jQuery('#ppr-paypal').is(':not(:checked)') && jQuery('#ppr-card').is(':not(:checked)')) {
            // Only auto-select ppr-paypal if we're not in the middle of selecting a sub-radio
            jQuery('#ppr-paypal').prop('checked', true);
        }
        if (jQuery('#ppr-card').is(':checked')) {
            showPprCcFields();
            updateSavedCardVisibility();
        } else {
            hidePprCcFields();
            updateSavedCardVisibility();
        }
    });

    // Handle mousedown events to ensure parent radio is selected BEFORE the sub-radio changes
    // This ensures the main payment radio is selected before any validation occurs
    jQuery('#ppr-paypal, #ppr-card').on('mousedown', function() {
        selectingSubRadio = true;
        if (jQuery('#pmt-paypalr').is(':radio') && jQuery('#pmt-paypalr').is(':not(:checked)')) {
            jQuery('#pmt-paypalr').prop('checked', true).trigger('change');
        }
        // Clear the flag after a short delay to allow the click to complete
        setTimeout(function() {
            selectingSubRadio = false;
        }, 50);
    });

    // Handle mousedown on labels as well to cover all click scenarios
    jQuery('label[for="ppr-paypal"], label[for="ppr-card"]').on('mousedown', function() {
        selectingSubRadio = true;
        if (jQuery('#pmt-paypalr').is(':radio') && jQuery('#pmt-paypalr').is(':not(:checked)')) {
            jQuery('#pmt-paypalr').prop('checked', true).trigger('change');
        }
        // Clear the flag after a short delay to allow the click to complete
        setTimeout(function() {
            selectingSubRadio = false;
        }, 50);
    });

    jQuery('#ppr-paypal, #ppr-card').on('change', function() {
        // When a sub-radio (PayPal Wallet or Credit Card) is selected,
        // ensure the parent payment module radio is also selected
        if (jQuery('#pmt-paypalr').is(':radio') && jQuery('#pmt-paypalr').is(':not(:checked)')) {
            jQuery('#pmt-paypalr').prop('checked', true).trigger('change');
        }
        if (jQuery('#ppr-card').is(':checked')) {
            showPprCcFields();
            updateSavedCardVisibility();
        } else {
            hidePprCcFields();
            updateSavedCardVisibility();
        }
    });

    // Also handle click events to ensure parent radio is selected when user clicks sub-radios
    jQuery('#ppr-paypal, #ppr-card').on('click', function() {
        if (jQuery('#pmt-paypalr').is(':radio') && jQuery('#pmt-paypalr').is(':not(:checked)')) {
            jQuery('#pmt-paypalr').prop('checked', true).trigger('change');
        }
    });

    // Handle clicks on the labels for the sub-radios as well
    jQuery('label[for="ppr-paypal"], label[for="ppr-card"]').on('click', function() {
        if (jQuery('#pmt-paypalr').is(':radio') && jQuery('#pmt-paypalr').is(':not(:checked)')) {
            jQuery('#pmt-paypalr').prop('checked', true).trigger('change');
        }
    });

    jQuery(document).on('change', 'input[name="paypalr_saved_card"]', function() {
        if (jQuery('#ppr-card').is(':checked')) {
            updateSavedCardVisibility();
        }
    });

    // When user clicks on any credit card input field, select the PayPal payment method and card option
    jQuery(document).on('focus click', '.ppr-card-new input, .ppr-card-new select', function(event) {
        // First, ensure the parent payment module is selected
        if (jQuery('#pmt-paypalr').is(':radio') && jQuery('#pmt-paypalr').is(':not(:checked)')) {
            jQuery('#pmt-paypalr').prop('checked', true).trigger('change');
        }
        
        // Then, ensure the card option is selected
        if (jQuery('#ppr-card').length && jQuery('#ppr-card').is(':not(:checked)')) {
            jQuery('#ppr-card').prop('checked', true).trigger('change');
        }
    });

    // Handle browser autofill events which trigger 'change' and 'input' events
    // This ensures the parent radio stays checked when autofill populates credit card fields
    jQuery(document).on('change input', '.ppr-card-new input, .ppr-card-new select', function(event) {
        // Only proceed if this field has a value (autofill happened)
        if (jQuery(this).val()) {
            // Ensure the parent payment module is selected
            if (jQuery('#pmt-paypalr').is(':radio') && jQuery('#pmt-paypalr').is(':not(:checked)')) {
                jQuery('#pmt-paypalr').prop('checked', true).trigger('change');
            }
            
            // Ensure the card option is selected
            if (jQuery('#ppr-card').length && jQuery('#ppr-card').is(':not(:checked)')) {
                jQuery('#ppr-card').prop('checked', true).trigger('change');
            }
        }
    });

    updateSavedCardVisibility();

    var $checkoutForm = jQuery('form[name="checkout_payment"]');
    var $paypalButton = jQuery('#ppr-choice-paypal .ppr-choice-label');

    if (!$paypalButton.length) {
        $paypalButton = jQuery('label.payment-method-item-label[for="pmt-paypalr"] img');
    }

    if (!$paypalButton.length) {
        $paypalButton = jQuery('label.payment-method-item-label[for="pmt-paypalr"]');
    }

    function paypalWalletIsSelected()
    {
        var $paypalRadio = jQuery('#ppr-paypal');
        if ($paypalRadio.length && $paypalRadio.is(':radio')) {
            return $paypalRadio.is(':checked');
        }

        var $pprTypeInputs = jQuery('input[name="ppr_type"]');
        if (!$pprTypeInputs.length) {
            return true;
        }

        var $checkedInput = $pprTypeInputs.filter(':checked');
        if ($checkedInput.length) {
            return $checkedInput.val() === 'paypal';
        }

        var paypalHidden = $pprTypeInputs.filter(function() {
            var inputType = (jQuery(this).attr('type') || '').toLowerCase();
            return inputType === 'hidden' && jQuery(this).val() === 'paypal';
        });

        return paypalHidden.length > 0;
    }

    if ($checkoutForm.length && $paypalButton.length) {
        $paypalButton.on('click', function() {
            window.setTimeout(function() {
                if (!paypalWalletIsSelected()) {
                    return;
                }

                var $moduleRadio = jQuery('#pmt-paypalr');
                var moduleSelected =
                    !$moduleRadio.length || !$moduleRadio.is(':radio') || $moduleRadio.is(':checked');

                if (moduleSelected) {
                    if (typeof window.oprcShowProcessingOverlay === 'function') {
                        window.oprcShowProcessingOverlay();
                    }

                    var formElement = $checkoutForm.get(0);
                    if (!formElement) {
                        return;
                    }

                    var previousAllowState = typeof window.oprcAllowNativeCheckoutSubmit !== 'undefined' 
                        ? window.oprcAllowNativeCheckoutSubmit 
                        : false;
                    window.oprcAllowNativeCheckoutSubmit = true;

                    try {
                        if (typeof formElement.requestSubmit === 'function') {
                            formElement.requestSubmit();
                        } else if (typeof formElement.submit === 'function') {
                            formElement.submit();
                        }
                    } finally {
                        window.oprcAllowNativeCheckoutSubmit = previousAllowState;
                    }
                }
            }, 0);
        });
    }

    var $ccNumberInput = jQuery('#paypalr-cc-number');
    if ($ccNumberInput.length) {
        function getCardGrouping(digits)
        {
            if (/^3[47]/.test(digits)) {
                return [4, 6, 5];
            }
            if (/^3(?:0[0-5]|[68])/.test(digits)) {
                return [4, 6, 4];
            }
            return [4, 4, 4, 4, 3];
        }

        function formatCardNumber(digits)
        {
            var cleaned = digits.replace(/\D/g, '').substring(0, 19);
            var grouping = getCardGrouping(cleaned);
            var formatted = [];
            var position = 0;

            for (var i = 0; i < grouping.length && position < cleaned.length; i++) {
                var groupSize = grouping[i];
                if (groupSize <= 0) {
                    break;
                }
                formatted.push(cleaned.substr(position, groupSize));
                position += groupSize;
            }

            if (position < cleaned.length) {
                formatted.push(cleaned.substr(position));
            }

            return formatted.join(' ');
        }

        function digitsBeforeCaret(value, caretPosition)
        {
            var digitsCount = 0;
            for (var i = 0; i < Math.min(caretPosition, value.length); i++) {
                if (/\d/.test(value.charAt(i))) {
                    digitsCount++;
                }
            }
            return digitsCount;
        }

        function caretFromDigits(value, digitsCount)
        {
            if (digitsCount <= 0) {
                return 0;
            }

            var digitsSeen = 0;
            for (var i = 0; i < value.length; i++) {
                if (/\d/.test(value.charAt(i))) {
                    digitsSeen++;
                    if (digitsSeen === digitsCount) {
                        return i + 1;
                    }
                }
            }

            return value.length;
        }

        function applyFormattedValue(input)
        {
            var currentValue = input.value;
            var caretStart = input.selectionStart || 0;
            var digitsCount = digitsBeforeCaret(currentValue, caretStart);
            var formattedValue = formatCardNumber(currentValue);

            input.value = formattedValue;

            if (document.activeElement === input && typeof input.setSelectionRange === 'function') {
                var newCaret = caretFromDigits(formattedValue, digitsCount);
                input.setSelectionRange(newCaret, newCaret);
            }
        }

        $ccNumberInput.on('input', function() {
            applyFormattedValue(this);
        });

        $ccNumberInput.on('blur', function() {
            this.value = formatCardNumber(this.value);
        });

        jQuery('form[name="checkout_payment"]').on('submit', function() {
            if ($ccNumberInput.length) {
                $ccNumberInput.val($ccNumberInput.val().replace(/\D/g, '').substring(0, 19));
            }
        });

        applyFormattedValue($ccNumberInput.get(0));
    }
});
