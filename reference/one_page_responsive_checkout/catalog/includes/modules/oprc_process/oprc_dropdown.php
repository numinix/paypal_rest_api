<?php
  // BEGIN OPRC v1.24a DROP DOWN
  if (OPRC_DROP_DOWN == 'true') {
    if (zen_not_null($_POST['dropdown'])) {
      $_SESSION['dropdown'] = zen_db_prepare_input($_POST['dropdown']);
    }
    $dropdown = $_SESSION['dropdown'];
  }
  if (OPRC_GIFT_MESSAGE == 'true') {
    if (zen_not_null($_POST['gift-message'])) {
      $_SESSION['gift-message'] = zen_db_prepare_input($_POST['gift-message']);
    }
    $gift_message = $_SESSION['gift-message'];
  }
  // END DROP DOWN
?>