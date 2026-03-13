"""Nginx virtual host management."""

import asyncio
from pathlib import Path

from backend.core.config import settings

VHOST_TEMPLATE = """server {{
    listen 80;
    listen [::]:80;
    server_name {domain} www.{domain};
    root {document_root};
    index index.php index.html index.htm;

    access_log /var/log/nginx/{domain}.access.log;
    error_log /var/log/nginx/{domain}.error.log;

    location / {{
        try_files $uri $uri/ /index.php?$query_string;
    }}

    location ~ \\.php$ {{
        fastcgi_pass unix:/var/run/php/php{php_version}-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_index index.php;
    }}

    location ~ /\\.ht {{
        deny all;
    }}

    location ~* \\.(jpg|jpeg|png|gif|ico|css|js|woff2?)$ {{
        expires 30d;
        add_header Cache-Control "public, no-transform";
    }}
}}
"""

VHOST_SSL_TEMPLATE = """server {{
    listen 80;
    listen [::]:80;
    server_name {domain} www.{domain};
    return 301 https://$host$request_uri;
}}

server {{
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name {domain} www.{domain};
    root {document_root};
    index index.php index.html index.htm;

    ssl_certificate /etc/letsencrypt/live/{domain}/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/{domain}/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;

    access_log /var/log/nginx/{domain}.access.log;
    error_log /var/log/nginx/{domain}.error.log;

    location / {{
        try_files $uri $uri/ /index.php?$query_string;
    }}

    location ~ \\.php$ {{
        fastcgi_pass unix:/var/run/php/php{php_version}-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_index index.php;
    }}

    location ~ /\\.ht {{
        deny all;
    }}

    location ~* \\.(jpg|jpeg|png|gif|ico|css|js|woff2?)$ {{
        expires 30d;
        add_header Cache-Control "public, no-transform";
    }}
}}
"""


async def create_vhost(domain: str, php_version: str = "8.3", ssl: bool = False) -> dict:
    document_root = str(settings.WEB_ROOT / domain / "public_html")
    template = VHOST_SSL_TEMPLATE if ssl else VHOST_TEMPLATE
    config = template.format(
        domain=domain,
        document_root=document_root,
        php_version=php_version,
    )

    vhost_path = settings.VHOSTS_DIR / domain
    enabled_path = settings.VHOSTS_ENABLED_DIR / domain

    try:
        # Create document root
        doc_root = Path(document_root)
        doc_root.mkdir(parents=True, exist_ok=True)

        # Write default index
        index_file = doc_root / "index.html"
        if not index_file.exists():
            index_file.write_text(
                f"<html><body><h1>Bienvenue sur {domain}</h1>"
                f"<p>Site géré par ABPanel</p></body></html>"
            )

        # Write vhost config
        vhost_path.write_text(config)

        # Enable site (symlink)
        if not enabled_path.exists():
            enabled_path.symlink_to(vhost_path)

        # Test nginx config
        result = await _test_nginx_config()
        if not result["success"]:
            # Rollback
            enabled_path.unlink(missing_ok=True)
            vhost_path.unlink(missing_ok=True)
            return {"success": False, "error": f"Nginx config test failed: {result['error']}"}

        # Reload nginx
        await _reload_nginx()
        return {"success": True, "document_root": document_root}

    except Exception as e:
        return {"success": False, "error": str(e)}


async def delete_vhost(domain: str) -> dict:
    vhost_path = settings.VHOSTS_DIR / domain
    enabled_path = settings.VHOSTS_ENABLED_DIR / domain

    try:
        enabled_path.unlink(missing_ok=True)
        vhost_path.unlink(missing_ok=True)
        await _reload_nginx()
        return {"success": True}
    except Exception as e:
        return {"success": False, "error": str(e)}


async def _test_nginx_config() -> dict:
    proc = await asyncio.create_subprocess_exec(
        settings.NGINX_BIN, "-t",
        stdout=asyncio.subprocess.PIPE,
        stderr=asyncio.subprocess.PIPE,
    )
    _, stderr = await proc.communicate()
    if proc.returncode == 0:
        return {"success": True}
    return {"success": False, "error": stderr.decode().strip()}


async def _reload_nginx() -> dict:
    proc = await asyncio.create_subprocess_exec(
        settings.SYSTEMCTL_BIN, "reload", "nginx",
        stdout=asyncio.subprocess.PIPE,
        stderr=asyncio.subprocess.PIPE,
    )
    _, stderr = await proc.communicate()
    if proc.returncode == 0:
        return {"success": True}
    return {"success": False, "error": stderr.decode().strip()}
