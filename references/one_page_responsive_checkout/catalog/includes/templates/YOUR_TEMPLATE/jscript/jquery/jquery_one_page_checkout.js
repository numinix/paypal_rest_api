// GLOBAL JAVASCRIPT
var widthMin = 1232;
var fancyboxEnabled = false;
var oprcAddressSubmitWrapperSelector = '#js-submit-new-address, #js-submit-chosen-address';
var oprcAddressSubmitElementSelector = 'button, input[type="submit"], input[type="image"], .cssButton';
var oprcAddressWrapperLoadingClass = 'is-loading';

var oprcProcessingOverlayShouldReloadQuotes = false;
var oprcProcessingOverlayCartChanged = false;
var sessionExpiredStorageKey = 'oprcSessionExpired';

var oprcLastSubmittedShippingMethod = null;
var oprcShippingContainerTemplateHtml = null;
var oprcLoginCheckRequest = null;
var oprcUpdateFormProcessRequest = null;
var oprcShippingUpdateRequest = null;
var oprcAllowNativeCheckoutSubmit = false;
var oprcPaymentReloadQueue = [];
var oprcIsProcessingPaymentReload = false;
var oprcLastPaymentReloadId = 0;
var oprcPaymentReloadIdleDelay = 120;
var oprcPaymentReloadMaxWait = 2000;
var oprcActivePaymentReloadId = null;
var oprcManagedEventListeners = window.oprcManagedEventListeners || [];
window.oprcManagedEventListeners = oprcManagedEventListeners;
var oprcSuppressListenerCapture = false;
var oprcAlertMessagesSelector = '.disablejAlert';
var oprcSuccessMessageClass = 'success';
var oprcPaymentPreSubmitCallbacks = [];
var oprcKeepaliveIntervalId = null;
var oprcKeepaliveIntervalMs = 60000; // 1 minute

/**
 * Register a callback to be executed before payment submission.
 * This allows payment modules to perform asynchronous operations (like 3DS verification)
 * while OPRC maintains control of the processing overlay.
 * 
 * @param {Function} callback - Function to call before submission. Should return a Promise or call done()/fail().
 * @param {string} paymentCode - The payment module code (e.g., 'braintree_api')
 */
function oprcRegisterPaymentPreSubmitCallback(callback, paymentCode) {
  if (typeof callback !== 'function') {
    console.error('OPRC: Payment pre-submit callback must be a function');
    return;
  }
  
  oprcPaymentPreSubmitCallbacks.push({
    callback: callback,
    paymentCode: paymentCode || 'unknown'
  });
  
  console.log('OPRC: Registered pre-submit callback for payment module:', paymentCode);
}

/**
 * Clear all registered payment pre-submit callbacks.
 * Typically called when payment methods are reloaded.
 */
function oprcClearPaymentPreSubmitCallbacks() {
  oprcPaymentPreSubmitCallbacks = [];
  console.log('OPRC: Cleared all payment pre-submit callbacks');
}

/**
 * Execute registered payment pre-submit callbacks for the selected payment method.
 * Returns a promise that resolves when all callbacks complete.
 * 
 * @param {string} selectedPaymentCode - The currently selected payment method code
 * @returns {Promise} Promise that resolves when callbacks complete or rejects on error
 */
function oprcExecutePaymentPreSubmitCallbacks(selectedPaymentCode) {
  return new Promise(function(resolve, reject) {
    var relevantCallbacks = oprcPaymentPreSubmitCallbacks.filter(function(item) {
      return item.paymentCode === selectedPaymentCode || item.paymentCode === '*';
    });
    
    if (relevantCallbacks.length === 0) {
      console.log('OPRC: No pre-submit callbacks registered for payment:', selectedPaymentCode);
      resolve();
      return;
    }
    
    console.log('OPRC: Executing', relevantCallbacks.length, 'pre-submit callback(s) for payment:', selectedPaymentCode);
    
    var callbackPromises = relevantCallbacks.map(function(item) {
      return new Promise(function(callbackResolve, callbackReject) {
        try {
          var result = item.callback({
            paymentCode: selectedPaymentCode,
            resolve: callbackResolve,
            reject: callbackReject
          });
          
          // If callback returns a promise, use it
          if (result && typeof result.then === 'function') {
            result.then(callbackResolve).catch(callbackReject);
          }
          // If callback doesn't return a promise and doesn't call resolve/reject,
          // assume it completed synchronously
          else if (result !== false) {
            callbackResolve();
          }
        } catch (error) {
          console.error('OPRC: Payment pre-submit callback error:', error);
          callbackReject(error);
        }
      });
    });
    
    Promise.all(callbackPromises)
      .then(function() {
        console.log('OPRC: All payment pre-submit callbacks completed successfully');
        resolve();
      })
      .catch(function(error) {
        console.error('OPRC: Payment pre-submit callback failed:', error);
        reject(error);
      });
  });
}

/**
 * Determine the URL we should load after the shopper chooses guest checkout.
 * Reloading the checkout ensures third-party payment scripts (e.g. hosted
 * fields) can initialize against the visible DOM instead of the hidden
 * login view.
 *
 * @param {jQuery} $form - Guest checkout form
 * @returns {string} URL to load or an empty string if none can be resolved
 */
function oprcResolveGuestCheckoutRedirectUrl($form) {
  if ($form && $form.length && typeof $form.attr === 'function') {
    var formAction = $form.attr('action');
    if (formAction && formAction.length) {
      return formAction;
    }
  }

  if (typeof onePageCheckoutURL === 'string' && onePageCheckoutURL.length) {
    var separator = onePageCheckoutURL.indexOf('?') === -1 ? '?' : '&';
    return onePageCheckoutURL + separator + 'type=cowoa&hideregistration=true&step=2';
  }

  return '';
}

function oprcResetManagedEventListeners() {
  if (!Array.isArray(oprcManagedEventListeners)) {
    oprcManagedEventListeners = [];
    window.oprcManagedEventListeners = oprcManagedEventListeners;
  }

  if (!oprcManagedEventListeners.length) {
    return;
  }

  oprcSuppressListenerCapture = true;

  for (var i = oprcManagedEventListeners.length - 1; i >= 0; i--) {
    var entry = oprcManagedEventListeners[i];

    if (!entry || !entry.target || typeof entry.target.removeEventListener !== 'function') {
      continue;
    }

    try {
      entry.target.removeEventListener(entry.type, entry.listener, entry.options);
    } catch (err) {
      // Ignore removal errors since listeners may already be detached
    }
  }

  oprcManagedEventListeners.length = 0;
  window.oprcManagedEventListeners = oprcManagedEventListeners;
  oprcSuppressListenerCapture = false;
}

function oprcCaptureEventListeners(callback) {
  if (typeof callback !== 'function') {
    return;
  }

  if (!Array.isArray(oprcManagedEventListeners)) {
    oprcManagedEventListeners = [];
    window.oprcManagedEventListeners = oprcManagedEventListeners;
  }

  var eventTargetProto = window.EventTarget && window.EventTarget.prototype;
  var originalProtoAdd = null;
  var originalProtoRemove = null;

  if (eventTargetProto) {
    originalProtoAdd = eventTargetProto.addEventListener;
    originalProtoRemove = eventTargetProto.removeEventListener;

    if (originalProtoAdd) {
      eventTargetProto.addEventListener = function(type, listener, options) {
        originalProtoAdd.apply(this, arguments);

        if (oprcSuppressListenerCapture || !listener) {
          return;
        }

        oprcManagedEventListeners.push({
          target: this,
          type: type,
          listener: listener,
          options: options
        });
      };
    }

    if (originalProtoRemove) {
      eventTargetProto.removeEventListener = function(type, listener, options) {
        originalProtoRemove.apply(this, arguments);

        if (!listener || !oprcManagedEventListeners.length) {
          return;
        }

        for (var i = oprcManagedEventListeners.length - 1; i >= 0; i--) {
          var entry = oprcManagedEventListeners[i];
          if (entry && entry.target === this && entry.type === type && entry.listener === listener && entry.options === options) {
            oprcManagedEventListeners.splice(i, 1);
          }
        }
      };
    }
  }

  try {
    callback();
  } finally {
    if (eventTargetProto) {
      if (originalProtoAdd) {
        eventTargetProto.addEventListener = originalProtoAdd;
      }
      if (originalProtoRemove) {
        eventTargetProto.removeEventListener = originalProtoRemove;
      }
    }
  }
}

function oprcWaitForPaymentContainerIdle(callback) {
  var container = document.getElementById('paymentMethodContainer');

  if (!container || typeof MutationObserver !== 'function') {
    setTimeout(function() {
      callback();
    }, 0);
    return;
  }

  var idleTimer = null;
  var maxTimer = null;
  var observer = new MutationObserver(function() {
    if (idleTimer) {
      clearTimeout(idleTimer);
    }

    idleTimer = setTimeout(finish, oprcPaymentReloadIdleDelay);
  });

  function cleanup() {
    if (observer) {
      observer.disconnect();
    }
    if (idleTimer) {
      clearTimeout(idleTimer);
    }
    if (maxTimer) {
      clearTimeout(maxTimer);
    }
  }

  function finish() {
    cleanup();
    callback();
  }

  observer.observe(container, {
    childList: true,
    subtree: true,
    attributes: true
  });

  idleTimer = setTimeout(finish, oprcPaymentReloadIdleDelay);
  maxTimer = setTimeout(finish, oprcPaymentReloadMaxWait);
}

function oprcProcessNextPaymentReload() {
  if (!oprcPaymentReloadQueue.length) {
    oprcIsProcessingPaymentReload = false;
    return;
  }

  oprcIsProcessingPaymentReload = true;
  var nextId = oprcPaymentReloadQueue.shift();
  oprcActivePaymentReloadId = nextId;
  var eventDetail = {
    paymentReloadId: nextId
  };

  var event;
  if (typeof window.CustomEvent === 'function') {
    event = new CustomEvent('onePageCheckoutReloaded', { detail: eventDetail });
  } else {
    event = document.createEvent('Event');
    event.initEvent('onePageCheckoutReloaded', true, true);
    event.detail = eventDetail;
  }

  try {
    document.dispatchEvent(event);
  } catch (err) {
    setTimeout(function() {
      throw err;
    }, 0);
  }

  oprcWaitForPaymentContainerIdle(function() {
    if (oprcActivePaymentReloadId === nextId) {
      oprcActivePaymentReloadId = null;
    }
    oprcIsProcessingPaymentReload = false;
    if (oprcPaymentReloadQueue.length) {
      oprcProcessNextPaymentReload();
    }
  });
}

function oprcQueuePaymentReloadEvent() {
  oprcLastPaymentReloadId += 1;
  oprcPaymentReloadQueue.push(oprcLastPaymentReloadId);

  if (!oprcIsProcessingPaymentReload) {
    oprcProcessNextPaymentReload();
  }
}

// Provide a fallback for the legacy global validation helper so that the checkout
// submission continues even if a payment module does not inject the function.
if (typeof window.check_payment_form !== 'function') {
  if (typeof window.check_form === 'function') {
    window.check_payment_form = function() {
      return window.check_form.apply(this, arguments);
    };
  } else {
    window.check_payment_form = function() {
      return true;
    };
  }
}

function oprcFetchLoginCheck()
{
  var separator = ajaxLoginCheckURL.indexOf('?') === -1 ? '?' : '&';
  return jQuery.get(ajaxLoginCheckURL + separator + '_=' + (new Date()).getTime());
}

/**
 * Send a keepalive request to prevent the session from timing out.
 * This function is called periodically while the checkout page is open.
 */
function oprcSendKeepalive()
{
  if (typeof ajaxKeepaliveURL === 'undefined' || !ajaxKeepaliveURL) {
    return;
  }

  var separator = ajaxKeepaliveURL.indexOf('?') === -1 ? '?' : '&';
  jQuery.ajax({
    url: ajaxKeepaliveURL + separator + '_=' + (new Date()).getTime(),
    type: 'GET',
    dataType: 'json',
    cache: false
  }).fail(function(xhr, status, error) {
    // Silently handle keepalive failures - they shouldn't interrupt the user experience
    // The session may still be valid, or the next user action will trigger a session check
    if (window.console && typeof window.console.log === 'function') {
      console.log('OPRC: Session keepalive request failed:', status);
    }
  });
}

/**
 * Start the session keepalive interval.
 * Sends a keepalive request every minute to prevent session timeout.
 */
function oprcStartKeepalive()
{
  if (oprcKeepaliveIntervalId !== null) {
    return;
  }

  oprcKeepaliveIntervalId = setInterval(oprcSendKeepalive, oprcKeepaliveIntervalMs);
}

/**
 * Stop the session keepalive interval.
 */
function oprcStopKeepalive()
{
  if (oprcKeepaliveIntervalId !== null) {
    clearInterval(oprcKeepaliveIntervalId);
    oprcKeepaliveIntervalId = null;
  }
}

