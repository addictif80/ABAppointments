"""phpMyAdmin integration service."""

import asyncio
import os
from pathlib import Path

from backend.core.config import settings

PHPMYADMIN_DIR = Path("/opt/abpanel/addons/phpmyadmin")
PHPMYADMIN_VERSION = "5.2.1"
PHPMYADMIN_URL = f"https://files.phpmyadmin.net/phpMyAdmin/{PHPMYADMIN_VERSION}/phpMyAdmin-{PHPMYADMIN_VERSION}-all-languages.tar.gz"

NGINX_PMA_CONFIG = """
location /phpmyadmin {{
    alias {pma_dir}/;
    index index.php;

    location ~ \\.php$ {{
        fastcgi_pass unix:/var/run/php/php{php_version}-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $request_filename;
        include fastcgi_params;
    }}
}}
"""


async def install_phpmyadmin() -> dict:
    """Download and install phpMyAdmin."""
    try:
        PHPMYADMIN_DIR.mkdir(parents=True, exist_ok=True)

        # Download
        proc = await asyncio.create_subprocess_exec(
            "wget", "-qO", "/tmp/phpmyadmin.tar.gz", PHPMYADMIN_URL,
            stdout=asyncio.subprocess.PIPE,
            stderr=asyncio.subprocess.PIPE,
        )
        _, stderr = await proc.communicate()
        if proc.returncode != 0:
            return {"success": False, "error": f"Download failed: {stderr.decode()}"}

        # Extract
        proc = await asyncio.create_subprocess_exec(
            "tar", "xzf", "/tmp/phpmyadmin.tar.gz",
            "--strip-components=1", "-C", str(PHPMYADMIN_DIR),
            stdout=asyncio.subprocess.PIPE,
            stderr=asyncio.subprocess.PIPE,
        )
        await proc.communicate()

        # Generate blowfish secret
        import secrets
        blowfish = secrets.token_hex(16)

        # Create config
        config_content = f"""<?php
$cfg['blowfish_secret'] = '{blowfish}';
$cfg['Servers'][1]['auth_type'] = 'cookie';
$cfg['Servers'][1]['host'] = 'localhost';
$cfg['Servers'][1]['compress'] = false;
$cfg['Servers'][1]['AllowNoPassword'] = false;
$cfg['UploadDir'] = '';
$cfg['SaveDir'] = '';
$cfg['TempDir'] = '/tmp';
$cfg['DefaultLang'] = 'fr';
?>
"""
        (PHPMYADMIN_DIR / "config.inc.php").write_text(config_content)

        # Set permissions
        await asyncio.create_subprocess_exec(
            "chown", "-R", "www-data:www-data", str(PHPMYADMIN_DIR),
        )

        # Cleanup
        Path("/tmp/phpmyadmin.tar.gz").unlink(missing_ok=True)

        return {"success": True, "message": f"phpMyAdmin {PHPMYADMIN_VERSION} installed", "path": str(PHPMYADMIN_DIR)}

    except Exception as e:
        return {"success": False, "error": str(e)}


async def get_status() -> dict:
    """Check if phpMyAdmin is installed."""
    installed = (PHPMYADMIN_DIR / "index.php").exists()
    version = None
    if installed:
        version_file = PHPMYADMIN_DIR / "VERSION"
        if version_file.exists():
            version = version_file.read_text().strip()
    return {"installed": installed, "version": version or PHPMYADMIN_VERSION if installed else None, "path": str(PHPMYADMIN_DIR)}


async def uninstall_phpmyadmin() -> dict:
    """Remove phpMyAdmin."""
    try:
        if PHPMYADMIN_DIR.exists():
            proc = await asyncio.create_subprocess_exec(
                "rm", "-rf", str(PHPMYADMIN_DIR),
            )
            await proc.communicate()
        return {"success": True, "message": "phpMyAdmin uninstalled"}
    except Exception as e:
        return {"success": False, "error": str(e)}
