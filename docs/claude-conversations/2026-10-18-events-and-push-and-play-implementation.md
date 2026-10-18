# Events: organiser, several venues, regular events — plus Push & Play

**Date:** 2026-10-18 (same session; start 00:46)
**Branch:** `people-and-collaborations` (continued; third commit)
**Release:** `scripts/release-2026-10-18-events-and-push-and-play.sh` (after the Series and People + Collaborations releases)

Rob's additions after the People + Collaborations work: 3.5.1.3 (recurring events, events at several venues at once, a label for regular events), 3.5.1.4 (a single Organiser organisation on Event) and 3.4.2.2 (real content: the Push & Play skatepark launch as a Collaboration, event 706 published).

## Event content model
- `field_organiser` (entity_reference → organisation, single) on Event, after Where on the form. Full page: "Organised by <link>"; teaser: an Organiser row with a people icon; compact card unchanged (kept short).
- `field_where` storage cardinality 1 → unlimited (config only; a dedicated-table field keeps its schema). Form shows "Add another". Every reader of the field now loops: `_customsolent_preprocess_event_shared_extras()` sets `where_list` ({name, area}) and `where_areas` ("Southampton, Fareham and Portsmouth"); the old `where_name` / `location_area` still describe the first venue.
- Recurring dates: `smart_date_recur` was already enabled; `field_when` now has the third-party setting `allow_recurring: true`, `month_limit: 12`. The event form gains the repeat controls (frequency, interval, end). Saving stores one `field_when` item per instance with the rule id; cron extends unlimited rules.
- `field_when` formatter on teaser / compact / default switched from `smartdate_custom` to `smartdate_recurring` with three new Smart Date formats (`slnt_card` D j M · `slnt_teaser` D, j M Y · `slnt_full` D, M j Y; all g:ia). For a one-off event the output is unchanged; the templates do not use the module's recurring markup (details/summary) but their own line.

## Display of a regular event
- Badge "Regular event" (Living green, like an ongoing series) under the title on cards and in the badge slot on the full page (hidden there when the event is past).
- Date line: "Every Monday, 7pm - 9pm" + "Next: Mon 19 Oct" (compact: on its own line; teaser: inline; full: the next three dates). The phrase comes from `_customsolent_recurrence_phrase()` (frequency + interval + weekday), not the module's "Weekly on Monday, 7 - 9pm", which also leaked theme-debug comments when rendered to a string.
- Several venues: compact card says "4 venues" with the towns beneath; teaser and full page list each venue with its town.
- Cache: `event_when_cache` carries the rule's cache tag and a max-age to the end of the current/next instance, so "Next" moves on without a cache clear. Full page "past event" now means the LAST instance has ended.

## One row per recurring event in listings
**Regression fixed the same night:** the first version added the condition to Views where-group 0, which `views_contextual_filters_or` makes an OR group holding the topic arguments; every one-off event then matched on every section page (Rob saw /living/mental-health listing far more than its six tagged events). The condition now sits in its own AND group (`$query->setWhereGroup('AND')`).

`_customsolent_helpers_one_row_per_recurring_event()` (called from the existing `hook_views_query_alter` for `events_listing`) adds `field_when_rrule IS NULL OR field_when_end_value = (SELECT MIN(end) … same rule … end >= today)`. One-off events are untouched; a recurring event appears once, at its next instance, in every display (front page, section pages, date pills). A finished recurring event has no row anywhere, which the upcoming-only displays already imply. Verified: Andysmanclub (52 instances) appears exactly once in `view_display_listing_cards` and once on the front page.

## Content
- `scripts/create_push_and_play_collaboration.php`: six new Organisations (We Skate Soton CIC, Skate Southampton, Skateboard GB, Carabiner Club Southampton, Southampton Sapphic Social, Iron Born Ltd — standfirsts first drafts, URLs only where certain), ten Collaborators (role Partner for all, as Rob does not yet know the involvement), the Collaboration "Push & Play: New Hoglands Park Skatepark" (outcome: event 706), event 706 published with Organiser = We Skate Soton CIC. Southampton City Council (on the thank-you poster, node 205) is left for Rob to add.
- `scripts/create_regular_events_examples.php`: Andysmanclub (Mon 7–9pm, four venues) and Mangang (Wed 6.30–8.30pm, three venues) as **unpublished** drafts with weekly rules and 12 months of instances, plus the venue Location terms (University of Portsmouth under Portsmouth). End times and topics are my guesses for Rob to check before publishing.

## Verified locally
- Push & Play: collaboration page (Who ×10, Events ×1), event page (Past event · Organised by We Skate Soton CIC · Part of a collaboration), We Skate Soton and GO! Southampton organisation pages, /explore/collaborations.
- Andysmanclub (published briefly for the check): compact card with badge, "Every Monday, 7pm - 9pm", "Next: Mon 19 Oct", "4 venues · Southampton, Fareham, University of Portsmouth and Portsmouth"; full page with the venue list and three next dates; one row in listings.
- Release script rehearsed from the `pre-events` DDEV snapshot; `drush config:status` clean; no PHP errors.

## Notes for Rob
- Editing a recurring event: the form shows the rule once (not 52 rows); changing the rule regenerates instances. Smart Date Recur also offers per-instance overrides (cancel/move one Monday) via the "Manage instances" link.
- A recurring event's listing position is its next instance, so it floats to the top of "What's on" every week. If that proves too prominent, a "regular events" block or a lower sort weight is the next step.
- The compact card shows "4 venues" and towns; the parent of a venue term is what shows as the town, so "University of Portsmouth" appears as an area because Rob asked for that parent.
