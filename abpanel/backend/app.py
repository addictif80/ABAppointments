"""ABPanel - Main FastAPI application."""

from contextlib import asynccontextmanager

from fastapi import FastAPI, Request
from fastapi.responses import HTMLResponse
from fastapi.staticfiles import StaticFiles
from fastapi.templating import Jinja2Templates

from backend.api import auth, backups, dashboard, databases, dns, email, files, firewall_routes, ftp, services, ssl_routes, websites
from backend.core.config import settings
from backend.core.database import init_db
from backend.core.security import hash_password
from backend.models.user import User
from backend.models.website import Backup, Database, DNSZone, EmailAccount, FTPAccount, Website


@asynccontextmanager
async def lifespan(app: FastAPI):
    # Startup
    await init_db()
    # Create default admin if not exists
    from sqlalchemy import select
    from backend.core.database import async_session
    async with async_session() as session:
        result = await session.execute(select(User).where(User.username == "admin"))
        if not result.scalar_one_or_none():
            admin = User(
                username="admin",
                email="admin@localhost",
                hashed_password=hash_password("admin"),
                role="admin",
            )
            session.add(admin)
            await session.commit()
    yield
    # Shutdown


app = FastAPI(
    title=settings.APP_NAME,
    version=settings.APP_VERSION,
    description=settings.APP_DESCRIPTION,
    lifespan=lifespan,
)

# Static files & templates
app.mount("/static", StaticFiles(directory="frontend/static"), name="static")
templates = Jinja2Templates(directory="frontend/templates")

# API routes
app.include_router(auth.router)
app.include_router(dashboard.router)
app.include_router(websites.router)
app.include_router(databases.router)
app.include_router(dns.router)
app.include_router(ssl_routes.router)
app.include_router(files.router)
app.include_router(email.router)
app.include_router(ftp.router)
app.include_router(firewall_routes.router)
app.include_router(backups.router)
app.include_router(services.router)


@app.get("/", response_class=HTMLResponse)
async def index(request: Request):
    return templates.TemplateResponse("pages/login.html", {"request": request})


@app.get("/dashboard", response_class=HTMLResponse)
async def dashboard_page(request: Request):
    return templates.TemplateResponse("pages/dashboard.html", {"request": request})


@app.get("/sites", response_class=HTMLResponse)
async def sites_page(request: Request):
    return templates.TemplateResponse("pages/websites.html", {"request": request})


@app.get("/databases-page", response_class=HTMLResponse)
async def databases_page(request: Request):
    return templates.TemplateResponse("pages/databases.html", {"request": request})


@app.get("/dns-page", response_class=HTMLResponse)
async def dns_page(request: Request):
    return templates.TemplateResponse("pages/dns.html", {"request": request})


@app.get("/emails", response_class=HTMLResponse)
async def emails_page(request: Request):
    return templates.TemplateResponse("pages/email.html", {"request": request})


@app.get("/file-manager", response_class=HTMLResponse)
async def filemanager_page(request: Request):
    return templates.TemplateResponse("pages/filemanager.html", {"request": request})


@app.get("/ssl-page", response_class=HTMLResponse)
async def ssl_page(request: Request):
    return templates.TemplateResponse("pages/ssl.html", {"request": request})


@app.get("/firewall-page", response_class=HTMLResponse)
async def firewall_page(request: Request):
    return templates.TemplateResponse("pages/firewall.html", {"request": request})


@app.get("/backups-page", response_class=HTMLResponse)
async def backups_page(request: Request):
    return templates.TemplateResponse("pages/backups.html", {"request": request})


@app.get("/services-page", response_class=HTMLResponse)
async def services_page(request: Request):
    return templates.TemplateResponse("pages/services.html", {"request": request})
