# The Solent Metropolitan — Typography & Spacing Consolidation Brief

## Overview

Consolidate all typography (font-size, font-weight, line-height) into `fonts.css` as the single source of truth. Add spacing CSS custom properties to `elements.css`. Switch all font sizes from `em` to `rem` to prevent compounding. Add mobile overrides. Clean up stale breakpoints and remove unused Oswald references.

CSS files are in: `web/themes/custom/customsolent/css/`

---

## 1. Breakpoint policy

The site uses two fundamental breakpoints:

- **Mobile:** `max-width: 799px`
- **Desktop:** `min-width: 800px`

**EXCEPTION — keep these submenu column breakpoints in `menu-desktop.css` (lines 337–355):**
- `min-width: 1200px` → 6 columns
- `max-width: 1199px` → 4 columns

These are layout refinements within the desktop range and should stay in `menu-desktop.css`.

**Remove or update the following stale breakpoints found in other files:**
- `header.css`: 576px, 575px, 1023px — update to 800px
- `paragraph-section-2-column.css`: 1023px, 1024px — update to 800px (columns stack on mobile)
- `paragraph-section-3-column.css`: 1023px, 1024px — update to 800px
- `fonts.css`: 576px, 575px, 479px (Oswald rules) — **delete entirely**, these are unused

---

## 2. Switch font sizes from `em` to `rem`

Every `font-size` declaration currently using `em` must be changed to `rem`. The reason: `em` compounds when nested (1.2em inside 1.2em = 1.44× root), while `rem` always refers to the root `html` font-size.

**Do not change** `padding`, `margin`, or `line-height` values that use `em` — only `font-size`.

---

## 3. Restructure `fonts.css`

Replace the entire contents of `fonts.css` with the following structure. Keep the existing `@font-face` declarations but restructure everything else.

### Font faces
Keep the three existing `@font-face` blocks (Regular 400, Bold 700, Italic 400) exactly as they are.

### Root font size
```css
html {
  font-size: 16px;
}
```

### Base body type
```css
body,
.slnt-text,
.slnt-text > * a {
  font-family: 'Atkinson Hyperlegible Next', sans-serif;
  font-size: 1rem;
  line-height: 1.65;
}
```

### Desktop heading scale (default — applies at all widths, overridden by mobile below)
```css
h1 {
  font-family: 'Atkinson Hyperlegible Next', sans-serif;
  font-size: 2.4rem;
  font-weight: 700;
  line-height: 1.15;
  margin-block: 0;
}

h2 {
  font-family: 'Atkinson Hyperlegible Next', sans-serif;
  font-size: 1.8rem;
  font-weight: 700;
  line-height: 1.25;
  margin-block: 0;
}

h3 {
  font-family: 'Atkinson Hyperlegible Next', sans-serif;
  font-size: 1.35rem;
  font-weight: 700;
  line-height: 1.3;
  margin-block: 0;
}

h4 {
  font-family: 'Atkinson Hyperlegible Next', sans-serif;
  font-size: 1.1rem;
  font-weight: 700;
  margin-block: 0;
}
```

### Mobile heading scale
```css
@media (max-width: 799px) {
  h1 {
    font-size: 1.75rem;
  }
  h2 {
    font-size: 1.4rem;
  }
  h3 {
    font-size: 1.15rem;
  }
  h4 {
    font-size: 1rem;
  }
}
```

### Content heading colours
```css
.layout-content h1,
.layout-content h2,
.layout-content h3 {
  color: var(--solent-blue);
}
```

### Body content links
```css
.layout-content a {
  color: var(--text);
  font-weight: 700;
  text-decoration: underline;
  text-decoration-color: var(--magenta);
  text-underline-offset: 3px;
  text-decoration-thickness: 2px;
  transition: color 0.15s;
}

.layout-content a:hover {
  color: var(--magenta);
}
```

### Contextual type sizes — consolidate from other files

These rules move INTO `fonts.css` FROM the files listed. **Remove the original `font-size`, `font-weight`, and `line-height` declarations from those source files** — but keep all other properties (layout, colour, padding, etc.) in the source files.

**From `paragraph-hero-art-style.css`** (lines 29–30):
```css
.hero-art-style__title {
  font-size: 1.8rem;
  line-height: 1.2;
}

@media (max-width: 799px) {
  .hero-art-style__title {
    font-size: 1.4rem;
  }
}
```

**From `menu-common.css`** (lines 111, 124):
```css
.main_nav_link {
  font-size: 0.85rem;
}

.main-menu-item-container li.main-menu-item-wrapper > ul {
  font-weight: 800;
}
```

**From `menu-mobile.css`** (lines 231, 237, 407–408):
```css
@media (max-width: 799px) {
  .mobile-main-menu-item-text {
    font-size: 1.6rem;
  }

  .mobile-submenu-heading-text {
    font-size: 1.2rem;
  }

  .mobile-menu-icon-bar {
    font-size: 1rem;
    line-height: 1em;
  }
}
```

**From `menu-desktop.css`** (lines 286, 292):
```css
@media (min-width: 800px) {
  .sub-menu-container .first-link:hover,
  .sub-menu-item-container a:hover {
    font-weight: 700;
  }

  .main-menu-item-container > li:has(.sub-menu-container.visible-2l) button.main_nav_link > span {
    font-weight: 700;
  }
}
```

