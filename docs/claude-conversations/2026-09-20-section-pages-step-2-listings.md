# Section pages, step 2 — preview blocks, automatic "View more", listing pages

**Date:** 2026-09-20
**Branch:** `section-pages-listings`
**Brief:** `2026-09-20-section-pages-listings-brief.md` · step 1 log: `…-step-1-cards.md`
**Model:** Claude Fable 5.1 via Claude Code

---

## What a visitor sees now

- **Section page** (`/culture/music`): an *Events* block and an *Organisations & links* block, each
  up to **8 cards** in rows of four, **no pager anywhere**. Where a topic has more than 8, a
  **"View more"** link sits on the heading's line (desktop) or under the cards (phones).
- **Listing page** (`/culture/music/events`, `/culture/music/organisations`): topic trail, a plain
  h1, the topic filter where the topic has children, the full list, **one** pager. Organisations
  and links are one A–Z list, each card labelled.
- `/explore/events`, `/explore/directories-networks`, `/explore/places-maps` are unchanged: they
  *are* listing pages.

## How it works

### One paragraph, three behaviours
`_customsolent_listing_mode()` in `customsolent.theme` decides per View Display paragraph:

| Mode | When | Result |
|---|---|---|
| `plain` | not an events / organisations / links listing by topic (front page grids etc.) | untouched |
| `full` | the new **Listing mode** field says *Full listing*, or the paragraph is being rendered on its listing page | filter + pager, as before |
| `preview` | otherwise — **the default, including every existing paragraph** | 8 cards + View more |

So the ~70 topic pages changed behaviour with **no content edits**. Display machine names were
not renamed.

### Listing pages are the section page's own paragraph
`TopicListingController` does not build a list. It finds the View Display paragraph of that type
on the topic's page and renders **that paragraph** in a new paragraph view mode, `listing_page`,
which flips it to `full`. Section page and listing page therefore resolve their topic scope through
exactly the same code (`_customsolent_resolve_topic_context`, including an explicit topic on the
paragraph or on a section filter) and cannot disagree about what belongs to a topic.

### URLs
`TopicListingPathProcessor` maps `/culture/music/events` ⇄ `/topic-listing/24/events`, inbound and
outbound, so pager and filter links stay pretty. It runs before core's alias processor.
**A real page wins:** if the full path is itself an alias, the processor steps aside — verified by
creating a temporary alias at `/culture/music/events` and seeing that node render, then removing
it. A word with no route (`/culture/music/links`) is a 404.

### Previews
New display `view_display_preview` on `events_listing` and on a new view **`directory_listing`**
(organisations + links, A–Z; a duplicate of `organisations_listing` with both types). Both reuse
the existing single OR query (primary **or** related topic, de-duplicated). They fetch **9** rows:
the theme trims the ninth and uses its existence to switch "View more" on, so there is no second
count query and no link to a page that would show nothing new.

Generated through the Views / Field APIs by `scripts/generate_listing_preview_displays.php` and
exported — safer than hand-written Views YAML. That script is a local generator, not part of the
release.

### Heading and "View more" on one row, without editing 74 pages
On section pages the heading is usually a *separate* heading paragraph above the listing. In
Preview mode the listing takes that text and renders heading + link as one row; the heading
paragraph renders nothing (`customsolent_preprocess_paragraph__heading`, with the listing's cache
tag added so it re-renders when the listing is edited). Side benefit: an empty block takes its
heading with it — no more "Events" heading over nothing.

Exception: if the visitor has a topic filter active and a block has no matches, the block stays
and says "Nothing here matches the current filter." A heading that vanishes right after a click
looks like a fault.

### Combining organisations and links (Rob's answer 1)
Any organisations *or* links block renders the combined preview. Where a page has both, the first
renders it and the later one renders nothing (`/explore/data`: two blocks → one). A heading that
reads just "Organisations" or "Links" becomes "Organisations & links"; any other wording the
editor chose is kept. An editor-marked *Full listing* page keeps exactly the view it was given.

### Smaller pieces
- **Card grid is automatic** for these listings now; the classy style from step 1 is no longer
  needed for them (left in place — harmless, and still available for other uses).
- **Filter carries over:** "View more" from `/sectors?topic=153` goes to
  `/sectors/organisations?topic=153`, and the listing page opens with that filter active.
- **Kickers on listing pages** behave as on the section page (`KickerLazyBuilder` now resolves
  `/culture/music/events` to the Music page).
- **Reserved words:** a topic named Events, Organisations, Links or Articles is refused on the
  term form (`Explore / …` exempt). `links` and `articles` are reserved ahead of being built.
- Listing pages share the off-white page background (body class set for the route).

## Verified locally

