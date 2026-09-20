# The Solent Metropolitan — Section pages: per-type listings, cards, "View more"

**Date:** 2026-09-20
**Branch:** `section-pages-listings` (cut from `main`)
**Status:** Brief for Rob to review. No code written yet.
**Drupal 11 compatible.**

---

## The problem

Section pages such as `/culture/music` show several kinds of content on one page — an events
listing, then organisations, sometimes links — each added with a View Display paragraph, and
there is pagination at the bottom. Which list does the pager belong to? It isn't clear.

It is worse than unclear. Every listing display uses a full pager on the **same page counter**
(`?page=`), so on `/culture/music` clicking "2" pages the events *and* the organisations together.
And a single View Display paragraph can already hold two lists: the "primary and related" display
renders the primary-topic list and then the related-topic list, each with its own pager. So even a
page with one listing has the problem.

### Scale (published content, 2026-09-20)

| | |
|---|---|
| Composite pages with at least one listing | 74 |
| …of which have two or more listings | 24 |
| …of which have a section filter | 10 |
| Topic terms | 107 |
| Organisations / events / links / articles | 463 / 52 / 21 / 7 |
| Under *Culture / Music* alone | 37 organisations, 12 events, 1 link |

Which displays those 74 pages use:

| Uses | View : display |
|---|---|
| 68 | `organisations_listing : view_display_primary_and_related` |
| 17 | `links_listing : view_display_primary_and_related` |
| 16 | `events_listing : view_display_primary_and_related` |
| 1 each | the two front-page displays; `organisations_listing : view_display_orgs_directories_page` |

---

## The approach (agreed in discussion)

Follow the BBC home page pattern the front page already uses: a section page shows a **fixed
number of items per content type**, each with a **"View more"** link to a listing page that is
specific to that content type. The filter and the pager live on that listing page, where they are
unambiguous.

### Decisions taken — don't revisit without asking Rob

| Decision | Choice |
|---|---|
| Items in a fixed block | **8** (two rows of four, like the front page) |
| Carousel / swipe | **No.** A swipeable row implies it holds everything, which argues with the "View more" beside it |
| Listing URLs | `/{topic path}/events` and `/{topic path}/organisations` |
| URL word for the combined organisations + links list | **`organisations`** — see below |
| Organisations and links | **One combined list**, alphabetical, each card labelled *Organisation* or *Link*. They stay separate content types |
| Listing pages as nodes? | **No** — one automated route. But a real page at the same address always wins |
| Event button text | Uniform **"Event info"**, not the per-event link title |

### Why `organisations`

`/explore/organisations` already means "all organisations" and `/explore/events` already means
"all events". So `/culture/music/events` and `/culture/music/organisations` follow a rule a visitor
learns once: the same word, narrowed by the path in front of it. It also works for genres with no
extra work: `/culture/music/jazz/events`.

Rejected: *directory* and *network* — on Explore, "Directories & Networks" means lists of other
people's lists, and one word with two meanings on the same site is the thing to avoid. *Guide* is
opaque. *Connect / connection* suggests people networking. *organisations-and-links* is honest but
is our terminology, not the visitor's.

The URL and the heading need not match. URL `organisations`; on-page heading "Organisations &
links". With 463 organisations to 21 links the page is overwhelmingly organisations anyway. If
links ever grow into their own thing, `/{topic path}/links` is still free.

---

## Design

### 1. Shared card for organisations and links *(step 1 — self-contained)*

Today both are plain teaser rows and a long standfirst runs the full width of the page.

- One card design used by **both** content types, in the `compact` view mode (the generic node
  view mode the event and article cards already use): new
  `core.entity_view_display.node.organisation.compact.yml` and `…link.compact.yml`,
  `node--organisation--compact.html.twig`, `node--link--compact.html.twig` (the second can simply
  include the first), one CSS file.
- White box on the page, square corners, 1px solent-blue top rule — the same family as the event
  card. Title with the external-link icon (both types point off-site via `field_url`); standfirst
  clamped to three lines; topic term pinned to the bottom of the card; a small **type label**
  ("Organisation" / "Link").
- Line length fixes itself: a card column is narrow. **Three columns** on desktop suits text-heavy
  cards better than four; two on tablets and phones; one on very narrow screens.
- DOM order title → standfirst → topic term, so heading navigation hears the whole card (the
  lesson from the front-page cards). Stretched-link pattern so the whole card is clickable while
  the accessible name is just the title; topic links stay independently clickable.
