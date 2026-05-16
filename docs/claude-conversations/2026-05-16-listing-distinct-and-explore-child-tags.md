# Listing fixes: distinct dedup + Explore-child terms as content tags — 2026-05-16

Two related bug fixes landed in commit `feed030` on branch `fix_results_github_273`.

## Fix 1 — duplicate teasers in `view_display_primary_and_related` compositions

### Symptom

On `/culture/screen`, organisation node 204 (*Vrasp Acting School*) appeared **twice** in the listing — both copies on the related side. Same row, same node, two times.

### Cause

The `view_display_primary_and_related` paragraph template renders two views back-to-back: `view_display_primary_topic` (filtering on `field_primary_topic`) and `view_display_related_topics` (filtering on `field_related_topics`). Each view receives the page's topic scope as a `+`-joined contextual argument.

For `/culture/screen`, the scope expands to `[49 (Screen), 165 (Film Acting School)]` (Screen + its descendants). Node 204's `field_related_topics` contains BOTH terms. Views' SQL JOIN across the multi-value field produces one row per matching value, so a node that matches on multiple argument terms appears multiple times.

### Fix

Enable `distinct: true` on the query options of all four listing views (`articles_listing`, `events_listing`, `links_listing`, `organisations_listing`). The query gains `SELECT DISTINCT`, the JOIN still happens but duplicate rows are coalesced.

```yaml
query:
  type: views_query
  options:
    query_comment: ''
    disable_sql_rewrite: false
    distinct: true          # was: false
    replica: false
    query_tags: {  }
```

Standard Drupal pattern for multi-value contextual filter dedup. No performance concern at this scale — the views return tens of rows, not thousands.

## Fix 2 — Explore-child terms treated as content tags when the editor explicitly chooses them

### Symptom

`/explore/data` was listing ALL organisations (40+ teasers) instead of only the 3 nodes tagged with *"Explore / Data"* (term 106) in `field_primary_topic` or `field_related_topics`. The section_filter on the page had `field_topic` set to 106, but it was being ignored.

### Cause

The original brief's all-topics fallback fired for any term whose top-level parent was Explore or About — those were designated "structural" terms (used for landing pages, not content tagging). That's correct for `/explore/events`, `/explore/articles`, `/explore/orgs-directories` (which aggregate content of a type across Culture/Sectors/Living).

But editors are also using some Explore children as REAL content tags:
- `/explore/data` — orgs tagged with *"Explore / Data"*
- `/explore/opinion` — articles tagged with *"Explore / Opinion"*
- `/explore/archive` — past events (future)

The Explore guard couldn't tell these apart from the structural ones, so it fell back to all-topics for both kinds.

### Fix

Two changes in `_customsolent_resolve_topic_context()`:

1. **`field_topic` on the paragraph escapes the guard.** If the editor explicitly sets `field_topic` on a section_filter or view_display paragraph, that term is used unconditionally — the editor's explicit choice is honoured even when the term's top-level is Explore/About.

2. **Host's `field_primary_topic` still has the guard.** When neither paragraph sets `field_topic` and we fall back to the host node's primary topic, the Explore/About guard still applies. This preserves the structural behaviour for `/explore/events` etc., which rely on host-primary inheritance.

```php
// Priority 1: field_topic on the paragraph — unconditional, no guard.
if ($paragraph->hasField('field_topic') && !$paragraph->get('field_topic')->isEmpty()) {
  $term = $paragraph->get('field_topic')->entity;
  if ($term) {
    return _customsolent_topic_context_for_term($term, $with_children);
  }
}

// Priority 2: host's field_primary_topic — Explore/About guard still applies.
if ($host && $host->getEntityTypeId() === 'node'
  && $host->hasField('field_primary_topic')
  && !$host->get('field_primary_topic')->isEmpty()) {
  $term = $host->get('field_primary_topic')->entity;
  if ($term) {
    $top = _customsolent_topic_top_level($term);
    $top_name = $top ? $top->getName() : '';
    if ($top && !in_array($top_name, ['Explore', 'About'], TRUE)) {
      return _customsolent_topic_context_for_term($term, $with_children);
    }
  }
}

return _customsolent_topic_context_all($with_children);
```

