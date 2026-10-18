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

echo "==> Step 1/9: Backing up database..."
$DRUSH sql:dump --gzip --result-file="$BACKUP_DIR/backup-pre-release-2026-10-18-events-$(date +%Y%m%d-%H%M%S).sql"

echo "==> Step 2/9: Enabling maintenance mode..."
$DRUSH state:set system.maintenance_mode 1 -y

echo "==> Step 3/9: Importing configuration (organiser field, venues unlimited, recurring dates, date formats)..."
$DRUSH config:import -y

echo "==> Step 4/9: Clearing caches..."
$DRUSH cr

echo "==> Step 5/9: Push & Play: organisations, collaborators, collaboration; event 706 published..."
$DRUSH php:script scripts/create_push_and_play_collaboration.php

echo "==> Step 6/9: Regular events: Andysmanclub (published), Mangang (draft)..."
$DRUSH php:script scripts/create_regular_events_examples.php

echo "==> Step 7/9: Organisers on known events (Common Ground Regen CIC on 776, 777)..."
$DRUSH php:script scripts/set_event_organisers.php

echo "==> Step 8/9: Final cache rebuild..."
$DRUSH cr

echo "==> Step 9/9: Disabling maintenance mode..."
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
echo "    /                                Andysmanclub card: Regular event badge, Every Monday, Next: …"
echo "    /organisations/common-ground-regen-cic   Coming up: the two walks events"
echo "    /admin/content?type=event&status=2    Mangang draft: open, check, publish"
echo "    /node/add/event                  Organiser field; 'Add another' on Where; repeat options on When"
echo "  Then: $DRUSH config:status     (expect: no differences)"
echo "============================================"
