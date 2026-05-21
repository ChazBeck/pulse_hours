FROM php:8.3-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git unzip libzip-dev libpng-dev libjpeg-dev libfreetype6-dev libxml2-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_mysql zip gd bcmath \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html
