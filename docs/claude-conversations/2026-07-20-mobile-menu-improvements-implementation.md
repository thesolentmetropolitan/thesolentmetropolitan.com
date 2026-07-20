# Mobile Menu Improvements — Implementation Log

**Date:** 2026-07-20
**Branch:** `2026-07-20-mobil-menu-navigation-state` (off `main`)
**Brief:** [`2026-07-20-mobile-menu-improvements-brief.md`](./2026-07-20-mobile-menu-improvements-brief.md)
**Files changed:** `web/themes/custom/customsolent/js/customsolent.js`, `web/themes/custom/customsolent/css/menu-mobile.css`
**Not changed (verified sufficient):** `js/menu-state.js`, `templates/navigation/menu--main.html.twig`

Scope: mobile menu only (`@media (max-width: 799px)`). Desktop untouched.

---

## What the brief asked for

Replace the mobile menu's blanket-cover **overlay** with **push-down** behaviour (menu sits in
normal flow and pushes page content down, page stays scrollable), plus dividers, full-width tap
targets, a sub-menu left-edge fix, the full visual-state matrix (default / hover / active /
nav-indicated combinations for main and sub items), a 4px left-border indicator, chevron colours,
and a fixed ~6-row sub-menu cap. Preserve the overlay code but deactivate it.

---

## Key facts discovered about the existing code

- **Breakpoint:** `menu-mobile.css` is one big `@media (max-width: 799px)` block; desktop is
  `min-width: 800px`. The brief's 799px was correct (the 1199/1200 figures elsewhere are an
  *internal* desktop mega-menu tweak, not the mobile boundary).
- **DOM (shared between mobile & desktop, styled per breakpoint):**
  ```
  nav[role=navigation] > .main-menu-wrap > ul.main-menu-item-container
    > li                                   ← "main menu row"  (gets .is-current-section)
       > span.sub-menu-item
          > div.main-menu-item-wrapper
             > a.main_nav_link (Home)  |  button.main_nav_link (section parents) → span + chevron svg
             > div.sub-menu-container.hidden-2l/.visible-2l   ← submenu (open = .visible-2l)
                > .sub-menu-items-outer-container
                   > span.sub-menu-item.first-link > a   ("View All …")
                   > ul.sub-menu-item-container > li > span.sub-menu-item > a   ← "sub-menu item"
  ```
  Note the top-level `<li>` *also* wraps its content in `span.sub-menu-item`, but sub-item anchors
  are `span.sub-menu-item > a` (direct child) whereas main links are nested a level deeper via
  `.main-menu-item-wrapper` — so `.sub-menu-item > a` cleanly targets only sub-items.
- **Overlay mechanism:** opening added `body{overflow:hidden}` + `.slnt-overlay-menu-bg` and forced
  `#slnt-header{height:100vh}`. Because `<header>` already precedes `<main>` in normal flow,
  push-down is mostly *removing* these hacks.
- **"Active" (submenu-open) marker already exists:** `customsolent.js` toggles
  `.navigation__link--selected` on the `.main_nav_link` button (menu-common.css rotates the chevron
  off it). Used as the CSS hook for the active state — no new class needed.
- **Nav-indication:** `menu-state.js` queries the shared `.main-menu-item-container > li` regardless
  of viewport, adding `.is-current-section` (top-level li) and `.is-active-page`
  (`span.sub-menu-item`). On content full-view pages it reads `article[data-primary-topic-path]` and
  indicates the content's primary topic instead of the URL. **All viewport-independent → no JS
  change required for mobile.**
- **Sub-menu scroll/overflow/max-height are JS-driven** (`customsolent.js` sets `overflow-y:auto`
  and inline `max-height` after the open animation), not CSS.

---

## Changes made

### `customsolent.js`

- **Open/close** (`setupMobileBurgerMenu`): replaced the overlay show/hide (body overflow, header
  100vh, opacity fade, display toggling) with a single `slnt-mobile-menu-open` class toggled on the
  `<nav>`. Open/closed is now detected by that class, not by `display`. CSS owns the animation.
