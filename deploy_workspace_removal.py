import os
import subprocess
import paramiko

BASE = os.path.dirname(os.path.abspath(__file__))
ADMIN = os.path.join(os.path.dirname(BASE), "Admin-DressnMore")

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
    print(f"\n>>> {cmd}")
    ssh = paramiko.SSHClient()
    ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    ssh.connect(HOST, username=USER, password=PASSWORD, timeout=30)
    _, stdout, stderr = ssh.exec_command(cmd, timeout=900)
    out = stdout.read().decode("utf-8", errors="replace")
    err = stderr.read().decode("utf-8", errors="replace")
    code = stdout.channel.recv_exit_status()
    if out.strip():
        print(out.encode("ascii", errors="replace").decode("ascii")[:5000])
    if err.strip():
        print("ERR:", err.encode("ascii", errors="replace").decode("ascii")[:2000])
    ssh.close()
    return code


# Backend commit + push
run_local(BASE, "git", "add", "-A")
if run_local(
    BASE,
    "git",
    "commit",
    "-m",
    "Allow tenant login by email only without workspace field.",
) == 0:
    run_local(BASE, "git", "push", "origin", "main")

# Admin commit + push
run_local(ADMIN, "git", "add", "-A")
if run_local(
    ADMIN,
    "git",
    "commit",
    "-m",
    "Remove workspace slug field from tenant creation admin flow.",
) == 0:
    run_local(ADMIN, "git", "push", "origin", "main")

# Server deploy
cmds = [
    "cd /var/www/back-dressnmore-new && git fetch origin && git reset --hard origin/main && php artisan migrate --force && php artisan tenant:sync-user-directory && php artisan route:clear && php artisan config:clear && php artisan cache:clear",
    "cd /var/www/admin-dressnmore-new && git fetch origin && git reset --hard origin/main && npm ci && npm run build",
]
for cmd in cmds:
    code = run_remote(cmd)
    if code != 0:
        print(f"WARNING: exit {code}")

print("\nDone")
