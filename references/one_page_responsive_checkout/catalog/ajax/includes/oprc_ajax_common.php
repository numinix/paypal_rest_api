<?php
$oprcFunctionsPath = __DIR__ . '/../../includes/functions/extra_functions/oprc_functions.php';
if (file_exists($oprcFunctionsPath)) {
    require_once $oprcFunctionsPath;
}
if (!function_exists('oprc_login_check_response')) {
    function oprc_login_check_response(array $session)
    {
        return (!empty($session['customer_id'])) ? '1' : '0';
    }
}

if (!function_exists('oprc_keepalive_response')) {
    /**
     * Handle keepalive request to prevent session timeout.
     * Simply accessing the session via application_top.php keeps it alive.
     *
     * @param array $session The current session data
     * @return array Response indicating success and session status
     */
    function oprc_keepalive_response(array $session)
    {
        return [
            'success' => true,
            'logged_in' => !empty($session['customer_id']),
        ];
    }
}

if (!function_exists('oprc_account_check_response')) {
    function oprc_account_check_response(array $postData, $db, $noAccountAlways = null)
    {
        $checkoutType = $postData['checkoutType'] ?? '';
        $emailAddress = $postData['hide_email_address_register'] ?? '';

        if ($noAccountAlways === null) {
            $noAccountAlways = defined('OPRC_NOACCOUNT_ALWAYS') ? OPRC_NOACCOUNT_ALWAYS : 'false';
        }

        $tableCustomers = defined('TABLE_CUSTOMERS') ? TABLE_CUSTOMERS : 'customers';

        $hasMatchingAccount = function () use ($db, $tableCustomers, $emailAddress) {
            if (!is_object($db) || !method_exists($db, 'Execute')) {
                return false;
            }

            $query = "SELECT * FROM " . $tableCustomers . " WHERE customers_email_address = '" . $emailAddress . "' AND COWOA_account != 1 LIMIT 1;";
            $result = $db->Execute($query);
            return is_object($result) && method_exists($result, 'RecordCount') && $result->RecordCount() > 0;
        };

        switch ($checkoutType) {
            case 'account':
                return $hasMatchingAccount() ? '1' : '0';

            case 'guest':
                if ($noAccountAlways === 'true') {
                    return '0';
                }

                return $hasMatchingAccount() ? '1' : '0';

            default:
                return '0';
        }
    }
}

if (!function_exists('oprc_validate_checkout_state')) {
    function oprc_validate_checkout_state(array $session, $cart, ?callable $validateSession = null, ?callable $loginUrlResolver = null, ?callable $cartUrlResolver = null)
    {
        $customerId = $session['customer_id'] ?? null;
        $isLoggedIn = !empty($customerId);

        if (!$isLoggedIn) {
            return [
                'redirect_url' => $loginUrlResolver ? $loginUrlResolver() : null,
            ];
        }

        $hasCart = is_object($cart) && method_exists($cart, 'count_contents') && $cart->count_contents() > 0;
        if (!$hasCart) {
            return [
                'redirect_url' => $cartUrlResolver ? $cartUrlResolver() : null,
            ];
        }

        if ($validateSession !== null && $validateSession($customerId) === false) {
            return [
                'redirect_url' => $loginUrlResolver ? $loginUrlResolver() : null,
            ];
        }

        return null;
    }
}

if (!function_exists('oprc_determine_shipping_selection')) {
    function oprc_determine_shipping_selection(array $postData)
    {
        if (array_key_exists('shipping', $postData) && $postData['shipping'] !== '') {
            return $postData['shipping'];
        }

        if (array_key_exists('shipping_method', $postData) && $postData['shipping_method'] !== '') {
            return $postData['shipping_method'];
        }

        return null;
    }
}

if (!function_exists('oprc_should_refresh_payment_container')) {
    function oprc_should_refresh_payment_container()
    {
        if (!defined('OPRC_REFRESH_PAYMENT')) {
            return true;
        }

        return OPRC_REFRESH_PAYMENT === 'true';
    }
}

