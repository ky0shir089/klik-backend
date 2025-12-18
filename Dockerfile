# =======================
# Base runtime (PHP 8.4)
# =======================
FROM dunglas/frankenphp:1.4.4-php8.4-alpine AS base

RUN install-php-extensions \
    pcntl \
    pdo_mysql \
    zip

# =======================
# Composer stage (PHP 8.4)
# =======================
FROM php:8.4-cli-alpine AS vendor

RUN apk add --no-cache \
    git \
    unzip \
    curl \
    libzip-dev

RUN docker-php-ext-install zip

RUN curl -sS https://getcomposer.org/installer | php \
    -- --install-dir=/usr/bin --filename=composer

WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --prefer-dist \
    --no-interaction \
    --no-progress \
    --no-scripts

# =======================
# Final image
# =======================
FROM base

WORKDIR /app

COPY . .
COPY --from=vendor /app/vendor ./vendor

RUN php artisan optimize

EXPOSE 8000
ENTRYPOINT ["php", "artisan", "octane:frankenphp"]