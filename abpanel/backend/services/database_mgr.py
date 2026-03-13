"""MySQL/MariaDB database management."""

import asyncio
import secrets
import string

from backend.core.config import settings


def _generate_password(length: int = 24) -> str:
    alphabet = string.ascii_letters + string.digits + "!@#$%^&*"
    return "".join(secrets.choice(alphabet) for _ in range(length))


async def _run_mysql(sql: str) -> dict:
    proc = await asyncio.create_subprocess_exec(
        settings.MYSQL_BIN, "-u", "root", "-e", sql,
        stdout=asyncio.subprocess.PIPE,
        stderr=asyncio.subprocess.PIPE,
    )
    stdout, stderr = await proc.communicate()
    if proc.returncode == 0:
        return {"success": True, "output": stdout.decode().strip()}
    return {"success": False, "error": stderr.decode().strip()}


async def create_database(db_name: str, db_user: str | None = None) -> dict:
    if not db_name.isalnum() or len(db_name) > 64:
        return {"success": False, "error": "Invalid database name"}

    if db_user is None:
        db_user = db_name

    if not db_user.isalnum() or len(db_user) > 32:
        return {"success": False, "error": "Invalid username"}

    password = _generate_password()

    commands = [
        f"CREATE DATABASE IF NOT EXISTS `{db_name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;",
        f"CREATE USER IF NOT EXISTS '{db_user}'@'localhost' IDENTIFIED BY '{password}';",
        f"GRANT ALL PRIVILEGES ON `{db_name}`.* TO '{db_user}'@'localhost';",
        "FLUSH PRIVILEGES;",
    ]

    for cmd in commands:
        result = await _run_mysql(cmd)
        if not result["success"]:
            return result

    return {
        "success": True,
        "database": db_name,
        "username": db_user,
        "password": password,
    }


async def delete_database(db_name: str, db_user: str | None = None) -> dict:
    if not db_name.isalnum():
        return {"success": False, "error": "Invalid database name"}

    result = await _run_mysql(f"DROP DATABASE IF EXISTS `{db_name}`;")
    if not result["success"]:
        return result

    if db_user:
        await _run_mysql(f"DROP USER IF EXISTS '{db_user}'@'localhost';")
        await _run_mysql("FLUSH PRIVILEGES;")

    return {"success": True}


async def list_databases() -> dict:
    result = await _run_mysql("SHOW DATABASES;")
    if not result["success"]:
        return result

    system_dbs = {"information_schema", "mysql", "performance_schema", "sys"}
    databases = [
        db for db in result["output"].split("\n")[1:]
        if db and db not in system_dbs
    ]
    return {"success": True, "databases": databases}
