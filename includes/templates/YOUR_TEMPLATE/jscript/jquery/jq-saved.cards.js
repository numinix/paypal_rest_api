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
  });
})(window.jQuery);
