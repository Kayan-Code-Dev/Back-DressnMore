import paramiko

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect("159.198.74.223", username="root", password="RBZ4cjZE184wOx37ip", timeout=30)

cmd = "grep -o 'subscription?.lifecycle_status' /var/www/tenant-dressnmore-new/dist/assets/*.js 2>/dev/null | head -3"
_, stdout, _ = ssh.exec_command(cmd, timeout=60)
print("optional chaining:", stdout.read().decode() or "NOT FOUND - rebuilding needed")

# Rebuild if needed
cmd2 = "cd /var/www/tenant-dressnmore-new && npm run build 2>&1 | tail -5"
_, stdout, _ = ssh.exec_command(cmd2, timeout=300)
print("build:", stdout.read().decode())

ssh.close()
