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

    require_once DIR_FS_CATALOG . 'zc_plugins/PayPalAdvancedCheckout/v2.0.0/catalog/includes/modules/payment/paypal/PayPalAdvancedCheckout/Common/Helpers.php';
    require_once DIR_FS_CATALOG . 'zc_plugins/PayPalAdvancedCheckout/v2.0.0/catalog/includes/modules/payment/paypal/PayPalAdvancedCheckout/Common/Logger.php';
}

namespace PayPalAdvancedCheckout\Tests {
    use PayPalAdvancedCheckout\Common\Helpers;
    use PayPalAdvancedCheckout\Common\Logger;
    use PHPUnit\Framework\TestCase;

    final class Utf8JsonPayloadSanitizationTest extends TestCase
    {
        public function testToUtf8RepairsInvalidByteSequences(): void
        {
            // Lone continuation-ish high byte — invalid UTF-8 in isolation.
            $raw = "Hello\xBAworld";
            $this->assertFalse(json_encode($raw) !== false && json_last_error() === JSON_ERROR_NONE);

            $clean = Helpers::toUtf8($raw);
            $this->assertNotSame('', $clean);
            $this->assertNotFalse(json_encode($clean));
            $this->assertSame(JSON_ERROR_NONE, json_last_error());
        }

        public function testTruncateUtf8DoesNotSplitMultibyteCharacters(): void
        {
            $name = 'äăâîșțÁÉÍ' . str_repeat('x', 130);
            $cutChars = Helpers::truncateUtf8($name, 127);

            $this->assertNotFalse(json_encode($cutChars));
            $this->assertLessThanOrEqual(127, mb_strlen($cutChars, 'UTF-8'));
            $this->assertTrue(mb_check_encoding($cutChars, 'UTF-8'));

            // Historical bug: byte substr can break UTF-8 on long personalisation strings.
            $longRomanian = 'Silver Antique Personalised Jewellery Box: ' . str_repeat('păstrate ', 20);
            $broken = substr($longRomanian, 0, 127);
            if (!mb_check_encoding($broken, 'UTF-8') || json_encode($broken) === false) {
                $this->assertNotFalse(json_encode(Helpers::truncateUtf8($longRomanian, 127)));
            } else {
                $this->assertTrue(true);
            }
        }

        public function testJsonEncodePayloadNeverReturnsFalseForInvalidUtf8(): void
        {
            $payload = [
                'intent' => 'CAPTURE',
                'purchase_units' => [[
                    'items' => [[
                        'name' => "Gift\xBAbox: personalised \xE9 message",
                        'quantity' => '1',
                    ]],
                ]],
            ];

            $this->assertFalse(json_encode($payload) !== false && json_last_error() === JSON_ERROR_NONE);

            $encoded = Helpers::jsonEncodePayload($payload);
            $this->assertNotNull($encoded);
            $decoded = json_decode($encoded, true);
            $this->assertIsArray($decoded);
            $this->assertSame('CAPTURE', $decoded['intent']);
            $this->assertArrayHasKey('purchase_units', $decoded);
        }

        public function testLogJsonDoesNotCollapseToNullOnInvalidUtf8(): void
        {
            $payload = [
                'intent' => 'CAPTURE',
                'purchase_units' => [[
                    'items' => [['name' => "Bad\xBA"]],
                ]],
            ];

            $logged = Logger::logJSON($payload, true, true);
            $this->assertNotSame('NULL', $logged);
            $this->assertStringContainsString('intent', $logged);
        }
    }
}
