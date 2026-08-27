jQuery(document).ready(function() {
    if (jQuery('#pmt-paypalac').is(':checked')) {
        jQuery('input[name=payment][value=paypalac]').prop('checked', false).trigger('change');
    }
    jQuery('#pmt-paypalac').prop('disabled', true);
});