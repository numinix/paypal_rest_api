<?php
/**
 * Optional template override for rendering the Google reCAPTCHA widget on the OPRC registration step.
 */
if (!defined('IS_ADMIN_FLAG')) {
    die('Illegal Access');
}

$recaptchaConfigured = (trim(OPRC_RECAPTCHA_KEY) !== '' && trim(OPRC_RECAPTCHA_SECRET) !== '');

if ($recaptchaConfigured) {
    if (!defined('OPRC_RECAPTCHA_SCRIPT_OUTPUT')) {
        define('OPRC_RECAPTCHA_SCRIPT_OUTPUT', true);
        echo '<script src="https://www.google.com/recaptcha/api.js" async defer></script>';
    }
    ?>
    <div id="securityCheck" class="nmx-box">
        <h3><?php echo ENTRY_SECURITY_CHECK; ?></h3>
        <div class="g-recaptcha" data-sitekey="<?php echo zen_output_string_protected(OPRC_RECAPTCHA_KEY); ?>" data-theme="<?php echo zen_output_string_protected(OPRC_RECAPTCHA_THEME); ?>"></div>
    </div>
    <?php
} else {
    ?>
    <div id="securityCheck" class="nmx-box">
        <h3><?php echo ENTRY_SECURITY_CHECK; ?></h3>
        <p class="information nmx-mb0"><?php echo zen_output_string_protected(ENTRY_SECURITY_CHECK_RECAPTCHA_MISCONFIGURED); ?></p>
    </div>
    <?php
}
