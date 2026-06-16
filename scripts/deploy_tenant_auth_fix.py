#!/usr/bin/env python3
"""Deploy tenant auth fix + admin i18n labels to production."""
from __future__ import annotations

import datetime as dt
import os
import sys

import paramiko

sys.stdout.reconfigure(encoding="utf-8", errors="replace")

HOST = "159.198.74.223"
USER = "root"
PWD = "RBZ4cjZE184wOx37ip"
ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
TENANT = "/var/www/dressnmore-production/tenant"
ADMIN = "/var/www/dressnmore-production/admin"
BACKEND = "/var/www/dressnmore-production/backend"
TS = dt.datetime.utcnow().strftime("%Y%m%d_%H%M%S")
BACKUP = f"/var/backups/dressnmore/{TS}_tenant_auth_fix"

TENANT_FILES = [
    ("deploy/tenant_auth_fix/src/shared/lib/tenant/tenant-context.ts", "src/shared/lib/tenant/tenant-context.ts"),
    ("deploy/tenant_auth_fix/src/shared/lib/auth/session.store.ts", "src/shared/lib/auth/session.store.ts"),
]
ADMIN_FILES = [
    ("deploy/subscription_patches/admin/src/components/base/StatsCard.tsx", "src/components/base/StatsCard.tsx"),
    ("deploy/subscription_patches/admin/src/pages/dashboard/components/SubscriptionStatsRow.tsx", "src/pages/dashboard/components/SubscriptionStatsRow.tsx"),
]


def run(client: paramiko.SSHClient, cmd: str, timeout: int = 600) -> str:
    _, o, e = client.exec_command(cmd, timeout=timeout)
    return (o.read() + e.read()).decode("utf-8", errors="replace")


def main() -> int:
    client = paramiko.SSHClient()
    client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    client.connect(HOST, username=USER, password=PWD, timeout=30)

    run(client, f"mkdir -p {BACKUP}/tenant/dist {BACKUP}/admin/out")
    run(client, f"cp -a {TENANT}/dist {BACKUP}/tenant/ 2>/dev/null || true")
    run(client, f"cp -a {ADMIN}/out {BACKUP}/admin/ 2>/dev/null || true")
    print(f"BACKUP_DIR={BACKUP}")

    sftp = client.open_sftp()
    for local_rel, remote_rel in TENANT_FILES + ADMIN_FILES:
        local = os.path.join(ROOT, local_rel.replace("/", os.sep))
        remote = f"{TENANT if 'tenant_auth_fix' in local_rel else ADMIN}/{remote_rel}"
        sftp.put(local, remote)
        print(f"uploaded {remote_rel}")
    sftp.close()

    print("=== build tenant ===")
    print(run(client, f"cd {TENANT} && npm run build 2>&1", timeout=600)[-2000:])
    print("=== build admin ===")
    print(run(client, f"cd {ADMIN} && npm run build 2>&1", timeout=600)[-2000:])

    # uncancel test tenant for browser QA
    run(
        client,
        f"cd {BACKEND} && php artisan tinker --execute=\""
        f"\\\\App\\\\Models\\\\Central\\\\Tenant::where('slug','alhatom')->update(['cancelled_at'=>null,'cancellation_reason'=>null]);\"",
    )
    print("DEPLOY_TENANT_AUTH_FIX_OK")
    client.close()
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
