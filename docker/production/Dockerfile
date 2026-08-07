# syntax=docker/dockerfile:1

# ─────────────────────────────────────────────────────────
# Stage 1 — PHP dependencies (no dev packages, optimized autoloader)
# ─────────────────────────────────────────────────────────
FROM composer:2 AS vendor

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-scripts \
    --no-interaction \
    --no-progress \
    --prefer-dist \
    --ignore-platform-reqs

COPY . .
RUN composer dump-autoload --optimize --no-dev --classmap-authoritative

# ─────────────────────────────────────────────────────────
# Stage 2 — Frontend build (Tailwind v4 + Vite)
# ─────────────────────────────────────────────────────────
FROM node:22-alpine AS frontend

WORKDIR /app

# Vite bakes import.meta.env.VITE_* values into the compiled JS at BUILD
# time — they can't come from the runtime .env bind-mount (that file
# doesn't exist yet during `docker build`). Without these, resources/js/echo.js
# instantiates Pusher/Reverb with an empty app key and throws immediately
# ("You must pass your app key"), on every page, for every visitor.
ARG VITE_APP_NAME=Sanaga24
ARG VITE_REVERB_APP_KEY
ARG VITE_REVERB_HOST
ARG VITE_REVERB_PORT
ARG VITE_REVERB_SCHEME
ENV VITE_APP_NAME=$VITE_APP_NAME \
    VITE_REVERB_APP_KEY=$VITE_REVERB_APP_KEY \
    VITE_REVERB_HOST=$VITE_REVERB_HOST \
    VITE_REVERB_PORT=$VITE_REVERB_PORT \
    VITE_REVERB_SCHEME=$VITE_REVERB_SCHEME

COPY package.json package-lock.json ./
RUN npm ci

COPY . .
# resources/css/app.css imports Flux's stylesheet straight out of
# vendor/livewire/flux/dist — Vite needs that file to resolve at build
# time, so the PHP deps from the "vendor" stage have to land here too.
COPY --from=vendor /app/vendor ./vendor
RUN npm run build

# ─────────────────────────────────────────────────────────
# Stage 3 — Runtime image: PHP-FPM + Nginx + Supervisor in one container
# ─────────────────────────────────────────────────────────
FROM php:8.4-fpm-alpine AS app

ARG APP_UID=1000
ARG APP_GID=1000

WORKDIR /var/www/html

# The base image ships this directory with the sticky bit set
# (drwxrwxrwt, meant for shared/multi-tenant scratch dirs like /tmp) —
# harmless most of the time, but it's been observed to block writes to
# files bind-mounted here (e.g. .env, for `artisan key:generate`) even as
# root, depending on the host's storage driver. Normalize it.
RUN chmod 755 /var/www/html

# System packages + build deps for PHP extensions (build deps removed after).
# libwebp-dev is what makes GD able to encode WebP — required by
# App\Services\ImageOptimizer, which re-encodes every uploaded picture to
# WebP. Without it, GD silently falls back to PNG/JPEG and the app's own
# "always .webp" assumption (asserted in tests) breaks in production.
RUN apk add --no-cache \
        nginx \
        supervisor \
        postgresql-client \
        libpng \
        libjpeg-turbo \
        libwebp \
        freetype \
        icu-libs \
        oniguruma \
        libzip \
        bash \
        curl \
    && apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        libpng-dev \
        libjpeg-turbo-dev \
        libwebp-dev \
        freetype-dev \
        icu-dev \
        oniguruma-dev \
        libzip-dev \
        postgresql-dev \
    && docker-php-ext-configure gd --with-jpeg --with-webp --with-freetype \
    && docker-php-ext-install -j$(nproc) \
        gd \
        pdo_pgsql \
        pgsql \
        zip \
        bcmath \
        intl \
        pcntl \
        opcache \
    && curl -sSLf -o /usr/local/bin/install-php-extensions \
        https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions \
    && chmod +x /usr/local/bin/install-php-extensions \
    && install-php-extensions redis \
    && apk del .build-deps \
    && rm -rf /var/cache/apk/*

# Non-root user matching the host user (avoids root-owned files on the
# storage/ bind mount) — falls back to www-data's usual 82/82 if unset.
RUN deluser www-data 2>/dev/null; \
    delgroup www-data 2>/dev/null; \
    addgroup -g ${APP_GID} www-data && \
    adduser -D -u ${APP_UID} -G www-data -s /bin/sh www-data

COPY --from=vendor --chown=www-data:www-data /app/vendor ./vendor
COPY --from=frontend --chown=www-data:www-data /app/public/build ./public/build
COPY --chown=www-data:www-data . .

COPY docker/production/php.ini /usr/local/etc/php/conf.d/99-production.ini
COPY docker/production/nginx.conf /etc/nginx/http.d/default.conf
COPY docker/production/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/production/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

RUN mkdir -p storage/framework/{sessions,views,cache} storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R ug+rwX storage bootstrap/cache

EXPOSE 80

ENTRYPOINT ["entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf", "-n"]
