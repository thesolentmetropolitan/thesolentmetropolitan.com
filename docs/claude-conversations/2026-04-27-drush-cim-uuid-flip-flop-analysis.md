# `drush cim` showing Create + Delete pairs for the same configs (UUID flip-flop)

Encountered while applying the hero-tile updates from
`docs/claude-conversations/2026-04-26-BRIEF-update-hero-tiles.md` (issue
unscoped — was a build-pipeline change, not a GitHub-issue task).

## Symptom

After committing four new classy paragraph YAMLs (`hero_art_style_culture_festivals`,
`hero_art_style_culture_identity`, `hero_art_style_culture_outdoor_active`,
`hero_art_style_about_accessibility`) and one deletion (`hero_art_style_culture_outdoors`),
running `drush cim` displayed an unusual diff table:

```
+------------+---------------------------------------------------------------------------------+-----------+
| Collection | Config                                                                          | Operation |
+------------+---------------------------------------------------------------------------------+-----------+
|            | hero_art_style_about_accessibility                                              | Create    |
|            | hero_art_style_culture_festivals                                                | Create    |
|            | hero_art_style_culture_identity                                                 | Create    |
|            | hero_art_style_culture_outdoor_active                                           | Create    |
|            | structure_sync.data                                                             | Update    |
|            | hero_art_style_culture_outdoor_active                                           | Delete    |
|            | hero_art_style_culture_identity                                                 | Delete    |
|            | hero_art_style_culture_festivals                                                | Delete    |
|            | hero_art_style_about_accessibility                                              | Delete    |
+------------+---------------------------------------------------------------------------------+-----------+
```

Each new entity appeared as **both Create and Delete** in the same diff. Worry: the
import was caught in some kind of loop that wouldn't settle.

## Two distinct root causes — same symptom on local and prod

The flip-flop pattern came from two different problems with the same end-state
appearance.

### Local — missing `uuid:` in the new YAMLs

When I hand-wrote the four new YAML files, I followed the visible pattern of the
simplest existing classy YAMLs (`hero_art_style_culture_music.yml` and similar)
which **don't** include a `uuid:` field. Those existing files happen to work
because they were imported once-upon-a-time via a different path that
auto-generated UUIDs server-side; subsequent re-imports compare on machine ID
rather than UUID and the missing-uuid in YAML doesn't trip them up.

But for **brand-new** entities being imported for the first time, Drupal's
`StorageComparer` requires `uuid:` in the YAML. Without it:

- `drush cim` emits `Undefined array key "uuid" in StorageComparer.php:303` warnings
- The import is silently skipped
- `drush config:status` then shows the entries stuck on **"Only in sync dir"**
- No error is raised — only warnings — easy to miss

**Fix:** add a generated UUID to each new YAML. Order in the file should be
`uuid:` first, then `langcode:`, `status:`, `dependencies:`, then entity-specific
fields:

```yaml
uuid: 805f14bc-526a-4e64-8e00-33f89cf88c25
langcode: en
status: true
dependencies: {  }
id: hero_art_style_culture_festivals
label: 'Culture - Festivals'
classes: hero-art-style--culture-festivals
```

UUID generation: `uuidgen | tr '[:upper:]' '[:lower:]'` (macOS/Linux), or
`python -c 'import uuid; print(uuid.uuid4())'`.

### Production — UUID mismatch between YAML and DB

After the YAMLs were fixed locally, committed, pushed, and pulled on production,
running `drush cim` on prod showed the **same Create+Delete flip-flop pattern**.

What differed: production's DB **already held** the four entities, with
auto-generated UUIDs that differed from the ones now in the YAMLs.

This happened because an earlier `drush cim` had been run on prod *before* the
YAMLs were fixed. On that prod system (different drush/PHP version perhaps),
the import succeeded silently — Drupal generated server-side UUIDs and
inserted the entities — rather than failing silently as it had on local.
So:

- Prod DB: classy entities present with auto-generated UUIDs (e.g. some random
  UUID Drupal made up at import time)
- YAMLs: now carrying my local-generated UUIDs (e.g. `805f14bc-526a-...`)
- StorageComparer treats DB and YAML as **different entities** because the
  UUIDs don't match → wants to delete the DB ones and create the YAML ones
- Net diff display: 4 Creates + 4 Deletes for the same machine names

**Fix:** delete the conflicting DB rows so the YAMLs can import cleanly with
their own UUIDs. Safe because the binding script (which would attach these
entities to nodes via `field_classy`) hadn't been run on prod yet — no live
node referenced them.

```bash
./drush-dir/drush sqlq "DELETE FROM config WHERE name IN (
  'classy_paragraphs.classy_paragraphs_style.hero_art_style_culture_festivals',
  'classy_paragraphs.classy_paragraphs_style.hero_art_style_culture_identity',
  'classy_paragraphs.classy_paragraphs_style.hero_art_style_culture_outdoor_active',
  'classy_paragraphs.classy_paragraphs_style.hero_art_style_about_accessibility')"
./drush-dir/drush cr
./drush-dir/drush cim -y
./drush-dir/drush config:status
```

After this, `cim` showed the expected 4 Creates + 1 Update (for `structure_sync.data`),
imported cleanly, and `config:status` reported **"No differences"**.

Alternative fix not used: extract the UUIDs from the prod DB and update the
local YAMLs to match. Would have avoided the DELETE but required re-importing
locally too. The DELETE+import approach was simpler and produces a single
canonical UUID source going forward (the YAMLs).

## Diagnostic recipe for any "cim flip-flop" pattern

When `drush cim` shows the same item as both Create and Delete:

1. **Check for module interference first** (config_split, config_filter,
   config_ignore). If any are enabled, they're the most common cause of
   flip-flop diffs and operate by transforming the storage layer:
   ```bash
   drush pm:list --status=enabled --format=json | grep -iE 'config_split|config_filter|config_ignore'
   ```

2. **If no transformer modules**, check the YAML for `uuid:`:
   ```bash
   head -2 config/sync/<the-config-name>.yml
   ```
   Missing UUID → fix the YAML, see "Local" section above.

3. **If UUID is present in YAML**, check whether the DB also has a row with
   a *different* UUID:
   ```bash
   drush sqlq "SELECT name FROM config WHERE name = '<the-config-name>'"
   ```
   Row exists with mismatched UUID → see "Production" section above.

4. **Smoke test after either fix**:
   ```bash
   drush cim -y
   drush config:status
   ```
   `config:status` should report "No differences".

## Lessons captured to auto-memory

- `feedback_drupal_config_yaml_uuid.md` — when hand-writing a new config YAML,
  always include `uuid:` at the top to avoid silent cim failure.

## Lesson for next time

When creating new classy paragraph YAMLs (or any new config entity YAMLs),
**either**:
- Generate them via the admin UI then `drush cex` (UUID is added automatically), **or**
- Hand-write them with a freshly-generated UUID at the top.

Hand-writing without UUID looks fine on inspection (existing files in the repo
do the same and "work") but breaks for first-time imports. Mixing UUID-bearing
and UUID-less files in the same repo is a footgun that hides until you try to
import something brand-new.

## Things that were red herrings

- I initially suspected `config_split`, `config_filter`, or `config_ignore`
  modules — sensible first guesses for flip-flop diffs, but none were enabled
  in this project.
- Suspected multiple `config_directories` — only one declared in `settings.php`.
- Considered `structure_sync` involvement — it was in the diff (legitimate menu
  update), but doesn't manage classy_paragraphs configs.
