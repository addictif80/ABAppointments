"""System monitoring service."""

import asyncio
import platform
from datetime import datetime, timezone

import psutil


async def get_system_info() -> dict:
    cpu_percent = psutil.cpu_percent(interval=0.5)
    memory = psutil.virtual_memory()
    disk = psutil.disk_usage("/")
    boot_time = datetime.fromtimestamp(psutil.boot_time(), tz=timezone.utc)
    uptime = datetime.now(timezone.utc) - boot_time
    load_avg = psutil.getloadavg()

    net_io = psutil.net_io_counters()

    return {
        "hostname": platform.node(),
        "os": f"{platform.system()} {platform.release()}",
        "arch": platform.machine(),
        "uptime_seconds": int(uptime.total_seconds()),
        "uptime_human": str(uptime).split(".")[0],
        "cpu": {
            "count": psutil.cpu_count(),
            "percent": cpu_percent,
            "load_avg": {
                "1min": round(load_avg[0], 2),
                "5min": round(load_avg[1], 2),
                "15min": round(load_avg[2], 2),
            },
        },
        "memory": {
            "total": memory.total,
            "used": memory.used,
            "available": memory.available,
            "percent": memory.percent,
        },
        "disk": {
            "total": disk.total,
            "used": disk.used,
            "free": disk.free,
            "percent": disk.percent,
        },
        "network": {
            "bytes_sent": net_io.bytes_sent,
            "bytes_recv": net_io.bytes_recv,
        },
    }


async def get_service_status(service_name: str) -> dict:
    try:
        proc = await asyncio.create_subprocess_exec(
            "systemctl", "is-active", service_name,
            stdout=asyncio.subprocess.PIPE,
            stderr=asyncio.subprocess.PIPE,
        )
        stdout, _ = await proc.communicate()
        status = stdout.decode().strip()
        return {"name": service_name, "status": status, "running": status == "active"}
    except Exception:
        return {"name": service_name, "status": "unknown", "running": False}


async def get_all_services_status() -> list[dict]:
    services = ["nginx", "mysql", "mariadb", "php8.3-fpm", "php8.2-fpm",
                 "postfix", "dovecot", "named", "pure-ftpd", "ufw", "fail2ban"]
    tasks = [get_service_status(s) for s in services]
    return await asyncio.gather(*tasks)


async def manage_service(service_name: str, action: str) -> dict:
    if action not in ("start", "stop", "restart", "reload"):
        return {"success": False, "error": "Invalid action"}

    allowed_services = {"nginx", "mysql", "mariadb", "php8.3-fpm", "php8.2-fpm",
                        "postfix", "dovecot", "named", "pure-ftpd"}
    if service_name not in allowed_services:
        return {"success": False, "error": "Service not allowed"}

    try:
        proc = await asyncio.create_subprocess_exec(
            "systemctl", action, service_name,
            stdout=asyncio.subprocess.PIPE,
            stderr=asyncio.subprocess.PIPE,
        )
        _, stderr = await proc.communicate()
        if proc.returncode == 0:
            return {"success": True, "message": f"{service_name} {action} successful"}
        return {"success": False, "error": stderr.decode().strip()}
    except Exception as e:
        return {"success": False, "error": str(e)}
