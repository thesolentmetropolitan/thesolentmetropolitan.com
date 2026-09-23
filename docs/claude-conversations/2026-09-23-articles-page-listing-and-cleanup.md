# Articles page: listing display, orphan cleanup, release script

**Date:** 2026-09-23
**Branch:** `articles-page-cleanup`
**Follows:** `docs/claude-conversations/2026-09-23-view-field-autocomplete-fix.md`

## Ask

Rob cloned the front-page articles display into a new
`view_display_listing_cards` display on `articles_listing`, put it on the
Articles page (node 92, `/explore/articles`) inside an Enclosure with the
"Articles Compact Grid" style and the "full" listing mode, and committed the
view (`e0eabd2`). Then: clean up node 92 (the orphaned paragraph found
earlier) and produce a deploy script for live.

## What was done

### 1. The new display paginates now

The cloned display kept the front page's pager: "some", 8 items, no links.
The "full" listing mode on the paragraph does not add paging for articles,
because the theme's listing modes (`_customsolent_topic_listing_types`)
only cover events, organisations and links; an articles paragraph renders
the display as-is. So the display itself now has a full pager, 24 per
page, the same as events' `view_display_listing_cards`. With 7 published
articles no pager shows yet.

A gotcha on the way: `drush cim` does not recompute a view's
`cache_metadata` (View::preSave skips it while syncing), so after
importing the pager change the display still lacked the `url.query_args`
cache context, which a pager needs or page 2 can be served from page 1's
render cache. Saving the view once from PHP (`View::load(...)->save()`)
recomputed it; the export now carries `url.query_args`, so prod gets the
right metadata from the YAML.

### 2. Orphan cleanup: `scripts/delete_orphan_paragraphs.php`

A paragraph is orphaned when it records a parent whose current revision
no longer lists it. Editors leave these behind by removing a paragraph
and saving; the old revision keeps the reference, the entity stays, and
it never renders. The script walks one node's paragraph tree (through
enclosures, columns, any nesting), reports each orphan with what it
holds, and deletes on request. `--nid=` is required; `--dry-run` shows
what would go. Run on node 92: deleted paragraph 655
(`articles_listing/block_2`, heading "Articles"), revisions included.

The same check across the whole site finds 33 orphans (Home, Culture,
Data, the footer blocks, and others). Only node 92 was touched; the
script is ready for the rest if wanted, one node at a time.

### 3. Listing on prod: `scripts/articles_page_listing.php`

Reproduces Rob's local content change on live: appends an Enclosure (2em)
holding a View Display paragraph with `articles_listing /
view_display_listing_cards`, the compact-grid style and the full listing
mode, to the page whose alias is `/explore/articles`. Idempotent: if any
View Display in the page's tree already embeds `articles_listing` it does
nothing. Rehearsed against a ddev snapshot: removed the local listing,
ran the script, checked `/explore/articles` rendered 7 cards in a
four-column grid, then restored the snapshot.

### 4. Release: `scripts/release-2026-09-23-articles-page.sh`

Backup, maintenance on, config import, cache clear, orphan cleanup on
the `/explore/articles` node (looked up by alias), listing script, cache
clear, maintenance off. Same shape as the other release scripts.

## Loose ends

- `articles_listing` also gained a disabled `block_3` display ("Block 3")
  in Rob's commit, probably a leftover from cloning. Harmless; delete
  from the view UI when convenient.
- `articles_page_block` (`block_2`) is now unused by any paragraph.
- Articles are still outside the theme's listing modes, so no
  automatic preview / signpost behaviour and no `/{topic}/articles`
  route. Adding `articles_listing` to `_customsolent_topic_listing_types`
  would be the next step if the section pages should preview articles.
