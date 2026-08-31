#!/usr/bin/env bash
#
# Import block content using single_content_sync.
# Run from the project root (where composer.json lives).
#
# Usage (local dev):
#   ddev exec bash scripts/content-import.sh
#
# Usage (production, where drush is in PATH):
#   bash scripts/content-import.sh
#
set -euo pipefail

IMPORT_DIR="content_sync/blocks"

# Detect environment. Only /.dockerenv reliably identifies the ddev
# container — a bare "[ -d /var/www/html ]" check misfires on servers
# that have a stock Apache docroot lying around (as on prod, where it
# sent the script to /var/www/html and "No yml files found").
if [ -f /.dockerenv ]; then
  DOCROOT="/var/www/html"
else
  # Resolve the project root from this script's own location so the
  # script works from any cwd (running it from inside scripts/ used to
  # fail with "No yml files found").
  DOCROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
fi

cd "$DOCROOT"

shopt -s nullglob
YML_FILES=("$IMPORT_DIR"/*.yml)
shopt -u nullglob

if [ ${#YML_FILES[@]} -eq 0 ]; then
  echo "ERROR: No yml files found in $IMPORT_DIR/"
  exit 1
fi

echo "==> Importing ${#YML_FILES[@]} block content file(s)..."
echo ""

for yml in "${YML_FILES[@]}"; do
  FILENAME=$(basename "$yml")
  echo "  Importing: $FILENAME"
  # Note: drush content:import path is relative to the Drupal docroot (web/), so use ../
  drush content:import "../$IMPORT_DIR/$FILENAME" -y
done

echo ""
echo "==> Clearing caches..."
drush cr

echo "==> Done. All block content imported."
