# The Solent Metropolitan — Section CTAs Brief

## Overview

Style the CTA button paragraph and the 3-column paragraph so they work together as a "Solent DNA" section on the front page (Culture / Sectors / Living buttons), while remaining reusable in other contexts. No changes to the enclosure paragraph template or CSS.

---

## Current structure

The DOM nesting is:

```
Enclosure (paragraph--type--enclosure)
  └── 3-Column (paragraph--type--section-3-column)
        ├── .slnt-column-left → CTA paragraph (Culture)
        ├── .slnt-column-middle → CTA paragraph (Sectors)
        └── .slnt-column-right → CTA paragraph (Living)
```

The enclosure provides the background colour and horizontal padding/centering.
The 3-column provides the grid layout.
Each CTA is an independent paragraph with its own link, text colour, and background colour set by the editor via the Color taxonomy.

---

## Template files

- Enclosure: `web/themes/custom/customsolent/templates/paragraphs/paragraph--enclosure.html.twig` — **no changes needed**
- 3-Column: `web/themes/custom/customsolent/templates/paragraphs/paragraph--section-3-column.html.twig` — minor adjustment needed
- CTA: `web/themes/custom/customsolent/templates/paragraphs/paragraph--call-to-action.html.twig` — adjustments needed

---

## Step 1: CTA paragraph template adjustments

The current CTA template (paragraph--call-to-action.html.twig) has a few issues to fix:

### Current code (line 51):
```twig
<div{{ attributes.addClass(classes).addClass('slnt-cta-outer') }}">
```
There's an extra stray `"` after the closing `}}` — remove it.

### Add border colour support

The CTA currently supports `field_color_text` and `field_color_background`. For the border (3px), the template needs to handle a border colour. Two approaches:

**Option A (simpler — derive border from background):**
Use the background colour as the border colour too. This works for the section CTAs where the border and fill are the same colour.

**Option B (more flexible — add a field):**
If you want independent border colour control, add a `field_color_border` entity reference field to the CTA paragraph type. But for now, Option A is probably sufficient.

### Add the SVG chevron

The CTA needs the right-pointing chevron using the shared `#tvip-down-triangle` SVG symbol. Add it after the link title text.

### Updated template:

```twig
{%
  set classes = [
    'paragraph',
    'paragraph--type--' ~ paragraph.bundle|clean_class,
    view_mode ? 'paragraph--view-mode--' ~ view_mode|clean_class,
    not paragraph.isPublished() ? 'paragraph--unpublished'
  ]
%}

{% set text_color = paragraph.field_color_text.0.entity.field_color.value.0.color|default('inherit') %}
{% set bg_color = paragraph.field_color_background.0.entity.field_color.value.0.color|default('transparent') %}

{% block paragraph %}
  <div{{ attributes.addClass(classes).addClass('slnt-cta-outer') }}>
    <a href="{{ paragraph.field_link.0.url }}" class="slnt-cta"
       style="color: {{ text_color }}; background-color: {{ bg_color }}; border-color: {{ bg_color }};">
      {% block content %}
        <span class="slnt-cta__text">{{ paragraph.field_link.0.title }}</span>
        <svg class="slnt-cta__chevron" role="presentation" focusable="false">
          <use href="#tvip-down-triangle"></use>
        </svg>
      {% endblock %}
    </a>
  </div>
{% endblock paragraph %}
```

**Key changes from current template:**
1. Removed the stray `"` on the outer div
2. Moved the `<a>` to be the styled element (not a nested `<span>`) — the link itself is the button. This is better for accessibility: the entire clickable area is the `<a>`, with proper focus/hover states.
3. Added `border-color` inline style derived from the background colour
4. Added the SVG chevron using the shared `#tvip-down-triangle` symbol
5. Wrapped the title text in a `<span class="slnt-cta__text">` for fine-grained styling if needed

---

## Step 2: CTA CSS

Add these styles to `css/cta.css` (which already exists in your libraries.yml):

