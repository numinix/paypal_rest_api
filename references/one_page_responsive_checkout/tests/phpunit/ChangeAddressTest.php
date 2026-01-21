<?php

use PHPUnit\Framework\TestCase;

class ChangeAddressTest extends TestCase
{
    public function testDetectsShippingAddressChangeForDifferentSendTo(): void
    {
        $this->assertTrue(oprc_has_shipping_address_changed('billto', 5, 6));
    }

    public function testDetectsShippingAddressChangeWhenAddressTypeRequiresShipping(): void
    {
        $this->assertTrue(oprc_has_shipping_address_changed('shipto', 10, 10));
    }

    public function testRecognizesUnchangedBillingAddress(): void
    {
        $this->assertFalse(oprc_has_shipping_address_changed('billto', 3, 3));
    }

    public function testPreconditionFailureTriggersLoginRedirect(): void
    {
        $preconditions = oprc_validate_checkout_state(
            ['customer_id' => 20],
            new class {
                public function count_contents()
                {
                    return 2;
                }
            },
            fn () => false,
            fn () => 'login-url',
            fn () => 'cart-url'
        );

        $this->assertSame('login-url', $preconditions['redirect_url']);
    }
}
