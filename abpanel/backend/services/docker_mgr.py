"""Docker Manager service."""

import asyncio
import json


async def _run_docker(*args: str) -> dict:
    try:
        proc = await asyncio.create_subprocess_exec(
            "docker", *args,
            stdout=asyncio.subprocess.PIPE,
            stderr=asyncio.subprocess.PIPE,
        )
        stdout, stderr = await proc.communicate()
        if proc.returncode == 0:
            return {"success": True, "output": stdout.decode().strip()}
        return {"success": False, "error": stderr.decode().strip()}
    except FileNotFoundError:
        return {"success": False, "error": "Docker not installed"}
    except Exception as e:
        return {"success": False, "error": str(e)}


async def get_docker_status() -> dict:
    """Check Docker installation and status."""
    result = await _run_docker("info", "--format", "{{json .}}")
    if not result["success"]:
        return {"installed": False, "running": False, "error": result["error"]}

    try:
        info = json.loads(result["output"])
        return {
            "installed": True,
            "running": True,
            "version": info.get("ServerVersion", "unknown"),
            "containers": info.get("Containers", 0),
            "containers_running": info.get("ContainersRunning", 0),
            "images": info.get("Images", 0),
            "storage_driver": info.get("Driver", "unknown"),
        }
    except json.JSONDecodeError:
        return {"installed": True, "running": True}


async def install_docker() -> dict:
    """Install Docker using the official script."""
    try:
        proc = await asyncio.create_subprocess_shell(
            "curl -fsSL https://get.docker.com | sh",
            stdout=asyncio.subprocess.PIPE,
            stderr=asyncio.subprocess.PIPE,
        )
        _, stderr = await proc.communicate()
        if proc.returncode == 0:
            # Enable and start
            await asyncio.create_subprocess_exec("systemctl", "enable", "docker")
            await asyncio.create_subprocess_exec("systemctl", "start", "docker")
            return {"success": True, "message": "Docker installed and started"}
        return {"success": False, "error": stderr.decode()[:500]}
    except Exception as e:
        return {"success": False, "error": str(e)}


async def list_containers(all_containers: bool = True) -> dict:
    """List Docker containers."""
    args = ["ps", "--format", "{{json .}}"]
    if all_containers:
        args.insert(1, "-a")

    result = await _run_docker(*args)
    if not result["success"]:
        return result

    containers = []
    for line in result["output"].split("\n"):
        if line.strip():
            try:
                containers.append(json.loads(line))
            except json.JSONDecodeError:
                pass

    return {"success": True, "containers": containers}


async def list_images() -> dict:
    """List Docker images."""
    result = await _run_docker("images", "--format", "{{json .}}")
    if not result["success"]:
        return result

    images = []
    for line in result["output"].split("\n"):
        if line.strip():
            try:
                images.append(json.loads(line))
            except json.JSONDecodeError:
                pass

    return {"success": True, "images": images}


async def pull_image(image: str, tag: str = "latest") -> dict:
    """Pull a Docker image."""
    return await _run_docker("pull", f"{image}:{tag}")


async def create_container(name: str, image: str, ports: list[str] | None = None,
                           env: dict | None = None, volumes: list[str] | None = None,
                           restart: str = "unless-stopped") -> dict:
    """Create and start a Docker container."""
    args = ["run", "-d", "--name", name, "--restart", restart]

    if ports:
        for p in ports:
            args.extend(["-p", p])

    if env:
        for k, v in env.items():
            args.extend(["-e", f"{k}={v}"])

    if volumes:
        for v in volumes:
            args.extend(["-v", v])

    args.append(image)
    return await _run_docker(*args)


async def container_action(container_id: str, action: str) -> dict:
    """Start, stop, restart, or remove a container."""
    if action not in ("start", "stop", "restart", "rm"):
        return {"success": False, "error": "Invalid action"}

    args = [action]
    if action == "rm":
        args.append("-f")
    args.append(container_id)

    return await _run_docker(*args)


async def container_logs(container_id: str, tail: int = 100) -> dict:
    """Get container logs."""
    return await _run_docker("logs", "--tail", str(tail), container_id)


async def search_images(query: str) -> dict:
    """Search Docker Hub for images."""
    result = await _run_docker("search", "--format", "{{json .}}", "--limit", "20", query)
    if not result["success"]:
        return result

    images = []
    for line in result["output"].split("\n"):
        if line.strip():
            try:
                images.append(json.loads(line))
            except json.JSONDecodeError:
                pass

    return {"success": True, "images": images}


