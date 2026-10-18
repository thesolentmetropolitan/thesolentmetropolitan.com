# People, Series and Collaborations — design brief

**Date:** 2026-10-18 (session start 00:46)
**Status:** Phase 1 (Series + Black History Month) built on branch `series-and-black-history-month`; Phases 2 and 3 (People, Collaborations) built the same night on branch `people-and-collaborations` after Rob's decisions: Collaborator as a reusable **node** (not the paragraph recommended in §2.2), location on Organisation and Person, plural URL prefixes. Logs: `2026-10-18-series-implementation.md`, `2026-10-18-people-collaborations-implementation.md`
**Rob's request:** extend Article and Event, add Person, Series, Collaborator and Collaboration, populate `/explore/series` with Black History Month South 2026, and advise on names, dates, URLs, images and external articles.

---

## 1. Where the site is today (survey)

- Content types: `article`, `event`, `organisation`, `link`, `composite_page`, `page`. **No author, person or team modelling exists** — no `field_author`, no byline anywhere; `/about/team` is a hand-built composite page (node 88).
- Event fields: `field_when` (Smart Date, unlimited), `field_where` (Location term), `field_url` (link), `field_image`, `field_standfirst`, primary/related topic. **No body/description field.**
- Article fields: `field_body`, `field_image`, `field_standfirst`, `field_where`, topics.
- Organisation / Link: `field_url`, `field_standfirst`, topics. Organisation has **no `field_where`** and no `field_content_component` yet.
- Node view modes: `full`, `teaser`, `compact`, `card_flyer_social`, `rss`, `search_index`, `search_result`, `token`. Cards are rendered by hand in `templates/content/node--{type}--{mode}.html.twig`; the compact event card is one whole-card link to `field_url` (node fallback), the kicker sits outside the link.
- Pathauto: `/articles/[title]`, `/events/[title]-[event_date_formatted]`, `/organisations/[title]`, `/explore/links/[title]`.
- Modules already present that we need: smart_date, pathauto, token, paragraphs + entity_reference_revisions, viewsreference, classy_paragraphs, rabbit_hole, media, focal_point, crop, responsive_image. Not present: auto_entitylabel, inline_entity_form, geolocation, datetime_range (core, one line to enable).
- Listing "kinds" (Events · Articles · Organisations & links) are one table in the theme, `_customsolent_listing_kinds()`, wired through the module's route, controller and `preselect_views`. Series and Collaborations will **not** join the section strip in this round (they are site-wide explorations, not per-topic listings) — noted as a later option.
- `/explore/series` (node 94) is a composite page with a hero and one intro text paragraph in an enclosure; the listing goes into that enclosure.

## 2. Advice on the open questions

### 2.1 First name + last name (3.1.2) — agree, keep them separate
Two plain string fields, `field_first_name` and `field_last_name` (last name optional, for mononyms), and the node title auto-composed as `First Last` on save by a small `hook_entity_presave` in `customsolent_helpers`, with the title widget hidden on the form. Benefits you get for free: sort the team page by surname, search either part, and every byline can simply render the entity *label* (no separate view mode needed for "first name, last name" on cards — the label already is that). I would not add the contrib `name` module (title/given/middle/family/suffix in one field): heavier than you need and awkward in Views. `auto_entitylabel` would also work, but a ten-line hook is less to update and follows the existing form-guard pattern in the module. One caveat to accept: a person whose public name is not "First Last" (family-name-first cultures, stage names) gets whatever you type into the two boxes, so the "first name" box is really "given/display name" — fine for this site.

