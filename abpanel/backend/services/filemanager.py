"""File manager service."""

import os
import shutil
from pathlib import Path

from backend.core.config import settings


def _validate_path(base: str, requested: str) -> Path:
    """Ensure the resolved path is within the allowed base directory."""
    base_path = Path(base).resolve()
    full_path = (base_path / requested).resolve()
    if not str(full_path).startswith(str(base_path)):
        raise PermissionError("Access denied: path traversal detected")
    return full_path


def list_directory(base_path: str, relative_path: str = "") -> dict:
    try:
        target = _validate_path(base_path, relative_path)
        if not target.is_dir():
            return {"success": False, "error": "Not a directory"}

        items = []
        for entry in sorted(target.iterdir(), key=lambda e: (not e.is_dir(), e.name.lower())):
            stat = entry.stat()
            items.append({
                "name": entry.name,
                "type": "directory" if entry.is_dir() else "file",
                "size": stat.st_size if entry.is_file() else 0,
                "modified": stat.st_mtime,
                "permissions": oct(stat.st_mode)[-3:],
            })

        return {"success": True, "path": relative_path or "/", "items": items}
    except PermissionError as e:
        return {"success": False, "error": str(e)}
    except Exception as e:
        return {"success": False, "error": str(e)}


def read_file(base_path: str, relative_path: str) -> dict:
    try:
        target = _validate_path(base_path, relative_path)
        if not target.is_file():
            return {"success": False, "error": "Not a file"}
        if target.stat().st_size > 5 * 1024 * 1024:  # 5MB limit
            return {"success": False, "error": "File too large to read (max 5MB)"}
        content = target.read_text(errors="replace")
        return {"success": True, "content": content, "path": relative_path}
    except PermissionError as e:
        return {"success": False, "error": str(e)}
    except Exception as e:
        return {"success": False, "error": str(e)}


def write_file(base_path: str, relative_path: str, content: str) -> dict:
    try:
        target = _validate_path(base_path, relative_path)
        target.parent.mkdir(parents=True, exist_ok=True)
        target.write_text(content)
        return {"success": True, "path": relative_path}
    except PermissionError as e:
        return {"success": False, "error": str(e)}
    except Exception as e:
        return {"success": False, "error": str(e)}


def create_directory(base_path: str, relative_path: str) -> dict:
    try:
        target = _validate_path(base_path, relative_path)
        target.mkdir(parents=True, exist_ok=True)
        return {"success": True, "path": relative_path}
    except PermissionError as e:
        return {"success": False, "error": str(e)}
    except Exception as e:
        return {"success": False, "error": str(e)}


def delete_item(base_path: str, relative_path: str) -> dict:
    try:
        target = _validate_path(base_path, relative_path)
        if not target.exists():
            return {"success": False, "error": "Path does not exist"}
        if target.is_dir():
            shutil.rmtree(target)
        else:
            target.unlink()
        return {"success": True}
    except PermissionError as e:
        return {"success": False, "error": str(e)}
    except Exception as e:
        return {"success": False, "error": str(e)}


def rename_item(base_path: str, old_path: str, new_name: str) -> dict:
    try:
        source = _validate_path(base_path, old_path)
        dest = _validate_path(base_path, str(source.parent.relative_to(Path(base_path).resolve()) / new_name))
        if not source.exists():
            return {"success": False, "error": "Source does not exist"}
        source.rename(dest)
        return {"success": True}
    except PermissionError as e:
        return {"success": False, "error": str(e)}
    except Exception as e:
        return {"success": False, "error": str(e)}


def change_permissions(base_path: str, relative_path: str, mode: str) -> dict:
    try:
        target = _validate_path(base_path, relative_path)
        if not target.exists():
            return {"success": False, "error": "Path does not exist"}
        os.chmod(target, int(mode, 8))
        return {"success": True}
    except PermissionError as e:
        return {"success": False, "error": str(e)}
    except Exception as e:
        return {"success": False, "error": str(e)}
