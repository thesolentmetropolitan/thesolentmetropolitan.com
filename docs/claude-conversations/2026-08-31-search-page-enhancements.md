# Search page enhancements — issue #131

Date: 2026-08-31 · Branch: search-page-enhancements

All six items from Rob's brief, verified with Playwright on ddev.

## 1. Left alignment with the "Home" menu item

`body.search-results-page .layout-content` now uses the same 1200px clamp
formula as the menu/hero (`max(var(--align-floor), calc((100% - 1200px) / 2))`),
plus the menu's own `(1em - 12px)` text offset. Measured: Home and headings both
at x=37 (1280w) and x=357 (1920w) — pixel-exact.

## 2. Hero banner (search is not a composite page)

Per Rob's suggested approach — block referencing a hero paragraph:
- New tile `search.svg` (magnifying-glass, binoculars, file-text icons) added to
  the generator (`build_tiles.py` PAGE_MAP) and built with the existing hex-grid
  pipeline; solent-blue gradient class `.hero-art-style--search` appended to
  `hero-art-styles.css`.
- New classy style `hero_art_style_search` (config).
- New custom block "Search page hero banner" (composite_block_type holding a
  hero_with_art_style paragraph, title "Search") — content, exported to
  `content_sync/blocks/…7cce9b5c….yml`.
- Placed via `block.block.customsolent_searchherobanner.yml` in the new
  `highlighted` region (added to customsolent.info.yml — page.html.twig already
  printed it above `.layout-content`), visibility `/search*`.
- `scripts/create-search-hero.php` (idempotent) creates all three — run on prod.

## 3+5. Headings: spacing + solent blue (contrast)

Headings were near-black and cramped; result titles were light blue #029CDC
(≈2.9:1 on white — fails AA). Now: h2s `var(--solent-blue)` with margins;
result title links `var(--solent-blue)` (8.1:1, AAA) with magenta underline on
hover (site link convention). The old CSS targeted a stale block id
(`#block-slnt-content`) — actual id is `#block-customsolent-content`; rules now
scope to `body.search-results-page .layout-content`.

## 4. Pagination — root cause found and fixed

The pager wasn't missing CSS — it never rendered, despite core adding it and
186 matches for "southampton". Root cause (subtle):

1. Search results include composite/landing pages.
2. `SearchNode::prepareResults()` renders each result node → embedded
   view_display paragraphs execute their views.
3. Each view's pager RE-REGISTERS pager element 0, overwriting the search
   query's pager (fingerprint: total 3, limit 5 = last embedded view's rows).
4. `pager.html.twig` then renders empty, and the anonymous page cache freezes
   the pagerless page — warm render caches skip the node renders, so drush
   probes paged fine while the web never did. (Maddening to reproduce.)

Fix: `customsolent_helpers_views_pre_build()` — on route
`search.view_node_search` only, embedded views get a non-paginating `some`
pager at runtime. They only ever render inside result nodes there and never
show pager UI, so nothing is lost; section pages keep their normal pagination.
Prod note: the same bug exists on prod (Rob's screenshot shows 10 results, no
pager) — fixed by deploying this branch; `drush cr` clears the frozen page cache.

Pager styled to match the view-display pager (solent-blue boxes, magenta-dark
active), scoped in search.css. Verified: 12-item pager renders, page 2 loads
different results, active state correct.

## 6. Friendly empty message on /search

`customsolent_preprocess_item_list__search_results()`: when `keys` is absent,
the "Your search yielded no results." message becomes "Please enter search
terms above to search the site." A genuine empty result set keeps the core
message. (/search redirects to /search/node — both covered.)

## Also fixed on the way

- `form--search-form.html.twig` submit icon referenced a long-gone theme
  (`/themes/custom/eela/search-magnifying-glass.svg`, 404 on every search page
  load; icon invisible). Replaced with the inline magnifier SVG used by the
  menu search form. Console now clean.
- Mobile: input + magnifier stay on one line; headings/results align to the
  16px floor.

## Files

- M web/modules/custom/customsolent_helpers/customsolent_helpers.module (pager fix)
- M web/themes/custom/customsolent/customsolent.theme (empty message)
- M web/themes/custom/customsolent/css/search.css (alignment, colours, spacing, pager)
- M web/themes/custom/customsolent/css/hero-art-styles.css (+search style)
- M web/themes/custom/customsolent/customsolent.info.yml (+highlighted region)
- M web/themes/custom/customsolent/templates/search/search-page/form--search-form.html.twig
- M scripts/generate-patterned-hero-banners/build_tiles.py (+search PAGE_MAP entry)
- A web/themes/custom/customsolent/images/hero-tiles/search.svg (+ generator copy)
- A config/sync/block.block.customsolent_searchherobanner.yml
- A config/sync/classy_paragraphs.classy_paragraphs_style.hero_art_style_search.yml
- A scripts/create-search-hero.php
- A/M content_sync/blocks/*.yml (hero block + refreshed exports)

## Production release steps

1. Deploy code; deploy.sh (cim imports classy style + block placement + region;
   content-import.sh imports the hero block content).
2. `drush php:script scripts/create-search-hero.php` — idempotent safety net
   (wires placement to the imported block's uuid if it differs).
3. `drush cr` (also flushes the frozen pagerless page cache).

## Follow-up fixes (same day)

- **Bug: pager params leaked into the search box** — after paging,
  `?keys=music&page=1` showed "music&page=1" in the input. The JS
  (customsolent.js ~line 259) captured everything after `keys=` with a regex.
  Replaced with `new URLSearchParams(window.location.search).get('keys')` —
  handles any extra params, ordering, and URL-encoding (verified
  `fish%20%26%20chips` → "fish & chips"), which a strip-`&page=N` regex would
  not. Also removed the leftover console.logs.
- **Desktop: "Searching for:" and the box on one line** — flex row on
  `#search-page-form-container` at ≥800px, vertically centred (measured
  centres identical at 334px). Mobile unchanged (stacked). One gotcha: the
  media-query margin override had to sit after the base form rule in the file —
  same specificity, source order decides.
- **Desktop spacing above "Search results"** — 2.2em margin-top
  (`#search-page-form-container + h2`, desktop media query) so the heading
  groups with the result list below (63px above vs 14px below) rather than
  with the form. Mobile unchanged.
