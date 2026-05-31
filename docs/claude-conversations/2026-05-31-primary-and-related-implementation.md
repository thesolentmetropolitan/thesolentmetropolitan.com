# Primary + Related composition — implementation — 2026-05-31

Implements the migration sketched in [`2026-05-26-primary-and-related-architectural-fix.md`](2026-05-26-primary-and-related-architectural-fix.md). Closes issues [#284](https://github.com/thesolentmetropolitan/thesolentmetropolitan.com/issues/284) and [#307](https://github.com/thesolentmetropolitan/thesolentmetropolitan.com/issues/307); supersedes the workaround documented in [`2026-05-19-primary-and-related-cross-display-dedup.md`](2026-05-19-primary-and-related-cross-display-dedup.md) (issue [#280](https://github.com/thesolentmetropolitan/thesolentmetropolitan.com/issues/280)).

Branch: `20260526-280-284-307-primary-and-related-arch`. Eight commits, summarised at the bottom.

## What changed in plain English

`view_display_primary_and_related` used to be a virtual display name that the paragraph template intercepted and rendered as **two** Views calls back-to-back — one for `view_display_primary_topic`, one for `view_display_related_topics`. Two queries → two pagers → two independent sorts → out-of-order events on /culture, duplicate rows on /sectors, mid-page pager on /sectors/media.

It is now a **real** display with two contextual filters (`field_primary_topic_target_id` and `field_related_topics_target_id`) that get OR'd together at the WHERE level by the `views_contextual_filters_or` module's "Contextual filters OR" query option. The paragraph template makes one `drupal_view()` call and passes the topic-scope TID string twice (positional: one arg per contextual filter). One query, one pager, one global sort, automatic dedup via OR + Distinct.

## Gotchas worth remembering

Several details bit us during implementation. They're not obvious from the architecture; capturing here so the next migration doesn't repeat them.

### 1. The module's UI is one checkbox, not an "OR group"

The original brief described an imagined drag-and-drop OR-group UI. There isn't one. The module exposes **a single checkbox** at `Advanced → Query settings → Contextual filters OR`. In YAML: `display_options.query.options.contextual_filters_or: true`.

How it works: the module globally swaps Views' SQL query plugin with `ExtendedSql`, which flips the operator on WHERE group 0 (where contextual filters land) from AND to OR. Conditions in other groups (e.g. the published-status and bundle filters Views places in `group: 1`) are unaffected and stay AND'd against the OR'd contextuals — which is exactly what we want.

For the checkbox to take effect, the display must override the default's `query` block (`defaults.query: false` in YAML).

### 2. Distinct is already on at the default-display level

The brief told us to set Distinct per display. Wrong — `query.options.distinct: true` is already on the default display in all three target views, so every display inherits it. No per-display Distinct override needed.

### 3. Two "Allow multiple values" checkboxes mean different things

A contextual filter form has two near-identical "Allow multiple values" controls and they do different things:

- **In the More fieldset** at the bottom of the contextual filter form: this is `break_phrase: true`. It controls whether a multi-value argument (`73+74+75`) is split into individual TIDs before SQL generation.
- **Inside the Validator section** (Taxonomy term → "Multiple arguments" radio): this is `validate_options.multiple`. The radio choices are "Single ID" (`multiple: 0`) or "One or more IDs separated by , or +" (`multiple: 1`).

You need **both** for our `+`-joined TID argument to work: `break_phrase: true` to split it, and `multiple: 1` so the validator accepts the multi-value input.

If you only set `break_phrase: true` and leave `multiple: 0`, the validator rejects the multi-value argument → `fail: ignore` ("Display all results for the specified field") fires → the contextual filter is silently dropped from the WHERE clause. With both contextual filters dropped, every node matches.

That's the bug that surfaced as "every organisation listed on /sectors, including ones with no sector subterm in either field" (e.g. node/289, node/161). Fixed by flipping six radio buttons (3 views × 2 filters).

### 4. The "Exclude" checkbox on a contextual filter is destructive

A separate but related trap: on a contextual filter, the "Exclude" checkbox sets `not: true`. Combined with OR'd contextual filters, `not: true` on one filter inverts that side: "match if primary IS in scope OR if related is NOT in scope" — surfaces lots of off-topic rows. Caught this on `links_listing.view_display_primary_and_related` before the template change went live.

### 5. links_listing was widely used

A DB query (`SELECT COUNT(DISTINCT entity_id) FROM paragraph__field_view WHERE field_view_target_id = 'links_listing' AND field_view_display_id = 'view_display_primary_and_related'`) returned **13 distinct paragraphs**. The template change has to migrate all three views' configs first, otherwise those 13 pages render empty links blocks the moment the template is deployed.

General lesson: before a template change that depends on a stub display becoming real, DB-check live usage on every view that exposes the stub. The list of three views in the brief wasn't the question — usage was.

## Final shape

**Module** — `drupal/views_contextual_filters_or` 8.x-1.5 (Drupal-11-compatible, Drupal Security Team coverage).

**Views config** — `view_display_primary_and_related` on each of:

- `views.view.organisations_listing.yml`
- `views.view.events_listing.yml`
- `views.view.links_listing.yml`

is now a real display with:

- Two contextual filters: `field_primary_topic_target_id` and `field_related_topics_target_id`. Both with `break_phrase: true`, `not: false`, `default_action: ignore`, Taxonomy-term validator scoped to `bundles: { topic: topic }`, `multiple: 1`.
- `query.options.contextual_filters_or: true`.
- `defaults.query: false` and `defaults.arguments: false` so the overrides take effect.
- Pager / style / row / empty either overridden (organisations_listing) or inherited from the default display (events_listing, links_listing — editorial choice).

**Template** — `web/themes/custom/customsolent/templates/content/paragraph--view-display.html.twig` makes one call:

```twig
{{ drupal_view(vname, did, primary_topic_tid, primary_topic_tid) }}
```

Two positional args — one per contextual filter. Single-contextual-filter displays (e.g. selecting `view_display_primary_topic` directly) silently ignore the second arg.

**Removed**:

- `customsolent_helpers_views_query_alter` in `web/modules/custom/customsolent_helpers/customsolent_helpers.module` (the events-only NOT-IN dedup). Superseded by OR + Distinct.
- `.slnt-composition__primary` / `.slnt-composition__related` rules in `web/themes/custom/customsolent/css/section-listing.css` (the wrapping divs they targeted are gone).

## Commit ledger

```
8c1e0c1 Cleanup: remove cross-display dedup alter and dead composition CSS
a9bdece Step 2 fix: validator multiple=1 on primary_and_related contextual filters
b69bc98 Step 2+3: events/links_listing primary+related displays + template merge
f4990d5 Step 2 (organisations_listing): real primary+related display
25f4d15 menu adj                                  (unrelated, on branch)
7f204f9 taxonomy update                           (unrelated, on branch)
3c58226 step 1 (config)                           (module enable)
ab18067 step 1                                    (composer require)
```

## Outstanding

Visual regression sweep. Walk:

- `/sectors`, `/sectors/media`, `/living` — single pager, no duplicates. Reproducer: node/333 (primary 153 + related 70 inside sectors scope) should appear exactly once.
- `/culture`, `/explore/events` — events in chronological order across the full list. Reproducer: node/251 (Fri 18 Sep 2026) should appear before node/401.

Not yet done — to do on the live preview.
