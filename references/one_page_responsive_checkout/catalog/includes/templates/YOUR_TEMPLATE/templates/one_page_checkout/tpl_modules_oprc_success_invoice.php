<!-- bof invoice -->
<table border="1" width="100%" cellspacing="0" cellpadding="2">
  <tr>
    <td>
      <table border="0" width="100%" cellspacing="0" cellpadding="0">
        <tr>
          <td valign="top">
            <table border="0" cellspacing="2" cellpadding="0">
              <tr>
                <td class="invoiceHeading"><?php echo nl2br(STORE_NAME_ADDRESS); ?></td>
              </tr>
              <tr>
                <td><?php echo zen_draw_separator('pixel_trans.gif', '1', '10'); ?></td>
              </tr>
            </table>
          </td>
        </tr>
      </table>
    </td>
  </tr>
  <tr>
    <td class="pageHeading"><? echo HEADER_INVOICE . $orders_id; ?></td>
  </tr>
  <tr>
    <td><table width="100%" border="0" cellspacing="0" cellpadding="2">
      <tr>
        <td colspan="6"><?php echo zen_draw_separator(); ?></td>
      </tr>
      <tr>
        <td><?php echo zen_draw_separator('pixel_trans.gif', '23', '1'); ?></td>
        <td valign="top"><table width="100%" border="0" cellspacing="0" cellpadding="2">
          <tr>
            <td><?php echo zen_draw_separator('pixel_trans.gif', '1', '5'); ?></td>
          </tr>        
          <tr>
            <td class="main"><b><?php echo ENTRY_BILL_TO; ?></b></td>
          </tr>
          <tr>
            <td><?php echo zen_draw_separator('pixel_trans.gif', '1', '5'); ?></td>
          </tr>
          <tr>
            <td class="main"><?php echo zen_address_format($order->customer['format_id'], $order->billing, 1, '', '<br>'); ?></td>
          </tr>
          <tr>
            <td><?php echo zen_draw_separator('pixel_trans.gif', '1', '5'); ?></td>
          </tr>
          <tr>
            <td class="main"><?php echo $order->customer['telephone']; ?></td>
          </tr>
          <tr>
            <td class="main"><?php echo '<a href="mailto:' . $order->customer['email_address'] . '">' . $order->customer['email_address'] . '</a>'; ?></td>
          </tr>
        </table></td>
        <td><?php echo zen_draw_separator('pixel_trans.gif', '60', '1'); ?></td>
        <td valign="top"><table width="100%" border="0" cellspacing="0" cellpadding="2">
          <tr>
            <td><?php echo zen_draw_separator('pixel_trans.gif', '1', '5'); ?></td>
          </tr>
          <tr>
            <td class="main"><b><?php echo ENTRY_SHIP_TO; ?></b></td>
          </tr>
          <tr>
            <td><?php echo zen_draw_separator('pixel_trans.gif', '1', '5'); ?></td>
          </tr>
          <tr>
            <td class="main"><?php echo zen_address_format($order->delivery['format_id'], $order->delivery, 1, '', '<br>'); ?></td>
          </tr>
        </table></td>
      </tr>
    </table></td>
  </tr>
  <tr>
    <td><?php echo zen_draw_separator('pixel_trans.gif', '1', '10'); ?></td>
  </tr>
  <tr>
    <td><table border="0" cellspacing="0" cellpadding="2">
      <tr>
        <td class="main"><b><?php echo ENTRY_ORDER_ID . $orders_id; ?></b></td>
      </tr>
      <tr>
        <td class="main"><b><?php echo ENTRY_DATE_PURCHASED; ?></b></td>
        <td class="main"><?php echo zen_date_long($order->info['date_purchased']); ?></td>
      </tr>
      <tr>
        <td class="main"><b><?php echo ENTRY_PAYMENT_METHOD; ?></b></td>
        <td class="main"><?php echo $order->info['payment_method']; ?></td>
      </tr>
    </table></td>
  </tr>
  <tr>
    <td><?php echo zen_draw_separator('pixel_trans.gif', '1', '10'); ?></td>
  </tr>
  <tr>
    <td><table border="0" width="100%" cellspacing="0" cellpadding="2">
      <tr class="dataTableHeadingRow">
        <td class="dataTableHeadingContent" colspan="2"><?php echo TABLE_HEADING_PRODUCTS; ?></td>
        <td class="dataTableHeadingContent"><?php echo TABLE_HEADING_PRODUCTS_MODEL; ?></td>
        <td class="dataTableHeadingContent" align="right"><?php echo TABLE_HEADING_TAX; ?></td>
        <td class="dataTableHeadingContent" align="right"><?php echo TABLE_HEADING_PRICE_NO_TAX; ?></td>
        <td class="dataTableHeadingContent" align="right"><?php echo TABLE_HEADING_TOTAL_NO_TAX; ?></td>
      </tr>
