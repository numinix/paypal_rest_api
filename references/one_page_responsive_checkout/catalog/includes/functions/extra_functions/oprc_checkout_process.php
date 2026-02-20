<?php
/**
 * Shared OPRC checkout-process helpers.
 */



if (!isset($oprcCheckoutDebugTrace) || !is_array($oprcCheckoutDebugTrace)) {
    $oprcCheckoutDebugTrace = [];
}

if (!function_exists('oprc_debug_format_context')) {
    function oprc_debug_format_context(array $context)
    {
        $encoded = json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
        if ($encoded === false) {
            return '[unserializable context]';
        }
        $maxLength = 2000;
        if (strlen($encoded) > $maxLength) {
            $encoded = substr($encoded, 0, $maxLength) . '...';
        }
        return $encoded;
    }
}

if (!function_exists('oprc_debug_checkpoint')) {
    function oprc_debug_checkpoint($label, array $context = [])
    {
        global $oprcCheckoutDebugTrace;

        if (!isset($oprcCheckoutDebugTrace) || !is_array($oprcCheckoutDebugTrace)) {
            $oprcCheckoutDebugTrace = [];
        }

        $entry = [
            'label' => (string) $label,
            'time' => microtime(true),
        ];
        if (!empty($context)) {
            $entry['context'] = $context;
        }
        $oprcCheckoutDebugTrace[] = $entry;

        // Only log if debug mode is enabled
        if (!defined('OPRC_DEBUG_MODE') || OPRC_DEBUG_MODE !== 'true') {
            return;
        }

        $message = 'OPRC checkout_process debug: ' . $entry['label'];
        if (!empty($context)) {
            $message .= ' | ' . oprc_debug_format_context($context);
        }
        error_log($message);
    }
}

if (!function_exists('oprc_debug_log_trace')) {
    function oprc_debug_log_trace($reason = '')
    {
        global $oprcCheckoutDebugTrace;

        if (empty($oprcCheckoutDebugTrace) || !is_array($oprcCheckoutDebugTrace)) {
            return;
        }

        // Only log if debug mode is enabled
        if (!defined('OPRC_DEBUG_MODE') || OPRC_DEBUG_MODE !== 'true') {
            return;
        }

        $prefix = 'OPRC checkout_process debug trace';
        if ($reason !== '') {
            $prefix .= ' (' . $reason . ')';
        }

        foreach ($oprcCheckoutDebugTrace as $index => $entry) {
            $label = isset($entry['label']) ? $entry['label'] : ('#' . ($index + 1));
            $message = $prefix . ' #' . ($index + 1) . ': ' . $label;
            if (isset($entry['context']) && is_array($entry['context']) && !empty($entry['context'])) {
                $message .= ' | ' . oprc_debug_format_context($entry['context']);
            }
            error_log($message);
        }
    }
}

if (!class_exists('OprcAjaxCheckoutException')) {
    class OprcAjaxCheckoutException extends Exception
    {
        protected $redirectUrl;
        protected $messagesHtml;
        protected $payload;

        public function __construct($message = '', $redirectUrl = null, $messagesHtml = null, array $payload = [], $code = 0, ?Throwable $previous = null)
        {
            parent::__construct($message, $code, $previous);
            $this->redirectUrl = $redirectUrl;
            $this->messagesHtml = $messagesHtml;
            $this->payload = $payload;
        }

        public function getRedirectUrl()
        {
            return $this->redirectUrl;
        }

        public function getMessagesHtml()
        {
            return $this->messagesHtml;
        }

        public function getPayload()
        {
            return $this->payload;
        }
    }
}

if (!function_exists('oprc_constant_or_default')) {
    function oprc_constant_or_default($name, $default)
    {
        return defined($name) ? constant($name) : $default;
    }
}