function oprcNormalizeForgottenPasswordHref(href)
{
  if (typeof href !== 'string') {
    return '';
  }

  var normalized = href.replace(/\s+#/g, '#').replace(/%20#/g, '#');
  var hashIndex = normalized.indexOf('#');
  if (hashIndex !== -1) {
    var base = normalized.substring(0, hashIndex).trim();
    var fragment = normalized.substring(hashIndex);
    return base + fragment;
  }

  return normalized.trim();
}

function oprcGetForgottenPasswordMessages()
{
  if (typeof window.oprcForgottenPasswordMessages === 'object' && window.oprcForgottenPasswordMessages !== null) {
    return window.oprcForgottenPasswordMessages;
  }

  var messages = {};
  var $link = jQuery('#forgottenPasswordLink a');
  if ($link.length) {
    var processing = $link.data('processing-message');
    var error = $link.data('error-message');
    var success = $link.data('success-message');

    if (typeof processing === 'string' && processing.length) {
      messages.processing = processing;
    }

    if (typeof error === 'string' && error.length) {
      messages.error = error;
    }

    if (typeof success === 'string' && success.length) {
      messages.success = success;
    }
  }

  window.oprcForgottenPasswordMessages = messages;

  return messages;
}

function oprcGetForgottenPasswordMessage(key, fallback)
{
  var messages = oprcGetForgottenPasswordMessages();
  if (messages && Object.prototype.hasOwnProperty.call(messages, key)) {
    var value = messages[key];
    if (typeof value === 'string' && value.length) {
      return value;
    }
  }

  return typeof fallback === 'undefined' ? '' : fallback;
}

function oprcForgottenPasswordShowMessage($container, type, message)
{
  if (!$container || !$container.length) {
    return;
  }

  var content = typeof message === 'string' ? message : '';
  if (!content.length) {
    $container.empty();
    return;
  }

  var classes = 'disablejAlert alert';
  var messageClass = 'messageStackCaution';

  if (type === 'success') {
    classes += ' success';
    messageClass = 'messageStackSuccess';
  } else if (type === 'error') {
    classes += ' validation';
    messageClass = 'messageStackError';
  } else {
    classes += ' information';
  }

  var $messageWrapper = jQuery('<div></div>').addClass(classes);
  var $message = jQuery('<div></div>').addClass(messageClass).html(content);
  $messageWrapper.append($message);

  $container
    .attr('role', 'alert')
    .attr('aria-live', 'polite')
    .empty()
    .append($messageWrapper);
}

function oprcBindForgottenPasswordForm($context)
{
  var $container = $context && $context.length ? $context : jQuery('#passwordForgotten');
  var $form = $container.find('form[name="password_forgotten"], form#password_forgotten, form#passwordForgotten, form[action*="password_forgotten"]').first();

  if (!$form.length) {
    $form = jQuery('form[name="password_forgotten"], form#password_forgotten, form#passwordForgotten, form[action*="password_forgotten"]').first();
    $container = $form.closest('#passwordForgotten');
  }

  if (!$form.length) {
    return;
  }

  if (!$container || !$container.length) {
    $container = $form.closest('.centerColumn');
  }

  var $messageContainer = $container && $container.length ? $container.find('.oprc-forgotten-password-response').first() : jQuery();
  if (!$messageContainer.length) {
    $messageContainer = jQuery('<div class="oprc-forgotten-password-response"></div>');
    if ($form.length) {
      $form.before($messageContainer);
    } else if ($container && $container.length) {
      $container.prepend($messageContainer);
    }
  }

  var isProcessing = false;
  var $submitButtons = $form.find('button[type="submit"], input[type="submit"], input[type="image"], .cssButton');

  $form.off('submit.oprcForgotten').on('submit.oprcForgotten', function(event)
    {
      if (event && typeof event.preventDefault === 'function') {
        event.preventDefault();
      }

      if (isProcessing) {
        return false;
      }

      isProcessing = true;

      $submitButtons.prop('disabled', true).addClass('is-processing');
      $form.attr('aria-busy', 'true');

      var processingMessage = oprcGetForgottenPasswordMessage('processing', 'Processing…');
      var successMessageFallback = oprcGetForgottenPasswordMessage(
        'success',
        'If the email address you entered matches an account, we\'ll email you a link to reset your password.'
      );

      oprcForgottenPasswordShowMessage($messageContainer, 'information', processingMessage);

      var ajaxUrl = $form.attr('action') || '';
      var method = ($form.attr('method') || 'post').toUpperCase();
      var formData = $form.serializeArray();
      var serializedQuery = jQuery.param(formData);
      if (!/(?:^|&)action=/.test(serializedQuery) && typeof ajaxUrl === 'string' && ajaxUrl.indexOf('action=') === -1) {
        formData.push({ name: 'action', value: 'process' });
      }

      var requestData = jQuery.param(formData);
      var targetUrl = typeof ajaxUrl === 'string' && ajaxUrl.length ? ajaxUrl : window.location.href;
      var hashIndex = targetUrl.indexOf('#');
      if (hashIndex !== -1) {
        targetUrl = targetUrl.substring(0, hashIndex);
      }

      if (targetUrl.indexOf('action=') === -1) {
        targetUrl += (targetUrl.indexOf('?') === -1 ? '?' : '&') + 'action=process';
      }

      if (targetUrl.indexOf('ajaxRequest=1') === -1) {
        targetUrl += (targetUrl.indexOf('?') === -1 ? '?' : '&') + 'ajaxRequest=1';
      }

      jQuery.ajax({
        url: targetUrl,
        type: method === 'GET' ? 'GET' : 'POST',
        data: requestData,
        dataType: 'json'
      })
      .done(function(response)
        {
          var isSuccess = response && response.status === 'success';
          var message = response && typeof response.message === 'string' ? response.message : '';

          if (!message.length) {
            message = isSuccess
              ? successMessageFallback
              : oprcGetForgottenPasswordMessage('error', 'We were unable to process your request. Please try again.');
          }

          if (!message.length) {
            message = successMessageFallback;
          }

          oprcForgottenPasswordShowMessage($messageContainer, isSuccess ? 'success' : 'error', message);

          if (isSuccess) {
            $form.trigger('reset');
          }
        }
      )
      .fail(function(jqXHR)
        {
          var message = '';
          if (jqXHR && jqXHR.responseJSON && typeof jqXHR.responseJSON.message === 'string') {
            message = jqXHR.responseJSON.message;
          } else if (jqXHR && typeof jqXHR.responseText === 'string') {
            message = jQuery.trim(jqXHR.responseText);
          }

          if (!message.length) {
            message = oprcGetForgottenPasswordMessage('error', 'We were unable to process your request. Please try again.');
          }

          if (!message.length) {
            message = successMessageFallback;
          }

          oprcForgottenPasswordShowMessage($messageContainer, 'error', message);
        }
      )
      .always(function()
        {
          isProcessing = false;
          $submitButtons.prop('disabled', false).removeClass('is-processing');
          $form.removeAttr('aria-busy');
        }
      );

      return false;
    }
  );
}

function oprcInitForgottenPasswordModal()
{
  var $link = jQuery('#forgottenPasswordLink a');
  if (!$link.length || typeof $link.fancybox !== 'function') {
    return;
  }

  window.oprcForgottenPasswordMessages = null;

  $link.each(function()
    {
      var $element = jQuery(this);
      var href = $element.attr('href');
      var normalized = oprcNormalizeForgottenPasswordHref(href);
      if (normalized && normalized !== href) {
        $element.attr('href', normalized);
      }
    }
  );

  $link.off('click.fb-start');

  $link.fancybox(
    {
      autoSize: true,
      closeClick: true,
      openEffect: 'fade',
      closeEffect: 'fade',
      closeClick: false,
      type: 'ajax',
      maxWidth: 878,
      padding: 0,
      beforeLoad: function()
      {
        if (typeof this.href === 'string') {
          this.href = oprcNormalizeForgottenPasswordHref(this.href);
        }

        if (typeof oprcShowProcessingOverlay === 'function') {
          oprcShowProcessingOverlay();
        }
      },
      ajax: {
        dataFilter: function(data)
        {
          var filtered = data;

          try {
            var $wrapper = jQuery('<div></div>').append(jQuery.trim(data));
            var $content = $wrapper.find('#passwordForgotten').first();

            if (!$content.length) {
              var $form = $wrapper.find('form[name="password_forgotten"], form#password_forgotten, form#passwordForgotten').first();
              if ($form.length) {
                $content = $form.closest('.centerColumn');
                if (!$content.length) {
                  $content = $form;
                }
              }
            }

            if ($content.length) {
              var $modalWrapper = jQuery('<div class="oprc-forgotten-password-modal"></div>');
              $modalWrapper.append($content);
              filtered = $modalWrapper.html();
            }
          } catch (err) {
            filtered = data;
          }

          return filtered;
        }
      },
      afterShow: function()
      {
        var $modalContent = jQuery('.fancybox-inner');
        oprcBindForgottenPasswordForm($modalContent);

        if (typeof oprcHideProcessingOverlay === 'function') {
          oprcHideProcessingOverlay();
        }
      },
      onCancel: function()
      {
        if (typeof oprcHideProcessingOverlay === 'function') {
          oprcHideProcessingOverlay();
        }
      },
      afterClose: function()
      {
        if (typeof oprcHideProcessingOverlay === 'function') {
          oprcHideProcessingOverlay();
        }
      }
    }
  );
}

var oprcAddressLookupModule = (function($) {
  var selectors = {
    trigger: '.js-oprc-address-lookup-trigger',
    results: '.js-oprc-address-lookup-results'
  };

  var state = {
    isRequestRunning: false,
    selectIdCounter: 0
  };

  function getMessages()
  {
    if (typeof oprcAddressLookupMessages === 'object' && oprcAddressLookupMessages !== null) {
      return oprcAddressLookupMessages;
    }

    return {};
  }

  function getMessage(key, fallback)
  {
    var messages = getMessages();
    if (messages.hasOwnProperty(key) && typeof messages[key] === 'string') {
      return messages[key];
    }

    return typeof fallback === 'undefined' ? '' : fallback;
  }

  function getEndpoint()
  {
    if (typeof ajaxAddressLookupURL === 'string') {
      return ajaxAddressLookupURL;
    }

    return '';
  }

  function getProviderTitle()
  {
    if (typeof oprcAddressLookupProviderTitle === 'string') {
      return oprcAddressLookupProviderTitle;
    }

    return '';
  }

  function setBusy($container, isBusy)
  {
    if (!$container || !$container.length) {
      return;
    }

    $container.attr('aria-busy', isBusy ? 'true' : 'false');
  }

  function toggleResultsField($container, shouldShow)
  {
    if (!$container || !$container.length) {
      return;
    }

    var $field = $container.closest('.address-form-field--postcode-results');
    if ($field.length) {
      $field.toggleClass('is-visible', !!shouldShow);
    }
  }

  function showMessage($container, message, type)
  {
    if (!$container || !$container.length) {
      return;
    }

    setBusy($container, false);

    var hasMessage = typeof message === 'string' && message.length > 0;
    toggleResultsField($container, hasMessage);

    if (!hasMessage) {
      $container.empty();
      return;
    }

    var classes = 'disablejAlert alert';
    var messageClass = 'messageStackCaution';

    if (type === 'error') {
      classes += ' validation';
      messageClass = 'messageStackError';
    } else if (type === 'success') {
      classes += ' success';
      messageClass = 'messageStackSuccess';
    } else if (type === 'info') {
      classes += ' information';
      messageClass = 'messageStackCaution';
    }

    var $message = $('<div></div>').addClass(classes).append(
      $('<div></div>').addClass(messageClass).text(message)
    );

    $container.empty().append($message);
  }

  function showLoading($container)
  {
    if (!$container || !$container.length) {
      return;
    }

    setBusy($container, true);
    toggleResultsField($container, true);

    var loadingMessage = getMessage('loading', 'Loading ...');
    var $message = $('<div></div>').addClass('oprc-address-lookup__status').text(loadingMessage);
    $container.empty().append($message);
  }

  function gatherContext($form)
  {
    var contextFields = ['zone_country_id', 'country', 'country_id', 'state', 'zone_id', 'city', 'suburb', 'street_address'];
    var context = {};

    $.each(contextFields, function(index, name) {
      var $elements = $form.find('[name="' + name + '"]');
      if (!$elements.length) {
        return;
      }

      if ($elements.length > 1) {
        var value = '';
        $elements.each(function() {
          var $element = $(this);
          if ($element.is(':checkbox') || $element.is(':radio')) {
            if ($element.is(':checked')) {
              value = $element.val();
              return false;
            }
          } else {
            var candidate = $.trim($element.val());
            if (candidate.length && !value.length) {
              value = candidate;
            }
          }
        });
        context[name] = value;
      } else {
        var $element = $elements.eq(0);
        if ($element.is(':checkbox') || $element.is(':radio')) {
          context[name] = $element.is(':checked') ? $element.val() : '';
        } else {
          context[name] = $.trim($element.val());
        }
      }
    });

    return context;
  }

  function sortFieldEntries(fields)
  {
    var entries = [];
    $.each(fields, function(name, value) {
      entries.push({ name: name, value: value });
    });

    entries.sort(function(a, b) {
      if (a.name === 'zone_country_id' && b.name !== 'zone_country_id') {
        return -1;
      }
      if (b.name === 'zone_country_id' && a.name !== 'zone_country_id') {
        return 1;
      }

      return 0;
    });

    return entries;
  }

  function applyFields($form, fields)
  {
    if (!$form || !$form.length) {
      return;
    }

    var entries = sortFieldEntries(fields);

    $.each(entries, function(index, entry) {
      var name = entry.name;
      var value = entry.value;
      var $elements = $form.find('[name="' + name + '"]');

      if (!$elements.length) {
        return;
      }

      $elements.each(function() {
        var $element = $(this);
        if ($element.is(':checkbox') || $element.is(':radio')) {
          $element.prop('checked', $element.val() == value);
        } else {
          $element.val(value);
        }

        $element.trigger('change');
      });
    });

    if (fields.hasOwnProperty('street_address')) {
      var $street = $form.find('[name="street_address"]');
      if ($street.length) {
        $street.eq(0).focus();
      }
    }
  }

  function renderResults($container, suggestions, $form, provider)
  {
    if (!$container || !$container.length) {
      return;
    }

    setBusy($container, false);
    toggleResultsField($container, true);

    if (!suggestions || !suggestions.length) {
      showMessage($container, getMessage('noResults', ''), 'info');
      return;
    }

    var selectId = 'oprc-address-lookup-select-' + (++state.selectIdCounter);
    var labelText = getMessage('label', 'Select Address:');
    var $label = $();
    if (labelText && labelText.length) {
      $label = $('<label></label>')
        .addClass('oprc-address-lookup__label nmx-fw')
        .attr('for', selectId)
        .text(labelText);
    }

    var $select = $('<select></select>')
      .addClass('oprc-address-lookup__select nmx-fw')
      .attr('id', selectId);
    var placeholder = getMessage('placeholder', '');
    var placeholderText = placeholder.length ? placeholder : getMessage('heading', '');
    if (!placeholderText.length && labelText && labelText.length) {
      placeholderText = labelText;
    }
    if (!placeholderText.length) {
      placeholderText = 'Select an address';
    }

    $select.append(
      $('<option></option>')
        .attr({ value: '', selected: 'selected', disabled: 'disabled' })
        .text(placeholderText)
    );

    $.each(suggestions, function(index, suggestion) {
      if (!suggestion || typeof suggestion.label !== 'string' || suggestion.label === '') {
        return;
      }

      var $option = $('<option></option>')
        .attr('value', index)
        .text(suggestion.label);
      $option.data('addressFields', suggestion.fields || {});
      $select.append($option);
    });

    if ($select.children('option').length <= 1) {
      showMessage($container, getMessage('noResults', ''), 'info');
      return;
    }

    var providerTemplate = getMessage('provider', '');
    var providerTitle = '';
    if (provider && typeof provider.title === 'string' && provider.title.length) {
      providerTitle = provider.title;
    } else {
      providerTitle = getProviderTitle();
    }

    $container.data('oprcAddressLookupForm', $form);
    $container.empty();

    if ($label && $label.length) {
      $container.append($label);
    }

    $container.append($select);

    if (providerTemplate && providerTitle) {
      $container.append(
        $('<p></p>').addClass('oprc-address-lookup__provider').text(providerTemplate.replace('%s', providerTitle))
      );
    }
  }

  function findResultsContainer($trigger, $form)
  {
    var $results = $();

    if ($trigger && $trigger.length) {
      var $control = $trigger.closest('.oprc-address-lookup__control');
      if ($control.length) {
        $results = $control.nextAll(selectors.results).first();
        if (!$results.length) {
          $results = $control.parent().find(selectors.results).first();
        }
        if (!$results.length) {
          var $postcodeField = $control.closest('.address-form-field--postcode');
          if ($postcodeField.length) {
            $results = $postcodeField.nextAll('.address-form-field--postcode-results').find(selectors.results).first();
          }
        }
      }
    }

    if (!$results.length && $form && $form.length) {
      $results = $form.find(selectors.results).first();
    }

    if (!$results.length) {
      $results = $(selectors.results).first();
    }

    return $results;
  }

  function performLookup($trigger)
  {
    if (!$trigger || !$trigger.length) {
      return;
    }

    if (state.isRequestRunning) {
      return;
    }

    var $form = $trigger.closest('form');
    if (!$form.length) {
      return;
    }

    var $results = findResultsContainer($trigger, $form);
    if (!$results.length) {
      return;
    }

    var postalCodeField = $form.find('[name="postcode"]');
    var postalCode = $.trim(postalCodeField.val());

    if (!postalCode.length) {
      showMessage($results, getMessage('missingPostalCode', ''), 'error');
      return;
    }

    var endpoint = getEndpoint();
    if (!endpoint.length) {
      showMessage($results, getMessage('unavailable', getMessage('error', '')), 'error');
      return;
    }

    state.isRequestRunning = true;
    $trigger.prop('disabled', true);
    showLoading($results);

    var payload = {
      postal_code: postalCode,
      context: gatherContext($form)
    };

    $.ajax({
      url: endpoint,
      type: 'POST',
      dataType: 'json',
      data: payload
    }).done(function(response) {
      if (response && response.success) {
        renderResults($results, response.addresses || [], $form, response.provider || {});
      } else {
        var message = getMessage('error', getMessage('unavailable', ''));
        if (response && typeof response.message === 'string' && response.message.length) {
          message = response.message;
        }
        showMessage($results, message, 'error');
      }
    }).fail(function() {
      showMessage($results, getMessage('error', getMessage('unavailable', '')), 'error');
    }).always(function() {
      state.isRequestRunning = false;
      $trigger.prop('disabled', false);
    });
  }

  function handleSelection(event)
  {
    var $target = $(this);
    var $option;

    if ($target.is('select')) {
      var selectedIndex = $target.prop('selectedIndex');
      if (typeof selectedIndex !== 'number' || selectedIndex <= 0) {
        return;
      }
      $option = $target.find('option').eq(selectedIndex);
    } else {
      event.preventDefault();
      $option = $target.closest('.oprc-address-lookup__option');
    }

    if (!$option || !$option.length) {
      return;
    }

    var fields = $option.data('addressFields') || {};
    var $results = $target.closest(selectors.results);
    var $form = $results.data('oprcAddressLookupForm');

    if (!$form || !$form.length) {
      $form = $target.closest('form');
    }

    if ($target.is('select')) {
      $target.prop('selectedIndex', 0);
    }

    applyFields($form, fields);
    // Clear the results container - passing an empty message clears the container
    // This prevents a success message from interfering with form submission
    showMessage($results, '', 'success');
  }

  function init()
  {
    var endpoint = getEndpoint();

    if (!endpoint.length) {
      return;
    }

    $(document).off('click.oprcAddressLookup', selectors.trigger).on('click.oprcAddressLookup', selectors.trigger, function(event) {
      event.preventDefault();
      performLookup($(this));
    });

    $(document)
      .off('change.oprcAddressLookupSelect', '.oprc-address-lookup__select')
      .on('change.oprcAddressLookupSelect', '.oprc-address-lookup__select', handleSelection);
  }

  return {
    init: init
  };
})(jQuery);

function oprcRememberShippingContainerTemplate() {
  var $container = jQuery('#shippingMethodContainer');

  if (!$container.length) {
    return;
  }

  oprcRestoreShippingContainerStructure();

  var $shippingMethods = $container.find('#shippingMethods');

  if (!$shippingMethods.length) {
    oprcUpdateShippingMethodsPanelState();
    return;
  }

  var $clone = $container.clone();
  $clone.find('#shippingMethods').empty();

  var templateHtml = $clone.html();

  if (typeof templateHtml !== 'string') {
    return;
  }

  if (jQuery.trim(templateHtml).length === 0) {
    return;
  }

  oprcShippingContainerTemplateHtml = templateHtml;
}

function oprcRestoreShippingContainerStructure() {
  var $container = jQuery('#shippingMethodContainer');

  if (!$container.length) {
    return;
  }

  if ($container.find('#shippingMethods').length > 0) {
    return;
  }

  if (typeof oprcShippingContainerTemplateHtml === 'string' && jQuery.trim(oprcShippingContainerTemplateHtml).length > 0) {
    $container.html(oprcShippingContainerTemplateHtml);
    return;
  }

  if ($container.find('#shippingMethods').length === 0) {
    $container.append('<div id="shippingMethods"></div>');
  }
}

function oprcRenderNoShippingMethodsMessage() {
  var $methods = jQuery('#shippingMethods');

  if (!$methods.length) {
    return;
  }

  var message = (typeof oprcNoShippingAvailableMessage === 'string')
    ? jQuery.trim(oprcNoShippingAvailableMessage)
    : '';

  if (message.length === 0) {
    $methods.empty();
    return;
  }

  $methods.html('<div class="oprc-no-shipping-message">' + message + '</div>');
}

function oprcHasShippingMethodsContent($methods) {
  if (!$methods || !$methods.length) {
    return false;
  }

  var $clone = $methods.clone();
  $clone.find('#oprc-processing-overlay').remove();

  var hasOptionElements = $clone.find('.shipping-method, .custom-control, #freeShip, #defaultSelected').length > 0;
  if (hasOptionElements) {
    return true;
  }

  var textContent = jQuery.trim($clone.text());
  return textContent.length > 0;
}

function oprcUpdateShippingMethodsPanelState() {
  var $panel = jQuery('.oprc-shipping-methods-panel');

  if (!$panel.length) {
    return;
  }

  var hasMessages = $panel.find('.disablejAlert').filter(function() {
    return jQuery.trim(jQuery(this).text()).length > 0;
  }).length > 0;

  var $methods = jQuery('#shippingMethods');
  var hasShippingContent = oprcHasShippingMethodsContent($methods);

  if (hasMessages || hasShippingContent) {
    $panel.removeClass('is-empty');
  } else {
    $panel.addClass('is-empty');
  }
}

function blockPage(shouldReloadQuotes, cartChanged)
{
  if (typeof shouldReloadQuotes === 'undefined') {
    shouldReloadQuotes = false;
  }

  if (typeof cartChanged === 'undefined') {
    cartChanged = false;
  }

  oprcProcessingOverlayShouldReloadQuotes = !!shouldReloadQuotes;
  oprcProcessingOverlayCartChanged = !!cartChanged;

  if (typeof oprcShowProcessingOverlay === 'function') {
    oprcShowProcessingOverlay();
  }
}

function unblockPage() {
  var shouldReload = oprcProcessingOverlayShouldReloadQuotes && oprcAJaxShippingQuotes == true;
  var cartChanged = oprcProcessingOverlayCartChanged;

  oprcProcessingOverlayShouldReloadQuotes = false;
  oprcProcessingOverlayCartChanged = false;

  oprcHideProcessingOverlay(function() {
    if (shouldReload) {
      ajaxLoadShippingQuote(cartChanged);
    }
  });
}

function oprcGetSelectedShippingValue() {
  var $selectedShipping = jQuery('input[name="shipping"]:checked');

  if ($selectedShipping.length === 0) {
    return null;
  }

  return $selectedShipping.val();
}

function oprcSyncSelectedShippingSelection() {
  oprcLastSubmittedShippingMethod = oprcGetSelectedShippingValue();
}

function oprcNormalizeHtmlMarkup(html) {
  if (typeof html !== 'string') {
    return '';
  }

  return jQuery.trim(html.replace(/>\s+</g, '><'));
}

function oprcExtractPaymentContainer($root) {
  if (!$root || !$root.length) {
    return null;
  }

  var $candidate = $root;
  if (!$candidate.is('#paymentMethodContainer')) {
    $candidate = $root.find('#paymentMethodContainer').first();
  }

  if (!$candidate.length) {
    return null;
  }

  return {
    element: $candidate,
    innerHtml: typeof $candidate.html() === 'string' ? $candidate.html() : ''
  };
}

function oprcParsePaymentContainerHtml(html) {
  if (typeof html !== 'string') {
    return null;
  }

  var trimmed = jQuery.trim(html);
  if (!trimmed) {
    return null;
  }

  var $wrapper = jQuery('<div></div>').html(trimmed);
  return oprcExtractPaymentContainer($wrapper);
}

function oprcHasPaymentContainerContentChanged($container, htmlContent, $replacementElement) {
  if ($replacementElement && $replacementElement.length && $container.length && $container[0] && typeof $container[0].outerHTML === 'string') {
    var $outerWrapper = jQuery('<div></div>');
    $outerWrapper.append($replacementElement.clone(true));

    var normalizedCurrentOuter = oprcNormalizeHtmlMarkup($container[0].outerHTML || '');
    var normalizedReplacementOuter = oprcNormalizeHtmlMarkup($outerWrapper.html());

    if (normalizedReplacementOuter.length > 0) {
      return normalizedReplacementOuter !== normalizedCurrentOuter;
    }
  }

  var currentHtml = typeof $container.html() === 'string' ? $container.html() : '';
  var normalizedCurrent = oprcNormalizeHtmlMarkup(currentHtml);

  if (typeof htmlContent === 'string') {
    return oprcNormalizeHtmlMarkup(htmlContent) !== normalizedCurrent;
  }

  if (htmlContent === null || typeof htmlContent === 'undefined') {
    return normalizedCurrent.length > 0;
  }

  var $wrapper = jQuery('<div></div>');
  $wrapper.append(jQuery(htmlContent).clone(true));

  return oprcNormalizeHtmlMarkup($wrapper.html()) !== normalizedCurrent;
}

function oprcProcessScriptsAndDispatch(container) {
  var scripts = container.find('script');
  var pending = 0;
  var dispatched = false;
  var processedScripts = window.oprcProcessedScripts;

  oprcResetManagedEventListeners();
  oprcClearPaymentPreSubmitCallbacks();

  if (!processedScripts || typeof processedScripts !== 'object') {
    processedScripts = {};
  }

  if (typeof processedScripts.inline !== 'object' || processedScripts.inline === null) {
    processedScripts.inline = {};
  }

  if (typeof processedScripts.external !== 'object' || processedScripts.external === null) {
    processedScripts.external = {};
  }

  window.oprcProcessedScripts = processedScripts;

  function triggerOnce() {
    if (dispatched) {
      return;
    }
    dispatched = true;
    oprcWaitForPaymentContainerIdle(function() {
      oprcQueuePaymentReloadEvent();
    });
  }

  scripts.each(function() {
    var script = this;
    var type = script.getAttribute('type');
    var isModule = type === 'module';
    var isJavaScriptType = !type || /^(?:text|application)\/(?:java|ecma)script$/i.test(type) || isModule;

    if (!isJavaScriptType) {
      return;
    }

    var allowMultiple = script.hasAttribute('data-oprc-allow-multiple');
    if (allowMultiple) {
      var allowMultipleValue = script.getAttribute('data-oprc-allow-multiple');
      if (typeof allowMultipleValue === 'string' && allowMultipleValue.length > 0) {
        allowMultiple = allowMultipleValue.toLowerCase() !== 'false';
      }
    }
    var scriptSrcAttr = script.getAttribute('src');
    var hasSrc = typeof scriptSrcAttr === 'string' && scriptSrcAttr.trim().length > 0;
    var scriptSrc = '';
    var scriptContent = '';
    var trimmedContent = '';
    var containsReloadListener = false;

    if (hasSrc) {
      scriptSrc = script.src;

      if (!allowMultiple) {
        if (Object.prototype.hasOwnProperty.call(processedScripts.external, scriptSrc)) {
          return;
        }
      }
    } else {
      scriptContent = script.textContent || script.innerText || '';
      trimmedContent = scriptContent.trim();
      containsReloadListener = /onePageCheckoutReloaded/.test(trimmedContent);

      if (!allowMultiple && containsReloadListener && trimmedContent.length > 0) {
        if (Object.prototype.hasOwnProperty.call(processedScripts.inline, trimmedContent)) {
          return;
        }
      }
    }

    var newScript = document.createElement('script');
    Array.prototype.forEach.call(script.attributes, function(attr) {
      newScript.setAttribute(attr.name, attr.value);
    });

    if (hasSrc) {
      if (!allowMultiple) {
        processedScripts.external[scriptSrc] = true;
      }

      pending++;
      newScript.onload = function() {
        pending--;
        if (pending === 0) {
          triggerOnce();
        }
      };
      newScript.onerror = function() {
        if (!allowMultiple) {
          delete processedScripts.external[scriptSrc];
        }
        pending--;
        if (pending === 0) {
          triggerOnce();
        }
      };
      newScript.src = scriptSrc;
    } else {
      if (!allowMultiple && containsReloadListener && trimmedContent.length > 0) {
        processedScripts.inline[trimmedContent] = true;
      }
      newScript.textContent = scriptContent;
    }

    if (!hasSrc) {
      oprcCaptureEventListeners(function() {
        document.body.appendChild(newScript);
      });
    } else {
      document.body.appendChild(newScript);
    }
  });

  if (pending === 0) {
    triggerOnce();
  }
}

function oprcReplacePaymentMethodContainer(htmlContent, options) {
  if (typeof options !== 'object' || options === null) {
    options = {};
  }

  var $replacementElement = options.replacementElement || null;
  var $container = jQuery('#paymentMethodContainer');
  var hasChanged = true;

  if (!$container.length) {
    return;
  }

  hasChanged = oprcHasPaymentContainerContentChanged($container, htmlContent, $replacementElement);

  if (!hasChanged) {
    return;
  }

  if ($replacementElement && $replacementElement.length) {
    var $newContainer = $replacementElement.first().clone(true);
    if ($newContainer.attr('id') !== 'paymentMethodContainer') {
      $newContainer.attr('id', 'paymentMethodContainer');
    }
    $container.replaceWith($newContainer);
    $container = $newContainer;
  } else if (typeof htmlContent === 'string') {
    $container.html(htmlContent);
  } else if (htmlContent === null || typeof htmlContent === 'undefined') {
    $container.empty();
  } else {
    $container.empty().append(jQuery(htmlContent).clone(true));
  }

  $container = jQuery('#paymentMethodContainer');

  oprcProcessScriptsAndDispatch($container);
  oprcUpdatePaymentMethodSelectionState();
}

function oprcInitChangeAddressModal(forceReinit) {
  if (typeof forceReinit === 'undefined') {
    forceReinit = false;
  }

  if (!forceReinit && fancyboxEnabled) {
    // Even if already enabled, we need to check if the elements exist and have fancybox bound
    var $shippingLink = jQuery('#linkCheckoutShippingAddr');
    var $paymentLink = jQuery('#linkCheckoutPaymentAddr');
    
    // Check if links exist but don't have fancybox bound (e.g., after AJAX update)
    var needsReinit = false;
    if ($shippingLink.length && !$shippingLink.data('fancybox')) {
      needsReinit = true;
    }
    if ($paymentLink.length && !$paymentLink.data('fancybox')) {
      needsReinit = true;
    }
    
    if (!needsReinit) {
      return;
    }
  }

  initiateFancyBox('#linkCheckoutShippingAddr');
  initiateFancyBox('#linkCheckoutPaymentAddr');
  fancyboxEnabled = true;
}

var oprcBrowserInfo = (function() {
  var ua = window.navigator.userAgent;
  var msie = /MSIE|Trident/i.test(ua);
  var match = ua.match(/(?:MSIE |rv:)(\d+(?:\.\d+)?)/i);

  return {
    isIE: msie,
    ieVersion: match ? parseFloat(match[1]) : NaN
  };
})();

jQuery(document).ready(function()
  {
    var oprcSubmitPlacement = (function()
      {
        var submitSelector = '#js-submit';
        var submitHomeSelector = '#js-submit-home';
        var mobileSubmitTargetSelector = '#oprcMobileSubmitTarget';

        if (typeof window.matchMedia !== 'function') {
          return {
            refresh: function() {}
          };
        }

        var mobileLayoutQuery = window.matchMedia('(max-width: ' + widthMin + 'px)');

        function moveSubmit(isMobileLayout)
        {
          var $submit = jQuery(submitSelector);
          var $home = jQuery(submitHomeSelector);

          if (!$submit.length || !$home.length) {
            return;
          }

          if (isMobileLayout) {
            var $mobileTarget = jQuery(mobileSubmitTargetSelector);

            if ($mobileTarget.length && !$mobileTarget.find(submitSelector).length) {
              $mobileTarget.append($submit);
            }
          } else if (!$home.next(submitSelector).length) {
            $home.after($submit);
          }
        }

        function handleLayoutChange(event)
        {
          moveSubmit(event.matches);
        }

        moveSubmit(mobileLayoutQuery.matches);

        if (typeof mobileLayoutQuery.addEventListener === 'function') {
          mobileLayoutQuery.addEventListener('change', handleLayoutChange);
        } else if (typeof mobileLayoutQuery.addListener === 'function') {
          mobileLayoutQuery.addListener(handleLayoutChange);
        }

        return {
          refresh: function()
          {
            moveSubmit(mobileLayoutQuery.matches);
          }
        };
      }());

    jQuery(document).off('ajaxStop.oprc').on('ajaxStop.oprc', function()
      {
        // only unblock ajax if not final submit, used a selector from oprc_confirmation that will most likely always be present on that page
        if (jQuery('#checkoutConfirmDefaultBillingAddress').length == 0) {
          unblockPage();
        }

        oprcSubmitPlacement.refresh();
      }
    );

    jQuery("#navBreadCrumb").addClass('nmx-wrapper');

    displaySessionExpiredMessage();

    // BEGIN CHECKOUT WIDE EVETNS
    if (oprcAJAXErrors == 'true') {
      jQuery(document).off('ajaxError.oprc').on('ajaxError.oprc', function(e, xhr, settings, exception)
        {
          if (exception != 'abort') {
            if (window.console && typeof window.console.error === 'function') {
              console.error('Error in request to ' + settings.url + ':', xhr.responseText);
            }
          }
        }
      );
    }

    if (typeof oprcType !== 'undefined' && oprcType === 'paypalrest') {
      submitCheckout();
      oprcType = '';
    }

    // initial process
    prepareMessages();
    reconfigureSideMenu();
    savedCardsLayout();
    oprcUpdatePaymentMethodSelectionState();
    jQuery(document).off('change.oprcPaymentState', '#paymentModules input[name="payment"]').on('change.oprcPaymentState', '#paymentModules input[name="payment"]', function()
      {
        oprcUpdatePaymentMethodSelectionState();
      }
    );
    oprcRememberShippingContainerTemplate();
    if (oprcAJaxShippingQuotes == true) ajaxLoadShippingQuote(true);
    oprcUpdateShippingMethodsPanelState();

    if (typeof oprcAddressLookupEnabled !== 'undefined' && oprcAddressLookupEnabled === 'true') {
      oprcAddressLookupModule.init();

      if (typeof document.addEventListener === 'function') {
        document.addEventListener('onePageCheckoutReloaded', function() {
          oprcAddressLookupModule.init();
        });
      }
    }

    if (typeof document.addEventListener === 'function') {
      document.addEventListener('onePageCheckoutReloaded', function() {
        oprcUpdatePaymentMethodSelectionState();
      });
      document.addEventListener('onePageCheckoutReloaded', function() {
        oprcInitForgottenPasswordModal();
      });
    }

    oprcSyncSelectedShippingSelection();

    // Start the session keepalive to prevent session timeout during checkout
    oprcStartKeepalive();

    //force an extra refresh on the order total box.  This is nessisary when cart contents have changed.
    if(recalculate_shipping_cost == '1') {
      var url_params = ajaxOrderTotalURL.match(/\?./)? '&action=refresh': '?action=refresh';
      jQuery.get(ajaxOrderTotalURL + url_params, function(data)
        {
          var shopBagWrapper = jQuery(data).find('#shopBagWrapper').html();
          jQuery('#shopBagWrapper').html(shopBagWrapper);
        }
      );
    }

  if (oprcRemoveCheckout != '') {
    // remove products from checkout
    jQuery(document).off('click.oprc', 'a.removeProduct').on('click.oprc', 'a.removeProduct', function()
      {
        var $link = jQuery(this);
        var href = $link.attr('href') || '';
        var idMatch = href.match(/product_id=([^&]+)/i);
        var productId = idMatch && idMatch[1] ? decodeURIComponent(idMatch[1]) : null;

        if (!productId) {
          window.location.assign(href);
          return false;
        }

        blockPage(true, true);
        clearMessages({ force: true });

        var requestData = jQuery('[name="checkout_payment"]').serialize();
        if (requestData.length) {
          requestData += '&';
        }
        requestData += 'request=ajax&product_id=' + encodeURIComponent(productId);

        jQuery.ajax({
          url: ajaxRemoveProductURL,
          method: 'POST',
          data: requestData,
          dataType: 'json'
        })
        .done(function(response) {
          if (response && typeof response.redirect_url !== 'undefined' && response.redirect_url) {
            unblockPage();
            window.location.replace(response.redirect_url);
            return;
          }

          oprcApplyShippingUpdateResponse(response, {
            shouldRefreshPaymentContainer: oprcIsTruthy(oprcRefreshPayment)
          });
          var refreshMarkup = '';
          if (response && typeof response.step3Html === 'string') {
            refreshMarkup += response.step3Html;
          }
          if (response && typeof response.orderTotalHtml === 'string') {
            refreshMarkup += response.orderTotalHtml;
          }
          if (refreshMarkup.length) {
            oprcRemoveCheckoutRefreshSelectors(refreshMarkup);
          }
          unblockPage();
        })
        .fail(function(xhr, status, error) {
          if (status !== 'abort') {
            var errorHtml = '';
            if (xhr && xhr.responseJSON && xhr.responseJSON.messagesHtml) {
              errorHtml = xhr.responseJSON.messagesHtml;
            } else if (xhr && xhr.responseText) {
              errorHtml = xhr.responseText;
            } else if (error) {
              errorHtml = error;
            }

            if (errorHtml) {
              if (typeof displayErrors === 'function') {
                displayErrors(errorHtml);
              } else {
                jQuery('.messageStackErrors').html(errorHtml);
              }
              if (typeof prepareMessages === 'function') {
                prepareMessages();
              }
            }
          }
          unblockPage();
        });

        return false;
      }
    );
  }

    jQuery(document).off('click.oprc', '.nmx-panel-head a').on('click.oprc', '.nmx-panel-head a', function()
      {
        var orderStepURL = jQuery(this).attr('href');
        var step = orderStepURL.match(/step=([0-9]+)/);
        if (step === null) {
          return true;
        }
        blockPage(false, false);
        jQuery.get(orderStepURL, function(data)
          {
            // login check
            oprcFetchLoginCheck().done(function(loginCheck)
              {
                if (parseInt(loginCheck) == 1) {
                  // step 3
                } else {
                  // step 1 or 2
                  switch(step[1]) {
                    case '1':
                      var onePageCheckout = jQuery(data).find('#onePageCheckout').html();
                      oprcLoginRegistrationRefreshSelectors(data);
                      jQuery('#onePageCheckout').replaceWith('<div id="onePageCheckout" class="centerColumn nmx-wrapper nmx nmx-plugin nmx-plugin--oprc nmx-cf">' + onePageCheckout + '</div>');
                      jQuery('#easyLogin').hide(function()
                        {
                          if (oprcHideRegistration) {
                            reconfigureLogin();
                          }
                          reconfigureSideMenu();
                        }
                      );
                      break;
                    case '2':
                      var onePageCheckout = jQuery(data).find('#onePageCheckout').html();
                      oprcLoginRegistrationRefreshSelectors(data);
                      jQuery('#onePageCheckout').replaceWith('<div id="onePageCheckout" class="centerColumn nmx-wrapper nmx nmx-plugin nmx-plugin--oprc nmx-cf">' + onePageCheckout + '</div>');
                      jQuery('#hideRegistration').hide(function()
                        {
                          if (oprcHideRegistration) {
                            reconfigureLogin();
                          }
                          reconfigureSideMenu();
                        }
                      );
                      break;
                    default:
                      // reload the checkout
                      window.location.replace(onePageCheckoutURL);
                  }
                }
              }
            );
          }
        );
        return false;
      }
    );

    // END CHECKOUT WIDE EVENTS
    // BEGIN NOT LOGGED IN EVENTS
    // get the URL parameters
    // use:
    // queryParameters['paramaterName'] = 'value';
    // location.search = jQuery.param(queryParameters);
    /*
  var queryParameters = {}, queryString = location.search.substring(1), re = /([^&=]+)=([^&]*)/g, m;

  while (m = re.exec(queryString)) {
    queryParameters[decodeURIComponent(m[1])] = decodeURIComponent(m[2]);
  }
  */
    if (oprcHideRegistration) {
      reconfigureLogin();
    }

    // login
    jQuery(document).off('submit.oprc', 'form[name="login"]').on('submit.oprc', 'form[name="login"]', function()
      {
        jQuery('#onePageCheckout input').css('border-color', '');
        jQuery('#onePageCheckout .validation').remove();
        jQuery('.missing').removeClass('missing');
        jQuery('.disablejAlert').remove();
        // remove error messages
        clearMessages({ force: true });
        blockPage(true, true);
        var $emailField = jQuery('input[name="email_address"]');
        var $passwordField = jQuery('input[name="password"]');
        var emailValue = jQuery.trim($emailField.val());
        var passwordValue = $passwordField.val();
        var emailMinLength = parseInt(oprcEmailMinLength, 10);
        var passwordMinLength = parseInt(oprcPasswordMinLength, 10);
        var requiredEmailLength = !isNaN(emailMinLength) && emailMinLength > 0 ? emailMinLength : 1;
        var requiredPasswordLength = !isNaN(passwordMinLength) && passwordMinLength > 0 ? passwordMinLength : 1;

        $emailField.val(emailValue);

        var emailIsValid = emailValue.length >= requiredEmailLength;
        var passwordIsValid = passwordValue.length >= requiredPasswordLength;

        if (emailIsValid && passwordIsValid) {
          var $form = jQuery(this);
          var loginRequest = jQuery.post($form.attr('action'), $form.serialize(), function(data)
            {
              var onePageCheckout = jQuery(data).find('#onePageCheckout').html();
              if (onePageCheckout == undefined) {
                setTimeout(function() {window.location = onePageCheckoutURL;}, 3000)
              } else {
                // check if logged in
                var loginCheckRequest = oprcFetchLoginCheck();

                loginCheckRequest.done(function(loginCheck)
                  {
                    if (parseInt(loginCheck) == 1) {
                      unblockPage();
                      window.location = onePageCheckoutURL;
                      return;
                    } else {
                      var $response = jQuery(data);
                      var $loginMarkup = $response.find('#oprc_login');

                      if ($loginMarkup.length) {
                        jQuery('#oprc_login').replaceWith($loginMarkup);
                      } else {
                        jQuery('#oprc_login').html($response.find('#oprc_login').html());
                      }

                      var $loginContainer = jQuery('#oprc_login');
                      if ($loginContainer.length) {
                        var $loginError = $loginContainer.find('.disablejAlert.loginError');
                        if ($loginError.length) {
                          $loginError.addClass('alert validation');
                        } else if (typeof oprcLoginValidationErrorMessage === 'string' && oprcLoginValidationErrorMessage.length) {
                          $loginContainer.find('input[name="password"]').after('<div class="disablejAlert alert validation loginError">' + oprcLoginValidationErrorMessage + '</div>');
                        }
                      }

                      reconfigureLogin();
                      if (typeof prepareMessages === 'function') {
                        prepareMessages();
                      }
                      oprcProcessingOverlayShouldReloadQuotes = false;
                      oprcProcessingOverlayCartChanged = false;
                      unblockPage();
                      return;
                    }
                  }
                );

                loginCheckRequest.fail(function()
                  {
                    oprcProcessingOverlayShouldReloadQuotes = false;
                    oprcProcessingOverlayCartChanged = false;
                    unblockPage();
                  }
                );

                loginCheckRequest.always(function()
                  {
                    oprcLoginRegistrationRefreshSelectors(data);
                    checkPageErrors();
                  }
                );
              }
            }
          );

          loginRequest.fail(function()
            {
              oprcProcessingOverlayShouldReloadQuotes = false;
              oprcProcessingOverlayCartChanged = false;
              unblockPage();
            }
          );
        } else {
          if (!emailIsValid) {
            $emailField.addClass('missing');
          }
          if (!passwordIsValid) {
            $passwordField.addClass('missing');
          }
          $passwordField.after('<div class="disablejAlert loginError">' + oprcLoginValidationErrorMessage + '</div>');
          // remove loading
          setTimeout(function()
            {
              unblockPage();
            }, 300
          );
        }
        return false;
      }
    );

    // registration
    jQuery(document).off('submit.oprc', 'form[name="create_account"]').on('submit.oprc', 'form[name="create_account"]', function()
      {
        jQuery('[name=create_account] *').removeClass('missing');
        jQuery('[name=create_account] .validation').remove();
        
        // Check if validation function exists
        if (typeof check_form_registration !== 'function') {
          console.error('check_form_registration function is not defined. Validation cannot proceed.');
          return false;
        }
        
        if (check_form_registration("create_account")) {
          // remove any existing message stack notices now that the form is valid
          clearMessages({ force: true });
          blockPage(true, true);
          var $registrationForm = jQuery(this);
          var registrationRequest = jQuery.post($registrationForm.attr('action'), $registrationForm.serialize(), function(data)
            {
              // check if logged in
              var loginCheckRequest = oprcFetchLoginCheck();

              loginCheckRequest.done(function(loginCheck)
                {
                  if (parseInt(loginCheck) == 1) {
                    unblockPage();
                    window.location = onePageCheckoutURL;
                    return;
                  } else {
                    var easyLogin = jQuery(data).find('#easyLogin').html();
                    jQuery('#easyLogin').html(easyLogin);
                    // checkGuestByDefault();
                    reconfigureLogin();
                    oprcProcessingOverlayShouldReloadQuotes = false;
                    oprcProcessingOverlayCartChanged = false;
                    unblockPage();
                  }
                }
              );

              loginCheckRequest.fail(function()
                {
                  oprcProcessingOverlayShouldReloadQuotes = false;
                  oprcProcessingOverlayCartChanged = false;
                  unblockPage();
                }
              );

              loginCheckRequest.always(function()
                {
                  oprcLoginRegistrationRefreshSelectors(data);
                  checkPageErrors();
                }
              );
            }
          );

          registrationRequest.fail(function()
            {
              oprcProcessingOverlayShouldReloadQuotes = false;
              oprcProcessingOverlayCartChanged = false;
              unblockPage();
            }
          );
        } else {
          // Validation failed - ensure errors are visible
          unblockPage();
          reconfigureLogin();
          scrollToValidationError();
          return false;
        }
        return false;
      }
    );

    if (oprcGuestAccountOnly == 'false') {
      jQuery(document).off('submit.oprc', 'form[name="hideregistration_register"]').on('submit.oprc', 'form[name="hideregistration_register"]', function()
        {
          jQuery.history.load("hideregistration_register");
          jQuery('#hideRegistration input').css('border-color', '');
          jQuery('#hideRegistration .validation').remove();
          jQuery('.missing').removeClass('missing');
          jQuery('.disablejAlert').remove();
          jQuery('input[name="cowoa-checkbox"]').val("false");
          // begin validation
          var error = false;
          var email_address_register = jQuery('form[name="hideregistration_register"] input[name="hide_email_address_register"]').val();
          if (!email_address_register.length > 0 || email_address_register.search(/@/) == -1 || email_address_register.search(/\./) == -1) {
            error = true;
            jQuery('form[name="hideregistration_register"] input[name="hide_email_address_register"]').addClass('missing');
            // add message next to label
            jQuery('form[name="hideregistration_register"] input[name="hide_email_address_register"]').after('<div class="disablejAlert alert validation">&nbsp;invalid email</div>');
          }
          if (oprcConfirmEmail == 'true') {
            var hide_email_address_confirm = jQuery('form[name="hideregistration_register"] input[name="hide_email_address_confirm"]').val();
            if (!hide_email_address_confirm.length > 0 || hide_email_address_confirm.search(/@/) == -1 || hide_email_address_confirm.search(/\./) == -1) {
              error = true;
              jQuery('form[name="hideregistration_register"] input[name="hide_email_address_confirm"]').addClass('missing');
              // add message next to label
              jQuery('form[name="hideregistration_register"] input[name="hide_email_address_confirm"]').after('<div class="disablejAlert alert validation">&nbsp;invalid email</div>');
            }
            if (email_address_register.length > 0 && hide_email_address_confirm.length > 0 && email_address_register != hide_email_address_confirm) {
              error = true;
              jQuery('form[name="hideregistration_register"] input[name="hide_email_address_register"]').addClass('missing');
              jQuery('form[name="hideregistration_register"] input[name="hide_email_address_confirm"]').addClass('missing');
              // add message next to label
              jQuery('form[name="hideregistration_register"] input[name="hide_email_address_confirm"]').after('<div class="disablejAlert alert validation">&nbsp;email mismatch</div>');
            }
          }
          if (!error) {
            var overlayShown = false;
            var hasPendingOrderStepsRequest = false;

            if (typeof oprcShowProcessingOverlay === 'function') {
              oprcShowProcessingOverlay();
              overlayShown = true;
            }

            var request = jQuery.post(ajaxAccountCheckURL, jQuery('[name="hideregistration_register"]').serialize());

            if (request && typeof request.done === 'function') {
              request.done(function(data)
                {
                  if (data == '0') {
                    if (oprcConfirmEmail == 'true') {
                      var email_address_confirm = jQuery('form[name="hideregistration_register"] input[name="hide_email_address_confirm"]').val();
                    }
                    /*
         var password_register = jQuery('form[name="hideregistration_register"] input[name="hide_password_register"]').val();
         var password_confirmation = jQuery('form[name="hideregistration_register"] input[name="hide_password_confirmation"]').val();
         */
                    jQuery('#hideRegistration').hide(function()
                      {
                        jQuery('#easyLogin').show();
                        jQuery('#easyLogin').css('visibility', 'visible');
                        jQuery('form[name="create_account"] input[name="email_address_register"]').val(email_address_register);
                        if (oprcConfirmEmail == 'true') {
                          jQuery('form[name="create_account"] input[name="email_address_confirm"]').val(email_address_confirm);
                        }
                        //jQuery('form[name="create_account"] input[name="password_register"]').val(password_register);
                        //jQuery('form[name="create_account"] input[name="password_confirmation"]').val(password_confirmation);
                        scrollToRegistration();
                      }
                    );
                    if (oprcOrderSteps == 'true') {
                      hasPendingOrderStepsRequest = true;
                      var orderStepsRequest = jQuery.get(onePageCheckoutURL + '&hideregistration=true');

                      if (orderStepsRequest && typeof orderStepsRequest.done === 'function') {
                        orderStepsRequest.done(function(orderStepsData)
                          {
                            updateOrderSteps(orderStepsData);
                          }
                        );
                        oprcAttachDeferredCallback(orderStepsRequest, function()
                          {
                            hasPendingOrderStepsRequest = false;
                            if (overlayShown && typeof oprcHideProcessingOverlay === 'function') {
                              oprcHideProcessingOverlay();
                              overlayShown = false;
                            }
                          }
                        );
                      } else {
                        hasPendingOrderStepsRequest = false;
                        if (overlayShown && typeof oprcHideProcessingOverlay === 'function') {
                          oprcHideProcessingOverlay();
                          overlayShown = false;
                        }
                      }
                    }
                  } else {
                    var $registrationForm = jQuery('form[name="hideregistration_register"]');
                    $registrationForm.find('input[name="hide_email_address_register"]').addClass('missing');
                    showRegistrationEmailExistsError(oprcEntryEmailAddressErrorExists);
                  }
                }
              );

              if (typeof request.fail === 'function') {
                request.fail(function()
                  {
                    hasPendingOrderStepsRequest = false;
                  }
                );
              }

              if (typeof request.always === 'function') {
                request.always(function()
                  {
                    if (!hasPendingOrderStepsRequest && overlayShown && typeof oprcHideProcessingOverlay === 'function') {
                      oprcHideProcessingOverlay();
                      overlayShown = false;
                    }
                  }
                );
              } else if (overlayShown && typeof oprcHideProcessingOverlay === 'function') {
                oprcHideProcessingOverlay();
                overlayShown = false;
              }
            } else if (overlayShown && typeof oprcHideProcessingOverlay === 'function') {
              oprcHideProcessingOverlay();
              overlayShown = false;
            }
          } else {
            // Validation failed - scroll to first error
            scrollToValidationError();
          }
          return false;
        }
      );

      jQuery(document).off('submit.oprc', 'form[name="hideregistration_guest"]').on('submit.oprc', 'form[name="hideregistration_guest"]', function()
        {
          jQuery.history.load("hideregistration_guest");
          jQuery('#hideRegistration input').css('border-color', '');
          jQuery('#hideRegistration .validation').remove();
          // check checkout as guest by default
          // checkGuestByDefault();
          // begin validation
          var error = false;
          if (!error) {
            var $guestForm = jQuery(this);
            var guestRedirectUrl = oprcResolveGuestCheckoutRedirectUrl($guestForm);
            if (guestRedirectUrl) {

              if (guestRedirectUrl.indexOf('#') === -1) {
                guestRedirectUrl += '#hideregistration_guest';
              }

              window.location = guestRedirectUrl;
              return false;
            }

            jQuery('#hideRegistration').fadeOut('fast', function()
              {
                jQuery('#easyLogin').fadeIn();
                jQuery('#easyLogin').css('visibility', 'visible');
                //reconfigureLogin('true');
              }
            );
            if (oprcOrderSteps == 'true') {
              var guestOrderStepsRequest = jQuery.get(onePageCheckoutURL + '&hideregistration=true');

              if (guestOrderStepsRequest && typeof guestOrderStepsRequest.done === 'function') {
                guestOrderStepsRequest.done(function(orderStepsData)
                  {
                    updateOrderSteps(orderStepsData);
                  }
                );
              }
            }
            scrollToRegistration();
          }
          return false;
        }
      );

      jQuery(document).off('click.oprc', '#hideregistrationBack a').on('click.oprc', '#hideregistrationBack a', function()
        {
          if (jQuery('#hideRegistration').length > 0) {
            window.location.hash = '';
            jQuery('#easyLogin').fadeOut('fast', function()
              {
                jQuery('#hideRegistration').fadeIn();
                if (oprcOrderSteps == 'true') {
                  var backOrderStepsOverlayShown = false;
                  if (typeof oprcShowProcessingOverlay === 'function') {
                    oprcShowProcessingOverlay();
                    backOrderStepsOverlayShown = true;
                  }

                  var backOrderStepsRequest = jQuery.get(onePageCheckoutURL);

                  if (backOrderStepsRequest && typeof backOrderStepsRequest.done === 'function') {
                    backOrderStepsRequest.done(function(orderStepsData)
                      {
                        updateOrderSteps(orderStepsData);
                      }
                    );
                    oprcAttachDeferredCallback(backOrderStepsRequest, function()
                      {
                        if (backOrderStepsOverlayShown && typeof oprcHideProcessingOverlay === 'function') {
                          oprcHideProcessingOverlay();
                          backOrderStepsOverlayShown = false;
                        }
                      }
                    );
                  } else {
                    if (backOrderStepsOverlayShown && typeof oprcHideProcessingOverlay === 'function') {
                      oprcHideProcessingOverlay();
                      backOrderStepsOverlayShown = false;
                    }
                  }
                }
              }
            );
            return false;
          } else {
            return true;
          }
        }
      );
    }

    jQuery(document).off('click.oprc', '#shippingAddress-checkbox').on('click.oprc', '#shippingAddress-checkbox', function()
      {
        // clear all message stack errors
        clearMessages();
        if(jQuery(this).is(':checked')) {
          jQuery('#shippingField').fadeOut(function()
            {
            }
          );
        } else {
          jQuery('#shippingField').fadeIn(function()
            {
            }
          );
        }
      }
    );

    if (oprcGuestAccountStatus == 'true') {
      if (oprcGuestFieldType == 'button') {
        // hide cowoa field
        jQuery('#no_account_switch a').off('click.oprc').on('click.oprc', function()
          {
            // clear all message stack errors
            clearMessages();

            jQuery('#passwordField').addClass('nmx-hidden');
            if (oprcGuestHideEmail == 'true') {
              jQuery('#emailOptions').fadeOut();
            }
            jQuery('#cowoaOff').fadeOut('fast', function()
              {
                jQuery('#cowoaOn').fadeIn();
              }
            );
            jQuery('input[name="cowoa-checkbox"]').val('true');
            return false;
          }
        );

        //show cowoa field
        jQuery('#register_switch a').off('click.oprc').on('click.oprc', function()
          {
            // clear all message stack errors
            clearMessages();
            jQuery('#passwordField').removeClass('nmx-hidden');
            if (oprcGuestHideEmail == 'true') {
              jQuery('#emailOptions').fadeIn();
            }
            jQuery('#cowoaOn').fadeOut('fast', function()
              {
                jQuery('#cowoaOff').fadeIn();
              }
            );
            jQuery('input[name="cowoa-checkbox"]').val('false');
            return false;
          }
        );

      } else if (oprcGuestFieldType == 'checkbox') {
        // CHECKBOX METHOD
        // checkGuestByDefault();

        jQuery(document).off('click.oprc', '#cowoa-checkbox').on('click.oprc', '#cowoa-checkbox', function()
          {
            // clear all message stack errors
            clearMessages();
            if (jQuery(this).is(':checked')) {
              guestHideShowInfo();
            } else {
              guestHideShowInfo('checked');
            }
          }
        );

      } else if (oprcGuestFieldType == 'radio') {
        // RADIO METHOD
        // checkGuestByDefault();

        jQuery(document).off('click.oprc', 'input[name="cowoa-radio"]').on('click.oprc', 'input[name="cowoa-radio"]', function()
          {
            // clear all message stack errors
            clearMessages();
            guestHideShowInfo(jQuery(this).val());
          }
        );
      }
    }

    function guestHideShowInfo(option) {
      var cowoaInput = jQuery('input[name="cowoa-checkbox"]'),
      passwordFields = jQuery('#passwordField'),
      newsOptions = jQuery('#emailOptions');

      if (option == "on" || option == "checked") {
        cowoaInput.val('true');
        passwordFields.addClass('nmx-hidden');
        if (oprcGuestHideEmail == 'true') {
          newsOptions.fadeOut();
        }
      } else {
        cowoaInput.val('false');
        passwordFields.removeClass('nmx-hidden');
        if (oprcGuestHideEmail == 'true') {
          newsOptions.fadeIn();
        }
      }
    }

    oprcInitForgottenPasswordModal();
    // END NOT LOGGED IN EVENTS

    // BEGIN LOGGED IN EVENTS
    jQuery('.termsdescription a').fancybox(
      {
      autoSize: true,
      closeClick: true,
      openEffect: 'fade',
      closeEffect: 'fade',
      closeClick: false,
      type: 'ajax',
      maxWidth: 878,
      padding: 0,
      beforeLoad: function()
      {
        if (typeof oprcShowProcessingOverlay === 'function') {
          oprcShowProcessingOverlay();
        }
      },
      afterShow: function()
      {
        if (typeof oprcHideProcessingOverlay === 'function') {
          oprcHideProcessingOverlay();
        }
      },
      onCancel: function()
      {
        if (typeof oprcHideProcessingOverlay === 'function') {
          oprcHideProcessingOverlay();
        }
      },
      afterClose: function()
      {
        if (typeof oprcHideProcessingOverlay === 'function') {
          oprcHideProcessingOverlay();
        }
      }
      }
    );

    jQuery('#privacyPolicy').fancybox(
      {
      autoSize: true,
      closeClick: true,
      openEfoprct: 'elastic',
      closeEfoprct: 'elastic',
      closeClick: false,
      type: 'ajax',
      maxWidth: 878,
      padding: 0,
      beforeLoad: function()
      {
        if (typeof oprcShowProcessingOverlay === 'function') {
          oprcShowProcessingOverlay();
        }
      },
      afterShow: function()
      {
        if (typeof oprcHideProcessingOverlay === 'function') {
          oprcHideProcessingOverlay();
        }
      },
      onCancel: function()
      {
        if (typeof oprcHideProcessingOverlay === 'function') {
          oprcHideProcessingOverlay();
        }
      },
      afterClose: function()
      {
        if (typeof oprcHideProcessingOverlay === 'function') {
          oprcHideProcessingOverlay();
        }
      }
      }
    );

    jQuery('#conditionsUse').fancybox(
      {
      autoSize: true,
      closeClick: true,
      openEfoprct: 'elastic',
      closeEfoprct: 'elastic',
      closeClick: false,
      type: 'ajax',
      maxWidth: 878,
      padding: 0,
      beforeLoad: function()
      {
        if (typeof oprcShowProcessingOverlay === 'function') {
          oprcShowProcessingOverlay();
        }
      },
      afterShow: function()
      {
        if (typeof oprcHideProcessingOverlay === 'function') {
          oprcHideProcessingOverlay();
        }
      },
      onCancel: function()
      {
        if (typeof oprcHideProcessingOverlay === 'function') {
          oprcHideProcessingOverlay();
        }
      },
      afterClose: function()
      {
        if (typeof oprcHideProcessingOverlay === 'function') {
          oprcHideProcessingOverlay();
        }
      }
      }
    );

    jQuery(document).off('click.oprc', '#js-submit :submit').on('click.oprc', '#js-submit :submit', function(e)
      {
        if (e && typeof e.preventDefault === 'function') {
          e.preventDefault();
        }

        oprcProcessCheckoutSubmission(e);
        return false;
      }
    );

    jQuery(document).off('submit.oprc', 'form[name="checkout_payment"]').on('submit.oprc', 'form[name="checkout_payment"]', function(e)
      {
        if (oprcAllowNativeCheckoutSubmit) {
          oprcAllowNativeCheckoutSubmit = false;
          return true;
        }

        if (e && typeof e.preventDefault === 'function') {
          e.preventDefault();
        }

        oprcProcessCheckoutSubmission(e);
        return false;
      }
    );

    // keypress equivalent for update credit module
    jQuery(document).off('keypress.oprc', '.discount input').on('keypress.oprc', '.discount input', function(e)
      {
        if (e.keyCode == 13) {
          updateCredit(jQuery(this).parents('.discount'));
          return false;
        }
      }
    );
    jQuery(document).off('click.oprc', '#discountsContainer .updateButton').on('click.oprc', '#discountsContainer .updateButton', function()
      {
        updateCredit(jQuery(this).parents('.discount'));
      }
    );
    jQuery(document).off('click.oprc', '.gvRemove').on('click.oprc', '.gvRemove', function()
      {
        jQuery('input[name="cot_gv"]').val(0);
        updateCredit();
        //setTimeout(jQuery('#discountFormdisc-ot_gv h2').after('<div class="disablejAlert"><div class="messageStackSuccess">' + oprcGVName + ' removed.</div></div>'), 5000);
        return false;
      }
    );

    jQuery(document).off('click.oprc', '#shopBagWrapper .couponRemove').on('click.oprc', '#shopBagWrapper .couponRemove', function()
      {
        var removeToken = 'remove';
        var messages = typeof oprcGetCouponMessages === 'function' ? oprcGetCouponMessages() : null;

        if (messages && typeof messages.removeToken === 'string' && messages.removeToken !== '') {
          removeToken = jQuery.trim(messages.removeToken);
        } else {
          var $couponForm = typeof oprcGetCouponFormElement === 'function' ? oprcGetCouponFormElement() : jQuery();
          if ($couponForm.length) {
            var removeAttr = $couponForm.attr('data-oprc-coupon-remove-token');
            if (typeof removeAttr === 'string' && removeAttr !== '') {
              removeToken = jQuery.trim(removeAttr);
            }
          }
        }

        if (typeof removeToken !== 'string' || removeToken === '') {
          removeToken = 'remove';
        }

        var $couponFormElement = typeof oprcGetCouponFormElement === 'function' ? oprcGetCouponFormElement() : jQuery();
        var $input = $couponFormElement.length ? $couponFormElement.find('input[name="dc_redeem_code"]').first() : jQuery();
        if (!$input.length) {
          $input = jQuery('input[name="dc_redeem_code"]').first();
        }

        if ($input.length) {
          $input.val(removeToken);
        } else {
          jQuery('input[name="dc_redeem_code"]').val(removeToken);
        }

        updateCredit();
        return false;
      }
    );

    displayCreditUpdate();

    oprcInitChangeAddressModal(true);

    jQuery(document).off('click.oprc', '.delete-address-button').on('click.oprc', '.delete-address-button', function()
      {
        //blockPage(false, false);
        var address_entry = jQuery(this).parents('.addressEntry');
        var data = {
        address_book_id: jQuery(this).attr('address-book-id'),
        default_selected: jQuery(this).attr('default-selected')
        };
        // check if logged in
        oprcFetchLoginCheck().done(function(loginCheck)
          {
            if (parseInt(loginCheck) == 1) {
              jQuery.ajax(
                {
                url: ajaxDeleteAddressURL,
                method: 'POST',
                data: data,
                dataType: 'json',
                success: function (response) {
                    if(response.success == false) {
                      jQuery('.alert-message').remove();
                      jQuery('#addressBookContainer').prepend('<p class="messageStackError alert-message">' + response.message + '</p>');
                    } else if(response.success == true) {
                      jQuery('.alert-message').remove();
                      jQuery('#addressBookContainer').prepend('<p class="messageStackSuccess alert-message">' + response.message + '</p>');
                      address_entry.remove();
                    }
                  }
                }
              );
            } else {
              // redirect to checkout
              confirm('Sorry, your session has expired.', 'Time Out', function(r)
                {
                  window.location.replace(fecQuickCheckoutURL);
                }
              );
            }
          }
        );
        //unblockPage();
        return false;
      }
    );

    jQuery(document).off('submit.oprc', 'form[name="checkout_address"]').on('submit.oprc', 'form[name="checkout_address"]', function()
      {
        var $theForm = jQuery(this);
        blockPage(true, true);
        var newAddress = check_new_address('checkout_address');
        if (!newAddress) {
          unblockPage();
          $theForm.removeData('oprcActiveSubmitWrapper');
          return false;
        }

        var $activeSubmitWrapper = oprcGetActiveAddressWrapper($theForm);
        if ($activeSubmitWrapper && $activeSubmitWrapper.length) {
          oprcToggleAddressWrapperLoading($activeSubmitWrapper, true);
          $theForm.data('oprcLoadingWrapperId', $activeSubmitWrapper.attr('id'));
        }

        var formAction = $theForm.attr('action');
        var addressType = 'billto';
        if (formAction && formAction.indexOf('checkout_shipping_address') !== -1) {
          addressType = 'shipto';
        } else if (formAction && formAction.indexOf('checkout_payment_address') !== -1) {
          addressType = 'billto';
        }

        var requestData = $theForm.serialize();
        if (requestData.length) {
          requestData += '&';
        }
        requestData += 'addressType=' + encodeURIComponent(addressType) + '&oprc_change_address=submit';

        jQuery.ajax(
          {
            url: ajaxChangeAddressURL,
            method: 'POST',
            data: requestData,
            dataType: 'json'
          }
        )
        .done(function(response)
          {
            if (response && typeof response.redirect_url !== 'undefined' && response.redirect_url) {
              oprcClearAddressWrapperLoading($theForm);
              unblockPage();
              window.location.replace(response.redirect_url);
              return;
            }

            oprcFetchLoginCheck()
              .done(function(loginCheck)
                {
                  if (parseInt(loginCheck) == 1) {
                    if (window.location.hash == '#checkoutShipAddressDefault' || window.location.hash == '#checkoutPayAddressDefault') {
                      oprcClearAddressWrapperLoading($theForm);
                      unblockPage();
                      window.location.assign(onePageCheckoutURL);
                      return;
                    }

                    checkPageErrors();

                    var step3Html = (response && typeof response.step3Html !== 'undefined' && response.step3Html !== null) ? response.step3Html : '';
                    var orderTotalHtml = (response && typeof response.orderTotalHtml !== 'undefined' && response.orderTotalHtml !== null) ? response.orderTotalHtml : '';
                    var $step3Wrapper = step3Html ? jQuery('<div></div>').html(step3Html) : null;
                    var $orderTotalWrapper = orderTotalHtml ? jQuery('<div></div>').html(orderTotalHtml) : null;

                    var newAddressesHtml;
                    if (response && Object.prototype.hasOwnProperty.call(response, 'oprcAddresses')) {
                      newAddressesHtml = response.oprcAddresses;
                    } else if ($step3Wrapper && $step3Wrapper.length) {
                      var $addressesNode = $step3Wrapper.find('#oprcAddresses');
                      if ($addressesNode.length) {
                        newAddressesHtml = $addressesNode.html();
                      }
                    }
                    if (typeof newAddressesHtml !== 'undefined') {
                      jQuery('#oprcAddresses').html(newAddressesHtml);
                    }

                    var shippingAddressChanged = (addressType !== 'billto');
                    if (response && Object.prototype.hasOwnProperty.call(response, 'shippingAddressChanged')) {
                      shippingAddressChanged = oprcIsTruthy(response.shippingAddressChanged);
                    }

                    if (shippingAddressChanged) {
                      var newShippingHtml;
                      if (response && Object.prototype.hasOwnProperty.call(response, 'shippingMethodContainer')) {
                        newShippingHtml = response.shippingMethodContainer;
                      } else if ($step3Wrapper && $step3Wrapper.length) {
                        var $shippingNode = $step3Wrapper.find('#shippingMethodContainer');
                        if ($shippingNode.length) {
                          newShippingHtml = $shippingNode.html();
                        }
                      }
                      if (typeof newShippingHtml !== 'undefined') {
                        jQuery('#shippingMethodContainer').html(newShippingHtml ? newShippingHtml : '');
                        if (oprcAJaxShippingQuotes == true) {
                          if (!newShippingHtml || jQuery.trim(newShippingHtml).length === 0) {
                            oprcRestoreShippingContainerStructure();
                          }
                          oprcRememberShippingContainerTemplate();
                        }
                        oprcUpdateShippingMethodsPanelState();
                      }

                      if (response && Object.prototype.hasOwnProperty.call(response, 'shippingMethodsHtml')) {
                        var newShippingMethodsHtml = response.shippingMethodsHtml;
                        if (typeof newShippingMethodsHtml === 'string') {
                          var trimmedShippingMethods = jQuery.trim(newShippingMethodsHtml);
                          if (trimmedShippingMethods.length > 0) {
                            jQuery('#shippingMethods').html(newShippingMethodsHtml);
                            jQuery('#shippingMethodContainer').show();
                          } else {
                            oprcRestoreShippingContainerStructure();
                            oprcRenderNoShippingMethodsMessage();
                            jQuery('#shippingMethodContainer').show();
                          }
                          oprcUpdateShippingMethodsPanelState();
                        }
                      }
                    } else {
                      oprcUpdateShippingMethodsPanelState();
                    }

                    oprcSyncSelectedShippingSelection();

                    if (oprcRefreshPayment == 'true') {
                      var newPaymentHtml;
                      var paymentContainerInfo = null;

                      if (response && Object.prototype.hasOwnProperty.call(response, 'paymentMethodContainerOuter')) {
                        paymentContainerInfo = oprcParsePaymentContainerHtml(response.paymentMethodContainerOuter);
                      }

                      if (!paymentContainerInfo && $step3Wrapper && $step3Wrapper.length) {
                        paymentContainerInfo = oprcExtractPaymentContainer($step3Wrapper);
                      }

                      if (response && Object.prototype.hasOwnProperty.call(response, 'paymentMethodContainer')) {
                        newPaymentHtml = response.paymentMethodContainer;
                      }

                      if (typeof newPaymentHtml === 'undefined' && paymentContainerInfo && typeof paymentContainerInfo.innerHtml === 'string') {
                        newPaymentHtml = paymentContainerInfo.innerHtml;
                      }

                      if (typeof newPaymentHtml !== 'undefined' || (paymentContainerInfo && paymentContainerInfo.element && paymentContainerInfo.element.length)) {
                        var $replacementElement = paymentContainerInfo ? paymentContainerInfo.element : null;
                        oprcReplacePaymentMethodContainer(typeof newPaymentHtml !== 'undefined' ? newPaymentHtml : null, {
                          replacementElement: $replacementElement
                        });
                      }
                    }

                    var newOrderTotalHtml;
                    if (response && Object.prototype.hasOwnProperty.call(response, 'shopBagWrapper')) {
                      newOrderTotalHtml = response.shopBagWrapper;
                    } else if ($orderTotalWrapper && $orderTotalWrapper.length) {
                      var $orderNode = $orderTotalWrapper.find('#shopBagWrapper');
                      if ($orderNode.length) {
                        newOrderTotalHtml = $orderNode.html();
                      }
                    }
                    if (typeof newOrderTotalHtml !== 'undefined') {
                      jQuery('#shopBagWrapper').html(newOrderTotalHtml);
                    }

                    if (response && typeof response.oprcAddressMissing !== 'undefined') {
                      oprcAddressMissing = response.oprcAddressMissing;
                    } else {
                      oprcAddressMissing = 'false';
                    }

                    if (response && typeof response.messagesHtml !== 'undefined' && response.messagesHtml) {
                      if (typeof displayErrors === 'function') {
                        displayErrors(response.messagesHtml);
                      } else {
                        jQuery('.messageStackErrors').html(response.messagesHtml);
                      }
                    } else {
                      jQuery('.messageStackErrors').empty();
                    }

                    if (typeof prepareMessages === 'function') {
                      prepareMessages();
                    }

                    oprcProcessingOverlayShouldReloadQuotes = false;
                    oprcProcessingOverlayCartChanged = false;

                    if (oprcAJaxShippingQuotes == true && shippingAddressChanged) {
                      ajaxLoadShippingQuote(true);
                    }

                    jQuery.fancybox.close(true);
                    savedCardsLayout();
                    oprcInitChangeAddressModal(true);
                  } else {
                    confirm('Sorry, your session has expired.', 'Time Out', function(r)
                      {
                        window.location.replace(onePageCheckoutURL);
                      }
                    );
                  }
                }
              )
              .fail(function(xhr)
                {
                  var errorHtml = '';
                  if (xhr && xhr.responseJSON && xhr.responseJSON.messagesHtml) {
                    errorHtml = xhr.responseJSON.messagesHtml;
                  } else if (xhr && xhr.responseJSON && xhr.responseJSON.messages) {
                    errorHtml = xhr.responseJSON.messages;
                  } else if (xhr && xhr.responseText) {
                    errorHtml = xhr.responseText;
                  }
                  if (errorHtml) {
                    if (typeof displayErrors === 'function') {
                      displayErrors(errorHtml);
                    } else {
                      jQuery('.messageStackErrors').html(errorHtml);
                    }
                    if (typeof prepareMessages === 'function') {
                      prepareMessages();
                    }
                  }
                }
              )
              .always(function()
                {
                  oprcClearAddressWrapperLoading($theForm);
                }
              );
          }
        )
        .fail(function(xhr)
          {
            var messagesHtml = '';
            if (xhr && xhr.responseJSON && xhr.responseJSON.messagesHtml) {
              messagesHtml = xhr.responseJSON.messagesHtml;
            } else if (xhr && xhr.responseJSON && xhr.responseJSON.messages) {
              messagesHtml = xhr.responseJSON.messages;
            } else if (xhr && xhr.responseText) {
              messagesHtml = xhr.responseText;
            }
            if (messagesHtml) {
              if (typeof displayErrors === 'function') {
                displayErrors(messagesHtml);
              } else {
                jQuery('.messageStackErrors').html(messagesHtml);
              }
              if (typeof prepareMessages === 'function') {
                prepareMessages();
              }
            }
            oprcClearAddressWrapperLoading($theForm);
            unblockPage();
          }
        );

        return false;
      }
    );

    // END LOGGED IN EVENTS

    // Events after the page has completely loaded
    jQuery.history.init(function(hash)
      {
        if(hash == "hideregistration_guest") {
          // Check if the form exists before trying to submit
          var $guestForm = jQuery('form[name="hideregistration_guest"]');
          if ($guestForm.length > 0) {
            // Form exists, submit it
            $guestForm.submit();
          } else {
            // Form doesn't exist (we're already on step 2), hide the processing overlay
            if (typeof unblockPage === 'function') {
              unblockPage();
            }
          }
        }
      },
      {unescape: ",/"});

    scrollToError();

    // If the floating shopping cart is going to go further than the footer
    jQuery("#shoppingBagContainer .back").click(function()
      {
        $borda = jQuery("#footer").offset().top - jQuery("#needHelpHead").offset().top;
        if ($borda < 200) {
          //alert("warning");
          document.getElementById("orderTotal").style.overflowY = "scroll";
          document.getElementById("orderTotal").style.height = "250px";
        }
      }
    );

  }
);

function oprcGetActiveAddressWrapper($form) {
  if (!$form || !$form.length) {
    return jQuery();
  }

  var wrapperId = $form.data('oprcActiveSubmitWrapper');
  if (wrapperId) {
    var $wrapperFromData = jQuery('#' + wrapperId);
    if ($wrapperFromData.length) {
      return $wrapperFromData;
    }
  }

  var $activeElement = jQuery(document.activeElement);
  if ($activeElement.length) {
    var $wrapperFromActive = $activeElement.closest(oprcAddressSubmitWrapperSelector);
    if ($wrapperFromActive.length) {
      return $wrapperFromActive;
    }
  }

  return jQuery('#js-submit-new-address');
}

function oprcToggleAddressWrapperLoading($wrapper, isLoading) {
  if (!$wrapper || !$wrapper.length) {
    return;
  }

  if (isLoading) {
    $wrapper.addClass(oprcAddressWrapperLoadingClass);
  } else {
    $wrapper.removeClass(oprcAddressWrapperLoadingClass);
  }

  var $submitElement = $wrapper.find(oprcAddressSubmitElementSelector).first();
  if (!$submitElement.length) {
    return;
  }

  if ($submitElement.is('button') || $submitElement.is('input')) {
    $submitElement.prop('disabled', isLoading);
  } else if ($submitElement.is('a')) {
    if (isLoading) {
      $submitElement.attr('aria-disabled', 'true');
    } else {
      $submitElement.removeAttr('aria-disabled');
    }
  }
}

function oprcClearAddressWrapperLoading($form) {
  if (!$form || !$form.length) {
    return;
  }

  var wrapperId = $form.data('oprcLoadingWrapperId');
  if (wrapperId) {
    var $wrapper = jQuery('#' + wrapperId);
    oprcToggleAddressWrapperLoading($wrapper, false);
    $form.removeData('oprcLoadingWrapperId');
  }

  $form.removeData('oprcActiveSubmitWrapper');
}

jQuery(window).off('resize.oprc').on('resize.oprc', function()
  {
    oprcInitChangeAddressModal();
  }
);

function oprcBuildFallbackMessageHtml(messageText) {
  if (typeof messageText !== 'string' || messageText === '') {
    return '';
  }

  var escaped = jQuery('<div/>').text(messageText).html();
  return '<div class="disablejAlert"><div class="messageStackError">' + escaped + '</div></div>';
}

function oprcTryHandleModalJsonResponse(rawData, fallbackMessage) {
  if (typeof rawData !== 'string') {
    return false;
  }

  var trimmed = jQuery.trim(rawData);
  if (!trimmed) {
    return false;
  }

  var firstChar = trimmed.charAt(0);
  if (firstChar !== '{' && firstChar !== '[') {
    return false;
  }

  var payload;
  try {
    payload = jQuery.parseJSON(trimmed);
  } catch (jsonError) {
    return false;
  }

  var handled = false;

  if (payload && typeof payload === 'object') {
    if (payload.redirect_url) {
      window.location.assign(payload.redirect_url);
      handled = true;
    }

    var messageParts = [];
    if (payload.messagesHtml) {
      messageParts.push(payload.messagesHtml);
    }
    if (Array.isArray(payload.messages) && payload.messages.length) {
      messageParts.push(oprcBuildFallbackMessageHtml(payload.messages.join(' ')));
    }
    if (payload.message) {
      messageParts.push(oprcBuildFallbackMessageHtml(payload.message));
    }
    if (payload.error) {
      messageParts.push(oprcBuildFallbackMessageHtml(payload.error));
    }
    if (payload.error_message) {
      messageParts.push(oprcBuildFallbackMessageHtml(payload.error_message));
    }

    if (!messageParts.length && typeof fallbackMessage === 'string' && fallbackMessage !== '') {
      messageParts.push(oprcBuildFallbackMessageHtml(fallbackMessage));
    }

    if (messageParts.length) {
      var combinedMessages = messageParts.join('');
      if (typeof displayErrors === 'function') {
        displayErrors(combinedMessages);
      } else {
        jQuery('.messageStackErrors').html(combinedMessages);
      }
      if (typeof prepareMessages === 'function') {
        prepareMessages();
      }
      handled = true;
    }
  }

  return handled;
}

function initiateFancyBox(selector) {
  var $elements = jQuery(selector);

  if (!$elements.length) {
    return;
  }

  $elements.each(function() {
    var $element = jQuery(this);
    var href = $element.attr('href');

    if (typeof href === 'string' && href.indexOf('%20#') !== -1) {
      $element.attr('href', href.replace('%20#', ' #'));
    }
  });

  $elements.off('click.fb-start');

  $elements.fancybox(
    {
    autoSize: false,
    autoResize: true,
    autoCenter: false,
    closeClick: true,
    openEffect: 'fade',
    closeEffect: 'fade',
    closeClick: false,
    type: 'ajax',
    scrollOutside: false,
    scrolling: 'auto',
    padding: 0,
    ajax: {
      dataFilter: function(data) {
        var fallbackMessage = (typeof oprcChangeAddressLoadErrorMessage !== 'undefined') ? oprcChangeAddressLoadErrorMessage : '';
        if (oprcTryHandleModalJsonResponse(data, fallbackMessage)) {
          if (typeof oprcHideProcessingOverlay === 'function') {
            oprcHideProcessingOverlay();
          }
          if (jQuery.fancybox && typeof jQuery.fancybox.close === 'function') {
            jQuery.fancybox.close();
          }
          return '';
        }

        return data;
      },
      error: function(jqXHR) {
        var fallbackMessage = (typeof oprcChangeAddressLoadErrorMessage !== 'undefined') ? oprcChangeAddressLoadErrorMessage : '';
        var handled = false;

        if (jqXHR && typeof jqXHR.responseText === 'string') {
          handled = oprcTryHandleModalJsonResponse(jqXHR.responseText, fallbackMessage);
        }

        if (!handled) {
          var responseText = jqXHR && typeof jqXHR.responseText === 'string' ? jQuery.trim(jqXHR.responseText) : '';

          if (responseText) {
            var responseHtml = responseText.charAt(0) === '<' ? responseText : oprcBuildFallbackMessageHtml(responseText);
            if (typeof displayErrors === 'function') {
              displayErrors(responseHtml);
            } else {
              jQuery('.messageStackErrors').html(responseHtml);
            }
            handled = true;
          } else if (fallbackMessage) {
            var fallbackHtml = oprcBuildFallbackMessageHtml(fallbackMessage);
            if (typeof displayErrors === 'function') {
              displayErrors(fallbackHtml);
            } else {
              jQuery('.messageStackErrors').html(fallbackHtml);
            }
            handled = true;
          }

          if (handled && typeof prepareMessages === 'function') {
            prepareMessages();
          }
        }

        if (typeof oprcHideProcessingOverlay === 'function') {
          oprcHideProcessingOverlay();
        }
        if (jQuery.fancybox && typeof jQuery.fancybox.close === 'function') {
          jQuery.fancybox.close();
        }
      }
    },
    beforeLoad: function() {
      if (typeof oprcShowProcessingOverlay === 'function') {
        oprcShowProcessingOverlay();
      }
    },
    afterShow: function() {
      if (typeof oprcHideProcessingOverlay === 'function') {
        oprcHideProcessingOverlay();
      }
    },
    onCancel: function() {
      if (typeof oprcHideProcessingOverlay === 'function') {
        oprcHideProcessingOverlay();
      }
    },
    afterClose: function() {
      if (typeof oprcHideProcessingOverlay === 'function') {
        oprcHideProcessingOverlay();
      }
    },
    beforeShow: function() {

        oprcChangeAddressCallback();

        // initiate tabs
        // when user clicks on tab, this code will be executed
        var tabs_list = jQuery("#nmx-tabs-nav"),
        tab = tabs_list.find("li"),
        tab_content = jQuery(".nmx-tab-content");

        tab.first().addClass('active');
        tab_content.first().addClass('active');

        if (tab.length === 1) {
          tab.first().addClass('full')
        }

        tab.click(function()
          {
            // first remove class "active" from currently active tab
            tab.removeClass('active');

            // now add class "active" to the selected/clicked tab
            jQuery(this).addClass("active");
            // hide all tab content
            tab_content.removeClass('active');

            // here we get the href value of the selected tab
            var selected_tab = jQuery(this).find("a").attr("href");

            // show the selected tab content
            jQuery(selected_tab).addClass('active');

            // at the end, we add return false so that the click on the link is not executed
            return false;
          }
        );

        // form
        var theForm = jQuery('[name="checkout_address"]');
        jQuery('[name="checkout_address"]').removeAttr('onsubmit');
        update_zone(theForm[0]);
      }
    }
  );
}

function disableFancyBox(selector) {
  jQuery(selector).off('click.fb-start');
  //fancyboxEnabled = false;
}

function reconfigureSideMenu() {
  // layout handled via CSS sticky positioning; nothing to do here.
}

function updateOrderSteps(data) {
  if (oprcOrderSteps == 'true') {
    var orderSteps = jQuery(data).find('.orderSteps').html();
    jQuery('.orderSteps').html(orderSteps);
  } else {
    return false;
  }
}

function prepareMessages() {
  var hasMessages = false;
  var messageStackErrors = '';
  var $messages = jQuery('.messageStackError, .messageStackCaution, .messageStackSuccess, .messageStackWarning');

  if ($messages.length > 0) {
    $messages.each(function()
      {
        if (!jQuery(this).parents().hasClass('disablejAlert')) {
          messageStackErrors += jQuery(this).text();
          hasMessages = true;
        }
      }
    );
  }

  var $messageContainer = jQuery('.messageStackErrors');

  if (hasMessages && messageStackErrors !== '') {
    $messageContainer.html(messageStackErrors);
    $messageContainer.attr('data-oprc-has-server-messages', 'true');
  } else {
    if ($messageContainer.length) {
      $messageContainer.removeAttr('data-oprc-has-server-messages');
      if (!hasMessages) {
        $messageContainer.empty();
      }
    }
  }

  return hasMessages;
}

function showRegistrationEmailExistsError(message) {
  if (typeof message === 'undefined' || message === null || message === '') {
    return;
  }

  var $registrationForm = jQuery('form[name="hideregistration_register"]');

  if (!$registrationForm.length) {
    return;
  }

  var $buttonRow = $registrationForm.find('.nmx-buttons').first();
  var $messageContainer = $registrationForm.find('.js-registration-email-exists-error');

  if (!$messageContainer.length) {
    $messageContainer = jQuery('<div class="disablejAlert alert validation registrationError js-registration-email-exists-error" role="alert" aria-live="polite"></div>');

    if ($buttonRow.length) {
      $buttonRow.before($messageContainer);
    } else {
      $registrationForm.append($messageContainer);
    }
  }

  $messageContainer.text(message);
}

function clearMessages(options) {
  var settings = jQuery.extend({ force: false }, options);
  var $messageContainer = jQuery('.messageStackErrors');
  var hasServerMessages = $messageContainer.attr('data-oprc-has-server-messages') === 'true';

  if (settings.force === true) {
    $messageContainer.empty();
    $messageContainer.removeAttr('data-oprc-has-server-messages');
  } else if (!hasServerMessages) {
    $messageContainer.empty();
  }

  if (settings.force === true || !hasServerMessages) {
    jQuery('.messageStackError, .messageStackCaution, .messageStackSuccess, .messageStackWarning').each(function()
      {
        jQuery(this).remove();
      }
    );
  }
}

function displaySessionExpiredMessage() {
  if (typeof window.sessionStorage === 'undefined') {
    return;
  }

  var storage;

  try {
    storage = window.sessionStorage;
  } catch (error) {
    return;
  }

  if (!storage) {
    return;
  }

  if (storage.getItem(sessionExpiredStorageKey) !== 'true') {
    return;
  }

  storage.removeItem(sessionExpiredStorageKey);

  var $messageContainer = jQuery('#messageStackErrors');

  if (!$messageContainer.length) {
    var $leftColumn = jQuery('#oprcLeft');

    $messageContainer = jQuery('<div id="messageStackErrors" class="messageStackErrors"></div>');

    if ($leftColumn.length) {
      $leftColumn.prepend($messageContainer);
    } else {
      jQuery('#onePageCheckoutContent').prepend($messageContainer);
    }
  }

  $messageContainer.attr('data-oprc-has-server-messages', 'true');
  $messageContainer.find('.js-session-expired-message').remove();

  var $message = jQuery('<div/>', {
    'class': 'disablejAlert alert validation js-session-expired-message',
    role: 'alert',
    'aria-live': 'polite'
  }).append(
    jQuery('<div/>', {
      'class': 'messageStackError',
      text: 'Sorry, your session has expired.'
    })
  );

  $messageContainer.append($message);
}

// Convert HTML breaks to newline
String.prototype.br2nl =
function() {
  return this.replace(/<br\s*\/?>/mg, "\n");
};

function reconfigureLogin() {// note: cowoaStatus removed, but checks still left in case we need them in the future
  update_zone(document.create_account);
  if (oprcShippingAddress == 'true') {
    update_zone_shipping(document.create_account);
  }
  if (oprcShippingAddressStatus == true) {
    blockShippingField();
  }
  if (oprcShippingAddressCheck != true || (oprcGetContentType == 'virtual')) {
    blockShippingContainer();
  }
  if (oprcGuestAccountStatus == 'true') {
    if (oprcGuestFieldType == 'button') {

    } else if (oprcGuestFieldType == 'checkbox') {
      // CHECKBOX METHOD
      // unhide checkbox
      jQuery('input#cowoa-checkbox').removeClass("hiddenField");

    } else if (oprcGuestFieldType == 'radio') {
      // RADIO METHOD
      jQuery('input[name=cowoa-radio]').removeClass("hiddenField");
    }
    // disable the links created for non-JavaScript users since JavaScript is enabled
    jQuery(".disableLink").each(function()
      {
        var linkContents = jQuery(this).contents();
        jQuery(this).replaceWith(linkContents);
      }
    );
  } // end COWOA check ?>
  reconfigureSideMenu();
}
// blocking functions
function blockShippingField() {
  if (jQuery('#shippingAddress-checkbox').is(':checked')) {
    jQuery('#shippingField').hide();
  }
}

function blockShippingField() {
  if (jQuery('#shippingAddress-checkbox').is(':checked')) {
    jQuery('#shippingField').hide();
  }
}

function blockShippingContainer() {
  jQuery('#shippingAddressContainer').hide();
}

function oprcAbortRequest(request) {
  if (request && typeof request.abort === 'function') {
    request.abort();
  }
}

function oprcAttachDeferredCallback(request, callback) {
  if (typeof callback !== 'function') {
    return false;
  }

  if (!request) {
    callback();
    return false;
  }

  if (typeof request.always === 'function') {
    request.always(callback);
    return true;
  }

  if (typeof request.complete === 'function') {
    request.complete(callback);
    return true;
  }

  if (typeof request.then === 'function') {
    request.then(callback, callback);
    return true;
  }

  callback();
  return false;
}

function oprcIsTruthy(value) {
  if (typeof value === 'string') {
    return value.toLowerCase() === 'true';
  }

  return !!value;
}

function oprcReadCreditCoversFlag() {
  var $flagInput = jQuery('#oprcCreditCoversFlag');
  if (!$flagInput.length) {
    return null;
  }

  return oprcIsTruthy($flagInput.val());
}

function oprcExtractElementInnerHtmlFromString(html, selector) {
  if (typeof html !== 'string' || html === '') {
    return null;
  }

  var $wrapper = jQuery('<div></div>').html(html);
  var $element = $wrapper.find(selector).first();

  if (!$element.length && $wrapper.is(selector)) {
    $element = $wrapper;
  }

  if ($element.length) {
    return $element.html();
  }

  return null;
}

function oprcApplyShippingUpdateResponse(response, options) {
  if (typeof options !== 'object' || options === null) {
    options = {};
  }

  var shouldRefreshShippingContainer = options.shouldRefreshShippingContainer !== false;
  var shouldRefreshShippingMethods = options.shouldRefreshShippingMethods !== false;
  var shouldRefreshPaymentContainer = options.shouldRefreshPaymentContainer !== false;
  var newShippingHtml;
  if (shouldRefreshShippingContainer && response && Object.prototype.hasOwnProperty.call(response, 'shippingMethodContainer')) {
    newShippingHtml = response.shippingMethodContainer;
  }

  if (shouldRefreshShippingContainer && typeof newShippingHtml !== 'undefined') {
    jQuery('#shippingMethodContainer').html(newShippingHtml ? newShippingHtml : '');
    if (oprcAJaxShippingQuotes == true) {
      if (!newShippingHtml || jQuery.trim(newShippingHtml).length === 0) {
        oprcRestoreShippingContainerStructure();
      }
      oprcRememberShippingContainerTemplate();
    }
    oprcUpdateShippingMethodsPanelState();
  }

  if (shouldRefreshShippingMethods && response && Object.prototype.hasOwnProperty.call(response, 'shippingMethodsHtml')) {
    var newShippingMethodsHtml = response.shippingMethodsHtml;
    if (typeof newShippingMethodsHtml === 'string') {
      var trimmedShippingMethods = jQuery.trim(newShippingMethodsHtml);
      if (trimmedShippingMethods.length > 0) {
        jQuery('#shippingMethods').html(newShippingMethodsHtml);
        jQuery('#shippingMethodContainer').show();
      } else {
        oprcRestoreShippingContainerStructure();
        oprcRenderNoShippingMethodsMessage();
        jQuery('#shippingMethodContainer').show();
      }
      oprcUpdateShippingMethodsPanelState();
    }
  }

  if (response && Object.prototype.hasOwnProperty.call(response, 'deliveryUpdates')) {
    oprcApplyDeliveryUpdates(response.deliveryUpdates);
  }

  if (shouldRefreshPaymentContainer && response) {
    var paymentContainerInfo = null;
    var newPaymentHtml;

    if (Object.prototype.hasOwnProperty.call(response, 'paymentMethodContainerOuter')) {
      paymentContainerInfo = oprcParsePaymentContainerHtml(response.paymentMethodContainerOuter);
    }

    if (Object.prototype.hasOwnProperty.call(response, 'paymentMethodContainer')) {
      newPaymentHtml = response.paymentMethodContainer;
    }

    if (typeof newPaymentHtml === 'undefined' && paymentContainerInfo && typeof paymentContainerInfo.innerHtml === 'string') {
      newPaymentHtml = paymentContainerInfo.innerHtml;
    }

    if ((typeof newPaymentHtml !== 'undefined' && typeof newPaymentHtml === 'string') || (paymentContainerInfo && paymentContainerInfo.element && paymentContainerInfo.element.length)) {
      oprcReplacePaymentMethodContainer(typeof newPaymentHtml === 'string' ? newPaymentHtml : null, {
        replacementElement: paymentContainerInfo ? paymentContainerInfo.element : null
      });
    }
  }

  var newDiscountsHtml;
  if (response && Object.prototype.hasOwnProperty.call(response, 'discountsContainer')) {
    newDiscountsHtml = response.discountsContainer;
  }
  if (typeof newDiscountsHtml === 'undefined' && response && Object.prototype.hasOwnProperty.call(response, 'step3Html')) {
    newDiscountsHtml = oprcExtractElementInnerHtmlFromString(response.step3Html, '#discountsContainer');
  }
  if (typeof newDiscountsHtml === 'string') {
    jQuery('#discountsContainer').html(newDiscountsHtml);
  }

  if (response && Object.prototype.hasOwnProperty.call(response, 'oprcAddresses')) {
    var newAddressesHtml = response.oprcAddresses;
    if (typeof newAddressesHtml === 'string') {
      jQuery('#oprcAddresses').html(newAddressesHtml);
    }
  }

  var newOrderTotalHtml;
  if (response && Object.prototype.hasOwnProperty.call(response, 'shopBagWrapper')) {
    newOrderTotalHtml = response.shopBagWrapper;
  }
  if (typeof newOrderTotalHtml === 'undefined' && response && Object.prototype.hasOwnProperty.call(response, 'orderTotalHtml')) {
    newOrderTotalHtml = oprcExtractElementInnerHtmlFromString(response.orderTotalHtml, '#shopBagWrapper');
  }
  if (typeof newOrderTotalHtml === 'string') {
    jQuery('#shopBagWrapper').html(newOrderTotalHtml);
  }

  if (response && Object.prototype.hasOwnProperty.call(response, 'oprcAddressMissing')) {
    oprcAddressMissing = response.oprcAddressMissing;
  }

  if (typeof reconfigureSideMenu === 'function') {
    reconfigureSideMenu();
  }

  if (typeof displayCreditUpdate === 'function') {
    displayCreditUpdate();
  }

  savedCardsLayout();
  oprcInitChangeAddressModal(true);
  oprcSyncSelectedShippingSelection();

  if (typeof response !== 'undefined' && response !== null && Object.prototype.hasOwnProperty.call(response, 'messagesHtml')) {
    var messagesHtml = response.messagesHtml;
    var $messageContainer = jQuery('.messageStackErrors');
    if (messagesHtml) {
      if (typeof displayErrors === 'function') {
        displayErrors(messagesHtml);
      } else {
        $messageContainer.html(messagesHtml);
      }
      $messageContainer.attr('data-oprc-has-server-messages', 'true');
    } else {
      if (typeof displayErrors === 'function') {
        displayErrors('');
      }
      $messageContainer.empty();
      $messageContainer.removeAttr('data-oprc-has-server-messages');
    }
  }

  if (typeof prepareMessages === 'function') {
    prepareMessages();
  }

  oprcUpdateShippingMethodsPanelState();

  setTimeout(function() {
    scrollToError();
  }, 250);
}

function oprcApplyDeliveryUpdates(updates) {
  if (!updates || typeof updates !== 'object') {
    return;
  }

  jQuery.each(updates, function(optionId, deliveryHtml) {
    if (typeof optionId !== 'string') {
      return;
    }

    var $input = jQuery('input[name="shipping"][value="' + optionId + '"]');
    if (!$input.length) {
      return;
    }

    var inputId = $input.attr('id');
    var $label = null;

    if (typeof inputId === 'string' && inputId.length) {
      $label = jQuery('label[for="' + inputId + '"]');
    }

    if (!$label || !$label.length) {
      $label = $input.closest('label');
    }

    if (!$label || !$label.length) {
      return;
    }

    var $dateSpan = $label.find('.shipping-estimated-date');
    var hasHtml = typeof deliveryHtml === 'string' && jQuery.trim(deliveryHtml).length > 0;

    if (!hasHtml) {
      if ($dateSpan.length) {
        $dateSpan.remove();
      }
      return;
    }

    if (!$dateSpan.length) {
      $dateSpan = jQuery('<span class="shipping-estimated-date"></span>');
      $label.append($dateSpan);
    }

    $dateSpan.html(deliveryHtml);
  });
}

function oprcSubmitShippingUpdate(selectedValue) {
  if (typeof selectedValue === 'undefined' || selectedValue === null) {
    selectedValue = oprcGetSelectedShippingValue();
  }

  if (selectedValue === null) {
    return;
  }

  if (selectedValue === oprcLastSubmittedShippingMethod) {
    return;
  }

  oprcLastSubmittedShippingMethod = selectedValue;

  clearMessages({ force: true });
  oprcAbortRequest(oprcShippingUpdateRequest);

  var requestData = jQuery('[name="checkout_payment"]').serialize();
  if (requestData.length) {
    requestData += '&';
  }
  requestData += 'shipping=' + encodeURIComponent(selectedValue) + '&request=ajax&oprcaction=process';

  blockPage();

  oprcShippingUpdateRequest = jQuery.ajax({
    url: ajaxUpdateShippingMethodURL,
    method: 'POST',
    data: requestData,
    dataType: 'json'
  })
  .done(function(response) {
    if (response && typeof response.redirect_url !== 'undefined' && response.redirect_url) {
      unblockPage();
      window.location.replace(response.redirect_url);
      return;
    }

    oprcApplyShippingUpdateResponse(response || {}, {
      shouldRefreshShippingContainer: false,
      shouldRefreshShippingMethods: false,
      shouldRefreshPaymentContainer: oprcIsTruthy(oprcRefreshPayment)
    });
  })
  .fail(function(xhr) {
    var messagesHtml = '';
    if (xhr && xhr.responseJSON && xhr.responseJSON.messagesHtml) {
      messagesHtml = xhr.responseJSON.messagesHtml;
    } else if (xhr && xhr.responseJSON && xhr.responseJSON.messages) {
      messagesHtml = xhr.responseJSON.messages;
    } else if (xhr && xhr.responseText) {
      messagesHtml = xhr.responseText;
    }

    if (messagesHtml) {
      if (typeof displayErrors === 'function') {
        displayErrors(messagesHtml);
      } else {
        jQuery('.messageStackErrors').html(messagesHtml);
      }
      jQuery('.messageStackErrors').attr('data-oprc-has-server-messages', 'true');
      if (typeof prepareMessages === 'function') {
        prepareMessages();
      }
    }
  })
  .always(function() {
    oprcShippingUpdateRequest = null;
    unblockPage();
  });
}

function updateForm(triggerType) {
  if (typeof triggerType === 'undefined') {
    triggerType = null;
  }

  if (triggerType === 'shipping') {
    var selectedShippingValue = oprcGetSelectedShippingValue();

    if (selectedShippingValue !== null && selectedShippingValue === oprcLastSubmittedShippingMethod) {
      return;
    }

    oprcLastSubmittedShippingMethod = selectedShippingValue;
  }

  oprcAbortRequest(oprcLoginCheckRequest);
  oprcAbortRequest(oprcUpdateFormProcessRequest);
  oprcAbortRequest(oprcShippingUpdateRequest);
  oprcLoginCheckRequest = null;
  oprcUpdateFormProcessRequest = null;
  oprcShippingUpdateRequest = null;

  blockPage();
  // clear all message stack errors
  clearMessages({ force: true });

  oprcLoginCheckRequest = oprcFetchLoginCheck();

  oprcLoginCheckRequest.done(function(loginCheck) {
    if (parseInt(loginCheck, 10) === 1) {
      var postData = jQuery("[name=checkout_payment]").serialize() + "&oprcaction=process&request=ajax";

      oprcUpdateFormProcessRequest = jQuery.post(onePageCheckoutURL, postData);

      oprcUpdateFormProcessRequest.done(function(data) {
        failCheck(data);

        var $response = jQuery(data);
        var shopBagWrapper = $response.find('#shopBagWrapper').html();
        jQuery('#shopBagWrapper').html(shopBagWrapper);

        if (oprcAJaxShippingQuotes == false) {
          //reconfigureSideMenu();
          var shippingMethods = $response.find('#shippingMethodContainer').html();
          jQuery('#shippingMethodContainer').html(shippingMethods ? shippingMethods : '');
          oprcSyncSelectedShippingSelection();
          oprcUpdateShippingMethodsPanelState();
        }

        var $discountsContainer = $response.find('#discountsContainer');
        if ($discountsContainer.length) {
          jQuery('#discountsContainer').html($discountsContainer.html());
        } else {
          jQuery('#discountsContainer').empty();
        }
        var creditCovers = oprcReadCreditCoversFlag();

        //address messageStack errors should persist (i.e. missing address)
        var oprcAddresses = $response.find('#oprcAddresses').html();
        jQuery('#oprcAddresses').html(oprcAddresses);

        var paymentContainerInfo = oprcExtractPaymentContainer($response);
        var paymentMethodContainer = paymentContainerInfo ? paymentContainerInfo.innerHtml : undefined;
        var replacePaymentContainer = function() {
          if (typeof paymentMethodContainer !== 'undefined' || (paymentContainerInfo && paymentContainerInfo.element && paymentContainerInfo.element.length)) {
            oprcReplacePaymentMethodContainer(typeof paymentMethodContainer !== 'undefined' ? paymentMethodContainer : null, {
              replacementElement: paymentContainerInfo ? paymentContainerInfo.element : null
            });
          }
        };

        if (oprcRefreshPayment == 'true') {
          replacePaymentContainer();
        } else {
          if (creditCovers === null) {
            var url_params = ajaxOrderTotalURL.match(/\?./) ? '&action=credit_check' : '?action=credit_check';
            jQuery.post(ajaxOrderTotalURL + url_params, jQuery("[name=checkout_payment]").serialize(), function(orderTotalCheck)
              {
                if (orderTotalCheck == 0 || (jQuery('#paymentMethodContainer').length > 0 && !jQuery('#paymentMethodContainer').html().trim())) {
                  replacePaymentContainer();
                } else if (!jQuery('#paymentMethodContainer').length > 0) {
                  window.location.replace(onePageCheckoutURL);
                }
              }
            );
          } else {
            if (creditCovers || (jQuery('#paymentMethodContainer').length > 0 && !jQuery('#paymentMethodContainer').html().trim())) {
              replacePaymentContainer();
            } else if (!jQuery('#paymentMethodContainer').length > 0) {
              window.location.replace(onePageCheckoutURL);
            }
          }
        }

        unblockPage();
        displayCreditUpdate();
        oprcSyncSelectedShippingSelection();
        oprcInitChangeAddressModal(true);
      }).fail(function(xhr, status, error) {
        if (status !== 'abort') {
          if (window.console && typeof window.console.error === 'function') {
            console.error('Error updating checkout form:', error || status);
          }
          unblockPage();
        }
      }).always(function() {
        oprcUpdateFormProcessRequest = null;
      });
    } else {
      // redirect to checkout
      unblockPage();
      confirm('Sorry, your session has expired.', 'Time Out', function(r)
        {
          window.location.replace(onePageCheckoutURL);
        }
      );
    }
  }).fail(function(xhr, status, error) {
    if (status !== 'abort') {
      if (window.console && typeof window.console.error === 'function') {
        console.error('Login check request failed:', error || status);
      }
      unblockPage();
    }
  }).always(function() {
    oprcLoginCheckRequest = null;
  });
}

function oprcDecodeHtmlEntities(value) {
  if (typeof value !== 'string') {
    return '';
  }

  return jQuery('<textarea/>').html(value).text();
}

function oprcGetCouponFormElement() {
  return jQuery('#discountFormot_coupon');
}

function oprcGetCouponDiscountElement() {
  return oprcGetCouponFormElement().find('.discount').first();
}

function oprcGetCouponMessages() {
  var $couponForm = oprcGetCouponFormElement();
  var $discount = oprcGetCouponDiscountElement();
  var messages = {
    appliedGeneric: '',
    appliedFormat: '',
    removed: '',
    error: '',
    invalid: '',
    removeToken: 'remove'
  };

  var $sources = [];
  if ($discount.length) {
    $sources.push($discount);
  }
  if ($couponForm.length && ($sources.length === 0 || $couponForm.get(0) !== $discount.get(0))) {
    $sources.push($couponForm);
  }

  for (var i = 0; i < $sources.length; i++) {
    var $source = $sources[i];
    if (!$source || !$source.length) {
      continue;
    }

    if (!messages.appliedGeneric) {
      var appliedGenericAttr = $source.attr('data-oprc-coupon-applied-message');
      if (typeof appliedGenericAttr === 'string') {
        messages.appliedGeneric = oprcDecodeHtmlEntities(appliedGenericAttr);
      }
    }

    if (!messages.appliedFormat) {
      var appliedFormatAttr = $source.attr('data-oprc-coupon-applied-message-format');
      if (typeof appliedFormatAttr === 'string') {
        messages.appliedFormat = oprcDecodeHtmlEntities(appliedFormatAttr);
      }
    }

    if (!messages.removed) {
      var removedAttr = $source.attr('data-oprc-coupon-removed-message');
      if (typeof removedAttr === 'string') {
        messages.removed = oprcDecodeHtmlEntities(removedAttr);
      }
    }

    if (messages.removeToken === 'remove') {
      var removeTokenAttr = $source.attr('data-oprc-coupon-remove-token');
      if (typeof removeTokenAttr === 'string' && removeTokenAttr !== '') {
        messages.removeToken = oprcDecodeHtmlEntities(removeTokenAttr);
      }
    }

    if (!messages.error) {
      var errorAttr = $source.attr('data-oprc-coupon-error-message');
      if (typeof errorAttr === 'string') {
        var decodedError = oprcDecodeHtmlEntities(errorAttr);
        messages.error = typeof decodedError === 'string' ? jQuery.trim(decodedError) : '';
      }
    }

    if (!messages.invalid) {
      var invalidAttr = $source.attr('data-oprc-coupon-invalid-message');
      if (typeof invalidAttr === 'string') {
        var decodedInvalid = oprcDecodeHtmlEntities(invalidAttr);
        messages.invalid = typeof decodedInvalid === 'string' ? jQuery.trim(decodedInvalid) : '';
      }
    }
  }

  messages.removeToken = (messages.removeToken || 'remove').toLowerCase();

  return messages;
}

function oprcExtractCouponCodeFromTitle(titleText) {
  if (typeof titleText !== 'string') {
    return null;
  }

  var normalized = jQuery.trim(titleText.replace(/\s+/g, ' '));
  if (!normalized) {
    return null;
  }

  var parenMatch = normalized.match(/\(([^)]+)\)/);
  if (parenMatch && parenMatch[1]) {
    return jQuery.trim(parenMatch[1]);
  }

  var parts = normalized.split(':');
  for (var i = parts.length - 1; i >= 0; i--) {
    var candidate = parts[i];
    if (typeof candidate !== 'string') {
      continue;
    }

    candidate = jQuery.trim(candidate.replace(/[)]+$/, ''));
    if (candidate) {
      return candidate;
    }
  }

  return null;
}

