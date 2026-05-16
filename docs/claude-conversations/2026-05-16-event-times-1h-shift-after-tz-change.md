# Event times displayed 1h ahead after TZ change — fixed — 2026-05-16

## The symptom

After yesterday's site-timezone change (`UTC → Europe/London`), every previously-created event displayed **one hour ahead** of the time entered in the admin UI. Example:

- Node 219 *URBOND's Socio Cultural Event* — entered as `12:00 - 14:00`, displaying as `1pm - 3pm` on the front page (view_display_front_page).

## Why

Smart Date stores its values as Unix timestamps. Drupal interprets clock-time input from the admin form using the **site default timezone** at the moment of save.

- Before the change (site TZ = UTC), entering `12:00` stored the Unix timestamp for `12:00 UTC`.
- After the change (site TZ = Europe/London = BST = UTC+1 in May), that same stored UTC timestamp displays as `13:00 BST` = `1pm`.

Every event modified before the TZ change carried a "UTC-interpreted" timestamp; reading it back under BST shifted display by +1h for summer events and 0h for winter events.

## The fix

A one-off data migration. For each Smart Date item on every event node:

1. Read the stored Unix timestamp.
2. Format it as a wall-clock string in UTC (e.g. `2026-05-30 12:00:00`).
3. Re-interpret that wall-clock string as **Europe/London** time and take the new Unix timestamp.

For summer events the new timestamp is 1h earlier (12:00 UTC → 12:00 BST = 11:00 UTC stored). For winter events the new timestamp equals the old (12:00 UTC = 12:00 GMT, no shift). The algorithm self-handles BST/GMT correctly because `DateTime` with `Europe/London` zone applies the right offset per date.

```php
$london = new DateTimeZone('Europe/London');
$ns = \Drupal::entityTypeManager()->getStorage('node');
$nids = $ns->getQuery()->accessCheck(FALSE)->condition('type', 'event')->execute();
foreach ($ns->loadMultiple($nids) as $n) {
  $changed = FALSE;
  foreach ($n->get('field_when') as $item) {
    foreach (['value', 'end_value'] as $key) {
      $old = (int) $item->$key;
      if (!$old) continue;
      $wall = (new DateTime('@' . $old))->format('Y-m-d H:i:s');
      $new = (new DateTime($wall, $london))->getTimestamp();
      if ($new !== $old) {
        $item->$key = $new;
        $changed = TRUE;
      }
    }
  }
  if ($changed) {
    $n->setNewRevision(FALSE);
    $n->save();
  }
}
```

Ran via `ddev drush ev "<above>"` followed by `drush cr`. Fixed all 18 event nodes (every existing event was modified before the TZ change).

## Important — do not re-run

This script is **single-use** for the UTC → Europe/London transition. Re-running it would shift everything by another hour and break correctness. Now that the site TZ matches the editor's clock, newly-created or freshly-edited events store their timestamps correctly and need no migration.

## Future-proofing

If the site TZ ever changes again, the same shift will hit any timestamps stored under the previous TZ. The way to avoid this:

1. **Don't change site TZ casually.** It's effectively a data interpretation switch.
2. If you must, **either**:
    - run the equivalent migration immediately afterwards (as we did here), with the appropriate from/to TZ in the algorithm; or
    - configure Smart Date to **store a per-item timezone** alongside each timestamp (the `timezone` column on Smart Date items is currently empty on this site) — then each event becomes self-describing and TZ changes don't disturb stored data.

For now, this is one-and-done.

## What didn't need fixing

- **No code change** — the bug was data, not the rendering logic.
- **No config change** — `system.date.yml` is already correct (Europe/London).
- **No git commit** — Smart Date values are content, not config; the migration was a DB-only operation.

## Verified

- `drush ev` re-read on node 219 → `12:00 - 14:00 BST` (matches entered value) ✓
- Front page event cards display correct times (test event today 16 may shows `16 May, 12:15 - 2:15am` matching its title) ✓
