# Docker & CORS Setup Guide for Laravel on Railway/Render

This guide provides detailed instructions on setting up Docker and CORS for deploying Laravel applications to cloud platforms like Railway or Render.

## Table of Contents

1. [Docker Setup](#docker-setup)
2. [Nginx Configuration](#nginx-configuration)
3. [CORS Deep Dive](#cors-deep-dive)
4. [Railway-Specific Settings](#railway-specific-settings)
5. [Render-Specific Settings](#render-specific-settings)
6. [Common Issues & Solutions](#common-issues--solutions)

---

## Docker Setup

### Required Files

Your Laravel project needs these files in a `/docker` folder:

```
laravel-service/
├── docker/
│   ├── nginx.conf          # Nginx web server config
│   ├── php.ini             # PHP configuration
│   ├── supervisord.conf    # Process manager config
│   └── entrypoint.sh       # Container startup script
├── Dockerfile              # Docker build instructions
├── .dockerignore           # Files to exclude
└── ... (Laravel files)
```

### Dockerfile Explained

```dockerfile
# ============================================================
# Stage 1: Composer Dependencies
# ============================================================
FROM composer:2.7 AS vendor

WORKDIR /app
COPY . .

# Install dependencies without dev packages
RUN composer install \
    --no-dev \
    --no-scripts \
    --prefer-dist \
    --optimize-autoloader \
    --ignore-platform-reqs

# ============================================================
# Stage 2: Production Image
# ============================================================
FROM php:8.2-fpm-alpine AS production

# Install system packages and PHP extensions
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
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip opcache

# Copy configuration files
COPY docker/php.ini /usr/local/etc/php/conf.d/app.ini
COPY docker/nginx.conf /etc/nginx/nginx.conf.template
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Setup entrypoint
COPY docker/entrypoint.sh /entrypoint.sh
RUN sed -i 's/\r//' /entrypoint.sh && chmod +x /entrypoint.sh

# Copy application
WORKDIR /var/www/html
COPY --from=vendor /app/vendor ./vendor
COPY . .

# Set permissions
RUN mkdir -p storage/framework/{sessions,views,cache} storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# IMPORTANT: Expose the port nginx listens on
EXPOSE 8080

ENTRYPOINT ["/entrypoint.sh"]
```

### Key Docker Concepts

#### Multi-Stage Builds
- **Stage 1 (vendor)**: Installs Composer dependencies
- **Stage 2 (production)**: Creates the final image with only what's needed
- This keeps the final image small and secure

#### Port Configuration
```dockerfile
EXPOSE 8080  # Must match nginx listen port
```

⚠️ **Critical**: The `EXPOSE` port must match what nginx listens on!

### .dockerignore

Exclude unnecessary files to speed up builds:

```
.git
.gitignore
.gitattributes
node_modules
vendor
tests
*.md
*.txt
*.json
!composer.json
!composer.lock
.env
.env.*
!.env.example
storage/logs/*.log
storage/framework/cache
storage/framework/sessions
storage/framework/views
bootstrap/cache/*.php
```

⚠️ **Warning**: Don't add `public` to .dockerignore or .gitignore!

---

## Nginx Configuration

### Complete nginx.conf

```nginx
user www-data;
worker_processes auto;
pid /tmp/nginx.pid;
error_log /dev/stderr warn;

events {
    worker_connections 1024;
}

http {
    include /etc/nginx/mime.types;
    default_type application/octet-stream;

    log_format main '$remote_addr - [$time_local] "$request" '
                    '$status $body_bytes_sent "$http_referer" '
                    '"$http_user_agent"';

    access_log /dev/stdout main;

    sendfile on;
    keepalive_timeout 65;
    client_max_body_size 50M;

    gzip on;
    gzip_types text/plain text/css application/json application/javascript text/xml application/xml text/javascript;

    server {
        # Port is replaced by entrypoint.sh at runtime
        listen 8080;
        server_name _;

        root /var/www/html/public;
        index index.php;

        # Security headers
        add_header X-Frame-Options "SAMEORIGIN" always;
        add_header X-Content-Type-Options "nosniff" always;
        add_header X-XSS-Protection "1; mode=block" always;

        # Health check endpoint (no PHP)
        location = /ping {
            access_log off;
            add_header Content-Type text/plain;
            return 200 "OK\n";
        }

        # Main location with CORS preflight handling
        location / {
            # Handle OPTIONS preflight requests
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

        # PHP processing
        location ~ \.php$ {
            fastcgi_pass 127.0.0.1:9000;
            fastcgi_index index.php;
            fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
            include fastcgi_params;
            fastcgi_read_timeout 300;
        }

        # Deny hidden files
        location ~ /\.(?!well-known).* {
            deny all;
        }
    }
}
```

### Why Nginx + PHP-FPM?

```
Browser Request → Nginx (port 8080) → PHP-FPM (port 9000) → Laravel
                        ↓
                  Static files
                  (CSS, JS, images)
```

- **Nginx**: Handles static files efficiently, manages connections
- **PHP-FPM**: Processes PHP code, runs Laravel
- **Port 8080**: What Railway/Render connects to
- **Port 9000**: Internal PHP-FPM (not exposed externally)

---

## CORS Deep Dive

### What is CORS?

**Cross-Origin Resource Sharing** allows your frontend (e.g., `https://app.vercel.app`) to make API requests to your backend (e.g., `https://api.railway.app`).

### The Preflight Request

When your frontend sends a request with:
- Custom headers (`Authorization`, `Content-Type: application/json`)
- Methods other than GET/POST simple requests

The browser first sends an **OPTIONS** request (preflight):

```
1. Browser → OPTIONS /api/user/profile
   Headers: Origin, Access-Control-Request-Method, Access-Control-Request-Headers

2. Server → 204 No Content
   Headers: Access-Control-Allow-Origin, Access-Control-Allow-Methods, etc.

3. Browser → GET /api/user/profile (actual request)
   Headers: Authorization: Bearer token

4. Server → 200 OK + data
   Headers: Access-Control-Allow-Origin
```

### CORS Headers Explained

| Header | Purpose | Example |
|--------|---------|---------|
| `Access-Control-Allow-Origin` | Which origins can access | `https://app.vercel.app` |
| `Access-Control-Allow-Methods` | Allowed HTTP methods | `GET, POST, PUT, DELETE, OPTIONS` |
| `Access-Control-Allow-Headers` | Allowed request headers | `Authorization, Content-Type` |
| `Access-Control-Allow-Credentials` | Allow cookies/auth | `true` |
| `Access-Control-Max-Age` | Cache preflight (seconds) | `3600` |

### Laravel CORS Configuration

**`config/cors.php`**:

```php
<?php

return [
    // Which paths should have CORS headers
    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    // Allowed HTTP methods
    'allowed_methods' => ['*'],

    // Allowed origins (IMPORTANT!)
    'allowed_origins' => [
        env('FRONTEND_URL', 'http://localhost:8080'),
        'http://localhost:5173',
        'http://localhost:3000',
        'https://your-frontend.vercel.app',  // Production frontend
    ],

    // Regex patterns for origins
    'allowed_origins_patterns' => [
        '#^https://your-app.*\.vercel\.app$#',  // Vercel preview URLs
    ],

    // Allowed request headers
    'allowed_headers' => ['*'],

    // Headers to expose to frontend
    'exposed_headers' => [],

    // Preflight cache time
    'max_age' => 3600,

    // CRITICAL: Must be true when using Authorization header
    'supports_credentials' => true,
];
```

### Where to Handle CORS?

You have two options:

#### Option 1: Nginx handles OPTIONS, Laravel handles actual requests (Recommended)

```
OPTIONS request → Nginx returns 204 with CORS headers
GET/POST request → Nginx → PHP → Laravel (HandleCors middleware adds headers)
```

**nginx.conf** (OPTIONS only):
```nginx
location / {
    if ($request_method = OPTIONS) {
        add_header Access-Control-Allow-Origin "https://frontend.vercel.app" always;
        # ... other CORS headers
        return 204;
    }
    try_files $uri $uri/ /index.php?$query_string;
}
```

**DON'T** add CORS headers to `location ~ \.php$` - Laravel will add them!

#### Option 2: Laravel handles everything

Remove all CORS from nginx, let Laravel's `HandleCors` middleware handle all requests including OPTIONS.

⚠️ This can be slower and may not work in all cases.

### Common CORS Mistakes

#### ❌ Duplicate Headers

```
Access-Control-Allow-Origin: https://app.vercel.app, https://app.vercel.app
```

**Cause**: Both nginx AND Laravel adding headers  
**Fix**: Remove CORS from nginx `location ~ \.php$` block

#### ❌ Using Wildcard with Credentials

```php
'allowed_origins' => ['*'],  // WON'T WORK with credentials!
'supports_credentials' => true,
```

**Fix**: Specify exact origins when using credentials

#### ❌ Missing Origin in Allow List

```
Access to fetch blocked by CORS policy: No 'Access-Control-Allow-Origin' header
```

**Fix**: Add your frontend URL to `config/cors.php` allowed_origins

---

## Railway-Specific Settings

### Service Configuration

1. **Settings → Networking → Port**: `8080`
2. **Settings → Networking → Public Networking**: Enabled

### Environment Variables

Set in **Variables** tab:

```
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:your-key-here
DB_HOST=shortline.proxy.rlwy.net
DB_PORT=33588
FRONTEND_URL=https://your-frontend.vercel.app
```

### Database Connection

For MySQL on Railway:

1. Get the **public** URL from MySQL service (Settings → Networking)
2. Format: `host:port` (e.g., `shortline.proxy.rlwy.net:33588`)
3. Set `DB_HOST` and `DB_PORT` separately

⚠️ **Don't use** `${{MySQL.MYSQLHOST}}` syntax if having connection issues - use actual values.

### Port Detection

Railway auto-detects ports. If it detects wrong port (e.g., 9000 instead of 8080):

1. Go to **Settings → Networking**
2. Click on the port number
3. Change to `8080`

---

## Render-Specific Settings

### Service Configuration

1. **Environment**: Docker
2. **Health Check Path**: `/ping`
3. **Port**: `8080` (set via `PORT` env var or Dockerfile EXPOSE)

### Environment Variables

```
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:your-key-here
DB_HOST=your-db-host.render.com
DB_PORT=5432
FRONTEND_URL=https://your-frontend.vercel.app
```

### Build Settings

```
Build Command: (uses Dockerfile)
Start Command: (uses ENTRYPOINT from Dockerfile)
```

---

## Common Issues & Solutions

### Issue: 502 Bad Gateway

**Symptoms**: All requests return 502

**Causes & Solutions**:

| Cause | Solution |
|-------|----------|
| Wrong port | Set port to 8080 in platform settings |
| nginx not starting | Check nginx.conf syntax |
| PHP-FPM crashed | Check memory limits, error logs |
| Missing public folder | Ensure `public/` is not in .gitignore |

### Issue: CORS - No Header Present

**Symptoms**: 
```
No 'Access-Control-Allow-Origin' header is present
```

**Solutions**:
1. Add frontend URL to `config/cors.php`
2. Ensure nginx handles OPTIONS with CORS headers
3. Check `supports_credentials` is `true`

### Issue: CORS - Multiple Values

**Symptoms**:
```
'Access-Control-Allow-Origin' header contains multiple values
```

**Solution**: Remove CORS headers from nginx `location ~ \.php$` block:

```nginx
location ~ \.php$ {
    # NO add_header here - Laravel handles it
    fastcgi_pass 127.0.0.1:9000;
    # ...
}
```

### Issue: Database Connection Refused

**Symptoms**:
```
SQLSTATE[HY000] [2002] Connection refused
```

**Solutions**:
1. Use public database URL (not internal)
2. Verify `DB_HOST` and `DB_PORT` are correct
3. Check database service is running
4. Ensure services are in same region (for internal networking)

### Issue: Missing public/index.php

**Symptoms**:
```
realpath() "/var/www/html/public" failed (No such file or directory)
```

**Solution**:
1. Check `.gitignore` doesn't have `public` line
2. Add public folder to git: `git add -f public/`
3. Commit and push

---

## Checklist for Deployment

Before deploying, verify:

- [ ] `Dockerfile` has `EXPOSE 8080`
- [ ] `nginx.conf` has `listen 8080`
- [ ] `public/` folder is NOT in `.gitignore`
- [ ] `config/cors.php` has your frontend URL
- [ ] `config/cors.php` has `supports_credentials => true`
- [ ] Platform port setting is `8080`
- [ ] Database credentials are correct
- [ ] `APP_KEY` is set

After deploying, test:

```bash
# Health check
curl https://your-api.railway.app/ping

# CORS preflight
curl -I -X OPTIONS "https://your-api.railway.app/api/test" \
  -H "Origin: https://your-frontend.vercel.app" \
  -H "Access-Control-Request-Method: GET"

# API request
curl -H "Authorization: Bearer TOKEN" \
  "https://your-api.railway.app/api/user/profile"
```
