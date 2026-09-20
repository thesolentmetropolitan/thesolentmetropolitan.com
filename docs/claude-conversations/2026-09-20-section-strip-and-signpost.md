# Section strip and signpost box

**Date:** 2026-09-20
**Branch:** `section-strip-and-signpost` (cut from `main`, which holds all of the section-pages work)
**Mock-up:** https://claude.ai/artifact/7fbgZKDxcL2a6qp6oWoNFh (private to Rob)
**Model:** Claude Fable 5.1 via Claude Code

---

## Rob's thinking that led here

The preview-plus-"View more" pattern suits **events and articles**, where "soonest" or "latest" is
a real editorial order. It does not suit **organisations**: the eight shown are just the first
eight alphabetically, which means nothing. Keep the route to the full list, lose the grid — but
without the link looking like a spare part on a page that is otherwise about events. Occasional
cases where a chosen few organisations *are* meaningful (sponsors, "who has joined the cause") are
possible later, so the capability should stay.

**Rule Rob set:** every Culture, Sectors and Living section page must be able to show events. Most
don't yet only because he hasn't set them up and there is no content. (Not About or Explore.)

Scale when this was decided: 13 section pages had events + organisations blocks, 55 organisations
only, 2 events only.

Two ideas were mocked up interactively; Rob chose **both**, with the strip **stacked** on narrow
screens.

## What was built

### 1. Section strip — `Overview · Events 12 · Organisations & links 38`
- Directly under the banner on every Culture / Sectors / Living section page **and** on their
  listing pages, with the current page marked (`aria-current`, magenta).
- **Navigation, not tabs.** Each item is a real page with its own address, so it is built as links
  in a `<nav>`, not an ARIA tab widget: shareable, printable, right for screen readers.
- **Never scrolls sideways.** One row on wide screens, wrapping to a second line if needed; below
  600px a stacked list of 44px rows with a caret on every row except the current one. Rob's
  reasoning: a swipe row needs an affordance and is painful with a mouse on a narrowed desktop
  window (NN/g), and stacking leaves room for more types.
- Counts: events = not yet ended (same rule as the listings); organisations + links = distinct
  items tagged with the topic or anything beneath it, primary or related.
- A type with nothing in it is left out; if only "Overview" would remain, no strip.
- **No content edits:** `customsolent_preprocess_field()` inserts it as the second item of the
  page's content field when the first item is the hero banner.
- Cached with `node_list:*` tags and a max-age to midnight, because the events count falls as
  dates pass.

### 2. Banner on listing pages
`/culture/music/events` and `/culture/music/organisations` now show the section's own hero banner,
then the strip, then the h1 ("Events" / "Organisations & links"). The banner renders in the
`listing_page` paragraph view mode, where the topic name is a span with the same look rather than
an h1 — so there is exactly one h1. (Bug met: the hero template used
`topic_trail_as_heading|default(true)`; Twig's `default` treats `false` as empty, so the flag was
ignored. Now tested with `is defined`.)

### 3. Signpost box in place of the organisations grid
White box, solent-blue top rule — the family of the front-page intro box:
"**37** organisations and **1** link for Music in the Greater Solent." · **Browse all 38 ›**.
A **Browse by** row of sub-topics appears when the topic has children that actually hold
something and the page has no section filter already doing that job (so Music gets it once its
genres exist).

**Listing mode** is now: *automatic* (field left empty) → **Preview** for events, **Signpost** for
organisations and links; or an explicit **Preview**, **Signpost** or **Full listing**. Preview
stays available for organisations as a deliberate per-page choice, for the sponsor / cause cases.

### 4. Every section page can show events
`scripts/ensure_section_page_listings.php` checked 82 section pages and added 67 Events listings
and 16 Organisations listings where missing — beside the page's existing listing, Events first,
and above a heading paragraph that belongs to the listing below it. A listing with nothing in it
renders nothing, so these are invisible until a topic gets its first event.

Independently, the listing **route** no longer needs a placed paragraph: if a section page has no
listing of the requested kind, the controller renders the same listing from an unsaved paragraph
belonging to that node, so the strip's links always work.

## Verified locally
| Check | Result |
|---|---|
| `/culture/music` | strip Overview · Events 2 · Organisations & links 38; 2 event cards; signpost with count; no organisation cards |
| `/culture/music/events`, `/organisations` | banner + strip with the right item current; exactly one h1; 24 cards + pager |
| `/culture` | strip Events 9 · Organisations & links 302 |
| `/living/community`, `/sectors/technology`, `/culture/stage/comedy` | two-item strip, signpost, no empty Events heading |
| `/explore/events`, `/explore/data`, `/`, About | no strip |
| Phone 375px | three stacked rows, 44px each, 328px wide; current row marked; no horizontal scroll |
| Script second run | 0 added; `config:status` clean |

## For later — Rob's notes, recorded so they aren't lost
- **Radio shows:** today a Link with the "Radio & Podcasts" topic. Rob prefers generic content
  types defined by their terms, not a Radio Show type. A strip item that means "type X *with* term
  Y" would mix two axes — a design discussion for later.
- **Artist / Author:** probably a future content type; open whether one type covers artists,
  authors who write here and other creative roles, or several.
- **Collaboration:** two or more organisations working together — not just music.
- **Venue:** stays a Location term (it has a town as its parent). An Organisation can also *be* a
  venue, or run several; ideally handled by marking an Organisation rather than a Venue type, so it
  stays reusable (e.g. in collaborations).
- **Genres:** sub-topics of Music. They are the *other axis* to the strip: each genre page gets
  its own strip automatically, and within Music a genre is a filter inside each type.
- At five or more types the stacked strip costs about 265px on a phone; revisit then.

## Still queued from Rob's list
2 wider content / smaller card type · 3 "Greater Solent" line break · 4 "View more Events" /
"View more Articles" · 5 populate Explore with events and articles · 6 404 page · 7 login page ·
8 maintenance page · 9 section headings clickable · 10 footer menu grid and underline.

## Production deploy
```bash
git pull origin main
composer install --no-dev --optimize-autoloader
bash scripts/release-2026-09-20-section-strip.sh
```
DDEV snapshot `pre-strip-signpost-20260920` was taken before applying locally.
