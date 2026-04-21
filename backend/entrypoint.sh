#!/usr/bin/env bash
set -e

# Si vendor absent, installer les dépendances dans le volume nommé
if [ ! -d "/var/www/vendor" ] || [ -z "$(ls -A /var/www/vendor 2>/dev/null)" ]; then
  echo "vendor absent — installation des dépendances composer..."
  if command -v composer >/dev/null 2>&1; then
    composer install --no-dev --optimize-autoloader --no-interaction || true
  elif [ -f /usr/local/bin/composer ]; then
    /usr/local/bin/composer install --no-dev --optimize-autoloader --no-interaction || true
  else
    echo "Composer non disponible dans le conteneur. Rebuild de l'image nécessaire."
  fi
fi

# Permissions
chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache /var/www/vendor || true
chmod -R 775 /var/www/storage /var/www/bootstrap/cache /var/www/vendor || true

# Exécuter la commande par défaut
exec "$@"
