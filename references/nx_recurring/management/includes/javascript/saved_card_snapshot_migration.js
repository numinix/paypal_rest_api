(function (window, document) {
  'use strict';

  var config = window.nmxSavedCardSnapshotConfig || {};
  var startButton = document.getElementById('sccr-start');
  var removeButton = document.getElementById('sccr-remove');
  var messages = document.getElementById('sccr-migration-messages');
  var pendingEl = document.getElementById('sccr-pending-count');
  var completedEl = document.getElementById('sccr-completed-count');
  var lastIdEl = document.getElementById('sccr-last-id');
  var issuesContainer = document.getElementById('sccr-issues');

  if (!messages) {
    return;
  }

  var securityToken = typeof config.securityToken !== 'undefined' ? config.securityToken : null;
  var processUrl = config.processUrl || '';
  var removeUrl = config.removeUrl || '';

  function messageClass(type) {
    switch (type) {
      case 'success':
        return 'nmx-alert nmx-alert-success';
      case 'error':
        return 'nmx-alert nmx-alert-error';
      case 'warning':
      case 'caution':
        return 'nmx-alert nmx-alert-warning';
      default:
        return 'nmx-alert nmx-alert-info';
    }
  }

  function appendMessage(text, type) {
    if (!messages) {
      return;
    }

    var div = document.createElement('div');
    div.className = messageClass(type);
    div.textContent = text;
    messages.appendChild(div);
  }

  function clearMessages() {
    if (messages) {
      messages.innerHTML = '';
    }
  }

  function updateStats(stats, lastId) {
    if (stats) {
      if (typeof stats.pending !== 'undefined' && pendingEl) {
        pendingEl.textContent = stats.pending;
      }
      if (typeof stats.completed !== 'undefined' && completedEl) {
        completedEl.textContent = stats.completed;
      }
    }
    if (typeof lastId !== 'undefined' && lastIdEl) {
      lastIdEl.textContent = lastId;
    }
  }

  function toggleButtons(running, complete) {
    if (startButton) {
      startButton.disabled = !!running;
      startButton.classList.toggle('is-loading', !!running);
      startButton.style.display = complete ? 'none' : '';
    }
    if (removeButton) {
      removeButton.style.display = complete ? '' : 'none';
    }
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

  function handleIssues(issues) {
    if (!issuesContainer) {
      return;
    }

    if (!issues || !issues.length) {
      return;
    }

    issuesContainer.style.display = '';
    var list = issuesContainer.querySelector('ul');
    if (!list) {
      return;
    }

    list.innerHTML = '';
    issues.forEach(function (issue) {
      var li = document.createElement('li');
      li.textContent = issue;
      list.appendChild(li);
    });
  }

  function processBatch() {
    if (!securityToken) {
      appendMessage('Unable to continue: Missing security token for this session.', 'error');
      if (startButton) {
        startButton.disabled = false;
      }
      return;
    }

    toggleButtons(true, false);
    clearMessages();
    appendMessage('Processing next batch...', 'success');

    fetch(processUrl, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
      },
      body: buildFormBody()
    })
      .then(function (response) {
        if (!response.ok) {
          throw new Error('Unexpected response from the server.');
        }
        return response.json();
      })
      .then(function (data) {
        if (data && data.message) {
          appendMessage(data.message, data.success ? 'success' : 'error');
        }
        if (data && data.stats) {
          updateStats(data.stats, data.lastProcessedId);
        }
        if (data && data.complete) {
          toggleButtons(false, true);
        } else {
          toggleButtons(false, false);
        }
        if (data && data.issues && data.issues.length) {
          handleIssues(data.issues);
        }
      })
      .catch(function (error) {
        appendMessage(error.message || 'An unexpected error occurred while processing the batch.', 'error');
        toggleButtons(false, false);
      });
  }

  function removeTool() {
    if (!securityToken) {
      appendMessage('Unable to remove tool: Missing security token for this session.', 'error');
      return;
    }

    toggleButtons(true, false);
    appendMessage('Removing migration tool from the admin menu...', 'success');

    fetch(removeUrl, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
      },
      body: buildFormBody()
    })
      .then(function (response) {
        if (!response.ok) {
          throw new Error('Unexpected response from the server.');
        }
        return response.json();
      })
      .then(function (data) {
        if (data && data.message) {
          appendMessage(data.message, data.success ? 'success' : 'error');
        }
        if (data && data.removed) {
          if (issuesContainer) {
            issuesContainer.style.display = 'none';
          }
          toggleButtons(false, true);
        } else {
          toggleButtons(false, false);
        }
      })
      .catch(function (error) {
        appendMessage(error.message || 'An unexpected error occurred while removing the tool.', 'error');
        toggleButtons(false, false);
      });
  }

  if (startButton) {
    startButton.addEventListener('click', processBatch);
  }

  if (removeButton) {
    removeButton.addEventListener('click', removeTool);
  }
})(window, document);
