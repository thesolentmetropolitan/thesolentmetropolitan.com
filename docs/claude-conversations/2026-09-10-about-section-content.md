# About Section Content & Restructuring

**Date:** 2026-09-10
**Script:** `scripts/populate_about_pages.php` (run via
`drush php:script`; already run locally, needs running on prod)

## Brief (Rob)

Populate the About pages with first-draft content (Rob will re-voice later),
and finish converting the remaining Article pages to composite pages so they
can carry banners. Fold Overview into /about and drop it.

## What the survey found (differed from the brief's assumptions)

- `/about` was **already** a composite page (node 87) — just near-empty.
- The team page lives at `/about/team` (not `/about/our-team`); the Article
  is node 15. An **unpublished composite placeholder "Our team" (node 88)**
  already existed with the correct `hero_art_style_about_our_team` banner and
  topic (116) — from an earlier conversion batch. Reused rather than
  duplicated.
- An unpublished composite `/about/why` (node 90, topic 113 "Overview") also
  exists from that batch — left untouched, see loose ends.
- All other About pages were already composite with hero banners and
  placeholder text.

## What the script does

1. **/about (87)** — intro absorbing the Overview article's essence
   (independent, grass-roots, apolitical, both cities equally, slow content,
   built locally, open source) + "In this section" table of contents with
   one-line summaries linking all seven sections.
2. **/about/our-services (101)** — mission echo (three dimensions / triple
   helix), then: events guide (trusted organisers can publish), organisation
   listings, articles/slow content, curated links, connections, think tank;
   "What we're not" (not an ads site); "Work with us" → contact.
3. **/about/editorial-policy (108)** — Impartiality (apolitical; and
   *place* impartiality: Southampton and Portsmouth championed equally,
   plus IOW/Fareham/Gosport/Eastleigh), Accuracy & corrections,
   Independence (self-funded), Opinion clearly labelled, Listings caveats,
   Feedback & complaints → contact.
4. **/about/accessibility (112)** — Atkinson Hyperlegible Next typeface,
   contrast, keyboard navigation with visible focus, no-JS fallbacks,
   responsive, invitation to report barriers. **No hero banner — deliberate**
   (as with Disability in Living). Also promoted the page's title heading
   paragraph h2 → h1 (the page had no h1 — poor hierarchy, of all pages).
5. **/about/privacy-policy (106)** — minimal-data stance: no
   ad/analytics/tracking cookies, Klaro present but dormant until an opt-in
   service exists (asks first if that changes), contact form data usage,
   server logs, UK GDPR rights, changes.
6. **/about/terms (107)** — code open source under **GPL v3** (per repo
   LICENSE) on GitHub, Drupal itself GPL; copyright over content, name,
   brand, tagline "The broader perspective, for a distinct region" and the
   three dimensions / triple helix concept; listings & links caveats;
   fair use of the site; changes.
7. **Our team** — populated placeholder node 88 with a tidied rewrite of the
   Team article's body (first person, LinkedIn link, radio history, Drupal
   work, the project's drive) and published it; moved the `/about/team`
   alias from Article 15 to node 88; unpublished Article 15. The menu item
   uses `internal:/about/team`, so it followed the alias unchanged.
8. **Overview retired** — unpublished Article 1, disabled its main-menu item,
   deleted its `/about/overview` alias, 301 redirect
   `/about/overview → /node/87` (resolves to `/about`).

## Mechanics

- Text updates locate paragraphs **structurally** (first text paragraph in
  the node's first enclosure) rather than by paragraph ID, and save with
  `setNewRevision(FALSE)` so every existing revision reference stays valid.
- Node IDs match across environments (local DB is refreshed from prod).
- Idempotent for the structural steps. Text updates are deterministic
  overwrites: safe to re-run, but **do not re-run after editing the pages
  in admin** — it would clobber the edits.
- No config changes in this task; everything is content.

## Deploy

On the prod release:

```bash
./drush-dir/drush php:script scripts/populate_about_pages.php
./drush-dir/drush cr
```

## Verified locally

- All seven pages render with their banners (Accessibility deliberately
  without one, now with an h1); h2 sections present on each.
- `/about/overview` 301-redirects to `/about`.
- `/about/team` serves the composite page (title "Our Team" from the topic,
  ABOUT kicker, people-pattern banner).
- "Overview" no longer appears in the About submenu or anywhere on the page.

## Loose ends

- Node 90 "Why?" (unpublished composite, topic 113 "Overview") is now fully
  orphaned — its topic's page is retired. Delete it, or repurpose if a
  "Why" page is ever wanted.
- Unpublished originals kept for reference: Article 1 (Overview) and
  Article 15 (Team) — delete whenever comfortable.
- Topic term 113 "About / Overview" still exists in the taxonomy.
- All copy is first-draft for Rob to re-voice.
