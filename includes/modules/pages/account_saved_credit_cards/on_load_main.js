(function () {
  if (typeof update_zone === 'function' && document.edit_credit_card) {
    update_zone(document.edit_credit_card);
  }

  var form = document.forms.edit_credit_card || document.edit_credit_card;
  if (!form) {
    return;
  }

  var query = window.location.search;
  if (!query || query.length <= 1) {
    return;
  }

  var params = {};
  var parts = query.substring(1).split('&');
  for (var i = 0; i < parts.length; i++) {
    var section = parts[i];
    if (!section) {
      continue;
    }
    var tuple = section.split('=');
    if (tuple.length < 1) {
      continue;
    }
    var name = decodeURIComponent(tuple[0].replace(/\+/g, ' '));
    var value = tuple.length > 1 ? decodeURIComponent(tuple[1].replace(/\+/g, ' ')) : '';
    params[name] = value;
  }

  if (params.subscription_card_token && !form.querySelector('input[name="subscription_card_token"]')) {
    var tokenField = document.createElement('input');
    tokenField.type = 'hidden';
    tokenField.name = 'subscription_card_token';
    tokenField.value = params.subscription_card_token;
    form.appendChild(tokenField);
  }
})();