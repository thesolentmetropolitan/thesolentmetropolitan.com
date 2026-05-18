# Cross-display dedup for primary + related composition — 2026-05-19

Issue [#280](https://github.com/thesolentmetropolitan/thesolentmetropolitan.com/issues/280). Some events were appearing twice on `/explore/events` (node/91, which renders `events_listing` via the `view_display_primary_and_related_topics` composition). Examples Rob flagged:

- node/233 — `field_primary_topic: 68`, `field_related_topics: 35`
- node/217 — `field_primary_topic: 55`, `field_related_topics: 44`

Each appeared once in the primary panel *and* once in the related panel of the same page.

## Why

The `view_display_primary_and_related` selection on the View Display paragraph isn't a real Drupal Views display — it's a placeholder. The paragraph template intercepts it and renders two real displays back to back:

```twig
{# templates/content/paragraph--view-display.html.twig #}
{% if did == 'view_display_primary_and_related' %}
  {{ drupal_view(vname, 'view_display_primary_topic',   primary_topic_tid) }}
  {{ drupal_view(vname, 'view_display_related_topics',  primary_topic_tid) }}
{% endif %}
```

Both displays receive the same contextual filter argument (`primary_topic_tid`) but apply it to different fields:

- `view_display_primary_topic` filters on `field_primary_topic IN args`.
- `view_display_related_topics` filters on `field_related_topics IN args`.

A node whose primary topic AND at least one related topic both fall inside the page scope is matched by both queries — so it appears in both rendered panels.

The duplication was most visible on `/explore/events` because its scope is "all topics" (Culture + Sectors + Living + descendants), so *every* event with both fields populated double-rendered. The same bug would surface on a section page (e.g. `/culture`) any time a node had both `field_primary_topic = Culture/Music` and `field_related_topics` containing another Culture child.

## Why Distinct doesn't help

Drupal Views' **Distinct** query setting dedupes rows *within a single query*. It can't see across two independently-executed display renders. With or without Distinct, each display's own results are unique — and the duplication arises from rendering them both on the same page. There's no SQL-level expression of the constraint until you alter one of the queries against the other's keys.

## Fix shape

Application-level: a `hook_views_query_alter` that, when `events_listing.view_display_related_topics` is about to run, looks up every node whose `field_primary_topic` is in the same argument scope (those are the nodes the primary display would render) and adds them as a `NOT IN` filter on the related-topics query.

That way the related panel only renders nodes that *wouldn't* already show in the primary panel. Done at query time, so pagination counts the deduped result (the alternative — filtering at render time via `pre_render` — would leave the related view's per-page count and pager confusing).

Lives in the existing `customsolent_helpers` module (already established as the home for site-specific Views/render glue):

```php
function customsolent_helpers_views_query_alter(\Drupal\views\ViewExecutable $view, \Drupal\views\Plugin\views\query\QueryPluginBase $query) {
  $deduped_displays = [
    'events_listing.view_display_related_topics',
  ];
  $key = $view->id() . '.' . $view->current_display;
  if (!in_array($key, $deduped_displays, TRUE)) return;
  if (!$query instanceof \Drupal\views\Plugin\views\query\Sql) return;
  if (empty($view->args)) return;

  $arg = (string) $view->args[0];
  $tids = (strpos($arg, '+') !== FALSE) ? explode('+', $arg) : [$arg];
  $tids = array_filter(array_map('intval', $tids));
  if (empty($tids)) return;

  $exclude_nids = \Drupal::database()->select('node__field_primary_topic', 'p')
    ->fields('p', ['entity_id'])
    ->condition('p.field_primary_topic_target_id', $tids, 'IN')
    ->execute()
    ->fetchCol();

  if (empty($exclude_nids)) return;
  $query->addWhere(0, 'node_field_data.nid', $exclude_nids, 'NOT IN');
}
```

The "exclude nids" lookup is a single indexed query against `node__field_primary_topic` (cheap) rather than nesting a SQL subquery in the Views query (which is brittle across database backends and Drupal Views' query builder). One extra round-trip — negligible at this site's scale.

## Verification

Before: `/explore/events` rendered 15 node references but only 13 unique nodes (233 and 217 each appeared twice).

After:

```
/explore/events:    15 unique nodes / 15 references / 0 duplicates
/culture:           15 unique nodes / 15 references / 0 duplicates
```

Both 233 and 217 still appear — they're just rendered once each, by the primary display (because their primary topic IS in the page's scope, the primary display picks them up; the related display now skips them).

## When to extend

The `$deduped_displays` array currently contains only `events_listing.view_display_related_topics`. If the same primary + related composition gets wired up for the other listing views (articles, organisations, links), add their IDs to the array:

```php
$deduped_displays = [
  'events_listing.view_display_related_topics',
  'articles_listing.view_display_related_topics',
  'organisations_listing.view_display_related_topics',
  'links_listing.view_display_related_topics',
];
```

No other changes needed — the lookup assumes both `field_primary_topic` and `field_related_topics` are taxonomy entity-reference fields, which is true for all four bundles.

## Deploy

Pure code change, no config touched:

```bash
git pull
drush cr
```

No `drush cim` needed.

## Files

```
M  web/modules/custom/customsolent_helpers/customsolent_helpers.module
   + customsolent_helpers_views_query_alter()
```

## Related

- [#280](https://github.com/thesolentmetropolitan/thesolentmetropolitan.com/issues/280) — original report (duplicate events on `/explore/events`).
- [#279](https://github.com/thesolentmetropolitan/thesolentmetropolitan.com/issues/279) — earlier `customsolent_helpers` work (lazy_builder for topic kickers); same module hosts both pieces of site-specific Views glue.
- `2026-05-18-kicker-lazy-builder-fix.md` — where the `customsolent_helpers` module was first introduced.
- `paragraph--view-display.html.twig:105` — where the `view_display_primary_and_related` virtual display is intercepted and the two real displays are rendered.
