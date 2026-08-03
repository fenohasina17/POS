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

DEBIAN_VERSION=$(cat /etc/debian_version | cut -d. -f1)
info "Debian détecté : version $DEBIAN_VERSION"

REPO_URL="${REPO_URL:-https://github.com/fenohasina17/POS.git}"
INSTALL_DIR="${INSTALL_DIR:-/opt/pos}"
APP_USER="${APP_USER:-pos}"

detect_server_ip() {
    ip route get 1.1.1.1 2>/dev/null | awk '{for(i=1;i<=NF;i++) if($i=="src") print $(i+1)}' | head -1
}

echo -e "${BOLD}"
echo "  ╔══════════════════════════════════════╗"
echo "  ║    POS — Installation Debian         ║"
echo "  ╚══════════════════════════════════════╝"
echo -e "${NC}"

# ── 1. Dépendances système ───────────────────────────────────
step "Installation des dépendances système"
apt-get update -qq
apt-get install -y -qq \
    ca-certificates curl gnupg lsb-release \
    git openssl apt-transport-https libnss3-tools \
    > /dev/null
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

# ── 3. mkcert ────────────────────────────────────────────────
step "Installation de mkcert (CA locale standard)"
if ! command -v mkcert &>/dev/null; then
    MKCERT_VERSION="v1.4.4"
    ARCH=$(dpkg --print-architecture)
    case "$ARCH" in
        amd64) MKCERT_ARCH="linux-amd64" ;;
        arm64) MKCERT_ARCH="linux-arm64" ;;
        arm)   MKCERT_ARCH="linux-arm" ;;
        *)     err "Architecture non supportée: $ARCH" ;;
    esac
    curl -fsSL "https://github.com/FiloSottile/mkcert/releases/download/${MKCERT_VERSION}/mkcert-${MKCERT_VERSION}-${MKCERT_ARCH}" \
        -o /usr/local/bin/mkcert
    chmod +x /usr/local/bin/mkcert
    log "mkcert ${MKCERT_VERSION} installé"
else
    info "mkcert déjà installé : $(mkcert --version 2>/dev/null || echo 'version inconnue')"
fi

# ── 4. Utilisateur applicatif ────────────────────────────────
step "Création de l'utilisateur applicatif"
if ! id "$APP_USER" &>/dev/null; then
    useradd -m -s /bin/bash "$APP_USER"
    log "Utilisateur '$APP_USER' créé"
else
    info "Utilisateur '$APP_USER' existant — conservé"
fi
usermod -aG docker "$APP_USER"

# ── 5. Clonage du dépôt ──────────────────────────────────────
step "Clonage du dépôt dans $INSTALL_DIR"
if [[ -d "$INSTALL_DIR/.git" ]]; then
    info "Dépôt déjà présent — mise à jour"
    git -C "$INSTALL_DIR" pull --ff-only
else
    git clone "$REPO_URL" "$INSTALL_DIR"
fi
chown -R "$APP_USER:$APP_USER" "$INSTALL_DIR"
log "Dépôt prêt"

# ── 6. Génération des certificats SSL avec mkcert ────────────
step "Génération des certificats SSL (mkcert)"
CERTS_DIR="$INSTALL_DIR/docker/certs"
mkdir -p "$CERTS_DIR"

SERVER_IP=$(detect_server_ip)
[[ -z "$SERVER_IP" ]] && SERVER_IP="127.0.0.1"

# Installer la CA mkcert dans le système (Debian + Firefox/Chrome NSS)
CAROOT_DIR="$CERTS_DIR/ca"
mkdir -p "$CAROOT_DIR"
CAROOT="$CAROOT_DIR" mkcert -install

# Générer le certificat pour l'IP du serveur + localhost
CAROOT="$CAROOT_DIR" mkcert \
    -cert-file "$CERTS_DIR/cert.pem" \
    -key-file  "$CERTS_DIR/key.pem" \
    "$SERVER_IP" localhost 127.0.0.1

# Exporter la CA pour les clients
cp "$CAROOT_DIR/rootCA.pem" "$INSTALL_DIR/pos-ca.crt"
# Importer dans le trust store Debian (curl, wget, etc.)
cp "$CAROOT_DIR/rootCA.pem" /usr/local/share/ca-certificates/pos-mkcert-ca.crt
update-ca-certificates > /dev/null 2>&1

chmod 644 "$CERTS_DIR/cert.pem" "$CERTS_DIR/key.pem"
log "Certificats SSL générés pour IP: $SERVER_IP"

# ── 7. Configuration .env ─────────────────────────────────────
step "Configuration de l'environnement"
ENV_FILE="$INSTALL_DIR/.env"

if [[ -f "$ENV_FILE" ]]; then
    warn ".env existant détecté — conservé (pas d'écrasement)"
else
    APP_KEY="base64:$(openssl rand -base64 32)"
    DB_PASSWORD=$(openssl rand -hex 16)
    REVERB_APP_ID=$(shuf -i 100000-999999 -n 1)
    REVERB_APP_KEY=$(openssl rand -hex 16)
    REVERB_APP_SECRET=$(openssl rand -hex 16)

    cat > "$ENV_FILE" <<EOF
# Application
APP_NAME=POS
APP_ENV=production
APP_KEY=${APP_KEY}
APP_DEBUG=false
APP_URL=https://${SERVER_IP}:8443
SERVER_IP=${SERVER_IP}

