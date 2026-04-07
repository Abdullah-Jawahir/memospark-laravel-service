#!/bin/sh
# No "set -e" — individual failures must not kill the container

# ── Validate required env vars ─────────────────────────────
if [ -z "$APP_KEY" ]; then
  echo "FATAL: APP_KEY is not set. Container cannot start."
  exit 1
fi

# ── Port ───────────────────────────────────────────────────
export PORT="${PORT:-8080}"
echo "==> Starting MemoSpark Laravel (port $PORT)"

# ── Inject PORT into nginx config ─────────────────────────
sed "s/listen 8080;/listen ${PORT};/" /etc/nginx/nginx.conf.template > /etc/nginx/nginx.conf

# ── Ensure writable directories exist ─────────────────────
mkdir -p /var/www/html/storage/framework/{sessions,views,cache} \
         /var/www/html/storage/logs \
         /var/www/html/bootstrap/cache

# ── Nginx temp directories ─────────────────────────────────
mkdir -p /tmp/nginx_client_body /tmp/nginx_proxy /tmp/nginx_fastcgi /tmp/nginx_uwsgi /tmp/nginx_scgi
chown -R www-data:www-data /tmp/nginx_client_body /tmp/nginx_proxy /tmp/nginx_fastcgi /tmp/nginx_uwsgi /tmp/nginx_scgi
chmod -R 755 /tmp/nginx_client_body /tmp/nginx_proxy /tmp/nginx_fastcgi /tmp/nginx_uwsgi /tmp/nginx_scgi

cd /var/www/html

# ── Clear any stale bootstrap cache from the image ────────
rm -f bootstrap/cache/config.php bootstrap/cache/routes*.php

# ── Artisan cache ops ──────────────────────────────────────
# Run as root here — chown happens AFTER to ensure www-data owns all generated files
echo "==> Clearing config and caches..."
php artisan config:clear
php artisan cache:clear

echo "==> Caching config and routes..."
php artisan config:cache || echo "WARNING: config:cache failed, continuing."
php artisan route:cache  || echo "WARNING: route:cache failed, continuing."

# ── Run migrations ─────────────────────────────────────────
echo "==> Running migrations..."
php artisan migrate --force --no-interaction \
  && echo "INFO: Migrations completed." \
  || echo "WARNING: Migrations failed or DB not reachable yet."

# ── Fix ownership AFTER artisan runs ──────────────────────
# This ensures www-data can write to logs, cache, sessions, views —
# including any files just created by artisan above (owned by root).
echo "==> Setting file ownership..."
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# ── Start supervisor (manages nginx + php-fpm + queue) ─────
echo "==> Launching supervisor..."
exec supervisord -c /etc/supervisor/conf.d/supervisord.conf