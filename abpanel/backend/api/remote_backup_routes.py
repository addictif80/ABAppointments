"""Remote Backup API routes."""

from fastapi import APIRouter, Depends
from pydantic import BaseModel

from backend.middleware.auth import require_admin
from backend.models.user import User
from backend.services.remote_backup import (
    backup_and_upload,
    configure_s3,
    configure_sftp,
    get_configured_remotes,
)

router = APIRouter(prefix="/api/addons/remote-backup", tags=["addons"])


class S3ConfigRequest(BaseModel):
    bucket: str
    access_key: str
    secret_key: str
    region: str = "us-east-1"
    endpoint: str | None = None


class SFTPConfigRequest(BaseModel):
    host: str
    port: int = 22
    username: str
    password: str | None = None
    key_path: str | None = None
    remote_path: str = "/backups"


class RemoteBackupRequest(BaseModel):
    domain: str
    destination: str = "s3"


@router.get("/remotes")
async def get_remotes(user: User = Depends(require_admin)):
    return await get_configured_remotes()


@router.post("/configure/s3")
async def config_s3(data: S3ConfigRequest, user: User = Depends(require_admin)):
    return await configure_s3(data.bucket, data.access_key, data.secret_key, data.region, data.endpoint)


@router.post("/configure/sftp")
async def config_sftp(data: SFTPConfigRequest, user: User = Depends(require_admin)):
    return await configure_sftp(data.host, data.port, data.username, data.password, data.key_path, data.remote_path)


@router.post("/backup")
async def remote_backup(data: RemoteBackupRequest, user: User = Depends(require_admin)):
    return await backup_and_upload(data.domain, data.destination)
