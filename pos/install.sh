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

# Serveur central : une seule instance pour toute la chaîne, fixe — inutile
# de la retaper à chaque caisse. Surchargeable via CENTRAL_SERVER_URL si un
# jour plusieurs instances Central existent.
CENTRAL_SERVER_URL="${CENTRAL_SERVER_URL:-https://central.igp.group}"
CENTRAL_API_KEY="${CENTRAL_API_KEY:-}"
RESTAURANT_ID="${RESTAURANT_ID:-}"
TERMINAL_ID="${TERMINAL_ID:-}"
# Identifiants admin du central pour l'enrôlement (questions si interactif,
# ou variables d'environnement en déploiement scripté)
ENROLL_EMAIL="${ENROLL_EMAIL:-}"
ENROLL_PASSWORD="${ENROLL_PASSWORD:-}"

detect_server_ip() {
    ip route get 1.1.1.1 2>/dev/null \
      | awk '{for(i=1;i<=NF;i++) if($i=="src") print $(i+1)}' | head -1
}

echo -e "${BOLD}"
echo "  ╔══════════════════════════════════════╗"
echo "  ║    POS — Installation Debian         ║"
echo "  ╚══════════════════════════════════════╝"
echo -e "${NC}"

# ── Identité de ce terminal ────────────────────────────────────
# Seuls le code du point de vente et le n° de caisse changent d'une machine
# à l'autre — tout le reste (serveur central, dépendances, build) est
# automatique. Si ces valeurs sont déjà passées en variables d'environnement
# (déploiement scripté), aucune question n'est posée.
if [[ -t 0 ]]; then
    if [[ -z "$RESTAURANT_ID" ]]; then
        echo ""
        while [[ -z "$RESTAURANT_ID" ]]; do
            read -rp "Code du point de vente (ex: 101) : " RESTAURANT_ID
        done
    fi
    if [[ -z "$TERMINAL_ID" ]]; then
        read -rp "Numéro de cette caisse dans ce point de vente [1] : " TERM_NUM
        TERMINAL_ID="pos-${RESTAURANT_ID}-${TERM_NUM:-1}"
    fi
    if [[ -z "$CENTRAL_API_KEY" ]]; then
        # La clé API n'est plus collée à la main : un admin du siège
        # s'identifie et la caisse la récupère auprès du central (après
        # l'installation des dépendances, curl étant requis).
        echo ""
        info "Enrôlement auprès du central (${CENTRAL_SERVER_URL})"
        [[ -z "$ENROLL_EMAIL" ]] && read -rp "Email d'un admin du central (Entrée = mode autonome, sans supervision) : " ENROLL_EMAIL
        if [[ -n "$ENROLL_EMAIL" && -z "$ENROLL_PASSWORD" ]]; then
            read -rsp "Mot de passe : " ENROLL_PASSWORD
            echo ""
        fi
    fi
else
    # Mode non-interactif (CI, provisioning scripté) : valeurs par défaut
    # si non fournies, pas de blocage sur une question sans réponse possible.
    RESTAURANT_ID="${RESTAURANT_ID:-restaurant-1}"
    TERMINAL_ID="${TERMINAL_ID:-pos-$(hostname)}"
fi

# ── 1. Dépendances système ───────────────────────────────────
step "Installation des dépendances système"
apt-get update -qq
apt-get install -y -qq ca-certificates curl gnupg lsb-release git openssl make > /dev/null
log "Dépendances installées"

# Le dépôt est chown vers $APP_USER après le clone (plus bas), donc root
# (qui exécute tout ce script) n'en est plus "propriétaire" aux yeux de git
# dès le 2e passage — refusé par sécurité (CVE-2022-24765) sans cette
# exception explicite.
git config --global --get-all safe.directory 2>/dev/null | grep -qx "$INSTALL_DIR" \
    || git config --global --add safe.directory "$INSTALL_DIR"