- The grid is CSS scoped by a classy style on the View Display paragraph, as with the other grids.
- The type label is what makes the combined list — and later the search centre — a config change
  rather than a redesign.

### 2. Uniform "Event info" button *(step 1)*

The event teaser button prints the link title an editor typed on each event
(`field_url_text` in `node--event--teaser.html.twig`), so buttons vary in width and the listing
looks ragged. Fix the visible text to **"Event info"** in the template, keep the external-link
icon, and append the event title as visually-hidden text so each is announced distinctly
("Event info: Southampton Film Week") — the same technique as the front page "View more" links.
"Event info" rather than "Info" so it still makes sense out of context; it suits free events too.

### 3. Preview blocks on section pages *(step 2)*

**A finding that shapes this.** `/explore/events`, `/explore/organisations` and `/culture` use the
*same* `view_display_primary_and_related` displays as the topic pages. Those Explore pages *are*
listing pages and must keep their filter and pager. So "change what the display does" cannot be
blanket.

Proposal: a small list field on the View Display paragraph, **`field_listing_mode`**, with two
values:

- **Preview** (default) — up to 8 items, no pager, automatic "View more".
- **Full listing** — filter and pager, as today.

Default *Preview* means the 70-odd topic pages change behaviour with **no content edits**. A short
script sets *Full listing* on the handful of pages whose whole purpose is one listing (the Explore
pages). Editors can see and change the choice. Display machine names are **not** renamed — a
viewsreference field pointing at a renamed display strands old revisions on a dead id and cron
then loops on broken renders (this has bitten before).

**Automatic "View more".** Placing Link paragraphs by hand on 74 pages is not realistic. The View
Display paragraph already knows its topic and its view, so in Preview mode it renders the link
itself: URL = the page's topic path + the type word; hidden when the topic has no more items than
are shown; same look and hidden-heading accessible name as the front-page links. On the front
page the hand-placed links stay as they are.

**One list, not two.** In Preview mode primary-topic and related-topic items appear as one list of
8, primary first. See "The one hard part" below.

### 4. Automated listing pages *(step 2)*

One route in `customsolent_helpers`, not ~200 nodes (2 types × 107 topics).

- An inbound path processor recognises `/{an existing topic page's path}/{events|organisations}`
  and maps it to an internal route carrying the topic term id and the type. A controller renders:
  topic trail, a heading ("Music: events"), the section filter for that topic's children, the
  list, **one** pager.
- The `?topic=` filter convention carries over, so music genres work as soon as the sub-terms
  exist.
- **A real page wins.** The processor only acts when no path alias matches, so creating a
  composite page at `/culture/music/jazz/events` overrides the automatic one. Rob is never locked
  out of customising a single listing.
- **Reserved words.** `events`, `organisations`, `links`, `articles` may not be used as a child
  topic's name; validation on the topic term form, with a clear message.
- Menu highlighting and the topic trail work from the path prefix, which is the topic's own path.
- Cache contexts `url.path`, `url.query_args:topic` and `url.query_args.pagers`; cache tags
  `node_list:{type}`.

### The one hard part: primary + related in one paged list

Today the "primary and related" display is *virtual*: the paragraph template renders the
primary-topic display, then the related-topic display — two queries, two pagers. A listing page
needs **one** query: items whose primary topic **or** any related topic is in scope, de-duplicated,
with a single sort and a single pager.

