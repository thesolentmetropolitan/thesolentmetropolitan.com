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
# site-wide count, linking to /explore/articles; /culture/writing/articles
# itself now hands over there too.
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

echo "==> Step 1/9: Backing up database..."
$DRUSH sql:dump --gzip --result-file="$BACKUP_DIR/backup-pre-release-2026-09-24-writing-$(date +%Y%m%d-%H%M%S).sql"

echo "==> Step 2/9: Enabling maintenance mode..."
$DRUSH state:set system.maintenance_mode 1 -y

echo "==> Step 3/9: Importing configuration (listing-mode option, 3-column classy style)..."
$DRUSH config:import -y

echo "==> Step 4/9: Clearing caches (theme code and template change)..."
$DRUSH cr

echo "==> Step 5/9: Switching the Writing page's Articles block to all topics..."
$DRUSH php:script scripts/set_writing_articles_all_topics.php

echo "==> Step 6/9: Adding the topic filter to /explore/articles..."
$DRUSH php:script scripts/add_explore_listing_filters.php

echo "==> Step 7/9: Three columns on /explore/articles..."
$DRUSH php:script scripts/set_explore_articles_three_columns.php

echo "==> Step 8/9: Final cache rebuild..."
$DRUSH cr

echo "==> Step 9/9: Disabling maintenance mode..."
$DRUSH state:set system.maintenance_mode 0 -y

echo ""
echo "============================================"
echo "  Release complete."
echo ""
echo "  Check by eye:"
echo "    /culture/writing        Articles block shows the newest articles from"
echo "                            across the site, not just Writing ones; its"
echo "                            heading and both 'View more Articles' links"
echo "                            point at /explore/articles; the section strip"
echo "                            has 'Articles N' linking to /explore/articles"
echo "    /culture/writing/articles   redirects to /explore/articles"
echo "    /culture/identity       unchanged: still only its own topic's articles,"
echo "                            'View more' still goes to /culture/identity/articles"
echo "    /sectors/regional-development   unchanged, same check as above"
echo "    /explore/articles       Culture / Sectors / Living filter above the"
echo "                            list; choosing one narrows the list and the"
echo "                            pager keeps ?topic=; three cards per row"
echo "                            on a wide screen"

echo "  Then: $DRUSH config:status     (expect: no differences)"
echo "============================================"
