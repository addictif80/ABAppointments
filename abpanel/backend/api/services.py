"""Service management API routes."""

from fastapi import APIRouter, Depends
from pydantic import BaseModel

from backend.middleware.auth import require_admin
from backend.models.user import User
from backend.services.system import get_all_services_status, manage_service

router = APIRouter(prefix="/api/services", tags=["services"])


class ServiceActionRequest(BaseModel):
    service: str
    action: str  # start, stop, restart, reload


@router.get("/")
async def list_services(user: User = Depends(require_admin)):
    return await get_all_services_status()


@router.post("/manage")
async def api_manage_service(data: ServiceActionRequest, user: User = Depends(require_admin)):
    return await manage_service(data.service, data.action)
