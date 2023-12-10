<?php
/**
 * A class that provides the main PayPal request history table for a given order
 * in the Zen Cart admin placed with the PayPal Restful payment module.
 *
 * @copyright Copyright 2023 Zen Cart Development Team
 * @license https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 * @version $Id: lat9 2023 Nov 16 Modified in v2.0.0 $
 */
namespace PayPalRestful\Admin\Formatters;

use PayPalRestful\Common\Helpers;
use PayPalRestful\Common\Logger;
use PayPalRestful\Zc2Pp\Amount;

class MainDisplay
{
    protected string $mainDisplay = '';

    protected array $settledFunds = [
        'currency' => '',
        'value' => 0,
        'fee' => 0,
        'exchange_rate' => 0,
    ];

    protected string $modals = '';

    protected Amount $amount;

    protected string $currencyCode;

    protected array $paypalDbTxns;

    protected bool $jQueryLoadRequired = false;

    protected static array $txnTableFields = [
        ['name' => MODULE_PAYMENT_PAYPALR_TXN_TYPE, 'field' => 'txn_type', 'align' => 'center'],
        ['name' => MODULE_PAYMENT_PAYPALR_TXN_PARENT_TXN_ID, 'field' => 'txn_id', 'align' => 'center'],
        ['name' => MODULE_PAYMENT_PAYPALR_DATE_CREATED, 'field' => 'date_added', 'align' => 'center'],
        ['name' => MODULE_PAYMENT_PAYPALR_PAYMENT_TYPE, 'field' => 'payment_type'],
        ['name' => MODULE_PAYMENT_PAYPALR_TXN_STATUS, 'field' => 'payment_status'],
        ['name' => MODULE_PAYMENT_PAYPALR_NAME_EMAIL_ID, 'field' => 'payer_email'],
        ['name' => MODULE_PAYMENT_PAYPALR_GROSS_AMOUNT, 'field' => 'mc_gross', 'align' => 'right', 'is_amount' => true],
        ['name' => MODULE_PAYMENT_PAYPALR_PAYMENT_FEE, 'field' => 'payment_fee', 'align' => 'right', 'is_amount' => true],
    ];

    protected static array $paymentTableFields = [
        ['name' => MODULE_PAYMENT_PAYPALR_TXN_TYPE, 'field' => 'txn_type', 'align' => 'center'],
        ['name' => MODULE_PAYMENT_PAYPALR_TXN_ID, 'field' => 'txn_id', 'align' => 'center'],
        ['name' => MODULE_PAYMENT_PAYPALR_DATE_CREATED, 'field' => 'date_added', 'align' => 'center'],
        ['name' => MODULE_PAYMENT_PAYPALR_TXN_STATUS, 'field' => 'payment_status'],
        ['name' => MODULE_PAYMENT_PAYPALR_EXCHANGE_RATE, 'field' => 'exchange_rate', 'align' => 'right'],
        ['name' => MODULE_PAYMENT_PAYPALR_GROSS_AMOUNT, 'field' => 'payment_gross', 'align' => 'right', 'is_amount' => true],
        ['name' => MODULE_PAYMENT_PAYPALR_PAYMENT_FEE, 'field' => 'payment_fee', 'align' => 'right', 'is_amount' => true],
        ['name' => MODULE_PAYMENT_PAYPALR_SETTLE_AMOUNT, 'field' => 'settle_amount', 'align' => 'right', 'is_amount' => true],
    ];

    public function __construct(array $paypal_db_txns)
    {
        $this->paypalDbTxns = $paypal_db_txns;

        $this->currencyCode = $paypal_db_txns[0]['mc_currency'];
        $this->amount = new Amount($this->currencyCode);

        $this->mainDisplay =
            '<style>' . file_get_contents(DIR_FS_CATALOG . DIR_WS_INCLUDES . 'modules/payment/paypal/PayPalRestful/paypalr.admin.css') . '</style>' .
            $this->buildTxnTable() .
            $this->buildPaymentsTable();

        // -----
        // Done here instead of in the concatenation above, since the modals element is
        // created by the table-builders!
        //
        $this->mainDisplay .=
            $this->modals .
            $this->loadJQuery();
    }

    public function get(): string
    {
        return $this->mainDisplay;
    }