```css
/* ══════════════════════════════════════
   CTA Button Styles
   ══════════════════════════════════════ */

/* Outer wrapper — reset any inherited link styles */
.slnt-cta-outer {
  display: block;
}

/* The button itself (the <a> element) */
.slnt-cta {
  display: inline-flex;
  align-items: center;
  gap: 0.5em;
  font-family: 'Atkinson Hyperlegible Next', sans-serif;
  font-size: 1rem;
  font-weight: 700;
  padding: 0.75em 1.5em;
  border: 3px solid transparent;  /* colour set inline from taxonomy */
  border-radius: 4px;
  text-decoration: none;
  cursor: pointer;
  transition: background-color 0.2s ease, color 0.2s ease, border-color 0.2s ease, opacity 0.2s ease;
  line-height: 1.2;
}

/* Override body link styles — CTAs must NOT inherit magenta underline */
.layout-content .slnt-cta,
.layout-content .slnt-cta:hover {
  text-decoration: none;
  text-decoration-color: transparent;
}

/* Hover: subtle darkening */
.slnt-cta:hover {
  opacity: 0.88;
}

/* Focus state for keyboard navigation */
.slnt-cta:focus-visible {
  outline: 3px solid #f5b0d8;
  outline-offset: 2px;
}

/* Chevron — rotated version of menu down-triangle */
.slnt-cta__chevron {
  width: 0.75em;
  height: 0.75em;
  transform: rotate(-90deg);
  transition: transform 0.2s ease;
  flex-shrink: 0;
  fill: currentColor;
}

.slnt-cta:hover .slnt-cta__chevron {
  transform: rotate(-90deg) translateY(-3px);
}

/* ── Mobile adjustments ── */
@media (max-width: 799px) {
  .slnt-cta {
    font-size: 0.9rem;
    padding: 0.65em 1.2em;
  }
}
```

**Note:** The colours are set inline via the Twig template from the Color taxonomy, so the CSS doesn't define specific section colours. The hover uses `opacity: 0.88` as a universal darkening effect that works with any background colour. If you want more control over hover states per colour (e.g. Culture Mid → Culture Dark on hover), that would require either additional CSS classes or JavaScript — but opacity is a clean, universal approach for now.

---

## Step 3: 3-Column paragraph — add classy class support

The 3-column template currently doesn't output the classy paragraphs field. Check if the `field_classy` value is available on the paragraph and add it to the outer div's classes. 

### Check the field:
The classy paragraphs module typically adds a class to the paragraph's attributes automatically. Verify by enabling Twig debug and inspecting the rendered HTML. If the class is already being output by the module on the `attributes` variable, no template change is needed.

If it's NOT being output automatically, update the template to include it:

```twig
{% set classy_class = paragraph.field_classy.value|default('') %}

{% block paragraph %}
  <div{{ attributes.addClass(classes).addClass(classy_class) }}>
    <div class="slnt-encl">
      {% block content %}
        <div class="slnt-content-3-column">
          <div class="slnt-column-left">
              {{ content.field_content_component_left }}
          </div>
          <div class="slnt-column-middle">
              {{ content.field_content_component_middle }}
          </div>
          <div class="slnt-column-right">
              {{ content.field_content_component_right }}
          </div>
        </div>
      {% endblock %}
    </div>
  </div>
{% endblock paragraph %}
```

---

## Step 4: 3-Column CSS for CTA context

Add to `css/paragraph-section-3-column.css`. The CTA-specific layout uses the classy class `slnt-3col-cta` so it doesn't affect other uses of the 3-column paragraph.

### Layout approach

For the CTA use case, the three 33% float columns are **not ideal** — they'd space the buttons too far apart on wide viewports. Instead, when the `slnt-3col-cta` class is present, override the float layout with a centred flex row where the buttons have a fixed width, equal sizing, and even gaps between them.

```css
/* ══════════════════════════════════════
   3-column CTA layout (classy: slnt-3col-cta)
   ══════════════════════════════════════ */

/* ── Desktop: centred flex row with fixed-width buttons ── */
@media (min-width: 800px) {
  /* Override the float layout for this specific use */
  .slnt-3col-cta .slnt-content-3-column {
    display: flex;
    justify-content: center;
    gap: 1.5rem;
  }

  /* Remove the float and percentage width from columns */
  .slnt-3col-cta .slnt-content-3-column .slnt-column-left,
  .slnt-3col-cta .slnt-content-3-column .slnt-column-middle,
  .slnt-3col-cta .slnt-content-3-column .slnt-column-right {
    float: none;
    width: auto;
    padding-right: 0;
    padding-bottom: 0;
  }

  /* Fixed width on the CTA buttons for uniformity */
  .slnt-3col-cta .slnt-cta {
    min-width: 180px;
    justify-content: center;  /* centres text+chevron within the button */
  }
}

/* ── Mobile: vertical stack, Culture first, then Sectors, then Living ── */
@media (max-width: 799px) {
  .slnt-3col-cta .slnt-content-3-column {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.8rem;
  }

  /* Remove the float and full-width from columns */
  .slnt-3col-cta .slnt-content-3-column .slnt-column-left,
  .slnt-3col-cta .slnt-content-3-column .slnt-column-middle,
  .slnt-3col-cta .slnt-content-3-column .slnt-column-right {
    float: none;
    width: auto;
    clear: none;
    padding-bottom: 0;
  }

  /* On mobile, buttons can be slightly wider for easy tap targets */
  .slnt-3col-cta .slnt-cta {
    min-width: 200px;
    justify-content: center;
  }
}
```

