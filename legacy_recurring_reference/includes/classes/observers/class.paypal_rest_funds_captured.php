<?php
//
// +----------------------------------------------------------------------+
// |zen-cart Open Source E-commerce                                       |
// +----------------------------------------------------------------------+
// | Copyright (c) 2007-2008 Numinix Technology http://www.numinix.com    |
// |                                                                      |
// | Portions Copyright (c) 2003-2006 Zen Cart Development Team           |
// | http://www.zen-cart.com/index.php                                    |
// |                                                                      |
// | Portions Copyright (c) 2003 osCommerce                               |
// +----------------------------------------------------------------------+
// | This source file is subject to version 2.0 of the GPL license,       |
// | that is bundled with this package in the file LICENSE, and is        |
// | available through the world-wide-web at the following url:           |
// | http://www.zen-cart.com/license/2_0.txt.                             |
// | If you did not receive a copy of the zen-cart license and are unable |
// | to obtain it through the world-wide-web, please send a note to       |
// | license@zen-cart.com so we can mail you a copy immediately.          |
// +----------------------------------------------------------------------+
//
/**
 * Observer used to normalize PayPal REST capture notifications so existing
 * recurring-payment handlers receive data in the expected structure.
 */
class paypalRestFundsCapturedObserver extends base
{
        public function __construct()
        {
                global $zco_notifier;
                $zco_notifier->attach($this, array('NOTIFY_PAYPALR_FUNDS_CAPTURED'));
        }

        public function update(&$class, $eventID, $paramsArray)
        {
                if ($eventID !== 'NOTIFY_PAYPALR_FUNDS_CAPTURED') {
                        return;
                }

                if (!is_array($paramsArray)) {
                        return;
                }

                global $subscriptionsPaymentsObserver;
                if (!is_object($subscriptionsPaymentsObserver) || !method_exists($subscriptionsPaymentsObserver, 'normalizeRestPayload')) {
                        return;
                }

                $normalized = $subscriptionsPaymentsObserver->normalizeRestPayload($paramsArray);
                if (is_array($normalized) && method_exists($subscriptionsPaymentsObserver, 'setPendingRestNotification')) {
                        $subscriptionsPaymentsObserver->setPendingRestNotification($normalized);
                }
        }
}
