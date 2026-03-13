"""Firewall management API routes."""

from fastapi import APIRouter, Depends
from pydantic import BaseModel

from backend.middleware.auth import require_admin
from backend.models.user import User
from backend.services.firewall import allow_ip, allow_port, deny_ip, deny_port, delete_rule, get_rules, get_status

router = APIRouter(prefix="/api/firewall", tags=["firewall"])


class PortRuleRequest(BaseModel):
    port: int
    proto: str = "tcp"


class IPRuleRequest(BaseModel):
    ip: str


class DeleteRuleRequest(BaseModel):
    rule_number: int


@router.get("/status")
async def firewall_status(user: User = Depends(require_admin)):
    return await get_status()


@router.get("/rules")
async def firewall_rules(user: User = Depends(require_admin)):
    return await get_rules()


@router.post("/allow-port")
async def api_allow_port(data: PortRuleRequest, user: User = Depends(require_admin)):
    return await allow_port(data.port, data.proto)


@router.post("/deny-port")
async def api_deny_port(data: PortRuleRequest, user: User = Depends(require_admin)):
    return await deny_port(data.port, data.proto)


@router.post("/allow-ip")
async def api_allow_ip(data: IPRuleRequest, user: User = Depends(require_admin)):
    return await allow_ip(data.ip)


@router.post("/deny-ip")
async def api_deny_ip(data: IPRuleRequest, user: User = Depends(require_admin)):
    return await deny_ip(data.ip)


@router.delete("/rule")
async def api_delete_rule(data: DeleteRuleRequest, user: User = Depends(require_admin)):
    return await delete_rule(data.rule_number)
