---
title: Laravel to FastAPI Cloud Run IAM Authentication
category: service
date_created: 2026-04-11
last_updated: 2026-04-11
author: github-copilot
status: active
---

## Purpose

Enable secure Laravel-to-FastAPI calls when the FastAPI Cloud Run service is private and protected by IAM.

## What Was Implemented

1. Added optional IAM token authentication in `FastApiService` for all FastAPI outbound requests.
2. Added identity token retrieval from Google metadata server:
   - Endpoint: `http://metadata/computeMetadata/v1/instance/service-accounts/default/identity`
   - Header: `Metadata-Flavor: Google`
   - Query: `audience=<FASTAPI_AUDIENCE>&format=full`
3. Added token caching to reduce metadata calls and latency.
4. Preserved backward compatibility:
   - Local development and non-IAM deployments keep working unchanged when IAM auth is disabled.

## Environment Variables

Add these to Laravel service environment settings:

```env
FASTAPI_URL=https://YOUR_FASTAPI_CLOUD_RUN_URL
FASTAPI_IAM_AUTH_ENABLED=true
FASTAPI_IAM_AUDIENCE=
FASTAPI_IAM_METADATA_URL=http://metadata/computeMetadata/v1/instance/service-accounts/default/identity
FASTAPI_IAM_TOKEN_CACHE_SECONDS=3000
```

Notes:

- `FASTAPI_IAM_AUDIENCE` can be left empty to default to `FASTAPI_URL`.
- `FASTAPI_IAM_AUTH_ENABLED` should be `false` for local non-GCP environments.
- Token cache default is 3000 seconds (50 minutes), below typical 1-hour token lifetime.

## Runtime Request Flow

1. Laravel builds FastAPI request.
2. If IAM auth is enabled and `FASTAPI_URL` uses HTTPS:
   - Laravel obtains an identity token from metadata server.
   - Laravel sends `Authorization: Bearer <identity_token>` to FastAPI.
3. Cloud Run IAM validates caller identity and `aud` claim.
4. FastAPI request is accepted only for authorized invokers.

## Security Properties

- FastAPI service remains private (not publicly invokable).
- Only service accounts with `roles/run.invoker` can invoke the service.
- External callers without valid IAM token get `401 Unauthorized`.

## Verification Checklist

1. Cloud Run IAM policy includes Laravel runtime service account as `roles/run.invoker` on FastAPI service.
2. Laravel service environment has IAM variables configured.
3. Laravel outbound FastAPI calls succeed in deployed environment.
4. Unauthenticated direct calls to FastAPI URL return `401`.

## Automated Test Coverage Added

Feature tests were added to validate:

1. IAM token is attached when IAM auth is enabled.
2. No token is attached and metadata is not called when IAM auth is disabled.
3. Token is cached and reused across multiple FastAPI requests.

## Operational Troubleshooting

If Laravel receives `401` from FastAPI:

1. Confirm `FASTAPI_IAM_AUTH_ENABLED=true` in Laravel runtime.
2. Confirm `FASTAPI_URL`/`FASTAPI_IAM_AUDIENCE` exactly matches FastAPI Cloud Run audience expectation.
3. Confirm Laravel service account has `roles/run.invoker` on FastAPI service.
4. Confirm Laravel runs on GCP environment with metadata server access.