| Check | Result |
|---|---|
| `/culture/music` | 2 preview blocks, 0 pagers, 8 directory cards, 2 event cards |
| `/culture/music/organisations` | 24 cards + pager; `?page=1` → 14; total 38 (37 orgs + 1 link) |
| `/culture/music/events` | 2 event teasers with date pills |
| `/culture/music/links` | 404 |
| `/culture/events` | topic filter present; `?topic=34` narrows and marks the option active |
| `/explore/events`, `/explore/directories-networks`, `/explore/places-maps` | full listings, no preview |
| `/explore/data` | two blocks collapsed into one, with View more |
| Real page at the same address | wins; listing returns when it is removed |
| Reserved words | Events / Organisations / Links refused; Jazz, "Events & Festivals", `Explore / Events` allowed |
| Listing page | one h1; trail Culture → Music; Culture lit in the menu; off-white background |
| Phone (375px) | one card column; View more below the cards; only one of each link pair reachable |
| Horizontal scroll | none |
| `config:status` | clean |

## For Rob

- **A topic with 8 or fewer events shows no "View more"**, so from that section page there is no
  route to the events listing and its date pills. Deliberate (nothing more to see), but say if
  you'd rather the link always showed for events.
- `/explore/organisations` uses its own non-topic display, so it is untouched and still
  organisations only. Everything reached through a topic is the combined list.
- Music genres: add them as child terms of *Culture / Music* and the filter appears on
  `/culture/music/events` and `/culture/music/organisations` by itself.
- The "Work in progress" note is still on section pages such as `/culture/music`.

## Production deploy

```bash
git pull origin main
composer install --no-dev --optimize-autoloader
bash scripts/release-2026-09-20-section-listings.sh
```

This script includes step 1's card release, so it is the only one to run. DDEV snapshot
`pre-step2-20260920` was taken before applying locally.

---

## Follow-up — Rob's review: a caching bug, events grid, two new filters

Rob confirmed two behaviours as wanted: no "View more" at 8 or fewer events, and an empty block
disappearing along with its heading.

### The `/culture` bug — mine
*Symptom:* filter stuck on "Art & Design", nothing listed, other topics did nothing.

*Cause:* the page was cached without varying by `?topic=`. Every listing used to render a paged
view, and a paged view brings the broad `url` cache context with it. A preview block does not
always render a view — an empty block renders nothing, and the "nothing matches" message is plain
markup — so `/culture` was stored in whichever filter state was requested first (`?topic=34`, from
one of my own tests) and served to everyone.

*Why the existing safeguard didn't catch it:* `customsolent_paragraph_view_alter()` in
`customsolent.theme` was written to add exactly that cache context, but entity view-alter hooks
are invoked on **modules only**, never themes. It had never run; the views' own `url` context had
been masking that since it was written.

*Fix:* the hook now lives in `customsolent_helpers.module` and covers listings, section filters and
headings. Independently, every by-topic listing prints a small `listing_cache` render array in
**every** template branch, carrying the `?topic=` / `?date_filter_id=` contexts and the
`node_list:*` tags — so a block that is empty today reappears when an event is added, and is never
cached as empty for someone else's filter.

### `/explore/events` as a card grid
New display `events_listing : view_display_listing_cards` (generated via the API): the same
by-topic OR query, the exposed date filter and a full pager, with compact-card rows, 24 per page.
All **full** events listings use it, so `/culture/music/events` is a grid too, consistent with
`/explore/events`. Works with the existing Section Filter and the date pills together. Beside the
filter sidebar the grid is three columns rather than four (four gave ~210px cards).

The event *teaser* — and its "Event info" button from step 1 — is therefore no longer used by any
by-topic listing. Left in place; say if the row layout is wanted back anywhere.

### Filters on Directories & Networks and on Organisations
Both get the same Section Filter paragraph as `/explore/events` (Culture / Sectors / Living,
expanding to sub-topics), added by `scripts/add_explore_listing_filters.php`.

They needed something new underneath. Everywhere else `?topic=` *replaces* the list's scope with
the chosen sub-tree. These two lists are scoped differently — Directories & Networks to its own
Explore term, Organisations to nothing at all — so choosing "Culture" has to narrow **within** the
list. `customsolent_helpers_views_query_alter()` adds that as an AND condition (primary or related
topic in the chosen sub-tree) whenever `?topic=` has not already been consumed as the view's scope.
First attempt missed Organisations: its display has no contextual filters and silently ignores the
arguments the template passes, so the "already consumed?" check was wrongly satisfied. It now only
counts a display that actually has argument handlers.

`view_display_orgs_directories_page` moves from 10 to 24 per page (cards sit three to a row).

### Verified
| Check | Result |
|---|---|
| `/culture` | starts on All Topics, 8 cards; `?topic=44` (Music) shows its 2 events; back to `/culture` still All Topics |
| `/explore/events` | 11 cards, date pills present; Culture → 9; Music → 2 |
| `/explore/directories-networks` | filter present; Culture / Sectors / Living → 12 / 17 / 7 on page one |
| `/explore/organisations` | 20 pages → 13 (Culture) / 12 (Sectors) / 5 (Living) / 2 (Music); page 2 keeps the filter |
| Desktop layout | sidebar + grid; selected branch expands to sub-topics |

Release script now has 9 steps (the filter script is step 7).