    protected function buildTxnTable(): string
    {
        $include_action_column = true;
        return
            "<table class=\"table ppr-table\">\n" .
            '  <caption class="lead text-center">' . MODULE_PAYMENT_PAYPALR_TXN_TABLE_CAPTION . "</caption>\n" .
            "  <tbody>\n" .
                $this->buildTableHeader(self::$txnTableFields, $include_action_column) .
                $this->buildTxnTableData() .
            "  </tbody>\n" .
            "</table>\n";
    }

    protected function buildPaymentsTable(): string
    {
        $include_action_column = false;
        return
            "<table class=\"table ppr-table\">\n" .
            '  <caption class="lead text-center">' . MODULE_PAYMENT_PAYPALR_PAYMENTS_TABLE_CAPTION . "</caption>\n" .
            "  <tbody>\n" .
                $this->buildTableHeader(self::$paymentTableFields, $include_action_column) .
                $this->buildPaymentTableData() .
            "  </tbody>\n" .
            "</table><hr>\n";
    }

    protected function buildTableHeader(array $table_fields, bool $include_action_column): string
    {
        $header =
            "<tr class=\"dataTableHeadingRow\">\n";

        foreach ($table_fields as $next_field) {
            $align_class = (isset($next_field['align'])) ? " text-{$next_field['align']}" : '';
            $header .=
                "  <th class=\"dataTableHeadingContent$align_class\">" . rtrim($next_field['name'], ':') . "</th>\n";
        }

        if ($include_action_column === true) {
            $header .=
                '  <th class="dataTableHeadingContent text-right">' . MODULE_PAYMENT_PAYPALR_ACTION . "</th>\n";
                "</tr>\n";
        }

        return $header;
    }

    protected function buildTxnTableData(): string
    {
        $found_captures = false;
        $capture_indices = [];
        $last_auth_index = null;
        $found_refunds = false;
        $refund_indices = [];
        $transaction_voided = false;

        $data = '';
        $txn_index = -1;
        foreach ($this->paypalDbTxns as $next_txn) {
            $action_buttons = '';
            $modals = '';

            $data .=
                "<tr class=\"dataTableRow\">\n";

            $txn_index++;
            foreach (self::$txnTableFields as $next_field) {
                // -----
                // Retrieve the field's value, converting it to an 'amount' if so indicated.
                //
                $value = $next_txn[$next_field['field']];
                if (isset($next_field['is_amount']) && $value !== null) {
                    $value = $this->amount->getValueFromString($value);
                }

                // -----
                // Special cases where fields are combined to reduce columns required.
                //
                switch ($next_field['field']) {
                    // -----
                    // Special case for 'txn_id' field, it's followed by its parent-txn-id.
                    //
                    case 'txn_id':
                        if (!empty($next_txn['parent_txn_id'])) {
                            $value .= '<br>' . $next_txn['parent_txn_id'];
                        }
                        break;

                    // -----
                    // Special case for 'payer_email' field, it's displayed as the first/last name,
                    // email-address and payer-id in a single column.
                    //
                    case 'payer_email':
                        $first_name = $next_txn['first_name'];
                        $last_name = $next_txn['last_name'];
                        if (($first_name . $last_name) !== '') {
                            $value = $first_name . ' ' . $last_name . ' (' . $next_txn['payer_status'] . ')<br>' . $value;
                        }
                        $value .= '<br>' . $next_txn['payer_id'];
                        break;

                    // -----
                    // Special case for 'payment_status' field, it's followed by its "pending_reason",
                    // if present.
                    //
                    case 'payment_status':
                        if ($next_txn['pending_reason'] !== null) {
                            $value .= '<br><small>' . $next_txn['pending_reason'] . '</small>';
                        }
                        break;

                    // -----
                    // Special case for 'mc_gross' field, it's followed by its "mc_currency",
                    // if present.
                    //
                    case 'mc_gross':
                        $value .= ' ' . $next_txn['mc_currency'];
                        break;

                    default:
                        if (empty($value) || $value === '0001-01-01 00:00:00') {
                            $value = '&mdash;';
                        }
                        break;
                }

                $align_class = (isset($next_field['align'])) ? " text-{$next_field['align']}" : '';
                $data .=
                    "  <td class=\"dataTableContent$align_class\">$value</td>\n";
            }

            // -----
            // Determine possible actions for a PayPal transaction.
            //
            $action_buttons = "::action::$txn_index";
            switch ($next_txn['txn_type']) {
                case 'CREATE':
                    $action_buttons = $this->createActionButton('details', MODULE_PAYMENT_PAYPALR_ACTION_DETAILS, 'primary');
                    $days_to_settle = '';
                    if ($next_txn['expiration_time'] !== null) {
                        $days_to_settle = Helpers::getDaysTo($next_txn['expiration_time']);
                    }
                    $modals = $this->createDetailsModal($next_txn, $days_to_settle);
                    break;

                case 'AUTHORIZE':
                    [$action_buttons, $modals] = $this->createAuthButtonsAndModals($txn_index, $days_to_settle);
                    break;

                case 'CAPTURE':
                    [$action_buttons, $modals] = $this->createCaptureButtonsAndModals($txn_index);
                    break;

                case 'REFUND':
                    [$action_buttons, $modals] = $this->createRefundButtonsAndModals($txn_index);
                    break;

                default:
                    break;
            }

            $this->modals .= $modals;

            $data .=
                '  <td class="dataTableContent text-right">' . (($action_buttons === '') ? '&mdash;' : $action_buttons) . "</td>\n";
                "</tr>\n";
        }

        return $data;
    }

