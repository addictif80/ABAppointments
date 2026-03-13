# ABPanel

Panneau de contrôle d'hébergement web — alternative moderne à CyberPanel.

## Fonctionnalités

- **Tableau de bord** : Monitoring système en temps réel (CPU, RAM, disque, réseau)
- **Sites web** : Gestion des virtual hosts Nginx avec support multi-PHP
- **Bases de données** : Création/suppression MySQL/MariaDB avec génération auto de mots de passe
- **Zones DNS** : Gestion complète des enregistrements DNS (A, AAAA, CNAME, MX, TXT, NS, SRV, CAA)
- **Certificats SSL** : Émission et renouvellement Let's Encrypt automatique
- **Gestionnaire de fichiers** : Navigateur et éditeur de fichiers intégré
- **Emails** : Gestion des comptes email (Postfix/Dovecot)
- **FTP** : Gestion des comptes FTP (Pure-FTPd)
- **Pare-feu** : Gestion des règles UFW
- **Sauvegardes** : Sauvegarde et restauration complète/partielle
- **Services** : Démarrage/arrêt/redémarrage des services système

## Stack technique

- **Backend** : Python 3.11+ / FastAPI
- **Frontend** : HTML, Tailwind CSS, JavaScript vanilla
- **Base de données** : SQLite (configuration) + MySQL/MariaDB (sites)
- **Web server** : Nginx
- **Auth** : JWT + cookies HttpOnly

## Installation

```bash
# Sur Ubuntu 22.04/24.04 ou Debian 12
sudo bash scripts/install.sh
```

## Accès

- URL : `https://votre-ip:8443`
- Utilisateur : `admin`
- Mot de passe : `admin` (à changer immédiatement)

## Développement

```bash
pip install -r requirements.txt
ABPANEL_DEBUG=true python run.py
```

## Licence

MIT
