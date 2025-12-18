# =======================
# Base runtime (PHP 8.4)
# =======================
FROM dunglas/frankenphp:1.4.4-php8.4-alpine AS base

# Install PHP extensions yang dibutuhkan Laravel & Octane
RUN install-php-extensions \
    pcntl \
    pdo_mysql \
    zip \
    intl \
    opcache

# =======================
# Composer stage
# =======================
FROM php:8.4-cli-alpine AS vendor

RUN apk add --no-cache \
    git \
    unzip \
    libzip-dev

RUN docker-php-ext-install zip

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY composer.json composer.lock ./
# Install tanpa dev dependencies untuk production
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

# 1. Copy seluruh source code
COPY . .

# 2. Copy vendor dari stage sebelumnya
COPY --from=vendor /app/vendor ./vendor

# 3. PENTING: Set permissions agar worker FrankenPHP bisa menulis logs/cache
# Tanpa ini, worker akan crash dan muncul error "too many consecutive worker failures"
RUN chown -R www-data:www-data /app

USER www-data

# 4. Jalankan optimasi Laravel
RUN php artisan optimize

RUN php artisan octane:install --server=frankenphp

# 5. Set Environment Variables untuk Octane
ENV OCTANE_SERVER=frankenphp
ENV SERVER_NAME=:8000

EXPOSE 8000

# Menggunakan format yang benar agar tidak ada error "got php argument"
ENTRYPOINT ["php", "artisan", "octane:frankenphp", "--host=0.0.0.0", "--port=8000"]
