FROM php:8.4-apache
RUN apt-get update && apt-get install -y --no-install-recommends libcurl4-openssl-dev libpng-dev libjpeg62-turbo-dev libwebp-dev libfreetype6-dev libonig-dev libzip-dev libsodium-dev unzip && docker-php-ext-configure gd --with-jpeg --with-webp --with-freetype && docker-php-ext-install -j$(nproc) pdo_mysql mbstring gd zip sodium opcache && a2enmod rewrite headers && rm -rf /var/lib/apt/lists/*
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/*.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/manor.ini
COPY . /var/www/html
RUN mkdir -p storage/private/manor storage/keys storage/logs storage/sessions public/uploads/manor && chown -R www-data:www-data storage public/uploads
USER www-data
USER root
EXPOSE 80
