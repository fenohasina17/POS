#!/usr/bin/env bash
# ============================================================
#  POS Central — Script d'installation automatique
#  Cible : Ubuntu Server 22.04 LTS / Debian 12
# ============================================================
set -euo pipefail

RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'
BLUE='\033[0;34m'; CYAN='\033[0;36m'; BOLD='\033[1m'; NC='\033[0m'

log()  { echo -e "${GREEN}[✔]${NC} $*"; }
info() { echo -e "${BLUE}[i]${NC} $*"; }
warn() { echo -e "${YELLOW}[!]${NC} $*"; }
err()  { echo -e "${RED}[✘]${NC} $*" >&2; exit 1; }
step() { echo -e "\n${BOLD}${CYAN}▶ $*${NC}"; }

[[ $EUID -ne 0 ]] && err "Ce script doit être exécuté en root (sudo ./install.sh)"
[[ ! -f /etc/debian_version ]] && err "Ce script est réservé aux systèmes Debian/Ubuntu"

REPO_URL="${REPO_URL:-https://github.com/fenohasina17/POS.git}"
INSTALL_DIR="${INSTALL_DIR:-/opt/pos-central}"
APP_USER="${APP_USER:-central}"

detect_server_ip() {
    ip route get 1.1.1.1 2>/dev/null \
      | awk '{for(i=1;i<=NF;i++) if($i=="src") print $(i+1)}' | head -1
}

echo -e "${BOLD}"
echo "  ╔══════════════════════════════════════════╗"
echo "  ║   POS Central — Installation serveur    ║"
echo "  ╚══════════════════════════════════════════╝"
echo -e "${NC}"

# ── 1. Dépendances système ────────────────────────────────────
step "Installation des dépendances système"
apt-get update -qq
apt-get install -y -qq ca-certificates curl gnupg lsb-release git openssl make > /dev/null
log "Dépendances installées"

# ── 2. Docker ─────────────────────────────────────────────────
step "Vérification / installation de Docker"
if ! docker version &>/dev/null; then
    install -m 0755 -d /etc/apt/keyrings
    curl -fsSL https://download.docker.com/linux/ubuntu/gpg \
        | gpg --dearmor -o /etc/apt/keyrings/docker.gpg
    chmod a+r /etc/apt/keyrings/docker.gpg
    echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] \
https://download.docker.com/linux/ubuntu $(lsb_release -cs) stable" \
        > /etc/apt/sources.list.d/docker.list
    apt-get update -qq
    apt-get install -y -qq docker-ce docker-ce-cli containerd.io docker-compose-plugin > /dev/null
    log "Docker installé"
else
    log "Docker déjà présent : $(docker version --format '{{.Server.Version}}')"
fi
! docker compose version &>/dev/null && apt-get install -y -qq docker-compose-plugin > /dev/null
log "docker compose : $(docker compose version --short)"

# ── 3. Utilisateur applicatif ─────────────────────────────────
step "Création de l'utilisateur applicatif '$APP_USER'"
if ! id "$APP_USER" &>/dev/null; then
    useradd -r -m -s /bin/bash "$APP_USER"
    usermod -aG docker "$APP_USER"
    log "Utilisateur '$APP_USER' créé"
else
    log "Utilisateur '$APP_USER' déjà existant"
fi

# ── 4. Clonage du dépôt ───────────────────────────────────────
step "Clonage du dépôt dans $INSTALL_DIR"
if [[ -d "$INSTALL_DIR/.git" ]]; then
    git -C "$INSTALL_DIR" pull --ff-only
    log "Dépôt mis à jour"
else
    git clone "$REPO_URL" "$INSTALL_DIR"
    log "Dépôt cloné"
fi
chown -R "$APP_USER:$APP_USER" "$INSTALL_DIR"

# ── 5. Configuration .env ─────────────────────────────────────
step "Configuration de l'environnement"
ENV_FILE="$INSTALL_DIR/central/.env"

if [[ -f "$ENV_FILE" ]]; then
    warn ".env existant détecté — conservé (pas d'écrasement)"
else
    SERVER_IP=$(detect_server_ip)
    [[ -z "$SERVER_IP" ]] && SERVER_IP="127.0.0.1"

    APP_KEY="base64:$(openssl rand -base64 32)"
    DB_PASSWORD=$(openssl rand -hex 16)
    REDIS_PASSWORD=$(openssl rand -hex 16)
    REVERB_APP_ID=$(shuf -i 100000-999999 -n 1)
    REVERB_APP_KEY=$(openssl rand -hex 16)
    REVERB_APP_SECRET=$(openssl rand -hex 16)
    CENTRAL_API_KEY=$(openssl rand -hex 32)
    ADMIN_PASSWORD=$(openssl rand -base64 12 | tr -dc 'a-zA-Z0-9' | head -c 16)

    cat > "$ENV_FILE" <<EOF
