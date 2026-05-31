#!/bin/bash
set -euo pipefail

APP_DIR="/var/www/tenant-dressnmore-new"
BRANCH="${1:-feat/tenant-design-migration}"
LOG="/var/log/dressnmore-deploy-front.log"

exec > >(tee -a "$LOG") 2>&1
echo "=== Tenant front deploy started at $(date -u +%Y-%m-%dT%H:%M:%SZ) branch=$BRANCH ==="

cd "$APP_DIR"
git fetch origin
git reset --hard "origin/$BRANCH"

npm ci
npm run build

echo "=== Tenant front deploy finished: $(git log -1 --oneline) ==="