    protected function createActionButton(string $modal_name, string $button_name, string $button_color): string
    {
        return '<button type="button" class="btn btn-' . $button_color . ' btn-sm" data-toggle="modal" data-target="#' . $modal_name . 'Modal">' . $button_name . '</button>';
    }

    protected function createDetailsModal(array $create_fields, string $days_to_settle): string
    {
        $modal_body =
            '<div class="row">
                <div class="col-md-6 ppr-pr-0">
                    <h5>' . MODULE_PAYMENT_PAYPALR_BUYER_INFO . '</h5>
                    <div class="form-horizontal">';
        $modal_body .= $this->createStaticFormGroup(3, MODULE_PAYMENT_PAYPALR_PAYER_NAME, $create_fields['first_name'] . ' ' . $create_fields['last_name']);
        $modal_body .= $this->createStaticFormGroup(3, MODULE_PAYMENT_PAYPALR_PAYER_ID, $create_fields['payer_id']);
        $modal_body .= $this->createStaticFormGroup(3, MODULE_PAYMENT_PAYPALR_PAYER_STATUS, $create_fields['payer_status']);

        if (!empty($create_fields['address_status'])) {
            $address_elements = [
                'address_name' => MODULE_PAYMENT_PAYPALR_ADDRESS_NAME,
                'address_street' => MODULE_PAYMENT_PAYPALR_ADDRESS_STREET,
                'address_city' => MODULE_PAYMENT_PAYPALR_ADDRESS_CITY,
                'address_state' => MODULE_PAYMENT_PAYPALR_ADDRESS_STATE,
                'address_zip' => MODULE_PAYMENT_PAYPALR_ADDRESS_ZIP,
                'address_country' => MODULE_PAYMENT_PAYPALR_ADDRESS_COUNTRY,
            ];
            foreach ($address_elements as $field_name => $label) {
                $value = $create_fields[$field_name];
                if ($field_name === 'address_name') {
                    $value .= ' (' . MODULE_PAYMENT_PAYPALR_ADDRESS . ' ' . $create_fields['address_status'] . ')';
                }
                $modal_body .= $this->createStaticFormGroup(3, $label, $value);
            }
        }

        $modal_body .=
                    '</div>
                </div>
                <div class="col-md-6 ppr-pr-0">
                    <h5>' . MODULE_PAYMENT_PAYPALR_SELLER_INFO . '</h5>
                    <div class="form-horizontal">';

        $seller_elements = [
            'business' => 'Business:',
            'receiver_email' => 'Email:',
            'receiver_id' => 'Merchant ID:',
        ];
        foreach ($seller_elements as $field_name => $label) {
            $modal_body .= $this->createStaticFormGroup(3, $label, $create_fields[$field_name]);
        }

        $modal_body .= $this->createStaticFormGroup(3, MODULE_PAYMENT_PAYPALR_GROSS_AMOUNT, $this->amount->getValueFromString($create_fields['mc_gross']) . ' ' . $create_fields['mc_currency']);
        if ($days_to_settle !== '') {
            $modal_body .= $this->createStaticFormGroup(3, MODULE_PAYMENT_PAYPALR_DAYSTOSETTLE, $days_to_settle);
        }

        $modal_body .=
                    '</div>
                </div>
            </div>';

        return $this->createModal('details', MODULE_PAYMENT_PAYPALR_DETAILS_TITLE, $modal_body, 'lg');
    }

