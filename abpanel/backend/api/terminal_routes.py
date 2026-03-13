"""SSH Terminal API routes."""

from fastapi import APIRouter, Depends
from pydantic import BaseModel

from backend.middleware.auth import require_admin
from backend.models.user import User
from backend.services.ssh_terminal import execute_command, get_command_history

router = APIRouter(prefix="/api/addons/terminal", tags=["addons"])


class CommandRequest(BaseModel):
    command: str
    cwd: str = "/root"
    timeout: int = 30


@router.post("/execute")
async def terminal_execute(data: CommandRequest, user: User = Depends(require_admin)):
    return await execute_command(data.command, data.cwd, data.timeout)


@router.get("/history")
async def terminal_history(limit: int = 50, user: User = Depends(require_admin)):
    return await get_command_history(limit)
