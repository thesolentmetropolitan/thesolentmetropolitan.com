#!/usr/bin/env bash
#
# One-time release: front page reorganised into full-width slices, and the
# Home node converted from Landing Page to Composite Page (in place).
#
# Full context:
#   docs/claude-conversations/2026-09-19-front-page-reorganise-composite-home.md
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

if [ ! -f scripts/release-2026-09-19-front-page.sh ]; then
  echo "Run this from the project root (the directory that contains scripts/)."; exit 1
fi

echo "============================================"
echo "  Release 2026-09-19: front page + composite home"
echo "============================================"

echo "==> Step 1/8: Backing up database..."
$DRUSH sql:dump --gzip --result-file="$BACKUP_DIR/backup-pre-release-2026-09-19-front-page-$(date +%Y%m%d-%H%M%S).sql"

echo "==> Step 2/8: Enabling maintenance mode..."
$DRUSH state:set system.maintenance_mode 1 -y

echo "==> Step 3/8: Importing configuration (intro style class; events view shows 4)..."
$DRUSH config:import -y

echo "==> Step 4/8: Clearing caches (also registers the home-page form guard)..."
$DRUSH cr

echo "==> Step 5/8: Converting the Home node to Composite Page, in place..."
$DRUSH php:script scripts/convert_home_to_composite_page.php

echo "==> Step 6/8: Reorganising the front page paragraphs..."
$DRUSH php:script scripts/reorganise_front_page.php

echo "==> Step 7/8: Final cache rebuild..."
$DRUSH cr

echo "==> Step 8/8: Disabling maintenance mode..."
$DRUSH state:set system.maintenance_mode 0 -y

echo ""
echo "============================================"
echo "  Release complete."
echo ""
echo "  Check by eye:"
echo "    /                 Welcome, then 4 events in one row, then tagline | intro + centred About,"
echo "                      then Latest articles"
echo "    /                 phone width: events 2 x 2"
echo "    /node/<home>/edit 'Edit Composite Page Home'; NO topic fields on this form"
echo "    /culture          a normal section page still has its topic trail and filters"
echo "  Then: $DRUSH config:status     (expect: no differences)"
echo "============================================"