Proposed: a new display per listing view, `view_display_topic_listing`, taking one contextual
argument (the scope's term ids, `+`-joined as now) applied as an OR across both topic fields via
`hook_views_query_alter` in `customsolent_helpers`, `DISTINCT` on nid. Sort: events by start date
ascending, upcoming only; organisations + links by title. Preview blocks use the same query with a
limit of 8 plus a primary-first sort, so a section page and its listing page can never disagree
about what belongs to a topic.

Two Views gotchas already recorded for this site apply here: contextual filters taking multiple
values need **both** "allow multiple" settings or the validator silently drops the filter and every
node matches; and pager element 0 is request-global, so an embedded view can clobber a page's
pager (the existing fix lives in `customsolent_helpers_views_pre_build`).

### 5. Combined organisations + links view

Both are nodes, so one view filtered to `type IN (organisation, link)` sorted by title. New view
`directory_listing` (machine name only — nothing visitor-facing says "directory") with the
`view_display_topic_listing` display. `organisations_listing` and `links_listing` stay, because
74 pages reference them; in Preview mode the paragraph can render the combined view in place of
either, and a page holding both an organisations block and a links block collapses them into one.
That collapse is the fiddliest content rule here — **decision needed** (open question 1).

---

## Bespoke pages and special weeks (Rob's point 5)

- `/culture/music/jazz` is already an editable composite page. A jazz festival slice belongs
  there.
- `/culture/music/jazz/events` and `…/organisations` stay automated — and overridable, per above.
- **Special days and weeks** need no override. There is already an *Explore / Series* topic and
  page. "Mental Health Awareness Week" becomes a term under Series; relevant events get it as a
  **related topic**; a composite page lists them with the existing View Display paragraph. Rob
  chooses which events get the tag, which handles "sometimes all events are relevant, not always".
  `/living/mental-health` carries a slice pointing to it. No new mechanism.

## Keeping the future "search centre" possible (Rob's point 2)

Not built now. Two things in this work keep it cheap later:

- Listing logic takes two inputs — **topic scope** and **content type(s)** — in one reusable place
  (the query alter plus a small scope-resolving service), so `/search/culture/music/jazz` can
  resolve its scope the same way.
- The **shared, type-labelled card** is what a mixed-type results page needs.

Search today is Drupal core search. A filtered, cross-type, keyword listing is really a Search API
job (database backend would do). That is the likely prerequisite when the time comes.

## Location on organisations (Rob's point 5 of the original list — later)

Events already carry `field_where` against the Location vocabulary (venue with a parent town).
Reuse the same field and vocabulary on Organisation, optional, **town level only**. Low
maintenance, not sensitive, and sparse data is fine because the card omits it when empty. It later
becomes a search facet for free. To be weighed against the conclusions of the separate
colour-scheme chat about how much is stored per organisation, which this session cannot see.

---

## Order of work

| Step | What | Depends on |
|---|---|---|
| **1** | Shared organisation/link card (compact view mode, template, CSS, classy grid style); uniform "Event info" button | nothing — visible, self-contained, deployable alone |
| **2a** | `view_display_topic_listing` displays + query alter (primary OR related, one pager); combined view | — |
| **2b** | Listing route: path processor, controller, reserved words, real-page-wins | 2a |
| **2c** | `field_listing_mode`, Preview rendering with automatic "View more", script to mark the Explore pages *Full listing* | 2a, 2b |
| later | Location on organisations; search centre | 1, 2 |

Each step ends with a release script in the established pattern (backup → maintenance → `cim` →
content scripts → `cr`), rehearsed locally against a pre-change database first.

## Open questions for Rob

1. **Pages that have both an organisations block and a links block today** (17 pages have a links
   listing). In Preview mode, collapse them into one "Organisations & links" block automatically,
   or leave the blocks as the editor placed them and only combine on the listing page?
   *Recommendation: collapse automatically — a links block with one item looks thin.*
2. **Events preview: upcoming only?** *Recommendation: yes, soonest first, matching the front page;
   the listing page can offer past events later.*
3. **Articles.** Only 7 exist and no topic page lists them yet. Reserve `/{topic}/articles` now and
   build it when there is content? *Recommendation: reserve the word, build later.*
4. **Heading wording on listing pages** — "Music: events", "Events in Music", or just "Events" under
   the topic trail? *Recommendation: topic trail above, plain "Events" as the h1.*

## Verification checklist (for when it is built)

- [ ] `/culture/music`: two blocks of up to 8, no pager anywhere on the page, a "View more" per block
- [ ] "View more" absent where a topic has 8 or fewer items of that type
- [ ] `/culture/music/events` and `/culture/music/organisations`: one list, one pager; `?page=2` pages only that list
- [ ] Filter on a listing page narrows to child topics and survives paging
- [ ] An item tagged by related topic only appears once, in the right list
- [ ] `/explore/events`, `/explore/organisations`, `/culture` behave exactly as before
- [ ] A composite page created at `/culture/music/jazz/events` replaces the automatic page
- [ ] A topic term named "Events" is refused with a clear message
- [ ] Cards: 3 / 2 / 1 columns; standfirst clamps at three lines; type label present; whole card clickable, topic links independent; focus ring visible; no horizontal scroll
- [ ] Event buttons all read "Event info", equal width; screen reader hears the event title
- [ ] Front page unchanged
- [ ] `drush cex --diff` shows no unexpected drift
