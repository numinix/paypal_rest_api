(function () {
    function setGooglePayPayload(payload) {
        var payloadField = document.getElementById('paypalr-googlepay-payload');
        if (payloadField) {
            try {
                payloadField.value = JSON.stringify(payload || {});
            } catch (error) {
                console.error('Unable to serialise Google Pay payload', error);
                payloadField.value = '';
            }
        }

        var statusField = document.getElementById('paypalr-googlepay-status');
        if (statusField) {
            statusField.value = payload ? 'approved' : '';
        }
    }

    window.paypalrGooglePaySetPayload = setGooglePayPayload;

    document.addEventListener('paypalr:googlepay:payload', function (event) {
        setGooglePayPayload(event.detail || {});
    });

    var container = document.getElementById('paypalr-googlepay-button');
    if (container && container.innerHTML.trim() === '') {
        container.innerHTML = '<span class="paypalr-googlepay-placeholder">' + (typeof paypalrGooglePayText !== 'undefined' ? paypalrGooglePayText : 'Google Pay will be presented once you confirm your details.') + '</span>';
    }
})();
