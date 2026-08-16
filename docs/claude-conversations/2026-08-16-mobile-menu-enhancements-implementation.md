# Mobile Menu Enhancements — Implementation Log

**Date:** 2026-08-16
**Brief:** `2026-08-15,16-mobile-menu-enhancements-brief.md`
**Branch:** `2026-08-16-mobile-menu-enhancements`
**Implemented by:** Claude Code (Fable 5), tested against the local ddev site with Playwright.

---

## What was done

### §1 Home alignment fix
**Root cause:** two stacked problems in `menu-mobile.css`.
- The legacy rule `a.main_nav_link { padding-left: calc(6px + 1.4em) }` didn't match the
  button items' actual text offset, which is `16px row padding + 1em chevron + 0.2em span gap`
  (the mobile chevron sits *before* the text via `order: -1`).
- On the home page, Home's `<li>` is nav-indicated, and the generic
  `.is-current-section .main_nav_link { padding-left: 12px }` rule (specificity 0,2,0) beat
  `a.main_nav_link` (0,1,1), collapsing Home's padding to 12px — the "noticeably misaligned
  on the home page" symptom.

**Fix:** `a.main_nav_link { padding-left: calc(16px + 1em + 0.2em) }` plus
`.is-current-section a.main_nav_link { padding-left: calc(12px + 1em + 0.2em) }` (0,2,1 —
compensates the 4px indicated border). Verified with glyph-level measurement: all seven
main items' text starts at the same x-position on both the home page and section pages.

### §2 Scroll affordance — dark gradient
- Both fades (top + bottom) replaced with dark `rgba(0,0,0,0.12)` gradients, 48px tall
  (≈ one item), still `position: sticky` inside the scrollable `.sub-menu-container`.
- **Flash fixed:** the pseudo-elements now render whenever `.has-overflow` is present, and
  `showSubmenu()` sets `.has-overflow` *before* the reveal animation starts (comparing
  `scrollHeight` against the 290px cap while still collapsed) instead of a 500ms timeout.
  The gradient is there from the first frame.
- Show/hide of each fade is now an `opacity` toggle with a 150ms transition (previously the
  pseudo-element appeared/disappeared entirely), so reaching the bottom fades the gradient
  out smoothly. Existing scroll-listener classes (`scrolled-down`, `scrolled-to-bottom`)
  unchanged; they're now also cleaned off on submenu close.
- Short submenus that fit within the cap never get `.has-overflow`, so no gradient.

### §3 Search auto-scroll + sizing
- `showSearchForm()` mobile branch: after the 500ms height transition completes,
  `scrollIntoView({ behavior: 'smooth', block: 'nearest' })` on `#search-form-container` —
  no-op when already visible, scrolls minimally when below the fold (verified: 101px scroll
  on a 480px-tall viewport).
- Input sized to match the Search menu row: 48px tall, 16px side insets, input flexes to
  fill with the magnifier submit button keeping its space on the right.

### §4 Close button label
- Burger markup (injected by `setupMobileBurgerMenu()`): `.icon-close` is now two stacked
  `.icon-close-line` spans — ✕ / CLOSE / MENU, centred.
- Revised after Rob's review (2026-08-16): the label keeps the original 16px font size and
  the button keeps ONE position for both states — `top: 17px`, high enough that the icon
  plus two text lines centre within the 96px logo band. The icon and the first text line
  are at identical coordinates in both states, so toggling moves nothing; the open state
  just adds its second line below.
- Label convention: visible text is "MENU" closed / "CLOSE MENU" open — toggle labels
  conventionally name what they reveal, not the action; "Open menu" lives in the
  `aria-label` for screen readers.
- ARIA: `role="button"`, `aria-expanded` true/false, `aria-label` "Open menu"/"Close menu".

### §5 Keyboard accessibility
**Root cause of "tab doesn't work":** `button.main_nav_link:focus, a.main_nav_link:focus
{ outline: none !important }` in `menu-common.css` — focus moved but was invisible. Removed.
- New `css/menu-focus.css` (loaded last in the library): orange `#e8870e` background +
  `#1a1a1a` text on `:focus-visible` **only** — never `:focus` — for main items, sub items,
  chevrons/icons, the toggle (orange ring), the no-JS fallback, and the search controls.
  `!important` is deliberate there: the focus marker must beat every state-rule combination
  (hover/active/indicated go up to 0,4,2 specificity).
