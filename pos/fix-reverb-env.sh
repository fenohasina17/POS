#!/usr/bin/env bash
# ============================================================
#  POS — Correctif Reverb pour les caisses déjà installées (PR #81 / #84)
# ============================================================
# Les caisses installées avant ces deux correctifs ont dans leur .env :
#   REVERB_SCHEME=ws            → cURL refuse "ws://", tout broadcast échoue
#                                 (et une vente peut partir en 500, #81)
#   REVERB_HOST=<IP du serveur> → l'appel passe par le nginx public, qui
#   REVERB_PORT=8000              ne proxifie pas /apps/{id}/events (404, #84)
#
# install.sh conserve un .env existant : relancer l'installation ne corrige
# donc rien. Ce script réécrit uniquement les trois valeurs internes
# (backend → reverb) et laisse les VITE_REVERB_* (navigateur) intactes.
#
# Usage (sur la caisse, en root) :
#   sudo ./fix-reverb-env.sh              # applique + recrée les conteneurs
#   sudo ./fix-reverb-env.sh --dry-run    # affiche ce qui changerait
#   sudo ./fix-reverb-env.sh --no-restart # modifie le .env seulement
#
# Idempotent : sur une caisse déjà corrigée, ne touche à rien et ne
# redémarre rien.
set -euo pipefail

RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'
BLUE='\033[0;34m'; NC='\033[0m'

log()  { echo -e "${GREEN}[✔]${NC} $*"; }
info() { echo -e "${BLUE}[i]${NC} $*"; }
warn() { echo -e "${YELLOW}[!]${NC} $*"; }
err()  { echo -e "${RED}[✘]${NC} $*" >&2; exit 1; }

DRY_RUN=false
RESTART=true
for arg in "$@"; do
    case "$arg" in
        --dry-run)    DRY_RUN=true ;;
        --no-restart) RESTART=false ;;
        -h|--help)    sed -n '2,21p' "$0" | sed 's/^# \{0,1\}//'; exit 0 ;;
        *)            err "Option inconnue : $arg (voir --help)" ;;
    esac
done

POS_DIR="${POS_DIR:-$(cd "$(dirname "$0")" && pwd)}"
ENV_FILE="$POS_DIR/.env"
COMPOSE_FILE="$POS_DIR/docker-compose.yml"

[[ -f "$ENV_FILE" ]]     || err ".env introuvable : $ENV_FILE (définir POS_DIR si besoin)"
[[ -f "$COMPOSE_FILE" ]] || err "docker-compose.yml introuvable : $COMPOSE_FILE"

# Avant #84, le frontend recevait REVERB_HOST/PORT/SCHEME (valeurs backend).
# Passer REVERB_HOST=reverb avec cet ancien docker-compose.yml enverrait les
# navigateurs vers "ws://reverb:8080" — injoignable hors du réseau Docker.
grep -q 'REVERB_HOST=${VITE_REVERB_HOST' "$COMPOSE_FILE" \
    || err "docker-compose.yml antérieur à #84 — mettre à jour le dépôt d'abord (git pull --ff-only)"

env_get() { grep -E "^$1=" "$ENV_FILE" | tail -1 | cut -d= -f2- || true; }

# Calcule le nouveau contenu dans un fichier temporaire, puis compare.
TMP=$(mktemp)
trap 'rm -f "$TMP"' EXIT
cp "$ENV_FILE" "$TMP"

CHANGES=()

env_set() {
    local key="$1" value="$2" current
    current=$(env_get "$key")
    [[ "$current" == "$value" ]] && grep -qE "^$key=" "$TMP" && return 0
    if grep -qE "^$key=" "$TMP"; then
        sed -i.bak "s|^$key=.*|$key=$value|" "$TMP" && rm -f "$TMP.bak"
        CHANGES+=("$key : ${current:-<vide>} → $value")
    else
        printf '%s=%s\n' "$key" "$value" >> "$TMP"
        CHANGES+=("$key : <absent> → $value")
    fi
}