    protected function createAuthButtonsAndModals(int $auth_index, string $days_to_settle): array
    {
        $action_buttons = '';
        $modals = '';

        // -----
        // Actions on authorizations are allowed up to and including the 29th day after the
        // original AUTHORIZE transaction was placed.
        //
        if ($days_to_settle <= 29) {
            $action_buttons =
                $this->createActionButton("reauth-$auth_index", MODULE_PAYMENT_PAYPALR_ACTION_REAUTH, 'primary') . ' ' .
                $this->createActionButton("capture-$auth_index", MODULE_PAYMENT_PAYPALR_ACTION_CAPTURE, 'warning') . ' ' .
                $this->createActionButton("void-$auth_index", MODULE_PAYMENT_PAYPALR_ACTION_VOID, 'danger');

            $modals =
                $this->createReauthModal($auth_index) .
                $this->createCaptureModal($auth_index) .
                $this->createVoidModal($auth_index);
        }

        return [$action_buttons, $modals];
    }
    protected function createReauthModal(int $auth_index): string
    {
        foreach ($this->paypalDbTxns as $next_txn) {
            if ($next_txn['txn_type'] === 'AUTHORIZE') {
                $first_authorization = $next_txn;
                break;
            }
        }
        $last_authorization = $this->paypalDbTxns[$auth_index]; //-FIXME, not necessarily the last auth

        $days_to_settle = Helpers::getDaysTo($last_authorization['expiration_time']);

        $original_auth_value = $this->amount->getValueFromString($first_authorization['mc_gross']);
        $currency_decimals = $this->amount->getCurrencyDecimals();
        $multiplier = ($currency_decimals === 0) ? 1 : 100;
        $maximum_auth_value = $this->amount->getValueFromFloat(floor($original_auth_value * 1.15 * $multiplier) / $multiplier);
        $amount_input_params = 'type="number" min="1" max="' . $maximum_auth_value . '" step="0.01"';
        $amount_help_text = sprintf(MODULE_PAYMENT_PAYPALR_AMOUNT_RANGE, $this->currencyCode, $maximum_auth_value);

        $days_since_last_auth = Helpers::getDaysFrom($last_authorization['date_added']);

        $submit_button_id = "ppr-reauth-submit-$auth_index";

        $modal_body =
            zen_draw_form("auth-form-$auth_index", FILENAME_ORDERS, zen_get_all_get_params(['action']) . '&action=doAuth', 'post', 'class="form-horizontal"') .
                zen_draw_hidden_field('doAuthOid', $first_authorization['order_id']) .
                zen_draw_hidden_field('auth_txn_id', $this->paypalDbTxns[$auth_index]['txn_id']) .
                '<p>' . MODULE_PAYMENT_PAYPALR_REAUTH_INSTRUCTIONS . '</p>' .
                '<p><b>' . MODULE_PAYMENT_PAYPALR_NOTES . '</b></p>' .
                '<ol>
                    <li>' . MODULE_PAYMENT_PAYPALR_REAUTH_NOTE1 . '</li>
                    <li>' . MODULE_PAYMENT_PAYPALR_REAUTH_NOTE2 . '</li>
                    <li>' . MODULE_PAYMENT_PAYPALR_REAUTH_NOTE3 . '</li>
                    <li>' . sprintf(MODULE_PAYMENT_PAYPALR_REAUTH_NOTE4, $maximum_auth_value . ' ' . $this->currencyCode) . '</li>
                </ol>' .
                $this->createStaticFormGroup(6, MODULE_PAYMENT_PAYPALR_REAUTH_ORIGINAL, $this->amount->getValueFromString($first_authorization['mc_gross']) . ' ' . $this->currencyCode) .
                $this->createStaticFormGroup(6, MODULE_PAYMENT_PAYPALR_DAYSTOSETTLE, $days_to_settle) .
                $this->createStaticFormGroup(6, MODULE_PAYMENT_PAYPALR_REAUTH_DAYS_FROM_LAST, $days_since_last_auth) .
                $this->createModalInput(6, MODULE_PAYMENT_PAYPALR_AMOUNT, $original_auth_value, "auth-amt-$auth_index", 'ppr-amount', $amount_input_params, $amount_help_text) .
                $this->createModalButtons("ppr-reauth-submit-$auth_index", MODULE_PAYMENT_PAYPALR_ACTION_REAUTH, MODULE_PAYMENT_PAYPALR_CONFIRM) .
            '</form>';

        return $this->createModal("reauth-$auth_index", MODULE_PAYMENT_PAYPALR_REAUTH_TITLE, $modal_body);
    }
    protected function createCaptureModal(int $auth_index): string
    {
        $auth_db_txn = $this->paypalDbTxns[$auth_index];
        $original_auth_value = $this->amount->getValueFromString($auth_db_txn['mc_gross']);

        $previously_captured_value = 0;
        $auth_txn_id = $auth_db_txn['txn_id'];
        foreach ($this->paypalDbTxns as $next_txn) {
            if ($next_txn['txn_type'] === 'CAPTURE' && $next_txn['parent_txn_id'] === $auth_txn_id) {
                $previously_captured_value += $next_txn['payment_gross'];
            }
        }

        $maximum_capt_value = $this->amount->getValueFromFloat((float)($original_auth_value - $previously_captured_value));

        $amount_input_params = 'type="number" min="1" max="' . $maximum_capt_value . '" step="0.01"';
        $amount_help_text = sprintf(MODULE_PAYMENT_PAYPALR_AMOUNT_RANGE, $this->currencyCode, $maximum_capt_value);

        $modal_body =
            zen_draw_form("capt-form-$auth_index", FILENAME_ORDERS, zen_get_all_get_params(['action']) . '&action=doCapture', 'post', 'class="form-horizontal"') .
                zen_draw_hidden_field('doCaptOid', $auth_db_txn['order_id']) .
                zen_draw_hidden_field('auth_txn_id', $auth_db_txn['txn_id']) .
                '<p>' . MODULE_PAYMENT_PAYPALR_CAPTURE_INSTRUCTIONS . '</p>' .
                $this->createStaticFormGroup(4, MODULE_PAYMENT_PAYPALR_REAUTH_ORIGINAL, $original_auth_value . ' ' . $this->currencyCode) .
                $this->createStaticFormGroup(4, MODULE_PAYMENT_PAYPALR_CAPTURED_SO_FAR, $this->amount->getValueFromFloat((float)$previously_captured_value) . ' ' . $this->currencyCode) .
                $this->createStaticFormGroup(4, MODULE_PAYMENT_PAYPALR_REMAINING_TO_CAPTURE, $maximum_capt_value . ' ' . $this->currencyCode) .
                $this->createModalInput(4, MODULE_PAYMENT_PAYPALR_AMOUNT, $maximum_capt_value, "capt-amt-$auth_index", 'ppr-amount', $amount_input_params, $amount_help_text) .
                $this->createModalTextArea(4, MODULE_PAYMENT_PAYPALR_CUSTOMER_NOTE, MODULE_PAYMENT_PAYPALR_CAPTURE_DEFAULT_MESSAGE, "capt-note-$auth_index", 'ppr-capt-note') .
                $this->createModalCheckbox(4, MODULE_PAYMENT_PAYPALR_CAPTURE_FINAL_TEXT, 'ppr-capt-final') .
                $this->createModalButtons("ppr-capt-submit-$auth_index", MODULE_PAYMENT_PAYPALR_ACTION_CAPTURE, MODULE_PAYMENT_PAYPALR_CONFIRM) .
            '</form>';
        return $this->createModal("capture-$auth_index", MODULE_PAYMENT_PAYPALR_CAPTURE_TITLE, $modal_body);
    }
    protected function createVoidModal(int $auth_index): string
    {
        $auth_db_txn = $this->paypalDbTxns[$auth_index];

        $modal_body =
            zen_draw_form("void-form-$auth_index", FILENAME_ORDERS, zen_get_all_get_params(['action']) . '&action=doVoid', 'post', 'class="form-horizontal"') .
                zen_draw_hidden_field('doVoidOid', $auth_db_txn['order_id']) .
                $this->createStaticFormGroup(4, MODULE_PAYMENT_PAYPALR_VOID_AUTH_ID, $auth_db_txn['txn_id']) .
                $this->createStaticFormGroup(4, MODULE_PAYMENT_PAYPALR_AMOUNT, $this->amount->getValueFromString($auth_db_txn['mc_gross']) . ' ' . $this->currencyCode) .
                '<p>' . MODULE_PAYMENT_PAYPALR_VOID_INSTRUCTIONS . '</p>' .
                $this->createModalInput(4, MODULE_PAYMENT_PAYPALR_VOID_AUTH_ID, '', "void-id-$auth_index", 'ppr-void-id', 'type="text" required') .
                $this->createModalTextArea(4, MODULE_PAYMENT_PAYPALR_CUSTOMER_NOTE, MODULE_PAYMENT_PAYPALR_VOID_DEFAULT_MESSAGE, "void-note-$auth_index", 'ppr-void-note') .
                $this->createModalButtons("ppr-void-submit-$auth_index", MODULE_PAYMENT_PAYPALR_ACTION_VOID, MODULE_PAYMENT_PAYPALR_CONFIRM) .
            '</form>';
        return $this->createModal("void-$auth_index", MODULE_PAYMENT_PAYPALR_VOID_TITLE, $modal_body);
    }

