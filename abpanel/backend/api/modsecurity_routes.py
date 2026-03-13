"""ModSecurity WAF API routes."""

from fastapi import APIRouter, Depends
from pydantic import BaseModel

from backend.middleware.auth import require_admin
from backend.models.user import User
from backend.services.modsecurity import (
    add_whitelist_rule,
    get_audit_log,
    get_status,
    install_modsecurity,
    toggle_modsecurity,
)

router = APIRouter(prefix="/api/addons/modsecurity", tags=["addons"])


class ToggleRequest(BaseModel):
    enable: bool


class WhitelistRequest(BaseModel):
    ip: str


@router.get("/status")
async def modsec_status(user: User = Depends(require_admin)):
    return await get_status()


@router.post("/install")
async def modsec_install(user: User = Depends(require_admin)):
    return await install_modsecurity()


@router.post("/toggle")
async def modsec_toggle(data: ToggleRequest, user: User = Depends(require_admin)):
    return await toggle_modsecurity(data.enable)


@router.get("/audit-log")
async def modsec_audit_log(lines: int = 100, user: User = Depends(require_admin)):
    return await get_audit_log(lines)


@router.post("/whitelist")
async def modsec_whitelist(data: WhitelistRequest, user: User = Depends(require_admin)):
    return await add_whitelist_rule(data.ip)
