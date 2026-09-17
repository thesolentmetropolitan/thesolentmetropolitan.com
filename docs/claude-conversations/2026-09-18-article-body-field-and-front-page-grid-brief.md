# The Solent Metropolitan — Article body field & front-page article grid

**Date:** 2026-09-18
**Branch:** `article-body-field-and-front-page-grid` (cut from `main` at `d1dd95c`)
**Related issue:** [#180 — Article body field migration](https://github.com/thesolentmetropolitan/thesolentmetropolitan.com/issues/180)
**Drupal 11 compatible.**

---

## Overview

Three pieces of work on the Article content type:

1. **Full node view — body alignment.** The body column was centred inside the 1200px container, so it sat right of the title, standfirst and date. Make it flush left. ✅ **Done**
2. **Field restructure.** Retire the core `body` field (`text_with_summary`, whose summary column was never displayed) in favour of a plain, reusable `field_body` (`text_long`). Standfirst + body becomes the editorial model. ✅ **Done**
3. **Front-page article grid.** Surface articles on the front page as a grid of cards — topic, title, summary — mirroring the existing events grid. ⬜ **To do — this is the work remaining**

Parts 1 and 2 are already applied in the working tree on this branch, **uncommitted**. Part 3 is specified below with proposed files.

> **Read this section before touching anything.** The database has already been migrated locally. Do not re-run the migration script expecting fresh data, and do not recreate `field_body` — it exists.

---

## Decisions already taken

Settled before implementation started; don't revisit without asking Rob:

| Decision | Choice |
|---|---|
| New field name | `field_body`, plain and reusable (not `field_article_body`) |
| Scope | Article only for now. Adding it to Event later is a two-file config change, no data migration. |
| Old `body` field | Deleted from **Article and Basic Page**, and `field.storage.node.body` dropped entirely. Basic Page had the field configured but **zero rows**, so nothing was lost. `block_content` keeps its own separate `field.storage.block_content.body` — untouched. |
| Grid card summary | Standfirst, falling back to a trimmed body when no standfirst is written |
| Grid columns | 4 per row at desktop, 2 at small tablet / large phone, 1 on narrow phones |

---

## Part 1 — Body alignment (done)

**File:** `web/themes/custom/customsolent/css/node.css`

The single offending rule was `margin: 0 auto` on `.slnt-article__body`, which centred the 740px reading column inside the 1200px `.slnt-article` container. The 740px measure is worth keeping; the centring was not.

```css
/* ── Body — narrower reading column, flush left ── */
.slnt-article__body {
  max-width: 740px;
  margin: 0;
}
```

The mobile override at `max-width: 799px` already sets `max-width: 100%`, so nothing needed changing there.

**Also fixed in the same pass:** `templates/content/node--article--teaser.html.twig` had the `</div>` closing `.slnt-article-teaser__text` swallowed inside a `{# … #}` comment block, so the div was never closed in the rendered output. The commented-out date block was removed and the div properly closed.

---

## Part 2 — `body` → `field_body` (done)

### What exists now

- `config/sync/field.storage.node.field_body.yml` — `text_long`, cardinality 1, translatable
- `config/sync/field.field.node.article.field_body.yml` — label "Body", description "The main text of the article."
- `config/sync/field.field.node.article.body.yml`, `field.field.node.page.body.yml`, `field.storage.node.body.yml` — **deleted**
- Display configs updated so `field_body` occupies exactly the slots `body` held:
  - **Form display:** `text_textarea`, 9 rows. Field order also tidied to title (0) → standfirst (1) → body (2), which matches the new editorial model. Everything else keeps its existing weight.
  - **Default view display:** `text_default`, label hidden, weight 2
  - **`card_flyer_social`:** `text_default`, label hidden, weight 0 (body was visible here, so `field_body` is too)
  - **`teaser`, `rss`:** hidden (as `body` was)
- `templates/content/node--article--full.html.twig` renders `{{ content.field_body }}`

### Migration script

**File:** `scripts/migrate_article_body_to_field_body.php`

Copies `body_value` / `body_format` into `field_body_value` / `field_body_format` for **both** `node__body` → `node__field_body` and `node_revision__body` → `node_revision__field_body`, preserving full revision history.

It works at SQL level rather than through entity saves, deliberately: revision rows copy verbatim, no new revisions are created, and `changed` timestamps stay untouched. It is **idempotent** — existing rows are skipped, so a re-run after a partial failure is safe. It aborts with a clear message if `node__field_body` doesn't exist yet, or if `node__body` has already been purged.

It also **promotes orphaned summaries to standfirst**: where a node had a non-empty `body_summary` but an empty `field_standfirst`, the summary is stripped of markup, whitespace-collapsed, truncated to 255 chars and saved as the standfirst. Summaries on nodes that already had a standfirst are printed to the console and discarded — the standfirst wins.

### Local run result

```
Default values:  16 copied, 0 already present
Revision values: 68 copied, 0 already present

  nid 2: has a standfirst already — summary NOT used.
         discarded summary: "What do great cities do when they need space? They build UP."
  nid 3: standfirst set from summary — "What is the local accent in Solent? Or is there one at all?"
  nid 4: standfirst set from summary — "Walking into a bank or other institution building, sometimes
          there are clocks on the wall showing the time in well known places."
```

Verified by SQL: 16 body rows, 16 field_body rows, 16 with byte-identical value **and** format.

**Rob to review:** nodes 3 and 4 now have standfirsts written by the script from their old summaries. Node 4's in particular reads like a first body sentence rather than a standfirst — worth re-voicing by hand.

### Production deploy sequence

The migration must run **after** config import (so `field_body` exists) but **before** cron purges the deleted field data. Drupal defers field data purging to cron, so `node__body` still holds its rows immediately after `cim`.

```bash
# On the production server, from project root:
drush state:set system.maintenance_mode 1 -y
drush config:import -y
drush scr scripts/migrate_article_body_to_field_body.php
drush cr
drush state:set system.maintenance_mode 0 -y
```

⚠️ **Take a database backup before step 2.** ⚠️ **Do not run cron between `cim` and the script.**

`scripts/deploy.sh` does not know about the script — run the sequence above by hand for this release, or add the `scr` line to a one-off release script the way `scripts/release-2026-07-13.sh` did.

---

## Part 3 — Front-page article grid (to do)

### The pattern to copy

The events grid is the model and it is entirely convention-driven. Read these five files first — the article grid is the same shape throughout:

| Events (existing) | Articles (to create) |
|---|---|
| `core.entity_view_mode.node.compact.yml` | *reuse — it's a generic node view mode, not event-specific* |
| `core.entity_view_display.node.event.compact.yml` | `core.entity_view_display.node.article.compact.yml` |
| `templates/content/node--event--compact.html.twig` | `templates/content/node--article--compact.html.twig` |
| `css/event-compact.css` | `css/article-compact.css` |
| `classy_paragraphs…events_compact_grid.yml` → `slnt-events-compact-grid` | `classy_paragraphs…articles_compact_grid.yml` → `slnt-articles-compact-grid` |
| `views.view.events_listing.yml`, display `view_display_front_page` | `views.view.articles_listing.yml`, new display `view_display_front_page` |

The grid itself is **CSS, not a Views grid style** — the view uses the default (unformatted) row style and the CSS applies `display: grid` to the rows wrapper, scoped by the classy_paragraphs class the editor picks on the View Display paragraph. Keep it that way.

### Proposed files

#### 1. `config/sync/core.entity_view_display.node.article.compact.yml` — new

The template renders title and topic trail itself, so the display only needs to expose `field_standfirst` and `field_body` (the latter for the summary fallback — see the preprocess below). Everything else hidden.

Easiest to create through the UI at **Structure → Content types → Article → Manage display → Compact**, then `drush cex`. Remember the new YAML needs a `uuid:` key — `cex` adds it, but if you hand-write the file, add one or `cim` will silently skip the import with an "Undefined array key uuid" warning.

#### 2. `config/sync/classy_paragraphs.classy_paragraphs_style.articles_compact_grid.yml` — new

```yaml
uuid: <generate one>
langcode: en
status: true
dependencies: {  }
id: articles_compact_grid
label: 'Articles Compact Grid'
classes: slnt-articles-compact-grid
```

#### 3. `config/sync/views.view.articles_listing.yml` — modified

Add a block display with machine name **`view_display_front_page`**, matching the events view's naming.

- **Duplicate** the existing `front_page_block` display in the Views UI, then override:
  - **Row style:** Content → view mode **Compact** (currently Teaser)
  - **Items to display:** 8
  - **Footer:** keep or drop the "see more articles" link — a CTA paragraph below the grid is the more consistent choice, since that's what the events block does
- Set the machine name **at creation**. Views display IDs are painful to change afterwards, and once a `viewsreference` field points at one, renaming it strands old paragraph revisions on a dead ID — cron and search re-indexing then loop on broken renders. (This bit us before; see `docs/claude-conversations/2026-04-25-display-list-of-articles-github-181/2026-04-25-viewsreference-stale-display-id-analysis.md`.)

> **Note on filtering:** `promote` defaults to `1` on every Article (`core.base_field_override.node.article.promote.yml`), so unlike the events front-page display, filtering on "Promoted to front page" would currently match every article. Sort by `created` desc and cap at 8 instead. If Rob wants curation later, the promote flag is there once he starts setting it deliberately.

#### 4. `web/themes/custom/customsolent/templates/content/node--article--compact.html.twig` — new

Structure mirrors the event compact card:

```
.slnt-article-compact-card              — outer wrapper, top border tinted
                                          to the section colour
  .slnt-topic-trail (kicker)            — OUTSIDE the clickable link so the
                                          parent topic terms stay independently
                                          navigable
  a.slnt-article-compact__link          — whole-card clickable area
    article.slnt-article-compact
      h3.slnt-article-compact__title
      p.slnt-article-compact__summary   — standfirst, or trimmed body fallback
```

Points to get right:

- Keep the topic trail **outside** the `<a>`. The event card does this deliberately — nesting links is invalid and it kills keyboard navigation to the parent terms.
- `section_color` and `section_key` come free from `customsolent_preprocess_node()` — apply them the same way (`style="border-top-color: …"`, `data-section="…"`).
- Include the topic trail via `{% include '@customsolent/components/topic-trail.html.twig' with {…} only %}`.
- End with `{{ content|without(…) }}` listing every field rendered manually, so nothing appears twice.
- No external-link icon — unlike events, articles are always internal nodes, so use `path('entity.node.canonical', {'node': node.id})` directly.

#### 5. `web/themes/custom/customsolent/customsolent.theme` — modified

Add the summary fallback. Follow the existing `_customsolent_preprocess_event_compact_extras()` shape and wire it into `customsolent_preprocess_node()`:

```php
if ($node->bundle() === 'article' && ($variables['view_mode'] ?? '') === 'compact') {
  _customsolent_preprocess_article_compact_extras($node, $variables);
}
```

The helper sets `$variables['card_summary']`:

1. If `field_standfirst` is non-empty, use it.
2. Otherwise take `field_body`, `strip_tags()`, collapse whitespace, and truncate with `Unicode::truncate($text, 120, TRUE, TRUE)` (word boundary, ellipsis).
3. If both are empty, leave it unset and let the template omit the paragraph.

Doing the fallback in preprocess rather than Twig keeps the truncation logic testable and out of the template, and means `field_body` can stay `label: hidden` in the compact display without ever being printed in full.

> **Context:** only 3 of 16 articles have a standfirst written, which is exactly why the fallback exists. As Rob writes standfirsts the cards improve on their own.

#### 6. `web/themes/custom/customsolent/css/article-compact.css` — new

Grid, scoped to the classy_paragraphs class, targeting the rows wrapper the way `event-compact.css` does — modern Views renders `<div class="views-row">` directly inside the view wrapper with no `.view-content` layer:

```css
.slnt-articles-compact-grid [class*="js-view-dom-id-"] {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 0.8rem;
}

@media (max-width: 999px) {
  .slnt-articles-compact-grid [class*="js-view-dom-id-"] {
    grid-template-columns: 1fr 1fr;
  }
}

@media (max-width: 499px) {
  .slnt-articles-compact-grid [class*="js-view-dom-id-"] {
    grid-template-columns: 1fr;
  }
}
```

Card styling should follow `event-compact.css` closely — same border-top treatment, same hover (warm-grey background + magenta underline on the title text), same `:focus-visible` outline. Two rules worth copying verbatim, both of which exist for a reason:

```css
/* Beat .layout-content's magenta-underline rule on the wrapping link. */
.layout-content a.slnt-article-compact__link,
.layout-content a.slnt-article-compact__link:hover {
  text-decoration: none;
  text-decoration-color: transparent;
  color: inherit;
}
```

Type sizes should come down slightly from the event card, since four columns are narrower than two — title around `0.95rem`, summary around `0.8rem`, and clamp the summary to 3 lines with `-webkit-line-clamp` so uneven standfirst lengths don't make the rows ragged.

**Never use `100vw` for full-bleed anything here** — it includes the scrollbar width and caused a permanent horizontal scrollbar that took a while to track down. Use `width: 100%`.

#### 7. `web/themes/custom/customsolent/customsolent.libraries.yml` — modified

Add `css/article-compact.css` to the `global` library's `component` group, next to the existing `css/event-compact.css` entry.

#### 8. Front-page content — not config

The Home node is **node 17**, a `landing_page`. Its paragraph tree:

```
enclosure (476)
  enclosure (507)
    section_2_column (276)
      heading (2)         "Welcome to The Solent Metropolitan"
      heading (3)         "The broader perspective, for a distinct region."
      intro (514)
      call_to_action (516)
      heading (556)       "What's on across the Greater Solent"
      view_display (557)  events_listing : view_display_front_page
      call_to_action (558)
enclosure (508)
  …
```

The events grid is paragraph **557**, a `view_display` with:

```
field_classy = events_compact_grid
field_view   = events_listing : view_display_front_page
field_heading = (empty — the heading is a separate paragraph above it)
```

So the articles grid needs the same trio added to `section_2_column` (276): a `heading` paragraph, a `view_display` paragraph pointing at `articles_listing : view_display_front_page` with `field_classy = articles_compact_grid`, and optionally a `call_to_action` linking to `/explore/articles`.

**Rob's call whether to place these in the editor or script them.** The editor is probably quicker for three paragraphs, and it's content rather than config so it won't come through `cex` either way. If scripting, follow the shape of `scripts/wrap_in_enclosure.php`.

---

## Verification checklist

Parts 1 and 2 are applied but **were not visually verified before this brief was written** — the local DDEV site was migrated and the data checked by SQL, but no page render was confirmed. Start here:

- [ ] `ddev drush cr`, then load `/articles/we-dont-need-provincial-rivalry` — body renders, text starts on the same vertical as the title and standfirst
- [ ] Check an article **with** a hero image and one **without** (nodes 5, 8, 10 are published)
- [ ] Mobile (≤799px) and tablet (800–1199px) — body still full-width on mobile, container padding unchanged
- [ ] Edit form at `/node/5/edit` — one "Body" field, no summary widget, order is Title → standfirst → Body
- [ ] `/explore/articles` listing still renders (teaser view mode, unchanged)
- [ ] RSS feed and any social-card rendering still work
- [ ] Search: re-index and confirm article body text is still found — `drush search-api:reset-tracker` / `drush sapi-i` or core search re-index as appropriate
- [ ] `ddev drush cex --diff` shows **no** unexpected config drift

Then for Part 3:

- [ ] Grid renders 4 / 2 / 1 across the breakpoints
- [ ] Cards with no standfirst show the trimmed-body fallback
- [ ] Whole card is clickable; topic-trail links inside the kicker still work independently
- [ ] Keyboard: tab to a card, `:focus-visible` outline shows, Enter follows the link
- [ ] No horizontal scrollbar at any width
- [ ] Both light and dark section colours look right on the top border

---

## Known state / gotchas

- **Working tree is uncommitted.** Fifteen files modified, three deleted, three new. `git status` before starting.
- **The local database is already migrated.** If you need to start over, restore from `databases/dslnt_20260918_003940.sql.gz` — taken immediately before any changes.
- **Nodes 1 and 15** ("Overview: What is The Solent Metropolitan & Why?" and "Team") are unpublished orphans Rob has flagged for deletion. Both were migrated along with everything else; they'll disappear from the grid anyway since it filters on published.
- **`node--blog--full.html.twig` and `node--blog--teaser.html.twig`** still reference `content.body` and `content.body['#items'][0].summary`. There is no `blog` content type on this site — these templates are dead and were left alone. Worth deleting separately.
- Alignment across the site depends on the editor-set `field_padding` inline style on enclosure paragraphs. If the grid looks misaligned against the content above it, inspect the computed inline style on the parent enclosure before changing CSS.
- Keyboard focus styles live in `css/menu-focus.css` and are `!important` by design. Don't add `:focus` rules or `outline: none` to anything that ends up inside a menu.

---

## Suggested commit split

1. `Article: replace body field with reusable field_body (text_long)` — config, migration script, template field reference
2. `Article: align full-node body column flush left` — `node.css`, plus the teaser template div fix
3. `Article: front-page grid of compact cards` — view mode, view display, template, CSS, libraries, classy_paragraphs style

Rob merges to `main` himself.
