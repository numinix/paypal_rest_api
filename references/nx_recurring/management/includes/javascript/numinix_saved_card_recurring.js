(function ($, window) {
  'use strict';

  var config = window.nmxSavedCardRecurringConfig || {};
  var savedCardUrl = config.savedCardUrl || '';
  var querySuffix = config.queryString || '';
  if (querySuffix && querySuffix.charAt(0) !== '&') {
    querySuffix = '&' + querySuffix;
  }

  function ajaxUpdate(url) {
    if (!url || !savedCardUrl) {
      return;
    }

    $.get(url).done(function (data) {
      var $response = $(data);
      var $tableHtml = $response.find('#subscription_table').html();
      if (typeof $tableHtml !== 'undefined') {
        $('#subscription_table').html($tableHtml);
        bindEditLinks();
      }
    });
  }

  function bindEditLinks() {
    $('.edit_content').hide();

    $('.show_edit').off('click').on('click', function () {
      $(this).parent().find('.edit_content').show();
      $(this).hide();
    });

    $('.cancel_edit').off('click').on('click', function () {
      $(this).closest('.edit_content').hide();
      $(this).closest('td').find('.show_edit').show();
    });

    $('.save_date').off('click').on('click', function () {
      if (!savedCardUrl) {
        return;
      }
      var newDate = $(this).parent().find('.set_date').val();
      var url = savedCardUrl + '&action=update_payment_date&saved_card_recurring_id=' + $(this).attr('data-saved_card_recurring_id') + '&set_date=' + encodeURIComponent(newDate) + querySuffix;
      ajaxUpdate(url);
    });

    $('.save_card').off('click').on('click', function () {
      if (!savedCardUrl) {
        return;
      }
      var newCard = $(this).parent().find('select[name="set_card"]').find(':selected').val();
      var url = savedCardUrl + '&action=update_credit_card&saved_card_recurring_id=' + $(this).attr('data-saved_card_recurring_id') + '&set_card=' + encodeURIComponent(newCard) + querySuffix;
      ajaxUpdate(url);
    });

    $('.save_product').off('click').on('click', function () {
      if (!savedCardUrl) {
        return;
      }
      var newProduct = $(this).parent().find('select[name="set_products_id"]').find(':selected').val();
      var originalOrdersProductsId = $(this).parent().find('input[name="hidden_original_orders_products_id"]').val();
      var url = savedCardUrl + '&action=update_product_id&saved_card_recurring_id=' + $(this).attr('data-saved_card_recurring_id') + '&set_products_id=' + encodeURIComponent(newProduct) + '&original_orders_products_id=' + encodeURIComponent(originalOrdersProductsId) + querySuffix;
      ajaxUpdate(url);
    });

    $('.save_amount').off('click').on('click', function () {
      if (!savedCardUrl) {
        return;
      }
      var newAmount = $(this).parent().find('.set_amount').val();
      var url = savedCardUrl + '&action=update_amount_subscription&saved_card_recurring_id=' + $(this).attr('data-saved_card_recurring_id') + '&set_amount=' + encodeURIComponent(newAmount) + querySuffix;
      ajaxUpdate(url);
    });
  }

  $(document).ready(function () {
    bindEditLinks();
  });
})(window.jQuery, window);
