#!/usr/bin/env bash
#
# One-time release: section pages become previews (8 cards + "View more"),
# with automated listing pages at /{topic}/events and /{topic}/organisations.
# Steps 1 and 2 of
#   docs/claude-conversations/2026-09-20-section-pages-listings-brief.md
# It INCLUDES the step 1 card release, so run this one script whether or not
# scripts/release-2026-09-20-directory-cards.sh has already been run.
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

if [ ! -f scripts/release-2026-09-20-section-listings.sh ]; then
  echo "Run this from the project root (the directory that contains scripts/)."; exit 1
fi

echo "============================================"
echo "  Release 2026-09-20: section previews + listing pages"
echo "============================================"

echo "==> Step 1/9: Backing up database..."
$DRUSH sql:dump --gzip --result-file="$BACKUP_DIR/backup-pre-release-2026-09-20-listings-$(date +%Y%m%d-%H%M%S).sql"

echo "==> Step 2/9: Enabling maintenance mode..."
$DRUSH state:set system.maintenance_mode 1 -y

echo "==> Step 3/9: Importing configuration (card displays, combined view, preview displays, listing mode field)..."
$DRUSH config:import -y

echo "==> Step 4/9: Clearing caches (registers the listing route, path processor and templates)..."
$DRUSH cr

echo "==> Step 5/9: Card grid style on existing organisations / links listings..."
$DRUSH php:script scripts/apply_directory_card_grid.php

echo "==> Step 6/9: Marking the Explore listing pages as Full listing..."
$DRUSH php:script scripts/set_full_listing_pages.php

echo "==> Step 7/9: Topic filter on Explore → Directories & Networks and Explore → Organisations..."
$DRUSH php:script scripts/add_explore_listing_filters.php

echo "==> Step 8/9: Final cache rebuild..."
$DRUSH cr

echo "==> Step 9/9: Disabling maintenance mode..."
$DRUSH state:set system.maintenance_mode 0 -y

echo ""
echo "============================================"
echo "  Release complete."
echo ""
echo "  Check by eye:"
echo "    /culture/music                 Events and 'Organisations & links' blocks, up to 8 cards"
echo "                                   each, NO pager; 'View more' beside the second heading"
echo "    /culture/music/organisations   one list, one pager; page 2 works; Culture lit in the menu"
echo "    /culture/music/events          events with date pills"
echo "    /culture/events                topic filter present and narrows the list"
echo "    /explore/events                card grid, date pills, topic filter; choose Culture then Music"
echo "    /explore/directories-networks  new topic filter; choosing a topic narrows the list"
echo "    /explore/organisations         new topic filter; Sectors cuts ~20 pages to ~12; page 2 keeps it"
echo "    /culture                       filter starts on All topics; Music shows its events"
echo "    /explore/data                  its two old blocks are now one combined block"
echo "    phone width                    'View more' appears under the cards"
echo "  Then: $DRUSH config:status     (expect: no differences)"
echo "============================================"