# Frontend
FRONTEND_URL=https://${SERVER_IP}:5443
SANCTUM_STATEFUL_DOMAINS=${SERVER_IP}:5443

# Frontend build
VITE_API_URL=https://${SERVER_IP}:8443

# Database
DB_CONNECTION=pgsql
DB_HOST=db
DB_PORT=5432
DB_DATABASE=pos_system
DB_USERNAME=pos_user
DB_PASSWORD=${DB_PASSWORD}

# Cache
CACHE_STORE=redis
REDIS_CLIENT=phpredis
REDIS_HOST=redis
REDIS_PORT=6379
REDIS_PASSWORD=

# Session
SESSION_DRIVER=file
SESSION_LIFETIME=120

# Queue
QUEUE_CONNECTION=sync

# Logging
LOG_CHANNEL=daily
LOG_LEVEL=error
LOG_DEPRECATIONS_CHANNEL=null

# Broadcasting (Reverb WebSocket)
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=${REVERB_APP_ID}
REVERB_APP_KEY=${REVERB_APP_KEY}
REVERB_APP_SECRET=${REVERB_APP_SECRET}
REVERB_HOST=${SERVER_IP}
REVERB_PORT=8443
REVERB_SCHEME=https

# Frontend build — valeurs Reverb exposées à Vite
VITE_REVERB_APP_KEY=${REVERB_APP_KEY}
VITE_REVERB_HOST=${SERVER_IP}
VITE_REVERB_PORT=8443
VITE_REVERB_SCHEME=https
EOF

    chmod 600 "$ENV_FILE"
    chown "$APP_USER:$APP_USER" "$ENV_FILE"
    log ".env généré (IP: $SERVER_IP)"
fi

# ── 8. Build et démarrage ─────────────────────────────────────
step "Build des images Docker (peut prendre 5-10 min)"
cd "$INSTALL_DIR"
docker compose build --no-cache
log "Build terminé"

step "Démarrage des services"
docker compose up -d
log "Tous les services démarrés"

# ── 9. Migrations ────────────────────────────────────────────
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
    step "Exécution des migrations Laravel"
    docker compose exec -T backend php artisan migrate --force
    log "Migrations exécutées"
fi

# ── 10. Systemd service ───────────────────────────────────────
step "Création du service systemd (démarrage automatique)"
cat > /etc/systemd/system/pos.service <<EOF
[Unit]
Description=POS Application (Docker Compose)
Requires=docker.service
After=docker.service network-online.target
Wants=network-online.target

[Service]
Type=oneshot
RemainAfterExit=yes
WorkingDirectory=${INSTALL_DIR}
ExecStart=/usr/bin/docker compose up -d --remove-orphans
ExecStop=/usr/bin/docker compose down
TimeoutStartSec=300
User=root

[Install]
WantedBy=multi-user.target
EOF

systemctl daemon-reload
systemctl enable pos.service
log "Service pos.service activé (démarrage au boot)"

# ── 11. Résumé ────────────────────────────────────────────────
SERVER_IP=$(grep '^SERVER_IP=' "$ENV_FILE" | cut -d= -f2)

echo ""
echo -e "${BOLD}${GREEN}╔══════════════════════════════════════════════════════╗"
echo -e "║  ✅  Installation terminée avec succès !              ║"
echo -e "╠══════════════════════════════════════════════════════╣${NC}"
echo -e "${GREEN}║${NC}  Frontend  :  ${BOLD}https://${SERVER_IP}:5443${NC}"
echo -e "${GREEN}║${NC}  API       :  ${BOLD}https://${SERVER_IP}:8443${NC}"
echo -e "${GREEN}║${NC}  Jenkins   :  ${BOLD}http://${SERVER_IP}:9090${NC}"
echo -e "${GREEN}║${NC}  Uptime    :  ${BOLD}http://${SERVER_IP}:3001${NC}"
echo -e "${GREEN}╠══════════════════════════════════════════════════════╣${NC}"
echo -e "${GREEN}║${NC}  Répertoire  :  ${INSTALL_DIR}"
echo -e "${GREEN}║${NC}  CA mkcert   :  ${INSTALL_DIR}/pos-ca.crt"
echo -e "${GREEN}╠══════════════════════════════════════════════════════╣${NC}"
echo -e "${CYAN}║${NC}  ${BOLD}Importer pos-ca.crt sur chaque poste client :${NC}"
echo -e "${CYAN}║${NC}"
echo -e "${CYAN}║${NC}  Windows  : double-clic → Installer → Autorités"
echo -e "${CYAN}║${NC}             de certification racines de confiance"
echo -e "${CYAN}║${NC}  macOS    : double-clic → Trousseaux → Toujours"
echo -e "${CYAN}║${NC}             approuver"
echo -e "${CYAN}║${NC}  Linux    : sudo cp pos-ca.crt /usr/local/share/"
echo -e "${CYAN}║${NC}             ca-certificates/ && update-ca-certificates"
echo -e "${GREEN}╚══════════════════════════════════════════════════════╝${NC}"
echo ""
echo -e "  Commandes utiles :"
echo -e "  ${CYAN}cd ${INSTALL_DIR} && docker compose logs -f${NC}"
echo -e "  ${CYAN}systemctl status pos${NC}"
echo -e "  ${CYAN}systemctl restart pos${NC}"
echo ""
