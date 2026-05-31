#!/bin/bash
set -euo pipefail

APP_DIR="/var/www/admin-dressnmore-new"
BRANCH="${1:-main}"
LOG="/var/log/dressnmore-deploy-admin.log"

exec > >(tee -a "$LOG") 2>&1
echo "=== Admin deploy started at $(date -u +%Y-%m-%dT%H:%M:%SZ) branch=$BRANCH ==="

cd "$APP_DIR"
git fetch origin
git reset --hard "origin/$BRANCH"

npm ci
npm run build

echo "=== Admin deploy finished: $(git log -1 --oneline) ==="
