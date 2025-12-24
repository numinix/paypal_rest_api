<?php
global $messageStack;

if (!function_exists('oprc_get_constant_value')) {
    function oprc_get_constant_value($constantName, $default = '')
    {
        return defined($constantName) ? constant($constantName) : $default;
    }
}

// Column configuration
$columns = '';
if ($_SESSION['cart']->get_content_type() == 'virtual') {
    $columns = '2';
    $widthClass = 'width50';
} else {
    $widthClass = 'width33';
}

if (OPRC_ONE_PAGE == 'true') {
    $title_checkout = TITLE_CONFIRM_CHECKOUT;
    $checkout_procedure = TEXT_CONFIRM_CHECKOUT;
    $button = BUTTON_IMAGE_CONFIRM_ORDER;
    $button_alt = defined('TEXT_OPRC_COMPLETE_PURCHASE')
        ? TEXT_OPRC_COMPLETE_PURCHASE
        : (defined('BUTTON_CONFIRM_ORDER_ALT') ? BUTTON_CONFIRM_ORDER_ALT : BUTTON_CONTINUE_ALT);
    $button_class = 'button_confirm_checkout';
} else {
    $title_checkout = TITLE_CONTINUE_CHECKOUT_CONFIRMATION;
    $checkout_procedure = TEXT_CONTINUE_CHECKOUT_CONFIRMATION;
    $button = BUTTON_IMAGE_CONTINUE_CHECKOUT;
    $button_alt = BUTTON_CONTINUE_ALT;
    $button_class = 'button_continue_checkout';
}

$button_text = $button_alt;
?>

