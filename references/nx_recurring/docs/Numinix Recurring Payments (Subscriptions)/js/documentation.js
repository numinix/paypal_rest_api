jQuery(document).ready(function($) {
  var $sections = $(".bodyHeaderContainer");
  var sectionIds = [];

  $(".contentMenu a").each(function() {
    var $link = $(this);
    var target = $link.data("target");

    if (!target) {
      var href = $link.attr("href");
      if (href && href.indexOf("#") === 0) {
        target = href.substring(1);
      }
    }

    if (target) {
      sectionIds.push(target);
      $link.data("target", target);
    }
  });

  function showSection(sectionId) {
    if (!sectionId || !document.getElementById(sectionId)) {
      return;
    }

    $sections.hide();
    $("#" + sectionId).show();
    $(".contentMenu a").removeClass("active");
    $(".contentMenu a[data-target='" + sectionId + "']").addClass("active");
  }

  $(".contentMenu a").on("click", function(event) {
    var target = $(this).data("target");

    if (target) {
      event.preventDefault();
      showSection(target);

      if (window.history && window.history.replaceState) {
        window.history.replaceState(null, document.title, "#" + target);
      } else {
        window.location.hash = target;
      }

      $(".contentMenu").removeClass("active");
    }
  });

  $("#js-nav-icon").on("click", function(event) {
    event.preventDefault();
    $(".contentMenu").toggleClass("active");
  });

  var initialTarget = window.location.hash ? window.location.hash.substring(1) : "";

  if (jQuery.inArray(initialTarget, sectionIds) === -1) {
    initialTarget = sectionIds.length ? sectionIds[0] : null;
  }

  if (initialTarget) {
    showSection(initialTarget);
  }

  $(window).on("hashchange", function() {
    var target = window.location.hash ? window.location.hash.substring(1) : "";

    if (jQuery.inArray(target, sectionIds) !== -1) {
      showSection(target);
    }
  });

  // CODE BOXES
  // highlight
  sh_highlightDocument();
  // select all functionality
  jQuery(document).on('click', '.select', function() {
    selectCode(jQuery(this).parent('div').next('pre').children('code')[0]);
  });
});

function selectCode(a)
{
  // Get ID of code block
  var e = a.parentNode.parentNode.getElementsByTagName('CODE')[0];

  // Not IE
  if (window.getSelection)
  {
    var s = window.getSelection();
    // Safari
    if (s.setBaseAndExtent)
    {
      s.setBaseAndExtent(e, 0, e, e.innerText.length - 1);
    }
    // Firefox and Opera
    else
    {
      // workaround for bug # 42885
      if (window.opera && e.innerHTML.substring(e.innerHTML.length - 4) == '<BR>')
      {
        e.innerHTML = e.innerHTML + '&nbsp;';
      }

      var r = document.createRange();
      r.selectNodeContents(e);
      s.removeAllRanges();
      s.addRange(r);
    }
  }
  // Some older browsers
  else if (document.getSelection)
  {
    var s = document.getSelection();
    var r = document.createRange();
    r.selectNodeContents(e);
    s.removeAllRanges();
    s.addRange(r);
  }
  // IE
  else if (document.selection)
  {
    var r = document.body.createTextRange();
    r.moveToElementText(e);
    r.select();
  }

}