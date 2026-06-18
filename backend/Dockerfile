# Stage builder : dépendances + compilation
FROM php:8.4-fpm-bookworm AS builder
WORKDIR /var/www

RUN apt-get update && apt-get install -y --no-install-recommends \
    git unzip zip curl \
    && rm -rf /var/lib/apt/lists/*

# install-php-extensions : méthode fiable (gère toutes les dépendances système)
RUN curl -sSfL https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions \
        -o /usr/local/bin/install-php-extensions \
    && chmod +x /usr/local/bin/install-php-extensions \
    && install-php-extensions pdo_pgsql zip intl redis apcu

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER=1

COPY composer.json composer.lock ./
RUN composer install --no-interaction --no-scripts --optimize-autoloader

COPY . /var/www

# Nécessaire pour que artisan fonctionne pendant le build
RUN cp .env.example .env

# Créer les dossiers Laravel (gitignorés, absents du repo)
RUN mkdir -p storage/framework/views \
             storage/framework/cache \
             storage/framework/sessions \
             storage/logs \
             bootstrap/cache

RUN composer run-script post-autoload-dump || true \
    && php artisan key:generate --ansi || true \
    && composer dump-autoload --optimize

RUN chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache || true \
    && chmod -R 775 /var/www/storage /var/www/bootstrap/cache || true

# Stage runtime : image finale allégée (sans composer, sans outils de build)
FROM php:8.4-fpm-bookworm
WORKDIR /var/www

RUN apt-get update && apt-get install -y --no-install-recommends \
    curl \
    && rm -rf /var/lib/apt/lists/*

RUN curl -sSfL https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions \
        -o /usr/local/bin/install-php-extensions \
    && chmod +x /usr/local/bin/install-php-extensions \
    && install-php-extensions pdo_pgsql zip intl redis apcu

COPY --from=builder /var/www /var/www
COPY --from=builder /usr/bin/composer /usr/bin/composer

RUN chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache || true

COPY entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 9000
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php-fpm"]