function oprcCaptureCouponState() {
  var state = {
    applied: false,
    code: null,
    inputValue: '',
    inputExists: false
  };

  var $couponForm = oprcGetCouponFormElement();
  if ($couponForm.length) {
    var $input = $couponForm.find('input[name="dc_redeem_code"]').first();
    if ($input.length) {
      state.inputExists = true;
      state.inputValue = jQuery.trim($input.val());
    }
  }

  var $couponRow = jQuery('#orderTotals .couponRemove').closest('tr');
  if ($couponRow.length) {
    state.applied = true;
    var $titleCell = $couponRow.children('td').first();
    var titleText = '';

    if ($titleCell.length) {
      var $titleClone = $titleCell.clone();
      $titleClone.find('.couponRemove').remove();
      titleText = $titleClone.text();
    } else {
      var $rowClone = $couponRow.clone();
      $rowClone.find('.couponRemove').remove();
      titleText = $rowClone.text();
    }

    state.code = oprcExtractCouponCodeFromTitle(titleText);
  }

  return state;
}

function oprcBuildCouponRequestContext(discountMod) {
  var messages = oprcGetCouponMessages();
  var context = {
    isCouponRequest: false,
    requestedCode: '',
    inputExists: false,
    removeToken: messages.removeToken,
    messages: messages
  };

  var $couponForm = oprcGetCouponFormElement();
  if ($couponForm.length) {
    var $input = $couponForm.find('input[name="dc_redeem_code"]').first();
    if ($input.length) {
      context.inputExists = true;
      context.requestedCode = jQuery.trim($input.val());
    }
  }

  if (discountMod && jQuery(discountMod).length && jQuery(discountMod).closest('#discountFormot_coupon').length) {
    context.isCouponRequest = true;
  }

  if (!context.isCouponRequest && context.requestedCode) {
    context.isCouponRequest = true;
  }

  return context;
}

