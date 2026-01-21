<div id="contactDetailsContainer" class="nmx-box">
    <h3><?php echo TABLE_HEADING_CONTACT_DETAILS; ?></h3>
    <?php
    require($template->get_template_dir('tpl_modules_oprc_cowoa.php', DIR_WS_TEMPLATE, $current_page_base, 'templates/one_page_checkout'). '/tpl_modules_oprc_cowoa.php');
    ?>
    <div class="nmx-form-address nmx-box">
        <div class="nmx-row nmx-form-group">
            <div class="nmx-col-6">
                <label for="email-address-register">
                    <?php
                    echo ENTRY_EMAIL_ADDRESS;
                    echo oprc_required_indicator(ENTRY_EMAIL_ADDRESS_MIN_LENGTH, ENTRY_EMAIL_ADDRESS_TEXT);
                    ?>
                </label>
                <?php
                echo zen_draw_input_field(
                    'email_address_register',
                    (isset($_SESSION['email_address_register']) ? $_SESSION['email_address_register'] : ''),
                    zen_set_field_length(TABLE_CUSTOMERS, 'customers_email_address', '40') . ' class="nmx-fw" id="email-address-register"'
                );
                if ($messageStack->size('email_address_register') > 0) {
                    echo '<div class="disablejAlert alert validation">';
                    echo $messageStack->output('email_address_register');
                    echo '</div>';
                }
                ?>
            </div>
            <?php if (OPRC_CONFIRM_EMAIL == 'true') { ?>
                <div class="nmx-col-6">
                    <label for="login-email-address-confirm">
                        <?php
                        echo ENTRY_EMAIL_ADDRESS_CONFIRM;
                        echo oprc_required_indicator(ENTRY_EMAIL_ADDRESS_MIN_LENGTH, ENTRY_EMAIL_ADDRESS_TEXT);
                        ?>
                    </label>
                    <?php
                    echo zen_draw_input_field(
                        'email_address_confirm',
                        (isset($_SESSION['email_address_confirm']) ? $_SESSION['email_address_confirm'] : ''),
                        zen_set_field_length(TABLE_CUSTOMERS, 'customers_email_address', '40') . ' class="nmx-fw" id="login-email-address-confirm"'
                    );
                    if ($messageStack->size('email_address_confirm') > 0) {
                        echo '<div class="disablejAlert alert validation">';
                        echo $messageStack->output('email_address_confirm');
                        echo '</div>';
                    }
                    ?>
                </div>
            <?php } ?>
        </div>

        <?php if (OPRC_NOACCOUNT_ONLY_SWITCH == 'false') { ?>
            <div id="passwordField"
                class="nmx-row nmx-form-group <?php echo (OPRC_NOACCOUNT_DEFAULT == 'true' && ($_SESSION['cart']->get_content_type() === 'physical' || OPRC_NOACCOUNT_VIRTUAL == 'true') ? 'nmx-hidden' : '') ?>">
                <?php
                if (isset($_GET['type']) && $_GET['type'] == 'cowoa') {
                    $hiddenField = ' style="display:none;"';
                    echo zen_draw_hidden_field('cowoa', 'true', 'class="hiddenField"');
                } else {
                    echo zen_draw_hidden_field('cowoa', 'false', 'class="hiddenField"');
                }
                ?>
                <div class="nmx-col-6">
                    <label for="password-register">
                        <?php
                        echo ENTRY_PASSWORD;
                        echo oprc_required_indicator(ENTRY_PASSWORD_MIN_LENGTH, ENTRY_PASSWORD_TEXT);
                        ?>
                    </label>
                    <?php
                    echo zen_draw_password_field(
                        'password-register',
                        '',
                        zen_set_field_length(TABLE_CUSTOMERS, 'customers_password', '20') . ' class="nmx-fw" id="password-register"'
                    );
                    if ($messageStack->size('password-register') > 0) {
                        echo '<div class="disablejAlert alert validation">';
                        echo $messageStack->output('password-register');
                        echo '</div>';
                    }
                    ?>
                </div>
                <div class="nmx-col-6">
                    <label for="password-confirmation">
                        <?php
                        echo ENTRY_PASSWORD_CONFIRMATION;
                        echo oprc_required_indicator(ENTRY_PASSWORD_MIN_LENGTH, ENTRY_PASSWORD_CONFIRMATION_TEXT);
                        ?>
                    </label>
                    <?php
                    echo zen_draw_password_field(
                        'password-confirmation',
                        '',
                        zen_set_field_length(TABLE_CUSTOMERS, 'customers_password', '20') . ' class="nmx-fw" id="password-confirmation"'
                    );
                    if ($messageStack->size('password-confirmation') > 0) {
                        echo '<div class="disablejAlert alert validation">';
                        echo $messageStack->output('password-confirmation');
                        echo '</div>';
                    }
                    ?>
                </div>
            </div>
        <?php } ?>

        <?php if (ACCOUNT_TELEPHONE == 'true' && ACCOUNT_TELEPHONE_SHIPPING != 'true') { ?>
            <div class="nmx-row nmx-form-group">
                <div class="nmx-col-6">
                    <label for="telephone">
                        <?php
                        echo ENTRY_TELEPHONE_NUMBER;
                        echo oprc_required_indicator(ENTRY_TELEPHONE_MIN_LENGTH, ENTRY_TELEPHONE_NUMBER_TEXT);
                        ?>
                    </label>
                    <?php
                    echo zen_draw_input_field(
                        'telephone',
                        (isset($_SESSION['telephone']) ? $_SESSION['telephone'] : ''),
                        zen_set_field_length(TABLE_CUSTOMERS, 'customers_telephone', '40') . ' class="nmx-fw" id="telephone"'
                    );
                    if ($messageStack->size('telephone') > 0) {
                        echo '<div class="disablejAlert alert validation">';
                        echo $messageStack->output('telephone');
                        echo '</div>';
                    }
                    ?>
                </div>
                <?php if (ACCOUNT_FAX_NUMBER == 'true') { ?>
                    <div class="nmx-col-6">
                        <label for="fax">
                            <?php
                            echo ENTRY_FAX_NUMBER;
                            if (zen_not_null(ENTRY_FAX_NUMBER_TEXT)) {
                                echo '<span class="alert">' . ENTRY_FAX_NUMBER_TEXT . '</span>';
                            }
                            ?>
                        </label>
                        <?php
                        echo zen_draw_input_field(
                            'fax',
                            (isset($_SESSION['fax']) ? $_SESSION['fax'] : ''),
                            'id="fax"'
                        );
                        if ($messageStack->size('fax') > 0) {
                            echo '<div class="disablejAlert alert validation">';
                            echo $messageStack->output('fax');
                            echo '</div>';
                        }
                        ?>
                    </div>
                <?php } ?>
            </div>
        <?php } ?>

        <?php if (CUSTOMERS_REFERRAL_STATUS == 2) { ?>
            <div class="nmx-form-group">
                <h3><?php echo TABLE_HEADING_REFERRAL_DETAILS; ?></h3>
                <div class="nmx-row">
                    <div class="nmx-col-6">
                        <label for="customers_referral"><?php echo ENTRY_CUSTOMERS_REFERRAL; ?></label>
                        <?php
                        echo zen_draw_input_field(
                            'customers_referral',
                            (isset($_SESSION['referred_by_code']) ? $_SESSION['referred_by_code'] : ''),
                            zen_set_field_length(TABLE_CUSTOMERS, 'customers_referral', '15') . ' class="nmx-fw" id="customers_referral"'
                        );
                        if ($messageStack->size('customers_referral') > 0) {
                            echo '<div class="disablejAlert alert validation">';
                            echo $messageStack->output('customers_referral');
                            echo '</div>';
                        }
                        ?>
                    </div>
                </div>
            </div>
        <?php } ?>

        <div class="nmx-form-group">
            <?php if (
                OPRC_NOACCOUNT_HIDEEMAIL == 'false'
                || (OPRC_NOACCOUNT_HIDEEMAIL == 'true' && (!isset($_SESSION['COWOA']) || !$_SESSION['COWOA']))
            ) { ?>
                <?php if (isset($_GET['type']) && $_GET['type'] == 'cowoa') {
                    $hiddenField = ' style="display:none;"';
                } ?>
                <?php if (ACCOUNT_NEWSLETTER_STATUS != 0) { ?>
                    <div id="newsletterOptions" <?php echo (OPRC_FORCE_GUEST_ACCOUNT_SUBSCRIPTION == 'true' && isset($hiddenField)) ? $hiddenField : ''; ?>>
                        <?php
                        echo '<div class="custom-control custom-checkbox">'
                            . zen_draw_checkbox_field('newsletter', '1', $newsletter, 'id="newsletter-checkbox"')
                            . '<label for="newsletter-checkbox">'
                            . ENTRY_NEWSLETTER
                            . (zen_not_null(ENTRY_NEWSLETTER_TEXT) ? '<span class="alert">' . ENTRY_NEWSLETTER_TEXT . '</span>' : '')
                            . '</label></div>';
                        if ($messageStack->size('newsletter') > 0) {
                            echo '<div class="disablejAlert alert validation">';
                            echo $messageStack->output('newsletter');
                            echo '</div>';
                        }
                        ?>
                    </div>
                <?php } ?>
            <?php } elseif (ACCOUNT_NEWSLETTER_STATUS == 2) { ?>
                <?php echo zen_draw_hidden_field('newsletter', '1'); ?>
            <?php } ?>

            <?php if (
                OPRC_HIDEEMAIL_ALL != "true"
                && $email_format != "HTML"
                && OPRC_NOACCOUNT_HIDEEMAIL != "true"
            ) { ?>
                <div id="emailOptions">
                    <?php
                    echo '<div class="custom-control custom-radio">'
                        . zen_draw_radio_field('email_format', 'HTML', ($email_format == 'HTML'), 'id="email-format-html"')
                        . '<label for="email-format-html">' . ENTRY_EMAIL_HTML_DISPLAY . '</label></div>'
                        . '<div class="custom-control custom-radio">'
                        . zen_draw_radio_field('email_format', 'TEXT', ($email_format == 'TEXT'), 'id="email-format-text"')
                        . '<label>' . ENTRY_EMAIL_TEXT_DISPLAY . '</label></div>';
                    if ($messageStack->size('email_format') > 0) {
                        echo '<div class="disablejAlert alert validation">';
                        echo $messageStack->output('email_format');
                        echo '</div>';
                    }
                    ?>
                </div>
            <?php } ?>
        </div>
    </div>

    <?php if (ACCOUNT_DOB == 'true') { ?>
        <div class="nmx-form-group nmx-box">
            <h3><?php echo TABLE_HEADING_DATE_OF_BIRTH; ?></h3>
            <div class="nmx-row">
                <div class="nmx-col-6">
                    <label>
                        <?php
                        echo ENTRY_DATE_OF_BIRTH;
                        echo oprc_required_indicator(ENTRY_DOB_MIN_LENGTH, ENTRY_DATE_OF_BIRTH_TEXT);
                        ?>
                    </label>
                    <?php
                    $dob_months_array = [
                        ['id' => '01', 'text' => 'January'],
                        ['id' => '02', 'text' => 'February'],
                        ['id' => '03', 'text' => 'March'],
                        ['id' => '04', 'text' => 'April'],
                        ['id' => '05', 'text' => 'May'],
                        ['id' => '06', 'text' => 'June'],
                        ['id' => '07', 'text' => 'July'],
                        ['id' => '08', 'text' => 'August'],
                        ['id' => '09', 'text' => 'September'],
                        ['id' => '10', 'text' => 'October'],
                        ['id' => '11', 'text' => 'November'],
                        ['id' => '12', 'text' => 'December'],
                    ];
                    $dob_days_array = [];
                    for ($i = 1; $i <= 31; $i++) {
                        $dob_days_array[] = ['id' => sprintf('%02d', $i), 'text' => $i];
                    }
                    $dob_years_array = [];
                    for ($i = date('Y'); $i >= date('Y') - 100; $i--) {
                        $dob_years_array[] = ['id' => ($i < 10 ? '0' . $i : $i), 'text' => $i];
                    }
                    ?>
                    <div class="dob">
                        <?php
                        echo zen_draw_pull_down_menu(
                            'dob_month',
                            $dob_months_array,
                            (isset($_SESSION['dob_month']) ? $_SESSION['dob_month'] : ''),
                            'id="dob_month" class="dob"'
                        );
                        echo zen_draw_pull_down_menu(
                            'dob_day',
                            $dob_days_array,
                            (isset($_SESSION['dob_day']) ? $_SESSION['dob_day'] : ''),
                            'id="dob_day" class="dob"'
                        );
                        echo zen_draw_pull_down_menu(
                            'dob_year',
                            $dob_years_array,
                            (isset($_SESSION['dob_year']) ? $_SESSION['dob_year'] : ''),
                            'id="dob_year" class="dob"'
                        );
                        ?>
                    </div>
                    <?php
                    if ($messageStack->size('dob') > 0) {
                        echo '<div class="disablejAlert alert validation">';
                        echo $messageStack->output('dob');
                        echo '</div>';
                    }
                    ?>
                </div>
            </div>
        </div>
    <?php } ?>
</div>
