---
title: User ID Type Consistency Across Laravel Endpoints
date: 2026-04-11
status: completed
---

## Context

Production errors showed UUID casting failures on PostgreSQL:

- `invalid input syntax for type uuid: "3"`
- failing query path: `dashboard/achievements` against `user_achievements.user_id`

Root cause was mixed use of two user identifiers:

- Local user ID (`users.id`, bigint)
- Supabase user ID (`users.supabase_user_id`, UUID string)

## Canonical User ID Rules

Use local user ID (`users.id`) for:

- `flashcard_reviews.user_id`

Use Supabase user ID (`users.supabase_user_id`) for:

- `decks.user_id`
- `documents.user_id`
- `user_goals.user_id`
- `user_achievements.user_id`
- `study_activity_timings.user_id`
- search flashcards tables (`search_flashcard_*`) user ownership fields

## Applied Changes

### Dashboard and study tracking

- `DashboardController::achievements()` now queries `user_achievements` with Supabase UUID.
- `DashboardController::ensurePointBasedAchievements()` now writes UUID user IDs for achievements.
- `StudyTrackingController` keeps local user IDs for `flashcard_reviews`, but writes UUID user IDs for `user_achievements`.
- `StudyTrackingController::getRecentActivity()` resolves local user first, then queries `flashcard_reviews` by local ID.

### Flashcard review endpoints

- `FlashcardReviewController` now resolves local user ID from authenticated Supabase user before create/list operations.
- Prevents writing UUID values to bigint `flashcard_reviews.user_id`.

### Model relationship alignment

Updated relations to use correct keys:

- `UserAchievement::user()` -> `user_id` to `users.supabase_user_id`
- `UserGoal::user()` -> `user_id` to `users.supabase_user_id`
- `User::achievements()` and `Achievement::users()` -> pivot via Supabase UUID
- `User::userGoals()` -> `user_id` to `users.supabase_user_id`
- `Deck::user()` and `Document::user()` -> `user_id` to `users.supabase_user_id`

### Admin endpoint hardening

Additional proactive fixes to prevent future production failures:

- `AdminController::recentActivity()` now resolves deck owner through UUID-aware relation.
- `goalsOverview()` and `goalStatistics()` now join/compare by `users.supabase_user_id`.
- `createUserGoal()` accepts legacy local user IDs but persists canonical Supabase UUID in `user_goals`.
- Added cross-database SQL compatibility for date/day aggregation (`pgsql`, `sqlite`, mysql-like drivers).
- Replaced nonexistent `documents.content_type` aggregation with `documents.file_type`.
- Removed invalid UUID assignment to `goal_types.id` in default-goal creation flow.

## Regression Tests Added

- `tests/Feature/DashboardTest.php`
  - verifies achievements endpoint uses Supabase UUID path
- `tests/Feature/FlashcardReviewControllerTest.php`
  - verifies review create/list uses local numeric user ID
- `tests/Feature/AdminControllerIdConsistencyTest.php`
  - verifies admin recent activity UUID mapping
  - verifies goal overview/statistics on UUID user goals
  - verifies admin goal creation maps local user ID to Supabase UUID

## Validation

- Targeted suites: pass
- Full Laravel test suite: pass
- Workspace diagnostics: no errors
- Cloud Run deployment completed on latest revision with traffic migrated
- Post-deploy checks:
  - `/api/ping` returns `200`
  - unauthenticated `/api/dashboard/achievements` returns `401` (expected)
  - no new error-level logs on latest revision after deployment

## Operational Note

When adding new endpoints, choose one ID domain per table and convert at boundaries (middleware/controller), not inside mixed query predicates.
