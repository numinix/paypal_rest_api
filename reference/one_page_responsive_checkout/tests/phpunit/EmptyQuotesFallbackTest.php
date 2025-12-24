<?php

use PHPUnit\Framework\TestCase;

/**
 * Tests for the fix that ensures delivery updates work even when quotes array is empty.
 * This specifically tests the fallback mechanism added to oprc_prepare_delivery_updates_for_quotes.
 */
class EmptyQuotesFallbackTest extends TestCase
{
    protected function setUp(): void
    {
        // Clean up any previous test state
        if (isset($GLOBALS['quotes'])) {
            unset($GLOBALS['quotes']);
        }
    }

    protected function tearDown(): void
    {
        // Clean up after tests
        if (isset($GLOBALS['quotes'])) {
            unset($GLOBALS['quotes']);
        }
    }

    /**
     * Test that oprc_prepare_delivery_updates_for_quotes falls back to $GLOBALS['quotes']
     * when called with an empty quotes array.
     */
    public function testFallbackToGlobalQuotesWhenEmptyArrayPassed(): void
    {
        // Set up global quotes
        $GLOBALS['quotes'] = [
            [
                'id' => 'flat24',
                'methods' => [
                    [
                        'id' => 'flat24',
                        'date' => 'Delivery in 24 hours',
                    ],
                ],
            ],
            [
                'id' => 'flat48',
                'methods' => [
                    [
                        'id' => 'flat48',
                        'date' => 'Delivery in 48 hours',
                    ],
                ],
            ],
        ];

        // Call with empty array - should fall back to $GLOBALS['quotes']
        $result = oprc_prepare_delivery_updates_for_quotes([], null, []);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('rendered_updates', $result);
        $this->assertIsArray($result['rendered_updates']);

        // Should have rendered the delivery updates from global quotes
        $this->assertArrayHasKey('flat24_flat24', $result['rendered_updates']);
        $this->assertArrayHasKey('flat48_flat48', $result['rendered_updates']);
        $this->assertSame('Delivery in 24 hours', $result['rendered_updates']['flat24_flat24']);
        $this->assertSame('Delivery in 48 hours', $result['rendered_updates']['flat48_flat48']);
    }

    /**
     * Test that oprc_prepare_delivery_updates_for_quotes falls back to shipping_modules->quotes
     * when both the quotes parameter is empty and a shipping modules object is provided.
     */
    public function testFallbackToShippingModulesQuotesWhenEmptyArrayPassed(): void
    {
        // Create a mock shipping modules object
        $shippingModules = new class {
            public $quotes = [
                [
                    'id' => 'flatRUSH24',
                    'methods' => [
                        [
                            'id' => 'rush24',
                            'date' => 'Rush delivery in 24 hours',
                        ],
                    ],
                ],
            ];
        };

        // Call with empty array - should fall back to $shippingModules->quotes
        $result = oprc_prepare_delivery_updates_for_quotes([], $shippingModules, []);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('rendered_updates', $result);
        $this->assertIsArray($result['rendered_updates']);

        // Should have rendered the delivery updates from shipping modules quotes
        $this->assertArrayHasKey('flatRUSH24_rush24', $result['rendered_updates']);
        $this->assertSame('Rush delivery in 24 hours', $result['rendered_updates']['flatRUSH24_rush24']);
    }

    /**
     * Test the priority order: shipping_modules->quotes takes precedence over $GLOBALS['quotes'].
     */
    public function testShippingModulesQuotesTakesPrecedenceOverGlobalQuotes(): void
    {
        // Set up global quotes
        $GLOBALS['quotes'] = [
            [
                'id' => 'flat',
                'methods' => [
                    [
                        'id' => 'flat',
                        'date' => 'Global delivery date',
                    ],
                ],
            ],
        ];

        // Create a mock shipping modules object with different quotes
        $shippingModules = new class {
            public $quotes = [
                [
                    'id' => 'express',
                    'methods' => [
                        [
                            'id' => 'express',
                            'date' => 'Module delivery date',
                        ],
                    ],
                ],
            ];
        };

        // Call with empty array - should prefer $shippingModules->quotes
        $result = oprc_prepare_delivery_updates_for_quotes([], $shippingModules, []);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('rendered_updates', $result);
        
        // Should use shipping modules quotes, not global quotes
        $this->assertArrayHasKey('express_express', $result['rendered_updates']);
        $this->assertArrayNotHasKey('flat_flat', $result['rendered_updates']);
        $this->assertSame('Module delivery date', $result['rendered_updates']['express_express']);
    }

    /**
     * Test that when quotes are explicitly provided, they are used instead of fallbacks.
     */
    public function testExplicitQuotesAreUsedInsteadOfFallbacks(): void
    {
        // Set up global quotes
        $GLOBALS['quotes'] = [
            [
                'id' => 'global',
                'methods' => [
                    [
                        'id' => 'global',
                        'date' => 'Should not be used',
                    ],
                ],
            ],
        ];

        // Create a mock shipping modules object
        $shippingModules = new class {
            public $quotes = [
                [
                    'id' => 'module',
                    'methods' => [
                        [
                            'id' => 'module',
                            'date' => 'Should not be used either',
                        ],
                    ],
                ],
            ];
        };

        // Provide explicit quotes
        $explicitQuotes = [
            [
                'id' => 'explicit',
                'methods' => [
                    [
                        'id' => 'explicit',
                        'date' => 'This should be used',
                    ],
                ],
            ],
        ];

        $result = oprc_prepare_delivery_updates_for_quotes($explicitQuotes, $shippingModules, []);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('rendered_updates', $result);
        
        // Should use explicit quotes only
        $this->assertArrayHasKey('explicit_explicit', $result['rendered_updates']);
        $this->assertArrayNotHasKey('global_global', $result['rendered_updates']);
        $this->assertArrayNotHasKey('module_module', $result['rendered_updates']);
        $this->assertSame('This should be used', $result['rendered_updates']['explicit_explicit']);
    }

    /**
     * Test that when no fallbacks are available, an empty rendered_updates is returned.
     */
    public function testReturnsEmptyWhenNoQuotesAvailable(): void
    {
        // Ensure no global quotes
        if (isset($GLOBALS['quotes'])) {
            unset($GLOBALS['quotes']);
        }

        // Call with empty array and no shipping modules
        $result = oprc_prepare_delivery_updates_for_quotes([], null, []);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('rendered_updates', $result);
        $this->assertIsArray($result['rendered_updates']);
        $this->assertEmpty($result['rendered_updates']);
    }
}