<div id="column1<?php echo $columns; ?>" class="oprc-step-columns">
    <div id="column2<?php echo $columns; ?>" class="oprc-step-columns__inner">
        <?php
        $checkoutLogoHtml = '';
        $logoAltText = defined('HEADER_ALT_TEXT') ? HEADER_ALT_TEXT : STORE_NAME;

        if (defined('OPRC_CHECKOUT_LOGO_SECURE_PATH') && trim(OPRC_CHECKOUT_LOGO_SECURE_PATH) !== '') {
            $checkoutLogoSrc = trim(OPRC_CHECKOUT_LOGO_SECURE_PATH);
        } else {
            $checkoutLogoSrc = $template->get_template_dir(HEADER_LOGO_IMAGE, DIR_WS_TEMPLATE, $current_page_base, 'images') . '/' . HEADER_LOGO_IMAGE;
        }

        if (!empty($checkoutLogoSrc)) {
            $checkoutLogoHtml = '<img class="oprc-mobile-header-logo__image" src="' . $checkoutLogoSrc . '" alt="' . zen_output_string_protected($logoAltText) . '" />';
        }
        ?>
        <?php if ($checkoutLogoHtml !== '') { ?>
            <div class="oprc-mobile-header-logo">
                <a class="oprc-mobile-header-logo__link" href="<?php echo zen_href_link(FILENAME_DEFAULT); ?>">
                    <?php echo $checkoutLogoHtml; ?>
                </a>
            </div>
        <?php } ?>
        <?php
        // shipping, payment and confirmation
        ?>
        <!-- panel -->
        <?php if (OPRC_HIDE_WELCOME != 'true' && empty($_SESSION['customer_id'])) { ?>
            <div class="nmx-panel nmx-welcome">

                <!-- panel head -->
                <div class="nmx-panel-head">
                    <?php echo oprc_get_constant_value('HEADING_STEP_1', 'Sign In / Register'); ?>
                </div>
                <!-- end panel head -->

                <!-- panel body -->
                <div class="nmx-panel-body nmx-cf" id="oprcWelcome">
                    <?php echo sprintf(HEADING_WELCOME, $_SESSION['customer_first_name'] ?? ''); ?>
                </div>
                <!-- end panel head -->

            </div>
        <?php } ?>
        <!-- end panel -->

        <!-- panel -->
        <div class="nmx-panel" id="oprcAddresses">

            <!-- panel head -->
            <div class="nmx-panel-head">
                <?php
                echo (
                    (OPRC_SHIPPING_ADDRESS == 'false' || $_SESSION['cart']->get_content_type() == 'virtual')
                    ? oprc_get_constant_value('HEADING_STEP_2_NO_SHIPPING', 'Billing Information')
                    : oprc_get_constant_value('HEADING_STEP_2', 'Billing & Shipping Address')
                );
                ?><!--EOF .oprcHead-->
            </div>

            <!-- panel body -->
            <div class="nmx-panel-body nmx-cf">

                <?php if (isset($messageStack) && is_object($messageStack) && $messageStack->size('checkout_address') > 0) echo $messageStack->output('checkout_address'); ?>

                <!-- row -->
                <div class="nmx-row shipping-billing-addresses nmx-cf">

                    <!-- col-6 -->
                    <div class="nmx-col-6">
                        <?php
                        if (
                            ($_SESSION['cart']->get_content_type() == 'virtual' && OPRC_FORCE_SHIPPING_ADDRESS_TO_BILLING == 'true')
                            || OPRC_FORCE_SHIPPING_ADDRESS_TO_BILLING == 'false'
                        ) {
                            require($template->get_template_dir('tpl_modules_revise_billing.php',DIR_WS_TEMPLATE, $current_page_base,'templates/one_page_checkout'). '/tpl_modules_revise_billing.php');
                        } else {
                            echo "<div id='ignoreAddressCheck'></div>";
                        }
                        ?>
                    </div>
                    <!-- end col-6 -->

                    <!-- col-6 -->
                    <div class="nmx-col-6">
                        <?php
                        if ($_SESSION['cart']->get_content_type() != 'virtual') {
                            require($template->get_template_dir('tpl_modules_revise_shipping.php',DIR_WS_TEMPLATE, $current_page_base,'templates/one_page_checkout'). '/tpl_modules_revise_shipping.php');
                        }
                        ?>
                    </div>
                    <!-- end col-6 -->

                </div>
                <!-- end row -->

            </div>
            <!-- end body -->

        </div>
        <!-- end panel -->

        <!-- panel -->
        <div class="nmx-panel">

            <!-- panel head -->
            <div class="nmx-panel-head current">
                <?php
                echo (
                    (OPRC_SHIPPING_ADDRESS == 'false' || $_SESSION['cart']->get_content_type() == 'virtual')
                    ? oprc_get_constant_value('HEADING_STEP_3_NO_SHIPPING', 'Payment Method')
                    : oprc_get_constant_value('HEADING_STEP_3', 'Shipping & Payment Method')
                );
                ?>
            </div>
            <!-- end panel -->

            <!-- panel body -->
            <div class="nmx-panel-body nmx-cf" id="step3">

                <div id="oprc_column2<?php echo $columns; ?>" class="nmx-box <?php echo $widthClass; ?>">
                    <?php
                    if ($_SESSION['cart']->get_content_type() == 'virtual') {
                        require($template->get_template_dir('tpl_modules_oprc_payment.php',DIR_WS_TEMPLATE, $current_page_base,'templates/one_page_checkout'). '/tpl_modules_oprc_payment.php');
                    } else {
                        require($template->get_template_dir('tpl_modules_oprc_shipping.php',DIR_WS_TEMPLATE, $current_page_base,'templates/one_page_checkout'). '/tpl_modules_oprc_shipping.php');
                    }
                    if (OPRC_CREDIT_POSITION == 1) {
                        require($template->get_template_dir('tpl_modules_oprc_credit.php',DIR_WS_TEMPLATE, $current_page_base,'templates/one_page_checkout'). '/tpl_modules_oprc_credit.php');
                    }
                    ?>
                </div>

                <?php if ($_SESSION['cart']->get_content_type() != 'virtual') { ?>
                    <div id="oprc_column1<?php echo $columns; ?>" class="nmx-box <?php echo $widthClass; ?>">
                        <?php require($template->get_template_dir('tpl_modules_oprc_payment.php',DIR_WS_TEMPLATE, $current_page_base,'templates/one_page_checkout'). '/tpl_modules_oprc_payment.php'); ?>
                    </div>
                <?php } ?>

                <?php
                if ($_SESSION['customer_id']) {
                    require($template->get_template_dir('tpl_modules_oprc_credit.php',DIR_WS_TEMPLATE, $current_page_base,'templates/one_page_checkout'). '/tpl_modules_oprc_credit.php');
                }
                ?>

                <!-- bof FEC v1.27 CHECKBOX -->
                <?php
                $counter = 0;
                if (OPRC_CHECKBOX == 'true') {
                    $checkbox = (isset($_SESSION['oprc_checkbox']) && $_SESSION['oprc_checkbox'] == '1');
                    $counter++;
                    ?>
                    <div id="checkoutFECCheckbox" class="nmx-box">
                        <h3><?php echo oprc_get_constant_value('TABLE_HEADING_OPRC_CHECKBOX', 'Gift Receipt'); ?></h3>
                        <div class="nmx-checkbox">
                            <div class="custom-control custom-checkbox">
                                <?php echo zen_draw_checkbox_field('oprc_checkbox', '1', $checkbox, 'id="oprc_checkbox"'); ?>
                                <label><?php echo oprc_get_constant_value('TEXT_OPRC_CHECKBOX', 'Include gift receipt (prices not displayed)'); ?></label>
                            </div>
                            <?php if (isset($messageStack) && is_object($messageStack) && $messageStack->size('oprc_checkbox') > 0) { ?>
                                <span class="alert validation disablejAlert">
                                    <?php echo $messageStack->output('oprc_checkbox'); ?>
                                </span>
                            <?php } ?>
                        </div>
                    </div>
                <?php
                }
                ?>

                <?php if (OPRC_GIFT_MESSAGE == 'true') {
                    $counter++; ?>
                    <div id="giftMessage" class="nmx-box">
                        <h3><?php echo oprc_get_constant_value('TABLE_HEADING_GIFT_MESSAGE', 'Gift Message'); ?></h3>
                        <div class="boxContents">
                            <?php echo zen_draw_textarea_field('gift-message', '45', '3', $_SESSION['gift_message'] ?? ''); ?>
                            <?php if (isset($messageStack) && is_object($messageStack) && $messageStack->size('gift-message') > 0) { ?>
                                <span class="alert validation disablejAlert">
                                    <?php echo $messageStack->output('gift-message'); ?>
                                </span>
                            <?php } ?>
                        </div>
                    </div>
                <?php } ?>
                <!-- eof FEC v1.27 CHECKBOX -->

                <!-- bof FEC v1.24a DROP DOWN -->
                <?php
                if (OPRC_DROP_DOWN == 'true') {
                    $counter++;
                    ?>
                    <div id="checkoutDropdown" class="nmx-box">
                        <h3><?php echo oprc_get_constant_value('TABLE_HEADING_DROPDOWN', 'Drop Down Heading'); ?></h3>
                        <div class="boxContents">
                            <label><?php echo oprc_get_constant_value('TEXT_DROP_DOWN', 'Select an option: '); ?></label>
                            <?php echo zen_draw_pull_down_menu('dropdown', $dropdown_list_array, $_SESSION['dropdown'] ?? '', 'onchange="updateForm()"', true); ?>
                            <?php if (isset($messageStack) && is_object($messageStack) && $messageStack->size('dropdown') > 0) { ?>
                                <span class="alert validation disablejAlert">
                                    <?php echo $messageStack->output('dropdown'); ?>
                                </span>
                            <?php } ?>
                        </div>
                    </div>
                <?php
                }
                ?>
                <!-- eof DROP DOWN -->

                <?php
                if (DISPLAY_CONDITIONS_ON_CHECKOUT == 'true') {
                    ?>
                    <div id="conditions_checkout" class="nmx-box">
                        <h3><?php echo oprc_get_constant_value('TABLE_HEADING_CONDITIONS', '<span class="termsconditions">Terms & Conditions</span>'); ?></h3>
                        <?php
                        if (isset($messageStack) && is_object($messageStack) && $messageStack->size('conditions') > 0) {
                            ?>
                            <div class="disablejAlert">
                                <?php echo $messageStack->output('conditions'); ?>
                            </div>
                        <?php } ?>
                        <p class="information nmx-mb0"><?php echo oprc_get_constant_value('TEXT_PRIVACY_CONDITIONS_DESCRIPTION_OPRC'); ?></p>

                        <div class="nmx-checkbox nmx-hidden">
                            <label>
                                <?php echo oprc_get_constant_value('TEXT_CONDITIONS_CONFIRM', '<span class="termsiagree">I have read and agreed to the terms and conditions bound to this order.</span>'); ?>
                                <?php echo zen_draw_checkbox_field('conditions', '1', true, 'id="conditions"'); ?>
                            </label>
                        </div>
                    </div>
                <?php
                }
                ?>

            </div>
            <!-- panel body -->
        </div>
        <!-- end panel -->

        <?php if (!defined('OPRC_ORDER_COMMENTS_STATUS') || OPRC_ORDER_COMMENTS_STATUS == 'true') { ?>
        <div class="nmx-panel">
            <!-- panel head -->
            <div class="nmx-panel-head">
                <?php echo oprc_get_constant_value('HEADING_STEP_3_COMMENTS', 'Order Comments'); ?>
            </div>
            <!-- end panel head -->
            <div class="nmx-panel-body">
                <?php
                if ($_SESSION['customer_id']) {
                    require($template->get_template_dir('tpl_modules_oprc_comments.php',DIR_WS_TEMPLATE, $current_page_base,'templates/one_page_checkout'). '/tpl_modules_oprc_comments.php');
                }
                ?>
            </div>
        </div>
        <?php } ?>
        <!-- end panel -->

        <?php
        $button_class_list = trim($button_class . ' oprc-confirm-button');
        $button_attributes = 'name="btn_submit" id="btn_submit"' . ($button_class_list !== '' ? ' class="' . $button_class_list . '"' : '');

        if (OPRC_CSS_BUTTONS == 'false') {
            $oprcButtonMarkup = zen_image_submit($button, $button_alt, $button_attributes);
        } else {
            $css_class_list = trim('cssButton ' . $button_class_list);
            $charset = defined('CHARSET') ? CHARSET : 'UTF-8';
            $button_label = function_exists('zen_output_string_protected') ? zen_output_string_protected($button_text) : htmlspecialchars($button_text, ENT_COMPAT, $charset);
            $button_aria = function_exists('zen_output_string_protected') ? zen_output_string_protected($button_alt) : htmlspecialchars($button_alt, ENT_COMPAT, $charset);
            $oprcButtonMarkup = '<button type="submit" name="btn_submit" id="btn_submit" class="' . $css_class_list . '" aria-label="' . $button_aria . '"><span class="oprc-confirm-button__label">' . $button_label . '</span></button>';
        }
        ?>
        <div class="nmx-box oprc-section-panel oprc-submit-panel">
            <div id="oprcMobileSubmitTarget" class="oprc-mobile-submit-target"></div>
            <div id="js-submit-home"></div>
            <div id="js-submit" class="nmx-panel-footer nmx-buttons">
                <?php echo $oprcButtonMarkup; ?>
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 18 18" fill="none">
                    <path d="M14.25 7.5H3.75C2.92157 7.5 2.25 8.17157 2.25 9V15C2.25 15.8284 2.92157 16.5 3.75 16.5H14.25C15.0784 16.5 15.75 15.8284 15.75 15V9C15.75 8.17157 15.0784 7.5 14.25 7.5Z" stroke="white" stroke-linecap="round" stroke-linejoin="round"></path>
                    <path d="M4.5 4.5C4.5 3.90326 4.73705 3.33097 5.15901 2.90901C5.58097 2.48705 6.15326 2.25 6.75 2.25H11.25C11.8467 2.25 12.419 2.48705 12.841 2.90901C13.2629 3.33097 13.5 3.90326 13.5 4.5V7.5H4.5V4.5Z" stroke="white" stroke-linecap="round" stroke-linejoin="round"></path>
                </svg>
            </div>
        </div>

    </div>
</div>
