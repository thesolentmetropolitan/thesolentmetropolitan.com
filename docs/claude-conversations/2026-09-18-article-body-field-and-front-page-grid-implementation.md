# Front-page article grid — implementation log

**Date:** 2026-09-18 (evening)
**Branch:** `article-body-field-and-front-page-grid`
**Brief:** `2026-09-18-article-body-field-and-front-page-grid-brief.md` (Part 3)
**Model:** Claude Fable 5.1 via Claude Code, picking up from Claude CoWork

---

## Starting state

Parts 1 and 2 of the brief (body column flush left; `body` → `field_body`) had been
committed on the branch since the brief was written (`f2e1407`, `e86cca5`, `e33939f`).
Working tree was clean. Part 3 — the front-page grid — was the remaining work.

## What was built

Mirrors the events compact grid file-for-file:

| File | Role |
|---|---|
| `config/sync/core.entity_view_display.node.article.compact.yml` | Compact view mode for Article — only `field_standfirst` exposed |
| `config/sync/classy_paragraphs.classy_paragraphs_style.articles_compact_grid.yml` | Editor-pickable class `slnt-articles-compact-grid` |
| `config/sync/views.view.articles_listing.yml` | New block display `view_display_front_page`: Compact row style, 8 items, no footer |
| `templates/content/node--article--compact.html.twig` | Card: kicker outside the link, whole card clickable, title + summary |
| `customsolent.theme` | `_customsolent_preprocess_article_compact_extras()` — section colour + `card_summary` |
| `css/article-compact.css` | 4 / 2 / 1 column grid at ≥1000 / 500–999 / <500px; card styling |
| `customsolent.libraries.yml` | Loads the new CSS next to `event-compact.css` |
| `scripts/add_front_page_articles_grid.php` | Adds the heading / grid / CTA paragraphs to the Home node (content, not config) |

Config imported locally with `cex --diff` reporting no drift.

### Deviations from the brief, and why

1. **`field_body` is hidden in the compact display, not exposed.** The summary fallback is
   computed in preprocess straight from the entity, so exposing the field would render every
   article body in full (text filters, media embeds) only to discard it.

2. **The grid is not in `section_2_column` 276.** The brief said to add the heading / grid / CTA
   trio alongside the events trio, but that trio lives in the *right-hand 50% column* of a
   two-column section. Four columns in half a container gives ~140px cards. Instead the script
   appends a new **enclosure** (`field_padding: 1em`) to the parent enclosure 507, directly
   after the two-column section, so the band runs the full 1200px width and aligns with the
   welcome copy above (measured 49px vs 52px left edge at 1280 wide; 32 vs 35 at 400 wide).
   The result on the local DB: enclosure 711 → heading 708, view_display 709, call_to_action 710.

3. **Body-fallback text swaps tags for spaces before stripping.** Plain `strip_tags()` joined
   words across `</p><p>` boundaries ("labourWith"). The helper now replaces each tag with a
   space, decodes entities, collapses whitespace, then truncates at 120 chars on a word boundary.

### A layout bug found and fixed on the way

The first render squeezed the grid to half width. Cause: `.slnt-content-2-column` never
contained its floated columns. The enclosure that holds it uses `overflow: auto` (a block
formatting context), and so does the new enclosure that follows it — a BFC placed after
uncontained floats sits *beside* them, not below, at whatever width is left.

Fix: `display: flow-root` on `.slnt-content-2-column` and `.slnt-content-3-column`, so each
section contains its own floats. Checked every multi-column section on the site for following
siblings first: only the Home node has any (this new band, and enclosure 520 after the
three-column CTA row, which already used a flex override). Footer sections have no siblings.
Desktop before/after screenshots of the footer are identical.

Also added `overflow-wrap: anywhere` to card titles and summaries — one article's body begins
with a long Guardian URL which otherwise overflowed its card.

## Verification (Playwright against DDEV)

| Check | Result |
|---|---|
| Grid columns at 1280 / 800 / 400px | 4 / 2 / 1 |
| Card widths at 1280px | 282px each, spanning 49→1217px |
| Horizontal scrollbar at any width | none |
| Cards rendered | 7 (only 7 published articles; cap is 8) |
| Summaries present | 7 of 7 — standfirst on 3, body fallback on 4 |
| Section colour on top border | purple for Culture, blue for Sectors; grey fallback where no primary topic |
| Kicker links independent of card link | yes — trail is outside the `<a>` |
| Keyboard focus | `:focus-visible` matches, 3px pink outline; Enter follows native anchor |
| Full article page (`/articles/we-dont-need-provincial-rivalry`) | title, standfirst, body all at x=49; body 740px wide, margin 0 |
| `/explore/articles`, `/rss.xml` | HTTP 200 |
| Watchdog | no PHP errors; only the usual local SMTP failures |

