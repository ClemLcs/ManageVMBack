# ManageVMBack

Service API backend qui fait le pont entre les applications frontend et la gestion des machines virtuelles Proxmox VE. Ce service fournit une API RESTful pour lister, surveiller et contrôler les VMs Proxmox sur plusieurs nœuds.

## 🎯 Objectif

Ce backend agit comme un middleware entre votre application frontend et les serveurs Proxmox VE, offrant :
- Points de terminaison API unifiés pour la gestion des VMs
- **🔐 Authentification JWT sécurisée** pour protéger l'accès à l'API
- Gestion de l'authentification avec l'API Proxmox
- Agrégation de données depuis plusieurs nœuds Proxmox
- Capacités de pagination et de filtrage
- Gestion du cycle de vie des VMs (démarrage, arrêt, redémarrage, etc.)

## 📋 Prérequis

- PHP 8.2 ou supérieur
- Composer
- Symfony 7.x
- Accès à l'API Proxmox VE (v6.x ou v7.x)

## 🖥️ Configuration d'une VM Linux pour Héberger le Backend

Cette section détaille les dépendances et configurations nécessaires pour créer une machine virtuelle Linux qui hébergera ce backend et fera le lien entre les machines Proxmox et le frontend.

### Spécifications Recommandées de la VM

**Configuration Minimale :**
- **OS** : Ubuntu 22.04 LTS / Debian 12 / Rocky Linux 9
- **CPU** : 2 vCPUs
- **RAM** : 2 GB minimum (4 GB recommandé)
- **Disque** : 20 GB minimum (50 GB recommandé)
- **Réseau** : Interface réseau avec accès à Proxmox et au frontend

**Configuration pour Production :**
- **CPU** : 4+ vCPUs
- **RAM** : 8 GB+
- **Disque** : 100 GB+ (SSD recommandé)
- **Réseau** : Interface réseau avec IP statique

### Dépendances Système à Installer

#### 1. Mise à Jour du Système

```bash
# Pour Ubuntu/Debian
sudo apt update && sudo apt upgrade -y

# Pour Rocky Linux/RHEL
sudo dnf update -y
```

#### 2. Installation de PHP 8.2+

**Ubuntu/Debian :**
```bash
# Ajouter le dépôt PPA pour PHP 8.2+
sudo apt install software-properties-common -y
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update

# Installer PHP 8.2 et les extensions requises
sudo apt install -y \
    php8.2 \
    php8.2-cli \
    php8.2-fpm \
    php8.2-common \
    php8.2-curl \
    php8.2-mbstring \
    php8.2-xml \
    php8.2-zip \
    php8.2-intl \
    php8.2-opcache \
    php8.2-ctype \
    php8.2-iconv

# Vérifier la version
php -v
```

**Rocky Linux/RHEL :**
```bash
# Activer le dépôt EPEL et Remi
sudo dnf install -y epel-release
sudo dnf install -y https://rpms.remirepo.net/enterprise/remi-release-9.rpm

# Activer PHP 8.2
sudo dnf module reset php -y
sudo dnf module enable php:remi-8.2 -y

# Installer PHP et les extensions
sudo dnf install -y \
    php \
    php-cli \
    php-fpm \
    php-common \
    php-curl \
    php-mbstring \
    php-xml \
    php-zip \
    php-intl \
    php-opcache

# Vérifier la version
php -v
```

#### 3. Installation de Composer

```bash
# Télécharger l'installateur
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"

# Vérifier l'installateur (optionnel)
php -r "if (hash_file('sha384', 'composer-setup.php') === file_get_contents('https://composer.github.io/installer.sig')) { echo 'Installer verified'; } else { echo 'Installer corrupt'; unlink('composer-setup.php'); } echo PHP_EOL;"

# Installer Composer globalement
sudo php composer-setup.php --install-dir=/usr/local/bin --filename=composer

# Nettoyer
php -r "unlink('composer-setup.php');"

# Vérifier l'installation
composer --version
```

#### 4. Installation d'un Serveur Web

**Option A : Nginx (Recommandé pour la Production)**

```bash
# Ubuntu/Debian
sudo apt install -y nginx

# Rocky Linux/RHEL
sudo dnf install -y nginx

# Démarrer et activer Nginx
sudo systemctl start nginx
sudo systemctl enable nginx
```

**Configuration Nginx pour Symfony :**

```bash
sudo nano /etc/nginx/sites-available/managevmback
```

Contenu du fichier :
```nginx
server {
    listen 80;
    server_name votre-domaine.com;  # Remplacer par votre domaine ou IP
    root /var/www/ManageVMBack/public;

    location / {
        try_files $uri /index.php$is_args$args;
    }

    location ~ ^/index\.php(/|$) {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_split_path_info ^(.+\.php)(/.*)$;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT $realpath_root;
        internal;
    }

    location ~ \.php$ {
        return 404;
    }

    error_log /var/log/nginx/managevmback_error.log;
    access_log /var/log/nginx/managevmback_access.log;
}
```

```bash
# Activer le site
sudo ln -s /etc/nginx/sites-available/managevmback /etc/nginx/sites-enabled/

# Tester la configuration
sudo nginx -t

# Recharger Nginx
sudo systemctl reload nginx
```

**Option B : Apache**

```bash
# Ubuntu/Debian
sudo apt install -y apache2 libapache2-mod-php8.2

# Rocky Linux/RHEL
sudo dnf install -y httpd

# Activer les modules nécessaires
sudo a2enmod rewrite
sudo a2enmod php8.2

# Démarrer et activer Apache
sudo systemctl start apache2  # ou httpd pour Rocky Linux
sudo systemctl enable apache2
```

