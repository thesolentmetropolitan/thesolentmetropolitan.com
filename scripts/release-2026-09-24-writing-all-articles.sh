#!/usr/bin/env bash
#
# One-time release: the "Preview, all topics" listing mode, and /culture/writing
# using it for its Articles block.
#
# Writing is the one section page whose subject IS the written word, so listing
# only the articles tagged "Writing" undersells it. Its Articles block now shows
# the newest articles from across the whole site and hands over to
# /explore/articles rather than /culture/writing/articles.
#
# /explore/articles itself gains the topic filter (Culture / Sectors / Living,
# with sub-topics) that Events, Organisations and Directories & Networks
# already have, so the hand-over lands on a page that can be narrowed, and
# shows three cards per row rather than four now that the filter sits beside
# them. The Writing page's section strip gains an Articles item with the
# site-wide count, linking to /explore/articles.
#
# Then (2026-09-25) the Writing page takes its two-block shape: the intro
# carries the "we write" message, a topic-scoped "Articles about writing"
# block sits above the all-topics "Latest from our contributors" block, and a
# "Write for us" call to action follows the cards. With a topic-scoped block
# placed, the strip and /culture/writing/articles belong to it, as on every
# other section page; the all-topics block keeps its own "View more".
#
# The listing mode itself is general: any View Display paragraph for articles,
# events or organisations can be set to "Preview, all topics" in the editor.
#
# Run ON the production server, from the project root, AFTER:
#   git pull origin main
#   composer install --no-dev --optimize-autoloader   (no composer changes; harmless)
#
# Every step is idempotent: after a failure, fix the cause and run it again.
#
set -euo pipefail

DRUSH="${DRUSH:-./drush-dir/drush}"
BACKUP_DIR="${BACKUP_DIR:-$HOME}"

if [ ! -f scripts/release-2026-09-24-writing-all-articles.sh ]; then
  echo "Run this from the project root (the directory that contains scripts/)."; exit 1
fi

echo "============================================"
echo "  Release 2026-09-24: Writing lists all articles"
echo "============================================"

echo "==> Step 1/10: Backing up database..."
$DRUSH sql:dump --gzip --result-file="$BACKUP_DIR/backup-pre-release-2026-09-24-writing-$(date +%Y%m%d-%H%M%S).sql"

echo "==> Step 2/10: Enabling maintenance mode..."
$DRUSH state:set system.maintenance_mode 1 -y

echo "==> Step 3/10: Importing configuration (listing-mode option, 3-column classy style)..."
$DRUSH config:import -y

echo "==> Step 4/10: Clearing caches (theme code and template change)..."
$DRUSH cr

echo "==> Step 5/10: Switching the Writing page's Articles block to all topics..."
$DRUSH php:script scripts/set_writing_articles_all_topics.php

echo "==> Step 6/10: Adding the topic filter to /explore/articles..."
$DRUSH php:script scripts/add_explore_listing_filters.php

echo "==> Step 7/10: Three columns on /explore/articles..."
$DRUSH php:script scripts/set_explore_articles_three_columns.php

echo "==> Step 8/10: Shaping the Writing page (intro, two article blocks, call to action)..."
$DRUSH php:script scripts/shape_writing_page_two_blocks.php

echo "==> Step 9/10: Final cache rebuild..."
$DRUSH cr

echo "==> Step 10/10: Disabling maintenance mode..."
$DRUSH state:set system.maintenance_mode 0 -y

echo ""
echo "============================================"
echo "  Release complete."
echo ""
echo "  Check by eye:"
echo "    /culture/writing        intro ends with the 'We write too' paragraph;"
echo "                            'Latest from our contributors' shows the newest"
echo "                            articles from across the site, both 'View more"
echo "                            Articles' links go to /explore/articles; 'Write"
echo "                            for us' button below the cards, with a gap"
echo "                            before Organisations & links; NO Articles item"
echo "                            on the strip and no 'Articles about writing'"
echo "                            block until an article is tagged Writing"
echo "    Then re-voice the intro copy and the button wording in the editor."
echo "    /culture/identity       unchanged: still only its own topic's articles,"
echo "                            'View more' still goes to /culture/identity/articles"
echo "    /sectors/regional-development   unchanged, same check as above"
echo "    /explore/articles       Culture / Sectors / Living filter above the"
echo "                            list; choosing one narrows the list and the"
echo "                            pager keeps ?topic=; three cards per row"
echo "                            on a wide screen"

echo "  Then: $DRUSH config:status     (expect: no differences)"
echo "============================================"
