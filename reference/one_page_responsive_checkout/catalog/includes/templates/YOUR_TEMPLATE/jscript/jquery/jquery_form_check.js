var form = "";
var submitted = false;
var error = false;
var error_message = "";

function check_input(field_name, field_size, message) {
  if (jQuery('[name=' + field_name + ']') && (jQuery('[name=' + field_name + ']').attr("visibility") != "hidden")) {
    if (field_size == 0) return;
    var field_value = jQuery('[name=' + field_name + ']').val();
    if (field_value == '' || field_value.length < field_size) {
      error = true; 
      jQuery('[name=' + field_name + ']').addClass("missing");
      jQuery('[name=' + field_name + ']').after(' <span class="alert validation">' + message + '</span>');
    }
  }
}

function check_radio(field_name, message) {
  var isChecked = false;

  if (jQuery('[name=' + field_name + ']').val() && (jQuery('[name=' + field_name + ']').attr("visibility") != "hidden")) {
    var radio = jQuery('[name=' + field_name + ']');

    for (var i=0; i<radio.length; i++) {
      if (radio[i].checked == true) {
        isChecked = true;
        break;
      }
    }

    if (isChecked == false) {
      jQuery('[name=' + field_name + ']').addClass("missing");
      jQuery('[name=' + field_name + ']').next().after(' <span class="alert validation">' + message + '</span>');
      error = true;
    }
  }
}

function check_select(field_name, field_default, message) {
  if (jQuery('[name=' + field_name + ']') && (jQuery('[name=' + field_name + ']').attr("visibility") != "hidden")) {
    var field_value = jQuery('[name=' + field_name + ']').val();

    if (field_value == field_default) {
      jQuery('[name=' + field_name + ']').addClass("missing");
      jQuery('[name=' + field_name + ']').after(' <span class="alert validation">' + message + '</span>');
      error = true; 
    }
  }
}

function check_password(field_name_1, field_name_2, field_size, message_1, message_2) {
  if (jQuery('[name=' + field_name_1 + ']') && (jQuery('[name=' + field_name_1 + ']').attr("visibility") != "hidden")) {
    var password = jQuery('[name=' + field_name_1 + ']').val();
    var confirmation = jQuery('[name=' + field_name_2 + ']').val();

    if (password == '' || password.length < field_size) {
      jQuery('[name=' + field_name_1 + ']').addClass("missing");
      jQuery('[name=' + field_name_1 + ']').after(' <span class="alert validation">' + message_1 + '</span>');
      error = true;
    } else if (password != confirmation) {
      jQuery('[name=' + field_name_2 + ']').addClass("missing");
      jQuery('[name=' + field_name_2 + ']').after(' <span class="alert validation">' + message_2 + '</span>');
      error = true; 
    }
  }
}

function check_email(field_name_1, field_name_2, field_size, message_1, message_2) {
  if (jQuery('[name=' + field_name_1 + ']') && (jQuery('[name=' + field_name_1 + ']').attr("visibility") != "hidden")) {
    var email = jQuery('[name=' + field_name_1 + ']').val();
    var email_confirmation = jQuery('[name=' + field_name_2 + ']').val();
    if (email == '' || email.length < field_size) {
      jQuery('[name=' + field_name_1 + ']').addClass("missing");
      jQuery('[name=' + field_name_1 + ']').after(' <span class="alert validation">' + message_1 + '</span>');
      error = true;    
    } else if (jQuery('[name=' + field_name_2 + ']').length > 0 && email != email_confirmation) {
      jQuery('[name=' + field_name_2 + ']').addClass("missing");
      jQuery('[name=' + field_name_2 + ']').after(' <span class="alert validation">' + message_2 + '</span>');
      error = true; 
    }
  }
}

function check_password_new(field_name_1, field_name_2, field_name_3, field_size, message_1, message_2, message_3) {
  if (jQuery('[name=' + field_name_1 + ']') && (jQuery('[name=' + field_name_1 + ']').attr("visibility") != "hidden")) {
    var password_current = jQuery('[name=' + field_name_1 + ']').val();
    var password_new = jQuery('[name=' + field_name_2 + ']').val();
    var password_confirmation = jQuery('[name=' + field_name_3 + ']').val();

    if (password_current == '' || password_current.length < field_size) {
      jQuery('[name=' + field_name_1 + ']').addClass("missing");
      jQuery('[name=' + field_name_1 + ']').after(' <span class="alert validation">' + message_1 + '</span>');
      error = true; 
    } else if (password_new == '' || password_new.length < field_size) {
      jQuery('[name=' + field_name_2 + ']').addClass("missing");
      jQuery('[name=' + field_name_2 + ']').after(' <span class="alert validation">' + message_2 + '</span>');
      error = true; 
    } else if (password_new != password_confirmation) {
      jQuery('[name=' + field_name_3 + ']').addClass("missing");
      jQuery('[name=' + field_name_3 + ']').after(' <span class="alert validation">' + message_3 + '</span>');
      error = true;
    }
  }
}