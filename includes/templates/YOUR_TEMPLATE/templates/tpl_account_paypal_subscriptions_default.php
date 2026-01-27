<?php
/**
 * Customer subscription management page template.
 */
?>
<div class="centerColumn account-page" id="paypalSubscriptionsDefault">
  <header class="page-header">
    <h1><?php echo HEADING_TITLE; ?></h1>
  </header>

  <?php require $template->get_template_dir('tpl_modules_account_menu.php', DIR_WS_TEMPLATE, $current_page_base, 'templates') . '/tpl_modules_account_menu.php'; ?>

  <?php if ($paypal_subscriptions_message !== '') { ?>
    <?php
      $alertClass = 'alert-info';
      if ($paypal_subscriptions_message_type === 'success') {
        $alertClass = 'alert-success';
      } elseif ($paypal_subscriptions_message_type === 'error') {
        $alertClass = 'alert-danger';
      } elseif ($paypal_subscriptions_message_type === 'warning') {
        $alertClass = 'alert-warning';
      }
    ?>
    <div class="messageStack-header noprint">
      <div class="row messageStackAlert alert <?php echo $alertClass; ?>"><?php echo zen_output_string_protected($paypal_subscriptions_message); ?></div>
    </div>
  <?php } ?>

  <?php if ($hide_paypal_subscriptions_page === true) { ?>
    <div class="alert alert-info" role="status"><?php echo TEXT_SUBSCRIPTIONS_DISABLED; ?></div>
  <?php } else { ?>
    <p class="subscriptions-intro"><?php echo TEXT_SUBSCRIPTIONS_INTRO; ?></p>

    <?php if (empty($paypal_subscriptions)) { ?>
      <p class="subscriptions-empty lead"><?php echo TEXT_NO_SUBSCRIPTIONS; ?></p>
    <?php } else { ?>
      <div class="subscription-grid">
        <?php foreach ($paypal_subscriptions as $subscription) { ?>
          <article class="subscription-card <?php echo zen_output_string_protected($subscription['status_class']); ?>">
            <header class="subscription-card__header">
              <div>
                <h2 class="subscription-card__title h4 mb-1"><?php echo zen_output_string_protected($subscription['products_name']); ?></h2>
                <?php if (!empty($subscription['plan_id'])) { ?>
                  <div class="subscription-card__plan text-muted"><?php echo sprintf(TEXT_SUBSCRIPTION_PLAN_ID, zen_output_string_protected($subscription['plan_id'])); ?></div>
                <?php } ?>
              </div>
              <span class="subscription-card__status badge <?php echo zen_output_string_protected($subscription['status_class']); ?>"><?php echo zen_output_string_protected($subscription['status_label']); ?></span>
            </header>

            <div class="subscription-card__body">
              <dl class="subscription-card__meta">
                <div>
                  <dt><?php echo TEXT_SUBSCRIPTION_AMOUNT_LABEL; ?></dt>
                  <dd><?php echo zen_output_string_protected($subscription['amount']); ?></dd>
                </div>
                <?php if ($subscription['schedule'] !== '') { ?>
                  <div>
                    <dt><?php echo TEXT_SUBSCRIPTION_SCHEDULE_LABEL; ?></dt>
                    <dd><?php echo zen_output_string_protected($subscription['schedule']); ?></dd>
                  </div>
                <?php } ?>
                <?php if ($subscription['total_cycles'] !== '') { ?>
                  <div>
                    <dt><?php echo TEXT_SUBSCRIPTION_TOTAL_CYCLES_LABEL; ?></dt>
                    <dd><?php echo zen_output_string_protected($subscription['total_cycles']); ?></dd>
                  </div>
                <?php } ?>
                <?php if ($subscription['trial'] !== '') { ?>
                  <div>
                    <dt><?php echo TEXT_SUBSCRIPTION_TRIAL_LABEL; ?></dt>
                    <dd><?php echo zen_output_string_protected($subscription['trial']); ?></dd>
                  </div>
                <?php } ?>
                <?php if ($subscription['start_date'] !== '') { ?>
                  <div>
                    <dt><?php echo TEXT_SUBSCRIPTION_CREATED_LABEL; ?></dt>
                    <dd><?php echo zen_output_string_protected($subscription['start_date']); ?></dd>
                  </div>
                <?php } ?>
                <?php if ($subscription['last_modified'] !== '' && $subscription['last_modified'] !== $subscription['start_date']) { ?>
                  <div>
                    <dt><?php echo TEXT_SUBSCRIPTION_UPDATED_LABEL; ?></dt>
                    <dd><?php echo zen_output_string_protected($subscription['last_modified']); ?></dd>
                  </div>
                <?php } ?>
                <?php if (!empty($subscription['orders_id'])) { ?>
                  <div>
                    <dt><?php echo TEXT_SUBSCRIPTION_ORDER_LABEL; ?></dt>
                    <dd><?php echo sprintf(TEXT_SUBSCRIPTION_ORDER_VALUE, (int) $subscription['orders_id']); ?></dd>
                  </div>
                <?php } ?>
              </dl>
            </div>

            <section class="subscription-card__section">
              <h3 class="subscription-card__section-title"><?php echo TEXT_SUBSCRIPTION_PAYMENT_METHOD_HEADING; ?></h3>
              <div class="subscription-card__payment-summary">
                <span class="badge <?php echo zen_output_string_protected($subscription['vault_summary']['status_class']); ?>"><?php echo zen_output_string_protected($subscription['vault_summary']['status_label']); ?></span>
                <span class="subscription-card__payment-label"><?php echo zen_output_string_protected($subscription['vault_summary']['label']); ?></span>
              </div>

              <?php if (!empty($subscription['vault_options'])) { ?>
                <?php echo zen_draw_form('update_vault_' . (int) $subscription['paypal_subscription_id'], zen_href_link(FILENAME_ACCOUNT_PAYPAL_SUBSCRIPTIONS, '', 'SSL'), 'post', 'class="subscription-card__form"'); ?>
                  <?php echo zen_draw_hidden_field('action', 'update-vault'); ?>
                  <?php echo zen_draw_hidden_field('paypal_subscription_id', (int) $subscription['paypal_subscription_id']); ?>
                  <?php echo zen_draw_hidden_field('securityToken', $_SESSION['securityToken']); ?>

                  <label class="form-label" for="vault-select-<?php echo (int) $subscription['paypal_subscription_id']; ?>"><?php echo TEXT_SUBSCRIPTION_PAYMENT_METHOD_SELECT; ?></label>
                  <?php echo zen_draw_pull_down_menu('paypal_vault_id', $subscription['vault_options'], $subscription['vault_id'], 'class="form-select" id="vault-select-' . (int) $subscription['paypal_subscription_id'] . '"'); ?>

                  <button type="submit" class="btn btn-primary mt-3"><?php echo TEXT_SUBSCRIPTION_PAYMENT_METHOD_UPDATE_BUTTON; ?></button>
                </form>
              <?php } else { ?>
                <p class="text-muted mb-0"><?php echo TEXT_SUBSCRIPTION_PAYMENT_METHOD_NO_OPTIONS; ?>
                  <?php if ($paypal_subscriptions_manage_cards_url !== '') { ?>
                    <a href="<?php echo $paypal_subscriptions_manage_cards_url; ?>"><?php echo TEXT_SUBSCRIPTION_PAYMENT_METHOD_MANAGE_LINK; ?></a>
                  <?php } ?>
                </p>
              <?php } ?>
            </section>

            <?php if ($subscription['remote_details']['id'] !== '' || $subscription['remote_error'] !== '') { ?>
              <section class="subscription-card__section">
                <h3 class="subscription-card__section-title"><?php echo TEXT_SUBSCRIPTION_REMOTE_SECTION_HEADING; ?></h3>

                <?php if ($subscription['remote_error'] !== '') { ?>
                  <div class="alert alert-warning" role="alert"><?php echo zen_output_string_protected($subscription['remote_error']); ?></div>
                <?php } else { ?>
                  <dl class="subscription-card__remote">
                    <div>
                      <dt><?php echo TEXT_SUBSCRIPTION_REMOTE_ID; ?></dt>
                      <dd><?php echo zen_output_string_protected($subscription['remote_details']['id']); ?></dd>
                    </div>
                    <?php if ($subscription['remote_details']['status_label'] !== '') { ?>
                      <div>
                        <dt><?php echo TEXT_SUBSCRIPTION_REMOTE_STATUS; ?></dt>
                        <dd><?php echo zen_output_string_protected($subscription['remote_details']['status_label']); ?></dd>
                      </div>
                    <?php } ?>
                    <?php if ($subscription['remote_details']['next_billing'] !== '') { ?>
                      <div>
                        <dt><?php echo TEXT_SUBSCRIPTION_REMOTE_NEXT_BILLING; ?></dt>
                        <dd><?php echo zen_output_string_protected($subscription['remote_details']['next_billing']); ?></dd>
                      </div>
                    <?php } ?>
                    <?php if ($subscription['remote_details']['last_payment'] !== '') { ?>
                      <div>
                        <dt><?php echo TEXT_SUBSCRIPTION_REMOTE_LAST_PAYMENT; ?></dt>
                        <dd><?php echo zen_output_string_protected($subscription['remote_details']['last_payment']); ?></dd>
                      </div>
                    <?php } ?>
                    <?php if ($subscription['remote_details']['last_payment_amount'] !== '') { ?>
                      <div>
                        <dt><?php echo TEXT_SUBSCRIPTION_REMOTE_LAST_PAYMENT_AMOUNT; ?></dt>
                        <dd><?php echo zen_output_string_protected($subscription['remote_details']['last_payment_amount']); ?></dd>
                      </div>
                    <?php } ?>
                    <?php if ($subscription['remote_details']['cycle_summary'] !== '') { ?>
                      <div>
                        <dt><?php echo TEXT_SUBSCRIPTION_REMOTE_CYCLE_SUMMARY_HEADING; ?></dt>
                        <dd><?php echo zen_output_string_protected($subscription['remote_details']['cycle_summary']); ?></dd>
                      </div>
                    <?php } ?>
                  </dl>
                <?php } ?>
              </section>
            <?php } ?>

            <?php if (!empty($subscription['actions'])) { ?>
              <section class="subscription-card__section subscription-card__section--actions">
                <h3 class="subscription-card__section-title"><?php echo TEXT_SUBSCRIPTION_ACTIONS_HEADING; ?></h3>
                <div class="subscription-card__actions">
                  <?php foreach ($subscription['actions'] as $action) { ?>
                    <?php echo zen_draw_form('subscription_action_' . (int) $subscription['paypal_subscription_id'] . '_' . zen_output_string_protected($action['action']), zen_href_link(FILENAME_ACCOUNT_PAYPAL_SUBSCRIPTIONS, '', 'SSL'), 'post', 'class="subscription-card__action-form"'); ?>
                      <?php echo zen_draw_hidden_field('action', $action['action']); ?>
                      <?php echo zen_draw_hidden_field('paypal_subscription_id', (int) $subscription['paypal_subscription_id']); ?>
                      <?php echo zen_draw_hidden_field('securityToken', $_SESSION['securityToken']); ?>
                      <button type="submit" class="<?php echo zen_output_string_protected($action['button_class']); ?>" <?php if (!empty($action['confirm'])) { ?>onclick="return confirm('<?php echo zen_output_string_protected($action['confirm']); ?>');"<?php } ?>><?php echo zen_output_string_protected($action['label']); ?></button>
                    </form>
                  <?php } ?>
                </div>
              </section>
            <?php } ?>
          </article>
        <?php } ?>
      </div>
    <?php } ?>
  <?php } ?>
</div>