<?php
    for ($i = 0, $n = sizeof($order->products); $i < $n; $i++) {
      echo '      <tr class="dataTableRow">' . "\n" .
           '        <td class="dataTableContent" valign="top" align="right">' . $order->products[$i]['qty'] . '&nbsp;x</td>' . "\n" .
           '        <td class="dataTableContent" valign="top">' . $order->products[$i]['name'];

      if (isset($order->products[$i]['attributes']) && (($k = sizeof($order->products[$i]['attributes'])) > 0)) {
        for ($j = 0; $j < $k; $j++) {
          echo '<br><nobr><small>&nbsp;<i> - ' . $order->products[$i]['attributes'][$j]['option'] . ': ' . $order->products[$i]['attributes'][$j]['value'];
          if ($order->products[$i]['attributes'][$j]['price'] != '0') echo ' (' . $order->products[$i]['attributes'][$j]['prefix'] . $currencies->format($order->products[$i]['attributes'][$j]['price'] * $order->products[$i]['qty'], true, $order->info['currency'], $order->info['currency_value']) . ')';
          if ($order->products[$i]['attributes'][$j]['product_attribute_is_free'] == '1' and $order->products[$i]['product_is_free'] == '1') echo TEXT_INFO_ATTRIBUTE_FREE;
          echo '</i></small></nobr>';
        }
      }

      echo '        </td>' . "\n" .
           '        <td class="dataTableContent" valign="top">' . $order->products[$i]['model'] . '</td>' . "\n";
      echo '        <td class="dataTableContent" align="right" valign="top">';
      if ($display_tax) {
        echo zen_display_tax_value($order->products[$i]['tax']) . '%';
      }
      else {
        echo ENTRY_NO_TAX;
      }
      echo '</td>' . "\n" ;
    if ($display_tax) {
      echo '        <td class="dataTableContent" align="right" valign="top"><b>' . $currencies->format($order->products[$i]['final_price'], true, $order->info['currency'], $order->info['currency_value']) . '</b></td>' . "\n" .
           '        <td class="dataTableContent" align="right" valign="top"><b>' . $currencies->format(zen_add_tax($order->products[$i]['final_price'], $order->products[$i]['tax']), true, $order->info['currency'], $order->info['currency_value']) . '</b></td>' . "\n" .
           '        <td class="dataTableContent" align="right" valign="top"><b>' . $currencies->format($order->products[$i]['final_price'] * $order->products[$i]['qty'], true, $order->info['currency'], $order->info['currency_value']) . '</b></td>' . "\n" .
           '        <td class="dataTableContent" align="right" valign="top"><b>' . $currencies->format(zen_add_tax($order->products[$i]['final_price'], $order->products[$i]['tax']) * $order->products[$i]['qty'], true, $order->info['currency'], $order->info['currency_value']) . '</b></td>' . "\n";
      echo '      </tr>' . "\n";
    }
    else {
      echo '        <td class="dataTableContent" align="right" valign="top"><b>' . $currencies->format($order->products[$i]['final_price'], true, $order->info['currency'], $order->info['currency_value']) . '</b></td>' . "\n" .
           '        <td class="dataTableContent" align="right" valign="top"><b>' . $currencies->format($order->products[$i]['final_price'] * $order->products[$i]['qty'], true, $order->info['currency'], $order->info['currency_value']) . '</b></td>' . "\n" .
           '      </tr>' . "\n";
    }
    }
?>
      <tr>
        <td align="right" colspan="8"><table border="0" cellspacing="0" cellpadding="2">
<?php
  for ($i = 0, $n = sizeof($order->totals); $i < $n; $i++) {
    if ($order->totals[$i]['class'] == 'ot_shipping') {
      $format_shipping = explode(" (", $order->totals[$i]['title'], 2);
      $clean_shipping = rtrim($format_shipping[0], ":");
      $display_title = $clean_shipping . ':';
    }
    else {
      $display_title = $order->totals[$i]['title'];
    }

    echo '          <tr>' . "\n" .
         '            <td align="right" class="'. str_replace('_', '-', $order->totals[$i]['class']) . '-Text">' . $display_title . '</td>' . "\n" .
         '            <td align="right" class="'. str_replace('_', '-', $order->totals[$i]['class']) . '-Amount">' . $order->totals[$i]['text'] . '</td>' . "\n" .
         '          </tr>' . "\n";
    $order_total = $order->totals[$i]['text'];
  }
?>
        </table></td>
      </tr>
    </table></td>
  </tr>
<?php if ($display_notes == 1) { ?>
  <tr>
    <td><?php echo zen_draw_separator('pixel_trans.gif', '1', '5'); ?></td>
  </tr>
  <tr>
    <td class="main" colspan="2"><b><?php echo HEADER_CUSTOMER_NOTES; ?></b></td>
  </tr>
  <tr>
    <td class="main" colspan="2"><?php echo $customer_notes; ?></td>
  </tr>
<?php } ?>  
</table>
<!-- eof invoice -->