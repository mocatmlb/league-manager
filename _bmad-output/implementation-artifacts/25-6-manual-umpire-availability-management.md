# Story 25.6: Manual Umpire Availability Management

Status: done

<!-- Note: Created by bmad-create-story on 2026-06-28. Validated and improved by bmad-create-story checklist on 2026-06-28. -->

## Story

As an admin or umpire assignor,
I want to manually edit an umpire's availability,
so that I can keep availability accurate when umpires notify me verbally, by email, or by text.

## Acceptance Criteria

1. Given I am an authenticated admin or umpire assignor, when I open the manual availability management page, then I can select an active umpire and view that umpire's availability windows in chronological order.
2. Given I am managing an umpire's availability, when I add a window with a valid start date/time, end date/time, and optional note, then the window is stored against that umpire and appears in availability-pool calculations immediately.
3. Given I am managing an umpire's existing availability window, when I update or delete it, then only that umpire's selected window changes and the availability pool recalculates dynamically without mutating assignment rows.
4. Given I submit a blank, malformed, reversed, zero-length, or unauthorized window change, when the request is processed, then the change is rejected with a clear error and no availability data is written.
5. Given a coach, team owner, regular umpire, or unauthenticated user attempts to access the manual availability management page or submit a change, when authorization is checked, then they are denied access or redirected to login.
6. Given an admin or assignor is on the roster or umpire tools navigation, when they need to manage availability for an umpire, then there is a discoverable link in the admin mega-menu Umpires section, in the Umpire Tools dropdown, and as a per-umpire row action on the roster page — without replacing the umpire self-service My Availability route.

## Tasks / Subtasks

- [x] Create the admin/assignor management page without colliding with Story 25.3 (AC: 1, 5, 6)
  - [x] Create `public/admin/umpires/availability-management.php`. Do NOT use `public/admin/umpires/availability.php` — Story 25.3 reserves that path for the standalone pool query (that file does not exist yet; do not create it).
  - [x] Use `roster.php` (not `availability.php`) as the bootstrap template — the require block in `roster.php` has the correct two-level EnvLoader fallback path for this directory:
    ```php
    define('D8TL_APP', true);
    $envLoader = file_exists(__DIR__ . '/../../includes/env-loader.php')
        ? __DIR__ . '/../../includes/env-loader.php'
        : __DIR__ . '/../../../includes/env-loader.php';
    require_once $envLoader;
    require_once EnvLoader::getPath('includes/bootstrap.php');
    require_once EnvLoader::getPath('includes/PermissionGuard.php');
    require_once EnvLoader::getPath('includes/UmpireRosterService.php');
    require_once EnvLoader::getPath('includes/UmpireAvailabilityService.php');
    ```
    Copying the bootstrap from `availability.php` will cause a fatal include error due to wrong path depth.
  - [x] Protect with `PermissionGuard::requireRole('umpire_assignor', '/login.php')`. Admin satisfies `umpire_assignor` via the role hierarchy in `PermissionGuard::$ROLE_SATISFIES` — using just `'umpire_assignor'` is the correct codebase pattern (matches roster.php). Using `['admin', 'umpire_assignor']` also works but is redundant.
  - [x] Get the **acting admin/assignor** via `Auth::getCurrentUser()['id']` — NOT `$_SESSION['coach_user_id']`. That session key holds the umpire portal's authenticated user ID and must never be used on admin pages.
  - [x] Accept the **target umpire** via `GET ?umpire_user_id=123`. Validate server-side: call `UmpireRosterService::getUmpire((int)$_GET['umpire_user_id'])` and then assert `$umpire !== null && $umpire['status'] === 'active'`. A non-existent, non-active, or non-umpire target renders an inline error — it does not redirect.
  - [x] Load active umpires for the selector via `UmpireRosterService::getRoster(true)`, sorted alphabetically by last name, rendered as a `<select>` dropdown.
  - [x] List the selected umpire's windows via `UmpireAvailabilityService::listForUmpire($targetUmpireId)` in chronological order.
  - [x] CSS: `../../assets/css/style.css` (same relative depth as roster.php). Bootstrap CDN: `bootstrap@5.1.3` (exact version used by roster.php and availability.php — do not use 5.3).
  - [x] Use `isActiveNav('availability-management', 'umpires')` for the active nav state.

