#!/usr/bin/env bash
#
# Release: Series and Collaborations join the section strip (Culture /
# Sectors / Living pages and their sub-topics) whenever the topic holds
# some, with the automated listing pages /{topic}/series and
# /{topic}/collaborations. Also carries the same day's amends (prefix
# redirects, related events → organiser's other events, badge, spacing),
# which are code only.
#
# Full context:
#   docs/claude-conversations/2026-10-18-afternoon-amends.md
#
# Run ON the production server, from the project root, AFTER the three
# 2026-10-18 releases (series; people-collaborations; events-and-push-and-play)
# and:
#   git pull origin main
#
# Every step is idempotent: after a failure, fix the cause and run it again.
#
set -euo pipefail

DRUSH="${DRUSH:-./drush-dir/drush}"
BACKUP_DIR="${BACKUP_DIR:-$HOME}"

if [ ! -f scripts/release-2026-10-18-strip-series-collaborations.sh ]; then
  echo "Run this from the project root (the directory that contains scripts/)."; exit 1
fi

echo "============================================"
echo "  Release 2026-10-18: Series + Collaborations in the section strip"
echo "============================================"

echo "==> Step 1/5: Backing up database..."
$DRUSH sql:dump --gzip --result-file="$BACKUP_DIR/backup-pre-release-2026-10-18-strip-$(date +%Y%m%d-%H%M%S).sql"

echo "==> Step 2/5: Enabling maintenance mode..."
$DRUSH state:set system.maintenance_mode 1 -y

echo "==> Step 3/5: Importing configuration (by-topic displays on the series and collaborations views)..."
$DRUSH config:import -y

echo "==> Step 4/5: Rebuilding caches (new routes, strip kinds, templates, CSS)..."
$DRUSH cr

echo "==> Step 5/5: Disabling maintenance mode..."
$DRUSH state:set system.maintenance_mode 0 -y

echo ""
echo "============================================"
echo "  Release complete."
echo ""
echo "  Check by eye:"
echo "    /culture                      strip: Overview · Events · Articles · Series 1 ·"
echo "                                  Collaborations 1 · Organisations & links"
echo "    /culture/series               Black History Month card"
echo "    /culture/sport                strip has Collaborations, no Series"
echo "    /living                       strip unchanged (no series or collaborations yet)"
echo "    /series  /collaborations      redirect to /explore/series, /explore/collaborations"
echo "    an event page                 'Coming up from <organiser>' at the foot;"
echo "                                  Regular event badge light blue, beside the title"
echo "    a phone                       event grids reach the text edges on Home and series pages"
echo ""
echo "  Then: $DRUSH config:status   (expect: No differences)"
echo "============================================"
