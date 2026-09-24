#!/usr/bin/env bash
#
# One-time release: Articles on the section pages. The section strip gains
# an Articles tab (Overview · Events · Articles · Organisations & links),
# every Culture / Sectors / Living section page gets an Articles preview
# block, and /{topic}/articles is the automated full listing.
#
# Full context:
#   docs/claude-conversations/2026-09-24-section-articles.md
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

if [ ! -f scripts/release-2026-09-24-section-articles.sh ]; then
  echo "Run this from the project root (the directory that contains scripts/)."; exit 1
fi

echo "============================================"
echo "  Release 2026-09-24: Articles on section pages"
echo "============================================"

echo "==> Step 1/7: Backing up database..."
$DRUSH sql:dump --gzip --result-file="$BACKUP_DIR/backup-pre-release-2026-09-24-articles-$(date +%Y%m%d-%H%M%S).sql"

echo "==> Step 2/7: Enabling maintenance mode..."
$DRUSH state:set system.maintenance_mode 1 -y

echo "==> Step 3/7: Importing configuration (articles_listing by-topic displays)..."
$DRUSH config:import -y

echo "==> Step 4/7: Clearing caches (new route word, theme and module code)..."
$DRUSH cr

echo "==> Step 5/7: Adding an Articles listing to every section page..."
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
echo "    /sectors/regional-development   strip reads Overview · Events · Articles 3 ·"
echo "                                    Organisations & links; an Articles block of"
echo "                                    three cards sits between Events and the"
echo "                                    organisations signpost"
echo "    /sectors/regional-development/articles   h1 'Articles', same three cards,"
echo "                                    strip with Articles marked current"
echo "    /culture/dance                  no Articles tab and no Articles block"
echo "    /explore/articles               unchanged: every article, pager"
echo "  Then: $DRUSH config:status     (expect: no differences)"
echo "============================================"
