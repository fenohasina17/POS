# 🚀 DevOps Project — Laravel + Vue.js + PostgreSQL

Pipeline CI/CD complet sur Docker Desktop + Kubernetes (WSL2 Windows 10)

---

## 📁 Structure du projet

```
devops-project/
├── backend/                        ← Laravel (PHP 8.2)
│   ├── Dockerfile
│   └── .env.example
├── frontend/                       ← Vue.js 3 + Vite
│   ├── Dockerfile
│   └── nginx.conf                  ← Nginx SPA config
├── docker/                         ← Environnement local
│   ├── docker-compose.yml
│   ├── nginx/
│   │   └── backend.conf
│   └── postgres/
│       └── init.sql
├── k8s/                            ← Manifests Kubernetes
│   ├── namespace.yaml
│   ├── ingress.yaml
│   ├── postgres/
│   │   ├── secret.yaml
│   │   ├── pvc.yaml
│   │   ├── deployment.yaml
│   │   └── service.yaml
│   ├── backend/
│   │   ├── configmap.yaml
│   │   ├── nginx-configmap.yaml
│   │   ├── secret.yaml
│   │   ├── deployment.yaml
│   │   └── service.yaml
│   └── frontend/
│       └── deployment.yaml         ← Deployment + Service
├── jenkins/
│   └── docker-compose.jenkins.yml
├── Jenkinsfile                     ← Pipeline CI/CD
├── setup.sh                        ← Script d'installation
└── README.md
```

---

## ⚡ Démarrage rapide

### Prérequis
- Windows 10 + WSL2
- Docker Desktop (avec Kubernetes activé)
- Git

### Installation

```bash
# 1. Cloner et entrer dans le projet
cd ~/devops-project

# 2. Rendre le script exécutable et lancer
chmod +x setup.sh
./setup.sh
```

---

## 🐳 Développement Local (Docker Compose)

```bash
# Démarrer tous les services
docker compose -f docker/docker-compose.yml up -d --build

# Voir les logs
docker compose -f docker/docker-compose.yml logs -f

# Arrêter
docker compose -f docker/docker-compose.yml down

# Réinitialiser la BDD
docker compose -f docker/docker-compose.yml down -v
```

| Service    | URL                         |
|------------|-----------------------------|
| Frontend   | http://localhost:5173        |
| Backend    | http://localhost:8000/api   |
| PostgreSQL | localhost:5432              |

### Commandes Laravel utiles

```bash
# Migrations
docker exec devops_backend php artisan migrate

# Seeder
docker exec devops_backend php artisan db:seed

# Tests PHPUnit
docker exec devops_backend php vendor/bin/phpunit

# Cache clear
docker exec devops_backend php artisan cache:clear
docker exec devops_backend php artisan config:clear
```

---

## ☸️ Kubernetes (Docker Desktop)

### 1. Registry Docker local

```bash
# Lancer un registry local
docker run -d --name registry --restart=always \
  -p 5000:5000 registry:2

# Dans Docker Desktop > Settings > Docker Engine, ajouter :
# "insecure-registries": ["localhost:5000"]
```

### 2. Build & Push des images

```bash
# Backend
docker build -t localhost:5000/backend:v1.0 ./backend
docker push localhost:5000/backend:v1.0

# Frontend
docker build -t localhost:5000/frontend:v1.0 ./frontend
docker push localhost:5000/frontend:v1.0
```

### 3. Encoder les secrets en base64

```bash
# Linux/WSL
echo -n "StrongP@ssw0rd!" | base64

# Générer APP_KEY Laravel
php artisan key:generate --show
# ou via Docker :
docker run --rm php:8.2-cli php -r "echo 'base64:'.base64_encode(random_bytes(32));"
```

### 4. Modifier les secrets

```bash
# Editer k8s/postgres/secret.yaml et k8s/backend/secret.yaml
# avec les valeurs base64 correctes, puis :
kubectl apply -f k8s/postgres/secret.yaml
kubectl apply -f k8s/backend/secret.yaml
```

### 5. Remplacer les tags d'image dans les deployments

```bash
# Dans k8s/backend/deployment.yaml et k8s/frontend/deployment.yaml
# Remplacer REGISTRY/backend:IMAGE_TAG par :
# localhost:5000/backend:v1.0

sed -i 's|REGISTRY/backend:IMAGE_TAG|localhost:5000/backend:v1.0|g' k8s/backend/deployment.yaml
sed -i 's|REGISTRY/frontend:IMAGE_TAG|localhost:5000/frontend:v1.0|g' k8s/frontend/deployment.yaml
```

### 6. Déployer sur Kubernetes

