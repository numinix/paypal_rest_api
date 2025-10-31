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

    window.paypalrApplePaySetPayload = setApplePayPayload;

    document.addEventListener('paypalr:applepay:payload', function (event) {
        setApplePayPayload(event.detail || {});
    });

    var container = document.getElementById('paypalr-applepay-button');
    if (container && container.innerHTML.trim() === '') {
        container.innerHTML = '<span class="paypalr-applepay-placeholder">' + (typeof paypalrApplePayText !== 'undefined' ? paypalrApplePayText : 'Apple Pay will be presented once you confirm your details.') + '</span>';
    }
})();
