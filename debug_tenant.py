import json
import paramiko

HOST = "159.198.74.223"
USER = "root"
PASSWORD = "RBZ4cjZE184wOx37ip"


def run(cmd):
    ssh = paramiko.SSHClient()
    ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    ssh.connect(HOST, username=USER, password=PASSWORD, timeout=30)
    _, stdout, stderr = ssh.exec_command(cmd, timeout=120)
    out = stdout.read().decode("utf-8", errors="replace")
    err = stderr.read().decode("utf-8", errors="replace")
    code = stdout.channel.recv_exit_status()
    ssh.close()
    return code, out, err


print("=== Recent tenants ===")
_, out, _ = run(
    "cd /var/www/back-dressnmore-new && php artisan tinker --execute=\""
    "echo json_encode(\\App\\Models\\Central\\Tenant::orderByDesc('id')->take(5)->get(['id','name','slug','status','metadata'])->toArray());\""
)
print(out)

print("=== tenant_user_directory (latest) ===")
_, out, _ = run(
    "cd /var/www/back-dressnmore-new && php artisan tinker --execute=\""
    "echo json_encode(\\App\\Models\\Central\\TenantUserDirectory::orderByDesc('id')->take(10)->get()->toArray());\""
)
print(out)

print("=== Laravel log (last 80 lines) ===")
_, out, _ = run("tail -80 /var/www/back-dressnmore-new/storage/logs/laravel.log 2>/dev/null || echo 'no log'")
print(out[-8000:] if len(out) > 8000 else out)
