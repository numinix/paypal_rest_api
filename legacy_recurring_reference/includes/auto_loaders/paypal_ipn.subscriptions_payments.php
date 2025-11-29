<?php
        $autoLoadConfig[85][] = array('autoType'=>'class',
                              'loadFile'=>'observers/class.paypal_rest_funds_captured.php');
        $autoLoadConfig[85][] = array('autoType'=>'classInstantiate',
                              'className'=>'paypalRestFundsCapturedObserver',
                              'objectName'=>'paypalRestFundsCapturedObserver');
        $autoLoadConfig[90][] = array('autoType'=>'class',
                              'loadFile'=>'observers/class.subscriptions_payments.php');
        $autoLoadConfig[90][] = array('autoType'=>'classInstantiate',
                              'className'=>'subscriptionsPaymentsObserver',
                              'objectName'=>'subscriptionsPaymentsObserver');

