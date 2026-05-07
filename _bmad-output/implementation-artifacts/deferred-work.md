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
