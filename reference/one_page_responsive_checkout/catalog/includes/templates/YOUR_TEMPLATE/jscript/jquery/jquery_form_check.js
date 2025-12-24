var form = "";
var submitted = false;
var error = false;
var inlineFieldErrorClass = 'js-inline-field-error';

function getFieldElements(field_name) {
  return jQuery('[name="' + field_name + '"]');
}

function appendFieldError($elements, message) {
  if (!$elements || !$elements.length) {
    return;
  }

  var $target = jQuery($elements[$elements.length - 1]);
  $elements.addClass('missing');

  var $existing = $target.next('.' + inlineFieldErrorClass);
  if ($existing.length) {
    $existing.remove();
  }

  var $message = jQuery('<div/>', {
    'class': 'disablejAlert alert validation ' + inlineFieldErrorClass,
    role: 'alert',
    'aria-live': 'polite'
  }).append(
    jQuery('<div/>', {
      'class': 'messageStackError',
      text: message
    })
  );

  $target.after($message);
}

function check_input(field_name, field_size, message) {
  var $field = getFieldElements(field_name);
  if ($field.length && ($field.attr("visibility") != "hidden")) {
    if (field_size == 0) return;
    var field_value = $field.val();
    if (field_value == '' || field_value.length < field_size) {
      error = true;
      appendFieldError($field, message);
    }
  }
}

function check_radio(field_name, message) {
  var isChecked = false;
  var $field = getFieldElements(field_name);

  if ($field.length && ($field.attr("visibility") != "hidden")) {
    for (var i = 0; i < $field.length; i++) {
      if ($field[i].checked == true) {
        isChecked = true;
        break;
      }
    }

    if (isChecked == false) {
      appendFieldError($field, message);
      error = true;
    }
  }
}

function check_select(field_name, field_default, message) {
  var $field = getFieldElements(field_name);
  if ($field.length && ($field.attr("visibility") != "hidden")) {
    var field_value = $field.val();

    if (field_value == field_default) {
      appendFieldError($field, message);
      error = true;
    }
  }
}

function check_password(field_name_1, field_name_2, field_size, message_1, message_2) {
  var $field = getFieldElements(field_name_1);
  if ($field.length && ($field.attr("visibility") != "hidden")) {
    var password = $field.val();
    var $confirmationField = getFieldElements(field_name_2);
    var confirmation = $confirmationField.val();

    if (password == '' || password.length < field_size) {
      appendFieldError($field, message_1);
      error = true;
    } else if (password != confirmation) {
      appendFieldError($confirmationField, message_2);
      error = true;
    }
  }
}

function check_email(field_name_1, field_name_2, field_size, message_1, message_2) {
  var $field = getFieldElements(field_name_1);
  if ($field.length && ($field.attr("visibility") != "hidden")) {
    var email = $field.val();
    var $emailConfirmation = getFieldElements(field_name_2);
    var email_confirmation = $emailConfirmation.val();
    if (email == '' || email.length < field_size) {
      appendFieldError($field, message_1);
      error = true;
    } else if ($emailConfirmation.length > 0 && email != email_confirmation) {
      appendFieldError($emailConfirmation, message_2);
      error = true;
    }
  }
}

function check_password_new(field_name_1, field_name_2, field_name_3, field_size, message_1, message_2, message_3) {
  var $currentField = getFieldElements(field_name_1);
  if ($currentField.length && ($currentField.attr("visibility") != "hidden")) {
    var password_current = $currentField.val();
    var $newField = getFieldElements(field_name_2);
    var password_new = $newField.val();
    var $confirmationField = getFieldElements(field_name_3);
    var password_confirmation = $confirmationField.val();

    if (password_current == '' || password_current.length < field_size) {
      appendFieldError($currentField, message_1);
      error = true;
    } else if (password_new == '' || password_new.length < field_size) {
      appendFieldError($newField, message_2);
      error = true;
    } else if (password_new != password_confirmation) {
      appendFieldError($confirmationField, message_3);
      error = true;
    }
  }
}