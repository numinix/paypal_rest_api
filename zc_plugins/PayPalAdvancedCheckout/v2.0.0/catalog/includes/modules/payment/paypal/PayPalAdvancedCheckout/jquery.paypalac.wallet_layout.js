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

    /** Branded wallet-button modules only (not creditcard / savedcard). */
    function isBrandedWalletRadio(el) {
        if (!el || !el.id) {
            return false;
        }
        for (var i = 0; i < WALLET_IDS.length; i++) {
            if (el.id === 'pmt-' + WALLET_IDS[i]) {
                return true;
            }
        }
        return false;
    }

    function setPaymentIndent(valuePx) {
        var value = Math.max(0, Math.round(valuePx)) + 'px';
        // Prefer innermost container first; set on every known host so nested
        // OPRC (#paymentMethodContainer inside fieldset.payment) stays in sync.
        var hosts = [
            document.querySelector('#paymentMethodContainer'),
            document.querySelector('#checkoutPayment fieldset.payment'),
            document.querySelector('fieldset.payment')
        ];
        var seen = [];
        for (var i = 0; i < hosts.length; i++) {
            var host = hosts[i];
            if (!host || seen.indexOf(host) !== -1) {
                continue;
            }
            seen.push(host);
            host.style.setProperty('--paypalac-payment-indent', value);
        }
    }

    /**
     * Match wallet radios to the theme's Credit Card / other payment column by
     * copying that radio's computed margin-left (e.g. sts_next_level's 20px).
     * Prefer paypalac_creditcard / savedcard / third-party methods — not the
     * branded wallet button radios themselves.
     */
    function syncPaymentIndentFromTheme() {
        var scope = document.querySelector('#paymentMethodContainer')
            || document.querySelector('#checkoutPayment fieldset.payment')
            || document.querySelector('fieldset.payment')
            || document;

        var radios = scope.querySelectorAll('input[type="radio"][name="payment"]');
        var sample = null;
        var preferred = ['pmt-paypalac_creditcard', 'pmt-paypalac_savedcard'];
        for (var p = 0; p < preferred.length && !sample; p++) {
            for (var i = 0; i < radios.length; i++) {
                if (radios[i].id === preferred[p]) {
                    sample = radios[i];
                    break;
                }
            }
        }
        for (var j = 0; j < radios.length && !sample; j++) {
            if (!isBrandedWalletRadio(radios[j])) {
                sample = radios[j];
            }
        }
        if (!sample) {
            setPaymentIndent(0);
            return;
        }

        var marginLeft = parseFloat(window.getComputedStyle(sample).marginLeft) || 0;
        setPaymentIndent(marginLeft);
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