    protected function createCaptureButtonsAndModals(int $capture_index): array
    {
        $action_buttons = '';
        $modals = '';

        // -----
        // Captures can be refunded, so long as they haven't been fully refunded.
        //
        $action_buttons =
            $this->createActionButton("refund-$capture_index", MODULE_PAYMENT_PAYPALR_ACTION_REFUND, 'warning');

        $modals =
            $this->createRefundModal($capture_index);

        return [$action_buttons, $modals];
    }
    protected function createRefundModal(int $capture_index): string
    {
        $capture_db_txn = $this->paypalDbTxns[$capture_index];
        $original_capture_value = $this->amount->getValueFromString($capture_db_txn['mc_gross']);

        $previously_refunded_value = 0;
        $capture_txn_id = $capture_db_txn['txn_id'];
        foreach ($this->paypalDbTxns as $next_txn) {
            if ($next_txn['txn_type'] === 'REFUND' && $next_txn['parent_txn_id'] === $capture_txn_id) {
                $previously_refunded_value += $next_txn['payment_gross'];
            }
        }

        $maximum_refund_value = $this->amount->getValueFromFloat((float)($original_capture_value - $previously_refunded_value));

        $amount_input_params = 'type="number" min="1" max="' . $maximum_refund_value . '" step="0.01"';
        $amount_help_text = sprintf(MODULE_PAYMENT_PAYPALR_AMOUNT_RANGE, $this->currencyCode, $maximum_refund_value);

        $modal_body =
            zen_draw_form("refund-form-$capture_index", FILENAME_ORDERS, zen_get_all_get_params(['action']) . '&action=doRefund', 'post', 'class="form-horizontal"') .
                zen_draw_hidden_field('doRefundOid', $capture_db_txn['order_id']) .
                zen_draw_hidden_field('capture_txn_id', $capture_db_txn['txn_id']) .
                $this->createStaticFormGroup(4, MODULE_PAYMENT_PAYPALR_VOID_AUTH_ID, $capture_db_txn['txn_id']) .
                $this->createStaticFormGroup(4, MODULE_PAYMENT_PAYPALR_AMOUNT, $this->amount->getValueFromString($capture_db_txn['mc_gross']) . ' ' . $this->currencyCode) .
                $this->createStaticFormGroup(4, MODULE_PAYMENT_PAYPALR_REMAINING_TO_REFUND, $maximum_refund_value . ' ' . $this->currencyCode) .
                '<p>' . MODULE_PAYMENT_PAYPALR_REFUND_INSTRUCTIONS . '</p>' .
                '<ol>
                    <li>' . MODULE_PAYMENT_PAYPALR_REFUND_NOTE1 . '</li>
                    <li>' . MODULE_PAYMENT_PAYPALR_REFUND_NOTE2 . '</li>
                    <li>' . MODULE_PAYMENT_PAYPALR_REFUND_NOTE3 . '</li>
                </ol>' .
                $this->createModalInput(4, MODULE_PAYMENT_PAYPALR_REFUND_AMOUNT, $maximum_refund_value, "refund-amt-$capture_index", 'ppr-amount', $amount_input_params, $amount_help_text) .
                $this->createModalCheckbox(4, MODULE_PAYMENT_PAYPALR_REFUND_FULL, 'ppr-refund-full') .
                $this->createModalTextArea(4, MODULE_PAYMENT_PAYPALR_CUSTOMER_NOTE, MODULE_PAYMENT_PAYPALR_REFUND_DEFAULT_MESSAGE, "refund-note-$capture_index", 'ppr-refund-note') .

                $this->createModalButtons("ppr-refund-submit-$capture_index", MODULE_PAYMENT_PAYPALR_ACTION_REFUND, MODULE_PAYMENT_PAYPALR_CONFIRM) .
            '</form>';
        return $this->createModal("refund-$capture_index", MODULE_PAYMENT_PAYPALR_REFUND_TITLE, $modal_body);
    }
    protected function createRefundButtonsAndModals(int $refund_index): array
    {
        $action_buttons = '';
        $modals = '';

        return [$action_buttons, $modals];
    }

