# Issue 465: regular events out of order in the listings (23 October 2026)

Session: late evening 22 Oct into 23 Oct 2026. Branch
`issue-465-regular-events-order`. Release
`scripts/release-2026-10-23-issue-465-events-order.sh` (git pull, cr, cim,
cr). No composer or content change.

## Rob's report

Regular events appear out of order in the View when displayed with
non-regular events sorted by date ascending. They should sort by the date
of their next instance, the one the card prints after "Next:". Screenshot
from the front page on Thursday evening 22 Oct: Socialise (Next: Thu 29
Oct), Salsa & Bachata (Next: Wed 28 Oct) and Ballroom & Latin (Next: Thu
29 Oct) sat between Thursday's and Friday's one-off events.

## Cause

Two time bounds disagreed.

- The card's "Next:" (customsolent.theme, `_customsolent_preprocess_event_shared_extras`)
  is the first instance whose end is at or after the request time.
- The listing keeps one row per regular event
  (`_customsolent_helpers_one_row_per_recurring_event`, hook_views_query_alter)
  and chose the earliest instance whose end is at or after *today
  midnight*. On Thursday evening that was still Thursday's finished 7pm
  instance, so the event sorted at 7pm Thursday while its card said next
  week.

A second, quieter problem: the view uses Views' "Tag based" results cache,
which holds query results until an event is saved. Even with the bound
fixed, the results computed in the morning would have stayed all evening.
The card already carries a max-age to the instance end so the rendered
output refreshes, but the re-run view would have read the stale results.

## Fix (customsolent_helpers)

- The row bound is now the clock, `UNIX_TIMESTAMP()` in the SQL rather
  than a query argument. Views keys its results cache on the query and its
  arguments; a request-time argument would make every execution a miss
  that leaves a permanent cache entry behind. The 'today' date filter is
  still an argument, so the key changes once a day as before. Non-MySQL
  engines fall back to a request-time argument.
- New Views cache plugin `customsolent_events_tag`
  (`src/Plugin/views/cache/EventsTag.php`, schema in `config/schema`):
  Tag caching whose results expire at the earliest end of a listed regular
  event's instance (+1s) or midnight, whichever first. The query alter adds
  `slnt_when_end` / `slnt_when_rrule` columns for it to read. Rendered
  output keeps the cards' own max-age, which expires at the same moment.
- `views.view.events_listing` default display cache type switched to the
  new plugin (all displays inherit it).

Checked locally (Fri 23 Oct 00:57): front page rows unchanged (no
instance had ended today), results cache expiry 21:30:01 = end of
Ballroom & Latin's Friday instance + 1. Replaying the front-page SQL at
Thu 22 Oct 21:00 BST: old bound reproduces the screenshot order; new
bound drops Socialise and Salsa & Bachata to their next week instance and
keeps Ballroom & Latin (DM Dance Studios) whose 20:35 instance was still
running. Events page and listing-cards displays with the "today" pill run
fine. Config import clean, schema registered, no log errors.

## Side effect worth knowing

A regular event whose instance has finished today no longer counts as
"today" in the date pills (its row is next week's instance). One-off
events that finished earlier today still show until midnight, as before.
That matches the card, which would otherwise say "Next: next week" under
a Today filter.

## For Rob

- Run the release; look at the front page in the evening after a regular
  event has finished: it should have moved to its next date.
- Related upstream work (issue 462's Smart Date patch) still pending.
