FROM php:8.3-fpm

# pdo_mysql and opcache are not in the base image. The production container
# had them from manual installs inside a long-lived container, which meant any
# recreate would silently lose them. Baking them in makes the runtime
# reproducible.
RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends git unzip; \
    docker-php-ext-install -j"$(nproc)" pdo_mysql opcache; \
    rm -rf /var/lib/apt/lists/*

# Application code is bind-mounted and updated by git pull rather than baked
# into the image, so opcache must re-stat files instead of trusting its cache
# for the lifetime of the process.
RUN { \
        echo 'opcache.enable=1'; \
        echo 'opcache.validate_timestamps=1'; \
        echo 'opcache.revalidate_freq=0'; \
    } > /usr/local/etc/php/conf.d/opcache-deploy.ini

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