#### 5. Outils Système Supplémentaires

```bash
# Ubuntu/Debian
sudo apt install -y \
    git \
    curl \
    wget \
    unzip \
    vim \
    net-tools \
    ca-certificates

# Rocky Linux/RHEL
sudo dnf install -y \
    git \
    curl \
    wget \
    unzip \
    vim \
    net-tools \
    ca-certificates
```

#### 6. Configuration SSL/TLS (Optionnel mais Recommandé)

```bash
# Installer Certbot pour Let's Encrypt
# Ubuntu/Debian
sudo apt install -y certbot python3-certbot-nginx

# Rocky Linux/RHEL
sudo dnf install -y certbot python3-certbot-nginx

# Obtenir un certificat SSL
sudo certbot --nginx -d votre-domaine.com

# Le renouvellement automatique est configuré par défaut
sudo systemctl status certbot.timer
```

#### 7. Configuration du Pare-feu

```bash
# UFW (Ubuntu/Debian)
sudo ufw allow 22/tcp      # SSH
sudo ufw allow 80/tcp      # HTTP
sudo ufw allow 443/tcp     # HTTPS
sudo ufw enable

# FirewallD (Rocky Linux/RHEL)
sudo firewall-cmd --permanent --add-service=ssh
sudo firewall-cmd --permanent --add-service=http
sudo firewall-cmd --permanent --add-service=https
sudo firewall-cmd --reload
```

#### 8. Optimisation PHP pour Production

Éditer le fichier `php.ini` :
```bash
sudo nano /etc/php/8.2/fpm/php.ini  # Ubuntu/Debian
# ou
sudo nano /etc/php.ini  # Rocky Linux/RHEL
```

Paramètres recommandés :
```ini
memory_limit = 256M
max_execution_time = 30
upload_max_filesize = 20M
post_max_size = 20M
opcache.enable = 1
opcache.memory_consumption = 128
opcache.max_accelerated_files = 10000
opcache.revalidate_freq = 60
```

Redémarrer PHP-FPM :
```bash
sudo systemctl restart php8.2-fpm  # Ubuntu/Debian
sudo systemctl restart php-fpm     # Rocky Linux/RHEL
```

#### 9. Configuration des Permissions

```bash
# Créer un utilisateur dédié pour l'application (optionnel)
sudo useradd -m -s /bin/bash vmbackend

# Créer le répertoire de l'application
sudo mkdir -p /var/www/ManageVMBack
sudo chown -R www-data:www-data /var/www/ManageVMBack  # Ubuntu/Debian
# ou
sudo chown -R nginx:nginx /var/www/ManageVMBack  # Rocky Linux/RHEL

# Définir les permissions appropriées
sudo chmod -R 755 /var/www/ManageVMBack
```

### Configuration Réseau

#### Connexion à Proxmox

Assurez-vous que la VM peut atteindre les serveurs Proxmox :

```bash
# Tester la connectivité
curl -k https://votre-serveur-proxmox:8006

# Vérifier la résolution DNS
nslookup votre-serveur-proxmox
```

#### Autoriser les Connexions Frontend

Configurez le pare-feu pour autoriser les connexions depuis le frontend :

```bash
# Si le frontend a une IP fixe
sudo ufw allow from IP_DU_FRONTEND to any port 80
sudo ufw allow from IP_DU_FRONTEND to any port 443
```

### Liste de Contrôle Finale

Avant de déployer l'application, vérifiez :

- [ ] PHP 8.2+ installé avec toutes les extensions requises
- [ ] Composer installé et accessible globalement
- [ ] Serveur web (Nginx/Apache) installé et configuré
- [ ] PHP-FPM configuré et en cours d'exécution
- [ ] Pare-feu configuré (ports 80/443 ouverts)
- [ ] Permissions correctes sur les répertoires
- [ ] Connexion réseau vers Proxmox fonctionnelle
- [ ] CORS configuré pour le frontend
- [ ] SSL/TLS configuré (pour la production)
- [ ] Sauvegardes automatiques configurées (optionnel)

### Surveillance et Logs

Emplacements des logs importants :

```bash
# Logs Nginx
/var/log/nginx/managevmback_error.log
/var/log/nginx/managevmback_access.log

# Logs Apache
/var/log/apache2/error.log  # Ubuntu/Debian
/var/log/httpd/error_log    # Rocky Linux/RHEL

# Logs PHP-FPM
/var/log/php8.2-fpm.log     # Ubuntu/Debian
/var/log/php-fpm/error.log  # Rocky Linux/RHEL

# Logs de l'application Symfony
/var/www/ManageVMBack/var/log/prod.log
/var/www/ManageVMBack/var/log/dev.log
```

Commandes utiles pour surveiller :
```bash
# Surveiller les logs en temps réel
sudo tail -f /var/log/nginx/managevmback_error.log

# Vérifier l'état des services
sudo systemctl status nginx
sudo systemctl status php8.2-fpm

# Vérifier l'utilisation des ressources
htop
free -h
df -h
```

## 🚀 Installation

### 1. Cloner le dépôt

```bash
git clone https://github.com/ClemLcs/ManageVMBack/
cd ManageVMBack
```

### 2. Installer les dépendances

```bash
composer install
```

### 3. Configurer les variables d'environnement

Le fichier `.env` devrait déjà exister. Éditez-le avec vos identifiants Proxmox :

```bash
# Éditer le fichier .env
nano .env
```

**Configurations requises :**

