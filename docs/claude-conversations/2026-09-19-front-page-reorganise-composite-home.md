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

---

## Follow-up — adjustments after Rob's review

Rob will delete the Landing Page content type himself once this is live, then export and push.

| Asked | Done |
|---|---|
| "Welcome to The Solent Metropolitan" on one line on desktop; on mobile break between "Welcome to" and "The Solent Metropolitan"; relax on very narrow screens | New classy style **Heading One Line Desktop** on the h1 |
| Tagline breaks between "The broader perspective," and "for a distinct region." at every width; relax when very narrow | Line break added to the heading text |
| About button in its own slice, centred in the browser | New enclosure below the intro section with classy style **Centre Call To Action** |
| About and See all articles in the deep Explore orange, contrast checked first | Both use the *Explore* colour term. White on `#BC4A08` is **5.1:1** (AA pass); near-black would be 3.4:1 (fail), so white stays |
| Button text larger, buttons the same size | 1rem → 1.15rem desktop, 1.1rem mobile; padding trimmed to hold the box |
| Events grid shows 8 | `views.view.events_listing` front-page display, 4 → 8 |
| Fix the KickerLazyBuilder bug | Done — see below |

**How the heading breaks work.** `paragraph--heading.html.twig` now wraps each line the editor
typed in `<span class="slnt-heading__line">`, `display: block`. A typed line break is therefore a
deliberate break at every width — and because each line still wraps normally inside itself,
nothing can be pushed off a narrow screen, which is the "relax" Rob asked for (checked at 280px:
no overflow, no horizontal scroll). *Heading One Line Desktop* switches the spans to inline at
≥800px. Only one heading on the site had a line break before this, so nothing else changes.

**Button text: a specificity bug found on the way.** `fonts.css` has
`.slnt-text > * a { font-size: 1rem }` (specificity 0,1,1), which outranks a bare `.slnt-cta`
(0,1,0). The font sizes in `cta.css` — including the 0.9rem mobile size — had therefore never
applied; every button rendered at 1rem. The new size is set with a selector that wins, and the
padding was tuned by measurement:

| Button | Before | After |
|---|---|---|
| About (desktop) | 116 × 49, 16px | 108 × 49, 18.4px |
| See all events | 176 × 49 | 178 × 49 |
| See all articles | ~183 × 49 | 186 × 49 |
| About (phone) | 46px tall, 16px | 46px tall, 17.6px |

This applies to every `.slnt-cta` button on the site, including the three Discover buttons.

**Events at 8.** Only six events currently qualify (published, promoted, not ended), so the grid
is 4 + 2 until two more are promoted. Eight upcoming events exist.

**"See all events" is still solent-blue.** Rob named About and See all articles. `/explore/events`
is under Explore too, so by the same reasoning it could be orange; left for Rob to decide.

### KickerLazyBuilder fix

`resolvePageContext()` looked for the page's `section_filter` in a field called `field_content`.
The field is `field_content_component`, and section filters sit inside enclosures, so that branch
never matched. It now uses the theme's `_customsolent_find_section_filter_on_host()`, which walks
the nested paragraph tree, and follows the same two-step rule as the listings
(`_customsolent_resolve_topic_context`): an explicit `field_topic` on the section filter is used
unconditionally; the node's primary topic is the fallback, with the Explore/About guard applying
to the fallback only.

**Correction to something said mid-session:** I reported that `/explore/data` was a live case of
listing and kickers disagreeing. It is not. The section filters that query found on the Data page
belong to old paragraph revisions; the current revision has none, so listing and kickers both
fall back to all-topics and agree. On every page that does have a section filter with a topic
(`/culture`, Stage, Screen, Music, Comedy), that topic equals the page's primary topic. So the bug
was latent, and the fix changes no page today — it removes a trap for the first page whose
filter topic differs from its page topic. Verified the helper finds the filter on `/culture`.

### Scripts

`scripts/front_page_adjustments.php` does the four content changes; each step checks its own
state, so it is safe to re-run (second run: everything "already…"). Added to
`scripts/release-2026-09-19-front-page.sh` as step 7 of 9. `config:status` clean.
