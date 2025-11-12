<?php
/**
 * paypalr_applepay.php payment module class for handling Apple Pay via PayPal Advanced Checkout.
 *
 * @copyright Copyright 2025 Zen Cart Development Team
 * @license   https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */
require_once DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/paypalr.php';

class paypalr_applepay extends paypalr
{
    /**
     * Variant configuration overrides used by the parent paypalr module when initialising.
     */
    protected string $variantStatusSetting = 'False';
    protected ?int $variantSortOrder = null;
    protected int $variantZoneSetting = 0;

    public function __construct()
    {
        $this->variantStatusSetting = defined('MODULE_PAYMENT_PAYPALR_APPLEPAY_STATUS') ? MODULE_PAYMENT_PAYPALR_APPLEPAY_STATUS : 'False';
        $this->variantSortOrder = defined('MODULE_PAYMENT_PAYPALR_APPLEPAY_SORT_ORDER') ? (int)MODULE_PAYMENT_PAYPALR_APPLEPAY_SORT_ORDER : null;
        $this->variantZoneSetting = defined('MODULE_PAYMENT_PAYPALR_APPLEPAY_ZONE') ? (int)MODULE_PAYMENT_PAYPALR_APPLEPAY_ZONE : 0;

        parent::__construct();

        if ($this->sort_order === null) {
            return;
        }

        $this->code = 'paypalr_applepay';

        $module_status_setting = $this->getModuleStatusSetting();
        $debugActive = (strpos(MODULE_PAYMENT_PAYPALR_DEBUGGING, 'Log') !== false);

        if (IS_ADMIN_FLAG === false) {
            $this->title = MODULE_PAYMENT_PAYPALR_APPLEPAY_TEXT_TITLE;
        } else {
            $curl_installed = (function_exists('curl_init'));
            $statusSuffix = '';
            if ($module_status_setting === 'Retired') {
                $statusSuffix .= ' <strong>(Retired)</strong>';
            }
            if (MODULE_PAYMENT_PAYPALR_SERVER === 'sandbox') {
                $statusSuffix .= $this->alertMsg(' (sandbox active)');
            }
            if ($debugActive === true) {
                $statusSuffix .= ' <strong>(Debug)</strong>';
            }
            $this->title = MODULE_PAYMENT_PAYPALR_APPLEPAY_TEXT_TITLE_ADMIN . $statusSuffix . (($curl_installed === true) ? '' : $this->alertMsg(MODULE_PAYMENT_PAYPALR_ERROR_NO_CURL));
            $this->description = MODULE_PAYMENT_PAYPALR_APPLEPAY_TEXT_DESCRIPTION;
        }

        if (!defined('MODULE_PAYMENT_PAYPALR_VERSION')) {
            $this->enabled = false;
            if (IS_ADMIN_FLAG === true) {
                $this->title .= $this->alertMsg(MODULE_PAYMENT_PAYPALR_APPLEPAY_ERROR_PAYPAL_REQUIRED);
            }
            return;
        }

        // Apple Pay never presents on-site card entry, ensure card flags are disabled.
        $this->cardsAccepted = false;
        $this->collectsCardDataOnsite = false;
    }

    protected function getModuleStatusSetting(): string
    {
        return $this->variantStatusSetting;
    }

    protected function getModuleSortOrder(): ?int
    {
        return $this->variantSortOrder;
    }

    protected function getModuleZoneSetting(): int
    {
        return $this->variantZoneSetting;
    }

    public function selection(): array
    {
        unset($_SESSION['PayPalRestful']['Order']['wallet_payment_confirmed']);

        $buttonContainer = '<div id="paypalr-applepay-button" class="paypalr-applepay-button"></div>';
        $hiddenFields =
            zen_draw_hidden_field('ppr_type', 'apple_pay') .
            zen_draw_hidden_field('paypalr_applepay_payload', '', 'id="paypalr-applepay-payload"') .
            zen_draw_hidden_field('paypalr_applepay_status', '', 'id="paypalr-applepay-status"');

        $script = '<script>' . file_get_contents(DIR_WS_MODULES . 'payment/paypal/PayPalRestful/jquery.paypalr.applepay.js') . '</script>';

        return [
            'id' => $this->code,
            'module' => MODULE_PAYMENT_PAYPALR_APPLEPAY_TEXT_SELECTION,
            'fields' => [
                [
                    'title' => $buttonContainer,
                    'field' => $hiddenFields . $script,
                ],
            ],
        ];
    }

    public function javascript_validation(): string
    {
        return '';
    }

