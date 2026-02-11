<?php
declare(strict_types=1);

namespace {
    if (!defined('DIR_FS_CATALOG')) {
        define('DIR_FS_CATALOG', dirname(__DIR__) . '/');
    }
    if (!defined('DIR_FS_LOGS')) {
        define('DIR_FS_LOGS', sys_get_temp_dir());
    }
    if (!defined('IS_ADMIN_FLAG')) {
        define('IS_ADMIN_FLAG', false);
    }

    require_once DIR_FS_CATALOG . 'includes/modules/payment/paypal/PayPalRestful/Common/Logger.php';

    use PHPUnit\Framework\TestCase;
    use PayPalRestful\Common\Logger;

    /**
     * Test that Logger::logJSON masks sensitive data without modifying the original array
     * 
     * This test verifies the fix for the bug where Logger::logJSON was modifying the
     * original data array passed to it, causing credit card numbers to be truncated
     * to only the last 4 digits before being sent to PayPal's API.
     * 
     * Bug introduced: February 10, 2026 (commit 9348c59)
     * Bug fixed: February 11, 2026
     */
    final class LoggerCardNumberMaskingTest extends TestCase
    {
        /**
         * Test that logJSON does not modify the original card number
         */
        public function testLogJsonDoesNotModifyOriginalCardNumber(): void
        {
            $originalCardNumber = '4532015112830366';
            $originalSecurityCode = '123';
            
            $requestData = [
                'intent' => 'CAPTURE',
                'payment_source' => [
                    'card' => [
                        'number' => $originalCardNumber,
                        'security_code' => $originalSecurityCode,
                        'expiry' => '2028-12',
                    ],
                ],
                'purchase_units' => [
                    [
                        'amount' => [
                            'currency_code' => 'USD',
                            'value' => '100.00',
                        ],
                    ],
                ],
            ];
            
            // Call logJSON - this should not modify $requestData
            $logOutput = Logger::logJSON($requestData, false, false);
            
            // Verify the original data is unchanged
            $this->assertEquals(
                $originalCardNumber,
                $requestData['payment_source']['card']['number'],
                'Logger::logJSON should not modify the original card number'
            );
            
            $this->assertEquals(
                $originalSecurityCode,
                $requestData['payment_source']['card']['security_code'],
                'Logger::logJSON should not modify the original security code'
            );
            
            // Verify the log output contains masked data
            $logData = json_decode($logOutput, true);
            $this->assertEquals(
                substr($originalCardNumber, -4),
                $logData['payment_source']['card']['number'],
                'Log output should contain only last 4 digits of card number'
            );
            
            $this->assertEquals(
                '***',
                $logData['payment_source']['card']['security_code'],
                'Log output should mask the security code'
            );
        }
        
        /**
         * Test that logJSON works correctly with var_export format
         */
        public function testLogJsonVarExportFormat(): void
        {
            $originalCardNumber = '5555555555554444';
            
            $requestData = [
                'payment_source' => [
                    'card' => [
                        'number' => $originalCardNumber,
                    ],
                ],
            ];
            
            // Call logJSON with var_export format
            $logOutput = Logger::logJSON($requestData, false, true);
            
            // Verify the original data is unchanged
            $this->assertEquals(
                $originalCardNumber,
                $requestData['payment_source']['card']['number'],
                'Logger::logJSON should not modify the original card number when using var_export'
            );
            
            // Verify the log output contains masked data
            $this->assertStringContainsString(
                "'4444'",
                $logOutput,
                'Log output should contain only last 4 digits when using var_export'
            );
            
            $this->assertStringNotContainsString(
                $originalCardNumber,
                $logOutput,
                'Log output should not contain the full card number'
            );
        }
        
        /**
         * Test that logJSON handles nested arrays correctly
         */
        public function testLogJsonDeepCopy(): void
        {
            $requestData = [
                'payment_source' => [
                    'card' => [
                        'number' => '4111111111111111',
                        'security_code' => '456',
                    ],
                ],
                'access_token' => 'secret_token',
                'links' => [
                    ['href' => 'https://api.paypal.com/v2/checkout/orders/123'],
                ],
            ];
            
            // Call logJSON
            $logOutput = Logger::logJSON($requestData, false, false);
            
            // Verify original card data is preserved
            $this->assertEquals('4111111111111111', $requestData['payment_source']['card']['number']);
            $this->assertEquals('456', $requestData['payment_source']['card']['security_code']);
            
            // Verify sensitive data is still in original (logJSON only removes from copy)
            $this->assertEquals('secret_token', $requestData['access_token']);
            $this->assertArrayHasKey('links', $requestData);
            
            // Verify log output has masked data
            $logData = json_decode($logOutput, true);
            $this->assertEquals('1111', $logData['payment_source']['card']['number']);
            $this->assertEquals('***', $logData['payment_source']['card']['security_code']);
            
            // Verify sensitive fields are removed from log output
            $this->assertArrayNotHasKey('access_token', $logData);
            $this->assertArrayNotHasKey('links', $logData);
        }
        
        /**
         * Test that logJSON handles non-array data gracefully
         */
        public function testLogJsonWithNonArrayData(): void
        {
            $stringData = 'test string';
            $logOutput = Logger::logJSON($stringData, false, false);
            
            // Non-array data should be JSON encoded as-is
            $this->assertEquals(json_encode($stringData, JSON_PRETTY_PRINT), $logOutput);
        }
    }
}
