(function (window, document) {
  'use strict';

  var container = document.getElementById('active-subscriptions-report');
  if (!container) {
    return;
  }

  var filterForm = container.querySelector('form.active-subscriptions-filter');
  if (!filterForm) {
    return;
  }

  var typeFilter = filterForm.querySelector('#type-filter');
  if (typeFilter) {
    typeFilter.addEventListener('change', function () {
      filterForm.submit();
    });
  }

  var searchField = filterForm.querySelector('#search-filter');
  if (searchField) {
    searchField.addEventListener('keydown', function (event) {
      if (event.key === 'Enter' && !event.shiftKey) {
        filterForm.submit();
      }
    });
  }
})(window, document);
