"""WordPress Manager service."""

import asyncio
import json
import secrets
import string
from pathlib import Path

from backend.core.config import settings


async def _run_wp_cli(site_path: str, *args: str) -> dict:
    """Run WP-CLI command."""
    cmd = ["wp", "--path=" + site_path, "--allow-root", *args]
    try:
        proc = await asyncio.create_subprocess_exec(
            *cmd,
            stdout=asyncio.subprocess.PIPE,
            stderr=asyncio.subprocess.PIPE,
        )
        stdout, stderr = await proc.communicate()
        if proc.returncode == 0:
            return {"success": True, "output": stdout.decode().strip()}
        return {"success": False, "error": stderr.decode().strip()}
    except FileNotFoundError:
        return {"success": False, "error": "WP-CLI not installed. Run: curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar && chmod +x wp-cli.phar && mv wp-cli.phar /usr/local/bin/wp"}
    except Exception as e:
        return {"success": False, "error": str(e)}


async def install_wp_cli() -> dict:
    """Install WP-CLI if not present."""
    try:
        proc = await asyncio.create_subprocess_exec(
            "which", "wp",
            stdout=asyncio.subprocess.PIPE,
            stderr=asyncio.subprocess.PIPE,
        )
        await proc.communicate()
        if proc.returncode == 0:
            return {"success": True, "message": "WP-CLI already installed"}

        commands = [
            "curl -sO https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar",
            "chmod +x wp-cli.phar",
            "mv wp-cli.phar /usr/local/bin/wp",
        ]
        for cmd in commands:
            proc = await asyncio.create_subprocess_shell(
                cmd, stdout=asyncio.subprocess.PIPE, stderr=asyncio.subprocess.PIPE,
            )
            await proc.communicate()

        return {"success": True, "message": "WP-CLI installed"}
    except Exception as e:
        return {"success": False, "error": str(e)}


async def install_wordpress(domain: str, site_title: str, admin_user: str,
                            admin_password: str, admin_email: str,
                            db_name: str, db_user: str, db_password: str) -> dict:
    """One-click WordPress installation."""
    site_path = str(settings.WEB_ROOT / domain / "public_html")
    Path(site_path).mkdir(parents=True, exist_ok=True)

    # Download WordPress
    result = await _run_wp_cli(site_path, "core", "download", "--locale=fr_FR")
    if not result["success"] and "already present" not in result.get("error", ""):
        return result

    # Generate config
    result = await _run_wp_cli(
        site_path, "config", "create",
        f"--dbname={db_name}", f"--dbuser={db_user}", f"--dbpass={db_password}",
        "--dbhost=localhost", "--dbcharset=utf8mb4",
    )
    if not result["success"]:
        return result

    # Install
    result = await _run_wp_cli(
        site_path, "core", "install",
        f"--url=https://{domain}",
        f"--title={site_title}",
        f"--admin_user={admin_user}",
        f"--admin_password={admin_password}",
        f"--admin_email={admin_email}",
    )
    if not result["success"]:
        return result

    # Set permissions
    await asyncio.create_subprocess_exec("chown", "-R", "www-data:www-data", site_path)

    return {
        "success": True,
        "message": f"WordPress installed for {domain}",
        "url": f"https://{domain}",
        "admin_url": f"https://{domain}/wp-admin",
    }


async def get_wp_info(domain: str) -> dict:
    """Get WordPress site information."""
    site_path = str(settings.WEB_ROOT / domain / "public_html")

    if not Path(site_path, "wp-config.php").exists():
        return {"installed": False}

    version = await _run_wp_cli(site_path, "core", "version")
    plugins = await _run_wp_cli(site_path, "plugin", "list", "--format=json")
    themes = await _run_wp_cli(site_path, "theme", "list", "--format=json")

    info = {
        "installed": True,
        "version": version.get("output", "unknown"),
        "plugins": [],
        "themes": [],
    }

    if plugins.get("success"):
        try:
            info["plugins"] = json.loads(plugins["output"])
        except json.JSONDecodeError:
            pass

    if themes.get("success"):
        try:
            info["themes"] = json.loads(themes["output"])
        except json.JSONDecodeError:
            pass

    return info


async def update_wordpress(domain: str) -> dict:
    """Update WordPress core, plugins and themes."""
    site_path = str(settings.WEB_ROOT / domain / "public_html")
    results = {}

    # Update core
    results["core"] = await _run_wp_cli(site_path, "core", "update")
    results["db"] = await _run_wp_cli(site_path, "core", "update-db")
    results["plugins"] = await _run_wp_cli(site_path, "plugin", "update", "--all")
    results["themes"] = await _run_wp_cli(site_path, "theme", "update", "--all")

    return {"success": True, "results": results}


async def create_staging(domain: str) -> dict:
    """Create a staging copy of a WordPress site."""
    source = settings.WEB_ROOT / domain / "public_html"
    staging_domain = f"staging.{domain}"
    staging_path = settings.WEB_ROOT / staging_domain / "public_html"

    if not source.exists():
        return {"success": False, "error": "Source site not found"}

    try:
        staging_path.parent.mkdir(parents=True, exist_ok=True)

        # Copy files
        proc = await asyncio.create_subprocess_exec(
            "cp", "-a", str(source), str(staging_path),
            stdout=asyncio.subprocess.PIPE, stderr=asyncio.subprocess.PIPE,
        )
        await proc.communicate()

        # Update WordPress URL
        await _run_wp_cli(
            str(staging_path),
            "search-replace", f"https://{domain}", f"https://{staging_domain}",
        )

        return {
            "success": True,
            "staging_domain": staging_domain,
            "message": f"Staging site created at {staging_domain}",
        }
    except Exception as e:
        return {"success": False, "error": str(e)}


async def install_plugin(domain: str, plugin_slug: str) -> dict:
    """Install and activate a WordPress plugin."""
    site_path = str(settings.WEB_ROOT / domain / "public_html")
    return await _run_wp_cli(site_path, "plugin", "install", plugin_slug, "--activate")


async def delete_plugin(domain: str, plugin_slug: str) -> dict:
    """Deactivate and delete a WordPress plugin."""
    site_path = str(settings.WEB_ROOT / domain / "public_html")
    await _run_wp_cli(site_path, "plugin", "deactivate", plugin_slug)
    return await _run_wp_cli(site_path, "plugin", "delete", plugin_slug)
