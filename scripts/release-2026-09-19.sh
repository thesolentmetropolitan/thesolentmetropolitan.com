#!/usr/bin/env bash
#
# One-time release: Article body -> field_body, front-page article grid,
# restyled front-page cards, AA-compliant Living / Explore colours.
#
# Full context:
#   docs/claude-conversations/2026-09-18-article-body-field-and-front-page-grid-brief.md
#   docs/claude-conversations/2026-09-18-article-body-field-and-front-page-grid-implementation.md
#
# Run ON the production server, from the project root, AFTER:
#   git pull origin main
#   composer install --no-dev --optimize-autoloader     (no composer changes in
#                                                        this release; harmless)
#
# WHY TWO CONFIG IMPORTS
# The full config import deletes the old `body` field. The moment a field
# storage is deleted Drupal RENAMES its tables (node__body ->
# field_deleted_data_<hash>), so a data copy that runs after the full import
# cannot find node__body. Instead:
#   phase 1  partial import of just the two field_body config files
#            -> field_body exists, body is still completely alive
#   migrate  copy body -> field_body (values, formats, all revisions)
#   phase 2  full import -> removes body, adds everything else
# Nothing depends on cron timing this way.
#
# Every step is idempotent, so after a failure it is safe to fix the cause
# and simply run this script again.
#
set -euo pipefail

# Drush invocation for this environment. Override: DRUSH="drush" bash scripts/…
DRUSH="${DRUSH:-./drush-dir/drush}"
# Absolute: drush resolves a relative --source against the web root, not here.
PHASE1_DIR="${PHASE1_DIR:-$(pwd)/config/release-2026-09-19-phase1}"
if [ ! -f scripts/release-2026-09-19.sh ]; then
  echo "Run this from the project root (the directory that contains scripts/)."; exit 1
fi
BACKUP_DIR="${BACKUP_DIR:-$HOME}"

echo "============================================"
echo "  Release 2026-09-19: article body + front page"
echo "============================================"

echo "==> Step 1/11: Backing up database..."
$DRUSH sql:dump --gzip --result-file="$BACKUP_DIR/backup-pre-release-2026-09-19-$(date +%Y%m%d-%H%M%S).sql"

echo "==> Step 2/11: Enabling maintenance mode..."
$DRUSH state:set system.maintenance_mode 1 -y

echo "==> Step 3/11: Phase 1 — creating field_body (partial config import)..."
$DRUSH config:import --partial --source="$PHASE1_DIR" -y

echo "==> Step 4/11: Copying body -> field_body (values, formats, revisions)..."
$DRUSH php:script scripts/migrate_article_body_to_field_body.php

echo "==> Step 5/11: Verifying the copy before body is removed..."
BODY_ROWS=$($DRUSH sql:query "SELECT COUNT(*) FROM node__body" 2>/dev/null | tr -dc '0-9' || true)
MATCH_ROWS=$($DRUSH sql:query "SELECT COUNT(*) FROM node__body b JOIN node__field_body f ON f.entity_id=b.entity_id AND f.revision_id=b.revision_id AND f.langcode=b.langcode AND f.delta=b.delta WHERE f.field_body_value <=> b.body_value AND f.field_body_format <=> b.body_format" 2>/dev/null | tr -dc '0-9' || true)
REV_ROWS=$($DRUSH sql:query "SELECT COUNT(*) FROM node_revision__body" 2>/dev/null | tr -dc '0-9' || true)
REV_MATCH=$($DRUSH sql:query "SELECT COUNT(*) FROM node_revision__body b JOIN node_revision__field_body f ON f.entity_id=b.entity_id AND f.revision_id=b.revision_id AND f.langcode=b.langcode AND f.delta=b.delta WHERE f.field_body_value <=> b.body_value AND f.field_body_format <=> b.body_format" 2>/dev/null | tr -dc '0-9' || true)
if [ -z "$BODY_ROWS" ]; then
  echo "    node__body no longer exists — body was already removed by an earlier run; skipping check."
else
  echo "    current:   $MATCH_ROWS of $BODY_ROWS body rows identical in field_body"
  echo "    revisions: $REV_MATCH of $REV_ROWS body revision rows identical in field_body"
  if [ "$BODY_ROWS" != "$MATCH_ROWS" ] || [ "$REV_ROWS" != "$REV_MATCH" ]; then
    echo ""
    echo "ABORT: copy is incomplete. body has NOT been removed and nothing is lost."
    echo "       The site is still in maintenance mode. Investigate, then re-run,"
    echo "       or bring the site back with:"
    echo "         $DRUSH state:set system.maintenance_mode 0 -y && $DRUSH cr"
    exit 1
  fi
fi

echo "==> Step 6/11: Phase 2 — full config import (removes body; adds grid config)..."
$DRUSH config:import -y

echo "==> Step 7/11: Clearing caches..."
$DRUSH cr

echo "==> Step 8/11: Adding the Latest articles band to the front page..."
$DRUSH php:script scripts/add_front_page_articles_grid.php

echo "==> Step 9/11: Deepening the Living / Explore colour terms..."
$DRUSH php:script scripts/update_section_colour_terms.php

echo "==> Step 10/11: Final cache rebuild..."
$DRUSH cr

echo "==> Step 11/11: Disabling maintenance mode..."
$DRUSH state:set system.maintenance_mode 0 -y

echo ""
echo "============================================"
echo "  Release complete."
echo ""
echo "  Check by eye:"
echo "    /                       Latest articles band: 4 filled cards per row"
echo "    /                       events: 1px blue rule, topic term last"
echo "    /articles/<any>         body text present, flush left under the title"
echo "    /node/<article>/edit    one Body field, no summary box"
echo "  Then: $DRUSH config:status     (expect: no differences)"
echo "============================================"