function oprcCouponHasServerMessages($couponForm) {
  if (!$couponForm || !$couponForm.length) {
    return false;
  }

  var $boxContents = $couponForm.find('.boxContents');
  if (!$boxContents.length) {
    return false;
  }

  var $messages = $boxContents.find('.messageStackError, .messageStackCaution, .messageStackWarning, .messageStackSuccess').filter(function() {
    return jQuery(this).closest('.js-oprc-coupon-feedback').length === 0;
  });

  return $messages.length > 0;
}

function oprcRemoveCouponServerMessages($couponForm) {
  if (!$couponForm || !$couponForm.length) {
    return false;
  }

  var $boxContents = $couponForm.find('.boxContents');
  if (!$boxContents.length) {
    return false;
  }

  var $messages = $boxContents
    .find('.messageStackError, .messageStackCaution, .messageStackWarning, .messageStackSuccess')
    .filter(function() {
      return jQuery(this).closest('.js-oprc-coupon-feedback').length === 0;
    });

  if ($messages.length) {
    $messages.remove();
    return true;
  }

  return false;
}

function oprcRenderInlineCouponMessage(type, message) {
  if (typeof message !== 'string' || message === '') {
    return;
  }

  var $couponForm = oprcGetCouponFormElement();
  if (!$couponForm.length) {
    return;
  }

  var $boxContents = $couponForm.find('.boxContents');
  if (!$boxContents.length) {
    return;
  }

  $boxContents.find('.js-oprc-coupon-feedback').remove();

  var messageClass = 'messageStackSuccess';
  if (type === 'error') {
    messageClass = 'messageStackError';
  } else if (type === 'warning') {
    messageClass = 'messageStackWarning';
  } else if (type === 'caution') {
    messageClass = 'messageStackCaution';
  }

  var $wrapper = jQuery('<div class="disablejAlert js-oprc-coupon-feedback" role="alert" aria-live="polite"></div>');
  $wrapper.append(jQuery('<div></div>').addClass(messageClass).text(message));
  $boxContents.prepend($wrapper);

  var $heading = $couponForm.find('.discount > h3').first();
  if (typeof couponAccordion === 'function' && $heading.length) {
    couponAccordion($heading);
  }
}

