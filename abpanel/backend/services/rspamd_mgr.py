"""Rspamd spam filter management."""

import asyncio
import json
from pathlib import Path

RSPAMD_CONF_DIR = Path("/etc/rspamd")
RSPAMD_LOCAL_DIR = RSPAMD_CONF_DIR / "local.d"


async def get_status() -> dict:
    """Check Rspamd status."""
    try:
        proc = await asyncio.create_subprocess_exec(
            "systemctl", "is-active", "rspamd",
            stdout=asyncio.subprocess.PIPE,
        )
        stdout, _ = await proc.communicate()
        status = stdout.decode().strip()

        installed = RSPAMD_CONF_DIR.exists()

        return {
            "installed": installed,
            "running": status == "active",
            "status": status,
        }
    except Exception:
        return {"installed": False, "running": False, "status": "unknown"}


async def install_rspamd() -> dict:
    """Install Rspamd."""
    try:
        commands = [
            "apt-get install -y -qq lsb-release wget gnupg",
            "wget -qO- https://rspamd.com/apt-stable/gpg.key | apt-key add -",
            'echo "deb http://rspamd.com/apt-stable/ $(lsb_release -cs) main" > /etc/apt/sources.list.d/rspamd.list',
            "apt-get update -qq",
            "apt-get install -y -qq rspamd redis-server",
        ]

        for cmd in commands:
            proc = await asyncio.create_subprocess_shell(
                cmd, stdout=asyncio.subprocess.PIPE, stderr=asyncio.subprocess.PIPE,
            )
            _, stderr = await proc.communicate()

        # Configure Rspamd with Redis
        RSPAMD_LOCAL_DIR.mkdir(parents=True, exist_ok=True)

        # Redis backend
        (RSPAMD_LOCAL_DIR / "redis.conf").write_text(
            'servers = "127.0.0.1";\n'
        )

        # Classifier (spam learning)
        (RSPAMD_LOCAL_DIR / "classifier-bayes.conf").write_text(
            'backend = "redis";\nautolearn = true;\n'
        )

        # Enable milter for Postfix
        (RSPAMD_LOCAL_DIR / "milter_headers.conf").write_text(
            'extended_spam_headers = true;\n'
            'use = ["x-spamd-bar", "x-spam-level", "authentication-results"];\n'
        )

        # DKIM signing
        (RSPAMD_LOCAL_DIR / "dkim_signing.conf").write_text(
            "allow_username_mismatch = true;\n"
            'path = "/var/lib/rspamd/dkim/$domain.$selector.key";\n'
            'selector = "dkim";\n'
        )

        # Start services
        await asyncio.create_subprocess_exec("systemctl", "enable", "rspamd")
        await asyncio.create_subprocess_exec("systemctl", "start", "rspamd")
        await asyncio.create_subprocess_exec("systemctl", "enable", "redis-server")
        await asyncio.create_subprocess_exec("systemctl", "start", "redis-server")

        return {"success": True, "message": "Rspamd installed and configured"}

    except Exception as e:
        return {"success": False, "error": str(e)}


async def get_stats() -> dict:
    """Get Rspamd statistics via API."""
    try:
        proc = await asyncio.create_subprocess_exec(
            "curl", "-s", "http://localhost:11334/stat",
            stdout=asyncio.subprocess.PIPE,
            stderr=asyncio.subprocess.PIPE,
        )
        stdout, _ = await proc.communicate()
        if proc.returncode == 0:
            return {"success": True, "stats": json.loads(stdout.decode())}
        return {"success": False, "error": "Cannot reach Rspamd API"}
    except Exception as e:
        return {"success": False, "error": str(e)}


async def learn_spam(message_path: str) -> dict:
    """Train Rspamd to recognize spam."""
    try:
        proc = await asyncio.create_subprocess_exec(
            "rspamc", "learn_spam", message_path,
            stdout=asyncio.subprocess.PIPE,
            stderr=asyncio.subprocess.PIPE,
        )
        stdout, _ = await proc.communicate()
        return {"success": True, "output": stdout.decode().strip()}
    except Exception as e:
        return {"success": False, "error": str(e)}


async def learn_ham(message_path: str) -> dict:
    """Train Rspamd to recognize ham (not spam)."""
    try:
        proc = await asyncio.create_subprocess_exec(
            "rspamc", "learn_ham", message_path,
            stdout=asyncio.subprocess.PIPE,
            stderr=asyncio.subprocess.PIPE,
        )
        stdout, _ = await proc.communicate()
        return {"success": True, "output": stdout.decode().strip()}
    except Exception as e:
        return {"success": False, "error": str(e)}


async def generate_dkim(domain: str, selector: str = "dkim") -> dict:
    """Generate DKIM keys for a domain."""
    dkim_dir = Path("/var/lib/rspamd/dkim")
    dkim_dir.mkdir(parents=True, exist_ok=True)

    key_file = dkim_dir / f"{domain}.{selector}.key"

    try:
        proc = await asyncio.create_subprocess_exec(
            "rspamadm", "dkim_keygen",
            "-s", selector, "-d", domain,
            "-k", str(key_file),
            stdout=asyncio.subprocess.PIPE,
            stderr=asyncio.subprocess.PIPE,
        )
        stdout, _ = await proc.communicate()

        await asyncio.create_subprocess_exec("chown", "rspamd:rspamd", str(key_file))

        return {
            "success": True,
            "dns_record": stdout.decode().strip(),
            "message": f"DKIM key generated. Add the DNS TXT record for {selector}._domainkey.{domain}",
        }
    except Exception as e:
        return {"success": False, "error": str(e)}