```env
###> symfony/framework-bundle ###
APP_ENV=dev
APP_SECRET=changeme_this_is_a_secret_key_change_it_in_production
###< symfony/framework-bundle ###

###> symfony/routing ###
DEFAULT_URI=http://localhost:8000
###< symfony/routing ###

###> nelmio/cors-bundle ###
# Ajustez cette regex pour correspondre au domaine de votre frontend
CORS_ALLOW_ORIGIN='^https?://(localhost|127\.0\.0\.1)(:[0-9]+)?$'
###< nelmio/cors-bundle ###

###> proxmox configuration ###
PROXMOX_HOST=https://votre-serveur-proxmox:8006
PROXMOX_USER=root@pam
PROXMOX_PASSWORD=votre-mot-de-passe
PROXMOX_VERIFY_SSL=false
###< proxmox configuration ###
```

**⚠️ Notes importantes :**

- `PROXMOX_HOST` : URL de votre serveur Proxmox (généralement port 8006)
- `PROXMOX_USER` : Utilisateur Proxmox avec accès API (format : `utilisateur@realm`)
- `PROXMOX_PASSWORD` : Mot de passe utilisateur
- `PROXMOX_VERIFY_SSL` : `false` pour les certificats auto-signés, `true` pour la production
- `APP_SECRET` : Générez un secret aléatoire pour la production : `php bin/console secrets:generate-keys`

### 4. Alternative : Utiliser les tokens API Proxmox (Recommandé pour la Production)

Au lieu d'utiliser nom d'utilisateur/mot de passe, vous pouvez utiliser des tokens API :

1. Créez un token dans l'interface Proxmox : **Datacenter → Permissions → API Tokens**
2. Mettez à jour votre `.env` :

```env
# Commentez PROXMOX_USER et PROXMOX_PASSWORD
# Puis ajoutez (vous devrez modifier ProxmoxClient.php pour supporter les tokens) :
# PROXMOX_TOKEN_ID=user@realm!tokenid
# PROXMOX_TOKEN_SECRET=your-token-secret
```

### 5. Configuration JWT (Authentification)

Ce backend utilise JWT (JSON Web Tokens) pour sécuriser l'API. Les clés JWT ont déjà été générées lors de l'installation.

**Vérifier que les clés JWT existent :**
```bash
ls -la config/jwt/
# Vous devriez voir : private.pem et public.pem
```

**Si les clés n'existent pas, générez-les :**
```bash
php bin/console lexik:jwt:generate-keypair
```

**Configuration des variables d'environnement JWT :**

Les variables suivantes sont automatiquement configurées dans `.env` :
```env
###> lexik/jwt-authentication-bundle ###
JWT_SECRET_KEY=%kernel.project_dir%/config/jwt/private.pem
JWT_PUBLIC_KEY=%kernel.project_dir%/config/jwt/public.pem
JWT_PASSPHRASE=your_passphrase_here
###< lexik/jwt-authentication-bundle ###
```

**Utilisateurs de test configurés :**

Pour le développement, deux utilisateurs sont préconfigurés dans `config/packages/security.yaml` :

| Username | Password | Roles |
|----------|----------|-------|
| `admin` | `admin123` | ROLE_ADMIN, ROLE_USER |
| `user` | `user123` | ROLE_USER |

⚠️ **Important** : En production, remplacez ces utilisateurs par une vraie base de données utilisateurs !

### 6. Vider le cache

```bash
php bin/console cache:clear
```

## 🎮 Démarrage de l'Application

### Serveur de Développement

**Option 1 : Utiliser Symfony CLI (recommandé)**
```bash
symfony server:start
```

**Option 2 : Utiliser le serveur PHP intégré**
```bash
php -S localhost:8000 -t public/
```

L'API sera disponible sur : `http://localhost:8000`

### Production

