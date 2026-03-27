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

# Resolve project root from the script's own location
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"

cd "$PROJECT_ROOT"
echo "==> Working directory: $(pwd)"

shopt -s nullglob
YML_FILES=("$IMPORT_DIR"/*.yml)
shopt -u nullglob

if [ ${#YML_FILES[@]} -eq 0 ]; then
  echo "ERROR: No yml files found in $IMPORT_DIR/"
  echo "       Looking in: $(pwd)/$IMPORT_DIR/"
  ls -la "$IMPORT_DIR/" 2>/dev/null || echo "       Directory does not exist."
  exit 1
fi

echo "==> Importing ${#YML_FILES[@]} block content file(s)..."
echo ""

for yml in "${YML_FILES[@]}"; do
  FILENAME=$(basename "$yml")
  echo "  Importing: $FILENAME"
  # Note: drush content:import path is relative to the Drupal docroot (web/), so use ../
  ./drush-dir/drush content:import "../$IMPORT_DIR/$FILENAME" -y
done

echo ""
echo "==> Clearing caches..."
./drush-dir/drush cr

echo "==> Done. All block content imported."
