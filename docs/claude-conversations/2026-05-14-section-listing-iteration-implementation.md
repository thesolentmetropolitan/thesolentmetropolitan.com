# Section listing iteration — implementation — 2026-05-14

Builds on `2026-05-13-section-listing-enhancements-implementation.md`. Covers
everything that landed during the 2026-05-14 follow-on conversation:
events date filter, pill styling, combined filtering, events
`view_display_primary_and_related`, and a handful of regression fixes
along the way.

Read this alongside the original brief
(`2026-05-13-section-listing-enhancements-brief.md`) and the 2026-05-13
implementation log.

## What landed

| Commit | What |
|---|---|
| `2b80d00` | **Date exposed filter** on events_listing: grouped filter on `field_when` (end value), four presets (today / this weekend / this week / this month), identifier `date_filter_id`. Rob configured in Views UI, exported to YAML. |
| `2b80d00` | **`customsolent_form_views_exposed_form_alter`** renames the grouped filter's `- Any -` option to lowercase `all`. Scoped via `$form_state->getStorage()['view']` to events_listing only. Iterates select / radios / checkboxes element types so the rename keeps working if the widget changes. |
| `0b4732b` | **Pill styling + auto-submit JS** for the date filter. CSS hides the native radio input (visually-hidden pattern, keyboard-accessible) and styles the adjacent `<label.option>` as a pill. New `js/date-filter.js` auto-submits the form on radio change and adds `js-auto-submit` to `<html>` so CSS hides the Apply button only when JS is enabled (no-JS fallback preserved). |
| `52d8bd3` | **Rewrote pill CSS against real Drupal markup.** The grouped radio widget renders as `<fieldset><legend>when</legend><div class="fieldset-wrapper">…</div></fieldset>` — there is no `.form-radios` wrapper. Targets the actual `<fieldset>` / `.form-type-radio` structure; flattens `.fieldset-wrapper` via `display: contents` so all radios lay out in one flex row. |
| `eff0a1e` | **Rectangular pills** (`border-radius: 4px`) instead of fully-rounded (`100px`) to match `.slnt-filter-item`'s shape. |
| `463bc73` | **Combined topic + date filtering**. New `_customsolent_build_filter_url()` helper merges current query params with the ones being set/cleared, so topic-pill URLs and the "All" link preserve `?date_filter_id=` (and any future filter params). Date-filter form gains a hidden `topic` field populated from the current request so submitting a date pill preserves the active topic. Render cache for view_display + section_filter paragraphs now also varies by `url.query_args:date_filter_id`. "when" legend visually hidden (still announced by screen readers — the pills are self-explanatory). Pill height matched to topic item via padding (`0.4rem 0.6rem`) and font-size (`0.82rem`). |
| `e983db1` | **events_listing `view_display_primary_and_related`** placeholder display. Paragraph template wraps each composition view in `.slnt-composition__primary` / `.slnt-composition__related` so CSS can hide the duplicate exposed-filter form. Both views still receive `?date_filter_id=` from the URL so the date filter applies to primary AND related rows uniformly. |
| `87f296b` | **Regression fix:** the Radios widget had been set via the Views admin UI but never exported to `config/sync`, so the `drush cim` run that imported the new `view_display_primary_and_related` reverted it to `widget: select`. Re-set to `widget: radios` in YAML. **Lesson:** always `drush cex` first when about to `drush cim`, so UI-side changes get captured. |
| `754a2b6` | **Pill colour palette aligned with topic items.** Solent-blue outline + text at rest; on hover (non-checked): solent-blue fill, soft-pink (`#f5b0d8`) text; checked: magenta-dark fill, white text; checked + hover: solent-blue fill, white text. Same vocabulary as `.slnt-filter-item`. |

## Key design / debugging decisions

### `hook_form_BASE_FORM_ID_alter` over generic `hook_form_alter`

The first `customsolent_form_alter()` implementation never fired — themes' generic `hook_form_alter` isn't reliably invoked for Views exposed forms via `\Drupal\Core\Form\FormBuilder::prepareForm()`. The base-form-id alter (`hook_form_views_exposed_form_alter`) IS invoked for theme implementations. Switched to that — once switched, label rename worked immediately.

