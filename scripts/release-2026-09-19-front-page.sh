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

echo "==> Step 1/9: Backing up database..."
$DRUSH sql:dump --gzip --result-file="$BACKUP_DIR/backup-pre-release-2026-09-19-front-page-$(date +%Y%m%d-%H%M%S).sql"

echo "==> Step 2/9: Enabling maintenance mode..."
$DRUSH state:set system.maintenance_mode 1 -y

echo "==> Step 3/9: Importing configuration (three style classes; events view shows 8)..."
$DRUSH config:import -y

echo "==> Step 4/9: Clearing caches (also registers the home-page form guard)..."
$DRUSH cr

echo "==> Step 5/9: Converting the Home node to Composite Page, in place..."
$DRUSH php:script scripts/convert_home_to_composite_page.php

echo "==> Step 6/9: Reorganising the front page paragraphs..."
$DRUSH php:script scripts/reorganise_front_page.php

echo "==> Step 7/9: Front page adjustments (heading breaks, About slice, Explore-colour buttons)..."
$DRUSH php:script scripts/front_page_adjustments.php

echo "==> Step 8/9: Final cache rebuild..."
$DRUSH cr

echo "==> Step 9/9: Disabling maintenance mode..."
$DRUSH state:set system.maintenance_mode 0 -y

echo ""
echo "============================================"
echo "  Release complete."
echo ""
echo "  Check by eye:"
echo "    /                 Welcome on ONE line, events in rows of 4, tagline (2 lines) | intro,"
echo "                      orange About button centred on its own, then Latest articles"
echo "    /                 See all articles button is orange; button text larger, same button size"
echo "    /                 phone width: Welcome to / The Solent Metropolitan; events in pairs"
echo "    /node/<home>/edit 'Edit Composite Page Home'; NO topic fields on this form"
echo "    /culture          a normal section page still has its topic trail and filters"
echo "    /explore/data     listing and its topic kickers agree (kicker scope fix)"
echo "  Then: $DRUSH config:status     (expect: no differences)"
echo "============================================"
