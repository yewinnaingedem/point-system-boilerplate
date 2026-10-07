# PHP-FPM image for the POS System (app, queue worker and scheduler all use it).
# Compose runs it as the host uid/gid (`user:`), so files in the bind mount stay editable.
FROM php:8.4-fpm-alpine

# pdo_sqlite ships with the base image; Redis goes through predis, so no phpredis.
COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions pdo_mysql bcmath intl gd zip exif pcntl opcache

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY php.ini /usr/local/etc/php/conf.d/zz-app.ini

WORKDIR /var/www/html
