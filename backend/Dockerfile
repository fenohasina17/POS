FROM php:8.4-fpm-bookworm
WORKDIR /var/www

# Outils système pour composer + build des extensions
RUN apt-get update && apt-get install -y --no-install-recommends \
    git unzip zip curl \
    && rm -rf /var/lib/apt/lists/*

# install-php-extensions : méthode fiable (évite les problèmes PECL/pecl.php.net)
# Gère automatiquement toutes les dépendances système (libpq-dev, libzip-dev, libicu-dev…)
RUN curl -sSfL https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions \
        -o /usr/local/bin/install-php-extensions \
    && chmod +x /usr/local/bin/install-php-extensions \
    && install-php-extensions pdo_pgsql zip intl redis

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER=1

COPY composer.json composer.lock ./
RUN composer install --no-interaction --no-scripts --optimize-autoloader

COPY . /var/www

RUN composer run-script post-autoload-dump || true \
    && composer dump-autoload --optimize

COPY entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

RUN chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache || true \
    && chmod -R 775 /var/www/storage /var/www/bootstrap/cache || true

EXPOSE 9000
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php-fpm"]