### Bonus: topic-anchor inheritance

The view_display preprocess now defers to a sibling **section_filter** paragraph when its own `field_topic` is empty. Editors set `field_topic` once on the section_filter (the canonical "topic anchor" for the page) and the listings on the same page follow automatically — no need to set `field_topic` twice.

```php
// In customsolent_preprocess_paragraph__view_display():
$anchor_paragraph = $paragraph;
$has_own_topic = $paragraph->hasField('field_topic')
  && !$paragraph->get('field_topic')->isEmpty();
if (!$has_own_topic && $host) {
  $sf = _customsolent_find_section_filter_on_host($host);
  if ($sf && $sf->id() !== $paragraph->id()) {
    $anchor_paragraph = $sf;
  }
}
$context = _customsolent_resolve_topic_context($anchor_paragraph, $host);
```

New helper `_customsolent_find_section_filter_on_host($host)` returns the first section_filter paragraph anywhere on the host's paragraph reference fields (recursively, up to 4 levels deep). The existing `_customsolent_section_filter_exists_on_host()` boolean check now wraps it.

## Editor model — three patterns for an /explore landing

After these fixes, /explore landings break into three patterns based on what the editor wants:

| Pattern | Example | Section filter `field_topic` | view_display `field_topic` | Result |
|---|---|---|---|---|
| Structural landing (all content of a type) | `/explore/events`, `/explore/orgs-directories` | empty | empty | host primary is under Explore → guard fires → all-topics fallback. Listing shows all events / orgs / etc. |
| Content-tag landing (only content tagged with this term) | `/explore/data`, `/explore/opinion` | set to the term (e.g. 106) | empty (inherits via section_filter sibling) | term scope. Listing shows only content with that term in primary OR related. |
| Custom mix | rare — content tag with override | set to one term | set to a different term | view_display uses its own field_topic, ignores the section_filter. |

In the first two patterns the editor only needs to think about the section_filter — the view_display paragraph(s) on the same page follow automatically.

## Verification — what to check after the deploy

| URL | Expect |
|---|---|
| `/culture/screen` | Org listing — node 204 appears **once**, on the related side. |
| `/explore/data` | 3 orgs/links matching term 106 (was 40+). |
| `/culture`, `/culture/music`, `/sectors` | Listings unchanged. |
| `/explore/events` | All-events listing unchanged. |

## Deploying to live

Standard config-only deploy. The change touches one PHP file (the theme) and four config YAMLs.

```bash
# On live (SSH / hosting console)

# 1. Pull latest code. Confirm commit feed030 is present on the deploy branch.

# 2. Capture any UI-side config that's not in YAML yet.
drush cex -y
git status            # If any of articles_listing.yml / events_listing.yml
                      # / links_listing.yml / organisations_listing.yml shows
                      # a diff, that's a UI edit on live. Decide whether to
                      # keep it or `git checkout --` it before cim.

# 3. Import.
drush cim -y          # Expect: Update on four listing views (and any other
                      # config you intentionally exported in step 2).

# 4. Cache.
drush cr

# 5. Spot-check the four URLs in the table above.
```

## Files changed

```
M  config/sync/views.view.articles_listing.yml       (distinct: true)
M  config/sync/views.view.events_listing.yml         (distinct: true)
M  config/sync/views.view.links_listing.yml          (distinct: true)
M  config/sync/views.view.organisations_listing.yml  (distinct: true)
M  web/themes/custom/customsolent/customsolent.theme
   (resolver: field_topic escapes the Explore/About guard;
    new helper _customsolent_find_section_filter_on_host();
    view_display preprocess: anchor-paragraph inheritance from sibling section_filter)
```

