# Primary + Related composition — architectural fix evaluation — 2026-05-26

Related issues: [#307](https://github.com/thesolentmetropolitan/thesolentmetropolitan.com/issues/307), [#284](https://github.com/thesolentmetropolitan/thesolentmetropolitan.com/issues/284). Follows on from the earlier patchwork dedup in [#280](https://github.com/thesolentmetropolitan/thesolentmetropolitan.com/issues/280) (see `2026-05-19-primary-and-related-cross-display-dedup.md`).

## Original prompt (Rob, 2026-05-26)

> Good evening Claude, thank you for all your help so far, next assignment please: here's some bugs with the output using view_display_primary_and_related view display on various views
>
> **Item 1.** Introduction: view organisations_listing view, with its view_display_primary_and_related view display, this is used in sectors node 19 in the field_view_display
>
> The problem is that Pagination is being displayed twice on /sectors and some items are being repeated. For those nodes that are being repeated, the reason for the repeat is when the node's field_primary_topic has a sub term off sector and also the node's field_related_topics has another sub term off sector. E.g. node 333 , field_primary_topic = 153 and field_related_topics contains 70. This is deliberate and intentional that a node has a sector sub term in both fields.
>
> This is related to the pagination being displayed twice on /sectors. Essentially I think we have 2 lists showing: the first list and pagination picks up the nodes with sector sub term in field_primary_topic. Then, appended onto this, the 2nd list is picking up nodes with sector sub term in field_related_topics. In that 2nd list, some of the nodes also appear in the 1st list, because those nodes have a sector sub term in field_primary_topic and in field_related_topics.
>
> The fix for this needs to, before displaying, join both lists as a superset list in an appropriate data structure and dedupe the nodes that appear more than once. And then sort the superset list alphabetically, ascending - starting from A through to Z, and have the pagination apply to the de-duped superset list.
>
> This problem also occurs in /living for the same reason, and probably would occur in /culture if we were listing organisations there, but we're not yet.
>
> **Item 2.** Problem: Potentially related to 1. In sub-menu item pages in /sectors with many items, like /sectors/media pagination is occurring in what appears to be the middle of the list. But what is actually happening is that the nodes that match sectors / media term (id = 81) in their field_primary_topic are listed first, and there's pagination for those, and then on the same page, nodes that have sectors / media term (id = 81) in their field_related_topics are listed afterwards, as if they are appended to the list.
>
> The fix, partially similar to item 1 above, is that the fix here needs to, before displaying, join both lists as a superset list in an appropriate data structure and then sort then A-Z and have the pagination apply to the de-duped superset list.
>
> **Item 3.** Introduction: In /culture (node 18) in field_view_display, events_listing View's View Display view_display_primary_and_related is being used.
>
> Problem: For event nodes that have a sub term of culture in their field_related_topics, they are listed in a separate list, appended onto the end of the first list, where in that 1st list, event nodes have a culture sub term in their field_primary_topic. This appending means that event nodes with a sub term of culture in their field_related_topics that are earlier than items of the first list, will appear later, i.e. out of order. E.g. node 251 which has node 43 culture sub term, is on Friday 18 September 2026, yet it appears AFTER a node 401 which has node 49 culture sub term in its field_primary_topic.
>
> Architectural thinking about items 1, 2 and 3. A common theme is the stuck together lists. An elegant solution might be to be able to have conditional contextual filters in the View, i.e. match by term in field_primary_topic OR the term in field_related_topics but not both. This, instead of bespoke code. The closest module I've found so far that might do this, but I don't know, could be: https://www.drupal.org/project/views_contextual_filters_or .
>
> So the possible solutions to items 1, 2 and 3, are:
>
> 1. Fix the bespoke code we've we've written
> 2. Evaluate https://www.drupal.org/project/views_contextual_filters_or plus an additional bespoke code separately in our code to support our specific case
> 3. Make our own custom module, that we could potentially contribute back to the community with some bespoke code separately for our specific case that the community might not use

## Current architecture (what's actually happening)

The "primary and related" composition is **not** a real Views display — it's a virtual stub. Three pieces collaborate:

1. **The stub display** `view_display_primary_and_related` is declared in each of three view configs (`config/sync/views.view.organisations_listing.yml`, `views.view.events_listing.yml`, `views.view.links_listing.yml`) with no fields, no filters, no sorts — just enough metadata for the viewsreference editor dropdown to list it.
2. **The paragraph template** `web/themes/custom/customsolent/templates/content/paragraph--view-display.html.twig:104-121` intercepts that display id and fires *two* `drupal_view()` calls back-to-back:
   ```twig
   {% if did == 'view_display_primary_and_related' %}
     <div class="slnt-composition__primary">
       {{ drupal_view(vname, 'view_display_primary_topic',   primary_topic_tid) }}
     </div>
     <div class="slnt-composition__related">
       {{ drupal_view(vname, 'view_display_related_topics', primary_topic_tid) }}
     </div>
   {% endif %}
   ```
3. **A query alter** `web/modules/custom/customsolent_helpers/customsolent_helpers.module:50-117` adds a `NOT IN` constraint to `events_listing.view_display_related_topics` so events already shown in the primary panel don't repeat in the related panel.

## Why the three reported bugs fall out of that design

- **Item 1 — /sectors and /living, organisations duplicated, two pagers.** The cross-display dedup hook is wired *only* for `events_listing.view_display_related_topics`. `organisations_listing` was never added to the `$deduped_displays` array at `customsolent_helpers.module:78`, so its related display happily re-renders nodes that already appeared in the primary display.
- **Item 2 — /sectors/media, mid-page pager.** Two displays = two SQL queries = two independent pagers. Even with perfect dedup, the user sees pager-A, then a chunk of related-list rows, then potentially another pager-B. No amount of patching the existing code merges those into one pager.
- **Item 3 — /culture, events out of chronological order.** Each display sorts its own result set. A `field_when_value ASC` sort in the related-topics display starts a fresh ordering at the top of the second list. Events whose dates would have interleaved with the primary list are stranded below it. Again, structural — not fixable in the current shape.

The earlier "patch the query" approach (the `NOT IN` alter) was the right call for Item 1's *duplication* but cannot reach Items 2 or 3, because both stem from "two separately-paginated, separately-sorted queries rendered sequentially". You cannot merge-sort across paginated result sets after the fact without re-querying.

## Solution options

1. **Patch existing code.** Add `organisations_listing.view_display_related_topics` (and likely `links_listing.view_display_related_topics`) to the `$deduped_displays` array. Closes Item 1's duplication; leaves Items 2 and 3 unfixable.
2. **`views_contextual_filters_or` + collapse to a single real display.** Make `view_display_primary_and_related` a real display with OR'd contextual filters on the two fields, distinct query, single pager, single sort. Delete the back-to-back template branch and the query alter.
3. **Bespoke custom module.** Implement the OR-rewrite ourselves via `hook_views_query_alter`. ~30 lines of code, no contrib dependency, but reinvents what option 2 buys for free.

Recommendation: **Option 2.** It addresses all three items in one structural change, removes more code than it adds, and uses a security-covered contrib module. Option 3 is the fallback if the module audit (below) surfaces a blocker.

## `views_contextual_filters_or` compatibility check

| Concern | Finding |
| --- | --- |
| Drupal 11 support | Yes — `drupal: ^10 \|\| ^11`. |
| Latest release | 8.x-1.5, 2025-01-13. |
| Security coverage | Stable release covered by the Drupal Security Team. |
| Active installs | ~10,259. |
| Maintenance status | "Minimally maintained" — issues are watched, fast responses not guaranteed. Yellow flag, not a blocker. |
| Issue queue | 10 open of 57 total. |
| PHP minimum | Not stated on the project page; verify when installing. |
| Notable caveat | Project page warns "After enabled this module there's a possibility existing views to break down." The module rewrites Views' filter-group operator handling, so every other view should be smoke-tested once after install. |

## Migration sketch

> **Correction — 2026-05-30.** The original migration sketch described an "OR-group UI" where contextual filters get dragged into a group, and instructed setting Distinct per display. **Both were wrong.** After reading the module source (`web/modules/contrib/views_contextual_filters_or/src/Plugin/views/query/ExtendedSql.php`):
>
> - The module's UI is **a single checkbox** under `Advanced → Query settings`, labelled **"Contextual filters OR"**. There is no drag-and-drop OR group. The checkbox flips the operator on WHERE group 0 (where contextual filters land) from AND to OR. In our three target views, the standard published + bundle filters live in `group: 1`, so they remain AND'd correctly against the OR'd contextuals.
> - **Distinct is already set on the default display's query** in all three views (`distinct: true` at the default `query.options.distinct` level), so every display inherits it automatically. No per-display override needed.
>
> Step 2 below has been amended to reflect this. The UI-walk version of the same instructions appears in the conversation log for 2026-05-30.

Current state recap:

- 3 views carry the stub display: `organisations_listing`, `events_listing`, `links_listing`.
- Template branch at `paragraph--view-display.html.twig:104-121`.
- Query alter at `customsolent_helpers.module:50-117` (events only).

Target state: `view_display_primary_and_related` becomes a real display with one query, one pager, one sort. The two existing sub-displays (`view_display_primary_topic`, `view_display_related_topics`) stay — other paragraphs still pick them individually.

Steps, in order:

1. **Install the module.**
   ```sh
   composer require drupal/views_contextual_filters_or
   drush en views_contextual_filters_or
   drush cr
   ```
   Then walk the existing views (`/admin/structure/views`) and confirm no AND/OR filter-group regressions on the other views. The smoke test is required, not optional — see the project's own warning.
2. **Flesh out `view_display_primary_and_related` in each of the three view configs.** For each view (organisations_listing, events_listing, links_listing):
   - Override the pager / style / row / empty-area block from the current `view_display_primary_topic` so output style is unchanged (`defaults.pager`, `defaults.style`, `defaults.row`, `defaults.empty` all set to `false` in the new display).
   - Inherit the default-display sort (title ASC for organisations, `field_when_value` ASC for events) — i.e. leave `defaults.sorts: true`.
   - Add **two contextual filters**:
     - `field_primary_topic_target_id` (table `node__field_primary_topic`).
     - `field_related_topics_target_id` (table `node__field_related_topics`).
     - Both with `break_phrase: true`, `default_action: ignore`, `not: false`, validator `entity:taxonomy_term` restricted to `bundles: { topic: topic }` — exactly as the existing two displays already set them. (Set `defaults.arguments: false`.)
   - **Tick "Contextual filters OR"** under `Advanced → Query settings` on this display. In YAML this is `display_options.query.options.contextual_filters_or: true`, alongside the existing `distinct: true` which is already inherited. Set `defaults.query: false` on this display so the contextual_filters_or flag actually takes effect (otherwise the display inherits the default's query options and the flag is ignored).
   - **Distinct: do nothing** — it's already on at the default display level (`query.options.distinct: true` in all three views), so every display inherits it automatically. Confirmed by reading the existing YAML; this was a correction to the original sketch.
3. **Adjust the template** at `paragraph--view-display.html.twig:104-121`. Remove the `if did == 'view_display_primary_and_related'` branch entirely; let the display id fall through to the normal `drupal_view(vname, did, primary_topic_tid, primary_topic_tid)` call. The argument needs to be supplied **twice** in positional order — Views maps the first arg to the first contextual filter (primary), the second arg to the second contextual filter (related). Both get the same `+`-joined TID string the template already builds.
4. **Delete the query alter** at `customsolent_helpers.module:50-117` (`customsolent_helpers_views_query_alter` plus its docblock). The OR'd contextual filter + `Distinct` does the dedup natively, and pagination counts the deduped result automatically.
5. **Editor data — no migration needed.** The display id `view_display_primary_and_related` is unchanged, so every existing `field_view.display_id` value on existing paragraphs continues to point at it. The display just *does* something now instead of being intercepted by the template.
6. **Drop dead CSS.** Rules in `web/themes/custom/customsolent/css/section-listing.css:620+` (`.slnt-composition__primary`, `.slnt-composition__related`) will no longer have any DOM to target. Confirm with `grep -rn slnt-composition` then remove.
7. **Visual regression check.** Walk `/sectors`, `/sectors/media`, `/living`, `/culture`, `/explore/events`. Verify:
   - One pager only.
   - No duplicated nodes.
   - Events appear in true chronological order across the whole list.
   - The known repro cases: node/333 (organisations, primary 153 + related 70 inside sectors scope), node/251 vs node/401 (events, culture ordering).
8. **Roll-out order.** Do `organisations_listing` first (Item 1's reproducer, smallest blast radius), verify, then `events_listing` (Item 3), then `links_listing`.

## Rollback

Each step is reversible:

- View config: `drush cim` to revert to current `config/sync/`.
- Template + module change: a single `git revert` of the commit.
- Module itself: `drush pmu views_contextual_filters_or && composer remove drupal/views_contextual_filters_or`.

## Risks worth flagging before starting

- **Module's filter-group rewrite.** Step 1's audit of the other views is real work, not a formality. If a regression surfaces, fall back to Option 3 (bespoke `hook_views_query_alter`) instead.
- **Distinct + sort on joined fields.** A `Distinct` query with a sort on a multi-value field (e.g. `field_when_value` for events) can in some DB backends produce non-deterministic ordering when a node has multiple `field_related_topics` values. Worth verifying on the project's MySQL with a multi-related-topic event before declaring done.

## Status

Investigation complete. Implementation not started — awaiting Rob's go-ahead.
