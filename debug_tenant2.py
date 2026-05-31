import json
import paramiko

HOST = "159.198.74.223"
USER = "root"
PASSWORD = "RBZ4cjZE184wOx37ip"

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect(HOST, username=USER, password=PASSWORD, timeout=30)

cmds = [
    "grep -r 'VITE_API' /var/www/tenant-dressnmore-new/.env* 2>/dev/null; ls -la /var/www/tenant-dressnmore-new/",
    "cd /var/www/tenant-dressnmore-new && git log -1 --oneline 2>/dev/null || echo 'no git'",
    "grep -o 'https://[^\"]*api[^\"]*' /var/www/tenant-dressnmore-new/dist/assets/*.js 2>/dev/null | head -5 || grep -o 'localhost:3000' /var/www/tenant-dressnmore-new/dist/assets/*.js 2>/dev/null | head -3",
    "grep -i 'login\\|tenant\\|credentials\\|subscription' /var/www/back-dressnmore-new/storage/logs/laravel.log 2>/dev/null | tail -30",
    "cd /var/www/back-dressnmore-new && php artisan tinker --execute=\""
    "$t=\\App\\Models\\Central\\Tenant::find(7); "
    "\\App\\Services\\Tenant\\TenantDatabaseManager::class; "
    "app(\\App\\Services\\Tenant\\TenantDatabaseManager::class)->connect($t); "
    "echo json_encode(\\App\\Models\\Tenant\\User::where('email','yuosefalhatom123@gmail.com')->first(['id','email','status']));\"",
]

for cmd in cmds:
    print(f"\n>>> {cmd[:120]}...")
    _, stdout, stderr = ssh.exec_command(cmd, timeout=120)
    out = stdout.read().decode("utf-8", errors="replace")
    err = stderr.read().decode("utf-8", errors="replace")
    print(out[:4000])
    if err.strip():
        print("ERR:", err[:1000])

ssh.close()
