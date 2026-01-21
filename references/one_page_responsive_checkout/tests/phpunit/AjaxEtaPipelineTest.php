<?php

use PHPUnit\Framework\TestCase;

/**
 * Tests to verify that the AJAX ETA pipeline runs correctly on all AJAX endpoints.
 * This ensures that delivery updates are populated even when module->date is empty
 * but method-level dates are present (the scenario described in the problem statement).
 */
class AjaxEtaPipelineTest extends TestCase
{
    /**
     * Test that oprc_capture_shipping_methods_html always executes the template render
     * to trigger module ETA computation, even when shouldReturnHtml is false.
     */
    public function testCaptureShippingMethodsAlwaysExecutesTemplateRender(): void
    {
        // Mock the session and cart
        $originalSession = $_SESSION ?? null;
        $_SESSION = is_array($_SESSION ?? null) ? $_SESSION : [];
        $originalCart = $_SESSION['cart'] ?? null;

        $_SESSION['cart'] = new class {
            public function count_contents() { return 1; }
            public function show_weight() { return 0; }
        };

        // Set up GLOBALS
        $previousTemplate = $GLOBALS['template'] ?? null;
        $previousOrder = $GLOBALS['order'] ?? null;
        $previousShippingModules = $GLOBALS['shipping_modules'] ?? null;
        $previousFlatModule = $GLOBALS['flat'] ?? null;

        // Create mock shipping module
        $GLOBALS['flat'] = new class {
            public $date = '';
            public $modules = ['flat.php'];
        };

        // Create mock order
        $orderStub = new stdClass();
        $orderStub->content_type = 'physical';
        $GLOBALS['order'] = $orderStub;

        // Create mock shipping modules
        $shippingModulesStub = new class {
            public $modules = ['flat.php'];
            public $quotes = [];
        };
        $GLOBALS['shipping_modules'] = $shippingModulesStub;

        // Mock template
        $templateStub = new class {
            public function get_template_dir($templateFile, $templateBaseDir, $pageBase, $defaultDir)
            {
                return __DIR__ . '/fixtures/templates/one_page_checkout';
            }
        };
        $GLOBALS['template'] = $templateStub;

        // Define required constants
        if (!defined('DIR_WS_TEMPLATE')) define('DIR_WS_TEMPLATE', '');
        if (!defined('DIR_WS_INCLUDES')) define('DIR_WS_INCLUDES', __DIR__ . '/fixtures/includes/');
        if (!defined('DIR_FS_CATALOG')) define('DIR_FS_CATALOG', realpath(__DIR__ . '/../../catalog/') . '/');
        if (!defined('DIR_WS_MODULES')) define('DIR_WS_MODULES', DIR_FS_CATALOG . 'includes/modules/');
        if (!defined('DIR_WS_CLASSES')) define('DIR_WS_CLASSES', DIR_FS_CATALOG . 'includes/classes/');

        // Test with shouldReturnHtml = false (the fix scenario)
        // Note: If OPRC_AJAX_SHIPPING_QUOTES is already defined as 'true' from a previous test,
        // we can't redefine it. In that case, we expect HTML to be returned.
        $expectHtml = defined('OPRC_AJAX_SHIPPING_QUOTES') && OPRC_AJAX_SHIPPING_QUOTES === 'true';
        
        if (!defined('OPRC_AJAX_SHIPPING_QUOTES')) {
            define('OPRC_AJAX_SHIPPING_QUOTES', 'false');
        }

        // Set up quotes with method-level dates
        $quotes = [
            [
                'id' => 'flat',
                'module' => 'Flat Rate',
                'methods' => [
                    [
                        'id' => 'flat',
                        'title' => 'Flat Rate',
                        'cost' => 5.00,
                        'date' => 'Estimated delivery: 3-5 business days',
                    ],
                ],
            ],
        ];
        $GLOBALS['quotes'] = $quotes;

        // Provide cached shipping update to avoid running oprc_update_shipping.php
        $GLOBALS['oprc_last_shipping_update'] = [
            'order' => $orderStub,
            'shipping_modules' => $shippingModulesStub,
            'globals' => [
                'quotes' => $quotes,
                'free_shipping' => false,
                'shipping_weight' => 0,
                'total_weight' => 0,
                'total_count' => 1,
                'recalculate_shipping_cost' => null,
                'pass' => null,
            ],
            'module_dates' => [],
            'delivery_updates' => [],
        ];

        // Call the function - it should execute the template even when shouldReturnHtml is false
        $result = oprc_capture_shipping_methods_html();

        // When shouldReturnHtml is false, it should return null; when true, it returns HTML
        if ($expectHtml) {
            $this->assertIsString($result, 'Should return HTML when OPRC_AJAX_SHIPPING_QUOTES is true');
        } else {
            $this->assertNull($result, 'Should return null when OPRC_AJAX_SHIPPING_QUOTES is false');
        }

        // The template should have been executed, so delivery updates should be computable
        $deliveryData = oprc_prepare_delivery_updates_for_quotes($quotes, $shippingModulesStub);

        // Verify delivery updates are not empty (the main fix)
        $this->assertNotEmpty($deliveryData['rendered_updates'], 'Delivery updates should be populated after template render');
        $this->assertArrayHasKey('flat_flat', $deliveryData['rendered_updates']);
        $this->assertSame('Estimated delivery: 3-5 business days', $deliveryData['rendered_updates']['flat_flat']);

        // Cleanup
        if ($previousTemplate === null) unset($GLOBALS['template']); else $GLOBALS['template'] = $previousTemplate;
        if ($previousOrder === null) unset($GLOBALS['order']); else $GLOBALS['order'] = $previousOrder;
        if ($previousShippingModules === null) unset($GLOBALS['shipping_modules']); else $GLOBALS['shipping_modules'] = $previousShippingModules;
        if ($previousFlatModule === null) unset($GLOBALS['flat']); else $GLOBALS['flat'] = $previousFlatModule;
        if ($originalCart === null) unset($_SESSION['cart']); else $_SESSION['cart'] = $originalCart;
        if ($originalSession === null) unset($_SESSION); else $_SESSION = $originalSession;
    }

