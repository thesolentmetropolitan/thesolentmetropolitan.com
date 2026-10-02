#!/usr/bin/env bash
#
# Release 2026-10-03: Drupal core + contrib module updates (SECURITY).
#
# What changes (all via composer.lock — no config, no content):
#   drupal/core             11.4.5  -> 11.4.8   (11.4.7 = SA-CORE-2026-013, security)
#   drupal/webform          6.3.0   -> 6.3.1    (security release, 22 fixes)
#   drupal/focal_point      2.1.2   -> 2.1.3
#   drupal/single_content_sync 1.4.16 -> 1.4.17
#   drupal/superfish        1.15.0  -> 1.16.0   (+ library 2.3.10 -> 2.3.11,
#                                                tracked in web/libraries/)
#   drush/drush             13.7.6  -> 13.8.0
#   symfony/*, twig/twig and other transitive dependencies within constraints.
#   drupal/custom_field stays on 4.x (5.0 is a major; separate job).
#
# Rehearsed locally 2026-10-03: no pending database updates, no config
# changes, key pages 200, no log errors.
#
# Run ON the production server, from the project root, AFTER:
#   git pull origin main
#
# Composer install is run BY THIS SCRIPT, inside maintenance mode, so the
# site never serves a half-swapped codebase. Every step is idempotent:
# after a failure, fix the cause and run it again.
#
set -euo pipefail

DRUSH="${DRUSH:-./drush-dir/drush}"
COMPOSER_BIN="${COMPOSER_BIN:-./composer}"   # tracked in git; or `composer` on PATH.
                                             # (Not named COMPOSER: Composer reads that
                                             # env var as the path to composer.json.)
BACKUP_DIR="${BACKUP_DIR:-$HOME}"

if [ ! -f scripts/release-2026-10-03-core-module-updates.sh ]; then
  echo "Run this from the project root (the directory that contains scripts/)."; exit 1
fi
if [ ! -x "$COMPOSER_BIN" ] && ! command -v "$COMPOSER_BIN" >/dev/null 2>&1; then
  echo "Composer not found at '$COMPOSER_BIN'. Set COMPOSER_BIN=/path/to/composer and rerun."; exit 1
fi
if ! grep -q '"version": "11.4.8"' composer.lock; then
  echo "composer.lock does not contain drupal/core 11.4.8 — did 'git pull origin main' run?"; exit 1
fi

echo "============================================"
echo "  Release 2026-10-03: core + module updates"
echo "============================================"
echo "Before:  $($DRUSH status --fields=drupal-version,php-version --format=list | tr '\n' ' ')"
echo ""

echo "==> Step 1/7: Backing up database..."
$DRUSH sql:dump --gzip --result-file="$BACKUP_DIR/backup-pre-release-2026-10-03-updates-$(date +%Y%m%d-%H%M%S).sql"

echo "==> Step 2/7: Enabling maintenance mode..."
$DRUSH state:set system.maintenance_mode 1 -y
$DRUSH cr

echo "==> Step 3/7: Installing updated code (composer install from composer.lock)..."
$COMPOSER_BIN install --no-dev --optimize-autoloader --no-interaction

echo "==> Step 4/7: Running database updates (none expected)..."
$DRUSH updatedb -y

echo "==> Step 5/7: Cache rebuild..."
$DRUSH cr

echo "==> Step 6/7: Checking configuration is still in sync..."
if $DRUSH config:status --format=list 2>/dev/null | grep -q .; then
  echo "  !! Active config differs from config/sync (listed above/below)."
  echo "     This release makes no config changes, so this is pre-existing drift."
  echo "     Not importing automatically — review with: $DRUSH config:status"
  $DRUSH config:status
else
  echo "  Config in sync."
fi

echo "==> Step 7/7: Disabling maintenance mode..."
$DRUSH state:set system.maintenance_mode 0 -y
$DRUSH cr

echo ""
echo "============================================"
echo "  Release complete."
echo "After:   $($DRUSH status --fields=drupal-version,drush-version,php-version --format=list | tr '\n' ' ')"
echo ""
echo "  Requirement errors (SMTP warning is expected if mail is off):"
$DRUSH core:requirements --severity=2 --format=table || true
echo ""
echo "  Check by eye:"
echo "    /                  front page renders, menu opens (mobile + desktop)"
echo "    /explore/articles  listing + pager"
echo "    /about/contact     webform renders and submits (Webform was a security update)"
echo "    /admin/reports/status   no new errors; 'Drupal 11.4.8'"
echo "    /admin/reports/dblog    no PHP errors after a few page loads"
echo ""
echo "  Rollback (code): git checkout <previous main sha> -- composer.lock web/libraries"
echo "                   && $COMPOSER_BIN install --no-dev && $DRUSH cr"
echo "  Rollback (db):   gunzip < \$BACKUP_DIR/backup-pre-release-2026-10-03-updates-*.sql.gz | $DRUSH sql:cli"
echo "============================================"
