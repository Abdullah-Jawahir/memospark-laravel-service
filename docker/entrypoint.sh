#!/bin/sh
set -e

# ── Render injects PORT; default to 10000 if not set ──────
export PORT="${PORT:-10000}"

echo "==> Starting MemoSpark Laravel (port $PORT)"

# ── Inject PORT into nginx config ─────────────────────────
envsubst '${PORT}' < /etc/nginx/nginx.conf.template > /etc/nginx/nginx.conf

# ── Ensure writable directories exist ─────────────────────
mkdir -p storage/framework/{sessions,views,cache} storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

# ── Run migrations (non-interactive, will skip if DB unreachable) ──
echo "==> Running migrations..."
php artisan migrate --force --no-interaction || echo "WARNING: Migrations failed or skipped."

# ── Cache config / routes / views for production ──────────
echo "==> Caching config, routes, views..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# ── Start supervisor (manages nginx + php-fpm + queue) ─────
echo "==> Launching supervisor..."
exec supervisord -c /etc/supervisor/conf.d/supervisord.conf
