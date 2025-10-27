/* global sh_highlightDocument */
document.addEventListener("DOMContentLoaded", function() {
  var sections = Array.prototype.slice.call(document.querySelectorAll(".bodyHeaderContainer"));
  var links = Array.prototype.slice.call(document.querySelectorAll(".contentMenu a"));
  var sectionIds = [];
  var contentMenu = document.querySelector(".contentMenu");
  var tabList = contentMenu ? contentMenu.querySelector("ul") : null;

  if (!sections.length || !links.length) {
    return;
  }

  document.body.classList.add("has-doc-tabs");

  if (tabList) {
    tabList.setAttribute("role", "tablist");
  }

  sections.forEach(function(section) {
    section.setAttribute("role", "tabpanel");
    section.setAttribute("aria-hidden", "true");
    section.style.display = "none";
  });

  links.forEach(function(link) {
    var target = link.getAttribute("data-target");

    if (!target) {
      var href = link.getAttribute("href");
      if (href && href.indexOf("#") === 0) {
        target = href.substring(1);
      }
    }

    if (target) {
      if (sectionIds.indexOf(target) === -1) {
        sectionIds.push(target);
      }
      link.setAttribute("data-target", target);
      link.setAttribute("role", "tab");
      link.setAttribute("aria-controls", target);
      link.setAttribute("aria-selected", "false");
      link.setAttribute("tabindex", "-1");
    }
  });

  function showSection(sectionId, options) {
    var opts = Object.assign({ updateHash: true, scrollIntoView: false }, options || {});

    if (sectionIds.indexOf(sectionId) === -1) {
      return;
    }

    var activeSection = null;

    sections.forEach(function(section) {
      var isActive = section.id === sectionId;
      section.classList.toggle("is-active", isActive);
      section.style.display = isActive ? "" : "none";
      section.setAttribute("aria-hidden", isActive ? "false" : "true");
      if (isActive) {
        activeSection = section;
      }
    });

    links.forEach(function(link) {
      var matches = link.getAttribute("data-target") === sectionId;
      link.classList.toggle("active", matches);
      link.setAttribute("aria-selected", matches ? "true" : "false");
      link.setAttribute("tabindex", matches ? "0" : "-1");
    });

    if (opts.updateHash) {
      if (window.history && typeof window.history.replaceState === "function") {
        window.history.replaceState(null, document.title, "#" + sectionId);
      } else {
        window.location.hash = sectionId;
      }
    }

    if (activeSection && opts.scrollIntoView && typeof activeSection.scrollIntoView === "function") {
      activeSection.scrollIntoView({ behavior: "smooth", block: "start" });
    }
  }

  links.forEach(function(link) {
    link.addEventListener("click", function(event) {
      var target = link.getAttribute("data-target");

      if (!target) {
        return;
      }

      event.preventDefault();
      showSection(target, { scrollIntoView: true });

      if (contentMenu) {
        contentMenu.classList.remove("active");
      }
    });
  });

  var navIcon = document.getElementById("js-nav-icon");

  if (navIcon) {
    navIcon.addEventListener("click", function(event) {
      event.preventDefault();
      if (contentMenu) {
        contentMenu.classList.toggle("active");
      }
    });
  }

  var initialTarget = window.location.hash ? window.location.hash.substring(1) : "";

  if (sectionIds.indexOf(initialTarget) === -1) {
    initialTarget = sectionIds.length ? sectionIds[0] : null;
  }

  if (initialTarget) {
    showSection(initialTarget, { updateHash: false });
  }

  window.addEventListener("hashchange", function() {
    var target = window.location.hash ? window.location.hash.substring(1) : "";

    if (sectionIds.indexOf(target) !== -1) {
      showSection(target, { updateHash: false, scrollIntoView: true });
    }
  });

  if (typeof sh_highlightDocument === "function") {
    sh_highlightDocument();
  }
});

document.addEventListener("click", function(event) {
  var trigger = event.target.closest(".select");

  if (!trigger) {
    return;
  }

  var container = trigger.parentElement;
  var sibling = container ? container.nextElementSibling : null;

  while (sibling && sibling.tagName !== "PRE") {
    sibling = sibling.nextElementSibling;
  }

  if (!sibling) {
    return;
  }

  var codeElement = sibling.querySelector("code");

  if (codeElement) {
    selectCode(codeElement);
  }
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
