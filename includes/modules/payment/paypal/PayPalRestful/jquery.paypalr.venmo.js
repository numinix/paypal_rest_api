(function () {
    function setVenmoPayload(payload) {
        var payloadField = document.getElementById('paypalr-venmo-payload');
        if (payloadField) {
            try {
                payloadField.value = JSON.stringify(payload || {});
            } catch (error) {
                console.error('Unable to serialise Venmo payload', error);
                payloadField.value = '';
            }
        }

        var statusField = document.getElementById('paypalr-venmo-status');
        if (statusField) {
            statusField.value = payload ? 'approved' : '';
        }
    }

    /**
     * Select the Venmo module radio button.
     * Called when the user interacts with the Venmo button.
     */
    function selectVenmoRadio() {
        var moduleRadio = document.getElementById('pmt-paypalr_venmo');
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
        var moduleRadio = document.getElementById('pmt-paypalr_venmo');
        if (moduleRadio) {
            moduleRadio.classList.add('paypalr-wallet-radio-hidden');
        }
    }

    window.paypalrVenmoSetPayload = setVenmoPayload;
    window.paypalrVenmoSelectRadio = selectVenmoRadio;

    document.addEventListener('paypalr:venmo:payload', function (event) {
        setVenmoPayload(event.detail || {});
    });

    // Hide the radio button on page load
    hideModuleRadio();

    // Add click handler to the button container to select the radio
    var container = document.getElementById('paypalr-venmo-button');
    if (container) {
        container.addEventListener('click', function() {
            selectVenmoRadio();
        });
        
        if (container.innerHTML.trim() === '') {
            container.innerHTML = '<span class="paypalr-venmo-placeholder">' + (typeof paypalrVenmoText !== 'undefined' ? paypalrVenmoText : 'Venmo will be presented once you confirm your details.') + '</span>';
        }
    }
})();