    protected function createStaticFormGroup(int $label_width, string $label_text, string $value_text): string
    {
        $value_width = 12 - $label_width;
        return
            '<div class="form-group">
                <label class="control-label col-sm-' . $label_width . '  ppr-pr-0">' . $label_text . '</label>
                <div class="col-sm-' . $value_width . '">
                    <p class="form-control-static">' . zen_output_string_protected($value_text) . '</p>
                </div>
            </div>';
    }

    protected function createModalInput(int $label_width, string $label_text, string $input_value, string $element_id, string $input_name, string $parameters, string $help_text = ''): string
    {
        $value_width = 12 - $label_width;
        if ($parameters !== '') {
            $parameters = " $parameters";
        }
        if ($help_text !== '') {
            $help_text = '<span class="help-block">' . $help_text . '</span>';
        }
        return
            '<div class="form-group">
                <label class="control-label col-sm-' . $label_width . '" for="' . $element_id . '">' . $label_text . '</label>
                <div class="col-sm-' . $value_width . '">
                    <input name="' . $input_name . '" class="form-control" id="' . $element_id . '" value="' . $input_value . '"' . $parameters . '>
                </div>
            </div>';
    }

    protected function createModalTextArea(int $label_width, string $label_text, string $default_message, string $element_id, string $textarea_name): string
    {
        $value_width = 12 - $label_width;
        return
            '<div class="form-group">
                <label class="control-label col-sm-' . $label_width . '" for="' . $element_id . '">' . $label_text . '</label>
                <div class="col-sm-' . $value_width . '">
                    <textarea name="' . $textarea_name . '" class="form-control" rows="5" id="' . $element_id . '">' . $default_message . '</textarea>
                </div>
            </div>';
    }

