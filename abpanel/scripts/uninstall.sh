#!/bin/bash
set -e

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

if [[ $EUID -ne 0 ]]; then
    echo -e "${RED}Ce script doit être exécuté en tant que root${NC}"
    exit 1
fi

echo -e "${YELLOW}Êtes-vous sûr de vouloir désinstaller ABPanel ? (y/N)${NC}"
read -r confirm
if [[ "$confirm" != "y" && "$confirm" != "Y" ]]; then
    echo "Annulé."
    exit 0
fi

echo -e "${GREEN}[ABPanel]${NC} Arrêt du service..."
systemctl stop abpanel 2>/dev/null || true
systemctl disable abpanel 2>/dev/null || true
rm -f /etc/systemd/system/abpanel.service
systemctl daemon-reload

echo -e "${GREEN}[ABPanel]${NC} Suppression des fichiers..."
rm -rf /opt/abpanel
rm -rf /var/log/abpanel

echo -e "${GREEN}ABPanel a été désinstallé.${NC}"
echo -e "${YELLOW}Note : Nginx, MariaDB et les sites web n'ont pas été supprimés.${NC}"
