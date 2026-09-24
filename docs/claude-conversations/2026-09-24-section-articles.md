# Articles on the section pages: strip tab, preview block, /{topic}/articles

**Date:** 2026-09-24
**Branch:** `section-articles`

## Ask

Rob: how do I get an Articles tab on the section strip, and on selecting
it, the articles? It must hide when there are no articles, like the other
types. Some articles are tagged Regional Development, so the tab should
appear on that section page (node 127). He expected it to be a View
Display paragraph, possibly re-using `view_display_listing_cards`, and
wanted it set up on every section page by script rather than by hand.

## How the existing strip works (recap)

The strip is built by `_customsolent_build_section_strip()` in the theme
from `_customsolent_topic_scope_stats()` counts; a type with nothing in
it is left out. Each section page carries one View Display paragraph per
type pointing at `{view}:view_display_primary_and_related`; the theme's
listing mode logic renders it as a preview (8 cards + View more) on the
page and as the full listing on the automated `/{topic}/{type}` route
served by `TopicListingController`. The route words live in one function,
`customsolent_helpers_listing_route_words()`.

## What was done

**Views config** (`scripts/generate_articles_listing_displays.php`, a
local generator run once, then `cex`): `articles_listing` gained
`view_display_primary_and_related` (topic arguments and OR query copied
from `events_listing`, compact card rows, full pager, 24 per page) and
`view_display_preview` (9 rows).

`view_display_listing_cards` was deliberately NOT given topic arguments.
It is the all-articles page (/explore/articles). Explore pages fall back
to a Culture + Sectors + Living scope, and an article's primary topic is
not required, so a filtered listing_cards would drop untagged or
Explore-only articles from the all-articles page. (Local check:
/explore/articles shows 7 articles; only 6 have a section primary topic.)
Events avoid this only because their cards display is reached through a
paragraph in Full mode. So articles' by-topic display is its own, with the
same compact card grid; the look Rob asked for is reused, the display is
not.

**Theme** (`customsolent.theme`): new `_customsolent_listing_kinds()`
gathers what each kind of listing needs (label, node_list cache tags, grid
class, the [view, display] used in Full mode, stats key). The strip,
`_customsolent_preprocess_listing_mode()` and the preview heading / View
more label now read from it instead of an events-or-organisations
branch, so a fourth kind later is one array entry. Stats count published
articles tagged primary or related. `_customsolent_topic_listing_types()`
maps `articles_listing` to the word `articles`.

**Module** (`customsolent_helpers`): `articles` added to the route words,
the route requirement, the controller's view map and h1, and the list of
views that `?topic=` can narrow within.

**Content** (`scripts/ensure_section_page_listings.php`, extended): every
Culture / Sectors / Living section page now also gets an Articles listing
paragraph, placed directly after the Events listing (or where Events
would go). 82 pages locally, idempotent (second run adds nothing). No
heading paragraph is placed: the preview falls back to "Articles".

## Verified locally

| Page | Result |
|---|---|
| /sectors/regional-development | strip Overview · Events 1 · Articles 3 · Organisations & links 45; Articles block of 3 cards between Events and the signpost |
| /sectors/regional-development/articles | h1 "Articles", strip with Articles current, 3 cards |
| /culture/identity | strip Overview · Articles 2 (no events, no organisations) |
| /culture/dance | no Articles tab, no Articles block |
| /explore/articles | unchanged, 7 articles |

## Noticed, not changed

Article cards on a section page show the kicker "SECTORS / REGIONAL
DEVELOPMENT" even when that is the page's own topic. Event cards suppress
the "in" kicker in that case (teaser preprocess); the article compact card
prints its kicker unconditionally because it was designed for the front
page. Rob's call whether section-page article cards should drop it.

## Deploy

`scripts/release-2026-09-24-section-articles.sh` on the prod server:
config import (the two displays), cache rebuild, the ensure script, cache
rebuild. Editor step: none.
