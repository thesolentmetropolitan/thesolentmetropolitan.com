#!/usr/bin/env bash
#
# Release 2026-10-14: Drupal core 11.4.10 (CRITICAL, SA-CORE-2026-014) plus
# two contrib security fixes found by `composer audit`.
#
# What changes (all via composer.lock — no config changes expected):
#   drupal/core               11.4.8 -> 11.4.10  SA-CORE-2026-014, critical 19/25,
#                                                 RCE via Field UI; one exploit path
#                                                 needs no authentication.
#   drupal/menu_link_attributes 1.7.0 -> 1.10.0  SA-CONTRIB-2026-209, XSS (11/25).
#                                                 Brings 3 database updates that
#                                                 strip unsafe attribute definitions.
#                                                 Our three definitions (container_class,
#                                                 class, target) are all safe: rehearsed
#                                                 locally, config and menu links unchanged.
#   drupal/svg_image          3.2.4 -> 3.2.5     lets enshrined/svg-sanitize move to 1.0.0,
#   enshrined/svg-sanitize    0.22.0 -> 1.0.0    fixing 3 advisories (stored XSS, DoS).
#   symfony/* 7.4.21 and other transitive dependencies within constraints.
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

if [ ! -f scripts/release-2026-10-14-core-security.sh ]; then
  echo "Run this from the project root (the directory that contains scripts/)."; exit 1
fi
if [ ! -x "$COMPOSER_BIN" ] && ! command -v "$COMPOSER_BIN" >/dev/null 2>&1; then
  echo "Composer not found at '$COMPOSER_BIN'. Set COMPOSER_BIN=/path/to/composer and rerun."; exit 1
fi
if ! grep -q '"version": "11.4.10"' composer.lock; then
  echo "composer.lock does not contain drupal/core 11.4.10 — did 'git pull origin main' run?"; exit 1
fi

echo "============================================"
echo "  Release 2026-10-14: core 11.4.10 security"
echo "============================================"
echo "Before:  $($DRUSH status --fields=drupal-version,php-version --format=list | tr '\n' ' ')"
echo ""

echo "==> Step 1/7: Backing up database..."
$DRUSH sql:dump --gzip --result-file="$BACKUP_DIR/backup-pre-release-2026-10-14-core-security-$(date +%Y%m%d-%H%M%S).sql"

echo "==> Step 2/7: Enabling maintenance mode..."
$DRUSH state:set system.maintenance_mode 1 -y
$DRUSH cr

echo "==> Step 3/7: Installing updated code (composer install from composer.lock)..."
$COMPOSER_BIN install --no-dev --optimize-autoloader --no-interaction

echo "==> Step 4/7: Running database updates (3 expected, all from menu_link_attributes)..."
$DRUSH updatedb -y

echo "==> Step 5/7: Cache rebuild..."
$DRUSH cr

echo "==> Step 6/7: Checking configuration is still in sync..."
if $DRUSH config:status --format=list 2>/dev/null | grep -q .; then
  echo "  !! Active config differs from config/sync (listed below)."
  echo "     Rehearsal showed no config change from this release, so this is either"
  echo "     pre-existing drift or menu_link_attributes_update_8004 removed an"
  echo "     attribute definition that exists only on production."
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
echo "  Composer audit (should report no advisories):"
$COMPOSER_BIN audit --no-dev || true
echo ""
echo "  Check by eye:"
echo "    /                  front page renders, menu opens (mobile + desktop)"
echo "    /explore/articles  listing + pager"
echo "    /culture           hero tile SVGs render (svg_image + sanitiser updated)"
echo "    /about/contact     webform renders"
echo "    /admin/reports/status   'Drupal 11.4.10', no new errors"
echo "    /admin/reports/dblog    no PHP errors after a few page loads"
echo ""
echo "  Rollback (code): git checkout <previous main sha> -- composer.lock"
echo "                   && $COMPOSER_BIN install --no-dev && $DRUSH cr"
echo "  Rollback (db):   gunzip < \$BACKUP_DIR/backup-pre-release-2026-10-14-core-security-*.sql.gz | $DRUSH sql:cli"
echo "============================================"
