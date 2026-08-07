#!/usr/bin/env sh
set -e

mkdir -p /var/www/storage/framework/{sessions,views,cache/data}
mkdir -p /var/www/storage/logs
mkdir -p /var/www/bootstrap/cache

chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache 2>/dev/null || true
chmod -R 775 /var/www/storage /var/www/bootstrap/cache 2>/dev/null || true

php artisan config:clear 2>/dev/null || true
php artisan cache:clear 2>/dev/null || true

echo "En attente de la base de données centrale..."
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
  echo "  Base non disponible, nouvel essai dans 2s..."
  sleep 2
done
echo "Base de données prête."

echo "Exécution des migrations..."
php artisan migrate --force

php artisan config:cache 2>/dev/null || true

exec "$@"
