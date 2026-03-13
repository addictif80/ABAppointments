"""Dashboard API routes."""

from fastapi import APIRouter, Depends
from sqlalchemy import func, select
from sqlalchemy.ext.asyncio import AsyncSession

from backend.core.database import get_db
from backend.middleware.auth import get_current_user
from backend.models.user import User
from backend.models.website import Backup, Database, EmailAccount, Website
from backend.services.system import get_all_services_status, get_system_info

router = APIRouter(prefix="/api/dashboard", tags=["dashboard"])


@router.get("/system")
async def system_info(user: User = Depends(get_current_user)):
    return await get_system_info()


@router.get("/services")
async def services_status(user: User = Depends(get_current_user)):
    return await get_all_services_status()


@router.get("/stats")
async def get_stats(
    user: User = Depends(get_current_user),
    db: AsyncSession = Depends(get_db),
):
    if user.role == "admin":
        websites = await db.execute(select(func.count(Website.id)))
        databases = await db.execute(select(func.count(Database.id)))
        emails = await db.execute(select(func.count(EmailAccount.id)))
        users = await db.execute(select(func.count(User.id)))
    else:
        websites = await db.execute(
            select(func.count(Website.id)).where(Website.user_id == user.id)
        )
        databases = await db.execute(
            select(func.count(Database.id)).where(Database.user_id == user.id)
        )
        emails = await db.execute(
            select(func.count(EmailAccount.id)).where(EmailAccount.user_id == user.id)
        )
        users = None

    stats = {
        "websites": websites.scalar() or 0,
        "databases": databases.scalar() or 0,
        "email_accounts": emails.scalar() or 0,
    }
    if users is not None:
        stats["users"] = users.scalar() or 0

    return stats