### 2.2 Collaborator: paragraph, not a node (3.4.1)
Your spec says "no full node page" and "child of Collaboration". That is exactly what a **paragraph type** is. A `collaborator` paragraph on `Collaboration.field_collaborators` gives you: inline editing on the Collaboration form (no Inline Entity Form module), ordering, deletion with its parent, no stray URLs to block with Rabbit Hole, and no title to invent. The reverse question "which collaborations is this organisation/person in?" is still answerable: a Views relationship from Organisation → paragraph `field_collaborator` (reverse) → parent Collaboration, or a two-line entity query in the relations service (section 4). The one thing you lose is being able to reference a Collaborator from elsewhere, which nothing in the spec needs. **If you prefer the node version anyway**, it is the same fields plus a Rabbit Hole "access denied" setting — tell me and I build that.

### 2.3 Location on Collaborator (3.4.1) — put it on Organisation and Person instead
"Where they are" is a property of the organisation or person, not of their role in one collaboration; storing it per collaborator duplicates it every time the same organisation collaborates. Add `field_where` (existing Location vocabulary, single) to **Organisation** (already in your plan from 2026-09-30) and to **Person**, and let the collaborator row show the referenced entity's location. If a collaboration-specific place is ever needed, that is the Collaboration's own `field_where`.

### 2.4 Series membership lives on the Series (3.2.2) — as specified
Keeping `field_series_items` on the Series (rather than a "part of series" field on each event) is right for your use: one place to curate and order, a mix of events, articles and links, and a Link node can join without editing it. The cost is that the event card needs a **reverse lookup** ("which series contain me?"). That is one entity query, cached on the card with the `node_list:series` tag (Drupal ≥10.1 bundle list tags, already used by the strip), so a saved Series refreshes the cards. Same mechanism answers "which collaborations is this event/article/series in?" via `field_outcomes`, directly and through series, de-duplicated (3.4.3).

### 2.5 Series dates (3.2.2) — two date-only fields, not Smart Date
Smart Date's end value is technically nullable but its widgets and formatters assume an end, and "all day" is a 00:00–23:59 convention, which is wrong for a date range with no times. Core's `daterange` requires an end in 11.4 (no optional-end setting). Cleanest: `field_series_start` (date only, required) and `field_series_end` (date only, optional). Empty end = open-ended/ongoing. Sorting and "past / current / upcoming" logic are then trivial, and a Views date filter works on either. Display formats as "1–31 October 2026", "24 October – 19 November 2026", or "From 29 September 2026" when open.

### 2.6 URL patterns — plural prefixes to match the site
Existing patterns are plural (`/articles/`, `/events/`, `/organisations/`). I recommend `/people/first-last`, `/series/title-YYYY` and `/collaborations/title`. For Series I suggest the **start year** rather than the full start date: Black History Month recurs annually, so `/series/black-history-month-2026` disambiguates and reads well; `-2026-10-01` would also work if you prefer. `/collaborations/x` does not clash with `/explore/collaborations` (different prefix). If you want your singular versions (`/person/`, `/collaboration/`) it is a one-line pattern change — say so.

### 2.7 Event card: series line position (3.3.1)
The compact card is a single link (nested links are invalid HTML), and the kicker already sits outside it for the same reason. The series name goes **after the link block and before the kicker**, as its own small clickable line with a "stack" icon, so it stays independently clickable. On the teaser it goes after Where and before the CTA.

### 2.8 Images for articles (3.5.2.3) — the moving parts, briefly
- **Media image** is the stored thing (file + alt + focal point). Article `field_image` already references it.
- **Image style** = one derivative recipe (scale-and-crop to WxH around the focal point). You already have `article_hero` 1200×675 and `article_teaser` 400×225 (both 16:9). Focal Point replaces the need for a separate "crop type" per style: one dot the editor drags, every style honours it. Only add Image Widget Crop (hand-drawn crop per aspect ratio) if 16:9 and a square avatar start fighting over the same photo.
- **Responsive image style** = a bundle of image styles plus `sizes`/breakpoints that renders a `<picture>`/`srcset` so phones download the small derivative. Your `wide`/`narrow` responsive styles exist but the media displays use the plain image formatter. Recommended: one new responsive style **`article_hero_responsive`** (styles at 600/900/1200/1800 wide, all 16:9, `sizes="(min-width: 1360px) 1360px, 100vw"`) used by the media `article_hero` view mode; the teaser can stay a single 400×225 (two-per-row cards never exceed ~680px, so a 800×450 derivative with `sizes` is the only upgrade worth doing).
- **Media view mode** = which formatter and style a Media entity uses when embedded. `article_hero` and `article_teaser` already exist; add **`portrait_thumb`** (square 96×96) and **`portrait`** (square 400×400) for Person.
- **Card fallback**: keep the SVG tile; the template checks `field_image` and uses it when present, tile otherwise. No new mechanism needed.