### Combined filter composition without code changes server-side

The server-side filter logic already composed both filters correctly — `/culture?topic=35&date_filter_id=4` returned the intersection without any new code, because the topic filter comes through the preprocess-set contextual argument (`primary_topic_tid`) while the date filter is on the exposed form (Views reads it from `$_GET`). What was missing was the **UI gap**: each filter's interaction clobbered the other's query param. Fix was UI-only: rewrite topic pill URLs to carry the rest of the query string; inject a hidden `topic` field into the date form so its submission carries the topic forward.

### Why suppress the duplicate exposed form via CSS, not via Views config

When the paragraph template renders `view_display_primary_and_related`, it calls `drupal_view()` twice — once for `view_display_primary_topic`, once for `view_display_related_topics`. Each emits its own exposed filter form. Suppressing the form via Views per-display config would break the standalone use of `view_display_related_topics` (where it should still render its own form). CSS targeted to the composition wrapper (`.slnt-composition__related .views-exposed-form { display: none }`) only hides the duplicate within the composition, leaves the standalone use intact.

### Why pills not Select list

The Views grouped filter widget can be Select / Radios / Checkboxes. Pills are realisable as styled radios:
- Native radio semantics preserved (keyboard arrow-key navigation, screen-reader announcement, "X of N" indicator).
- Visually hide the input via the standard accessibility-friendly clip pattern; style the `<label.option>` adjacent to it.
- Auto-submit JS makes the interaction feel like links on click, with the radio semantics underneath for accessibility.

### Cache contexts

`customsolent_paragraph_view_alter()` adds `url.query_args:topic` AND `url.query_args:date_filter_id` to view_display + section_filter paragraph builds. Each unique combo of those two params gets its own cached render. Acceptable fragmentation given listings are the heaviest caching target on the site already.

## Files changed (since 2026-05-13)

```
M  config/sync/views.view.events_listing.yml
M  web/themes/custom/customsolent/css/section-listing.css
M  web/themes/custom/customsolent/customsolent.libraries.yml
M  web/themes/custom/customsolent/customsolent.theme
M  web/themes/custom/customsolent/templates/content/paragraph--view-display.html.twig
A  web/themes/custom/customsolent/js/date-filter.js
A  docs/claude-conversations/2026-05-14-section-listing-iteration-implementation.md
A  docs/claude-conversations/2026-05-14-section-listing-status.md
```

## Open issues filed during this iteration

- **[#257](https://github.com/thesolentmetropolitan/thesolentmetropolitan.com/issues/257)** — verify date-filter boundary handling between "This month" and a future "Next month onwards" preset; also covers slug-keyed URL values (per-preset machine names instead of array indices).
- **[#258](https://github.com/thesolentmetropolitan/thesolentmetropolitan.com/issues/258)** — clean URLs via custom controller (`/culture/community/this-month`). Detailed routing + controller outline in the issue.

## Workflow notes for future iterations

1. **`drush cex` before `drush cim`.** Any UI-side Views/config change goes to the DB only — re-importing stale YAML will revert it silently. The pill-widget regression on 2026-05-14 was caused by missing this step.
2. **Inspect the rendered DOM before writing CSS.** The grouped-radios widget rendered as a `<fieldset>`, not as `.form-radios` — first round of pill CSS targeted the wrong selectors and matched nothing. Playwright eval to dump the form's HTML was the fastest path to the right selectors.
3. **`hook_form_BASE_FORM_ID_alter` for Views exposed forms from themes.** Generic `hook_form_alter` is unreliable from a theme; the base-form-id variant is what's documented to work.
4. **CSS specificity with `:has()`.** A default rule using `:has(> .field__item > .x)` has higher specificity (because of the two-class argument) than an override using `:has(> .x)`. Override rules need to repeat the parent's `:has()` clause to match or exceed the default's specificity, otherwise source order doesn't save you.
