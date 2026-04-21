# backend/Dockerfile (contexte = ./backend)

# Stage builder: installer dépendances composer sans exécuter les scripts
FROM php:8.4-fpm AS builder
WORKDIR /var/www

RUN apt-get update && apt-get install -y \
    git unzip libpq-dev libzip-dev libicu-dev zip \
    && docker-php-ext-configure intl \
    && docker-php-ext-install pdo_pgsql zip intl \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER=1

# Copier composer files pour tirer parti du cache
COPY composer.json composer.lock ./

# Installer dépendances sans exécuter les scripts (artisan pas encore copié)
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts

# Copier le reste de l'application (artisan, app, config, etc.)
COPY . /var/www

# Exécuter les scripts qui nécessitent artisan et générer la clé si possible
RUN composer run-script post-autoload-dump || true \
    && php artisan key:generate --ansi || true \
    && composer dump-autoload --optimize

# Fix permissions
RUN chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache || true \
    && chmod -R 775 /var/www/storage /var/www/bootstrap/cache || true

# Stage runtime
FROM php:8.4-fpm
WORKDIR /var/www

RUN apt-get update && apt-get install -y libpq-dev libicu-dev \
    && docker-php-ext-configure intl \
    && docker-php-ext-install pdo_pgsql intl \
    && rm -rf /var/lib/apt/lists/*

COPY --from=builder /var/www /var/www

RUN chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache || true

EXPOSE 9000
CMD ["php-fpm"]
