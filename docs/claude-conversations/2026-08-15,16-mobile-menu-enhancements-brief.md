# The Solent Metropolitan — Mobile Menu Enhancements Brief

## Overview

A set of refinements to the mobile menu following the push-down and visual states implementation. Covers alignment fixes, scroll affordance improvement, search UX, close button labelling, keyboard accessibility, progressive enhancement (no-JS fallback), and an outline for a future contrib module.

**Desktop menu is NOT affected by this brief unless explicitly stated. All changes scoped to mobile only.**

**Drupal 11 compatible.**

---

## 1. Home alignment fix

### Problem
The "Home" main menu item is not horizontally aligned with the other menu items. On the home page it is noticeably misaligned. On other pages it is slightly out of line.

### Fix
Claude Code should inspect the mobile menu markup for the Home item and determine what causes the horizontal offset. Common causes:

- Different padding or margin on the first menu item
- The navigation-indicated left border (4px) shifting the text without compensating padding on non-indicated items
- A different DOM structure for the Home item vs other items (e.g. missing a wrapper element)

The fix should ensure all main menu items share identical horizontal padding so text aligns on the same left edge. When the navigation-indicated left border is present (4px), the padding-left should reduce by 4px to compensate — and non-indicated items should have the standard padding-left so everything lines up.

---

## 2. Scroll affordance — dark gradient fade

### Current behaviour
When a sub-menu is opened, the last partially visible item momentarily displays fully and then a white fade/shading is applied. This creates a visual glitch on open.

### Changes needed

**A) Fix the momentary flash on sub-menu open.** The fade should be present from the first frame of the sub-menu being visible, not applied after a delay. The gradient overlay should be rendered as part of the sub-menu container's CSS (a `::after` pseudo-element), not added by JS after a timeout. This eliminates the flash.

**B) Replace white gradient with dark gradient.** The white fade is not effective enough as a scroll affordance indicator. Replace with a gradient from transparent to a dark tone:

```css
.submenu-container::after {
  content: '';
  position: absolute;
  bottom: 0;
  left: 0;
  right: 0;
  height: 48px;  /* approximately one item height */
  background: linear-gradient(
    to bottom,
    rgba(0, 0, 0, 0) 0%,
    rgba(0, 0, 0, 0.12) 100%
  );
  pointer-events: none;  /* allow clicks/taps through the gradient */
  z-index: 1;
}
```

This creates the visual impression that the list curves away like a cylinder — items at the bottom appear to recede into shadow. This is a widely used pattern (iOS scroll views, Google Maps, Spotify).

**C) Keep the half-height bottom item.** The last visible item being partially cut off (roughly half height visible) reinforces the scroll affordance alongside the gradient. Both cues together — the cut-off item and the darkening gradient — clearly communicate "scroll for more."

**D) Gradient visibility when scrolled to bottom.** When the user has scrolled to the bottom of the sub-menu (all items visible), the gradient should **disappear**. This signals "you've seen everything." Implementation: a small JS scroll listener on the sub-menu container that adds/removes a class (e.g. `.is-scrolled-to-bottom`) which hides the `::after` gradient.

```css
.submenu-container.is-scrolled-to-bottom::after {
  opacity: 0;
  transition: opacity 150ms;
}
```

**E) Contrast consideration.** The dark gradient slightly reduces contrast on the bottom-most visible item. This is acceptable — the item is partially obscured anyway (half-height) and the primary purpose of that visual zone is affordance, not readability. The user scrolls to fully reveal items they want to read.

### Future consideration: arrow buttons (parked)
The PBS-style arrow buttons for explicit scroll indication remain parked. If the dark gradient proves insufficient in user testing, arrow buttons can be added later without architectural changes — they would sit at the bottom of the sub-menu container as an additional affordance layer.

---

## 3. Search auto-scroll on small viewports

### Problem
On devices like iPhone SE3, the Search menu item is at or near the bottom of the visible area when the menu is open. Clicking Search reveals the search form (`<div id="search-form-container">`), but this form appears below the viewport and the user must manually scroll down to see it.

### Fix
When the Search menu item is clicked and the search form is revealed, **auto-scroll the page** so the search form is visible. The scroll should bring the search input field into view, not just the container.

```javascript
// After the search form is revealed and its height transition completes:
const searchContainer = document.getElementById('search-form-container');
if (searchContainer) {
  searchContainer.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}
```

**Timing:** The scroll should happen **after** the search form's height transition completes (otherwise it scrolls to the wrong position while the form is still expanding). Use a `transitionend` event listener or a timeout matching the transition duration.

