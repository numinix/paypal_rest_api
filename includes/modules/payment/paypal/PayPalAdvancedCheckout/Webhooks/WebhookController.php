<?php
/**
 * PayPal Advanced Checkout Webhook Controller
 * This controller parses the incoming webhook and brokers the
 * necessary steps for validation and dispatching based on the
 * nature of the webhook content.
 *
 * @copyright Copyright 2023-2025 Zen Cart Development Team
 * @license https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 * @version $Id: DrByte June 2025 $
 *
 * Last updated: v1.3.11
 */

namespace PayPalAdvancedCheckout\Webhooks;

use PayPalAdvancedCheckout\Common\Logger;

class WebhookController
{
    protected bool $enableDebugFileLogging;
    /** @var Logger */
    protected $ppac_logger;

    public function __construct(?bool $enableDebugFileLogging = null)
    {
        if ($enableDebugFileLogging === null) {
            $enableDebugFileLogging = defined('MODULE_PAYMENT_PAYPALAC_DEBUGGING')
                && strpos(MODULE_PAYMENT_PAYPALAC_DEBUGGING, 'Log') !== false;
        }

        $this->enableDebugFileLogging = $enableDebugFileLogging;
    }
    public function __invoke(): ?bool
    {
        defined('TABLE_PAYPAL_WEBHOOKS') or define('TABLE_PAYPAL_WEBHOOKS', DB_PREFIX . 'paypal_webhooks');

        // Inspect and collect webhook details
        $request_method = $_SERVER['REQUEST_METHOD'];
        $request_headers = getallheaders();
        $request_body = file_get_contents('php://input');
        $json_body = json_decode($request_body, true);
        $user_agent = $_SERVER['HTTP_USER_AGENT'];
        $event = $json_body['event_type'] ?? '(event not determined)';
        $summary = $json_body['summary'] ?? '(summary not determined)';
        $logIdentifier = $json_body['id'] ?? $json_body['event_type'] ?? '';

        // Create logger, just for logging to /logs directory
        $this->ppac_logger = new Logger($logIdentifier);

        // Enable logging
        if ($this->enableDebugFileLogging) {
            $this->ppac_logger->enableDebug();
        }

        // log that we got an incoming webhook, and its details
        $this->ppac_logger->write("ppac_webhook ($event, $user_agent, $request_method) starts.\n" . Logger::logJSON($json_body), true);

        // set object, which will be used for validation and for dispatching
        $webhook = new WebhookObject($request_method, $request_headers, $request_body, $user_agent);

        // prepare for verification
        $verifier = new WebhookResponder($webhook);

        // Ensure that the incoming request contains headers etc relevant to PayPal
        if (!$verifier->shouldRespond()) {
            $this->ppac_logger->write('ppac_webhook IGNORED DUE TO HEADERS MISMATCH' . "\n" . print_r($request_headers, true), false, 'before');
            $this->saveToDatabase($user_agent, $request_method, $request_body, $request_headers, 'ignored');
            return false;
        }

        // Verify that the webhook's signature is valid, to avoid spoofing and fraud, and wasted processing cycles
        $status = $verifier->verify();

        if ($status === null) {
            // For future dev: null means this webhook handler should be ignored, and go to next one
            // Probably this logic would be in a loop of classes being iterated, and would respond null to loop to the next one.
            $this->saveToDatabase($user_agent, $request_method, $request_body, $request_headers, 'skipped');
            return null;
        }

        // This should never happen, but we must abort if verification fails.
        if ($status === false) {
            $this->ppac_logger->write('ppac_webhook FAILED VERIFICATION', false, 'before');
            // The verifier already sent an HTTP response, so we just exit here by returning false to the ppac_webhook handler script.
            $this->saveToDatabase($user_agent, $request_method, $request_body, $request_headers, 'failed');
            return false;
        }

        $this->ppac_logger->write("\n\n" . 'webhook verification passed', false, 'before');

        // Log the verified webhook to the database
        $this->saveToDatabase($user_agent, $request_method, $request_body, $request_headers, 'verified');

        // Now that verification has passed, dispatch the webhook according to the declared event_type
        return $this->dispatch($event, $webhook);
    }

    protected function dispatch(string $event, WebhookObject $webhook): bool
    {
        // Lookup class name
        $objectName = 'PayPalAdvancedCheckout\Webhooks\Events\\' . $this->strToStudly($event);

        if (class_exists($objectName)) {
//debug:    $this->ppac_logger->write('class found: ' . $objectName, false, 'before');

            $call = new $objectName($webhook);
            if ($call->eventTypeIsSupported()) {
                $this->ppac_logger->write("\n\n" . 'webhook event supported by ' . $objectName . "\n", false, 'before');

                // dispatch to take the necessary action for the webhook
                $call->action();

                return true;
            }
        }
        $this->ppac_logger->write('class NOT found: ' . $objectName, false, 'before');
        return false;
    }

    /**
     * Convert string to Studly/CamelCase, using space, dot, hyphen, underscore as word break indicators
     */
    protected function strToStudly(string $value, array $dividers = ['.', '-', '_']): string
    {
        $words = explode(' ', str_replace($dividers, ' ', strtolower($value)));
        $studlyWords = array_map(static fn($word) => mb_strtoupper(mb_substr($word, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($word, 1, null, 'UTF-8'), $words);
        return implode($studlyWords);
    }

    /**
     * Save webhook records to database for subsequent querying
     *
     * @param string $user_agent
     * @param string $request_method
     * @param string $request_body
     * @param array|string|null $request_headers
     * @param string $verification_status One of: verified, failed, ignored, skipped
     */
    protected function saveToDatabase(string $user_agent, string $request_method, string $request_body, $request_headers, string $verification_status = 'verified'): void
    {
        $json_body = json_decode($request_body, true);

        $sql_data_array = [
            'webhook_id' => substr($json_body['id'] ?? '(webhook id not determined)', 0, 64),
            'event_type' => substr($json_body['event_type'] ?? '(event not determined)', 0, 64),
            'user_agent' => substr($user_agent, 0, 192),
            'request_method' => substr($request_method, 0, 32),
            'request_headers' => \json_encode($request_headers ?? []),
            'body' => $request_body,
            'verification_status' => substr($verification_status, 0, 16),
        ];

        // ensure table exists
        $this->createDatabaseTable();

        // store
        zen_db_perform(TABLE_PAYPAL_WEBHOOKS, $sql_data_array);
    }

    /**
     * Ensure database table exists
     */
    protected function createDatabaseTable(): void
    {
        global $db;
        $db->Execute(
            "CREATE TABLE IF NOT EXISTS " . TABLE_PAYPAL_WEBHOOKS . " (
                id BIGINT NOT NULL AUTO_INCREMENT,
                webhook_id VARCHAR(64) NOT NULL,
                event_type VARCHAR(64) DEFAULT NULL,
                body LONGTEXT NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                user_agent VARCHAR(192) DEFAULT NULL,
                request_method VARCHAR(32) DEFAULT NULL,
                request_headers TEXT DEFAULT NULL,
                verification_status VARCHAR(16) NOT NULL DEFAULT 'verified',
                PRIMARY KEY (id),
                KEY idx_pprwebhook_zen (webhook_id, id, created_at)
            )"
        );
    }
}
