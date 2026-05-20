#!/usr/bin/env sh
set -e

# Créer les répertoires Laravel obligatoires
mkdir -p /var/www/storage/framework/{sessions,views,cache/data}
mkdir -p /var/www/storage/logs
mkdir -p /var/www/bootstrap/cache

# Permissions
chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache /var/www/vendor 2>/dev/null || true
chmod -R 775 /var/www/storage /var/www/bootstrap/cache /var/www/vendor 2>/dev/null || true

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

# Exécuter les migrations
echo "Exécution des migrations..."
php artisan migrate --force || true

# Seeder uniquement si la base est vide (premier déploiement)
USER_COUNT=$(php -r "
try {
    \$pdo = new PDO(
        'pgsql:host=' . getenv('DB_HOST') . ';port=' . getenv('DB_PORT') . ';dbname=' . getenv('DB_DATABASE'),
        getenv('DB_USERNAME'),
        getenv('DB_PASSWORD')
    );
    echo \$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
} catch (Exception \$e) {
    echo 0;
}
" 2>/dev/null)

if [ -z "$USER_COUNT" ] || [ "$USER_COUNT" = "0" ]; then
    echo "Base vide - exécution des seeders..."
    php artisan db:seed --force || true
    echo "Seeders terminés."
else
    echo "Base déjà initialisée ($USER_COUNT utilisateur(s)) - seeders ignorés."
fi

# Vider les caches
php artisan config:clear 2>/dev/null || true
php artisan cache:clear 2>/dev/null || true

exec "$@"
