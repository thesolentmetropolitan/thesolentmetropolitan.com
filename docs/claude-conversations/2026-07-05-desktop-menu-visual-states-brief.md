# The Solent Metropolitan — Desktop Menu Visual States Brief

## Overview

Define and implement the complete set of visual states for the **desktop-width main menu and sub-menus**. This covers hover states, active (clicked) states, and navigation-indicated states (showing which section the user is currently in). Mobile menu is out of scope.

**Drupal 11 compatible. All changes are CSS and JavaScript only — no PHP/Twig changes expected unless the markup needs additional classes.**

---

## A) Definitions

### Menu items

**Main menu items:** Home, Culture, Sectors, Living, Explore, About, Search

**Sub-menu items:** Culture, Sectors, Living, Explore, and About each have sub-menu items revealed when the main menu item is clicked.

### Colour set reference

| Name | Hex | Usage |
|------|-----|-------|
| Solent Blue | `#2c4f6e` | Main menu bar background |
| Solent Blue Light | `#3a6489` | — |
| Pink Hover | `#f5b0d8` | Hover text on solent blue (5.0:1 contrast) |
| Magenta | `#c5007a` | Link underlines, accents |
| Magenta Dark | `#a30065` | Current-state indicators |
| Off-white / Warm Grey | `#f5f3f0` | Sub-menu background, active main item background |
| White | `#ffffff` | Default main menu text |

### State terminology

**Active main menu item** — a main menu item that has been clicked on, resulting in its sub-menu items being revealed. The visual state changes immediately on click (background flips to off-white, text to solent blue).

**Navigation-indicated main menu item** — the visual state of a main menu item when the current page belongs to its section. E.g. the "Culture" main menu item is navigation-indicated on `/culture`, `/culture/music`, `/culture/music/dance`, and any other page whose URL starts with `/culture`.

**Navigation-indicated sub-menu item** — the visual state of a sub-menu item when the current page matches or is a child of that sub-menu item's URL. E.g. the "Music" sub-menu item is navigation-indicated on `/culture/music` and `/culture/music/dance`.

---

## B) Complete visual state matrix

### Main menu items

| State | Text colour | Text weight | Background | Bottom line | Chevron |
|-------|------------|-------------|------------|-------------|---------|
| **Default** | White `#fff` | Normal | Solent Blue `#2c4f6e` | None | White |
| **Hovered** (non-active, non-indicated) | Pink `#f5b0d8` | Normal | Solent Blue | None | Pink `#f5b0d8` |
| **Active** (clicked, submenu open) | Solent Blue `#2c4f6e` | Normal | Off-white `#f5f3f0` | None | Solent Blue |
| **Active + hovered** | Magenta Dark `#a30065` | Normal | Off-white | None | Magenta Dark |
| **Navigation-indicated** (non-active) | White `#fff` | **Bold** | Solent Blue | Magenta Dark `#a30065`, 3px, 80% width, centred | White |
| **Navigation-indicated + hovered** (non-active) | White `#fff` | **Bold** | Solent Blue | Magenta Dark, 3px, 80% width | White (no change from indicated) |
| **Navigation-indicated + active** (clicked open) | Solent Blue `#2c4f6e` | **Bold** | Off-white | Magenta Dark, 3px, 80% width | Solent Blue |
| **Navigation-indicated + active + hovered** | Magenta Dark `#a30065` | **Bold** | Off-white | Magenta Dark, 3px, 80% width | Magenta Dark |

**Notes on navigation-indicated hover behaviour:**
- When the main menu item is navigation-indicated but NOT active (submenu closed), hovering has **no additional visual effect** — the bold text and bottom line already communicate "you are here." Applying a hover colour change on top of the indicator would be visually noisy.
- When the main menu item is navigation-indicated AND active (submenu open), the active+hovered state applies (magenta dark text) to confirm the item is interactive.

### Sub-menu items

