#!/usr/bin/env bash
#
# Export block content using single_content_sync.
# Run from the project root (where composer.json lives).
#
# Usage (local dev):
#   ddev exec bash scripts/content-export.sh
#
# Usage (production, where drush is in PATH):
#   bash scripts/content-export.sh
#
set -euo pipefail

EXPORT_DIR="content_sync/blocks"

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
mkdir -p "$EXPORT_DIR"

echo "==> Exporting block content..."

# Export all block_content entities to a temp dir
# Note: drush content:export path is relative to the Drupal docroot (web/), so use ../
TEMP_EXPORT="$EXPORT_DIR/.tmp_export"
mkdir -p "$TEMP_EXPORT"
drush content:export block_content "../$TEMP_EXPORT" -y

# Find the exported zip (check both possible locations)
ZIP_FILE=$(find "$TEMP_EXPORT" -name "*.zip" -type f 2>/dev/null | head -1)

if [ -z "$ZIP_FILE" ]; then
  echo "ERROR: No export zip file found"
  rm -rf "$TEMP_EXPORT"
  exit 1
fi

# Clear existing yml files and extract new ones
rm -f "$EXPORT_DIR"/*.yml
unzip -o "$ZIP_FILE" -d "$EXPORT_DIR"

# Clean up
rm -rf "$TEMP_EXPORT"

echo ""
echo "==> Exported block content to $EXPORT_DIR/:"
ls -1 "$EXPORT_DIR"/*.yml
echo ""
echo "==> Done. Commit the yml files to git."