Pour le déploiement en production, utilisez un serveur web approprié (Apache/Nginx) avec PHP-FPM. Voir la [documentation de déploiement Symfony](https://symfony.com/doc/current/deployment.html).

## 📚 Documentation de l'API

### URL de Base
```
http://localhost:8000/api/v1
```

### 🔐 Authentification JWT

Tous les endpoints API (sauf `/api/login_check`) nécessitent une authentification JWT.

#### Obtenir un Token JWT

**Endpoint :** `POST /api/login_check`

**Requête :**
```bash
curl -X POST http://localhost:8000/api/login_check \
  -H "Content-Type: application/json" \
  -d '{
    "username": "admin",
    "password": "admin123"
  }'
```

**Réponse :**
```json
{
  "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9..."
}
```

Le token est valide pendant **1 heure** (3600 secondes).

#### Utiliser le Token JWT

Incluez le token dans l'en-tête `Authorization` avec le préfixe `Bearer` :

```bash
curl -H "Authorization: Bearer VOTRE_TOKEN_ICI" \
  http://localhost:8000/api/v1/vms
```

**Exemple complet :**
```bash
# 1. Obtenir le token
TOKEN=$(curl -s -X POST http://localhost:8000/api/login_check \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"admin123"}' \
  | jq -r '.token')

# 2. Utiliser le token pour accéder à l'API
curl -H "Authorization: Bearer $TOKEN" \
  http://localhost:8000/api/v1/vms
```

**JavaScript/Frontend :**
```javascript
// 1. Login
const response = await fetch('http://localhost:8000/api/login_check', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
  },
  body: JSON.stringify({
    username: 'admin',
    password: 'admin123'
  })
});

const { token } = await response.json();

// 2. Stocker le token (localStorage, sessionStorage, ou cookie sécurisé)
localStorage.setItem('jwt_token', token);

// 3. Utiliser le token dans les requêtes suivantes
const vmsResponse = await fetch('http://localhost:8000/api/v1/vms', {
  headers: {
    'Authorization': `Bearer ${token}`
  }
});

const vms = await vmsResponse.json();
```

**Gestion des erreurs JWT :**

| Code | Message | Signification |
|------|---------|---------------|
| 401 | JWT Token not found | Token manquant dans l'en-tête Authorization |
| 401 | Invalid JWT Token | Token invalide ou malformé |
| 401 | Expired JWT Token | Token expiré (> 1 heure) |
| 401 | Invalid credentials | Username ou password incorrect |

### Référence Rapide

| Catégorie | Méthode | Endpoint | Description |
|-----------|---------|----------|-------------|
| **Authentification** | POST | `/api/login_check` | 🔓 Obtenir un token JWT (pas d'auth requise) |
| **VMs** | GET | `/api/v1/vms` | 🔒 Lister toutes les VMs (avec filtres) |
| | GET | `/api/v1/vms/{node}/{vmid}` | 🔒 Obtenir les détails d'une VM |
| **Contrôle VM Simplifié** | POST | `/api/v1/vms/{vmid}/start` | ⭐ Démarrer une VM (détection auto du nœud) |
| | POST | `/api/v1/vms/{vmid}/stop` | ⭐ Arrêter une VM avec mode (acpi/hard) |
| **Snapshots** | GET | `/api/v1/vms/{vmid}/snapshots` | Lister les snapshots d'une VM |
| | POST | `/api/v1/vms/{vmid}/snapshot` | Créer un snapshot |
| | POST | `/api/v1/vms/{vmid}/snapshot/{name}/rollback` | Revenir à un snapshot |
| **Événements** | GET | `/api/v1/events/stream` | Flux d'événements en temps réel (SSE) |
| **Santé** | GET | `/health` | Sonde de vivacité |
| | GET | `/ready` | Sonde de disponibilité (vérifie Proxmox) |
| **Avancé** | POST | `/api/v1/vms/{node}/{vmid}/start` | Démarrer une VM (avec nœud) |
| | POST | `/api/v1/vms/{node}/{vmid}/stop` | Arrêt forcé d'une VM |
| | POST | `/api/v1/vms/{node}/{vmid}/shutdown` | Extinction gracieuse |
| | POST | `/api/v1/vms/{node}/{vmid}/reboot` | Redémarrer une VM |
| | POST | `/api/v1/vms/{node}/{vmid}/suspend` | Suspendre une VM |
| | POST | `/api/v1/vms/{node}/{vmid}/resume` | Reprendre une VM |

⭐ = Recommandé pour le frontend (plus simple, détection auto du nœud)

---

### Points de Terminaison Détaillés

#### 1. Lister les VMs

**GET** `/api/v1/vms`

Obtenir la liste de toutes les VMs avec filtrage et pagination optionnels.

**Paramètres de Requête :**
- `state` (optionnel) : Filtrer par état de VM
  - `running` - Seulement les VMs en cours d'exécution
  - `stopped` - Seulement les VMs arrêtées
  - `paused` - Seulement les VMs en pause/suspendues
- `node` (optionnel) : Filtrer par nom de nœud Proxmox (ex : `proxmox1`)
- `page` (optionnel) : Numéro de page (défaut : `1`)
- `pageSize` (optionnel) : Éléments par page (défaut : `50`, max : `100`)

**Exemple de Requête :**
```bash
curl "http://localhost:8000/api/v1/vms?state=running&node=proxmox1&page=1&pageSize=50"
```

**Exemple de Réponse :**
```json
{
  "success": true,
  "data": [
    {
      "vmid": 100,
      "name": "ubuntu-server",
      "status": "running",
      "node": "proxmox1",
      "maxmem": 8589934592,
      "cpus": 4,
      "maxdisk": 107374182400,
      "uptime": 86400,
      "netin": 1234567,
      "netout": 9876543
    }
  ],
  "pagination": {
    "page": 1,
    "pageSize": 50,
    "total": 125,
    "totalPages": 3
  }
}
```

#### 2. Obtenir les Détails d'une VM

**GET** `/api/v1/vms/{node}/{vmid}`

Obtenir des informations détaillées sur une VM spécifique.

**Exemple de Requête :**
```bash
curl "http://localhost:8000/api/v1/vms/proxmox1/100"
```

**Exemple de Réponse :**
```json
{
  "success": true,
  "data": {
    "vmid": 100,
    "name": "ubuntu-server",
    "status": "running",
    "cpus": 4,
    "maxmem": 8589934592,
    "mem": 4294967296,
    "maxdisk": 107374182400,
    "disk": 53687091200,
    "uptime": 86400,
    "cpu": 0.15,
    "netin": 1234567,
    "netout": 9876543
  }
}
```

#### 3. Démarrer une VM (avec nœud)

**POST** `/api/v1/vms/{node}/{vmid}/start`

Démarrer une VM arrêtée ou suspendue.

**Exemple de Requête :**
```bash
curl -X POST "http://localhost:8000/api/v1/vms/proxmox1/100/start"
```

**Exemple de Réponse :**
```json
{
  "success": true,
  "message": "VM start command sent successfully",
  "data": {
    "data": "UPID:proxmox1:0000A1B2:00C3D4E5:..."
  }
}
```

#### 4. Arrêter une VM (Forcé)

**POST** `/api/v1/vms/{node}/{vmid}/stop`

Arrêt forcé d'une VM en cours d'exécution (arrêt immédiat).

**Exemple de Requête :**
```bash
curl -X POST "http://localhost:8000/api/v1/vms/proxmox1/100/stop"
```

#### 5. Éteindre une VM (Gracieux)

**POST** `/api/v1/vms/{node}/{vmid}/shutdown`

Éteindre gracieusement une VM en cours d'exécution (envoie un signal d'arrêt ACPI).

**Exemple de Requête :**
```bash
curl -X POST "http://localhost:8000/api/v1/vms/proxmox1/100/shutdown"
```

#### 6. Redémarrer une VM

**POST** `/api/v1/vms/{node}/{vmid}/reboot`

Redémarrer une VM en cours d'exécution.

**Exemple de Requête :**
```bash
curl -X POST "http://localhost:8000/api/v1/vms/proxmox1/100/reboot"
```

#### 7. Suspendre une VM

**POST** `/api/v1/vms/{node}/{vmid}/suspend`

Suspendre une VM en cours d'exécution (enregistrer l'état sur disque).