### How this works

**Desktop (800px+):** The three columns sit in a centred flex row with `gap: 1.5rem` between them. Each button has `min-width: 180px` so they all match the width of the longest label. The buttons cluster together in the centre of the 1200px content area rather than spreading across the full width.

**Narrow desktop (800–960px):** At 180px × 3 plus gaps (1.5rem × 2 = 48px) the total is about 588px — fits comfortably into 800px. No wrapping needed.

**Mobile (<800px):** The flex direction switches to `column`, stacking the buttons vertically. The DOM order is left → middle → right, which maps to Culture → Sectors → Living. Each button is centred horizontally with a slightly wider `min-width: 200px` for comfortable tap targets.

### Important

These styles are scoped to `.slnt-3col-cta` (the classy class). **Other uses of the 3-column paragraph are unaffected** — they continue to use the existing float layout. In Drupal, set the classy paragraph value to `slnt-3col-cta` on the 3-column instance that holds the CTAs.

---

## Step 5: Editor setup in Drupal

When creating the front page "Solent DNA" section, the editor will:

1. Create an **Enclosure** paragraph with:
   - Background colour: body bg (#FAF9F7) or white
   - Appropriate padding setting

2. Inside the enclosure, add a **3-Column** paragraph with:
   - Classy: `slnt-3col-cta` (if using the scoped approach)
   - Left column: **CTA** paragraph with:
     - Link: `/culture` with title "Culture"
     - Text colour: **white** (#FFFFFF)
     - Background colour: **Culture** (#7C3AED)
   - Middle column: **CTA** paragraph with:
     - Link: `/sectors` with title "Sectors"
     - Text colour: **white** (#FFFFFF)
     - Background colour: **Sectors** (#2563EB)
   - Right column: **CTA** paragraph with:
     - Link: `/living` with title "Living"
     - Text colour: **white** (#FFFFFF)
     - Background colour: **Living** (#059669)

---

## Step 6: Verify

After clearing cache (`drush cr`), check:

- All three CTAs render as coloured buttons with white text and the right-pointing chevron
- The chevron nudges right on hover
- The buttons are centred within their columns on desktop
- The buttons stack vertically on mobile (<800px)
- The buttons do NOT inherit the magenta underline from `.layout-content a`
- The inline `border-color` matches the `background-color` on each button
- The `#tvip-down-triangle` SVG symbol is present in the page HTML (it's already there for the menu)
- Keyboard focus shows a visible outline (the pink focus ring)
- The 3-column layout still works correctly for other (non-CTA) uses

---

## Notes

- **No enclosure changes needed.** The enclosure template and CSS remain untouched.
- **CTA colours are editor-controlled** via the Color taxonomy. The CSS handles layout, spacing, and interaction — not colour.
- **The SVG chevron** reuses the existing `#tvip-down-triangle` symbol from the menu. No new SVG file needed. The `transform: rotate(-90deg)` in CSS turns the down-arrow into a right-arrow.
- **The opacity hover** (0.88) is a pragmatic universal approach. If specific hover colours are wanted later (e.g. Culture Mid → Culture Dark), add CSS classes per section or extend the Twig template with a hover-colour field. But opacity works well for now.
- **Font sizes** should be in `fonts.css` per the typography brief. Add the CTA font-size there and remove from `cta.css` if consolidating. For now, having it in `cta.css` is fine as a self-contained component.
- **The 3-column `float` layout** (existing in paragraph-section-3-column.css) is overridden for the CTA use case via the `.slnt-3col-cta` classy class. The override switches to flex with centred, fixed-width buttons. Other uses of the 3-column paragraph (text, images, etc.) continue to use the existing float layout untouched.
