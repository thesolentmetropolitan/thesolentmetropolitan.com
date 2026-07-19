# The Solent Metropolitan — Mobile Menu Improvements Brief

## Overview

Improvements to the mobile menu (below 800px viewport width). Covers push-down behaviour, full-width tap targets, dividers, hover states, and navigation-indicated visual states. The existing sub-menu scrollable area with fade indicator is retained.

**Desktop menu is NOT affected by this brief. All changes scoped to mobile only.**

**Drupal 11 compatible.**

---

## Colour reference

| Name | Hex | CSS variable |
|------|-----|-------------|
| Solent Blue | `#2c4f6e` | `--solent-blue` |
| Pink Hover | `#f5b0d8` | `--pink-hover` |
| Magenta | `#c5007a` | `--magenta` |
| Magenta Dark | `#a30065` | `--magenta-dark` |
| Off-white / Warm Grey | `#f5f3f0` | `--warm-grey` |
| White | `#ffffff` | — |
| Text | `#1a1a1a` | `--text` |

---

## 1. Push-down behaviour

### Current behaviour
When the hamburger menu is toggled open, the menu overlays/blanket covers the main page content.

### New behaviour
When the hamburger menu is toggled open, the main page content is **pushed down** below the menu. The menu sits in the document flow above the content. The page is scrollable — the user can scroll past the menu to reach the content.

### DOM structure

Two separate containers contribute to the push-down:

1. **`<nav role="navigation">`** — contains all main menu items (Home, Culture, Sectors, Living, Explore, About, Search) and their sub-menu items
2. **`<div id="search-form-container">`** — sits **after** the `<nav>` in the DOM. Contains the search form revealed when the Search menu item is clicked.

Both containers animate independently. The main page content below both is pushed down by the combined height of whatever is currently open.

### Animation specification

All height transitions use CSS transitions — smooth, no jumps. Duration: 250–300ms, ease-out curve.

**Menu open/close** (hamburger toggle):
- Opening: `<nav role="navigation">` transitions from zero height to its full height (showing the main menu items). Content below pushes down smoothly.
- Closing: reverse — nav transitions to zero height, content slides back up.

**Sub-menu open/close** (tapping a main menu item):
- Opening a sub-menu: the nav's overall height increases as the sub-menu items are revealed within it. Transition on the sub-menu container's height/max-height.
- Closing a sub-menu: the nav's height decreases. Same smooth transition.
- Switching sub-menus (closing one, opening another): if the new sub-menu is a different height, the nav height transitions smoothly between the two.

**Search form reveal/hide** (tapping Search):
- `<div id="search-form-container">` transitions from zero height to visible. This pushes content down by the search form height, independently of the nav.
- The search form and a section sub-menu can both be open simultaneously — they coexist. Opening Search does NOT close any open sub-menu.

**Desktop:** The same animation approach applies to both the nav and the search form container on desktop.

### Sub-menu scrollable region sizing

Sub-menus with **6 or fewer items** display all items — the container height matches the content naturally.

Sub-menus with **more than 6 items** get a fixed max-height equivalent to approximately 6 items (~290px at 48px per row), with the existing scroll and fade indicator. This means:

| Section | Items | Display |
|---------|-------|---------|
| About | 8 | Shows 6, scrolls for 2 |
| Explore | 10 | Shows 6, scrolls for 4 |
| Living | 12 | Shows 6, scrolls for 6 |
| Culture | 24 | Shows 6, scrolls for 18 |
| Sectors | 35 | Shows 6, scrolls for 29 |

The capped height keeps the overall menu height **roughly consistent** regardless of which section is open. The only variation is sections with fewer than 6 items.

When switching between sub-menus of different heights (e.g. closing a capped 6-item container and opening an uncapped 4-item one), the transition animates the height change smoothly.

### Implementation
Remove any `position: fixed`, `position: absolute`, or `z-index` overlay behaviour on the mobile menu. Both `<nav role="navigation">` and `<div id="search-form-container">` should be block-level elements in the normal document flow with CSS transitions on their height or max-height.

**Claude Code:** Inspect the current mobile menu CSS and JS (`customsolent.js` or equivalent) to identify the overlay mechanism and replace it with the push-down approach. The JS toggle behaviour (tap hamburger to open/close) stays the same — only the CSS positioning and animation changes.

---

## 2. Dividers

### Main menu items
Thin horizontal dividers between each main menu item. The divider colour should be a semi-transparent white to give the illusion of a thinner line blending with the solent blue background:

```css
border-bottom: 1px solid rgba(255, 255, 255, 0.2);
```

The last main menu item (Search) should also have a bottom divider to close the menu visually.

