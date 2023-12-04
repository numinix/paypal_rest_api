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

    protected bool $jQueryLoadRequired = false;

    protected static array $tableFields = [
        ['name' => MODULE_PAYMENT_PAYPALR_TXN_TYPE, 'field' => 'txn_type', 'align' => 'center'],
        ['name' => MODULE_PAYMENT_PAYPALR_TXN_ID, 'field' => 'txn_id', 'align' => 'center'],
        ['name' => MODULE_PAYMENT_PAYPALR_DATE_CREATED, 'field' => 'date_added', 'align' => 'center'],
        ['name' => MODULE_PAYMENT_PAYPALR_PAYMENT_TYPE, 'field' => 'payment_type'],
        ['name' => MODULE_PAYMENT_PAYPALR_NAME_EMAIL, 'field' => 'payer_email'],
        ['name' => MODULE_PAYMENT_PAYPALR_PAYER_ID, 'field' => 'payer_id', 'align' => 'center'],
        ['name' => MODULE_PAYMENT_PAYPALR_CURRENCY_HDR, 'field' => 'mc_currency', 'align' => 'center'],
        ['name' => MODULE_PAYMENT_PAYPALR_GROSS_AMOUNT, 'field' => 'mc_gross', 'align' => 'right'],
        ['name' => MODULE_PAYMENT_PAYPALR_PAYMENT_FEE, 'field' => 'payment_fee', 'align' => 'right'],
        ['name' => MODULE_PAYMENT_PAYPALR_EXCHANGE_RATE, 'field' => 'exchange_rate', 'align' => 'right'],
    ];

    public function __construct(array $paypal_db_txns, array $paypal_status_response)
    {
        $this->mainDisplay =
            '<style>' . file_get_contents(DIR_FS_CATALOG . DIR_WS_INCLUDES . 'modules/payment/paypal/PayPalRestful/paypalr.admin.css') . '</style>';

        $this->mainDisplay .=
            "<hr>\n" .
            "<table class=\"table\">\n" .
            "  <tbody>\n";

        $this->mainDisplay .= $this->buildTableHeader();

        $this->mainDisplay .= $this->buildTableData($paypal_db_txns, $paypal_status_response);

        $this->mainDisplay .=
            "  </tbody>\n" .
            "</table><hr>\n";

        $this->mainDisplay .= $this->modals;

        $this->mainDisplay .= $this->loadJQuery();
    }

    public function get(): string
    {
        return $this->mainDisplay;
    }

    protected function buildTableHeader(): string
    {
        $header =
            "<tr class=\"dataTableHeadingRow\">\n";

        foreach (self::$tableFields as $next_field) {
            $align_class = (isset($next_field['align'])) ? " text-{$next_field['align']}" : '';
            $header .=
                "  <th class=\"dataTableHeadingContent$align_class\">" . rtrim($next_field['name'], ':') . "</th>\n";
        }

        $header .=
            '  <th class="dataTableHeadingContent text-right">' . MODULE_PAYMENT_PAYPALR_ACTION . "</th>\n";
            "</tr>\n";

        return $header;
    }

    protected function buildTableData(array $paypal_db_txns, array $paypal_status_response): string
    {
        $last_capture_index = null;
        $first_auth_index = null;
        $auth_count = 0;
        $last_refund_index = null;
        $last_void_index = null;

        $data = '';
        $txn_index = -1;
        foreach ($paypal_db_txns as $next_txn) {
            $txn_index++;

            $data .=
                "<tr class=\"dataTableRow\">\n";

            foreach (self::$tableFields as $next_field) {
                // -----
                // Special case for 'payer_email' field, append (if not empty) the 'first_name'
                // and 'last_name' preceded by a <br>.
                //
                $value = $next_txn[$next_field['field']];
                if ($next_field['field'] === 'payer_email') {
                    $first_name = $next_txn['first_name'];
                    $last_name = $next_txn['last_name'];
                    if (($first_name . $last_name) !== '') {
                        $value = $first_name . ' ' . $last_name . ' (' . $next_txn['payer_status'] . ')<br>' . $value;
                    }
                }

                if (empty($value) || $value === '0001-01-01 00:00:00') {
                    $value = '&mdash;';
                }
                $align_class = (isset($next_field['align'])) ? " text-{$next_field['align']}" : '';
                $data .=
                    "  <td class=\"dataTableContent$align_class\">$value</td>\n";
            }

            // -----
            // Determine possible actions for a PayPal transaction; buttons for transactions
            // that are currently authorized, captured, refunded or voided will be handled
            // at the bottom of this loop.
            //
            $action_buttons = "::action::$txn_index";
            switch ($next_txn['txn_type']) {
                case 'CREATE':
                    $action_buttons = $this->createActionButton('details', MODULE_PAYMENT_PAYPALR_ACTION_DETAILS, 'primary');
                    $days_to_settle = '';
                    if ($next_txn['expiration_time'] !== null) {
                        $days_to_settle = Helpers::getDaysToSettle($next_txn['expiration_time']);
                    }
                    $this->modals .= $this->createDetailsModal($next_txn, $days_to_settle);
                    if ($next_txn['payment_status'] === 'VOIDED') {
                        $last_void_index = 0;
                    }
                    break;

                case 'CAPTURE':
                    $last_capture_index = $txn_index;
                    break;

                case 'AUTHORIZE':
                    $first_auth_index = ($first_auth_index ?? $txn_index);
                    $auth_count++;
                    break;

                case 'REFUND':
                    $last_refund_index = $txn_index;
                    break;

                case 'VOID':
                    $last_void_index = $last_void_index ?? $txn_index;
                    break;

                default:
                    break;
            }

            $data .=
                '  <td class="dataTableContent text-right">' . $action_buttons . "</td>\n";
                "</tr>\n";
        }

        // -----
        // Now, check to see what (if any) additional action-buttons need to be applied to
        // the PayPal transactions' table.
        //
        if ($last_void_index === null) {
            if ($last_refund_index !== null) {
            } elseif ($last_capture_index !== null) {
            } elseif ($first_auth_index !== null) {
                $first_auth_txn = $paypal_db_txns[$first_auth_index];

                // -----
                // A reauthorization is only allowed once from Day 4 to Day 29 since
                // the date of the original authorization.
                //
                $action_buttons = '';
                if ($auth_count === 1 && $days_to_settle >= 0 && $days_to_settle <= 25) {
                    $action_buttons = $this->createActionButton('reauth', MODULE_PAYMENT_PAYPALR_ACTION_REAUTH, 'primary') . ' ';
                    $this->modals .= $this->createReauthModal($first_auth_txn, $paypal_status_response['purchase_units'][0]['payments']['authorizations']);
                }
                $action_buttons .=
                    $this->createActionButton('capture', MODULE_PAYMENT_PAYPALR_ACTION_CAPTURE, 'warning') . ' ' .
                    $this->createActionButton('void', MODULE_PAYMENT_PAYPALR_ACTION_VOID, 'danger');
                $data = str_replace("::action::$first_auth_index", $action_buttons, $data);

                $this->modals .=
                    $this->createCaptureModal($first_auth_txn) .
                    $this->createVoidModal($first_auth_txn);
            }
        }
        $data = preg_replace('/::action::\d+/', '&mdash;', $data);

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

        $modal_body .= $this->createStaticFormGroup(3, MODULE_PAYMENT_PAYPALR_GROSS_AMOUNT, $create_fields['mc_gross'] . ' ' . $create_fields['mc_currency']);
        if ($days_to_settle !== '') {
            $modal_body .= $this->createStaticFormGroup(3, MODULE_PAYMENT_PAYPALR_DAYSTOSETTLE, $days_to_settle);
        }

        $modal_body .=
                    '</div>
                </div>
            </div>';

        return $this->createModal('details', MODULE_PAYMENT_PAYPALR_DETAILS_TITLE, $modal_body, 'lg');
    }

    protected function createReauthModal(array $auth_db_txn, array $paypal_authorizations): string
    {
        $first_authorization = current($paypal_authorizations);
        $last_authorization = end($paypal_authorizations);

        $days_to_settle = Helpers::getDaysToSettle($last_authorization['expiration_time']);

        $maximum_auth_value = number_format($first_authorization['amount']['value'] * 1.15, 2, '.', '');
        $auth_currency = $auth_db_txn['mc_currency'];

        $modal_body =
            zen_draw_form('auth-form', FILENAME_ORDERS, zen_get_all_get_params(['action']) . '&action=doAuth', 'post', 'class="form-horizontal"') .
                zen_draw_hidden_field('doAuthOid', $auth_db_txn['order_id']) .
                $this->createStaticFormGroup(4, MODULE_PAYMENT_PAYPALR_REAUTH_ORIGINAL, $auth_db_txn['mc_gross']) .
                $this->createStaticFormGroup(4, MODULE_PAYMENT_PAYPALR_CURRENCY_HDR, $auth_currency) .
                $this->createStaticFormGroup(4, MODULE_PAYMENT_PAYPALR_PROTECTIONELIG, $last_authorization['seller_protection']['status']) .
                $this->createStaticFormGroup(4, MODULE_PAYMENT_PAYPALR_DAYSTOSETTLE, $days_to_settle) .
                '<div class="form-group">
                    <label class="control-label col-sm-4" for="auth-amount">' . MODULE_PAYMENT_PAYPALR_AMOUNT . '</label>
                    <div class="col-sm-8">
                        <input name="ppr-amount" class="form-control" id="auth-amount" type="number" min="1" max="' . $maximum_auth_value . '" value="' . $last_authorization['amount']['value'] . '">
                        <span class="help-block">' . sprintf(MODULE_PAYMENT_PAYPALR_REAUTH_AMOUNT_RANGE, $auth_currency, $maximum_auth_value) . '</span>
                    </div>
                </div>
                <div class="btn-group btn-group-justified ppr-button-row">
                    <div class="btn-group">
                        <button type="button" class="btn btn-info" data-toggle="collapse" data-target="#ppr-reauth-submit">' . MODULE_PAYMENT_PAYPALR_ACTION_REAUTH . '</button>
                    </div>
                    <div class="btn-group">
                        <button type="submit" class="btn btn-danger collapse" id="ppr-reauth-submit">' . MODULE_PAYMENT_PAYPALR_CONFIRM . '</button>
                    </div>
                </div>
            </form>';

        return $this->createModal('reauth', MODULE_PAYMENT_PAYPALR_REAUTH_TITLE, $modal_body);
    }

    protected function createCaptureModal(array $auth_db_txn): string
    {
        $modal_body = 'TBD';
        return $this->createModal('capture', MODULE_PAYMENT_PAYPALR_CAPTURE_TITLE, $modal_body);
    }

    protected function createVoidModal(array $auth_db_txn): string
    {
        $modal_body =
            zen_draw_form('void-form', FILENAME_ORDERS, zen_get_all_get_params(['action']) . '&action=doVoid', 'post', 'class="form-horizontal"') .
                zen_draw_hidden_field('doVoidOid', $auth_db_txn['order_id']) .
                $this->createStaticFormGroup(4, MODULE_PAYMENT_PAYPALR_VOID_AUTH_ID, $auth_db_txn['txn_id']) .
                $this->createStaticFormGroup(4, MODULE_PAYMENT_PAYPALR_VOID_AMOUNT, $auth_db_txn['mc_gross'] . ' ' . $auth_db_txn['mc_currency']) .
                '<p>' . MODULE_PAYMENT_PAYPALR_VOID_INSTRUCTIONS . '</p>
                <div class="form-group">
                    <label class="control-label col-sm-4" for="void-id">' . MODULE_PAYMENT_PAYPALR_VOID_AUTH_ID . '</label>
                    <div class="col-sm-8">
                        <input name="ppr-void-id" class="form-control" id="void-id" type="text" value="" required>
                    </div>
                </div>
                <div class="form-group">
                    <label class="control-label col-sm-4" for="void-note">' . MODULE_PAYMENT_PAYPALR_VOID_CUSTOMOR_NOTE . '</label>
                    <div class="col-sm-8">
                        <textarea name="ppr-void-note" class="form-control" rows="5" id="void-note">' . MODULE_PAYMENT_PAYPALR_VOID_DEFAULT_MESSAGE . '</textarea>
                    </div>
                </div>
                <div class="btn-group btn-group-justified ppr-button-row">
                    <div class="btn-group">
                        <button type="button" class="btn btn-info" data-toggle="collapse" data-target="#ppr-void-submit">' . MODULE_PAYMENT_PAYPALR_VOID_BUTTON_TEXT . '</button>
                    </div>
                    <div class="btn-group">
                        <button type="submit" class="btn btn-danger collapse" id="ppr-void-submit">' . MODULE_PAYMENT_PAYPALR_CONFIRM . '</button>
                    </div>
                </div>
            </form>';
        return $this->createModal('void', MODULE_PAYMENT_PAYPALR_VOID_TITLE, $modal_body);
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

    protected function loadJQuery(): string
    {
        $jquery = '';
        if ($this->jQueryLoadRequired === true) {
        }
        return $jquery;
    }
}
