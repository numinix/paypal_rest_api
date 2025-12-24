<?php    
  // BOF GIFT WRAPPING
  if (OPRC_GIFT_WRAPPING_SWITCH == 'true') {  
    $wrapsettings = array(); 
    $prod_count = 1; 
    for ($i=0, $n=sizeof($order->products); $i<$n; $i++) {
       $prid = $order->products[$i]['id'];
       $wrapsettings[$prid] = array(); 
       for ($q = 1; $q <= $order->products[$i]['qty']; $q++) {
           $name = "wrap_prod_" . $prod_count; 
           $paper = "wrapping_paper_" . $prod_count; 
           if (isset($_POST[$name]) && ($_POST[$name] == 'on')) {
              $wrapsettings[$prid][$q]['setting'] = 1;  
              $wrapsettings[$prid][$q]['paper'] = $_POST[$paper];  
           } else {
              $wrapsettings[$prid][$q]['setting'] = 0;  
           }
           $prod_count++;  
       }
    }
    $_SESSION['wrapsettings'] = $wrapsettings;
  }
  // EOF GIFT WRAPPING
?>