## Aside — why `view_display_primary_and_related` has no contextual filters in its YAML

Drupal Views doesn't natively support OR logic between two *contextual* filter fields. You can't say "filter rows where the argument matches `field_primary_topic` OR matches `field_related_topics`" in a single display — each display takes a single contextual argument and matches against one field configuration.

Three workarounds existed (per the original brief, section 2):

- **A — Render two displays together.** Keep `view_display_primary_topic` and `view_display_related_topics` as they are; have the paragraph template render both back-to-back. CSS handles the duplicate exposed-filter form; the recently-added `distinct: true` handles duplicate rows within each side.
- **B — Custom Views filter plugin.** Write a small custom module providing a contextual-filter handler that accepts a term id and checks both fields with OR logic. The cleanest data-layer solution but adds a module + tests.
- **C — Views relationship + regular filter with OR grouping.** Add two relationships (one per field), then use a non-contextual filter group with OR. More configuration in Views UI but stays within Views.

**Option A was chosen as MVP** — no new module, no Views-internals complexity. The trade-off is the placeholder display in YAML.

The `view_display_primary_and_related` display IS defined in `views.view.<name>.yml` but has no `display_options` worth speaking of:

```yaml
view_display_primary_and_related:
  id: view_display_primary_and_related
  display_title: 'View Display Primary and Related Topics'
  display_plugin: block
  position: 5
  display_options:
    display_description: ''
    display_extenders: {  }
  cache_metadata: { … }
```

It exists for one reason only: the **viewsreference** field on the View Display paragraph type lets editors pick a (view, display) pair. Drupal validates that the display id is real. The placeholder lets the id be selectable in the editor UI.

When `drupal_view($view, 'view_display_primary_and_related', $arg)` is called, the paragraph template **intercepts before that ever happens** and instead calls `drupal_view()` twice against the two real displays:

```twig
{% if did == 'view_display_primary_and_related' %}
  <div class="slnt-composition__primary">
    {{ drupal_view(vname, 'view_display_primary_topic', primary_topic_tid) }}
  </div>
  <div class="slnt-composition__related">
    {{ drupal_view(vname, 'view_display_related_topics', primary_topic_tid) }}
  </div>
{% else %}
  {{ drupal_view(vname, did, primary_topic_tid) }}
{% endif %}
```

So contextual filters live on `view_display_primary_topic` (filtering `field_primary_topic_target_id`) and `view_display_related_topics` (filtering `field_related_topics_target_id`). The placeholder doesn't need any because it never actually runs as a Views display.

If you ever want to switch to Option B (custom Views filter plugin), the placeholder could become a real display with that plugin attached, and the paragraph template's special-case branch would collapse back to a single `drupal_view()` call.

## Future refactor — move the topic-resolution logic out of `customsolent.theme`

`customsolent.theme` now carries ~990 lines including the entire topic-resolution chain (term hierarchy walking, scope-id computation, section_filter sibling lookup, filter URL preservation, form-alter hooks, paragraph preprocesses, helpers). It's grown beyond what a `.theme` file is comfortable with.

When time permits, this can move to a custom module — e.g. `web/modules/custom/customsolent_listings/`. Benefits:

- Separation of concerns: theme renders, module decides what to render.
- Services-based DI rather than procedural drush-style helpers — easier to test in isolation.
- Drupal upgrade story: modules survive theme changes / replacements; helpers parked in `.theme` would need re-migrating.
- A custom Views filter plugin (Option B above) could live there, eventually retiring the placeholder-display convention.

Not urgent. The current code works and is consistent; a refactor would be cosmetic until the codebase grows further.

## Related docs

- `2026-05-13-section-listing-enhancements-brief.md` — the original brief that introduced the Explore/About guard and the Option A placeholder approach.
- `2026-05-13-section-listing-enhancements-implementation.md` — first-round implementation.
- `2026-05-14-section-listing-iteration-implementation.md` — combined topic + date filtering work where the resolver got its current shape.
