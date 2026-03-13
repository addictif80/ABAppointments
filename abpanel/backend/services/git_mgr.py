"""Git Manager service - deploy from repositories."""

import asyncio
from pathlib import Path

from backend.core.config import settings


async def _run_git(cwd: str, *args: str) -> dict:
    try:
        proc = await asyncio.create_subprocess_exec(
            "git", *args,
            cwd=cwd,
            stdout=asyncio.subprocess.PIPE,
            stderr=asyncio.subprocess.PIPE,
        )
        stdout, stderr = await proc.communicate()
        if proc.returncode == 0:
            return {"success": True, "output": stdout.decode().strip()}
        return {"success": False, "error": stderr.decode().strip()}
    except Exception as e:
        return {"success": False, "error": str(e)}


async def clone_repo(repo_url: str, domain: str, branch: str = "main",
                     deploy_path: str | None = None) -> dict:
    """Clone a git repository to a website's document root."""
    target = deploy_path or str(settings.WEB_ROOT / domain / "public_html")
    target_path = Path(target)

    if target_path.exists() and any(target_path.iterdir()):
        # Directory not empty, try pulling instead
        git_dir = target_path / ".git"
        if git_dir.exists():
            return await pull_repo(domain, deploy_path)
        return {"success": False, "error": "Target directory is not empty and not a git repo"}

    target_path.mkdir(parents=True, exist_ok=True)

    result = await _run_git(
        str(target_path.parent),
        "clone", "--branch", branch, "--single-branch", repo_url, str(target_path),
    )

    if result["success"]:
        await asyncio.create_subprocess_exec("chown", "-R", "www-data:www-data", target)

    return result


async def pull_repo(domain: str, deploy_path: str | None = None) -> dict:
    """Pull latest changes from remote."""
    target = deploy_path or str(settings.WEB_ROOT / domain / "public_html")

    if not Path(target, ".git").exists():
        return {"success": False, "error": "Not a git repository"}

    return await _run_git(target, "pull", "--ff-only")


async def get_repo_info(domain: str, deploy_path: str | None = None) -> dict:
    """Get repository information."""
    target = deploy_path or str(settings.WEB_ROOT / domain / "public_html")

    if not Path(target, ".git").exists():
        return {"has_repo": False}

    branch = await _run_git(target, "branch", "--show-current")
    remote = await _run_git(target, "remote", "get-url", "origin")
    log = await _run_git(target, "log", "--oneline", "-10")
    status = await _run_git(target, "status", "--short")

    return {
        "has_repo": True,
        "branch": branch.get("output", "unknown"),
        "remote_url": remote.get("output", "unknown"),
        "recent_commits": log.get("output", "").split("\n") if log.get("output") else [],
        "status": status.get("output", ""),
    }


async def checkout_branch(domain: str, branch: str, deploy_path: str | None = None) -> dict:
    """Checkout a specific branch."""
    target = deploy_path or str(settings.WEB_ROOT / domain / "public_html")
    return await _run_git(target, "checkout", branch)


async def list_branches(domain: str, deploy_path: str | None = None) -> dict:
    """List all branches."""
    target = deploy_path or str(settings.WEB_ROOT / domain / "public_html")

    # Fetch remote branches
    await _run_git(target, "fetch", "--all")

    result = await _run_git(target, "branch", "-a")
    if not result["success"]:
        return result

    branches = [b.strip().lstrip("* ") for b in result["output"].split("\n") if b.strip()]
    return {"success": True, "branches": branches}


async def setup_webhook_deploy(domain: str, branch: str = "main",
                               deploy_path: str | None = None) -> dict:
    """Generate a webhook URL for auto-deployment."""
    import secrets
    token = secrets.token_urlsafe(32)
    return {
        "success": True,
        "webhook_url": f"/api/git/webhook/{domain}?token={token}",
        "token": token,
        "branch": branch,
        "message": "Configure this URL in your Git provider's webhook settings",
    }
