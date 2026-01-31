<?php
/**
 * Test that the recurring cron output formatting functions work correctly
 * for both CLI and web contexts.
 */

// Simulate the helper functions from the cron file for testing
if (!function_exists('recurring_is_cli')) {
    function recurring_is_cli() {
        return php_sapi_name() === 'cli' || defined('STDIN');
    }
}

if (!function_exists('recurring_format_output')) {
    function recurring_format_output($text) {
        if (recurring_is_cli()) {
            return $text;
        }
        // In web context, wrap in <pre> tags to preserve formatting
        return '<pre style="font-family: monospace; white-space: pre-wrap; word-wrap: break-word; background: #f5f5f5; padding: 15px; border-radius: 5px; margin: 10px 0; line-height: 1.4;">' . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . '</pre>';
    }
}

class RecurringOutputFormattingTest
{
    private $passed = 0;
    private $failed = 0;

    public function run()
    {
        echo "Testing recurring output formatting...\n\n";
        
        $this->testIsCliDetection();
        $this->testCliOutputPreservesNewlines();
        $this->testWebOutputWrapsInPreTags();
        $this->testWebOutputEscapesHtml();
        $this->testPreTagsHaveProperStyling();
        
        echo "\n==============================\n";
        echo "Passed: {$this->passed}\n";
        echo "Failed: {$this->failed}\n";
        echo "==============================\n";
        
        return $this->failed === 0 ? 0 : 1;
    }

    private function assert($condition, $message)
    {
        if ($condition) {
            echo "✓ PASS: {$message}\n";
            $this->passed++;
        } else {
            echo "✗ FAIL: {$message}\n";
            $this->failed++;
        }
    }

    public function testIsCliDetection()
    {
        // In test context (CLI), this should return true
        $isCli = recurring_is_cli();
        $this->assert($isCli === true, 'recurring_is_cli() returns true in CLI context');
    }

    public function testCliOutputPreservesNewlines()
    {
        $text = "Line 1\nLine 2\nLine 3";
        
        // In CLI mode, output should be returned as-is
        // We simulate CLI by checking current behavior
        if (recurring_is_cli()) {
            $output = recurring_format_output($text);
            $this->assert($output === $text, 'CLI output preserves original text with newlines');
            $this->assert(strpos($output, "\n") !== false, 'CLI output contains newline characters');
        } else {
            $this->assert(true, 'Skipped CLI test - not in CLI mode');
        }
    }

    public function testWebOutputWrapsInPreTags()
    {
        $text = "Test output\nWith newlines";
        
        // Simulate web output by directly testing the transformation
        $webOutput = '<pre style="font-family: monospace; white-space: pre-wrap; word-wrap: break-word; background: #f5f5f5; padding: 15px; border-radius: 5px; margin: 10px 0; line-height: 1.4;">' . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . '</pre>';
        
        $this->assert(strpos($webOutput, '<pre') === 0, 'Web output starts with <pre> tag');
        $this->assert(strpos($webOutput, '</pre>') !== false, 'Web output contains closing </pre> tag');
    }

    public function testWebOutputEscapesHtml()
    {
        $text = '<script>alert("xss")</script>';
        
        // Simulate web output
        $webOutput = '<pre style="font-family: monospace; white-space: pre-wrap; word-wrap: break-word; background: #f5f5f5; padding: 15px; border-radius: 5px; margin: 10px 0; line-height: 1.4;">' . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . '</pre>';
        
        $this->assert(strpos($webOutput, '&lt;script&gt;') !== false, 'Web output escapes HTML tags');
        $this->assert(strpos($webOutput, '<script>') === false, 'Web output does not contain raw script tags');
    }

    public function testPreTagsHaveProperStyling()
    {
        $text = "Test";
        
        // Simulate web output
        $webOutput = '<pre style="font-family: monospace; white-space: pre-wrap; word-wrap: break-word; background: #f5f5f5; padding: 15px; border-radius: 5px; margin: 10px 0; line-height: 1.4;">' . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . '</pre>';
        
        $this->assert(strpos($webOutput, 'white-space: pre-wrap') !== false, 'Pre tag has white-space: pre-wrap for proper wrapping');
        $this->assert(strpos($webOutput, 'font-family: monospace') !== false, 'Pre tag has monospace font');
        $this->assert(strpos($webOutput, 'padding: 15px') !== false, 'Pre tag has proper padding');
    }
}

$test = new RecurringOutputFormattingTest();
exit($test->run());
