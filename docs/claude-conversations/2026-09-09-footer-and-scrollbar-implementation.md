# Footer Redesign & Horizontal Scrollbar Fix — Implementation

**Date:** 2026-09-09
**Brief:** `2026-09-09-footer-and-scrollbar-brief.md`
**Branch:** `footer-redesign-and-scrollbar-fix`

## Part 1: Horizontal scrollbar fix

### Diagnosis

Measured in the browser at 1280px: `document.documentElement.scrollWidth` was
1273 vs `clientWidth` 1265 — an 8px overflow with **no element** whose bounding
rect exceeded the viewport. The culprits were **pseudo-elements**, which don't
show up in an element sweep:

- `menu-desktop.css` — `.sub-menu-container::before` (submenu full-bleed
  warm-grey background): `width: 100vw; left: 50%; transform: translateX(-50%)`
- `search.css` — `#search-form-container.visible-2l::before`: same pattern

`100vw` includes the vertical scrollbar width (15px in Chrome), so a centred
100vw element pokes ~7.5px past each viewport edge; the right-hand excess
creates a permanent horizontal scrollbar. 1265 + 7.5 ≈ 1273 — exactly the
measured overflow.

### Fix

Both `.sub-menu-container` and `#search-form-container` are already
`width: 100%` full-viewport-width absolute elements, so the pseudo-element
doesn't need `100vw` at all:

```css
width: 100%;
left: 0;
/* transform removed */
```

Verified: overflow is now 0 at 375 / 1024 / 1280 / 1440 / 1920 px, and the
submenu + search reveals still paint edge-to-edge (background width exactly
matches the container). The JS `clip-path: inset(... -100vw ...)` insets are
negative (anti-clipping) and unaffected.

## Part 2: Footer three-layer redesign

### Approach

Rob preferred a **block + paragraph** over hard-coding the legal menu in a
template — and this is what was built. The footer is now three blocks in the
`footer` region:

| Weight | Block | Layer |
|--------|-------|-------|
| -7 | Footer menu and social (existing) | 1 — main nav + social icons |
| -6 | **Footer legal (new)** | 2 — legal/policy links |
| -5 | Footer copyright and open source (existing, weight was -6) | 3 — credits + copyright |

The new block is a `composite_block_type` block containing an **enclosure**
paragraph (classy style: *Footer Legal*) holding a **menu** paragraph with
`field_menu_name = footer-legal` and `field_menu_aria_label = "Legal and
policies"`. The existing `customsolent_preprocess_paragraph__menu()` renders it
— no new preprocess or template needed.

### Config changes (`config/sync/`)

- `field.storage.paragraph.field_menu_name.yml` — added allowed value
  `footer-legal` / "Footer legal" (Rob had already created + exported the menu
  itself in commit 524389a).
- `classy_paragraphs.classy_paragraphs_style.footer_legal.yml` — **new** style,
  class `classy_footer_legal` (selectable on enclosures via Style field).
- `block.block.customsolent_footerlegal.yml` — **new** placement, footer region
  weight -6.
- `block.block.customsolent_footercopyrightandopensource.yml` — weight -6 → -5.
- `structure_sync.data.yml` — added the Footer legal block entry (hand-added;
  a full `drush eb` + `cex` also swept in four previously-unexported blocks —
  "Content to follow", "Content to appear here", "known bugs", "Search page
  hero banner" — which was reverted to keep this change minimal. **Rob:** worth
  deciding separately whether those should be exported.)

### Content creation script

`scripts/create_footer_legal_block.php` (idempotent, run via
`drush php:script`) creates the menu paragraph, enclosure and block content
with the **fixed uuid** `9c1f6a2e-4b7d-4b9a-9f3e-2c8a51d0f7b1` that the block
placement config references. Already run locally. **On prod:** run this script
before `cim` (or let content sync carry the entities, if it covers blocks and
paragraphs) so the placement finds its block.

### CSS (`footer-end.css`)

**Layer 1** (`classy_footer_menu`):
- Menu columns divider: `::before` on `.paragraph-menu__nav` at `left: 50%`,
  1px `rgba(255,255,255,0.3)`, extending 0.6rem above/below the text
  (taller-than-text per brief). `column-gap: 3rem` centres it in the gap;
  nav `max-width: 30rem` keeps the columns together.
- Desktop (≥800px): `.slnt-content-2-column` switched to flex (overriding the
  generic float layout at higher specificity). Left column flexes; right
  column (social icons) is auto-width with `border-left` as the full-height
  centre divider, `align-items: center` both sides so content is vertically
  centred against it. On mobile the flex rules don't apply, so nav and icons
  stack and the centre divider disappears — no extra mobile rules needed.
- Menu link hover: white → `var(--pink-hover)`.

**Layer 2** (`classy_footer_legal`, new section):
- `border-top`/`border-bottom` 1px `rgba(255,255,255,0.2)` on the enclosure —
  full content-area width.
- `ul`: `columns: auto` (cancels the generic `.paragraph-menu ul` column
  count), flex + wrap, centred on desktop, left-aligned on mobile; symmetric
  `1.2rem` vertical padding; horizontal padding `var(--content-pad-x)` so the
  first link left-aligns with the menu above on mobile (verified: all three
  layers' text starts at the same x).
- Links: white, no underline, 0.82rem / 400, `0.3em 1em` padding (mobile:
  right-padding only), pink hover.

**Layer 3** (`classy_footer_copyright_open_source`):
- Open-source text + GitHub/Drupal icons now inline (flex on the
  section-1-column field items).
- Mobile: copyright flex `justify-content: flex-start` (was flex-end, which
  kept it right-aligned on mobile).
- Existing 2em bottom padding retained.

**Icons:** pink fill on hover (`footer a:hover … svg *`) — safe because all
five footer icons are monochrome white SVGs.

### Accessibility

`paragraph--icon-with-link.html.twig` now emits
`aria-label="{{ paragraph.field_link.0.title }}"` when the link field has a
title. All five footer icon links already had titles in content, e.g.
"TheSolentMetropolitan LinkedIn". The Drupal icon's title is just "Drupal" —
worth improving in admin (e.g. "The Solent Metropolitan on drupal.org").

### Verified

- No horizontal scrollbar at 375 / 1024 / 1280 / 1440 / 1920 px, including the
  search results page and with submenu/search reveals open.
- Desktop: 2-col menu + centred divider, full-height centre divider, icons
  right; legal links centred between full-width lines; open-source left /
  copyright right.
- Mobile (375px): menu columns + divider, icons below left-aligned, no centre
  divider; legal links wrap to two lines left-aligned; layer 3 stacked
  left-aligned; consistent left edge across all layers.
- All five icon links have aria-labels; legal links are 0.82rem white with
  pink hover.

### Remaining for Rob

- Test on real devices (iPhone SE3 per brief checklist).
- Deploy: `cim` will apply the config; run
  `scripts/create_footer_legal_block.php` on prod **before** `cim` so the
  block placement's content dependency exists (or confirm content sync covers
  block_content + paragraphs).
- Optionally improve the Drupal icon link title, and decide whether the four
  never-exported blocks should go into structure_sync.
- Menu item URLs: the brief listed `/about/contact-us` and `/about/terms-use`;
  the exported menu uses `/about/contact` and `/about/terms` — confirm those
  paths are the intended ones.
