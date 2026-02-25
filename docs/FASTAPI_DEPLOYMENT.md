# FastAPI Application Deployment Guide

This guide covers deploying the MemoSpark FastAPI backend to Railway (or similar platforms).

## Table of Contents

1. [Architecture Overview](#architecture-overview)
2. [Prerequisites](#prerequisites)
3. [Project Structure](#project-structure)
4. [Docker Configuration](#docker-configuration)
5. [Railway Setup](#railway-setup)
6. [Environment Variables](#environment-variables)
7. [CORS Configuration](#cors-configuration)
8. [Troubleshooting](#troubleshooting)

---

## Architecture Overview

The FastAPI application handles:
- Document processing (PDF, DOCX, PPTX, TXT)
- AI-powered flashcard generation
- Search flashcards functionality

```
┌─────────────────────────────────────────────────────────┐
│                    Docker Container                      │
│  ┌─────────────────────────────────────────────────┐   │
│  │              FastAPI Application                 │   │
│  │  ┌─────────────┐  ┌─────────────────────────┐  │   │
│  │  │   Uvicorn   │  │   AI Services           │  │   │
│  │  │ (port 8000) │  │   - OpenRouter API      │  │   │
│  │  └─────────────┘  │   - Google Gemini       │  │   │
│  │                    │   - Local Models        │  │   │
│  │                    └─────────────────────────┘  │   │
│  └─────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────────────────────────┐
│           External Services                              │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐  │
│  │  OpenRouter  │  │   Gemini     │  │   Laravel    │  │
│  │     API      │  │    API       │  │   Backend    │  │
│  └──────────────┘  └──────────────┘  └──────────────┘  │
└─────────────────────────────────────────────────────────┘
```

---

## Prerequisites

1. **GitHub Repository** - FastAPI code pushed to GitHub
2. **Railway Account** - Sign up at [railway.app](https://railway.app)
3. **API Keys** (optional):
   - OpenRouter API key for AI features
   - Google Gemini API key as fallback

---

## Project Structure

```
fastapi-service/
├── app/
│   ├── __init__.py
│   ├── main.py              # FastAPI app entry point
│   ├── config.py            # Configuration settings
│   ├── middleware.py        # CORS and logging middleware
│   ├── routes/              # API route handlers
│   │   ├── __init__.py
│   │   ├── file_processing.py
│   │   ├── health.py
│   │   └── search_flashcards.py
│   ├── services/            # Business logic
│   │   ├── ai_service.py
│   │   ├── document_processor.py
│   │   └── flashcard_generator.py
│   └── logger.py            # Logging configuration
├── requirements.txt         # Python dependencies
├── Dockerfile              # Docker build instructions
├── .dockerignore           # Files to exclude from Docker
└── .env.example            # Example environment variables
```

---

## Docker Configuration

### Dockerfile

```dockerfile
FROM python:3.11-slim

WORKDIR /app

# Install system dependencies
RUN apt-get update && apt-get install -y \
    build-essential \
    curl \
    && rm -rf /var/lib/apt/lists/*

# Install Python dependencies
COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

# Copy application code
COPY . .

# Create non-root user
RUN useradd -m -u 1000 appuser && chown -R appuser:appuser /app
USER appuser

# Expose port
EXPOSE 8000

# Health check
HEALTHCHECK --interval=30s --timeout=10s --start-period=5s --retries=3 \
    CMD curl -f http://localhost:8000/health || exit 1

# Start the application
CMD ["uvicorn", "app.main:app", "--host", "0.0.0.0", "--port", "8000"]
```

### .dockerignore

```
.git
.gitignore
.env
.env.*
__pycache__
*.pyc
*.pyo
*.pyd
.pytest_cache
.coverage
htmlcov
.tox
.nox
venv
.venv
*.egg-info
dist
build
logs/*.log
model_cache
.idea
.vscode
*.md
tests
```

### requirements.txt

```
fastapi>=0.109.0
uvicorn[standard]>=0.27.0
python-multipart>=0.0.6
python-dotenv>=1.0.0
httpx>=0.26.0
pydantic>=2.5.0
PyPDF2>=3.0.0
python-docx>=1.1.0
python-pptx>=0.6.23
aiofiles>=23.2.1
google-generativeai>=0.3.0
```

---

## Railway Setup

### Step 1: Create a New Service

1. In your Railway project, click **"+ New"**
2. Select **"GitHub Repo"**
3. Choose your FastAPI repository
4. Railway will auto-detect the Dockerfile

### Step 2: Configure the Service

1. Go to **Settings → Networking**
2. Set **Port** to `8000`
3. Enable **Public Networking** to get a public URL

### Step 3: Add Environment Variables

Go to **Variables** tab and add:

| Variable | Value |
|----------|-------|
| `FRONTEND_URL` | `https://your-frontend.vercel.app` |
| `OPENROUTER_API_KEY` | Your OpenRouter API key |
| `GEMINI_API_KEY` | Your Google Gemini API key |
| `ENABLE_OPENROUTER` | `true` |
| `ENABLE_GEMINI` | `true` |
| `FALLBACK_TO_LOCAL` | `true` |

### Step 4: Deploy

Railway will automatically deploy when you push to your main branch.

---

## Environment Variables

### Required Variables

| Variable | Description | Example |
|----------|-------------|---------|
| `FRONTEND_URL` | Frontend URL(s) for CORS | `https://app.vercel.app` |

### Optional Variables

| Variable | Description | Default |
|----------|-------------|---------|
| `OPENROUTER_API_KEY` | OpenRouter API key | - |
| `GEMINI_API_KEY` | Google Gemini API key | - |
| `ENABLE_OPENROUTER` | Enable OpenRouter | `true` |
| `ENABLE_GEMINI` | Enable Gemini | `true` |
| `FALLBACK_TO_LOCAL` | Use local models as fallback | `true` |
| `AI_MODEL_PRIORITY` | Model priority order | `openrouter,gemini,local,rule_based` |

---

## CORS Configuration

### Middleware Setup (`app/middleware.py`)

```python
import os
from fastapi import Request
from fastapi.middleware.cors import CORSMiddleware

def setup_cors(app):
    """Setup CORS middleware."""
    frontend_url = os.getenv("FRONTEND_URL", "http://localhost:8080")
    
    # Parse comma-separated origins from env
    from_env = [origin.strip() for origin in frontend_url.split(",") if origin.strip()]
    
    # Always allow production and dev origins
    default_origins = [
        "https://memo-spark-two.vercel.app",
        "http://localhost:5173",
        "http://localhost:3000",
        "http://localhost:8080",
        "http://127.0.0.1:5173",
        "http://127.0.0.1:3000",
    ]
    
    # Merge origins, remove duplicates
    allowed_origins = list(dict.fromkeys(from_env + default_origins))
    
    app.add_middleware(
        CORSMiddleware,
        allow_origins=allowed_origins,
        allow_credentials=True,
        allow_methods=["*"],
        allow_headers=["*"],
    )
```

### Main Application (`app/main.py`)

```python
from fastapi import FastAPI
from .middleware import setup_cors

def create_app() -> FastAPI:
    app = FastAPI(
        title="MemoSpark FastAPI Backend",
        description="AI-powered document processing service",
        version="1.0.0"
    )
    
    # Setup CORS first
    setup_cors(app)
    
    # Include routers
    # ... your routes here
    
    return app

app = create_app()
```

### CORS Configuration Notes

1. **Multiple Origins**: Use comma-separated values in `FRONTEND_URL` env var
2. **Credentials**: `allow_credentials=True` is required for authenticated requests
3. **Methods**: `["*"]` allows all HTTP methods
4. **Headers**: `["*"]` allows all headers including `Authorization`

---

## API Endpoints

### Health Check

```
GET /health
```

Returns service health status.

### Document Processing

```
POST /api/v1/process
Content-Type: multipart/form-data

file: <uploaded file>
language: en|si|ta (optional)
```

### Search Flashcards

```
POST /api/v1/search-flashcards/generate
Content-Type: application/json

{
    "topic": "Machine Learning",
    "num_cards": 10,
    "difficulty": "medium"
}
```

---

## Troubleshooting

### 502 Bad Gateway

**Cause**: Railway can't connect to your container

**Solutions**:
1. Verify port is set to `8000` in Railway Settings
2. Check the `CMD` in Dockerfile uses `--port 8000`
3. Look at deploy logs for startup errors

### CORS Errors

**"No Access-Control-Allow-Origin header"**

1. Verify `FRONTEND_URL` is set correctly
2. Check the origin is in the allowed list
3. Ensure CORS middleware is added BEFORE routes

**Solution**: Update middleware to include your frontend URL:

```python
default_origins = [
    "https://your-frontend.vercel.app",
    # ... other origins
]
```

### Import Errors

**"ModuleNotFoundError"**

1. Ensure all dependencies are in `requirements.txt`
2. Check `__init__.py` files exist in all packages
3. Verify the import paths are correct

### Memory Issues

**"Container killed - out of memory"**

1. Reduce model cache size in `config.py`
2. Use smaller AI models
3. Increase Railway service memory (Settings → Resources)

### API Key Issues

**"Authentication failed" or "Invalid API key"**

1. Verify API keys are set in Railway Variables
2. Check for trailing spaces in key values
3. Ensure keys have proper permissions/credits

---

## Quick Reference

### Useful Commands

```bash
# Local development
uvicorn app.main:app --reload --port 8000

# Test health endpoint
curl http://localhost:8000/health

# Test with Docker locally
docker build -t fastapi-app .
docker run -p 8000:8000 --env-file .env fastapi-app

# View Railway logs
railway logs -f
```

### Project Dependencies

Core dependencies for the FastAPI service:

```
fastapi          - Web framework
uvicorn          - ASGI server
python-multipart - File uploads
httpx            - HTTP client for AI APIs
PyPDF2           - PDF processing
python-docx      - DOCX processing  
python-pptx      - PPTX processing
google-generativeai - Gemini API
```

### Health Check Response

```json
{
    "status": "healthy",
    "version": "1.0.0",
    "services": {
        "openrouter": true,
        "gemini": true,
        "local_models": true
    }
}
```
