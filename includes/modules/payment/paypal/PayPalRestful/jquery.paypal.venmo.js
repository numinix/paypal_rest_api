(function () {
    function setVenmoPayload(payload) {
        var payloadField = document.getElementById('paypal-venmo-payload');
        if (payloadField) {
            try {
                payloadField.value = JSON.stringify(payload || {});
            } catch (error) {
                console.error('Unable to serialise Venmo payload', error);
                payloadField.value = '';
            }
        }

        var statusField = document.getElementById('paypal-venmo-status');
        if (statusField) {
            statusField.value = payload ? 'approved' : '';
        }
    }

    window.paypalVenmoSetPayload = setVenmoPayload;

    document.addEventListener('paypal:venmo:payload', function (event) {
        setVenmoPayload(event.detail || {});
    });

    var container = document.getElementById('paypal-venmo-button');
    if (container && container.innerHTML.trim() === '') {
        container.innerHTML = '<span class="paypal-venmo-placeholder">' + (typeof paypalVenmoText !== 'undefined' ? paypalVenmoText : 'Venmo will be presented once you confirm your details.') + '</span>';
    }
})();
