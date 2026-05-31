import paramiko

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect("159.198.74.223", username="root", password="RBZ4cjZE184wOx37ip", timeout=30)

cmds = [
    "cat /root/.ssh/authorized_keys",
    "cat /root/.ssh/github_actions_admin.pub",
    "cat /root/.ssh/github_deploy_key.pub",
    "ls -la /etc/nginx/conf.d/ 2>/dev/null; ls -la /etc/nginx/sites-available/ 2>/dev/null",
    "grep -r 'dressnmore' /etc/nginx/ 2>/dev/null | head -40",
    "test -f /var/www/back-dressnmore-new/.env && echo 'back .env exists' || echo 'no back env'",
    "test -f /var/www/admin-dressnmore-new/.env.production && cat /var/www/admin-dressnmore-new/.env.production 2>/dev/null || ls /var/www/admin-dressnmore-new/.env* 2>/dev/null",
    "cat /var/www/tenant-dressnmore-new/.env.production 2>/dev/null",
]

for cmd in cmds:
    print(">>>", cmd[:80])
    _, stdout, _ = ssh.exec_command(cmd, timeout=60)
    print(stdout.read().decode("utf-8", errors="replace")[:3000])
    print()

ssh.close()
