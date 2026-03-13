"""Email Debugger API routes."""

from fastapi import APIRouter, Depends
from pydantic import BaseModel

from backend.middleware.auth import require_admin
from backend.models.user import User
from backend.services.email_debug import (
    check_email_config,
    flush_mail_queue,
    purge_mail_queue,
    send_test_email,
)

router = APIRouter(prefix="/api/addons/email-debug", tags=["addons"])


class TestEmailRequest(BaseModel):
    from_addr: str
    to_addr: str
    subject: str = "ABPanel Test Email"


@router.get("/check")
async def email_check(user: User = Depends(require_admin)):
    return await check_email_config()


@router.post("/test")
async def email_test(data: TestEmailRequest, user: User = Depends(require_admin)):
    return await send_test_email(data.from_addr, data.to_addr, data.subject)


@router.post("/flush-queue")
async def email_flush(user: User = Depends(require_admin)):
    return await flush_mail_queue()


@router.post("/purge-queue")
async def email_purge(user: User = Depends(require_admin)):
    return await purge_mail_queue()
