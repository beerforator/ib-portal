FROM php:8.2-apache-bookworm
RUN apt-get update && apt-get install -y --no-install-recommends libfreetype6-dev libjpeg62-turbo-dev libpng-dev libwebp-dev libzip-dev libonig-dev unzip mariadb-client git && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp && docker-php-ext-install -j2 gd mysqli pdo_mysql mbstring zip && a2enmod rewrite headers && rm -rf /var/lib/apt/lists/*
RUN printf 'upload_max_filesize=32M\npost_max_size=36M\n' > /usr/local/etc/php/conf.d/uploads.ini
