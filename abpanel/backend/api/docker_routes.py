"""Docker Manager API routes."""

from fastapi import APIRouter, Depends
from pydantic import BaseModel

from backend.middleware.auth import require_admin
from backend.models.user import User
from backend.services.docker_mgr import (
    container_action,
    container_logs,
    create_container,
    deploy_one_click_app,
    get_docker_status,
    get_one_click_apps,
    install_docker,
    list_containers,
    list_images,
    pull_image,
    search_images,
)

router = APIRouter(prefix="/api/addons/docker", tags=["addons"])


class PullImageRequest(BaseModel):
    image: str
    tag: str = "latest"


class CreateContainerRequest(BaseModel):
    name: str
    image: str
    ports: list[str] | None = None
    env: dict | None = None
    volumes: list[str] | None = None


class ContainerActionRequest(BaseModel):
    container_id: str
    action: str


class DeployAppRequest(BaseModel):
    app_key: str
    custom_env: dict | None = None


@router.get("/status")
async def docker_status(user: User = Depends(require_admin)):
    return await get_docker_status()


@router.post("/install")
async def docker_install(user: User = Depends(require_admin)):
    return await install_docker()


@router.get("/containers")
async def docker_containers(user: User = Depends(require_admin)):
    return await list_containers()


@router.get("/images")
async def docker_images(user: User = Depends(require_admin)):
    return await list_images()


@router.post("/pull")
async def docker_pull(data: PullImageRequest, user: User = Depends(require_admin)):
    return await pull_image(data.image, data.tag)


@router.post("/create")
async def docker_create(data: CreateContainerRequest, user: User = Depends(require_admin)):
    return await create_container(data.name, data.image, data.ports, data.env, data.volumes)


@router.post("/action")
async def docker_action(data: ContainerActionRequest, user: User = Depends(require_admin)):
    return await container_action(data.container_id, data.action)


@router.get("/logs/{container_id}")
async def docker_logs(container_id: str, tail: int = 100, user: User = Depends(require_admin)):
    return await container_logs(container_id, tail)


@router.get("/search/{query}")
async def docker_search(query: str, user: User = Depends(require_admin)):
    return await search_images(query)


@router.get("/apps")
async def docker_apps(user: User = Depends(require_admin)):
    return await get_one_click_apps()


@router.post("/deploy")
async def docker_deploy(data: DeployAppRequest, user: User = Depends(require_admin)):
    return await deploy_one_click_app(data.app_key, data.custom_env)