- [x] Implement manual create/update/delete flows (AC: 2, 3, 4, 5)
  - [x] Use POST for all mutations. Verify `Auth::verifyCSRFToken($_POST['csrf_token'] ?? '')` before any mutation. The architecture document shows `CsrfProtection::validateToken()` — that is NOT what the codebase uses; use `Auth::verifyCSRFToken()` (see roster.php line 32 and availability.php line 32 for the canonical pattern).
  - [x] Include `umpire_user_id` as a hidden field in every create, update, and delete form so it survives the POST.
  - [x] After any successful mutation, redirect to `availability-management.php?umpire_user_id={validatedId}` — not bare `availability-management.php`. The target umpire ID must be re-validated before the redirect destination is constructed.
  - [x] Normalize `datetime-local` inputs from `YYYY-MM-DDTHH:mm` to `YYYY-MM-DD HH:mm:ss` before passing to the service.
  - [x] Reject: missing or invalid target umpire id, inactive target umpire, unsupported action, blank datetimes, malformed datetimes, `starts_at >= ends_at`.
  - [x] For update/delete: call `UmpireAvailabilityService::updateWindow()` or `deleteWindow()` passing the **target umpire's user ID** (not the acting admin's ID) as `$umpireUserId`. The service `validateOwnership()` method checks `WHERE availability_id = :id AND umpire_user_id = :umpire_user_id` — passing the admin's ID here will cause all mutations to fail silently with "not found."
  - [x] Flash messages: use the **admin pattern** `$_SESSION['flash_message']` for success and `$_SESSION['flash_error']` for error. Do NOT use `$_SESSION['flash_success']` — that is the umpire portal pattern and will not display on admin pages.
  - [x] Keep notes optional. Notes go in the shared `umpire_availability_windows.notes` column — umpires can see them via their own portal (`availability.php` renders notes to the umpire). Store only operational context that is appropriate for the umpire to read, such as "call confirming 6/28 window." Do not store internal notes not intended for the umpire.
  - [x] Escape all rendered values with `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`.

- [x] Extend audit logging for admin-originated actions (AC: 2, 3)
  - [x] The existing service logs only `umpire_user_id` and `availability_id`, making admin edits indistinguishable from umpire self-service. Add optional `$actorUserId` and `$source` parameters to `createWindow()`, `updateWindow()`, and `deleteWindow()` (default to `null` / `'self'` for backward compat with the umpire portal path).
  - [x] Pass `$actorUserId = Auth::getCurrentUser()['id']` and `$source = 'admin_manual'` when calling from the admin page.
  - [x] Include `actor_user_id` and `source` in the ActivityLogger payload alongside existing fields. Do not log notes text in ActivityLogger.
  - [x] Do not alter the public `getAvailableUmpireIdsForWindow(DateTimeInterface $startsAt, DateTimeInterface $endsAt): int[]` contract.

- [x] Add discoverability from existing umpire admin surfaces (AC: 1, 6)
  - [x] **Admin mega-menu** (`includes/nav.php`, admin Umpires section, lines ~122-129): Add "Manage Availability" after the "Umpire Roster" link and before "Import Umpires":
    ```php
    <a class="dropdown-item <?php echo isActiveNav('availability-management', 'umpires'); ?>"
       href="<?php echo $rootPath; ?>admin/umpires/availability-management.php">
        <i class="fas fa-calendar-check"></i> Manage Availability
    </a>
    ```
  - [x] **Umpire Tools dropdown** (`includes/nav.php`, `$isUmpireAssignor` block, lines ~276+): Add the same link after "Umpire Roster" and before any subsequent items. Both blocks are ~120 lines apart — update both. Missing either block is a regression.
  - [x] **Roster per-umpire row action** (`public/admin/umpires/roster.php`): Add an anchor link in the Actions column **for active umpires only** (`$umpire['status'] === 'active'`). Use an anchor, not a button-with-JS-onclick:
    ```php
    <a href="availability-management.php?umpire_user_id=<?= (int)$umpire['id'] ?>"
       class="btn btn-outline-info btn-sm" title="Manage Availability">
        <i class="fas fa-calendar-check"></i> Availability
    </a>
    ```
    Do not render this link for inactive umpires — they are excluded from pools and the page will show a validation error.
  - [x] Keep `public/umpires/availability.php` and its "My Availability" nav link for regular umpires entirely unchanged.