**Exemple de Requête :**
```bash
curl -X POST "http://localhost:8000/api/v1/vms/proxmox1/100/suspend"
```

#### 8. Reprendre une VM

**POST** `/api/v1/vms/{node}/{vmid}/resume`

Reprendre une VM suspendue.

**Exemple de Requête :**
```bash
curl -X POST "http://localhost:8000/api/v1/vms/proxmox1/100/resume"
```

---

## 🆕 Endpoints Simplifiés (Recommandés pour le Frontend)

Ces endpoints détectent automatiquement sur quel nœud se trouve une VM, vous n'avez donc pas besoin de le spécifier dans l'URL.

#### 9. Démarrer une VM (Simplifié)

**POST** `/api/v1/vms/{vmid}/start`

Démarrer une VM sans spécifier le nœud.

**Exemple de Requête :**
```bash
curl -X POST "http://localhost:8000/api/v1/vms/100/start"
```

**Exemple de Réponse :**
```json
{
  "success": true,
  "message": "VM start command sent successfully",
  "node": "proxmox1",
  "data": {
    "data": "UPID:proxmox1:..."
  }
}
```

#### 10. Arrêter une VM (Simplifié avec Mode)

**POST** `/api/v1/vms/{vmid}/stop`

Arrêter une VM avec paramètre de mode optionnel.

**Corps de la Requête :**
```json
{
  "mode": "acpi"
}
```

**Modes :**
- `acpi` (par défaut) - Extinction ACPI gracieuse (recommandé)
- `hard` - Arrêt forcé (immédiat, peut causer une perte de données)

**Exemple de Requête (extinction ACPI) :**
```bash
curl -X POST "http://localhost:8000/api/v1/vms/100/stop" \
  -H "Content-Type: application/json" \
  -d '{"mode": "acpi"}'
```

**Exemple de Requête (arrêt forcé) :**
```bash
curl -X POST "http://localhost:8000/api/v1/vms/100/stop" \
  -H "Content-Type: application/json" \
  -d '{"mode": "hard"}'
```

**Exemple de Réponse :**
```json
{
  "success": true,
  "message": "VM stop command sent successfully (mode: acpi)",
  "node": "proxmox1",
  "mode": "acpi",
  "data": {
    "data": "UPID:proxmox1:..."
  }
}
```

---

## 📸 Gestion des Snapshots

#### 11. Lister les Snapshots

**GET** `/api/v1/vms/{vmid}/snapshots`

Obtenir tous les snapshots pour une VM.

**Exemple de Requête :**
```bash
curl "http://localhost:8000/api/v1/vms/100/snapshots"
```

**Exemple de Réponse :**
```json
{
  "success": true,
  "data": [
    {
      "name": "avant-mise-a-jour",
      "description": "Snapshot avant mise à jour système",
      "snaptime": 1704067200,
      "vmstate": 1,
      "parent": "current"
    },
    {
      "name": "installation-propre",
      "description": "Installation fraîche",
      "snaptime": 1703980800,
      "parent": "no-parent"
    }
  ],
  "count": 2
}
```

#### 12. Créer un Snapshot

**POST** `/api/v1/vms/{vmid}/snapshot`

Créer un nouveau snapshot.

**Corps de la Requête :**
```json
{
  "name": "nom-du-snapshot",
  "description": "Description optionnelle"
}
```

**Exemple de Requête :**
```bash
curl -X POST "http://localhost:8000/api/v1/vms/100/snapshot" \
  -H "Content-Type: application/json" \
  -d '{"name": "avant-mise-a-jour", "description": "Snapshot avant mise à jour système"}'
```

**Exemple de Réponse :**
```json
{
  "success": true,
  "message": "Snapshot creation initiated",
  "snapshot": "avant-mise-a-jour",
  "data": {
    "data": "UPID:proxmox1:..."
  }
}
```

#### 13. Revenir à un Snapshot

**POST** `/api/v1/vms/{vmid}/snapshot/{snapname}/rollback`

Revenir une VM à un snapshot spécifique.

**Exemple de Requête :**
```bash
curl -X POST "http://localhost:8000/api/v1/vms/100/snapshot/avant-mise-a-jour/rollback"
```

**Exemple de Réponse :**
```json
{
  "success": true,
  "message": "Rollback to snapshot 'avant-mise-a-jour' initiated",
  "snapshot": "avant-mise-a-jour",
  "data": {
    "data": "UPID:proxmox1:..."
  }
}
```

---

## 🔔 Flux d'Événements

#### 14. Flux d'Événements (Server-Sent Events)

**GET** `/api/v1/events/stream`

Flux d'événements Proxmox en temps réel utilisant Server-Sent Events (SSE).

