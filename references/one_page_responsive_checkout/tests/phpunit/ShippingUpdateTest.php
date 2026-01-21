<?php

use PHPUnit\Framework\TestCase;

class ShippingUpdateTest extends TestCase
{
    public function testReturnsLoginRedirectWhenCustomerMissing(): void
    {
        $preconditions = oprc_validate_checkout_state(
            [],
            new class {
                public function count_contents()
                {
                    return 1;
                }
            },
            null,
            fn () => 'login-url',
            fn () => 'cart-url'
        );

        $this->assertSame('login-url', $preconditions['redirect_url']);
    }

    public function testReturnsCartRedirectWhenCartEmpty(): void
    {
        $preconditions = oprc_validate_checkout_state(
            ['customer_id' => 10],
            new class {
                public function count_contents()
                {
                    return 0;
                }
            },
            null,
            fn () => 'login-url',
            fn () => 'cart-url'
        );

        $this->assertSame('cart-url', $preconditions['redirect_url']);
    }

    public function testReturnsNullWhenSessionValid(): void
    {
        $result = oprc_validate_checkout_state(
            ['customer_id' => 10],
            new class {
                public function count_contents()
                {
                    return 2;
                }
            },
            fn () => true,
            fn () => 'login-url',
            fn () => 'cart-url'
        );

        $this->assertNull($result);
    }

    public function testDeterminesShippingSelectionFromPrimaryField(): void
    {
        $this->assertSame('flat.flat', oprc_determine_shipping_selection(['shipping' => 'flat.flat']));
    }

    public function testDeterminesShippingSelectionFromFallbackField(): void
    {
        $this->assertSame('storepickup.pickup', oprc_determine_shipping_selection(['shipping_method' => 'storepickup.pickup']));
    }

    public function testDeterminesShippingSelectionReturnsNullWhenMissing(): void
    {
        $this->assertNull(oprc_determine_shipping_selection([]));
    }
}