function oprcSerializeCouponFormInputs() {
  var $couponForm = oprcGetCouponFormElement();
  if (!$couponForm.length) {
    return '';
  }

  var parts = [];
  var $fields = $couponForm.find('input[name="dc_redeem_code"], input[name="submit_redeem_coupon"], button[name="submit_redeem_coupon"]');

  $fields.each(function() {
    var $field = jQuery(this);
    if ($field.is(':disabled')) {
      return;
    }

    if (($field.is(':checkbox') || $field.is(':radio')) && !$field.is(':checked')) {
      return;
    }

    var name = $field.attr('name');
    if (!name) {
      return;
    }

    var value = $field.val();
    if ($field.is('button') && (typeof value === 'undefined' || value === null || value === '')) {
      value = $field.text() || '1';
    }

    if (value === null || typeof value === 'undefined') {
      value = '';
    }

    parts.push(encodeURIComponent(name) + '=' + encodeURIComponent(value));
  });

  return parts.join('&');
}

function oprcParseAjaxJsonResponse(rawData) {
  if (rawData === null || typeof rawData === 'undefined') {
    return { ok: true, data: {} };
  }

  if (typeof rawData === 'object') {
    return { ok: true, data: rawData };
  }

  if (typeof rawData === 'string') {
    var trimmed = jQuery.trim(rawData);
    if (!trimmed) {
      return { ok: true, data: {} };
    }

    var parseJson = function(text) {
      try {
        return { success: true, value: jQuery.parseJSON(text) };
      } catch (jsonError) {
        return { success: false };
      }
    };

    var directParse = parseJson(trimmed);
    if (directParse.success) {
      return { ok: true, data: directParse.value };
    }

    var firstBrace = trimmed.indexOf('{');
    var firstBracket = trimmed.indexOf('[');
    var jsonStart = -1;
    var closingChar = '';

    if (firstBrace !== -1 && (firstBracket === -1 || firstBrace < firstBracket)) {
      jsonStart = firstBrace;
      closingChar = '}';
    } else if (firstBracket !== -1) {
      jsonStart = firstBracket;
      closingChar = ']';
    }

    if (jsonStart !== -1 && closingChar) {
      var jsonEnd = trimmed.lastIndexOf(closingChar);
      if (jsonEnd !== -1 && jsonEnd >= jsonStart) {
        var candidate = trimmed.slice(jsonStart, jsonEnd + 1);
        var parsedCandidate = parseJson(candidate);

        if (parsedCandidate.success) {
          var leadingHtml = jQuery.trim(trimmed.slice(0, jsonStart));
          var trailingHtml = jQuery.trim(trimmed.slice(jsonEnd + 1));

          if (leadingHtml || trailingHtml) {
            return {
              ok: true,
              data: parsedCandidate.value,
              leadingHtml: leadingHtml || '',
              trailingHtml: trailingHtml || ''
            };
          }

          return { ok: true, data: parsedCandidate.value };
        }
      }
    }

    return { ok: false, raw: trimmed };
  }

  return { ok: false, raw: rawData };
}

