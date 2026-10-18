# Afternoon amends: redirects, spacing, card links, related events, kickers

**Date:** 2026-10-18 (session start 13:27, after the overnight session 00:46–03:00)
**Branch:** `amends-2026-10-18` (off `main` at eb8559c, after the three releases were merged)
**Deploy:** code only, no config and no content script — `git pull`, then `drush cr` (the new routes need a router rebuild). Screenshots: `2026-10-18-afternoon-amends/`.

Rob's amends after reviewing the overnight work, with the remaining 3.5 items recapped and new "near future" items logged (see the brief, §7, and the memory file).

## What changed

- **Bare prefixes redirect to the listing pages.** `/series` → `/explore/series`, `/collaborations` and `/collaboration` → `/explore/collaborations`, and (same pattern, not asked for) `/people` → `/about/team`. Routes in `customsolent_helpers.routing.yml`, one controller `ListingHomeRedirectController::redirectToListing()` returning a cacheable 301 `LocalRedirectResponse`. Code, not `redirect` entities, so it ships with `git pull` and needs no content script; the targets are the listing pages' aliases, resolved at request time.
- **Air at the top of full pages below 1200px.** `.slnt-node-full` (event, series, collaboration, person, organisation, link pages) and `.slnt-article` had `padding: 0 …` in the tablet and mobile media queries, so the first badge or kicker sat hard against the menu bar. Now 1.5rem (tablet) / 1.4rem (mobile) on top, in line with the 2em on desktop. Before/after: `amends-event-full-mobile-before.png`, `-after.png`.
- **Organisation cards go to the organisation page.** `node--organisation--compact.html.twig` (the card on `/explore/organisations` and the topic organisation listings) now links an Organisation to its own page with no external icon; a Link card keeps its outward link, new tab and icon. The organisation page carries the website button, "Coming up" and collaborations. (The `organisation` *teaser* template is not used by any view; the listings use `compact`.)
- **Related events at the foot of the event page.** `ContentRelations::relatedEvents($event, $limit = 4)`: upcoming published events (end at or after today) whose primary or related topics overlap this event's, soonest first, this event excluded, each recurring event once. Rendered as compact cards in the series-grid style under "Related events", after the collaborations block. Cache: `node_list:event` + max-age to midnight, printed in every branch.
- **Organiser on the compact event card.** A row with the people icon and the organiser's name, plain text inside the card link (the organisation is linked on the event page). The teaser already had it. Verified on the front page, `/explore/events` and in the related-events grid.
- **Series and Collaboration pages say what they are.** A kind-of-page kicker above the topic trail: the layers icon + "SERIES" linking to `/explore/series` (status badge beside it on the same row), and the people icon + "COLLABORATION" linking to `/explore/collaborations`. `type_home_url` set in `customsolent_preprocess_node()`; styles `.slnt-node-full__type-kicker` / `__type-link` in `node-full-views.css` (doubled selectors beat `.layout-content a`). Screen readers hear "Series: all series".

## Checks
- `curl -I /series` 301 → `/explore/series`; `/collaborations`, `/collaboration` → `/explore/collaborations`; `/people` → `/about/team`; `/series/black-history-month-2026` still 200.
- Andysmanclub page: 4 related events (Mangang, the two Common Ground events, The Big Movement), organiser row on each card.
- Link card rendered in isolation still `target="_blank"` to its URL; organisation card to `/organisations/<name>`.
- PHP lint clean; no new watchdog errors.

