#!/usr/bin/env sh
set -e

# Créer les répertoires Laravel obligatoires
mkdir -p /var/www/storage/framework/{sessions,views,cache/data}
mkdir -p /var/www/storage/logs
mkdir -p /var/www/bootstrap/cache

# Permissions
chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache /var/www/vendor 2>/dev/null || true
chmod -R 775 /var/www/storage /var/www/bootstrap/cache /var/www/vendor 2>/dev/null || true

# Tout le reste tourne en www-data, l'utilisateur de PHP-FPM : storage est un
# volume partagé entre backend, worker, scheduler et reverb — un fichier créé
# par root (cache, log du jour) bloquerait ensuite PHP-FPM en écriture
# (Permission denied → 500 sur /api/users, /api/sales...).
as_app() {
    if [ "$(id -u)" = "0" ]; then runuser -u www-data -- "$@"; else "$@"; fi
}

# Vider le cache config AVANT tout (évite de lire des valeurs figées du build)
as_app php artisan config:clear 2>/dev/null || true
as_app php artisan cache:clear 2>/dev/null || true

# Attendre que la base de données soit prête (via PDO)
echo "En attente de la base de données..."
until php -r "
try {
    \$pdo = new PDO(
        'pgsql:host=' . getenv('DB_HOST') . ';port=' . getenv('DB_PORT') . ';dbname=' . getenv('DB_DATABASE'),
        getenv('DB_USERNAME'),
        getenv('DB_PASSWORD')
    );
    echo 'connected';
} catch (Exception \$e) {
    exit(1);
}
" 2>/dev/null | grep -q connected; do
  echo "  Base de données non disponible, nouvel essai dans 2s..."
  sleep 2
done
echo "Base de données prête."

# Exécuter les migrations — erreur fatale si ça échoue (schéma incohérent = arrêt propre)
echo "Exécution des migrations..."
as_app php artisan migrate --force

# Reconstruire le cache config avec les vraies valeurs runtime
as_app php artisan config:cache 2>/dev/null || true

# ── Sync de démarrage ────────────────────────────────────────────────────────
# Si le central est configuré, essayer d'envoyer les données en attente au démarrage
# (couvre le cas : machine éteinte sans avoir fini de syncer)
if [ -n "$CENTRAL_SERVER_URL" ]; then
    echo "Sync de démarrage (données en attente depuis dernier arrêt)..."
    as_app php artisan pos:sync 2>/dev/null || echo "Sync de démarrage ignorée (central pas encore disponible)"
fi

# ── Handler de shutdown propre ───────────────────────────────────────────────
# Intercepte SIGTERM (docker stop) et SIGINT pour syncer avant de quitter
_shutdown() {
    echo ""
    echo "Signal d'arrêt reçu — sync d'urgence avant extinction..."
    as_app php artisan pos:sync-shutdown 2>/dev/null || true
    echo "Arrêt propre terminé."
    kill "$MAIN_PID" 2>/dev/null || true
}
trap '_shutdown' TERM INT

# php-fpm : le maître doit rester root (il bascule lui-même ses workers en
# www-data) ; scheduler, worker, reverb : directement en www-data.
if [ "$1" = "php-fpm" ]; then
    "$@" &
else
    as_app "$@" &
fi
MAIN_PID=$!
wait $MAIN_PID
