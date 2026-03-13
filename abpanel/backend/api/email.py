"""Email management API routes."""

from fastapi import APIRouter, Depends, HTTPException, status
from pydantic import BaseModel
from sqlalchemy import select
from sqlalchemy.ext.asyncio import AsyncSession

from backend.core.database import get_db
from backend.core.security import hash_password
from backend.middleware.auth import get_current_user
from backend.models.user import User
from backend.models.website import EmailAccount

router = APIRouter(prefix="/api/email", tags=["email"])


class CreateEmailRequest(BaseModel):
    email: str
    password: str
    quota_mb: int = 1024


@router.get("/")
async def list_email_accounts(
    user: User = Depends(get_current_user),
    db: AsyncSession = Depends(get_db),
):
    if user.role == "admin":
        result = await db.execute(select(EmailAccount))
    else:
        result = await db.execute(select(EmailAccount).where(EmailAccount.user_id == user.id))

    accounts = result.scalars().all()
    return [
        {
            "id": a.id,
            "email": a.email,
            "domain": a.domain,
            "quota_mb": a.quota_mb,
            "is_active": a.is_active,
        }
        for a in accounts
    ]


@router.post("/", status_code=status.HTTP_201_CREATED)
async def create_email_account(
    data: CreateEmailRequest,
    user: User = Depends(get_current_user),
    db: AsyncSession = Depends(get_db),
):
    if "@" not in data.email:
        raise HTTPException(status_code=400, detail="Invalid email format")

    domain = data.email.split("@")[1]

    existing = await db.execute(select(EmailAccount).where(EmailAccount.email == data.email))
    if existing.scalar_one_or_none():
        raise HTTPException(status_code=400, detail="Email already exists")

    account = EmailAccount(
        email=data.email,
        domain=domain,
        password_hash=hash_password(data.password),
        quota_mb=data.quota_mb,
        user_id=user.id,
    )
    db.add(account)
    await db.flush()

    return {"id": account.id, "email": account.email, "message": "Email account created"}


@router.delete("/{account_id}")
async def delete_email_account(
    account_id: int,
    user: User = Depends(get_current_user),
    db: AsyncSession = Depends(get_db),
):
    result = await db.execute(select(EmailAccount).where(EmailAccount.id == account_id))
    account = result.scalar_one_or_none()

    if not account:
        raise HTTPException(status_code=404, detail="Email account not found")
    if user.role != "admin" and account.user_id != user.id:
        raise HTTPException(status_code=403, detail="Access denied")

    await db.delete(account)
    return {"message": f"Email account {account.email} deleted"}
