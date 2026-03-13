"""SSL certificate management API routes."""

from fastapi import APIRouter, Depends, HTTPException
from pydantic import BaseModel
from sqlalchemy import select
from sqlalchemy.ext.asyncio import AsyncSession

from backend.core.database import get_db
from backend.middleware.auth import get_current_user, require_admin
from backend.models.user import User
from backend.models.website import Website
from backend.services.nginx import create_vhost
from backend.services.ssl import issue_certificate, list_certificates, renew_certificates, revoke_certificate

router = APIRouter(prefix="/api/ssl", tags=["ssl"])


class IssueSSLRequest(BaseModel):
    domain: str
    email: str = "admin@localhost"


@router.post("/issue")
async def issue_ssl(
    data: IssueSSLRequest,
    user: User = Depends(get_current_user),
    db: AsyncSession = Depends(get_db),
):
    # Verify user owns the domain
    result = await db.execute(select(Website).where(Website.domain == data.domain))
    website = result.scalar_one_or_none()

    if not website:
        raise HTTPException(status_code=404, detail="Website not found")
    if user.role != "admin" and website.user_id != user.id:
        raise HTTPException(status_code=403, detail="Access denied")

    ssl_result = await issue_certificate(data.domain, data.email)
    if not ssl_result["success"]:
        raise HTTPException(status_code=500, detail=ssl_result["error"])

    # Reconfigure nginx with SSL
    await create_vhost(data.domain, website.php_version, ssl=True)

    website.ssl_enabled = True
    return ssl_result


@router.post("/renew")
async def renew_all(user: User = Depends(require_admin)):
    return await renew_certificates()


@router.get("/list")
async def get_certificates(user: User = Depends(get_current_user)):
    return await list_certificates()
