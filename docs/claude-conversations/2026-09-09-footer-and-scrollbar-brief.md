# The Solent Metropolitan — Footer Improvements & Scrollbar Fix Brief

## Overview

Two pieces of work:

1. **Footer redesign** — restructure into three distinct layers following a BBC-style layout: main navigation + social icons, legal/policy links, and copyright/open source credits.
2. **Horizontal scrollbar fix** — eliminate the persistent horizontal scrollbar on desktop caused by content exceeding the viewport width.

**Drupal 11 compatible.**

---

## Part 1: Footer Redesign

### Current state
The footer has the main navigation menu (6 items in 2 columns), social icons (LinkedIn, Instagram, Bluesky), and copyright/open source text. The layout needs refinement — better spacing, dividers, and a new legal links menu.

### New structure: three layers

The footer background remains as-is (solent blue / dark). All text and icons are white/light. Dividing lines are white, 1px, with subtle opacity.

---

### Layer 1: Main navigation + Social icons

**Desktop layout:**

```
+-------------------------------------------------------------+
|                                                             |
|  Home       |  Living        ||   LI  IG  BS               |
|  Culture    |  Explore       ||                              |
|  Sectors    |  About         ||                              |
|                              ||                              |
+-------------------------------------------------------------+
```

- **Left section:** Existing 2-column, 3-row main menu (Home, Culture, Sectors | Living, Explore, About). Excludes Search.
  - **Vertical divider** centred between the two columns. White, 1px, `rgba(255, 255, 255, 0.3)`. Height taller than the text block — extends above and below the text rows so the text is vertically centred against the line.
- **Centre divider:** Vertical dividing line between the menu section and the social icons section. Same style — white 1px, `rgba(255, 255, 255, 0.3)`, full height of the layer.
- **Right section:** The 3 social icons (LinkedIn, Instagram, Bluesky). No title above — the icons are universally recognisable. Each icon link must have `aria-label` for accessibility (e.g. `aria-label="Follow us on LinkedIn"`).

**Mobile layout:**

```
+--------------------------+
|                          |
|  Home       |  Living    |
|  Culture    |  Explore   |
|  Sectors    |  About     |
|                          |
|  LI  IG  BS             |
|                          |
+--------------------------+
```

