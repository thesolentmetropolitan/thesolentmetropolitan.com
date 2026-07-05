/**
 * @file
 * Desktop menu navigation-state indicator.
 *
 * Reads the current URL on page load and marks which section / sub-section the
 * user is currently in, by adding classes that the desktop menu CSS styles:
 *
 *   .is-current-section  — on the top-level <li> whose section URL is a prefix
 *                          of the current path (bold white text + magenta-dark
 *                          bottom line).
 *   .is-active-page      — on the single most-specific matching sub-menu item
 *                          (span.sub-menu-item, including the "View All"
 *                          first-link) within the current section (magenta-dark
 *                          background, off-white text).
 *
 * This is purely additive: it does NOT touch the click-to-open submenu logic in
 * customsolent.js. The open/active visual state is driven separately by the
 * `.sub-menu-container.visible-2l` class that customsolent.js manages.
 *
 * Real menu markup (see templates/navigation/menu--main.html.twig):
 *   ul.main-menu-item-container
 *     > li                                         (top-level main menu item)
 *         span.sub-menu-item
 *           div.main-menu-item-wrapper
 *             a.main_nav_link                       (direct-link items, e.g. Home)
 *             button.main_nav_link                  (mega-menu parents; no href)
 *             div.sub-menu-container
 *               span.sub-menu-item.first-link > a   ("View All <Section>")
 *               ul.sub-menu-item-container
 *                 > li > span.sub-menu-item > a     (sub-menu items)
 *
 * Section URL for a mega-menu parent (which has no href of its own) is taken
 * from its "View All" first-link. Search has no navigable href and is skipped.
 */
(function () {
  'use strict';

  /**
   * Strip a trailing slash (except for the root "/") so equal paths compare
   * equal regardless of how they were authored.
   */
  function normalizePath(path) {
    if (path && path.length > 1 && path.charAt(path.length - 1) === '/') {
      return path.slice(0, -1);
    }
    return path;
  }

  /**
   * True if `path` is `base` itself or a descendant of it. Home ("/") only
   * matches exactly.
   */
  function pathMatches(path, base) {
    if (base === '/') {
      return path === '/';
    }
    return path === base || path.indexOf(base + '/') === 0;
  }

  function init() {
    var currentPath = normalizePath(window.location.pathname);

    var mainItems = document.querySelectorAll('.main-menu-item-container > li');

    Array.prototype.forEach.call(mainItems, function (li) {
      // Search has no section URL — leave it untouched.
      if (li.querySelector('#search-in-menu')) {
        return;
      }

      // Section URL: a direct link (Home etc.) if present, otherwise the
      // "View All" first-link of a mega-menu parent. Using the anchor's
      // .pathname (rather than the raw href) normalises relative/absolute and
      // any base path, matching window.location.pathname.
      var directLink = li.querySelector('.main-menu-item-wrapper > a.main_nav_link');
      var firstLink = li.querySelector('.sub-menu-container .first-link a');
      var sectionAnchor = directLink || firstLink;
      if (!sectionAnchor) {
        return;
      }

      var sectionPath = normalizePath(sectionAnchor.pathname);
      if (!pathMatches(currentPath, sectionPath)) {
        return;
      }

      li.classList.add('is-current-section');

      // Within the current section, pick the single most-specific sub-menu item
      // (longest matching URL). This keeps "View All" (the section root) from
      // lighting up on every child page — only the deepest match is indicated.
      var subAnchors = li.querySelectorAll(
        '.first-link a, .sub-menu-item-container a'
      );
      var best = null;
      var bestLength = -1;

      Array.prototype.forEach.call(subAnchors, function (anchor) {
        var subPath = normalizePath(anchor.pathname);
        if (pathMatches(currentPath, subPath) && subPath.length > bestLength) {
          bestLength = subPath.length;
          best = anchor;
        }
      });

      if (best) {
        var subItem = best.closest('.sub-menu-item');
        if (subItem) {
          subItem.classList.add('is-active-page');
        }
      }
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