### Sub-menu items
Thin horizontal dividers between each sub-menu item. Since the sub-menu background is off-white, use a light grey:

```css
border-bottom: 1px solid #e0e0e0;
```

The last sub-menu item should also have a bottom divider.

---

## 3. Full-width tap targets

### Main menu items
The entire row is clickable, not just the text. The `<a>` or `<button>` element should:

```css
display: block;
width: 100%;
padding: 14px 16px;
min-height: 48px;  /* exceeds WCAG 2.5.5 minimum of 44px */
```

### Sub-menu items
Same principle — the full width of the sub-menu row is clickable:

```css
display: block;
width: 100%;
padding: 12px 16px;
min-height: 44px;
```

**Sub-menu item rectangle width:** Currently the sub-menu item rectangle doesn't fill to the left edge. Fix this by ensuring the `<a>` element has no left margin or padding offset, and extends fully to the left edge of the screen. Claude Code should inspect the current markup and identify what's causing the left-side gap.

---

## 4. Visual states — complete matrix

### Main menu items

| # | State | Text colour | Text weight | Background | Left border | Chevron | Divider |
|---|-------|------------|-------------|------------|-------------|---------|---------|
| 1 | **Default** | White | Normal | Solent blue | None | White | rgba(255,255,255,0.2) bottom |
| 2 | **Hover** (non-active, non-indicated) | Pink `#f5b0d8` | Normal | Solent blue | None | Pink | unchanged |
| 3 | **Active** (submenu open, non-indicated) | Solent blue | Normal | White | None | Solent blue ↓ | unchanged |
| 4 | **Active + hover** (non-indicated) | Magenta dark `#a30065` | Normal | White | None | Magenta dark | unchanged |
| 5 | **Nav-indicated, non-active** | White | **Bold** | Solent blue | White, 4px solid, full height | White | unchanged |
| 6 | **Nav-indicated, non-active + hover** | Pink `#f5b0d8` | **Bold** | Solent blue | Pink `#f5b0d8`, 4px solid | Pink | unchanged |
| 7 | **Nav-indicated, active** (submenu open) | Magenta `#c5007a` | **Bold** | White | Magenta `#c5007a`, 4px solid | Magenta | unchanged |
| 8 | **Nav-indicated, active + hover** | Magenta `#c5007a` | **Bold** | White | Magenta `#c5007a`, 4px solid | unchanged | *(no hover change)* |

### Sub-menu items

| # | State | Text colour | Background | Divider |
|---|-------|------------|------------|---------|
| 1 | **Default** | Solent blue | Off-white `#f5f3f0` | #e0e0e0 bottom |
| 2 | **Hover** (non-indicated) | White | Solent blue | unchanged |
| 3 | **Nav-indicated** | White | Magenta dark `#a30065` | unchanged |
| 4 | **Nav-indicated + hover** | White | Solent blue | unchanged |

---

## 5. Left border indicator — specification

The left border replaces the desktop's bottom line as the navigation indicator for mobile. It runs the full height of the main menu item row.

```css
/* Navigation-indicated left border */
.mobile-menu-item.is-current-section {
  border-left: 4px solid #ffffff;
}

/* Adjust padding to compensate for border width so text doesn't shift */
.mobile-menu-item.is-current-section > a {
  padding-left: 12px;  /* 16px default minus 4px border */
}
```

The border colour changes with state:
- Non-active: white (matches bold white text on solent blue background)
- Non-active + hover: pink (matches pink hover text)
- Active: magenta (matches magenta text on white background)

---

## 6. Navigation indication — JS

The existing `menu-state.js` (or equivalent) that applies `.is-current-section` and `.is-active-page` classes based on URL matching should **already work for mobile** — the classes are applied to the menu items regardless of viewport width, and CSS handles how they display per breakpoint.

The topic-based navigation indication from `menu-state-topic.js` (for content full view pages reading `data-primary-topic-path`) should also apply to mobile.

**Claude Code should verify** that both scripts apply their classes to the mobile menu elements as well as the desktop menu elements. If the mobile menu uses different DOM elements (a separate `<nav>` or `<ul>`), the JS may need to query both. If the mobile and desktop menus share the same DOM elements (just styled differently per breakpoint), no JS changes are needed.

---

## 7. Sub-menu scrollable area

### Existing behaviour (retain)
The sub-menu items display within a scrollable area with a fade indicator at the bottom when items overflow. This is already implemented and working.

### No changes needed to scroll/fade mechanism
The push-down behaviour (section 1) does not affect the scroll and fade — they continue to work within the sub-menu container as before. The max-height and sizing rules are specified in section 1 above.

---

## 8. Chevron states

The chevron (▼/▶) on main menu items should follow the same colour as the text in each state:

| Main menu state | Chevron colour | Chevron direction |
|----------------|---------------|-------------------|
| Default (closed) | White | ▶ (right / pointing down) |
| Hover (closed) | Pink | ▶ |
| Active (open) | Solent blue | ▼ (pointing down, rotated) |
| Active + hover | Magenta dark | ▼ |
| Nav-indicated, closed | White | ▶ |
| Nav-indicated, closed + hover | Pink | ▶ |
| Nav-indicated, open | Magenta | ▼ |
| Nav-indicated, open + hover | Magenta | ▼ (no change) |

Claude Code should check how the chevron is implemented (SVG, CSS triangle, icon font) and apply colour changes via `fill`, `color`, or `border-color` as appropriate.

---

## CSS implementation notes

All mobile menu styles should be within `@media (max-width: 799px)` to avoid affecting the desktop menu.

The CSS class names used in this brief (`.mobile-menu-item`, etc.) are **placeholders**. Claude Code must inspect the actual mobile menu markup and use the correct selectors. The existing `customsolent.js` or menu template will show the actual class structure.

### CSS structure

```css
@media (max-width: 799px) {

  /* ── Push-down behaviour ── */
  /* Replace overlay positioning with in-flow block display */

  /* ── Main menu dividers ── */
  .mobile-menu-item {
    border-bottom: 1px solid rgba(255, 255, 255, 0.2);
  }

  /* ── Sub-menu dividers ── */
  .mobile-submenu-item {
    border-bottom: 1px solid #e0e0e0;
  }

  /* ── Full-width tap targets ── */
  .mobile-menu-item > a,
  .mobile-menu-item > button {
    display: block;
    width: 100%;
    padding: 14px 16px;
    min-height: 48px;
  }

  .mobile-submenu-item > a {
    display: block;
    width: 100%;
    padding: 12px 16px;
    min-height: 44px;
  }

  /* ── Main menu hover states ── */
  .mobile-menu-item:not(.is-active):hover > a {
    color: var(--pink-hover, #f5b0d8);
  }
  .mobile-menu-item:not(.is-active):hover .menu-chevron {
    fill: var(--pink-hover, #f5b0d8);
  }

  .mobile-menu-item.is-active:hover > a {
    color: var(--magenta-dark, #a30065);
  }
  .mobile-menu-item.is-active:hover .menu-chevron {
    fill: var(--magenta-dark, #a30065);
  }

  /* ── Navigation-indicated main menu item — non-active ── */
  .mobile-menu-item.is-current-section:not(.is-active) {
    border-left: 4px solid #ffffff;
  }
  .mobile-menu-item.is-current-section:not(.is-active) > a {
    font-weight: 700;
    color: #ffffff;
    padding-left: 12px; /* compensate for 4px left border */
  }

  /* Nav-indicated, non-active + hover */
  .mobile-menu-item.is-current-section:not(.is-active):hover {
    border-left-color: var(--pink-hover, #f5b0d8);
  }
  .mobile-menu-item.is-current-section:not(.is-active):hover > a {
    color: var(--pink-hover, #f5b0d8);
  }
  .mobile-menu-item.is-current-section:not(.is-active):hover .menu-chevron {
    fill: var(--pink-hover, #f5b0d8);
  }

  /* ── Navigation-indicated main menu item — active (open) ── */
  .mobile-menu-item.is-current-section.is-active {
    border-left: 4px solid var(--magenta, #c5007a);
    background: #ffffff;
  }
  .mobile-menu-item.is-current-section.is-active > a {
    font-weight: 700;
    color: var(--magenta, #c5007a);
    padding-left: 12px;
  }
  .mobile-menu-item.is-current-section.is-active .menu-chevron {
    fill: var(--magenta, #c5007a);
  }

  /* Nav-indicated, active + hover — no change */
  .mobile-menu-item.is-current-section.is-active:hover > a {
    color: var(--magenta, #c5007a); /* stays magenta */
  }

  /* ── Sub-menu hover ── */
  .mobile-submenu-item:not(.is-active-page):hover > a {
    background: var(--solent-blue, #2c4f6e);
    color: #ffffff;
  }

  /* ── Navigation-indicated sub-menu item ── */
  .mobile-submenu-item.is-active-page > a {
    background: var(--magenta-dark, #a30065);
    color: #ffffff;
  }

  /* Nav-indicated sub-menu + hover */
  .mobile-submenu-item.is-active-page:hover > a {
    background: var(--solent-blue, #2c4f6e);
    color: #ffffff;
  }

}
```

---

## Contrib module note

The existing blanket-cover mobile menu behaviour should be **preserved in the codebase** but deactivated. Structure the CSS/JS so the menu display mode can be switched via a data attribute or class on the menu container:

- `data-mobile-menu-mode="overlay"` — current blanket cover behaviour
- `data-mobile-menu-mode="pushdown"` — new push-down behaviour

For this implementation, hardcode `pushdown` as the active mode. The overlay mode CSS remains in the codebase (commented out or scoped to `[data-mobile-menu-mode="overlay"]`) for future use in the contrib module.

This is a **low-priority** structural consideration. If it adds significant complexity, Claude Code can skip the configurable mode and simply replace the overlay with pushdown. The overlay CSS can be preserved in a git branch or a commented block.

---

## Testing

1. **Push-down:** Open the hamburger menu. Main content pushes down below the menu — not covered by it. Content is visible and scrollable below the menu.
2. **Push-down animation:** Menu slides open smoothly. Content shifts down smoothly, not a jump.
3. **Close menu:** Tap hamburger again. Menu closes, content slides back up.
4. **Dividers — main items:** Thin white-ish lines visible between Home, Culture, Sectors, Living, Explore, About, Search.
5. **Dividers — sub items:** Thin grey lines visible between sub-menu items when a section is open.
6. **Full-width tap — main items:** Tapping anywhere on the row (not just the text) opens/closes the submenu. The entire row responds to touch.
7. **Full-width tap — sub items:** Tapping anywhere on the sub-menu row navigates to that page. No dead zones.
8. **Sub-menu left edge:** Sub-menu item rectangles extend to the left edge of the screen — no gap.
9. **Hover — non-active main item:** Hover turns text and chevron pink.
10. **Hover — active main item:** Hover turns text and chevron magenta dark.
11. **Nav-indicated non-active:** On `/culture/music`, "Culture" shows bold white text with white left border (menu closed).
12. **Nav-indicated non-active + hover:** Hovering "Culture" in above state turns text, chevron, and left border pink.
13. **Nav-indicated active:** Open Culture submenu on `/culture/music`. "Culture" shows bold magenta text on white background with magenta left border.
14. **Nav-indicated active + hover:** Hovering in above state — no change (stays magenta).
15. **Nav-indicated sub-menu:** With Culture submenu open on `/culture/music`, "Music" shows white text on magenta dark background.
16. **Nav-indicated sub-menu + hover:** Hovering "Music" in above state — background changes to solent blue, text stays white.
17. **Non-indicated sub-menu hover:** Hovering a non-indicated sub-menu item — text becomes white, background becomes solent blue (inversion).
18. **Scrollable sub-menu:** Open Culture (24 items). Sub-items display in scrollable area with fade indicator. Scrolling works within the container.
19. **Desktop unaffected:** Above 800px, all menu behaviour is unchanged. No mobile styles leak into desktop.
20. **Topic-based navigation:** Visit `/events/jazz-on-the-seafront-2026-05-24-19-30` (primary topic Culture / Music) at mobile width. "Culture" has nav-indicated state in the mobile menu. Open the submenu — "Music" has nav-indicated state.

---

## Files to create or modify

```
M  web/themes/custom/customsolent/css/navigation.css (or wherever mobile menu styles live)
     — push-down behaviour
     — dividers
     — full-width tap targets
     — all visual states (hover, active, nav-indicated combinations)
     — left border indicator
     — sub-menu item left-edge fix

M  web/themes/custom/customsolent/customsolent.js (or equivalent menu JS)
     — replace overlay toggle with push-down toggle
     — preserve overlay code for contrib module (commented or mode-gated)

M  web/themes/custom/customsolent/customsolent.libraries.yml (if new CSS/JS files added)
```

Claude Code should inspect the codebase to identify the exact files containing mobile menu CSS and JS.

---

## Implementation order

| Step | Task | Who |
|------|------|-----|
| 1 | Inspect current mobile menu CSS and JS — identify overlay mechanism | Claude Code |
| 2 | Replace overlay with push-down behaviour (CSS + JS) | Claude Code |
| 3 | Add dividers between main menu items and sub-menu items | Claude Code |
| 4 | Fix full-width tap targets on main and sub-menu items | Claude Code |
| 5 | Fix sub-menu item left-edge gap | Claude Code |
| 6 | Add hover states for all main menu item combinations | Claude Code |
| 7 | Add hover states for sub-menu items | Claude Code |
| 8 | Add navigation-indicated states with left border for main items | Claude Code |
| 9 | Add navigation-indicated states for sub-menu items | Claude Code |
| 10 | Verify JS navigation classes apply to mobile menu elements | Claude Code |
| 11 | Adjust sub-menu max-height if needed after push-down change | Claude Code |
| 12 | Test all states and combinations | Rob |
