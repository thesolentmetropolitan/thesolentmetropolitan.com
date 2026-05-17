# Cache staleness on teaser kickers, and `drush it` hangs — 2026-05-17

Two reference items from a debug session.

## #1 — Why teaser kickers sometimes look "stuck" on the wrong page

### Symptom

On /culture/music, primary-side events (whose primary topic IS Music — exact match with the page term) appeared with an `in MUSIC` kicker when they shouldn't have one at all. Same nodes also showed `in <SUBTERM>` on the related side instead of the expected `from <FULL CHAIN>` kicker.

Meanwhile organisations on the same page rendered correctly: SoCo Music (primary = Music, exact match) had no kicker, and Stereo Underground Radio Show (related side) showed `from Culture / Radio & Podcast`. Same view structure, same paragraph, same code path — different result per content type.

### Cause

Stale teaser render cache. The teasers had been rendered on a different page (/culture, where `page_term_depth = 0`) at a moment before today's cache contexts were fully in place. When /culture/music subsequently rendered, Drupal returned the cached version from /culture, with the kicker computed for the wrong page depth.

The events vs orgs split was just an accident of access patterns: events had been cached during earlier development; orgs hadn't.

### How the cache fragments are *supposed* to work

`customsolent_node_view_alter` adds two cache contexts to every teaser build:

```php
$build['#cache']['contexts'][] = 'url.path';
$build['#cache']['contexts'][] = 'url.query_args:topic';
```

These make the teaser render cache fragment per page URL. After a fresh `drush cr` and controlled navigation (visiting /culture, then /culture/music) the kickers render correctly per page — verified empirically.

So the code works for fresh state. The "stuck" cache scenario only happens when:

1. The cache fills under one set of preprocess logic (e.g. before `page_term_depth` existed)
2. Code changes
3. Cache isn't cleared between (1) and the next visit

A `drush cr` after deploying changes that affect kicker computation will sort it.

### When to expect this and what to do

Whenever you change anything in `customsolent_preprocess_node`, the in-/from-kicker helpers, or the view_display preprocess where `page_topic_tids` / `page_term_depth` are set — clear cache afterwards:

```bash
drush cr
```

For deploys to live, the kicker work's deploy snippet (in `2026-05-17-term-display-in-kicker-implementation.md`) already includes `drush cr` as step 4. Always run it.

If a specific URL is still showing the wrong kicker after `drush cr` and a hard refresh, the next things to check are:

- The host node's `field_primary_topic` — has it changed recently?
- The taxonomy term parent of the content's primary topic — see the recurring 2026-05-16 issue with term 162.
- The View display selected on the View Display paragraph — must be `view_display_primary_and_related` (or a similar contextually-filtered display), not `view_display_events_page` etc.

### Going forward

For most editorial work and bug fixes, the existing cache contexts (`url.path` + `url.query_args:topic`) are enough and a `drush cr` after structural changes covers the rest. If stale-cache issues become frequent, the next architectural step would be a **custom cache context** that captures `page_topic_tids` + `page_term_depth` directly — that would key the cache on the actual computation inputs rather than the URL proxy. Not urgent; flagged for a future custom-module refactor.

---

## #2 — `drush it` (structure_sync taxonomy import) hangs at the end

`drush it` = `drush structure-sync:import-taxonomies`. On larger vocabularies, the command can appear to hang on the *final stages* of the import — actual progress, just slow under verbose post-processing.

### The actual culprit on this site: silent `drupal_flush_all_caches()`

Confirmed by reading `web/modules/contrib/structure_sync/src/Controller/TaxonomiesController.php`:

- **Full mode** (line 497) ends with `drupal_flush_all_caches()`. On Drupal 11 with many modules this routinely takes 30–90+ seconds.
- **Safe mode** (line 658) skips the cache flush entirely — that's why Safe completes immediately.
- The module *intends* to emit a `Flushing all caches` notice (line 494) but the check is `if ($context['drush'] === TRUE)`. The drush command sets `'drush' => TRUE` in the form-options array (`StructureSyncCommands.php` line 66), but Drupal's Batch API passes its own `$context` to operations — the form-options flag never reaches the batch context. So the notice never fires and the flush runs invisibly.

**So when `drush it` looks hung at the end of Full mode, it's almost certainly the silent flush. Wait 1–3 minutes before assuming a real hang.**

### What Safe vs Full vs Force actually do

| Mode | Creates new terms | Updates existing terms | Deletes terms not in YAML | Cache flush at end |
|---|---|---|---|---|
| **Safe** | Yes | **No** — skips if term name already exists | No | No |
| **Full** | Yes | Yes — name, parent, description, weight | Yes (terms removed from YAML) | Yes — `drupal_flush_all_caches()` (slow) |
| **Force** | Yes | Yes (after deleting everything first) | Effectively yes (deletes all first) | Yes |

**Practical recommendation for routine work:**

