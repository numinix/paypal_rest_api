 jQuery(function() {
 	// ---------------
	// vars
	var wrapEntry  	  = jQuery( '#js-address_entry '),
		selectAddress = jQuery( '#js-address_book' ),
		formAddress	  = jQuery( '#js-form_credit_card'),
		cIsHidden	  = 'nmx-hidden';

	var $ccNumber = jQuery( '#js-cc_number' ),
			$ccFlag 	= jQuery( '#js-flags' ),
			cIsActive = 'nmx-active';

	// ---------------
	// credit card validation
	// card masks
	VMasker($ccNumber).maskPattern("9999 9999 9999 9999");

	// card detection
	if($ccNumber.length) {
		// validate
		$ccNumber.validateCreditCard(function(result) {
				var cardFlagName = (result.card_type == null ? '' : result.card_type.name);
				if (cardFlagName !== '') {
					if ($ccFlag.find( '.' + cIsActive ).length) {
						// unhighlight card
						unactiveCardFlag();
					}
					// highlight card
					activeCardFlag(cardFlagName);
        } else {
        	// unhighlight card
					unactiveCardFlag();
        }
    	}, { 
    		accept: ['visa', 'mastercard'] 
    	}
    )

    function unactiveCardFlag() {
    	$ccFlag
    		.find( '.' + cIsActive )
    		.removeClass( cIsActive );
    }

    function activeCardFlag(cardFlagName) {
    	$ccFlag
    		.find( '.' + cardFlagName )
    		.addClass( cIsActive );
    }

    function toTitleCase(str) {
	    return str.replace(/\w\S*/g, function(txt){return txt.charAt(0).toUpperCase() + txt.substr(1).toLowerCase();});
		}
	}

	// ---------------
	// fields validations (not credit card)
	$.validate({
		form : formAddress
	});

	// ---------------
	// address form
	selectAddress.on( 'change', function(e){
		// console.log( '1' );
		show_hide_address();
	});

	function show_hide_address() {
		console.log(selectAddress.val());
		if(selectAddress.val() === "0"){
		  wrapEntry.removeClass( cIsHidden );
		}
		else {
		  wrapEntry.addClass( cIsHidden);
		}
	}

});