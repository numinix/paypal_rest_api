<?php
/**
 * Lightweight compatibility implementation of Zen Cart's order class.
 *
 * This shim provides the minimal surface area required by the REST webhook
 * bootstrap so that webhook requests can be processed in environments where the
 * full Zen Cart storefront stack isn't available (e.g. stand-alone webhook
 * endpoints).
 * 
 * NOTE: This is a minimal placeholder class. Methods like query() and the constructor
 * are non-functional stubs. Webhook handlers should not rely on full order functionality
 * when this compatibility shim is loaded instead of the full core order class.
 */

if (class_exists('order')) {
    return;
}

class order
{
    /**
     * Order information array
     *
     * @var array
     */
    public $info = [];

    /**
     * Order products array
     *
     * @var array
     */
    public $products = [];

    /**
     * Order totals array
     *
     * @var array
     */
    public $totals = [];

    /**
     * Customer information
     *
     * @var array
     */
    public $customer = [];

    /**
     * Delivery information
     *
     * @var array
     */
    public $delivery = [];

    /**
     * Billing information
     *
     * @var array
     */
    public $billing = [];

    /**
     * Content type
     *
     * @var string
     */
    public $content_type = 'physical';

    /**
     * Minimal constructor for compatibility.
     * 
     * @param int|null $order_id Optional order ID (not used in compatibility shim)
     */
    public function __construct($order_id = null)
    {
        // Minimal constructor for compatibility.
        // This compatibility class does not load order data from the database.
        // It exists only to prevent class-not-found errors in webhook contexts.
    }

    /**
     * Placeholder query method for compatibility.
     * 
     * @param int $order_id Order ID (not used in compatibility shim)
     * @return void
     */
    public function query($order_id)
    {
        // No-op in compatibility shim.
        // Webhook handlers should not rely on this method when the compatibility class is loaded.
    }
}
