<?php

use PHPUnit\Framework\TestCase;

final class AjaxRequestDetectionTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        require_once __DIR__ . '/../../catalog/includes/functions/extra_functions/oprc_checkout_process.php';
    }

    protected function setUp(): void
    {
        parent::setUp();
        $_REQUEST = [];
        $_SERVER = [];
    }

    public function testDetectsAjaxViaExplicitFlag(): void
    {
        $this->assertTrue(oprc_is_ajax_request(['request' => 'ajax'], []));
        $this->assertTrue(oprc_is_ajax_request(['request' => 'AJAX'], []));
    }

    public function testDetectsAjaxViaHeader(): void
    {
        $this->assertTrue(oprc_is_ajax_request([], ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']));
        $this->assertTrue(oprc_is_ajax_request([], ['HTTP_X_REQUESTED_WITH' => 'xmlhttprequest']));
    }

    public function testFallsBackToGlobals(): void
    {
        $_REQUEST['request'] = 'ajax';
        $this->assertTrue(oprc_is_ajax_request());

        $_REQUEST = [];
        $_SERVER = [];
        $this->assertFalse(oprc_is_ajax_request());
    }

    public function testReturnsFalseWhenNoIndicatorsPresent(): void
    {
        $this->assertFalse(oprc_is_ajax_request([], []));
    }
}
