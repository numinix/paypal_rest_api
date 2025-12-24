  <?php
    if (zen_count_shipping_modules() > 0) {
      if ($free_shipping == true) {
  ?>
        <div id="freeShip" class="nmx-box important">
          <?php echo FREE_SHIPPING_TITLE; ?> <?php echo $quotes[$i]['icon']; ?>
        </div>
        <div class="nmx-box" id="defaultSelected">
          <?php echo sprintf(FREE_SHIPPING_DESCRIPTION, $currencies->format(MODULE_ORDER_TOTAL_SHIPPING_FREE_SHIPPING_OVER)) . zen_draw_hidden_field('shipping', 'free_free'); ?>
        </div>
  <?php
      } else {
        $radio_buttons = 0;
        for ($i=0, $n=sizeof($quotes); $i<$n; $i++) {

        if ( OPRC_SHIPPING_INFO == 'true' && isset($quotes[$i]['info']) && $quotes[$i]['info'] != '' ) {
          $toggleShipping = 'is-clickable';
        } else {
          $toggleShipping = '';
        }
  ?>
        <div class="shipping-method cf">
          <?php if(OPRC_SHOW_SHIPPING_METHOD_GROUP == 'true') { ?>
            <h4 class="<?php echo $toggleShipping ?>">
              <?php echo $quotes[$i]['module']; ?> 
              <?php if (isset($quotes[$i]['icon']) && zen_not_null($quotes[$i]['icon'])) { 
                echo $quotes[$i]['icon'];
              } ?>
            </h4>
          <?php } ?>
          <?php
            if (OPRC_SHIPPING_INFO == 'true' && isset($quotes[$i]['info']) && $quotes[$i]['info'] != '') {
              if(OPRC_SHOW_SHIPPING_METHOD_GROUP != 'true') echo '<h4 class="is-clickable">'.HEADING_SHIPPING_INFO.'</h4>';
              echo '<p class="information">' . $quotes[$i]['info'] . '</p>';
            }
          ?>
  <?php
          if (isset($quotes[$i]['error'])) {
  ?>
          <div><?php echo $quotes[$i]['error']; ?></div>
  <?php
          } else {
            for ($j=0, $n2=sizeof($quotes[$i]['methods']); $j<$n2; $j++) {
              // set the radio button to be checked if it is the method chosen
              $checked = (($quotes[$i]['id'] . '_' . $quotes[$i]['methods'][$j]['id'] == $_SESSION['shipping']['id']) ? true : false);
              if ( ($checked == true) || ($n == 1 && $n2 == 1) ) {
                // perform something
              }
?>
          <div class="custom-control custom-radio">
              <?php echo zen_draw_radio_field('shipping', $quotes[$i]['id'] . '_' . $quotes[$i]['methods'][$j]['id'], $checked, 'onclick="updateForm();" id="ship-'.$quotes[$i]['id'] . '-' . $quotes[$i]['methods'][$j]['id'].'"'); ?>
              <label for="<?php echo 'ship-'.$quotes[$i]['id'] . '-' . $quotes[$i]['methods'][$j]['id'].''; ?>">
                <?php echo $quotes[$i]['methods'][$j]['title']; ?></span>
<?php
              if ( ($n > 1) || ($n2 > 1) ) {
  ?>
                <span class="shipping-price"><span>&mdash;</span> <?php echo $currencies->format(zen_add_tax($quotes[$i]['methods'][$j]['cost'], (isset($quotes[$i]['tax']) ? $quotes[$i]['tax'] : 0))); ?></span>
  <?php
              } else {
  ?>
                <span class="shipping-price"><span>&mdash;</span> <?php echo $currencies->format(zen_add_tax($quotes[$i]['methods'][$j]['cost'], $quotes[$i]['tax'])) . zen_draw_hidden_field('shipping', $quotes[$i]['id'] . '_' . $quotes[$i]['methods'][$j]['id']); ?></span>
  <?php
              } ?>
            </label>
          </div>
<?php

              $radio_buttons++;
              }
          }
    ?>
        </div>
    <?php
        }
      }
    } elseif ($_SESSION['shipping'] != 'free_free') {
  ?>
      <h3 id="checkoutShippingHeadingMethod"><?php echo TITLE_NO_SHIPPING_AVAILABLE; ?></h3>
      <div id="checkoutShippingContentChoose" class="important"><?php echo TEXT_NO_SHIPPING_AVAILABLE; ?></div>
  <?php
    }
  ?>