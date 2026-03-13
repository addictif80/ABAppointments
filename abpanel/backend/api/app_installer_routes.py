"""One-Click App Installer API routes."""

from fastapi import APIRouter, Depends, HTTPException
from pydantic import BaseModel
from sqlalchemy import select
from sqlalchemy.ext.asyncio import AsyncSession

from backend.core.database import get_db
from backend.middleware.auth import get_current_user
from backend.models.user import User
from backend.models.website import Website
from backend.services.app_installer import get_available_apps, install_app

router = APIRouter(prefix="/api/addons/apps", tags=["addons"])


class InstallAppRequest(BaseModel):
    app_key: str
    domain: str


@router.get("/available")
async def api_available_apps(user: User = Depends(get_current_user)):
    return await get_available_apps()


@router.post("/install")
async def api_install_app(
    data: InstallAppRequest,
    user: User = Depends(get_current_user),
    db: AsyncSession = Depends(get_db),
):
    result = await db.execute(select(Website).where(Website.domain == data.domain))
    website = result.scalar_one_or_none()
    if not website:
        raise HTTPException(status_code=404, detail="Website not found")
    if user.role != "admin" and website.user_id != user.id:
        raise HTTPException(status_code=403, detail="Access denied")

    return await install_app(data.app_key, data.domain)
