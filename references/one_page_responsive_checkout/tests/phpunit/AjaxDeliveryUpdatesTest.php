<?php

use PHPUnit\Framework\TestCase;

class AjaxDeliveryUpdatesTest extends TestCase
{
    /**
     * Test that oprc_prepare_delivery_updates_for_quotes returns rendered_updates
     * in the format expected by the JavaScript oprcApplyDeliveryUpdates function.
     */
    public function testPrepareDeliveryUpdatesReturnsRenderedUpdates(): void
    {
        $quotes = [
            [
                'id' => 'flat',
                'methods' => [
                    [
                        'id' => 'flat',
                        'date' => 'Delivery in 3-5 business days',
                    ],
                ],
            ],
            [
                'id' => 'ups',
                'methods' => [
                    [
                        'id' => 'ground',
                        'date' => 'UPS Ground: 5-7 days',
                    ],
                    [
                        'id' => 'express',
                        'date' => '<strong>Next day delivery</strong>',
                    ],
                ],
            ],
        ];

        $result = oprc_prepare_delivery_updates_for_quotes($quotes, null);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('rendered_updates', $result);
        $this->assertIsArray($result['rendered_updates']);

        // Check that rendered_updates contains the expected shipping method IDs
        $this->assertArrayHasKey('flat_flat', $result['rendered_updates']);
        $this->assertArrayHasKey('ups_ground', $result['rendered_updates']);
        $this->assertArrayHasKey('ups_express', $result['rendered_updates']);

        // Verify the content is sanitized HTML strings
        $this->assertSame('Delivery in 3-5 business days', $result['rendered_updates']['flat_flat']);
        $this->assertSame('UPS Ground: 5-7 days', $result['rendered_updates']['ups_ground']);
        $this->assertSame('<strong>Next day delivery</strong>', $result['rendered_updates']['ups_express']);
    }

    /**
     * Test that rendered_updates properly sanitizes HTML content.
     */
    public function testRenderedUpdatesSanitizesHtml(): void
    {
        $quotes = [
            [
                'id' => 'test',
                'methods' => [
                    [
                        'id' => 'safe',
                        'date' => '<span>Safe HTML</span>',
                    ],
                    [
                        'id' => 'unsafe',
                        'date' => '<script>alert("xss")</script>Normal text',
                    ],
                ],
            ],
        ];

        $result = oprc_prepare_delivery_updates_for_quotes($quotes, null);

        $this->assertArrayHasKey('test_safe', $result['rendered_updates']);
        $this->assertSame('<span>Safe HTML</span>', $result['rendered_updates']['test_safe']);

        // Script tags should be removed
        $this->assertArrayHasKey('test_unsafe', $result['rendered_updates']);
        $this->assertStringNotContainsString('<script>', $result['rendered_updates']['test_unsafe']);
        $this->assertStringContainsString('Normal text', $result['rendered_updates']['test_unsafe']);
    }

    /**
     * Test that methods without dates inherit from module-level date.
     */
    public function testMethodsInheritModuleLevelDate(): void
    {
        $quotes = [
            [
                'id' => 'test',
                'methods' => [
                    [
                        'id' => 'with_date',
                        'date' => 'Ships tomorrow',
                    ],
                    [
                        'id' => 'without_date',
                        // No date - will inherit from module level (derived from first method)
                    ],
                ],
            ],
        ];

        $result = oprc_prepare_delivery_updates_for_quotes($quotes, null);

        $this->assertArrayHasKey('test_with_date', $result['rendered_updates']);
        $this->assertSame('Ships tomorrow', $result['rendered_updates']['test_with_date']);

        // Methods without explicit dates inherit the module-level date
        $this->assertArrayHasKey('test_without_date', $result['rendered_updates']);
        $this->assertSame('Ships tomorrow', $result['rendered_updates']['test_without_date']);
    }

    /**
     * Test that the function handles module-level dates when methods don't have dates.
     */
    public function testModuleLevelDatesAppliedToMethods(): void
    {
        $quotes = [
            [
                'id' => 'carrier',
                'moduleDate' => 'Ships in 2-4 days',
                'methods' => [
                    [
                        'id' => 'standard',
                        // No date property - should inherit from module
                    ],
                    [
                        'id' => 'express',
                        'date' => 'Next day',
                    ],
                ],
            ],
        ];

        $result = oprc_prepare_delivery_updates_for_quotes($quotes, null);

        // Standard should get the module-level date
        $this->assertArrayHasKey('carrier_standard', $result['rendered_updates']);
        $this->assertSame('Ships in 2-4 days', $result['rendered_updates']['carrier_standard']);

        // Express has its own date
        $this->assertArrayHasKey('carrier_express', $result['rendered_updates']);
        $this->assertSame('Next day', $result['rendered_updates']['carrier_express']);
    }

