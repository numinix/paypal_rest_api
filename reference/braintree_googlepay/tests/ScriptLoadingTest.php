<?php
use PHPUnit\Framework\TestCase;

class ScriptLoadingTest extends TestCase {
    public function testGooglePayScriptLoadedFirstInCheckout() {
        // This test verifies that the Google Pay API script is loaded before Braintree libraries
        // to prevent race conditions on iOS Chrome
        
        // Read the actual module file and check the JavaScript code
        $moduleFile = file_get_contents(__DIR__ . '/../includes/modules/payment/braintree_googlepay.php');
        
        // Verify that the script array exists and contains the correct scripts
        $this->assertStringContainsString('const scripts = [', $moduleFile);
        $this->assertStringContainsString('"https://pay.google.com/gp/p/js/pay.js"', $moduleFile);
        $this->assertStringContainsString('"https://js.braintreegateway.com/web/3.133.0/js/client.min.js"', $moduleFile);
        $this->assertStringContainsString('"https://js.braintreegateway.com/web/3.133.0/js/google-payment.min.js"', $moduleFile);
        
        // Extract the section containing the scripts array
        $scriptsStart = strpos($moduleFile, 'function loadGooglePayScripts()');
        $scriptsEnd = strpos($moduleFile, 'function setup3DS(', $scriptsStart);
        $scriptsSection = substr($moduleFile, $scriptsStart, $scriptsEnd - $scriptsStart);
        
        // Find positions of each script in the scripts section
        $payGooglePos = strpos($scriptsSection, '"https://pay.google.com/gp/p/js/pay.js"');
        $clientPos = strpos($scriptsSection, '"https://js.braintreegateway.com/web/3.133.0/js/client.min.js"');
        $googlePaymentPos = strpos($scriptsSection, '"https://js.braintreegateway.com/web/3.133.0/js/google-payment.min.js"');
        
        // Assert that pay.google.com appears before the Braintree scripts
        $this->assertNotFalse($payGooglePos, 'Google Pay API script must be in the function');
        $this->assertNotFalse($clientPos, 'Braintree client script must be in the function');
        $this->assertNotFalse($googlePaymentPos, 'Braintree google-payment script must be in the function');
        
        $this->assertLessThan($clientPos, $payGooglePos, 
            'Google Pay API script must be loaded before Braintree client script');
        $this->assertLessThan($googlePaymentPos, $payGooglePos, 
            'Google Pay API script must be loaded before Braintree google-payment script');
        
        // Verify browser detection (now in global scope) and conditional loading strategy
        $this->assertStringContainsString('/CriOS/.test(ua)', $moduleFile, 
            'Must detect iOS Chrome using CriOS user agent check');
        $this->assertStringContainsString('if (isIOSChrome)', $scriptsSection, 
            'Must have conditional logic for iOS Chrome');
        
        // Verify iOS Chrome gets sequential loading to avoid race conditions
        $this->assertStringContainsString('scripts.reduce', $scriptsSection, 
            'iOS Chrome must use sequential loading with scripts.reduce');
        $this->assertStringContainsString('Using sequential loading for iOS Chrome', $scriptsSection, 
            'iOS Chrome must log sequential loading strategy');
        
        // Verify other browsers get full parallel loading
        $this->assertStringContainsString('Promise.all(scripts.map', $scriptsSection, 
            'Non-iOS Chrome browsers must use full parallel loading for all scripts');
        
        // Verify async attribute is set
        $this->assertStringContainsString('script.async = true', $scriptsSection,
            'Scripts must have async attribute set');
        
        // Verify scripts are appended to document.head
        $this->assertStringContainsString('document.head.appendChild(script)', $scriptsSection,
            'Scripts must be appended to document.head, not document.body');
        
        // Verify dataset.loaded is used for tracking
        $this->assertStringContainsString('dataset.loaded', $scriptsSection,
            'Script loading must be tracked using dataset.loaded');
    }
}
?>
