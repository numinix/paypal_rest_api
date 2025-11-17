<?php
/**
 * paypalr_creditcard.php payment module class for Credit Card payments via PayPal Advanced Checkout
 *
 * @copyright Copyright 2023-2025 Zen Cart Development Team
 * @copyright Portions Copyright 2003 osCommerce
 * @license http://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 *
 * Last updated: v1.3.3
 */

/**
 * Load the base paypalr module which this extends
 */
require_once DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/paypalr.php';

/**
 * The Credit Card payment module using PayPal's REST APIs (v2)
 * This module extends paypalr and shows ONLY credit card payment options
 */
class paypalr_creditcard extends paypalr
{
    /**
     * Override to use credit card-specific status setting
     */
    protected function getModuleStatusSetting(): string
    {
        return defined('MODULE_PAYMENT_PAYPALR_CREDITCARD_STATUS') ? MODULE_PAYMENT_PAYPALR_CREDITCARD_STATUS : 'False';
    }

    /**
     * Override to use credit card-specific sort order
     */
    protected function getModuleSortOrder(): ?int
    {
        return defined('MODULE_PAYMENT_PAYPALR_CREDITCARD_SORT_ORDER') ? (int)MODULE_PAYMENT_PAYPALR_CREDITCARD_SORT_ORDER : null;
    }

    /**
     * Override to use credit card-specific zone setting
     */
    protected function getModuleZoneSetting(): int
    {
        return defined('MODULE_PAYMENT_PAYPALR_CREDITCARD_ZONE') ? (int)MODULE_PAYMENT_PAYPALR_CREDITCARD_ZONE : 0;
    }

    /**
     * class constructor
     */
    public function __construct()
    {
        // Call parent constructor to initialize all the base functionality
        parent::__construct();

        // Override the module code to distinguish this from the base paypalr module
        $this->code = 'paypalr_creditcard';

        // Override titles for this module
        if (IS_ADMIN_FLAG === false) {
            $this->title = MODULE_PAYMENT_PAYPALR_CREDITCARD_TEXT_TITLE ?? 'PayPal Credit Cards';
        } else {
            $this->title = (MODULE_PAYMENT_PAYPALR_CREDITCARD_TEXT_TITLE_ADMIN ?? 'PayPal Credit Cards') . 
                          (function_exists('curl_init') ? '' : $this->alertMsg(MODULE_PAYMENT_PAYPALR_ERROR_NO_CURL ?? ''));
            $this->description = sprintf(
                MODULE_PAYMENT_PAYPALR_CREDITCARD_TEXT_DESCRIPTION ?? 'Accept credit card payments via PayPal Advanced Checkout (v%s). Requires the main PayPal Advanced Checkout module to be installed.',
                self::CURRENT_VERSION
            );

            // Add version check
            $installed_version = defined('MODULE_PAYMENT_PAYPALR_CREDITCARD_VERSION') ? MODULE_PAYMENT_PAYPALR_CREDITCARD_VERSION : '0.0.0';
            if (version_compare($installed_version, self::CURRENT_VERSION, '<')) {
                $this->description .= sprintf(
                    '<br><br><p><strong>Update Available:</strong> Version %2$s is available. You are currently running version %1$s.</p>',
                    $installed_version,
                    self::CURRENT_VERSION
                );
            }
        }

        // Ensure credit cards are properly configured and enabled
        // The parent constructor already checked SSL, card types, etc.
        // If parent disabled cards due to missing requirements, show appropriate message
        if (IS_ADMIN_FLAG === false && $this->enabled === true) {
            if ($this->cardsAccepted === false) {
                // Cards are not accepted - likely due to SSL or configuration issues
                // Disable this module as it has no purpose without card acceptance
                $this->enabled = false;
            } else {
                // Cards are accepted - ensure collection flag is set
                $this->collectsCardDataOnsite = true;
                
                // Force the session to use 'card' payment type when this module is selected
                if (isset($_SESSION['payment']) && $_SESSION['payment'] === $this->code) {
                    $_SESSION['PayPalRestful']['ppr_type'] = 'card';
                }
            }
        }
    }

