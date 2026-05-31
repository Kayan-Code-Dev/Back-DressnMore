import paramiko

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect("159.198.74.223", username="root", password="RBZ4cjZE184wOx37ip", timeout=30)

for f in ["staging-admin.conf", "staging-api.conf", "staging-tenant.conf"]:
    print(f"=== {f} ===")
    _, stdout, _ = ssh.exec_command(f"cat /etc/nginx/conf.d/{f}", timeout=30)
    print(stdout.read().decode())

_, stdout, _ = ssh.exec_command("cat /var/www/admin-dressnmore-new/.env", timeout=30)
print("=== admin .env ===")
print(stdout.read().decode())

ssh.close()
