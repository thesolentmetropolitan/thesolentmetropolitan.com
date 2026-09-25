# 2026-09-24 — Writing lists all articles; topic filter on /explore/articles

Branch: `writing-all-articles-and-articles-filter`
Session: started 13:48 BST (resumed session; the first part of this work
was done earlier the same day and committed as 9a6719c).

## Rob's brief

> On the /culture/writing page, node/27, I have a special case whereby it
> seems to make sense to list all our articles (regardless of topics) —
> say the 8 fixed first and then a link to /culture/writing/articles —
> this is essentially the same as /explore/articles. So I think the best
> thing is to list the latest 8 Articles (of all topics) … and this added
> as an entry in the field_view_display on node 27. Then the View more
> articles links to /explore/articles (node/92) — the dedicated articles
> page — this re-uses that page and allows us to add more functionality
> like filtering and bespoke stuff rather than overloading it as a
> subsection of /culture/writing. On the subject of node/92 — I need to
> add the general filter — please remind me how — I can check other pages
> as examples.

## Part 1 (commit 9a6719c): "Preview, all topics" listing mode

Rather than special-casing the Writing page, the View Display paragraph's
existing Listing mode dropdown gained a fourth option, "Preview, all
topics". It keeps the preview chrome (heading, "View more" beside it, up
to eight cards, second link below for narrow screens) but:

- resolves the topic scope as all-topics instead of the page's own term —
  done where the context is resolved, so `primary_topic_tid`, the
  `page_topic_tids` request attribute and the card kickers all agree with
  what the block shows;
- points "View more" at the site-wide listing page (/explore/articles,
  /explore/events, /explore/organisations) and always renders it, since
  that page is richer (filter, pager) whatever the count.

`scripts/set_writing_articles_all_topics.php` sets the mode on the
Writing page's existing articles listing (page found by alias, paragraph
by walking the page; idempotent; `--dry-run`). Verified: /culture/writing
shows all seven published articles, heading and both links go to
/explore/articles; /culture/identity unchanged.

## Part 2 (this session): the general filter on /explore/articles

### How the filter works — the reminder Rob asked for

The "general filter" is the **Section Filter** paragraph type. It is
placed on a Composite Page directly above the View Display paragraph, in
the same enclosure, with **Show sub-topics** ticked and **Topic** left
empty. Left empty it offers Culture / Sectors / Living, and with
sub-topics on, choosing a section expands to that section's child topics.
That is exactly how /explore/events (node 91) has it, and how
/explore/organisations and /explore/directories-networks got theirs
on 2026-09-20.

Nothing else is needed for articles because the plumbing was already
general:

- `customsolent_helpers_topic_filtered_views()` already lists
  `articles_listing`, so `customsolent_helpers_views_query_alter()`
  makes `?topic=<tid>` narrow WITHIN the list (primary OR related topic,
  the chosen term and everything beneath it). It does that here because
  `articles_listing:view_display_listing_cards` deliberately has no
  contextual filters, so nothing consumes `?topic=` as scope.
- That display's cache metadata already carries `url.query_args`, so the
  filtered pages are cached separately (the memory rule about Views cache
  metadata not being recomputed on cim does not bite here).

So the editor route is: edit node 92 → in the enclosure holding the
Articles listing, add a Section Filter paragraph above it, tick Show
sub-topics, save. The scripted route, which is what this branch does, is
below.

### What was done

`scripts/add_explore_listing_filters.php` — the 2026-09-20 script that
added the filter to Organisations and Directories & Networks — gained
`'Explore / Articles'` in its page list. It finds the page by primary
topic term, is idempotent, and inserts the paragraph immediately before
the listing in whatever field holds it. Run locally: the two existing
pages reported "already has a topic filter", /explore/articles got one
above listing paragraph 811.

`scripts/release-2026-09-24-writing-all-articles.sh` gained a step 6 that
runs this script on prod (now 8 steps) and a check-by-eye line for
/explore/articles.

### Verified locally

| URL | Cards | Matches |
|---|---|---|
| /explore/articles | 7 | all published articles; filter shows Culture · Sectors · Living |
| ?topic=29 Culture | 2 | the two Culture / Identity articles |
| ?topic=30 Sectors | 5 | four with a Sectors primary topic plus node 8, which has no primary topic and Sectors / Public Sector as related |
| ?topic=31 Living | 0 | no articles tagged Living |
| ?topic=121 Culture / Identity | 2 | sub-topic pill works; no kicker on the cards, as the page's resolved topic is their primary topic |

With a section chosen, the filter expands to that section's sub-topic
pills (28 / 39 / 15 distinct links for Culture / Sectors / Living).

### Observation, not changed

With Living chosen the list is simply empty below the filter: the view
has no "no results" text on this display, the same as the other Explore
listings. Worth a sentence if Rob wants one, but it is the existing
behaviour and not part of this brief.

## Deploy

`scripts/release-2026-09-24-writing-all-articles.sh` on the prod server:
backup, maintenance on, cim, cr, Writing-page script, filter script, cr,
maintenance off. Editor step: none.

## Part 3 (same session): Articles on the Writing strip; three columns

Rob, after reviewing part 2:

> Please can 'Articles' be added to the tab strip on /culture/writing —
> this will link to /explore/articles. If possible, please have the total
> number of articles next to 'Articles' in the tab, consistent with the
> other content types.
>
> On /explore/articles, please have 3 columns instead of 4 because the
> cards look too narrow with the filter. Please use classy_paragraphs
> and/or page_specific_class modules as required.

