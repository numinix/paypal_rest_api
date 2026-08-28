/**
 * Keep wallet radios and branded buttons on one horizontal line for classic
 * Zen Cart checkout templates that render:
 *   <input type="radio" id="pmt-paypalac_…">
 *   <label class="radioButtonLabel" for="…">…button…</label>
 *   <br class="clearBoth">
 *
 * Also:
 * - Copies the theme's left indent from Credit Card (etc.) onto
 *   --paypalac-payment-indent (row padding), without hardcoding 20px.
 * - Forces wallet radios visible (undoes .paypalac-wallet-radio-hidden /
 *   display:none left by older hide paths).
 * - Clears stacked theme margins on nested wallet button wrappers.
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

    var MAX_INDENT_PX = 40;

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
        var capped = Math.max(0, Math.min(MAX_INDENT_PX, Math.round(valuePx)));
        var value = capped + 'px';
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
     * Match wallet rows to the theme's Credit Card / other payment column.
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

    /** Undo sr-only / display:none hide used by older wallet hide paths. */
    function ensureWalletRadioVisible(radio) {
        if (!radio) {
            return;
        }
        radio.classList.remove('paypalac-wallet-radio-hidden');
        radio.style.display = '';
        radio.style.position = '';
        radio.style.width = '';
        radio.style.height = '';
        radio.style.margin = '';
        radio.style.padding = '';
        radio.style.opacity = '';
        radio.style.visibility = '';
        radio.style.clip = '';
        radio.style.clipPath = '';
        radio.removeAttribute('aria-hidden');
        if (radio.tabIndex < 0) {
            radio.tabIndex = 0;
        }

        var label = document.querySelector('label[for="' + radio.id + '"]');
        if (label) {
            label.classList.remove('paypalac-wallet-label-hidden');
            if (label.style.display === 'none') {
                label.style.display = '';
            }
            label.removeAttribute('aria-hidden');
        }

        var control = radio.closest('.paypalac-wallet-radio-hidden-control');
        if (control) {
            control.classList.remove('paypalac-wallet-radio-hidden-control');
        }
    }

    function zeroNestedWalletMargins(label) {
        if (!label) {
            return;
        }
        var nodes = label.querySelectorAll('div, span, button, apple-pay-button');
        for (var i = 0; i < nodes.length; i++) {
            nodes[i].style.marginLeft = '0';
            nodes[i].style.marginRight = '0';
            nodes[i].style.paddingLeft = '0';
        }
    }

    function wrapRadioAndLabel(radio) {
        if (!radio) {
            return;
        }

        ensureWalletRadioVisible(radio);

        var label = document.querySelector('label[for="' + radio.id + '"]');
        if (!label) {
            return;
        }

        zeroNestedWalletMargins(label);

        if (radio.closest('.paypalac-wallet-payment-row')) {
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

    // Wallet SDKs may reflow / re-hide after init.
    setTimeout(alignWalletRadioRows, 0);
    setTimeout(alignWalletRadioRows, 250);
    setTimeout(alignWalletRadioRows, 1000);
    setTimeout(alignWalletRadioRows, 2500);
}());
