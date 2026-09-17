# syntax=docker/dockerfile:1
#
# Capstone CRM — Laravel backend (production).
# Multi-stage: frontend assets (Vite) + PHP deps, then a slim Apache runtime.
# Expects all config via environment (see .env.example); never bake a .env in.

# ---------- Stage 1: backend Vite assets (welcome view, etc.) ----------
FROM node:22-slim AS assets
WORKDIR /app
COPY package.json package-lock.json* ./
RUN npm ci --no-audit --no-fund
COPY vite.config.js* vite.config.ts* tailwind.config.js* postcss.config.js* ./
COPY resources ./resources
COPY public ./public
RUN npm run build

# ---------- Stage 2: PHP dependencies ----------
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --no-scripts \
    --prefer-dist \
    --optimize-autoloader

# ---------- Stage 3: production runtime ----------
FROM php:8.5-apache
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public \
    COMPOSER_ALLOW_SUPERUSER=1

# System deps + PHP extensions: pgsql (Neon), zip, intl, gd (dompdf), pcntl.
RUN apt-get update && apt-get install -y --no-install-recommends \
        libpq-dev libzip-dev libicu-dev libpng-dev libjpeg-dev libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_pgsql pgsql zip intl gd bcmath pcntl opcache \
    && a2enmod rewrite headers \
    && sed -ri "s!/var/www/html!${APACHE_DOCUMENT_ROOT}!g" /etc/apache2/sites-available/000-default.conf \
    && rm -rf /var/lib/apt/lists/*

# Opcache tuned for Laravel prod.
RUN { \
      echo 'opcache.enable=1'; \
      echo 'opcache.memory_consumption=256'; \
      echo 'opcache.max_accelerated_files=20000'; \
      echo 'opcache.validate_timestamps=0'; \
    } > /usr/local/etc/php/conf.d/opcache-prod.ini

WORKDIR /var/www/html

# Application code (excludes dev files via .dockerignore).
# Single-artifact note: backend + frontend are separate repos, so this image
# cannot build ../front. Instead, sync the SPA into backend/public/ BEFORE
# `docker build` (from repo root):
#   cd front && npm ci && VITE_API_URL= npm run build
#   ./backend/scripts/sync-frontend.sh
# That places public/index.html + public/assets/ here, and the COPY below
# picks them up. Apache serves them directly; Laravel's fallback route covers
# SPA deep-links (/login, /survey/{token}).
COPY --chown=www-data:www-data . .
COPY --from=vendor --chown=www-data:www-data /app/vendor ./vendor
COPY --from=assets --chown=www-data:www-data /app/public/build ./public/build

# Writable dirs for the web user.
RUN mkdir -p storage/framework/{sessions,views,cache} bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 80

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["apache2-foreground"]
