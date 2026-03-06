<?php
/**
 *  ot_local_sales_tax module
 *
 *   By Heather Gardner AKA: LadyHLG
 *   The module should apply tax based on the field you
 *   choose options are Zip Code, City, and Suburb.
 *   It should also compound the tax to whatever zone
 *   taxes you already have set up.  Which means you
 *   can apply multiple taxes to any zone based on
 *   different criteria.
 *  ot_local_sales_taxes.php  version 2.5.3
 */
class ot_local_sales_taxes
{
    /**
    * The unique "code" identifying this Order Total Module
    */
    public string $code;

    /**
    * The title shown for this Order Total Module
    */
    public string $title;

    /**
    * The description shown for this Order Total Module
    * @var string
    */
    public string $description;

    /**
    * The sort order at which to apply this Order Total Module
    * @var string
    */
    public int|null $sort_order;

    /**
    * The output from this Order Total Module
    */
    public array $output;

    /**
    * The Tax Class for this Order Total Module
    */
    public int $tax_class_id;

    protected string $store_tax_basis;
    protected bool $debug;
    protected $_check;

    public function __construct()
    {
        $this->code = 'ot_local_sales_taxes';
        $this->title = MODULE_ORDER_TOTAL_COUNTY_LOCAL_TAX_TITLE;
        $this->description = MODULE_ORDER_TOTAL_COUNTY_LOCAL_TAX_DESCRIPTION;

        $this->sort_order = defined('MODULE_ORDER_TOTAL_COUNTY_LOCAL_TAX_SORT_ORDER') ? MODULE_ORDER_TOTAL_COUNTY_LOCAL_TAX_SORT_ORDER : null;
        if (null === $this->sort_order) {
            return;
        }

        $this->store_tax_basis = MODULE_ORDER_TOTAL_COUNTY_LOCAL_TAX_STORE_TAX_BASIS;
        $this->debug = (MODULE_ORDER_TOTAL_COUNTY_LOCAL_TAX_DEBUG === 'true');
        $this->output = [];
    }

    // -----
    // Nothing to do in the order-total's processing; any local sales-tax rates are
    // set via the associated auto-loaded observer.
    //
    public function process()
    {
        return [];
    }

    public function check(): int
    {
        global $db;

        if (!isset($this->_check)) {
            $check_query = $db->Execute("SELECT configuration_value FROM " . TABLE_CONFIGURATION . " WHERE configuration_key = 'MODULE_ORDER_TOTAL_COUNTY_LOCAL_TAX_STATUS' LIMIT 1");
            $this->_check = (int)$check_query->RecordCount();
        }
        return $this->_check;
    }

    public function keys(): array
    {
        return [
            'MODULE_ORDER_TOTAL_COUNTY_LOCAL_TAX_STATUS',
            'MODULE_ORDER_TOTAL_COUNTY_LOCAL_TAX_SORT_ORDER',
            'MODULE_ORDER_TOTAL_COUNTY_LOCAL_TAX_STORE_TAX_BASIS',
            'MODULE_ORDER_TOTAL_COUNTY_LOCAL_TAX_DEBUG',
        ];
    }

    public function install(): void
    {
        global $db;

        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) VALUES ('This module is installed', 'MODULE_ORDER_TOTAL_COUNTY_LOCAL_TAX_STATUS', 'true', '', 6, 1,'zen_cfg_select_option([\'true\'], ', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) VALUES ('Sort Order', 'MODULE_ORDER_TOTAL_COUNTY_LOCAL_TAX_SORT_ORDER', '301', 'Sort order of display.', 6, 2, now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) VALUES ('Store Pickup Tax Basis', 'MODULE_ORDER_TOTAL_COUNTY_LOCAL_TAX_STORE_TAX_BASIS', '', 'Should be a zip code, city name or suburb entry. This should match to at least one of the local tax records.', 6, 3, now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function ,date_added) VALUES ('Debugging is active', 'MODULE_ORDER_TOTAL_COUNTY_LOCAL_TAX_DEBUG', 'false', 'Turn Debugging on or off.', 6, 6,'zen_cfg_select_option([\'false\', \'true\'], ', now())");
    }

    public function remove(): void
    {
        global $db;
        $db->Execute("DELETE FROM " . TABLE_CONFIGURATION . " WHERE configuration_key IN ('" . implode("', '", $this->keys()) . "')");
    }
}
