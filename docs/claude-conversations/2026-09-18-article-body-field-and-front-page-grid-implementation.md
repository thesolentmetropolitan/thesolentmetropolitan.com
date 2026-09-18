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
