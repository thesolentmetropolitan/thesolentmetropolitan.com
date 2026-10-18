# Topic re-alignment — batch 1 (18–19 October 2026)

Session: evening 18 Oct (from 23:04) into 19 Oct. Branch `topic-realignment-2026-10-19`.

## Why

As events and organisations were added, the content showed which topic terms
needed renaming, which were unused, and which should become sub-sub terms.
Rob asked for a review list drawn from the open GitHub issues, then a first
batch of changes.

## Review list (from 282 open issues, 36 touch the taxonomy)

**New terms suggested:** 408 conferences / exhibitions (Culture and Sectors);
349 nature & environment in Culture; 327 workshops for business; training for
Sectors (no issue); 316 theatre and 400 open mic under Stage; 405 fiction and
406 poetry under Writing; 311 street art; 287 / 288 sport and music sub-terms;
249 news outlet under Media; 300 budgeting / money under Living; 410 wellbeing;
409 micro manufacturing; 299 scientific research; 340 international outreach;
294 advertising / cultural growth / business community; 323 entrepreneur
expanded.

**Renames:** 322 Stage; 319 / 354 Theatre Schools; 360 Active & Outdoor; 321
Maritime & Marine; 320 Venue & Events; 318 Media & Publishing; 333
Organisations & links; 295 Arts & Culture children; Living / Disablity typo.

**Moves, merges, deletions:** 428 Identity under Community; Culture / Workshops
children (Acting Schools overlaps Stage / Theatre Schools and Screen / Film
Acting Schools); eight Sectors terms with no primary organisation (Consulting,
Finance, Legal, Logistics, Manufacturing, Marketing, Trades, Utilities);
Explore / Jobs Boards unused; 292 what defines Sectors.

**Closed as done:** 176, 183, 295 (comment links 319, 354), 333.

**Rob's decisions on 18 Oct (done by hand on prod, exported):** 292 is editorial
guidance (Sectors = B2B audience plus people wanting to work in a sector; to go
in the /sectors intro); 317 Made in Solent is a Series node; Disablity typo
fixed; Arts & Culture children renamed.

## Batch 1 — what changed

| Issue | From | To | Old address | New address |
|---|---|---|---|---|
| 360 | Culture / Outdoor & Active | Culture / Active & Outdoor | /culture/outdoor-active | /culture/active-outdoor |
| 318 | Sectors / Media | Sectors / Media & Publishing | /sectors/media | /sectors/media-publishing |
| 321 | Culture / Maritime | Culture / Maritime & Marine | /culture/maritime | /culture/maritime-marine |
| 321 | Sectors / Maritime | Sectors / Maritime & Marine | /sectors/maritime | /sectors/maritime-marine |
| — | Explore / Jobs Boards (term 108, unpublished page 102) | deleted | — | — |

### A topic is five things that must agree

Kicker links are built from the term name by convention
(`_customsolent_build_term_url`: "Culture / Active & Outdoor" →
`/culture/active-outdoor`), and the hero tile is looked up by the same slug
(`culture_active_outdoor.svg`). So a rename touches:

1. the Topic term's name;
2. the composite page's title and its hero paragraph's `field_title`;
3. the composite page's URL alias (saving the changed alias lets the Redirect
   module add the old → new 301 itself);
4. the main-menu link's title and `internal:` uri;
5. redirects for the five automatic listings under the old address
   (`/events`, `/articles`, `/series`, `/collaborations`, `/organisations`),
   targeted at `internal:/topic-listing/{nid}/{word}` so the outbound path
   processor prints whatever alias the page has, now and after any later rename;
6. hand-written links in rich text (the Sectors Maritime intro linked to
   `/culture/maritime`).

All six are done by `scripts/realign_topics_2026-10-19.php` (idempotent,
`--dry-run`, matches by name and alias, never by id). Terms, nodes, menu links
and redirects are content, so the script is the deployable artefact;
`structure_sync.data.yml` was re-exported (`drush et`, `drush em`, `drush cex`)
for the record.

### Code and config

- Hero tiles renamed to the new slugs (`culture_active_outdoor.svg`,
  `culture_maritime_marine.svg`, `sectors_maritime_marine.svg`,
  `sectors_media_publishing.svg`); `explore_jobs_boards.svg` removed.
  `css/hero-art-styles.css` and the generator (`build_tiles.py`, its CSS copy)
  updated to match.
- Classy art-style **ids and class names kept** (`hero_art_style_culture_outdoor_active`,
  `.hero-art-style--culture-outdoor-active`): renaming a config entity id would
  mean re-pointing the hero paragraphs on prod for no visible gain. Labels
  updated so editors see the new names; the Jobs boards style deleted.

### Release

`scripts/release-2026-10-19-topic-realignment.sh`: backup → maintenance →
data script → `config:import` → `cr` → maintenance off. Data script runs before
the import so the Jobs boards page is gone before its art style is.
Rehearsed locally twice (second run all OK, nothing changed).

### Verified locally

Old base and listing addresses 301 to the new ones; new pages 200 with the
new titles ("Active & Outdoor: Events"); menu labels, kickers on organisation
pages, the strip and the Sectors Maritime intro link all use the new
addresses; four renamed tiles served, Jobs boards tile 404; no watchdog errors;
`config:status` clean.

## Still open from the review list

Everything in "New terms", the other renames (320, 322, 319/354), 428 Identity
under Community, the Workshops / Acting Schools overlap, the unused Sectors
terms. Constraint: Culture has 26 menu topics, so a new second-level term needs
a retired one; sub-sub terms cost nothing. Reserved last words: Events,
Articles, Series, Collaborations, Organisations, Links.
