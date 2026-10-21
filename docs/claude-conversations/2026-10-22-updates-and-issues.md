# Updates + issues 148, 436, 437, 443, 444, 460, 461 (22 October 2026)

Session: just after midnight, 22 Oct 2026 (00:35 to about 01:05). Branch
`updates-and-issues-2026-10-22`, off `main` at b8d81a3. Release:
`scripts/release-2026-10-22-updates-and-issues.sh` (composer install +
updatedb + cim + Link URL script + cr). Rehearsed locally from DDEV snapshot
`pre-2026-10-22`.

## Rob's request

"Work on the following issues plus a security update/update (core, and
contrib modules and theme)": 460, 444, 436, 461, 437, 443, 148.

## Updates

`composer audit` flagged one advisory (Gin, SA-CONTRIB-2026-219, XSS 13/25).
`composer outdated --direct` listed core 11.4.11 plus five contrib minors.
Release notes checked on drupal.org:

| Package | From → to | Why |
|---|---|---|
| drupal/core | 11.4.10 → 11.4.11 | Bug-fix release, no SA-CORE. Re-scaffolds `web/.htaccess`: the deprecated `assert.active 0` becomes `zend.assertions 0`. |
| drupal/gin | 5.0.15 → 5.0.16 | SA-CONTRIB-2026-219: XSS in the admin theme; needs "administer menu" or HTML with data attributes. |
| drupal/rabbit_hole | 2.0.0-beta2 → beta3 | SA-CONTRIB-2026-252: access bypass (11/25), a query parameter skipped the behaviour; also "enforce field access". `composer audit` did not flag it (the advisory names the 1.x fix). Collaborator 301s re-tested. |
| drupal/single_content_sync | 1.4.17 → 1.4.18 | Fixes an arbitrary file upload introduced in 1.4.17 (no SA yet). |
| drupal/klaro | 3.1.1 → 3.1.2 | Bug fixes: uses masterminds/html5 for unknown-source parsing. |
| drupal/superfish | 1.16.0 → 1.17.0 | Minor. The module is enabled but the menu is the theme's own JS; release page 404s on new.drupal.org. |
| guzzlehttp/guzzle, promises, psr7 | minors | Transitive. |

Left alone: `drupal/custom_field` 4.0.8 → 5.0.3 is a major version (not a
security fix), separate job.

No database updates. Config unchanged after the update. `web/sites/example.settings.local.php`
was re-scaffolded too (stock file, committed with the lock).

## Issues

**#461 regular events on two days.** `_customsolent_recurrence_phrase()`
built "Every <weekday of the first instance>", so Salsa & Bachata
(RRULE `FREQ=WEEKLY;BYDAY=WE,TH`) read "Every Wednesday". It now names every
BYDAY day, Monday first: "Every Wednesday and Thursday, 7:30pm"; "Every other
…" and "Every N weeks on …" take the same list. New helper
`_customsolent_recurrence_day_names()`. Cards, teaser and full page all use
the phrase. Checked on /explore/events: two events now read Wednesday and
Thursday, the single-day ones unchanged.

**#437 body text line length.** The 740px reading column existed for the
article body, series description, person bio and collaboration (`.slnt-article__body`).
Editor Text paragraphs on composite pages (About pages, section intros) ran
the full 1280px, 180+ characters a line. New rule in `css/node.css`:
`.layout-content .paragraph--type--enclosure .paragraph--type--text { max-width: 740px }`.
All 105 Text paragraphs in the main content sit in an Enclosure (query of
`paragraphs_item_field_data`); the footer's two are outside `.layout-content`
and untouched; two-column slices are already narrower. Capped, not centred,
so the left edge stays on the heading line. Before/after on
/about/editorial-policy; front page cards and intro unaffected.

**#460 several locations on an organisation.** `field_where` has been
unlimited since the 18 Oct events release (storage shared with Event) and the
form already shows "Where (value 1) / (value 2) / Add another item", checked on
/node/879/edit. What was missing was the page: the template did
`content.field_where|render|striptags`, which would mash two terms together.
`customsolent_preprocess_node()` now sets `organisation_where` ("Southampton and
Portsmouth") and the template prints that. Shared joiner
`_customsolent_join_names()` (also replaces the inline version in the event
code). Verified with a temporary second location on Caustic Bloom, then
reverted. If Rob saw a single-value widget, it was before that release ran on
prod.

**#443 several roles on a collaborator.** Also already multi-value, but the
widget was `entity_reference_autocomplete_tags`: one text box, roles separated
by commas, which hides that more than one is possible. Switched to the plain
autocomplete widget: one row per role with "Add another item", same as the
other reference fields. Config only. Checked on /node/845/edit.

**#444 Link URLs.** Pattern `/explore/links/[node:title]` → `/link/[node:title]`
(config). `scripts/move_link_urls_2026-10-22.php` regenerates the 27 Link
nodes' aliases and ensures a 301 from each old address to the node (the
redirect module's auto-redirect also records one; the script checks first, so
one per address). 13 older nodes had pathauto switched off, but their aliases
were exactly what the old pattern produced, so the script switches them back
to automatic rather than leaving them on the old URL; a genuinely hand-typed
alias (not under /explore/links/) would be skipped and listed. Node 474
(unpublished, no alias) gets one. Idempotent, `--dry-run`. Bare `/link` and
`/links` 301 to /explore/organisations via two routes in
`customsolent_helpers.routing.yml` (same controller as /series etc.). Nothing
else in config or code referenced /explore/links. Run locally: 27 moved, 27
redirects, second run "27 already in place".

**#436 organiser on a series.** Series had no organiser field. Added
`field_organiser` to Series (the Event's storage, one Organisation; config
`field.field.node.series.field_organiser.yml`, form + view display). The page
prints "Organised by <org>" under the dates, same markup and CSS as the event
page, only when set. Kept single-valued on purpose: Rob's note says several
organisations on a series is semantically a Collaboration. Form order:
Organiser sits after Description (same weight; adjust in Manage form display
if wanted). Verified with a temporary organiser on Black History Month, then
reverted. Teaser display lists the field as hidden.

**#148 favicon.** The site served core's default Drupal icon. Added to the
theme root: `favicon.ico` (16/32/48, Drupal picks a theme-root favicon.ico up
by itself), `favicon.svg` (the square SLNT social mark, Inkscape metadata
stripped) and `apple-touch-icon.png` (180px), plus `web/favicon.ico` for
clients that request the root path. `customsolent_page_attachments_alter()`
adds the SVG and touch-icon links. Built with Pillow from
`logo202604g_social.png`. The 16px version is the coloured blocks; the
letters are not legible that small, by nature.

## Decisions taken without asking

- Rabbit Hole beta3 and Single Content Sync 1.4.18 included although
  `composer audit` only named Gin: both fix security bugs per their release
  notes.
- Custom Field major update left out.
- `/links` redirects as well as `/link`.
- The 13 pathauto-off Link nodes are moved (see #444) rather than skipped.
- Series organiser is single-valued.

## Still open / for Rob

- Run the release on prod, then close the seven issues.
- #460: if the request was something other than the form and page (for
  example a location filter on the organisations listing), say so.
- Rob's `field_where` entries: Caustic Bloom is back to Southampton only.
