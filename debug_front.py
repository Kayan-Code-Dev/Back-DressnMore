import paramiko

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect("159.198.74.223", username="root", password="RBZ4cjZE184wOx37ip", timeout=30)

cmds = [
    "cd /var/www/tenant-dressnmore-new && git log -1 --oneline",
    "ls -la /var/www/tenant-dressnmore-new/dist/index.html",
    "grep -o 'subscription' /var/www/tenant-dressnmore-new/dist/assets/*.js 2>/dev/null | head -3",
    "grep -o 'lifecycle_status' /var/www/tenant-dressnmore-new/dist/assets/*.js 2>/dev/null | head -3",
]

for cmd in cmds:
    print(f">>> {cmd}")
    _, stdout, _ = ssh.exec_command(cmd, timeout=60)
    print(stdout.read().decode())

ssh.close()
