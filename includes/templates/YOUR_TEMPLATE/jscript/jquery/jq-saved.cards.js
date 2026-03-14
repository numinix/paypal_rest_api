(function($) {
  'use strict';

  if (typeof $ === 'undefined') {
    return;
  }

  $(function() {
    $('[data-saved-card-toggle]').each(function() {
      var $toggle = $(this);
      var targetId = $toggle.data('target');
      var $target = $('#' + targetId);

      if ($target.length === 0) {
        return;
      }

      $toggle.on('click', function(event) {
        event.preventDefault();

        var isExpanded = $toggle.attr('aria-expanded') === 'true';
        var labelCollapsed = $toggle.data('label-collapsed');
        var labelExpanded = $toggle.data('label-expanded');

        $toggle.attr('aria-expanded', (!isExpanded).toString());
        $toggle.text(isExpanded ? labelCollapsed : labelExpanded);
        $target.toggleClass('is-collapsed', isExpanded);
      });
    });

    var addressToggleSelector = '[data-address-toggle]';
    if ($(addressToggleSelector).length) {
      var refreshAddressSections = function() {
        var selected = $(addressToggleSelector + ':checked');
        if (!selected.length) {
          selected = $(addressToggleSelector + ':not(:disabled)').first();
        }
        var activeValue = selected.data('address-toggle');

        $('[data-address-target]').each(function() {
          var $section = $(this);
          var targetValue = $section.data('address-target');
          if (targetValue === activeValue) {
            $section.removeClass('d-none');
          } else {
            $section.addClass('d-none');
          }
        });
      };

      $(document).on('change', addressToggleSelector, refreshAddressSections);

      refreshAddressSections();
    }

    // Handle add card form address toggles
    var addAddressToggleSelector = '[data-add-address-toggle]';
    if ($(addAddressToggleSelector).length) {
      var refreshAddCardAddressSections = function() {
        var selected = $(addAddressToggleSelector + ':checked');
        if (!selected.length) {
          selected = $(addAddressToggleSelector + ':not(:disabled)').first();
        }
        var activeValue = selected.data('add-address-toggle');

        $('[data-add-address-target]').each(function() {
          var $section = $(this);
          var targetValue = $section.data('add-address-target');
          if (targetValue === activeValue) {
            $section.removeClass('d-none');
          } else {
            $section.addClass('d-none');
          }
        });
      };

      $(document).on('change', addAddressToggleSelector, refreshAddCardAddressSections);

      refreshAddCardAddressSections();
    }
  });
})(window.jQuery);
