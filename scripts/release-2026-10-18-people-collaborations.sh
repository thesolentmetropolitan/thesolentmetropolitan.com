#!/usr/bin/env bash
#
# One-time release: People (Person type, article authors, team page) and
# Collaborations (Collaborator + Collaboration types, collaborator roles,
# /explore/collaborations listing, "Part of a collaboration" on event,
# article, series, link, person and organisation pages; Organisation gains
# Where + content components).
#
# Requires the Series release (release-2026-10-18-series.sh) to have run
# first: the collaboration lookups build on the Series service.
#
# Full context:
#   docs/claude-conversations/2026-10-18-people-series-collaborations-brief.md
#   docs/claude-conversations/2026-10-18-people-collaborations-implementation.md
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

if [ ! -f scripts/release-2026-10-18-people-collaborations.sh ]; then
  echo "Run this from the project root (the directory that contains scripts/)."; exit 1
fi

echo "============================================"
echo "  Release 2026-10-18: People + Collaborations"
echo "============================================"

echo "==> Step 1/9: Backing up database..."
$DRUSH sql:dump --gzip --result-file="$BACKUP_DIR/backup-pre-release-2026-10-18-people-$(date +%Y%m%d-%H%M%S).sql"

echo "==> Step 2/9: Enabling maintenance mode..."
$DRUSH state:set system.maintenance_mode 1 -y

echo "==> Step 3/9: Importing configuration (Person, Collaborator, Collaboration, fields, views, URL patterns)..."
$DRUSH config:import -y

echo "==> Step 4/9: Clearing caches (new hooks, templates and CSS)..."
$DRUSH cr

echo "==> Step 5/9: Collaborator role terms..."
$DRUSH php:script scripts/create_collaborator_types.php

echo "==> Step 6/9: The team on /about/team (founder Person + people listing)..."
$DRUSH php:script scripts/shape_team_page_people.php

echo "==> Step 7/9: Placing the Collaborations listing on /explore/collaborations..."
$DRUSH php:script scripts/add_collaborations_listing.php

echo "==> Step 8/9: Final cache rebuild..."
$DRUSH cr

echo "==> Step 9/9: Disabling maintenance mode..."
$DRUSH state:set system.maintenance_mode 0 -y

echo ""
echo "============================================"
echo "  Release complete."
echo ""
echo "  Check by eye:"
echo "    /about/team                 'The team' heading, one card: Rob Davis"
echo "    /people/rob-davis           initials avatar, bio (first draft — re-voice)"
echo "    /node/add/article           Author field under the title"
echo "    /node/add/person            First name / Last name, no Title field"
echo "    /explore/collaborations     intro text, no cards yet (none created)"
echo "    /node/add/collaborator      Who (organisation or person), roles, description"
echo "    /node/add/collaboration     collaborators, outcomes, components, topics"
echo "    /organisations/<any>        edit form has Where + Content components"
echo "  Then: $DRUSH config:status     (expect: no differences)"
echo "============================================"
