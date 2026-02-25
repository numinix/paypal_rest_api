/**
 * Global callback function for PayPal's embedded signup flow.
 * 
 * This function is called by PayPal's partner.js when the seller completes
 * the signup flow in the mini-browser. PayPal passes the authCode and sharedId
 * which are needed to exchange for the seller's REST API credentials.
 *
 * @param {string} authCode - The authorization code for OAuth2 token exchange
 * @param {string} sharedId - The shared ID used as code_verifier in PKCE flow
 */
function paypalOnboardedCallback(authCode, sharedId) {
    console.log('[CALLBACK TEST] paypalOnboardedCallback called!', { authCode: authCode, sharedId: sharedId });
    // Dispatch a custom event that the main script can listen for
    var event = new CustomEvent('paypalOnboardingComplete', {
        detail: {
            authCode: authCode,
            sharedId: sharedId,
            source: 'partner_js_callback'
        }
    });
    window.dispatchEvent(event);
}

console.log('[CALLBACK TEST] paypalOnboardedCallback function defined and ready');
window.paypalOnboardedCallback = paypalOnboardedCallback; // Ensure it's global
