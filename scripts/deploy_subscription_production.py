#!/usr/bin/env python3
"""Production deploy with backups for subscription billing module."""
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
BACKEND = "/var/www/dressnmore-production/backend"
TENANT = "/var/www/dressnmore-production/tenant"
ADMIN = "/var/www/dressnmore-production/admin"
BACKUP_ROOT = "/var/backups/dressnmore"
PATCHES = os.path.join(ROOT, "deploy", "subscription_patches")
TS = dt.datetime.utcnow().strftime("%Y%m%d_%H%M%S")
BACKUP_DIR = f"{BACKUP_ROOT}/{TS}_subscription_billing"

BACKEND_FILES = [
    "app/Services/Platform/SubscriptionDashboardStatsService.php",
    "app/Http/Controllers/Platform/DashboardController.php",
    "app/Services/Platform/TenantPlanChangeRequestService.php",
    "app/Services/Platform/TenantSubscriptionBillingService.php",
    "app/Services/Platform/TenantSubscriptionAdminService.php",
    "app/Services/Platform/TenantSubscriptionCancellationService.php",
    "app/Services/Platform/SubscriptionPaymentService.php",
    "app/Services/Platform/PlanRequestApprovalService.php",
    "app/Services/Platform/PlanRequestService.php",
    "app/Services/Platform/PaymentGatewayService.php",
    "app/Http/Controllers/Platform/PaymentController.php",
    "app/Http/Controllers/Platform/PaymentGatewayController.php",
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
    "database/seeders/Central/PaymentGatewaySeeder.php",
]


def run(client: paramiko.SSHClient, cmd: str, timeout: int = 300) -> str:
    _, o, e = client.exec_command(cmd, timeout=timeout)
    return (o.read() + e.read()).decode("utf-8", errors="replace")


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

    print("=== Pre-check ===")
    print(run(client, f"php -v | head -1; node -v; npm -v; df -h {BACKEND} | tail -1"))

    print("=== Backups ===")
    print(run(client, f"mkdir -p {BACKUP_DIR}"))
    print(run(client, f"cp -a {BACKEND}/.env {BACKUP_DIR}/backend.env"))
    print(run(client, f"cd {BACKEND} && php artisan migrate:status 2>&1 | tail -20"))
    print(run(client, f"cd {BACKEND} && php artisan db:show 2>&1 | head -5 || true"))
    db_dump = run(
        client,
        f"cd {BACKEND} && php -r \"require 'vendor/autoload.php'; "
        f"$app=require 'bootstrap/app.php'; $app->make('Illuminate\\\\Contracts\\\\Console\\\\Kernel')->bootstrap(); "
        f"echo config('database.connections.central.database');\" 2>/dev/null",
    ).strip()
    if db_dump:
        print(run(client, f"mysqldump central > {BACKUP_DIR}/central.sql 2>&1 || mysqldump {db_dump} > {BACKUP_DIR}/central.sql 2>&1"))
    print(run(client, f"test -d {ADMIN}/out && cp -a {ADMIN}/out {BACKUP_DIR}/admin_out || true"))
    print(run(client, f"test -d {TENANT}/dist && cp -a {TENANT}/dist {BACKUP_DIR}/tenant_dist || true"))
    print(f"BACKUP_DIR={BACKUP_DIR}")

    sftp = client.open_sftp()
    print("=== Backend upload ===")
    for rel in BACKEND_FILES:
        local = os.path.join(ROOT, rel.replace("/", os.sep))
        remote = f"{BACKEND}/{rel}"
        ensure_remote_dir(sftp, os.path.dirname(remote))
        sftp.put(local, remote)
        print(f"  uploaded {remote}")

    print("=== Frontend upload ===")
    upload_tree(sftp, os.path.join(PATCHES, "tenant", "src"), f"{TENANT}/src")
    upload_tree(sftp, os.path.join(PATCHES, "admin", "src"), f"{ADMIN}/src")
    sftp.close()

    print("=== Migrate & seed ===")
    print(run(client, f"cd {BACKEND} && php artisan migrate --force 2>&1"))
    print(run(client, f"cd {BACKEND} && php artisan db:seed --class=Database\\\\Seeders\\\\Central\\\\PlanSeeder --force 2>&1"))
    print(run(client, f"cd {BACKEND} && php artisan db:seed --class=Database\\\\Seeders\\\\Central\\\\PaymentGatewaySeeder --force 2>&1"))

    print("=== Optimize ===")
    print(run(client, f"cd {BACKEND} && php artisan optimize:clear && php artisan config:cache 2>&1"))

    for label, path in [("tenant", TENANT), ("admin", ADMIN)]:
        print(f"=== Build {label} ===")
        print(run(client, f"cd {path} && npm run build 2>&1", timeout=600))

    print("=== Smoke API (local curl) ===")
    print(run(client, "curl -sk -o /dev/null -w '%{http_code}' https://api.dressnmore.it.com/api/platform/health"))
    client.close()
    print("DEPLOY_SUBSCRIPTION_PRODUCTION_OK")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