- Tab-order fixes: collapsed containers now also get `visibility: hidden` (removes links
  from the tab order) — the closed `.main-menu-wrap` inner `<ul>` (delayed 0.28s so the
  collapse animation isn't cut), closed mobile `.sub-menu-container.hidden-2l`, and the
  closed mobile `#search-form-container.hidden-2l`. Desktop already did this via inline JS
  styles.
- Focus management in `customsolent.js`: burger handler refactored into
  `openMobileMenu()` / `closeMobileMenu(returnFocus)`. Open moves focus to Home after the
  0.28s reveal; Escape closes the mobile menu and returns focus to the toggle; on desktop
  Escape closes the open submenu or search panel and returns focus to its button. Space
  activates the toggle (Enter was already native on the `<a role="button">`).
- Verified end-to-end with real key presses: toggle → Enter → Home (orange) → Tab →
  Culture → Enter → Tab → View All Culture (orange) → Escape → focus back on toggle,
  menu closed, and Tab then skips all menu items.

### §6 Progressive enhancement — no-JS
- `html.html.twig`: `<script>document.documentElement.classList.add('js');</script>` first
  in `<head>` (the Olivero pattern — this theme never had a js class).
- **Desktop no-JS:** `menu-desktop.css` pins `.hidden-2l` submenus hidden, then
  `:hover` / `:focus-within` on `.main-menu-item-wrapper` reveals them at `top: 48px` with
  the warm-grey full-width backdrop — classic dropdown overlaying the page. `:focus-within`
  keeps them open while Tab moves through their links.
- **Mobile no-JS:** new `menu_links_noscript` macro in `menu--main.html.twig` renders a
  `<details>/<summary>` fallback from the same menu tree (sections' children include the
  "View All" item). Styled minimally on-brand in `menu-mobile.css`. The enhanced menu stays
  collapsed (its hamburger is JS-injected so it simply never exists).
- **Search no-JS:** the toggle button and form panel are useless without JS, so both are
  hidden and a plain `/search/node` link (rendered next to the button in the template)
  shows instead — works at both widths.
- Switching rules centralised in `menu-common.css` (`html.js` / `html:not(.js)`).
- Verified in a real `javaScriptEnabled: false` browser context: fallback menu + native
  expand/collapse on mobile, hover + focus-within dropdowns on desktop, no hamburger,
  fallback invisible when JS is on.

### §7 Contrib module outline
Considerations only per the brief — no implementation. Note for later: the legacy overlay
mode, `data-mobile-menu-mode` hook and the new `js`-class switching are all
contrib-friendly seams.

---

## Files changed

- `css/menu-mobile.css` — Home alignment; dark gradients; tab-order visibility; close
  button label + reposition; no-JS fallback styling
- `css/menu-desktop.css` — no-JS hover/focus-within dropdowns
- `css/menu-common.css` — removed focus-killing outline rule; JS/no-JS switching rules
- `css/menu-focus.css` — **new**, orange `:focus-visible` system (all widths)
- `css/search.css` — mobile input sizing; closed-form tab-order fix
- `js/customsolent.js` — burger refactor + ARIA; focus management; Escape handling;
  up-front gradient classes; search auto-scroll
- `templates/navigation/menu--main.html.twig` — no-JS fallback macro; no-JS search link
- `templates/layout/html.html.twig` — `js` class script
- `customsolent.libraries.yml` — menu-focus.css registered

## Testing done (Playwright against ddev)

All 21 brief test scenarios covered except real-device checks (18: Rob to verify on
iPhone SE3, and visual taste checks on the close-button position). Notable measurements:
- Main item text glyphs all at identical x (home page and section pages).
- Gradient present at first frame of submenu open (`opacity: 1` on `::after` immediately).
- Bottom gradient `opacity: 0` at scroll bottom; top gradient appears once scrolled.
- Search: input 48px/full-width at 16px insets; page auto-scrolled 101px on a short
  viewport to reveal the form.
- Desktop regression: submenu open/close, switching animation, nav indication, search
  panel all behave as before.

## Known nits / follow-ups

- The `<summary>` rows in the no-JS fallback don't show a disclosure marker
  (`display: block` suppresses it) — matches the brief's CSS, but a chevron could be added.
- `menu--main.html.twig`'s enhanced-menu macro emits unbalanced open/close tags (browsers
  repair it — the fallback lands as a sibling of the enhanced menu, which is fine, but
  worth straightening out someday, especially before any contrib extraction).
- ~~Close-button open-state position~~ resolved: single `top: 17px` for both states after
  Rob's review.
