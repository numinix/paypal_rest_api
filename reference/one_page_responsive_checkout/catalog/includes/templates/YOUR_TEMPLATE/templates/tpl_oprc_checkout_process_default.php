<?php
/**
 * Template for OPRC checkout process external hand-off.
 */

$action = '';
$method = 'post';
$fields = [];
$rawHtml = '';
$autoSubmit = true;

if (!empty($oprcConfirmationForm) && is_array($oprcConfirmationForm)) {
    $action = isset($oprcConfirmationForm['action']) ? $oprcConfirmationForm['action'] : '';
    $method = isset($oprcConfirmationForm['method']) ? strtolower($oprcConfirmationForm['method']) : 'post';
    if ($method !== 'get' && $method !== 'post') {
        $method = 'post';
    }
    $fields = isset($oprcConfirmationForm['fields']) && is_array($oprcConfirmationForm['fields'])
        ? $oprcConfirmationForm['fields']
        : [];
    $rawHtml = isset($oprcConfirmationForm['raw_html']) ? $oprcConfirmationForm['raw_html'] : '';
    if (isset($oprcConfirmationForm['auto_submit'])) {
        $autoSubmit = (bool)$oprcConfirmationForm['auto_submit'];
    }
}

if (!function_exists('oprc_checkout_process_render_fields')) {
    function oprc_checkout_process_render_fields($name, $value)
    {
        if (is_array($value)) {
            foreach ($value as $key => $child) {
                $childName = $name . '[' . $key . ']';
                oprc_checkout_process_render_fields($childName, $child);
            }
            return;
        }

        echo '<input type="hidden" name="' . htmlspecialchars($name, ENT_COMPAT, CHARSET) . '" value="' . htmlspecialchars($value, ENT_COMPAT, CHARSET) . '" />' . "\n";
    }
}
?>
<div id="oprcCheckoutProcess" class="centeredContent">
    <?php if (!empty($oprcProcessMessages)) : ?>
        <div class="messageStackErrors"><?php echo $oprcProcessMessages; ?></div>
    <?php endif; ?>

    <p class="forward"><?php echo htmlspecialchars(defined('TEXT_OPRC_CHECKOUT_PROCESS_REDIRECTING') ? TEXT_OPRC_CHECKOUT_PROCESS_REDIRECTING : 'Redirecting to the selected payment provider…', ENT_COMPAT, CHARSET); ?></p>

    <form id="oprcExternalCheckoutForm" action="<?php echo htmlspecialchars($action, ENT_COMPAT, CHARSET); ?>" method="<?php echo htmlspecialchars($method, ENT_COMPAT, CHARSET); ?>">
        <?php
        foreach ($fields as $name => $value) {
            oprc_checkout_process_render_fields($name, $value);
        }
        if (empty($fields) && is_string($rawHtml) && trim($rawHtml) !== '') {
            echo $rawHtml;
        }
        ?>
        <noscript>
            <p><?php echo htmlspecialchars(defined('TEXT_OPRC_CHECKOUT_PROCESS_SUBMIT') ? TEXT_OPRC_CHECKOUT_PROCESS_SUBMIT : 'Click the button below to continue.', ENT_COMPAT, CHARSET); ?></p>
            <button type="submit" class="button" id="oprcExternalCheckoutSubmit"><?php echo htmlspecialchars(defined('TEXT_OPRC_CHECKOUT_PROCESS_CONTINUE') ? TEXT_OPRC_CHECKOUT_PROCESS_CONTINUE : 'Continue', ENT_COMPAT, CHARSET); ?></button>
        </noscript>
    </form>
</div>
<?php if ($autoSubmit) : ?>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var form = document.getElementById('oprcExternalCheckoutForm');
        if (form) {
            form.submit();
        }
    });
</script>
<?php endif; ?>