- Same 2-column menu with vertical divider between columns
- Social icons below the menu, left-aligned or centred (match the menu's left edge)
- No centre divider on mobile (menu and icons stack vertically)

---

### Layer 2: Legal/policy links menu

**Desktop and mobile layout:**

```
+-------------------------------------------------------------+
|  -----------------------------------------------------------  |
|                                                             |
|  Contact Us   Terms of Use   Privacy Policy   Editorial     |
|  Policy   Accessibility                                     |
|                                                             |
|  -----------------------------------------------------------  |
+-------------------------------------------------------------+
```

- **Horizontal line above:** Full width of the content area. White 1px, `rgba(255, 255, 255, 0.2)`.
- **Vertical space above:** Equal padding above and below the links row.
- **Links:** Inline, spaced with horizontal padding. **No vertical dividers** between links — spacing alone provides separation.
- **Horizontal line below:** Same as above.
- **Vertical space below:** Equal to the space above — symmetrical padding.

**Link styling:**
- White text, no underline by default
- Pink hover (`#f5b0d8`) — matching the site's hover convention on solent blue backgrounds
- Font size: 0.82rem
- Font weight: 400

**On mobile:** The links wrap naturally. On very narrow screens (iPhone SE3), the links may stack to two lines — spacing between items provides separation.

### Menu creation

**Menu name:** Footer legal
**Machine name:** `footer_legal`

Rob will create this menu in the Drupal admin and export via `structure_sync` to config.

**Menu items (in order):**
1. Contact Us -> `/about/contact-us`
2. Terms of Use -> `/about/terms-use`
3. Privacy Policy -> `/about/privacy-policy`
4. Editorial Policy -> `/about/editorial-policy`
5. Accessibility -> `/about/accessibility`

### Rendering the menu

The footer legal menu needs a Twig template or preprocess that renders the items inline with dividers. Options:

**Option A: Render via a new paragraph type or a direct Twig include in the footer template.**

If the footer is built from paragraphs, add a new paragraph or use a custom block. If the footer is a Twig template, add a `drupal_menu('footer_legal')` call (via Twig Tweak) and style with CSS.

**Option B: Preprocess similar to the existing main menu footer rendering.**

The existing main navigation in the footer is handled by `customsolent_preprocess_paragraph__menu` in `customsolent.theme`. A similar preprocess for the legal menu can render it inline.

**Claude Code should inspect how the current footer is structured** and choose the approach that fits the existing architecture. The legal menu is a simple flat list of links — no hierarchy, no toggle, no sub-items.

### Divider specification (all dividers)

All vertical and horizontal dividers in the footer share these properties:

```css
/* Vertical dividers */
.slnt-footer__divider-v {
  width: 1px;
  background: rgba(255, 255, 255, 0.3);
  align-self: stretch;  /* full height of flex container */
}

/* Horizontal dividers */
.slnt-footer__divider-h {
  height: 1px;
  background: rgba(255, 255, 255, 0.2);
  width: 100%;
}
```

The vertical dividers should be **taller than the content they separate** — this is achieved by the divider being a flex item with `align-self: stretch` in a flex container, while the text/icons are vertically centred with `align-items: center`. The divider extends to the full height of the container's padding box, and the content sits centred within that height.

---

### Layer 3: Copyright and open source

**Desktop layout:**

- **Left:** "This site source code is open source." text + GitHub and Drupal SVG icons. Left-aligned.
- **Right:** "Copyright (c) The Solent Metropolitan" + year. Right-aligned.

**Mobile layout:**

- **Both sections left-aligned** on mobile (not right-aligned for the copyright). This avoids a ragged right edge on narrow screens.
- Stack vertically with space between.

**Vertical spacing below Layer 3:** Add padding below the copyright section for both desktop and mobile so the content doesn't touch the bottom of the viewport.

---

### Overall footer CSS structure

```css
/* ======================================================
   Footer — three-layer structure
   ====================================================== */

.slnt-footer {
  background: var(--solent-blue, #2c4f6e);
  color: #ffffff;
  font-family: 'Atkinson Hyperlegible Next', sans-serif;
}

.slnt-footer__inner {
  max-width: 1200px;
  margin: 0 auto;
  padding: 0 var(--content-pad, 2rem);
}

/* -- Layer 1: Nav + Social -- */
.slnt-footer__layer1 {
  display: flex;
  align-items: center;
  gap: 2rem;
  padding: 2rem 0;
}

.slnt-footer__nav {
  flex: 1;
}

.slnt-footer__nav-grid {
  display: grid;
  grid-template-columns: 1fr auto 1fr;  /* col1 | divider | col2 */
  gap: 0.5rem 1rem;
  align-items: center;
}

.slnt-footer__social {
  display: flex;
  align-items: center;
  gap: 1rem;
}

.slnt-footer__social a {
  color: #ffffff;
  transition: color 0.15s;
}

.slnt-footer__social a:hover {
  color: var(--pink-hover, #f5b0d8);
}

/* -- Layer 2: Legal links -- */
.slnt-footer__layer2 {
  padding: 1.2rem 0;
}

.slnt-footer__legal {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0;
  justify-content: center;
}

.slnt-footer__legal a {
  color: #ffffff;
  text-decoration: none;
  font-size: 0.82rem;
  font-weight: 400;
  padding: 0.3em 1em;
  transition: color 0.15s;
}

.slnt-footer__legal a:hover {
  color: var(--pink-hover, #f5b0d8);
}

/* -- Layer 3: Copyright + Open Source -- */
.slnt-footer__layer3 {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1.5rem 0 2rem;
  font-size: 0.78rem;
  color: rgba(255, 255, 255, 0.7);
}

.slnt-footer__opensource {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.slnt-footer__copyright {
  text-align: right;
}

/* -- Mobile -- */
@media (max-width: 799px) {
  .slnt-footer__inner {
    padding: 0 var(--content-pad-mobile, 1.2rem);
  }

  /* Layer 1: stack nav above social */
  .slnt-footer__layer1 {
    flex-direction: column;
    align-items: flex-start;
    gap: 1.5rem;
  }

  /* Hide centre divider between nav and social on mobile */
  .slnt-footer__layer1 > .slnt-footer__divider-v {
    display: none;
  }

  /* Layer 2: wrap legal links */
  .slnt-footer__legal {
    justify-content: flex-start;
  }

  /* Layer 3: both sections left-aligned, stacked */
  .slnt-footer__layer3 {
    flex-direction: column;
    align-items: flex-start;
    gap: 0.8rem;
    padding: 1.2rem 0 1.5rem;
  }

  .slnt-footer__copyright {
    text-align: left;
  }
}
```

---

## Part 2: Horizontal Scrollbar Fix

### Problem
On desktop, the page always has a horizontal scrollbar, indicating content is wider than the viewport. This is a common CSS issue caused by an element exceeding 100vw (which includes the scrollbar width) or having negative margins/overflow.

### Diagnosis
Claude Code should inspect the page at desktop width and identify the element causing the overflow. Common culprits:

1. **`100vw` usage** — `width: 100vw` includes the scrollbar width, making the element wider than the visible viewport. Fix: use `width: 100%` instead of `100vw`, or add `overflow-x: hidden` to the `<html>` or `<body>`.

2. **Negative margins** on full-width elements (hero gradient, footer) that extend beyond the content container without being clipped.

3. **A specific element** (table, image, code block, SVG) that has a fixed width exceeding the viewport.

4. **Horizontal padding/margin** that adds to 100% width — e.g. `width: 100%; padding: 0 2rem;` without `box-sizing: border-box`.

### Fix approach

**Step 1:** Use the browser's DevTools "Elements" panel. On the page with the scrollbar, add `overflow-x: hidden` temporarily to `<html>` to confirm the issue is horizontal overflow. Then remove it and find the specific element.

**Step 2:** In DevTools, use the "Select element" tool and hover across the page at the far right edge. The overflowing element will highlight.

**Step 3:** Alternatively, add this debugging CSS temporarily:

```css
* { outline: 1px solid red !important; }
```

This outlines every element, making it easy to spot which one extends beyond the viewport edge.

**Step 4:** Fix the specific element. Common fixes:
- Replace `100vw` with `100%`
- Add `overflow-x: hidden` to the body/html (last resort — hides the symptom, not the cause)
- Add `box-sizing: border-box` if missing
- Constrain a specific element with `max-width: 100%`

---

## Checklist: What Rob should configure

### Footer legal menu
- [ ] Create menu "Footer legal" (machine name: `footer_legal`) in Drupal admin
- [ ] Add menu items: Contact Us, Terms of Use, Privacy Policy, Editorial Policy, Accessibility — each linking to the appropriate `/about/...` path
- [ ] Export via structure_sync to config

### Social icon accessibility
- [ ] Verify each social icon link has an `aria-label` (e.g. `aria-label="Follow us on LinkedIn"`). If not, Claude Code should add them.

---

## Implementation order

| Step | Task | Who |
|------|------|-----|
| 1 | Create "Footer legal" menu with 5 items, export to config | Rob |
| 2 | Diagnose and fix horizontal scrollbar issue | Claude Code |
| 3 | Restructure footer template into 3 layers | Claude Code |
| 4 | Render footer legal menu in Layer 2 (inline, spaced, no dividers) | Claude Code |
| 5 | Add vertical dividers to Layer 1 (between nav columns, between nav and social) | Claude Code |
| 6 | Add horizontal dividers above and below Layer 2 | Claude Code |
| 7 | Style Layer 3 — desktop right-aligned copyright, mobile left-aligned | Claude Code |
| 8 | Add vertical spacing below Layer 3 | Claude Code |
| 9 | Add `aria-label` to social icon links if missing | Claude Code |
| 10 | Mobile adjustments — stack, hide centre divider, left-align copyright | Claude Code |
| 11 | Test on desktop and mobile (including iPhone SE3) | Rob |

---

## Testing

1. **Layer 1 — desktop:** Main menu displays in 2 columns, 3 rows. Vertical divider between columns. Centre vertical divider separates menu from social icons. Social icons right-aligned.
2. **Layer 1 — mobile:** Menu in 2 columns with divider. Social icons below the menu. No centre divider.
3. **Layer 1 — divider height:** Vertical dividers between menu columns are taller than the text rows. Text is vertically centred against the divider.
4. **Layer 2 — legal links:** Five links displayed inline with spacing between them. No dividers. Links are white, pink on hover. Horizontal lines above and below with equal spacing.
5. **Layer 2 — mobile:** Links wrap to multiple lines if needed. Spacing between items provides separation.
7. **Layer 3 — desktop:** Open source text + icons left-aligned. Copyright right-aligned.
8. **Layer 3 — mobile:** Both sections left-aligned, stacked vertically.
9. **Layer 3 — bottom spacing:** Adequate vertical padding below the copyright on both desktop and mobile.
10. **Horizontal scrollbar — desktop:** No horizontal scrollbar present at any standard desktop width (1024px, 1280px, 1440px, 1920px).
11. **Horizontal scrollbar — mobile:** No horizontal scrollbar on mobile widths.
12. **Footer alignment:** Footer content aligns with the page's max-width (1200px) and horizontal padding, consistent with the header and main content.
13. **Social icon accessibility:** Each social icon link has a descriptive `aria-label`.
14. **Legal menu links work:** Each link in Layer 2 navigates to the correct page.
15. **Hover states:** Menu links, legal links, and social icons all show pink hover on the solent blue background.

---

## Files to create or modify

```
M  web/themes/custom/customsolent/templates/ (footer template — Claude Code to identify)
     — restructure into 3 layers
     — add footer legal menu rendering
     — add dividers

M  web/themes/custom/customsolent/css/footer.css (or equivalent)
     — 3-layer layout
     — divider styles
     — legal links inline with dividers
     — mobile adjustments
     — bottom spacing

M  web/themes/custom/customsolent/css/ (global CSS — for scrollbar fix)
     — fix the element causing horizontal overflow

M  web/themes/custom/customsolent/customsolent.theme (if preprocess needed for legal menu)
```
