<?php
  $selection =  $order_total_modules->credit_selection();
  $numselection = sizeof($selection);
  if ($numselection>0) {
?>
<div id="discountsContainer" class="nmx-box">
<!-- bof Gift Wrap -->
<?php
  $gift_wrap_switch = false; 
  if (OPRC_GIFT_WRAPPING_SWITCH == 'true') {
    if (OPRC_STACKED == 'true') {
      echo '<div class="nmx-box"></div>';
    } else {
    }
    if (!file_exists(DIR_WS_MODULES . "order_total/ot_giftwrap_checkout.php")) {
      echo '<font color="red"><strong>GIFTWRAP MODULE NOT INSTALLED, PLEASE DISABLE IN CONFIGURATION</strong></font>';
    } else {
      $gift_wrap_switch = true;     
?>

<script type="text/javascript"><!--//
  function showGWChoices(id, id2) { // This gets executed when the user clicks on the checkbox
    var obj = document.getElementById(id);
    var obj2 = document.getElementById(id2);
    if (obj.checked) { 
      obj2.style.display= "block";
    } else {
      obj2.style.display = "none";
    }
  }
//--></script>
<?php
   $value = "ot_giftwrap_checkout.php"; 
   include_once(zen_get_file_directory(DIR_WS_LANGUAGES . $_SESSION['language'] .
          '/modules/order_total/', $value, 'false'));
   include_once(DIR_WS_MODULES . "order_total/" . $value);
   $wrap_mod = new ot_giftwrap_checkout(); 
   $use_gift_wrap = true;
   if ($wrap_mod->check()) {
?>
    <div id="gift_wrap" class="nmx-box">
    <h3><?php echo GIFT_WRAP_HEADING; ?></h3>
    <div class="boxContents">
<?php
    echo '<div id="cartWrapExplain">'; 
    echo '<a href="javascript:alert(\'' . GIFT_WRAP_EXPLAIN_DETAILS . '\')">' . GIFT_WRAP_EXPLAIN_LINK . '</a>';
    echo '</div>'; 
?>
      <table border="0" width="100%" cellspacing="0" cellpadding="0" id="cartContentsDisplay">
        <tr class="cartTableHeading">
        <th scope="col" id="ccProductsHeading"><?php echo TABLE_HEADING_PRODUCTS; ?></th>
         <th scope="col" id="ccWrapHeading"><?php echo GIFT_WRAP_CHECKOFF; ?></th>
        </tr>
<?php  
       // now loop thru all products to display quantity and price
   $prod_count = 1; 
   for ($i=0, $n=sizeof($order->products); $i<$n; $i++) {
     for ($q = 1; $q <= $order->products[$i]['qty']; $q++) {
        if ($prod_count%2 == 0) {
           echo '<tr class="rowEven">';
        } else {
           echo '<tr class="rowOdd">';
        }
        echo '<td class="cartProductDisplay">' . $order->products[$i]['name'];

        // if there are attributes, loop thru them and display one per line
        if (isset($order->products[$i]['attributes']) && sizeof($order->products[$i]['attributes']) > 0 ) {
            echo '<ul class="cartAttribsList">';
            for ($j=0, $n2=sizeof($order->products[$i]['attributes']); $j<$n2; $j++) {
                echo '<li>' . $order->products[$i]['attributes'][$j]['option'] . ': ' . nl2br($order->products[$i]['attributes'][$j]['value']) . '</li>'; 
            } // end loop
            echo '</ul>';
        } // endif attribute-info

        echo '</td>'; 
        // gift wrap setting
        echo '<td class="cartWrapCheckDisplay">'; 
        $prid = $order->products[$i]['id'];
        if (zen_get_products_virtual($order->products[$i]['id'])) {
           echo GIFT_WRAP_NA;
        } else if (DOWNLOAD_ENABLED && product_attributes_downloads_status($order->products[$i]['id'], $order->products[$i]['attributes'])) {
           echo GIFT_WRAP_NA; 
        } else if ($wrap_mod->exclude_product($prid)) {
           echo GIFT_WRAP_NA; 
        } else if ($wrap_mod->exclude_category($prid)) {
           echo GIFT_WRAP_NA; 
        } else { 
           $gift_id = "wrap_prod_" . $prod_count;
           if ($wrapconfig != 'Checkbox') {
             echo zen_draw_checkbox_field($gift_id, '', (isset($_SESSION['wrapsettings'][$prid][$q]) && $_SESSION['wrapsettings'][$prid][$q]['setting'] != 0 ? true : false), 'id="'.$gift_id .'" onclick=\'showGWChoices("wrap_prod_' . $prod_count . '", "slider_' . $prod_count . '")\'');
           } else {
             echo zen_draw_checkbox_field($gift_id, '', (isset($_SESSION['wrapsettings'][$prid][$q]) && $_SESSION['wrapsettings'][$prid][$q]['setting'] != 0 ? true : false), 'id="'.$gift_id .'" onclick="updateForm();"');
           }
        }
        echo "</td>"; 
        echo "</tr>"; 
        // Add in the wrapping paper images
        if ($prod_count%2 == 0) {
             echo '<tr class="rowEven" ';
        } else { 
             echo '<tr class="rowOdd" ';
        }
        echo ' id="wrap_sel_' . $prod_count . '">';
        echo '<td colspan="2" class="paperRow">';
        echo "\n"; 
        echo '<div id="slider_' . $prod_count . '" style="display:none;">' . "\n";
        $count = 0; 
        if ($wrapconfig == "Images") { 
           foreach ($papername as $paper) { 
              echo '<span class="wrapImageBox">';
              echo '<img src="' .GIFTWRAP_IMAGE_DIR . $paper . '" height="' . GIFTWRAP_IMAGE_HEIGHT . '" width="' . GIFTWRAP_IMAGE_WIDTH. '" />';
              echo zen_draw_radio_field('wrapping_paper_'.$prod_count, $paper, ($count==0), 'onclick="updateForm();"');
              $count++; 
              echo '&nbsp;&nbsp;';
              echo '</span>';
           }
        } else if ($wrapconfig == "Descriptions") { 
           foreach ($wrap_selections as $paper) { 
              echo '<span class="wrapImageBox">';
              echo $paper; 
              echo zen_draw_radio_field('wrapping_paper_'.$prod_count, $paper, ($count==0), 'onclick="updateForm();"');
              $count++; 
              echo '&nbsp;&nbsp;';
              echo '</span>';
           }
        }
        echo '</div>'; 
        echo '</td>'; 
        echo '</tr>'; 
        $prod_count++; 
     }
   }  // end for loopthru all products 
?>
      </table>
    </div>
  </div>
<?php
      }
    }
  }  
?>
<!-- eof Gift Wrap -->
<?php
    for ($i=0, $n=sizeof($selection); $i<$n; $i++) {
      if (OPRC_STACKED == 'true') {
        echo '';
      } else {
        echo '';
      }
      echo '<div class="nmx-box">';
      if ($_GET['credit_class_error_code'] == $selection[$i]['id']) {
?>
<div class="disablejAlert"><div class="messageStackError"><?php echo zen_output_string_protected($_GET['credit_class_error']); ?></div></div>

<?php
      }
      for ($j=0, $n2=sizeof($selection[$i]['fields']); $j<$n2; $j++) {
?>

<?php if(!($COWOA && $selection[$i]['module']==MODULE_ORDER_TOTAL_GV_TITLE)) {?>

  <?php 
    $continue_discount = true;
    if ( (($selection[$i]['module']) == MODULE_ORDER_TOTAL_INSURANCE_TITLE) && ($order->content_type == 'virtual') ) { 
      $continue_discount = false;
      $_SESSION['insurance'] = $_SESSION['opt_insurance'] = '0';
    }
    if ($continue_discount == true) {
  ?>
  <div id="discountForm<?php echo $selection[$i]['id']; ?>">
    <div class="discount">
      <?php 
        if (OPRC_COLLAPSE_DISCOUNTS == 'true') {
          $isClickable = 'is-clickable';
        } 
        if (OPRC_EXPAND_GC == 'true' && $selection[$i]['id'] == 'ot_gv' && isset($_SESSION['customer_id']) && zen_user_has_gv_account($_SESSION['customer_id']) > 0) {
          $isClickable = '';
        }
      ?>
      <h3 class="<?php echo $isClickable ?>"><?php echo $selection[$i]['module']; ?></h3>
      <div class="boxContents">
        <?php
          if ($messageStack->size($selection[$i]['id']) > 0) {
            echo '<div class="disablejAlert">';
            echo $messageStack->output($selection[$i]['id']);
            echo '</div>';
          }
        ?>      
        <div><?php echo $selection[$i]['redeem_instructions']; ?></div>
        <?php if ($selection[$i]['checkbox'] != ""): ?>
        <div class="gvBal">
            <?php echo $selection[$i]['checkbox']; ?>
            <div class="buttonRow updateButton forward"><?php echo (OPRC_CSS_BUTTONS == 'false' ? zen_image_button(BUTTON_IMAGE_UPDATE, BUTTON_APPLY_ALT) : zenCssButton(BUTTON_IMAGE_UPDATE, BUTTON_APPLY_ALT, 'button')); ?></div>
        </div>
        <?php endif ?>

        <div class="gvBal">
            <label class="inputLabel"<?php echo ($selection[$i]['fields'][$j]['tag']) ? ' for="'.$selection[$i]['fields'][$j]['tag'].'"': ''; ?>><?php echo $selection[$i]['fields'][$j]['title']; ?></label>        
            <div class="discount__group">
              <?php echo $selection[$i]['fields'][$j]['field']; ?>
              <div class="buttonRow updateButton forward"><?php echo (OPRC_CSS_BUTTONS == 'false' ? zen_image_button(BUTTON_IMAGE_UPDATE, BUTTON_APPLY_ALT) : zenCssButton(BUTTON_IMAGE_UPDATE, BUTTON_APPLY_ALT, 'button')); ?></div>
            </div>
        </div>

      </div>
    </div>
  </div>  
  <?php } ?>
  
  
<?php   }
      }
      echo '</div>';
    }
?>
</div>
<?php
    }
?>
