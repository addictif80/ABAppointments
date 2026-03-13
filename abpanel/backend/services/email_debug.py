"""Email Debugger - diagnose email delivery issues."""

import asyncio
import re
from pathlib import Path


async def check_email_config() -> dict:
    """Run comprehensive email configuration checks."""
    results = {}

    # Check Postfix
    results["postfix"] = await _check_service("postfix")

    # Check Dovecot
    results["dovecot"] = await _check_service("dovecot")

    # Check ports
    results["ports"] = await _check_email_ports()

    # Check hostname/PTR
    results["hostname"] = await _check_hostname()

    # Check DNS records
    results["dns"] = await _check_dns_setup()

    # Check mail queue
    results["queue"] = await _check_mail_queue()

    # Check logs for errors
    results["recent_errors"] = await _get_recent_errors()

    return results


async def _check_service(name: str) -> dict:
    proc = await asyncio.create_subprocess_exec(
        "systemctl", "is-active", name,
        stdout=asyncio.subprocess.PIPE,
    )
    stdout, _ = await proc.communicate()
    status = stdout.decode().strip()
    return {"running": status == "active", "status": status}


async def _check_email_ports() -> dict:
    ports = {"25": "SMTP", "465": "SMTPS", "587": "Submission", "993": "IMAPS", "110": "POP3", "995": "POP3S"}
    results = {}

    for port, name in ports.items():
        proc = await asyncio.create_subprocess_shell(
            f"ss -tlnp | grep :{port}",
            stdout=asyncio.subprocess.PIPE,
            stderr=asyncio.subprocess.PIPE,
        )
        stdout, _ = await proc.communicate()
        results[name] = {"port": port, "listening": bool(stdout.decode().strip())}

    return results


async def _check_hostname() -> dict:
    proc = await asyncio.create_subprocess_exec(
        "hostname", "-f",
        stdout=asyncio.subprocess.PIPE,
    )
    stdout, _ = await proc.communicate()
    hostname = stdout.decode().strip()

    proc = await asyncio.create_subprocess_exec(
        "postconf", "myhostname",
        stdout=asyncio.subprocess.PIPE,
        stderr=asyncio.subprocess.PIPE,
    )
    stdout, _ = await proc.communicate()
    postfix_hostname = stdout.decode().strip().split("=")[-1].strip() if proc.returncode == 0 else "unknown"

    return {"system_hostname": hostname, "postfix_hostname": postfix_hostname}


async def _check_dns_setup() -> dict:
    # Get server IP
    proc = await asyncio.create_subprocess_shell(
        "curl -s ifconfig.me",
        stdout=asyncio.subprocess.PIPE,
    )
    stdout, _ = await proc.communicate()
    server_ip = stdout.decode().strip()

    return {"server_ip": server_ip}


async def _check_mail_queue() -> dict:
    proc = await asyncio.create_subprocess_exec(
        "mailq",
        stdout=asyncio.subprocess.PIPE,
        stderr=asyncio.subprocess.PIPE,
    )
    stdout, _ = await proc.communicate()
    output = stdout.decode().strip()

    queue_empty = "Mail queue is empty" in output
    return {"empty": queue_empty, "output": output[:2000]}


async def _get_recent_errors() -> dict:
    proc = await asyncio.create_subprocess_shell(
        "grep -i 'error\\|reject\\|denied\\|failed' /var/log/mail.log 2>/dev/null | tail -20",
        stdout=asyncio.subprocess.PIPE,
    )
    stdout, _ = await proc.communicate()
    return {"errors": stdout.decode().strip().split("\n") if stdout.decode().strip() else []}


async def send_test_email(from_addr: str, to_addr: str, subject: str = "ABPanel Test Email") -> dict:
    """Send a test email to verify configuration."""
    message = f"""Subject: {subject}
From: {from_addr}
To: {to_addr}
Content-Type: text/plain; charset=UTF-8

Ceci est un email de test envoyé depuis ABPanel.
Si vous recevez ce message, votre configuration email fonctionne correctement.
"""
    try:
        proc = await asyncio.create_subprocess_exec(
            "sendmail", "-f", from_addr, to_addr,
            stdin=asyncio.subprocess.PIPE,
            stdout=asyncio.subprocess.PIPE,
            stderr=asyncio.subprocess.PIPE,
        )
        _, stderr = await proc.communicate(input=message.encode())

        if proc.returncode == 0:
            return {"success": True, "message": f"Email de test envoyé à {to_addr}"}
        return {"success": False, "error": stderr.decode().strip()}
    except Exception as e:
        return {"success": False, "error": str(e)}


async def flush_mail_queue() -> dict:
    """Flush the mail queue."""
    proc = await asyncio.create_subprocess_exec(
        "postqueue", "-f",
        stdout=asyncio.subprocess.PIPE,
        stderr=asyncio.subprocess.PIPE,
    )
    _, stderr = await proc.communicate()
    if proc.returncode == 0:
        return {"success": True, "message": "Mail queue flushed"}
    return {"success": False, "error": stderr.decode().strip()}


async def purge_mail_queue() -> dict:
    """Purge all messages from the mail queue."""
    proc = await asyncio.create_subprocess_exec(
        "postsuper", "-d", "ALL",
        stdout=asyncio.subprocess.PIPE,
        stderr=asyncio.subprocess.PIPE,
    )
    _, stderr = await proc.communicate()
    if proc.returncode == 0:
        return {"success": True, "message": "Mail queue purged"}
    return {"success": False, "error": stderr.decode().strip()}
