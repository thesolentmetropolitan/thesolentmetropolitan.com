#!/usr/bin/env bash
#
# One-time release: front page hero title aligned with the "What's on"
# heading below it on desktop, via the new classy style
# "Hero: Title Flush Left (desktop)".
#
# Full context:
#   docs/claude-conversations/2026-09-24-hero-title-flush-left.md
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

if [ ! -f scripts/release-2026-09-24-hero-title-flush-left.sh ]; then
  echo "Run this from the project root (the directory that contains scripts/)."; exit 1
fi

echo "============================================"
echo "  Release 2026-09-24: Front page hero title flush left"
echo "============================================"

echo "==> Step 1/7: Backing up database..."
$DRUSH sql:dump --gzip --result-file="$BACKUP_DIR/backup-pre-release-2026-09-24-$(date +%Y%m%d-%H%M%S).sql"

echo "==> Step 2/7: Enabling maintenance mode..."
$DRUSH state:set system.maintenance_mode 1 -y

echo "==> Step 3/7: Importing configuration (classy style hero_title_flush_left)..."
$DRUSH config:import -y

echo "==> Step 4/7: Clearing caches (new CSS)..."
$DRUSH cr

echo "==> Step 5/7: Adding the classy style to the front page hero..."
$DRUSH php:script scripts/front_page_hero_flush_left.php

echo "==> Step 6/7: Final cache rebuild..."
$DRUSH cr

echo "==> Step 7/7: Disabling maintenance mode..."
$DRUSH state:set system.maintenance_mode 0 -y

echo ""
echo "============================================"
echo "  Release complete."
echo ""
echo "  Check by eye (desktop width, 800px and up):"
echo "    /   'Welcome to The Solent Metropolitan' starts on the same vertical"
echo "        line as 'What's on across the Greater Solent'; the white cut-out"
echo "        block now overhangs that line to the left by about 16px"
echo "    /   on a phone nothing changes (cut-out keeps its 1em indent)"
echo "  Then: $DRUSH config:status     (expect: no differences)"
echo "============================================"
