FROM dunglas/frankenphp:1.4.4-php8.4-alpine AS base

RUN install-php-extensions \
    pcntl \
    pdo_mysql

# -----------------------
# Stage: Composer
# -----------------------
FROM composer:2 AS vendor

WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-progress --no-scripts

# -----------------------
# Stage: Final Image
# -----------------------
FROM base

WORKDIR /app

# Copy seluruh project
COPY . .

# Copy vendor hasil composer
COPY --from=vendor /app/vendor ./vendor

EXPOSE 8000

ENTRYPOINT ["php", "artisan", "octane:frankenphp"]