### Search form sizing
The search input box should match the **height and width** of the Search menu item itself, maintaining space on the right for the magnifying glass icon. This keeps the interaction compact and visually consistent with the menu.

```css
#search-form-container input[type="search"],
#search-form-container input[type="text"] {
  height: 48px;  /* match menu item min-height */
  width: 100%;
  padding-right: 48px;  /* space for magnifying glass */
  font-size: 1rem;
  font-family: 'Atkinson Hyperlegible Next', sans-serif;
}
```

### Edge case detection
Rather than calculating exact viewport positions, use a simpler heuristic: **always auto-scroll when the search form opens on mobile.** If the form is already in view, `scrollIntoView({ block: 'nearest' })` does nothing. If it's off-screen, it scrolls. No viewport measurement needed.

---

## 4. Close menu button — text label

### Current behaviour
The hamburger toggle shows ☰ (open) and ✕ (close) icons. The close state is an "X" without text.

### New behaviour
When the menu is open, the toggle button displays:

```
✕
CLOSE
MENU
```

Three lines: the X icon, then "CLOSE" on one line, then "MENU" beneath. All centred.

The button should be repositioned **slightly higher vertically** so it aligns neatly with the site logo to its left. The overall button height increases to accommodate the text, but it should sit comfortably within the header bar height.

### Implementation

```html
<button class="menu-toggle is-open" aria-expanded="true" aria-label="Close menu">
  <span class="menu-toggle__icon">✕</span>
  <span class="menu-toggle__label">
    <span class="menu-toggle__label-close">Close</span>
    <span class="menu-toggle__label-menu">Menu</span>
  </span>
</button>
```

```css
.menu-toggle.is-open {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 1px;
}

.menu-toggle__label {
  display: none;  /* hidden when menu is closed (showing hamburger) */
}

.menu-toggle.is-open .menu-toggle__label {
  display: flex;
  flex-direction: column;
  align-items: center;
  font-family: 'Atkinson Hyperlegible Next', sans-serif;
  font-size: 0.55rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: white;
  line-height: 1.1;
}
```

**Claude Code:** Inspect the existing hamburger button markup and adapt. The button may use SVG for the icons, CSS-drawn lines, or icon fonts. The text label addition should not break the existing hamburger display when the menu is closed.

---

## 5. Keyboard accessibility — tab navigation

### Current state
Tabbing through main menu items does not work. Tabbing within an opened sub-menu and its items does work.

### Required behaviour
All interactive menu elements must be reachable via keyboard Tab key:

1. Tab reaches the hamburger toggle button
2. After opening the menu (Enter/Space on the toggle), Tab moves through main menu items in order: Home → Culture → Sectors → Living → Explore → About → Search
3. Enter/Space on a main menu item opens its sub-menu
4. Tab then moves through the sub-menu items
5. When the sub-menu ends, Tab moves to the next main menu item
6. Shift+Tab reverses the order

### Common causes of broken tab navigation
- Menu items using `<div>` or `<span>` instead of `<a>` or `<button>` — these are not focusable by default
- `tabindex="-1"` applied to main menu items
- `display: none` or `visibility: hidden` on the menu container when closed (this correctly removes items from tab order when the menu is closed, but the items must become tabbable when the menu is open)
- Focus not being managed when the menu opens — focus should move to the first menu item after the menu opens

### Implementation notes
- Ensure all main menu items are `<a>` or `<button>` elements (or have `tabindex="0"` and appropriate `role`)
- When the menu opens, set focus on the first menu item (Home)
- When the menu closes, return focus to the hamburger toggle button
- Sub-menu items that are hidden (sub-menu closed) should have `tabindex="-1"` or be in a container with `display: none` so they're removed from the tab order. When the sub-menu opens, they become tabbable.
- The Escape key should close the menu and return focus to the hamburger toggle

### Focus visibility
Focused menu items should have a distinctive **orange background with dark text** — visible only during keyboard navigation (`:focus-visible` does not activate on mouse/touch clicks). The orange is not used anywhere else in the menu, making it unambiguous as a keyboard focus indicator.

**Important — use `:focus-visible` exclusively, never `:focus`.** The browser tracks input mode: when the user presses Tab, `:focus-visible` activates. When they switch to mouse, `:focus-visible` deactivates immediately — the orange disappears cleanly. Using `:focus` instead would cause the orange to persist after mouse clicks (the element retains focus), creating a stuck visual state. Claude Code must not mix `:focus` and `:focus-visible` on menu elements.

