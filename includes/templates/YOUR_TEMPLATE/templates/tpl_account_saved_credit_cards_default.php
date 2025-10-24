<?php
/**
 * Saved credit cards page template
 */
?>
<div class="centerColumn account-page" id="savedCreditCardsDefault">
  <header class="page-header">
    <h1><?php echo HEADING_TITLE; ?></h1>
  </header>

  <?php require $template->get_template_dir('tpl_modules_account_menu.php', DIR_WS_TEMPLATE, $current_page_base, 'templates') . '/tpl_modules_account_menu.php'; ?>

  <?php if ($messageStack->size('saved_credit_cards') > 0) echo $messageStack->output('saved_credit_cards'); ?>

  <?php if ($hide_saved_cards_page === true) { ?>
    <div class="alert alert-info" role="status"><?php echo TEXT_SAVED_CARDS_DISABLED; ?></div>
  <?php } else { ?>
    <?php if ($delete_card !== null) { ?>
      <section class="saved-cards-confirm card border-danger mb-4">
        <div class="card-body">
          <h2 class="card-title h4 mb-3"><?php echo HEADING_TITLE_DELETE_CARD; ?></h2>
          <p class="mb-4"><?php echo sprintf(TEXT_DELETE_CARD_CONFIRMATION, zen_output_string_protected($delete_card['brand']), zen_output_string_protected($delete_card['last_digits'])); ?></p>
          <div class="saved-card__actions">
            <?php echo zen_draw_form('delete_card', zen_href_link(FILENAME_ACCOUNT_SAVED_CREDIT_CARDS, '', 'SSL'), 'post', 'class="saved-card__form"'); ?>
              <?php echo zen_draw_hidden_field('action', 'delete-card'); ?>
              <?php echo zen_draw_hidden_field('paypal_vault_id', (int)$delete_card['paypal_vault_id']); ?>
              <?php echo zen_draw_hidden_field('securityToken', $_SESSION['securityToken']); ?>
              <button type="submit" class="btn btn-danger"><?php echo TEXT_SAVED_CARD_DELETE_BUTTON; ?></button>
            </form>
            <a class="btn btn-outline-secondary" href="<?php echo zen_href_link(FILENAME_ACCOUNT_SAVED_CREDIT_CARDS, '', 'SSL'); ?>"><?php echo BUTTON_CANCEL_ALT; ?></a>
          </div>
        </div>
      </section>
    <?php } ?>

    <?php if (empty($saved_credit_cards)) { ?>
      <p class="saved-cards-empty lead"><?php echo TEXT_NO_SAVED_CARDS; ?></p>
    <?php } else { ?>
      <p class="saved-cards-intro"><?php echo TEXT_SAVED_CARDS_INTRO; ?></p>
      <div class="saved-cards-grid">
        <?php foreach ($saved_credit_cards as $card) { ?>
          <article class="saved-card <?php echo zen_output_string_protected($card['status_class']); ?>">
            <header class="saved-card__header">
              <div class="saved-card__summary">
                <span class="saved-card__brand"><?php echo zen_output_string_protected($card['brand']); ?></span>
                <?php if ($card['last_digits'] !== '') { ?>
                  <span class="saved-card__digits"><?php echo sprintf(TEXT_CARD_ENDING_IN, zen_output_string_protected($card['last_digits'])); ?></span>
                <?php } ?>
              </div>
              <span class="saved-card__status badge <?php echo zen_output_string_protected($card['status_class']); ?>"><?php echo zen_output_string_protected($card['status_label']); ?></span>
            </header>

            <div class="saved-card__meta">
              <?php if ($card['expiry'] !== '') { ?>
                <div class="saved-card__line"><?php echo sprintf(TEXT_CARD_EXPIRY, zen_output_string_protected($card['expiry'])); ?></div>
              <?php } ?>
              <?php if ($card['cardholder_name'] !== '') { ?>
                <div class="saved-card__line"><?php echo sprintf(TEXT_CARDHOLDER_NAME, zen_output_string_protected($card['cardholder_name'])); ?></div>
              <?php } ?>
              <?php if ($card['last_used'] !== '') { ?>
                <div class="saved-card__line"><?php echo sprintf(TEXT_CARD_LAST_USED, zen_output_string_protected($card['last_used'])); ?></div>
              <?php } ?>
              <?php if ($card['updated'] !== '' && $card['updated'] !== $card['last_used']) { ?>
                <div class="saved-card__line"><?php echo sprintf(TEXT_CARD_UPDATED, zen_output_string_protected($card['updated'])); ?></div>
              <?php } ?>
              <?php if ($card['created'] !== '') { ?>
                <div class="saved-card__line"><?php echo sprintf(TEXT_CARD_ADDED, zen_output_string_protected($card['created'])); ?></div>
              <?php } ?>
            </div>

            <?php if (!empty($card['billing_address'])) { ?>
              <div class="saved-card__details is-collapsed" id="<?php echo zen_output_string_protected($card['details_id']); ?>">
                <h3 class="saved-card__details-title h6"><?php echo TEXT_CARD_BILLING_ADDRESS; ?></h3>
                <address class="saved-card__address">
                  <?php foreach ($card['billing_address'] as $line) { ?>
                    <span><?php echo $line; ?></span>
                  <?php } ?>
                </address>
              </div>
            <?php } ?>

            <div class="saved-card__actions">
              <?php if (!empty($card['billing_address'])) { ?>
                <button type="button"
                        class="btn btn-link saved-card__toggle"
                        data-saved-card-toggle
                        data-target="<?php echo zen_output_string_protected($card['details_id']); ?>"
                        data-label-collapsed="<?php echo zen_output_string_protected(TEXT_SAVED_CARD_DETAILS_BUTTON); ?>"
                        data-label-expanded="<?php echo zen_output_string_protected(TEXT_SAVED_CARD_HIDE_DETAILS); ?>"
                        aria-controls="<?php echo zen_output_string_protected($card['details_id']); ?>"
                        aria-expanded="false">
                  <?php echo TEXT_SAVED_CARD_DETAILS_BUTTON; ?>
                </button>
              <?php } ?>
              <a class="btn btn-outline-danger" href="<?php echo zen_output_string_protected($card['delete_href']); ?>">
                <?php echo TEXT_SAVED_CARD_DELETE_BUTTON; ?>
              </a>
            </div>
          </article>
        <?php } ?>
      </div>
    <?php } ?>
  <?php } ?>
</div>
