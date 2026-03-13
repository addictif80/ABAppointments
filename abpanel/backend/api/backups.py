"""Backup management API routes."""

from fastapi import APIRouter, Depends, HTTPException
from pydantic import BaseModel
from sqlalchemy import select
from sqlalchemy.ext.asyncio import AsyncSession

from backend.core.database import get_db
from backend.middleware.auth import get_current_user
from backend.models.user import User
from backend.models.website import Website
from backend.services.backup import create_backup, delete_backup, list_backups, restore_backup

router = APIRouter(prefix="/api/backups", tags=["backups"])


class CreateBackupRequest(BaseModel):
    domain: str
    backup_type: str = "full"


class RestoreBackupRequest(BaseModel):
    backup_name: str
    domain: str


@router.get("/")
async def api_list_backups(
    domain: str | None = None,
    user: User = Depends(get_current_user),
):
    return await list_backups(domain)


@router.post("/create")
async def api_create_backup(
    data: CreateBackupRequest,
    user: User = Depends(get_current_user),
    db: AsyncSession = Depends(get_db),
):
    # Verify ownership
    result = await db.execute(select(Website).where(Website.domain == data.domain))
    website = result.scalar_one_or_none()
    if not website:
        raise HTTPException(status_code=404, detail="Website not found")
    if user.role != "admin" and website.user_id != user.id:
        raise HTTPException(status_code=403, detail="Access denied")

    return await create_backup(data.domain, data.backup_type)


@router.post("/restore")
async def api_restore_backup(
    data: RestoreBackupRequest,
    user: User = Depends(get_current_user),
    db: AsyncSession = Depends(get_db),
):
    result = await db.execute(select(Website).where(Website.domain == data.domain))
    website = result.scalar_one_or_none()
    if not website:
        raise HTTPException(status_code=404, detail="Website not found")
    if user.role != "admin" and website.user_id != user.id:
        raise HTTPException(status_code=403, detail="Access denied")

    return await restore_backup(data.backup_name, data.domain)


@router.delete("/{backup_name}")
async def api_delete_backup(
    backup_name: str,
    user: User = Depends(get_current_user),
):
    return await delete_backup(backup_name)
