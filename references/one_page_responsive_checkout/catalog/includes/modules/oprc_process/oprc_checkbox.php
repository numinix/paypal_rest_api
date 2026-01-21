<?php

// BEGIN OPTIONAL CHECKBOX
if (OPRC_CHECKBOX == 'true') {
    if (zen_not_null($_POST['oprc_checkbox'])) {
        $_SESSION['oprc_checkbox'] = $_POST['oprc_checkbox'];
    } else {
        unset($_SESSION['oprc_checkbox']);
    }
}
// END OPTIONAL CHECKBOX
