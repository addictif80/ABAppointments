"""Remote backup service - S3, Google Drive, SFTP."""

import asyncio
import json
from pathlib import Path

from backend.core.config import settings

REMOTE_CONF = settings.DATA_DIR / "remote_backup.json"


def _load_config() -> dict:
    if REMOTE_CONF.exists():
        return json.loads(REMOTE_CONF.read_text())
    return {}


def _save_config(config: dict):
    REMOTE_CONF.parent.mkdir(parents=True, exist_ok=True)
    REMOTE_CONF.write_text(json.dumps(config, indent=2))


async def configure_s3(bucket: str, access_key: str, secret_key: str,
                       region: str = "us-east-1", endpoint: str | None = None) -> dict:
    """Configure S3 remote backup destination."""
    config = _load_config()
    config["s3"] = {
        "type": "s3",
        "bucket": bucket,
        "access_key": access_key,
        "secret_key": secret_key,
        "region": region,
        "endpoint": endpoint,
    }
    _save_config(config)
    return {"success": True, "message": "S3 backup configured"}


async def configure_sftp(host: str, port: int, username: str,
                         password: str | None = None, key_path: str | None = None,
                         remote_path: str = "/backups") -> dict:
    """Configure SFTP remote backup destination."""
    config = _load_config()
    config["sftp"] = {
        "type": "sftp",
        "host": host,
        "port": port,
        "username": username,
        "password": password,
        "key_path": key_path,
        "remote_path": remote_path,
    }
    _save_config(config)
    return {"success": True, "message": "SFTP backup configured"}


async def configure_gdrive(credentials_json: str) -> dict:
    """Configure Google Drive remote backup."""
    config = _load_config()
    cred_path = settings.DATA_DIR / "gdrive_credentials.json"
    cred_path.write_text(credentials_json)
    config["gdrive"] = {
        "type": "gdrive",
        "credentials_path": str(cred_path),
    }
    _save_config(config)
    return {"success": True, "message": "Google Drive backup configured"}


async def get_configured_remotes() -> dict:
    """List configured remote backup destinations."""
    config = _load_config()
    remotes = []
    for key, val in config.items():
        remote = {"type": val["type"]}
        if val["type"] == "s3":
            remote["bucket"] = val["bucket"]
            remote["region"] = val["region"]
        elif val["type"] == "sftp":
            remote["host"] = val["host"]
            remote["remote_path"] = val["remote_path"]
        elif val["type"] == "gdrive":
            remote["status"] = "configured"
        remotes.append(remote)

    return {"success": True, "remotes": remotes}


async def upload_to_s3(local_path: str, remote_key: str) -> dict:
    """Upload a file to S3."""
    config = _load_config()
    s3_conf = config.get("s3")
    if not s3_conf:
        return {"success": False, "error": "S3 not configured"}

    env = {
        "AWS_ACCESS_KEY_ID": s3_conf["access_key"],
        "AWS_SECRET_ACCESS_KEY": s3_conf["secret_key"],
        "AWS_DEFAULT_REGION": s3_conf["region"],
    }

    cmd = ["aws", "s3", "cp", local_path, f"s3://{s3_conf['bucket']}/{remote_key}"]
    if s3_conf.get("endpoint"):
        cmd.extend(["--endpoint-url", s3_conf["endpoint"]])

    try:
        proc = await asyncio.create_subprocess_exec(
            *cmd,
            env={**dict(__import__("os").environ), **env},
            stdout=asyncio.subprocess.PIPE,
            stderr=asyncio.subprocess.PIPE,
        )
        _, stderr = await proc.communicate()
        if proc.returncode == 0:
            return {"success": True, "message": f"Uploaded to S3: {remote_key}"}
        return {"success": False, "error": stderr.decode()[:500]}
    except Exception as e:
        return {"success": False, "error": str(e)}


async def upload_to_sftp(local_path: str, remote_filename: str) -> dict:
    """Upload a file via SFTP."""
    config = _load_config()
    sftp_conf = config.get("sftp")
    if not sftp_conf:
        return {"success": False, "error": "SFTP not configured"}

    remote_full = f"{sftp_conf['remote_path']}/{remote_filename}"

    cmd = ["scp", "-P", str(sftp_conf["port"])]
    if sftp_conf.get("key_path"):
        cmd.extend(["-i", sftp_conf["key_path"]])
    cmd.extend([local_path, f"{sftp_conf['username']}@{sftp_conf['host']}:{remote_full}"])

    try:
        proc = await asyncio.create_subprocess_exec(
            *cmd,
            stdout=asyncio.subprocess.PIPE,
            stderr=asyncio.subprocess.PIPE,
        )
        _, stderr = await proc.communicate()
        if proc.returncode == 0:
            return {"success": True, "message": f"Uploaded via SFTP: {remote_full}"}
        return {"success": False, "error": stderr.decode()[:500]}
    except Exception as e:
        return {"success": False, "error": str(e)}


async def backup_and_upload(domain: str, destination: str = "s3") -> dict:
    """Create a backup and upload it to remote destination."""
    from backend.services.backup import create_backup

    # Create local backup
    backup_result = await create_backup(domain, "full")
    if not backup_result.get("success"):
        return backup_result

    backup_path = backup_result["path"]
    backup_name = backup_result["backup_name"]

    # Compress for upload
    archive = f"/tmp/{backup_name}.tar.gz"
    proc = await asyncio.create_subprocess_exec(
        "tar", "czf", archive, "-C", str(Path(backup_path).parent), backup_name,
        stdout=asyncio.subprocess.PIPE,
        stderr=asyncio.subprocess.PIPE,
    )
    await proc.communicate()

    # Upload
    if destination == "s3":
        result = await upload_to_s3(archive, f"backups/{backup_name}.tar.gz")
    elif destination == "sftp":
        result = await upload_to_sftp(archive, f"{backup_name}.tar.gz")
    else:
        result = {"success": False, "error": f"Unknown destination: {destination}"}

    # Cleanup
    Path(archive).unlink(missing_ok=True)

    return result
