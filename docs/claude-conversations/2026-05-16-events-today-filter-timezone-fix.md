# Events "today" date-filter — timezone fix — 2026-05-16

## The symptom

A test event titled *"test event today 16 may"* with end time `Sat, 16 May 2026, 12:15 - 2:15am` did not appear under the **today** date filter (`?date_filter_id=1`) on `/explore/events`.

## The cause

It was a **timezone mismatch**, not a filter-value problem.

- Site timezone was configured as **UTC** at `/admin/config/regional/settings`.
- Server clock at the time of testing: `2026-05-15 23:45 UTC` — i.e. 15 minutes before midnight UTC.
- The "today" preset is defined as `Between now AND tomorrow midnight`. Evaluated in UTC, that range was `2026-05-15 23:45 UTC → 2026-05-16 00:00 UTC` — a 15-minute window.
- The test event ends `2026-05-16 02:15`. Smart Date stores values in UTC. The event end fell **outside** the 15-minute window (after `tomorrow midnight` UTC).
- From the user's perspective (UK / BST = UTC+1), local time was `00:45 on 16 May` — so the event clearly *is* today. But the server, running in UTC, thought it was still 15 May. PHP's `strtotime('today')` and `strtotime('tomorrow midnight')` use the server's timezone, so the filter range was inferred for the wrong calendar day.

## The fix

Set the site timezone to **Europe/London** at `/admin/config/regional/settings`. After the change:

- In May (BST = UTC+1), `today midnight` resolves to `16 May 00:00 BST = 15 May 23:00 UTC`.
- The "today" filter range becomes `15 May 23:00 UTC → 16 May 23:00 UTC` — a full 24-hour window of "the user's today".
- The test event's end at `16 May 02:15` falls inside that range and now appears under "today" ✓.

## Steps that were run

1. `/admin/config/regional/settings` → set Default time zone to `Europe/London` → Save.
2. `drush cr` — flush cached View renders.
3. Reload `/explore/events?date_filter_id=1` — confirmed the test event appears.
4. `drush cex -y` — exported the change to `config/sync/system.date.yml`.

## Why not change the filter values to use timezone-aware strings

`strtotime` accepts timezone suffixes (e.g. `today midnight Europe/London`) but:

- The values are entered in the Views admin UI by editors. Asking them to type a timezone suffix in every grouped-filter value is fragile and easy to forget.
- Editors entering event times in the admin UI also use the site timezone — keeping both the View filter and the event entry in the same timezone avoids subtle off-by-one bugs.
- Setting the site timezone once at `/admin/config/regional/settings` is one config switch that aligns the whole system.

## Caveat for previously-stored event times

Events created before the timezone change were stored assuming UTC (because that was the site timezone at creation). Smart Date renders in the user's timezone (or site default), so most displays auto-correct after the change. If a future event ever appears off by one hour, the previously-stored UTC time is the cause — re-edit it in the admin UI to fix.

## Related observation: section_filter paragraph's role

Same conversation reaffirmed the brief's intent: the **section_filter paragraph is an explicit topic override**, not "the" filter UI. When a section page has no section_filter paragraph, the view_display paragraph still renders its own filter sidebar — driven by the page's `field_primary_topic`. The section_filter exists for the two cases where that isn't enough:

1. **One filter per page across multiple view_display paragraphs** (Events + Articles + Organisations on one section page should share one filter, not three).
2. **An explicit topic override** for pages whose primary topic doesn't yield a useful filter scope — most importantly Explore landings, where the page's primary topic is structural and the section_filter falls back to the all-topics scope (Culture / Sectors / Living).

Further work on the section_filter is intended after this commit.

## Files changed

```
M  config/sync/system.date.yml      (timezone.default: UTC → Europe/London)
A  docs/claude-conversations/2026-05-16-events-today-filter-timezone-fix.md
```

## Related issues

- [#262](https://github.com/thesolentmetropolitan/thesolentmetropolitan.com/issues/262)
- [#263](https://github.com/thesolentmetropolitan/thesolentmetropolitan.com/issues/263)