**From `search.css`** — consolidate all search typography:
```css
/* Search page typography */
.search-results-heading {
  font-size: 1.4rem;
  font-weight: 500;
}

.search-result__title {
  font-size: 1.1rem;
}

.search-result__snippet {
  font-size: 0.95rem;
  line-height: 1.5;
}

.search-result__url {
  font-size: 0.85rem;
}

@media (max-width: 799px) {
  .search-results-heading {
    font-size: 1.2rem;
  }
}
```
**Note:** The search.css selectors above are approximations based on what was in the file. Claude Code should check the actual class names used in search.css and map the font-size/font-weight/line-height rules accordingly, then remove those declarations from search.css while keeping layout/colour/padding rules there.

### Small text utility
```css
small, .text-small {
  font-size: 0.85rem;
}
```

### DELETE from `fonts.css`
Remove the entire Oswald section at the bottom (the three `@media` blocks referencing `.page-specific-front h1` and `font-family: "Oswald"`). This is unused.

---

## 4. Add spacing variables to `elements.css`

Add these CSS custom properties inside the existing `:root` block in `elements.css`, after the colour variables:

```css
:root {
  /* ... existing colour variables ... */

  /* Spacing scale */
  --space-xs: 0.5rem;
  --space-sm: 1rem;
  --space-md: 1.5rem;
  --space-lg: 2rem;
  --space-xl: 3rem;

  /* Content padding — used by section/column/enclosure components */
  --content-pad: 2rem;
}

/* Mobile spacing override */
@media (max-width: 799px) {
  :root {
    --content-pad: 1.2rem;
  }
}
```

---

## 5. Replace hardcoded padding with variable

In the following files, replace hardcoded `2em` padding values with `var(--content-pad)`:

**`paragraph-section-1-column.css`:**
```css
.slnt-content-1-column {
  padding: var(--content-pad);
}
```

**`paragraph-section-2-column.css`:**
Replace all `padding-top: 2em`, `padding-left: 2em`, `padding-right: 2em`, `padding-bottom: 2em` with the variable:
```css
.slnt-content-2-column {
  padding-top: var(--content-pad);
  padding-left: var(--content-pad);
  padding-right: var(--content-pad);
}

.slnt-content-2-column .slnt-column-left,
.slnt-content-2-column .slnt-column-right {
  padding-bottom: var(--content-pad);
}
```

**`paragraph-section-3-column.css`:**
Same approach — replace `2em` with `var(--content-pad)`.

**`paragraph-enclosure.css`:**
```css
.field--name-field-page-builder,
.field--name-field-block-builder {
  padding: var(--content-pad);
}
```

**Do NOT replace** the `2em` values in `menu-desktop.css`, `page.css`, or `paragraph-hero-art-style.css` — these use `2em` for alignment with the 1200px content grid, not general content padding, and should remain as they are.

---

## 6. Update stale breakpoints in non-menu files

**`header.css`:**
- Line 17: `min-width: 576px` → `min-width: 800px`
- Line 26: `max-width: 575px` → `max-width: 799px`
- Line 32: `max-width: 1023px` → `max-width: 799px`

**`paragraph-section-2-column.css`:**
- Line 20: `max-width: 1023px` → `max-width: 799px`
- Line 34: `min-width: 1024px` → `min-width: 800px`

**`paragraph-section-3-column.css`:**
- Line 24: `max-width: 1023px` → `max-width: 799px`
- Line 47: `min-width: 1024px` → `min-width: 800px`

---

## 7. Summary of what changes in each file

| File | Action |
|---|---|
| `fonts.css` | **Rewrite.** Full restructure as specified above. Single source of truth for all typography. |
| `elements.css` | **Add** spacing variables to `:root`. Add mobile override media query. |
| `paragraph-section-1-column.css` | **Replace** hardcoded `2em` with `var(--content-pad)`. |
| `paragraph-section-2-column.css` | **Replace** `2em` with `var(--content-pad)`. **Update** breakpoints to 800px. |
| `paragraph-section-3-column.css` | **Replace** `2em` with `var(--content-pad)`. **Update** breakpoints to 800px. |
| `paragraph-enclosure.css` | **Replace** `2em` with `var(--content-pad)`. |
| `header.css` | **Update** breakpoints to 800px/799px. |
| `search.css` | **Remove** font-size, font-weight, line-height declarations (moved to fonts.css). Keep everything else. |
| `menu-common.css` | **Remove** font-size and font-weight declarations (moved to fonts.css). Keep everything else. |
| `menu-mobile.css` | **Remove** font-size and line-height declarations (moved to fonts.css). Keep everything else. |
| `menu-desktop.css` | **Remove** font-weight declarations from hover/active rules (moved to fonts.css). **Keep** the 1200px/1199px column breakpoints unchanged. Keep everything else. |
| `paragraph-hero-art-style.css` | **Remove** font-size and line-height from `.hero-art-style__title` (moved to fonts.css). Keep everything else. |

---

## 8. Important notes

- **Only move `font-size`, `font-weight`, and `line-height` to fonts.css.** All other properties (colour, padding, margin, layout, animation, display, position, etc.) stay in their current files.
- **Use `rem` for all font sizes.** Never `em` for font-size. `em` is fine for padding/margin.
- **Test after changes** by checking: the homepage, a content page with headings, the search results page, the mobile menu, and the desktop submenu — at both mobile (<800px) and desktop (>800px) widths.
- **The `line-height` property** does not need `em` or `rem` — unitless values like `1.65` are preferred (they multiply by the element's own computed font-size, which is the correct behaviour).
- When removing declarations from source files, **check there are no `!important` overrides** that might need adjusting.
- The CSS loading order in `customsolent.libraries.yml` matters — `elements.css` (with variables) and `fonts.css` should load before component files that reference the variables.