```css
/* Keyboard focus — orange background, dark text */
/* Main menu items (on solent blue background) */
.menu-item:focus-visible > a {
  background: #e8870e;
  color: var(--text, #1a1a1a);
  outline: none;  /* replaced by the background change */
}

.menu-item:focus-visible .menu-chevron {
  fill: var(--text, #1a1a1a);
}

/* Sub-menu items (on off-white background) */
.submenu-item:focus-visible > a {
  background: #e8870e;
  color: var(--text, #1a1a1a);
  outline: none;
}
```

**Contrast:** `#e8870e` orange background with `#1a1a1a` dark text gives approximately 4.6:1 — passes WCAG AA.

### Tab navigation applies to both desktop AND mobile

Keyboard tab navigation, focus management, and the orange focus style apply at **all viewport widths** — not just desktop. Users connect Bluetooth keyboards to phones and tablets, and accessibility auditors test keyboard navigation at mobile widths. The tab order, Enter/Space to open sub-menus, Escape to close, and focus styles must all work within the mobile menu layout as well as the desktop layout.

---

## 6. Progressive enhancement — no-JS fallback

### Principle
The menu should be **functional without JavaScript** — all navigation links reachable, all pages accessible. JavaScript enhances with toggle behaviour, animations, and visual states. Without JS, a simpler but fully functional menu operates.

### Detection mechanism
There is no reliable server-side detection of JS capability — the HTTP request contains no such information. The standard approach (used by Drupal core) is client-side: JS adds a class to `<html>` on load. CSS uses its presence or absence to switch between implementations.

Drupal core already adds a `js` class to `<html>` when JS is active. Use this existing class rather than adding a custom `js-menu-enhanced` class. Check the actual class name in the Drupal output — it may be `js` or `js-enabled` depending on the Drupal version.

### Two separate implementations

Both menu versions exist in the markup. CSS shows one and hides the other based on the `js` class.

**With JS (`<html class="js">`):**
- The JS-enhanced menu operates as it does now — hamburger toggle, push-down, animations, visual states, navigation indication
- The no-JS fallback markup is hidden via CSS

**Without JS (no `js` class on `<html>`):**
- The JS-enhanced menu controls (hamburger button, toggle behaviour) are hidden
- The no-JS fallback menu is visible and functional

### Desktop no-JS: CSS hover sub-menus

The existing main menu markup can serve double duty. Without JS, CSS `:hover` and `:focus-within` reveal sub-menus on desktop:

```css
/* No-JS desktop: hover/focus reveals sub-menus */
html:not(.js) .main-menu-item .submenu {
  display: none;
  position: absolute;
  /* standard dropdown positioning */
}

html:not(.js) .main-menu-item:hover .submenu,
html:not(.js) .main-menu-item:focus-within .submenu {
  display: block;
}
```

`:focus-within` ensures keyboard users can access sub-menus via Tab — when focus enters any element within the menu item (including sub-menu links), the sub-menu stays visible. This covers the accessibility gap that `:hover` alone would leave.

**No structural markup changes needed for desktop.** The existing `<ul>/<li>` menu structure supports CSS-only dropdowns natively.

### Mobile no-JS: `<details>/<summary>` fallback menu

A separate menu block in the markup using native HTML toggle elements:

```html
<nav class="mobile-menu-noscript" aria-label="Mobile navigation">
  <a href="/">Home</a>

  <details>
    <summary>Culture</summary>
    <ul>
      <li><a href="/culture">View All Culture</a></li>
      <li><a href="/culture/music">Music</a></li>
      <li><a href="/culture/screen">Screen</a></li>
      <li><a href="/culture/dance">Dance</a></li>
      <!-- etc. -->
    </ul>
  </details>

  <details>
    <summary>Sectors</summary>
    <ul>
      <li><a href="/sectors">View All Sectors</a></li>
      <li><a href="/sectors/technology">Technology</a></li>
      <!-- etc. -->
    </ul>
  </details>

  <details>
    <summary>Living</summary>
    <ul>
      <li><a href="/living">View All Living</a></li>
      <!-- etc. -->
    </ul>
  </details>

  <details>
    <summary>Explore</summary>
    <ul>
      <li><a href="/explore">View All Explore</a></li>
      <!-- etc. -->
    </ul>
  </details>

  <details>
    <summary>About</summary>
    <ul>
      <li><a href="/about">View All About</a></li>
      <!-- etc. -->
    </ul>
  </details>

  <a href="/search">Search</a>
</nav>
```

