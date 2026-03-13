"""Rspamd Manager API routes."""

from fastapi import APIRouter, Depends
from pydantic import BaseModel

from backend.middleware.auth import require_admin
from backend.models.user import User
from backend.services.rspamd_mgr import (
    generate_dkim,
    get_stats,
    get_status,
    install_rspamd,
)

router = APIRouter(prefix="/api/addons/rspamd", tags=["addons"])


class DKIMRequest(BaseModel):
    domain: str
    selector: str = "dkim"


@router.get("/status")
async def rspamd_status(user: User = Depends(require_admin)):
    return await get_status()


@router.post("/install")
async def rspamd_install(user: User = Depends(require_admin)):
    return await install_rspamd()


@router.get("/stats")
async def rspamd_stats(user: User = Depends(require_admin)):
    return await get_stats()


@router.post("/dkim")
async def rspamd_dkim(data: DKIMRequest, user: User = Depends(require_admin)):
    return await generate_dkim(data.domain, data.selector)
