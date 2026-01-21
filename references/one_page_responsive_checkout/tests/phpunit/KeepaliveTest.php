<?php

use PHPUnit\Framework\TestCase;

class KeepaliveTest extends TestCase
{
    public function testReturnsSuccessTrueAlways(): void
    {
        $response = oprc_keepalive_response([]);
        $this->assertTrue($response['success']);
    }

    public function testReturnsLoggedInTrueWhenCustomerIdPresent(): void
    {
        $response = oprc_keepalive_response(['customer_id' => 123]);
        $this->assertTrue($response['logged_in']);
    }

    public function testReturnsLoggedInFalseWhenCustomerIdMissing(): void
    {
        $response = oprc_keepalive_response([]);
        $this->assertFalse($response['logged_in']);
    }

    public function testReturnsLoggedInFalseWhenCustomerIdEmpty(): void
    {
        $response = oprc_keepalive_response(['customer_id' => '']);
        $this->assertFalse($response['logged_in']);
    }

    public function testReturnsLoggedInFalseWhenCustomerIdZero(): void
    {
        $response = oprc_keepalive_response(['customer_id' => 0]);
        $this->assertFalse($response['logged_in']);
    }
}
