"""FTP account management API routes."""

from fastapi import APIRouter, Depends, HTTPException, status
from pydantic import BaseModel
from sqlalchemy import select
from sqlalchemy.ext.asyncio import AsyncSession

from backend.core.database import get_db
from backend.core.security import hash_password
from backend.middleware.auth import get_current_user
from backend.models.user import User
from backend.models.website import FTPAccount

router = APIRouter(prefix="/api/ftp", tags=["ftp"])


class CreateFTPRequest(BaseModel):
    username: str
    password: str
    home_directory: str
    website_id: int | None = None


@router.get("/")
async def list_ftp_accounts(
    user: User = Depends(get_current_user),
    db: AsyncSession = Depends(get_db),
):
    if user.role == "admin":
        result = await db.execute(select(FTPAccount))
    else:
        result = await db.execute(select(FTPAccount).where(FTPAccount.user_id == user.id))

    accounts = result.scalars().all()
    return [
        {
            "id": a.id,
            "username": a.username,
            "home_directory": a.home_directory,
            "website_id": a.website_id,
            "is_active": a.is_active,
        }
        for a in accounts
    ]


@router.post("/", status_code=status.HTTP_201_CREATED)
async def create_ftp_account(
    data: CreateFTPRequest,
    user: User = Depends(get_current_user),
    db: AsyncSession = Depends(get_db),
):
    existing = await db.execute(select(FTPAccount).where(FTPAccount.username == data.username))
    if existing.scalar_one_or_none():
        raise HTTPException(status_code=400, detail="FTP username already exists")

    account = FTPAccount(
        username=data.username,
        password_hash=hash_password(data.password),
        home_directory=data.home_directory,
        user_id=user.id,
        website_id=data.website_id,
    )
    db.add(account)
    await db.flush()

    return {"id": account.id, "username": account.username, "message": "FTP account created"}


@router.delete("/{account_id}")
async def delete_ftp_account(
    account_id: int,
    user: User = Depends(get_current_user),
    db: AsyncSession = Depends(get_db),
):
    result = await db.execute(select(FTPAccount).where(FTPAccount.id == account_id))
    account = result.scalar_one_or_none()

    if not account:
        raise HTTPException(status_code=404, detail="FTP account not found")
    if user.role != "admin" and account.user_id != user.id:
        raise HTTPException(status_code=403, detail="Access denied")

    await db.delete(account)
    return {"message": f"FTP account {account.username} deleted"}
