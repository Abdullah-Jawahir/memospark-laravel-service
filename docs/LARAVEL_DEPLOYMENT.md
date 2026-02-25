# Laravel Application Deployment Guide

This guide covers deploying the MemoSpark Laravel backend to Railway (or similar platforms like Render).

## Table of Contents

1. [Architecture Overview](#architecture-overview)
2. [Prerequisites](#prerequisites)
3. [Docker Configuration](#docker-configuration)
4. [Railway Setup](#railway-setup)
5. [Environment Variables](#environment-variables)
6. [CORS Configuration](#cors-configuration)
7. [Troubleshooting](#troubleshooting)

---

## Architecture Overview

The Laravel application runs in a Docker container with:

- **Nginx** - Web server (listens on port 8080)
- **PHP-FPM** - PHP process manager (internal port 9000)
- **Supervisor** - Process manager for nginx, php-fpm, and queue worker

```
┌─────────────────────────────────────────────────────────┐
│                    Docker Container                      │
│  ┌─────────────┐   ┌─────────────┐   ┌──────────────┐  │
│  │   Nginx     │──▶│  PHP-FPM    │──▶│   Laravel    │  │
│  │  (port 8080)│   │ (port 9000) │   │     App      │  │
│  └─────────────┘   └─────────────┘   └──────────────┘  │
│         │                                               │
│         ▼                                               │
│  ┌─────────────┐                                       │
│  │Queue Worker │                                       │
│  └─────────────┘                                       │
└─────────────────────────────────────────────────────────┘
         │
         ▼
┌─────────────────┐
│     MySQL       │
│   (Railway)     │
└─────────────────┘
```

---

## Prerequisites

1. **GitHub Repository** - Your Laravel code pushed to GitHub
2. **Railway Account** - Sign up at [railway.app](https://railway.app)
3. **MySQL Database** - Can be provisioned on Railway

---

## Docker Configuration

### Dockerfile

The Dockerfile uses a multi-stage build:

```dockerfile
# Stage 1: Install Composer dependencies
FROM composer:2.7 AS vendor
WORKDIR /app
COPY . .
RUN composer install --no-dev --no-scripts --prefer-dist --optimize-autoloader --ignore-platform-reqs

# Stage 2: Production image
FROM php:8.2-fpm-alpine AS production

# Install system dependencies
RUN apk add --no-cache nginx supervisor mysql-client curl \
    libpng-dev libjpeg-turbo-dev libwebp-dev freetype-dev libzip-dev oniguruma-dev gettext \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip opcache

# Copy configuration files
COPY docker/php.ini /usr/local/etc/php/conf.d/app.ini
COPY docker/nginx.conf /etc/nginx/nginx.conf.template
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

# Copy application
WORKDIR /var/www/html
COPY --from=vendor /app/vendor ./vendor
COPY . .

# Set permissions
RUN mkdir -p storage/framework/{sessions,views,cache} storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

EXPOSE 8080
ENTRYPOINT ["/entrypoint.sh"]
```

### Key Files in `/docker` Folder

#### 1. `nginx.conf`

```nginx
user www-data;
worker_processes auto;
pid /tmp/nginx.pid;

events {
    worker_connections 1024;
}

http {
    include /etc/nginx/mime.types;
    default_type application/octet-stream;

    server {
        listen 8080;
        server_name _;
        root /var/www/html/public;
        index index.php;

        # Health check endpoint
        location = /ping {
            return 200 "OK\n";
            add_header Content-Type text/plain;
        }

        # CORS preflight handling
        location / {
            if ($request_method = OPTIONS) {
                add_header Access-Control-Allow-Origin "https://your-frontend.vercel.app" always;
                add_header Access-Control-Allow-Methods "GET, POST, PUT, PATCH, DELETE, OPTIONS" always;
                add_header Access-Control-Allow-Headers "Authorization, Content-Type, Accept, X-Requested-With, Origin" always;
                add_header Access-Control-Allow-Credentials "true" always;
                add_header Access-Control-Max-Age 3600 always;
                return 204;
            }
            try_files $uri $uri/ /index.php?$query_string;
        }

        location ~ \.php$ {
            fastcgi_pass 127.0.0.1:9000;
            fastcgi_index index.php;
            fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
            include fastcgi_params;
            fastcgi_read_timeout 300;
        }

        location ~ /\.(?!well-known).* {
            deny all;
        }
    }
}
```

#### 2. `entrypoint.sh`

```bash
#!/bin/sh

# Use Railway's PORT or default to 8080
export PORT="${PORT:-8080}"

echo "==> Starting Laravel (port $PORT)"

# Inject PORT into nginx config
sed "s/listen 8080;/listen ${PORT};/" /etc/nginx/nginx.conf.template > /etc/nginx/nginx.conf

# Setup directories
mkdir -p /var/www/html/storage/framework/{sessions,views,cache} \
         /var/www/html/storage/logs \
         /var/www/html/bootstrap/cache
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

cd /var/www/html

# Clear caches
php artisan config:clear
php artisan cache:clear

# Cache routes
php artisan route:cache

# Run migrations
php artisan migrate --force --no-interaction

# Start supervisor
exec supervisord -c /etc/supervisor/conf.d/supervisord.conf
```

#### 3. `supervisord.conf`

```ini
[supervisord]
nodaemon=true
user=root

[program:php-fpm]
command=php-fpm -F
autostart=true
autorestart=true
stdout_logfile=/dev/stdout
stdout_logfile_maxbytes=0
stderr_logfile=/dev/stderr
stderr_logfile_maxbytes=0

[program:nginx]
command=nginx -g "daemon off;"
autostart=true
autorestart=true
stdout_logfile=/dev/stdout
stdout_logfile_maxbytes=0
stderr_logfile=/dev/stderr
stderr_logfile_maxbytes=0

[program:queue-worker]
command=php /var/www/html/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stdout_logfile=/dev/stdout
stdout_logfile_maxbytes=0
stderr_logfile=/dev/stderr
stderr_logfile_maxbytes=0
```

---

## Railway Setup

### Step 1: Create a New Project

1. Go to [railway.app](https://railway.app) and sign in
2. Click **"New Project"**
3. Select **"Deploy from GitHub repo"**
4. Connect your GitHub account and select your Laravel repository

### Step 2: Configure the Service

After deploying, go to your service settings:

1. **Settings → Networking → Port**: Set to `8080`
2. **Settings → Networking → Public Networking**: Enable and note the URL

### Step 3: Add MySQL Database

1. In your Railway project, click **"+ New"**
2. Select **"Database" → "MySQL"**
3. Railway will provision a MySQL instance

### Step 4: Configure Environment Variables

Go to your Laravel service → **Variables** tab and add:

| Variable | Value |
|----------|-------|
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `APP_KEY` | `base64:...` (generate with `php artisan key:generate --show`) |
| `APP_URL` | `https://your-service.up.railway.app` |
| `DB_CONNECTION` | `mysql` |
| `DB_HOST` | MySQL public host (e.g., `shortline.proxy.rlwy.net`) |
| `DB_PORT` | MySQL public port (e.g., `33588`) |
| `DB_DATABASE` | `railway` |
| `DB_USERNAME` | `root` |
| `DB_PASSWORD` | (from MySQL service variables) |
| `FRONTEND_URL` | `https://your-frontend.vercel.app` |

### Step 5: Verify Deployment

After deployment, test:

```bash
# Health check
curl https://your-service.up.railway.app/ping

# API endpoint
curl https://your-service.up.railway.app/api/user/profile
```

---

## Environment Variables

### Required Variables

| Variable | Description | Example |
|----------|-------------|---------|
| `APP_KEY` | Laravel encryption key | `base64:xxxxx` |
| `APP_ENV` | Environment | `production` |
| `APP_DEBUG` | Debug mode | `false` |
| `DB_HOST` | Database host | `shortline.proxy.rlwy.net` |
| `DB_PORT` | Database port | `33588` |
| `DB_DATABASE` | Database name | `railway` |
| `DB_USERNAME` | Database user | `root` |
| `DB_PASSWORD` | Database password | (from Railway) |
| `FRONTEND_URL` | Frontend URL for CORS | `https://app.vercel.app` |

### Optional Variables

| Variable | Description | Default |
|----------|-------------|---------|
| `PORT` | Server port | `8080` |
| `LOG_CHANNEL` | Logging channel | `stack` |
| `CACHE_STORE` | Cache driver | `database` |
| `QUEUE_CONNECTION` | Queue driver | `database` |
| `SESSION_DRIVER` | Session driver | `database` |

---

## CORS Configuration

### Laravel CORS (`config/cors.php`)

```php
<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    
    'allowed_methods' => ['*'],
    
    'allowed_origins' => [
        env('FRONTEND_URL', 'http://localhost:8080'),
        'http://localhost:5173',
        'http://localhost:3000',
        'https://your-frontend.vercel.app',
    ],
    
    'allowed_origins_patterns' => [
        '#^https://your-app.*\.vercel\.app$#',
    ],
    
    'allowed_headers' => ['*'],
    
    'exposed_headers' => [],
    
    'max_age' => 3600,
    
    'supports_credentials' => true,
];
```

### Important CORS Notes

1. **`supports_credentials: true`** - Required when frontend sends `Authorization` header
2. **Don't use `*` for origins** - When credentials are used, you must specify exact origins
3. **Handle OPTIONS in nginx** - For preflight requests, nginx returns 204 with CORS headers
4. **Laravel handles actual requests** - The `HandleCors` middleware adds headers to GET/POST responses
5. **Don't duplicate headers** - Either nginx OR Laravel should add CORS headers, not both

---

## Troubleshooting

### 502 Bad Gateway

**Cause**: Railway can't connect to your container

**Solutions**:
1. Check the port in Railway Settings → Networking (should be `8080`)
2. Verify nginx is listening on the correct port
3. Check deploy logs for startup errors

### CORS Errors

**"No 'Access-Control-Allow-Origin' header"**
- Ensure your frontend URL is in `config/cors.php` allowed_origins
- Check nginx is handling OPTIONS requests

**"Multiple values for Access-Control-Allow-Origin"**
- Remove CORS headers from nginx `location ~ \.php$` block
- Let Laravel handle CORS for actual requests

### Database Connection Refused

**Cause**: Wrong MySQL host/port or services in different regions

**Solutions**:
1. Use the **public** MySQL URL (not internal)
2. Format: `host:port` (e.g., `shortline.proxy.rlwy.net:33588`)
3. Ensure both services are in the same Railway project

### "realpath() failed - No such file or directory"

**Cause**: The `public` folder is missing from the Docker image

**Solution**: 
1. Check `.gitignore` doesn't include `public`
2. Ensure `public/index.php` exists in your repo
3. Rebuild the Docker image

### Queue Worker Crashing

**Cause**: Usually database connection issues

**Solution**: 
1. Verify `DB_HOST` and `DB_PORT` are correct
2. Check MySQL is running and accessible
3. Look at queue worker logs in Railway

---

## Quick Reference

### Useful Commands

```bash
# Generate app key
php artisan key:generate --show

# Clear all caches
php artisan config:clear && php artisan cache:clear && php artisan route:clear

# Run migrations
php artisan migrate --force

# Test CORS preflight
curl -I -X OPTIONS "https://your-api.up.railway.app/api/endpoint" \
  -H "Origin: https://your-frontend.vercel.app" \
  -H "Access-Control-Request-Method: GET"

# Test actual request
curl -H "Authorization: Bearer TOKEN" \
  "https://your-api.up.railway.app/api/user/profile"
```

### Railway CLI (Optional)

```bash
# Install
npm install -g @railway/cli

# Login
railway login

# Deploy
railway up

# View logs
railway logs
```
