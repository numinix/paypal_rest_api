# Implementing Site-Specific Customizations via Observers

This document explains how to implement customizations outside the core PayPal plugin using Zen Cart's observer pattern. This keeps the plugin clean and maintainable while allowing sites to add custom functionality.

## Why Use Observers?

Site-specific customizations should NOT be added directly to plugin code because:
- They make the plugin harder to maintain and upgrade
- They cause errors on installations that don't have the required dependencies
- They mix business logic specific to one site with general plugin functionality

Instead, use Zen Cart's **observer pattern** to hook into the plugin's events.

## Available Notification Points

The PayPal subscription plugin provides these notification points in the `prepare_order()` method:

```php
// Before order totals are processed
$zco_notifier->notify('NOTIFY_CHECKOUT_PROCESS_BEFORE_ORDER_TOTALS_PROCESS');

// After order totals are processed  
$zco_notifier->notify('NOTIFY_CHECKOUT_PROCESS_AFTER_ORDER_TOTALS_PROCESS');
```

## Example 1: Store Credit Restrictions Based on Product Category

**Use Case:** Prevent store credit from being used on subscription products in certain categories (e.g., "plans" that themselves provide store credit).

**Implementation:** Create an observer class in your site's custom directory.

### File: `includes/classes/observers/class.paypal_subscription_store_credit.php`

```php
<?php
/**
 * Observer to handle store credit restrictions for PayPal subscriptions
 * 
 * Prevents store credit from being used on products in specific categories
 * during recurring subscription processing.
 */

class paypal_subscription_store_credit extends base {
    
    public function __construct() {
        // Listen for the notification before order totals are processed
        $this->attach(
            $this, 
            array('NOTIFY_CHECKOUT_PROCESS_BEFORE_ORDER_TOTALS_PROCESS')
        );
    }
    
    public function update(&$class, $eventID, $param1, &$param2, &$param3, &$param4, &$param5) {
        if ($eventID == 'NOTIFY_CHECKOUT_PROCESS_BEFORE_ORDER_TOTALS_PROCESS') {
            $this->handleStoreCreditForSubscriptions();
        }
    }
    
    private function handleStoreCreditForSubscriptions() {
        // Only run in cron context for recurring subscriptions
        if (empty($_SESSION['in_cron'])) {
            return;
        }
        
        // Check if we have the necessary constants defined
        if (!defined('CATEGORY_ID_PLANS')) {
            return;
        }
        
        // Get the product ID from the cart
        global $order;
        if (empty($order->contents)) {
            return;
        }
        
        // Check if any products in the cart are in restricted categories
        $isRestrictedProduct = false;
        foreach ($order->contents as $product) {
            $products_id = $product['id'] ?? 0;
            
            // Check if product is in plans category
            if (defined('CATEGORY_ID_PLANS') && 
                function_exists('zen_product_in_category') &&
                zen_product_in_category($products_id, CATEGORY_ID_PLANS)) {
                $isRestrictedProduct = true;
                break;
            }
            
            // Check if product is in custom plans category
            if (defined('CATEGORY_ID_CUSTOM_PLANS') && 
                function_exists('zen_product_in_category') &&
                zen_product_in_category($products_id, CATEGORY_ID_CUSTOM_PLANS)) {
                $isRestrictedProduct = true;
                break;
            }
        }
        
        // Handle store credit based on whether product is restricted
        if ($isRestrictedProduct) {
            // Disable store credit for restricted products
            $_SESSION['storecredit'] = 0;
        } else {
            // Load store credit if available
            if (class_exists('storeCredit') && !empty($_SESSION['customer_id'])) {
                $store_credit = new storeCredit();
                $_SESSION['storecredit'] = $store_credit->retrieve_customer_credit($_SESSION['customer_id']);
            }
        }
    }
}
```

### Activating the Observer

The observer will be automatically loaded by Zen Cart if placed in the correct directory. No additional activation needed!

## Example 2: Custom Email Notifications

**Use Case:** Send custom email notifications to a specific address when subscriptions are cancelled or fail.

### File: `includes/classes/observers/class.paypal_subscription_notifications.php`

```php
<?php
/**
 * Observer to send custom email notifications for PayPal subscription events
 */

class paypal_subscription_notifications extends base {
    
    public function __construct() {
        // Listen for subscription status updates
        // Note: You may need to add notification points to the plugin
        // or hook into existing Zen Cart notifications
        $this->attach(
            $this,
            array('NOTIFY_CHECKOUT_PROCESS_AFTER_ORDER_TOTALS_PROCESS')
        );
    }
    
    public function update(&$class, $eventID, $param1, &$param2, &$param3, &$param4, &$param5) {
        if ($eventID == 'NOTIFY_CHECKOUT_PROCESS_AFTER_ORDER_TOTALS_PROCESS') {
            // Add your custom notification logic here
        }
    }
}
```

## Example 3: Custom URL in Email Messages

If you need to customize the payment update URL in emails, you can use Zen Cart's email template system or create an observer that modifies email content.

### Method 1: Using Email Templates

Create a custom email template override:
- `email/header.html` - Add your site-specific header
- `email/footer.html` - Add your site-specific footer with custom URLs

### Method 2: Using an Observer (Advanced)

Hook into email sending notifications to modify message content before emails are sent.

## Testing Your Observers

1. Place your observer file in `includes/classes/observers/`
2. Clear your Zen Cart cache if applicable
3. Run the cron job: `php cron/paypalac_saved_card_recurring.php`
4. Check logs for any errors
5. Verify the expected behavior (store credit restrictions, custom emails, etc.)

## Debugging Tips

Add logging to your observer to verify it's working:

```php
error_log('PayPal Subscription Observer: Handling store credit for product ' . $products_id);
```

Check your error logs to see if the observer is being triggered.

## Benefits of This Approach

✅ **Clean plugin code** - No site-specific modifications in core plugin  
✅ **Easy upgrades** - Update the plugin without losing customizations  
✅ **Maintainable** - All customizations in one place  
✅ **Portable** - Easy to disable or move between installations  
✅ **Error-resistant** - Missing dependencies don't break the plugin  

## Migration from Old Customizations

If you previously had customizations in the plugin code:

1. Create observer file(s) as shown above
2. Move your custom logic into the observer's `update()` method
3. Test thoroughly to ensure behavior is preserved
4. Update the plugin to the clean version without customizations
5. Keep your observer files in version control separately

## Further Reading

- [Zen Cart Observer/Notifier System Documentation](https://docs.zen-cart.com/dev/code/notifiers/)
- [Creating Custom Observers](https://docs.zen-cart.com/dev/code/observers/)
