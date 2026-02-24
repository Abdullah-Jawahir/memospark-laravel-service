#!/bin/sh
# No "set -e" — individual failures must not kill the container

# ── Railway injects PORT automatically ────────────────────
export PORT="${PORT:-8080}"

echo "==> Starting MemoSpark Laravel (port $PORT)"

# ── Copy nginx config and inject PORT using sed (safer than envsubst) ────
sed "s/listen 8080;/listen ${PORT};/" /etc/nginx/nginx.conf.template > /etc/nginx/nginx.conf

# ── Ensure writable directories exist ─────────────────────
mkdir -p /var/www/html/storage/framework/{sessions,views,cache} \
         /var/www/html/storage/logs \
         /var/www/html/bootstrap/cache
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

cd /var/www/html

# ── Clear any stale bootstrap cache from the image ────────
rm -f bootstrap/cache/config.php bootstrap/cache/routes*.php

# ── Cache routes only (safe — no view compilation involved) ──
echo "==> Caching routes..."
php artisan route:cache || echo "WARNING: route:cache failed, continuing."

# ── Run migrations ─────────────────────────────────────────
echo "==> Running migrations..."
php artisan migrate --force --no-interaction \
  && echo "INFO: Migrations completed." \
  || echo "WARNING: Migrations failed or DB not reachable yet."

# ── Start supervisor (manages nginx + php-fpm + queue) ─────
echo "==> Launching supervisor..."
exec supervisord -c /etc/supervisor/conf.d/supervisord.conf

