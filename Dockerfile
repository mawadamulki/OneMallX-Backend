# ---------- Base PHP ----------
FROM php:8.2-fpm-alpine AS base

# Install system dependencies + PHP extensions
RUN --mount=type=cache,target=/var/cache/apk \
    apk add --no-cache \
        icu-dev \
        oniguruma-dev \
        libzip-dev \
        freetype-dev \
        libjpeg-turbo-dev \
        libpng-dev \
        linux-headers \
        git \
        unzip \
        $PHPIZE_DEPS \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        intl \
        pdo_mysql \
        bcmath \
        opcache \
        zip \
        pcntl \
        gd \
    && apk del $PHPIZE_DEPS

# ✅ FIX: copy composer from official image (no ":2")
RUN curl -sS https://getcomposer.org/installer | php -- \
    --install-dir=/usr/local/bin \
    --filename=composer

WORKDIR /var/www/html
ENV COMPOSER_ALLOW_SUPERUSER=1

# Entrypoint
COPY entrypoint.sh /usr/local/bin/entrypoint.sh
RUN tr -d '\r' < /usr/local/bin/entrypoint.sh > /usr/local/bin/entrypoint_unix.sh \
    && mv /usr/local/bin/entrypoint_unix.sh /usr/local/bin/entrypoint.sh \
    && chmod +x /usr/local/bin/entrypoint.sh

# ---------- Vendor (Composer install) ----------
FROM base AS vendor

COPY composer.json composer.lock ./

RUN --mount=type=cache,target=/root/.composer/cache,id=composer-onemall,sharing=locked \
    composer install \
        --no-dev \
        --no-scripts \
        --prefer-dist \
        --no-interaction \
        --no-autoloader

COPY . .

RUN --mount=type=cache,target=/root/.composer/cache,id=composer-onemall,sharing=locked \
    composer dump-autoload \
        --optimize \
        --classmap-authoritative \
        --no-interaction

# ---------- Production ----------
FROM base AS production

WORKDIR /var/www/html

COPY --from=vendor /var/www/html /var/www/html

RUN chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]

EXPOSE 9000

CMD ["php-fpm"]