function oprcHandleCouponAjaxFailure(details) {
  details = details || {};

  var requestContext = details.requestContext || {};
  if (!requestContext.isCouponRequest && !details.force) {
    return false;
  }

  var responseText = details.responseText;
  var message = null;
  var refreshPayload = null;
  var startedFallbackRefresh = false;
  var defaultInvalidCouponMessage = '';
  var shouldRefreshTotalsForInvalidCoupon = false;

  if (details.responseJson && typeof details.responseJson === 'object') {
    refreshPayload = details.responseJson;
  }

  if (typeof responseText === 'string') {
    var trimmed = jQuery.trim(responseText);
    if (trimmed) {
      if (trimmed.charAt(0) === '{' || trimmed.charAt(0) === '[') {
        var parsed = oprcParseAjaxJsonResponse(trimmed);
        if (parsed.ok && parsed.data && typeof parsed.data === 'object') {
          if (!refreshPayload) {
            refreshPayload = parsed.data;
          }

          var parsedData = parsed.data;
          if (typeof parsedData.error === 'string' && parsedData.error !== '') {
            message = parsedData.error;
          } else if (typeof parsedData.error_message === 'string' && parsedData.error_message !== '') {
            message = parsedData.error_message;
          } else if (typeof parsedData.message === 'string' && parsedData.message !== '') {
            message = parsedData.message;
          } else if (typeof parsedData.messagesHtml === 'string' && parsedData.messagesHtml !== '') {
            message = jQuery.trim(jQuery(parsedData.messagesHtml).text());
          }
        }
      } else if (trimmed.charAt(0) !== '<') {
        message = trimmed;
      }
    }
  }

  var $couponForm = oprcGetCouponFormElement();

  if (refreshPayload && typeof oprcApplyShippingUpdateResponse === 'function') {
    oprcApplyShippingUpdateResponse(refreshPayload, {
      shouldRefreshPaymentContainer: oprcIsTruthy(oprcRefreshPayment)
    });
    $couponForm = oprcGetCouponFormElement();
  }

  var messages = requestContext.messages || oprcGetCouponMessages();
  var fallbackMessage = '';

  if (messages) {
    if (typeof messages.error === 'string' && messages.error !== '') {
      fallbackMessage = jQuery.trim(messages.error);
    }

    if (typeof messages.invalid === 'string' && messages.invalid !== '') {
      defaultInvalidCouponMessage = jQuery.trim(messages.invalid);
    }
  }

  if (!defaultInvalidCouponMessage && fallbackMessage && fallbackMessage.toLowerCase().indexOf('invalid') !== -1) {
    defaultInvalidCouponMessage = fallbackMessage;
  }

  if (!message && typeof details.error === 'string' && details.error !== '') {
    message = details.error;
  }

  if (!message && fallbackMessage) {
    message = fallbackMessage;
  }

  if (typeof message === 'string') {
    message = jQuery.trim(message);
  }

  if (!message && defaultInvalidCouponMessage) {
    message = defaultInvalidCouponMessage;
  }

  if (defaultInvalidCouponMessage && message === defaultInvalidCouponMessage) {
    shouldRefreshTotalsForInvalidCoupon = true;
  }

  var ensureCouponForm = function() {
    if ($couponForm && $couponForm.length) {
      var formElement = $couponForm[0];
      var docElement = formElement && formElement.ownerDocument && formElement.ownerDocument.documentElement;

      if (formElement && docElement && jQuery.contains(docElement, formElement)) {
        return $couponForm;
      }
    }

    $couponForm = oprcGetCouponFormElement();
    return $couponForm;
  };

  var renderInlineError = function() {
    var $form = ensureCouponForm();
    if (!$form || !$form.length) {
      return;
    }

    oprcRemoveCouponServerMessages($form);
    oprcRenderInlineCouponMessage('error', message);
    if (typeof prepareMessages === 'function') {
      prepareMessages();
    }
  };

  if (!$couponForm.length) {
    startedFallbackRefresh = oprcRefreshCouponSectionsFromCheckout(function() {
      ensureCouponForm();
      renderInlineError();
    });

    if (startedFallbackRefresh) {
      return true;
    }

    return false;
  }

  if (oprcCouponHasServerMessages($couponForm)) {
    var removedServerMessages = oprcRemoveCouponServerMessages($couponForm);
    if (!removedServerMessages && oprcCouponHasServerMessages($couponForm)) {
      if (typeof prepareMessages === 'function') {
        prepareMessages();
      }
      return true;
    }
  }

  if (!refreshPayload || (shouldRefreshTotalsForInvalidCoupon && !startedFallbackRefresh)) {
    startedFallbackRefresh = oprcRefreshCouponSectionsFromCheckout(function() {
      ensureCouponForm();
      renderInlineError();
    });

    if (startedFallbackRefresh) {
      return true;
    }
  }

  renderInlineError();

  return true;
}

