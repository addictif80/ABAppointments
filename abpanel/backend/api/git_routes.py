"""Git Manager API routes."""

from fastapi import APIRouter, Depends, HTTPException
from pydantic import BaseModel
from sqlalchemy import select
from sqlalchemy.ext.asyncio import AsyncSession

from backend.core.database import get_db
from backend.middleware.auth import get_current_user
from backend.models.user import User
from backend.models.website import Website
from backend.services.git_mgr import (
    checkout_branch,
    clone_repo,
    get_repo_info,
    list_branches,
    pull_repo,
    setup_webhook_deploy,
)

router = APIRouter(prefix="/api/addons/git", tags=["addons"])


class CloneRequest(BaseModel):
    repo_url: str
    domain: str
    branch: str = "main"


class CheckoutRequest(BaseModel):
    domain: str
    branch: str


@router.post("/clone")
async def api_clone(
    data: CloneRequest,
    user: User = Depends(get_current_user),
    db: AsyncSession = Depends(get_db),
):
    result = await db.execute(select(Website).where(Website.domain == data.domain))
    website = result.scalar_one_or_none()
    if not website:
        raise HTTPException(status_code=404, detail="Website not found")
    if user.role != "admin" and website.user_id != user.id:
        raise HTTPException(status_code=403, detail="Access denied")

    return await clone_repo(data.repo_url, data.domain, data.branch)


@router.post("/pull/{domain}")
async def api_pull(domain: str, user: User = Depends(get_current_user)):
    return await pull_repo(domain)


@router.get("/info/{domain}")
async def api_repo_info(domain: str, user: User = Depends(get_current_user)):
    return await get_repo_info(domain)


@router.get("/branches/{domain}")
async def api_branches(domain: str, user: User = Depends(get_current_user)):
    return await list_branches(domain)


@router.post("/checkout")
async def api_checkout(data: CheckoutRequest, user: User = Depends(get_current_user)):
    return await checkout_branch(data.domain, data.branch)


@router.post("/webhook/{domain}")
async def api_webhook(domain: str, token: str):
    """Git webhook endpoint for auto-deployment."""
    return await pull_repo(domain)