- **`initializeMenu` + resize handler:** removed the `display:none` hide (CSS now collapses the menu
  by default) and clear the open-class on mode switches; kept the legacy overlay-class cleanup as
  inert safety.
- **`calculateMobileSubmenuHeight()`:** replaced the viewport-fit calculation (an overlay-era
  assumption) with a fixed `return 290` (~six 48px rows). Under push-down the page scrolls, so
  sub-menus no longer need to shrink to the viewport; ≤6-item sections show fully, larger ones cap
  and scroll with the existing fade.

### `menu-mobile.css`

- **Push-down animation:** `.main-menu-wrap` uses the grid `0fr ↔ 1fr` auto-height technique
  (`grid-template-rows` transition), with its single `ul` child set `min-height:0; overflow:hidden`.
  The 1fr track tracks content height, so the nav also animates smoothly as a submenu expands within
  it — no JS height-measuring. Toggled by `nav.slnt-mobile-menu-open`.
- **Legacy overlay rules** kept but marked deactivated (a future contrib module could re-enable via
  `data-mobile-menu-mode="overlay"`).
- **Dividers:** `rgba(255,255,255,.2)` between main items; `#e0e0e0` between sub items (incl. the
  "View All" first-link).
- **Full-width tap targets:** 48px main / 44px sub, `width:100%`, `padding:14px 16px` / `12px 16px`.
- **Left-edge fix:** zeroed the `.sub-menu-items-outer-container` `2.8em` indent and the first-link
  `2em` inline-start, **and** reset the browser-default `<ul>` `padding-inline-start` on the main
  menu (the theme only reset it on the sub menu — see bug 2 below).
- **Visual-state matrix** mapped to the real selectors:
  `.main_nav_link.navigation__link--selected` = active; `.is-current-section` = nav-indicated (4px
  left border, state-coloured); `.is-active-page` = indicated sub-page. Hover rules gated behind
  `@media (hover: hover)` so touch devices don't get stuck-hover.
- **Chevrons** follow the link's `color` via `currentColor` (the symbol path is
  `fill="currentColor"`), so every state just sets `color` and the chevron matches.

---

## Two bugs caught during live testing (both CSS specificity)

1. **Dark text / non-full-width buttons.** My initial class-only selectors (`.main_nav_link`, 0,1,0)
   lost to `menu-common.css`'s element-qualified rules (`button.main_nav_link > span` 0,1,2 for the
   dark-blue text, `button.main_nav_link` 0,1,1 for `width:auto; padding:0 12px`). Fix:
   element-qualify my rules (`a.main_nav_link, button.main_nav_link …`) and drive the chevron via
   `currentColor`.
2. **Every row indented 40px from the left edge.** This was the **browser-default `<ul>`
   `padding-inline-start: 40px`** — the theme resets it only on `ul.sub-menu-item-container`, never
   on the main `ul`. Fix: reset it for `ul.main-menu-item-container` on mobile.

---

## Verification (Playwright @ 390px + 1280px)

Push-down open/close, dividers (main+sub), full-width tap targets, sub-menu left-edge, default main
(white text+chevron), active (white row; magenta-dark on hover), nav-indicated non-active (bold
white + white 4px border, on `/culture/art-design`), nav-indicated active (magenta + magenta
border), nav-indicated sub-item (`Art & Design` / `Identity` white-on-magenta-dark), scrollable
24-item Culture submenu (capped ~290px with fade), desktop unaffected (horizontal menu + underline
indicator intact), and topic-based indication on `/articles/time-solent`
(`data-primary-topic-path=/culture/identity` → Culture + Identity lit). Hover-only states verified
via computed styles.

---

## Notes / follow-ups

- The per-state chevron `fill`/`stroke` declarations are now redundant (currentColor handles it) but
  harmless and consistent; left in place to avoid churn.
- Nothing committed — left on the feature branch for review and merge to `main`.
- Deploy: production runs its own deploy on the prod server; a theme asset change like this ships
  via the normal deploy path (no one-off data migration needed).
