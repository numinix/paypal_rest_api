/**
 * Keep wallet radios and branded buttons on one horizontal line for classic
 * Zen Cart checkout templates that render:
 *   <input type="radio" id="pmt-paypalac_…">
 *   <label class="radioButtonLabel" for="…">…button…</label>
 *   <br class="clearBoth">
 * Themes often style labels as block / with large left margins so the button
 * drops under the radio. Wrapping the adjacent pair is more reliable than CSS
 * alone across custom checkouts (e.g. sts_next_level).
 *
 * Also copies the theme's left indent from a non-wallet payment radio onto
 * --paypalac-payment-indent so PayPal-family radios line up with Credit Card
 * without hardcoding 20px (sts_next_level fieldset margin-left).
 */
(function () {
    'use strict';

    var WALLET_IDS = [
        'paypalac',
        'paypalac_applepay',
        'paypalac_googlepay',
        'paypalac_venmo',
        'paypalac_paylater'
    ];

    function isWalletPaymentRadio(el) {
        if (!el || !el.id) {
            return false;
        }
        return el.id.indexOf('pmt-paypalac') === 0;
    }

    function findIndentHost() {
        return document.querySelector('#checkoutPayment fieldset.payment')
            || document.querySelector('fieldset.payment')
            || document.querySelector('#paymentMethodContainer');
    }

    /**
     * Match wallet radios to the theme's Credit Card / other payment column by
     * copying that radio's computed margin-left (e.g. sts_next_level's 20px).
     * Do not use getBoundingClientRect offsets — shared container padding would
     * double-count once we also apply margin-left on the wallet radios.
     */
    function syncPaymentIndentFromTheme() {
        var host = findIndentHost();
        if (!host) {
            return;
        }

        var radios = host.querySelectorAll('input[type="radio"][name="payment"]');
        var sample = null;
        for (var i = 0; i < radios.length; i++) {
            if (!isWalletPaymentRadio(radios[i])) {
                sample = radios[i];
                break;
            }
        }
        if (!sample) {
            host.style.setProperty('--paypalac-payment-indent', '0px');
            return;
        }

        var marginLeft = parseFloat(window.getComputedStyle(sample).marginLeft) || 0;
        host.style.setProperty('--paypalac-payment-indent', Math.max(0, Math.round(marginLeft)) + 'px');
    }

    function wrapRadioAndLabel(radio) {
        if (!radio || radio.closest('.paypalac-wallet-payment-row')) {
            return;
        }

        var label = document.querySelector('label[for="' + radio.id + '"]');
        if (!label) {
            return;
        }

        var parent = radio.parentNode;
        if (!parent || label.parentNode !== parent) {
            return;
        }

        var row = document.createElement('span');
        row.className = 'paypalac-wallet-payment-row';
        parent.insertBefore(row, radio);
        row.appendChild(radio);
        row.appendChild(label);
    }

    function alignWalletRadioRows() {
        syncPaymentIndentFromTheme();
        for (var i = 0; i < WALLET_IDS.length; i++) {
            wrapRadioAndLabel(document.getElementById('pmt-' + WALLET_IDS[i]));
        }
    }

    window.paypalacAlignWalletRadioRows = alignWalletRadioRows;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', alignWalletRadioRows);
    } else {
        alignWalletRadioRows();
    }

    // Wallet SDKs may reflow the button containers after init.
    setTimeout(alignWalletRadioRows, 0);
    setTimeout(alignWalletRadioRows, 250);
    setTimeout(alignWalletRadioRows, 1000);
}());
