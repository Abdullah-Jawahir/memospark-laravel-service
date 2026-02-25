# MemoSpark Deployment Documentation

Welcome to the MemoSpark deployment documentation. This folder contains comprehensive guides for deploying all components of the MemoSpark application.

## Documentation Overview

| Document | Description |
|----------|-------------|
| [LARAVEL_DEPLOYMENT.md](./LARAVEL_DEPLOYMENT.md) | Complete guide for deploying the Laravel backend to Railway |
| [FASTAPI_DEPLOYMENT.md](./FASTAPI_DEPLOYMENT.md) | Guide for deploying the FastAPI service to Railway |
| [DOCKER_CORS_SETUP.md](./DOCKER_CORS_SETUP.md) | Deep dive into Docker configuration and CORS settings |
| [MYSQL_DATABASE_SETUP.md](./MYSQL_DATABASE_SETUP.md) | MySQL database setup and configuration on Railway |

## Quick Start

### 1. Deploy MySQL Database

1. Create MySQL service on Railway
2. Get public connection URL (host:port)
3. Note down credentials

See: [MYSQL_DATABASE_SETUP.md](./MYSQL_DATABASE_SETUP.md)

### 2. Deploy Laravel Backend

1. Push code to GitHub
2. Create Railway service from repo
3. Set port to `8080`
4. Configure environment variables
5. Wait for deployment

See: [LARAVEL_DEPLOYMENT.md](./LARAVEL_DEPLOYMENT.md)

### 3. Deploy FastAPI Service

1. Push code to GitHub
2. Create Railway service from repo
3. Set port to `8000`
4. Configure environment variables

See: [FASTAPI_DEPLOYMENT.md](./FASTAPI_DEPLOYMENT.md)

### 4. Configure CORS

1. Update Laravel `config/cors.php` with frontend URL
2. Update nginx.conf with frontend URL
3. Update FastAPI middleware with frontend URL

See: [DOCKER_CORS_SETUP.md](./DOCKER_CORS_SETUP.md)

## Architecture Overview

```
┌─────────────────────────────────────────────────────────────────┐
│                         Frontend                                 │
│                   (Vercel - React/Vite)                         │
│                  https://app.vercel.app                          │
└──────────────────────────┬──────────────────────────────────────┘
                           │
                           ▼
┌──────────────────────────────────────────────────────────────────┐
│                        Railway Project                            │
│  ┌─────────────────────┐  ┌─────────────────────┐                │
│  │   Laravel Service   │  │   FastAPI Service   │                │
│  │   (Nginx + PHP)     │  │   (Uvicorn)         │                │
│  │   Port 8080         │  │   Port 8000         │                │
│  └──────────┬──────────┘  └─────────────────────┘                │
│             │                                                     │
│             ▼                                                     │
│  ┌─────────────────────┐                                         │
│  │   MySQL Database    │                                         │
│  │   Port 3306/33588   │                                         │
│  └─────────────────────┘                                         │
└──────────────────────────────────────────────────────────────────┘
                           │
                           ▼
┌──────────────────────────────────────────────────────────────────┐
│                      External Services                            │
│  ┌────────────┐  ┌────────────┐  ┌────────────────────┐         │
│  │  Supabase  │  │ OpenRouter │  │   Google Gemini    │         │
│  │   (Auth)   │  │   (AI)     │  │      (AI)          │         │
│  └────────────┘  └────────────┘  └────────────────────┘         │
└──────────────────────────────────────────────────────────────────┘
```

## Common Issues Quick Reference

| Issue | Document Section |
|-------|------------------|
| 502 Bad Gateway | [Docker & CORS](./DOCKER_CORS_SETUP.md#issue-502-bad-gateway) |
| CORS errors | [Docker & CORS](./DOCKER_CORS_SETUP.md#cors-deep-dive) |
| Database connection | [MySQL Setup](./MYSQL_DATABASE_SETUP.md#troubleshooting) |
| Duplicate CORS headers | [Docker & CORS](./DOCKER_CORS_SETUP.md#issue-cors---multiple-values) |
| Missing public folder | [Docker & CORS](./DOCKER_CORS_SETUP.md#issue-missing-publicindexphp) |

## Environment Variables Checklist

### Laravel Service

```env
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:...
APP_URL=https://your-laravel.railway.app
DB_HOST=shortline.proxy.rlwy.net
DB_PORT=33588
DB_DATABASE=railway
DB_USERNAME=root
DB_PASSWORD=...
FRONTEND_URL=https://your-frontend.vercel.app
SUPABASE_URL=https://xxx.supabase.co
SUPABASE_KEY=...
FASTAPI_URL=https://your-fastapi.railway.app
```

### FastAPI Service

```env
FRONTEND_URL=https://your-frontend.vercel.app
OPENROUTER_API_KEY=...
GEMINI_API_KEY=...
```

### Frontend (Vercel)

```env
VITE_LARAVEL_API_URL=https://your-laravel.railway.app
VITE_FASTAPI_URL=https://your-fastapi.railway.app
VITE_SUPABASE_URL=https://xxx.supabase.co
VITE_SUPABASE_ANON_KEY=...
```

## Deployment Checklist

Before deploying, verify:

- [ ] All `.gitignore` files don't ignore essential folders (`public/`)
- [ ] Dockerfile `EXPOSE` matches nginx listen port (8080)
- [ ] CORS configuration includes production frontend URL
- [ ] Database credentials are correct
- [ ] API keys are set
- [ ] Railway port settings are correct (8080 for Laravel, 8000 for FastAPI)

## Support

If you encounter issues not covered in these docs:

1. Check Railway deployment logs
2. Test endpoints with `curl`
3. Verify environment variables
4. Check CORS with browser dev tools Network tab

## Contributing

To update these docs:

1. Edit the relevant markdown file
2. Commit with message: `docs: update [document name]`
3. Push to repository
