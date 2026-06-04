"""
Upload deploy scripts to server and configure GitHub Actions secrets.

Requires GITHUB_TOKEN env var with repo scope for:
  Kayan-Code-Dev/Back-DressnMore
  Kayan-Code-Dev/Admin-DressnMore
  Kayan-Code-Dev/Front-DressnMore
"""

from __future__ import annotations

import base64
import os
import subprocess
import sys
from pathlib import Path

import paramiko

try:
    from nacl import encoding, public
except ImportError:
    public = None

HOST = os.environ.get("DRESSNMORE_VPS_HOST", "").strip()
USER = os.environ.get("DRESSNMORE_VPS_USER", "root").strip()
PASSWORD = os.environ.get("DRESSNMORE_VPS_PASSWORD", "").strip()
OWNER = "Kayan-Code-Dev"
REPOS = ["Back-DressnMore", "Admin-DressnMore", "Front-DressnMore"]
BASE = Path(__file__).resolve().parent
SCRIPTS_DIR = BASE / "deploy" / "server"


def connect_ssh() -> paramiko.SSHClient:
    if HOST == "" or USER == "" or PASSWORD == "":
        raise RuntimeError("DRESSNMORE_VPS_HOST, DRESSNMORE_VPS_USER, and DRESSNMORE_VPS_PASSWORD are required.")

    ssh = paramiko.SSHClient()
    ssh.load_system_host_keys()
    ssh.set_missing_host_key_policy(paramiko.RejectPolicy())
    ssh.connect(HOST, username=USER, password=PASSWORD, timeout=30)

    return ssh


def upload_deploy_scripts() -> None:
    ssh = connect_ssh()
    sftp = ssh.open_sftp()

    ssh.exec_command("mkdir -p /opt/dressnmore/deploy")
    for script in ["deploy-back.sh", "deploy-admin.sh", "deploy-front.sh"]:
        local = SCRIPTS_DIR / script
        remote = f"/opt/dressnmore/deploy/{script}"
        sftp.put(str(local), remote)
        ssh.exec_command(f"chmod +x {remote}")

    sftp.close()
    ssh.close()
    print("Deploy scripts uploaded to /opt/dressnmore/deploy/")


def read_ssh_private_key() -> str:
    ssh = connect_ssh()
    _, stdout, _ = ssh.exec_command("cat /root/.ssh/github_actions_admin", timeout=30)
    key = stdout.read().decode("utf-8").strip()
    ssh.close()
    if not key.startswith("-----BEGIN"):
        raise RuntimeError("Could not read github_actions_admin private key from server")
    return key + "\n"


def encrypt_secret(public_key: str, secret_value: str) -> str:
    if public is None:
        raise RuntimeError("PyNaCl is required: pip install pynacl")
    pk = public.PublicKey(public_key.encode("utf-8"), encoding.Base64Encoder())
    sealed = public.SealedBox(pk).encrypt(secret_value.encode("utf-8"))
    return base64.b64encode(sealed).decode("utf-8")


def set_repo_secret(token: str, repo: str, name: str, value: str) -> None:
    import json
    import urllib.error
    import urllib.request

    headers = {
        "Authorization": f"Bearer {token}",
        "Accept": "application/vnd.github+json",
        "X-GitHub-Api-Version": "2022-11-28",
    }

    pk_url = f"https://api.github.com/repos/{OWNER}/{repo}/actions/secrets/public-key"
    req = urllib.request.Request(pk_url, headers=headers)
    with urllib.request.urlopen(req) as resp:
        pk_data = json.loads(resp.read().decode())

    encrypted = encrypt_secret(pk_data["key"], value)
    body = json.dumps({"encrypted_value": encrypted, "key_id": pk_data["key_id"]}).encode()
    put_url = f"https://api.github.com/repos/{OWNER}/{repo}/actions/secrets/{name}"
    put_req = urllib.request.Request(put_url, data=body, headers=headers, method="PUT")
    try:
        with urllib.request.urlopen(put_req) as resp:
            resp.read()
        print(f"  [{repo}] secret {name} set")
    except urllib.error.HTTPError as exc:
        detail = exc.read().decode("utf-8", errors="replace")
        raise RuntimeError(f"Failed to set {name} on {repo}: {exc.code} {detail}") from exc


def configure_github_secrets(token: str, ssh_key: str) -> None:
    secrets = {
        "VPS_HOST": HOST,
        "VPS_USERNAME": USER,
        "VPS_PORT": "22",
        "VPS_SSH_KEY": ssh_key,
    }
    for repo in REPOS:
        print(f"Configuring secrets for {repo}...")
        for name, value in secrets.items():
            set_repo_secret(token, repo, name, value)


def git_push_all() -> None:
    paths = [
        (BASE, "Add GitHub Actions staging auto-deploy workflow."),
        (BASE.parent / "Admin-DressnMore", "Switch admin deploy workflow to staging server git pull."),
        (BASE.parent / "Front-DressnMore-cursor-clean-frontend-foundation-042e", "Add GitHub Actions staging auto-deploy workflow."),
    ]
    for cwd, msg in paths:
        if not cwd.exists():
            print(f"Skip missing: {cwd}")
            continue
        print(f"\n>>> git push {cwd.name}")
        subprocess.run(["git", "add", "-A"], cwd=str(cwd), check=False)
        code = subprocess.run(["git", "commit", "-m", msg], cwd=str(cwd)).returncode
        if code == 0:
            subprocess.run(["git", "push", "origin", "HEAD"], cwd=str(cwd), check=True)


def main() -> None:
    upload_deploy_scripts()
    ssh_key = read_ssh_private_key()

    token = os.environ.get("GITHUB_TOKEN", "").strip()
    if token:
        configure_github_secrets(token, ssh_key)
    else:
        print("\nGITHUB_TOKEN not set — skipping secret configuration.")
        print("Add these secrets manually to each repo (Back, Admin, Front):")
        print(f"  VPS_HOST={HOST}")
        print(f"  VPS_USERNAME={USER}")
        print("  VPS_PORT=22")
        print("  VPS_SSH_KEY=<contents of /root/.ssh/github_actions_admin>")

    git_push_all()
    print("\nDone.")


if __name__ == "__main__":
    main()
