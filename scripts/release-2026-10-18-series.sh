#!/usr/bin/env bash
#
# One-time release: the Series content type, its listing on /explore/series,
# the "Part of the series …" line on event cards and pages, and the first
# series — Black History Month South 2026 (seven events, three new venues).
#
# Full context:
#   docs/claude-conversations/2026-10-18-people-series-collaborations-brief.md
#   docs/claude-conversations/2026-10-18-series-implementation.md
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

if [ ! -f scripts/release-2026-10-18-series.sh ]; then
  echo "Run this from the project root (the directory that contains scripts/)."; exit 1
fi

echo "============================================"
echo "  Release 2026-10-18: Series + Black History Month"
echo "============================================"

echo "==> Step 1/8: Backing up database..."
$DRUSH sql:dump --gzip --result-file="$BACKUP_DIR/backup-pre-release-2026-10-18-series-$(date +%Y%m%d-%H%M%S).sql"

echo "==> Step 2/8: Enabling maintenance mode..."
$DRUSH state:set system.maintenance_mode 1 -y

echo "==> Step 3/8: Importing configuration (Series type, fields, view, URL pattern)..."
$DRUSH config:import -y

echo "==> Step 4/8: Clearing caches (new service, templates and CSS)..."
$DRUSH cr

echo "==> Step 5/8: Placing the Series listing on /explore/series..."
$DRUSH php:script scripts/add_series_listing.php

echo "==> Step 6/8: Creating Black History Month 2026 (venues, events, series)..."
$DRUSH php:script scripts/create_black_history_month_2026.php

echo "==> Step 7/8: Final cache rebuild..."
$DRUSH cr

echo "==> Step 8/8: Disabling maintenance mode..."
$DRUSH state:set system.maintenance_mode 0 -y

echo ""
echo "============================================"
echo "  Release complete."
echo ""
echo "  Check by eye:"
echo "    /explore/series                     one card: Black History Month, 'Now on',"
echo "                                        1–31 October 2026, 7 events"
echo "    /series/black-history-month-2026    status badge, dates, description,"
echo "                                        Events (7) as compact cards, 'Also in' topics"
echo "    /                                   BHM event cards in What's on carry a"
echo "                                        'Black History Month' line above the kicker"
echo "    /events/chali-2na-skillz-live-band-2026-10-28-1800"
echo "                                        'Part of a series' block under the CTA"
echo "  Then: $DRUSH config:status     (expect: no differences)"
echo "============================================"
