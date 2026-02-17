<?php
/**
 * A class that formats admin messageStack messages for use by the
 * admin_notifications method of the PayPal Advanced Checkout payment module.
 *
 * @copyright Copyright 2023 Zen Cart Development Team
 * @license https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 * @version $Id: lat9 2023 Nov 16 Modified in v2.0.0 $
 * 
 * Last updated: v1.3.1
 */
namespace PayPalRestful\Admin\Formatters;

class Messages extends \messageStack
{
    // Signature must match parent messageStack::output()
    public function output($class = 'header'): string
    {
        $this->table_data_parameters = 'class="pprNotification"';
        return $this->tableBlock($this->errors);
    }
}

