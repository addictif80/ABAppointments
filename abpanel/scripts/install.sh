#!/bin/bash
set -e

# ABPanel Installation Script
# Compatible: Ubuntu 22.04/24.04, Debian 12

ABPANEL_DIR="/opt/abpanel"
ABPANEL_USER="abpanel"
PYTHON_MIN="3.11"

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

log() { echo -e "${GREEN}[ABPanel]${NC} $1"; }
warn() { echo -e "${YELLOW}[WARN]${NC} $1"; }
error() { echo -e "${RED}[ERROR]${NC} $1"; exit 1; }

# Check root
if [[ $EUID -ne 0 ]]; then
    error "Ce script doit être exécuté en tant que root"
fi

echo -e "${BLUE}"
echo "  ╔═══════════════════════════════════════╗"
echo "  ║         ABPanel - Installation         ║"
echo "  ║   Panneau de contrôle d'hébergement    ║"
echo "  ╚═══════════════════════════════════════╝"
echo -e "${NC}"

# Detect OS
if [ -f /etc/os-release ]; then
    . /etc/os-release
    OS=$ID
    VERSION=$VERSION_ID
else
    error "Système d'exploitation non supporté"
fi

log "Système détecté : $OS $VERSION"

# Update system
log "Mise à jour du système..."
apt-get update -qq
apt-get upgrade -y -qq

# Install dependencies
log "Installation des dépendances..."
apt-get install -y -qq \
    nginx \
    mariadb-server \
    python3 python3-pip python3-venv \
    certbot python3-certbot-nginx \
    ufw fail2ban \
    git curl wget unzip \
    php8.3-fpm php8.3-mysql php8.3-curl php8.3-gd php8.3-mbstring php8.3-xml php8.3-zip php8.3-intl php8.3-bcmath \
    postfix dovecot-imapd dovecot-pop3d \
    pure-ftpd \
    2>/dev/null || {
    # Fallback for PHP 8.2 on older systems
    warn "PHP 8.3 non disponible, installation de PHP 8.2..."
    apt-get install -y -qq \
        php8.2-fpm php8.2-mysql php8.2-curl php8.2-gd php8.2-mbstring php8.2-xml php8.2-zip php8.2-intl php8.2-bcmath \
        2>/dev/null || true
}

# Create ABPanel directory
log "Création de la structure ABPanel..."
mkdir -p $ABPANEL_DIR/{data,backups,logs}
mkdir -p /var/www
mkdir -p /var/log/abpanel

# Copy application files
log "Copie des fichiers de l'application..."
cp -r "$(dirname "$0")/../backend" $ABPANEL_DIR/
cp -r "$(dirname "$0")/../frontend" $ABPANEL_DIR/
cp "$(dirname "$0")/../requirements.txt" $ABPANEL_DIR/
cp "$(dirname "$0")/../run.py" $ABPANEL_DIR/

# Setup Python virtual environment
log "Configuration de l'environnement Python..."
python3 -m venv $ABPANEL_DIR/venv
$ABPANEL_DIR/venv/bin/pip install --quiet --upgrade pip
$ABPANEL_DIR/venv/bin/pip install --quiet -r $ABPANEL_DIR/requirements.txt

# Generate secret key
SECRET_KEY=$(python3 -c "import secrets; print(secrets.token_hex(32))")

# Create .env file
cat > $ABPANEL_DIR/.env << EOF
ABPANEL_SECRET_KEY=$SECRET_KEY
ABPANEL_DEBUG=false
ABPANEL_DATABASE_URL=sqlite+aiosqlite:///$ABPANEL_DIR/data/abpanel.db
EOF

# Configure MariaDB
log "Configuration de MariaDB..."
systemctl enable mariadb
systemctl start mariadb

# Secure MariaDB
mysql -u root << 'EOSQL'
DELETE FROM mysql.user WHERE User='';
DELETE FROM mysql.user WHERE User='root' AND Host NOT IN ('localhost', '127.0.0.1', '::1');
DROP DATABASE IF EXISTS test;
FLUSH PRIVILEGES;
EOSQL

# Configure Nginx
log "Configuration de Nginx..."
systemctl enable nginx
systemctl start nginx

# Configure UFW
log "Configuration du pare-feu..."
ufw default deny incoming
ufw default allow outgoing
ufw allow 22/tcp
ufw allow 80/tcp
ufw allow 443/tcp
ufw allow 8443/tcp
ufw --force enable

# Configure Fail2ban
log "Configuration de Fail2ban..."
systemctl enable fail2ban
systemctl start fail2ban

# Create systemd service
log "Création du service systemd..."
cat > /etc/systemd/system/abpanel.service << EOF
[Unit]
Description=ABPanel - Hosting Control Panel
After=network.target mariadb.service nginx.service

[Service]
Type=simple
User=root
WorkingDirectory=$ABPANEL_DIR
ExecStart=$ABPANEL_DIR/venv/bin/python run.py
Restart=always
RestartSec=5
Environment=PYTHONPATH=$ABPANEL_DIR

[Install]
WantedBy=multi-user.target
EOF

systemctl daemon-reload
systemctl enable abpanel
systemctl start abpanel

# Get server IP
SERVER_IP=$(hostname -I | awk '{print $1}')

echo ""
echo -e "${GREEN}═══════════════════════════════════════════${NC}"
echo -e "${GREEN}  ABPanel installé avec succès !${NC}"
echo -e "${GREEN}═══════════════════════════════════════════${NC}"
echo ""
echo -e "  URL d'accès : ${BLUE}https://$SERVER_IP:8443${NC}"
echo -e "  Utilisateur : ${YELLOW}admin${NC}"
echo -e "  Mot de passe : ${YELLOW}admin${NC}"
echo ""
echo -e "  ${RED}IMPORTANT : Changez le mot de passe admin immédiatement !${NC}"
echo ""
echo -e "  Commandes utiles :"
echo -e "    systemctl status abpanel    - Voir le statut"
echo -e "    systemctl restart abpanel   - Redémarrer"
echo -e "    journalctl -u abpanel -f    - Voir les logs"
echo ""