# Pre-configured one-click apps
ONE_CLICK_APPS = {
    "wordpress": {
        "name": "WordPress",
        "description": "CMS populaire pour créer des sites web",
        "image": "wordpress:latest",
        "ports": ["8080:80"],
        "env": {
            "WORDPRESS_DB_HOST": "host.docker.internal",
            "WORDPRESS_DB_USER": "wordpress",
            "WORDPRESS_DB_PASSWORD": "changeme",
            "WORDPRESS_DB_NAME": "wordpress",
        },
    },
    "n8n": {
        "name": "n8n",
        "description": "Automatisation de workflows",
        "image": "n8nio/n8n:latest",
        "ports": ["5678:5678"],
        "env": {"GENERIC_TIMEZONE": "Europe/Paris"},
        "volumes": ["n8n_data:/home/node/.n8n"],
    },
    "redis": {
        "name": "Redis",
        "description": "Cache et base de données en mémoire",
        "image": "redis:alpine",
        "ports": ["6379:6379"],
    },
    "mongodb": {
        "name": "MongoDB",
        "description": "Base de données NoSQL",
        "image": "mongo:latest",
        "ports": ["27017:27017"],
        "volumes": ["mongodb_data:/data/db"],
    },
    "portainer": {
        "name": "Portainer",
        "description": "Interface de gestion Docker",
        "image": "portainer/portainer-ce:latest",
        "ports": ["9443:9443"],
        "volumes": ["/var/run/docker.sock:/var/run/docker.sock", "portainer_data:/data"],
    },
    "gitea": {
        "name": "Gitea",
        "description": "Serveur Git auto-hébergé",
        "image": "gitea/gitea:latest",
        "ports": ["3000:3000", "2222:22"],
        "volumes": ["gitea_data:/data"],
    },
    "nextcloud": {
        "name": "Nextcloud",
        "description": "Cloud personnel (fichiers, agenda, contacts)",
        "image": "nextcloud:latest",
        "ports": ["8081:80"],
        "volumes": ["nextcloud_data:/var/www/html"],
    },
    "matomo": {
        "name": "Matomo",
        "description": "Analytics web respectueux de la vie privée",
        "image": "matomo:latest",
        "ports": ["8082:80"],
        "volumes": ["matomo_data:/var/www/html"],
    },
    "grafana": {
        "name": "Grafana",
        "description": "Tableaux de bord et visualisation de données",
        "image": "grafana/grafana:latest",
        "ports": ["3001:3000"],
        "volumes": ["grafana_data:/var/lib/grafana"],
    },
    "uptime-kuma": {
        "name": "Uptime Kuma",
        "description": "Monitoring de disponibilité",
        "image": "louislam/uptime-kuma:latest",
        "ports": ["3002:3001"],
        "volumes": ["uptime_kuma_data:/app/data"],
    },
    "minio": {
        "name": "MinIO",
        "description": "Stockage objet compatible S3",
        "image": "minio/minio:latest",
        "ports": ["9000:9000", "9001:9001"],
        "volumes": ["minio_data:/data"],
        "env": {"MINIO_ROOT_USER": "admin", "MINIO_ROOT_PASSWORD": "changeme123"},
    },
    "mailhog": {
        "name": "MailHog",
        "description": "Serveur email de test",
        "image": "mailhog/mailhog:latest",
        "ports": ["1025:1025", "8025:8025"],
    },
}


async def deploy_one_click_app(app_key: str, custom_env: dict | None = None) -> dict:
    """Deploy a pre-configured one-click app."""
    if app_key not in ONE_CLICK_APPS:
        return {"success": False, "error": f"Unknown app: {app_key}"}

    app = ONE_CLICK_APPS[app_key]
    env = {**app.get("env", {}), **(custom_env or {})}

    # Pull image first
    pull_result = await pull_image(app["image"].split(":")[0], app["image"].split(":")[-1])
    if not pull_result["success"]:
        return pull_result

    return await create_container(
        name=f"abpanel-{app_key}",
        image=app["image"],
        ports=app.get("ports"),
        env=env if env else None,
        volumes=app.get("volumes"),
    )


async def get_one_click_apps() -> list[dict]:
    """Return available one-click apps."""
    return [
        {"key": k, "name": v["name"], "description": v["description"], "image": v["image"]}
        for k, v in ONE_CLICK_APPS.items()
    ]
