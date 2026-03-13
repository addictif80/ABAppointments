"""Website model."""

from sqlalchemy import Boolean, Column, DateTime, ForeignKey, Integer, String, Text, func
from backend.core.database import Base


class Website(Base):
    __tablename__ = "websites"

    id = Column(Integer, primary_key=True, autoincrement=True)
    domain = Column(String(255), unique=True, nullable=False, index=True)
    user_id = Column(Integer, ForeignKey("users.id"), nullable=False)
    document_root = Column(String(500), nullable=False)
    php_version = Column(String(10), default="8.3")
    ssl_enabled = Column(Boolean, default=False)
    ssl_auto_renew = Column(Boolean, default=True)
    is_active = Column(Boolean, default=True)
    created_at = Column(DateTime(timezone=True), server_default=func.now())
    updated_at = Column(DateTime(timezone=True), onupdate=func.now())


class Database(Base):
    __tablename__ = "databases"

    id = Column(Integer, primary_key=True, autoincrement=True)
    name = Column(String(64), unique=True, nullable=False, index=True)
    user_id = Column(Integer, ForeignKey("users.id"), nullable=False)
    website_id = Column(Integer, ForeignKey("websites.id"), nullable=True)
    db_user = Column(String(32), nullable=False)
    db_password_hash = Column(String(255), nullable=False)
    created_at = Column(DateTime(timezone=True), server_default=func.now())


class DNSZone(Base):
    __tablename__ = "dns_zones"

    id = Column(Integer, primary_key=True, autoincrement=True)
    domain = Column(String(255), nullable=False, index=True)
    record_type = Column(String(10), nullable=False)  # A, AAAA, CNAME, MX, TXT, NS, SRV
    name = Column(String(255), nullable=False)
    value = Column(Text, nullable=False)
    ttl = Column(Integer, default=3600)
    priority = Column(Integer, nullable=True)
    user_id = Column(Integer, ForeignKey("users.id"), nullable=False)
    created_at = Column(DateTime(timezone=True), server_default=func.now())


class Backup(Base):
    __tablename__ = "backups"

    id = Column(Integer, primary_key=True, autoincrement=True)
    website_id = Column(Integer, ForeignKey("websites.id"), nullable=False)
    user_id = Column(Integer, ForeignKey("users.id"), nullable=False)
    filename = Column(String(500), nullable=False)
    size_bytes = Column(Integer, default=0)
    backup_type = Column(String(20), default="full")  # full, files, database
    status = Column(String(20), default="pending")  # pending, running, completed, failed
    created_at = Column(DateTime(timezone=True), server_default=func.now())


class EmailAccount(Base):
    __tablename__ = "email_accounts"

    id = Column(Integer, primary_key=True, autoincrement=True)
    email = Column(String(255), unique=True, nullable=False, index=True)
    domain = Column(String(255), nullable=False)
    password_hash = Column(String(255), nullable=False)
    quota_mb = Column(Integer, default=1024)
    user_id = Column(Integer, ForeignKey("users.id"), nullable=False)
    is_active = Column(Boolean, default=True)
    created_at = Column(DateTime(timezone=True), server_default=func.now())


class FTPAccount(Base):
    __tablename__ = "ftp_accounts"

    id = Column(Integer, primary_key=True, autoincrement=True)
    username = Column(String(50), unique=True, nullable=False, index=True)
    password_hash = Column(String(255), nullable=False)
    home_directory = Column(String(500), nullable=False)
    user_id = Column(Integer, ForeignKey("users.id"), nullable=False)
    website_id = Column(Integer, ForeignKey("websites.id"), nullable=True)
    is_active = Column(Boolean, default=True)
    created_at = Column(DateTime(timezone=True), server_default=func.now())
