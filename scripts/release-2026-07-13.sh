#!/usr/bin/env bash
#
# One-time release: content URL prefixes (/articles, /events, /organisations),
# the [node:event_date_formatted] token, full node views + Rabbit Hole, and
# topic-based desktop-menu indication.
#
# Full context: docs/deploy/2026-07-13-content-url-nav-deploy.md
#
# Run ON the production server, from the project root, AFTER:
#   git pull origin main
#   composer install --no-dev --optimize-autoloader
#
# It backs up the DB, imports config, regenerates the content URL aliases
# (Redirect module auto-creates 301s; /about/* pages are protected), and adds
# the bare collection-path redirects — all inside maintenance mode.
#
set -euo pipefail

# Drush invocation for this environment. Change to `drush` if it's on PATH.
DRUSH="./drush-dir/drush"

echo "============================================"
echo "  Release 2026-07-13: content URLs + nav"
echo "============================================"

echo "==> Step 1/8: Backing up database..."
$DRUSH sql:dump --gzip --result-file="$HOME/backup-pre-release-2026-07-13-$(date +%Y%m%d-%H%M%S).sql.gz"

echo "==> Step 2/8: Enabling maintenance mode..."
$DRUSH state:set system.maintenance_mode 1 -y

echo "==> Step 3/8: Importing configuration (module, Rabbit Hole, Pathauto patterns)..."
$DRUSH config:import -y

echo "==> Step 4/8: Clearing caches..."
$DRUSH cr

echo "==> Step 5/8: Regenerating content URL aliases (creates 301s, protects /about/*)..."
$DRUSH php:script scripts/regenerate-url-aliases.php

echo "==> Step 6/8: Creating bare collection-path redirects..."
$DRUSH php:script scripts/create-collection-redirects.php

echo "==> Step 7/8: Final cache rebuild..."
$DRUSH cr

echo "==> Step 8/8: Disabling maintenance mode..."
$DRUSH state:set system.maintenance_mode 0 -y

echo ""
echo "============================================"
echo "  Release complete."
echo ""
echo "  Verify:"
echo "    curl -sI https://<site>/explore/events/<old-slug> | grep -i location   # 301 -> /events/<slug>"
echo "    curl -sI https://<site>/events        | grep -i location                # 301 -> /explore/events"
echo "    curl -sI https://<site>/about/overview | head -1                        # 200 (unchanged)"
echo "============================================"
