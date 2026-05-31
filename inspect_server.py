import paramiko

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect("159.198.74.223", username="root", password="RBZ4cjZE184wOx37ip", timeout=30)

cmds = [
    "echo '=== BACK ===' && cd /var/www/back-dressnmore-new && git remote -v && git branch && git log -1 --oneline",
    "echo '=== ADMIN ===' && cd /var/www/admin-dressnmore-new && git remote -v && git branch && git log -1 --oneline",
    "echo '=== FRONT ===' && cd /var/www/tenant-dressnmore-new && git remote -v && git branch && git log -1 --oneline",
    "node -v && npm -v && php -v | head -1",
    "ls -la /root/.ssh/",
    "cat /etc/nginx/sites-enabled/* 2>/dev/null | grep -E 'server_name|root ' | head -30",
]

for cmd in cmds:
    print(">>>", cmd)
    _, stdout, stderr = ssh.exec_command(cmd, timeout=60)
    out = stdout.read().decode("utf-8", errors="replace")
    err = stderr.read().decode("utf-8", errors="replace")
    print(out)
    if err.strip():
        print("ERR:", err[:500])

ssh.close()