if (!function_exists('oprc_has_shipping_address_changed')) {
    function oprc_has_shipping_address_changed($addressType, $previousSendTo, $currentSendTo)
    {
        if ($addressType !== 'billto') {
            return true;
        }

        return $previousSendTo !== $currentSendTo;
    }
}

if (!function_exists('oprc_capture_template_output')) {
    function oprc_capture_template_output($templateFile)
    {
        global $template, $db, $messageStack, $currencies, $currency;
        global $order, $order_total_modules, $payment_modules, $shipping_modules, $credit_covers;
        global $cart, $navigation, $zcDate, $zcPassword;

        if (!isset($template) || !is_object($template)) {
            $templateGuardPath = DIR_WS_INCLUDES . 'init_includes/init_oprc_template_guard.php';
            if (file_exists($templateGuardPath)) {
                require_once $templateGuardPath;
            }

            if (!isset($template) || !is_object($template)) {
                if (!class_exists('template_func')) {
                    $templateClassPath = DIR_WS_CLASSES . 'template_func.php';
                    if (file_exists($templateClassPath)) {
                        require_once $templateClassPath;
                    }
                }

                if (class_exists('template_func')) {
                    $template = new template_func();
                } else {
                    return '';
                }
            }
        }

        $templatePath = $template->get_template_dir($templateFile, DIR_WS_TEMPLATE, 'one_page_checkout', 'templates/one_page_checkout') . '/' . $templateFile;
        ob_start();
        extract($GLOBALS, EXTR_SKIP);
        require($templatePath);
        return ob_get_clean();
    }
}

if (!function_exists('oprc_import_session_messages')) {
    function oprc_import_session_messages($messageStack)
    {
        if (!isset($_SESSION['messageToStack']) || !is_array($_SESSION['messageToStack']) || empty($_SESSION['messageToStack'])) {
            return;
        }

        if (!is_object($messageStack) || !method_exists($messageStack, 'add')) {
            unset($_SESSION['messageToStack']);
            return;
        }

        foreach ($_SESSION['messageToStack'] as $stack => $messages) {
            if (!is_array($messages)) {
                $messages = [$messages];
            }

            $stackName = is_string($stack) && trim($stack) !== '' ? trim($stack) : 'header';

            foreach ($messages as $messageDetails) {
                $messageText = '';
                $messageType = null;

                if (is_array($messageDetails)) {
                    if (isset($messageDetails['text']) && $messageDetails['text'] !== '') {
                        $messageText = (string) $messageDetails['text'];
                    } elseif (isset($messageDetails['message']) && $messageDetails['message'] !== '') {
                        $messageText = (string) $messageDetails['message'];
                    } elseif (isset($messageDetails[0]) && $messageDetails[0] !== '') {
                        $messageText = (string) $messageDetails[0];
                    }

                    if (isset($messageDetails['type']) && $messageDetails['type'] !== '') {
                        $messageType = (string) $messageDetails['type'];
                    } elseif (isset($messageDetails['params']) && $messageDetails['params'] !== '') {
                        $messageType = (string) $messageDetails['params'];
                    } elseif (isset($messageDetails[1]) && $messageDetails[1] !== '') {
                        $messageType = (string) $messageDetails[1];
                    }
                } else {
                    $messageText = (string) $messageDetails;
                }

                if ($messageText === '') {
                    continue;
                }

                $messageStack->add($stackName, $messageText, ($messageType !== null && $messageType !== '') ? $messageType : 'error');
            }
        }

        unset($_SESSION['messageToStack']);
    }
}

