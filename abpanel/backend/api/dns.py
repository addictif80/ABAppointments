"""DNS zone management API routes."""

from fastapi import APIRouter, Depends, HTTPException, status
from pydantic import BaseModel
from sqlalchemy import select
from sqlalchemy.ext.asyncio import AsyncSession

from backend.core.database import get_db
from backend.middleware.auth import get_current_user
from backend.models.user import User
from backend.models.website import DNSZone

router = APIRouter(prefix="/api/dns", tags=["dns"])


class CreateDNSRecordRequest(BaseModel):
    domain: str
    record_type: str
    name: str
    value: str
    ttl: int = 3600
    priority: int | None = None


@router.get("/{domain}")
async def get_dns_records(
    domain: str,
    user: User = Depends(get_current_user),
    db: AsyncSession = Depends(get_db),
):
    query = select(DNSZone).where(DNSZone.domain == domain)
    if user.role != "admin":
        query = query.where(DNSZone.user_id == user.id)

    result = await db.execute(query)
    records = result.scalars().all()

    return [
        {
            "id": r.id,
            "domain": r.domain,
            "type": r.record_type,
            "name": r.name,
            "value": r.value,
            "ttl": r.ttl,
            "priority": r.priority,
        }
        for r in records
    ]


@router.post("/", status_code=status.HTTP_201_CREATED)
async def create_dns_record(
    data: CreateDNSRecordRequest,
    user: User = Depends(get_current_user),
    db: AsyncSession = Depends(get_db),
):
    valid_types = {"A", "AAAA", "CNAME", "MX", "TXT", "NS", "SRV", "CAA"}
    if data.record_type.upper() not in valid_types:
        raise HTTPException(status_code=400, detail=f"Invalid record type. Must be one of: {valid_types}")

    record = DNSZone(
        domain=data.domain,
        record_type=data.record_type.upper(),
        name=data.name,
        value=data.value,
        ttl=data.ttl,
        priority=data.priority,
        user_id=user.id,
    )
    db.add(record)
    await db.flush()

    return {"id": record.id, "message": "DNS record created"}


@router.put("/{record_id}")
async def update_dns_record(
    record_id: int,
    data: CreateDNSRecordRequest,
    user: User = Depends(get_current_user),
    db: AsyncSession = Depends(get_db),
):
    result = await db.execute(select(DNSZone).where(DNSZone.id == record_id))
    record = result.scalar_one_or_none()

    if not record:
        raise HTTPException(status_code=404, detail="Record not found")
    if user.role != "admin" and record.user_id != user.id:
        raise HTTPException(status_code=403, detail="Access denied")

    record.record_type = data.record_type.upper()
    record.name = data.name
    record.value = data.value
    record.ttl = data.ttl
    record.priority = data.priority

    return {"message": "DNS record updated"}


@router.delete("/{record_id}")
async def delete_dns_record(
    record_id: int,
    user: User = Depends(get_current_user),
    db: AsyncSession = Depends(get_db),
):
    result = await db.execute(select(DNSZone).where(DNSZone.id == record_id))
    record = result.scalar_one_or_none()

    if not record:
        raise HTTPException(status_code=404, detail="Record not found")
    if user.role != "admin" and record.user_id != user.id:
        raise HTTPException(status_code=403, detail="Access denied")

    await db.delete(record)
    return {"message": "DNS record deleted"}
