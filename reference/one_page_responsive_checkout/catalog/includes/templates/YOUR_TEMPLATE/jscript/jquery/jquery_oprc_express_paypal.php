<script type="text/javascript">
	jQuery(document).ready(function(){
		var paypalExpressCheckout = <?php echo (defined('MODULE_PAYMENT_PAYPALWPP_STATUS') && MODULE_PAYMENT_PAYPALWPP_STATUS == 'True') ? 'true' : 'false'; ?>;
		var paypalExpressCheckoutValue = 'paypalwpp';
		if(paypalExpressCheckout == true) {
			if(jQuery('input[name=payment]:checked').val() == paypalExpressCheckoutValue) {
				jQuery('input[name=payment]:checked').attr('checked', false);
			}
			jQuery('form[name=checkout_payment]').on('click', '#customPPECbutton', function(){
				blockPage();
		    	jQuery('.payment-method').each(function(){
		    		if(jQuery(this).find('input[name=payment]').val() == paypalExpressCheckoutValue){
		    			jQuery(this).find('input[name=payment]').attr('checked', true);
			    	}
			    });
				jQuery('form[name=checkout_payment]').submit();
			});
		}
	});
</script>