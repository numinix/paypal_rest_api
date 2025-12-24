<?php

use PHPUnit\Framework\TestCase;

class DeliveryUpdatesTest extends TestCase
{
    public function testExtractsModuleDateFromModuleObject(): void
    {
        $moduleId = 'test_shipping_module';
        $GLOBALS[$moduleId] = new class {
            public $date = 'Expected delivery: 2-3 business days';
        };

        $result = oprc_extract_delivery_update_from_module($moduleId);

        $this->assertSame('Expected delivery: 2-3 business days', $result);

        unset($GLOBALS[$moduleId]);
    }

    public function testExtractsEstimatedDateFromModuleObject(): void
    {
        $moduleId = 'test_shipping_module_estimated';
        $GLOBALS[$moduleId] = new class {
            public $date = '';
            public $estimated_date = 'Estimated delivery: Friday';
        };

        $result = oprc_extract_delivery_update_from_module($moduleId);

        $this->assertSame('Estimated delivery: Friday', $result);

        unset($GLOBALS[$moduleId]);
    }

    public function testExtractsModuleDateFromQuotesProperty(): void
    {
        $moduleId = 'test_shipping_module2';
        $GLOBALS[$moduleId] = new class {
            public $quotes = [
                'date' => 'Ships tomorrow',
            ];
        };

        $result = oprc_extract_delivery_update_from_module($moduleId);

        $this->assertSame('Ships tomorrow', $result);

        unset($GLOBALS[$moduleId]);
    }

    public function testReturnsEmptyStringWhenModuleMissing(): void
    {
        $result = oprc_extract_delivery_update_from_module('nonexistent_module');

        $this->assertSame('', $result);
    }

    public function testReturnsEmptyStringWhenModuleDateMissing(): void
    {
        $moduleId = 'test_shipping_module3';
        $GLOBALS[$moduleId] = new class {
            public $someOtherProperty = 'value';
        };

        $result = oprc_extract_delivery_update_from_module($moduleId);

        $this->assertSame('', $result);

        unset($GLOBALS[$moduleId]);
    }

    public function testNormalizesDeliveryUpdatesArray(): void
    {
        $input = [
            'module1' => 'Expected delivery: Monday',
            'module2' => '  Arrives in 2 days  ',
            'module3' => '',
            'module4' => ['date' => 'Ships today'],
        ];

        $result = oprc_normalize_delivery_updates_array($input);

        $this->assertArrayHasKey('module1', $result);
        $this->assertSame('Expected delivery: Monday', $result['module1']);

        $this->assertArrayHasKey('module2', $result);
        $this->assertSame('Arrives in 2 days', $result['module2']);

        $this->assertArrayNotHasKey('module3', $result);

        $this->assertArrayHasKey('module4', $result);
        $this->assertSame('Ships today', $result['module4']);
    }

    public function testRemovesRedundantModuleDeliveryUpdates(): void
    {
        $input = [
            'module1' => 'Module level date',
            'module1_method1' => 'Method specific date',
            'module1_method2' => 'Another method date',
            'module2' => 'Only module level',
        ];

        $result = oprc_remove_redundant_module_delivery_updates($input);

        // module1 should be removed since it has method-specific entries
        $this->assertArrayNotHasKey('module1', $result);

        // Method-specific entries should remain
        $this->assertArrayHasKey('module1_method1', $result);
        $this->assertArrayHasKey('module1_method2', $result);

        // module2 has no method-specific entries, so it should remain
        $this->assertArrayHasKey('module2', $result);
    }

    public function testExtractsDeliveryUpdateFromQuotes(): void
    {
        $quotes = [
            'moduleDate' => 'Delivery estimate',
        ];

        $result = oprc_extract_delivery_update_from_quotes($quotes);

        $this->assertSame('Delivery estimate', $result);
    }

    public function testExtractsEstimatedDateFromQuotes(): void
    {
        $quotes = [
            'estimated_date' => 'Arrives next week',
        ];

        $result = oprc_extract_delivery_update_from_quotes($quotes);

        $this->assertSame('Arrives next week', $result);
    }

    public function testExtractsDeliveryUpdateFromQuotesWithMethods(): void
    {
        $quotes = [
            'methods' => [
                [
                    'date' => 'Method delivery date',
                ],
            ],
        ];

        $result = oprc_extract_delivery_update_from_quotes($quotes);

        $this->assertSame('Method delivery date', $result);
    }

