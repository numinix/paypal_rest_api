<?php

// BEGIN OPTIONAL CHECKBOX
if (OPRC_CHECKBOX == 'true') {
    if (isset($_POST['oprc_checkbox'])) {
        $_SESSION['oprc_checkbox'] = $_POST['oprc_checkbox'];
    }
}
// END OPTIONAL CHECKBOX