- [x] Preserve availability, assignment, and adjacent story boundaries (AC: 2, 3, 6)
  - [x] Do not change assignment drawer pool/default behavior in `public/admin/umpires/assignment-drawer.js`; Story 25.2 owns that surface.
  - [x] Do not create or rename `public/admin/umpires/availability.php`; Story 25.3 reserves that path for the standalone pool query.
  - [x] Do not add third-party game creation, third-party assignment display, or third-party conflict expansion; Stories 25.4 and 25.5 own that scope.
  - [x] Do not mutate `game_umpire_assignments` when availability windows change. FR-28 requires dynamic recalculation from current availability plus assignments.

- [x] Add targeted tests and verification (AC: 1, 2, 3, 4, 5, 6)
  - [x] Extend `tests/unit/UmpireAvailabilityServiceTest.php` to cover the new optional `$actorUserId` / `$source` parameters: verify existing create/update/delete and pool tests still pass with defaults, and new param values appear in ActivityLogger payload.
  - [x] Add negative-path coverage: invalid target umpire id, inactive target umpire, invalid CSRF, unsupported action, reversed times, tampered availability id.
  - [x] Before modifying nav.php, check `tests/unit/NavCssTest.php` (currently untracked) — it contains nav/CSS assertions that may provide a test pattern to extend for availability-management nav assertions.
  - [x] Syntax-check all modified files:
    ```
    php -l includes/UmpireAvailabilityService.php
    php -l public/admin/umpires/availability-management.php
    php -l public/admin/umpires/roster.php
    php -l includes/nav.php
    ```
  - [x] Run: `php tests/unit/run-unit-tests.php --file=UmpireAvailabilityServiceTest.php`
  - [x] If browser verification is available, smoke as admin/assignor: select an umpire, add a window, edit it, delete it, submit invalid times, verify coach/regular-umpire access is denied, and confirm the flash message and redirect URL include the umpire_user_id param.

## Dev Notes

Epic 25 is the P1 availability and third-party events track. Stories 25.1 and 25.2 are implemented. Stories 25.3–25.5 are ready-for-dev (not yet implemented). This story extends availability-management capability for adminoriginated edits — it does not replace umpire self-service.

### Route Reference Table

| Path | Owner | Role guard |
|------|-------|-----------|
| `public/umpires/availability.php` | Story 25.1 (done) | `PermissionGuard::requireRole('umpire')` |
| `public/admin/umpires/availability-management.php` | **This story** | `PermissionGuard::requireRole('umpire_assignor')` |
| `public/admin/umpires/availability.php` | Story 25.3 (ready-for-dev, file does not exist yet) | Reserved — do not create |

### Current System Context

- `database/migrations/051_create_umpire_availability_windows.sql` creates `umpire_availability_windows` with `availability_id`, `umpire_user_id`, `starts_at`, `ends_at`, optional `notes`, timestamps, FK to `users(id)`, and lookup indexes.
- `includes/UmpireAvailabilityService.php` provides `listForUmpire()`, `createWindow()`, `updateWindow()`, `deleteWindow()`, strict datetime validation, ownership checks, ActivityLogger events, and `getAvailableUmpireIdsForWindow()`. Reuse it — do not put raw SQL in the page.
- `public/umpires/availability.php` gives regular umpires self-service CRUD using `$_SESSION['coach_user_id']` as the acting umpire id. This is the **umpire portal** session key — it must not be used on admin pages.
- Admin pages in `public/admin/umpires/` use `Auth::getCurrentUser()` for the acting user and guard with `PermissionGuard::requireRole('umpire_assignor', '/login.php')`.
- `public/admin/umpires/roster.php` lists umpires and handles CRUD via `UmpireRosterService`; it is the correct bootstrap template for new admin umpire pages.
- `includes/nav.php` has two admin/assignor umpire nav surfaces: the admin Manage > Umpires section (col-md-3, lines ~109-130) and the `$isUmpireAssignor` Umpire Tools dropdown (lines ~249-290+). Both must be updated.

