# View Display paragraph: the View autocomplete returned nothing

**Date:** 2026-09-23
**Branch:** `fix-view-field-autocomplete`

## Ask

On a node edit form, the View field of a View Display paragraph (`field_view`, a
viewsreference field) stopped autocompleting: clear an existing value, start
typing a view name, and no suggestions appear. Rob's observation was that a
freshly added, empty View Display paragraph still worked.

Second question: the Articles page (`/explore/articles`, node 92) needs its
articles list. Is the `articles_page_block` display ready, and does it look
like the front page one?

## What was found

Reproduced in the browser on the Explore page edit form (node 86, three
existing View Display paragraphs). Fetching each field's autocomplete endpoint
directly returned `[]` for every query, including the exact machine name
`articles_listing`. A freshly added paragraph on node 92 returned `[]` too, so
the empty-field case was not actually working either; the two cases share the
same selection settings hash.

Cause: the field config `field.field.paragraph.view_display.field_view` has a
`preselect_views` setting. When it is non-empty the widget swaps in the
`viewsreference:view` selection handler, which adds `id IN (preselect list)`
to the entity query. The list held a single entry, `articles`, from commit
`ecd7033` on 2026-05-03 (the same day the listing views were split up under
issue 90). The `articles` view was renamed to `articles_listing` afterwards,
so the preselect pointed at a view that no longer exists and every query
matched nothing. No code broke; the config went stale.

## Fix

`preselect_views` now lists the five listing views that editors actually
embed: `articles_listing`, `events_listing`, `organisations_listing`,
`links_listing`, `directory_listing`. Keeping a list (rather than clearing it)
still hides admin views such as Content and Watchdog from the picker.

Imported locally with `drush cim`. Verified in the browser: on the Explore
edit form, clearing the Articles value and typing "art" opens the jQuery UI
menu with "Articles Listing"; typing "list" offers all five; typing
"content" offers nothing.

Deploy: config only, the normal `deploy.sh` config import covers it.

## The articles page block

`articles_listing` has a `block_2` display titled `articles_page_block`. It
overrides nothing, so it inherits the default display: article **teaser** view
mode, full pager at 20 per page, no header or footer. It is a plain list.

The front page and Explore page use `view_display_front_page`: article
**compact** view mode, 8 items, no pager, and the card grid comes from the
"Articles Compact Grid" classy style on the paragraph (`article-compact.css`
targets `.slnt-articles-compact-grid` and the compact card markup). Adding
that classy style to a paragraph that uses `block_2` would not give the
front-page look, because the CSS is written for compact cards and `block_2`
renders teasers.

Articles are also outside the theme's automatic listing modes
(`_customsolent_topic_listing_types` covers events, organisations and links),
so a View Display paragraph using `articles_listing` renders as-is with no
preview / signpost / full behaviour.

If the Articles page should match the front page cards with paging, the
closest precedent is events' `view_display_listing_cards` (compact view mode,
24 per page, full pager): an equivalent display on `articles_listing` would be
a small view config change.

Side finding: node 92 has an orphaned View Display paragraph (id 655,
`articles_listing` / `block_2`, heading "Articles") whose parent enclosure
(369) no longer references it. It is not on the edit form and does not
render.
