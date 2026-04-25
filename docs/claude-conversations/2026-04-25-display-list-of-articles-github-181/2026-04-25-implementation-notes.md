# Issue 181 — Article list, implementation notes

GitHub issue: https://github.com/thesolentmetropolitan/thesolentmetropolitan.com/issues/181

## What was built

Display an initial, transient list of articles on the front page, driven by the `articles` View (`articles_front` block display), embedded via a "View Display" paragraph, with a horizontal-card teaser layout and an eela-style numbered pager.

### Files changed / added

**Templates**

- `web/themes/custom/customsolent/templates/content/node--article--teaser.html.twig` — rewritten from a stub to a whole-card clickable horizontal layout. `<a href="{{ url }}">` wraps `<article>`; image on the left, text (title / standfirst / date) on the right. Conditional image render — when a teaser has no image the text row fills the whole card (no empty image column).
- `web/themes/custom/customsolent/templates/content/paragraph--view-display.html.twig` — new template for the `view_display` bundle (not `paragraph--view.html.twig`: the bundle machine name is `view_display`, so Drupal's default suggestion is `paragraph--view-display`). Renders `{{ content.field_view_display }}` and pulls classes from any `field_classy` values onto the wrapper via the same loop used in `paragraph--hero-with-art-style.html.twig`.

**CSS** (`web/themes/custom/customsolent/css/node.css`)

- **Paragraph wrapper** (`.slnt-view-display`): 1200px max-width aligned to the site's `--align-floor` grid, with matching tablet and mobile padding adjustments (same pattern as the article container).
- **Article teaser card** (`.slnt-article-teaser` family): flex row, 240px image column, magenta title on card hover, mobile (<600px) stacks image on top. Stripped nested `.field` and `<p>` wrappers around the standfirst so it renders inline.
- **Pager — full type**, scoped under `.slnt-articles-list`: padding lives on the `<a>` not the `<li>` so `<a>:hover` fills the whole bordered box; non-active items use `var(--solent-blue)` border + text with flex-centred digits; the current page (`.is-active`) uses `var(--magenta-dark)` for both background and border with white text; ellipsis items hidden.
- **Views "unformatted list" bullet/indent removal**, scoped under `.slnt-articles-list .item-list > ul > li`.
- **Teaser link reset**, scoped under `.slnt-articles-list a.slnt-article-teaser-link` — overrides the site-wide `.layout-content a` magenta underline + bold that was leaking onto the whole-card link text.

**Config**

- `config/sync/classy_paragraphs.classy_paragraphs_style.articles_list.yml` — new Classy Paragraphs style with label "Articles List" and class string `slnt-articles-list`.
- `config/sync/field.field.paragraph.view_display.field_classy.yml` — `field_classy` added to the `view_display` paragraph bundle.
- `config/sync/core.entity_form_display.paragraph.view_display.default.yml` + `core.entity_view_display.paragraph.view_display.default.yml` — updated for the new `field_classy` field.
- `config/sync/crop.type.focal_point.yml` — incidental YAML reorder from a prior config export, harmless.

**Docs**

- `docs/claude-conversations/2026-04-25-display-list-of-articles-github-181/2026-04-25-viewsreference-stale-display-id-analysis.md` — separate write-up of the viewsreference broken-revision incident that happened during this work.

## Two debugging lessons captured during this work

### 1. When CSS targeting is guesswork, open a browser

I went through several rounds of guessing which selector the bullets belonged to (pager `<li>`s, `::marker` pseudo-element, `list-style-type: disc`, etc.) before the user suggested Playwright. One `browser_evaluate` call, walking up the DOM from the visible element and logging each ancestor's `computed style`, immediately revealed the bullets were on the Views "Unformatted list" output (`div.item-list > ul > li` — both classless) — not the pager at all. My pager CSS was fine; the hunt was in the wrong part of the DOM.

Lesson: for "this isn't doing what I expected" CSS problems, 30 seconds of DOM inspection via Playwright (or DevTools screenshot dump) beats 30 minutes of CSS spelunking.

### 2. Finalise Views display machine names *before* referencing them from viewsreference

Captured in the companion note (`2026-04-25-viewsreference-stale-display-id-analysis.md`) and in auto-memory. Short version: if you rename a Views display after a viewsreference paragraph field has already been saved pointing at the old name, the field item silently strands itself on a non-existent display ID, and cron/search re-indexing loops on the broken render indefinitely. Fix used: DB sync from live + recreate the paragraph. Prevention: set the display's final machine name on creation.

## Before / after

Screenshots from Playwright, showing the bullets on each teaser row (before) and the clean list (after):

- `pager-before.png`
- `pager-after.png`

## Testing to do before merge to main

The user intends to regression-test before merging the `181-working-on-article-list` feature branch:

- Front page: article list renders with correct horizontal teasers, no bullets/underlines, pager works (click through pages 1–4, test hover + active states at `--solent-blue` / `--magenta-dark`).
- Article full view (issue 178 work): no regressions.
- **Main menu:** user explicitly called this out — confirm desktop + mobile menu still behaves after the CSS additions.
- Composite pages: check alignment hasn't drifted.
- Any Views elsewhere on the site: confirm they're unaffected by the new `.slnt-articles-list`-scoped rules (they should be — all scoped).
- Search results page: confirm the previous viewsreference warning/error is gone (was the main driver behind the DB sync).
