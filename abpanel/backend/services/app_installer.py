"""One-Click App Installer - Joomla, PrestaShop, Mautic, Laravel, etc."""

import asyncio
from pathlib import Path

from backend.core.config import settings

APPS = {
    "wordpress": {
        "name": "WordPress",
        "description": "CMS le plus populaire au monde",
        "icon": "fab fa-wordpress",
        "download_url": "https://wordpress.org/latest.tar.gz",
        "extract_dir": "wordpress",
    },
    "joomla": {
        "name": "Joomla",
        "description": "CMS flexible et extensible",
        "icon": "fab fa-joomla",
        "download_url": "https://downloads.joomla.org/cms/joomla5/5-2/Joomla_5-2-3-Stable-Full_Package.tar.gz",
        "extract_dir": None,
    },
    "prestashop": {
        "name": "PrestaShop",
        "description": "Solution e-commerce open source",
        "icon": "fas fa-shopping-cart",
        "download_url": "https://github.com/PrestaShop/PrestaShop/releases/download/8.2.0/prestashop_8.2.0.zip",
        "extract_dir": None,
    },
    "drupal": {
        "name": "Drupal",
        "description": "CMS enterprise puissant",
        "icon": "fab fa-drupal",
        "download_url": "https://www.drupal.org/download-latest/tar.gz",
        "extract_dir": None,
    },
    "mautic": {
        "name": "Mautic",
        "description": "Automatisation marketing open source",
        "icon": "fas fa-bullhorn",
        "download_url": "https://github.com/mautic/mautic/releases/latest/download/mautic.zip",
        "extract_dir": None,
    },
    "laravel": {
        "name": "Laravel",
        "description": "Framework PHP moderne",
        "icon": "fab fa-laravel",
        "composer": True,
        "command": "composer create-project laravel/laravel .",
    },
    "nextcloud": {
        "name": "Nextcloud",
        "description": "Cloud personnel auto-hébergé",
        "icon": "fas fa-cloud",
        "download_url": "https://download.nextcloud.com/server/releases/latest.tar.bz2",
        "extract_dir": "nextcloud",
    },
    "matomo": {
        "name": "Matomo",
        "description": "Analytics respectueux de la vie privée",
        "icon": "fas fa-chart-bar",
        "download_url": "https://builds.matomo.org/matomo-latest.tar.gz",
        "extract_dir": "matomo",
    },
    "mediawiki": {
        "name": "MediaWiki",
        "description": "Moteur de wiki (comme Wikipedia)",
        "icon": "fab fa-wikipedia-w",
        "download_url": "https://releases.wikimedia.org/mediawiki/1.42/mediawiki-1.42.3.tar.gz",
        "extract_dir": None,
    },
    "phpmyadmin": {
        "name": "phpMyAdmin",
        "description": "Interface web pour MySQL/MariaDB",
        "icon": "fas fa-database",
        "download_url": "https://files.phpmyadmin.net/phpMyAdmin/5.2.1/phpMyAdmin-5.2.1-all-languages.tar.gz",
        "extract_dir": None,
    },
}


async def get_available_apps() -> list[dict]:
    """Return list of available apps to install."""
    return [
        {"key": k, "name": v["name"], "description": v["description"], "icon": v["icon"]}
        for k, v in APPS.items()
    ]


async def install_app(app_key: str, domain: str) -> dict:
    """Install an application to a website's document root."""
    if app_key not in APPS:
        return {"success": False, "error": f"Application inconnue : {app_key}"}

    app = APPS[app_key]
    target = str(settings.WEB_ROOT / domain / "public_html")
    Path(target).mkdir(parents=True, exist_ok=True)

    try:
        if app.get("composer"):
            # Composer-based install
            proc = await asyncio.create_subprocess_shell(
                f"cd {target} && {app['command']}",
                stdout=asyncio.subprocess.PIPE,
                stderr=asyncio.subprocess.PIPE,
            )
            _, stderr = await proc.communicate()
            if proc.returncode != 0:
                return {"success": False, "error": stderr.decode()[:500]}
        else:
            # Download and extract
            url = app["download_url"]
            tmp_file = f"/tmp/abpanel_app_{app_key}"

            if url.endswith(".zip"):
                tmp_file += ".zip"
            elif url.endswith(".tar.bz2"):
                tmp_file += ".tar.bz2"
            else:
                tmp_file += ".tar.gz"

            # Download
            proc = await asyncio.create_subprocess_exec(
                "wget", "-qO", tmp_file, url,
                stdout=asyncio.subprocess.PIPE,
                stderr=asyncio.subprocess.PIPE,
            )
            _, stderr = await proc.communicate()
            if proc.returncode != 0:
                return {"success": False, "error": f"Échec du téléchargement : {stderr.decode()[:200]}"}

            # Extract
            if tmp_file.endswith(".zip"):
                proc = await asyncio.create_subprocess_exec(
                    "unzip", "-qo", tmp_file, "-d", target,
                    stdout=asyncio.subprocess.PIPE,
                    stderr=asyncio.subprocess.PIPE,
                )
            elif tmp_file.endswith(".tar.bz2"):
                proc = await asyncio.create_subprocess_exec(
                    "tar", "xjf", tmp_file, "--strip-components=1", "-C", target,
                    stdout=asyncio.subprocess.PIPE,
                    stderr=asyncio.subprocess.PIPE,
                )
            else:
                strip = "1" if app.get("extract_dir") else "0"
                proc = await asyncio.create_subprocess_exec(
                    "tar", "xzf", tmp_file, f"--strip-components={strip}", "-C", target,
                    stdout=asyncio.subprocess.PIPE,
                    stderr=asyncio.subprocess.PIPE,
                )

            _, stderr = await proc.communicate()
            if proc.returncode != 0:
                return {"success": False, "error": f"Échec de l'extraction : {stderr.decode()[:200]}"}

            # Cleanup
            Path(tmp_file).unlink(missing_ok=True)

        # Set permissions
        await asyncio.create_subprocess_exec("chown", "-R", "www-data:www-data", target)

        return {
            "success": True,
            "message": f"{app['name']} installé dans {target}",
            "url": f"https://{domain}",
        }

    except Exception as e:
        return {"success": False, "error": str(e)}
