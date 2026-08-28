/**
 * Keep wallet radios and branded buttons on one horizontal line for classic
 * Zen Cart checkout templates that render:
 *   <input type="radio" id="pmt-paypalac_…">
 *   <label class="radioButtonLabel" for="…">…button…</label>
 *   <br class="clearBoth">
 * Themes often style labels as block / with large left margins so the button
 * drops under the radio. Wrapping the adjacent pair is more reliable than CSS
 * alone across custom checkouts (e.g. sts_next_level).
 */
(function () {
    'use strict';

    var WALLET_IDS = [
        'paypalac_applepay',
        'paypalac_googlepay',
        'paypalac_venmo',
        'paypalac_paylater'
    ];

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
