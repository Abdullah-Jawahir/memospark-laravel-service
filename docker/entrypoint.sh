#!/bin/sh
# NOTE: no "set -e" — artisan commands may fail gracefully but supervisor must always start

# ── Railway injects PORT automatically ────────────────────
export PORT="${PORT:-8080}"

echo "==> Starting MemoSpark Laravel (port $PORT)"

# ── Inject PORT into nginx config ─────────────────────────
envsubst '${PORT}' < /etc/nginx/nginx.conf.template > /etc/nginx/nginx.conf

# ── Ensure writable directories exist ─────────────────────
mkdir -p /var/www/html/storage/framework/{sessions,views,cache} \
         /var/www/html/storage/logs \
         /var/www/html/bootstrap/cache
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

cd /var/www/html

# ── Cache config & routes (always safe) ───────────────────
echo "==> Caching config and routes..."
php artisan config:cache  || echo "WARNING: config:cache failed."
php artisan route:cache   || echo "WARNING: route:cache failed."

# ── Skip view:cache — API-only service, no Blade views compiled ──
# php artisan view:cache

# ── Run migrations (only after config is cached so DB env vars are loaded) ──
echo "==> Running migrations..."
php artisan migrate --force --no-interaction \
  && echo "INFO: Migrations completed." \
  || echo "WARNING: Migrations failed or DB not reachable yet."

# ── Start supervisor (manages nginx + php-fpm + queue) ─────
echo "==> Launching supervisor..."
exec supervisord -c /etc/supervisor/conf.d/supervisord.conf
