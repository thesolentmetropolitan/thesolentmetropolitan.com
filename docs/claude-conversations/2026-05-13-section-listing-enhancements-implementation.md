# Section listing enhancements — implementation — 2026-05-13

Implements the Claude Code half of
`2026-05-13-section-listing-enhancements-brief.md`. Covers steps 3, 4, 5,
6, 8, 9, 10, 11 of the implementation order (Rob handles the paragraph
type + Views display creation as separate config tasks, already in place
per the survey at the start of this conversation).

## What was added / changed

| File | Why |
|---|---|
| `web/themes/custom/customsolent/customsolent.theme` | Rewrote `customsolent_preprocess_paragraph__view_display` so it (a) reads `field_topic` on the paragraph first, then falls back to the host node's `field_primary_topic`, (b) detects Explore/About / no-term and uses an "all content topics" fallback (Culture + Sectors + Living and descendants), and (c) suppresses its own filter sidebar when a `section_filter` paragraph is present on the host page. Added `customsolent_preprocess_paragraph__section_filter` that mirrors the same topic-resolution logic but exposes only the filter UI variables. Added five helpers (`_customsolent_resolve_topic_context`, `_customsolent_topic_context_for_term`, `_customsolent_topic_context_all`, `_customsolent_apply_filter_options_to_variables`, `_customsolent_section_filter_exists_on_host`). Extended `customsolent_preprocess_node` to expose `show_primary_kicker` / `primary_kicker_label` / `primary_kicker_color` on teasers whose primary topic falls outside the host page's scope. Added `customsolent_node_view_alter` to fragment teaser render cache by `url.path` + `url.query_args:topic` so the kicker re-evaluates per host page. Extended `customsolent_paragraph_view_alter` to cover the new `section_filter` bundle. |
| `web/themes/custom/customsolent/templates/content/paragraph--view-display.html.twig` | Added the `view_display_primary_and_related` virtual-display branch: when the editor picks that display, the template renders `view_display_primary_topic` + `view_display_related_topics` back-to-back from the same View, giving "everything connected to this topic" from a single paragraph. |
| `web/themes/custom/customsolent/templates/content/paragraph--section-filter.html.twig` | New template. Renders the page-level topic filter — desktop sticky sidebar on `min-width: 800px`, sticky bar + slide-up panel below. Mirrors the markup the View Display paragraph used to emit so the existing `filter-panel.js` + section-listing CSS work unchanged. |
| `web/themes/custom/customsolent/templates/components/primary-kicker.html.twig` | New include. Renders the small "from <Section>" kicker label coloured to match the foreign section. Included into article / event / organisation / link teasers. |
| `web/themes/custom/customsolent/templates/content/node--article--teaser.html.twig` | Includes `@customsolent/components/primary-kicker.html.twig` just above the title. |
| `web/themes/custom/customsolent/templates/content/node--event--teaser.html.twig` | Includes the kicker above the event title. |
| `web/themes/custom/customsolent/templates/content/node--organisation--teaser.html.twig` | Includes the kicker above the link. |
| `web/themes/custom/customsolent/templates/content/node--link--teaser.html.twig` | Includes the kicker above the link. |
| `web/themes/custom/customsolent/css/section-listing.css` | Added the `:has()` grid layout that turns `.field--name-field-content-component` into a 220px sidebar + 1fr content grid when a `paragraph--type--section-filter` is present. The filter's `.field__item` becomes column 1 / sticky, all subsequent `.field__item` wrappers become column 2, pre-filter items (hero/intro) span both columns. Added a belt-and-braces suppression block so any latent per-paragraph filter markup is hidden when the page-level filter exists. Added section-colour vars on `.paragraph--type--section-filter[data-section=...]` so the panel "Clear" link inherits the right hue. Added `.slnt-teaser__kicker` styles. |
| `web/themes/custom/customsolent/css/node.css` | Generalised pagination CSS from `.slnt-articles-list / .slnt-events-list` to `.slnt-view-display` so every listing View (organisations, links, future ones) inherits the same pager styling without per-view tweaks. |

## Topic-resolution priority

A single helper, `_customsolent_resolve_topic_context($paragraph, $host)`,
decides what term (or fallback scope) a paragraph filters on. Used by
both the View Display and Section Filter paragraphs so behaviour is
identical:

1. `field_topic` on the paragraph itself (editor-set per paragraph).
2. `field_primary_topic` on the host node.
3. If the resolved term's top-level parent is Explore or About — or if
   no term resolved at all — fall back to the **all content topics**
   scope: Culture + Sectors + Living and their descendants. Filter
   options become Culture / Sectors / Living rather than a single
   section's children.

This avoids needing an "All" top-level taxonomy term (which would
disrupt breadcrumbs, URL aliases, and menu-state detection).

## Page-level vs. per-paragraph filter

The Section Filter paragraph owns the filter UI when it is present on a
page. The View Display paragraph still reads `?topic=<tid>` and narrows
its embedded View, but it does NOT render its own sidebar/bar/panel —
the preprocess sets `filter_has_options` to `FALSE` once a
`section_filter` paragraph is detected on the host. Pages without a
Section Filter paragraph keep working exactly as before (the View
Display paragraph renders its own filter for `view_display_primary_topic`
displays).

