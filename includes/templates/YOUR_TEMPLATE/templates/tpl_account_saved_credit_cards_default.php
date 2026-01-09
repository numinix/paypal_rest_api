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
            <?php
              $deleteAction = isset($delete_card['confirm_action']) ? $delete_card['confirm_action'] : 'delete-card';
              $cardIdField = ($delete_card['source'] ?? 'vault') === 'payflow' ? 'payflow_card_id' : 'paypal_vault_id';
              $cardIdValue = ($delete_card['source'] ?? 'vault') === 'payflow' ? (int)$delete_card['payflow_card_id'] : (int)$delete_card['paypal_vault_id'];
            ?>
            <?php echo zen_draw_form('delete_card', zen_href_link(FILENAME_ACCOUNT_SAVED_CREDIT_CARDS, '', 'SSL'), 'post', 'class="saved-card__form"'); ?>
              <?php echo zen_draw_hidden_field('action', $deleteAction); ?>
              <?php echo zen_draw_hidden_field($cardIdField, $cardIdValue); ?>
              <?php echo zen_draw_hidden_field('securityToken', $_SESSION['securityToken']); ?>
              <button type="submit" class="btn btn-danger"><?php echo TEXT_SAVED_CARD_DELETE_BUTTON; ?></button>
            </form>
            <a class="btn btn-outline-secondary" href="<?php echo zen_href_link(FILENAME_ACCOUNT_SAVED_CREDIT_CARDS, '', 'SSL'); ?>"><?php echo BUTTON_CANCEL_ALT; ?></a>
          </div>
        </div>
      </section>
    <?php } ?>

    <?php if ($edit_card !== null) { ?>
      <section class="saved-cards-editor card mb-4">
        <div class="card-body">
          <h2 class="card-title h4 mb-3"><?php echo HEADING_TITLE_EDIT_CARD; ?></h2>
          <p class="text-muted mb-4"><?php echo TEXT_EDIT_CARD_INTRO; ?></p>

          <?php if (!empty($edit_card_errors)) { ?>
            <div class="alert alert-danger" role="alert">
              <ul class="mb-0">
                <?php foreach ($edit_card_errors as $errorMessage) { ?>
                  <li><?php echo zen_output_string_protected($errorMessage); ?></li>
                <?php } ?>
              </ul>
            </div>
          <?php } ?>

          <?php echo zen_draw_form('update_card', zen_href_link(FILENAME_ACCOUNT_SAVED_CREDIT_CARDS, '', 'SSL'), 'post', 'class="saved-card__form"'); ?>
            <?php echo zen_draw_hidden_field('action', 'update-card'); ?>
            <?php echo zen_draw_hidden_field('paypal_vault_id', (int)$edit_card['paypal_vault_id']); ?>
            <?php echo zen_draw_hidden_field('securityToken', $_SESSION['securityToken']); ?>

            <div class="row g-3">
              <div class="col-12 col-md-6">
                <div class="mb-3">
                  <span class="badge <?php echo zen_output_string_protected($edit_card['status_class']); ?> mb-2"><?php echo zen_output_string_protected($edit_card['status_label']); ?></span>
                  <div class="fw-semibold">&nbsp;<?php echo zen_output_string_protected($edit_card['brand']); ?></div>
                  <?php if ($edit_card['last_digits'] !== '') { ?>
                    <small class="text-muted"><?php echo sprintf(TEXT_CARD_ENDING_IN, zen_output_string_protected($edit_card['last_digits'])); ?></small>
                  <?php } ?>
                </div>
              </div>

              <div class="col-12">
                <div class="mb-3">
                  <label class="form-label" for="edit_cardholder_name"><?php echo TEXT_EDIT_CARD_CARDHOLDER; ?></label>
                  <?php echo zen_draw_input_field('cardholder_name', $edit_form_values['cardholder_name'] ?? '', 'class="form-control" id="edit_cardholder_name" required', 'text', true); ?>
                </div>
              </div>

              <div class="col-12 col-md-6">
                <label class="form-label" for="edit_expiry_month"><?php echo TEXT_EDIT_CARD_EXPIRY; ?></label>
                <div class="row g-2 align-items-end">
                  <div class="col-6">
                    <?php echo zen_draw_pull_down_menu('expiry_month', $expiry_month_options, $edit_form_values['expiry_month'] ?? '', 'class="form-select" id="edit_expiry_month" required'); ?>
                  </div>
                  <div class="col-6">
                    <?php echo zen_draw_pull_down_menu('expiry_year', $expiry_year_options, $edit_form_values['expiry_year'] ?? '', 'class="form-select" id="edit_expiry_year" required'); ?>
                  </div>
                </div>
              </div>

              <div class="col-12 col-md-6">
                <div class="mb-3">
                  <label class="form-label" for="edit_security_code"><?php echo TEXT_EDIT_CARD_SECURITY_CODE; ?></label>
                  <?php echo zen_draw_input_field('security_code', '', 'class="form-control" id="edit_security_code" maxlength="4" inputmode="numeric" pattern="[0-9]*" placeholder="***"', 'text', false); ?>
                  <small class="form-text text-muted"><?php echo TEXT_EDIT_CARD_SECURITY_CODE_HELP; ?></small>
                </div>
              </div>

              <div class="col-12">
                <fieldset class="saved-card-editor__billing">
                  <legend class="form-label"><?php echo TEXT_EDIT_CARD_BILLING_CHOICE; ?></legend>

                  <?php
                    $addressModeExisting = ($edit_form_values['address_mode'] ?? '') !== 'new';
                    $existingId = 'address_mode_existing';
                    $newId = 'address_mode_new';
                  ?>

                  <div class="form-check">
                    <?php
                      $existingAttributes = 'id="' . $existingId . '" class="form-check-input" data-address-toggle="existing"';
                      if (empty($address_book_options)) {
                          $existingAttributes .= ' disabled="disabled"';
                      }
                      echo zen_draw_radio_field('address_mode', 'existing', $addressModeExisting, $existingAttributes);
                    ?>
                    <label class="form-check-label" for="<?php echo $existingId; ?>"><?php echo TEXT_EDIT_CARD_USE_EXISTING_ADDRESS; ?></label>
                  </div>

                  <div class="saved-card-editor__address saved-card-editor__address--existing <?php echo $addressModeExisting ? '' : 'd-none'; ?>" data-address-target="existing">
                    <?php if (!empty($address_book_options)) { ?>
                      <?php
                        $addressOptions = [];
                        foreach ($address_book_options as $option) {
                            $addressOptions[] = ['id' => $option['id'], 'text' => $option['label']];
                        }
                      ?>
                      <div class="mt-3">
                        <label class="form-label" for="edit_address_book_id"><?php echo TEXT_EDIT_CARD_ADDRESS_BOOK_SELECT; ?></label>
                        <?php echo zen_draw_pull_down_menu('address_book_id', $addressOptions, $edit_form_values['address_book_id'] ?? '', 'class="form-select" id="edit_address_book_id"'); ?>
                      </div>
                    <?php } else { ?>
                      <p class="text-muted mt-3 mb-0"><?php echo TEXT_EDIT_CARD_NO_ADDRESS_BOOK; ?></p>
                    <?php } ?>
                  </div>

                  <div class="form-check mt-3">
                    <?php echo zen_draw_radio_field('address_mode', 'new', ($edit_form_values['address_mode'] ?? '') === 'new', 'id="' . $newId . '" class="form-check-input" data-address-toggle="new"'); ?>
                    <label class="form-check-label" for="<?php echo $newId; ?>"><?php echo TEXT_EDIT_CARD_USE_NEW_ADDRESS; ?></label>
                  </div>

                  <div class="saved-card-editor__address saved-card-editor__address--new mt-3 <?php echo ($edit_form_values['address_mode'] ?? '') === 'new' ? '' : 'd-none'; ?>" data-address-target="new">
                    <div class="row g-3">
                      <div class="col-12">
                        <label class="form-label" for="edit_new_street_address"><?php echo TEXT_EDIT_CARD_NEW_STREET_ADDRESS; ?></label>
                        <?php echo zen_draw_input_field('new_street_address', $edit_form_values['new_address']['street_address'] ?? '', 'class="form-control" id="edit_new_street_address"'); ?>
                      </div>
                      <div class="col-12">
                        <label class="form-label" for="edit_new_street_address_2"><?php echo TEXT_EDIT_CARD_NEW_STREET_ADDRESS_2; ?></label>
                        <?php echo zen_draw_input_field('new_street_address_2', $edit_form_values['new_address']['street_address_2'] ?? '', 'class="form-control" id="edit_new_street_address_2"'); ?>
                      </div>
                      <div class="col-12 col-md-6">
                        <label class="form-label" for="edit_new_city"><?php echo TEXT_EDIT_CARD_NEW_CITY; ?></label>
                        <?php echo zen_draw_input_field('new_city', $edit_form_values['new_address']['city'] ?? '', 'class="form-control" id="edit_new_city"'); ?>
                      </div>
                      <div class="col-12 col-md-6">
                        <label class="form-label" for="edit_new_state"><?php echo TEXT_EDIT_CARD_NEW_STATE; ?></label>
                        <?php echo zen_draw_input_field('new_state', $edit_form_values['new_address']['state'] ?? '', 'class="form-control" id="edit_new_state"'); ?>
                      </div>
                      <div class="col-12 col-md-6">
                        <label class="form-label" for="edit_new_postcode"><?php echo TEXT_EDIT_CARD_NEW_POSTCODE; ?></label>
                        <?php echo zen_draw_input_field('new_postcode', $edit_form_values['new_address']['postcode'] ?? '', 'class="form-control" id="edit_new_postcode"'); ?>
                      </div>
                      <div class="col-12 col-md-6">
                        <label class="form-label" for="edit_new_country"><?php echo TEXT_EDIT_CARD_NEW_COUNTRY; ?></label>
                        <?php echo zen_draw_pull_down_menu('new_country_id', $country_dropdown, $edit_form_values['new_address']['country_id'] ?? '', 'class="form-select" id="edit_new_country"'); ?>
                      </div>
                    </div>
                  </div>
                </fieldset>
              </div>
            </div>

            <div class="d-flex flex-wrap gap-2 mt-4">
              <button type="submit" class="btn btn-primary"><?php echo TEXT_EDIT_CARD_SUBMIT_BUTTON; ?></button>
              <a class="btn btn-outline-secondary" href="<?php echo zen_href_link(FILENAME_ACCOUNT_SAVED_CREDIT_CARDS, '', 'SSL'); ?>"><?php echo TEXT_EDIT_CARD_CANCEL_BUTTON; ?></a>
            </div>
          </form>
        </div>
      </section>
    <?php } ?>

    <?php if ($add_card_mode === true) { ?>
      <script>
        // Expose PayPal client ID for JavaScript
        window.PAYPAL_CLIENT_ID = '<?php echo MODULE_PAYMENT_PAYPALR_SERVER === "live" ? MODULE_PAYMENT_PAYPALR_CLIENTID_L : MODULE_PAYMENT_PAYPALR_CLIENTID_S; ?>';
      </script>
      <section class="saved-cards-add-form card mb-4">
        <div class="card-body">
          <h2 class="card-title h4 mb-3"><?php echo HEADING_TITLE_ADD_CARD; ?></h2>
          <p class="text-muted mb-4"><?php echo TEXT_ADD_CARD_INTRO; ?></p>

          <?php if (!empty($add_card_errors)) { ?>
            <div class="alert alert-danger" role="alert">
              <ul class="mb-0">
                <?php foreach ($add_card_errors as $errorMessage) { ?>
                  <li><?php echo zen_output_string_protected($errorMessage); ?></li>
                <?php } ?>
              </ul>
            </div>
          <?php } ?>

          <?php echo zen_draw_form('add_card', zen_href_link(FILENAME_ACCOUNT_SAVED_CREDIT_CARDS, '', 'SSL'), 'post', 'class="saved-card__form" id="add-card-form"'); ?>
            <?php echo zen_draw_hidden_field('action', 'add-card'); ?>
            <?php echo zen_draw_hidden_field('setup_token_id', '', 'id="setup_token_id"'); ?>
            <?php echo zen_draw_hidden_field('securityToken', $_SESSION['securityToken']); ?>

            <div class="row g-3">
              <div class="col-12">
                <fieldset class="saved-card-add__billing">
                  <legend class="form-label"><?php echo TEXT_ADD_CARD_BILLING_ADDRESS; ?></legend>

                  <?php
                    $addressModeExisting = (count($address_book_options) > 0);
                    $existingId = 'add_address_mode_existing';
                    $newId = 'add_address_mode_new';
                  ?>

                  <div class="form-check">
                    <?php
                      $existingAttributes = 'id="' . $existingId . '" class="form-check-input" data-add-address-toggle="existing"';
                      if (empty($address_book_options)) {
                          $existingAttributes .= ' disabled="disabled"';
                      }
                      echo zen_draw_radio_field('add_address_mode', 'existing', $addressModeExisting, $existingAttributes);
                    ?>
                    <label class="form-check-label" for="<?php echo $existingId; ?>"><?php echo TEXT_ADD_CARD_USE_EXISTING_ADDRESS; ?></label>
                  </div>

                  <div class="saved-card-add__address saved-card-add__address--existing <?php echo $addressModeExisting ? '' : 'd-none'; ?>" data-add-address-target="existing">
                    <?php if (!empty($address_book_options)) { ?>
                      <?php
                        $addressOptions = [];
                        foreach ($address_book_options as $option) {
                            $addressOptions[] = ['id' => $option['id'], 'text' => $option['label']];
                        }
                      ?>
                      <div class="mt-3">
                        <label class="form-label" for="add_address_book_id"><?php echo TEXT_ADD_CARD_ADDRESS_BOOK_SELECT; ?></label>
                        <?php echo zen_draw_pull_down_menu('add_address_book_id', $addressOptions, '', 'class="form-select" id="add_address_book_id"'); ?>
                      </div>
                    <?php } else { ?>
                      <p class="text-muted mt-3 mb-0"><?php echo TEXT_ADD_CARD_NO_ADDRESS_BOOK; ?></p>
                    <?php } ?>
                  </div>

                  <div class="form-check mt-3">
                    <?php echo zen_draw_radio_field('add_address_mode', 'new', !$addressModeExisting, 'id="' . $newId . '" class="form-check-input" data-add-address-toggle="new"'); ?>
                    <label class="form-check-label" for="<?php echo $newId; ?>"><?php echo TEXT_ADD_CARD_USE_NEW_ADDRESS; ?></label>
                  </div>

                  <div class="saved-card-add__address saved-card-add__address--new mt-3 <?php echo !$addressModeExisting ? '' : 'd-none'; ?>" data-add-address-target="new">
                    <div class="row g-3">
                      <div class="col-12">
                        <label class="form-label" for="add_new_street_address"><?php echo TEXT_ADD_CARD_NEW_STREET_ADDRESS; ?></label>
                        <?php echo zen_draw_input_field('add_new_street_address', '', 'class="form-control" id="add_new_street_address"'); ?>
                      </div>
                      <div class="col-12">
                        <label class="form-label" for="add_new_street_address_2"><?php echo TEXT_ADD_CARD_NEW_STREET_ADDRESS_2; ?></label>
                        <?php echo zen_draw_input_field('add_new_street_address_2', '', 'class="form-control" id="add_new_street_address_2"'); ?>
                      </div>
                      <div class="col-12 col-md-6">
                        <label class="form-label" for="add_new_city"><?php echo TEXT_ADD_CARD_NEW_CITY; ?></label>
                        <?php echo zen_draw_input_field('add_new_city', '', 'class="form-control" id="add_new_city"'); ?>
                      </div>
                      <div class="col-12 col-md-6">
                        <label class="form-label" for="add_new_state"><?php echo TEXT_ADD_CARD_NEW_STATE; ?></label>
                        <?php echo zen_draw_input_field('add_new_state', '', 'class="form-control" id="add_new_state"'); ?>
                      </div>
                      <div class="col-12 col-md-6">
                        <label class="form-label" for="add_new_postcode"><?php echo TEXT_ADD_CARD_NEW_POSTCODE; ?></label>
                        <?php echo zen_draw_input_field('add_new_postcode', '', 'class="form-control" id="add_new_postcode"'); ?>
                      </div>
                      <div class="col-12 col-md-6">
                        <label class="form-label" for="add_new_country"><?php echo TEXT_ADD_CARD_NEW_COUNTRY; ?></label>
                        <select name="add_new_country_id" class="form-select" id="add_new_country">
                          <?php foreach ($country_dropdown as $country) { ?>
                            <option value="<?php echo $country['id']; ?>" data-iso2="<?php echo zen_output_string_protected($country['iso2']); ?>">
                              <?php echo $country['text']; ?>
                            </option>
                          <?php } ?>
                        </select>
                      </div>
                    </div>
                  </div>
                </fieldset>
              </div>

              <div class="col-12">
                <div id="card-fields-container" class="mb-3">
                  <!-- PayPal Advanced Card Fields will be inserted here by JavaScript -->
                  <div class="alert alert-info">
                    <span id="card-fields-loading"><?php echo TEXT_ADD_CARD_PROCESSING; ?></span>
                  </div>
                </div>
              </div>
            </div>

            <div class="d-flex flex-wrap gap-2 mt-4">
              <button type="submit" class="btn btn-primary" id="submit-card-btn" disabled><?php echo TEXT_ADD_CARD_SUBMIT_BUTTON; ?></button>
              <a class="btn btn-outline-secondary" href="<?php echo zen_href_link(FILENAME_ACCOUNT_SAVED_CREDIT_CARDS, '', 'SSL'); ?>"><?php echo TEXT_ADD_CARD_CANCEL_BUTTON; ?></a>
            </div>
          </form>
        </div>
      </section>
    <?php } ?>

    <?php if ($delete_card === null && $edit_card === null && $add_card_mode === false) { ?>
      <div class="mb-4">
        <a class="btn btn-primary" href="<?php echo zen_href_link(FILENAME_ACCOUNT_SAVED_CREDIT_CARDS, 'add=1', 'SSL'); ?>">
          <?php echo TEXT_ADD_CARD_BUTTON; ?>
        </a>
      </div>
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
                <?php if (($card['source'] ?? 'vault') === 'payflow') { ?>
                  <small class="text-muted ms-2" title="Legacy Payflow card">(Payflow)</small>
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
              <?php if (!empty($card['edit_href'])) { ?>
                <a class="btn btn-outline-primary" href="<?php echo zen_output_string_protected($card['edit_href']); ?>">
                  <?php echo TEXT_SAVED_CARD_EDIT_BUTTON; ?>
                </a>
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