    public function testExtractsDeliveryUpdatesFromQuotesList(): void
    {
        $quotes = [
            [
                'id' => 'flat',
                'methods' => [
                    [
                        'id' => 'flat',
                        'date' => 'Ships in 3-5 days',
                    ],
                ],
            ],
            [
                'id' => 'ups',
                'moduleDate' => 'UPS module date',
                'methods' => [
                    [
                        'id' => 'ground',
                        'date' => 'UPS Ground: 5-7 days',
                    ],
                    [
                        'id' => 'express',
                        'date' => 'UPS Express: Next day',
                    ],
                ],
            ],
        ];

        $result = oprc_extract_delivery_updates_from_quotes_list($quotes);

        $this->assertArrayHasKey('flat_flat', $result);
        $this->assertSame('Ships in 3-5 days', $result['flat_flat']);

        $this->assertArrayHasKey('ups_ground', $result);
        $this->assertSame('UPS Ground: 5-7 days', $result['ups_ground']);

        $this->assertArrayHasKey('ups_express', $result);
        $this->assertSame('UPS Express: Next day', $result['ups_express']);
    }

    public function testRestoresModuleDates(): void
    {
        $moduleId1 = 'test_module_restore1';
        $moduleId2 = 'test_module_restore2';

        $GLOBALS[$moduleId1] = new class {
            public $date = '';
        };
        $GLOBALS[$moduleId2] = new class {
            public $date = '';
        };

        $shippingUpdate = [
            'module_dates' => [
                $moduleId1 => 'Restored date 1',
                $moduleId2 => 'Restored date 2',
            ],
        ];

        oprc_restore_module_dates($shippingUpdate);

        $this->assertSame('Restored date 1', $GLOBALS[$moduleId1]->date);
        $this->assertSame('Restored date 2', $GLOBALS[$moduleId2]->date);

        unset($GLOBALS[$moduleId1]);
        unset($GLOBALS[$moduleId2]);
    }

    public function testRestoresModuleDatesHandlesEmptyUpdate(): void
    {
        $moduleId = 'test_module_restore3';

        $GLOBALS[$moduleId] = new class {
            public $date = 'Original date';
        };

        oprc_restore_module_dates([]);

        // Date should remain unchanged when no module_dates provided
        $this->assertSame('Original date', $GLOBALS[$moduleId]->date);

        unset($GLOBALS[$moduleId]);
    }

    public function testRestoresModuleDatesSkipsNonexistentModules(): void
    {
        $shippingUpdate = [
            'module_dates' => [
                'nonexistent_module' => 'This should not cause an error',
            ],
        ];

        // Should not throw an exception
        oprc_restore_module_dates($shippingUpdate);

        $this->assertTrue(true);
    }

    public function testPreservesDeliveryUpdatesFromShippingCache(): void
    {
        // Simulate a shipping update cache with delivery estimates
        $GLOBALS['oprc_last_shipping_update'] = [
            'module_dates' => [
                'flat' => 'Ships in 3-5 days',
                'ups' => 'UPS module estimate',
            ],
            'delivery_updates' => [
                'flat_flat' => 'Ships in 3-5 days',
                'ups_ground' => 'Ground: 5-7 days',
                'ups_express' => 'Express: Next day',
            ],
        ];

        // Prepare delivery updates with the cached data
        $quotes = [
            [
                'id' => 'flat',
                'methods' => [
                    ['id' => 'flat'],
                ],
            ],
            [
                'id' => 'ups',
                'methods' => [
                    ['id' => 'ground'],
                    ['id' => 'express'],
                ],
            ],
        ];

        $existingUpdates = [];
        if (isset($GLOBALS['oprc_last_shipping_update']['delivery_updates'])) {
            $existingUpdates = array_merge($existingUpdates, $GLOBALS['oprc_last_shipping_update']['delivery_updates']);
        }
        if (isset($GLOBALS['oprc_last_shipping_update']['module_dates'])) {
            $existingUpdates = array_merge($existingUpdates, $GLOBALS['oprc_last_shipping_update']['module_dates']);
        }

        $result = oprc_prepare_delivery_updates_for_quotes($quotes, null, $existingUpdates);

        // Verify that delivery estimates are preserved
        $this->assertArrayHasKey('rendered_updates', $result);
        $renderedUpdates = $result['rendered_updates'];

        $this->assertArrayHasKey('flat_flat', $renderedUpdates);
        $this->assertSame('Ships in 3-5 days', $renderedUpdates['flat_flat']);

        $this->assertArrayHasKey('ups_ground', $renderedUpdates);
        $this->assertSame('Ground: 5-7 days', $renderedUpdates['ups_ground']);

        $this->assertArrayHasKey('ups_express', $renderedUpdates);
        $this->assertSame('Express: Next day', $renderedUpdates['ups_express']);

        unset($GLOBALS['oprc_last_shipping_update']);
    }
}