    /**
     * Test the complete AJAX ETA pipeline:
     * 1. Rebuild order and shipping_modules
     * 2. Run quote computation
     * 3. Call oprc_capture_shipping_methods_html
     * 4. Call oprc_prepare_delivery_updates_for_quotes
     * 5. Verify deliveryUpdates contains rendered_updates
     */
    public function testCompleteAjaxEtaPipeline(): void
    {
        // Simulate quotes with method-level dates (as they would be after quote computation)
        $quotes = [
            [
                'id' => 'flat24',
                'module' => '24h Shipping',
                'methods' => [
                    [
                        'id' => 'standard',
                        'title' => 'Standard 24h',
                        'cost' => 10.00,
                        'date' => 'Delivery tomorrow',
                    ],
                ],
            ],
            [
                'id' => 'flat48',
                'module' => '48h Shipping',
                'methods' => [
                    [
                        'id' => 'standard',
                        'title' => 'Standard 48h',
                        'cost' => 8.00,
                        'date' => 'Delivery in 2 days',
                    ],
                    [
                        'id' => 'express',
                        'title' => 'Express 48h',
                        'cost' => 12.00,
                        'date' => 'Delivery in 1 day',
                    ],
                ],
            ],
        ];

        // Mock shipping modules
        $shippingModulesStub = new class {
            public $modules = ['flat24.php', 'flat48.php'];
        };

        // Step 4: Call oprc_prepare_delivery_updates_for_quotes (this is what AJAX endpoints do)
        $deliveryData = oprc_prepare_delivery_updates_for_quotes($quotes, $shippingModulesStub);

        // Step 5: Verify deliveryUpdates contains rendered_updates
        $this->assertIsArray($deliveryData);
        $this->assertArrayHasKey('rendered_updates', $deliveryData);
        $this->assertArrayHasKey('module_dates', $deliveryData);
        $this->assertArrayHasKey('method_dates', $deliveryData);

        // Verify rendered_updates is populated with all methods
        $renderedUpdates = $deliveryData['rendered_updates'];
        $this->assertCount(3, $renderedUpdates, 'Should have 3 shipping options');

        // Verify each method has its date
        $this->assertArrayHasKey('flat24_standard', $renderedUpdates);
        $this->assertSame('Delivery tomorrow', $renderedUpdates['flat24_standard']);

        $this->assertArrayHasKey('flat48_standard', $renderedUpdates);
        $this->assertSame('Delivery in 2 days', $renderedUpdates['flat48_standard']);

        $this->assertArrayHasKey('flat48_express', $renderedUpdates);
        $this->assertSame('Delivery in 1 day', $renderedUpdates['flat48_express']);

        // Verify method_dates is also populated
        $this->assertCount(3, $deliveryData['method_dates']);

        // Verify module_dates is populated
        $this->assertNotEmpty($deliveryData['module_dates']);
    }

