<?php

use PHPUnit\Framework\TestCase;

class LoginCheckTest extends TestCase
{
    public function testReturnsLoggedInFlagWhenCustomerPresent(): void
    {
        $this->assertSame('1', oprc_login_check_response(['customer_id' => 99]));
    }

    public function testReturnsLoggedOutFlagWhenCustomerMissing(): void
    {
        $this->assertSame('0', oprc_login_check_response([]));
    }
}
