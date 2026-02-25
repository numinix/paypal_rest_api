<?php
/**
 * Test that the MessageStack compatibility class properly outputs messages.
 * 
 * This test verifies that the messageStack class:
 * 1. Has an output() method
 * 2. Loads messages from session on initialization
 * 3. Renders messages with appropriate HTML and alert classes
 * 4. Handles success, error, and warning message types correctly
 */

use PHPUnit\Framework\TestCase;

class MessageStackOutputTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Initialize session array (no need to start actual session in CLI tests)
        if (!isset($_SESSION)) {
            $_SESSION = [];
        }
        
        // Clear any existing session messages
        if (isset($_SESSION['messageToStack'])) {
            unset($_SESSION['messageToStack']);
        }
        
        // Load the MessageStack compatibility class
        $messageStackPath = __DIR__ . '/../includes/modules/payment/paypal/PayPalAdvancedCheckout/Compatibility/MessageStack.php';
        if (file_exists($messageStackPath)) {
            // Only load if not already loaded (the class file returns early if already defined)
            require_once $messageStackPath;
        }
    }

    public function testMessageStackHasOutputMethod(): void
    {
        $messageStack = new messageStack();
        $this->assertTrue(method_exists($messageStack, 'output'), 'messageStack should have an output() method');
    }

    public function testMessageStackOutputsSuccessMessage(): void
    {
        $messageStack = new messageStack();
        $messageStack->add('test_stack', 'Test success message', 'success');
        
        $output = $messageStack->output('test_stack');
        
        $this->assertStringContainsString('Test success message', $output);
        $this->assertStringContainsString('alert-success', $output);
        $this->assertStringContainsString('messageStack-header', $output);
    }

    public function testMessageStackOutputsErrorMessage(): void
    {
        $messageStack = new messageStack();
        $messageStack->add('test_stack', 'Test error message', 'error');
        
        $output = $messageStack->output('test_stack');
        
        $this->assertStringContainsString('Test error message', $output);
        $this->assertStringContainsString('alert-danger', $output);
    }

    public function testMessageStackOutputsWarningMessage(): void
    {
        $messageStack = new messageStack();
        $messageStack->add('test_stack', 'Test warning message', 'warning');
        
        $output = $messageStack->output('test_stack');
        
        $this->assertStringContainsString('Test warning message', $output);
        $this->assertStringContainsString('alert-warning', $output);
    }

    public function testMessageStackLoadsSessionMessages(): void
    {
        // Set up session messages
        $_SESSION['messageToStack'] = [
            'test_stack' => [
                ['text' => 'Session message 1', 'type' => 'success'],
                ['text' => 'Session message 2', 'type' => 'error']
            ]
        ];
        
        // Create a new messageStack instance - should load from session
        $messageStack = new messageStack();
        
        // Session should be cleared after loading
        $this->assertArrayNotHasKey('messageToStack', $_SESSION, 'Session messages should be cleared after loading');
        
        // Messages should be loaded into the stack
        $this->assertEquals(2, $messageStack->size('test_stack'));
        
        // Output should contain both messages
        $output = $messageStack->output('test_stack');
        $this->assertStringContainsString('Session message 1', $output);
        $this->assertStringContainsString('Session message 2', $output);
    }

    public function testMessageStackAddSessionStoresInSession(): void
    {
        $messageStack = new messageStack();
        $messageStack->add_session('archive_test', 'Subscription has been archived', 'success');
        
        // Message should be in session
        $this->assertArrayHasKey('messageToStack', $_SESSION);
        $this->assertArrayHasKey('archive_test', $_SESSION['messageToStack']);
        $this->assertCount(1, $_SESSION['messageToStack']['archive_test']);
        $this->assertEquals('Subscription has been archived', $_SESSION['messageToStack']['archive_test'][0]['text']);
        $this->assertEquals('success', $_SESSION['messageToStack']['archive_test'][0]['type']);
    }

    public function testMessageStackOutputEscapesHtml(): void
    {
        $messageStack = new messageStack();
        $messageStack->add('test_stack', '<script>alert("xss")</script>', 'error');
        
        $output = $messageStack->output('test_stack');
        
        // Should NOT contain unescaped script tags
        $this->assertStringNotContainsString('<script>', $output);
        // Should contain escaped version
        $this->assertStringContainsString('&lt;script&gt;', $output);
    }

    public function testMessageStackOutputReturnsEmptyStringForEmptyStack(): void
    {
        $messageStack = new messageStack();
        $output = $messageStack->output('nonexistent_stack');
        
        $this->assertEquals('', $output);
    }

    public function testMessageStackHandlesMultipleMessages(): void
    {
        $messageStack = new messageStack();
        $messageStack->add('test_stack', 'Message 1', 'success');
        $messageStack->add('test_stack', 'Message 2', 'error');
        $messageStack->add('test_stack', 'Message 3', 'warning');
        
        $output = $messageStack->output('test_stack');
        
        $this->assertStringContainsString('Message 1', $output);
        $this->assertStringContainsString('Message 2', $output);
        $this->assertStringContainsString('Message 3', $output);
        $this->assertStringContainsString('alert-success', $output);
        $this->assertStringContainsString('alert-danger', $output);
        $this->assertStringContainsString('alert-warning', $output);
    }

    protected function tearDown(): void
    {
        // Clean up session
        if (isset($_SESSION['messageToStack'])) {
            unset($_SESSION['messageToStack']);
        }
        parent::tearDown();
    }
}
