"""Backup and restore management."""

import asyncio
import os
from datetime import datetime, timezone
from pathlib import Path

from backend.core.config import settings


async def create_backup(domain: str, backup_type: str = "full") -> dict:
    timestamp = datetime.now(timezone.utc).strftime("%Y%m%d_%H%M%S")
    backup_name = f"{domain}_{backup_type}_{timestamp}"
    backup_path = settings.BACKUP_DIR / backup_name

    try:
        backup_path.mkdir(parents=True, exist_ok=True)
        web_root = settings.WEB_ROOT / domain

        if backup_type in ("full", "files"):
            archive = backup_path / "files.tar.gz"
            proc = await asyncio.create_subprocess_exec(
                "tar", "czf", str(archive), "-C", str(web_root), ".",
                stdout=asyncio.subprocess.PIPE,
                stderr=asyncio.subprocess.PIPE,
            )
            _, stderr = await proc.communicate()
            if proc.returncode != 0:
                return {"success": False, "error": f"Files backup failed: {stderr.decode()}"}

        if backup_type in ("full", "database"):
            # Find associated database (convention: domain without dots)
            db_name = domain.replace(".", "_").replace("-", "_")
            dump_file = backup_path / "database.sql.gz"
            cmd = f"mysqldump -u root {db_name} 2>/dev/null | gzip > {dump_file}"
            proc = await asyncio.create_subprocess_shell(
                cmd,
                stdout=asyncio.subprocess.PIPE,
                stderr=asyncio.subprocess.PIPE,
            )
            await proc.communicate()
            # Database backup is optional, don't fail if DB doesn't exist

        # Backup nginx config
        vhost_file = settings.VHOSTS_DIR / domain
        if vhost_file.exists():
            (backup_path / "vhost.conf").write_text(vhost_file.read_text())

        # Calculate size
        total_size = sum(f.stat().st_size for f in backup_path.rglob("*") if f.is_file())

        return {
            "success": True,
            "backup_name": backup_name,
            "path": str(backup_path),
            "size_bytes": total_size,
        }

    except Exception as e:
        return {"success": False, "error": str(e)}


async def restore_backup(backup_name: str, domain: str) -> dict:
    backup_path = settings.BACKUP_DIR / backup_name

    if not backup_path.exists():
        return {"success": False, "error": "Backup not found"}

    try:
        web_root = settings.WEB_ROOT / domain
        web_root.mkdir(parents=True, exist_ok=True)

        # Restore files
        files_archive = backup_path / "files.tar.gz"
        if files_archive.exists():
            proc = await asyncio.create_subprocess_exec(
                "tar", "xzf", str(files_archive), "-C", str(web_root),
                stdout=asyncio.subprocess.PIPE,
                stderr=asyncio.subprocess.PIPE,
            )
            _, stderr = await proc.communicate()
            if proc.returncode != 0:
                return {"success": False, "error": f"File restore failed: {stderr.decode()}"}

        # Restore database
        db_dump = backup_path / "database.sql.gz"
        if db_dump.exists():
            db_name = domain.replace(".", "_").replace("-", "_")
            cmd = f"gunzip < {db_dump} | mysql -u root {db_name}"
            proc = await asyncio.create_subprocess_shell(
                cmd,
                stdout=asyncio.subprocess.PIPE,
                stderr=asyncio.subprocess.PIPE,
            )
            await proc.communicate()

        # Restore vhost
        vhost_backup = backup_path / "vhost.conf"
        if vhost_backup.exists():
            vhost_dest = settings.VHOSTS_DIR / domain
            vhost_dest.write_text(vhost_backup.read_text())
            enabled = settings.VHOSTS_ENABLED_DIR / domain
            if not enabled.exists():
                enabled.symlink_to(vhost_dest)

        return {"success": True, "message": f"Backup {backup_name} restored for {domain}"}

    except Exception as e:
        return {"success": False, "error": str(e)}


async def list_backups(domain: str | None = None) -> dict:
    try:
        backup_dir = settings.BACKUP_DIR
        if not backup_dir.exists():
            return {"success": True, "backups": []}

        backups = []
        for entry in sorted(backup_dir.iterdir(), reverse=True):
            if entry.is_dir():
                if domain and not entry.name.startswith(domain):
                    continue
                size = sum(f.stat().st_size for f in entry.rglob("*") if f.is_file())
                backups.append({
                    "name": entry.name,
                    "size_bytes": size,
                    "created": datetime.fromtimestamp(
                        entry.stat().st_ctime, tz=timezone.utc
                    ).isoformat(),
                })

        return {"success": True, "backups": backups}
    except Exception as e:
        return {"success": False, "error": str(e)}


async def delete_backup(backup_name: str) -> dict:
    backup_path = settings.BACKUP_DIR / backup_name
    if not backup_path.exists():
        return {"success": False, "error": "Backup not found"}

    try:
        proc = await asyncio.create_subprocess_exec(
            "rm", "-rf", str(backup_path),
            stdout=asyncio.subprocess.PIPE,
            stderr=asyncio.subprocess.PIPE,
        )
        await proc.communicate()
        return {"success": True}
    except Exception as e:
        return {"success": False, "error": str(e)}
