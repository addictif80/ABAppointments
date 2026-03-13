"""File manager API routes."""

from fastapi import APIRouter, Depends, HTTPException
from pydantic import BaseModel
from sqlalchemy import select
from sqlalchemy.ext.asyncio import AsyncSession

from backend.core.config import settings
from backend.core.database import get_db
from backend.middleware.auth import get_current_user
from backend.models.user import User
from backend.models.website import Website
from backend.services.filemanager import (
    change_permissions,
    create_directory,
    delete_item,
    list_directory,
    read_file,
    rename_item,
    write_file,
)

router = APIRouter(prefix="/api/files", tags=["files"])


def _get_base_path(user: User, domain: str) -> str:
    return str(settings.WEB_ROOT / domain)


class WriteFileRequest(BaseModel):
    path: str
    content: str


class RenameRequest(BaseModel):
    old_path: str
    new_name: str


class PermissionsRequest(BaseModel):
    path: str
    mode: str


@router.get("/{domain}/list")
async def api_list_directory(
    domain: str,
    path: str = "",
    user: User = Depends(get_current_user),
    db: AsyncSession = Depends(get_db),
):
    result = await db.execute(select(Website).where(Website.domain == domain))
    website = result.scalar_one_or_none()
    if not website:
        raise HTTPException(status_code=404, detail="Website not found")
    if user.role != "admin" and website.user_id != user.id:
        raise HTTPException(status_code=403, detail="Access denied")

    base = _get_base_path(user, domain)
    return list_directory(base, path)


@router.get("/{domain}/read")
async def api_read_file(
    domain: str,
    path: str,
    user: User = Depends(get_current_user),
    db: AsyncSession = Depends(get_db),
):
    result = await db.execute(select(Website).where(Website.domain == domain))
    website = result.scalar_one_or_none()
    if not website:
        raise HTTPException(status_code=404, detail="Website not found")
    if user.role != "admin" and website.user_id != user.id:
        raise HTTPException(status_code=403, detail="Access denied")

    base = _get_base_path(user, domain)
    return read_file(base, path)


@router.post("/{domain}/write")
async def api_write_file(
    domain: str,
    data: WriteFileRequest,
    user: User = Depends(get_current_user),
    db: AsyncSession = Depends(get_db),
):
    result = await db.execute(select(Website).where(Website.domain == domain))
    website = result.scalar_one_or_none()
    if not website:
        raise HTTPException(status_code=404, detail="Website not found")
    if user.role != "admin" and website.user_id != user.id:
        raise HTTPException(status_code=403, detail="Access denied")

    base = _get_base_path(user, domain)
    return write_file(base, data.path, data.content)


@router.post("/{domain}/mkdir")
async def api_create_directory(
    domain: str,
    data: WriteFileRequest,
    user: User = Depends(get_current_user),
    db: AsyncSession = Depends(get_db),
):
    result = await db.execute(select(Website).where(Website.domain == domain))
    website = result.scalar_one_or_none()
    if not website:
        raise HTTPException(status_code=404, detail="Website not found")
    if user.role != "admin" and website.user_id != user.id:
        raise HTTPException(status_code=403, detail="Access denied")

    base = _get_base_path(user, domain)
    return create_directory(base, data.path)


@router.delete("/{domain}/delete")
async def api_delete_item(
    domain: str,
    path: str,
    user: User = Depends(get_current_user),
    db: AsyncSession = Depends(get_db),
):
    result = await db.execute(select(Website).where(Website.domain == domain))
    website = result.scalar_one_or_none()
    if not website:
        raise HTTPException(status_code=404, detail="Website not found")
    if user.role != "admin" and website.user_id != user.id:
        raise HTTPException(status_code=403, detail="Access denied")

    base = _get_base_path(user, domain)
    return delete_item(base, path)


@router.post("/{domain}/rename")
async def api_rename_item(
    domain: str,
    data: RenameRequest,
    user: User = Depends(get_current_user),
    db: AsyncSession = Depends(get_db),
):
    result = await db.execute(select(Website).where(Website.domain == domain))
    website = result.scalar_one_or_none()
    if not website:
        raise HTTPException(status_code=404, detail="Website not found")
    if user.role != "admin" and website.user_id != user.id:
        raise HTTPException(status_code=403, detail="Access denied")

    base = _get_base_path(user, domain)
    return rename_item(base, data.old_path, data.new_name)


@router.post("/{domain}/chmod")
async def api_change_permissions(
    domain: str,
    data: PermissionsRequest,
    user: User = Depends(get_current_user),
    db: AsyncSession = Depends(get_db),
):
    result = await db.execute(select(Website).where(Website.domain == domain))
    website = result.scalar_one_or_none()
    if not website:
        raise HTTPException(status_code=404, detail="Website not found")
    if user.role != "admin" and website.user_id != user.id:
        raise HTTPException(status_code=403, detail="Access denied")

    base = _get_base_path(user, domain)
    return change_permissions(base, data.path, data.mode)