**Types d'Événements :**
- `connected` - Connexion initiale établie
- `heartbeat` - Signal de maintien en vie (toutes les 15 secondes)
- `vm_start` - VM démarrée
- `vm_stop` - VM arrêtée
- `vm_shutdown` - VM éteinte
- `vm_reboot` - VM redémarrée
- `vm_suspend` - VM suspendue
- `vm_resume` - VM reprise
- `vm_snapshot` - Snapshot créé
- `vm_rollback` - Retour à un snapshot
- `vm_create` - VM créée
- `vm_destroy` - VM détruite
- `task` - Autre tâche
- `error` - Erreur survenue

**Exemple d'Utilisation (JavaScript) :**
```javascript
const eventSource = new EventSource('http://localhost:8000/api/v1/events/stream');

// Écouter les événements de démarrage de VM
eventSource.addEventListener('vm_start', (event) => {
  const data = JSON.parse(event.data);
  console.log('VM démarrée:', data);
});

// Écouter tous les événements de tâche
eventSource.addEventListener('task', (event) => {
  const data = JSON.parse(event.data);
  console.log('Tâche:', data);
});

// Écouter le heartbeat
eventSource.addEventListener('heartbeat', (event) => {
  console.log('Connexion active');
});

// Gérer les erreurs
eventSource.addEventListener('error', (event) => {
  const data = JSON.parse(event.data);
  console.error('Erreur:', data);
});

// Nettoyer quand terminé
// eventSource.close();
```

**Exemple de Données d'Événement :**
```json
{
  "upid": "UPID:proxmox1:00001234:00ABCDEF:...",
  "node": "proxmox1",
  "pid": 4660,
  "pstart": 11263983,
  "starttime": 1704067200,
  "type": "qmstart",
  "id": "100",
  "user": "root@pam",
  "status": "running"
}
```

---

## 🏥 Vérifications de Santé

Ces endpoints sont conçus pour les sondes de vivacité et de disponibilité Kubernetes.

#### 15. Vérification de Santé (Vivacité)

**GET** `/health`

Sonde de vivacité basique - vérifie si l'application est en cours d'exécution.

**Exemple de Requête :**
```bash
curl "http://localhost:8000/health"
```

**Exemple de Réponse :**
```json
{
  "status": "ok",
  "timestamp": 1704067200,
  "service": "ManageVMBack"
}
```

**Code de Statut :** `200 OK`

#### 16. Vérification de Disponibilité

**GET** `/ready`

Sonde de disponibilité - vérifie si l'application peut servir du trafic et si Proxmox est accessible.

**Exemple de Requête :**
```bash
curl "http://localhost:8000/ready"
```

**Exemple de Réponse (Prêt) :**
```json
{
  "status": "ready",
  "timestamp": 1704067200,
  "proxmox": {
    "connected": true,
    "version": "8.1.3"
  }
}
```

**Code de Statut :** `200 OK`

**Exemple de Réponse (Non Prêt) :**
```json
{
  "status": "not_ready",
  "reason": "Cannot connect to Proxmox API",
  "timestamp": 1704067200
}
```

**Code de Statut :** `503 Service Unavailable`

---

### Réponses d'Erreur

Tous les endpoints retournent des réponses d'erreur dans le format suivant :

```json
{
  "success": false,
  "error": "Description du message d'erreur"
}
```

Codes de statut HTTP courants :
- `200` - Succès
- `400` - Requête invalide (paramètres manquants ou invalides)
- `500` - Erreur serveur (erreur API Proxmox, erreur réseau, etc.)

## 🏗️ Structure du Projet

```
ManageVMBack/
├── bin/
│   └── console              # Console Symfony
├── config/
│   ├── packages/            # Configurations des bundles
│   ├── routes/              # Configurations de routage
│   └── services.yaml        # Configuration du conteneur de services
├── public/
│   └── index.php            # Point d'entrée
├── src/
│   ├── Controller/
│   │   ├── VmController.php         # Endpoints API VM
│   │   ├── EventsController.php     # Flux d'événements SSE
│   │   └── HealthController.php     # Vérifications de santé
│   ├── Service/
│   │   └── ProxmoxClient.php        # Client API Proxmox
│   └── Kernel.php
├── .env                     # Configuration d'environnement (NE PAS COMMITER)
├── composer.json            # Dépendances PHP
└── README.md
```

## 🔧 Développement

### Vérifier les Routes

Lister toutes les routes disponibles :
```bash
php bin/console debug:router
```

Filtrer les routes liées aux VMs :
```bash
php bin/console debug:router | grep vms
```

### Vider le Cache

Après des changements de configuration :
```bash
php bin/console cache:clear
```

### Débogage

Activer le mode debug en configurant dans `.env` :
```env
APP_ENV=dev
```

Voir les logs :
```bash
tail -f var/log/dev.log
```

## 🔒 Considérations de Sécurité

### Sécurité Générale

1. **Ne jamais commiter le fichier `.env`** - Il contient des identifiants sensibles
2. **Utiliser des tokens API au lieu de mots de passe** en production
3. **Activer la vérification SSL** (`PROXMOX_VERIFY_SSL=true`) avec des certificats valides
4. **Mettre à jour `CORS_ALLOW_ORIGIN`** pour correspondre uniquement au domaine de votre frontend
5. **Changer `APP_SECRET`** pour une valeur aléatoire en production
6. **Restreindre les permissions utilisateur Proxmox** - Créer un utilisateur dédié avec les permissions minimales requises

### Sécurité JWT

