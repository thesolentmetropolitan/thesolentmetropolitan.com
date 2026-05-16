# Suppress date filter pills on view_display_front_page — 2026-05-16

Follow-on from `2026-05-16-events-date-filter-pills-restored.md`. The previous fix restored the date pill filter on every events_listing display — including `view_display_front_page`, which is the curated front-page summary that shouldn't have a filter row.

## Why it leaked to the front page

The pill filter is configured on the Default display of `events_listing`. Drupal Views inherits all of Default's filters into every sub-display unless that sub-display sets `defaults.filters: false`.

`view_display_front_page` previously had:

```yaml
defaults:
  pager: false        # has its own pager (items_per_page: 6)
  row: false          # has its own row plugin (view_mode: compact)
  filters: true       # ← inherited everything from Default, including pills
  filter_groups: true
  footer: false
```

So when the pill filter was added on Default, the front page picked it up.

## The fix

Set `defaults.filters: false` on `view_display_front_page` and write the filters block explicitly. Copy the three always-on filters from Default (`status=1`, `type=event`, `field_when_end_value` ≥ today) and **omit** `field_when_end_value_1` (the pill). Leave `defaults.filter_groups: true` because Default doesn't define a `filter_groups` block — the implicit single-AND group is fine.

```yaml
view_display_front_page:
  display_options:
    pager:           # … unchanged
    row:             # … unchanged
    filters:
      status:        { …  copied from Default … }
      type:          { …  copied from Default … }
      field_when_end_value:
                     # the always-on '>= today' hider — copied
      # NOTE: no field_when_end_value_1 here → no pills on this display
    defaults:
      pager: false
      row: false
      filters: false       # ← was: true
      filter_groups: true  # unchanged — inherit the implicit single group
      footer: false
```

After `drush cim && drush cr`:

- **Front page** (`view_display_front_page`): zero exposed forms above the event cards.
- **All other displays** (`view_display_events_page`, `view_display_primary_topic`, `view_display_related_topics`, `view_display_primary_and_related`): inherit from Default unchanged, pills still visible.

## Why per-display override rather than per-filter

Views' display-inheritance is per-section: filters as a whole, sorts as a whole, etc. There's no built-in "inherit the filters block but override just one filter's exposed flag". The clean way is to take ownership of the entire filters block on the display that wants different behaviour.

Cost: the three inherited filters are duplicated in the YAML (front_page now carries its own copy). If you ever change the default `>= today` value or the `type = event` filter on Default, you'd need to mirror the change on view_display_front_page too. Worth noting if you change those filters in the future — the editor diff between the two filter blocks is the source of truth for "what's intentionally different on front page".

## Future-proofing: adding a new display

If you add another events_listing display that should also not show the pill filter (e.g. a "promoted events" sidebar block), repeat the same pattern: set `defaults.filters: false` and copy in just the always-on filters. Or — easier — duplicate `view_display_front_page` in the Views UI and adapt.

If you add a display that SHOULD show the pills, just leave `defaults.filters: true` (or don't add a filters block) and it'll inherit them automatically.

## Verification on live

After `git pull` and `drush cim -y && drush cr` on prod, browse:

| Page | Expect |
|---|---|
| `/` (home) | Event cards, **no pill row** above them. |
| `/explore/events` | Pill row visible, picking a preset narrows the list. |
| `/culture` | Pill row visible (next to the topic sidebar). |
| `/sectors`, `/living` | Same as `/culture`. |

```bash
# Spot-check via drush (re-uses the snippet from the earlier doc but checks
# the front_page display specifically)
drush ev "
\$v = \Drupal\views\Views::getView('events_listing');
\$v->setDisplay('view_display_front_page');
\$v->initHandlers();
foreach (\$v->filter as \$id => \$h) {
  echo \$id . ' exposed=' . (\$h->isExposed() ? 'yes' : 'no') . PHP_EOL;
}
"
```

Expected output:

```
status exposed=no
type exposed=no
field_when_end_value exposed=no
```

No `field_when_end_value_1` line — the pill filter is intentionally absent from this display.

## Files changed

```
M  config/sync/views.view.events_listing.yml   (commit 8155ef5)
```

Config-only change. Same deploy mechanics as the previous filter restoration: `drush cex -y` first to capture any UI-side edits, then `drush cim -y && drush cr`.
