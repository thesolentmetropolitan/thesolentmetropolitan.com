#!/usr/bin/env bash
#
# One-time release: the "Preview, all topics" listing mode, and /culture/writing
# using it for its Articles block.
#
# Writing is the one section page whose subject IS the written word, so listing
# only the articles tagged "Writing" undersells it. Its Articles block now shows
# the newest articles from across the whole site and hands over to
# /explore/articles rather than /culture/writing/articles.
#
# The listing mode itself is general: any View Display paragraph for articles,
# events or organisations can be set to "Preview, all topics" in the editor.
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

if [ ! -f scripts/release-2026-09-24-writing-all-articles.sh ]; then
  echo "Run this from the project root (the directory that contains scripts/)."; exit 1
fi

echo "============================================"
echo "  Release 2026-09-24: Writing lists all articles"
echo "============================================"

echo "==> Step 1/7: Backing up database..."
$DRUSH sql:dump --gzip --result-file="$BACKUP_DIR/backup-pre-release-2026-09-24-writing-$(date +%Y%m%d-%H%M%S).sql"

echo "==> Step 2/7: Enabling maintenance mode..."
$DRUSH state:set system.maintenance_mode 1 -y

echo "==> Step 3/7: Importing configuration (new listing-mode option)..."
$DRUSH config:import -y

echo "==> Step 4/7: Clearing caches (theme code and template change)..."
$DRUSH cr

echo "==> Step 5/7: Switching the Writing page's Articles block to all topics..."
$DRUSH php:script scripts/set_writing_articles_all_topics.php

echo "==> Step 6/7: Final cache rebuild..."
$DRUSH cr

echo "==> Step 7/7: Disabling maintenance mode..."
$DRUSH state:set system.maintenance_mode 0 -y

echo ""
echo "============================================"
echo "  Release complete."
echo ""
echo "  Check by eye:"
echo "    /culture/writing        Articles block shows the newest articles from"
echo "                            across the site, not just Writing ones; its"
echo "                            heading and both 'View more Articles' links"
echo "                            point at /explore/articles"
echo "    /culture/identity       unchanged: still only its own topic's articles,"
echo "                            'View more' still goes to /culture/identity/articles"
echo "    /sectors/regional-development   unchanged, same check as above"
echo "  Then: $DRUSH config:status     (expect: no differences)"
echo "============================================"
