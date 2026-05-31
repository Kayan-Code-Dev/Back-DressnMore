import os
import subprocess
import paramiko

BASE = os.path.dirname(os.path.abspath(__file__))
FRONT = os.path.join(os.path.dirname(BASE), "Front-DressnMore-cursor-clean-frontend-foundation-042e")

HOST = "159.198.74.223"
USER = "root"
PASSWORD = "RBZ4cjZE184wOx37ip"


def run_local(cwd: str, *args: str) -> int:
    print(f"\n>>> {' '.join(args)}")
    r = subprocess.run(args, cwd=cwd, text=True, capture_output=True)
    if r.stdout:
        print(r.stdout)
    if r.stderr:
        print(r.stderr)
    return r.returncode


def run_remote(cmd: str) -> int:
    print(f"\n>>> {cmd[:200]}")
    ssh = paramiko.SSHClient()
    ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    ssh.connect(HOST, username=USER, password=PASSWORD, timeout=30)
    _, stdout, stderr = ssh.exec_command(cmd, timeout=900)
    out = stdout.read().decode("utf-8", errors="replace")
    err = stderr.read().decode("utf-8", errors="replace")
    code = stdout.channel.recv_exit_status()
    if out.strip():
        print(out.encode("ascii", errors="replace").decode("ascii")[:6000])
    if err.strip():
        print("ERR:", err.encode("ascii", errors="replace").decode("ascii")[:2000])
    ssh.close()
    return code


run_local(BASE, "git", "add", "-A")
if run_local(
    BASE,
    "git",
    "commit",
    "-m",
    "Return subscription object on tenant login and me endpoints.",
) == 0:
    run_local(BASE, "git", "push", "origin", "main")

if os.path.isdir(FRONT):
    run_local(FRONT, "git", "add", "-A")
    if run_local(
        FRONT,
        "git",
        "commit",
        "-m",
        "Handle missing subscription field safely on tenant login.",
    ) == 0:
        run_local(FRONT, "git", "push", "origin", "HEAD")

run_remote(
    "cd /var/www/back-dressnmore-new && git fetch origin && git reset --hard origin/main "
    "&& php artisan route:clear && php artisan config:clear && php artisan cache:clear"
)

if os.path.isdir(FRONT):
    run_remote(
        "cd /var/www/tenant-dressnmore-new && git fetch origin && git pull && npm ci && npm run build"
    )

# Test login response shape
payload = '{"email":"yuosefalhatom123@gmail.com","password":"test"}'
run_remote(
    f"curl -s -X POST https://staging-api.dressnmore.it.com/api/tenant/login "
    f"-H 'Content-Type: application/json' -d '{payload}' | python3 -m json.tool 2>/dev/null | head -40"
)

print("\nDone")
