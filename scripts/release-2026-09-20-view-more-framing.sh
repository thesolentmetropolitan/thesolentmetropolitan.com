#!/usr/bin/env bash
#
# One-time release: front page "View more" text links, "Read more about" intro
# link, warm-grey band with white event cards, rule above the intro.
#
# Full context:
#   docs/claude-conversations/2026-09-20-front-page-view-more-and-framing.md
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

if [ ! -f scripts/release-2026-09-20-view-more-framing.sh ]; then
  echo "Run this from the project root (the directory that contains scripts/)."; exit 1
fi

echo "============================================"
echo "  Release 2026-09-20: View more links + front page framing"
echo "============================================"

echo "==> Step 1/7: Backing up database..."
$DRUSH sql:dump --gzip --result-file="$BACKUP_DIR/backup-pre-release-2026-09-20-$(date +%Y%m%d-%H%M%S).sql"

echo "==> Step 2/7: Enabling maintenance mode..."
$DRUSH state:set system.maintenance_mode 1 -y

echo "==> Step 3/7: Importing configuration (classy field on Link paragraphs; three styles)..."
$DRUSH config:import -y

echo "==> Step 4/7: Clearing caches (registers the new Link paragraph template)..."
$DRUSH cr

echo "==> Step 5/7: View more links, Read more about, warm-grey band..."
$DRUSH php:script scripts/front_page_view_more_and_framing.php

echo "==> Step 6/7: Final cache rebuild..."
$DRUSH cr

echo "==> Step 7/7: Disabling maintenance mode..."
$DRUSH state:set system.maintenance_mode 0 -y

echo ""
echo "============================================"
echo "  Release complete."
echo ""
echo "  Check by eye:"
echo "    /  desktop   orange 'View more >' at the right of the What's on and Latest articles"
echo "                 heading lines; no See-all buttons; no About button"
echo "    /  desktop   off-white band, white event boxes, thin rule above 'The broader perspective'"
echo "    /  desktop   intro paragraph ends 'Read more about >' in bold orange"
echo "    /  phone     'View more >' appears UNDER each grid instead of beside the heading"
echo "  Then: $DRUSH config:status     (expect: no differences)"
echo "============================================"
