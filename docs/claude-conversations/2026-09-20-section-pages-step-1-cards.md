# Section pages, step 1 — shared organisation / link cards, "Event info" buttons

**Date:** 2026-09-20
**Branch:** `section-pages-listings`
**Brief:** `2026-09-20-section-pages-listings-brief.md` (approved by Rob the same day; his answers
to the four open questions are recorded in it)
**Model:** Claude Fable 5.1 via Claude Code

---

## What was built

| File | Role |
|---|---|
| `config/sync/core.entity_view_display.node.organisation.compact.yml`, `…node.link.compact.yml` | Compact (card) view mode for both types; only the standfirst exposed |
| `config/sync/views.view.organisations_listing.yml`, `views.view.links_listing.yml` | Row view mode teaser → compact on every display (4 and 3). Display machine names untouched |
| `config/sync/classy_paragraphs…directory_compact_grid.yml` | "Organisations And Links Compact Grid" → `slnt-directory-compact-grid` |
| `templates/content/node--organisation--compact.html.twig` | The card. `node--link--compact.html.twig` simply includes it |
| `customsolent.theme` | `field_url` extraction for the compact mode, and `card_type_label` |
| `css/directory-compact.css` | Grid 3 / 2 / 1 at ≥1000 / 600–999 / <600px; card styling |
| `templates/content/node--event--teaser.html.twig` | Button text fixed to "Event info" |
| `scripts/apply_directory_card_grid.php` | Sets the grid style on every View Display paragraph pointing at either view — 86 locally |
| `scripts/release-2026-09-20-directory-cards.sh` | Production sequence |

### The card

White box, 1px solent-blue top rule — the front-page event card family. On section pages the
background is `#FAF9F7`, where a white box alone is invisible, so the card also has a hairline
outline drawn with `box-shadow` (no effect on layout). Title links to `field_url` in a new tab
with the external icon and hidden "(opens in a new tab)" text, or to the node when there is no
URL. Standfirst clamped to three lines. Footer pinned to the bottom of the card: a type label
(ORGANISATION / LINK) and the existing page-aware topic kicker ("from Sectors / Education"), same
size as the label. Stretched-link pattern: whole card clickable, accessible name is just the
title, kicker links raised above it and independently clickable (verified by hit-testing).

One column on phones rather than two: two 140px columns of standfirst text would be unreadable.
When the topic filter sidebar is beside the list, two columns instead of three. The pager and any
empty text span the full grid width.

### Bugs found and fixed while verifying

- **Double-escaped text.** First pass printed `content.field_standfirst|render|striptags`, which is
  already-escaped text that Twig escapes again, so `&amp;`, `&quot;` and `&#039;` showed literally
  on the page. Now prints the raw field value, escaped once. (The article card's summary is built
  in preprocess from the raw value, so it never had this problem.)
- Kicker text was larger than the type label beside it; both now 0.8rem.

### "Event info"

`node--event--teaser.html.twig` printed each event's own link title, so buttons varied in width.
Now every button reads "Event info" with the event title as visually-hidden text — announced as
"Event info: Someday, Sky (Live), HTHR DJ + Guests". Measured on `/culture/music`: all buttons
159px wide.

## Correction carried into the brief

I had told Rob that the "primary and related" display renders two lists with two pagers and called
merging them "the one hard part" of step 2. Wrong: that display is already one query — the
`views_contextual_filters_or` module is installed and the display sets `contextual_filters_or:
true`, `distinct: true`. The stale claim came from a doc comment in `customsolent.theme` that I
repeated without checking the config. The brief now says so. Step 2 is simpler as a result. The
shared `?page=` counter across *separate* listings on one page is real and unchanged.

## Verified (Playwright + curl, local)

| Check | Result |
|---|---|
| `/culture/music` columns at 1280 / 375 | 3 / 1 |
| Card body click hits the title link; kicker link hits the kicker | yes / yes |
| Standfirsts still showing escaped entities | 0 of 9 |
| Pager spans full grid width | yes |
| `/explore/organisations`, `/explore/directories-networks`, `/explore/data`, `/sectors/technology` | 200, cards rendered, no old teaser markup left |
| Link cards labelled LINK | yes (`/explore/data`, `/sectors/technology`) |
| Horizontal scroll | none |
| Script second run | "Listings given the card grid: 0. Already had it: 86." |
| `config:status` | clean |

Pagination on section pages is **unchanged** by this step — that is step 2.

## For Rob

- Many organisations have no standfirst, so their cards are just a title and a label. The cards
  make that more visible than the old list did.
- The grid style is applied by script to existing listings. A **new** organisations or links
  listing added by hand needs "Organisations And Links Compact Grid" chosen in its classy field,
  or its cards stack one per row. Step 2's preview mode is a natural place to make that automatic.

## Production deploy

```bash
git pull origin main
composer install --no-dev --optimize-autoloader
bash scripts/release-2026-09-20-directory-cards.sh
```

DDEV snapshot `pre-directory-cards-20260920` was taken before applying locally.

---

## Follow-up — cards styled like the front page; off-white on Composite Pages

Rob: keep the top line, drop the thin lines on the other sides, white card, off-white page as on
the front page.

- Card: the hairline outline (a `box-shadow`) is gone. 1px solent-blue top rule only, white fill.
- **Off-white page background on every Composite Page** — all section pages and the front page.
  `customsolent_preprocess_html()` now adds a body class per content type
  (`page-node-type-composite-page`), and `css/elements.css` re-points `--body-bg` to the warm-grey
  on that class. Re-pointing the variable also recolours things that paint themselves in the page
  colour, such as the hero's cut-out title box, so nothing is left looking pasted on.
- **Deliberately not site-wide.** `--warm-grey` is also a *fill* elsewhere — the desktop submenu
  strip, the search boxes, a panel on full node pages — and those would vanish into a warm-grey
  page. Article, event and search pages keep `#FAF9F7`.
- **Found on the way:** at desktop widths `menu-desktop.css` paints `<main>` opaque white (it has
  to be opaque — the submenu slides out from behind it). So the content area had been pure white
  on desktop all along, and `#FAF9F7` only ever showed on phones. `<main>` stays opaque but takes
  the page colour on Composite Pages.
- New token `--rule-soft: #ddd8d0`. Two hairlines on section pages used `--warm-grey` as their
  colour (article teaser separators, the filter sidebar's child indent). That is 1.05:1 on
  `#FAF9F7` — effectively invisible already — and would be fully invisible now.

CSS and one preprocess hook; no config or content change, so the release script is unchanged.
