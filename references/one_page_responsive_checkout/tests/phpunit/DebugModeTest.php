<?php

use PHPUnit\Framework\TestCase;

/**
 * Test that debug mode configuration controls logging behavior
 */
final class DebugModeTest extends TestCase
{
    private static string $stubBaseDir;
    private static bool $functionsLoaded = false;

    public static function setUpBeforeClass(): void
    {
        self::$stubBaseDir = __DIR__ . '/stubs';
        self::ensureStubDirectories();
        self::defineFrameworkConstants();
        self::loadCheckoutProcessFunctions();
    }

    private static function ensureStubDirectories(): void
    {
        if (!is_dir(self::$stubBaseDir)) {
            mkdir(self::$stubBaseDir, 0777, true);
        }
        if (!is_dir(self::$stubBaseDir . '/classes')) {
            mkdir(self::$stubBaseDir . '/classes', 0777, true);
        }
    }

    private static function defineFrameworkConstants(): void
    {
        if (!defined('DIR_WS_CLASSES')) {
            define('DIR_WS_CLASSES', self::$stubBaseDir . '/classes/');
        }
        if (!defined('DIR_FS_LOGS')) {
            define('DIR_FS_LOGS', self::$stubBaseDir . '/logs/');
        }
    }

    private static function loadCheckoutProcessFunctions(): void
    {
        if (self::$functionsLoaded) {
            return;
        }

        $functionsFile = dirname(__DIR__, 2) . '/catalog/includes/functions/extra_functions/oprc_checkout_process.php';
        if (file_exists($functionsFile)) {
            require_once $functionsFile;
            self::$functionsLoaded = true;
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        global $oprcCheckoutDebugTrace;
        $oprcCheckoutDebugTrace = [];
    }

    public function testDebugCheckpointWithoutDebugModeDoesNotLog(): void
    {
        // Ensure OPRC_DEBUG_MODE is not defined or is false
        if (defined('OPRC_DEBUG_MODE')) {
            $this->markTestSkipped('OPRC_DEBUG_MODE is already defined');
        }

        // Capture error_log output by temporarily redirecting it
        $logFile = sys_get_temp_dir() . '/oprc_test_nolog_' . uniqid() . '.log';
        $originalErrorLog = ini_get('error_log');
        ini_set('error_log', $logFile);

        oprc_debug_checkpoint('test checkpoint', ['key' => 'value']);

        // Restore original error_log setting
        ini_set('error_log', $originalErrorLog);

        // Read the log file
        $logContents = file_exists($logFile) ? file_get_contents($logFile) : '';
        if (file_exists($logFile)) {
            unlink($logFile);
        }

        // Verify that no log message was written
        $this->assertEmpty($logContents, 'No logs should be written when OPRC_DEBUG_MODE is not enabled');

        // Verify that the checkpoint was still added to the trace
        global $oprcCheckoutDebugTrace;
        $this->assertCount(1, $oprcCheckoutDebugTrace);
        $this->assertEquals('test checkpoint', $oprcCheckoutDebugTrace[0]['label']);
    }

    public function testDebugCheckpointWithDebugModeEnabledLogs(): void
    {
        // Skip if OPRC_DEBUG_MODE cannot be defined
        if (defined('OPRC_DEBUG_MODE')) {
            $this->markTestSkipped('OPRC_DEBUG_MODE is already defined');
        }

        // Define OPRC_DEBUG_MODE as true
        define('OPRC_DEBUG_MODE', 'true');

        // Capture error_log output by temporarily redirecting it
        $logFile = sys_get_temp_dir() . '/oprc_test_' . uniqid() . '.log';
        ini_set('error_log', $logFile);

        oprc_debug_checkpoint('test checkpoint enabled', ['key' => 'value']);

        // Read the log file
        $logContents = file_exists($logFile) ? file_get_contents($logFile) : '';
        if (file_exists($logFile)) {
            unlink($logFile);
        }

        // Verify that the log message was written
        $this->assertStringContainsString('OPRC checkout_process debug: test checkpoint enabled', $logContents);
        $this->assertStringContainsString('"key":"value"', $logContents);
    }

    public function testDebugLogTraceWithoutDebugModeDoesNotLog(): void
    {
        // Skip this test if OPRC_DEBUG_MODE is already defined (from previous test in suite)
        if (defined('OPRC_DEBUG_MODE') && OPRC_DEBUG_MODE === 'true') {
            $this->markTestSkipped('OPRC_DEBUG_MODE is already enabled from a previous test');
        }

        global $oprcCheckoutDebugTrace;
        $oprcCheckoutDebugTrace = [
            ['label' => 'checkpoint 1', 'time' => microtime(true)],
            ['label' => 'checkpoint 2', 'time' => microtime(true), 'context' => ['data' => 'test']],
        ];

        // Capture error_log output by temporarily redirecting it
        $logFile = sys_get_temp_dir() . '/oprc_test_trace_nolog_' . uniqid() . '.log';
        $originalErrorLog = ini_get('error_log');
        ini_set('error_log', $logFile);

        oprc_debug_log_trace('test reason');

        // Restore original error_log setting
        ini_set('error_log', $originalErrorLog);

        // Read the log file
        $logContents = file_exists($logFile) ? file_get_contents($logFile) : '';
        if (file_exists($logFile)) {
            unlink($logFile);
        }

        // Verify that no log message was written
        $this->assertEmpty($logContents, 'No logs should be written when OPRC_DEBUG_MODE is not enabled');
    }

    public function testDebugFormatContextHandlesComplexData(): void
    {
        $context = [
            'string' => 'test',
            'number' => 123,
            'float' => 45.67,
            'bool' => true,
            'null' => null,
            'array' => ['nested' => 'value'],
        ];

        $result = oprc_debug_format_context($context);

        $this->assertIsString($result);
        $this->assertStringContainsString('"string":"test"', $result);
        $this->assertStringContainsString('"number":123', $result);
        $this->assertStringContainsString('"bool":true', $result);
    }

    public function testDebugFormatContextTruncatesLongStrings(): void
    {
        // Create a context with a very long string
        $longString = str_repeat('a', 3000);
        $context = ['long_value' => $longString];

        $result = oprc_debug_format_context($context);

        // Verify that the result is truncated
        $this->assertLessThanOrEqual(2003, strlen($result)); // 2000 + "..."
        $this->assertStringEndsWith('...', $result);
    }
}
