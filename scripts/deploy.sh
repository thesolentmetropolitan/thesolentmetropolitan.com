#!/usr/bin/env bash
#
# Deploy script for production.
# Imports config (enables modules, applies settings) then imports block content.
#
# Usage (on production server, from project root):
#   bash scripts/deploy.sh
#
# Prerequisites:
#   - Git pull already done (code + content_sync yml files are present)
#   - Composer install already done (single_content_sync module code is present)
#   - Drush is available in PATH
#
set -euo pipefail

echo "============================================"
echo "  Deploy: The Solent Metropolitan"
echo "============================================"
echo ""

# Step 1: Put site in maintenance mode
echo "==> Step 1: Enabling maintenance mode..."
drush state:set system.maintenance_mode 1 -y

# Step 2: Config import (enables single_content_sync module + all config changes)
echo "==> Step 2: Importing configuration..."
drush config:import -y

# Step 3: Clear caches after config import
echo "==> Step 3: Clearing caches..."
drush cr

# Step 4: Import block content
echo "==> Step 4: Importing block content..."
bash scripts/content-import.sh

# Step 5: Final cache rebuild
echo "==> Step 5: Final cache rebuild..."
drush cr

# Step 6: Disable maintenance mode
echo "==> Step 6: Disabling maintenance mode..."
drush state:set system.maintenance_mode 0 -y

echo ""
echo "============================================"
echo "  Deploy complete."
echo "============================================"
