---
title: MySQL and PostgreSQL Migration Compatibility Rules
category: database
date_created: 2026-04-08
last_updated: 2026-04-08
author: GitHub Copilot
status: active
---

## MySQL and PostgreSQL Migration Compatibility Rules

## Purpose

This document defines migration-safe schema patterns used in this project so the same Laravel migrations can run on both MySQL and PostgreSQL without database-specific failures.

## Applied Conventions

1. Primary keys

- Use `$table->id()` for application-managed table IDs.
- Avoid UUID-typed primary columns in migrations for internal entity IDs.

1. Foreign keys

- Use `foreignId(...)->constrained(...)->cascadeOnDelete()` (or `nullOnDelete()` when nullable) instead of manual `unsignedBigInteger` + `foreign(...)` pairs.
- This keeps foreign key definitions consistent and portable between MySQL and PostgreSQL.

1. Enums

- Avoid `$table->enum(...)` in migrations.
- Use `$table->string(...)` with application-level validation for allowed values.

1. Supabase identifiers

- Treat Supabase user IDs as external identifiers (`supabase_user_id` or string user reference columns), not as `users.id`.
- `users.id` remains the local auto-increment bigint key.

## UUID/Bigint Lookup Safety Rule

When input may be either email or ID, never issue a query that compares non-numeric input directly against bigint `users.id`.

Use branching logic:

- If input is email, query by email.
- Otherwise, query by local numeric ID.

For Supabase-authenticated flows in this project, prefer:

- `users.supabase_user_id` lookup first
- `users.email` fallback

## Notes for Future Migrations

- New migrations should follow this document and avoid database-specific SQL features unless guarded by explicit driver checks.
- If enum-like constraints are needed, enforce allowed values in request validation, model rules, or domain services.
- Keep `down()` methods reversible and constraint-safe (drop FK before dropping columns where required).
