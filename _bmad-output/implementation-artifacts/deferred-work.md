# Deferred Work

Items deferred from code reviews — captured here so they aren't lost.

## Deferred from: code review of Epic 3 (2026-05-06)

- **PII (raw email/identifier) logged on every failed auth attempt** [`includes/AuthService.php` `authenticate()`] — Story 3-1. Broader audit-log/PII-redaction policy decision spans the whole codebase, not story-specific.
- **`reg-progress` always shows step 1 active regardless of state** [`public/coaches/register.php`] — Story 3-2. Cosmetic; step-2 rendering belongs to Story 4.2.
- **Inconsistent session keys: new `coach_user_id` vs. existing `user_id`/`admin_id`** [`includes/AuthService.php`, `public/coaches/logout.php`] — Story 3-4. Cross-cutting; pages outside this diff that read `$_SESSION['user_id']` won't recognize logged-in coaches. Needs a codebase-wide audit (Epic 9 cutover scope).
- **Plaintext `users.password_reset_token` storage** — Story 3-5. Covered by the cross-story decision-needed in Story 3-1 (token hashing). Tracked there.
- **`robots.txt` only disallows production path `/coaches/register.php` but not the dev path `/public/coaches/register.php`** [`public/robots.txt`] — Story 3-6. Dev environments shouldn't be crawled regardless; low-impact.

## Deferred from: code review of 4-3-admin-team-assignment-pending-queue.md (2026-05-07)

- **N+1 query pattern loading season/program per pending row** [`public/admin/teams/index.php` pending loop] — Performance optimization; acceptable at low queue volume; could fold program/season into `getPendingRegistrations()` later.

## Deferred from: code review of 5-2-score-submission-page.md (2026-05-08)

- **20 CSRF tokens generated per page load for edit section** — One token per completed game (LIMIT 20 loop). Pre-existing CSRF token design; atomic single-use token strategy requires cross-codebase change.
- **Concurrent double-click `edit()` — no optimistic lock** — Two simultaneous POSTs can both pass `enforceCompletedForEdit` and the second silently overwrites the first. Requires schema-level row versioning or application lock; deferred to a future hardening epic.
- **`password_changed_at` refactor changes schema-incomplete semantics** — `AuthService::enforceSessionLifetime()` now always hits the `team_owners` table even when `password_changed_at` column is absent. Low risk in production (column is present); worth noting if the app is ever deployed fresh without running migrations.

## Deferred from: code review of 6-1-rescheduleservice-backend.md (2026-05-09)

- **No transaction around submit** — `insert()` commits immediately; notification/ActivityLogger failure can't roll back the DB row. Depends on whether Database layer supports transactions.
- **Orphaned requests permanently uncancellable** — FK `ON DELETE SET NULL` + `(int) NULL === 0` in cancel() makes deleted-user requests stuck in Pending. Rare scenario; `requested_by` preserves context.
- **Semantic confusion mapping cancellation to `Denied`** [includes/RescheduleService.php:123] — Coach-initiated cancellation is mapped to `'Denied'` in the database. While required by schema, it may trigger incorrect downstream logic/emails.
- **`RescheduleService::cancel()` Race Condition** [includes/RescheduleService.php:123] — Concurrent cancel requests can both pass the status check and update the same row.
- **No LIMIT/OFFSET on getCoachRequests** — Returns all rows with no pagination. Enhancement, not blocking review.
- **FK cascade handling for soft delete** — `ON DELETE SET NULL` silently drops the submitter link while `requested_by` holds old data. Pre-existing design decision.

## Deferred from: code review of 6-2-reschedule-request-page.md (2026-05-09)

- **Cancel confirmation is client-side only** — `onclick="return confirm(...)"` is trivially bypassed. No server-side secondary confirmation. Client-side confirm is sufficient for current UX flow.
