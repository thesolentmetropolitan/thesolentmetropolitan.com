#!/usr/bin/env bash
#
# One-time release: shared Organisation / Link cards on every listing, and
# uniform "Event info" buttons. Step 1 of
#   docs/claude-conversations/2026-09-20-section-pages-listings-brief.md
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

if [ ! -f scripts/release-2026-09-20-directory-cards.sh ]; then
  echo "Run this from the project root (the directory that contains scripts/)."; exit 1
fi

echo "============================================"
echo "  Release 2026-09-20: organisation + link cards"
echo "============================================"

echo "==> Step 1/7: Backing up database..."
$DRUSH sql:dump --gzip --result-file="$BACKUP_DIR/backup-pre-release-2026-09-20-cards-$(date +%Y%m%d-%H%M%S).sql"

echo "==> Step 2/7: Enabling maintenance mode..."
$DRUSH state:set system.maintenance_mode 1 -y

echo "==> Step 3/7: Importing configuration (card view displays, grid style, listing row mode)..."
$DRUSH config:import -y

echo "==> Step 4/7: Clearing caches (registers the new card templates)..."
$DRUSH cr

echo "==> Step 5/7: Giving every organisations / links listing the card grid..."
$DRUSH php:script scripts/apply_directory_card_grid.php

echo "==> Step 6/7: Final cache rebuild..."
$DRUSH cr

echo "==> Step 7/7: Disabling maintenance mode..."
$DRUSH state:set system.maintenance_mode 0 -y

echo ""
echo "============================================"
echo "  Release complete."
echo ""
echo "  Check by eye:"
echo "    /culture/music          organisations as white cards, 3 per row; ORGANISATION label;"
echo "                            event buttons all read 'Event info' and are the same width"
echo "    /explore/organisations  cards, pager still works and spans the full width"
echo "    a page with links       cards labelled LINK"
echo "    phone width             cards one per row"
echo "  Then: $DRUSH config:status     (expect: no differences)"
echo "============================================"
