# Submenu Bottom Fill — Extend Warm-Grey to Header Border

**Date:** 2026-02-17
**Branch:** main
**Status:** Partially complete — may need further refinement

## Problem

When a desktop submenu was open, the warm-grey background (`::before` pseudo-element) didn't extend all the way to the `<header>` element's `border-bottom` (solent-blue line). There was a ~16px gap caused by the JS nav height calculation adding `+ 16` beyond what the submenu's `offsetHeight` measured.

## Why Initial Attempts Failed

- **padding-bottom on `.sub-menu-container`** — increased `offsetHeight`, but JS added `+ 16` on top, so the gap remained.
- **padding-bottom on `ul.sub-menu-item-container`** — same issue: increased content height → increased `offsetHeight` → JS still added 16px gap.

The root cause: the `+ 16` in JS always created a gap between the submenu's measured height and the nav container's height. The `::before` at `height: 100%` only covered the measured height.

## Solution

Moved the 16px spacing from a JS offset into CSS `padding-bottom`, so the spacing is part of the container's `offsetHeight` and covered by the `::before` background:

1. **`menu-desktop.css`** — Added `padding-bottom: 16px` to `.sub-menu-container`
2. **`search.css`** — Added `padding-bottom: 16px` to `#search-form-container`
3. **`customsolent.js`** — Removed `+ 16` from all three nav height calculations:
   - Line ~151: submenu switch animation
   - Line ~629: search form show
   - Line ~873: `desktop_menu_drawer_show()`

## Why This Animates Smoothly

The padding is now inside the measured `offsetHeight`, so the nav height target matches the submenu's visual height exactly. Both animate together over 0.5s — no flash or abrupt appearance.

## TODO / Further Refinement

- Search tag: `submenu-bottom-fill`
- The warm-grey fill is improved but may need fine-tuning for edge cases (e.g., submenus with very few items, search form).
- An earlier CSS-only attempt using `height: calc(100% + 16px)` on the `::before` worked but caused abrupt appearance during transitions.
