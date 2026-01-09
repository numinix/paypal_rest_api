<?php
use PHPUnit\Framework\TestCase;

class IOSChromeParseResponseTest extends TestCase {
    public function testIOSChromeParseResponseHandling() {
        // This test verifies that paymentData is properly handled for iOS Chrome
        // to prevent the "Unable to parse reponse [[object Object]]" error
        
        // Read the actual module file and check the JavaScript code
        $moduleFile = file_get_contents(__DIR__ . '/../includes/modules/payment/braintree_googlepay.php');
        
        // Verify that iOS Chrome detection is defined globally
        $this->assertStringContainsString('const isIOSChrome = /CriOS/.test(ua)', $moduleFile,
            'iOS Chrome detection must be defined globally');
        
        // Extract the section containing the parseResponse call
        $parseResponseStart = strpos($moduleFile, 'paymentsClient.loadPaymentData(paymentDataRequest)');
        $parseResponseEnd = strpos($moduleFile, '.then(function (result) {', $parseResponseStart);
        $parseResponseSection = substr($moduleFile, $parseResponseStart, $parseResponseEnd - $parseResponseStart);
        
        // Verify that the iOS Chrome conditional logic exists
        $this->assertStringContainsString('if (isIOSChrome)', $parseResponseSection,
            'Must have conditional logic for iOS Chrome parseResponse handling');
        
        // Verify that JSON round-trip is used for iOS Chrome
        $this->assertStringContainsString('JSON.parse(JSON.stringify(paymentData))', $parseResponseSection,
            'iOS Chrome must use JSON round-trip to deep clone paymentData');
        
        // Verify that cloned data is passed to parseResponse for iOS Chrome
        $this->assertStringContainsString('const clonedPaymentData = JSON.parse(JSON.stringify(paymentData))', $parseResponseSection,
            'iOS Chrome must create cloned payment data');
        $this->assertStringContainsString('googlePaymentInstance.parseResponse(clonedPaymentData)', $parseResponseSection,
            'iOS Chrome must pass cloned data to parseResponse');
        
        // Verify that there's a fallback for non-iOS Chrome browsers
        $this->assertStringContainsString('googlePaymentInstance.parseResponse(paymentData)', $parseResponseSection,
            'Non-iOS Chrome browsers must pass paymentData directly to parseResponse');
        
        // Verify debug logging for iOS Chrome
        $this->assertStringContainsString('iOS Chrome detected - using JSON round-trip for parseResponse', $parseResponseSection,
            'Must log iOS Chrome detection for parseResponse handling');
        
        // Verify error handling
        $this->assertStringContainsString('try {', $parseResponseSection,
            'Must have try-catch block for JSON cloning');
        $this->assertStringContainsString('catch (err)', $parseResponseSection,
            'Must catch errors during JSON cloning');
        $this->assertStringContainsString('Failed to clone paymentData, falling back to direct call', $parseResponseSection,
            'Must log fallback when JSON cloning fails');
    }
    
    public function testIOSChromeDetectionPlacement() {
        // Verify that iOS Chrome detection is placed early in the script
        // so it's available for all functions that need it
        
        $moduleFile = file_get_contents(__DIR__ . '/../includes/modules/payment/braintree_googlepay.php');
        
        // Find the position of the iOS Chrome detection
        $iosChromeDetectionPos = strpos($moduleFile, 'const isIOSChrome = /CriOS/.test(ua)');
        $this->assertNotFalse($iosChromeDetectionPos, 'iOS Chrome detection must exist');
        
        // Find the position of loadGooglePayScripts function
        $loadScriptsPos = strpos($moduleFile, 'function loadGooglePayScripts()');
        $this->assertNotFalse($loadScriptsPos, 'loadGooglePayScripts function must exist');
        
        // Find the position of parseResponse call
        $parseResponsePos = strpos($moduleFile, 'googlePaymentInstance.parseResponse');
        $this->assertNotFalse($parseResponsePos, 'parseResponse call must exist');
        
        // iOS Chrome detection should come before both uses
        $this->assertLessThan($loadScriptsPos, $iosChromeDetectionPos,
            'iOS Chrome detection must be defined before loadGooglePayScripts function');
        $this->assertLessThan($parseResponsePos, $iosChromeDetectionPos,
            'iOS Chrome detection must be defined before parseResponse call');
    }
}
?>
