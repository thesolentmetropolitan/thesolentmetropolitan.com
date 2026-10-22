#!/usr/bin/env bash
#
# Release 2026-10-22 (b): issue 462 — editing one regular event's days changed
# other events too.
#
# Cause: Smart Date Recur keeps a recurring event's rule in its own
# smart_date_rule entity and stores only the rule id on each field_when item.
# Quick Node Clone copies the field values, id included, so a cloned event
# and its original pointed at ONE rule; saving either rewrote it in place.
#
# Fix (code, customsolent_helpers): the clone gets its own copy of every rule
# before its form is shown (hook_cloned_node_alter); a new node arriving with
# a rule id some other way is copied in presave; cron sweeps copies no node
# references after a day (the clone form copies on every request).
#
# Repair (content): scripts/split_shared_recurrence_rules_2026-10-22.php gives
# every event sharing a rule its own copy, with the days (BYDAY) of each
# event's rule set from the weekdays of the instances that event actually
# holds. Read its report: a non-weekly rule is listed for a manual check.
#
# Full context: docs/claude-conversations/2026-10-22-issue-462-shared-recurrence-rules.md
#
# Run ON the production server, from the project root, AFTER:
#   git pull origin main
#
# No composer or config changes. Idempotent.
#
set -euo pipefail
DRUSH="${DRUSH:-./drush-dir/drush}"
BACKUP_DIR="${BACKUP_DIR:-$HOME}"

if [ ! -f scripts/release-2026-10-22-issue-462-recurrence-rules.sh ]; then
  echo "Run this from the project root (the directory that contains scripts/)."; exit 1
fi

echo "============================================"
echo "  Release 2026-10-22 (b): issue 462, shared recurrence rules"
echo "============================================"

echo "==> Step 1/4: Backing up database..."
$DRUSH sql:dump --gzip --result-file="$BACKUP_DIR/backup-pre-release-2026-10-22-issue-462-$(date +%Y%m%d-%H%M%S).sql"

echo "==> Step 2/4: Cache rebuild (new hooks in customsolent_helpers)..."
$DRUSH cr

echo "==> Step 3/4: Dry run of the rule split (what would change)..."
$DRUSH php:script scripts/split_shared_recurrence_rules_2026-10-22.php -- --dry-run

echo "==> Step 4/4: Splitting shared rules..."
$DRUSH php:script scripts/split_shared_recurrence_rules_2026-10-22.php
$DRUSH cr

echo ""
echo "============================================"
echo "  Release complete."
echo ""
echo "  Check by eye (the nodes named in issue 462: 914, 916, 918):"
echo "    each event page shows ITS OWN days ('Every Wednesday and Thursday' etc.)"
echo "    open one, change the days, save: the others do not change"
echo "    any 'CHECK the days by hand' line above: open that event, confirm, save"
echo "    Clone an event (Clone tab), save: both keep their own days"
echo ""
echo "  Rollback (db): gunzip < \$BACKUP_DIR/backup-pre-release-2026-10-22-issue-462-*.sql.gz | $DRUSH sql:cli"
echo "============================================"
