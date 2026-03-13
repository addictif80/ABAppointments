"""SSL/Let's Encrypt certificate management."""

import asyncio

from backend.core.config import settings


async def issue_certificate(domain: str, email: str = "admin@localhost") -> dict:
    try:
        proc = await asyncio.create_subprocess_exec(
            settings.CERTBOT_BIN, "certonly", "--nginx",
            "-d", domain, "-d", f"www.{domain}",
            "--non-interactive", "--agree-tos",
            "--email", email,
            stdout=asyncio.subprocess.PIPE,
            stderr=asyncio.subprocess.PIPE,
        )
        stdout, stderr = await proc.communicate()
        if proc.returncode == 0:
            return {"success": True, "message": f"SSL certificate issued for {domain}"}
        return {"success": False, "error": stderr.decode().strip()}
    except Exception as e:
        return {"success": False, "error": str(e)}


async def renew_certificates() -> dict:
    try:
        proc = await asyncio.create_subprocess_exec(
            settings.CERTBOT_BIN, "renew", "--quiet",
            stdout=asyncio.subprocess.PIPE,
            stderr=asyncio.subprocess.PIPE,
        )
        stdout, stderr = await proc.communicate()
        if proc.returncode == 0:
            return {"success": True, "message": "Certificates renewed successfully"}
        return {"success": False, "error": stderr.decode().strip()}
    except Exception as e:
        return {"success": False, "error": str(e)}


async def revoke_certificate(domain: str) -> dict:
    cert_path = f"/etc/letsencrypt/live/{domain}/cert.pem"
    try:
        proc = await asyncio.create_subprocess_exec(
            settings.CERTBOT_BIN, "revoke",
            "--cert-path", cert_path,
            "--non-interactive",
            stdout=asyncio.subprocess.PIPE,
            stderr=asyncio.subprocess.PIPE,
        )
        _, stderr = await proc.communicate()
        if proc.returncode == 0:
            return {"success": True, "message": f"Certificate revoked for {domain}"}
        return {"success": False, "error": stderr.decode().strip()}
    except Exception as e:
        return {"success": False, "error": str(e)}


async def list_certificates() -> dict:
    try:
        proc = await asyncio.create_subprocess_exec(
            settings.CERTBOT_BIN, "certificates",
            stdout=asyncio.subprocess.PIPE,
            stderr=asyncio.subprocess.PIPE,
        )
        stdout, _ = await proc.communicate()
        return {"success": True, "output": stdout.decode().strip()}
    except Exception as e:
        return {"success": False, "error": str(e)}
