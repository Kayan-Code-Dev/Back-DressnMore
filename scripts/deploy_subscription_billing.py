#!/usr/bin/env python3
"""Deploy subscription billing lifecycle patches to production."""
from __future__ import annotations

import os
import sys

import paramiko

sys.stdout.reconfigure(encoding="utf-8", errors="replace")

HOST = "159.198.74.223"
USER = "root"
PWD = "RBZ4cjZE184wOx37ip"
ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
BACKEND = "/var/www/dressnmore-production/backend"
TENANT = "/var/www/dressnmore-production/tenant"
ADMIN = "/var/www/dressnmore-production/admin"
PATCHES = os.path.join(ROOT, "deploy", "subscription_patches")

BACKEND_GLOBS = [
    "app/Services/Platform/TenantPlanChangeRequestService.php",
    "app/Services/Platform/TenantSubscriptionBillingService.php",
    "app/Services/Platform/TenantSubscriptionAdminService.php",
    "app/Services/Platform/TenantSubscriptionCancellationService.php",
    "app/Services/Platform/SubscriptionPaymentService.php",
    "app/Services/Platform/PlanRequestApprovalService.php",
    "app/Http/Controllers/Platform/PaymentController.php",
    "app/Http/Controllers/Platform/SubscriptionController.php",
    "app/Http/Controllers/Tenant/SubscriptionController.php",
    "app/Http/Resources/Platform/SubscriptionResource.php",
    "app/Http/Resources/Platform/PlanRequestResource.php",
    "app/Models/Central/Tenant.php",
    "app/Models/Central/PlanRequest.php",
    "app/Models/Central/Payment.php",
    "app/Support/TenantSubscriptionPresenter.php",
    "routes/api/platform.php",
    "routes/api/tenant.php",
    "database/migrations/2026_06_18_100000_extend_subscription_billing_tables.php",
    "database/seeders/Central/PlanSeeder.php",
]


def ensure_remote_dir(sftp: paramiko.SFTPClient, remote_dir: str) -> None:
    parts = remote_dir.strip("/").split("/")
    cur = ""
    for part in parts:
        cur += f"/{part}"
        try:
            sftp.stat(cur)
        except OSError:
            sftp.mkdir(cur)


def upload_tree(sftp: paramiko.SFTPClient, local_root: str, remote_root: str) -> None:
    for dirpath, _, filenames in os.walk(local_root):
        rel = os.path.relpath(dirpath, local_root)
        remote_dir = remote_root if rel == "." else f"{remote_root}/{rel.replace(os.sep, '/')}"
        ensure_remote_dir(sftp, remote_dir)
        for fname in filenames:
            sftp.put(os.path.join(dirpath, fname), f"{remote_dir}/{fname}")
            print(f"  uploaded {remote_dir}/{fname}")


def main() -> int:
    client = paramiko.SSHClient()
    client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    client.connect(HOST, username=USER, password=PWD, timeout=30)
    sftp = client.open_sftp()

    print("=== Backend ===")
    for rel in BACKEND_GLOBS:
        local = os.path.join(ROOT, rel.replace("/", os.sep))
        remote = f"{BACKEND}/{rel}"
        ensure_remote_dir(sftp, os.path.dirname(remote))
        sftp.put(local, remote)
        print(f"  uploaded {remote}")

    print("=== Tenant frontend ===")
    upload_tree(sftp, os.path.join(PATCHES, "tenant", "src"), f"{TENANT}/src")
    print("=== Admin frontend ===")
    upload_tree(sftp, os.path.join(PATCHES, "admin", "src"), f"{ADMIN}/src")
    sftp.close()

    print("=== Migrate ===")
    _, o, e = client.exec_command(f"cd {BACKEND} && php artisan migrate --force 2>&1", timeout=120)
    print((o.read() + e.read()).decode("utf-8", errors="replace"))

    print("=== Optimize ===")
    _, o, e = client.exec_command(f"cd {BACKEND} && php artisan optimize:clear 2>&1")
    print((o.read() + e.read()).decode("utf-8", errors="replace"))

    for label, path in [("tenant", TENANT), ("admin", ADMIN)]:
        print(f"=== Build {label} ===")
        _, o, e = client.exec_command(f"cd {path} && npm run build 2>&1", timeout=600)
        out = (o.read() + e.read()).decode("utf-8", errors="replace")
        for line in out.splitlines()[-12:]:
            print(line)

    client.close()
    print("DEPLOY_SUBSCRIPTION_BILLING_OK")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
