<?php

// BEGIN OPRC v1.24a DROP DOWN
if (OPRC_DROP_DOWN == 'true') {
    // create list for dropdown
    $dropdown_list_array1 = explode(",", OPRC_DROP_DOWN_LIST);
    $dropdown_list_array = array();
    foreach ($dropdown_list_array1 as $key => $option) {
        $dropdown_list_array[] = array('id' => $option, 'text' => $option);
    }
    if (isset($_POST['dropdown'])) {
        $_SESSION['dropdown'] = zen_db_prepare_input($_POST['dropdown']);
    }
    $dropdown = isset($_SESSION['dropdown']) ? $_SESSION['dropdown'] : null;
}
// END DROP DOWN
