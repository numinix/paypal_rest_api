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
});