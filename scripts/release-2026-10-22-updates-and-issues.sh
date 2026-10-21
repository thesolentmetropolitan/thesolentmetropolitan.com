#!/usr/bin/env bash
#
# Release 2026-10-22: security/bug-fix updates plus seven GitHub issues.
#
# Code updates (composer.lock; .htaccess re-scaffolded by core):
#   drupal/core                11.4.10 -> 11.4.11   bug-fix release (no SA-CORE).
#                                                   Replaces the deprecated
#                                                   `assert.active` line in
#                                                   web/.htaccess with
#                                                   `zend.assertions 0`.
#   drupal/gin                 5.0.15  -> 5.0.16    SA-CONTRIB-2026-219, XSS (13/25),
#                                                   needs "administer menu" or HTML
#                                                   with data attributes to exploit.
#   drupal/rabbit_hole         2.0.0-beta2 -> beta3 SA-CONTRIB-2026-252, access bypass
#                                                   (11/25): a query parameter could
#                                                   skip the redirect/deny behaviour.
#                                                   Our collaborator 301s re-tested.
#   drupal/single_content_sync 1.4.17  -> 1.4.18    fixes an arbitrary file upload
#                                                   introduced in 1.4.17.
#   drupal/klaro               3.1.1   -> 3.1.2     bug fixes (HTML parser).
#   drupal/superfish           1.16.0  -> 1.17.0    minor release (module enabled,
#                                                   menu is the theme's own).
#   guzzlehttp/*               within constraints.
#   No database updates in the rehearsal.
#
# Issues (config import + theme code + one content script):
#   #148 favicon: theme favicon.ico / favicon.svg / apple-touch-icon.png
#        (the square SLNT mark) + web/favicon.ico. Code only.
#   #436 Series "Organised by <organisation>" — field_organiser on Series
#        (config), shown only when set.
#   #437 Text paragraphs in the main content get the 740px reading column.
#        CSS only.
#   #443 Collaborator "How they collaborate": one row per role with
#        "Add another item" (widget change, config).
#   #444 Link URLs /explore/links/<title> -> /link/<title>: pattern (config)
#        + scripts/move_link_urls_2026-10-22.php (aliases + 301s, content)
#        + /link and /links -> /explore/organisations (routes, code).
#   #460 Organisation page lists every location ("Southampton and
#        Portsmouth"). Theme code; the field already took several.
#   #461 Regular events on two days read "Every Wednesday and Thursday".
#        Theme code.
#
# Full context: docs/claude-conversations/2026-10-22-updates-and-issues.md
#
# Run ON the production server, from the project root, AFTER:
#   git pull origin main
#
# Composer install runs BY THIS SCRIPT, inside maintenance mode. Every step
# is idempotent: after a failure, fix the cause and run it again.
#
set -euo pipefail

DRUSH="${DRUSH:-./drush-dir/drush}"
COMPOSER_BIN="${COMPOSER_BIN:-./composer}"   # tracked in git; or `composer` on PATH.
                                             # (Not named COMPOSER: Composer reads that
                                             # env var as the path to composer.json.)
BACKUP_DIR="${BACKUP_DIR:-$HOME}"

if [ ! -f scripts/release-2026-10-22-updates-and-issues.sh ]; then
  echo "Run this from the project root (the directory that contains scripts/)."; exit 1
fi
if [ ! -x "$COMPOSER_BIN" ] && ! command -v "$COMPOSER_BIN" >/dev/null 2>&1; then
  echo "Composer not found at '$COMPOSER_BIN'. Set COMPOSER_BIN=/path/to/composer and rerun."; exit 1
fi
if ! grep -q '"version": "11.4.11"' composer.lock; then
  echo "composer.lock does not contain drupal/core 11.4.11 — did 'git pull origin main' run?"; exit 1
fi

echo "============================================"
echo "  Release 2026-10-22: updates + issues 148 436 437 443 444 460 461"
echo "============================================"
echo "Before:  $($DRUSH status --fields=drupal-version,php-version --format=list | tr '\n' ' ')"
echo ""

echo "==> Step 1/9: Backing up database..."
$DRUSH sql:dump --gzip --result-file="$BACKUP_DIR/backup-pre-release-2026-10-22-$(date +%Y%m%d-%H%M%S).sql"

echo "==> Step 2/9: Enabling maintenance mode..."
$DRUSH state:set system.maintenance_mode 1 -y
$DRUSH cr

echo "==> Step 3/9: Installing updated code (composer install from composer.lock)..."
$COMPOSER_BIN install --no-dev --optimize-autoloader --no-interaction

echo "==> Step 4/9: Running database updates (none expected)..."
$DRUSH updatedb -y

echo "==> Step 5/9: Importing configuration (Series organiser field, collaborator widget, Link URL pattern)..."
$DRUSH config:import -y

echo "==> Step 6/9: Moving Link content to /link/<title> with 301s from the old addresses..."
$DRUSH php:script scripts/move_link_urls_2026-10-22.php

echo "==> Step 7/9: Cache rebuild (routes, theme hooks, favicon links)..."
$DRUSH cr

echo "==> Step 8/9: Checking configuration is in sync..."
if $DRUSH config:status --format=list 2>/dev/null | grep -q .; then
  echo "  !! Active config differs from config/sync — review with: $DRUSH config:status"
  $DRUSH config:status
else
  echo "  Config in sync."
fi

echo "==> Step 9/9: Disabling maintenance mode..."
$DRUSH state:set system.maintenance_mode 0 -y
$DRUSH cr

echo ""
echo "============================================"
echo "  Release complete."
echo "After:   $($DRUSH status --fields=drupal-version,drush-version,php-version --format=list | tr '\n' ' ')"
echo ""
echo "  Composer audit (should report no advisories):"
$COMPOSER_BIN audit --no-dev || true
echo ""
echo "  Check by eye:"
echo "    /                                  front page; browser tab shows the SLNT mark (hard-refresh)"
echo "    /explore/links/money-helper        301 -> /link/money-helper (200)"
echo "    /link  and  /links                 301 -> /explore/organisations"
echo "    /events/salsa-bachata-...          'Every Wednesday and Thursday, 7:30pm'"
echo "    /about/editorial-policy            body text in a 740px column"
echo "    /organisations/<one with 2 locations>  'Town and Town' under the title"
echo "    /node/<series>/edit                Organiser field present; page shows 'Organised by' only when set"
echo "    /node/<collaborator>/edit          'How they collaborate' has value rows + Add another item"
echo "    /node/<collaborator>               still 301s to the organisation/person (Rabbit Hole beta3)"
echo "    /admin/reports/status              Drupal 11.4.11, no new errors"
echo ""
echo "  Rollback (code): git checkout <previous main sha> -- composer.lock web/.htaccess"
echo "                   && $COMPOSER_BIN install --no-dev && $DRUSH cr"
echo "  Rollback (db):   gunzip < \$BACKUP_DIR/backup-pre-release-2026-10-22-*.sql.gz | $DRUSH sql:cli"
echo "============================================"
