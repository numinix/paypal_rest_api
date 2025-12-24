<?php

// BEGIN OPRC v1.24a DROP DOWN
if (OPRC_DROP_DOWN == 'true') {
    if (isset($_POST['dropdown']) && zen_not_null($_POST['dropdown'])) {
        $_SESSION['dropdown'] = zen_db_prepare_input($_POST['dropdown']);
    }
    $dropdown = isset($_SESSION['dropdown']) ? $_SESSION['dropdown'] : null;
}
if (OPRC_GIFT_MESSAGE == 'true') {
    if (isset($_POST['gift-message']) && zen_not_null($_POST['gift-message'])) {
        $_SESSION['gift-message'] = zen_db_prepare_input($_POST['gift-message']);
    }
    $gift_message = isset($_SESSION['gift-message']) ? $_SESSION['gift-message'] : null;
}
// END DROP DOWN
