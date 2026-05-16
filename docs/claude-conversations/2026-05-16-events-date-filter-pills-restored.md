# Date filter pills restored alongside the "only current events" filter — 2026-05-16

## Symptom

On `/culture` (and `/explore/events`, `/sectors`, anywhere a View Display paragraph references `events_listing`), the date filter pills (`all dates / today / this weekend / this week / this month`) had disappeared. The page rendered events correctly but the exposed-filter form was missing from the DOM entirely.

## Cause

Commit `baf1180` ("only show current events") replaced the previous **grouped + exposed** date filter with a simple **`>= today`, non-exposed** filter on `field_when_end_value`. The intent was to hide past events from listings by default — a sensible product change. The side effect was that the grouped filter UI vanished, because:

- `exposed: false` → no form rendered.
- `is_grouped: false` → no pill options defined.
- `value: today, op: >=` → applied as a hidden "and this is upcoming" filter.

## Fix

Two filters now coexist on `field_when_end_value` — Views ANDs them together:

| Filter id | Purpose | Exposed | Grouped |
|---|---|---|---|
| `field_when_end_value` | Always-on. Hides past events from every listing. | No | No |
| `field_when_end_value_1` | User-selectable pill UI (today / this weekend / this week / this month). | Yes | Yes |

URL identifier is unchanged (`date_filter_id`), so existing bookmarks and pill click behaviour keep working.

Picking `all dates` → only the always-on hider applies → all upcoming events.
Picking `today` → hider AND today's window apply → events ending today only.
Same for the other presets.

The `_1` suffix on the second filter is required because Views demands unique handler ids when two filters target the same field column. It's purely an internal identifier — the field column itself is still `field_when_end_value`.

## Why "two filters" instead of changing the pill grouped filter's default

A grouped exposed filter doesn't have an implicit "always-applied" condition. The Default group is just "what option is selected when no parameter is in the URL", and every group option is a positive filter. There's no built-in way to express "the All option applies a hidden `>= today` clause". Two filters is the clean way to layer the two concerns.

An alternative would have been to add a `past` group option to the pills and make the default group `today onwards`. That's also valid, but it puts "past events" one click away from the home view of every section page — likely not what we want. The two-filter approach keeps past events strictly hidden unless someone removes the always-on filter via Views config.

## Caveat for Views UI editing in future

If you edit either filter via `/admin/structure/views/view/events_listing/edit` and save, **`drush cex` first** to capture any pending UI changes into the YAML, then re-verify the dual-filter setup before the next `drush cim`. The Views UI sometimes:

- Resets `exposed: true` to `false` when you re-save a filter without explicitly re-ticking *Expose this filter*.
- Renames `_1`-suffixed handlers to drop the suffix when adding/removing filters in the same session.
- Drops `widget: radios` back to `select`.

Any of these would silently break the pill UI again. The pattern is the same one that bit us on the original pill-widget regression (commit `87f296b`): the YAML is the source of truth, but UI edits go to the DB and need exporting.

## Files changed

```
M  config/sync/views.view.events_listing.yml
```

The change is config-only — no theme, no PHP, no template changes. The CSS and JS that style and auto-submit the pills are unchanged.

## Deploying to live

The change is one YAML file. Standard deployment:

```bash
# On live (SSH or hosting console)

# 1. Pull the latest code (whatever your prod deploy mechanism is — git pull,
#    pipeline, drush-deploy, etc.). Make sure commit 335d13f is on prod.

# 2. Export any UI-side config first so nothing in the DB gets clobbered.
drush cex -y
git status         # if there are uncommitted YAML diffs, decide what to do
                   # with them BEFORE running cim. They are UI edits made
                   # since the last deploy and may or may not be wanted.

# 3. Import the new config.
drush cim -y       # will show 'Update: views.view.events_listing'

# 4. Rebuild cache.
drush cr

# 5. Spot-check in a browser.
#    - /explore/events: pills visible, picking 'this week' narrows the list.
#    - /culture: pills visible, picking 'today' narrows to today's events.
#    - Pre-existing event pages still display correct times.
```

If `drush cex -y` in step 2 produces a diff on `views.view.events_listing.yml`, that's UI-side changes on live that conflict with this commit. Two options:

- **Discard the live diff** (`git checkout -- config/sync/views.view.events_listing.yml`) — if you don't want to keep the live UI changes — then `drush cim -y` as normal.
- **Keep the live diff** — commit it locally, rebase on top of `335d13f`, push to a feature branch, decide which version of the filter you want, and re-deploy. More effort but no data loss.

In practice, if no one has edited the events_listing filters via the live Views UI since this commit was prepared, `drush cex` will produce no diff and `drush cim` is safe.

## Verification

After deploy, on live:

```bash
# Confirm both filters are present and the pill filter is exposed + grouped
drush ev "
\$v = \Drupal\views\Views::getView('events_listing');
\$v->setDisplay('view_display_primary_topic');
\$v->initHandlers();
foreach (\$v->filter as \$id => \$h) {
  echo \$id . ' exposed=' . (\$h->isExposed() ? 'yes' : 'no') . ' grouped=' . (\$h->isAGroup() ? 'yes' : 'no') . PHP_EOL;
}
"
```

Expected output (the order may differ):

```
status exposed=no grouped=no
type exposed=no grouped=no
field_when_end_value exposed=no grouped=no
field_when_end_value_1 exposed=yes grouped=yes
```

Then load `/explore/events` in a browser and confirm the pill row is visible above the event listings. Pick a preset and confirm the URL becomes `…?date_filter_id=N` and the listing re-filters.