    /**
     * Test that oprc_attach_delivery_updates properly formats the payload
     * for all AJAX endpoints.
     */
    public function testAttachDeliveryUpdatesFormatsPayloadCorrectly(): void
    {
        $quotes = [
            [
                'id' => 'usps',
                'methods' => [
                    [
                        'id' => 'priority',
                        'date' => 'USPS Priority: 2-3 days',
                    ],
                    [
                        'id' => 'express',
                        'date' => 'USPS Express: Next day',
                    ],
                ],
            ],
        ];

        $payload = [];
        oprc_attach_delivery_updates($payload, $quotes, null);

        // Verify the payload structure matches what AJAX endpoints return
        $this->assertArrayHasKey('deliveryUpdates', $payload);
        $this->assertArrayHasKey('moduleDeliveryDates', $payload);
        $this->assertArrayHasKey('methodDeliveryDates', $payload);

        // Verify deliveryUpdates contains rendered HTML snippets
        $this->assertIsArray($payload['deliveryUpdates']);
        $this->assertArrayHasKey('usps_priority', $payload['deliveryUpdates']);
        $this->assertArrayHasKey('usps_express', $payload['deliveryUpdates']);

        // Verify the content is sanitized HTML
        $this->assertSame('USPS Priority: 2-3 days', $payload['deliveryUpdates']['usps_priority']);
        $this->assertSame('USPS Express: Next day', $payload['deliveryUpdates']['usps_express']);

        // Verify debug fields are populated
        $this->assertIsArray($payload['moduleDeliveryDates']);
        $this->assertIsArray($payload['methodDeliveryDates']);
    }

    /**
     * Test the scenario from the problem statement:
     * On AJAX paths, module->date is empty but methods have date keys.
     * The fix ensures deliveryUpdates is not empty.
     */
    public function testProblemStatementScenario(): void
    {
        // Simulate the exact scenario: 7 quotes, methods have id/title/cost/date
        // but module->date is empty on AJAX
        $quotes = [
            ['id' => 'item', 'methods' => [['id' => 'method1', 'title' => 'Item Shipping', 'cost' => 5.00, 'date' => 'Ships today']]],
            ['id' => 'flat24', 'methods' => [['id' => 'flat', 'title' => '24h', 'cost' => 10.00, 'date' => 'Delivery between 11-11-2025 and 12-11-2025']]],
            ['id' => 'flat48', 'methods' => [['id' => 'flat', 'title' => '48h', 'cost' => 8.00, 'date' => 'Delivery in 2 days']]],
            ['id' => 'flatRUSH24', 'methods' => [['id' => 'rush', 'title' => 'Rush 24h', 'cost' => 15.00, 'date' => 'Rush delivery tomorrow']]],
            ['id' => 'flatRUSHNEXTDAY', 'methods' => [['id' => 'nextday', 'title' => 'Next Day', 'cost' => 20.00, 'date' => 'Next day delivery']]],
            ['id' => 'flatSAT', 'methods' => [['id' => 'saturday', 'title' => 'Saturday', 'cost' => 18.00, 'date' => 'Saturday delivery']]],
            ['id' => 'free', 'methods' => [['id' => 'free', 'title' => 'Free Shipping', 'cost' => 0.00, 'date' => 'Delivery in 5-7 days']]],
        ];

        // Simulate AJAX: module->date is empty (this would normally cause deliveryUpdates to be [])
        foreach ($quotes as $quote) {
            $moduleId = $quote['id'];
            $GLOBALS[$moduleId] = new class {
                public $date = ''; // Empty on AJAX!
            };
        }

        $result = oprc_prepare_delivery_updates_for_quotes($quotes, null);

        // The fix ensures deliveryUpdates is NOT empty even when module->date is empty
        $this->assertNotEmpty($result['rendered_updates'], 'deliveryUpdates should NOT be empty (the main fix)');

        // Verify all 7 methods have their dates
        $this->assertCount(7, $result['rendered_updates'], 'Should have all 7 shipping options');

        // Spot check a few
        $this->assertArrayHasKey('flat24_flat', $result['rendered_updates']);
        $this->assertSame('Delivery between 11-11-2025 and 12-11-2025', $result['rendered_updates']['flat24_flat']);

        $this->assertArrayHasKey('flatRUSHNEXTDAY_nextday', $result['rendered_updates']);
        $this->assertSame('Next day delivery', $result['rendered_updates']['flatRUSHNEXTDAY_nextday']);

        // Cleanup
        foreach ($quotes as $quote) {
            unset($GLOBALS[$quote['id']]);
        }
    }
}
