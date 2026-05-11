FROM php:8.0-fpm

RUN apt-get update && apt-get install -y \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libpng-dev \
    libwebp-dev \
    libmagickwand-dev \
    libicu-dev \
    libzip-dev \
    libpq-dev \
    libxml2-dev \
    libonig-dev \
    unzip \
    git \
    && rm -rf /var/lib/apt/lists/*

# GD
RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install gd

# Core extensions
RUN docker-php-ext-install \
    bcmath \
    intl \
    mbstring \
    pdo_mysql \
    mysqli \
    pdo_pgsql \
    pdo_sqlite \
    zip \
    opcache \
    xml \
    soap

# PECL: imagick + redis
RUN pecl install imagick redis \
    && docker-php-ext-enable imagick redis

RUN usermod -u 1000 www-data

WORKDIR /var/www
