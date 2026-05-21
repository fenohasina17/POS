# Guide de Déploiement & Workflow CI/CD

## Architecture

```
GitHub (main) ──→ Jenkins CI/CD ──→ Serveur de production
                                     ├── backend  (PHP-FPM)
                                     ├── nginx    (API reverse proxy)
                                     ├── frontend (Vue.js static)
                                     ├── db       (PostgreSQL)  ← données persistantes
                                     └── redis    (cache)       ← données persistantes
```

## Stratégie de branches

| Branche | Rôle | Action Jenkins |
|---------|------|----------------|
| `main` | Production | Tests + Déploiement automatique |
| `develop` | Intégration | Tests uniquement |
| `feature/*` | Nouvelles fonctionnalités | Aucune |

### Workflow de développement

```
feature/ma-fonctionnalite
    ↓ Pull Request + review
develop
    ↓ Tests OK + validation
main ──→ Jenkins déploie automatiquement
```

## Déploiement Jenkins

### Déploiement normal (chaque commit sur main)

```
1. Checkout du code depuis GitHub
2. Build des images Docker (backend + frontend)
3. Tests automatisés (si échec → UNSTABLE mais déploiement continue)
4. Rolling update :
   - db/redis  : non redémarrés (données préservées)
   - backend   : ~5s d'interruption
   - nginx     : ~2s d'interruption
   - frontend  : ~2s d'interruption
5. php artisan migrate --force  (nouvelles migrations uniquement)
6. Sync rôles + permissions (idempotent)
7. Nettoyage caches
```

**Les données clients ne sont JAMAIS supprimées.**

### Premier déploiement (serveur vierge)

Cocher `INITIAL_SETUP = true` dans Jenkins lors du premier build :

```
1. Même processus que ci-dessus
2. + migrate:fresh --seed --force
   → Crée toutes les tables + données initiales
   → Crée les utilisateurs de test
```

⚠️ **Ne jamais activer INITIAL_SETUP sur un serveur avec des données clients.**

### Commande manuelle de seed (si besoin)

```bash
docker compose -p deployement-application-web exec backend php artisan db:seed --force
```

## Comptes initiaux (après INITIAL_SETUP)

| Email | Mot de passe | Rôle |
|-------|-------------|------|
| benzenito@igp.com | password | admin |
| test@igp.com | password | - |

## URLs

| Service | URL |
|---------|-----|
| Application (clients) | http://192.168.0.9:5173 |
| API Backend | http://192.168.0.9:8000 |
| Jenkins CI/CD | http://192.168.0.9:9090 |

## Modifier l'IP du serveur

Si l'IP change, mettre à jour ces 3 fichiers et relancer Jenkins :
- `Jenkinsfile` (lignes APP_URL, FRONTEND_URL, VITE_API_URL)
- `.env` (APP_URL, FRONTEND_URL)

