(function () {
    function setGooglePayPayload(payload) {
        var payloadField = document.getElementById('paypal-googlepay-payload');
        if (payloadField) {
            try {
                payloadField.value = JSON.stringify(payload || {});
            } catch (error) {
                console.error('Unable to serialise Google Pay payload', error);
                payloadField.value = '';
            }
        }

        var statusField = document.getElementById('paypal-googlepay-status');
        if (statusField) {
            statusField.value = payload ? 'approved' : '';
        }
    }

    window.paypalGooglePaySetPayload = setGooglePayPayload;

    document.addEventListener('paypal:googlepay:payload', function (event) {
        setGooglePayPayload(event.detail || {});
    });

    var container = document.getElementById('paypal-googlepay-button');
    if (container && container.innerHTML.trim() === '') {
        container.innerHTML = '<span class="paypal-googlepay-placeholder">' + (typeof paypalGooglePayText !== 'undefined' ? paypalGooglePayText : 'Google Pay will be presented once you confirm your details.') + '</span>';
    }
})();