1. **Protéger les clés JWT** :
   - Les fichiers `config/jwt/private.pem` et `config/jwt/public.pem` sont critiques
   - Ne jamais commiter ces fichiers dans Git (déjà dans `.gitignore`)
   - En production, stocker la clé privée de manière sécurisée (variables d'environnement, vault, etc.)
   - Permissions recommandées : `chmod 600 config/jwt/private.pem`

2. **Configurer une passphrase forte** :
   ```bash
   # Dans .env, définir une passphrase forte
   JWT_PASSPHRASE=VotrePassphraseComplexeEtSecurisee123!@#
   ```

3. **Gérer l'expiration des tokens** :
   - Par défaut : 1 heure (`token_ttl: 3600` dans `config/packages/lexik_jwt_authentication.yaml`)
   - Ajuster selon vos besoins de sécurité
   - Implémenter un système de refresh tokens pour les sessions longues

4. **Remplacer les utilisateurs en mémoire en production** :
   - Les utilisateurs actuels (`admin`, `user`) sont pour le développement uniquement
   - Créer une entité User avec Doctrine pour la production
   - Exemple de création d'entité User :
   ```bash
   php bin/console make:user
   php bin/console make:migration
   php bin/console doctrine:migrations:migrate
   ```

5. **Transmission sécurisée** :
   - Toujours utiliser HTTPS en production
   - Les tokens JWT ne doivent jamais être exposés dans les URLs
   - Stocker les tokens côté client de manière sécurisée (httpOnly cookies recommandés)

6. **Validation côté serveur** :
   - Les tokens sont automatiquement validés par le bundle
   - Vérifier les rôles utilisateur dans vos contrôleurs si nécessaire :
   ```php
   $this->denyAccessUnlessGranted('ROLE_ADMIN');
   ```

7. **Rotation des clés** :
   - En cas de compromission, regénérer les clés :
   ```bash
   php bin/console lexik:jwt:generate-keypair --overwrite
   ```
   - Tous les tokens existants seront invalides après la rotation

### Permissions Proxmox Recommandées

Créer un utilisateur/rôle dédié pour l'API avec uniquement les permissions requises :
- `VM.Audit` - Voir les informations VM
- `VM.PowerMgmt` - Démarrer/arrêter/redémarrer les VMs
- `VM.Console` - Accéder à la console VM (si nécessaire)
- `VM.Snapshot` - Gérer les snapshots

## 🐛 Dépannage

### Erreurs "Environment variable not found"

Assurez-vous que toutes les variables requises sont définies dans `.env` :
- `APP_SECRET`
- `DEFAULT_URI`
- `CORS_ALLOW_ORIGIN`
- `PROXMOX_HOST`
- `PROXMOX_USER`
- `PROXMOX_PASSWORD`
- `PROXMOX_VERIFY_SSL`

### Impossible de se connecter à Proxmox

1. Vérifiez que Proxmox est accessible : `curl -k https://votre-hote-proxmox:8006`
2. Vérifiez que les identifiants sont corrects
3. Vérifiez que les règles de pare-feu permettent la connexion depuis le serveur backend
4. Si vous utilisez des certificats auto-signés, assurez-vous que `PROXMOX_VERIFY_SSL=false`

### Erreurs CORS dans le navigateur

Mettez à jour `CORS_ALLOW_ORIGIN` dans `.env` pour correspondre au domaine de votre frontend :
```env
# Pour le développement local sur le port 3000
CORS_ALLOW_ORIGIN='^https?://(localhost|127\.0\.0\.1)(:[0-9]+)?$'

# Pour un domaine de production
CORS_ALLOW_ORIGIN='^https?://(www\.)?votredomaine\.com$'
```

### Erreurs 500

1. Vérifiez les logs Symfony : `var/log/dev.log`
2. Vérifiez que l'API Proxmox répond
3. Vérifiez que l'utilisateur Proxmox a les permissions requises
4. Videz le cache : `php bin/console cache:clear`

### VM non trouvée

Si vous obtenez "VM with ID {vmid} not found on any node" :
1. Vérifiez que le VMID existe dans Proxmox
2. Vérifiez que l'utilisateur a les permissions pour voir cette VM
3. Vérifiez que la VM n'est pas un conteneur LXC (cette API gère uniquement les VMs QEMU)

### Problèmes JWT

**"JWT Token not found"**
- Vérifiez que vous incluez l'en-tête : `Authorization: Bearer VOTRE_TOKEN`
- Vérifiez qu'il n'y a pas d'espace supplémentaire dans l'en-tête

**"Invalid JWT Token"**
- Le token est malformé ou corrompu
- Obtenez un nouveau token via `/api/login_check`

**"Expired JWT Token"**
- Le token a expiré (durée de vie : 1 heure)
- Obtenez un nouveau token via `/api/login_check`

**"Invalid credentials"**
- Username ou password incorrect
- Vérifiez les utilisateurs configurés dans `config/packages/security.yaml`
- Credentials par défaut : `admin` / `admin123` ou `user` / `user123`

**Les clés JWT n'existent pas**
```bash
# Générer les clés
php bin/console lexik:jwt:generate-keypair

# Vérifier les permissions
chmod 600 config/jwt/private.pem
chmod 644 config/jwt/public.pem
```

**Route `/api/login_check` not found**
```bash
# Vider le cache
php bin/console cache:clear

# Vérifier les routes
php bin/console debug:router | grep login
```

## 📝 Ajout de Nouvelles Fonctionnalités

### Ajouter de Nouvelles Opérations VM

1. Ajouter une méthode à `ProxmoxClient.php` :
```php
public function operationPersonnalisee(string $node, int $vmid): array
{
    return $this->post("/nodes/{$node}/qemu/{$vmid}/endpoint-personnalise");
}
```

2. Ajouter une action de contrôleur à `VmController.php` :
```php
#[Route('/vms/{vmid}/personnalise', name: 'api_vms_personnalise', methods: ['POST'])]
public function personnalise(int $vmid): JsonResponse
{
    try {
        $node = $this->proxmoxClient->findVMNode($vmid);
        $result = $this->proxmoxClient->operationPersonnalisee($node, $vmid);
        return $this->json(['success' => true, 'data' => $result]);
    } catch (\Exception $e) {
        return $this->json(['success' => false, 'error' => $e->getMessage()], 500);
    }
}
```

3. Vider le cache :
```bash
php bin/console cache:clear
```

## 🧪 Tests

### Tester l'Authentification JWT

```bash
# 1. Tester l'accès sans authentification (devrait échouer avec 401)
curl http://localhost:8000/api/v1/vms
# Réponse attendue : {"code":401,"message":"JWT Token not found"}

# 2. Obtenir un token JWT
curl -X POST http://localhost:8000/api/login_check \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"admin123"}'
# Réponse attendue : {"token":"eyJ0eXAiOiJKV1QiLCJ..."}

# 3. Tester avec un token valide
TOKEN="VOTRE_TOKEN_ICI"
curl -H "Authorization: Bearer $TOKEN" http://localhost:8000/api/v1/vms
# Devrait retourner la liste des VMs (ou une erreur Proxmox si non configuré)

# 4. Tester avec des credentials invalides
curl -X POST http://localhost:8000/api/login_check \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"mauvais_password"}'
# Réponse attendue : {"code":401,"message":"Invalid credentials."}
```

### Tester les Endpoints

```bash
# Tester la santé (pas d'authentification requise)
curl http://localhost:8000/health

# Tester la disponibilité (pas d'authentification requise)
curl http://localhost:8000/ready

# Obtenir un token et le stocker
TOKEN=$(curl -s -X POST http://localhost:8000/api/login_check \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"admin123"}' \
  | jq -r '.token')

# Lister les VMs (avec authentification)
curl -H "Authorization: Bearer $TOKEN" \
  "http://localhost:8000/api/v1/vms?state=running"

# Démarrer une VM
curl -X POST -H "Authorization: Bearer $TOKEN" \
  http://localhost:8000/api/v1/vms/100/start

# Arrêter une VM (gracieux)
curl -X POST -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"mode": "acpi"}' \
  http://localhost:8000/api/v1/vms/100/stop

# Lister les snapshots
curl -H "Authorization: Bearer $TOKEN" \
  http://localhost:8000/api/v1/vms/100/snapshots

# Créer un snapshot
curl -X POST -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"name": "test-snapshot", "description": "Test"}' \
  http://localhost:8000/api/v1/vms/100/snapshot

# Revenir à un snapshot
curl -X POST -H "Authorization: Bearer $TOKEN" \
  http://localhost:8000/api/v1/vms/100/snapshot/test-snapshot/rollback

# Flux d'événements (garder ouvert)
curl -H "Authorization: Bearer $TOKEN" \
  http://localhost:8000/api/v1/events/stream
```

## 🚀 Déploiement

### Configuration Docker/Kubernetes

**Exemple de configuration Kubernetes :**

```yaml
apiVersion: apps/v1
kind: Deployment
metadata:
  name: managevmback
spec:
  replicas: 2
  selector:
    matchLabels:
      app: managevmback
  template:
    metadata:
      labels:
        app: managevmback
    spec:
      containers:
      - name: managevmback
        image: votre-image:latest
        ports:
        - containerPort: 8000
        env:
        - name: APP_ENV
          value: "prod"
        - name: PROXMOX_HOST
          valueFrom:
            secretKeyRef:
              name: proxmox-secret
              key: host
        - name: PROXMOX_USER
          valueFrom:
            secretKeyRef:
              name: proxmox-secret
              key: user
        - name: PROXMOX_PASSWORD
          valueFrom:
            secretKeyRef:
              name: proxmox-secret
              key: password
        livenessProbe:
          httpGet:
            path: /health
            port: 8000
          initialDelaySeconds: 30
          periodSeconds: 10
        readinessProbe:
          httpGet:
            path: /ready
            port: 8000
          initialDelaySeconds: 5
          periodSeconds: 5
```

### Variables d'Environnement de Production

En production, définissez ces variables d'environnement :

```env
APP_ENV=prod
APP_SECRET=<secret-aleatoire-long>
PROXMOX_HOST=https://proxmox.votredomaine.com:8006
PROXMOX_USER=api-user@pam
PROXMOX_PASSWORD=<mot-de-passe-securise>
PROXMOX_VERIFY_SSL=true
CORS_ALLOW_ORIGIN='^https?://(www\.)?votredomaine\.com$'
```

## 🤝 Contribution

1. Créer une branche de fonctionnalité
2. Effectuer vos modifications
3. Tester minutieusement avec votre environnement Proxmox
4. Créer une pull request

## 📄 Licence

Voir le fichier LICENSE pour plus de détails.

## 👥 Équipe

Maintenu par l'équipe gérant l'infrastructure Proxmox VE.

## 🔗 Liens Utiles

- [Documentation API Proxmox VE](https://pve.proxmox.com/pve-docs/api-viewer/)
- [Documentation Symfony](https://symfony.com/doc/current/index.html)
- [Documentation API Platform](https://api-platform.com/docs/)
- [Server-Sent Events (SSE)](https://developer.mozilla.org/fr/docs/Web/API/Server-sent_events)

---

J'ai trop la flemme de faire plus alors cherchez sur google si ça marche pas

**Bonne chance les mecs ! 🔥**