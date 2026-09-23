#!/usr/bin/env bash
#
# One-time release: front page hero banner (Welcome h1 on a three-section
# gradient with every site icon), /culture banner with every Culture icon,
# and the Stage banner's missing mask icon.
#
# Full context:
#   docs/claude-conversations/2026-09-23-hero-tiles-culture-and-home.md
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

if [ ! -f scripts/release-2026-09-23-hero-tiles.sh ]; then
  echo "Run this from the project root (the directory that contains scripts/)."; exit 1
fi

echo "============================================"
echo "  Release 2026-09-23: Hero tiles - front page + Culture"
echo "============================================"

echo "==> Step 1/7: Backing up database..."
$DRUSH sql:dump --gzip --result-file="$BACKUP_DIR/backup-pre-release-2026-09-23-$(date +%Y%m%d-%H%M%S).sql"

echo "==> Step 2/7: Enabling maintenance mode..."
$DRUSH state:set system.maintenance_mode 1 -y

echo "==> Step 3/7: Importing configuration (classy style hero_art_style_home)..."
$DRUSH config:import -y

echo "==> Step 4/7: Clearing caches (new CSS, template and tiles)..."
$DRUSH cr

echo "==> Step 5/7: Front page hero paragraph in, old Welcome heading out..."
$DRUSH php:script scripts/front_page_hero.php

echo "==> Step 6/7: Final cache rebuild..."
$DRUSH cr

echo "==> Step 7/7: Disabling maintenance mode..."
$DRUSH state:set system.maintenance_mode 0 -y

echo ""
echo "============================================"
echo "  Release complete."
echo ""
echo "  Check by eye:"
echo "    /         banner under the menu, 110px tall: purple top-left, blue middle,"
echo "              green bottom-right, many different faint icons; 'Welcome to"
echo "              The Solent Metropolitan' cut out of its bottom edge, no other h1"
echo "    /culture  purple banner now shows a different icon in every position"
echo "    /culture/stage  three icons per row again (mask, microphone, ticket)"
echo "  Then: $DRUSH config:status     (expect: no differences)"
echo "============================================"