    protected function createModalCheckbox(int $label_width, string $label_text, string $checkbox_name): string
    {
        $value_width = 12 - $label_width;
        return
            '<div class="form-group">
                <div class="col-md-offset-' . $label_width . ' col-md-' . $value_width . '">
                    <div class="checkbox">
                        <label><input type="checkbox" name="' . $checkbox_name . '"> ' . $label_text . '</label>
                    </div>
                </div>
            </div>';
    }

    protected function createModalButtons(string $submit_button_id, string $toggle_button_name, string $submit_button_name): string
    {
        return
            '<div class="btn-group btn-group-justified ppr-button-row">
                <div class="btn-group">
                    <button type="button" class="btn btn-info" data-toggle="collapse" data-target="#' . $submit_button_id . '">' . $toggle_button_name . '</button>
                </div>
                <div class="btn-group">
                    <button type="submit" class="btn btn-danger collapse" id="' . $submit_button_id . '">' . $submit_button_name . '</button>
                </div>
            </div>';
    }

    protected function createModal(string $modal_id, string $modal_title, string $modal_body, string $modal_size = 'md'): string
    {
        return
            '<div id="' . $modal_id . 'Modal" class="modal fade ppr-modal">
                <div class="modal-dialog modal-' . $modal_size . '">
                    <div class="modal-content">
                        <div class="modal-header">
                            <button type="button" class="close" data-dismiss="modal">&times;</button>
                            <h4 class="modal-title text-center">' . $modal_title . '</h4>
                        </div>
                        <div class="modal-body">' . $modal_body . '</div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                        </div>
                   </div>
              </div>
        </div>';
    }

