"""Website management API routes."""

from fastapi import APIRouter, Depends, HTTPException, status
from pydantic import BaseModel
from sqlalchemy import select
from sqlalchemy.ext.asyncio import AsyncSession

from backend.core.database import get_db
from backend.middleware.auth import get_current_user, require_admin
from backend.models.user import User
from backend.models.website import Website
from backend.services.nginx import create_vhost, delete_vhost

router = APIRouter(prefix="/api/websites", tags=["websites"])


class CreateWebsiteRequest(BaseModel):
    domain: str
    php_version: str = "8.3"


class WebsiteResponse(BaseModel):
    id: int
    domain: str
    document_root: str
    php_version: str
    ssl_enabled: bool
    is_active: bool


@router.get("/")
async def list_websites(
    user: User = Depends(get_current_user),
    db: AsyncSession = Depends(get_db),
):
    if user.role == "admin":
        result = await db.execute(select(Website))
    else:
        result = await db.execute(select(Website).where(Website.user_id == user.id))

    websites = result.scalars().all()
    return [
        {
            "id": w.id,
            "domain": w.domain,
            "document_root": w.document_root,
            "php_version": w.php_version,
            "ssl_enabled": w.ssl_enabled,
            "is_active": w.is_active,
        }
        for w in websites
    ]


@router.post("/", status_code=status.HTTP_201_CREATED)
async def create_website(
    data: CreateWebsiteRequest,
    user: User = Depends(get_current_user),
    db: AsyncSession = Depends(get_db),
):
    # Check if domain already exists
    existing = await db.execute(select(Website).where(Website.domain == data.domain))
    if existing.scalar_one_or_none():
        raise HTTPException(status_code=400, detail="Domain already exists")

    # Create nginx vhost
    result = await create_vhost(data.domain, data.php_version)
    if not result["success"]:
        raise HTTPException(status_code=500, detail=result["error"])

    website = Website(
        domain=data.domain,
        user_id=user.id,
        document_root=result["document_root"],
        php_version=data.php_version,
    )
    db.add(website)
    await db.flush()

    return {
        "id": website.id,
        "domain": website.domain,
        "document_root": website.document_root,
        "message": "Website created successfully",
    }


@router.delete("/{website_id}")
async def remove_website(
    website_id: int,
    user: User = Depends(get_current_user),
    db: AsyncSession = Depends(get_db),
):
    result = await db.execute(select(Website).where(Website.id == website_id))
    website = result.scalar_one_or_none()

    if not website:
        raise HTTPException(status_code=404, detail="Website not found")
    if user.role != "admin" and website.user_id != user.id:
        raise HTTPException(status_code=403, detail="Access denied")

    await delete_vhost(website.domain)
    await db.delete(website)

    return {"message": f"Website {website.domain} deleted"}