function oprcHandleCouponStateChange(requestContext, beforeState, afterState) {
  requestContext = requestContext || {};
  beforeState = beforeState || {};
  afterState = afterState || {};

  var $couponForm = oprcGetCouponFormElement();
  if (!$couponForm.length) {
    return;
  }

  $couponForm.find('.js-oprc-coupon-feedback').remove();

  var messages = requestContext.messages || oprcGetCouponMessages();
  var removeToken = (requestContext.removeToken || (messages && messages.removeToken) || 'remove').toString().toLowerCase();
  var requestedCode = (requestContext.requestedCode || '').toString();
  var normalizedRequestedCode = requestedCode.toLowerCase();
  var removeRequested = normalizedRequestedCode === removeToken;

  if (!requestContext.isCouponRequest && !removeRequested) {
    return;
  }

  if (removeRequested && requestContext.inputExists) {
    $couponForm.find('input[name="dc_redeem_code"]').val('');
  }

  if (oprcCouponHasServerMessages($couponForm)) {
    var clearedServerMessages = oprcRemoveCouponServerMessages($couponForm);
    if (!clearedServerMessages && oprcCouponHasServerMessages($couponForm)) {
      if (typeof prepareMessages === 'function') {
        prepareMessages();
      }
      return;
    }
  }

  var messageText = null;

  if (afterState.applied && !beforeState.applied) {
    var appliedCode = afterState.code || (removeRequested ? '' : requestedCode);

    if (appliedCode && messages && typeof messages.appliedFormat === 'string' && messages.appliedFormat.indexOf('%s') !== -1) {
      messageText = messages.appliedFormat.replace('%s', appliedCode);
    } else if (messages && messages.appliedGeneric) {
      messageText = messages.appliedGeneric;
    }
  } else if (beforeState.applied && !afterState.applied) {
    if (messages && messages.removed) {
      messageText = messages.removed;
    }
  }

  if (messageText) {
    oprcRenderInlineCouponMessage('success', messageText);
    if (typeof prepareMessages === 'function') {
      prepareMessages();
    }
  }
}

function oprcRefreshCouponSectionsFromCheckout(onComplete) {
  if (typeof ajaxOrderTotalURL !== 'string' || !ajaxOrderTotalURL) {
    if (typeof onComplete === 'function') {
      onComplete();
    }
    return false;
  }

  var urlParams = ajaxOrderTotalURL.match(/\?./) ? '&action=refresh' : '?action=refresh';
  var jqxhr = jQuery.get(ajaxOrderTotalURL + urlParams)
    .done(function(data) {
      var $response = jQuery(data);
      var $shopBagWrapper = $response.find('#shopBagWrapper');
      if ($shopBagWrapper.length) {
        jQuery('#shopBagWrapper').html($shopBagWrapper.html());
      }

      var $discountsContainer = $response.find('#discountsContainer');
      if ($discountsContainer.length) {
        jQuery('#discountsContainer').html($discountsContainer.html());
      }

      if (typeof reconfigureSideMenu === 'function') {
        reconfigureSideMenu();
      }
      if (typeof displayCreditUpdate === 'function') {
        displayCreditUpdate();
      }

      oprcInitChangeAddressModal(true);
      oprcSyncSelectedShippingSelection();
    })
    .always(function() {
      if (typeof prepareMessages === 'function') {
        prepareMessages();
      }
      if (typeof onComplete === 'function') {
        onComplete();
      }
    });

  return !!jqxhr;
}

function updateCredit(discountMod) {
  var $discountMod = discountMod ? jQuery(discountMod) : null;
  var couponRequestContext = oprcBuildCouponRequestContext($discountMod);
  var couponStateBefore = oprcCaptureCouponState();

  blockPage();
  clearMessages({ force: true });

  oprcFetchLoginCheck()
    .done(function(loginCheck) {
      if (parseInt(loginCheck, 10) !== 1) {
        if (typeof window.sessionStorage !== 'undefined') {
          try {
            window.sessionStorage.setItem(sessionExpiredStorageKey, 'true');
          } catch (storageError) {}
        }

        unblockPage();
        window.location.replace(onePageCheckoutURL);
        return;
      }

      var baseRequestData = jQuery('[name="checkout_payment"]').serialize();
      var couponRequestData = oprcSerializeCouponFormInputs();
      var requestParts = [];

      if (baseRequestData) {
        requestParts.push(baseRequestData);
      }
      if (couponRequestData) {
        requestParts.push(couponRequestData);
      }
      requestParts.push('request=ajax&oprcaction=updateCredit');

      var requestData = requestParts.join('&');

      jQuery.ajax({
        url: ajaxUpdateCreditURL,
        method: 'POST',
        data: requestData,
        dataType: 'text'
      })
      .done(function(rawResponse) {
        var parseResult = oprcParseAjaxJsonResponse(rawResponse);
        if (!parseResult.ok) {
          var inlineHandled = oprcHandleCouponAjaxFailure({
            requestContext: couponRequestContext,
            responseText: parseResult.raw,
            force: couponRequestContext && couponRequestContext.isCouponRequest
          });

          if (!inlineHandled && parseResult.raw) {
            var fallbackContent = parseResult.raw;
            if (typeof fallbackContent === 'string' && fallbackContent.charAt(0) !== '<') {
              fallbackContent = oprcBuildFallbackMessageHtml(fallbackContent);
            }

            if (typeof displayErrors === 'function') {
              displayErrors(fallbackContent);
            } else {
              jQuery('.messageStackErrors').html(fallbackContent);
            }
            if (typeof prepareMessages === 'function') {
              prepareMessages();
            }
          }

          if (couponRequestContext && couponRequestContext.inputExists && couponRequestContext.requestedCode && couponRequestContext.removeToken && couponRequestContext.requestedCode.toLowerCase() === couponRequestContext.removeToken) {
            var $couponFormOnParseError = oprcGetCouponFormElement();
            if ($couponFormOnParseError.length) {
              $couponFormOnParseError.find('input[name="dc_redeem_code"]').val('');
            }
          }

          unblockPage();
          return;
        }

        var response = parseResult.data || {};
        var combinedMessages = '';
        if (parseResult.leadingHtml) {
          combinedMessages += parseResult.leadingHtml;
        }

        if (response && typeof response.messagesHtml === 'string' && response.messagesHtml) {
          combinedMessages += response.messagesHtml;
        }

        if (parseResult.trailingHtml) {
          combinedMessages += parseResult.trailingHtml;
        }

        if (combinedMessages) {
          response.messagesHtml = combinedMessages;
        }
        if (response && typeof response.redirect_url !== 'undefined' && response.redirect_url) {
          unblockPage();
          window.location.replace(response.redirect_url);
          return;
        }

        oprcApplyShippingUpdateResponse(response, {
          shouldRefreshPaymentContainer: oprcIsTruthy(oprcRefreshPayment)
        });

        jQuery('#discountsContainer .discount').each(function() {
          if (jQuery(this).find('.messageStackError, .messageStackCaution, .messageStackWarning, .messageStackSuccess').length > 0) {
            couponAccordion(jQuery(this).find('h3'));
          }
        });

        var couponStateAfter = oprcCaptureCouponState();
        oprcHandleCouponStateChange(couponRequestContext, couponStateBefore, couponStateAfter);

        unblockPage();
      })
      .fail(function(xhr, status, error) {
        if (status !== 'abort') {
          var inlineHandled = oprcHandleCouponAjaxFailure({
            requestContext: couponRequestContext,
            responseText: xhr && typeof xhr.responseText === 'string' ? xhr.responseText : '',
            responseJson: xhr && typeof xhr.responseJSON === 'object' ? xhr.responseJSON : null,
            error: error,
            force: couponRequestContext && couponRequestContext.isCouponRequest
          });

          var errorHtml = '';
          if (xhr && xhr.responseJSON && xhr.responseJSON.messagesHtml) {
            errorHtml = xhr.responseJSON.messagesHtml;
          } else if (xhr && xhr.responseText) {
            errorHtml = xhr.responseText;
          } else if (error) {
            errorHtml = error;
          }

          if (errorHtml && !inlineHandled) {
            if (typeof displayErrors === 'function') {
              displayErrors(errorHtml);
            } else {
              jQuery('.messageStackErrors').html(errorHtml);
            }
            if (typeof prepareMessages === 'function') {
              prepareMessages();
            }
          }
        }

        if (couponRequestContext && couponRequestContext.inputExists && couponRequestContext.requestedCode && couponRequestContext.removeToken && couponRequestContext.requestedCode.toLowerCase() === couponRequestContext.removeToken) {
          var $couponForm = oprcGetCouponFormElement();
          if ($couponForm.length) {
            $couponForm.find('input[name="dc_redeem_code"]').val('');
          }
        }
        unblockPage();
      });
    })
    .fail(function(xhr, status, error) {
      if (status !== 'abort') {
        if (window.console && typeof window.console.error === 'function') {
          console.error('Login check request failed:', error || status);
        }
        unblockPage();
      }
    });
}

// display the credit class update button if it exists
function displayCreditUpdate() {
  if (jQuery(".updateButton").length > 0) {
    jQuery(".updateButton").removeAttr("style");
  }
}

