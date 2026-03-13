"""Resource limits management using cgroups v2."""

import asyncio
import json
from pathlib import Path

CGROUP_BASE = Path("/sys/fs/cgroup")


async def get_system_limits() -> dict:
    """Get current system resource usage and limits."""
    try:
        # CPU info
        proc = await asyncio.create_subprocess_exec(
            "nproc",
            stdout=asyncio.subprocess.PIPE,
        )
        stdout, _ = await proc.communicate()
        cpu_count = int(stdout.decode().strip())

        # Memory
        proc = await asyncio.create_subprocess_exec(
            "free", "-b",
            stdout=asyncio.subprocess.PIPE,
        )
        stdout, _ = await proc.communicate()
        lines = stdout.decode().strip().split("\n")
        mem_parts = lines[1].split()
        total_mem = int(mem_parts[1])

        return {
            "success": True,
            "cpu_count": cpu_count,
            "total_memory_bytes": total_mem,
            "cgroup_v2": (CGROUP_BASE / "cgroup.controllers").exists(),
        }
    except Exception as e:
        return {"success": False, "error": str(e)}


async def create_user_limits(username: str, cpu_percent: int = 100,
                             memory_mb: int = 1024, io_weight: int = 100,
                             max_processes: int = 100) -> dict:
    """Create resource limits for a user using cgroups v2."""
    cgroup_path = CGROUP_BASE / "abpanel" / username

    try:
        cgroup_path.mkdir(parents=True, exist_ok=True)

        # CPU limit (in microseconds, 100000 = 100%)
        cpu_quota = int(cpu_percent * 1000)
        (cgroup_path / "cpu.max").write_text(f"{cpu_quota} 100000")

        # Memory limit
        mem_bytes = memory_mb * 1024 * 1024
        (cgroup_path / "memory.max").write_text(str(mem_bytes))

        # IO weight (1-10000)
        io_w = max(1, min(10000, io_weight * 100))
        try:
            (cgroup_path / "io.weight").write_text(f"default {io_w}")
        except OSError:
            pass  # IO controller might not be available

        # Process limit
        (cgroup_path / "pids.max").write_text(str(max_processes))

        return {
            "success": True,
            "message": f"Resource limits set for {username}",
            "limits": {
                "cpu_percent": cpu_percent,
                "memory_mb": memory_mb,
                "io_weight": io_weight,
                "max_processes": max_processes,
            },
        }

    except Exception as e:
        return {"success": False, "error": str(e)}


async def get_user_limits(username: str) -> dict:
    """Get resource limits for a user."""
    cgroup_path = CGROUP_BASE / "abpanel" / username

    if not cgroup_path.exists():
        return {"success": False, "error": "No limits configured for this user"}

    try:
        limits = {}

        cpu_max = (cgroup_path / "cpu.max").read_text().strip().split()
        if cpu_max[0] == "max":
            limits["cpu_percent"] = 100
        else:
            limits["cpu_percent"] = int(int(cpu_max[0]) / 1000)

        mem_max = (cgroup_path / "memory.max").read_text().strip()
        if mem_max == "max":
            limits["memory_mb"] = -1
        else:
            limits["memory_mb"] = int(int(mem_max) / (1024 * 1024))

        # Current usage
        try:
            mem_current = int((cgroup_path / "memory.current").read_text().strip())
            limits["memory_used_mb"] = int(mem_current / (1024 * 1024))
        except (FileNotFoundError, ValueError):
            pass

        try:
            cpu_stat = (cgroup_path / "cpu.stat").read_text().strip()
            limits["cpu_stat"] = cpu_stat
        except FileNotFoundError:
            pass

        pids_max = (cgroup_path / "pids.max").read_text().strip()
        limits["max_processes"] = int(pids_max) if pids_max != "max" else -1

        try:
            pids_current = int((cgroup_path / "pids.current").read_text().strip())
            limits["current_processes"] = pids_current
        except (FileNotFoundError, ValueError):
            pass

        return {"success": True, "limits": limits}

    except Exception as e:
        return {"success": False, "error": str(e)}


async def delete_user_limits(username: str) -> dict:
    """Remove resource limits for a user."""
    cgroup_path = CGROUP_BASE / "abpanel" / username

    if not cgroup_path.exists():
        return {"success": True, "message": "No limits to remove"}

    try:
        # rmdir is safe for cgroups
        proc = await asyncio.create_subprocess_exec(
            "rmdir", str(cgroup_path),
            stdout=asyncio.subprocess.PIPE,
            stderr=asyncio.subprocess.PIPE,
        )
        await proc.communicate()
        return {"success": True, "message": f"Limits removed for {username}"}
    except Exception as e:
        return {"success": False, "error": str(e)}
