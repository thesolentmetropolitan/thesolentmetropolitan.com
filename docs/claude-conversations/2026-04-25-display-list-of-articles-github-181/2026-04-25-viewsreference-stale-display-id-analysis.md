# Viewsreference: stale display ID after renaming a Views display

GitHub issue: https://github.com/thesolentmetropolitan/thesolentmetropolitan.com/issues/181

## What happened

While building the article-list paragraph during issue 181:

1. Created the View `articles` and added a block display, which Drupal auto-named `block_1`.
2. Created a "View Display" paragraph and pointed its `field_view_display` at `articles:block_1`. Saved. Drupal stored:
   - paragraph 531 rev **1731** → `articles:block_1`
   - parent paragraph 520 rev **1732** → child target_revision_id `1731`
3. Renamed the Views display from `block_1` to `articles_front` (changing both the human label and the machine name).
4. Re-saved the paragraph with the new display, producing:
   - paragraph 531 rev **1750** → `articles:articles_front`
   - parent paragraph 520 rev **1751** → child target_revision_id `1750`
5. Started seeing repeating watchdog entries:
   - `setDisplay() called with invalid display ID "block_1"` (Views, Warning)
   - `Trying to access array offset on null in ViewsReferenceFieldFormatter->viewElements() line 160` (PHP, Warning)
   - `TypeError: CacheableMetadata::createFromRenderArray(): Argument #1 ($build) must be of type array, null given` at line 165 (PHP, Error)

The fatal/warning cycle fired every few minutes — driven by cron + core search re-indexing.

## Root cause

Renaming a Views display **does not propagate** to entities that already reference it. The viewsreference field stores the display ID as a string (`display_id` column on the field item). After the rename:

- `block_1` no longer existed as a display on the View.
- The field item's `display_id` value at revision 1731 still pointed at it.
- `Views::getView()` succeeded and returned the View.
- `$view->setDisplay('block_1')` failed because the display didn't exist → returned FALSE.
- `$view->buildRenderable()` returned `NULL` (per `ViewExecutable::buildRenderable()`'s early return).
- `ViewsReferenceFieldFormatter::viewElements()` then tried `$render_array['#cache']` (line 160) and `CacheableMetadata::createFromRenderArray($render_array)` (line 165) on null → warning + TypeError.

## Why the live front page was fine but cron / search choked

The paragraphs entity reference revisions field stores both `target_id` (entity ID) **and** `target_revision_id` (specific revision). The live published chain was clean:

```
paragraph 520 rev 1751 → child paragraph 531 rev 1750 → display_id "articles_front" ✓
```

But revision history held the rotten pair:

```
paragraph 520 rev 1732 → child paragraph 531 rev 1731 → display_id "block_1" ✗
```

Normal page rendering walks current revisions — so the front page worked. But Drupal core search's indexer + render isolation ended up touching the old chain (likely via a cached or queued item), and once cron's session got corrupted by a BigPipe write inside cron context, the indexer wedged at ~50% and kept re-running the same broken render on every cron tick.

## Fix used

DB sync from live (which had a clean state with no `block_1` baggage), then re-added the View Display paragraph against `articles:articles_front` from scratch. Warnings stopped immediately.

Alternative fixes that would also have worked, in increasing destructiveness:
- Identify and delete the broken paragraph revision rows (1731 and 1732) directly via SQL.
- Delete and recreate the paragraph entity through admin UI (orphans the old revisions — they remain in `paragraph_revision__*` tables but become unreachable from the live tree).

## Diagnostic queries that proved useful

```sql
-- Live-table pointers to a specific paragraph
SELECT entity_id, revision_id, parent_id, parent_type, parent_field_name
FROM paragraphs_item_field_data WHERE id = <PID>;

-- The viewsreference field's stored target_id + display_id (live + revisions)
SELECT entity_id, revision_id, field_view_display_target_id, field_view_display_display_id
FROM paragraph__field_view_display;

SELECT entity_id, revision_id, field_view_display_target_id, field_view_display_display_id
FROM paragraph_revision__field_view_display;

-- Find any revisions still using a (now-deleted) display ID
SELECT entity_id, revision_id, field_view_display_display_id
FROM paragraph_revision__field_view_display
WHERE field_view_display_display_id = 'block_1';

-- Find any LIVE references pinned to a specific paragraph revision
SELECT entity_id, revision_id, field_X_target_id, field_X_target_revision_id
FROM paragraph__field_X
WHERE field_X_target_revision_id = <BAD_REV_ID>;
```

`drush ws --severity=Warning --count=30` and `drush ws --severity=Error --count=30` showed the recurring `setDisplay() called with invalid display ID` + the line 160/165 traces — that pattern is the giveaway for this class of bug.

## Lesson — finalise the Views display name before binding

**Before pointing any viewsreference paragraph (or any other consumer) at a Views display, get the display's machine name to its final value.** Rename or re-create the display *first*, then save the paragraph. Once a paragraph stores a display ID, renaming the display silently strands that paragraph (and any of its revisions) on a non-existent ID.

If you've already saved the paragraph and need to rename the display, the safe sequence is:

1. Change the display's machine name in the Views UI.
2. Re-edit each paragraph that references it and re-pick the display from the dropdown so the new value is written.
3. Re-save the parent entity (so all paragraph references update to current revisions).
4. Either accept that old revisions still pin the dead display ID and live with the warnings until those revisions are pruned, or delete the affected paragraph revision rows directly via SQL.

## Things that were red herrings during debugging

- The SMTP "could not connect" errors and the "headers already sent" BigPipe error inside cron — real but unrelated to the viewsreference issue. Worth fixing for cron health (point Drupal mailer at ddev's Mailpit or use `devel_mail_log`), but they don't cause the viewsreference null-render.
- The `search_index_clear()` function — exists in older Drupal, not in 10/11. Use `/admin/config/search/pages` → "Re-index site" UI button, or `DELETE FROM search_dataset WHERE type='node_search'; DELETE FROM search_index WHERE type='node_search'; DELETE FROM search_total;` to start fresh.
