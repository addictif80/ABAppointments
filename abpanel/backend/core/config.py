"""ABPanel configuration."""

import os
from pathlib import Path
from pydantic_settings import BaseSettings


class Settings(BaseSettings):
    APP_NAME: str = "ABPanel"
    APP_VERSION: str = "1.0.0"
    APP_DESCRIPTION: str = "Panneau de contrôle d'hébergement web"
    DEBUG: bool = False

    # Paths
    BASE_DIR: Path = Path("/opt/abpanel")
    DATA_DIR: Path = Path("/opt/abpanel/data")
    LOG_DIR: Path = Path("/var/log/abpanel")
    VHOSTS_DIR: Path = Path("/etc/nginx/sites-available")
    VHOSTS_ENABLED_DIR: Path = Path("/etc/nginx/sites-enabled")
    WEB_ROOT: Path = Path("/var/www")
    BACKUP_DIR: Path = Path("/opt/abpanel/backups")
    SSL_DIR: Path = Path("/etc/letsencrypt")

    # Server
    HOST: str = "0.0.0.0"
    PORT: int = 8443
    SECRET_KEY: str = "change-me-in-production"
    ACCESS_TOKEN_EXPIRE_MINUTES: int = 60
    ALGORITHM: str = "HS256"

    # Database
    DATABASE_URL: str = "sqlite+aiosqlite:///opt/abpanel/data/abpanel.db"

    # Services
    NGINX_BIN: str = "/usr/sbin/nginx"
    PHP_FPM_BIN: str = "/usr/sbin/php-fpm"
    MYSQL_BIN: str = "/usr/bin/mysql"
    CERTBOT_BIN: str = "/usr/bin/certbot"
    UFW_BIN: str = "/usr/sbin/ufw"
    SYSTEMCTL_BIN: str = "/usr/bin/systemctl"

    class Config:
        env_file = "/opt/abpanel/.env"
        env_prefix = "ABPANEL_"


settings = Settings()
