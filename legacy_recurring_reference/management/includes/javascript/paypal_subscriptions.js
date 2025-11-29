(function (window, document) {
  'use strict';

  var table = document.getElementById('paypalSubscriptions');
  if (!table) {
    return;
  }

  var config = window.nmxPaypalSubscriptionsConfig || {};
  var messageContainer = document.getElementById('paypal-subscriptions-messages');
  var securityToken = typeof config.securityToken !== 'undefined' ? config.securityToken : null;
  var refreshUrl = config.refreshUrl || '';
  var refreshMessages = config.refreshMessages || {};

  function resolveMessage(key, fallback) {
    return refreshMessages[key] || fallback || '';
  }

  function messageClass(level) {
    if (level === 'success') {
      return 'nmx-alert nmx-alert-success';
    }
    if (level === 'error') {
      return 'nmx-alert nmx-alert-error';
    }
    if (level === 'warning') {
      return 'nmx-alert nmx-alert-warning';
    }
    return 'nmx-alert nmx-alert-info';
  }

  function showMessage(level, message) {
    if (!message) {
      return;
    }

    if (!messageContainer) {
      window.alert(message);
      return;
    }

    var wrapper = document.createElement('div');
    wrapper.className = messageClass(level);
    wrapper.textContent = message;
    messageContainer.appendChild(wrapper);
  }

  function buildFormBody(params) {
    var pairs = [];

    if (securityToken) {
      pairs.push('securityToken=' + encodeURIComponent(securityToken));
    }

    if (params && typeof params === 'object') {
      Object.keys(params).forEach(function (key) {
        var value = params[key];
        if (typeof value !== 'undefined') {
          pairs.push(encodeURIComponent(key) + '=' + encodeURIComponent(value));
        }
      });
    }

    return pairs.join('&');
  }

  function setButtonLoading(button, loading) {
    if (!button) {
      return;
    }
    if (loading) {
      button.classList.add('is-loading');
      button.disabled = true;
    } else {
      button.classList.remove('is-loading');
      if (!button.classList.contains('is-disabled')) {
        button.disabled = false;
      }
    }
  }

  function updateRow(row, data) {
    if (!row || !data) {
      return;
    }

    if (typeof data.refreshed_at !== 'undefined' && data.refreshed_at) {
      row.setAttribute('data-refreshed-at', data.refreshed_at);
    }

    if (row.hasAttribute('data-refresh-pending')) {
      if (!data.refresh_pending) {
        row.removeAttribute('data-refresh-pending');
        if (row.classList) {
          row.classList.remove('is-refresh-pending');
        }
      }
    }

    if (typeof data.status === 'string' && data.status !== '') {
      row.setAttribute('data-status', data.status.toLowerCase());
    }

    var startCell = row.querySelector('td[data-column="start-date"]');
    if (startCell && typeof data.start_date !== 'undefined') {
      startCell.textContent = data.start_date || '';
    }

    var nextCell = row.querySelector('td[data-column="next-date"]');
    if (nextCell && typeof data.next_date !== 'undefined') {
      nextCell.textContent = data.next_date || '';
    }

    var paymentCell = row.querySelector('td[data-column="payment-method"]');
    if (paymentCell && typeof data.payment_method !== 'undefined') {
      paymentCell.textContent = data.payment_method || '';
    }

    var refreshCell = row.querySelector('td[data-column="last-refresh"]');
    if (refreshCell && typeof data.refreshed_at !== 'undefined') {
      refreshCell.textContent = data.refreshed_at || '';
    }

    var statusCell = row.querySelector('td[data-column="status"]');
    if (statusCell) {
      var statusList = statusCell.querySelector('ul');
      if (statusList && statusList.firstElementChild) {
        statusList.firstElementChild.textContent = data.status || statusList.firstElementChild.textContent;
      }

      var refreshButton = statusCell.querySelector('.js-admin-refresh');
      if (refreshButton) {
        if (data.profile_id) {
          refreshButton.setAttribute('data-profile-id', data.profile_id);
        }
        if (typeof data.refresh_eligible !== 'undefined') {
          refreshButton.disabled = !data.refresh_eligible;
          refreshButton.classList.toggle('is-disabled', !data.refresh_eligible);
        }
      }
    }
  }

  function handleRefresh(button) {
    if (!securityToken) {
      showMessage('error', resolveMessage('tokenError', 'Unable to refresh subscription: Missing security token.'));
      return;
    }

    if (!refreshUrl) {
      showMessage('error', resolveMessage('failure', 'Unable to refresh subscription: Missing endpoint.'));
      return;
    }

    if (!button || button.disabled) {
      return;
    }

    var row = button.closest('tr');
    if (!row) {
      showMessage('warning', resolveMessage('missingContext', 'Unable to determine subscription context.'));
      return;
    }

    var profileId = button.getAttribute('data-profile-id') || row.getAttribute('data-profile-id');
    var customerId = button.getAttribute('data-customer-id') || row.getAttribute('data-customer-id');
    if (!profileId || !customerId) {
      showMessage('warning', resolveMessage('missingIdentifiers', 'Missing subscription identifiers for refresh.'));
      return;
    }

    setButtonLoading(button, true);

    window.fetch(refreshUrl, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
        'Accept': 'application/json'
      },
      credentials: 'same-origin',
      body: buildFormBody({ profileId: profileId, customerId: customerId })
    })
      .then(function (response) {
        if (!response || !response.ok) {
          throw new Error('HTTP ' + (response ? response.status : '0'));
        }
        return response.json();
      })
      .then(function (payload) {
        if (!payload || payload.success !== true) {
          var fallback = payload && payload.message ? payload.message : resolveMessage('failure', 'Refresh failed.');
          throw new Error(fallback);
        }

        if (payload.subscription) {
          updateRow(row, payload.subscription);
        }

        var successMessage = payload && payload.message ? payload.message : resolveMessage('success', 'Subscription refreshed.');
        showMessage('success', successMessage);
        setButtonLoading(button, false);
      })
      .catch(function (error) {
        var message = (error && error.message) ? error.message : resolveMessage('failure', 'Refresh failed.');
        showMessage('error', message);
        setButtonLoading(button, false);
      });
  }

  table.addEventListener('click', function (event) {
    var target = event.target;
    if (!target) {
      return;
    }

    var button = null;
    if (typeof target.closest === 'function') {
      button = target.closest('.js-admin-refresh');
    } else if (target.classList && target.classList.contains('js-admin-refresh')) {
      button = target;
    }

    if (!button || !table.contains(button)) {
      return;
    }

    event.preventDefault();
    handleRefresh(button);
  });

  table.addEventListener('submit', function (event) {
    if (!event || typeof event.target === 'undefined' || typeof event.target.closest !== 'function') {
      return;
    }

    var refreshForm = event.target.closest('.js-admin-refresh-form');
    if (!refreshForm || !table.contains(refreshForm)) {
      return;
    }

    event.preventDefault();

    var refreshButton = refreshForm.querySelector('.js-admin-refresh');
    if (refreshButton) {
      handleRefresh(refreshButton);
    }
  });
})(window, document);
