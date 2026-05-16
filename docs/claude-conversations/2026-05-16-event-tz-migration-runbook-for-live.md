# Runbook: event timestamp migration on live — 2026-05-16

A copy-paste runbook for applying the same one-off Smart Date timestamp fix on production that was run on the dev environment this morning. Apply this exactly once, after switching the live site's timezone to `Europe/London`.

## What this fixes

Every event node modified while the site timezone was `UTC` had its Smart Date `value` / `end_value` stored as if the entered clock-time was UTC. After switching the site TZ to `Europe/London`, those timestamps display 1 hour ahead during BST (and stay correct during GMT). This script re-interprets each stored timestamp as *"that wall-clock was meant in Europe/London"* and saves the corrected absolute timestamp.

Full background: `2026-05-16-event-times-1h-shift-after-tz-change.md`.

## Prerequisites

- Live site TZ is **already** set to `Europe/London`. Confirm with:

    ```bash
    drush config:get system.date timezone.default
    # expect: timezone:
    #          default: Europe/London
    ```

  If it's still `UTC`, fix that first (`/admin/config/regional/settings`, save, `drush cr`, then `drush cex -y` to track in git).

- Drush is available on prod (most Drupal hosts; check by running `drush status`).

- A quiet window — no editor actively saving events during the migration.

## Step-by-step

### 1. Database backup (non-negotiable)

```bash
drush sql-dump --gzip --result-file=/tmp/before-event-tz-fix-$(date +%Y%m%d-%H%M%S).sql.gz
ls -lh /tmp/before-event-tz-fix-*.sql.gz
```

Verify the file exists and has non-zero size before continuing. If anything goes wrong, restore with `gunzip -c <file> | drush sqlc`.

### 2. Sanity-check event count

```bash
drush ev "
\$ns = \Drupal::entityTypeManager()->getStorage('node');
\$nids = \$ns->getQuery()->accessCheck(FALSE)->condition('type', 'event')->execute();
echo 'Event nodes total: ' . count(\$nids) . PHP_EOL;
"
```

Should print a number consistent with what you see at `/admin/content?type=event`.

### 3. Spot-check ONE event (dry-run, no write)

Pick an event ID you know the entered times for. Replace `NID_HERE`:

```bash
drush ev "
\$london = new DateTimeZone('Europe/London');
\$n = \Drupal\node\Entity\Node::load(NID_HERE);
echo \"Node \" . \$n->id() . \": \" . \$n->label() . PHP_EOL;
foreach (\$n->get('field_when') as \$item) {
  foreach (['value', 'end_value'] as \$key) {
    \$old = (int) \$item->\$key;
    if (!\$old) continue;
    \$wall = (new DateTime('@' . \$old))->format('Y-m-d H:i:s');
    \$new = (new DateTime(\$wall, \$london))->getTimestamp();
    \$d_old = (new DateTime('@' . \$old))->setTimezone(\$london)->format('H:i T');
    \$d_new = (new DateTime('@' . \$new))->setTimezone(\$london)->format('H:i T');
    echo \"  \$key: \$old (\$d_old) → \$new (\$d_new)\" . PHP_EOL;
  }
}
"
```

Confirm the `→` direction matches what you'd expect (e.g. `13:00 BST → 12:00 BST` for a summer event whose entered time was 12:00).

### 4. Run the migration

```bash
drush ev "
\$london = new DateTimeZone('Europe/London');
\$ns = \Drupal::entityTypeManager()->getStorage('node');
\$nids = \$ns->getQuery()->accessCheck(FALSE)->condition('type', 'event')->execute();
\$fixed = 0;
foreach (\$ns->loadMultiple(\$nids) as \$n) {
  \$changed = FALSE;
  foreach (\$n->get('field_when') as \$item) {
    foreach (['value', 'end_value'] as \$key) {
      \$old = (int) \$item->\$key;
      if (!\$old) continue;
      \$wall = (new DateTime('@' . \$old))->format('Y-m-d H:i:s');
      \$new = (new DateTime(\$wall, \$london))->getTimestamp();
      if (\$new !== \$old) {
        \$item->\$key = \$new;
        \$changed = TRUE;
      }
    }
  }
  if (\$changed) {
    \$n->setNewRevision(FALSE);
    \$n->save();
    \$fixed++;
    echo 'Fixed: ' . \$n->id() . ' — ' . \$n->label() . PHP_EOL;
  }
}
echo PHP_EOL . 'Total fixed: ' . \$fixed . PHP_EOL;
"
```

### 5. Cache rebuild

```bash
drush cr
```

### 6. Verify in the browser

- Open the home page (`view_display_front_page` renders event cards) — times should match what was entered in the admin UI.
- Open a known event node page — same check.
- Open `/explore/events` and `/explore/events?date_filter_id=1` (today) — sanity-check the listing and the "today" filter.

If anything looks off, restore from the backup (step 1) and contact me before re-running.

## Critical: do not re-run

The script shifts every stored timestamp whose UTC wall-clock differs from its Europe/London wall-clock. The first run produces the correct stored values. A second run would shift summer events by another hour (wrong); winter events would stay put (because their UTC and GMT wall-clocks match) but anything BST would drift further. If you accidentally trigger a second run, restore from the backup.

To prevent accidental re-run, you could rename your shell history entry or simply close the terminal session after a successful run.

## After the migration

Times stay correct through every future BST↔GMT transition without any further work, because:

- Smart Date stores absolute Unix timestamps.
- PHP's `DateTime` with `Europe/London` applies the right offset (BST or GMT) based on the event's own date when rendering.
- The site TZ now matches the editor's clock, so any new event entered via the admin UI is stored with the correct absolute timestamp from the start.

The single risk to revisit is if the site TZ is ever changed away from `Europe/London` (e.g. multi-region future). In that case a similar one-off migration tailored to the new TZ would be needed.

## Cross-DST events (informational, no action needed)

An event whose start and end straddle the BST↔GMT switchover (e.g. a 24-hour party on Oct 24–25) will display a wall-clock duration that's 25 hours long on the March transition (one less hour displayed) or 23 hours long on the October transition. That's reality — the clocks really did jump. Not a bug.

## Files referenced

- `2026-05-16-events-today-filter-timezone-fix.md` — the original `UTC → Europe/London` switch.
- `2026-05-16-event-times-1h-shift-after-tz-change.md` — the dev-side migration that this runbook mirrors.
