# Security — Credential Rotation & Git History Cleanup

## Contexte

Le fichier `.env` a été commité dans l'historique git (commits `a0ed079`,
`dc658c5`, `ccf0dc1`, `f769f01`, etc.) exposant les credentials suivants :

- `DB_PASSWORD` (mot de passe PostgreSQL)
- `APP_KEY` (clé de chiffrement Laravel)
- `REVERB_APP_KEY` / `REVERB_APP_SECRET` (WebSocket)

Ces credentials sont **compromis** et doivent être changés.

---

## Étape 1 — Rotation immédiate des credentials

### 1.1 Nouveau APP_KEY Laravel

```bash
cd backend
php artisan key:generate --force
# ou manuellement :
echo "APP_KEY=base64:$(openssl rand -base64 32)"
```

### 1.2 Nouveau mot de passe PostgreSQL

```bash
# Dans le container PostgreSQL :
docker compose exec db psql -U postgres -c \
  "ALTER USER pos_user PASSWORD '$(openssl rand -hex 16)';"
# Mettre à jour DB_PASSWORD dans .env
```

### 1.3 Nouveaux secrets Reverb

```bash
echo "REVERB_APP_KEY=$(openssl rand -hex 16)"
echo "REVERB_APP_SECRET=$(openssl rand -hex 16)"
echo "REVERB_APP_ID=$(shuf -i 100000-999999 -n 1)"
# Mettre à jour dans .env et redémarrer : docker compose restart reverb
```

---

## Étape 2 — Purger .env de l'historique git

> ⚠️ **Action destructive et irréversible.** Tous les collaborateurs devront
> re-cloner le repo après cette opération.

### Méthode recommandée : `git-filter-repo`

```bash
# Installation (une seule fois)
pip install git-filter-repo
# ou : brew install git-filter-repo

# Purger .env de TOUT l'historique
git filter-repo --path .env --invert-paths --force

# Vérifier que .env n'apparaît plus
git log --all --oneline -- .env  # doit être vide

# Forcer le push de toutes les branches
git push origin --force --all
git push origin --force --tags
```

### Alternative : BFG Repo-Cleaner

```bash
# Télécharger bfg.jar depuis https://rtyley.github.io/bfg-repo-cleaner/
java -jar bfg.jar --delete-files .env
git reflog expire --expire=now --all
git gc --prune=now --aggressive
git push origin --force --all
```

---

## Étape 3 — Installer le pre-commit hook

Ce hook empêche de committer accidentellement des fichiers contenant des secrets.

```bash
cat > .git/hooks/pre-commit << 'HOOK'
#!/usr/bin/env bash
# Bloque le commit si des fichiers sensibles sont stagés
FORBIDDEN=(.env backend/.env frontend/.env)
for f in "${FORBIDDEN[@]}"; do
    if git diff --cached --name-only | grep -qx "$f"; then
        echo "❌ ERREUR : '$f' ne doit pas être commité (secrets en clair)"
        exit 1
    fi
done

# Scan des patterns de secrets dans les fichiers stagés
if git diff --cached | grep -qE '(DB_PASSWORD|APP_KEY|REVERB_APP_SECRET|SECRET_KEY)=[^$\{]'; then
    echo "❌ ERREUR : Des secrets semblent être inclus dans ce commit"
    echo "   Vérifiez les fichiers stagés avec : git diff --cached"
    exit 1
fi
HOOK
chmod +x .git/hooks/pre-commit
echo "✅ Pre-commit hook installé"
```

---

## Étape 4 — Vérification post-nettoyage

```bash
# Aucune sortie = .env purgé
git log --all --oneline -- .env

# Scanner avec TruffleHog (optionnel)
pip install trufflehog
trufflehog git file://. --only-verified
```

---

## Outils de surveillance continue

- **GitGuardian** : https://www.gitguardian.com (scan automatique des repos)
- **GitHub Secret Scanning** : activé par défaut sur les repos privés GitHub
- **pre-commit** : https://pre-commit.com (hooks standardisés multi-projets)

---

## Contact sécurité

Pour signaler une vulnérabilité : ouvrir une issue privée sur GitHub
ou contacter l'équipe technique directement.