APP_NAME="POS Central"
APP_ENV=production
APP_KEY=${APP_KEY}
APP_DEBUG=false
APP_URL=http://${SERVER_IP}:9000
APP_VERSION=1.0.0

FRONTEND_URL=http://${SERVER_IP}:9001
SANCTUM_STATEFUL_DOMAINS=${SERVER_IP}:9001
SANCTUM_TOKEN_EXPIRATION=480

LOG_CHANNEL=daily
LOG_LEVEL=error

DB_CONNECTION=pgsql
DB_HOST=central_db
DB_PORT=5432
DB_DATABASE=pos_central
DB_USERNAME=central
DB_PASSWORD=${DB_PASSWORD}

CACHE_STORE=redis
SESSION_DRIVER=redis
SESSION_LIFETIME=120

REDIS_HOST=central_redis
REDIS_PORT=6379
REDIS_PASSWORD=${REDIS_PASSWORD}

QUEUE_CONNECTION=database

BROADCAST_CONNECTION=reverb
REVERB_APP_ID=${REVERB_APP_ID}
REVERB_APP_KEY=${REVERB_APP_KEY}
REVERB_APP_SECRET=${REVERB_APP_SECRET}
REVERB_HOST=${SERVER_IP}
REVERB_PORT=8080
REVERB_SCHEME=ws

CENTRAL_API_KEY=${CENTRAL_API_KEY}

ADMIN_EMAIL=admin@central.local
ADMIN_PASSWORD=${ADMIN_PASSWORD}
EOF

    chmod 600 "$ENV_FILE"
    chown "$APP_USER:$APP_USER" "$ENV_FILE"
    log ".env généré (IP: $SERVER_IP)"

    # Afficher les infos critiques
    echo ""
    echo -e "${BOLD}${YELLOW}  ┌─ INFORMATIONS IMPORTANTES ─────────────────────────┐${NC}"
    echo -e "${YELLOW}  │${NC}  CENTRAL_API_KEY=${BOLD}${CENTRAL_API_KEY}${NC}"
    echo -e "${YELLOW}  │${NC}  → À configurer dans le .env de chaque caisse POS"
    echo -e "${YELLOW}  │${NC}"
    echo -e "${YELLOW}  │${NC}  ADMIN_EMAIL   = admin@central.local"
    echo -e "${YELLOW}  │${NC}  ADMIN_PASSWORD= ${BOLD}${ADMIN_PASSWORD}${NC}"
    echo -e "${BOLD}${YELLOW}  └────────────────────────────────────────────────────┘${NC}"
    echo ""
fi

# ── 6. Build et démarrage ─────────────────────────────────────
step "Build et démarrage des services"
cd "$INSTALL_DIR/central"
docker compose build --no-cache
docker compose up -d
log "Tous les services démarrés"

# ── 7. Attente de la base de données ──────────────────────────
step "Attente de la base de données"
TRIES=0
until docker compose exec -T central_db pg_isready -U central &>/dev/null \
      || [[ $TRIES -ge 30 ]]; do
    sleep 2; TRIES=$((TRIES+1))
done

if [[ $TRIES -ge 30 ]]; then
    warn "Base de données non prête — vérifiez : docker compose logs central_db"
else
    log "Base de données prête"
fi

# ── 8. Service systemd ────────────────────────────────────────
step "Création du service systemd (démarrage automatique)"
cat > /etc/systemd/system/pos-central.service <<EOF
[Unit]
Description=POS Central (Docker Compose)
Requires=docker.service
After=docker.service network-online.target
Wants=network-online.target

[Service]
Type=oneshot
RemainAfterExit=yes
WorkingDirectory=${INSTALL_DIR}/central
ExecStart=/usr/bin/docker compose up -d --remove-orphans
ExecStop=/usr/bin/docker compose down
TimeoutStartSec=300
User=root

[Install]
WantedBy=multi-user.target
EOF

systemctl daemon-reload
systemctl enable pos-central.service
log "Service pos-central.service activé"

# ── 9. Résumé ─────────────────────────────────────────────────
SERVER_IP=$(detect_server_ip)

echo ""
echo -e "${BOLD}${GREEN}╔══════════════════════════════════════════════════════╗"
echo -e "║  ✅  Installation terminée avec succès !              ║"
echo -e "╠══════════════════════════════════════════════════════╣${NC}"
echo -e "${GREEN}║${NC}  Dashboard  :  ${BOLD}http://${SERVER_IP}:9001${NC}"
echo -e "${GREEN}║${NC}  API        :  ${BOLD}http://${SERVER_IP}:9000${NC}"
echo -e "${GREEN}║${NC}  WebSocket  :  ${BOLD}ws://${SERVER_IP}:8080${NC}"
echo -e "${GREEN}╚══════════════════════════════════════════════════════╝${NC}"
echo ""
echo -e "  Commandes utiles :"
echo -e "  ${CYAN}cd ${INSTALL_DIR}/central && docker compose logs -f${NC}"
echo -e "  ${CYAN}systemctl status pos-central${NC}"