    /**
     * Override selection to show ONLY credit card fields (no PayPal button)
     */
    public function selection(): array
    {
        // Get the parent selection which includes both PayPal and credit card options
        $parent_selection = parent::selection();

        // If the parent returned an error or unsupported country message, return as-is
        if (isset($parent_selection['fields']) && count($parent_selection['fields']) === 1) {
            $first_field = $parent_selection['fields'][0];
            if (isset($first_field['title']) && strpos($first_field['title'], 'PLEASE NOTE') !== false) {
                return $parent_selection;
            }
        }

        // Remove the PayPal wallet option fields - keep only credit card fields
        // The parent selection() returns fields array with PayPal button as first two elements
        // We want to remove those and keep only the credit card fields
        if (isset($parent_selection['fields']) && is_array($parent_selection['fields'])) {
            $credit_card_fields = [];
            $found_card_choice = false;
            
            foreach ($parent_selection['fields'] as $field) {
                // Skip the PayPal choice field (identified by tag='ppr-paypal')
                if (isset($field['tag']) && $field['tag'] === 'ppr-paypal') {
                    continue;
                }
                
                // Skip the card choice radio button field as we only have one option
                if (isset($field['tag']) && $field['tag'] === 'ppr-card') {
                    $found_card_choice = true;
                    continue;
                }
                
                // Keep all other fields (credit card input fields)
                $credit_card_fields[] = $field;
            }
            
            $parent_selection['fields'] = $credit_card_fields;
        }

        // Update the module display to just show credit card images
        $parent_selection['module'] = $this->buildCardsAccepted() .
            '<script defer src="' . DIR_WS_MODULES . 'payment/paypal/PayPalRestful/jquery.paypalr.checkout.js"></script>' .
            zen_draw_hidden_field('ppr_type', 'card');

        return $parent_selection;
    }

    /**
     * Evaluate installation status of this module
     */
    public function check(): bool
    {
        global $db;

        if (!isset($this->_check)) {
            $check_query = $db->Execute(
                "SELECT configuration_value
                   FROM " . TABLE_CONFIGURATION . "
                  WHERE configuration_key = 'MODULE_PAYMENT_PAYPALR_CREDITCARD_STATUS'
                  LIMIT 1"
            );
            $this->_check = !$check_query->EOF;
        }
        return $this->_check;
    }

    /**
     * Install the credit card module configuration
     */
    public function install()
    {
        global $db;

        $current_version = self::CURRENT_VERSION;
        $db->Execute(
            "INSERT INTO " . TABLE_CONFIGURATION . "
                (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added)
             VALUES
                ('Module Version', 'MODULE_PAYMENT_PAYPALR_CREDITCARD_VERSION', '$current_version', 'Currently-installed module version.', 6, 0, 'zen_cfg_read_only(', NULL, now()),

                ('Enable Credit Card Payments?', 'MODULE_PAYMENT_PAYPALR_CREDITCARD_STATUS', 'False', 'Do you want to enable credit card payments via PayPal? This module requires the main PayPal Advanced Checkout (paypalr) module to be installed and configured with valid API credentials. Use the <b>Retired</b> setting if you are planning to remove this payment module but still have administrative actions to perform against orders placed with this module.', 6, 0, 'zen_cfg_select_option([\'True\', \'False\', \'Retired\'], ', NULL, now()),

                ('Sort order of display.', 'MODULE_PAYMENT_PAYPALR_CREDITCARD_SORT_ORDER', '0', 'Sort order of display. Lowest is displayed first.', 6, 0, NULL, NULL, now()),

                ('Payment Zone', 'MODULE_PAYMENT_PAYPALR_CREDITCARD_ZONE', '0', 'If a zone is selected, only enable this payment method for that zone.', 6, 0, 'zen_cfg_pull_down_zone_classes(', 'zen_get_zone_class_title', now())"
        );

        $this->notify('NOTIFY_PAYMENT_PAYPALR_CREDITCARD_INSTALLED');
    }

    /**
     * Return the configuration keys for this module
     */
    public function keys(): array
    {
        return [
            'MODULE_PAYMENT_PAYPALR_CREDITCARD_VERSION',
            'MODULE_PAYMENT_PAYPALR_CREDITCARD_STATUS',
            'MODULE_PAYMENT_PAYPALR_CREDITCARD_SORT_ORDER',
            'MODULE_PAYMENT_PAYPALR_CREDITCARD_ZONE',
        ];
    }

    /**
     * Uninstall this module
     */
    public function remove()
    {
        global $db;

        $db->Execute("DELETE FROM " . TABLE_CONFIGURATION . " WHERE configuration_key LIKE 'MODULE\_PAYMENT\_PAYPALR\_CREDITCARD\_%'");

        $this->notify('NOTIFY_PAYMENT_PAYPALR_CREDITCARD_UNINSTALLED');
    }
}
