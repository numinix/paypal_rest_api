(function () {
    function setApplePayPayload(payload) {
        var payloadField = document.getElementById('paypal-applepay-payload');
        if (payloadField) {
            try {
                payloadField.value = JSON.stringify(payload || {});
            } catch (error) {
                console.error('Unable to serialise Apple Pay payload', error);
                payloadField.value = '';
            }
        }

        var statusField = document.getElementById('paypal-applepay-status');
        if (statusField) {
            statusField.value = payload ? 'approved' : '';
        }
    }

    window.paypalApplePaySetPayload = setApplePayPayload;

    document.addEventListener('paypal:applepay:payload', function (event) {
        setApplePayPayload(event.detail || {});
    });

    var container = document.getElementById('paypal-applepay-button');
    if (container && container.innerHTML.trim() === '') {
        container.innerHTML = '<span class="paypal-applepay-placeholder">' + (typeof paypalApplePayText !== 'undefined' ? paypalApplePayText : 'Apple Pay will be presented once you confirm your details.') + '</span>';
    }
})();