- Adding *brand-new* terms only → **Safe** is fast.
- Changing a parent, name, description, weight on an *existing* term → **Full** (Safe won't apply it).
- Removing terms → **Full**.

### Bypassing the interactive prompt

The interactive `Full / Safe / Force` prompt can be skipped with a flag:

```bash
drush it --choice=full
drush it --choice=safe
drush it --choice=force
```

This is also what makes `2>&1 | tee log` work without the `NonInteractiveValidationException` — no prompt, no need for a TTY.

### Capturing verbose output while keeping the prompt working

If you DO want interactive selection AND a saved log:

```bash
# A — script (built-in on macOS): records the terminal session
script -q /tmp/drush-it.log ddev drush it -vvv

# B — unbuffer (brew install expect): cleaner log
unbuffer ddev drush it -vvv | tee /tmp/drush-it.log

# C — leave stdout for the prompt, log stderr only
ddev drush it -vvv 2> >(tee /tmp/drush-it-stderr.log >&2)
```

Or just skip the prompt entirely with `--choice=full`:

```bash
drush it --choice=full -vvv 2>&1 | tee /tmp/drush-it.log
```

### If you've killed Full mid-flush

Terms are saved to the DB *during* the import loop, well before `drupal_flush_all_caches()` runs. So killing Full mid-flush doesn't lose data — it just leaves a stale cache. Recover with:

```bash
drush cr
```

That's it. No data loss, no need to re-import.

### Other things that can slow Full mode

Beyond the core cache flush, on some sites these post-save hooks also fire on each term save (during the loop, not at the end). If individual term saves take seconds rather than ms, one of these is the cause:

Quick checks while it's hanging:

```bash
# CLI memory limit — bump if it's tight (typically 256M or higher)
drush php:eval "echo ini_get('memory_limit') . PHP_EOL;"

# Recent watchdog warnings and errors
drush watchdog:show --severity=warning --count=30

# DB processlist — look for locks (MySQL/MariaDB)
drush sqlq "SHOW PROCESSLIST" | grep -iE "lock|waiting"

# What's PHP doing — needs SSH access to the box where drush runs
ps aux | grep -i drush
top -p $(pgrep -f drush)
```

### Common hang causes

| Cause | Symptom in `-vvv` log | Fix |
|---|---|---|
| Pathauto regenerating URL aliases | "creating alias" / "pathauto" lines repeated for every term | Temporarily disable pathauto term updates (see workaround below) |
| Search API reindexing | "search_api" / "indexing entity" lines | Pause the index, run `drush it`, then re-index |
| Cache invalidation cascade | Long pause after the last term save, no log output | Pre-warm of cache tags or just patience — usually completes |
| Term hierarchy rebuild | "rebuilding hierarchy" or no specific message | Drupal 11's `taxonomy_term__parent` table gets recomputed at end of run |
| PHP memory exhaustion | Stops at a term, then watchdog records "Allowed memory size … exhausted" | Raise `memory_limit` in `php.ini` or via `--php-options="-d memory_limit=512M"` |
| MySQL row lock | `SHOW PROCESSLIST` shows lock-wait state | Investigate concurrent process; usually another drush command or a queue worker |

### Workaround — disable pathauto during import

Pathauto term updates are the most common slowdown. Disable temporarily, import, re-enable, then regenerate aliases in one batch (much faster than per-term):

```bash
# Note current setting (default is 2 — "Update existing")
drush config:get pathauto.settings update_action

# Temporarily disable update on term save (0 = "Do nothing")
drush config:set pathauto.settings update_action 0 -y

# Run the import
drush it

# Restore
drush config:set pathauto.settings update_action 2 -y

# Regenerate all term aliases in one pass
drush pathauto:aliases-generate canonical taxonomy_term -v
```

### Workaround — pause Search API indexing

```bash
# List index ids
drush sapi:list

# Pause tracking on the index that includes taxonomy terms (replace <index-id>)
drush sapi:disable-tracker <index-id>

# Run the import
drush it

# Re-enable and reindex in one batch
drush sapi:enable-tracker <index-id>
drush sapi:index
```

### Don't kill it too early

Even with no log output for 30–60 seconds, the command might still be working. The cache-invalidation cascade at the end of a large import can be silent. Give it a couple of minutes before Ctrl-C — pulling the plug mid-write can leave the taxonomy in an inconsistent state (different terms saved, hierarchy rebuild incomplete).

If you do kill it, follow up with:

```bash
drush cr
drush updb -y           # if any pending updates
drush php:eval "\Drupal::service('entity_type.manager')->getStorage('taxonomy_term')->resetCache();"
```

Then re-run `drush it`.

### When to escalate

If `-vvv` shows the import looping on the same term, or memory grows without bound, capture the last 200 lines of the log and the term id involved, and we can patch around it (sometimes a single corrupted term reference holds up the whole import).
