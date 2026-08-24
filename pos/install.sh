#!/usr/bin/env bash
# ============================================================
#  POS — Script d'installation automatique pour Debian 11/12
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

info "Debian détecté : $(cat /etc/debian_version)"

REPO_URL="${REPO_URL:-https://github.com/fenohasina17/POS.git}"
INSTALL_DIR="${INSTALL_DIR:-/opt/pos}"
APP_USER="${APP_USER:-pos}"

# Le dépôt est chown vers $APP_USER après le clone (plus bas), donc root
# (qui exécute tout ce script) n'en est plus "propriétaire" aux yeux de git
# dès le 2e passage — refusé par sécurité (CVE-2022-24765) sans cette
# exception explicite.
git config --global --get-all safe.directory 2>/dev/null | grep -qx "$INSTALL_DIR" \
    || git config --global --add safe.directory "$INSTALL_DIR"

# Synchronisation vers le serveur central (laisser CENTRAL_SERVER_URL vide
# pour un POS 100% autonome, sans supervision). Valeurs affichées à la fin
# de central/install.sh — à passer ici en variables d'environnement :
#   CENTRAL_SERVER_URL=https://<domaine> CENTRAL_API_KEY=<clé> \
#   RESTAURANT_ID=restaurant-1 TERMINAL_ID=pos-1 sudo -E ./install.sh
CENTRAL_SERVER_URL="${CENTRAL_SERVER_URL:-}"
CENTRAL_API_KEY="${CENTRAL_API_KEY:-}"
TERMINAL_ID="${TERMINAL_ID:-pos-$(hostname)}"
RESTAURANT_ID="${RESTAURANT_ID:-restaurant-1}"

if [[ -z "$CENTRAL_SERVER_URL" ]]; then
    warn "CENTRAL_SERVER_URL non défini — ce POS tournera en mode autonome (pas de supervision centrale)"
    warn "Pour activer la sync, relancez avec CENTRAL_SERVER_URL=https://... CENTRAL_API_KEY=... ./install.sh"
fi

detect_server_ip() {
    ip route get 1.1.1.1 2>/dev/null \
      | awk '{for(i=1;i<=NF;i++) if($i=="src") print $(i+1)}' | head -1
}

echo -e "${BOLD}"
echo "  ╔══════════════════════════════════════╗"
echo "  ║    POS — Installation Debian         ║"
echo "  ╚══════════════════════════════════════╝"
echo -e "${NC}"

# ── 1. Dépendances système ───────────────────────────────────
step "Installation des dépendances système"
apt-get update -qq
apt-get install -y -qq ca-certificates curl gnupg lsb-release git openssl make > /dev/null
log "Dépendances installées"

# ── 2. Docker ────────────────────────────────────────────────
step "Installation de Docker"
if command -v docker &>/dev/null; then
    info "Docker déjà installé : $(docker --version | awk '{print $3}' | tr -d ',')"
else
    install -m 0755 -d /etc/apt/keyrings
    curl -fsSL https://download.docker.com/linux/debian/gpg \
        | gpg --dearmor -o /etc/apt/keyrings/docker.gpg
    chmod a+r /etc/apt/keyrings/docker.gpg
    echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] \
https://download.docker.com/linux/debian \
$(. /etc/os-release && echo "$VERSION_CODENAME") stable" \
        > /etc/apt/sources.list.d/docker.list
    apt-get update -qq
    apt-get install -y -qq docker-ce docker-ce-cli containerd.io docker-compose-plugin > /dev/null
    systemctl enable --now docker
    log "Docker installé et démarré"
fi
! docker compose version &>/dev/null && apt-get install -y -qq docker-compose-plugin > /dev/null
log "docker compose : $(docker compose version --short)"

# ── 3. Utilisateur applicatif ────────────────────────────────
step "Création de l'utilisateur applicatif"
if ! id "$APP_USER" &>/dev/null; then
    useradd -m -s /bin/bash "$APP_USER"
    log "Utilisateur '$APP_USER' créé"
else
    info "Utilisateur '$APP_USER' existant — conservé"
fi
usermod -aG docker "$APP_USER"

# ── 4. Clonage du dépôt ──────────────────────────────────────
step "Clonage du dépôt dans $INSTALL_DIR"
if [[ -d "$INSTALL_DIR/.git" ]]; then
    info "Dépôt déjà présent — mise à jour"
    git -C "$INSTALL_DIR" pull --ff-only
else
    git clone "$REPO_URL" "$INSTALL_DIR"