# Les VITE_REVERB_* sont lues par docker-compose.yml depuis #84. Une caisse
# dont le .env ne les aurait pas retomberait sur "localhost" côté navigateur :
# on les reconstitue depuis les anciennes valeurs REVERB_*, qui étaient
# justement les valeurs navigateur, AVANT de réécrire celles-ci.
OLD_HOST=$(env_get REVERB_HOST)
OLD_PORT=$(env_get REVERB_PORT)
if [[ -z "$(env_get VITE_REVERB_HOST)" ]]; then
    [[ -n "$OLD_HOST" && "$OLD_HOST" != "reverb" ]] \
        || err "VITE_REVERB_HOST absent et impossible à déduire — à renseigner à la main (IP de la caisse)"
    env_set VITE_REVERB_HOST "$OLD_HOST"
fi
if [[ -z "$(env_get VITE_REVERB_PORT)" ]]; then
    [[ -n "$OLD_PORT" && "$OLD_PORT" != "8080" ]] && env_set VITE_REVERB_PORT "$OLD_PORT" \
        || env_set VITE_REVERB_PORT 8000
fi
[[ -n "$(env_get VITE_REVERB_SCHEME)" ]]  || env_set VITE_REVERB_SCHEME ws
[[ -n "$(env_get VITE_REVERB_APP_KEY)" ]] || env_set VITE_REVERB_APP_KEY "$(env_get REVERB_APP_KEY)"

# Connexion interne backend → reverb : directement au conteneur, en HTTP.
env_set REVERB_HOST reverb
env_set REVERB_PORT 8080
env_set REVERB_SCHEME http

if [[ ${#CHANGES[@]} -eq 0 ]]; then
    log ".env déjà corrigé — rien à faire"
    exit 0
fi

info "Modifications sur $ENV_FILE :"
for c in "${CHANGES[@]}"; do echo "    $c"; done

if $DRY_RUN; then
    warn "--dry-run : aucun fichier modifié"
    exit 0
fi

BACKUP="$ENV_FILE.bak-$(date +%Y%m%d-%H%M%S)"
cp -p "$ENV_FILE" "$BACKUP"
# cat > conserve propriétaire et droits (600, utilisateur pos) du .env.
cat "$TMP" > "$ENV_FILE"
log ".env mis à jour (sauvegarde : $BACKUP)"

if ! $RESTART; then
    warn "--no-restart : recréer les conteneurs pour appliquer :"
    echo "    cd $POS_DIR && docker compose up -d --force-recreate --no-deps backend reverb worker scheduler frontend && docker compose restart nginx"
    exit 0
fi

# env_file n'est lu qu'à la création du conteneur : "docker compose restart"
# garderait les anciennes valeurs. Il faut recréer.
cd "$POS_DIR"
info "Recréation des conteneurs backend, reverb, worker, scheduler, frontend…"
# --no-deps : ne pas toucher db/redis. Les migrations sont déjà appliquées
# sur une caisse en service, donc pas de course au démarrage.
docker compose up -d --force-recreate --no-deps --wait backend reverb worker scheduler frontend
# Le backend recréé change d'IP interne : nginx doit la re-résoudre
# (caisses antérieures à #87 sinon en 502).
docker compose restart nginx

# Vérification : un vrai broadcast backend → reverb, comme le fait une vente.
# Le conteneur reverb n'a pas de healthcheck et passe par entrypoint.sh
# (migrate, config:cache) avant d'écouter sur 8080 : on réessaie ~60 s.
info "Test d'un broadcast backend → reverb…"
OUTPUT=""
for _ in $(seq 1 12); do
    OUTPUT=$(docker compose exec -T -u www-data -e HOME=/tmp backend php artisan tinker --execute="Broadcast::connection('reverb')->broadcast(['fix-reverb-env'], 'check', ['ok' => true]); echo 'BROADCAST_OK';" 2>&1 || true)
    if grep -q BROADCAST_OK <<<"$OUTPUT"; then
        log "Broadcast OK — correctif appliqué"
        exit 0
    fi
    sleep 5
done
echo "$OUTPUT" >&2
err "Le broadcast échoue encore — voir la sortie ci-dessus (restaurer avec : cp $BACKUP $ENV_FILE)"
