# Stage builder: installer dépendances composer
FROM php:8.4-fpm AS builder
WORKDIR /var/www

# Installation des dépendances système nécessaires pour le build
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libpq-dev \
    libzip-dev \
    libicu-dev \
    zip \
    && docker-php-ext-configure intl \
    && docker-php-ext-install pdo_pgsql zip intl \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER=1

# Copier composer files pour tirer parti du cache Docker
COPY composer.json composer.lock ./

# Installer les dépendances (on installe tout pour éviter les erreurs de classes manquantes comme Pail)
RUN composer install --no-interaction --no-scripts --optimize-autoloader

# Copier le reste de l'application
COPY . /var/www

# Exécuter les scripts post-installation et générer la clé
RUN composer run-script post-autoload-dump || true \
    && php artisan key:generate --ansi || true \
    && composer dump-autoload --optimize

# Fix permissions pour le stockage
RUN chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache || true \
    && chmod -R 775 /var/www/storage /var/www/bootstrap/cache || true

# --- Stage runtime ---
FROM php:8.4-fpm
WORKDIR /var/www

# On installe unzip et les extensions PHP ici aussi pour que les commandes artisan fonctionnent plus tard
RUN apt-get update && apt-get install -y \
    libpq-dev \
    libicu-dev \
    unzip \
    zip \
    git \
    && docker-php-ext-configure intl \
    && docker-php-ext-install pdo_pgsql intl \
    && rm -rf /var/lib/apt/lists/*

# On récupère tout ce qui a été préparé dans le builder
COPY --from=builder /var/www /var/www

# On s'assure que les permissions appartiennent bien à l'utilisateur web
RUN chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache || true

EXPOSE 9000
CMD ["php-fpm"]