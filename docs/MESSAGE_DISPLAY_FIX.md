# Message Display Fix - Visual Demonstration

## Problem (Before Fix)

When archiving a subscription, the page displayed:

```html
<div class="messageStack-header noprint">
    <div class="row messageStackAlert alert alert-danger">paypalr_subscriptions</div>
</div>
```

**Visual Result:**
```
┌─────────────────────────────────────────┐
│ ⚠️ paypalr_subscriptions                │  <-- ERROR: Stack key shown instead of message
└─────────────────────────────────────────┘
```

### Root Cause
The `MessageStack` compatibility class was missing:
1. `output()` method - so `$messageStack->output($messageStackKey)` failed silently
2. Session message loading - so messages from redirects weren't displayed

## Solution (After Fix)

After archiving a subscription, the page now correctly displays:

```html
<div class="messageStack-header noprint">
    <div class="row messageStackAlert alert alert-success">Subscription #123 has been archived.</div>
</div>
```

**Visual Result:**
```
┌─────────────────────────────────────────┐
│ ✓ Subscription #123 has been archived.  │  <-- SUCCESS: Proper message with context
└─────────────────────────────────────────┘
```

## Message Types Supported

### Success Messages (Green)
```html
<div class="messageStack-header noprint">
    <div class="row messageStackAlert alert alert-success">Subscription #456 has been unarchived.</div>
</div>
```
Visual: `✓ Subscription #456 has been unarchived.` (green background)

### Error Messages (Red)
```html
<div class="messageStack-header noprint">
    <div class="row messageStackAlert alert alert-danger">Unable to archive subscription. Missing identifier.</div>
</div>
```
Visual: `⚠️ Unable to archive subscription. Missing identifier.` (red background)

### Warning Messages (Yellow)
```html
<div class="messageStack-header noprint">
    <div class="row messageStackAlert alert alert-warning">This action cannot be undone.</div>
</div>
```
Visual: `⚠ This action cannot be undone.` (yellow background)

## How the Fix Works

### 1. Session Message Storage (During Action)
```php
// When user clicks "Archive" button
$messageStack->add_session('paypalr_subscriptions', 'Subscription #123 has been archived.', 'success');
zen_redirect($redirectUrl);
```

This stores the message in `$_SESSION['messageToStack']['paypalr_subscriptions']`

### 2. Session Message Loading (After Redirect)
```php
// On next page load, MessageStack constructor runs
public function __construct()
{
    // Load messages from session
    if (isset($_SESSION['messageToStack']) && is_array($_SESSION['messageToStack'])) {
        foreach ($_SESSION['messageToStack'] as $stack => $stackMessages) {
            // Load each message into $this->messages
        }
        // Clear session after loading
        unset($_SESSION['messageToStack']);
    }
}
```

### 3. Message Rendering (Page Display)
```php
// Admin page calls
echo $messageStack->output('paypalr_subscriptions');

// output() method renders HTML
public function output($stack = 'header'): string
{
    $messages = $this->messages[$stack] ?? [];
    // Map types to Bootstrap alert classes
    // Escape HTML to prevent XSS
    // Return formatted HTML
}
```

## Security Features

### XSS Prevention
Input: `<script>alert("xss")</script>`

Output:
```html
<div class="messageStack-header noprint">
    <div class="row messageStackAlert alert alert-danger">&lt;script&gt;alert(&quot;xss&quot;)&lt;/script;</div>
</div>
```

The malicious script is escaped and displayed as text, not executed.

### Alert Class Validation
```php
$alertClassMap = [
    'success' => 'alert-success',
    'error' => 'alert-danger',
    'warning' => 'alert-warning',
];
$alertClass = $alertClassMap[$type] ?? 'alert-info';
```

Only valid Bootstrap alert classes are used, preventing HTML injection through the type parameter.

## Impact

This fix affects **all admin pages** that use `messageStack` for displaying messages, including:

1. ✅ Subscription archiving/unarchiving
2. ✅ Subscription status changes (cancel, suspend, reactivate)
3. ✅ Subscription updates
4. ✅ Any other admin actions that use `add_session()` for messages

All success and error messages will now display properly instead of showing the stack key name.
