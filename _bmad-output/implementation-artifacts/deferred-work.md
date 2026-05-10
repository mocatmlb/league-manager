# Deferred Work

Items deferred from code reviews — triaged 2026-05-09.

---

## Routed to Story 9-1 (Cutover Service Backend)

These are cross-cutting issues that belong in the cutover scope. See the "Deferred Work" section in [9-1-cutover-service-backend.md](9-1-cutover-service-backend.md).

- **PII logged on failed auth** — audit-log/PII-redaction policy (Epic 3 review)
- **Session key inconsistency** — `coach_user_id` vs `user_id`/`admin_id` (Epic 3 review)
- **Plaintext `password_reset_token`** — token hashing (Story 3-1/3-5 review)
- **Legacy non-email username login breakage** — one-time data migration (email-as-username review)
- **`password_changed_at` schema-incomplete semantics** — verify column post-migration (Story 5-2 review)

## Routed to Story 9-2 (Admin Migration Cutover Panel)

- **Disabled-credential login message for legacy usernames** — coordinate with username migration in 9-1 (email-as-username review)

## Routed to Story 10-1 (Post-Launch Hardening)

These are race conditions, missing transactions, and performance issues. See [10-1-post-launch-hardening.md](10-1-post-launch-hardening.md).

- **Score edit race condition** — concurrent double-click overwrites (Story 5-2 review)
- **Reschedule submit not transactional** — insert commits before notification (Story 6-1 review)
- **Reschedule cancel race condition** — concurrent cancels both succeed (Story 6-1 review)
- **CSRF token-per-row on score page** — 20 tokens generated per load (Story 5-2 review)
- **N+1 query on pending registrations** — per-row season/program lookup (Story 4-3 review)

## Accepted as-is (no action planned)

- **`reg-progress` always shows step 1 active** — cosmetic; step-2 rendering belongs to Story 4.2 which is done (Epic 3 review)
- **`robots.txt` missing dev path** — dev environments shouldn't be publicly crawled (Epic 3 review)
- **Redundant `DuplicateUsernameException`/`DuplicateEmailException` catches** — functionally correct as-is (email-as-username review)
- **Pre-existing validation bug: name fields validated against phone key** — HTML `required` attribute covers it (email-as-username review)
- **Cancel confirmation is client-side only** — adequate for current UX flow (Story 6-2 review)
- **Hardcoded role map in PermissionGuard** — fine until roles change; no near-term need (Story 7-1 review)
- **No transaction for profile updates** — name update + log is low-risk non-transactional (Story 7-1 review)
- **Orphaned requests permanently uncancellable** — extremely rare edge case; `requested_by` preserves context (Story 6-1 review)
- **Semantic confusion: cancellation mapped to `Denied`** — required by schema; no downstream issue today (Story 6-1 review)
- **No LIMIT/OFFSET on getCoachRequests** — enhancement, not blocking (Story 6-1 review)
- **FK cascade handling for soft delete** — pre-existing design decision (Story 6-1 review)

## Deferred from: code review of 8-1-user-management-service-full-crud.md (2026-05-09)

- Missing Activity Log Details: `admin.user_role_changed` logs the role name but `admin.user_edited` only logs the field keys, not the old/new values. Existing patterns only log keys for large profile updates.
- Race Condition in `session_invalidated_at`: If `disable()` is called at the exact same second as a login, the `login_time < invalidatedAt` check might fail depending on precision. 1-second resolution is standard for this app.

## Deferred from: code review of 9-1-cutover-service-backend.md (2026-05-10)

- Hardcoded setting key string: The settings table uses `setting_key` and `setting_value`. `disableSharedCredential` uses a hardcoded string `'coaches_password'`.
- Fallback to `Database::getInstance()`: The constructor falls back to the static singleton if no instance is provided, which is a pre-existing pattern in the codebase.

## Deferred from: code review of 10-1-post-launch-hardening.md (2026-05-10)

- ActivityLogger exception swallowing limits transaction effectiveness for log failures. Pre-existing issue in `ActivityLogger` class where internal `try-catch` prevents exceptions from propagating to calling transactions.

## Deferred from: code review of spec-admin-email-verification-override.md (2026-05-10)

- TOCTOU race on status check in POST handlers: `$user['status']` loaded at page-boot is used as a guard but may be stale by the time the POST executes. The service-layer `WHERE status='unverified'` check is the true safety net — a stale outer check shows a generic error to the admin rather than an accurate "already verified" message. Pre-existing pattern across all actions on this page.
- `$stmt->rowCount()` crash if `query()` returns a non-object (DB failure): `forceVerify()` does not guard against a non-object return from `$this->db->query()` before calling `rowCount()`. Pre-existing pattern in `disable()`, `enable()`, `resetPassword()` across the service.