`<details>/<summary>` provides native browser toggle — tap the summary to expand, tap again to collapse. No JS needed. The browser handles the expand/collapse natively.

### CSS switching between implementations

```css
/* ── No-JS fallback menu: hidden when JS is active ── */
html.js .mobile-menu-noscript {
  display: none;
}

/* ── No-JS fallback menu: visible when JS is absent (mobile only) ── */
@media (max-width: 799px) {
  html:not(.js) .mobile-menu-noscript {
    display: block;
  }
}

/* ── JS-enhanced menu controls: hidden when JS is absent ── */
html:not(.js) .menu-toggle {
  display: none;  /* hide hamburger button */
}

/* ── On desktop without JS, hide the mobile fallback ── */
@media (min-width: 800px) {
  .mobile-menu-noscript {
    display: none;  /* desktop uses CSS hover on the main menu instead */
  }
}
```

### Styling the `<details>/<summary>` fallback

The fallback menu should be minimally styled to be usable — it doesn't need the full visual treatment of the JS-enhanced menu:

```css
html:not(.js) .mobile-menu-noscript {
  background: var(--solent-blue, #2c4f6e);
  padding: 0.5rem 0;
}

html:not(.js) .mobile-menu-noscript > a,
html:not(.js) .mobile-menu-noscript summary {
  display: block;
  padding: 12px 16px;
  min-height: 44px;
  color: white;
  text-decoration: none;
  font-family: 'Atkinson Hyperlegible Next', sans-serif;
  font-size: 1rem;
  border-bottom: 1px solid rgba(255, 255, 255, 0.2);
  cursor: pointer;
}

html:not(.js) .mobile-menu-noscript details ul {
  list-style: none;
  margin: 0;
  padding: 0;
  background: var(--warm-grey, #f5f3f0);
}

html:not(.js) .mobile-menu-noscript details li a {
  display: block;
  padding: 10px 16px;
  min-height: 44px;
  color: var(--solent-blue, #2c4f6e);
  text-decoration: none;
  font-family: 'Atkinson Hyperlegible Next', sans-serif;
  font-size: 0.95rem;
  border-bottom: 1px solid #e0e0e0;
}
```

### Implementation — where to put the fallback markup

The `<details>/<summary>` fallback menu should be generated from the same Drupal menu data as the main menu. Two options:

**Option A: Twig template.** Add the fallback markup to the page template or a block template, rendered from the same menu tree. This avoids maintaining two separate menu configurations.

**Option B: Custom block.** Create a block that renders the `<details>/<summary>` version of the main menu. Place it in the header region. CSS controls visibility based on `js` class.

**Option A is preferred** — the fallback is part of the page template, generated from the same menu data, and doesn't require a separate block configuration. Claude Code should determine the cleanest way to render the menu tree twice in the page template — once as the JS-enhanced version, once as the `<details>/<summary>` version.

### What works without JS

| Feature | Desktop | Mobile |
|---------|---------|--------|
| All navigation links reachable | ✓ (hover/focus submenus) | ✓ (details/summary toggle) |
| Sub-menu toggle | ✓ (hover/focus) | ✓ (native details/summary) |
| Push-down animation | ✗ | ✗ |
| Hover colour changes | ✓ (CSS :hover) | ✓ (CSS :hover for mouse users) |
| Navigation-indicated states | ✗ | ✗ |
| Search form | Visible (or link to /search) | Visible (or link to /search) |
| Keyboard tab navigation | ✓ (focus-within) | ✓ (native details/summary) |
| Hamburger button | ✗ (hidden) | ✗ (hidden) |

### Testing without JS
Disable JavaScript in the browser. Verify:
- **Desktop:** Hovering main menu items reveals sub-menus. Tab + focus reveals sub-menus. All links work.
- **Mobile:** `<details>/<summary>` menu is visible. Tapping sections expands/collapses them natively. All links work.
- No hamburger button visible on either viewport.
- Main page content is accessible below the menu.

---

## 7. Contrib module outline (considerations only — no implementation)

### Purpose
Package the mobile and desktop menu system as a reusable Drupal contrib module for other projects and the community. This section outlines what would need to be configurable, not how to implement it.

### Configurable variables

**Colour system:**
- Primary background colour (solent blue equivalent)
- Primary text colour (white equivalent)
- Hover colour (pink equivalent)
- Active/indicated colour (magenta/magenta dark equivalents)
- Sub-menu background colour (off-white equivalent)
- Sub-menu text colour
- Divider colours (main menu, sub-menu)
- Focus ring colour