### Availability Service Guardrails

- `updateWindow()` and `deleteWindow()` enforce ownership via `WHERE availability_id = :id AND umpire_user_id = :umpire_user_id`. In the admin context, pass the **target umpire's user ID** as `$umpireUserId`, not the acting admin's ID. Passing the admin's ID silently returns "not found."
- `getAvailableUmpireIdsForWindow()` is a stable `int[]` contract consumed by Stories 25.2 and 25.3. Do not change the return shape.
- The pool query inlines `$windowSeconds` via PHP string concatenation — that value is a programmatic config (not user input) so it is not a vulnerability. Do not replicate that pattern for any user-supplied values.
- Current conflict statuses that block pool membership: `Draft` and `Published`. `Open`, `Declined`, and `Cancelled` must not block pool membership.
- Availability windows are stored as local league `DATETIME`. Do not introduce timezone conversion or UTC storage.

### Audit and Notes

- Extend service methods with optional `$actorUserId` (int|null) and `$source` (string, default `'self'`) parameters. Pass `Auth::getCurrentUser()['id']` and `'admin_manual'` from the admin page. Default `null` / `'self'` preserves umpire portal behavior.
- The `notes` field is shared with the umpire self-service page — umpires can see it in their portal. Store only notes that are appropriate for the umpire to read. Do not log notes text in ActivityLogger.

### UI / UX Guardrails

- First screen: compact umpire selector and the selected umpire's current windows. No landing page or decorative hero.
- Bootstrap 5.1.3 (CDN pin from roster.php). Admin page card/table pattern — do not nest cards inside cards.
- Umpire selector: `<select>` populated from `UmpireRosterService::getRoster(true)`, sorted alphabetically by last name. If roster regularly exceeds 50 umpires, consider a client-side search filter or `<datalist>`.
- Display selected umpire's name and identifying info (email/phone from roster data) so the assignor knows whose availability is being changed.
- Use accessible labels for date/time fields and icon buttons. Icon-only buttons require `title` and accessible text.

### Security / Validation

- Only `admin` and `umpire_assignor` can access or mutate this page. Regular `umpire` users use `/umpires/availability.php`.
- All mutations require POST plus CSRF. Use `Auth::verifyCSRFToken($_POST['csrf_token'] ?? '')` — the architecture doc shows `CsrfProtection::validateToken()` which is NOT used in this codebase.
- Validate target umpire server-side on every GET and POST: `UmpireRosterService::getUmpire()` + `$umpire['status'] === 'active'`. Reject inactive umpires — they are excluded from pools and inactive umpires can't be managed.
- Escape all rendered values with `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`.
- Use `Database::getInstance()` through existing services. Do not introduce MySQLi or raw PDO.

### Previous Story Intelligence

- **Story 25.1** created and hardened the availability table/service/self-service page. Key review findings directly applicable here:
  - [Patch] FK signedness: `umpire_user_id` must match the signedness of `users.id`.
  - [Patch] Wrong schema references: `game_date_time` does not exist; use `game_date` and `game_time` from `schedules`.
  - [Patch] Missing update/edit POST path: the umpire portal initially had no edit control or update POST handler despite AC-3 requiring updated windows. This page must implement all three: create, update, and delete POST handlers.
  - [Patch] Unsupported action handling: unrecognized `$_POST['action']` values must be rejected, not silently ignored.
  - [Patch] Strict datetime parsing: `starts_at >= ends_at` must be rejected.
- **Story 25.2** consumed `getAvailableUmpireIdsForWindow()` for drawer pool mode and hardened Draft/Published exclusion and Cancelled/Declined/Open non-exclusion. Do not change pool exclusion logic.
- **Story 25.3** is ready-for-dev — the standalone read-only pool query file at `public/admin/umpires/availability.php` does NOT exist yet. Do not create or reserve that path.
- **Story 25.5** is ready-for-dev — do not expand availability pool SQL to third-party assignment targets.

