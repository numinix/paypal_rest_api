<?php
/**
 * PayPalAdvancedCheckout – stable cron dispatcher.
 *
 * Point crontab at zc_plugins/PayPalAdvancedCheckout/cron.php once; upgrades
 * only add new vX.Y.Z folders. Pass the job name as argv[1], e.g.:
 *   php zc_plugins/PayPalAdvancedCheckout/cron.php paypalac_saved_card_recurring
 *
 * Legacy catalog cron/*.php shims remain supported after install/upgrade.
 */

if (PHP_SAPI !== 'cli') {
    header('HTTP/1.1 403 Forbidden');
    exit('This script must be run from the command line.');
}

$job = $argv[1] ?? '';
$allowed = [
    'paypalac_saved_card_recurring',
    'paypalac_remove_expired_cards',
    'paypalac_subscription_cancellations',
    'paypalac_recurring_reminders',
];

if ($job === '' || !in_array($job, $allowed, true)) {
    fwrite(STDERR, "[PayPalAdvancedCheckout] Usage: php cron.php <" . implode('|', $allowed) . ">\n");
    exit(1);
}

$pluginRoot = __DIR__;
$bestRunner = null;
$bestVersion = '0.0.0';

foreach (glob($pluginRoot . '/v*', GLOB_ONLYDIR) ?: [] as $versionDir) {
    $folder = basename($versionDir);
    if (!preg_match('/^v\d+(?:\.\d+){0,2}$/', $folder)) {
        continue;
    }

    $runner = $versionDir . '/catalog/cron/' . $job . '.php';
    if (!is_file($runner)) {
        continue;
    }

    $semver = ltrim($folder, 'v');
    if ($bestRunner === null || version_compare($semver, $bestVersion, '>')) {
        $bestVersion = $semver;
        $bestRunner = $runner;
    }
}

if ($bestRunner === null) {
    fwrite(STDERR, "[PayPalAdvancedCheckout] No cron runner found for job {$job}\n");
    exit(1);
}

$runnerDir = dirname($bestRunner);
chdir($runnerDir);
require $bestRunner;
