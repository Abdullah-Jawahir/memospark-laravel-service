# ============================================================
# Stage 1 – Composer dependencies
# ============================================================
FROM composer:2.7 AS vendor

WORKDIR /app

COPY composer.json composer.lock ./

RUN composer install \
  --no-dev \
  --no-scripts \
  --no-autoloader \
  --prefer-dist \
  --ignore-platform-reqs

COPY . .

RUN composer dump-autoload --optimize --no-dev

# ============================================================
# Stage 2 – Node / Vite asset build
# ============================================================
FROM node:20-alpine AS assets

WORKDIR /app

COPY package.json ./
RUN npm install

COPY . .

RUN npm run build

# ============================================================
# Stage 3 – Production image (Nginx + PHP-FPM + Supervisor)
# ============================================================
FROM php:8.2-fpm-alpine AS production

LABEL maintainer="MemoSpark"

# ── System dependencies ────────────────────────────────────
RUN apk add --no-cache \
  nginx \
  supervisor \
  mysql-client \
  curl \
  libpng-dev \
  libjpeg-turbo-dev \
  libwebp-dev \
  freetype-dev \
  libzip-dev \
  oniguruma-dev \
  gettext \
  && docker-php-ext-configure gd \
  --with-freetype \
  --with-jpeg \
  --with-webp \
  && docker-php-ext-install -j$(nproc) \
  pdo_mysql \
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
# Uses envsubst at runtime so it picks up Render's $PORT
COPY docker/nginx.conf /etc/nginx/nginx.conf.template

# ── Supervisor configuration ────────────────────────────────
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# ── Entrypoint ─────────────────────────────────────────────
COPY docker/entrypoint.sh /entrypoint.sh
# Strip Windows line endings (CRLF → LF) to guarantee Linux compatibility
RUN sed -i 's/\r//' /entrypoint.sh && chmod +x /entrypoint.sh

# ── Application files ──────────────────────────────────────
WORKDIR /var/www/html

COPY --from=vendor /app/vendor ./vendor
COPY --from=assets /app/public/build ./public/build
COPY . .

# Writable directories
RUN mkdir -p storage/framework/{sessions,views,cache} \
  storage/logs \
  bootstrap/cache \
  && chown -R www-data:www-data storage bootstrap/cache \
  && chmod -R 775 storage bootstrap/cache

# Remove docker helper files from app dir (already placed at their targets)
RUN rm -rf docker .env.example

EXPOSE 10000

ENTRYPOINT ["/entrypoint.sh"]
