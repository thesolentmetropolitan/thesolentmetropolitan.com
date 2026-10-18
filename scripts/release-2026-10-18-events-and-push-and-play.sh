#!/usr/bin/env bash
#
# One-time release: Event changes — Organiser field, several venues at once,
# recurring (regular) events with one row per event in listings — plus the
# first real Collaboration: Push & Play, the Hoglands Park skatepark launch
# (ten collaborators, six new organisations, event 706 published).
#
# Requires release-2026-10-18-series.sh and
# release-2026-10-18-people-collaborations.sh to have run first.
#
# Full context:
#   docs/claude-conversations/2026-10-18-events-and-push-and-play-implementation.md
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

if [ ! -f scripts/release-2026-10-18-events-and-push-and-play.sh ]; then
  echo "Run this from the project root (the directory that contains scripts/)."; exit 1
fi

echo "============================================"
echo "  Release 2026-10-18: Events (organiser, venues, recurring) + Push & Play"
echo "============================================"

echo "==> Step 1/8: Backing up database..."
$DRUSH sql:dump --gzip --result-file="$BACKUP_DIR/backup-pre-release-2026-10-18-events-$(date +%Y%m%d-%H%M%S).sql"

echo "==> Step 2/8: Enabling maintenance mode..."
$DRUSH state:set system.maintenance_mode 1 -y

echo "==> Step 3/8: Importing configuration (organiser field, venues unlimited, recurring dates, date formats)..."
$DRUSH config:import -y

echo "==> Step 4/8: Clearing caches..."
$DRUSH cr

echo "==> Step 5/8: Push & Play: organisations, collaborators, collaboration; event 706 published..."
$DRUSH php:script scripts/create_push_and_play_collaboration.php

echo "==> Step 6/8: Regular-event examples (Andysmanclub, Mangang) as UNPUBLISHED drafts..."
$DRUSH php:script scripts/create_regular_events_examples.php

echo "==> Step 7/8: Final cache rebuild..."
$DRUSH cr

echo "==> Step 8/8: Disabling maintenance mode..."
$DRUSH state:set system.maintenance_mode 0 -y

echo ""
echo "============================================"
echo "  Release complete."
echo ""
echo "  Check by eye:"
echo "    /collaborations/push-play-new-hoglands-park-skatepark"
echo "                                 Who: ten collaborators (Partner); Events: the past event"
echo "    /events/push-play-new-hoglands-park-skatepark-2026-08-23-1100"
echo "                                 Past event · Organised by We Skate Soton CIC · Part of a collaboration"
echo "    /organisations/we-skate-soton-cic     Part of a collaboration"
echo "    /admin/content?type=event&status=2    Andysmanclub + Mangang drafts: open, check, publish"
echo "    /node/add/event                  Organiser field; 'Add another' on Where; repeat options on When"
echo "  Then: $DRUSH config:status     (expect: no differences)"
echo "============================================"