### Strip

The strip left Articles out on /culture/writing because it counts what
is tagged with the page's topic, and nothing is tagged Writing. The rule
now: a kind whose placed listing paragraph is in "Preview, all topics"
mode is a window on the whole site, so its strip item says the same
thing — the site-wide count (the same all-topics scope the block uses,
Culture + Sectors + Living and descendants) linking to the site-wide page.
New helper `_customsolent_sitewide_listing_kinds($node)` reads that off
the page's listing paragraphs; nothing is page-specific. Other section
pages are untouched (Identity still shows "Articles 2" to its own
sub-listing).

Consequence handled: `/culture/writing/articles` would otherwise still
render a Writing-only list and contradict both the strip and "View
more", so `TopicListingController` now answers a 302 to the site-wide
page when the placed paragraph is in that mode. Temporary rather than
permanent on purpose: the mode is an editor choice that can change back,
and a 301 would be cached by browsers. The URL map moved from the theme
to `customsolent_helpers_sitewide_listing_url()` in the module, because a
controller cannot rely on the theme being loaded; the theme function now
delegates to it.

### Three columns

A general classy_paragraphs style rather than page-specific code, per
Rob's standing preference: **"Articles Grid, 3 columns"**
(`articles_grid_three_columns`, class `slnt-articles-compact-grid--3-col`),
created through the API and exported. One CSS rule in
`article-compact.css` overrides the four-column track list at ≥1000px;
below that the existing 2 / 1 column rules apply unchanged. It goes in the
paragraph's classy field alongside the existing "Articles Compact Grid".
`scripts/set_explore_articles_three_columns.php` applies it to the
Articles page's listing paragraph (page by alias, node 92 fallback;
aborts if the style has not been imported; idempotent; `--dry-run`).
page_specific_class was not needed.

### Verified locally

- /culture/writing strip: Overview · Events 1 · **Articles 7 → /explore/articles** · Organisations & links 11. Same on /culture/writing/events.
- /culture/writing/articles → 302 → /explore/articles.
- /culture/identity strip unchanged: Articles 2 → /culture/identity/articles, which still returns 200.
- /explore/articles at 1400px wide: three cards per row beside the sidebar filter (screenshot checked), classes `slnt-articles-compact-grid slnt-articles-compact-grid--3-col`.

Release script is now 9 steps (step 7 runs the columns script).

## Part 4 (2026-09-25): the Writing page's two-block shape

Rob reflected on the all-topics block:

> The idea of the articles on this page was to say: "given that this is
> our culture/writing section, here are our own articles we and our
> contributors have written", inviting others to contribute — so I'd be
> looking for some words on that and perhaps a CTA. But also I am
> wondering if I also want to have the original mechanism _as well_,
> where the articles are filtered on the term for the section page — to
> actually have articles about writing _itself_. The overall message is:
> we write, and here are articles about the process of writing. I don't
> know whether having 2 lots of article cards, one for writing and one for
> everything makes sense.

My assessment, which Rob accepted: two grids make sense because they
answer two different questions, provided the section's own subject comes
first. Page shape, top to bottom: intro text extended with the "we write"
message; "Articles about writing" (topic-scoped, automatic mode, renders
nothing until the first article is tagged Writing); "Latest from our
contributors" (the all-topics block); a "Write for us" call to action;
then organisations and links as before. Events keep their place directly
after the intro, as on every other section page. Duplication (an article
about writing that is also among the eight newest) is small and honest;
a "skip items already shown above" feature is possible later but not
built.

### Code

- `_customsolent_sitewide_listing_kinds()` now means "shown ONLY
  site-wide": a kind is site-wide for the strip only when every placed
  listing of that kind is all-topics. With a topic-scoped block placed as
  well, the strip counts and links that block's topic, as on every other
  section page.
- `TopicListingController::findListingParagraph()` prefers a topic-scoped
  block and only falls back to an all-topics one; the 302 to the site-wide
  page fires only for that fallback. The old single-result `search()` is
  replaced by `searchAll()` (same display check).
- `cta.css`: whatever follows a call to action in the same enclosure gets
  1.5rem of top margin. The button has no margin of its own because it may
  sit last in a column; on Writing it sat hard against the Organisations
  & links box.

### Content

`scripts/shape_writing_page_two_blocks.php` (page by alias, node 27
fallback; each of the four parts checked separately; `--dry-run`):
appends the "we write" paragraph to the intro text, sets the all-topics
block's heading, inserts the topic-scoped block before it, and a
`call_to_action` (Culture background, white text, `internal:/about/contact`,
"Write for us") after it. First-draft copy for Rob to re-voice:

> We write too. Everything published on The Solent Metropolitan is written
> by people who live and work in the region, on our own team and among our
> contributors. If you have a story, an argument or a piece of local
> knowledge worth sharing, we would like to hear from you.

### Verified locally

Nothing tagged Writing (today): strip Overview · Events 2 · Organisations
& links 11 (no Articles item); h2s Events · Latest from our contributors ·
Organisations & links; 7 cards; both "View more Articles" → /explore/articles;
"Write for us" → /about/contact. Screenshot at 1400px checked.

With article 4 temporarily tagged Writing (local DB only, reverted):
strip gains "Articles 1" → /culture/writing/articles; h2 "Articles about
writing" appears above "Latest from our contributors"; 8 cards on the
page; /culture/writing/articles renders one card with the strip. Reverted
and re-checked.

Release script now 10 steps (step 8 runs the shape script).
