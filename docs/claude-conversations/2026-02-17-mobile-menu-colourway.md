# Mobile Menu Colourway - Apply Desktop Colour Scheme

**Date:** 2026-02-17
**Commit:** a768687
**Branch:** main
**Related issues:** #70, #62, #61, #56, #51

## Summary

Applied the desktop colourway to the mobile menu so both breakpoints share a consistent visual identity. Previously the mobile menu used a white background with dark text; now it uses the solent blue header with white text, matching desktop.

## Changes Made

All changes in `web/themes/custom/customsolent/css/menu-mobile.css`.

### Header & main menu backgrounds

- `#slnt-header` background: `white` → `var(--solent-blue)`
- `.slnt-menu-main-ul` background: `white` → `var(--solent-blue)`
- `.slnt-overlay-menu-bg` (full-screen overlay): `white` → `var(--solent-blue)`

### Main menu item text

- Non-hover: `rgba(255, 255, 255, 0.85)` with `transition: color 0.15s`
- Hover: `#fff`
- Split the previous combined colour rule so submenu items keep their dark blue (`rgb(16, 60, 96)`) while main menu items get white text

### Chevrons & search icon

- Non-hover fill/stroke: `rgba(255, 255, 255, 0.85)`
- Hover: `#fff`

### Submenu

- Added `background-color: var(--warm-grey)` to `.sub-menu-container.visible` and `.sub-menu-container.visible-2l`
- Submenu text colour unchanged (dark blue `rgb(16, 60, 96)`)
- Scroll fade gradients updated from white `rgba(255,255,255,...)` to warm-grey `rgba(245,243,240,...)` to match the new submenu background

### Burger menu icon & text

- All icon bars (three lines / X): `#000` → `rgba(255, 255, 255, 0.85)`
- Menu text colour: `#000` → `rgba(255, 255, 255, 0.85)`
- Hover states: `#333` → `#fff`
- Open (X) state: same off-white/white pattern
- Comment updated: "adjusted for white background" → "adjusted for solent blue background"

## Colour Reference

| Element | Value | Source |
|---|---|---|
| Solent blue | `#2c4f6e` / `var(--solent-blue)` | Header, overlay, main menu bg |
| Warm grey | `#f5f3f0` / `var(--warm-grey)` | Submenu background |
| Off-white text | `rgba(255, 255, 255, 0.85)` | Non-hover nav text, icons, burger |
| White text | `#fff` | Hover states |
| Dark blue text | `rgb(16, 60, 96)` | Submenu item text (unchanged) |
