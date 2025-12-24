(function(window) {
  'use strict';

  var overlayId = 'oprc-processing-overlay';

  function getOverlay() {
    return document.getElementById(overlayId);
  }

  function showOverlay() {
    var overlay = getOverlay();

    if (!overlay) {
      return;
    }

    if (overlay._oprcHideTimeout) {
      window.clearTimeout(overlay._oprcHideTimeout);
      overlay._oprcHideTimeout = null;
    }

    if (overlay._oprcHideHandler) {
      overlay.removeEventListener('transitionend', overlay._oprcHideHandler);
      overlay._oprcHideHandler = null;
    }

    overlay.setAttribute('aria-hidden', 'false');
    overlay.style.display = 'flex';

    // Force layout before toggling the active state so transitions fire consistently.
    overlay.getBoundingClientRect();
    overlay.classList.add('is-active');
  }

  function finalizeHide(overlay, callback) {
    overlay.style.display = 'none';
    overlay.setAttribute('aria-hidden', 'true');

    if (typeof callback === 'function') {
      callback();
    }
  }

  function hideOverlay(callback) {
    var overlay = getOverlay();

    if (!overlay) {
      if (typeof callback === 'function') {
        callback();
      }
      return;
    }

    var finish = function() {
      if (overlay._oprcHideHandler) {
        overlay.removeEventListener('transitionend', overlay._oprcHideHandler);
        overlay._oprcHideHandler = null;
      }

      if (overlay._oprcHideTimeout) {
        window.clearTimeout(overlay._oprcHideTimeout);
        overlay._oprcHideTimeout = null;
      }

      finalizeHide(overlay, callback);
    };

    if (!overlay.classList.contains('is-active')) {
      finish();
      return;
    }

    var handler = function(event) {
      if (event && event.propertyName && event.propertyName !== 'opacity') {
        return;
      }

      overlay.removeEventListener('transitionend', handler);
      overlay._oprcHideHandler = null;
      finish();
    };

    overlay._oprcHideHandler = handler;
    overlay.addEventListener('transitionend', handler);

    overlay.classList.remove('is-active');

    // Some mobile browsers defer repaints of fixed overlays until the next
    // user interaction (like scrolling), which makes the processing layer
    // appear "stuck". Forcing a synchronous layout after toggling the class
    // ensures the fade-out transition starts immediately.
    if (typeof overlay.getBoundingClientRect === 'function') {
      overlay.getBoundingClientRect();
    }

    overlay._oprcHideTimeout = window.setTimeout(function() {
      if (!overlay.classList.contains('is-active')) {
        finish();
      }
    }, 250);
  }

  window.oprcGetProcessingOverlay = getOverlay;
  window.oprcShowProcessingOverlay = showOverlay;
  window.oprcHideProcessingOverlay = hideOverlay;
})(window);
