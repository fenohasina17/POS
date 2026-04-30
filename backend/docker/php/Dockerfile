FROM php:8.4-fpm-alpine

# Installer les dépendances système
RUN apk add --no-cache \
    nginx \
    supervisor \
    curl \
    libzip-dev \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    oniguruma-dev \
    postgresql-dev \
    git \
    unzip \
    nodejs \
    npm \
    linux-headers \
    icu-dev \
    # AJOUT INDISPENSABLE POUR GD :
    zlib-dev \
    # Dépendances pour la compilation
    autoconf \
    g++ \
    make \
    openssl-dev \
    pcre-dev

# Installer les extensions PHP
# La configuration va maintenant trouver zlib
RUN docker-php-ext-configure gd --with-freetype --with-jpeg
RUN docker-php-ext-configure intl 

RUN docker-php-ext-install -j$(nproc) \
    pdo \
    pdo_pgsql \
    pgsql \
    zip \
    gd \
    mbstring \
    exif \
    pcntl \
    bcmath \
    opcache \
    intl