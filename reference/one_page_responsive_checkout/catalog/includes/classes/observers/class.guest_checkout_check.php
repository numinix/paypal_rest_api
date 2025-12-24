<?php
if (!class_exists('guestCheckoutCheck', false)) {
    class guestCheckoutCheck extends base
    {
            function __construct()
            {
                    global $zco_notifier;
                    $zco_notifier->attach(
                $this,
                array(
                    'NOTIFY_HEADER_START_OPRC'
                )
            );
            }

            function update(&$class, $eventID, $paramsArray) {
            global $messageStack;
            if(isset($_SESSION['COWOA']) && OPRC_NOACCOUNT_VIRTUAL == 'false'){
                $products = $_SESSION['cart']->get_products();
                foreach($products as $product){
                    if($product['products_virtual'] == '1'){
                        $messageStack->add_session('shopping_cart', VIRTUAL_PRODUCT_GUEST_ERROR, 'error');
                        zen_redirect(zen_href_link(FILENAME_AJAX_SHOPPING_CART, '', 'NONSSL'));
                    }
                }
            }
            }
    }
}
// eof