**Layout options:**
- Mobile menu mode: overlay | pushdown (configurable via admin UI or theme setting)
- Sub-menu display: vertical-single-column | vertical-grid | horizontal-scroll (future modes)
- Sub-menu max visible items (currently 6, should be configurable)
- Scroll affordance style: none | white-fade | dark-fade | arrow-buttons

**Logo and header:**
- Logo position: left | centre | right
- Logo size constraints
- Header bar height
- Hamburger button position relative to logo
- Close button label text (configurable or translatable)

**Behavioural options:**
- Push-down animation enabled: yes | no
- Animation duration (ms)
- Search form: inline (current) | separate page link
- Navigation indication: URL-based | topic-based | both | none
- Keyboard navigation: enabled | disabled
- No-JS fallback: expanded | collapsed-with-noscript-message

**Menu structure:**
- Maximum menu depth supported (currently 2 — main + sub)
- "View All" items: auto-generated | manual | hidden
- Dividers: visible | hidden

**Visual state configuration:**
- Navigation indicator style on main items: bottom-line (desktop) | left-border (mobile) | background-change | none
- Navigation indicator style on sub-items: background-change | underline | bold | none
- Hover style: colour-change | background-inversion | underline | none

### Architecture considerations

- **CSS custom properties** for all colours — the module provides defaults, themes override via `:root` or a settings form
- **Twig template** that the module provides as a default, but themes can override with their own template
- **JS as a behaviour** (Drupal.behaviors) rather than a standalone script, so it integrates with Drupal's AJAX system
- **Settings form** (or theme settings integration) for the configurable options above
- **Library definition** that themes can extend or override
- **No dependency on specific theme** — should work with any Drupal base theme (Olivero, Stark, Bootstrap, custom)
- **Accessibility built in** — ARIA attributes, keyboard navigation, focus management as non-optional features, not configuration toggles
- **RTL support** — left borders become right borders, scroll directions flip
- **Responsive breakpoint** configurable (currently 800px)

### What to extract from the current codebase

- `customsolent.js` menu toggle logic → generalised module JS
- `menu-state.js` URL-based navigation indication → module JS
- `menu-state-topic.js` topic-based indication → optional module JS (requires specific field setup)
- Mobile menu CSS (push-down, visual states, dividers, scroll affordance) → module CSS with custom properties
- Desktop menu CSS (visual states, bottom line, hover) → module CSS with custom properties
- Twig menu template → module default template

### What stays in the theme

- Specific colours (solent blue, magenta, pink)
- Logo and header layout specifics
- Site-specific typography (Atkinson Hyperlegible Next)
- Topic-based navigation (requires `field_primary_topic` — site-specific)

### Next steps (not for this brief)

1. Create a GitHub repository for the contrib module
2. Extract and generalise the menu code
3. Create a settings form or theme settings integration
4. Write documentation
5. Submit to Drupal.org as a contrib module

---

## Testing

