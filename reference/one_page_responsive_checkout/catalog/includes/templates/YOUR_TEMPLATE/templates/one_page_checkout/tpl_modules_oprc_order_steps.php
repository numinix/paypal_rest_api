<?php

// login/registration = 1
// shipping/payment = 2
if (OPRC_HIDE_REGISTRATION == 'true') {
    $order_steps = array(
        1 => HEADING_STEP_1,
        2 => (
            (OPRC_SHIPPING_ADDRESS == 'false' || $_SESSION['cart']->get_content_type() == 'virtual')
                ? HEADING_STEP_2_NO_SHIPPING
                : HEADING_STEP_2
        ),
        3 => (
            (OPRC_SHIPPING_ADDRESS == 'false' || $_SESSION['cart']->get_content_type() == 'virtual')
                ? HEADING_STEP_3_NO_SHIPPING
                : HEADING_STEP_3
        ),
    );
} else {
    $order_steps = array(
        1 => (
            (OPRC_SHIPPING_ADDRESS == 'false' || $_SESSION['cart']->get_content_type() == 'virtual')
                ? HEADING_STEP_2_NO_SHIPPING
                : HEADING_STEP_2
        ),
        2 => (
            (OPRC_SHIPPING_ADDRESS == 'false' || $_SESSION['cart']->get_content_type() == 'virtual')
                ? HEADING_STEP_3_NO_SHIPPING
                : HEADING_STEP_3
        ),
    );
}

if (OPRC_ONE_PAGE != 'true' || $_GET['main_page'] == FILENAME_OPRC_CONFIRMATION) {
    $order_steps[] = HEADING_STEP_4;
}

$current_step = 1;
if (!isset($_SESSION['customer_id'])) {
    if (OPRC_HIDE_REGISTRATION == 'true') {
        if (!isset($_GET['hideregistration']) || $_GET['hideregistration'] != 'true') {
            $current_step = 1;
        } else {
            $current_step = 2;
        }
    } else {
        $current_step = 1;
    }
} else {
    if ($_GET['main_page'] == FILENAME_ONE_PAGE_CHECKOUT) {
        if (OPRC_HIDE_REGISTRATION == 'true') {
            $current_step = 3;
        } else {
            $current_step = 2;
        }
    } elseif ($_GET['main_page'] == FILENAME_OPRC_CONFIRMATION) {
        if (OPRC_HIDE_REGISTRATION == 'true') {
            $current_step = 4;
        } else {
            $current_step = 3;
        }
    }
}

echo '<div class="orderSteps">' . "\n";
$step_counter = 0;
foreach ($order_steps as $step => $title) {
    $step_counter++;
    $params = '';
    switch ($step) {
        case 2:
            if ($current_step != 2) {
                $params = 'step=2&hideregistration=true';
                if (isset($_GET['type']) && $_GET['type'] == 'cowoa') {
                    $params .= '#hideregistration_guest';
                } else {
                    $params .= '#hideregistration_register';
                }
            }
            break;
        case 3:
            // you shouldn't be allowed to go to this step
            break;
        case 4:
            // you cannot just go to this step
            break;
        case 1:
        default:
            if ($current_step != 1) {
                $params = 'step=1';
            }
            break;
    }
    $link = '<span title="' . strip_tags($title) . '">' . $step . '</span>';
    echo '<span class="orderStep' . ($step_counter == $current_step ? ' currentStep' : '') . '">' . $link . '</span>' . "\n";
}
echo '</div>';
