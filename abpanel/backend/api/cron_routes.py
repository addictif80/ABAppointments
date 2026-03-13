"""Cron Job Manager API routes."""

from fastapi import APIRouter, Depends
from pydantic import BaseModel

from backend.middleware.auth import require_admin
from backend.models.user import User
from backend.services.cron_mgr import add_cron_job, delete_cron_job, get_presets, list_cron_jobs

router = APIRouter(prefix="/api/addons/cron", tags=["addons"])


class AddCronRequest(BaseModel):
    minute: str
    hour: str
    day: str
    month: str
    weekday: str
    command: str


@router.get("/")
async def cron_list(user: User = Depends(require_admin)):
    return await list_cron_jobs()


@router.post("/")
async def cron_add(data: AddCronRequest, user: User = Depends(require_admin)):
    return await add_cron_job(data.minute, data.hour, data.day, data.month, data.weekday, data.command)


@router.delete("/{job_index}")
async def cron_delete(job_index: int, user: User = Depends(require_admin)):
    return await delete_cron_job(job_index)


@router.get("/presets")
async def cron_presets(user: User = Depends(require_admin)):
    return await get_presets()
