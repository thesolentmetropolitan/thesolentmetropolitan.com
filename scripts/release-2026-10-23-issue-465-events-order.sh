#!/usr/bin/env bash
# Release 2026-10-23: regular events sort by their next instance (issue 465).
#
# Code: customsolent_helpers (events query alter + EventsTag views cache
# plugin + its config schema). Config: views.view.events_listing switches
# its cache plugin to customsolent_events_tag. No composer change, no
# content change.
#
# Run from the project root on the server after `git pull`:
#   scripts/release-2026-10-23-issue-465-events-order.sh
# Locally: DRUSH="ddev drush" scripts/release-2026-10-23-issue-465-events-order.sh
set -euo pipefail
cd "$(dirname "$0")/.."
DRUSH="${DRUSH:-./drush-dir/drush}"

echo "== backup =="
mkdir -p backups
$DRUSH sql:dump --gzip --result-file="$(pwd)/backups/pre-release-2026-10-23-issue-465-$(date +%Y%m%d-%H%M%S).sql"

echo "== cache rebuild (new cache plugin must be discoverable before cim) =="
$DRUSH cr

echo "== config import =="
$DRUSH cim -y

echo "== cache rebuild =="
$DRUSH cr

echo "== done =="
echo "Check the front page: regular events sit at their 'Next:' date among the one-off events."