function oprcDispatchCheckoutSubmitEvent($form, formElement)
{
  var defaultResult = { prevented: false, shouldUnblock: false };

  if (!$form || !$form.length) {
    return defaultResult;
  }

  var prevented = false;
  var shouldUnblock = false;

  if (formElement && typeof formElement.dispatchEvent === 'function') {
    var submitEvent;
    if (typeof Event === 'function') {
      submitEvent = new Event('submit', { bubbles: true, cancelable: true });
    } else {
      submitEvent = document.createEvent('Event');
      submitEvent.initEvent('submit', true, true);
    }

    // Allow submit handlers to explicitly request that the processing overlay
    // be hidden if they cancel submission.
    submitEvent.oprcShouldUnblockProcessingOverlay = false;

    prevented = !formElement.dispatchEvent(submitEvent);
    shouldUnblock = submitEvent.oprcShouldUnblockProcessingOverlay === true;
  } else {
    var jqSubmitEvent = jQuery.Event('submit');
    // Allow jQuery-based handlers to explicitly request that the processing
    // overlay be hidden if they cancel submission.
    jqSubmitEvent.oprcShouldUnblockProcessingOverlay = false;

    $form.trigger(jqSubmitEvent);

    prevented = jqSubmitEvent.isDefaultPrevented();
    shouldUnblock = jqSubmitEvent.oprcShouldUnblockProcessingOverlay === true;
  }

  return {
    prevented: prevented,
    shouldUnblock: shouldUnblock
  };
}

function oprcRunCheckoutSubmitHandlers()
{
  var $form = jQuery('[name="checkout_payment"]');
  if (!$form.length) {
    return { prevented: false, shouldUnblock: false };
  }

  var formElement = $form.get(0);
  var previousAllowState = oprcAllowNativeCheckoutSubmit;
  var dispatchResult = { prevented: false, shouldUnblock: false };

  oprcAllowNativeCheckoutSubmit = true;

  try {
    dispatchResult = oprcDispatchCheckoutSubmitEvent($form, formElement);
  } finally {
    oprcAllowNativeCheckoutSubmit = previousAllowState;
  }

  return dispatchResult;
}

function oprcSubmitCheckoutFormNatively()
{
  var $form = jQuery('[name="checkout_payment"]');
  if (!$form.length) {
    return;
  }

  var formElement = $form.get(0);
  var previousAllowState = oprcAllowNativeCheckoutSubmit;

  oprcAllowNativeCheckoutSubmit = true;

  try {
    if (formElement && typeof formElement.requestSubmit === 'function') {
      formElement.requestSubmit();
      return;
    }

    var dispatchResult = oprcDispatchCheckoutSubmitEvent($form, formElement);
    if (!dispatchResult.prevented && formElement && typeof formElement.submit === 'function') {
      formElement.submit();
    }
  } finally {
    oprcAllowNativeCheckoutSubmit = previousAllowState;
  }
}

function oprcProcessCheckoutSubmission(event) {
  checkAddress();
  blockPage(true, false);
  clearMessages({ force: true });
  submitFunction(parseFloat(oprcZenUserHasGVAccount), parseFloat(oprcTotalOrder));

  if (!check_payment_form("checkout_payment") || oprcAddressMissing == 'true') {
    if (oprcAddressMissing == 'true') {
      if (jQuery('.messageStackError').length > 0) {
        jQuery('html, body').animate(
          {
            scrollTop: jQuery('.messageStackError').offset().top
          }, 2000
        );
      } else {
        jQuery('html, body').animate(
          {
            scrollTop: jQuery('#paymentMethodContainer').offset().top
          }, 2000
        );
      }
    }

    unblockPage();
    return false;
  }

  if (oprcAJAXConfirmStatus == 'false') {
    oprcSubmitCheckoutFormNatively();
    return false;
  }

  if (jQuery('[name="dc_redeem_code"]').length > 0 && jQuery('[name="dc_redeem_code"]').val().length > 0) {
    updateCredit();
    return false;
  }

  // Get the selected payment method to execute any registered callbacks
  var $selectedPayment = jQuery('[name="payment"]:checked');
  var selectedPaymentCode = $selectedPayment.length > 0 ? $selectedPayment.val() : '';

  if (selectedPaymentCode && oprcPaymentPreSubmitCallbacks.length > 0) {
    console.log('OPRC: Executing payment pre-submit callbacks for:', selectedPaymentCode);
    
    // Execute payment module callbacks with the processing overlay already showing
    oprcExecutePaymentPreSubmitCallbacks(selectedPaymentCode)
      .then(function() {
        console.log('OPRC: Payment callbacks completed, continuing submission');
        // Continue with normal submission flow after callbacks complete
        oprcContinueCheckoutSubmissionAfterCallbacks();
      })
      .catch(function(error) {
        console.error('OPRC: Payment callback failed, aborting submission:', error);
        unblockPage();
        // Error handling is done by the callback itself
      });
    
    return false;
  }

  // No callbacks registered, continue with normal flow
  oprcContinueCheckoutSubmissionAfterCallbacks();
  return false;
}

/**
 * Continue checkout submission after payment callbacks have completed.
 * This is split out to allow async payment callbacks to complete before submission.
 */
function oprcContinueCheckoutSubmissionAfterCallbacks() {
  var submissionResult = oprcRunCheckoutSubmitHandlers();
  if (submissionResult.prevented) {
    if (submissionResult.shouldUnblock) {
      unblockPage();
    }
    return false;
  }

  var shouldAllowDefault = submitCheckout();
  if (!shouldAllowDefault) {
    return false;
  }

  oprcSubmitCheckoutFormNatively();
  return false;
}

function submitCheckout() {
  if (oprcOnePageStatus != 'true') {
    return true;
  }
  if (oprcAJAXConfirmStatus == 'false') {
    if (jQuery('textarea[name="comments"]').val().length > 0) {
      // In case the comments field has special characters, do not use AJAX post if AJAX status is false.
    }

    oprcSubmitCheckoutFormNatively();
    return true;
  }
  // clear validation errors
  jQuery('.validation').remove();
  jQuery('.missing').removeClass('missing');
  // clear error messages
  clearMessages({ force: true });
  oprcFetchLoginCheck().done(function(loginCheck)
    {
      if (parseInt(loginCheck) == 1) {
        var $checkoutForm = jQuery('[name="checkout_payment"]');
        if ($checkoutForm.length === 0) {
          failCheckConfirmation('');
          return false;
        }

        var basePayload = $checkoutForm.serializeArray();
        basePayload.push({
          name: 'request',
          value: 'ajax'
        });

        var $syntheticForm = jQuery('<form></form>')
          .attr('name', 'checkout_confirmation')
          .attr('method', 'post')
          .attr('action', ajaxCheckoutProcessURL);

        jQuery.each(basePayload, function(index, field) {
          jQuery('<input type="hidden" />')
            .attr('name', field.name)
            .val(field.value)
            .appendTo($syntheticForm);
        });

        var submissionContext = {
          form: $syntheticForm,
          payload: basePayload.slice(),
          payloadOverride: false,
          ajaxSettings: {},
          setPayload: function(newPayload) {
            this.payload = newPayload;
            this.payloadOverride = true;
          },
          setAjaxSettings: function(settings) {
            if (typeof settings === 'object' && settings !== null) {
              this.ajaxSettings = jQuery.extend(true, this.ajaxSettings, settings);
            }
          }
        };

        var renderMessages = function(messagesHtml) {
          if (typeof displayErrors === 'function') {
            displayErrors(messagesHtml || '');
          } else {
            jQuery('.messageStackErrors').html(messagesHtml || '');
          }
        };

        var runPostResponseHooks = function() {
          if (typeof prepareMessages === 'function') {
            prepareMessages();
          }
          if (typeof reconfigureSideMenu === 'function') {
            reconfigureSideMenu();
          }
          if (typeof displayCreditUpdate === 'function') {
            displayCreditUpdate();
          }
          setTimeout(function() {
            scrollToError();
          }, 500);
        };

        var handleRedirect = function(redirectUrl, messagesHtml) {
          if (!redirectUrl) {
            return false;
          }

          var normalizedRedirectUrl = (redirectUrl || '').toString().toLowerCase();
          var isConfirmationRedirect = normalizedRedirectUrl.indexOf('checkout_confirmation') !== -1 || normalizedRedirectUrl.indexOf('main_page=checkout_confirmation') !== -1;

          if (isConfirmationRedirect && messagesHtml) {
            renderMessages(messagesHtml);
            oprcAllowNativeCheckoutSubmit = false;
            runPostResponseHooks();
            unblockPage();
            return true;
          }

          unblockPage();
          window.location.assign(redirectUrl);
          return true;
        };

        oprcCheckoutSubmitCallback();

        // Inform payment modules that the confirmation form has been loaded.
        jQuery(document).trigger('oprc:checkoutConfirmationLoaded', [$syntheticForm]);

        var beforeProcessEvent = jQuery.Event('oprc:beforeCheckoutProcess');
        // Allow payment modules to keep the processing overlay active while they
        // handle asynchronous work by omitting the unblock request.
        beforeProcessEvent.oprcShouldUnblockProcessingOverlay = false;
        jQuery(document).trigger(beforeProcessEvent, [$syntheticForm, submissionContext]);
        if (beforeProcessEvent.isDefaultPrevented()) {
          if (beforeProcessEvent.oprcShouldUnblockProcessingOverlay === true) {
            unblockPage();
          }
          return false;
        }

        var finalPayload;
        if (submissionContext.payloadOverride) {
          finalPayload = submissionContext.payload;
        } else {
          finalPayload = $syntheticForm.serializeArray();
        }

        var ajaxData = finalPayload;
        var processData = true;
        var contentType = 'application/x-www-form-urlencoded; charset=UTF-8';

        if (typeof window.FormData !== 'undefined' && finalPayload instanceof window.FormData) {
          processData = false;
          contentType = false;
        } else if (Array.isArray(finalPayload)) {
          ajaxData = jQuery.param(finalPayload);
        }

        var ajaxOptions = jQuery.extend(true, {
          url: ajaxCheckoutProcessURL,
          type: 'POST',
          dataType: 'json',
          data: ajaxData,
          processData: processData,
          contentType: contentType
        }, submissionContext.ajaxSettings);

        jQuery.ajax(ajaxOptions)
          .done(function(response) {
            var messagesHtml = response && response.messages ? response.messages : '';

            if (response && response.status === 'requires_external' && response.confirmation_form) {
              var confirmationForm = response.confirmation_form || {};
              renderMessages(messagesHtml);
              oprcAllowNativeCheckoutSubmit = false;

              var buildForm = function(formSpec) {
                var formMethod = (formSpec.method || 'post').toString().toLowerCase();
                if (formMethod !== 'get' && formMethod !== 'post') {
                  formMethod = 'post';
                }

                var $form = jQuery('<form></form>')
                  .attr('method', formMethod)
                  .attr('action', formSpec.action || '')
                  .css({ position: 'absolute', left: '-10000px', width: '1px', height: '1px', overflow: 'hidden' });

                var appendField = function(prefix, value) {
                  if (jQuery.isArray(value)) {
                    for (var i = 0; i < value.length; i++) {
                      appendField(prefix + '[]', value[i]);
                    }
                    return;
                  }
                  if (value !== null && typeof value === 'object') {
                    jQuery.each(value, function(childKey, childValue) {
                      appendField(prefix + '[' + childKey + ']', childValue);
                    });
                    return;
                  }

                  jQuery('<input type="hidden" />')
                    .attr('name', prefix)
                    .val(value)
                    .appendTo($form);
                };

                if (formSpec.fields && typeof formSpec.fields === 'object') {
                  jQuery.each(formSpec.fields, function(fieldName, fieldValue) {
                    appendField(fieldName, fieldValue);
                  });
                }

                var hasParsedFields = formSpec.fields && typeof formSpec.fields === 'object' && !jQuery.isEmptyObject(formSpec.fields);
                if (!hasParsedFields && typeof formSpec.raw_html === 'string' && formSpec.raw_html.length > 0) {
                  $form.append(formSpec.raw_html);
                }

                return $form;
              };

              var $redirectForm = buildForm(confirmationForm);
              var externalEvent = jQuery.Event('oprc:beforeExternalFormSubmit');
              jQuery(document).trigger(externalEvent, [$redirectForm, submissionContext, response]);

              if (externalEvent.isDefaultPrevented()) {
                runPostResponseHooks();
                unblockPage();
                return;
              }

              jQuery('body').append($redirectForm);

              var autoSubmit = true;
              if (typeof confirmationForm.auto_submit !== 'undefined') {
                autoSubmit = !!confirmationForm.auto_submit;
              }

              if (autoSubmit && $redirectForm.length) {
                setTimeout(function() {
                  $redirectForm.trigger('submit');
                  if ($redirectForm.length && $redirectForm[0] && typeof $redirectForm[0].submit === 'function') {
                    $redirectForm[0].submit();
                  }
                }, 0);
              } else {
                runPostResponseHooks();
                unblockPage();
              }

              return;
            }

            if (response && response.redirect_url) {
              if (handleRedirect(response.redirect_url, messagesHtml)) {
                return;
              }
            }

            renderMessages(messagesHtml);

            if (messagesHtml) {
              oprcAllowNativeCheckoutSubmit = false;
            }

            runPostResponseHooks();

            unblockPage();
          })
          .fail(function(xhr) {
            var response = xhr && xhr.responseJSON ? xhr.responseJSON : null;
            var messagesHtml = '';
            var redirectUrl = null;

            if (response) {
              messagesHtml = response.messages || '';
              redirectUrl = response.redirect_url || null;
            } else if (xhr && xhr.responseText) {
              try {
                var parsed = JSON.parse(xhr.responseText);
                if (parsed && typeof parsed === 'object') {
                  messagesHtml = parsed.messages || '';
                  redirectUrl = parsed.redirect_url || null;
                } else {
                  messagesHtml = xhr.responseText;
                }
              } catch (e) {
                messagesHtml = xhr.responseText;
              }
            }

            if (redirectUrl && handleRedirect(redirectUrl, messagesHtml)) {
              return;
            }

            if (messagesHtml) {
              oprcAllowNativeCheckoutSubmit = false;
            }

            renderMessages(messagesHtml);
            runPostResponseHooks();
            unblockPage();
          });
        return false;
      } else {
        // redirect to checkout
        prompt('Sorry, your session has expired.', 'Time Out', function(r)
          {
            window.location.replace(onePageCheckoutURL);
          }
        );
      }
    });
  return false;
}

function scrollToAddresses() {
  if (jQuery("#oprcAddresses").length > 0) {
    jQuery('html, body').animate(
      {
      scrollTop: jQuery("#oprcAddresses").offset().top
      }, 1000
    );
  }
}

function scrollToError() {
  // Only scroll to actual error/warning messages, not success messages
  var $errorMessages = jQuery(oprcAlertMessagesSelector).not('.' + oprcSuccessMessageClass);
  if ($errorMessages.length > 0) {
    // scroll to top error message
    jQuery('html, body').animate(
      {
      scrollTop: $errorMessages.first().offset().top
      }, 1000
    );
  }
}

function scrollToValidationError() {
  setTimeout(function() {
    // Only scroll to actual errors, not success messages
    var $firstError = jQuery('.validation:first, .registrationError:first, ' + oprcAlertMessagesSelector + ':not(.' + oprcSuccessMessageClass + '):first');
    if ($firstError.length > 0) {
      var scrollTarget = $firstError.offset().top - 100; // 100px offset from top
      jQuery('html, body').animate({
        scrollTop: scrollTarget
      }, 500);
    }
  }, 100);
}

function scrollToRegistration() {
  jQuery('html, body').animate(
    {
    scrollTop: jQuery("html, body").offset().top
    }, 1000
  );
}

function failCheck(data) {
  if (data.length == 0) {
    window.location.replace(onePageCheckoutURL);
  }
}

function failCheckConfirmation(data) {
  if (typeof data === 'string') {
    data = data.trim();
  }

  if (data) {
    return true;
  }

  var fallbackMessage = '';
  if (typeof oprcConfirmationLoadErrorMessage === 'string' && oprcConfirmationLoadErrorMessage.length > 0) {
    fallbackMessage = oprcConfirmationLoadErrorMessage;
  } else {
    fallbackMessage = 'We were unable to load the confirmation step. Please refresh the page and try again.';
  }

  var messageHtml = '<div class="disablejAlert alert"><div class="messageStackError larger">' + jQuery('<div></div>').text(fallbackMessage).html() + '</div></div>';
  if (typeof displayErrors === 'function') {
    displayErrors(messageHtml);
  } else {
    jQuery('.messageStackErrors').html(messageHtml);
  }

  oprcAllowNativeCheckoutSubmit = false;
  unblockPage();
  scrollToError();

  return false;
}

function savedCardsLayout() {
  var saveCardOptions = jQuery('#pmt-authorizenet_saved_cards'),
  saveCards = saveCardOptions.closest('.payment-method'),
  paymentMethod = jQuery('.payment-method'),
  classSavedMethod = 'pmt-authorizenet_saved_cards';

  if (!saveCardOptions.length) {
    return;
  }

  saveCardOptions.closest('.nmx-radio').hide();
  saveCardOptions.addClass(classSavedMethod);
  saveCards.addClass('payment-method--saved-cards');

  paymentMethod.find('.nmx-radio > label > input:first').each(function(index, el)
    {
      jQuery(this).addClass('payment-radio');
    }
  );

  jQuery('.payment-radio').off('change.oprcSavedCards').on('change.oprcSavedCards', function()
    {
      if (!jQuery(this).hasClass(classSavedMethod)) {
        saveCards.find('input').each(function(index, el)
          {
            jQuery(this).prop('checked', false);
          }
        );
      }
    }
  );

  saveCards.find('input').off('change.oprcSavedCards').on('change.oprcSavedCards', function(event)
    {
      if (!saveCardOptions.is(':checked')) {
        saveCardOptions.trigger('click');
      }
    }
  );
}

function oprcUpdatePaymentMethodSelectionState() {
  jQuery('#paymentModules .payment-method').each(function()
    {
      var $paymentMethod = jQuery(this);
      var isChecked = $paymentMethod.find('input[name="payment"]:checked').length > 0;

      $paymentMethod.toggleClass('payment-method--selected', isChecked);
    }
  );
}

function ajaxLoadShippingQuote(isCartContentChanged)
{
  if (oprcAJaxShippingQuotes == true) {
    oprcRestoreShippingContainerStructure();
    oprcRememberShippingContainerTemplate();
  }

  var $shippingMethods = jQuery('#shippingMethods');

  if (!$shippingMethods.length) {
    oprcUpdateShippingMethodsPanelState();
    return;
  }

  $shippingMethods.html('Loading ...');
  oprcUpdateShippingMethodsPanelState();

  $shippingMethods.load(ajaxShippingQuotesURL, function(response, status, xhr)
    {
      if (status == "error") {
        $shippingMethods.html('Error:' + xhr.status + ' ' + xhr.statusText);
        oprcUpdateShippingMethodsPanelState();
      } else {
        if (!response) {
          jQuery('#shippingMethodContainer').hide();
          isCartContentChanged = true;
          oprcLastSubmittedShippingMethod = null;
        } else {
          jQuery('#shippingMethodContainer').show();
          oprcSyncSelectedShippingSelection();
        }

        oprcUpdateShippingMethodsPanelState();

        if (isCartContentChanged == true) {
          // contents need to be loaded again to get refreshed order total
          var url_params = ajaxOrderTotalURL.match(/\?./)? '&action=refresh': '?action=refresh';
          jQuery.get(ajaxOrderTotalURL + url_params, function(data)
            {
              var shopBagWrapper = jQuery(data).find('#shopBagWrapper').html();
              jQuery('#shopBagWrapper').html(shopBagWrapper);
            }
          );
          reconfigureSideMenu();
        }
        oprcInitChangeAddressModal(true);
      }
    }
  );
}

function checkPageErrors() {
  var u = window.location.href;
  jQuery.get(u, function(data)
    {
      var el = $('<div></div>');
      el.html(data);

      var hasNonLoginErrors = el
        .find('.messageStackError, .messageStackCaution, .messageStackWarning')
        .filter(function()
          {
            return jQuery(this).closest('#oprc_login').length === 0;
          }
        )
        .length > 0;

      if (hasNonLoginErrors) {
        location.reload(true);
      }
    }
  );
}

// new
jQuery(document).off('click.oprc', '.nmx-accordion-title').on('click.oprc', '.nmx-accordion-title', function(e)
  {
    jQuery(this).toggleClass('is-open');
    jQuery(this).next().slideToggle();
  }
);
// new
function checkAddress() {
  if (jQuery('#oprcAddresses #checkoutBillto address').length == 0 && jQuery('#ignoreAddressCheck').length == 0) {
    oprcAddressMissing = 'true';
  }
  else {
    oprcAddressMissing = 'false';
  }
}
