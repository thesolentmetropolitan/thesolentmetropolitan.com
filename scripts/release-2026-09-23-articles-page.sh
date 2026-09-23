#!/usr/bin/env bash
#
# One-time release: the Articles page (/explore/articles) gets its listing,
# the View field on View Display paragraphs autocompletes again, and the
# Articles page loses an orphaned paragraph left behind by an earlier edit.
#
# Full context:
#   docs/claude-conversations/2026-09-23-view-field-autocomplete-fix.md
#   docs/claude-conversations/2026-09-23-articles-page-listing-and-cleanup.md
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

if [ ! -f scripts/release-2026-09-23-articles-page.sh ]; then
  echo "Run this from the project root (the directory that contains scripts/)."; exit 1
fi

echo "============================================"
echo "  Release 2026-09-23: Articles page listing"
echo "============================================"

echo "==> Step 1/8: Backing up database..."
$DRUSH sql:dump --gzip --result-file="$BACKUP_DIR/backup-pre-release-2026-09-23-articles-$(date +%Y%m%d-%H%M%S).sql"

echo "==> Step 2/8: Enabling maintenance mode..."
$DRUSH state:set system.maintenance_mode 1 -y

echo "==> Step 3/8: Importing configuration..."
echo "    (field_view preselect list, articles_listing view_display_listing_cards display)"
$DRUSH config:import -y

echo "==> Step 4/8: Clearing caches..."
$DRUSH cr

echo "==> Step 5/8: Removing orphaned paragraphs from the Articles page..."
ARTICLES_NID=$($DRUSH php:eval 'print (int) substr(\Drupal::service("path_alias.manager")->getPathByAlias("/explore/articles"), 6);')
if [ -z "$ARTICLES_NID" ] || [ "$ARTICLES_NID" = "0" ]; then
  echo "    No node has the alias /explore/articles; skipping."
else
  echo "    Articles page is node $ARTICLES_NID"
  $DRUSH php:script scripts/delete_orphan_paragraphs.php -- --nid="$ARTICLES_NID"
fi

echo "==> Step 6/8: Adding the articles listing to the Articles page (skips if present)..."
$DRUSH php:script scripts/articles_page_listing.php

echo "==> Step 7/8: Final cache rebuild..."
$DRUSH cr

echo "==> Step 8/8: Disabling maintenance mode..."
$DRUSH state:set system.maintenance_mode 0 -y

echo ""
echo "============================================"
echo "  Release complete."
echo ""
echo "  Check by eye:"
echo "    /explore/articles   article cards in the front-page style below the intro"
echo "                        text; a pager appears once there are more than 24"
echo "    any node edit form  clear a View Display paragraph's View field, type"
echo "                        'list': the five listing views are offered"
echo "  Then: $DRUSH config:status     (expect: no differences)"
echo "============================================"
