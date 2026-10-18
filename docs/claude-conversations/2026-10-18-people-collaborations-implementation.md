# People + Collaborations — implementation log

**Date:** 2026-10-18 (same session as the Series work; session start 00:46)
**Branch:** `people-and-collaborations` (branched from `series-and-black-history-month`; merge that first)
**Release:** `scripts/release-2026-10-18-people-collaborations.sh` (run after `release-2026-10-18-series.sh`)
**Brief:** `2026-10-18-people-series-collaborations-brief.md` (Phases 2 and 3)

Rob's decisions that shaped this (his reply after the Series work): Collaborator is a **node**, because one collaborator appears in more than one collaboration; location lives on **Organisation and Person**, not on Collaborator; **plural URL prefixes** (`/people/`, `/collaborations/`).

## Phase 2 — People

### Content model
- `node.type.person`: `field_first_name` (required), `field_last_name` (optional, mononyms), `field_image` (media image, "Portrait"), `field_bio` (text_long), `field_where` (Location), `field_content_component` (same 20 paragraph bundles as composite pages). The **title is composed on save** as "First Last": an `#entity_builders` callback on the Person form (fills the title before validation, so the title widget is simply left out of the form display) plus `hook_node_presave` for programmatic saves. Both in `customsolent_helpers.module`.
- Article gains `field_author` (entity_reference → person, unlimited), placed under the title on the form. Cards show it as plain names; the full page renders the Person in `compact` view mode.
- Image styles `portrait_thumb` (96×96) and `portrait` (400×400), both focal-point crops; media view modes and `media.image.*` displays to match.
- Pathauto `/people/[node:title]`; Rabbit Hole display page; `people_listing` view (by last name, then first), classy style `people_grid`, `preselect_views` updated.

### Theme
- `node--person--compact.html.twig` — byline chip (avatar or initials + name, one link). `node--person--teaser.html.twig` — team card (round portrait, name with stretched link, where, bio clamped to five lines). `node--person--full.html.twig` — portrait beside the name, bio in the reading column, **Articles by this person** (reverse lookup `ContentRelations::articlesBy()`, compact cards), content components, collaborations.
- Article templates: teaser prints "By A and B" as text (the whole card is one link), compact prints it under the summary, full renders `content.field_author` chips before the date.
- `css/people.css`.

### Content
- `scripts/shape_team_page_people.php`: a published Person for the founder (Rob Davis, Southampton, a two-paragraph third-person bio drawn from the page's own text — first draft for Rob to re-voice) and a "The team" People listing appended to /about/team's enclosure. The page's existing first-person text is untouched.

## Phase 3 — Collaborations

### Content model
- Vocabulary `collaborator_type`; starter terms by `scripts/create_collaborator_types.php`: Writer, Sponsor, Project manager, Producer, Supplier (Rob's five) + Partner, Venue, Funder. The field allows typing new terms straight into the form.
- `node.type.collaborator`: title, `field_collaborator` (→ organisation or person, required), `field_collaborator_type` (terms, multiple, tags widget), `field_description` (shared text_long). **No page of its own:** Rabbit Hole `page_redirect` 301 to `[node:field_collaborator:entity:url]`, fallback access denied. Alias `/collaborators/<title>` only keeps admin links tidy.
- `node.type.collaboration`: `field_standfirst`, `field_collaborators` (→ collaborator, unlimited, ordered), `field_description`, `field_outcomes` (→ event, article, series, link; unlimited), `field_content_component`, topics. Pathauto `/collaborations/[node:title]`.
- Organisation gains `field_where` and `field_content_component` (form + full display; hidden on cards).
- `collaborations_listing` view (newest first), classy style `collaborations_grid`, `preselect_views` updated; placed on /explore/collaborations by `scripts/add_collaborations_listing.php` (into the enclosure that holds the intro text; the page's by-topic enclosure is left alone).

### Logic (`ContentRelations`)
- `collaborationsFor($node)`: collaborations whose `field_outcomes` hold the node **or any series the node is in** (the series lookup is reused), unique, newest first.
- `collaborationsInvolving($orgOrPerson)`: collaborator nodes referencing them → collaborations referencing those collaborators.
- `collaborationsCacheTags()`: `node_list:collaboration`, `node_list:collaborator`, `node_list:series` + each found node's tag, so creating or editing any of the three refreshes pages cached with no collaborations.

### Theme
- `node--collaboration--teaser.html.twig` (card: title, standfirst, "A, B and 2 more" with a people icon, outcome counts, kicker), `node--collaboration--full.html.twig` (trail, h1, standfirst, **Who** list of collaborator rows, description, outcomes grouped Events / Articles / Series / Links, content components, Also in), `node--collaborator--teaser.html.twig` (organisation link or Person chip + role tags + description).
- `components/collaborations-block.html.twig` — "Part of a collaboration" / "Collaborations" with teaser cards; included on event, article, series, link, person and organisation full pages. Organisation full page also now renders Where and content components.
- `css/collaborations.css`; the shared "Also in" helper `_customsolent_related_topic_links()` now serves series and collaboration pages.

## Verified locally (screenshots in `2026-10-18-people-collaborations/`)
- /about/team: "The team" heading and Rob's card (initials avatar, Southampton, clamped bio).
- /people/rob-davis: header, bio, Articles (1) when an article was given the author (test assignment, reverted).
- Article full page: "By [RD] Rob Davis · 5 August 2024"; compact card: "By Rob Davis" above the kicker.
- A throw-away test collaboration (BHM South producer/partner, John Hansard Gallery venue, Rob writer; outcome = the Black History Month series) rendered on /explore/collaborations, its own page, the series page, the Breaking Patterns event page (via the series), the gallery's organisation page and Rob's person page; `/collaborators/test-john-hansard-gallery` answered 301 to `/organisations/john-hansard-gallery`. Test content deleted afterwards so local matches production.
- `drush config:status` clean; no PHP errors; release script rehearsed from the `pre-people` DDEV snapshot.

## Notes for Rob
- No real Collaboration content was created: the Hoglands Park skatepark pilot from 30 September is ready to enter in the editor (Collaborators first, then the Collaboration).
- Organisation `field_where` is empty for all 497 organisations; fill as you go, or say the word and I script the obvious ones from location mentions in titles.
- `field_author` is empty on all 16 articles; set in the editor (Rob Davis exists as a Person).
- Series and Collaboration pages are not in the section strip; later if wanted.
