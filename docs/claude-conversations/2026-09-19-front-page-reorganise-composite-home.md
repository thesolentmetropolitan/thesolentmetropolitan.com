# Front page reorganised into slices; Home converted to Composite Page

**Date:** 2026-09-19 (evening)
**Branch:** `front-page-reorganise-composite-home` (cut from `main` at `41266ca`)
**Model:** Claude Fable 5.1 via Claude Code

---

## What Rob asked for

1. Housekeeping after the 2026-09-19 release: delete the phase-1 config folder and the merged
   feature branch.
2. Reorganise the front page:
   - Events grid straight after "Welcome to The Solent Metropolitan", as its own full-width
     slice — 4 columns on desktop, 2 on mobile.
   - Below it, a two-column slice: "The broader perspective…" on the left, the "The Solent
     Metropolitan is an…" text on the right, with the **About** button beneath that text, centred.
   - Drop "We're helping to build a strong, distinct identity…" and the "Work in progress" note.
   - Then the articles grid.
   - Why: events are the freshest, most-changing content, so they go above the fold and explain
     the site by implication — *fruit and veg at the front of the supermarket*. Visitors scroll
     down to learn the site's aims.
3. Migrate the home page from **Landing Page** to **Composite Page** (Landing Page is used only
   there). The topic fields must stay empty on the home page and must not trigger section logic.

## Front page — what changed

Inside the white welcome enclosure, in order:

| Slice | Contents |
|---|---|
| new enclosure, 1em padding | Welcome h1 · "What's on across the Greater Solent" · events grid · See all events |
| existing two-column section, style *Front Intro Two Column* | left: tagline h2 · right: intro text (first paragraph only), About button centred |
| existing enclosure | Latest articles (unchanged) |

- `scripts/reorganise_front_page.php` **moves** the existing paragraphs; only the one wrapper
  enclosure is new. It locates everything structurally, outward from the events grid, so it does
  not depend on paragraph ids. It prints which intro text it keeps and which it drops (the
  "Work in progress" wording differs by date, so it keeps the first non-empty block rather than
  matching strings). Dry-run flag; refuses to guess if the structure is not what it expects;
  does nothing on a second run.
- The Welcome h1 gained the existing *heading space below* style so it doesn't sit tight against
  the "What's on" heading now beneath it.
- **Centred button:** a new classy style `front_intro_two_column` (`slnt-front-intro-2col`), set
  in the two-column section's existing Style field, with a CSS rule centring a call-to-action in
  that section's right column. Scoped by class, per the site's pattern; the call-to-action
  paragraph type did not need a new field.
- **Events grid CSS:** 4 columns at ≥1000px, 2 at 340–999px, 1 below — the same steps as the
  articles grid.
- **Events view shows 4, not 6.** Only six events currently qualify (published, promoted,
  not yet ended). Six in four columns is a lopsided 4 + 2; four is always one clean row on
  desktop and 2 × 2 on a phone. One value in `views.view.events_listing` if Rob prefers 8.

Measured at 1280px: events row ends at 472px, so heading, four events and the See-all button are
all above a 900px fold. About button centre is within 8px of the text column's centre (the
difference is the column's own 1em inner padding). No horizontal scroll at 1280 or 375.

## Home → Composite Page

**Converted in place, not copied.** Drupal has no API to change a bundle, so
`scripts/convert_home_to_composite_page.php` updates the bundle name where the database records
it — `node.type`, `node_field_data.type`, and the `bundle` column of the field tables holding
this node's rows (2 current + 233 revision rows of `field_content_component`) — in a transaction,
then reloads the node through the entity API, verifies it, and re-saves it without a new revision.

Why in place: the two types are near-twins (byte-identical node templates; same
`field_content_component` storage). Keeping nid 17 means `system.site page.front`, revisions,
menu links and every paragraph's parent reference are untouched, and local and production cannot
drift on node ids. The older `scripts/copy_landing_to_composite.php` took the other route —
a second node *sharing* the same paragraphs — which is fragile: deleting the old node would
queue the shared paragraphs for deletion.

Guards: refuses unless the node is `landing_page`; refuses if any field holding data is missing
on the target type; dry-run lists every table and row count first.

### Audit: does Composite Page logic now fire on the home page?

Every topic-driven path is keyed on `field_primary_topic` being **non-empty**, so with the fields
empty the home page behaves exactly as it did as a Landing Page:

| Logic | Where | On an untagged home page |
|---|---|---|
| Topic breadcrumb trail / h1 | `customsolent_preprocess_node` | skipped — field empty |
| Hero shows topic trail | `preprocess_paragraph__hero_with_art_style` | skipped — field empty (and no hero) |
| Listing topic scope | `preprocess_paragraph__view_display` → `_customsolent_resolve_topic_context` | all-topics fallback, as before |
| Kicker scope | `KickerLazyBuilder::resolvePageContext` | returns the all-topics default, as before |
| "Term links to its landing page" | `_customsolent_term_has_landing_page` | looks up composite pages *by term*; home has none, never matches |
| Pathauto | no pattern for either type | unchanged |
| Rabbit Hole, promote override | identical config for both types | unchanged |

Verified after conversion: no page-level topic trail on `/`; `/culture` still renders its trail.

**Safeguard added.** `customsolent_helpers_form_node_form_alter()` hides the two topic fields on
the edit form of whichever node is the front page, so the home page can't be categorised by
accident. Verified: absent on `/node/17/edit`, present on `/node/92/edit`.

**Not done — Rob's call:** deleting the now-unused Landing Page content type (node type, field
instance, displays, Rabbit Hole and promote config, and a filter value in the
`node_list_topic_landing_pages` admin view). Config-only and straightforward once this is live.
`scripts/copy_landing_to_composite.php` is also now obsolete.

**Noticed, not touched:** `KickerLazyBuilder::resolvePageContext()` looks for a `section_filter`
paragraph in `field_content`, but the field is `field_content_component` (and section filters
sit inside enclosures), so that branch never matches and it always falls through to the node's
primary topic. Harmless today because the two agree on every page; it would matter only if a
section filter were given a different topic from its page.

## Housekeeping

`config/release-2026-09-19-phase1/` removed; local branch
`article-body-field-and-front-page-grid` deleted (fully merged, never pushed).

## Production deploy

```bash
git pull origin main
composer install --no-dev --optimize-autoloader
bash scripts/release-2026-09-19-front-page.sh
```

Backup → maintenance → `cim` → `cr` → convert → reorganise → `cr` → maintenance off. Applied
locally through the same two scripts (dry run, real run, second run = nothing to do), with a DDEV
snapshot `pre-front-page-reorg-20260919` taken first. `config:status` clean afterwards.
