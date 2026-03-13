"""WordPress Manager API routes."""

from fastapi import APIRouter, Depends, HTTPException
from pydantic import BaseModel
from sqlalchemy import select
from sqlalchemy.ext.asyncio import AsyncSession

from backend.core.database import get_db
from backend.middleware.auth import get_current_user
from backend.models.user import User
from backend.models.website import Website
from backend.services.wordpress import (
    create_staging,
    delete_plugin,
    get_wp_info,
    install_plugin,
    install_wordpress,
    install_wp_cli,
    update_wordpress,
)

router = APIRouter(prefix="/api/addons/wordpress", tags=["addons"])


class InstallWPRequest(BaseModel):
    domain: str
    site_title: str = "Mon Site"
    admin_user: str = "admin"
    admin_password: str
    admin_email: str
    db_name: str
    db_user: str
    db_password: str


class PluginRequest(BaseModel):
    domain: str
    plugin_slug: str


@router.post("/install-cli")
async def api_install_wp_cli(user: User = Depends(get_current_user)):
    return await install_wp_cli()


@router.post("/install")
async def api_install_wp(
    data: InstallWPRequest,
    user: User = Depends(get_current_user),
    db: AsyncSession = Depends(get_db),
):
    result = await db.execute(select(Website).where(Website.domain == data.domain))
    website = result.scalar_one_or_none()
    if not website:
        raise HTTPException(status_code=404, detail="Website not found")
    if user.role != "admin" and website.user_id != user.id:
        raise HTTPException(status_code=403, detail="Access denied")

    return await install_wordpress(
        data.domain, data.site_title, data.admin_user,
        data.admin_password, data.admin_email,
        data.db_name, data.db_user, data.db_password,
    )


@router.get("/info/{domain}")
async def api_wp_info(domain: str, user: User = Depends(get_current_user)):
    return await get_wp_info(domain)


@router.post("/update/{domain}")
async def api_update_wp(domain: str, user: User = Depends(get_current_user)):
    return await update_wordpress(domain)


@router.post("/staging/{domain}")
async def api_create_staging(domain: str, user: User = Depends(get_current_user)):
    return await create_staging(domain)


@router.post("/plugin/install")
async def api_install_plugin(data: PluginRequest, user: User = Depends(get_current_user)):
    return await install_plugin(data.domain, data.plugin_slug)


@router.post("/plugin/delete")
async def api_delete_plugin(data: PluginRequest, user: User = Depends(get_current_user)):
    return await delete_plugin(data.domain, data.plugin_slug)
