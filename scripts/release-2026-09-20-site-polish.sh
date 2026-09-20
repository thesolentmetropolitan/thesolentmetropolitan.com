#!/usr/bin/env bash
#
# One-time release: Rob's site-polish items 2 to 11 — wider content column,
# smaller card text, heading break, "View more Events/Articles", Explore
# populated, 404 / log in / maintenance pages, clickable section headings,
# footer menu, white desktop submenu.
#
# Full context:
#   docs/claude-conversations/2026-09-20-site-polish-items-2-to-11.md
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

if [ ! -f scripts/release-2026-09-20-site-polish.sh ]; then
  echo "Run this from the project root (the directory that contains scripts/)."; exit 1
fi

echo "============================================"
echo "  Release 2026-09-20: site polish"
echo "============================================"

echo "==> Step 1/7: Backing up database..."
$DRUSH sql:dump --gzip --result-file="$BACKUP_DIR/backup-pre-release-2026-09-20-polish-$(date +%Y%m%d-%H%M%S).sql"

echo "==> Step 2/7: Enabling maintenance mode (you will see the NEW maintenance page)..."
$DRUSH state:set system.maintenance_mode 1 -y

echo "==> Step 3/7: Importing configuration (404 no longer points at the old Team article)..."
$DRUSH config:import -y

echo "==> Step 4/7: Clearing caches..."
$DRUSH cr

echo "==> Step 5/7: Heading break, View more labels, Explore content..."
$DRUSH php:script scripts/site_polish_content.php

echo "==> Step 6/7: Final cache rebuild..."
$DRUSH cr

echo "==> Step 7/7: Disabling maintenance mode..."
$DRUSH state:set system.maintenance_mode 0 -y

echo ""
echo "============================================"
echo "  Release complete."
echo ""
echo "  Check by eye:"
echo "    /  on a wide screen    content column wider; four cards ~320px each"
echo "    /  phone               'What's on across' / 'the Greater Solent' on two lines"
echo "    /                      'View more Events', 'View more Articles'; section headings are links"
echo "    /explore               events, latest articles, organisations box"
echo "    /no-such-page          'Page not found' with onward links"
echo "    /user/login            styled form, 'Forgotten your password?'"
echo "    footer (desktop)       menu 3 across x 2 down, pink underlines"
echo "    desktop menu           open submenu is white"
echo "  Then: $DRUSH config:status     (expect: no differences)"
echo "============================================"
