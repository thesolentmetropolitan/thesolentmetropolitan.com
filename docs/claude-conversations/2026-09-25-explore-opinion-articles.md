# 2026-09-25 — /explore/opinion lists the articles tagged Opinion

Branch: `explore-opinion-articles` (from main after the Writing release
went live). Deploy: `scripts/release-2026-09-25-opinion-articles.sh`.

## Rob's brief

> I'd like to set up the page at explore/opinion to list articles tagged
> with Explore / Opinion. I did add them but I think there is some CSS
> missing, so they rendered as long rectangles rather than square-like
> blocks. This is currently 2 items which also show on Culture / Identity;
> they are tagged with Opinion as related topic.

## What was wrong

The published /explore/opinion (live and local) has only the hero and the
one-word intro; whatever Rob added is in an unpublished draft or not saved,
so I could not inspect it. The long-rectangle look is what the theme gives
a View Display paragraph outside the by-topic pattern: `_customsolent_listing_mode()`
returns `plain` for a display that is not one of the by-topic displays, so
no grid class is applied and each compact card stretches to the full
width. The front page avoids that by having the editor add the
"Articles Compact Grid" classy style by hand.

The second trap on an Explore page: `_customsolent_resolve_topic_context()`
deliberately does not use an Explore page's primary topic as scope, so
without the paragraph's own Topic field the block would list every article.

## What was done

`scripts/set_explore_opinion_articles.php`, copying the shape of the
Directories & Networks listing (paragraph 634): `articles_listing` /
`view_display_primary_and_related`, Topic = Explore / Opinion, Listing
mode = Full. Placed directly after the intro text. If the page already has
an articles listing paragraph, it corrects display, topic and mode in
place instead of adding a second one, leaving heading and classy styles
alone. Page by alias, node 41 fallback; idempotent; `--dry-run`.

Full mode gives the card grid (`slnt-articles-compact-grid`), one pager,
and — since Opinion has no child terms — no sidebar filter. No section
strip: strips are only built for Culture / Sectors / Living pages. Cards
show their primary-topic kicker (Sectors / Regional Development, Culture /
Identity) because the page's topic is not their primary topic, which is
the rule from 2026-09-24.

## Verified locally

/explore/opinion: h1 Opinion, two square cards in the grid at 1400px,
kickers as above, pager present, no strip, no filter. Screenshot checked.
/explore/opinion/articles resolves (200) as the automated route does for
any topic page; nothing links to it.

## Rob's soundboard question: a strip on Opinion?

Answered in the session reply (no strip on Explore pages; "opinion
organisations" reads oddly; renaming to "Opinions & ideas" or a separate
Ideas tag is content-model territory — noted, not built).
