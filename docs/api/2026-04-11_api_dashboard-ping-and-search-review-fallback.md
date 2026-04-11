---
title: Dashboard Stability and Ping Endpoint
category: api
date_created: 2026-04-11
last_updated: 2026-04-11
author: github-copilot
status: active
---

**Date Created:** 2026-04-11  
**Last Updated:** 2026-04-11  
**Author:** github-copilot

## Endpoint

`GET /api/ping`

## Description

Provides a lightweight public connectivity probe for frontend health checks.

## Request

### Headers

No required headers.

## Response

### Success — `200 OK`

```json
{
  "status": "ok",
  "service": "laravel",
  "timestamp": "2026-04-11T11:20:00+00:00"
}
```

## Dashboard Behavior Update

`GET /api/dashboard` and related dashboard metric calculations now safely handle environments where `search_flashcard_reviews` is temporarily unavailable.

### Fallback Logic

1. If `search_flashcard_reviews` exists, dashboard combines regular flashcard reviews and search flashcard reviews.
2. If `search_flashcard_reviews` is missing, dashboard skips search-review queries and uses regular flashcard data only.
3. Endpoint remains available and returns `200` instead of failing with a `500` table-not-found error.

## Operational Notes

- This fallback is a resilience layer and does not replace database migrations.
- Production should still run pending migrations so `search_flashcard_reviews` is created and search-study metrics are fully included.
