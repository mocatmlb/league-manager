# Deferred Work

Items deferred from code reviews — triaged 2026-05-09.

---

## From spec-home-schedule-mobile-cards (2026-06-03)

- `public/index.prod.php` is missing the weather widget that `index.php` has — this is a pre-existing divergence, not caused by the mobile card change. The prod copy should be brought in sync with the dev home page's weather widget feature.

## From spec-admin-schedules-submitted-reviewed-dates (2026-06-07)

- `public/admin/schedules/index.php` pending request accordion items use `data-bs-parent` which enforces single-open behavior — may want to remove it if admins want to compare multiple requests side by side.
- `public/admin/schedules/index.php` pending requests serialized as full JSON into onclick attributes (exposes `reviewed_by` ID and `review_notes` in HTML source) — pre-existing in the desktop table, now also in the mobile accordion. Consider passing only the fields the edit modal needs.

## Deferred from: code review of 20-1-game-scr-conflict-detection.md (2026-06-07)
- Pre-existing DB divergence between dev and prod environments.
- Missing indices on several legacy tables in `database/schema.sql`.

## Deferred from: code review of 20-2-scr-conflict-warnings.md (2026-06-07)
- User impersonation impact: Should conflict warnings differentiate between coach-led and admin-led submissions? (Reason: feature will be removed)
- Pre-existing DB divergence between dev and prod environments.
- Missing indices on several legacy tables in `database/schema.sql`.

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
## Deferred from: code review of 12-1-unified-login-page.md (2026-05-10)
- Legacy redirect cleanup: Old login paths still exist as 301 stubs. These should eventually be removed once all bookmarks/links are updated.

## Deferred from: nav unified-login-link (2026-05-10)

- **Admin `intended_url` whitelist too narrow** — `unified_login_safe_redirect_target()` only allows `coaches/*` paths; admins who time out and are redirected to `/login.php` with an `intended_url` of `admin/*` land on the admin dashboard instead of their original destination. Expand the whitelist regex in `public/login.php` to also allow `admin/*` paths.
- **301 redirect stubs for old login pages** — `coaches/login.php` and `admin/login.php` use HTTP 301 (permanent); browsers cache these indefinitely, making a transition to a different URL non-trivial. Pre-existing decision from Story 12.1.

## Deferred from: email-verification-ux-improvements (2026-05-10)

- **Persistent token as account-existence oracle** — `verifyEmail()` now preserves the verification token in the DB after successful verification (to support the "already verified" UX path). A user who receives a verification email retains a permanent oracle: clicking the link after verification always shows the `already-verified` page rather than "invalid token," confirming the account exists and is active. Acceptable tradeoff for the current threat model but should be revisited if phishing becomes a concern. Mitigation: enforce short token TTLs and consider expiring tokens on a cron after N days.
- **`{first_name}` not HTML-encoded in email templates** — `EmailService::processTemplate()` substitutes all context values with raw `str_replace()`. For plain-text templates this was harmless; for the new HTML `registration_verification` template, a first name containing `<` or `>` (e.g., `O'Brien`, `Jr.`) will break the email HTML structure. Fix requires `htmlspecialchars()` wrapping in `processTemplate()` — a shared function affecting all templates; needs broader testing before changing.

## Deferred from: code review of 15-2-enhanced-location-management.md (2026-05-23)

- Legacy text-name matching can miss renamed/variant values in `schedules.location`, so some semantically linked games may not be counted by the current `location = ?` fallback check. This is pre-existing data-model drift risk and not introduced by this story's implementation pattern.
- Renamed locations with legacy `schedules.location` text values can evade in-use detection when `location_id` is null and text no longer exactly matches current `location_name`; resolving this requires broader data backfill/normalization beyond this story.

## Deferred from: code review of 15-3-bulk-game-import.md (2026-05-23)

- Full-table existing fixture preload in `validateRows()` may degrade performance at scale as historical schedules grow; optimization should narrow duplicate lookups to relevant fixture candidates.

## Deferred from: code review of 13-3-schedule-management-bugfixes.md (2026-05-26)

- Legacy rows with missing `original_date` / `original_time` / `original_location` may now display as "Not scheduled" in history and "Unknown" in notifications; this is a pre-existing data-quality gap surfaced by the new display logic.
- `deny_change` can still show a success message when `request_id` is invalid/nonexistent because the update path does not validate affected rows; this behavior predates story 13.3 changes.

## Deferred from: code review of spec-user-profile-edit-personal-info.md (2026-05-28)

- **ActivityLogger called inside transaction** — `updateContactInfo()` (and all existing service methods) call `ActivityLogger::log()` before `commit()`. If the logger writes to a secondary store outside the DB transaction, a rollback leaves a phantom audit record for a write that never landed. Pre-existing pattern across `updateName`, `updatePhone`, `changePassword`. A future hardening pass should ensure the logger only fires after a confirmed commit, or confirm it writes to the same connection/transaction.
- **ActivityLogger fires on no-op saves** — `updateContactInfo()` always logs `profile.contact_updated` even when the submitted values are identical to the current DB values. Low-priority audit noise; a pre-submission diff check would skip the UPDATE and log entry entirely for unchanged saves.

## Deferred from: code review of spec-reschedule-submission-windows.md (2026-05-30)

