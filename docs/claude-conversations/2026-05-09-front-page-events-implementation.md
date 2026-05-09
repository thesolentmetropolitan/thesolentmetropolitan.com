# Front-page events implementation — 2026-05-09

Implements the Claude Code half of `2026-05-08-front-page-events-brief.md`
(steps 6, 7, 10 of the implementation order plus follow-on work for
the pager scope, view config, and layout iteration).

## What was added / changed

| File | Why |
|---|---|
| `web/themes/custom/customsolent/templates/content/node--event--compact.html.twig` | Whole-card-clickable template for the new "compact" view mode. Title (with optional external-link icon) → date → venue → area, each on its own line. |
| `web/themes/custom/customsolent/css/event-compact.css` | Single-column card layout (no border), 2-per-row grid scoped to `.slnt-events-compact-grid`, hover state (warm-grey fill, magenta title underline, icon to full opacity), keyboard focus ring, mobile stack at ≤799px. |
| `web/themes/custom/customsolent/customsolent.libraries.yml` | Registers `event-compact.css`. |
| `web/themes/custom/customsolent/customsolent.theme` | New `_customsolent_preprocess_event_compact_extras()` exposing `where_name`, `location_area` (parent of the venue term, used for the area line), and the existing `field_url_href` / `field_url_external` meta. |
| `config/sync/classy_paragraphs.classy_paragraphs_style.events_compact_grid.yml` | New `events_compact_grid` classy style → CSS class `slnt-events-compact-grid`. Editor applies it to the front-page View Display paragraph to switch on the 2-per-row grid. |
| `config/sync/classy_paragraphs.classy_paragraphs_style.events_list.yml` | New `events_list` classy style → CSS class `slnt-events-list`. Mirrors the existing `articles_list`. Editor applies it to the `/explore/events` View Display paragraph so the numbered pager picks up the same styling articles use. |
| `config/sync/views.view.events_listing.yml` | Added a `row` override on `view_display_front_page` so it renders rows in **Compact** view mode (the default display still uses Teaser, which is what the events-page display inherits). |
| `web/themes/custom/customsolent/css/node.css` | Pager rules previously scoped only under `.slnt-articles-list` now also match `.slnt-events-list`. |

## Layout decision: classy_paragraphs vs. legacy view classes

Modern Drupal Views emits a minimal wrapper (`js-view-dom-id-…` only),
so the older hooks like `view-events-listing`, `view-display-id-*`, and
`.view-content` are no longer present. Two options to give the front-page
grid a CSS hook:

1. **Re-add the legacy classes in `views-view.html.twig`.** Rejected —
   the user prefers staying on the modern Views markup pattern. Saved
   to memory.
2. **Add a classy_paragraphs style and scope CSS to it.** Chosen — one
   editor-side switch (apply *Events Compact Grid* to the paragraph),
   no template gymnastics. Less code overall.

## Pager scope decision

The pager rules in `node.css` were originally scoped to
`.slnt-articles-list` so they wouldn't leak to admin pagers. Rather
than dropping the scope, a sibling class (`slnt-events-list`) was
added and the rules extended to match both — keeping the existing
scoping intent while giving Rob a single editor-side switch for any
events listing's pager.

## Things Rob still needs to do

1. On the front page composite — the View Display paragraph in the right
   column already has **Events Compact Grid** applied (verified live).
2. Tighten the Smart Date formatter on the Compact display
   (`/admin/structure/types/manage/event/display/compact`) to abbreviate
   the date — drop the year (`j M`) — so longer dates don't push the
   area line to wrap awkwardly.
3. Add the `view_display_events_page` View Display paragraph to
   `/explore/events` and apply the new **Events List**
   (`slnt-events-list`) classy style to it so the pager picks up the
   numbered-pager styling.

## Files changed

```
A  config/sync/classy_paragraphs.classy_paragraphs_style.events_compact_grid.yml
A  config/sync/classy_paragraphs.classy_paragraphs_style.events_list.yml
M  config/sync/views.view.events_listing.yml
M  web/themes/custom/customsolent/css/node.css
A  web/themes/custom/customsolent/css/event-compact.css
M  web/themes/custom/customsolent/customsolent.libraries.yml
M  web/themes/custom/customsolent/customsolent.theme
A  web/themes/custom/customsolent/templates/content/node--event--compact.html.twig
```
