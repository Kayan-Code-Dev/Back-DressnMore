import paramiko

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect("159.198.74.223", username="root", password="RBZ4cjZE184wOx37ip", timeout=30)

cmd = """cd /var/www/back-dressnmore-new && php artisan tinker --execute="
\\$t = \\App\\Models\\Central\\Tenant::find(7);
echo json_encode(app(\\App\\Support\\TenantSubscriptionPresenter::class)->forTenant(\\$t));
\""""
_, stdout, _ = ssh.exec_command(cmd, timeout=60)
print(stdout.read().decode())
ssh.close()
