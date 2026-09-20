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