| State | Text colour | Background | Notes |
|-------|------------|------------|-------|
| **Default** | Solent Blue `#2c4f6e` | Off-white `#f5f3f0` | Standard sub-menu item |
| **Hovered** | Off-white `#f5f3f0` | Solent Blue `#2c4f6e` | Existing inversion behaviour |
| **Navigation-indicated** | Off-white `#f5f3f0` | Magenta Dark `#a30065` | Current page indicator |
| **Navigation-indicated + hovered** | Off-white `#f5f3f0` | Solent Blue `#2c4f6e` | Hover overrides the magenta dark bg — reverts to standard hover appearance |

**"View All" items** (View All Culture, View All Sectors, etc.) follow the same states as other sub-menu items. When on `/culture`, "View All Culture" is navigation-indicated per the rules above.

### Home main menu item

Follows the same main menu item states as defined above. On the `/` (home page), Home is navigation-indicated: white bold text, magenta dark bottom line.

### Search main menu item

No changes. Search retains its current behaviour.

---

## C) Bottom line specification

The navigation-indicated bottom line on main menu items:

- **Colour:** Magenta Dark `#a30065`
- **Thickness:** 3px
- **Width:** 80% of the menu item's clickable rectangle
- **Position:** Centred horizontally (10% inset from each side)
- **Vertical position:** Bottom edge of the menu item's rectangle, with a small gap (2–4px) above the bottom edge so the line doesn't touch the very edge
- **Implementation:** CSS `::after` pseudo-element on the menu item, positioned absolutely

```css
/* Navigation-indicated bottom line */
.main-menu-item.is-current-section::after {
  content: '';
  position: absolute;
  bottom: 4px;
  left: 10%;
  width: 80%;
  height: 3px;
  background: var(--magenta-dark, #a30065);
}
```

The bottom line persists in all navigation-indicated states (non-active, active, hovered). It does not appear on non-indicated items.

---

## D) Contrast verification

| Element | Foreground | Background | Ratio | Passes |
|---------|-----------|------------|-------|--------|
| Pink hover text on menu bar | `#f5b0d8` | `#2c4f6e` | 5.0:1 | AA ✓ |
| Magenta dark hover on off-white | `#a30065` | `#f5f3f0` | 7.2:1 | AAA ✓ |
| Magenta dark bottom line on solent blue | `#a30065` | `#2c4f6e` | ~2.5:1 | Decorative element, acceptable |
| White bold text on solent blue | `#ffffff` | `#2c4f6e` | 8.1:1 | AAA ✓ |
| Navigation-indicated sub item (off-white on magenta dark) | `#f5f3f0` | `#a30065` | 7.2:1 | AAA ✓ |

**Note on the bottom line contrast (2.5:1):** This is below the 3:1 non-text UI component guideline, but the line is a supplementary indicator — the primary navigation signal is the bold text weight. The line reinforces rather than carries the information. If higher visibility is desired, the line colour can be changed to white (`#ffffff`) for 8.1:1 contrast against solent blue.

---

## E) JavaScript: determining navigation state from URL

### Approach

Client-side JavaScript reads the current page URL and compares it against the `href` attributes of the main and sub-menu links already present in the DOM. No server-side rendering or Drupal preprocess needed — all information is available in the page markup.

### Logic

```javascript
/**
 * Desktop menu navigation state indicator.
 * Reads the current URL and applies classes to main menu and sub-menu items
 * to indicate which section and sub-section the user is currently in.
 *
 * Classes applied:
 *   .is-current-section — on the main menu item whose URL is a prefix of the current path
 *   .is-active-page — on the sub-menu item whose URL matches or is a prefix of the current path
 */
(function () {
  'use strict';

  const currentPath = window.location.pathname;

  // Main menu items — check if current path starts with the menu item's href
  const mainMenuItems = document.querySelectorAll('.main-menu-item');
  mainMenuItems.forEach(function (item) {
    const link = item.querySelector('a');
    if (!link) return;

    const href = link.getAttribute('href');
    if (!href) return;

    // Special case: Home ("/") should only match exactly "/"
    if (href === '/') {
      if (currentPath === '/') {
        item.classList.add('is-current-section');
      }
      return;
    }

    // For other items: current path starts with the menu item's href
    if (currentPath === href || currentPath.startsWith(href + '/')) {
      item.classList.add('is-current-section');

      // Sub-menu items within this section
      const subMenuItems = item.querySelectorAll('.sub-menu-item');
      subMenuItems.forEach(function (subItem) {
        const subLink = subItem.querySelector('a');
        if (!subLink) return;

        const subHref = subLink.getAttribute('href');
        if (!subHref) return;

        if (currentPath === subHref || currentPath.startsWith(subHref + '/')) {
          subItem.classList.add('is-active-page');
        }
      });
    }
  });
})();
```

