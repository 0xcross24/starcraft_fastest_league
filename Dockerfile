
# ======================================
# Node build stage (assets only)
# ======================================
FROM node:22-alpine AS node-builder

WORKDIR /app

COPY package*.json ./
RUN npm install

COPY resources ./resources
COPY public ./public
COPY vite.config.js ./
COPY tailwind.config.js ./
COPY postcss.config.js ./

RUN npm run build


# ======================================
# PHP runtime stage (migration-safe)
# ======================================
FROM php:8.2-fpm-alpine

# Install runtime dependencies only
RUN apk add --no-cache \
    curl \
    zip \
    unzip \
    icu-dev \
    oniguruma-dev \
    libzip-dev \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev

# PHP extensions (no Swoole, no Redis server)
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
        bcmath \
        gd \
        intl \
        mbstring \
        pdo_mysql \
        zip \
        pcntl

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Create non-root user
RUN addgroup -g 1000 -S www \
    && adduser -u 1000 -S www -G www

WORKDIR /var/www/html

# Copy application source
COPY --chown=www:www . .

# Install PHP dependencies
RUN composer install \
    --no-dev \
    --no-interaction \
    --optimize-autoloader

# Copy built frontend assets
COPY --from=node-builder --chown=www:www /app/public/build ./public/build

# Laravel permissions (no 777)
RUN chmod -R 755 storage bootstrap/cache

USER www

# Azure Container Apps expects HTTP on a port
EXPOSE 8080

# Run PHP built-in server (migration-friendly)
CMD ["php", "-S", "0.0.0.0:8080", "-t", "public"]
