"""Firewall management (UFW)."""

import asyncio

from backend.core.config import settings


async def _run_ufw(*args: str) -> dict:
    try:
        proc = await asyncio.create_subprocess_exec(
            settings.UFW_BIN, *args,
            stdout=asyncio.subprocess.PIPE,
            stderr=asyncio.subprocess.PIPE,
        )
        stdout, stderr = await proc.communicate()
        if proc.returncode == 0:
            return {"success": True, "output": stdout.decode().strip()}
        return {"success": False, "error": stderr.decode().strip()}
    except Exception as e:
        return {"success": False, "error": str(e)}


async def get_status() -> dict:
    return await _run_ufw("status", "verbose")


async def allow_port(port: int | str, proto: str = "tcp") -> dict:
    return await _run_ufw("allow", f"{port}/{proto}")


async def deny_port(port: int | str, proto: str = "tcp") -> dict:
    return await _run_ufw("deny", f"{port}/{proto}")


async def delete_rule(rule_number: int) -> dict:
    return await _run_ufw("--force", "delete", str(rule_number))


async def allow_ip(ip: str) -> dict:
    return await _run_ufw("allow", "from", ip)


async def deny_ip(ip: str) -> dict:
    return await _run_ufw("deny", "from", ip)


async def get_rules() -> dict:
    return await _run_ufw("status", "numbered")