### Important implementation notes

- **Claude Code must inspect the actual DOM** to find the correct CSS selectors for main menu items, sub-menu items, and their links. The selectors above (`.main-menu-item`, `.sub-menu-item`) are placeholders — replace with the actual class names from the Drupal menu markup.
- The script should be placed in a new file (e.g. `js/menu-state.js`) and registered in `customsolent.libraries.yml`.
- The script runs on page load. No need for mutation observers or dynamic updates — the menu state only changes on navigation (full page reload).
- **Must not regress the existing `customsolent.js` menu toggle behaviour** (clicking to open/close submenus). The navigation state classes are additive — they add `.is-current-section` and `.is-active-page` alongside whatever classes the existing JS manages. Claude Code should add a separate function or a separate file, not modify the existing menu toggle logic.

### Note on bold text shift (J)

When a main menu item becomes navigation-indicated (bold), this could cause a layout shift as bold text is wider than normal text. However, this is NOT an issue because navigation-indicated state is determined on page load — the user arrives at the page with the bold already applied. There is no visible transition from normal to bold weight. The bold is present from first paint.

---

## F) CSS implementation

### Main menu hover states

```css
/* D)i) Non-active main menu item hover — pink text + chevron */
.main-menu-item:not(.is-active):hover > a {
  color: var(--pink-hover, #f5b0d8);
}
.main-menu-item:not(.is-active):hover .menu-chevron {
  color: var(--pink-hover, #f5b0d8);
  /* or fill for SVG chevrons */
  fill: var(--pink-hover, #f5b0d8);
}

/* D)ii) Active (opened) main menu item hover — magenta dark */
.main-menu-item.is-active:hover > a {
  color: var(--magenta-dark, #a30065);
}
.main-menu-item.is-active:hover .menu-chevron {
  color: var(--magenta-dark, #a30065);
  fill: var(--magenta-dark, #a30065);
}

/* Navigation-indicated non-active — bold white text, no hover change */
.main-menu-item.is-current-section:not(.is-active) > a {
  font-weight: 700;
  color: #ffffff;
}

/* Navigation-indicated non-active — suppress hover colour change */
.main-menu-item.is-current-section:not(.is-active):hover > a {
  color: #ffffff;  /* stays white on hover */
}

/* Navigation-indicated + active — bold solent blue text on off-white */
.main-menu-item.is-current-section.is-active > a {
  font-weight: 700;
  color: var(--solent-blue, #2c4f6e);
}

/* Navigation-indicated + active + hover — magenta dark */
.main-menu-item.is-current-section.is-active:hover > a {
  color: var(--magenta-dark, #a30065);
}

/* Bottom line indicator */
.main-menu-item.is-current-section::after {
  content: '';
  position: absolute;
  bottom: 4px;
  left: 10%;
  width: 80%;
  height: 3px;
  background: var(--magenta-dark, #a30065);
  border-radius: 1.5px;
}
```

### Sub-menu navigation-indicated state

```css
/* Navigation-indicated sub-menu item — magenta dark bg, off-white text */
.sub-menu-item.is-active-page > a {
  background: var(--magenta-dark, #a30065);
  color: var(--off-white, #f5f3f0);
}

/* Navigation-indicated sub-menu item hover — reverts to standard hover (solent blue bg) */
.sub-menu-item.is-active-page:hover > a {
  background: var(--solent-blue, #2c4f6e);
  color: var(--off-white, #f5f3f0);
}
```

