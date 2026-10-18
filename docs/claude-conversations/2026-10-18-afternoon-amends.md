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
