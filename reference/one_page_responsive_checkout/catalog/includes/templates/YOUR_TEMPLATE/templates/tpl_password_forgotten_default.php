<?php
/**
 * Password forgotten page template
 *
 * @copyright Copyright 2003-2023 Zen Cart Development Team
 * @license http://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */

$heading = defined('TEXT_PASSWORD_FORGOTTEN_HEADING') ? TEXT_PASSWORD_FORGOTTEN_HEADING : (defined('HEADING_TITLE') ? HEADING_TITLE : '');
$instructions = defined('TEXT_PASSWORD_FORGOTTEN_INSTRUCTIONS') ? TEXT_PASSWORD_FORGOTTEN_INSTRUCTIONS : (defined('TEXT_MAIN') ? TEXT_MAIN : '');
$submitLabel = defined('TEXT_PASSWORD_FORGOTTEN_SUBMIT') ? TEXT_PASSWORD_FORGOTTEN_SUBMIT : (defined('BUTTON_SUBMIT_ALT') ? BUTTON_SUBMIT_ALT : 'Submit');
$returnLabel = defined('TEXT_PASSWORD_FORGOTTEN_RETURN') ? TEXT_PASSWORD_FORGOTTEN_RETURN : '';
$instructionsId = 'passwordForgottenInstructions';
$describedByAttribute = $instructions !== '' ? ' aria-describedby="' . $instructionsId . '"' : '';
$returnUrl = zen_href_link(FILENAME_LOGIN, '', 'SSL');
?>
<div class="centerColumn nmx oprc-forgotten-password" id="passwordForgotten">
  <?php if ($heading !== ''): ?>
    <h1 class="oprc-forgotten-password__title"><?php echo $heading; ?></h1>
  <?php endif; ?>

  <?php if ($instructions !== ''): ?>
    <p id="<?php echo $instructionsId; ?>" class="oprc-forgotten-password__description"><?php echo $instructions; ?></p>
  <?php endif; ?>

  <div class="oprc-forgotten-password-response">
    <?php
      if (isset($messageStack) && $messageStack->size('password_forgotten') > 0) {
        echo $messageStack->output('password_forgotten');
      }
    ?>
  </div>

  <?php echo zen_draw_form(
    'password_forgotten',
    zen_href_link(FILENAME_PASSWORD_FORGOTTEN, 'action=process', 'SSL'),
    'post',
    'class="oprc-forgotten-password__form nmx-form" id="passwordForgottenForm"'
  ); ?>
    <?php echo zen_draw_hidden_field('securityToken', $_SESSION['securityToken']); ?>

    <div class="nmx-form-group">
      <label for="passwordForgotten-email"><?php echo ENTRY_EMAIL_ADDRESS; ?></label>
      <?php echo zen_draw_input_field('email_address', '', 'id="passwordForgotten-email" class="nmx-form-control"' . $describedByAttribute . ' required', 'email'); ?>
    </div>

    <div class="oprc-forgotten-password__actions">
      <button type="submit" class="cssButton button_login oprc-forgotten-password__submit"><?php echo $submitLabel; ?></button>
    </div>
  </form>

  <?php if ($returnLabel !== ''): ?>
    <p class="oprc-forgotten-password__return">
      <a class="oprc-forgotten-password__return-link" href="<?php echo $returnUrl; ?>"><?php echo $returnLabel; ?></a>
    </p>
  <?php endif; ?>
</div>

<script>
  (function(window) {
    var messages = {
      processing: <?php echo json_encode(defined('TEXT_PASSWORD_FORGOTTEN_PROCESSING') ? TEXT_PASSWORD_FORGOTTEN_PROCESSING : 'Processing…', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>,
      error: <?php echo json_encode(defined('TEXT_PASSWORD_FORGOTTEN_ERROR') ? TEXT_PASSWORD_FORGOTTEN_ERROR : 'We were unable to process your request. Please try again.', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>,
      success: <?php echo json_encode(defined('SUCCESS_PASSWORD_SENT') ? SUCCESS_PASSWORD_SENT : 'If the email address you entered matches an account, we\'ll email you a link to reset your password.', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>
    };

    window.oprcForgottenPasswordMessages = messages;

    if (window.jQuery) {
      window.jQuery(function($) {
        if (typeof oprcBindForgottenPasswordForm === 'function') {
          oprcBindForgottenPasswordForm($('#passwordForgotten'));
        }
      });
    }
  })(window);
</script>
