#!/usr/bin/env bash
#
# One-time release: /explore/opinion lists the articles tagged "Explore / Opinion".
#
# Content only — no code or configuration changes. The page gets a View
# Display paragraph in the same shape as Explore → Directories & Networks:
# the page's own Explore term in the Topic field (without it an Explore page
# lists everything) and Full listing mode (card grid, one pager). If a
# listing paragraph was already added by hand, it is corrected in place
# rather than duplicated.
#
# Run ON the production server, from the project root, AFTER:
#   git pull origin main
#
# Every step is idempotent: after a failure, fix the cause and run it again.
#
set -euo pipefail

DRUSH="${DRUSH:-./drush-dir/drush}"
BACKUP_DIR="${BACKUP_DIR:-$HOME}"

if [ ! -f scripts/release-2026-09-25-opinion-articles.sh ]; then
  echo "Run this from the project root (the directory that contains scripts/)."; exit 1
fi

echo "============================================"
echo "  Release 2026-09-25: Opinion lists its articles"
echo "============================================"

echo "==> Step 1/3: Backing up database..."
$DRUSH sql:dump --gzip --result-file="$BACKUP_DIR/backup-pre-release-2026-09-25-opinion-$(date +%Y%m%d-%H%M%S).sql"

echo "==> Step 2/3: Placing (or correcting) the articles listing on /explore/opinion..."
$DRUSH php:script scripts/set_explore_opinion_articles.php

echo "==> Step 3/3: Cache rebuild..."
$DRUSH cr

echo ""
echo "============================================"
echo "  Release complete."
echo ""
echo "  Check by eye:"
echo "    /explore/opinion        the articles tagged Opinion as square cards in"
echo "                            the grid, each with its primary topic as the"
echo "                            kicker (e.g. Culture / Identity); no strip"
echo "  If a draft of the page exists in the editor from adding the listing by"
echo "  hand, discard it — the published revision now carries the listing."
echo "============================================"