- **`getSetting()` performance in `getEligibleGames()`** — `getSetting('reschedule_pre_game_hours')`, `getSetting('reschedule_post_game_hours')`, and `getSetting('timezone')` are called once per `getEligibleGames()` invocation (not per game), but `getSetting()` issues a DB query every call with no caching. Pre-existing pattern across the app; a settings cache would benefit multiple call sites.
- **Invalid timezone string from admin settings could crash `DateTimeZone` constructor** — If an admin saves a nonsense timezone value, `new DateTimeZone(getSetting('timezone'))` throws, bubbling out as a generic error. Pre-existing risk throughout the app (timezone is used everywhere). Mitigation: validate the timezone on save in the system-timezone settings section.
- **Malformed `game_date` string (non-NULL, non-ISO) crashes `DateTime` constructor in `enforceSubmissionWindows()`** — Pre-existing data quality risk; a corrupt schedule row would cause an unhandled exception (caught by generic `Throwable` handler in the controller, so no crash, but error message is misleading). Fix: wrap `DateTime` construction in a try/catch and throw a `TeamScopeViolationException` with a clear message.

## Deferred from: code review of 17-1-mobile-responsive-ui.md (2026-05-31)

- **Admin tables outside story file list lack `.table-responsive` (strict AC7)** — User confirmed strict AC7 applies: `admin/games/index.php`, `admin/divisions/index.php`, `admin/users/invitations.php`, and `admin/settings/sections/*` tables still need wraps. Not fixed in review pass — follow-up before story 17-1 is done.

## Deferred from: code review of 18-1-coach-game-postponement.md (2026-05-31)

- Missing notification — sendNotification placeholder for Story 18-2. Intentionally deferred to follow-up story. [includes/RescheduleService.php]

## Deferred from: spec-score-edit-time-gate (2026-06-01)

- **No unit test coverage for `enforceEditWindow`** — `ScoreServiceTest.php` has no cases for NULL `score_submitted_at`, expired window, or within-window edits. Consider adding these as part of a test hardening pass.
- **Admin score edit resets `score_submitted_at`** — `public/admin/games/index.php` uses `date('Y-m-d H:i:s')` on every admin score save, which resets the column and unintentionally opens a new 24h coach edit window after an admin correction. Pre-existing admin behavior; out of scope for this story.
- **No remaining-time indicator in "Edit a Previous Score" UI** — Coaches see the section disappear without forewarning when the window closes. A "closes in X hours" label per game row would improve UX.

## Deferred from: code review of 18-2-postponement-cancellation-notifications (2026-06-01)

- **`EmailService::triggerNotification()` ignores `processQueuedEmail()` failure** — Always returns `true` after queue processing regardless of send result; pre-existing EmailService behavior.
- **No unit tests for 18-2 notification wiring** — RescheduleServiceTest stubs `sendNotification` without invoke/skip assertions; story did not require new tests.
- **`approve_change` duplicate-approval race** — No Pending-status guard on approve path; can produce duplicate history rows and duplicate emails; pre-existing schedules bug.
- **Whitespace-only postpone/cancel reason on admin games page** — sanitize() trims but does not reject empty; emails can show blank REASON; pre-existing validation gap.

## Deferred from: spec-scr-canned-reasons (2026-06-01)

- **`scrAddReason()` uses innerHTML with caller-supplied string** — `public/admin/settings/sections/schedule-changes.php`. Call sites are hardcoded string literals so there is no live XSS vector. Consider switching to `createElement`/`setAttribute` if the function is ever generalized.
- **`INSERT IGNORE` can't reset canned reason defaults** — `database/migrations/036_add_scr_canned_reasons.sql`. An admin who accidentally clears all reasons via the UI cannot recover defaults from a migration re-run. Could add a separate "reset to defaults" button in the settings UI if needed.
## Deferred from: code review (2026-06-07) story 21.3
- [Review][Defer] Missing mobile support for special dates [public/schedule.php] — deferred, pre-existing (mobile lacks calendar)

## Deferred from: code review of 20-3-admin-schedule-change-conflict-prompt.md (2026-06-09)
- Pre-existing tech debt: `getGameConflicts` doesn't use the new exclusion logic [includes/ConflictDetectionService.php:33] — deferred, pre-existing

## Deferred from: code review of spec-sms-consent-compliance (2026-06-09)

- **Consent audit trail is point-in-time, not event-based** — `users.sms_consent_at` is cleared on opt-out and `terms_accepted_at` is overwritten on each re-affirmation, so the grant/revoke history and original acceptance date are lost. For a stronger TCPA/A2P posture, consider a consent-events log table (or `sms_consent_revoked_at` + ToS version column). Current design matches the approved spec.
- **No registration-path consent test coverage** — `RegistrationService::register()` consent persistence (`sms_opt_in`, `sms_consent_at`, `terms_accepted_at`) and the `accept_terms` gate in `register.php` are untested; spec only required the ProfileService test.
- **Profile error re-render discards POSTed edits** — `public/coaches/profile.php` repopulates the form from the DB row on any validation error (incl. the new ToS gate), losing the user's typed email/phone/SMS toggle. Pre-existing page pattern; repopulating from `$_POST` on error would fix it.
- **Profile save is not atomic across services** — `updateContactInfo()` commits (incl. consent stamps) before `updateName()` runs; a name failure leaves contact/consent saved while the user sees an error. Pre-existing two-call structure in profile.php.

## Deferred from: code review 22.2 — Umpire Roster Management (2026-06-14)

### Medium Priority
- **Consider caching umpire role ID** — `UmpireRosterService.php:66-72` fetches `umpire` role on every `createUmpire()` call. For high-volume operations, consider caching this value.
- **Improve temporary password UX** — `public/admin/umpires/roster.php:48` flashes temp password in session (visible on browser back). Consider displaying in a modal immediately after creation, or generating a secure one-time download link.

### Low Priority
- **Add client-side validation for DOB requirement** — `public/admin/umpires/roster.php` has HTML `required` attributes but no JS validation feedback for DOB when under-18 checkbox is checked.
- **Consider making phone type/role configurable** — `UmpireRosterService.php:107-111` hardcodes `type='Cell'` and `role='primary'` in dual-write to `user_phones`. This assumes all umpires use cell phones.