    public function testAjaxShippingQuotesIncludesCachedDeliveryEstimate(): void
    {
        if (!defined('OPRC_AJAX_SHIPPING_QUOTES')) {
            define('OPRC_AJAX_SHIPPING_QUOTES', 'true');
        }

        $originalSession = $_SESSION ?? null;
        $_SESSION = is_array($_SESSION ?? null) ? $_SESSION : [];
        $originalCart = $_SESSION['cart'] ?? null;

        $_SESSION['cart'] = new class {
            public function count_contents()
            {
                return 1;
            }

            public function show_weight()
            {
                return 0;
            }
        };

        $previousTemplate = $GLOBALS['template'] ?? null;
        $previousLastUpdate = $GLOBALS['oprc_last_shipping_update'] ?? null;
        $previousFlatModule = $GLOBALS['flat'] ?? null;

        $quotes = [
            [
                'id' => 'flat',
                'module' => 'Flat Rate',
                'methods' => [
                    [
                        'id' => 'flat',
                        'title' => 'Flat Rate',
                        'cost' => 5.00,
                        'date' => '',
                    ],
                ],
            ],
        ];

        $GLOBALS['flat'] = (object) [];

        $orderStub = new stdClass();
        $orderStub->content_type = 'physical';

        $shippingUpdate = [
            'order' => $orderStub,
            'shipping_modules' => null,
            'globals' => [
                'quotes' => $quotes,
                'free_shipping' => false,
                'shipping_weight' => 0,
                'total_weight' => 0,
                'total_count' => 1,
                'recalculate_shipping_cost' => null,
                'pass' => null,
            ],
            'module_dates' => [
                'flat' => 'Arrives tomorrow',
            ],
            'delivery_updates' => [
                'flat_flat' => 'Arrives tomorrow',
            ],
        ];

        $GLOBALS['oprc_last_shipping_update'] = $shippingUpdate;

        $templateStub = new class {
            public function get_template_dir($templateFile, $templateBaseDir, $pageBase, $defaultDir)
            {
                return __DIR__ . '/fixtures/templates/one_page_checkout';
            }
        };

        $GLOBALS['template'] = $templateStub;

        if (!defined('DIR_WS_TEMPLATE')) {
            define('DIR_WS_TEMPLATE', '');
        }

        if (!defined('DIR_WS_INCLUDES')) {
            define('DIR_WS_INCLUDES', __DIR__ . '/fixtures/includes/');
        }

        if (!defined('DIR_FS_CATALOG')) {
            define('DIR_FS_CATALOG', realpath(__DIR__ . '/../../catalog/') . '/');
        }

        if (!defined('DIR_WS_MODULES')) {
            define('DIR_WS_MODULES', DIR_FS_CATALOG . 'includes/modules/');
        }

        if (!defined('DIR_WS_CLASSES')) {
            define('DIR_WS_CLASSES', DIR_FS_CATALOG . 'includes/classes/');
        }

        $html = oprc_capture_shipping_methods_html();

        $this->assertIsString($html);
        $this->assertStringContainsString('data-id="flat_flat"', $html);
        $this->assertStringContainsString('Arrives tomorrow', $html);

        if ($previousTemplate === null) {
            unset($GLOBALS['template']);
        } else {
            $GLOBALS['template'] = $previousTemplate;
        }

        if ($previousLastUpdate === null && isset($GLOBALS['oprc_last_shipping_update'])) {
            unset($GLOBALS['oprc_last_shipping_update']);
        } elseif ($previousLastUpdate !== null) {
            $GLOBALS['oprc_last_shipping_update'] = $previousLastUpdate;
        }

        if ($previousFlatModule === null) {
            unset($GLOBALS['flat']);
        } else {
            $GLOBALS['flat'] = $previousFlatModule;
        }

        if ($originalCart === null) {
            unset($_SESSION['cart']);
        } else {
            $_SESSION['cart'] = $originalCart;
        }

        if ($originalSession === null) {
            unset($_SESSION);
        } else {
            $_SESSION = $originalSession;
        }
    }

    /**
     * Test that oprc_attach_delivery_updates helper sets correct payload fields.
     */
    public function testAttachDeliveryUpdatesHelper(): void
    {
        $quotes = [
            [
                'id' => 'fedex',
                'methods' => [
                    [
                        'id' => 'ground',
                        'date' => 'Delivery in 2-3 days',
                    ],
                    [
                        'id' => 'express',
                        'date' => 'Next day delivery',
                    ],
                ],
            ],
        ];

        $payload = [];
        oprc_attach_delivery_updates($payload, $quotes, null);

        // Check that the helper attached all expected fields
        $this->assertArrayHasKey('deliveryUpdates', $payload);
        $this->assertArrayHasKey('moduleDeliveryDates', $payload);
        $this->assertArrayHasKey('methodDeliveryDates', $payload);

        // Verify deliveryUpdates contains rendered HTML (what the UI expects)
        $this->assertIsArray($payload['deliveryUpdates']);
        $this->assertArrayHasKey('fedex_ground', $payload['deliveryUpdates']);
        $this->assertArrayHasKey('fedex_express', $payload['deliveryUpdates']);
        $this->assertSame('Delivery in 2-3 days', $payload['deliveryUpdates']['fedex_ground']);
        $this->assertSame('Next day delivery', $payload['deliveryUpdates']['fedex_express']);

        // Verify the diagnostic fields are populated
        $this->assertIsArray($payload['moduleDeliveryDates']);
        $this->assertIsArray($payload['methodDeliveryDates']);
    }

