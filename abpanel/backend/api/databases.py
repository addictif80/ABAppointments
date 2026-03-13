"""Database management API routes."""

from fastapi import APIRouter, Depends, HTTPException, status
from pydantic import BaseModel
from sqlalchemy import select
from sqlalchemy.ext.asyncio import AsyncSession

from backend.core.database import get_db
from backend.core.security import hash_password
from backend.middleware.auth import get_current_user
from backend.models.user import User
from backend.models.website import Database
from backend.services.database_mgr import create_database, delete_database, list_databases

router = APIRouter(prefix="/api/databases", tags=["databases"])


class CreateDatabaseRequest(BaseModel):
    name: str
    db_user: str | None = None
    website_id: int | None = None


@router.get("/")
async def get_databases(
    user: User = Depends(get_current_user),
    db: AsyncSession = Depends(get_db),
):
    if user.role == "admin":
        result = await db.execute(select(Database))
    else:
        result = await db.execute(select(Database).where(Database.user_id == user.id))

    databases = result.scalars().all()
    return [
        {
            "id": d.id,
            "name": d.name,
            "db_user": d.db_user,
            "website_id": d.website_id,
        }
        for d in databases
    ]


@router.post("/", status_code=status.HTTP_201_CREATED)
async def add_database(
    data: CreateDatabaseRequest,
    user: User = Depends(get_current_user),
    db: AsyncSession = Depends(get_db),
):
    result = await create_database(data.name, data.db_user)
    if not result["success"]:
        raise HTTPException(status_code=500, detail=result["error"])

    db_record = Database(
        name=result["database"],
        user_id=user.id,
        website_id=data.website_id,
        db_user=result["username"],
        db_password_hash=hash_password(result["password"]),
    )
    db.add(db_record)
    await db.flush()

    return {
        "id": db_record.id,
        "database": result["database"],
        "username": result["username"],
        "password": result["password"],
        "message": "Database created successfully",
    }


@router.delete("/{db_id}")
async def remove_database(
    db_id: int,
    user: User = Depends(get_current_user),
    db: AsyncSession = Depends(get_db),
):
    result = await db.execute(select(Database).where(Database.id == db_id))
    database = result.scalar_one_or_none()

    if not database:
        raise HTTPException(status_code=404, detail="Database not found")
    if user.role != "admin" and database.user_id != user.id:
        raise HTTPException(status_code=403, detail="Access denied")

    await delete_database(database.name, database.db_user)
    await db.delete(database)

    return {"message": f"Database {database.name} deleted"}