## Not done / noted
- The event grids stay two-per-row down to 340px (existing series-page rule); on a 390px phone the related-events cards are narrow. Same as the series page; left for a design pass.
- Only Series and Collaboration pages got the kind-of-page kicker. Events, articles, organisations could take the same (e.g. "EVENT" → the topic's events listing) if Rob wants the pattern everywhere.

## Round 2 (same afternoon, after Rob's review)

- **"Related events" replaced by the organiser's other upcoming events.** The topic-overlap list was too loosely connected (Rob's words: tenuous). The event page now ends with "Coming up from <organiser>": `ContentRelations::upcomingEventsOrganisedBy()`, the same list the organisation page shows, minus the event itself. No organiser, or no other events, means no section. `relatedEvents()` was removed from the service. Verified on Common Ground's event 776 (shows 777) and Andysmanclub (its organisation has no other event, so nothing).
- **"Regular event" badge: light blue, dark text, top right of the title row.** `#dbeafe` with solent-blue text (7.3:1) on the compact card, the teaser and the full page (was white on Living green). On the cards the title and badge share a flex row: the title wraps on the left, the badge keeps its place at the top right and is never pushed down by a long title. One rule makes it behave: the title has `flex: 1 1 0; min-width: min-content`, so it can shrink to its longest word but no further; words are not broken mid-word (the old `overflow-wrap: anywhere` on the title is gone). Only when the longest word and the badge cannot share a line, e.g. "Andysmanclub" on a 160px phone card, does the badge drop under the title. On phones (≤499px) the badge reads "REGULAR" alone and is a touch smaller, so "Mangang" keeps its badge beside it.
- **Phones: event grids use the side space.** On series, organisation and event pages the warm-grey band now bleeds to the viewport edges (negative margin = the page's 1.2rem side padding, never 100vw) with that same inner padding, so the cards' outer edges line up with the text above them. On Home the events grid and the Latest articles grid are pulled out by the difference between the two nested enclosures' padding (2rem) and that 1.2rem, so Home's cards share the series cards' edges. Scoped with the standard Drupal `path-frontpage` body class, which the theme's `html.html.twig` was not printing; `customsolent_preprocess_html()` now adds it. Cards went from 148px to 160px wide at 390px, no horizontal scroll (checked `scrollWidth`).
- Before/after at 390px: `amends2-front-mobile-before/after.png`, `amends2-series-mobile-before/after.png`.

Rob will take organisation names out of event titles where the organiser row now carries them (Common Ground's events), leaving them where the title is the organisation (Mangang).

## Round 3: Series and Collaborations in the section strip

Rob's last item for the day: add Series and Collaborations to the strip on /culture and its sub-topics, only when the topic holds some, by the same mechanism as Events, Articles and Organisations & links.

The strip is one table in the theme, `_customsolent_listing_kinds()`, read by the strip builder, the paragraph listing modes and the automated listing route. Adding a kind meant:

- **Views** (config, needs `cim`): `series_listing` and `collaborations_listing` gained a `view_display_primary_and_related` block display, cloned from `articles_listing`'s by a throw-away PHP script (topic arguments primary OR related with `contextual_filters_or`, `distinct`, full pager of 24, row = teaser). Their existing `view_display_preview` displays gained the same arguments (`default_action: ignore`, so a placed preview without a topic still lists everything) and fetch 9 rows so the "View more" rule works. Saved through the entity API so `cache_metadata` is right ([[feedback_views_cache_metadata_not_recomputed_on_cim]]). Nothing is placed with those displays yet; only the route uses them.
- **Theme**: `_customsolent_topic_listing_types()` maps the two views to the URL words `series` and `collaborations`; `_customsolent_listing_kinds()` has the two new kinds between Articles and Organisations & links (labels, `node_list` tags, the existing classy grid classes `slnt-series-grid` / `slnt-collaborations-grid`, the full display); `_customsolent_topic_scope_stats()` counts published series and collaborations tagged (primary or related) within the scope, past ones included.
- **Module**: route requirement and `customsolent_helpers_listing_route_words()` accept `series` and `collaborations` (the path processor maps `/culture/community/series` to the listing route; `/explore/series` is a real alias so it wins and is untouched); the words are reserved as topic names; `TopicListingController::VIEWS` and `heading()` know them; site-wide pages `/explore/series`, `/explore/collaborations` for the "Preview, all topics" hand-over; the two views join the `?topic=` filtered list.

Result: `/culture` reads Overview · Events · Articles · Series 1 · Collaborations 1 · Organisations & links; `/culture/community` has both (the BHM series and Push & Play both carry Community as a related topic); `/culture/sport` has Collaborations only; `/living` and `/sectors` are unchanged. `/culture/series`, `/culture/music/series`, `/culture/sport/collaborations` render the right cards in the right grid with the section banner and "Culture: Series" titles. `config:status` clean.

Release: `scripts/release-2026-10-18-strip-series-collaborations.sh` (backup, maintenance, `cim`, `cr`), after the three overnight releases. It also carries rounds 1 and 2, which are code only.

Next for Rob (his words): re-align some topic terms and add new ones with their menu items, without increasing the number of items. The reserved-word rule now also refuses a topic named "Series" or "Collaborations".