### 2.9 External articles (3.5.2.1–3.5.2.5) — extend Link, not Article
The site already models "something elsewhere" as a **Link** node (`/explore/links/…`), and `field_series_items` and `field_outcomes` already accept links. Add a `field_link_kind` term or a boolean "treat as article" on Link, plus optional `field_image` and `field_author`, and let the article card template render a link-kind-article as an article card with an external icon. A second Article variant with an external URL splits the article listing logic and search for little gain. "Trending" I would park, as you suspect.

### 2.10 How many related topics to show on event cards (3.5.1.2)
An integer `field_related_topics_shown` (0 default) is simple and editor-controlled, as you describe, and ordering of related topics becomes meaningful. Alternative with no new field: show related terms that share the primary's top-level section. The integer is more predictable; go with it when that item comes up.

## 3. Content model

Reuse marked ♻; new storage marked ✦.

### Person (`person`) ✦
| Field | Type | Notes |
|---|---|---|
| title | built-in | auto: `first last` (presave hook), hidden on form |
| field_first_name ✦ | string | required |
| field_last_name ✦ | string | optional |
| field_image ♻ | media image | portrait |
| field_bio ✦ | text_long | |
| field_where ♻ | Location term | optional |
| field_content_component ♻ | paragraphs | same 20 bundles |
URL `/people/[node:title]`. View modes: `compact` (thumb + name, for article full), `teaser` (team card), `full`.

### Article — add `field_author` ✦ (entity_reference → person, unlimited)
Teaser/compact: names via entity label, comma list, only when set. Full: `compact` Person renders (thumb, name, link). Collaboration teasers at the end (section 4).

### Series (`series`) ✦
| Field | Type |
|---|---|
| field_series_items ✦ | entity_reference → event, article, link; unlimited; ordered |
| field_standfirst ♻ | string |
| field_description ✦ | text_long (shared with Collaboration) |
| field_series_start ✦ / field_series_end ✦ | datetime (date only); end optional |
| field_primary_topic ♻ / field_related_topics ♻ | topic terms |
URL `/series/[node:title]-[node:field_series_start:date:html_year]`. View modes: `teaser` (card for /explore/series), `full`.

### Event — no new fields; series + collaboration lines come from reverse lookups.

### Collaborator (`collaborator`) ✦ paragraph (see 2.2)
field_collaborator ✦ (entity_reference → organisation, person; 1) · field_collaborator_type ✦ (terms, new vocab `collaborator_type`: writer, sponsor, project manager, producer, supplier, partner, venue, funder; multiple) · field_text ♻ (how they collaborated).

### Collaboration (`collaboration`) ✦
field_collaborators ✦ (paragraphs: collaborator) · field_standfirst ♻ · field_description ♻ · field_outcomes ✦ (entity_reference → event, series, link, article; unlimited) · field_content_component ♻ · topics ♻. URL `/collaborations/[node:title]`. View modes: `full`, `teaser`.

### Organisation — add `field_where` ♻ and `field_content_component` ♻.

## 4. Logic: one relations service

