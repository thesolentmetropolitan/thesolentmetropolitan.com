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
 *
 * The sheet can also be swiped down to dismiss it (issue 488), as on
 * LinkedIn and the like: a downward drag from the handle or header, or
 * from the list when it is scrolled to the top, moves the sheet with the
 * finger; letting go past the threshold closes it, short of it the sheet
 * springs back. An upward move, or a move that starts in a scrolled
 * list, is left to the list's own scrolling. "Clear" stays what it was:
 * it removes the filter, it does not merely hide the sheet.
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
    panels.forEach(enableSwipeToDismiss);
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') close();
    });

    function enableSwipeToDismiss(panel) {
      var list = panel.querySelector('.slnt-filter-panel__body');
      var startY = null;
      var dy = 0;
      var dragging = false;
      // Past this the sheet closes on release: a quarter of its height,
      // never less than 60px, so a flick on a short list still works.
      function threshold() {
        return Math.max(60, panel.offsetHeight / 4);
      }
      function reset() {
        panel.classList.remove('is-dragging');
        panel.style.transform = '';
        startY = null;
        dy = 0;
        dragging = false;
      }

      panel.addEventListener('touchstart', function (e) {
        if (!panel.classList.contains('is-open') || e.touches.length !== 1) return;
        // From inside the list only when it sits at the top; otherwise a
        // downward move is the list scrolling back up.
        if (list && list.contains(e.target) && list.scrollTop > 0) return;
        startY = e.touches[0].clientY;
        dy = 0;
        dragging = false;
      }, { passive: true });

      panel.addEventListener('touchmove', function (e) {
        if (startY === null) return;
        dy = e.touches[0].clientY - startY;
        if (!dragging) {
          // Upwards: not a dismiss, hand the gesture back to the list.
          if (dy < 0) { startY = null; return; }
          // A few pixels of slack before the sheet starts to follow.
          if (dy < 6) return;
          dragging = true;
          panel.classList.add('is-dragging');
        }
        panel.style.transform = 'translateY(' + Math.max(0, dy) + 'px)';
        // The sheet is moving, so the page and the list must not.
        if (e.cancelable) e.preventDefault();
      }, { passive: false });

      function release() {
        if (startY === null) return;
        var shouldClose = dragging && dy > threshold();
        reset();
        if (shouldClose) close();
      }
      panel.addEventListener('touchend', release);
      panel.addEventListener('touchcancel', release);
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
