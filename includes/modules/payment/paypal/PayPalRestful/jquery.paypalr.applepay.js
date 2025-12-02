(function () {
    function setApplePayPayload(payload) {
        var payloadField = document.getElementById('paypalr-applepay-payload');
        if (payloadField) {
            try {
                payloadField.value = JSON.stringify(payload || {});
            } catch (error) {
                console.error('Unable to serialise Apple Pay payload', error);
                payloadField.value = '';
            }
        }

        var statusField = document.getElementById('paypalr-applepay-status');
        if (statusField) {
            statusField.value = payload ? 'approved' : '';
        }
    }

    /**
     * Select the Apple Pay module radio button.
     * Called when the user interacts with the Apple Pay button.
     */
    function selectApplePayRadio() {
        var moduleRadio = document.getElementById('pmt-paypalr_applepay');
        if (moduleRadio && moduleRadio.type === 'radio' && !moduleRadio.checked) {
            moduleRadio.checked = true;
            // Trigger change event for any listeners
            if (typeof jQuery !== 'undefined') {
                jQuery(moduleRadio).trigger('change');
            } else {
                moduleRadio.dispatchEvent(new Event('change', { bubbles: true }));
            }
        }
    }

    /**
     * Hide the module's radio button using CSS.
     * The radio is still functional but visually hidden.
     */
    function hideModuleRadio() {
        var moduleRadio = document.getElementById('pmt-paypalr_applepay');
        if (moduleRadio) {
            moduleRadio.classList.add('paypalr-wallet-radio-hidden');
        }
    }

    window.paypalrApplePaySetPayload = setApplePayPayload;
    window.paypalrApplePaySelectRadio = selectApplePayRadio;

    document.addEventListener('paypalr:applepay:payload', function (event) {
        setApplePayPayload(event.detail || {});
    });

    // Hide the radio button on page load
    hideModuleRadio();

    // Add click handler to the button container to select the radio
    var container = document.getElementById('paypalr-applepay-button');
    if (container) {
        container.addEventListener('click', function() {
            selectApplePayRadio();
        });
        
        if (container.innerHTML.trim() === '') {
            container.innerHTML = '<span class="paypalr-applepay-placeholder">' + (typeof paypalrApplePayText !== 'undefined' ? paypalrApplePayText : 'Apple Pay will be presented once you confirm your details.') + '</span>';
        }
    }
})();
