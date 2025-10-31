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

    window.paypalrVenmoSetPayload = setVenmoPayload;

    document.addEventListener('paypalr:venmo:payload', function (event) {
        setVenmoPayload(event.detail || {});
    });

    var container = document.getElementById('paypalr-venmo-button');
    if (container && container.innerHTML.trim() === '') {
        container.innerHTML = '<span class="paypalr-venmo-placeholder">' + (typeof paypalrVenmoText !== 'undefined' ? paypalrVenmoText : 'Venmo will be presented once you confirm your details.') + '</span>';
    }
})();