if (!function_exists('oprc_is_ajax_request')) {
    function oprc_is_ajax_request(?array $request = null, ?array $server = null)
    {
        if ($request === null) {
            $request = isset($_REQUEST) ? $_REQUEST : [];
        }
        if ($server === null) {
            $server = isset($_SERVER) ? $_SERVER : [];
        }

        if (isset($request['request']) && strtolower((string) $request['request']) === 'ajax') {
            return true;
        }

        if (isset($server['HTTP_X_REQUESTED_WITH']) && strtolower((string) $server['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            return true;
        }

        return false;
    }
}

if (!function_exists('oprc_build_messages_html')) {
    function oprc_build_messages_html($messageStack, array $additionalMessages = [])
    {
        $html = '';
        if (is_object($messageStack) && isset($messageStack->messages) && is_array($messageStack->messages)) {
            foreach ($messageStack->messages as $message) {
                $params = !empty($message['params']) ? $message['params'] : 'class="messageStackError larger"';
                $html .= '<div ' . $params . '>' . $message['text'] . '</div>';
            }
        }
        foreach ($additionalMessages as $text) {
            if (trim($text) === '') {
                continue;
            }
            $html .= '<div class="messageStackError larger">' . htmlspecialchars($text, ENT_COMPAT, defined('CHARSET') ? CHARSET : 'UTF-8') . '</div>';
        }
        return $html;
    }
}

if (!function_exists('oprc_extract_form_fields_from_html')) {
    function oprc_extract_form_fields_from_html($html)
    {
        $html = (string) $html;
        if (trim($html) === '') {
            return [];
        }

        if (!class_exists('DOMDocument')) {
            error_log('OPRC checkout_process: DOMDocument extension unavailable while parsing process_button markup.');
            return [];
        }

        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $fragment = '<div>' . $html . '</div>';
        $options = 0;
        if (defined('LIBXML_HTML_NOIMPLIED')) {
            $options |= LIBXML_HTML_NOIMPLIED;
        }
        if (defined('LIBXML_HTML_NODEFDTD')) {
            $options |= LIBXML_HTML_NODEFDTD;
        }
        if ($options) {
            $loaded = $document->loadHTML($fragment, $options);
        } else {
            $loaded = $document->loadHTML($fragment);
        }
        $errors = libxml_get_errors();
        libxml_clear_errors();
        if ($previous !== null) {
            libxml_use_internal_errors($previous);
        }
        if (!$loaded) {
            $message = 'OPRC checkout_process: Failed to parse process_button markup.';
            if (!empty($errors)) {
                $firstError = reset($errors);
                if (is_object($firstError) && isset($firstError->message)) {
                    $message .= ' ' . trim($firstError->message);
                    if (isset($firstError->line) && isset($firstError->column)) {
                        $message .= ' (line ' . $firstError->line . ', column ' . $firstError->column . ')';
                    }
                }
            }
            error_log($message);
            return [];
        }
        if (!empty($errors)) {
            $firstError = reset($errors);
            if (is_object($firstError) && isset($firstError->message)) {
                $warningMessage = 'OPRC checkout_process: Non-fatal process_button markup warning: ' . trim($firstError->message);
                if (isset($firstError->line) && isset($firstError->column)) {
                    $warningMessage .= ' (line ' . $firstError->line . ', column ' . $firstError->column . ')';
                }
                error_log($warningMessage);
            }
        }

        $queryParts = [];
        $firstFormAction = '';
        $firstFormMethod = '';
        $forms = $document->getElementsByTagName('form');
        if ($forms->length > 0) {
            /** @var DOMElement $firstForm */
            $firstForm = $forms->item(0);
            $firstFormAction = trim((string)$firstForm->getAttribute('action'));
            $firstFormMethod = strtolower(trim((string)$firstForm->getAttribute('method')));
            if ($firstFormMethod !== 'get' && $firstFormMethod !== 'post') {
                $firstFormMethod = '';
            }
        }

        $inputs = $document->getElementsByTagName('input');
        foreach ($inputs as $input) {
            /** @var DOMElement $input */
            $name = $input->getAttribute('name');
            if ($name === '') {
                continue;
            }
            $type = strtolower($input->getAttribute('type'));
            if ($type === 'submit' || $type === 'button' || $type === 'image') {
                continue;
            }
            if ($type !== '' && $type !== 'hidden') {
                continue;
            }
            if ($input->hasAttribute('disabled')) {
                continue;
            }
            $value = $input->getAttribute('value');
            $queryParts[] = rawurlencode($name) . '=' . rawurlencode($value);
        }

        $textareas = $document->getElementsByTagName('textarea');
        foreach ($textareas as $textarea) {
            /** @var DOMElement $textarea */
            $name = $textarea->getAttribute('name');
            if ($name === '') {
                continue;
            }
            if ($textarea->hasAttribute('disabled')) {
                continue;
            }
            $value = $textarea->nodeValue;
            $queryParts[] = rawurlencode($name) . '=' . rawurlencode($value);
        }

        $selects = $document->getElementsByTagName('select');
        foreach ($selects as $select) {
            /** @var DOMElement $select */
            $name = $select->getAttribute('name');
            if ($name === '') {
                continue;
            }
            if ($select->hasAttribute('disabled')) {
                continue;
            }

            $isMultiple = $select->hasAttribute('multiple');
            $selectedOptions = [];

            foreach ($select->getElementsByTagName('option') as $option) {
                /** @var DOMElement $option */
                if ($option->hasAttribute('disabled')) {
                    continue;
                }
                $value = $option->getAttribute('value');
                if ($value === '' && !$option->hasAttribute('value')) {
                    $value = $option->nodeValue;
                }

                $isSelected = $option->hasAttribute('selected');
                if (!$isSelected && !$isMultiple) {
                    continue;
                }
                if ($isSelected) {
                    $selectedOptions[] = $value;
                }
            }

            if ($isMultiple) {
                foreach ($selectedOptions as $selectedValue) {
                    $queryParts[] = rawurlencode($name) . '[]=' . rawurlencode($selectedValue);
                }
            } elseif (!empty($selectedOptions)) {
                $queryParts[] = rawurlencode($name) . '=' . rawurlencode($selectedOptions[0]);
            }
        }

        parse_str(implode('&', $queryParts), $target);

        if ($firstFormAction !== '') {
            $target['form_action_url'] = $firstFormAction;
        }
        if ($firstFormMethod !== '') {
            $target['form_method'] = $firstFormMethod;
        }

        return $target;
    }
}

if (!function_exists('oprc_normalize_process_button_array')) {
    function oprc_normalize_process_button_array($data, $path = 'process_button_ajax')
    {
        $normalized = [];
        if (!is_array($data)) {
            return $normalized;
        }

        foreach ($data as $key => $value) {
            if (is_int($key)) {
                error_log('OPRC checkout_process: Converting unsupported process_button payload key type at ' . $path . ' from ' . gettype($key) . ' to string.');
                $key = (string)$key;
            }

            $segmentPath = $path . '[' . $key . ']';
            if (is_array($value)) {
                $normalized[$key] = oprc_normalize_process_button_array($value, $segmentPath);
                continue;
            }

            $type = gettype($value);
            if ($value === null) {
                $normalized[$key] = '';
            } elseif ($type === 'boolean') {
                $normalized[$key] = $value ? '1' : '0';
            } elseif ($type === 'integer' || $type === 'double') {
                $normalized[$key] = (string)$value;
            } elseif ($type === 'string') {
                $normalized[$key] = $value;
            } elseif ($type === 'object' && method_exists($value, '__toString')) {
                $normalized[$key] = (string)$value;
            } else {
                error_log('OPRC checkout_process: Ignoring unsupported process_button payload value at ' . $segmentPath . ' of type ' . $type . '.');
            }
        }

        return $normalized;
    }
}

if (!function_exists('oprc_merge_form_fields_into_request')) {
    function oprc_merge_form_fields_into_request(array $fields)
    {
        foreach ($fields as $name => $value) {
            if (is_array($value)) {
                if (isset($_POST[$name]) && is_array($_POST[$name])) {
                    $_POST[$name] = oprc_merge_recursive($_POST[$name], $value);
                } else {
                    $_POST[$name] = $value;
                }

                if (isset($_REQUEST[$name]) && is_array($_REQUEST[$name])) {
                    $_REQUEST[$name] = oprc_merge_recursive($_REQUEST[$name], $value);
                } else {
                    $_REQUEST[$name] = $value;
                }
            } else {
                $_POST[$name] = $value;
                $_REQUEST[$name] = $value;
            }
        }
    }
}

if (!function_exists('oprc_merge_recursive')) {
    function oprc_merge_recursive(array $target, array $value)
    {
        foreach ($value as $key => $item) {
            if (is_array($item) && isset($target[$key]) && is_array($target[$key])) {
                $target[$key] = oprc_merge_recursive($target[$key], $item);
            } else {
                $target[$key] = $item;
            }
        }

        return $target;
    }
}

if (!function_exists('oprc_is_checkout_process_action')) {
    function oprc_is_checkout_process_action($url)
    {
        $url = trim((string)$url);
        if ($url === '') {
            return true;
        }

        $normalized = strtolower($url);
        if (strpos($normalized, 'checkout_process') !== false) {
            return true;
        }
        if (strpos($normalized, 'main_page=checkout_process') !== false) {
            return true;
        }

        return false;
    }
}

if (!function_exists('oprc_checkout_process_build_post_data')) {
    function oprc_checkout_process_build_post_data(array $payload)
    {
        $postData = [];
        foreach (['post', 'post_data', 'fields'] as $key) {
            if (isset($payload[$key]) && is_array($payload[$key])) {
                $postData = oprc_merge_recursive($postData, $payload[$key]);
            }
        }

        if (empty($postData)) {
            $postData = $payload;
        } else {
            $topLevel = $payload;
            foreach (['post', 'post_data', 'fields'] as $unsetKey) {
                if (isset($topLevel[$unsetKey])) {
                    unset($topLevel[$unsetKey]);
                }
            }
            if (!empty($topLevel)) {
                $postData = oprc_merge_recursive($postData, $topLevel);
            }
        }

        return $postData;
    }
}

if (!function_exists('oprc_checkout_process_initialize_environment')) {
    function oprc_checkout_process_initialize_environment(array $options = [])
    {
        global $oprcAjaxPayload;

        $oprcAjaxPayload = [];

        if (isset($options['payload']) && is_array($options['payload'])) {
            $oprcAjaxPayload = $options['payload'];
            if (!empty($options['apply_payload'])) {
                $postData = oprc_checkout_process_build_post_data($oprcAjaxPayload);
                if (isset($oprcAjaxPayload['session']) && is_array($oprcAjaxPayload['session'])) {
                    foreach ($oprcAjaxPayload['session'] as $oprcSessionKey => $oprcSessionValue) {
                        $_SESSION[$oprcSessionKey] = $oprcSessionValue;
                    }
                    unset($postData['session']);
                }
                foreach ($postData as $oprcAjaxKey => $oprcAjaxValue) {
                    $_POST[$oprcAjaxKey] = $oprcAjaxValue;
                    $_REQUEST[$oprcAjaxKey] = $oprcAjaxValue;
                }
                if (isset($postData['payment']) && $postData['payment'] !== '') {
                    $_SESSION['payment'] = $postData['payment'];
                }
            }
            return;
        }

        $method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper((string)$_SERVER['REQUEST_METHOD']) : '';
        if ($method !== 'POST') {
            return;
        }

        $contentType = '';
        if (!empty($_SERVER['CONTENT_TYPE'])) {
            $contentType = (string)$_SERVER['CONTENT_TYPE'];
        } elseif (!empty($_SERVER['HTTP_CONTENT_TYPE'])) {
            $contentType = (string)$_SERVER['HTTP_CONTENT_TYPE'];
        }

        if ($contentType === '' || stripos($contentType, 'application/json') === false) {
            return;
        }

        $rawInput = file_get_contents('php://input');
        if ($rawInput === false || trim($rawInput) === '') {
            return;
        }

        $decoded = json_decode($rawInput, true);
        if (!is_array($decoded)) {
            return;
        }

        $oprcAjaxPayload = $decoded;
        $postData = oprc_checkout_process_build_post_data($decoded);

        if (isset($oprcAjaxPayload['session']) && is_array($oprcAjaxPayload['session'])) {
            foreach ($oprcAjaxPayload['session'] as $oprcSessionKey => $oprcSessionValue) {
                $_SESSION[$oprcSessionKey] = $oprcSessionValue;
            }
            unset($postData['session']);
        }

        if (isset($oprcAjaxPayload['module']) && is_string($oprcAjaxPayload['module'])) {
            $postData['module'] = $oprcAjaxPayload['module'];
            if (!isset($postData['payment']) || $postData['payment'] === '') {
                $postData['payment'] = $oprcAjaxPayload['module'];
            }
        }

        foreach ($postData as $oprcAjaxKey => $oprcAjaxValue) {
            $_POST[$oprcAjaxKey] = $oprcAjaxValue;
            $_REQUEST[$oprcAjaxKey] = $oprcAjaxValue;
        }

        if (isset($postData['payment']) && $postData['payment'] !== '') {
            $_SESSION['payment'] = $postData['payment'];
        }
    }
}

if (!function_exists('oprc_checkout_process')) {
    function oprc_checkout_process(array $options = [])
    {
        global $messageStack, $credit_covers, $zco_notifier, $db, $oprcAjaxPayload, $oprcCheckoutDebugTrace, $template_dir, $currencies, $template, $order_total_modules, $order, $insert_id, $order_totals, $languageLoader, $installedPlugins, $current_page;

        $oprcCheckoutDebugTrace = [];
        oprc_checkout_process_initialize_environment($options);

        if (!isset($oprcAjaxPayload) || !is_array($oprcAjaxPayload)) {
            $oprcAjaxPayload = [];
        }

        $requestType = isset($options['request_type']) ? strtolower((string)$options['request_type']) : '';
        if ($requestType === '') {
            $requestType = (isset($_REQUEST['request']) && $_REQUEST['request'] === 'ajax') ? 'ajax' : 'page';
        }
        $requestType = ($requestType === 'ajax') ? 'ajax' : 'page';

        if ($requestType === 'ajax') {
            $_SESSION['request'] = 'ajax';
        } else {
            $_SESSION['request'] = 'page';
        }

        if (is_object($zco_notifier)) {
            $zco_notifier->notify('NOTIFY_CHECKOUT_PROCESS_BEGIN');
        }

        if (!isset($template) || !is_object($template)) {
            $templateClassPath = DIR_WS_CLASSES . 'template_func.php';
            if (!class_exists('template_func') && file_exists($templateClassPath)) {
                require_once $templateClassPath;
            }

            if (class_exists('template_func')) {
                $template = new template_func();
            }
        }

        $requireLanguagesPath = DIR_WS_MODULES . (function_exists('zen_get_module_directory') ? zen_get_module_directory('require_languages.php') : 'require_languages.php');
        if (!isset($languageLoader) || !is_object($languageLoader)) {
            $languageLoaderFactoryPath = DIR_FS_CATALOG . DIR_WS_CLASSES . 'ResourceLoaders/LanguageLoaderFactory.php';
            if (file_exists($languageLoaderFactoryPath) && !class_exists('\\Zencart\\LanguageLoader\\LanguageLoaderFactory')) {
                require_once $languageLoaderFactoryPath;
            }

            if (class_exists('Zencart\\LanguageLoader\\LanguageLoaderFactory')) {
                $languageLoaderFactory = new \Zencart\LanguageLoader\LanguageLoaderFactory();
                $installedPluginList = isset($installedPlugins) && is_array($installedPlugins) ? $installedPlugins : [];
                $currentPageName = isset($current_page) && $current_page !== '' ? (string)$current_page : 'index';
                $templateDirectory = isset($template_dir) ? (string)$template_dir : '';
                $languageLoader = $languageLoaderFactory->make('catalog', $installedPluginList, $currentPageName, $templateDirectory);
            }
        }

        if (file_exists($requireLanguagesPath)) {
            require_once($requireLanguagesPath);
        }

        $slamming_threshold = 10;
        if (!isset($_SESSION['payment_attempt'])) {
            $_SESSION['payment_attempt'] = 0;
        }
        $_SESSION['payment_attempt']++;
        if (is_object($zco_notifier)) {
            $zco_notifier->notify('NOTIFY_CHECKOUT_SLAMMING_ALERT', $_SESSION['payment_attempt'], $slamming_threshold);
        }

        if (!isset($credit_covers)) {
            $credit_covers = false;
        }

        if (is_object($zco_notifier)) {
            $zco_notifier->notify('NOTIFY_HEADER_START_CHECKOUT_CONFIRMATION');
        }

        if (!isset($_SESSION['cart']) || $_SESSION['cart']->count_contents() <= 0) {
            $message = oprc_constant_or_default('ERROR_CART_EMPTY', 'Your shopping cart is empty.');
            throw new OprcAjaxCheckoutException($message, zen_href_link(FILENAME_TIME_OUT), '<div class="messageStackError larger">' . htmlspecialchars($message, ENT_COMPAT, defined('CHARSET') ? CHARSET : 'UTF-8') . '</div>');
        }

        if (empty($_SESSION['customer_id'])) {
            if (isset($_SESSION['navigation'])) {
                $_SESSION['navigation']->set_snapshot(['mode' => 'SSL', 'page' => FILENAME_ONE_PAGE_CHECKOUT]);
            }
            $message = oprc_constant_or_default('ERROR_NOT_LOGGED_IN', 'Please log in to complete your order.');
            throw new OprcAjaxCheckoutException($message, zen_href_link(FILENAME_LOGIN, '', 'SSL'));
        }

        if (function_exists('zen_get_customer_validate_session') && zen_get_customer_validate_session($_SESSION['customer_id']) === false) {
            if (isset($_SESSION['navigation'])) {
                $_SESSION['navigation']->set_snapshot();
            }
            $message = oprc_constant_or_default('ERROR_INVALID_CUSTOMER_SESSION', 'Please log in again to continue.');
            throw new OprcAjaxCheckoutException($message, zen_href_link(FILENAME_LOGIN, '', 'SSL'));
        }

        if (isset($_SESSION['cart']->cartID) && !empty($_SESSION['cartID']) && $_SESSION['cart']->cartID !== $_SESSION['cartID']) {
            $messageStack->add_session('checkout_shipping', oprc_constant_or_default('ERROR_SESSION_SECURITY', 'Please review your order and try again.'), 'error');
            throw new OprcAjaxCheckoutException('', zen_href_link(FILENAME_ONE_PAGE_CHECKOUT, 'request=ajax', 'SSL'));
        }

        if (isset($_POST['payment']) && $_POST['payment'] !== '') {
            $_SESSION['payment'] = $_POST['payment'];
        }

        if (isset($_POST['comments'])) {
            $_SESSION['comments'] = zen_output_string_protected($_POST['comments']);
        }

        if (defined('DISPLAY_CONDITIONS_ON_CHECKOUT') && DISPLAY_CONDITIONS_ON_CHECKOUT == 'true') {
            if (!isset($_POST['conditions']) || $_POST['conditions'] !== '1') {
                $messageStack->add_session('conditions', oprc_constant_or_default('ERROR_CONDITIONS_NOT_ACCEPTED', 'You must accept the terms and conditions.'), 'error');
                throw new OprcAjaxCheckoutException('', zen_href_link(FILENAME_ONE_PAGE_CHECKOUT, '', 'SSL'));
            }
        }

        require_once(DIR_WS_CLASSES . 'order.php');
        $order = new order();

        oprc_debug_checkpoint('order initialized', [
            'content_type' => isset($order->content_type) ? $order->content_type : null,
            'order_total' => isset($order->info['total']) ? $order->info['total'] : null,
        ]);

        if (!isset($order->products) || !is_array($order->products) || sizeof($order->products) < 1) {
            throw new OprcAjaxCheckoutException('', zen_href_link(defined('FILENAME_SHOPPING_CART') ? FILENAME_SHOPPING_CART : 'shopping_cart'));
        }

        require_once(DIR_WS_CLASSES . 'shipping.php');
        $shipping_modules = new shipping();

        $oprc_process_dir_full = DIR_FS_CATALOG . DIR_WS_MODULES . 'one_page_checkout_process/';
        $oprc_process_dir = DIR_WS_MODULES . 'one_page_checkout_process/';
        if (is_dir($oprc_process_dir_full) && $dir = @dir($oprc_process_dir_full)) {
            while (($file = $dir->read()) !== false) {
                if (substr($file, -4) === '.php') {
                    include($oprc_process_dir . $file);
                }
            }
            $dir->close();
        }

        require_once(DIR_WS_CLASSES . 'order_total.php');
        $order_total_modules = new order_total();
        $order_total_modules->collect_posts();

        if (is_object($zco_notifier)) {
            $zco_notifier->notify('NOTIFY_CHECKOUT_PROCESS_BEFORE_ORDER_TOTALS_PRE_CONFIRMATION_CHECK');
        }

        if (method_exists($order_total_modules, 'pre_confirmation_check')) {
            $order_total_modules->pre_confirmation_check();
        }

        $paymentSelection = isset($_SESSION['payment']) ? $_SESSION['payment'] : (isset($_POST['payment']) ? $_POST['payment'] : null);
        if (!is_null($paymentSelection) && is_array($paymentSelection) && isset($paymentSelection['id'])) {
            $paymentSelection = $paymentSelection['id'];
        }

        require_once(DIR_WS_CLASSES . 'payment.php');
        $payment_modules = new payment($paymentSelection);

        if (is_object($payment_modules) && method_exists($payment_modules, 'update_status')) {
            $payment_modules->update_status();
        }

        $selectedModule = null;
        $selectedModuleClass = null;
        if (is_string($paymentSelection) && isset($GLOBALS[$paymentSelection]) && is_object($GLOBALS[$paymentSelection])) {
            $selectedModule = $GLOBALS[$paymentSelection];
            $selectedModuleClass = get_class($selectedModule);
        }

        if ($credit_covers === true) {
            if (isset($order->info['payment_method'])) {
                $order->info['payment_method'] = '';
            }
            if (isset($order->info['payment_module_code'])) {
                $order->info['payment_module_code'] = '';
            }
        } elseif ($selectedModule || (is_string($paymentSelection) && $paymentSelection !== '')) {
            $selectedModuleCode = null;
            if ($selectedModule && isset($selectedModule->code) && $selectedModule->code !== '') {
                $selectedModuleCode = (string)$selectedModule->code;
            } elseif (is_string($paymentSelection) && $paymentSelection !== '') {
                $selectedModuleCode = $paymentSelection;
            }

            $selectedModuleTitle = null;
            if ($selectedModule && isset($selectedModule->public_title) && $selectedModule->public_title !== '') {
                $selectedModuleTitle = (string)$selectedModule->public_title;
            } elseif ($selectedModule && isset($selectedModule->title) && $selectedModule->title !== '') {
                $selectedModuleTitle = (string)$selectedModule->title;
            }

            if ($selectedModuleCode !== null) {
                $order->info['payment_module_code'] = $selectedModuleCode;
            }

            $existingPaymentTitle = isset($order->info['payment_method']) ? trim($order->info['payment_method']) : '';
            $isPlaceholderTitle = ($existingPaymentTitle === '' || stripos($existingPaymentTitle, 'gift certificate') !== false);

            if ($selectedModuleTitle !== null) {
                if ($isPlaceholderTitle || $existingPaymentTitle !== $selectedModuleTitle) {
                    $order->info['payment_method'] = $selectedModuleTitle;
                }
            } elseif (is_string($paymentSelection) && $paymentSelection !== '' && $isPlaceholderTitle) {
                $order->info['payment_method'] = $paymentSelection;
            }
        }

        oprc_debug_checkpoint('payment modules initialized', [
            'payment_selection' => $paymentSelection,
            'selected_module' => $selectedModuleClass,
            'credit_covers' => (bool)$credit_covers,
        ]);

        if (is_array($payment_modules->modules)) {
            $payment_modules->pre_confirmation_check();
        }

        if ($messageStack->size('checkout_payment') > 0 || $messageStack->size('redemptions') > 0) {
            throw new OprcAjaxCheckoutException('', zen_href_link(FILENAME_ONE_PAGE_CHECKOUT, 'request=ajax', 'SSL'));
        }

        $flagAnyOutOfStock = false;
        $stock_check = [];
        if (defined('STOCK_CHECK') && STOCK_CHECK == 'true') {
            if (defined('NMX_PRODUCT_VARIANTS_STATUS') && NMX_PRODUCT_VARIANTS_STATUS == 'true') {
                for ($i = 0, $n = sizeof($order->products); $i < $n; $i++) {
                    $products_attributes = [];

                    if (isset($order->products[$i]['attributes']) && sizeof($order->products[$i]['attributes']) > 0) {
                        foreach ($order->products[$i]['attributes'] as $products_attribute) {
                            $products_attributes[$products_attribute['option_id']] = $products_attribute['value_id'];
                        }
                    }

                    $stockUpdate = zen_get_products_stock($order->products[$i]['id'], $products_attributes);
                    $stockAvailable = is_array($stockUpdate) ? $stockUpdate['quantity'] : $stockUpdate;
                    if ($stockAvailable - $order->products[$i]['qty'] < 0) {
                        $flagAnyOutOfStock = true;
                    }
                }
            }

            for ($i = 0, $n = sizeof($order->products); $i < $n; $i++) {
                $stock_check[$i] = zen_check_stock($order->products[$i]['id'], $order->products[$i]['qty']);
                if ($stock_check[$i]) {
                    $flagAnyOutOfStock = true;
                }
            }

            if (STOCK_ALLOW_CHECKOUT != 'true' && $flagAnyOutOfStock === true) {
                throw new OprcAjaxCheckoutException('', zen_href_link(FILENAME_SHOPPING_CART, 'request=ajax', 'NONSSL', false));
            }
        }

        if (isset($order->content_type) && $order->content_type == 'virtual') {
            $_SESSION['sendto'] = false;
        }

        $processButtonFields = [];
        $processButtonHtml = '';
        if (is_object($payment_modules) && method_exists($payment_modules, 'confirmation')) {
            $confirmation = $payment_modules->confirmation();
        } elseif ($selectedModule && method_exists($selectedModule, 'confirmation')) {
            $confirmation = $selectedModule->confirmation();
        }

        if (!isset($confirmation)) {
            $confirmation = [];
        }

        if ($selectedModule && method_exists($selectedModule, 'process_button_ajax')) {
            $ajaxFields = [];
            $ajaxPayload = $oprcAjaxPayload;
            $ajaxInvocationErrors = [];
            $ajaxInvocationSucceeded = false;

            try {
                $ajaxFields = $selectedModule->process_button_ajax($ajaxPayload);
                $ajaxInvocationSucceeded = true;
            } catch (Throwable $exception) {
                $ajaxInvocationErrors[] = $exception;
                try {
                    $ajaxFields = $selectedModule->process_button_ajax();
                    $ajaxInvocationSucceeded = true;
                    error_log('OPRC checkout_process: process_button_ajax() invocation with payload failed for ' . get_class($selectedModule) . ': ' . $exception->getMessage());
                } catch (Throwable $secondaryException) {
                    $ajaxInvocationErrors[] = $secondaryException;
                }
            }

            if (!$ajaxInvocationSucceeded) {
                foreach ($ajaxInvocationErrors as $failure) {
                    error_log('OPRC checkout_process: process_button_ajax() threw exception for ' . get_class($selectedModule) . ': ' . $failure->getMessage());
                }
                $ajaxFields = [];
            }

            if (is_array($ajaxFields)) {
                $processButtonFields = oprc_normalize_process_button_array($ajaxFields);
            } elseif ($ajaxFields !== null) {
                $type = gettype($ajaxFields);
                if ($type === 'object') {
                    $type .= '(' . get_class($ajaxFields) . ')';
                }
                error_log('OPRC checkout_process: Unexpected process_button_ajax() return type for ' . get_class($selectedModule) . ': ' . $type);
            }
        }

        if ($selectedModule && method_exists($selectedModule, 'process_button')) {
            $processButtonHtml = $selectedModule->process_button();
        } elseif (is_object($payment_modules) && method_exists($payment_modules, 'process_button')) {
            $processButtonHtml = $payment_modules->process_button();
        }
        if (!is_string($processButtonHtml)) {
            if (is_scalar($processButtonHtml) || (is_object($processButtonHtml) && method_exists($processButtonHtml, '__toString'))) {
                $processButtonHtml = (string)$processButtonHtml;
            } else {
                $type = gettype($processButtonHtml);
                if ($type === 'object') {
                    $type .= '(' . get_class($processButtonHtml) . ')';
                }
                error_log('OPRC checkout_process: Unexpected process_button() return type: ' . $type);
                $processButtonHtml = '';
            }
        }

        $extractFormActionUrl = function (&$fields) {
            $result = [
                'action' => null,
                'method' => null,
            ];

            if (!is_array($fields) || empty($fields)) {
                return $result;
            }

            foreach (['form_action_url', 'formActionUrl'] as $actionKey) {
                if (!array_key_exists($actionKey, $fields)) {
                    continue;
                }

                $candidate = $fields[$actionKey];
                unset($fields[$actionKey]);

                if (is_scalar($candidate) || (is_object($candidate) && method_exists($candidate, '__toString'))) {
                    $candidate = trim((string)$candidate);
                } else {
                    $candidate = '';
                }

                if ($candidate !== '') {
                    $result['action'] = $candidate;
                    break;
                }
            }

            foreach (['form_method', 'formMethod'] as $methodKey) {
                if (!array_key_exists($methodKey, $fields)) {
                    continue;
                }

                $candidate = $fields[$methodKey];
                unset($fields[$methodKey]);

                if (is_scalar($candidate) || (is_object($candidate) && method_exists($candidate, '__toString'))) {
                    $candidate = strtolower(trim((string)$candidate));
                } else {
                    $candidate = '';
                }

                if ($candidate === 'get' || $candidate === 'post') {
                    $result['method'] = $candidate;
                    break;
                }
            }

            return $result;
        };

        $fieldDefinedTargets = $extractFormActionUrl($processButtonFields);
        $fieldDefinedActionUrl = $fieldDefinedTargets['action'];
        $fieldDefinedMethod = $fieldDefinedTargets['method'];

        $formActionUrl = '';
        $formMethod = $fieldDefinedMethod;
        if ($selectedModule && isset($selectedModule->form_action_url)) {
            $formActionUrl = trim((string)$selectedModule->form_action_url);
        }
        if ($formActionUrl === '' && is_object($payment_modules) && isset($payment_modules->form_action_url)) {
            $formActionUrl = trim((string)$payment_modules->form_action_url);
        }
        if ($formActionUrl === '' && $fieldDefinedActionUrl !== null) {
            $formActionUrl = $fieldDefinedActionUrl;
        }

        $htmlProcessFields = oprc_extract_form_fields_from_html($processButtonHtml);
        if (!empty($htmlProcessFields)) {
            $htmlDefinedTargets = $extractFormActionUrl($htmlProcessFields);
            if ($formActionUrl === '' && $htmlDefinedTargets['action'] !== null) {
                $formActionUrl = $htmlDefinedTargets['action'];
            }
            if ($formMethod === null && $htmlDefinedTargets['method'] !== null) {
                $formMethod = $htmlDefinedTargets['method'];
            }
            if (empty($processButtonFields)) {
                $processButtonFields = $htmlProcessFields;
            } else {
                $processButtonFields = oprc_merge_recursive($htmlProcessFields, $processButtonFields);
            }
        }

        $processFields = $processButtonFields;
        if (!is_array($processFields)) {
            $processFields = [];
        }

        $ensureProcessField = function ($name, $value) use (&$processFields) {
            if ($name === '' || $name === null) {
                return;
            }
            if (array_key_exists($name, $processFields)) {
                return;
            }
            if (is_scalar($value) || (is_object($value) && method_exists($value, '__toString'))) {
                $processFields[$name] = (string)$value;
            } elseif ($value !== null) {
                $processFields[$name] = $value;
            }
        };

        if (!isset($_POST['ppr_type'])) {
            $selectedPaymentCode = null;
            if (isset($_POST['payment']) && is_string($_POST['payment']) && $_POST['payment'] !== '') {
                $selectedPaymentCode = $_POST['payment'];
            } elseif (isset($_SESSION['payment']) && is_string($_SESSION['payment']) && $_SESSION['payment'] !== '') {
                $selectedPaymentCode = $_SESSION['payment'];
            }

            if ($selectedPaymentCode === 'paypalr') {
                $defaultPprType = 'paypal';

                $cardIndicators = [
                    'paypalr_cc_number',
                    'paypalr_cc_cvv',
                    'paypalr_cc_sca_always',
                    'paypalr_collects_onsite',
                    'ppr_cc_number',
                    'ppr_cc_cvv',
                    'ppr_cc_sca_always',
                    'ppr_collects_onsite',
                ];

                foreach ($cardIndicators as $indicatorField) {
                    if (!array_key_exists($indicatorField, $_POST)) {
                        continue;
                    }

                    $indicatorValue = $_POST[$indicatorField];

                    if (is_array($indicatorValue)) {
                        $indicatorValue = implode('', array_map(function ($value) {
                            if (is_scalar($value) || (is_object($value) && method_exists($value, '__toString'))) {
                                return (string)$value;
                            }
                            return '';
                        }, $indicatorValue));
                    } elseif (is_object($indicatorValue) && method_exists($indicatorValue, '__toString')) {
                        $indicatorValue = (string)$indicatorValue;
                    }

                    if (is_scalar($indicatorValue) && trim((string)$indicatorValue) !== '') {
                        $defaultPprType = 'card';
                        break;
                    }
                }

                $_POST['ppr_type'] = $defaultPprType;
                $_REQUEST['ppr_type'] = $defaultPprType;
            }
        }

        if (isset($_POST['ppr_type'])) {
            $ensureProcessField('ppr_type', $_POST['ppr_type']);
        }

        if (isset($_POST['securityToken'])) {
            $ensureProcessField('securityToken', $_POST['securityToken']);
        }

        if (isset($_POST['payment'])) {
            $ensureProcessField('payment', $_POST['payment']);
        } elseif (is_string($paymentSelection) && $paymentSelection !== '') {
            $ensureProcessField('payment', $paymentSelection);
        }

        if (defined('OPRC_ONE_PAGE') && OPRC_ONE_PAGE === 'true') {
            $processFields = oprc_merge_recursive($processFields, [
                'onePageStatus' => 'on',
                'email_pref_html' => 'email_format',
            ]);
        }

        if (!isset($_POST['comments']) && isset($order->info['comments']) && $order->info['comments'] !== '') {
            $processFields = oprc_merge_recursive($processFields, [
                'comments' => $order->info['comments'],
            ]);
        }

        if (isset($confirmation['fields']) && is_array($confirmation['fields'])) {
            foreach ($confirmation['fields'] as $field) {
                if (!isset($field['field']) || !isset($field['title'])) {
                    continue;
                }
                if (preg_match("/name\s*=\s*(?:\"([^\"]*)\"|'([^']*)'|([^\s>]+))/i", $field['field'], $matches)) {
                    $fieldName = '';
                    for ($m = 1; $m < count($matches); $m++) {
                        if ($matches[$m] !== '' && $matches[$m] !== null) {
                            $fieldName = $matches[$m];
                            break;
                        }
                    }
                    if ($fieldName === '') {
                        continue;
                    }
                    if (!array_key_exists($fieldName, $processFields) && isset($_POST[$fieldName])) {
                        $processFields[$fieldName] = $_POST[$fieldName];
                    }
                }
            }
        }

        if (!empty($processFields)) {
            oprc_merge_form_fields_into_request($processFields);
        }

        if (isset($order->info['comments']) && $order->info['comments'] !== '') {
            $_POST['comments'] = $order->info['comments'];
            $_REQUEST['comments'] = $order->info['comments'];
        }

        if (is_object($zco_notifier)) {
            $zco_notifier->notify('NOTIFY_HEADER_END_CHECKOUT_CONFIRMATION');
        }

        $finalFormActionUrl = $formActionUrl;
        $finalFormMethod = $formMethod;

        if ($selectedModule && isset($selectedModule->form_action_url)) {
            $candidate = trim((string)$selectedModule->form_action_url);
            if ($candidate !== '') {
                $finalFormActionUrl = $candidate;
            }
        }

        if ($finalFormActionUrl === '' && is_object($payment_modules) && isset($payment_modules->form_action_url)) {
            $candidate = trim((string)$payment_modules->form_action_url);
            if ($candidate !== '') {
                $finalFormActionUrl = $candidate;
            }
        }

        if ($finalFormActionUrl === '' && $fieldDefinedActionUrl !== null) {
            $finalFormActionUrl = $fieldDefinedActionUrl;
        }

        if ($finalFormMethod === null && $fieldDefinedMethod !== null) {
            $finalFormMethod = $fieldDefinedMethod;
        }

        if ($finalFormActionUrl === '') {
            $finalFormActionUrl = zen_href_link(FILENAME_CHECKOUT_PROCESS, '', 'SSL', false);
        }

        $normalizedFormMethod = $finalFormMethod ?? 'post';

        $requiresExternal = !oprc_is_checkout_process_action($finalFormActionUrl);

        if ($requiresExternal) {
            if (isset($processFields['request'])) {
                unset($processFields['request']);
            }
            oprc_debug_checkpoint('requires external confirmation', [
                'form_action_url' => $finalFormActionUrl,
                'process_fields_count' => is_array($processFields) ? count($processFields) : 0,
                'selected_module' => $selectedModule ? get_class($selectedModule) : null,
            ]);
            return [
                'status' => 'requires_external',
                'messages' => oprc_build_messages_html($messageStack),
                'confirmation_form' => [
                    'action' => $finalFormActionUrl,
                    'method' => $normalizedFormMethod,
                    'fields' => $processFields,
                    'raw_html' => $processButtonHtml,
                    'auto_submit' => defined('OPRC_CONFIRMATION_AUTOSUBMIT') ? (bool)OPRC_CONFIRMATION_AUTOSUBMIT : true,
                ],
                'confirmation' => $confirmation,
            ];
        }

        if (is_object($zco_notifier)) {
            $zco_notifier->notify('NOTIFY_CHECKOUT_PROCESS_BEFORE_ORDER_TOTALS_PROCESS');
        }

        $order_totals = [];
        if (method_exists($order_total_modules, 'process')) {
            $order_totals = $order_total_modules->process();
            if (!is_array($order_totals)) {
                $order_totals = [];
            }
        }

        if (is_object($zco_notifier)) {
            $zco_notifier->notify('NOTIFY_CHECKOUT_PROCESS_AFTER_ORDER_TOTALS_PROCESS');
        }

        if (!isset($_SESSION['payment']) && $credit_covers === false) {
            throw new OprcAjaxCheckoutException('', zen_href_link(FILENAME_DEFAULT));
        }

        $payment_modules->before_process();

        if (is_object($zco_notifier)) {
            $zco_notifier->notify('NOTIFY_CHECKOUT_PROCESS_AFTER_PAYMENT_MODULES_BEFOREPROCESS');
        }

        if (method_exists($order, 'create')) {
            $insert_id = $order->create($order_totals, 2);
        } else {
            $insert_id = isset($_SESSION['order_number_created']) ? (int)$_SESSION['order_number_created'] : 99;
        }

        if (is_object($zco_notifier)) {
            $zco_notifier->notify('NOTIFY_CHECKOUT_PROCESS_AFTER_ORDER_CREATE');
        }

        if (method_exists($payment_modules, 'after_order_create')) {
            $payment_modules->after_order_create($insert_id);
        }

        if (is_object($zco_notifier)) {
            $zco_notifier->notify('NOTIFY_CHECKOUT_PROCESS_AFTER_PAYMENT_MODULES_AFTER_ORDER_CREATE');
        }

        if (method_exists($order, 'create_add_products')) {
            $order->create_add_products($insert_id);
        }
        $_SESSION['order_number_created'] = $insert_id;

        if (is_object($zco_notifier)) {
            $zco_notifier->notify('NOTIFY_CHECKOUT_PROCESS_AFTER_ORDER_CREATE_ADD_PRODUCTS');
        }

        if (method_exists($order, 'send_order_email')) {
            $order->send_order_email($insert_id, 2);
        }

        if (is_object($zco_notifier)) {
            $zco_notifier->notify('NOTIFY_CHECKOUT_PROCESS_AFTER_SEND_ORDER_EMAIL');
            $zco_notifier->notify('NOTIFY_GENERATE_PDF_AFTER_SEND_ORDER_EMAIL');
        }

        if (isset($_SESSION['payment_attempt'])) {
            unset($_SESSION['payment_attempt']);
        }

        $oshipping = $otax = $ototal = $order_subtotal = $credits_applied = 0;
        for ($i = 0, $n = sizeof($order_totals); $i < $n; $i++) {
            if (!isset($order_totals[$i]['code'], $order_totals[$i]['value'])) {
                continue;
            }
            switch ($order_totals[$i]['code']) {
                case 'ot_subtotal':
                    $order_subtotal = $order_totals[$i]['value'];
                    break;
                case 'ot_total':
                    $ototal = $order_totals[$i]['value'];
                    break;
                case 'ot_tax':
                    $otax = $order_totals[$i]['value'];
                    break;
                case 'ot_shipping':
                    $oshipping = $order_totals[$i]['value'];
                    break;
            }
            if (isset(${$order_totals[$i]['code']}) && is_object(${$order_totals[$i]['code']}) && !empty(${$order_totals[$i]['code']}->credit_class)) {
                $credits_applied += $order_totals[$i]['value'];
            }
        }
        $commissionable_order = ($order_subtotal - $credits_applied);
        $commissionable_order_formatted = is_object($currencies) ? $currencies->format($commissionable_order) : (string)$commissionable_order;

        if (!isset($_SESSION['order_summary']) || !is_array($_SESSION['order_summary'])) {
            $_SESSION['order_summary'] = [];
        }

        $_SESSION['order_summary']['order_number'] = $insert_id;
        $_SESSION['order_summary']['order_subtotal'] = $order_subtotal;
        $_SESSION['order_summary']['credits_applied'] = $credits_applied;
        $_SESSION['order_summary']['order_total'] = $ototal;
        $_SESSION['order_summary']['commissionable_order'] = $commissionable_order;
        $_SESSION['order_summary']['commissionable_order_formatted'] = $commissionable_order_formatted;
        $_SESSION['order_summary']['coupon_code'] = isset($order->info['coupon_code']) ? urlencode($order->info['coupon_code']) : '';
        $_SESSION['order_summary']['currency_code'] = isset($order->info['currency']) ? $order->info['currency'] : '';
        $_SESSION['order_summary']['currency_value'] = isset($order->info['currency_value']) ? $order->info['currency_value'] : '';
        $_SESSION['order_summary']['payment_module_code'] = isset($order->info['payment_module_code']) ? $order->info['payment_module_code'] : '';
        $_SESSION['order_summary']['payment_method'] = isset($order->info['payment_method']) ? $order->info['payment_method'] : '';
        $_SESSION['order_summary']['shipping_method'] = isset($order->info['shipping_method']) ? $order->info['shipping_method'] : '';
        $_SESSION['order_summary']['order_status'] = isset($order->info['order_status']) ? $order->info['order_status'] : '';
        $_SESSION['order_summary']['orders_status'] = isset($order->info['order_status']) ? $order->info['order_status'] : '';
        $_SESSION['order_summary']['tax'] = $otax;
        $_SESSION['order_summary']['shipping'] = $oshipping;

        $products_array = [];
        if (isset($order->products) && is_array($order->products)) {
            foreach ($order->products as $key => $val) {
                if (!isset($val['id'], $val['model'])) {
                    continue;
                }
                $products_array[urlencode($val['id'])] = urlencode($val['model']);
            }
        }
        $_SESSION['order_summary']['products_ordered_ids'] = implode('|', array_keys($products_array));
        $_SESSION['order_summary']['products_ordered_models'] = implode('|', array_values($products_array));

        if (is_object($zco_notifier)) {
            $zco_notifier->notify('NOTIFY_CHECKOUT_PROCESS_HANDLE_AFFILIATES');
        }

        oprc_debug_checkpoint('after checkout_process pipeline', [
            'insert_id' => isset($insert_id) ? (int)$insert_id : null,
            'session_order_number' => isset($_SESSION['order_number_created']) ? (int)$_SESSION['order_number_created'] : null,
        ]);

        $payment_modules->after_process();

        $zco_notifier->notify('NOTIFY_CHECKOUT_PROCESS_BEFORE_CART_RESET', $insert_id);
        $_SESSION['cart']->reset(true);

        unset($_SESSION['sendto']);
        unset($_SESSION['billto']);
        unset($_SESSION['shipping']);
        unset($_SESSION['payment']);
        unset($_SESSION['comments']);
        $order_total_modules->clear_posts();

        $zco_notifier->notify('NOTIFY_HEADER_END_CHECKOUT_PROCESS', $insert_id);

        $redirect = zen_href_link(FILENAME_CHECKOUT_SUCCESS, (isset($_GET['action']) && $_GET['action'] == 'confirm' ? 'action=confirm' : ''), 'SSL');

        oprc_debug_checkpoint('checkout_process success', [
            'order_id' => (int)$insert_id,
            'redirect_url' => $redirect,
        ]);

        return [
            'status' => 'success',
            'order_id' => (int)$insert_id,
            'redirect_url' => $redirect,
            'messages' => oprc_build_messages_html($messageStack),
            'confirmation' => $confirmation,
        ];
    }
}
