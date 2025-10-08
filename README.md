# ManageVMBack

Service API backend qui fait le pont entre les applications frontend et la gestion des machines virtuelles Proxmox VE. Ce service fournit une API RESTful pour lister, surveiller et contrôler les VMs Proxmox sur plusieurs nœuds.

## 🎯 Objectif

Ce backend agit comme un middleware entre votre application frontend et les serveurs Proxmox VE, offrant :
- Points de terminaison API unifiés pour la gestion des VMs
- Gestion de l'authentification avec l'API Proxmox
- Agrégation de données depuis plusieurs nœuds Proxmox
- Capacités de pagination et de filtrage
- Gestion du cycle de vie des VMs (démarrage, arrêt, redémarrage, etc.)

## 📋 Prérequis

- PHP 8.2 ou supérieur
- Composer
- Symfony 7.x
- Accès à l'API Proxmox VE (v6.x ou v7.x)

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

### 5. Vider le cache

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

### Référence Rapide

| Catégorie | Méthode | Endpoint | Description |
|-----------|---------|----------|-------------|
| **VMs** | GET | `/api/v1/vms` | Lister toutes les VMs (avec filtres) |
| | GET | `/api/v1/vms/{node}/{vmid}` | Obtenir les détails d'une VM |
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

1. **Ne jamais commiter le fichier `.env`** - Il contient des identifiants sensibles
2. **Utiliser des tokens API au lieu de mots de passe** en production
3. **Activer la vérification SSL** (`PROXMOX_VERIFY_SSL=true`) avec des certificats valides
4. **Mettre à jour `CORS_ALLOW_ORIGIN`** pour correspondre uniquement au domaine de votre frontend
5. **Changer `APP_SECRET`** pour une valeur aléatoire en production
6. **Restreindre les permissions utilisateur Proxmox** - Créer un utilisateur dédié avec les permissions minimales requises

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

### Tester les Endpoints

```bash
# Tester la santé
curl http://localhost:8000/health

# Tester la disponibilité
curl http://localhost:8000/ready

# Lister les VMs
curl "http://localhost:8000/api/v1/vms?state=running"

# Démarrer une VM
curl -X POST http://localhost:8000/api/v1/vms/100/start

# Arrêter une VM (gracieux)
curl -X POST http://localhost:8000/api/v1/vms/100/stop \
  -H "Content-Type: application/json" \
  -d '{"mode": "acpi"}'

# Lister les snapshots
curl http://localhost:8000/api/v1/vms/100/snapshots

# Créer un snapshot
curl -X POST http://localhost:8000/api/v1/vms/100/snapshot \
  -H "Content-Type: application/json" \
  -d '{"name": "test-snapshot", "description": "Test"}'

# Revenir à un snapshot
curl -X POST http://localhost:8000/api/v1/vms/100/snapshot/test-snapshot/rollback

# Flux d'événements (garder ouvert)
curl http://localhost:8000/api/v1/events/stream
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