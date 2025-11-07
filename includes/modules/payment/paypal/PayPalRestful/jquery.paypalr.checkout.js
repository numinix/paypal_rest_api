jQuery(document).ready(function() {
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

    if (jQuery('#pmt-paypalr').is(':not(:checked)') || jQuery('#ppr-card').is(':not(:checked)')) {
        hidePprCcFields();
        if (jQuery('#pmt-paypalr').is(':not(:checked)') && jQuery('#pmt-paypalr').is(':radio')) {
            jQuery('#ppr-paypal, #ppr-card').prop('checked', false);
        } else if (jQuery('#pmt-paypalr').is(':not(:radio)')) {
            jQuery('#ppr-paypal').prop('checked', true);
        }
        updateSavedCardVisibility();
    }

    jQuery('input[name=payment]').on('change', function() {
        if (jQuery('#pmt-paypalr').is(':not(:checked)')) {
            jQuery('#ppr-paypal, #ppr-card').prop('checked', false);
        } else if (jQuery('#ppr-paypal').is(':not(:checked)') && jQuery('#ppr-card').is(':not(:checked)')) {
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

    jQuery('#ppr-paypal, #ppr-card').on('change', function() {
        if (jQuery('#pmt-paypalr').is(':not(:checked)') && jQuery('#pmt-paypalr').is(':radio')) {
            jQuery('input[name=payment]').prop('checked', false);
            jQuery('input[name=payment][value=paypalr]').prop('checked', true).trigger('change');
        }
        if (jQuery('#ppr-card').is(':checked')) {
            showPprCcFields();
            updateSavedCardVisibility();
        } else {
            hidePprCcFields();
            updateSavedCardVisibility();
        }
    });

    jQuery(document).on('change', 'input[name="paypalr_saved_card"]', function() {
        if (jQuery('#ppr-card').is(':checked')) {
            updateSavedCardVisibility();
        }
    });

    updateSavedCardVisibility();

    var $checkoutForm = jQuery('form[name="checkout_payment"]');
    var $paypalButton = jQuery('#ppr-choice-paypal .ppr-choice-label');

    if ($checkoutForm.length && $paypalButton.length) {
        $paypalButton.on('click', function() {
            window.setTimeout(function() {
                if (!jQuery('#ppr-paypal').is(':checked')) {
                    return;
                }

                var $moduleRadio = jQuery('#pmt-paypalr');
                var moduleSelected =
                    !$moduleRadio.length || !$moduleRadio.is(':radio') || $moduleRadio.is(':checked');

                if (moduleSelected) {
                    $checkoutForm.trigger('submit');
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
