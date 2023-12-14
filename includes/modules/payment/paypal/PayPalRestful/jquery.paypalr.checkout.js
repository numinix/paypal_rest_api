jQuery(document).ready(function() {
    if (jQuery('#pmt-paypalr, #ppr-card').is(':not(:checked)')) {
        jQuery('.ppr-cc').each(function() {
            jQuery(this).hide();
            jQuery(this).prev('label').hide();
            jQuery(this).next('br').hide();
        });
    }
    jQuery('#pmt-paypalr, .ppr-choice').on('change', function() {
        if (jQuery('#pmt-paypalr, #ppr-card').is(':not(:checked)')) {
            jQuery('.ppr-cc').each(function() {
                jQuery(this).hide();
                jQuery(this).prev('label').hide();
                jQuery(this).next('br').hide();
            });
        } else {
            jQuery('.ppr-cc').each(function() {
                jQuery(this).show();
                jQuery(this).prev('label').show();
                jQuery(this).next('br').show();
            });
        }
    });
});