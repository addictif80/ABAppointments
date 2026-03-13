"""Web-based SSH Terminal service."""

import asyncio
import json
from pathlib import Path


async def execute_command(command: str, cwd: str = "/root", timeout: int = 30) -> dict:
    """Execute a shell command and return output.

    This provides a web-based terminal experience. For full interactive SSH,
    consider integrating xterm.js with a WebSocket backend.
    """
    # Block dangerous commands
    blocked = ["rm -rf /", "mkfs", "dd if=", ":(){", "fork bomb"]
    for b in blocked:
        if b in command:
            return {"success": False, "error": "Command blocked for safety"}

    try:
        proc = await asyncio.create_subprocess_shell(
            command,
            cwd=cwd,
            stdout=asyncio.subprocess.PIPE,
            stderr=asyncio.subprocess.PIPE,
        )

        try:
            stdout, stderr = await asyncio.wait_for(proc.communicate(), timeout=timeout)
        except asyncio.TimeoutError:
            proc.kill()
            return {"success": False, "error": "Command timed out", "timeout": timeout}

        return {
            "success": proc.returncode == 0,
            "stdout": stdout.decode(errors="replace"),
            "stderr": stderr.decode(errors="replace"),
            "return_code": proc.returncode,
        }
    except Exception as e:
        return {"success": False, "error": str(e)}


async def get_command_history(limit: int = 50) -> dict:
    """Read bash history."""
    history_file = Path("/root/.bash_history")
    if not history_file.exists():
        return {"success": True, "history": []}

    try:
        lines = history_file.read_text().strip().split("\n")
        return {"success": True, "history": lines[-limit:]}
    except Exception as e:
        return {"success": False, "error": str(e)}
