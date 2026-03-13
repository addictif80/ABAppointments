"""Resource Limits API routes."""

from fastapi import APIRouter, Depends
from pydantic import BaseModel

from backend.middleware.auth import require_admin
from backend.models.user import User
from backend.services.resource_limits import (
    create_user_limits,
    delete_user_limits,
    get_system_limits,
    get_user_limits,
)

router = APIRouter(prefix="/api/addons/resources", tags=["addons"])


class SetLimitsRequest(BaseModel):
    username: str
    cpu_percent: int = 100
    memory_mb: int = 1024
    io_weight: int = 100
    max_processes: int = 100


@router.get("/system")
async def resource_system(user: User = Depends(require_admin)):
    return await get_system_limits()


@router.get("/user/{username}")
async def resource_user(username: str, user: User = Depends(require_admin)):
    return await get_user_limits(username)


@router.post("/user")
async def resource_set(data: SetLimitsRequest, user: User = Depends(require_admin)):
    return await create_user_limits(data.username, data.cpu_percent, data.memory_mb, data.io_weight, data.max_processes)


@router.delete("/user/{username}")
async def resource_delete(username: str, user: User = Depends(require_admin)):
    return await delete_user_limits(username)
