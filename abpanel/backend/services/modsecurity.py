"""ModSecurity WAF management."""

import asyncio
from pathlib import Path

MODSEC_CONF = Path("/etc/nginx/modsec")
MODSEC_RULES = MODSEC_CONF / "main.conf"
CRS_DIR = MODSEC_CONF / "coreruleset"


async def get_status() -> dict:
    """Check ModSecurity status."""
    installed = MODSEC_CONF.exists() and MODSEC_RULES.exists()
    enabled = False

    if installed and MODSEC_RULES.exists():
        content = MODSEC_RULES.read_text()
        enabled = "SecRuleEngine On" in content

    return {
        "installed": installed,
        "enabled": enabled,
        "crs_installed": CRS_DIR.exists(),
    }


async def install_modsecurity() -> dict:
    """Install ModSecurity with Nginx connector and OWASP CRS."""
    try:
        # Install libmodsecurity
        proc = await asyncio.create_subprocess_exec(
            "apt-get", "install", "-y", "-qq",
            "libmodsecurity3", "libnginx-mod-http-modsecurity",
            stdout=asyncio.subprocess.PIPE,
            stderr=asyncio.subprocess.PIPE,
        )
        _, stderr = await proc.communicate()
        if proc.returncode != 0:
            return {"success": False, "error": stderr.decode()[:500]}

        MODSEC_CONF.mkdir(parents=True, exist_ok=True)

        # Download OWASP Core Rule Set
        proc = await asyncio.create_subprocess_exec(
            "git", "clone", "--depth", "1",
            "https://github.com/coreruleset/coreruleset.git",
            str(CRS_DIR),
            stdout=asyncio.subprocess.PIPE,
            stderr=asyncio.subprocess.PIPE,
        )
        await proc.communicate()

        # Setup CRS config
        crs_setup = CRS_DIR / "crs-setup.conf.example"
        crs_conf = CRS_DIR / "crs-setup.conf"
        if crs_setup.exists() and not crs_conf.exists():
            crs_conf.write_text(crs_setup.read_text())

        # Create main ModSecurity config
        main_config = """# ModSecurity Configuration
SecRuleEngine On
SecRequestBodyAccess On
SecRequestBodyLimit 13107200
SecRequestBodyNoFilesLimit 131072
SecResponseBodyAccess Off
SecTmpDir /tmp/
SecDataDir /tmp/
SecAuditEngine RelevantOnly
SecAuditLogRelevantStatus "^(?:5|4(?!04))"
SecAuditLogParts ABCDEFHZ
SecAuditLogType Serial
SecAuditLog /var/log/nginx/modsec_audit.log
SecArgumentSeparator &
SecCookieFormat 0
SecUnicodeMapFile unicode.mapping 20127
SecStatusEngine On

# Include OWASP CRS
Include /etc/nginx/modsec/coreruleset/crs-setup.conf
Include /etc/nginx/modsec/coreruleset/rules/*.conf
"""
        MODSEC_RULES.write_text(main_config)

        # Copy unicode mapping
        unicode_src = Path("/usr/share/modsecurity-crs/unicode.mapping")
        if unicode_src.exists():
            (MODSEC_CONF / "unicode.mapping").write_text(unicode_src.read_text())
        else:
            (MODSEC_CONF / "unicode.mapping").write_text("")

        return {"success": True, "message": "ModSecurity + OWASP CRS installed"}

    except Exception as e:
        return {"success": False, "error": str(e)}


async def toggle_modsecurity(enable: bool) -> dict:
    """Enable or disable ModSecurity."""
    if not MODSEC_RULES.exists():
        return {"success": False, "error": "ModSecurity not installed"}

    content = MODSEC_RULES.read_text()

    if enable:
        content = content.replace("SecRuleEngine Off", "SecRuleEngine On")
        content = content.replace("SecRuleEngine DetectionOnly", "SecRuleEngine On")
    else:
        content = content.replace("SecRuleEngine On", "SecRuleEngine Off")

    MODSEC_RULES.write_text(content)

    # Reload nginx
    proc = await asyncio.create_subprocess_exec(
        "systemctl", "reload", "nginx",
        stdout=asyncio.subprocess.PIPE,
        stderr=asyncio.subprocess.PIPE,
    )
    await proc.communicate()

    return {"success": True, "enabled": enable}


async def get_audit_log(lines: int = 100) -> dict:
    """Read ModSecurity audit log."""
    log_file = Path("/var/log/nginx/modsec_audit.log")
    if not log_file.exists():
        return {"success": True, "log": "No audit log found"}

    try:
        proc = await asyncio.create_subprocess_exec(
            "tail", "-n", str(lines), str(log_file),
            stdout=asyncio.subprocess.PIPE,
        )
        stdout, _ = await proc.communicate()
        return {"success": True, "log": stdout.decode()}
    except Exception as e:
        return {"success": False, "error": str(e)}


async def add_whitelist_rule(ip: str) -> dict:
    """Add an IP to ModSecurity whitelist."""
    whitelist_file = MODSEC_CONF / "whitelist.conf"
    rule = f'SecRule REMOTE_ADDR "@ipMatch {ip}" "id:1000,phase:1,nolog,allow,ctl:ruleEngine=Off"\n'

    try:
        with open(whitelist_file, "a") as f:
            f.write(rule)

        proc = await asyncio.create_subprocess_exec("systemctl", "reload", "nginx")
        await proc.communicate()

        return {"success": True, "message": f"IP {ip} whitelisted"}
    except Exception as e:
        return {"success": False, "error": str(e)}