`Drupal\customsolent_helpers\ContentRelations` (service `customsolent_helpers.content_relations`):
- `seriesContaining(NodeInterface $node): NodeInterface[]` — published series whose `field_series_items` target the node.
- `collaborationsFor(NodeInterface $node): NodeInterface[]` — direct via `field_outcomes`, plus via every series from `seriesContaining()`, unique by nid, published only.
- `collaborationsInvolving(NodeInterface $orgOrPerson)` — via collaborator paragraphs' `field_collaborator` → parent collaboration.
- `seriesStatus(NodeInterface $series): 'past'|'current'|'upcoming'|'ongoing'` and `seriesCounts()` (events/articles/links).
Every result array carries cache tags (`node_list:series`, `node_list:collaboration`, the found nodes' tags) which preprocess merges into the render array, and a `max-age` to the next midnight for status words. Theme preprocess calls the service and templates render; no per-page logic in render-cached cards (lesson from the kicker work).

## 5. Listing pages
- `series_listing` view: default = published series sorted start desc; display `view_display_listing_cards` (teaser rows, pager); `view_display_preview`. Placed on node 94 via a `view_display` paragraph (`field_listing_mode: full`, classy grid class `slnt-series-grid`), by an idempotent script. Added to `field_view.preselect_views`.
- `collaborations_listing` view, same shape, on node 95 (Phase 3).
- Section strip and `/{topic}/series` deliberately not in scope; can join `_customsolent_listing_kinds()` later.

## 6. Black History Month South 2026 content (Phase 1 data)
Seven events from blackhistorymonthsouth.com/events (fetched 2026-10-18), created published by `scripts/create_black_history_month_2026.php` (idempotent: keyed on the source URL stored in a map by title; re-runs update, never duplicate), plus Location terms Engine Rooms, Good Things and MAST Mayflower Studios under Southampton (Turner Sims, John Hansard Gallery, The Brook exist), plus the Series node "Black History Month" 1–31 Oct 2026 with the seven in `field_series_items` in date order. Topics proposed: Music for the five gigs, Screen for the two screenings; related Heritage & History, Identity, Festivals; Series primary Culture / Heritage & History. Breaking Patterns runs 29 Sep–19 Nov (a continuous screening) — kept in the series; the range on the card shows the series' own dates. Times not published for SOUL 45; EST Gee doors 19:00 from The Brook.

## 7. Phasing
1. **Series + event integration + BHM content** (this session, time-critical: events 24–30 Oct). Branch `series-and-black-history-month`, release `scripts/release-2026-10-18-series.sh`.
2. **Person + Article author + /about/team view** — no open questions; next.
3. **Collaboration + Collaborator + Organisation fields + "part of" listings on event/article/series/person/organisation** — after Rob confirms 2.2, 2.3, 2.6.
4. Near-future items (3.5): ~~event card → node link~~ (done 18 Oct), map/geolocation + what3words, related-topics count, external articles (Link variant), article images, regular-events-only view per topic (3.5.1.5), Series tab in the section strip, past events on organisation pages + /explore/archive, event-adding automation (Instagram, LinkedIn saved items, WhatsApp, newsletters via emClient), maybe contrib `extlink`.
5. Afternoon amends 18 Oct (log `2026-10-18-afternoon-amends.md`): bare-prefix redirects, top padding below 1200px, organisation cards → node page, related events on event pages, organiser on compact cards, Series / Collaboration kickers.
6. Added to the near-future list 18 Oct afternoon (Rob, not yet to do): Claude to pick regionally relevant stock images from Rob's personal photo archive (access later); more collaboration content (Mangang, Big Movement, Zoie Logic; past events; Venture South); populate Organisation `field_where` (multiple); a View WITHOUT regular events for the highlights section; promote links to other specialist directories; sub-text explaining the site below the welcome; spike: multi-level promote flags (front / section / sub-section) to balance topics and places; ultra-short standfirst for teasers; mobile Section Filter: central detented, draggable/swipeable; author on articles (field exists, populate + show); editorial process for looking after contributors. Separate task: terms re-alignment (Identity moving under Community etc.), tracked in GitHub issues.

Each phase: feature branch, config export, idempotent content script, dated release script, conversation log, Rob merges and runs on prod.