# ── Enrôlement auprès du central ─────────────────────────────
json_escape() { local v=${1//\\/\\\\}; v=${v//\"/\\\"}; printf '%s' "$v"; }

if [[ -z "$CENTRAL_API_KEY" && -n "${ENROLL_EMAIL:-}" ]]; then
    step "Enrôlement auprès du central"
    for attempt in 1 2 3; do
        # Corps JSON passé sur stdin : le mot de passe n'apparaît jamais
        # dans la liste des processus (ps), contrairement à curl -d '...'.
        RESPONSE=$(printf '{"email":"%s","password":"%s","restaurant_id":"%s","terminal_id":"%s"}' \
                "$(json_escape "$ENROLL_EMAIL")" "$(json_escape "$ENROLL_PASSWORD")" \
                "$(json_escape "$RESTAURANT_ID")" "$(json_escape "$TERMINAL_ID")" \
            | curl -sS -w '\n%{http_code}' -X POST \
                -H 'Content-Type: application/json' -H 'Accept: application/json' \
                --data @- "${CENTRAL_SERVER_URL}/api/terminals/enroll" 2>&1) || true
        HTTP_CODE=${RESPONSE##*$'\n'}
        BODY=${RESPONSE%$'\n'*}

        if [[ "$HTTP_CODE" == "200" ]]; then
            CENTRAL_API_KEY=$(sed -n 's/.*"api_key":"\([^"]*\)".*/\1/p' <<<"$BODY")
            [[ -n "$CENTRAL_API_KEY" ]] && { log "Clé API récupérée auprès du central"; break; }
        fi

        warn "Enrôlement refusé (HTTP ${HTTP_CODE}) : $(sed -n 's/.*"message":"\([^"]*\)".*/\1/p' <<<"$BODY")"
        [[ "$HTTP_CODE" != "401" || $attempt -eq 3 || ! -t 0 ]] && break
        read -rsp "Mot de passe (nouvel essai ${attempt}/2) : " ENROLL_PASSWORD
        echo ""
    done
    unset ENROLL_PASSWORD
fi

if [[ -z "$CENTRAL_API_KEY" ]]; then
    warn "Aucune clé API — ce POS tournera en mode autonome (pas de supervision centrale)"
fi

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
# Connexion interne (backend → reverb) — directement au conteneur, jamais
# via nginx : nginx ne proxifie que /app/ (poignée de main WebSocket du
# navigateur), pas /apps/{id}/events (API REST Pusher utilisée par le
# backend pour déclencher un event) — cette route n'existe nulle part côté
# nginx. Même patron que central/install.sh (REVERB_HOST=central_reverb).
REVERB_HOST=reverb
REVERB_PORT=8080
REVERB_SCHEME=http

# Connexion navigateur (caisse → reverb via le proxy nginx /app/, vrai WebSocket)
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
    docker compose exec -T -u www-data backend php artisan migrate --force
    log "Migrations exécutées"

    # Rôles, point de vente local (nommé d'après RESTAURANT_ID) et compte
    # admin de démarrage — idempotent, sûr à rejouer si l'install.sh est
    # relancé. Permet une connexion immédiate sur l'interface de la caisse
    # sans étape manuelle (tinker) après l'installation.
    # Mot de passe propre à chaque caisse, généré ici et affiché une seule
    # fois dans le résumé. Ignoré par la commande si le compte existe déjà
    # (réinstallation) : le mot de passe choisi par l'admin est conservé.
    ADMIN_PASSWORD=$(openssl rand -base64 24 | tr -dc 'A-Za-z0-9' | head -c 16)
    BOOTSTRAP_OUT=$(docker compose exec -T -u www-data backend php artisan pos:bootstrap-admin \
        "$RESTAURANT_ID" --password="$ADMIN_PASSWORD")
    if grep -q "mot de passe inchangé" <<<"$BOOTSTRAP_OUT"; then
        ADMIN_STATUS="existing"
        log "Compte admin existant — mot de passe inchangé"
    else
        ADMIN_STATUS="created"
        log "Compte admin créé"
    fi
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
echo -e "${GREEN}║${NC}  Uptime    :  ${BOLD}http://${SERVER_IP}:3001${NC}"
echo -e "╠══════════════════════════════════════════════════════╣${NC}"
if [[ -n "$CENTRAL_API_KEY" ]]; then
    echo -e "${GREEN}║${NC}  Sync Central : ${BOLD}activée${NC} → ${CENTRAL_SERVER_URL}"
    echo -e "${GREEN}║${NC}  Point de vente : ${BOLD}${RESTAURANT_ID}${NC} · Terminal : ${BOLD}${TERMINAL_ID}${NC}"
else
    echo -e "${GREEN}║${NC}  Sync Central : ${YELLOW}désactivée${NC} (mode autonome)"
fi
echo -e "╠══════════════════════════════════════════════════════╣${NC}"
case "${ADMIN_STATUS:-}" in
    created)
        echo -e "${GREEN}║${NC}  Connexion   :  ${BOLD}admin@igp.group / ${ADMIN_PASSWORD}${NC}"
        echo -e "${GREEN}║${NC}                 ${YELLOW}(notez-le : il ne sera plus affiché)${NC}"
        ;;
    existing)
        echo -e "${GREEN}║${NC}  Connexion   :  ${BOLD}admin@igp.group${NC} (mot de passe inchangé)"
        ;;
    *)
        echo -e "${GREEN}║${NC}  Connexion   :  ${YELLOW}compte admin non créé (base non prête)${NC}"
        ;;
esac
echo -e "${GREEN}╚══════════════════════════════════════════════════════╝${NC}"
echo ""
echo -e "  Commandes utiles :"
echo -e "  ${CYAN}cd ${INSTALL_DIR}/pos && docker compose logs -f${NC}"
echo -e "  ${CYAN}systemctl status pos${NC}"
echo -e "  Mot de passe admin perdu :"
echo -e "  ${CYAN}cd ${INSTALL_DIR}/pos && docker compose exec -u www-data backend php artisan pos:bootstrap-admin ${RESTAURANT_ID} --reset-password${NC}"
echo ""
