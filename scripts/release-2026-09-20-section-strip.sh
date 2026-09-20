#!/usr/bin/env bash
#
# One-time release: section strip, banner on listing pages, signpost box in
# place of the organisations grid, and an Events listing on every Culture /
# Sectors / Living section page.
#
# Full context:
#   docs/claude-conversations/2026-09-20-section-strip-and-signpost.md
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

if [ ! -f scripts/release-2026-09-20-section-strip.sh ]; then
  echo "Run this from the project root (the directory that contains scripts/)."; exit 1
fi

echo "============================================"
echo "  Release 2026-09-20: section strip + signpost"
echo "============================================"

echo "==> Step 1/7: Backing up database..."
$DRUSH sql:dump --gzip --result-file="$BACKUP_DIR/backup-pre-release-2026-09-20-strip-$(date +%Y%m%d-%H%M%S).sql"

echo "==> Step 2/7: Enabling maintenance mode..."
$DRUSH state:set system.maintenance_mode 1 -y

echo "==> Step 3/7: Importing configuration (Listing mode gains 'Signpost'; default becomes automatic)..."
$DRUSH config:import -y

echo "==> Step 4/7: Clearing caches (registers the strip template)..."
$DRUSH cr

echo "==> Step 5/7: Events (and where missing, Organisations) listings on every section page..."
$DRUSH php:script scripts/ensure_section_page_listings.php

echo "==> Step 6/7: Final cache rebuild..."
$DRUSH cr

echo "==> Step 7/7: Disabling maintenance mode..."
$DRUSH state:set system.maintenance_mode 0 -y

echo ""
echo "============================================"
echo "  Release complete."
echo ""
echo "  Check by eye:"
echo "    /culture/music                 strip under the banner: Overview · Events n · Organisations & links n"
echo "                                   events cards, then a white box with a count and 'Browse all n'"
echo "    /culture/music/organisations   SAME banner and strip, 'Organisations & links' marked; one h1"
echo "    /living/community              no events yet: strip has two items, box carries the page"
echo "    phone width                    strip is a stacked list of rows; nothing scrolls sideways"
echo "    /explore/events, /about/...    no strip (Culture, Sectors and Living only)"
echo "  Then: $DRUSH config:status     (expect: no differences)"
echo "============================================"
