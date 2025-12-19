<?php
/**
 * Observer for Numinix PayPal signup telemetry events.
 */

if (!defined('IS_ADMIN_FLAG')) {
    return;
}

class numinix_paypal_signup extends base
{
    /**
     * @var string|null
     */
    protected $logFile;

    public function __construct()
    {
        global $zco_notifier;

        if (!isset($zco_notifier) || !is_object($zco_notifier)) {
            return;
        }

        $zco_notifier->attach($this, ['NOTIFY_NUMINIX_PAYPAL_ISU_EVENT']);
        $this->logFile = $this->resolveLogFile();
    }

    /**
     * Receives telemetry events from the notifier and writes log entries.
     *
     * @param mixed                $class
     * @param string               $eventId
     * @param array<string, mixed> $params
     * @return void
     */
    public function update(&$class, $eventId, $params)
    {
        if ($eventId !== 'NOTIFY_NUMINIX_PAYPAL_ISU_EVENT') {
            return;
        }

        if (!is_array($params)) {
            return;
        }

        $record = $this->formatRecord($params);
        if ($record !== null) {
            $this->writeLog($record);
        }
    }

    /**
     * Formats the payload into a structured JSON string.
     *
     * @param array<string, mixed> $payload
     * @return string|null
     */
    protected function formatRecord(array $payload): ?string
    {
        $timestamp = isset($payload['timestamp']) ? (int)$payload['timestamp'] : time();

        $record = [
            'timestamp' => date('c', $timestamp),
            'event' => isset($payload['event']) ? (string)$payload['event'] : '',
            'mode' => isset($payload['mode']) ? (string)$payload['mode'] : '',
            'environment' => isset($payload['environment']) ? (string)$payload['environment'] : '',
            'zen_cart_version' => isset($payload['zen_cart_version']) ? (string)$payload['zen_cart_version'] : '',
            'plugin_version' => isset($payload['plugin_version']) ? (string)$payload['plugin_version'] : '',
        ];

        if (!empty($payload['tracking_id'])) {
            $record['tracking_id'] = (string)$payload['tracking_id'];
        }

        if (!empty($payload['step'])) {
            $record['step'] = (string)$payload['step'];
        }

        if (!empty($payload['context']) && is_array($payload['context'])) {
            $record['context'] = $this->sanitizeValue($payload['context']);
        }

        $encoded = json_encode($record, JSON_UNESCAPED_SLASHES);
        if ($encoded === false) {
            return null;
        }

        return $encoded;
    }

    /**
     * Writes a single log entry to the log file.
     *
     * @param string $line
     * @return void
     */
    protected function writeLog(string $line): void
    {
        if ($line === '' || $this->logFile === null) {
            return;
        }

        $directory = dirname($this->logFile);
        if (!is_dir($directory)) {
            if (!mkdir($directory, 0775, true) && !is_dir($directory)) {
                if (function_exists('error_log')) {
                    error_log('Numinix PayPal ISU observer unable to create log directory: ' . $directory);
                }
                return;
            }
        }

        $result = file_put_contents($this->logFile, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
        if ($result === false && function_exists('error_log')) {
            error_log('Numinix PayPal ISU observer unable to write log file: ' . $this->logFile);
        }
    }

    /**
     * Resolves the log file path.
     *
     * @return string|null
     */
    protected function resolveLogFile(): ?string
    {
        $baseDir = null;
        if (defined('DIR_FS_LOGS')) {
            $baseDir = DIR_FS_LOGS;
        } elseif (defined('DIR_FS_CATALOG')) {
            $baseDir = rtrim(DIR_FS_CATALOG, '\\/') . '/logs';
        }

        if ($baseDir === null) {
            return null;
        }

        $baseDir = rtrim($baseDir, '\\/');

        return $baseDir . '/numinix_paypal_signup.log';
    }

    /**
     * Sanitizes context values prior to logging.
     *
     * @param mixed $value
     * @return mixed
     */
    protected function sanitizeValue($value)
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_scalar($value) || $value === null) {
            return $value;
        }

        if (is_array($value)) {
            $sanitized = [];
            foreach ($value as $key => $item) {
                if (!is_int($key) && !is_string($key)) {
                    continue;
                }
                $sanitized[$key] = $this->sanitizeValue($item);
            }

            return $sanitized;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format(DateTime::ATOM);
        }

        if (is_object($value) && method_exists($value, '__toString')) {
            return (string)$value;
        }

        return (string)$value;
    }
}