```bash
# Ordre de déploiement important !
kubectl apply -f k8s/namespace.yaml
kubectl apply -f k8s/postgres/secret.yaml
kubectl apply -f k8s/postgres/pvc.yaml
kubectl apply -f k8s/postgres/deployment.yaml
kubectl apply -f k8s/postgres/service.yaml

# Attendre PostgreSQL
kubectl rollout status deployment/postgres -n devops-app

# Backend
kubectl apply -f k8s/backend/nginx-configmap.yaml
kubectl apply -f k8s/backend/configmap.yaml
kubectl apply -f k8s/backend/secret.yaml
kubectl apply -f k8s/backend/deployment.yaml
kubectl apply -f k8s/backend/service.yaml

# Frontend
kubectl apply -f k8s/frontend/deployment.yaml

# Ingress
kubectl apply -f k8s/ingress.yaml
```

### 7. Vérifier le déploiement

```bash
kubectl get all -n devops-app
kubectl get pods -n devops-app
kubectl logs -f deployment/backend -n devops-app
kubectl describe pod <pod-name> -n devops-app
```

### 8. Accéder à l'application

Ajouter dans `C:\Windows\System32\drivers\etc\hosts` :
```
127.0.0.1   app.local
```

Puis ouvrir : http://app.local

---

## 🤖 Jenkins — Pipeline CI/CD

### Démarrage Jenkins

```bash
docker compose -f jenkins/docker-compose.jenkins.yml up -d

# Récupérer le mot de passe initial
docker exec jenkins cat /var/jenkins_home/secrets/initialAdminPassword
```

Accès : http://localhost:8080

### Configuration Jenkins (étapes)

1. **Plugins à installer** (après le wizard) :
   - Git, Pipeline, Docker Pipeline
   - Kubernetes CLI
   - Blue Ocean (optionnel, interface moderne)

2. **Credentials à créer** (Manage Jenkins > Credentials) :

   | ID             | Type                | Valeur                              |
   |----------------|---------------------|-------------------------------------|
   | `kubeconfig`   | Secret file         | Contenu de `~/.kube/config`        |
   | `docker-reg`   | Username/Password   | (si registry privé avec auth)      |

3. **Nouveau Pipeline** :
   - New Item > Pipeline
   - Definition : Pipeline script from SCM
   - SCM : Git → URL de votre repo
   - Script Path : `Jenkinsfile`

### Flux du Pipeline

```
Checkout
   ↓
Tests Backend (PHPUnit)  ──┐
                            ├── parallèle
Tests Frontend (Vitest)  ──┘
   ↓
Build Images Docker
  Backend  ──┐
             ├── parallèle
  Frontend ──┘
   ↓
Push → Registry local (localhost:5000)
   ↓
Deploy K8s (seulement sur branche main)
  ├── Appliquer les manifests
  ├── kubectl rollout status
  └── ✅ Done
```

---

## 🔧 Dépannage

```bash
# Pod bloqué en Pending
kubectl describe pod <pod-name> -n devops-app

# Logs d'un pod en erreur
kubectl logs <pod-name> -n devops-app --previous

# Accéder à un pod en bash
kubectl exec -it <pod-name> -n devops-app -- sh

# Migrations manuelles depuis K8s
kubectl exec -it deployment/backend -n devops-app -- php artisan migrate

# Vider le PVC postgres (ATTENTION : perte de données)
kubectl delete pvc postgres-pvc -n devops-app

# Redémarrer un déploiement
kubectl rollout restart deployment/backend -n devops-app
```

---

## 📊 Architecture globale

```
┌─────────────────────────────────────────────┐
│            Windows 10 / WSL2                │
│                                             │
│  ┌──────────────────────────────────────┐   │
│  │         Docker Desktop               │   │
│  │                                      │   │
│  │  ┌─────────┐   ┌──────────────────┐  │   │
│  │  │ Jenkins │   │   Kubernetes     │  │   │
│  │  │ :8080   │   │                  │  │   │
│  │  └────┬────┘   │  ┌────────────┐  │  │   │
│  │       │ CI/CD  │  │  Ingress   │  │  │   │
│  │       └───────►│  │  (Nginx)   │  │  │   │
│  │                │  └─────┬──────┘  │  │   │
│  │  ┌──────────┐  │        │         │  │   │
│  │  │ Registry │  │  ┌─────┴──────┐  │  │   │
│  │  │ :5000    │◄─┤  │  Frontend  │  │  │   │
│  │  └──────────┘  │  │  (Vue.js)  │  │  │   │
│  │                │  └────────────┘  │  │   │
│  │                │  ┌────────────┐  │  │   │
│  │                │  │  Backend   │  │  │   │
│  │                │  │ (Laravel)  │  │  │   │
│  │                │  └─────┬──────┘  │  │   │
│  │                │        │         │  │   │
│  │                │  ┌─────┴──────┐  │  │   │
│  │                │  │ PostgreSQL │  │  │   │
│  │                │  │  + PVC     │  │  │   │
│  │                │  └────────────┘  │  │   │
│  │                └──────────────────┘  │   │
│  └──────────────────────────────────────┘   │
└─────────────────────────────────────────────┘
```
