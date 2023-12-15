jQuery(document).ready(function() {
    function hidePprCcFields()
    {
        jQuery('.ppr-cc').each(function() {
            jQuery(this).hide().prop('disabled', true);
            jQuery(this).prev('label').hide();
            jQuery(this).next('br').hide();
        });
    }
    function showPprCcFields()
    {
        jQuery('.ppr-cc').each(function() {
            jQuery(this).show().prop('disabled', false);;
            jQuery(this).prev('label').show();
            jQuery(this).next('br').show();
        });
    }

    if (jQuery('#ppr-card').is(':not(:checked)')) {
        hidePprCcFields();
    }
    jQuery('input[name=payment], .ppr-choice').on('change', function() {
        if (jQuery('#pmt-paypalr').is(':not(:checked)') || jQuery('#ppr-card').is(':not(:checked)')) {
            hidePprCcFields();
        } else {
            showPprCcFields();
        }
        if (jQuery('#pmt-paypalr').is(':not(:checked)')) {
            jQuery('.ppr-choice').prop('checked', false);
        } else if (jQuery('#ppr-paypal').is(':not(:checked)') && jQuery('#ppr-card').is(':not(:checked)')) {
            jQuery('#ppr-paypal').prop('checked', true);
        }
    });
});