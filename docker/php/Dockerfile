# PHP-FPM image for the POS System (app, queue worker and scheduler all use it).
FROM php:8.4-fpm-alpine

# Extensions Laravel + this app need. pdo_sqlite ships with the base image (used by tests
# and by `db:import-sqlite`). Redis is reached with predis, so no phpredis extension.
COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions pdo_mysql bcmath intl gd zip exif pcntl opcache

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-app.ini

# Run as the host user's uid so files created in the bind mount (logs, uploads) stay editable.
ARG UID=1000
ARG GID=1000
RUN addgroup -g ${GID} app 2>/dev/null || true \
 && adduser -D -u ${UID} -G $(getent group ${GID} | cut -d: -f1) app 2>/dev/null || true \
 && sed -i "s/^user = .*/user = app/; s/^group = .*/group = $(getent group ${GID} | cut -d: -f1)/" /usr/local/etc/php-fpm.d/www.conf

WORKDIR /var/www/html
