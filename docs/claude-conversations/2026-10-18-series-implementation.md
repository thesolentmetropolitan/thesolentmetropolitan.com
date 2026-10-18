# Series content type + Black History Month 2026 — implementation log

**Date:** 2026-10-18 (session start 00:46)
**Branch:** `series-and-black-history-month`
**Release:** `scripts/release-2026-10-18-series.sh`
**Brief:** `2026-10-18-people-series-collaborations-brief.md` (Phase 1 of 4)

## What was built

### Content model (config/sync)
- `node.type.series` — Series: a container for events, articles and links on one theme over a period.
- New field storages: `field_series_items` (entity_reference → node: event, article, link; unlimited, editor-ordered), `field_description` (text_long, shared with Collaboration later), `field_series_start` and `field_series_end` (core datetime, date only; empty end = open-ended).
- Reused: `field_standfirst`, `field_primary_topic`, `field_related_topics`.
- Form display, `default` (full) and `teaser` view displays. Pathauto `/series/[node:title]-[node:field_series_start:date:html_year]` → `/series/black-history-month-2026`. Rabbit Hole: display page.
- `views.view.series_listing` — default display filters published series, newest start first; `view_display_listing_cards` (teaser rows, pager 24) and `view_display_preview` (6, no pager).
- Classy style `series_grid` → `.slnt-series-grid`. `field_view.preselect_views` gains `series_listing` (per the 2026-09-24 lesson: stale preselect entries make the View autocomplete return nothing).

### Logic (customsolent_helpers)
- `src/ContentRelations.php`, service `customsolent_helpers.content_relations`: `seriesContaining($node)` (reverse lookup via `field_series_items.target_id`, published only), `seriesStatus()` (upcoming / current / ongoing / past by calendar day in the site timezone), `statusLabel()` ("Coming up" / "Now on" / "Ongoing" / "Past series"), `seriesDateRange()` ("1–31 October 2026", "29 September – 19 November 2026", "From 1 October 2026"), `seriesCounts()`, `seriesMembers()`, `seriesCacheTags()` (`node_list:series` + each series' tag), `maxAgeUntilMidnight()`.
- `src/Render/SeriesMemberContext.php`: trusted `#pre_render`/`#post_render` pair that marks "rendering members of series N" so a member card on its own series page drops that series from its "Part of the series" line. The member build gets a distinct cache key (`in_series_N`) so this variant never shares a render-cache entry with the card elsewhere.

### Theme
- `customsolent.theme`: `_customsolent_preprocess_series_extras()` (status, range, counts, members grouped by bundle as compact cards, related topics as section-coloured links) and `_customsolent_preprocess_series_membership()` (event teaser / compact / full: `series_links` + `series_cache`). `series` added to the bundles that expose `primary_topic_menu_path`.
- Templates: `node--series--teaser.html.twig` (card: status pill, title with stretched link, date range, standfirst, counts, kicker), `node--series--full.html.twig` (badge, trail, h1, range, standfirst, description in the article reading column, Events / Articles / Links grids, "Also in" topics), `components/series-line.html.twig` (layers icon + series links; prints `series_cache` in every branch).
- Event templates: compact gets the series line between the card link and the kicker; teaser between Where and the CTA; full gets a "Part of a series" aside after the CTA with range and status.
- `css/series.css` (grid, card, series line, series page), registered in the global library.

### Content (scripts, idempotent, run by the release script)
- `scripts/add_series_listing.php` — View Display paragraph (series_listing / listing cards, Series Grid style) appended to the enclosure on /explore/series (node 94).
- `scripts/create_black_history_month_2026.php` — Location terms Good Things, Engine Rooms, MAST Mayflower Studios under Southampton; seven published events from blackhistorymonthsouth.com/events; Series "Black History Month" 1–31 Oct 2026, primary Culture / Heritage & History, related Identity, Community, Festivals, Music, Screen, with the seven events in date order.

## Decisions taken under assumption (brief §2; flip any of them cheaply)
- Series dates: two date-only fields, not Smart Date (no "all-day" hack, end optional for open-ended).
- URL: `/series/<title>-<start year>` (plural prefix like /events/, year not full date because annual series recur).
- Status words: Now on / Coming up / Ongoing / Past series.
- On a series' own page the member cards do not repeat the series name.
- "8pm – late" gigs stored as three hours; SOUL 45 (no time published) all-day; Breaking Patterns one 29 Sep – 19 Nov span.
- The pre-existing umbrella event "Black History Month South" (1–30 Oct, Various) was left out of the series; Rob may add it in the editor.

## Verified locally
- /explore/series: one card, "Now on", 1–31 October 2026, 7 events, Culture / Heritage & History kicker (desktop + phone screenshots in `2026-10-18-series/`).
- /series/black-history-month-2026: badge, trail, dates, description, Events (7) grid of compact cards without the redundant series line, "Also in" row; 2 / 1 columns on a phone.
- Front page What's on: Breaking Patterns and Manding Melody cards carry the "Black History Month" line above the kicker.
- Event full page: "Part of a series — Black History Month 1–31 October 2026 · Now on".
- `drush config:status`: no differences after export; no PHP errors in watchdog.
- Release script rehearsed from the `pre-series` DDEV snapshot with `DRUSH="ddev drush"` (see below).

## Known limits / follow-ups
- Status words change at local midnight: render cache carries a max-age to midnight; page cache max_age is 0 on this site so nothing else holds the old word.
- Any series save invalidates every event card (`node_list:series`): acceptable at this scale.
- Series and Collaborations are not in the section strip or `/{topic}/series`; add to `_customsolent_listing_kinds()` later if wanted.
- Collaboration teasers on series / event pages: Phase 3.
- Search indexes series nodes via the default display (the `search_index` view mode falls back to it).