if (!function_exists('oprc_is_change_address_submission')) {
    function oprc_is_change_address_submission(array $postData)
    {
        $requestMethod = isset($_SERVER['REQUEST_METHOD']) ? strtoupper($_SERVER['REQUEST_METHOD']) : 'GET';
        if ($requestMethod !== 'POST') {
            return false;
        }

        if (empty($postData)) {
            return false;
        }

        $submissionIndicators = [
            'address',
            'address_new',
            'firstname',
            'lastname',
            'street_address',
            'zone_country_id',
            'country',
            'zone_id',
            'telephone',
            'gender',
            'company',
        ];

        foreach ($submissionIndicators as $field) {
            if (!array_key_exists($field, $postData)) {
                continue;
            }

            $value = $postData[$field];
            if (is_array($value)) {
                foreach ($value as $item) {
                    if ($item !== '' && $item !== null && $item !== false) {
                        return true;
                    }
                }
                continue;
            }

            if ($value !== null && $value !== '' && $value !== false) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('oprc_snapshot_message_stack_area')) {
    function oprc_snapshot_message_stack_area($messageStack, $area)
    {
        if (!is_object($messageStack)) {
            return [];
        }

        $messages = [];

        if (isset($messageStack->messages) && is_array($messageStack->messages)) {
            $messages = $messageStack->messages;
        } elseif (method_exists($messageStack, 'getMessages')) {
            $messages = $messageStack->getMessages();
        }

        if (!is_array($messages)) {
            return [];
        }

        $snapshot = [];

        foreach ($messages as $message) {
            if (!is_array($message)) {
                continue;
            }

            $messageArea = '';
            if (isset($message['class'])) {
                $messageArea = (string) $message['class'];
            } elseif (isset($message['stack'])) {
                $messageArea = (string) $message['stack'];
            } elseif (isset($message['area'])) {
                $messageArea = (string) $message['area'];
            }

            if ($messageArea !== (string) $area) {
                continue;
            }

            $messageText = '';
            if (isset($message['text'])) {
                $messageText = (string) $message['text'];
            } elseif (isset($message['message'])) {
                $messageText = (string) $message['message'];
            }

            $messageType = '';
            if (isset($message['type'])) {
                $messageType = (string) $message['type'];
            } elseif (isset($message['params'])) {
                $messageType = (string) $message['params'];
            }

            $snapshot[] = [
                'text' => $messageText,
                'type' => $messageType,
            ];
        }

        return $snapshot;
    }
}

if (!function_exists('oprc_message_stack_has_new_entries')) {
    function oprc_message_stack_has_new_entries(array $baselineMessages, array $currentMessages)
    {
        $baselineCounts = [];

        foreach ($baselineMessages as $message) {
            if (!is_array($message)) {
                continue;
            }

            $key = json_encode([
                'text' => $message['text'] ?? '',
                'type' => $message['type'] ?? '',
            ]);

            if (!isset($baselineCounts[$key])) {
                $baselineCounts[$key] = 0;
            }

            $baselineCounts[$key]++;
        }

        foreach ($currentMessages as $message) {
            if (!is_array($message)) {
                continue;
            }

            $key = json_encode([
                'text' => $message['text'] ?? '',
                'type' => $message['type'] ?? '',
            ]);

            if (isset($baselineCounts[$key]) && $baselineCounts[$key] > 0) {
                $baselineCounts[$key]--;
                continue;
            }

            if (($message['text'] ?? '') === '' && ($message['type'] ?? '') === '') {
                continue;
            }

            return true;
        }

        return false;
    }
}


if (!function_exists('oprc_capture_shipping_methods_html')) {
    function oprc_capture_shipping_methods_html()
    {
        $shouldReturnHtml = defined('OPRC_AJAX_SHIPPING_QUOTES') && OPRC_AJAX_SHIPPING_QUOTES === 'true';

        if (!isset($_SESSION['cart']) || !is_object($_SESSION['cart']) || $_SESSION['cart']->count_contents() <= 0) {
            return '';
        }

        global $template, $order, $shipping_modules, $messageStack, $currencies;
        global $quotes, $free_shipping, $shipping_weight, $total_weight, $total_count;
        global $recalculate_shipping_cost, $pass;

        $usedCachedUpdate = false;
        $shippingUpdate = $GLOBALS['oprc_last_shipping_update'] ?? null;

        if (is_array($shippingUpdate) && isset($shippingUpdate['order']) && is_object($shippingUpdate['order'])) {
            $order = $shippingUpdate['order'];

            if (isset($shippingUpdate['shipping_modules']) && is_object($shippingUpdate['shipping_modules'])) {
                $shipping_modules = $shippingUpdate['shipping_modules'];
            }

            if (isset($shippingUpdate['globals']) && is_array($shippingUpdate['globals'])) {
                foreach ($shippingUpdate['globals'] as $globalKey => $globalValue) {
                    $GLOBALS[$globalKey] = $globalValue;
                }
            }

            oprc_restore_module_dates($shippingUpdate);

            $usedCachedUpdate = true;
        }

        // Recompute quotes on AJAX refreshes to ensure $GLOBALS['quotes'] is populated
        // Skip if we have valid cached data that was already restored
        if (!$usedCachedUpdate) {
            if ($shippingUpdate !== null) {
                unset($GLOBALS['oprc_last_shipping_update']);
            }
            
            $originalGetAction = $_GET['oprcaction'] ?? null;
            $originalPostAction = $_POST['oprcaction'] ?? null;
            $originalRequestFlag = $_REQUEST['request'] ?? null;

            $ajax_request = true;
            $_GET['oprcaction'] = 'process';
            $_POST['oprcaction'] = 'process';
            $_REQUEST['request'] = 'ajax';

            if (file_exists(DIR_WS_MODULES . 'oprc_update_shipping.php')) {
                require(DIR_WS_MODULES . 'oprc_update_shipping.php');
            } elseif (isset($shipping_modules) && is_object($shipping_modules) && method_exists($shipping_modules, 'quote')) {
                // Fallback: compute quotes directly if the helper file isn't present
                $maybeQuotes = $shipping_modules->quote();
                if (is_array($maybeQuotes)) {
                    $GLOBALS['quotes'] = $maybeQuotes;
                }
            }

            // Restore original request flags
            if (isset($originalGetAction)) {
                if ($originalGetAction !== null) {
                    $_GET['oprcaction'] = $originalGetAction;
                } else {
                    unset($_GET['oprcaction']);
                }
            }

            if (isset($originalPostAction)) {
                if ($originalPostAction !== null) {
                    $_POST['oprcaction'] = $originalPostAction;
                } else {
                    unset($_POST['oprcaction']);
                }
            }

            if (isset($originalRequestFlag)) {
                if ($originalRequestFlag !== null) {
                    $_REQUEST['request'] = $originalRequestFlag;
                } else {
                    unset($_REQUEST['request']);
                }
            }
        }

        // Log quotes snapshot after recomputation/restoration for debugging
        if (function_exists('oprc_delivery_debug_log')) {
            oprc_delivery_debug_log('oprc_capture_shipping_methods_html: quotes snapshot', [
                'shouldReturnHtml' => (defined('OPRC_AJAX_SHIPPING_QUOTES') ? OPRC_AJAX_SHIPPING_QUOTES : '(undef)'),
                'usedCachedUpdate' => $usedCachedUpdate,
                'globalsQuotesCount' => (isset($GLOBALS['quotes']) && is_array($GLOBALS['quotes'])) ? count($GLOBALS['quotes']) : 0,
                'shippingModulesQuotesCount' => (isset($shipping_modules->quotes) && is_array($shipping_modules->quotes)) ? count($shipping_modules->quotes) : 0,
            ]);
        }

        // Always render the shipping template to trigger module ETA computation (module->date side-effects)
        // even if we won't return the HTML. This ensures $GLOBALS[$moduleCode]->date gets set on AJAX.
        $renderedHtml = '';
        
        if ($order->content_type == 'virtual' && (!isset($_SESSION['shipping']['id']) || $_SESSION['shipping']['id'] == 'free_free')) {
            // Skip render for virtual orders with free shipping
            if ($usedCachedUpdate) {
                unset($GLOBALS['oprc_last_shipping_update']);
            }
            
            // Log that we skipped the render for this special case
            if (function_exists('oprc_delivery_debug_log')) {
                oprc_delivery_debug_log('oprc_capture_shipping_methods_html: Skipped render for virtual order', [
                    'contentType' => $order->content_type ?? 'unknown',
                ]);
            }
            
            return $shouldReturnHtml ? '' : null;
        }

        // Always execute the render to trigger ETA side-effects
        $renderedHtml = oprc_capture_template_output('tpl_modules_oprc_shipping_quotes.php');

        // Verify that module dates were set during the render
        if (function_exists('oprc_delivery_debug_log')) {
            $nonEmptyDates = [];
            if (isset($shipping_modules->modules) && is_array($shipping_modules->modules)) {
                foreach ($shipping_modules->modules as $moduleFile) {
                    $moduleCode = preg_replace('/\.php$/', '', $moduleFile);
                    if (isset($GLOBALS[$moduleCode]) && is_object($GLOBALS[$moduleCode]) && !empty($GLOBALS[$moduleCode]->date)) {
                        $nonEmptyDates[] = $moduleCode;
                    }
                }
            }
            oprc_delivery_debug_log('oprc_capture_shipping_methods_html: post-render ETA check', [
                'executedRenderer' => true,
                'nonEmptyModuleDates' => $nonEmptyDates,
            ]);
        }

        if ($usedCachedUpdate) {
            unset($GLOBALS['oprc_last_shipping_update']);
        }

        // Return HTML only if configured to do so, otherwise return empty string/null
        return $shouldReturnHtml ? trim($renderedHtml) : null;
    }
}

if (!function_exists('oprc_build_checkout_refresh_payload')) {
    function oprc_build_checkout_refresh_payload(array $options = [])
    {
        global $template, $current_page_base, $messageStack;
        global $order, $order_total_modules, $payment_modules, $shipping_modules, $credit_covers;

        oprc_ensure_language_is_loaded('lang.one_page_checkout');

        require_once(DIR_WS_CLASSES . 'order.php');
        $order = new order();

        require_once(DIR_WS_CLASSES . 'shipping.php');
        $shipping_modules = new shipping();

        // Capture shipping update data before it gets cleared by oprc_capture_shipping_methods_html
        $shippingUpdate = $GLOBALS['oprc_last_shipping_update'] ?? null;
        $existingDeliveryUpdates = [];
        if (is_array($shippingUpdate)) {
            if (isset($shippingUpdate['delivery_updates']) && is_array($shippingUpdate['delivery_updates'])) {
                $existingDeliveryUpdates = array_merge($existingDeliveryUpdates, $shippingUpdate['delivery_updates']);
            }
            if (isset($shippingUpdate['module_dates']) && is_array($shippingUpdate['module_dates'])) {
                $existingDeliveryUpdates = array_merge($existingDeliveryUpdates, $shippingUpdate['module_dates']);
            }
        }

        $shippingMethodsHtml = oprc_capture_shipping_methods_html();

        // Always compute updates via the same helper used elsewhere
        $quotes = isset($GLOBALS['quotes']) && is_array($GLOBALS['quotes']) ? $GLOBALS['quotes'] : [];
        $payload = [];
        oprc_attach_delivery_updates($payload, $quotes, $shipping_modules, $existingDeliveryUpdates);

        $deliveryUpdates = isset($payload['deliveryUpdates']) && is_array($payload['deliveryUpdates'])
            ? $payload['deliveryUpdates']
            : [];

        // Safety net: if nothing came through (e.g., odd module state), reuse the last rendered updates once
        if (empty($deliveryUpdates) && is_array($shippingUpdate)
            && isset($shippingUpdate['rendered_delivery_updates']) && is_array($shippingUpdate['rendered_delivery_updates'])) {
            $deliveryUpdates = $shippingUpdate['rendered_delivery_updates'];
        }

        // Refresh order-related classes so totals reflect any updates made by
        // the shipping-quote recalculation.
        $order = new order();
        $shipping_modules = new shipping();

        require_once(DIR_WS_CLASSES . 'payment.php');
        $payment_modules = new payment();

        require_once(DIR_WS_CLASSES . 'order_total.php');
        $order_total_modules = new order_total();
        $order_total_modules->collect_posts();
        $order_total_modules->pre_confirmation_check();
        $order_total_modules->process();

        if (
            isset($GLOBALS['ot_coupon'])
            && is_object($GLOBALS['ot_coupon'])
            && isset($GLOBALS['ot_coupon']->output)
            && is_array($GLOBALS['ot_coupon']->output)
            && !empty($_SESSION['cc_id'])
        ) {
            foreach ($GLOBALS['ot_coupon']->output as $index => $details) {
                if (!is_array($details)) {
                    continue;
                }

                $text = $details['text'] ?? '';
                if (!is_string($text) || strpos($text, 'couponRemove') !== false) {
                    continue;
                }

                $GLOBALS['ot_coupon']->output[$index]['text'] = $text . '<br /><a href="#" class="couponRemove"><span class="smallText">remove</span></a>';
            }
        }

        $credit_covers = (isset($_SESSION['credit_covers']) && $_SESSION['credit_covers'] == true);
        if ($credit_covers) {
            unset($_SESSION['payment']);
        }

        $originalPageBase = $current_page_base;
        $current_page_base = 'one_page_checkout';

        if (!isset($messageStack) || !is_object($messageStack)) {
            if (!class_exists('messageStack')) {
                require_once(DIR_WS_CLASSES . 'message_stack.php');
            }
            $messageStack = new messageStack();
        }

        oprc_import_session_messages($messageStack);

        $step3Html = oprc_capture_template_output('tpl_modules_oprc_step_3.php');
        $orderTotalHtml = oprc_capture_template_output('tpl_modules_oprc_ordertotal.php');

        $current_page_base = $originalPageBase;

        $finalPayload = [
            'shippingMethodContainer' => oprc_extract_inner_html($step3Html, 'shippingMethodContainer'),
            'discountsContainer' => oprc_extract_inner_html($step3Html, 'discountsContainer'),
            'oprcAddresses' => oprc_extract_inner_html($step3Html, 'oprcAddresses'),
            'shopBagWrapper' => oprc_extract_inner_html($orderTotalHtml, 'shopBagWrapper'),
            'shippingMethodsHtml' => $shippingMethodsHtml,
            'deliveryUpdates' => $deliveryUpdates,
            'oprcAddressMissing' => oprc_is_address_missing() ? 'true' : 'false',
            'creditCovers' => $credit_covers ? 'true' : 'false',
            'step3Html' => $step3Html,
            'orderTotalHtml' => $orderTotalHtml,
            // (Optional but helpful during debugging)
            'moduleDeliveryDates' => isset($payload['moduleDeliveryDates']) ? $payload['moduleDeliveryDates'] : [],
            'methodDeliveryDates' => isset($payload['methodDeliveryDates']) ? $payload['methodDeliveryDates'] : [],
        ];

        if (oprc_should_refresh_payment_container()) {
            $finalPayload['paymentMethodContainer'] = oprc_extract_inner_html($step3Html, 'paymentMethodContainer');
            $finalPayload['paymentMethodContainerOuter'] = oprc_extract_outer_html($step3Html, 'paymentMethodContainer');
        }

        $messageAreas = $options['messageAreas'] ?? ['redemptions', 'checkout_payment', 'checkout_shipping', 'checkout_address', 'header'];
        $messagesHtml = '';
        if (isset($messageStack) && is_object($messageStack)) {
            foreach ($messageAreas as $area) {
                if ($messageStack->size($area) > 0) {
                    $messagesHtml .= $messageStack->output($area);
                }
            }
        }

        $finalPayload['messagesHtml'] = $messagesHtml;
        $finalPayload['status'] = (trim(strip_tags($messagesHtml)) !== '') ? 'warning' : 'success';

        return $finalPayload;
    }
}

if (!function_exists('oprc_ensure_language_is_loaded')) {
    function oprc_ensure_language_is_loaded($languagePage)
    {
        static $loadedPages = [];

        if (isset($loadedPages[$languagePage])) {
            return;
        }

        $language = isset($_SESSION['language']) && $_SESSION['language'] !== ''
            ? $_SESSION['language']
            : 'english';

        $languageDirectories = [
            DIR_WS_LANGUAGES . $language . '/',
        ];

        $templateLanguageDir = null;
        if (defined('DIR_WS_TEMPLATE') && DIR_WS_TEMPLATE !== '') {
            $templateName = basename(trim(DIR_WS_TEMPLATE, '/'));
            if ($templateName !== '') {
                $potentialTemplateDir = DIR_WS_LANGUAGES . $language . '/' . $templateName . '/';
                if (is_dir($potentialTemplateDir)) {
                    $templateLanguageDir = $potentialTemplateDir;
                    $languageDirectories[] = $templateLanguageDir;
                }
            }
        }

        foreach ($languageDirectories as $directory) {
            $languageFile = $directory . $languagePage . '.php';
            if (!file_exists($languageFile)) {
                continue;
            }

            $defines = include $languageFile;
            if (is_array($defines)) {
                nmx_create_defines($defines);
            }
        }

        $extraDefinitionDirectories = [
            DIR_WS_LANGUAGES . $language . '/extra_definitions/',
        ];

        if ($templateLanguageDir !== null) {
            $extraDefinitionDirectories[] = $templateLanguageDir . 'extra_definitions/';
        }

        foreach ($extraDefinitionDirectories as $extraDefinitionsDir) {
            if (!is_dir($extraDefinitionsDir)) {
                continue;
            }

            $extraFiles = glob($extraDefinitionsDir . $languagePage . '*.php');
            if ($extraFiles === false) {
                continue;
            }

            foreach ($extraFiles as $extraFile) {
                $defines = include $extraFile;
                if (is_array($defines)) {
                    nmx_create_defines($defines);
                }
            }
        }

        $loadedPages[$languagePage] = true;
    }
}

if (!function_exists('oprc_parse_html_by_id')) {
    function oprc_parse_html_by_id($html)
    {
        $trimmed = trim($html);
        if ($trimmed === '') {
            return ['inner' => [], 'outer' => []];
        }

        static $cachedElements = [];

        $hash = md5($trimmed);

        if (!isset($cachedElements[$hash])) {
            $cachedElements[$hash] = ['inner' => [], 'outer' => []];

            $useDomDocument = class_exists('DOMDocument');
            $loaded = false;

            if ($useDomDocument) {
                $document = new DOMDocument('1.0', 'UTF-8');
                $useErrors = function_exists('libxml_use_internal_errors') ? libxml_use_internal_errors(true) : null;

                $loaded = $document->loadHTML('<?xml encoding="UTF-8"?><div>' . $trimmed . '</div>');
                if ($loaded !== false) {
                    $xpath = new DOMXPath($document);
                    $nodes = $xpath->query('//*[@id]');
                    if ($nodes !== false) {
                        foreach ($nodes as $node) {
                            $nodeId = $node->getAttribute('id');
                            if ($nodeId === '') {
                                continue;
                            }

                            $innerHtml = '';
                            foreach ($node->childNodes as $child) {
                                $innerHtml .= $document->saveHTML($child);
                            }

                            $cachedElements[$hash]['inner'][$nodeId] = trim($innerHtml);
                            $cachedElements[$hash]['outer'][$nodeId] = trim($document->saveHTML($node));
                        }
                    }
                }

                if (function_exists('libxml_clear_errors')) {
                    libxml_clear_errors();
                }
                if (function_exists('libxml_use_internal_errors')) {
                    libxml_use_internal_errors($useErrors);
                }
            }

            if (!$useDomDocument || $loaded === false || (empty($cachedElements[$hash]['inner']) && empty($cachedElements[$hash]['outer']))) {
                $pattern = '/<[^>]*\bid\s*=\s*("|\')([^"\']+)\1[^>]*>(.*?)<\/[^>]+>/is';
                if (preg_match_all($pattern, $trimmed, $matches, PREG_SET_ORDER)) {
                    foreach ($matches as $match) {
                        $cachedElements[$hash]['inner'][$match[2]] = trim($match[3]);
                        $cachedElements[$hash]['outer'][$match[2]] = trim($match[0]);
                    }
                }
            }
        }

        return $cachedElements[$hash];
    }
}

if (!function_exists('oprc_extract_inner_html')) {
    function oprc_extract_inner_html($html, $elementId)
    {
        $parsed = oprc_parse_html_by_id($html);

        if (isset($parsed['inner'][$elementId])) {
            return $parsed['inner'][$elementId];
        }

        return '';
    }
}

if (!function_exists('oprc_extract_outer_html')) {
    function oprc_extract_outer_html($html, $elementId)
    {
        $parsed = oprc_parse_html_by_id($html);

        if (isset($parsed['outer'][$elementId])) {
            return $parsed['outer'][$elementId];
        }

        return '';
    }
}

if (!function_exists('oprc_is_address_missing')) {
    function oprc_is_address_missing()
    {
        if (!isset($_SESSION['customer_id']) || !$_SESSION['customer_id']) {
            return true;
        }

        if (!isset($_SESSION['customer_default_address_id']) || !$_SESSION['customer_default_address_id']) {
            return true;
        }

        if (function_exists('user_owns_address') && !user_owns_address($_SESSION['customer_default_address_id'])) {
            return true;
        }

        return false;
    }
}

if (!function_exists('oprc_encode_json_response')) {
    function oprc_encode_json_response(array $response)
    {
        $prepared = oprc_prepare_json_data($response);

        $encoded = json_encode($prepared, JSON_UNESCAPED_UNICODE);
        if ($encoded !== false) {
            return $encoded;
        }

        if (defined('JSON_PARTIAL_OUTPUT_ON_ERROR')) {
            $encoded = json_encode($prepared, JSON_PARTIAL_OUTPUT_ON_ERROR);
            if ($encoded !== false) {
                return $encoded;
            }
        }

        $fallback = [
            'status' => 'error',
            'messagesHtml' => 'Unable to encode response.',
        ];

        return json_encode($fallback);
    }
}

if (!function_exists('oprc_prepare_json_data')) {
    function oprc_prepare_json_data($value)
    {
        if (is_array($value)) {
            $result = [];
            foreach ($value as $key => $item) {
                $preparedKey = is_string($key) ? oprc_convert_string_to_utf8($key) : $key;
                $result[$preparedKey] = oprc_prepare_json_data($item);
            }
            return $result;
        }

        if (is_string($value)) {
            return oprc_convert_string_to_utf8($value);
        }

        return $value;
    }
}

if (!function_exists('oprc_convert_string_to_utf8')) {
    function oprc_convert_string_to_utf8($string)
    {
        if (oprc_is_utf8($string)) {
            return $string;
        }

        if (function_exists('mb_detect_encoding') && function_exists('mb_convert_encoding')) {
            $encoding = mb_detect_encoding($string, 'UTF-8, ISO-8859-1, WINDOWS-1252, ASCII', true);
            if ($encoding === false) {
                $encoding = 'UTF-8';
            }
            return mb_convert_encoding($string, 'UTF-8', $encoding);
        }

        if (function_exists('iconv')) {
            $converted = @iconv('ISO-8859-1', 'UTF-8//IGNORE', $string);
            if ($converted !== false) {
                return $converted;
            }
        }

        return utf8_encode($string);
    }
}

if (!function_exists('oprc_is_utf8')) {
    function oprc_is_utf8($string)
    {
        return (bool)preg_match('//u', $string);
    }
}

if (!function_exists('oprc_attach_delivery_updates')) {
    /**
     * Centralized helper to attach delivery updates to AJAX payload.
     * This ensures consistent delivery update handling across all AJAX endpoints.
     *
     * @param array $payload The response payload to attach delivery updates to
     * @param mixed $quotes The shipping quotes array
     * @param mixed $shipping_modules The shipping modules object
     * @param array $existingUpdates Optional existing delivery updates to use as fallback
     */
    function oprc_attach_delivery_updates(array &$payload, $quotes, $shipping_modules, array $existingUpdates = [])
    {
        $quotesList = is_array($quotes) ? $quotes : [];
        $deliveryData = oprc_prepare_delivery_updates_for_quotes($quotesList, $shipping_modules, $existingUpdates);
        
        // For UI - rendered HTML snippets keyed by shipping option ID
        $payload['deliveryUpdates'] = $deliveryData['rendered_updates'];
        
        // Optional diagnostics - can be used for debugging
        $payload['moduleDeliveryDates'] = $deliveryData['module_dates'];
        $payload['methodDeliveryDates'] = $deliveryData['method_dates'];
    }
}