1. **Home alignment:** On the home page and on other pages, "Home" text is horizontally aligned with Culture, Sectors, Living, etc.
2. **Scroll affordance — no flash:** Open Culture sub-menu. The dark gradient is visible from the first frame — no momentary display of unshaded items.
3. **Scroll affordance — dark gradient:** The gradient fades from transparent to a subtle dark tone at the bottom of the sub-menu. The bottom-most visible item is partially obscured (half-height) with darkening.
4. **Scroll affordance — scrolled to bottom:** Scroll the Culture sub-menu to the very bottom. The gradient disappears, confirming all items have been seen.
5. **Scroll affordance — short menus:** Open About (8 items). If all items fit without scrolling, no gradient appears.
6. **Search auto-scroll (iPhone SE3 or equivalent):** Open the menu, tap Search. The search form scrolls into view automatically. The input field is visible without manual scrolling.
7. **Search form sizing:** The search input matches the height of the Search menu item. Magnifying glass icon has space on the right.
8. **Close button label:** When menu is open, the toggle shows ✕ with "CLOSE" and "MENU" text below. The button aligns vertically with the site logo.
9. **Close button — menu closed:** When menu is closed, the hamburger icon displays without the "CLOSE MENU" text.
10. **Tab navigation — mobile:** Open the menu. Tab key moves through Home → Culture → Sectors → Living → Explore → About → Search in order.
11. **Tab — sub-menu:** Tab to Culture, press Enter. Sub-menu opens. Tab moves through sub-menu items. After last sub-item, Tab moves to Sectors.
12. **Tab — focus style:** Each focused item has an orange background (`#e8870e`) with dark text. This only appears during keyboard navigation, not on mouse click or touch.
13. **Escape key:** With menu open, press Escape. Menu closes, focus returns to hamburger button.
14. **Desktop tab:** Verify Tab works through desktop menu items as well.
15. **No-JS desktop — hover submenus:** Disable JavaScript. On desktop width, hover a main menu item — sub-menu appears. Move cursor away — sub-menu disappears.
16. **No-JS desktop — keyboard:** Disable JavaScript. Tab to a main menu item, then Tab into its sub-menu. Sub-menu stays visible while focus is within it (`:focus-within`).
17. **No-JS mobile — details/summary:** Disable JavaScript. On mobile width, the `<details>/<summary>` fallback menu is visible. Tap a section name — it expands to show sub-items. Tap again — it collapses.
18. **No-JS mobile — all links work:** Disable JavaScript. All navigation links in the fallback menu navigate to the correct pages.
19. **No-JS — hamburger hidden:** Disable JavaScript. The hamburger button is not visible on any viewport width.
20. **No-JS — fallback hidden when JS active:** Enable JavaScript. The `<details>/<summary>` fallback menu is not visible. Only the JS-enhanced menu operates.
21. **No regressions:** Push-down animation, visual states, dividers, and full-width tap targets from previous briefs still work correctly with JS enabled.

---

## Files to create or modify

```
M  web/themes/custom/customsolent/css/navigation.css (or mobile menu CSS file)
     — Home alignment fix
     — Dark gradient scroll affordance
     — Search form sizing
     — Close button label styling
     — Focus ring styles
     — No-JS CSS hover/focus-within sub-menus for desktop
     — No-JS details/summary fallback menu styling
     — CSS switching: html.js / html:not(.js) visibility rules

M  web/themes/custom/customsolent/customsolent.js (or mobile menu JS)
     — Fix scroll affordance flash (gradient present from first frame)
     — Gradient hide on scroll-to-bottom
     — Search auto-scroll on reveal
     — Close button label toggle
     — Keyboard focus management (tab order, Escape key, focus on open/close)

M  web/themes/custom/customsolent/templates/ (page or block template)
     — Add <details>/<summary> fallback menu markup (generated from same menu data)

M  web/themes/custom/customsolent/customsolent.libraries.yml (if new files added)
```

Claude Code should inspect the codebase to identify the exact files.

---

## Implementation order

Steps are grouped by dependency. Groups can be done in any order internally, but the group order reflects interlinking.

### Group A — standalone fixes (any order within group)

| Step | Task | Who |
|------|------|-----|
| 1 | Fix Home menu item horizontal alignment | Claude Code |
| 2 | Replace white gradient with dark gradient on sub-menu scroll | Claude Code |
| 3 | Fix gradient flash on sub-menu open (present from first frame) | Claude Code |
| 4 | Add gradient hide on scroll-to-bottom | Claude Code |
| 5 | Add search auto-scroll for small viewports | Claude Code |
| 6 | Size search input to match Search menu item | Claude Code |
| 7 | Add "CLOSE MENU" text label to toggle button when open | Claude Code |
| 8 | Adjust toggle button vertical position to align with logo | Claude Code |

### Group B — keyboard + desktop no-JS (do together — share `:focus-within` on same selectors)

| Step | Task | Who |
|------|------|-----|
| 9 | Fix keyboard tab navigation through main menu items | Claude Code |
| 10 | Add focus management (focus on open, return on close, Escape key) | Claude Code |
| 11 | Add visible focus styles (orange background, dark text, `:focus-visible`) | Claude Code |
| 12 | Verify desktop tab navigation works | Claude Code |
| 13 | Add CSS-only hover/focus-within sub-menus for desktop no-JS | Claude Code |
| 14 | Add CSS switching (`html.js` / `html:not(.js)`) for desktop menu | Claude Code |

### Group C — mobile no-JS (standalone, do last)

| Step | Task | Who |
|------|------|-----|
| 15 | Create `<details>/<summary>` fallback menu markup for mobile no-JS | Claude Code |
| 16 | Style the `<details>/<summary>` fallback menu | Claude Code |
| 17 | Add CSS switching for mobile fallback visibility | Claude Code |

### Final

| Step | Task | Who |
|------|------|-----|
| 18 | Test all scenarios with JS enabled and disabled | Rob |
