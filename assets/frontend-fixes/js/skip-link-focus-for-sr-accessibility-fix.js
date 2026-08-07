document.addEventListener('DOMContentLoaded', function () {
(function () {
  function getHashFromElement(el) {
    var link = el.closest && el.closest('a[href^="#"]');
    if (link) return link.getAttribute('href');

    var clickable = el.closest && el.closest('.et_clickable');
    if (clickable && window.et_link_options_data) {
      for (var i = 0; i < window.et_link_options_data.length; i++) {
        var item = window.et_link_options_data[i];
        if (
          clickable.classList.contains(item.class) &&
          item.url &&
          item.url.charAt(0) === '#'
        ) {
          return item.url;
        }
      }
    }

    return null;
  }

  function focusHash(hash) {
    if (!hash || hash === '#') return;

    var id = decodeURIComponent(hash.substring(1));
    var section = document.getElementById(id);
    if (!section) return;

    var focusTarget =
      section.querySelector('h1, h2, h3, h4, h5, h6, a, button') || section;

    if (!focusTarget.hasAttribute('tabindex')) {
      focusTarget.setAttribute('tabindex', '-1');
    }

    section.scrollIntoView({ block: 'start' });

    [50, 150, 300, 600].forEach(function (delay) {
      setTimeout(function () {
        focusTarget.focus({ preventScroll: true });
      }, delay);
    });
  }

  function activate(e) {
    var hash = getHashFromElement(e.target);
    if (!hash) return;

    e.preventDefault();
    e.stopPropagation();
    if (e.stopImmediatePropagation) e.stopImmediatePropagation();

    if (history.pushState) {
      history.pushState(null, '', hash);
    } else {
      window.location.hash = hash;
    }

    focusHash(hash);
  }

  document.addEventListener('click', activate, true);

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' || e.key === ' ') {
      activate(e);
    }
  }, true);

  window.addEventListener('hashchange', function () {
    focusHash(window.location.hash);
  });
})();
});