`_customsolent_section_filter_exists_on_host()` walks the host node's
paragraph reference fields once per render and caches the result in
`drupal_static`. It's a direct-child check, not a deep walk, which
matches the editor workflow (the Section Filter paragraph sits at the
top level of the composite page).

## Primary-topic kicker — request attribute + cache fragmentation

The kicker compares the teaser node's own `field_primary_topic` to the
host page's effective topic scope, set by the View Display paragraph's
preprocess as a request attribute (`page_topic_tids`). The teaser
preprocess reads that attribute, sets `show_primary_kicker = TRUE` when
the node's primary topic is not in the scope, and the
`@customsolent/components/primary-kicker.html.twig` include renders the
label.

The teaser render cache would otherwise reuse a single cached output
across every section page the teaser appears on, so
`customsolent_node_view_alter` adds `url.path` + `url.query_args:topic`
as cache contexts on every teaser. Some extra cache fragmentation in
exchange for correctness — pragmatic given listings are the heaviest
caching target on the site already.

## view_display_primary_and_related — Option A from the brief

`view_display_primary_and_related` already exists on `organisations_listing`
and `links_listing` as a placeholder display (configured by Rob). The
paragraph template intercepts that machine name and renders the
`view_display_primary_topic` and `view_display_related_topics` displays
in succession with the same contextual argument. The placeholder display
itself is never actually rendered. Deduplication is left to a future
iteration (in practice rare — items rarely have the same term in both
primary and related fields).

## CSS `:has()` selector reach

Drupal's `field.html.twig` wraps each paragraph in a `.field__item` div
under `.field--name-field-content-component`. The grid layout therefore
treats `.field__item` as the grid item, not the paragraph itself —
selectors are `> .field__item:has(> .paragraph--type--section-filter)`
not `> .paragraph--type--section-filter`. `:has()` is supported in
Chrome 105+ / Firefox 121+ / Safari 15.4+ which covers the site's
audience.

## Pagination generalisation

Pagination selectors were scoped to two listing-specific classy classes
(`.slnt-articles-list`, `.slnt-events-list`). The scope is now
`.slnt-view-display` — a class added by the View Display paragraph
template to its wrapper element — so every listing View inherits the
same pager styling without needing a per-content-type classy class. The
original anti-leakage intent (don't style admin pagers) is preserved
because `.slnt-view-display` is only emitted by the View Display
paragraph template.

## Things Rob still needs to do

(per the brief's implementation order)

1. **Now** — confirm the new `paragraph--section-filter.html.twig`
   renders correctly on a section page after a cache rebuild.
2. **Now** — add a Section Filter paragraph to each section page that
   needs filtering (Culture, Culture / Music, Sectors,
   Sectors / Technology, ...). Leave `field_topic` empty so the
   paragraph inherits the page's primary topic.
3. **Now** — on Explore landing pages (Events, Orgs & Directories), add
   a Section Filter paragraph with `field_topic` empty — the preprocess
   detects the Explore parent and falls back to the all-topics scope.
4. **Soon** — add the date exposed filter (grouped: Today / This
   weekend / This week / This month / Coming up) to the
   `events_listing` displays. Date pill CSS deferred until the markup
   exists from the Views configuration.
5. **Soon** — add `view_display_primary_and_related` placeholder
   displays to `articles_listing` and `events_listing` if/when the same
   "primary + related" listing is wanted there.

## Testing

Drupal cache rebuild required after deployment so the new preprocess
hook, template, and CSS are picked up. Then walk through the brief's
testing checklist (sections 1–15) — most importantly:

- A leaf section page (e.g. Culture / Music) with a Section Filter
  paragraph and one or more View Display paragraphs — confirm one
  filter sidebar, no duplicates.
- A page WITHOUT a Section Filter paragraph — View Display paragraphs
  still render their own filter (existing flow unchanged).
- Cross-section content (e.g. an organisation with primary topic
  Sectors / Technology appearing on Culture / Technology via related
  topics) — confirm the "from Sectors / Technology" kicker in blue
  (`#2563EB`).
- Explore / Events — confirm the Section Filter shows Culture / Sectors
  / Living as top-level options and the listing pulls events across all
  three.

## Files changed

```
M  web/themes/custom/customsolent/customsolent.theme
M  web/themes/custom/customsolent/css/section-listing.css
M  web/themes/custom/customsolent/css/node.css
M  web/themes/custom/customsolent/templates/content/paragraph--view-display.html.twig
M  web/themes/custom/customsolent/templates/content/node--article--teaser.html.twig
M  web/themes/custom/customsolent/templates/content/node--event--teaser.html.twig
M  web/themes/custom/customsolent/templates/content/node--organisation--teaser.html.twig
M  web/themes/custom/customsolent/templates/content/node--link--teaser.html.twig
A  web/themes/custom/customsolent/templates/content/paragraph--section-filter.html.twig
A  web/themes/custom/customsolent/templates/components/primary-kicker.html.twig
A  docs/claude-conversations/2026-05-13-section-listing-enhancements-implementation.md
```