    protected function buildPaymentTableData(): string
    {
        $paypal_gross_total = 0;
        $paypal_fees_total = 0;
        $settled_total = 0;

        $data = '';
        foreach ($this->paypalDbTxns as $next_txn) {
            if ($next_txn['txn_type'] === 'CREATE' || $next_txn['settle_amount'] === null) {
                continue;
            }

            $data .=
                "<tr class=\"dataTableRow\">\n";

            foreach (self::$paymentTableFields as $next_field) {
                // -----
                // Retrieve the field's value, converting it to an 'amount' if so indicated.
                //
                $value = $next_txn[$next_field['field']];
                if (isset($next_field['is_amount']) && $value !== null) {
                    $value = $this->amount->getValueFromString($value);
                }

                // -----
                // Determine the currency in which the payment/refund was placed.
                //
                $mc_currency = $next_txn['mc_currency'];

                // -----
                // Calculations for the current PayPal settled amounts.
                //
                switch ($next_field['field']) {
                    // -----
                    // Special case for 'payment_status' field, it's followed by its "pending_reason",
                    // if present.
                    //
                    case 'payment_status':
                        if ($next_txn['pending_reason'] !== null) {
                            $value .= '<br><small>' . $next_txn['pending_reason'] . '</small>';
                        }
                        break;

                    // -----
                    // Special case for 'mc_gross' field, it's followed by its "mc_currency",
                    // if present.
                    //
                    case 'mc_gross':
                        $value .= ' ' . $mc_currency;
                        break;

                    // -----
                    // Payment fees are summed up for the totals row.
                    //
                    case 'payment_fee':
                        $paypal_fees_total += $value;
                        $value .= ' ' . $mc_currency;
                        break;

                    // -----
                    // Gross payments are summed up for the totals row, subtracted from
                    // the running total if the transaction was a REFUND; otherwise added.
                    //
                    case 'payment_gross':
                        if ($next_txn['txn_type'] === 'REFUND') {
                            $paypal_gross_total -= $value;
                            $value = "-$value";
                        } else {
                            $paypal_gross_total += $value;
                        }
                        $value .= ' ' . $mc_currency;
                        break;

                    // -----
                    // Settled amounts are summed up for the totals row, subtracted from
                    // the running total if the transaction was a REFUND; otherwise added.
                    //
                    case 'settle_amount':
                        if ($next_txn['txn_type'] === 'REFUND') {
                            $settled_total -= $value;
                            $value = "-$value";
                        } else {
                            $settled_total += $value;
                        }
                        $value .= ' ' . $next_txn['settle_currency'];
                        break;

                    default:
                        if (empty($value) || $value === '0001-01-01 00:00:00') {
                            $value = '&mdash;';
                        }
                        break;
                }

                $align_class = (isset($next_field['align'])) ? " text-{$next_field['align']}" : '';
                $data .=
                    "  <td class=\"dataTableContent$align_class\">$value</td>\n";
            }
        }

        // -----
        // If no settlements are recorded, return a table-row indicating as such.
        //
        $column_count = count(self::$paymentTableFields);
        if ($data === '') {
            return
                "<tr class=\"dataTableRow ppr-no-payments\">\n" .
                    "<td class=\"dataTableContents text-center\" colspan=\"$column_count\">" . MODULE_PAYMENT_PAYPALR_PAYMENTS_NONE . "</td>\n" .
                "</tr>\n";
        }

        // -----
        // Otherwise, add a table-entry for the current payments' totals.
        //
        $paypal_gross_total = $this->amount->getValueFromString((string)$paypal_gross_total);
        $paypal_fees_total = $this->amount->getValueFromString((string)$paypal_fees_total);
        $settled_total = $this->amount->getValueFromString((string)$settled_total);
        $column_count -= 3;
        $data .=
            "<tr class=\"dataTableHeadingRow text-right ppr-payments\">\n" .
                "<td class=\"dataTableHeadingContent\" colspan=\"$column_count\">" . MODULE_PAYMENT_PAYPALR_PAYMENTS_TOTAL . "</td>\n" .
                "<td class=\"dataTableHeadingContent\">" . $paypal_gross_total . "</td>\n" .
                "<td class=\"dataTableHeadingContent\">" . $paypal_fees_total . "</td>\n" .
                "<td class=\"dataTableHeadingContent\">" . $settled_total . "</td>\n" .
            "</tr>\n";

        return $data;
    }

    protected function loadJQuery(): string
    {
        $jquery = '';
        if ($this->jQueryLoadRequired === true) {
        }
        return $jquery;
    }
}