## Things for Rob

- **Heading text and CTA wording** are placeholders: "Latest articles" / "See all articles".
  Edit in the paragraph editor on the Home node as with any other content.
- **`/explore/articles` (node 92) has no listing.** Its paragraph tree is hero + one text
  paragraph. The events page has a `section_filter` plus a `view_display`; the articles page
  has neither. The new "See all articles" CTA lands on an empty page. Pre-existing, not caused
  by this work, but worth doing next — `articles_listing` has no
  `view_display_primary_and_related` display yet, so it isn't a pure content change.
- **Four articles have no standfirst**, so their cards show the body fallback. Two of those
  bodies open with a bare URL ("Source: https://…"). Writing standfirsts fixes the cards.
- **`promote`** is untouched; the display sorts by `created` desc, per the brief.

## Production deploy

Config is deployed the usual way. The front-page paragraphs are content, so after
`config:import` run once on the server:

```bash
drush php:script scripts/add_front_page_articles_grid.php -- --dry-run   # inspect
drush php:script scripts/add_front_page_articles_grid.php
drush cr
```

The script finds the Home node from `system.site` and anchors against the existing events grid,
so it does not depend on paragraph IDs matching between environments. It refuses to run twice.

This sits on top of the Part 2 deploy sequence in the brief (maintenance mode, DB backup,
`cim`, body migration script, `cr`). Both scripts can run in the same window.

---

## Follow-up, 2026-09-19 — article cards restyled

Rob's feedback on the first pass: copying the events grid was what was asked for, but the two
grids now look the same and need visual variety. Also a question about whether a topic kicker
*before* the title is an accessibility problem, and — if the kicker moves to the end — whether
the top rule then reads ambiguously as the end of one item or the start of the next.

### What changed (article cards only; event cards untouched)

- **Filled cards.** Each card is a section-coloured rectangle with white text, bottom-justified.
  No top rule — the rectangle itself marks where an item starts and ends, which removes the
  ambiguity Rob raised.
- **Topic tile band.** The top of each card shows the hero tile SVG for the article's own topic
  (`images/hero-tiles/<section>_<topic>.svg`), fading into the solid card colour. Preprocess picks
  the tile: topic → section "view all" tile → `explore_articles`. Topic term names carry their
  path ("Culture / Identity"), so the last segment is used for the filename. The tile's 12%
  opacity is baked into the SVG and was too faint at card size, so it is layered twice.
- **Text can never sit on the tile.** `padding-top` equals the fade depth, so a long title grows
  the card rather than climbing into the pattern.
- **Kicker moved to the end** (title → summary → kicker, in the DOM as well as visually), white
  rather than section-coloured, separated by a hairline.
- **Stretched-link pattern.** The `<a>` is now just the title inside the `h3`; its `::after`
  covers the card, so the whole rectangle is clickable while the accessible link name is only
  the title (previously title + summary). The kicker is raised above it so topic links still work.