### Project Structure Notes

New file:
- `public/admin/umpires/availability-management.php`

Existing files to update:
- `includes/nav.php` — two locations: admin mega-menu Umpires section AND `$isUmpireAssignor` Umpire Tools dropdown
- `public/admin/umpires/roster.php` — per-umpire Availability row action (active umpires only)
- `includes/UmpireAvailabilityService.php` — optional `$actorUserId` / `$source` audit parameters on create/update/delete
- `tests/unit/UmpireAvailabilityServiceTest.php` — extend for new audit params; check `tests/unit/NavCssTest.php` for nav assertion patterns

Files to avoid changing:
- `public/admin/umpires/availability.php` (reserved for Story 25.3 — do not create)
- `public/umpires/availability.php` (umpire self-service — preserve entirely)
- `public/admin/umpires/assignment-drawer.js` (Story 25.2 surface)
- `includes/UmpireAssignmentService.php` (no nav/roster integration requires this)
- Third-party game and conflict-checker files (Stories 25.4/25.5 scope)

### Recent Git Intelligence

Recent commits on `staging`: Story 25.2 review fixes (`48bc703`), Story 25.2 implementation (`c46c8b8`), Story 25.1 migration bookkeeping (`3292bae`), Story 25.1 review fixes (`d3c4b17`), Story 25.1 implementation (`494678a`). Pattern: story-scoped service/page changes with targeted unit coverage and explicit separation between baseline failures and story regressions.

### Latest Technical Notes

No new external libraries required. Stack: PHP 8.1, PDO through `Database::getInstance()`, Bootstrap 5.1.3, Font Awesome, jQuery/vanilla JS, no Node bundler.

### References

- `_bmad-output/project-context.md` — PHP 8.1, PDO, Bootstrap/jQuery, role/security, and testing rules
- `_bmad-output/planning-artifacts/epics-umpire-assignment.md` — Epic 25 availability story sequence and adjacent-story boundaries
- `_bmad-output/planning-artifacts/prd-umpire-assignment.md` — FR-26 through FR-30 availability requirements and Journey 7 availability management
- `_bmad-output/planning-artifacts/architecture-umpire-assignment.md` — route protection, service class, PDO, CSRF, and admin/umpire URL patterns
- `_bmad-output/implementation-artifacts/25-1-umpire-availability-entry.md` — implemented service/schema/self-service contract and review learnings (FK signedness, missing edit POST handler, unsupported action handling)
- `_bmad-output/implementation-artifacts/25-2-availability-pool-in-assignment-drawer.md` — pool consumption and service-contract review learnings
- `_bmad-output/planning-artifacts/epics-umpire-assignment.md` (Epic 25, Story 25.3 entry) — Story 25.3 boundaries (implementation artifact does not exist yet)
- `database/migrations/051_create_umpire_availability_windows.sql`
- `includes/UmpireAvailabilityService.php`
- `public/umpires/availability.php` (umpire self-service — reference only, do not modify)
- `public/admin/umpires/roster.php` (bootstrap template for new page)
- `includes/UmpireRosterService.php`
- `includes/nav.php`
- `tests/unit/UmpireAvailabilityServiceTest.php`
- `tests/unit/NavCssTest.php` (untracked — check for nav assertion patterns before adding nav tests)

## Dev Agent Record

### Agent Model Used

GPT-5 Codex

### Debug Log References

- 2026-06-28: Red phase confirmed with `php tests/unit/run-unit-tests.php --file=UmpireAvailabilityServiceTest.php`; new Story 25.6 tests failed for missing audit actor/source support and missing `public/admin/umpires/availability-management.php`.
- 2026-06-28: Syntax checks passed:
  - `php -l includes/UmpireAvailabilityService.php`
  - `php -l public/admin/umpires/availability-management.php`
  - `php -l public/admin/umpires/roster.php`
  - `php -l includes/nav.php`
