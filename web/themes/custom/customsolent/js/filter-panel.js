/**
 * @file
 * Mobile filter panel toggle (Section Landing Page filters).
 *
 * Buttons marked [data-filter-toggle] open the matching panel
 * marked [data-filter-panel]. The overlay [data-filter-overlay]
 * and the Esc key both close it. Background scroll is locked
 * while the panel is open.
 *
 * If multiple panels exist on a page (e.g. duplicated by stacked
 * primary-topic listings), all are toggled together — but the CSS
 * hides all but the first via the `~` combinator, so only one is
 * visible.
 */
(function () {
  'use strict';

  function init() {
    var buttons = document.querySelectorAll('[data-filter-toggle]');
    var overlays = document.querySelectorAll('[data-filter-overlay]');
    var panels = document.querySelectorAll('[data-filter-panel]');

    if (!buttons.length || !panels.length) {
      return;
    }

    function open(e) {
      if (e) e.preventDefault();
      panels.forEach(function (p) { p.classList.add('is-open'); });
      overlays.forEach(function (o) { o.classList.add('is-open'); });
      document.body.classList.add('slnt-filter-panel-open');
      buttons.forEach(function (b) { b.setAttribute('aria-expanded', 'true'); });
    }

    function close() {
      panels.forEach(function (p) { p.classList.remove('is-open'); });
      overlays.forEach(function (o) { o.classList.remove('is-open'); });
      document.body.classList.remove('slnt-filter-panel-open');
      buttons.forEach(function (b) { b.setAttribute('aria-expanded', 'false'); });
    }

    buttons.forEach(function (b) { b.addEventListener('click', open); });
    overlays.forEach(function (o) { o.addEventListener('click', close); });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') close();
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
