#!/usr/bin/env bash
#
# Release: topic re-alignment, batch 1.
#
#   Culture / Outdoor & Active  ->  Culture / Active & Outdoor     (issue 360)
#   Sectors / Media             ->  Sectors / Media & Publishing   (issue 318)
#   Culture / Maritime          ->  Culture / Maritime & Marine    (issue 321)
#   Sectors / Maritime          ->  Sectors / Maritime & Marine    (issue 321)
#   Explore / Jobs Boards       ->  deleted, with its unpublished page
#
# Terms, pages, aliases, menu links and redirects are content, so the data
# script does the work; the config import only renames the hero art-style
# labels and removes the Jobs boards style. Run the data script FIRST so the
# Jobs boards page (which references that style) is gone before the style is.
#
# Full context:
#   docs/claude-conversations/2026-10-19-topic-realignment.md
#
# Run ON the production server, from the project root, AFTER:
#   git pull origin main
#
# Every step is idempotent: after a failure, fix the cause and run it again.
#
set -euo pipefail

DRUSH="${DRUSH:-./drush-dir/drush}"
BACKUP_DIR="${BACKUP_DIR:-$HOME}"

if [ ! -f scripts/release-2026-10-19-topic-realignment.sh ]; then
  echo "Run this from the project root (the directory that contains scripts/)."; exit 1
fi

echo "============================================"
echo "  Release 2026-10-19: topic re-alignment (batch 1)"
echo "============================================"

echo "==> Step 1/6: Backing up database..."
$DRUSH sql:dump --gzip --result-file="$BACKUP_DIR/backup-pre-release-2026-10-19-topics-$(date +%Y%m%d-%H%M%S).sql"

echo "==> Step 2/6: Enabling maintenance mode..."
$DRUSH state:set system.maintenance_mode 1 -y

echo "==> Step 3/6: Renaming the four topics (term, page, alias, menu link, redirects) and deleting Jobs Boards..."
$DRUSH php:script scripts/realign_topics_2026-10-19.php

echo "==> Step 4/6: Importing configuration (hero art-style labels; Jobs boards style removed)..."
$DRUSH config:import -y

echo "==> Step 5/6: Rebuilding caches (menus, kicker links, renamed tiles)..."
$DRUSH cr

echo "==> Step 6/6: Disabling maintenance mode..."
$DRUSH state:set system.maintenance_mode 0 -y

echo ""
echo "============================================"
echo "  Release complete."
echo ""
echo "  Check by eye:"
echo "    /culture/outdoor-active           301 -> /culture/active-outdoor"
echo "    /culture/outdoor-active/events    301 -> /culture/active-outdoor/events"
echo "    /sectors/media                    301 -> /sectors/media-publishing"
echo "    /culture/maritime                 301 -> /culture/maritime-marine"
echo "    /sectors/maritime                 301 -> /sectors/maritime-marine"
echo "    Culture and Sectors menus         Active & Outdoor, Media & Publishing, Maritime & Marine"
echo "    /organisations/hilsea-lido        kicker reads Culture / Active & Outdoor and links to the new page"
echo "    /sectors/maritime-marine          intro link to the Culture Maritime & Marine page works"
echo ""
echo "  Then: $DRUSH config:status   (expect: No differences)"
echo "============================================"
