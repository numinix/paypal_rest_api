<?php
/**
 * jscript_form_check
 *
 * @package page
 * @copyright Copyright 2003-2010 Zen Cart Development Team
 * @copyright Portions Copyright 2003 osCommerce
 * @license http://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 * @version $Id: jscript_main.php 17018 2010-07-27 07:25:41Z drbyte $
 */
?>
<script language="javascript" type="text/javascript"><!--
var selected;

function check_form_optional(form_name) {
  var form = form_name;
  if (!form.elements['firstname']) {
    return true;
  } else {
    var firstname = form.elements['firstname'].value;
    var lastname = form.elements['lastname'].value;
    var street_address = form.elements['street_address'].value;

    if (firstname == '' && lastname == '' && street_address == '') {
      return true;
    } else {
      return check_form(form_name);
    }
  }
}
var form = "";
var submitted = false;
var error = false;
var error_message = "";

function check_input(field_name, field_size, message) {
  if (form.elements[field_name] && (form.elements[field_name].type != "hidden")) {
    if (field_size == 0) return;
    var field_value = form.elements[field_name].value;

    if (field_value == '' || field_value.length < field_size) {
      error_message = error_message + "* " + message + "\n";
      error = true;
    }
  }
}

function check_radio(field_name, message) {
  var isChecked = false;

  if (form.elements[field_name] && (form.elements[field_name].type != "hidden")) {
    var radio = form.elements[field_name];

    for (var i=0; i<radio.length; i++) {
      if (radio[i].checked == true) {
        isChecked = true;
        break;
      }
    }

    if (isChecked == false) {
      error_message = error_message + "* " + message + "\n";
      error = true;
    }
  }
}

function check_select(field_name, field_default, message) {
  if (form.elements[field_name] && (form.elements[field_name].type != "hidden")) {
    var field_value = form.elements[field_name].value;

    if (field_value == field_default) {
      error_message = error_message + "* " + message + "\n";
      error = true;
    }
  }
}

function check_password(field_name_1, field_name_2, field_size, message_1, message_2) {
  if (form.elements[field_name_1] && (form.elements[field_name_1].type != "hidden")) {
    var password = form.elements[field_name_1].value;
    var confirmation = form.elements[field_name_2].value;

    if (password == '' || password.length < field_size) {
      error_message = error_message + "* " + message_1 + "\n";
      error = true;
    } else if (password != confirmation) {
      error_message = error_message + "* " + message_2 + "\n";
      error = true;
    }
  }
}

function check_password_new(field_name_1, field_name_2, field_name_3, field_size, message_1, message_2, message_3) {
  if (form.elements[field_name_1] && (form.elements[field_name_1].type != "hidden")) {
    var password_current = form.elements[field_name_1].value;
    var password_new = form.elements[field_name_2].value;
    var password_confirmation = form.elements[field_name_3].value;

    if (password_current == '' ) {
      error_message = error_message + "* " + message_1 + "\n";
      error = true;
    } else if (password_new == '' || password_new.length < field_size) {
      error_message = error_message + "* " + message_2 + "\n";
      error = true;
    } else if (password_new != password_confirmation) {
      error_message = error_message + "* " + message_3 + "\n";
      error = true;
    }
  }
}

function check_state(min_length, min_message, select_message) {
  if (form.elements["state"] && form.elements["zone_id"]) {
    if (!form.state.disabled && form.zone_id.value == "") check_input("state", min_length, min_message);
  } else if (form.elements["state"] && form.elements["state"].type != "hidden" && form.state.disabled) {
    check_select("zone_id", "", select_message);
  }
}

function validateCardNumber(number) {
    var regex = new RegExp("^[0-9]{15,16}$");
    if (!regex.test(number))
        return false;
return true;
    //return luhnCheck(number);
}

function luhnCheck(val) {
    var sum = 0;
    for (var i = 0; i < val.length; i++) {
        var intVal = parseInt(val.substr(i, 1));
        if (i % 2 == 0) {
            intVal *= 2;
            if (intVal > 9) {
                intVal = 1 + (intVal % 10);
            }
        }
        sum += intVal;
    }
    return (sum % 10) == 0;
}

function check_form(form_name) {
  if (submitted == true) {
    alert("<?php echo JS_ERROR_SUBMITTED; ?>");
    return false;
  }

  error = false;
  form = form_name;
  error_message = "<?php echo JS_ERROR; ?>";

  check_input('fullname', 2, '<?php echo SAVED_CREDIT_CARD_ERROR_NAME; ?>');
  check_input('paymenttype', 2, '<?php echo SAVED_CREDIT_CARD_ERROR_TYPE; ?>');
  
  if(validateCardNumber(form.elements['cardnumber'].value) == false) {
    error_message = error_message + "* " + '<?php echo SAVED_CREDIT_CARD_ERROR_NUMBER; ?>' + "\n";
    error = true;
  }

  check_input('monthexpiry', 2, '<?php echo SAVED_CREDIT_CARD_ERROR_EXPIRY_MONTH; ?>');
  check_input('yearexpiry', 2, '<?php echo SAVED_CREDIT_CARD_EXPIRY_YEAR; ?>');
  check_input('cvv', 3, '<?php echo SAVED_CREDIT_CARD_ERROR_CVV; ?>');

  if (error == true) {
    alert(error_message);
    return false;
  } else {
    submitted = true;
    return true;
  }
}
//--></script>
