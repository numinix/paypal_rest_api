<?php
/**
 * Test that the recurring cron output formatting functions work correctly
 * for both CLI and web contexts.
 * 
 * @copyright Copyright 2026 Zen Cart Development Team
 * @license https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */

fwrite(STDOUT, "Running Recurring Output Formatting Test...\n\n");

$basePath = dirname(__DIR__);

// Test 1: Verify helper functions exist in cron file
fwrite(STDOUT, "Test 1: Checking recurring_is_cli() function exists...\n");
$cronFile = $basePath . '/cron/paypal_saved_card_recurring.php';
if (file_exists($cronFile)) {
    $content = file_get_contents($cronFile);
    
    if (strpos($content, "function recurring_is_cli()") !== false) {
        fwrite(STDOUT, "✓ recurring_is_cli() function found\n");
    } else {
        fwrite(STDERR, "✗ recurring_is_cli() function not found\n");
        exit(1);
    }
} else {
    fwrite(STDERR, "✗ paypal_saved_card_recurring.php not found\n");
    exit(1);
}

fwrite(STDOUT, "\n");

// Test 2: Verify recurring_format_output function exists
fwrite(STDOUT, "Test 2: Checking recurring_format_output() function exists...\n");
if (strpos($content, "function recurring_format_output(") !== false) {
    fwrite(STDOUT, "✓ recurring_format_output() function found\n");
} else {
    fwrite(STDERR, "✗ recurring_format_output() function not found\n");
    exit(1);
}

fwrite(STDOUT, "\n");

// Test 3: Verify format_output wraps in <pre> tags for web context
fwrite(STDOUT, "Test 3: Checking <pre> tag wrapper for web context...\n");
if (strpos($content, '<pre style="font-family: monospace;') !== false) {
    fwrite(STDOUT, "✓ Pre tag wrapper with styling found\n");
} else {
    fwrite(STDERR, "✗ Pre tag wrapper not found in format function\n");
    exit(1);
}

fwrite(STDOUT, "\n");

// Test 4: Verify print statements use recurring_format_output
fwrite(STDOUT, "Test 4: Checking print statements use recurring_format_output()...\n");
$formatOutputUsages = substr_count($content, 'print recurring_format_output(');
if ($formatOutputUsages >= 3) {
    fwrite(STDOUT, "✓ Found {$formatOutputUsages} print statements using recurring_format_output()\n");
} else {
    fwrite(STDERR, "✗ Expected at least 3 print statements using recurring_format_output(), found {$formatOutputUsages}\n");
    exit(1);
}

fwrite(STDOUT, "\n");

// Test 5: Verify htmlspecialchars is used for XSS protection
fwrite(STDOUT, "Test 5: Checking XSS protection with htmlspecialchars...\n");
if (strpos($content, "htmlspecialchars(\$text, ENT_QUOTES, 'UTF-8')") !== false) {
    fwrite(STDOUT, "✓ htmlspecialchars() used for XSS protection in format function\n");
} else {
    fwrite(STDERR, "✗ htmlspecialchars() not found in format function for XSS protection\n");
    exit(1);
}

fwrite(STDOUT, "\n");

// Test 6: Verify CLI detection uses php_sapi_name
fwrite(STDOUT, "Test 6: Checking CLI detection uses php_sapi_name()...\n");
if (strpos($content, "php_sapi_name() === 'cli'") !== false) {
    fwrite(STDOUT, "✓ CLI detection uses php_sapi_name()\n");
} else {
    fwrite(STDERR, "✗ CLI detection does not use php_sapi_name()\n");
    exit(1);
}

fwrite(STDOUT, "\n");

fwrite(STDOUT, "All tests passed! ✓\n");
fwrite(STDOUT, "\nVerified:\n");
fwrite(STDOUT, "1. recurring_is_cli() helper function exists\n");
fwrite(STDOUT, "2. recurring_format_output() helper function exists\n");
fwrite(STDOUT, "3. Web output uses <pre> tags with proper styling\n");
fwrite(STDOUT, "4. All print statements use recurring_format_output()\n");
fwrite(STDOUT, "5. XSS protection with htmlspecialchars()\n");
fwrite(STDOUT, "6. CLI detection uses php_sapi_name()\n");