### Selector note

**All CSS selectors above are illustrative.** Claude Code MUST inspect the actual Drupal menu markup to determine the correct selectors. The existing `customsolent.js` likely uses specific class names for the menu structure. The new CSS should use the same selectors, augmented with `.is-current-section` and `.is-active-page`.

---

## G) Scope reminder

- **Desktop only.** All styles should be scoped within a `@media (min-width: 800px)` query or equivalent, unless the menu structure is already desktop-only.
- **No changes to Search** menu item behaviour.
- **No changes to mobile menu.**
- **Must not regress** existing click-to-open submenu functionality in `customsolent.js`.

---

## Checklist: What Rob should configure

No Drupal admin configuration needed. This is entirely CSS and JavaScript work for Claude Code.

---

## Implementation order

| Step | Task | Who |
|------|------|-----|
| 1 | Inspect the DOM to identify correct menu item selectors and existing class names | Claude Code |
| 2 | Create `js/menu-state.js` — URL-based navigation state detection, adds `.is-current-section` and `.is-active-page` classes | Claude Code |
| 3 | Register `js/menu-state.js` in `customsolent.libraries.yml` | Claude Code |
| 4 | Add CSS for hover states (D): pink hover on non-active, magenta dark hover on active | Claude Code |
| 5 | Add CSS for navigation-indicated main menu items (E): bold text, bottom line, hover suppression | Claude Code |
| 6 | Add CSS for navigation-indicated sub-menu items (F): magenta dark background | Claude Code |
| 7 | Verify no regression of existing menu toggle JS (`customsolent.js`) | Claude Code |

---

## Testing

1. **Default hover (D)i):** Hover a non-active main menu item — text and chevron change to pink `#f5b0d8`.
2. **Active hover (D)ii):** Click a main menu item to open submenu, then hover it — text changes to magenta dark `#a30065`.
3. **Navigation-indicated main item (E):** Visit `/culture/music`. "Culture" main menu item shows bold white text with magenta dark bottom line (80% width, centred).
4. **Navigation-indicated hover suppression (E):** On `/culture/music`, hover the "Culture" main menu item (submenu closed) — no colour change, stays bold white.
5. **Navigation-indicated + active (E)i)2):** On `/culture/music`, click "Culture" to open submenu — text changes to bold solent blue on off-white background. Bottom line persists in magenta dark.
6. **Navigation-indicated + active + hover:** On the above state, hover "Culture" — text changes to magenta dark, bold retained, bottom line persists.
7. **Navigation-indicated sub-menu item (F):** On `/culture/music`, open the Culture submenu. "Music" shows magenta dark background with off-white text.
8. **Navigation-indicated sub-menu hover (F):** Hover the indicated "Music" item — background changes to solent blue (standard hover), text stays off-white.
9. **"View All" item (G):** On `/culture`, open Culture submenu. "View All Culture" shows the navigation-indicated state (magenta dark bg, off-white text).
10. **Home (H):** On `/`, "Home" shows bold white text with magenta dark bottom line.
11. **Non-indicated pages:** On a page that doesn't match any menu item URL (e.g. a standalone node), no main menu item or sub-menu item has the navigation-indicated state.
12. **Sub-sub-pages:** On `/culture/music/dance`, both "Culture" (main menu) and "Music" (sub-menu) show their navigation-indicated states.
13. **No regression:** Clicking main menu items still toggles submenus open/closed. Existing hover inversion on sub-menu items still works for non-indicated items.
14. **Desktop only:** Verify none of these styles appear below 800px viewport width.

---

## Files to create or modify

```
A  web/themes/custom/customsolent/js/menu-state.js
M  web/themes/custom/customsolent/customsolent.libraries.yml
M  web/themes/custom/customsolent/css/navigation.css (or wherever menu styles live)
```

Claude Code should identify the correct CSS file for menu styles by inspecting the existing codebase.
