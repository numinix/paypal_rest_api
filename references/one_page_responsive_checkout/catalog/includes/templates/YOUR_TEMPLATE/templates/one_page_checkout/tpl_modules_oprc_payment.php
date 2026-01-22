<?php if ($_SESSION['cart']->count_contents() > 0 && isset($_SESSION['customer_id'])) {  ?>
<div id="paymentMethodContainer" class="columnInner">
<?php //if (isset($credit_covers) && !$credit_covers && (!isset($_SESSION['credit_covers']) || !$_SESSION['credit_covers'])) { ?>
  <h3><?php echo TABLE_HEADING_PAYMENT_METHOD; ?></h3>
  <div class="boxContents oprc-section-panel">
    <?php
      // GOOGLE CHECKOUT
      foreach($payment_modules->modules as $pm_code => $pm) {
        if(OPRC_GOOGLECHECKOUT_STATUS == 'true' && substr($pm, 0, strrpos($pm, '.')) == 'googlecheckout') {
          unset($payment_modules->modules[$pm_code]);
        }
        if(OPRC_PAYPAL_EXPRESS_STATUS == 'true' && substr($pm, 0, strrpos($pm, '.')) == 'paypalwpp') {
          unset($payment_modules->modules[$pm_code]);
        }
      }
    ?>
    <?php 
      if ($messageStack->size('checkout_payment') > 0) {
        echo '<div class="disablejAlert">';
        echo $messageStack->output('checkout_payment');
        echo '</div>';
      } 
    ?>
    <?php
      $selection = $payment_modules->selection();

      if (sizeof($selection) > 1) {
    ?>
    <!--p class="important"><?php echo TEXT_SELECT_PAYMENT_METHOD; ?></p-->
    <?php
      } elseif (sizeof($selection) == 0) {
    ?>
    <p class="important"><?php echo TEXT_NO_PAYMENT_OPTIONS_AVAILABLE; ?></p>

    <?php
      }
    ?>
    <div id="paymentModules">
        <?php
          echo $payment_modules->javascript_validation();
          $radio_buttons = 0;
          for ($i=0, $n=sizeof($selection); $i<$n; $i++) {
        ?>
      <?php
        $paymentIdClass = preg_replace('/[^a-z0-9_-]/i', '', $selection[$i]['id']);
      ?>
      <?php
        $hasPaymentFields = (isset($selection[$i]['fields']) && is_array($selection[$i]['fields']));
        $isSelectedPayment = (isset($_SESSION['payment']) && $selection[$i]['id'] == $_SESSION['payment']);
        $paymentMethodClasses = array(
          'payment-method',
          'payment-method-item',
          'cf',
          $paymentIdClass
        );

        if ($hasPaymentFields) {
          $paymentMethodClasses[] = 'payment-method--has-form';
        }

        if ($isSelectedPayment) {
          $paymentMethodClasses[] = 'payment-method--selected';
        }

        $paymentMethodClassAttribute = implode(' ', array_filter($paymentMethodClasses));
      ?>
      <div class="<?php echo zen_output_string_protected($paymentMethodClassAttribute); ?>">
        <?php 
          if ($messageStack->size($selection[$i]['id']) > 0) {
            echo '<div class="disablejAlert">';
            echo $messageStack->output($selection[$i]['id']);
            echo '</div>';
          } 
        ?>
        <!-- custom-control custom-checkbox --> 
        <div class="custom-control <?php echo empty($selection[$i]['noradio']) ? 'custom-radio' : ''; ?>">
            
            <!-- radio input -->
            <?php
              if (empty($selection[$i]['noradio'])) {
                // auto check the first payment method if no method has been selected
                if (!isset($_SESSION['payment']) && $i == 0) $_SESSION['payment'] = $selection[$i]['id'];
                echo zen_draw_radio_field('payment', $selection[$i]['id'], ($selection[$i]['id'] == $_SESSION['payment'] ? true : false), 'id="pmt-'.$selection[$i]['id'].'"'); 
              } else {
                echo zen_draw_radio_field('payment', $selection[$i]['id'], ($selection[$i]['id'] == $_SESSION['payment'] ? true : false), 'id="pmt-'.$selection[$i]['id'].'" class="hiddenField"'); 
              }
            ?>
            <!-- end radio input -->
            
            <label class="payment-method-item-label" for="<?php echo 'pmt-'. $selection[$i]['id'] .''; ?>">
              <!-- method name -->
              <?php echo $selection[$i]['module']; ?>
              <!-- end method name -->

              <!-- flags -->
              <?php
                if (SHOW_ACCEPTED_CREDIT_CARDS != '0' && in_array($selection[$i]['id'], array('paypaldp','payflowpro','authorizenet','authorizenet_aim','authorizenet_cim','cc','braintree_api','moneriseselectplus'))) {
              ?>
                  <span id="creditcard-flags">
                  <?php
                      if (SHOW_ACCEPTED_CREDIT_CARDS == '1') {
                        echo TEXT_ACCEPTED_CREDIT_CARDS . zen_get_cc_enabled();
                      }
                      if (SHOW_ACCEPTED_CREDIT_CARDS == '2') {
                        echo TEXT_ACCEPTED_CREDIT_CARDS . zen_get_cc_enabled('IMAGE_');
                      }
                  ?>
                  </span>
              <?php } ?>
              <!-- end flags -->
            </label>
            <!-- label -->

        </div>
        <!-- end custom-control custom-checkbox -->

        <?php
            if (defined('MODULE_ORDER_TOTAL_COD_STATUS') && MODULE_ORDER_TOTAL_COD_STATUS == 'true' and $selection[$i]['id'] == 'cod') {
        ?>
        <div class="alert"><?php echo TEXT_INFO_COD_FEES; ?></div>
        <?php
            } else {
              // echo 'WRONG ' . $selection[$i]['id'];
        ?>
        <?php
            }
        ?>

        <?php
            if (isset($selection[$i]['error'])) {
        ?>
            <div><?php echo $selection[$i]['error']; ?></div>

        <?php
            } elseif (isset($selection[$i]['fields']) && is_array($selection[$i]['fields'])) {
        ?>
          <?php
            $layoutFields = [];
            $passThroughFields = [];
            foreach ($selection[$i]['fields'] as $field) {
              $fieldTitle = trim(strip_tags($field['title'] ?? ''));
              $fieldHtml = $field['field'] ?? '';
              $isScriptField = stripos($fieldHtml, '<script') !== false;
              $isHiddenField = stripos($fieldHtml, 'type="hidden"') !== false;

              if (($isScriptField || $isHiddenField) && $fieldTitle === '') {
                $passThroughFields[] = $field;
              } else {
                $layoutFields[] = $field;
              }
            }
          ?>

          <?php
            foreach ($passThroughFields as $field) {
              echo $field['field'];
            }
          ?>

          <?php if (!empty($layoutFields)) { ?>
          <!-- credit card form -->
          <div class="creditcard-form nmx-row">
            <?php
                  for ($j=0, $n2=sizeof($layoutFields); $j<$n2; $j++) {
                    $fieldTitle = $layoutFields[$j]['title'] ?? '';
                    $fieldHtml = $layoutFields[$j]['field'] ?? '';
                    $fieldTag = $layoutFields[$j]['tag'] ?? '';
                    $isCheckboxField = (stripos($fieldHtml, 'type="checkbox"') !== false);
                    $hasCustomCheckbox = (stripos($fieldHtml, 'custom-control') !== false);
                    $fieldColumnClass = $isCheckboxField ? 'nmx-col-12' : 'nmx-col-6';
            ?>
                <div class="<?php echo $fieldColumnClass; ?>">
                  <?php if ($isCheckboxField && !$hasCustomCheckbox) { ?>
                    <div class="custom-control custom-checkbox">
                      <?php echo $fieldHtml; ?>
                      <label class="custom-control-label checkboxLabel" <?php echo ($fieldTag !== '' ? 'for="' . $fieldTag . '"' : ''); ?>>
                        <?php echo $fieldTitle; ?>
                      </label>
                    </div>
                  <?php } else { ?>
                    <label <?php echo ($fieldTag !== '' ? 'for="' . $fieldTag . '" ' : ''); ?>><?php echo $fieldTitle; ?></label>
                    <?php echo $fieldHtml; ?>
                  <?php } ?>
                </div>
                
            <?php
                  }
            ?>
          </div>
          <!-- end credit card form -->
          <?php } ?>
        <?php
            }
        ?>
        <?php
            $radio_buttons++;
        ?>
      </div>
        <?php
          }
        ?>
    </div>
  </div>

  <?php //} ?>
</div>
<?php } ?>
