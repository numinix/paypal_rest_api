<?php
/**
 * OPRC checkout process page controller.
 */

$zco_notifier->notify('NOTIFY_HEADER_START_OPRC_CHECKOUT_PROCESS');

require_once(DIR_FS_CATALOG . DIR_WS_INCLUDES . 'functions/extra_functions/oprc_checkout_process.php');

$isAjaxRequest = oprc_is_ajax_request();
$oprcProcessResponse = null;
$oprcProcessMessages = '';
$oprcConfirmationForm = [];
$oprcConfirmationDetails = [];

try {
    $oprcProcessResponse = oprc_checkout_process([
        'request_type' => $isAjaxRequest ? 'ajax' : 'page',
    ]);

    if ($isAjaxRequest) {
        $payload = $oprcProcessResponse;
        if (!isset($payload['status'])) {
            $payload['status'] = 'success';
        }

        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }

        echo function_exists('oprc_encode_json_response')
            ? oprc_encode_json_response($payload)
            : json_encode($payload);

        $zco_notifier->notify('NOTIFY_HEADER_END_OPRC_CHECKOUT_PROCESS');
        require(DIR_WS_INCLUDES . 'application_bottom.php');
        exit();
    }

    if (isset($oprcProcessResponse['messages'])) {
        $oprcProcessMessages = $oprcProcessResponse['messages'];
    }

    if (isset($oprcProcessResponse['confirmation']) && is_array($oprcProcessResponse['confirmation'])) {
        $oprcConfirmationDetails = $oprcProcessResponse['confirmation'];
    }

    if (isset($oprcProcessResponse['status']) && $oprcProcessResponse['status'] === 'requires_external') {
        $oprcConfirmationForm = isset($oprcProcessResponse['confirmation_form']) && is_array($oprcProcessResponse['confirmation_form'])
            ? $oprcProcessResponse['confirmation_form']
            : [];
        $zc_skip_page_template = false;
        $zc_show_page = true;
        $zco_notifier->notify('NOTIFY_HEADER_END_OPRC_CHECKOUT_PROCESS');
    } elseif (isset($oprcProcessResponse['status']) && $oprcProcessResponse['status'] === 'success' && isset($oprcProcessResponse['redirect_url'])) {
        $zco_notifier->notify('NOTIFY_HEADER_END_OPRC_CHECKOUT_PROCESS');
        zen_redirect($oprcProcessResponse['redirect_url']);
    } else {
        $zco_notifier->notify('NOTIFY_HEADER_END_OPRC_CHECKOUT_PROCESS');
        zen_redirect(zen_href_link(FILENAME_ONE_PAGE_CHECKOUT, '', 'SSL'));
    }
} catch (OprcAjaxCheckoutException $exception) {
    $messagesHtml = $exception->getMessagesHtml();
    if ($messagesHtml === null) {
        $append = $exception->getMessage() ? [$exception->getMessage()] : [];
        $messagesHtml = oprc_build_messages_html($messageStack, $append);
    }

    if ($isAjaxRequest) {
        $payload = array_merge([
            'status' => 'error',
            'messages' => $messagesHtml,
        ], $exception->getPayload());
        if ($exception->getRedirectUrl()) {
            $payload['redirect_url'] = $exception->getRedirectUrl();
        }

        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }

        echo function_exists('oprc_encode_json_response')
            ? oprc_encode_json_response($payload)
            : json_encode($payload);

        $zco_notifier->notify('NOTIFY_HEADER_END_OPRC_CHECKOUT_PROCESS');
        require(DIR_WS_INCLUDES . 'application_bottom.php');
        exit();
    }

    if ($messagesHtml !== '') {
        $messageStack->add_session('header', strip_tags($messagesHtml), 'error');
    }

    $redirectUrl = $exception->getRedirectUrl();
    if (!$redirectUrl) {
        $redirectUrl = zen_href_link(FILENAME_ONE_PAGE_CHECKOUT, '', 'SSL');
    }

    $zco_notifier->notify('NOTIFY_HEADER_END_OPRC_CHECKOUT_PROCESS');
    zen_redirect($redirectUrl);
} catch (Throwable $throwable) {
    error_log('OPRC checkout_process exception: ' . $throwable->getMessage() . ' in ' . $throwable->getFile() . ':' . $throwable->getLine());
    oprc_debug_log_trace('throwable: ' . $throwable->getMessage());

    $errorMessage = trim($throwable->getMessage());
    if ($errorMessage === '') {
        $errorMessage = oprc_constant_or_default('ERROR_DEFAULT', 'An unexpected error occurred. Please try again.');
    }

    if (is_object($messageStack) && method_exists($messageStack, 'add_session')) {
        $messageStack->add_session('header', $errorMessage, 'error');
    }

    if ($isAjaxRequest) {
        $payload = [
            'status' => 'error',
            'messages' => oprc_build_messages_html($messageStack, [$errorMessage]),
            'redirect_url' => zen_href_link(FILENAME_ONE_PAGE_CHECKOUT, '', 'SSL'),
        ];

        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }

        echo function_exists('oprc_encode_json_response')
            ? oprc_encode_json_response($payload)
            : json_encode($payload);

        $zco_notifier->notify('NOTIFY_HEADER_END_OPRC_CHECKOUT_PROCESS');
        require(DIR_WS_INCLUDES . 'application_bottom.php');
        exit();
    }

    $zco_notifier->notify('NOTIFY_HEADER_END_OPRC_CHECKOUT_PROCESS');
    zen_redirect(zen_href_link(FILENAME_ONE_PAGE_CHECKOUT, '', 'SSL'));
}
