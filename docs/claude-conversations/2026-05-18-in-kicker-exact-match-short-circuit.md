# "in" kicker — defensive exact-match short-circuit — 2026-05-18

Defensive belt-and-braces fix in response to a report (issue [#279](https://github.com/thesolentmetropolitan/thesolentmetropolitan.com/issues/279)) that the "in" kicker occasionally re-appears on a section page for content whose primary topic IS that page's term — e.g. *"in MUSIC"* on `/culture/music` for an event with `field_primary_topic = Culture / Music`. After a `drush cr` the kicker disappears as intended; the report is that it intermittently comes back without an obvious trigger.

## What the existing logic does

`_customsolent_build_in_kicker($content_term, $page_term_depth)` returns NULL when the content's term IS the page's term — handled by the depth arithmetic:

```php
$display_from = $page_term_depth + 1;
if ($display_from >= count($chain)) {
  return NULL;          // Nothing left to display after stripping page-level ancestors.
}
```

For `/culture/music` (page_term_depth = 1) and content with primary `Culture / Music`:
- chain = `[Culture, Music]`, count = 2
- display_from = 1 + 1 = 2
- 2 ≥ 2 → return NULL → no kicker ✓

The view_display paragraph preprocess sets `page_term_depth` on the request before the teaser preprocess reads it. `customsolent_node_view_alter` adds `url.path` and `url.query_args:topic` as cache contexts on teaser builds so the rendered teaser cache fragments per page URL.

## What this commit changes

### 1. Stash the page's term tid alongside its depth

```php
$page_term_depth = -1;
$page_term_tid   = NULL;
if (($context['mode'] ?? '') === 'term' && !empty($context['term'])) {
  $page_term_depth = _customsolent_get_term_depth($context['term']);
  $page_term_tid   = (int) $context['term']->id();
}
\Drupal::request()->attributes->set('page_term_depth', $page_term_depth);
\Drupal::request()->attributes->set('page_term_tid',   $page_term_tid);
```

### 2. Short-circuit on tid equality in the teaser preprocess

```php
if (in_array($primary_tid, $page_tids, TRUE)) {
  $page_term_tid = \Drupal::request()->attributes->get('page_term_tid');
  if ($page_term_tid !== NULL && (int) $page_term_tid === $primary_tid) {
    // Exact match — no kicker regardless of what the depth arithmetic
    // in _customsolent_build_in_kicker would say.
  }
  else {
    $page_term_depth = (int) \Drupal::request()->attributes->get('page_term_depth', -1);
    $in_items = _customsolent_build_in_kicker($primary_term, $page_term_depth);
    if ($in_items) { /* set show_in_kicker, in_kicker_items, … */ }
  }
}
```

The equality check is independent of `page_term_depth`. If a future code path or stale request attribute somehow leaves `page_term_depth` at a wrong value, the tid comparison still catches the exact-match case.

### 3. Extend cache contexts to cover the `compact` view mode

`customsolent_node_view_alter` now adds `url.path` + `url.query_args:topic` for both `teaser` and `compact` (was teaser only). The compact template doesn't currently render the kicker, but the kicker variables ARE computed for compact in `customsolent_preprocess_node`, so the cache fragmentation matters for correctness if the compact card ever starts rendering kickers.

## Why a defensive layer at all

My controlled test on dev (visit /culture → /culture/music → /front → /culture/music → /sectors → /culture/music → /explore/events → /culture/music, fresh cache start) **never** reproduced the wrong kicker. The url.path cache context fragments correctly. So the existing depth-based logic IS doing its job in the scenarios I could reproduce.

But the report is reliable — Rob has seen the kicker flip-flop multiple times. Possible (unconfirmed) triggers:

- **Browser cache** showing stale HTML — hard refresh (Cmd+Shift+R / Ctrl+F5) should clear this without server-side action.
- A specific cache state during/after running `drush it` Full mode, or `drush cim` while the route is being rebuilt.
- An edge case in Drupal's render-cache backend timing.
- Some interaction with Views row caching that bypasses the entity render cache's url.path fragmentation.

The defensive equality short-circuit doesn't address WHY the wrong state would occur — it just ensures that whatever the state, the equality check prevents the kicker on exact match.

## How to test on live

Once deployed (`git pull` + `drush cr` — theme-only commit):

1. Browse around — /culture, /sectors, /explore/events, then /culture/music.
2. On /culture/music, primary-side events (with `field_primary_topic = Culture / Music`) should never show "in MUSIC" — only the title, the where/when rows, and the CTA.
3. If you ever see it come back: **capture the URL and a screenshot**, then `drush cr`. The screenshot + URL is the data we'd need to track down the underlying cause.

## Files changed

```
M  web/themes/custom/customsolent/customsolent.theme
   - view_display preprocess: stash page_term_tid alongside page_term_depth
   - preprocess_node teaser/compact: exact-match short-circuit on tid equality
   - node_view_alter: cache contexts also on compact view mode
```

## Related

- [#279](https://github.com/thesolentmetropolitan/thesolentmetropolitan.com/issues/279) — "in" kicker flip-flop report.
- `2026-05-17-term-display-in-kicker-implementation.md` — full kicker implementation.
- `2026-05-17-cache-staleness-and-drush-it-debug.md` — earlier cache-staleness diagnosis, applies here too.
