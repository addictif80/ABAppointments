"""Cron Job Manager."""

import asyncio
from pathlib import Path


async def list_cron_jobs(user: str = "root") -> dict:
    """List cron jobs for a user."""
    try:
        proc = await asyncio.create_subprocess_exec(
            "crontab", "-l", "-u", user,
            stdout=asyncio.subprocess.PIPE,
            stderr=asyncio.subprocess.PIPE,
        )
        stdout, stderr = await proc.communicate()

        if proc.returncode != 0:
            if "no crontab" in stderr.decode():
                return {"success": True, "jobs": []}
            return {"success": False, "error": stderr.decode().strip()}

        jobs = []
        for i, line in enumerate(stdout.decode().strip().split("\n")):
            line = line.strip()
            if not line or line.startswith("#"):
                continue

            parts = line.split(None, 5)
            if len(parts) >= 6:
                jobs.append({
                    "id": i,
                    "minute": parts[0],
                    "hour": parts[1],
                    "day": parts[2],
                    "month": parts[3],
                    "weekday": parts[4],
                    "command": parts[5],
                    "raw": line,
                })

        return {"success": True, "jobs": jobs}

    except Exception as e:
        return {"success": False, "error": str(e)}


async def add_cron_job(minute: str, hour: str, day: str, month: str,
                       weekday: str, command: str, user: str = "root") -> dict:
    """Add a cron job."""
    try:
        # Get existing crontab
        proc = await asyncio.create_subprocess_exec(
            "crontab", "-l", "-u", user,
            stdout=asyncio.subprocess.PIPE,
            stderr=asyncio.subprocess.PIPE,
        )
        stdout, stderr = await proc.communicate()
        existing = stdout.decode() if proc.returncode == 0 else ""

        # Append new job
        new_line = f"{minute} {hour} {day} {month} {weekday} {command}"
        new_crontab = existing.rstrip("\n") + "\n" + new_line + "\n"

        # Install new crontab
        proc = await asyncio.create_subprocess_exec(
            "crontab", "-u", user, "-",
            stdin=asyncio.subprocess.PIPE,
            stdout=asyncio.subprocess.PIPE,
            stderr=asyncio.subprocess.PIPE,
        )
        _, stderr = await proc.communicate(input=new_crontab.encode())

        if proc.returncode == 0:
            return {"success": True, "message": "Cron job added", "job": new_line}
        return {"success": False, "error": stderr.decode().strip()}

    except Exception as e:
        return {"success": False, "error": str(e)}


async def delete_cron_job(job_index: int, user: str = "root") -> dict:
    """Delete a cron job by index."""
    try:
        proc = await asyncio.create_subprocess_exec(
            "crontab", "-l", "-u", user,
            stdout=asyncio.subprocess.PIPE,
            stderr=asyncio.subprocess.PIPE,
        )
        stdout, _ = await proc.communicate()

        if proc.returncode != 0:
            return {"success": False, "error": "No crontab found"}

        lines = stdout.decode().strip().split("\n")
        # Find the actual job lines (non-comment, non-empty)
        job_lines = [(i, l) for i, l in enumerate(lines) if l.strip() and not l.strip().startswith("#")]

        if job_index >= len(job_lines):
            return {"success": False, "error": "Job index out of range"}

        # Remove the line
        line_to_remove = job_lines[job_index][0]
        lines.pop(line_to_remove)

        new_crontab = "\n".join(lines) + "\n"

        proc = await asyncio.create_subprocess_exec(
            "crontab", "-u", user, "-",
            stdin=asyncio.subprocess.PIPE,
            stdout=asyncio.subprocess.PIPE,
            stderr=asyncio.subprocess.PIPE,
        )
        _, stderr = await proc.communicate(input=new_crontab.encode())

        if proc.returncode == 0:
            return {"success": True, "message": "Cron job deleted"}
        return {"success": False, "error": stderr.decode().strip()}

    except Exception as e:
        return {"success": False, "error": str(e)}


# Common cron presets
CRON_PRESETS = {
    "every_minute": {"minute": "*", "hour": "*", "day": "*", "month": "*", "weekday": "*", "label": "Chaque minute"},
    "every_5min": {"minute": "*/5", "hour": "*", "day": "*", "month": "*", "weekday": "*", "label": "Toutes les 5 min"},
    "every_hour": {"minute": "0", "hour": "*", "day": "*", "month": "*", "weekday": "*", "label": "Chaque heure"},
    "daily": {"minute": "0", "hour": "2", "day": "*", "month": "*", "weekday": "*", "label": "Quotidien (02:00)"},
    "weekly": {"minute": "0", "hour": "2", "day": "*", "month": "*", "weekday": "0", "label": "Hebdomadaire (dim 02:00)"},
    "monthly": {"minute": "0", "hour": "2", "day": "1", "month": "*", "weekday": "*", "label": "Mensuel (1er du mois)"},
}


async def get_presets() -> list[dict]:
    """Return available cron presets."""
    return [{"key": k, **v} for k, v in CRON_PRESETS.items()]