- **Focus ring** goes round the whole card: 3px `#1a1a1a` with a 2px gap — 16:1 on the page
  background. (The events card's pink ring is about 1.7:1 on off-white; worth revisiting there.)

### Contrast (white text, WCAG AA needs 4.5:1)

| Section | Base colour | White on base | Card colour used | White on card | White over a tile icon |
|---|---|---|---|---|---|
| Culture | `#7C3AED` | 5.70 | `#6D28D9` | 7.10 | 5.62 |
| Sectors | `#2563EB` | 5.17 | `#1D4ED8` | 6.70 | 5.25 |
| Living | `#059669` | **3.77 — fails** | `#065F46` | 7.68 | 5.77 |
| Explore | `#D97706` | **3.19 — fails** | `#92400E` | 7.09 | 5.42 |
| About | `#475569` | 7.58 | `#475569` | 7.58 | 5.57 |
| no topic | — | — | `#2C4F6E` | 8.57 | 6.21 |

Card colours are the darker stops already used in each section's hero gradient, so they belong
to the palette. "Over a tile icon" is a worst case that the layout prevents anyway. Living needed
the 800-weight green; the 700 (`#047857`) passes on solid (5.48) but not over an icon (4.37).

### On the kicker-before-title question

The real issue is heading navigation. A screen-reader user who jumps heading to heading lands
on the card's `h3` and reads onward; anything placed *before* that heading is heard as the tail
of the previous card. So in a list of cards, topic-after-title is the better order, which is why
news sites do it. Moving it visually with CSS while leaving it first in the DOM would not help
and would put tab order out of step with visual order.

On a full article page the argument is much weaker: there is one `h1`, the kicker is a labelled
`<nav aria-label="Topic breadcrumb">` landmark, and a breadcrumb before the `h1` is the
long-established convention. No change recommended there.

Event cards still have the kicker first and a top rule. If the kicker moves to the end there,
the top rule still works provided the gap between cards is clearly larger than the gap inside
a card; or the events card could take a full hairline border. Not done — Rob's call.

### Verified

4 / 2 / 1 columns at 1280 / 800 / 400px, no horizontal scroll; tiles resolved to
`culture_identity`, `sectors_design`, and `explore_articles` for the article with no topic;
click on the tile band hits the card link, click on the kicker hits the topic link; focus ring
renders round the card.

---

## Follow-up 2, 2026-09-19 — scan line, event cards, type sizes, section colours

### Article cards
- **Titles start at the same height on every card** (96px down, about a third), directly under
  the tile band. Text runs downward; the kicker is pinned to the bottom edge with `margin-top:
  auto`. Previously text was bottom-justified, so the eye had to hunt up and down a row.
- Summary 1rem (was 0.85), title 1rem, kicker 0.8rem (was 0.72).
- Explore card colour moved to `#9A3412` to stay in family with the new Explore base.

### Event cards
- **Kicker moved to the end** in the DOM, still outside the link. Top rule kept; the grid's row
  gap is now 1.8rem against ≤0.4rem inside a card, so the rule reads as the start of the card
  below it.
- Title, date and location rows all 1rem; the parent location ("Southampton") is the same size
  and **solent-blue instead of `#888`** (3.4:1 on the page background, failed AA; now 8.2:1).
  Kicker 0.8rem (was 0.75).
- **Two columns on ordinary phones.** The one-column breakpoint was 499px, but phones are
  360–430 CSS px wide (iPhone SE3 375, Galaxy Note 10+ and A52 412), so they all got one column.
  Breakpoint is now 339px. To make two columns workable at that width: card side padding pulled
  in below 500px, and the 1.2rem side padding `.slnt-section-listing` adds at ≤799px is dropped
  for both compact grids — it was insetting the grids from their headings and costing 38px.
  At 375px: two 138px columns, grid edges identical to the heading's, nothing overflowing.

### Section colours — Living and Explore deepened site-wide

| | Old | White on it | As text on page | New | White on it | As text on page |
|---|---|---|---|---|---|---|
| Living | `#059669` | 3.77 | 3.58 | `#047857` | 5.48 | 5.21 |
| Explore | `#D97706` | 3.19 | 3.03 | `#BC4A08` | 5.10 | 4.84 |

Living stays emerald, one stop deeper — still a plant green. For Explore, amber-700 (`#B45309`)
passes but reads brown, which is the "dirty yellow" risk; orange-700 (`#C2410C`) is clean but
drifts toward red and the brand magenta. `#BC4A08` sits between: a clean burnt orange. Going
orange-ward does help — at equal saturation orange is less luminous than yellow, so it reaches
4.5:1 with less darkening.

Changed in: `_customsolent_topic_section_colors()` (kickers, top rules), `section-listing.css`,
`heading-gradient.css` end stops, the Living/Explore terms in the `color` vocabulary (so the
"Discover Living" button is now AA with its white label), and `structure_sync.data.yml`.
**Not changed:** the hero banner gradients. Their titles sit in a white cut-out box, so there is
no contrast problem, and the Living heroes already contain `#047857`. The Explore heroes are
still an amber sweep; if the new orange is kept they would want re-tinting to match.

### Focus ring on event cards — suggestion, not applied
`#f5b0d8` was chosen for the solent-blue menu bar (5.0:1 there). On the off-white page it is
about 1.7:1; WCAG 2.4.11 wants 3:1 for a focus indicator. Suggested: `outline: 3px solid
var(--text)` with `outline-offset: 2px` — the same ring the article cards now use (16:1).
Solent-blue (8.2:1) would also pass if a brand colour is preferred.

### Production deploy — one more script
After `cim`, alongside the other two scripts:

```bash
drush php:script scripts/update_section_colour_terms.php -- --dry-run
drush php:script scripts/update_section_colour_terms.php
```

Idempotent; matches terms by vocabulary + name rather than tid.
