import json
import paramiko

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect("159.198.74.223", username="root", password="RBZ4cjZE184wOx37ip", timeout=30)

payload = json.dumps({"email": "admin@temp-foundation.local", "password": "DressMore@2026"})
cmd = (
    "curl -s -X POST https://staging-api.dressnmore.it.com/api/tenant/login "
    f"-H 'Content-Type: application/json' -d '{payload}'"
)
_, stdout, _ = ssh.exec_command(cmd, timeout=60)
print(stdout.read().decode())
ssh.close()
