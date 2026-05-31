#!/bin/bash
set -euo pipefail

LOG="/var/log/dressnmore-auto-sync.log"

sync_repo() {
  local dir="$1"
  local branch="$2"
  local deploy_script="$3"

  if [ ! -d "$dir/.git" ]; then
    echo "[$(date -u +%Y-%m-%dT%H:%M:%SZ)] skip missing repo: $dir"
    return 0
  fi

  cd "$dir"
  git fetch origin "$branch" --quiet

  local remote_sha local_sha
  remote_sha="$(git rev-parse "origin/$branch")"
  local_sha="$(git rev-parse HEAD)"

  if [ "$remote_sha" = "$local_sha" ]; then
    return 0
  fi

  echo "[$(date -u +%Y-%m-%dT%H:%M:%SZ)] update detected in $dir ($local_sha -> $remote_sha)"
  bash "$deploy_script" "$branch"
}

{
  echo "[$(date -u +%Y-%m-%dT%H:%M:%SZ)] auto-sync tick"
  sync_repo "/var/www/back-dressnmore-new" "main" "/opt/dressnmore/deploy/deploy-back.sh"
  sync_repo "/var/www/admin-dressnmore-new" "main" "/opt/dressnmore/deploy/deploy-admin.sh"
  sync_repo "/var/www/tenant-dressnmore-new" "feat/api-integration-phase-1" "/opt/dressnmore/deploy/deploy-front.sh"
} >> "$LOG" 2>&1