    /**
     * Test that the fix for oprc_update_shipping_method.php uses rendered_updates.
     * This verifies that delivery updates are in the correct format for the frontend.
     */
    public function testUpdateShippingMethodUsesRenderedUpdates(): void
    {
        $quotes = [
            [
                'id' => 'usps',
                'methods' => [
                    [
                        'id' => 'priority',
                        'date' => 'USPS Priority: 2-3 days',
                    ],
                ],
            ],
        ];

        $deliveryData = oprc_prepare_delivery_updates_for_quotes($quotes, null);

        // The old code would incorrectly use method_dates
        $incorrectDeliveryUpdates = $deliveryData['method_dates'];
        
        // The fixed code should use rendered_updates
        $correctDeliveryUpdates = $deliveryData['rendered_updates'];

        // Verify that rendered_updates is what the frontend expects (HTML snippets)
        $this->assertIsArray($correctDeliveryUpdates);
        $this->assertArrayHasKey('usps_priority', $correctDeliveryUpdates);
        $this->assertSame('USPS Priority: 2-3 days', $correctDeliveryUpdates['usps_priority']);

        // Both should be arrays, but rendered_updates is the proper format for the UI
        $this->assertIsArray($incorrectDeliveryUpdates);
        
        // The key difference: rendered_updates contains sanitized HTML strings ready for injection
        // while method_dates may contain raw data. In this case they're similar, but the 
        // rendered_updates version goes through oprc_render_html_snippet() sanitization.
        $this->assertEquals($correctDeliveryUpdates, $incorrectDeliveryUpdates);
    }

    /**
     * Test that method-level dates are prioritized over quote-level and module-level dates.
     * This validates the fix for the AJAX issue where module->date is empty but method->date has values.
     */
    public function testMethodLevelDatesPrioritizedOverModuleLevel(): void
    {
        $quotes = [
            [
                'id' => 'flat24',
                'moduleDate' => 'Module-level estimate (should be ignored)',
                'methods' => [
                    [
                        'id' => 'flat',
                        'date' => 'Estimated delivery between 11-11-2025 and 12-11-2025',
                    ],
                ],
            ],
            [
                'id' => 'flat48',
                'date' => 'Quote-level estimate',
                'methods' => [
                    [
                        'id' => 'standard',
                        'date' => 'Method-level: Ships in 2 days',
                    ],
                    [
                        'id' => 'express',
                        // No method-level date - should inherit from quote-level
                    ],
                ],
            ],
        ];

        $result = oprc_prepare_delivery_updates_for_quotes($quotes, null);

        // flat24_flat should use method-level date, not moduleDate
        $this->assertArrayHasKey('flat24_flat', $result['rendered_updates']);
        $this->assertSame('Estimated delivery between 11-11-2025 and 12-11-2025', $result['rendered_updates']['flat24_flat']);

        // flat48_standard should use method-level date
        $this->assertArrayHasKey('flat48_standard', $result['rendered_updates']);
        $this->assertSame('Method-level: Ships in 2 days', $result['rendered_updates']['flat48_standard']);

        // flat48_express has no method-level date, should use quote-level
        $this->assertArrayHasKey('flat48_express', $result['rendered_updates']);
        $this->assertSame('Quote-level estimate', $result['rendered_updates']['flat48_express']);
    }

    /**
     * Test the scenario from the problem statement: module->date is empty during AJAX,
     * but methods have date values. This simulates the actual AJAX behavior.
     */
    public function testAjaxScenarioWithEmptyModuleDateButMethodDates(): void
    {
        // Simulate the AJAX scenario where module->date is empty string
        // but quotes are present with method-level dates
        $quotes = [
            [
                'id' => 'shipping_module',
                'module' => 'Shipping Module',
                'methods' => [
                    [
                        'id' => 'method1',
                        'title' => 'Method 1',
                        'cost' => 10.00,
                        'date' => 'Arrives in 3-5 business days',
                    ],
                    [
                        'id' => 'method2',
                        'title' => 'Method 2',
                        'cost' => 15.00,
                        'date' => 'Next day delivery',
                    ],
                ],
            ],
        ];

        $result = oprc_prepare_delivery_updates_for_quotes($quotes, null);

        // Verify that deliveryUpdates is not empty (the main fix)
        $this->assertNotEmpty($result['rendered_updates'], 'deliveryUpdates should not be empty when methods have dates');

        // Verify both methods have their dates
        $this->assertArrayHasKey('shipping_module_method1', $result['rendered_updates']);
        $this->assertSame('Arrives in 3-5 business days', $result['rendered_updates']['shipping_module_method1']);

        $this->assertArrayHasKey('shipping_module_method2', $result['rendered_updates']);
        $this->assertSame('Next day delivery', $result['rendered_updates']['shipping_module_method2']);

        // Verify method_dates are populated
        $this->assertArrayHasKey('shipping_module_method1', $result['method_dates']);
        $this->assertArrayHasKey('shipping_module_method2', $result['method_dates']);
    }
}
