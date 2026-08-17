<?php
/**
 * Idempotency and recovery helpers for PayPal Advanced Checkout.
 *
 * Capture/authorize must never reuse the create-order PayPal-Request-Id.
 * When PayPal returns ORDER_ALREADY_CAPTURED / ORDER_ALREADY_AUTHORIZED,
 * inspect the live order and either treat an existing payment as success
 * or recapture an APPROVED leftover with a new request id.
 *
 * @copyright Copyright 2023-2026 Zen Cart Development Team
 * @license http://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */

namespace PayPalAdvancedCheckout\Common;

class CheckoutRecovery
{
    public const DUPLICATE_PAYMENT_ACTION_ISSUES = [
        'ORDER_ALREADY_CAPTURED',
        'ORDER_ALREADY_AUTHORIZED',
    ];

    public const PRESERVE_ORDER_STATUSES = [
        'CREATED',
        'APPROVED',
        'PAYER_ACTION_REQUIRED',
        'SAVED',
        'COMPLETED',
        'CAPTURED',
    ];

    public static function paymentActionRequestId(string $guid, bool $should_capture): string
    {
        $guid = trim($guid);
        if ($guid === '') {
            $guid = bin2hex(random_bytes(8));
        }

        return $guid . ($should_capture ? '-capture' : '-authorize');
    }

    public static function paymentActionRetryRequestId(string $base_request_id): string
    {
        $base_request_id = trim($base_request_id);
        if ($base_request_id === '') {
            $base_request_id = bin2hex(random_bytes(8));
        }

        return $base_request_id . '-retry-' . bin2hex(random_bytes(3));
    }

    public static function newCreateRequestId(string $guid): string
    {
        $guid = trim($guid);
        if ($guid === '') {
            $guid = bin2hex(random_bytes(8));
        }

        return $guid . '-new-' . bin2hex(random_bytes(3));
    }

    public static function paypalIssueFromErrorInfo(array $error_info): string
    {
        if (!empty($error_info['details']) && is_array($error_info['details'])) {
            foreach ($error_info['details'] as $detail) {
                if (!is_array($detail)) {
                    continue;
                }
                $issue = strtoupper(trim((string)($detail['issue'] ?? '')));
                if ($issue !== '') {
                    return $issue;
                }
            }
        }

        return strtoupper(trim((string)($error_info['name'] ?? '')));
    }

    public static function isDuplicatePaymentActionIssue(array $error_info): bool
    {
        if (!empty($error_info['details']) && is_array($error_info['details'])) {
            foreach ($error_info['details'] as $detail) {
                if (!is_array($detail)) {
                    continue;
                }
                $issue = strtoupper(trim((string)($detail['issue'] ?? '')));
                if (in_array($issue, self::DUPLICATE_PAYMENT_ACTION_ISSUES, true)) {
                    return true;
                }
            }
        }

        $name = strtoupper(trim((string)($error_info['name'] ?? '')));
        return in_array($name, self::DUPLICATE_PAYMENT_ACTION_ISSUES, true);
    }

    public static function paypalOrderHasSuccessfulPaymentAction(array $paypal_order, bool $should_capture): bool
    {
        $bucket = $should_capture ? 'captures' : 'authorizations';
        $rows = $paypal_order['purchase_units'][0]['payments'][$bucket] ?? [];
        $ok_statuses = $should_capture
            ? ['COMPLETED', 'PENDING', 'CAPTURED']
            : ['CREATED', 'PENDING', 'CAPTURED', 'PARTIALLY_CAPTURED'];

        if (is_array($rows)) {
            foreach ($rows as $row) {
                if (!is_array($row) || ($row['id'] ?? '') === '') {
                    continue;
                }
                $status = strtoupper((string)($row['status'] ?? ''));
                if (in_array($status, $ok_statuses, true)) {
                    return true;
                }
            }
        }

        $order_status = strtoupper((string)($paypal_order['status'] ?? ''));
        if ($should_capture) {
            return in_array($order_status, ['COMPLETED', 'CAPTURED'], true);
        }

        return $order_status === 'COMPLETED';
    }

    public static function paypalOrderIsReusable(array $paypal_order): bool
    {
        $status = strtoupper((string)($paypal_order['status'] ?? ''));
        return in_array($status, ['CREATED', 'APPROVED', 'PAYER_ACTION_REQUIRED', 'SAVED'], true)
            || self::paypalOrderHasSuccessfulPaymentAction($paypal_order, true)
            || self::paypalOrderHasSuccessfulPaymentAction($paypal_order, false);
    }

    public static function shouldPreservePayPalOrderOnError(): bool
    {
        $paypal_order_id = trim((string)($_SESSION['PayPalAdvancedCheckout']['Order']['id'] ?? ''));
        if ($paypal_order_id === '') {
            return false;
        }

        $status = strtoupper((string)($_SESSION['PayPalAdvancedCheckout']['Order']['status'] ?? ''));
        return in_array($status, self::PRESERVE_ORDER_STATUSES, true);
    }

    public static function sessionOrderFromPayPalResponse(
        array $paypal_order,
        string $order_guid,
        string $ppac_type,
        array $amount_mismatch = []
    ): array {
        $paypal_id = (string)($paypal_order['id'] ?? '');
        $status = (string)($paypal_order['status'] ?? '');
        $copy = $paypal_order;
        unset(
            $copy['id'],
            $copy['status'],
            $copy['create_time'],
            $copy['links'],
            $copy['purchase_units'][0]['reference_id'],
            $copy['purchase_units'][0]['payee']
        );

        return [
            'current' => $copy,
            'id' => $paypal_id,
            'status' => $status,
            'guid' => $order_guid,
            'payment_source' => $ppac_type,
            'amount_mismatch' => $amount_mismatch,
        ];
    }

    public static function checkoutAlertContext(): string
    {
        $lines = ['Checkout context:'];
        $customer_id = (int)($_SESSION['customer_id'] ?? 0);
        if ($customer_id > 0) {
            $lines[] = 'Customer ID: ' . $customer_id;
        }

        $email = trim((string)($_SESSION['customer_email'] ?? $_SESSION['customers_email_address'] ?? ''));
        if ($email !== '') {
            $lines[] = 'Customer email: ' . $email;
        }

        $paypal_order_id = trim((string)($_SESSION['PayPalAdvancedCheckout']['Order']['id'] ?? ''));
        if ($paypal_order_id !== '') {
            $lines[] = 'PayPal order ID: ' . $paypal_order_id;
        }

        $status = trim((string)($_SESSION['PayPalAdvancedCheckout']['Order']['status'] ?? ''));
        if ($status !== '') {
            $lines[] = 'PayPal order status: ' . $status;
        }

        $guid = trim((string)($_SESSION['PayPalAdvancedCheckout']['Order']['guid'] ?? ''));
        if ($guid !== '') {
            $lines[] = 'Checkout GUID: ' . $guid;
        }

        if (count($lines) === 1) {
            return '';
        }

        return implode("\n", $lines) . "\n\n";
    }
}
