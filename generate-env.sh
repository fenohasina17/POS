#!/usr/bin/env bash
# Génère le fichier .env si absent
set -euo pipefail

ENV_FILE="${1:-.env}"

if [[ -f "$ENV_FILE" ]]; then
    echo "[.env] Fichier existant — conservé."
    exit 0
fi

echo "[.env] Génération du fichier $ENV_FILE..."

# Détection IP locale (fallback 127.0.0.1)
SERVER_IP=$(ip route get 1.1.1.1 2>/dev/null | awk '/src/{print $7}' | head -1)
[[ -z "$SERVER_IP" ]] && SERVER_IP="127.0.0.1"

# Génération des secrets
APP_KEY="base64:$(openssl rand -base64 32)"
DB_PASSWORD=$(openssl rand -hex 16)
REVERB_APP_ID=$(shuf -i 100000-999999 -n 1)
REVERB_APP_KEY=$(openssl rand -hex 16)
REVERB_APP_SECRET=$(openssl rand -hex 16)

cat > "$ENV_FILE" <<EOF
APP_NAME=POS
APP_ENV=production
APP_KEY=${APP_KEY}
APP_DEBUG=false
APP_URL=http://${SERVER_IP}:8000

FRONTEND_URL=http://${SERVER_IP}:5173
SANCTUM_STATEFUL_DOMAINS=${SERVER_IP}:5173
SANCTUM_TOKEN_EXPIRATION=480

VITE_API_URL=http://${SERVER_IP}:8000

DB_CONNECTION=pgsql
DB_HOST=db
DB_PORT=5432
DB_DATABASE=pos_system
DB_USERNAME=pos_user
DB_PASSWORD=${DB_PASSWORD}

CACHE_STORE=redis
REDIS_CLIENT=phpredis
REDIS_HOST=redis
REDIS_PORT=6379
REDIS_PASSWORD=

SESSION_DRIVER=file
SESSION_LIFETIME=120
QUEUE_CONNECTION=sync

LOG_CHANNEL=daily
LOG_LEVEL=error
LOG_DEPRECATIONS_CHANNEL=null

BROADCAST_CONNECTION=reverb
REVERB_APP_ID=${REVERB_APP_ID}
REVERB_APP_KEY=${REVERB_APP_KEY}
REVERB_APP_SECRET=${REVERB_APP_SECRET}
REVERB_HOST=${SERVER_IP}
REVERB_PORT=8000
REVERB_SCHEME=ws

VITE_REVERB_APP_KEY=${REVERB_APP_KEY}
VITE_REVERB_HOST=${SERVER_IP}
VITE_REVERB_PORT=8000
VITE_REVERB_SCHEME=ws
EOF

chmod 600 "$ENV_FILE"
echo "[.env] Généré avec succès (IP: ${SERVER_IP})"