fi
chown -R "$APP_USER:$APP_USER" "$INSTALL_DIR"
log "Dépôt prêt"

# ── 5. Configuration .env ─────────────────────────────────────
step "Configuration de l'environnement"
ENV_FILE="$INSTALL_DIR/pos/.env"

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
REDIS_PASSWORD=${REDIS_PASSWORD}

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

# Synchronisation vers le serveur central (vide = POS autonome)
CENTRAL_SERVER_URL=${CENTRAL_SERVER_URL}
CENTRAL_API_KEY=${CENTRAL_API_KEY}
TERMINAL_ID=${TERMINAL_ID}
RESTAURANT_ID=${RESTAURANT_ID}
EOF

    chmod 600 "$ENV_FILE"
    chown "$APP_USER:$APP_USER" "$ENV_FILE"
    log ".env généré (IP: $SERVER_IP)"
fi

# ── 6. Démarrage ─────────────────────────────────────────────
# cert-init génère les certs SSL automatiquement au premier démarrage
step "Build et démarrage (les certificats SSL sont générés automatiquement)"
cd "$INSTALL_DIR/pos"
docker compose build --no-cache
docker compose up -d
log "Tous les services démarrés"

# ── 7. Migrations ────────────────────────────────────────────
step "Attente de la base de données"
TRIES=0
DB_USER=$(grep '^DB_USERNAME=' "$ENV_FILE" | cut -d= -f2)
DB_NAME=$(grep '^DB_DATABASE=' "$ENV_FILE" | cut -d= -f2)
until docker compose exec -T db pg_isready -U "${DB_USER:-pos_user}" -d "${DB_NAME:-pos_system}" &>/dev/null \
      || [[ $TRIES -ge 30 ]]; do
    sleep 2; TRIES=$((TRIES+1))
done

if [[ $TRIES -ge 30 ]]; then
    warn "Base de données non prête — vérifiez: docker compose logs db"
else
    log "Base de données prête"
    docker compose exec -T backend php artisan migrate --force
    log "Migrations exécutées"
fi

# ── 8. Systemd service ───────────────────────────────────────
step "Création du service systemd (démarrage automatique au boot)"
cat > /etc/systemd/system/pos.service <<EOF
[Unit]
Description=POS Application (Docker Compose)
Requires=docker.service
After=docker.service network-online.target
Wants=network-online.target

[Service]
Type=oneshot
RemainAfterExit=yes
WorkingDirectory=${INSTALL_DIR}/pos
ExecStart=/usr/bin/docker compose up -d --remove-orphans
ExecStop=/usr/bin/docker compose down
TimeoutStartSec=300
User=root

[Install]
WantedBy=multi-user.target
EOF

systemctl daemon-reload
systemctl enable pos.service
log "Service pos.service activé"

# ── 9. Résumé ────────────────────────────────────────────────
SERVER_IP=$(detect_server_ip)
[[ -z "$SERVER_IP" ]] && SERVER_IP="127.0.0.1"

echo ""
echo -e "${BOLD}${GREEN}╔══════════════════════════════════════════════════════╗"
echo -e "║  ✅  Installation terminée avec succès !              ║"
echo -e "╠══════════════════════════════════════════════════════╣${NC}"
echo -e "${GREEN}║${NC}  Frontend  :  ${BOLD}http://${SERVER_IP}:5173${NC}"
echo -e "${GREEN}║${NC}  API       :  ${BOLD}http://${SERVER_IP}:8000${NC}"
echo -e "${GREEN}║${NC}  Jenkins   :  ${BOLD}http://${SERVER_IP}:9090${NC}"
echo -e "${GREEN}║${NC}  Uptime    :  ${BOLD}http://${SERVER_IP}:3001${NC}"
echo -e "╠══════════════════════════════════════════════════════╣${NC}"
if [[ -n "$CENTRAL_SERVER_URL" ]]; then
    echo -e "${GREEN}║${NC}  Sync Central : ${BOLD}activée${NC} → ${CENTRAL_SERVER_URL} (${TERMINAL_ID})"
else
    echo -e "${GREEN}║${NC}  Sync Central : ${YELLOW}désactivée${NC} (mode autonome)"
fi
echo -e "${GREEN}╚══════════════════════════════════════════════════════╝${NC}"
echo ""
echo -e "  Commandes utiles :"
echo -e "  ${CYAN}cd ${INSTALL_DIR}/pos && docker compose logs -f${NC}"
echo -e "  ${CYAN}systemctl status pos${NC}"
echo ""