    public function pre_confirmation_check()
    {
        global $messageStack;

        if ($messageStack->size('checkout_payment') > 0) {
            return;
        }

        $_POST['ppr_type'] = 'apple_pay';
        $_SESSION['PayPalRestful']['ppr_type'] = 'apple_pay';

        $payloadRaw = $_POST['paypalr_applepay_payload'] ?? '';
        if (trim($payloadRaw) === '') {
            $this->setMessageAndRedirect(MODULE_PAYMENT_PAYPALR_APPLEPAY_ERROR_PAYLOAD_MISSING, FILENAME_CHECKOUT_PAYMENT);
        }

        $payload = json_decode($payloadRaw, true);
        if (!is_array($payload)) {
            $this->setMessageAndRedirect(MODULE_PAYMENT_PAYPALR_APPLEPAY_ERROR_PAYLOAD_INVALID, FILENAME_CHECKOUT_PAYMENT);
        }

        $paypal_order_created = $this->createPayPalOrder('apple_pay');
        if ($paypal_order_created === false) {
            $error_info = $this->ppr->getErrorInfo();
            $error_code = $error_info['details'][0]['issue'] ?? 'OTHER';
            $this->sendAlertEmail(MODULE_PAYMENT_PAYPALR_ALERT_SUBJECT_ORDER_ATTN, MODULE_PAYMENT_PAYPALR_ALERT_ORDER_CREATE . \PayPalRestful\Common\Logger::logJSON($error_info));
            $this->setMessageAndRedirect(sprintf(MODULE_PAYMENT_PAYPALR_TEXT_CREATE_ORDER_ISSUE, MODULE_PAYMENT_PAYPALR_APPLEPAY_TEXT_TITLE, $error_code), FILENAME_CHECKOUT_PAYMENT);
        }

        $confirm_response = $this->ppr->confirmPaymentSource(
            $_SESSION['PayPalRestful']['Order']['id'],
            ['apple_pay' => $payload]
        );
        if ($confirm_response === false) {
            $this->errorInfo->copyErrorInfo($this->ppr->getErrorInfo());
            $this->setMessageAndRedirect(MODULE_PAYMENT_PAYPALR_APPLEPAY_ERROR_CONFIRM_FAILED, FILENAME_CHECKOUT_PAYMENT);
        }

        $response_status = $confirm_response['status'] ?? '';
        if ($response_status === PayPalRestfulApi::STATUS_PAYER_ACTION_REQUIRED) {
            $this->log->write('pre_confirmation_check (apple pay) unexpected payer action requirement.' . \PayPalRestful\Common\Logger::logJSON($confirm_response), true, 'after');
            $this->setMessageAndRedirect(MODULE_PAYMENT_PAYPALR_APPLEPAY_ERROR_PAYER_ACTION, FILENAME_CHECKOUT_PAYMENT);
        }

        if ($response_status !== '' && in_array($response_status, self::WALLET_SUCCESS_STATUSES, true)) {
            $_SESSION['PayPalRestful']['Order']['status'] = $response_status;
        }

        $_SESSION['PayPalRestful']['Order']['wallet_payment_confirmed'] = true;
        $_SESSION['PayPalRestful']['Order']['payment_source'] = 'apple_pay';

        $this->log->write('pre_confirmation_check (apple pay) completed successfully.', true, 'after');
    }

    public function confirmation()
    {
        return [
            'title' => MODULE_PAYMENT_PAYPALR_PAYING_WITH_APPLE_PAY,
        ];
    }

    public function process_button()
    {
        return false;
    }

    public function check(): bool
    {
        global $db;
        if (!isset($this->_check)) {
            $check_query = $db->Execute(
                "SELECT configuration_value\n                   FROM " . TABLE_CONFIGURATION . "\n                  WHERE configuration_key = 'MODULE_PAYMENT_PAYPALR_APPLEPAY_STATUS'\n                  LIMIT 1"
            );
            $this->_check = !$check_query->EOF;
        }
        return $this->_check;
    }

    public function install()
    {
        global $db;
        $db->Execute(
            "INSERT INTO " . TABLE_CONFIGURATION . "\n                (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added)\n             VALUES\n                ('Enable PayPal Apple Pay?', 'MODULE_PAYMENT_PAYPALR_APPLEPAY_STATUS', 'False', 'Do you want to enable PayPal Apple Pay payments?', 6, 0, 'zen_cfg_select_option([''True'', ''False'', ''Retired''], ', NULL, now()),\n                ('Sort order of display.', 'MODULE_PAYMENT_PAYPALR_APPLEPAY_SORT_ORDER', '0', 'Sort order of display. Lowest is displayed first.', 6, 0, NULL, NULL, now()),\n                ('Payment Zone', 'MODULE_PAYMENT_PAYPALR_APPLEPAY_ZONE', '0', 'If a zone is selected, only enable this payment method for that zone.', 6, 0, 'zen_cfg_pull_down_zone_classes(', 'zen_get_zone_class_title', now())"
        );
    }

    public function keys(): array
    {
        return [
            'MODULE_PAYMENT_PAYPALR_APPLEPAY_STATUS',
            'MODULE_PAYMENT_PAYPALR_APPLEPAY_SORT_ORDER',
            'MODULE_PAYMENT_PAYPALR_APPLEPAY_ZONE',
        ];
    }

    public function remove()
    {
        global $db;
        $db->Execute("DELETE FROM " . TABLE_CONFIGURATION . " WHERE configuration_key LIKE 'MODULE\\_PAYMENT\\_PAYPALR\\_APPLEPAY\\_%'");
    }
}
