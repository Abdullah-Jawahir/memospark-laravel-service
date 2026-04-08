# Build: 2026-04-08
# ============================================================
# Stage 1 – Composer dependencies
# ============================================================
FROM composer:2.7 AS vendor

WORKDIR /app

# Copy everything so composer.lock is used when present
COPY . .

RUN composer install \
  --no-dev \
  --no-scripts \
  --prefer-dist \
  --optimize-autoloader \
  --ignore-platform-reqs

# ============================================================
# Stage 2 – Production image (Nginx + PHP-FPM + Supervisor)
# ============================================================
FROM php:8.2-fpm-alpine AS production

LABEL maintainer="MemoSpark"

# ── System dependencies ────────────────────────────────────
RUN apk add --no-cache \
  nginx \
  supervisor \
  mysql-client \
  postgresql-client \
  curl \
  libpng-dev \
  libjpeg-turbo-dev \
  libwebp-dev \
  freetype-dev \
  libzip-dev \
  oniguruma-dev \
  libpq-dev \
  gettext \
  && docker-php-ext-configure gd \
  --with-freetype \
  --with-jpeg \
  --with-webp \
  && docker-php-ext-install -j$(nproc) \
  pdo_mysql \
  pdo_pgsql \
  pgsql \
  mbstring \
  exif \
  pcntl \
  bcmath \
  gd \
  zip \
  opcache

# ── PHP configuration ──────────────────────────────────────
COPY docker/php.ini /usr/local/etc/php/conf.d/app.ini

# ── Nginx configuration ────────────────────────────────────
COPY docker/nginx.conf /etc/nginx/nginx.conf.template

# ── Supervisor configuration ────────────────────────────────
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# ── Entrypoint ─────────────────────────────────────────────
COPY docker/entrypoint.sh /entrypoint.sh
RUN sed -i 's/\r//' /entrypoint.sh && chmod +x /entrypoint.sh

# ── Application files ──────────────────────────────────────
WORKDIR /var/www/html

COPY --from=vendor /app/vendor ./vendor
COPY . .

# ── View config (ensures storage_path() is used, not realpath()) ──
COPY docker/view.php /var/www/html/config/view.php

# Writable directories
RUN mkdir -p storage/framework/{sessions,views,cache} \
  storage/logs \
  bootstrap/cache \
  && chown -R www-data:www-data storage bootstrap/cache \
  && chmod -R 775 storage bootstrap/cache

# Remove docker helper files from app dir
RUN rm -rf .env.example

EXPOSE 8080

ENTRYPOINT ["/entrypoint.sh"]