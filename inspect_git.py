import paramiko

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect("159.198.74.223", username="root", password="RBZ4cjZE184wOx37ip", timeout=30)

cmds = [
    "cd /var/www/back-dressnmore-new && git fetch origin 2>&1 | tail -3",
    "cd /var/www/admin-dressnmore-new && git fetch origin 2>&1 | tail -3",
    "cd /var/www/tenant-dressnmore-new && git fetch origin 2>&1 | tail -3",
    "which composer; cd /var/www/back-dressnmore-new && composer --version 2>/dev/null | head -1",
    "cat /root/.ssh/config 2>/dev/null",
]

for cmd in cmds:
    print(">>>", cmd)
    _, stdout, stderr = ssh.exec_command(cmd, timeout=90)
    print(stdout.read().decode())
    print(stderr.read().decode()[:500])
    print()

ssh.close()
