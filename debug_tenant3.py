import paramiko

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect("159.198.74.223", username="root", password="RBZ4cjZE184wOx37ip", timeout=30)

cmds = [
    "cd /var/www/back-dressnmore-new && php artisan tinker --execute=\"echo json_encode(\\\\App\\\\Models\\\\Central\\\\Tenant::all(['id','name','slug','status'])->toArray());\"",
    "cd /var/www/back-dressnmore-new && php artisan tinker --execute=\"echo json_encode(\\\\App\\\\Models\\\\Central\\\\TenantUserDirectory::all()->toArray());\"",
]

for cmd in cmds:
    _, stdout, stderr = ssh.exec_command(cmd, timeout=60)
    print(stdout.read().decode())
    err = stderr.read().decode()
    if err.strip():
        print("ERR:", err[:500])

ssh.close()
