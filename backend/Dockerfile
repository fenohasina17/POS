# ── Stage 1 : dépendances PHP ──────────────────────────────────────────────
FROM php:8.2-fpm AS php-base

WORKDIR /var/www

# Extensions système
RUN apt-get update && apt-get install -y --no-install-recommends \
        git curl zip unzip libpng-dev libonig-dev \
        libxml2-dev libpq-dev libzip-dev \
    && docker-php-ext-install \
        pdo pdo_pgsql pgsql \
        mbstring exif pcntl bcmath gd zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Composer
COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer

# ── Stage 2 : dépendances Composer ────────────────────────────────────────
FROM php-base AS composer-deps
COPY composer.json composer.lock ./
RUN composer install --no-scripts --no-autoloader --prefer-dist --no-dev

# ── Stage 3 : image finale ────────────────────────────────────────────────
FROM php-base AS final

# Copier les vendors
COPY --from=composer-deps /var/www/vendor ./vendor

# Copier le code source
COPY . .

RUN composer dump-autoload --optimize --no-dev \
    && php artisan storage:link --force 2>/dev/null || true \
    && chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache \
    && chmod -R 775 /var/www/storage /var/www/bootstrap/cache

EXPOSE 9000
CMD ["php-fpm"]