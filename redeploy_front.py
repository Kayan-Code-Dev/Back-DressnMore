import os
import subprocess
import paramiko

FRONT = os.path.join(os.path.dirname(os.path.abspath(__file__)), "..", "Front-DressnMore-cursor-clean-frontend-foundation-042e")
HOST = "159.198.74.223"


def run_local(cwd, *args):
    print(">>>", " ".join(args))
    r = subprocess.run(args, cwd=cwd, text=True, capture_output=True)
    print(r.stdout or r.stderr)
    return r.returncode


def run_remote(cmd):
    print(">>>", cmd[:150])
    ssh = paramiko.SSHClient()
    ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    ssh.connect(HOST, username="root", password="RBZ4cjZE184wOx37ip", timeout=30)
    _, stdout, stderr = ssh.exec_command(cmd, timeout=600)
    out = stdout.read().decode("utf-8", errors="replace")
    err = stderr.read().decode("utf-8", errors="replace")
    print(out[-3000:] if len(out) > 3000 else out)
    if err.strip():
        print("ERR:", err[-1000:])
    ssh.close()


run_local(FRONT, "git", "add", "src/features/auth/pages/login-page.tsx")
if run_local(FRONT, "git", "commit", "-m", "Fix syntax error in login page subscription redirect.") == 0:
    run_local(FRONT, "git", "push", "origin", "HEAD")

run_remote(
    "cd /var/www/tenant-dressnmore-new && git fetch origin && git reset --hard origin/feat/tenant-design-migration && npm run build"
)
print("Done")