- 2026-06-28: Targeted unit checks passed: `php tests/unit/run-unit-tests.php --file=UmpireAvailabilityServiceTest.php` (16 passed, 0 failed).
- 2026-06-28: Full unit suite run completed with existing unrelated baseline failures: `php tests/unit/run-unit-tests.php` (410 passed, 38 failed). Story 25.6 tests passed inside the full suite.

### Completion Notes List

- Ultimate context engine analysis completed - comprehensive developer guide created.
- Checklist validation applied: 11 critical issues, 10 enhancements, 4 optimizations resolved.
- Implemented `public/admin/umpires/availability-management.php` for admin/assignor manual availability management with active-umpire selection, chronological window display, POST-only create/update/delete, CSRF validation, datetime normalization, target re-validation, admin flash messages, and redirect preservation of `umpire_user_id`.
- Extended `UmpireAvailabilityService` create/update/delete methods with backward-compatible optional `$actorUserId` and `$source` parameters and ActivityLogger payload fields, without changing `getAvailableUmpireIdsForWindow()`.
- Added discoverability in both admin/assignor nav surfaces and active-only roster row actions while leaving umpire self-service and reserved Story 25.3 route untouched.
- Added targeted unit coverage for audit payloads, route/nav/roster contracts, reserved-route preservation, and existing availability service regressions.

### File List

- includes/UmpireAvailabilityService.php
- includes/nav.php
- public/admin/umpires/availability-management.php
- public/admin/umpires/roster.php
- tests/unit/UmpireAvailabilityServiceTest.php
- _bmad-output/implementation-artifacts/25-6-manual-umpire-availability-management.md
- _bmad-output/implementation-artifacts/sprint-status.yaml

### Review Findings

- [x] [Review][Patch] GET umpire_user_id overrides POST umpire_user_id — crafted URL can target wrong umpire [availability-management.php:76]
- [x] [Review][Patch] csrf_token array bypass — $_POST['csrf_token'] could be array, bypassing verifyCSRFToken [availability-management.php:82]
- [x] [Review][Patch] source param is unvalidated free-text — no allowlist; arbitrary strings written to audit log [UmpireAvailabilityService.php:47]
- [x] [Review][Patch] listForUmpire GET path has no exception handler — RuntimeException surfaces as fatal 500 [availability-management.php:130-139]
- [x] [Review][Patch] RuntimeException messages forwarded verbatim to flash — internal details may leak to user [availability-management.php:118-121]
- [x] [Review][Patch] Notes input has no server-side length cap — POST bypass writes unbounded text to DB [availability-management.php:92]
- [x] [Review][Patch] formatForDatetimeLocal only handles 'Y-m-d H:i:s' — edit modal shows blank if DB row lacks seconds [availability-management.php:34-45]
- [x] [Review][Patch] parseLocalDateTime missing 'Y-m-d\TH:i:s' — ISO 8601 with seconds fails silently [UmpireAvailabilityService.php:273]
- [x] [Review][Patch] PHPDoc not updated for $actorUserId/$source params on createWindow/updateWindow/deleteWindow [UmpireAvailabilityService.php]
- [x] [Review][Defer] CDN links without SRI attributes — deferred, pre-existing pattern in codebase [availability-management.php:149-150]
- [x] [Review][Defer] Nav items visible to all admin roles regardless of umpire_assignor — deferred, existing nav behavior pattern [nav.php:123]
- [x] [Review][Defer] Service-level umpireUserId < 1 not validated — deferred, page validates before calling service [UmpireAvailabilityService.php]
- [x] [Review][Defer] TOCTOU: row deleted between validateOwnership and update/delete — deferred, negligible risk, silent no-op [UmpireAvailabilityService.php]
- [x] [Review][Defer] createWindowsForDates internal createWindow calls omit actorUserId/source — deferred, admin page does not call this method [UmpireAvailabilityService.php:122]
- [x] [Review][Defer] normalizeDatetimeLocal duplicates service parsing logic — deferred, maintenance risk only, no current bug [availability-management.php:19]

### Change Log

- 2026-06-28: Implemented Story 25.6 manual umpire availability management and moved story to review.
