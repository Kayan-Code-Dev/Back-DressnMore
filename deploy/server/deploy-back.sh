#!/bin/bash
set -euo pipefail

APP_DIR="/var/www/back-dressnmore-new"
BRANCH="${1:-main}"
LOG="/var/log/dressnmore-deploy-back.log"

exec > >(tee -a "$LOG") 2>&1
echo "=== Backend deploy started at $(date -u +%Y-%m-%dT%H:%M:%SZ) branch=$BRANCH ==="

cd "$APP_DIR"
git fetch origin
git reset --hard "origin/$BRANCH"

if [ -f composer.json ]; then
  composer install --no-dev --optimize-autoloader --no-interaction
fi

php artisan migrate --force
php artisan config:clear
php artisan route:clear
php artisan cache:clear
php artisan view:clear

echo "=== Backend deploy finished: $(git log -1 --oneline) ==="
