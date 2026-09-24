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
