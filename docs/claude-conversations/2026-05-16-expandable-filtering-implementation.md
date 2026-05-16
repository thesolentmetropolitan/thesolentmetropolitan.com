# Expandable filtering iteration — implementation — 2026-05-16

Builds on the prior section-listing work (`2026-05-13-…` and `2026-05-14-…`).
All of today's work lives on the `expandable_filtering` branch.

Two same-day docs already capture sub-topics that warranted their own
reference notes — read them alongside this one:

- `2026-05-16-events-today-filter-timezone-fix.md` — site timezone change.
- `2026-05-16-explore-landings-view-display-selection.md` — `view_display_primary_and_related` vs `view_display_events_page` on /explore/* landings.
- `2026-05-16-editing-views-empty-text.md` — how to edit a Views display's empty-area message (UI or YAML).

## What landed

| Commit | What |
|---|---|
| `f1cf1da` | **Site timezone UTC → Europe/London.** "Today" date filter was excluding events on the current calendar day from a UK-clock perspective because PHP strtotime computed `today midnight` / `tomorrow midnight` in UTC. Set `system.date.timezone.default` to `Europe/London`. Existing events stored in UTC re-render correctly because Smart Date display handlers honour the configured timezone. |
| `db771d7` | **section_filter sub-topic expansion (`field_show_subterms`).** New boolean field on the section_filter paragraph type. When ticked, each top-level option in the filter sidebar reveals its immediate sub-topics inline whenever that branch is active. Three-state behaviour (no filter / parent active / deeper child active) with auto-expand and an "All in \<Section\>" entry that only appears when a deeper child is selected. |
| `1efc85f` | **Per-listing empty-area copy + duplicate suppression in primary_and_related compositions.** Each of the four listing Views (`articles_`, `events_`, `links_`, `organisations_listing`) gets its own wording. Wrapped in `<p class="slnt-view-empty">…</p>` for CSS targeting (format switched from `basic_html` to `full_html` because basic_html strips class attributes). CSS hides the related side's empty, and also hides the primary side's empty when related has results (otherwise reads as "no events match" above visible results). |
| `379e551` | **Filter UI tweaks.** `- Any -` → `all dates`, `All` → `All Topics`, "Topic" kicker title removed, branch-active parent gets magenta-dark fill + white text + underline. |
| `74aa696` | **Branch-active parent styling refinement.** Dropped the magenta-dark fill on branch-active parents — replaced with magenta-dark text + underline only. The previous fill + filled active leaf doubled up the colour; text-only colour lets the active leaf carry the fill alone. |

## Section_filter sub-topic expansion — design + implementation

### Design discussion

The /explore/* landings (Events, Articles, Orgs & Directories) use the all-topics fallback so their default filter only shows the three roots (Culture / Sectors / Living). Users can drill into a root but the sidebar doesn't reveal sub-topics — they'd have to navigate away to a section page (e.g. /culture/music) to filter further. This works against the "land where it makes sense to you" philosophy.

Three patterns were considered:
- **A** — Auto-expand the active branch (no JS, single-branch focus).
- **B** — Manual chevron toggle independent of filter (more flexible but JS + two affordances per row).
- **C** — Two-row sections → sub-topics (mirrors the date pill row but doesn't fit sidebar layout).

Rob picked **A** plus showing "All in \<Section\>" as the first child option (but only when a deeper leaf is the active filter — otherwise the parent pill already represents that state).

### Three-state behaviour

| State | Example URL | Sidebar render |
|---|---|---|
| No filter active | `/explore/events` | All Topics, Culture, Sectors, Living (flat) |
| Parent term active | `?topic=29` (Culture) | Culture is filled magenta-dark; its children render directly under it indented. "All in Culture" is suppressed because the parent already represents that scope. |
| Deeper child active | `?topic=35` (Community) | Culture is magenta-dark text + underlined (no fill — branch-active); *All in Culture* (italic) appears as the way back; Community is filled magenta-dark. Sectors / Living stay collapsed. |

### Implementation

**Field**: `field_show_subterms` boolean on the section_filter paragraph type. Off by default. Editor opt-in per paragraph. Config in `field.storage.paragraph.field_show_subterms.yml`, `field.field.paragraph.section_filter.field_show_subterms.yml`, plus form-display update.

**Preprocess**: `_customsolent_resolve_topic_context($paragraph, $host, $with_children)` reads the new field and propagates `$with_children` to the context builders. Each top-level option carries:
- `tid` / `label` / `url` (as before)
- `scope_tids` — the term + all descendants. Used by `_customsolent_apply_filter_options_to_variables()` to compute `branch_active`.
- `children` — an "All in \<Section\>" entry (marked `is_all: true`) plus each direct child term.

`_customsolent_build_filter_option()` is the single helper that constructs an option with or without children, used by both the term-mode context (`_customsolent_topic_context_for_term`) and the all-topics fallback (`_customsolent_topic_context_all`).

**Twig**: the `topic_list` macro in `paragraph--section-filter.html.twig` renders the children list only when `option.branch_active` is true. The `child.is_all` entry is suppressed in the inner loop when the parent term itself is the active filter (`option.active`) — avoids the "Culture + All in Culture both filled" duplication that the first iteration had.

**CSS**: children indented with a warm-grey left rail; `.slnt-filter-item--child` font slightly smaller; `.slnt-filter-item--all-in` italic; `.is-branch-active:not(.is-active)` magenta-dark text + underline at rest, solent-blue + soft-pink on hover (specificity bumped on the hover rule so it beats the default `.slnt-filter-item:hover`).

## Empty-area text fix

Two problems combined:
1. **Wrong wording** — `events_listing`, `links_listing`, `organisations_listing` all said "No articles in this section yet. Check back soon." That was a copy-paste artefact and the "yet" was misleading when the issue is an active filter, not absent content.
2. **Duplicate rendering** — `view_display_primary_and_related` calls `drupal_view()` twice, once per underlying display. When both returned no rows, the empty message rendered twice.

Fix:
- Replaced the value across all four listing YAMLs with content-type-aware wording, wrapped in `<p class="slnt-view-empty">…</p>`. Switched format from `basic_html` to `full_html` because `basic_html` strips class attributes silently.
- New CSS rules on `.slnt-composition__related .slnt-view-empty` (always hide) and `.slnt-composition__primary:has(~ .slnt-composition__related .views-row) .slnt-view-empty` (hide primary's empty when related has results). Three intersection cases: both empty → one message; only primary empty → no message (related results carry); only related empty → no message; both have rows → no message.

The CSS hook + `:has()` approach was preferred over JavaScript or a Views template override because it stays declarative and survives Drupal version upgrades.

## Visual tweaks

| Change | Where |
|---|---|
| Date filter `- Any -` → `all dates` | `customsolent_form_views_exposed_form_alter()` |
| Topic filter `All` → `All Topics` | Both `topic_list` macros (section_filter + view_display templates) |
| Removed "Topic" kicker (`<div class="slnt-filter-section__title>`) | Same two macros |
| Branch-active parent: magenta-dark text + underline (no fill) | `.slnt-section-filter .slnt-filter-item.is-branch-active:not(.is-active)` |
| Branch-active hover: solent-blue + soft-pink (no underline) | Same selector + `:hover` (specificity (0,5,0) beats the default `:hover` at (0,3,0)) |

## Workflow lessons captured today

1. **Drush cex before drush cim — always.** Bit me again briefly when a UI-side Views edit (the Radios widget) hadn't been exported and was reverted by a routine `cim`. This is the second time in two days; should be muscle memory by now.
2. **`basic_html` strips class attributes.** Use `full_html` when your value contains a class. The strip is silent — no warning, no error.
3. **Per-display drift in Views.** `events_page` / `front_page` / `primary_topic` / `related_topics` / `primary_and_related` each have their own copy of the empty-area text (and other display options). Edit each that matters or use Apply-to-all-displays from the UI before any display has overridden.
4. **Editor-side display selection matters for filtering.** The `view_display_events_page` display has no contextual filter so the topic pills appear to do nothing on /explore/events. Switching the View Display paragraph's display to `view_display_primary_and_related` fixed it. Same pattern likely applies to /explore/articles and /explore/orgs-directories. See `2026-05-16-explore-landings-view-display-selection.md`.
5. **CSS `:has()` specificity is non-obvious.** A rule with a `:has(> .field__item > .x)` argument has higher specificity than a rule with `:has(> .x)` because the most-specific argument inside `:has()` is added to the rule's specificity. Override rules need to repeat the parent's `:has()` clause to match or exceed the default's specificity, otherwise source order doesn't save you. (Bit me on the grid layout work back on 2026-05-13.)

## Files changed today

```
# Config (db771d7, 1efc85f)
M  config/sync/system.date.yml
A  config/sync/field.storage.paragraph.field_show_subterms.yml
A  config/sync/field.field.paragraph.section_filter.field_show_subterms.yml
M  config/sync/core.entity_form_display.paragraph.section_filter.default.yml
M  config/sync/views.view.articles_listing.yml
M  config/sync/views.view.events_listing.yml
M  config/sync/views.view.links_listing.yml
M  config/sync/views.view.organisations_listing.yml

# Theme (db771d7, 379e551, 74aa696)
M  web/themes/custom/customsolent/customsolent.theme
M  web/themes/custom/customsolent/css/section-listing.css
M  web/themes/custom/customsolent/templates/content/paragraph--section-filter.html.twig
M  web/themes/custom/customsolent/templates/content/paragraph--view-display.html.twig

# Docs (this commit + earlier today)
A  docs/claude-conversations/2026-05-16-events-today-filter-timezone-fix.md
A  docs/claude-conversations/2026-05-16-explore-landings-view-display-selection.md
A  docs/claude-conversations/2026-05-16-editing-views-empty-text.md
A  docs/claude-conversations/2026-05-16-expandable-filtering-implementation.md
```

## Branch status

All commits pushed to `origin/expandable_filtering`. PR not yet created — local branch tracks the remote. Merge into `main` when ready, or open a PR for review at:
<https://github.com/thesolentmetropolitan/thesolentmetropolitan.com/pull/new/expandable_filtering>
