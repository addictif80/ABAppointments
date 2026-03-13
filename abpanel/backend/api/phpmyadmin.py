"""phpMyAdmin API routes."""

from fastapi import APIRouter, Depends

from backend.middleware.auth import require_admin
from backend.models.user import User
from backend.services.phpmyadmin import get_status, install_phpmyadmin, uninstall_phpmyadmin

router = APIRouter(prefix="/api/addons/phpmyadmin", tags=["addons"])


@router.get("/status")
async def pma_status(user: User = Depends(require_admin)):
    return await get_status()


@router.post("/install")
async def pma_install(user: User = Depends(require_admin)):
    return await install_phpmyadmin()


@router.post("/uninstall")
async def pma_uninstall(user: User = Depends(require_admin)):
    return await uninstall_phpmyadmin()
