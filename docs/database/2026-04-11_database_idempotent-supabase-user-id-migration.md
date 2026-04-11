---
title: Idempotent users.supabase_user_id Migration
category: database
date_created: 2026-04-11
last_updated: 2026-04-11
author: github-copilot
status: active
---

**Date Created:** 2026-04-11  
**Last Updated:** 2026-04-11  
**Author:** github-copilot

## Summary

Updated migration `2025_08_27_103344_add_supabase_user_id_to_users_table.php` to be idempotent.

## Why This Change Was Needed

Cloud Run startup runs `php artisan migrate --force` during deployment. The migration previously attempted to add `users.supabase_user_id` unconditionally, which fails when the column already exists.

That failure blocks subsequent pending migrations and can prevent required tables (including dashboard/search tables) from being created.

## What Changed

1. Added `Schema::hasColumn('users', 'supabase_user_id')` guard in `up()`.
2. Added matching guard in `down()` before dropping the column.

## Impact

- Startup migrations no longer fail on environments where `supabase_user_id` already exists.
- Pending migrations can proceed, reducing schema drift between local and production.

## Operational Note

After deploying this change, run or trigger migrations on production so previously blocked migrations are applied.
