<?php

use PHPUnit\Framework\TestCase;

class AccountCheckTest extends TestCase
{
    public function testAccountCheckoutReturnsMatchFlagWhenEmailExists(): void
    {
        $db = new class {
            public function Execute($query)
            {
                return new class {
                    public function RecordCount()
                    {
                        return 1;
                    }
                };
            }
        };

        $postData = [
            'checkoutType' => 'account',
            'hide_email_address_register' => 'customer@example.com',
        ];

        $this->assertSame('1', oprc_account_check_response($postData, $db));
    }

    public function testAccountCheckoutReturnsNoMatchWhenEmailMissing(): void
    {
        $db = new class {
            public function Execute($query)
            {
                return new class {
                    public function RecordCount()
                    {
                        return 0;
                    }
                };
            }
        };

        $postData = [
            'checkoutType' => 'account',
            'hide_email_address_register' => 'missing@example.com',
        ];

        $this->assertSame('0', oprc_account_check_response($postData, $db));
    }

    public function testGuestCheckoutHonorsAlwaysAllowSetting(): void
    {
        $db = new class {
            public function Execute($query)
            {
                return new class {
                    public function RecordCount()
                    {
                        return 1;
                    }
                };
            }
        };

        $postData = [
            'checkoutType' => 'guest',
            'hide_email_address_register' => 'customer@example.com',
        ];

        $this->assertSame('0', oprc_account_check_response($postData, $db, 'true'));
    }

    public function testGuestCheckoutDetectsExistingAccountWhenAllowed(): void
    {
        $db = new class {
            public function Execute($query)
            {
                return new class {
                    public function RecordCount()
                    {
                        return 1;
                    }
                };
            }
        };

        $postData = [
            'checkoutType' => 'guest',
            'hide_email_address_register' => 'customer@example.com',
        ];

        $this->assertSame('1', oprc_account_check_response($postData, $db, 'false'));
    }
}
