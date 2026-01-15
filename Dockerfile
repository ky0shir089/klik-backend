# =======================
# Base runtime (PHP 8.4)
# =======================
FROM dunglas/frankenphp:1.4.4-php8.4-alpine AS base

ENV TZ=Asia/Jakarta

RUN apk add --no-cache tzdata \
    && cp /usr/share/zoneinfo/$TZ /etc/localtime \
    && echo $TZ > /etc/timezone
    
RUN install-php-extensions \
    pcntl \
    pdo_mysql \
    zip \
    gd \
    intl

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
    --no-scripts \
    --ignore-platform-req=ext-gd \
    --ignore-platform-req=ext-intl

# =======================
# Final image
# =======================
FROM base

WORKDIR /app

COPY . .
COPY --from=vendor /app/vendor ./vendor

RUN echo "* * * * * cd /app && php artisan schedule:run >> /dev/null 2>&1" >> /etc/crontabs/root

EXPOSE 8000
ENTRYPOINT ["sh", "-c", "crond && php artisan optimize && php artisan octane:frankenphp --host=0.0.0.0 --port=8000"]
