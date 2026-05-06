# Story 3.6: Registration Toggle & QR Code Display

**Status:** review
**Epic:** 3 — Coach Registration & Authentication
**Story Key:** 3-6-registration-toggle-qr-code-display

---

## Story

As an admin,
I want to enable or disable open coach self-registration from the Settings panel, with a QR code displayed when enabled,
So that I control when coaches can self-register.

---

## Acceptance Criteria

**AC1: Settings panel shows registration toggle with QR code when enabled**
**Given** an admin navigates to `admin/settings/index.php`
**When** the page loads
**Then** a "Coach Self-Registration" section is visible with a toggle (enabled/disabled)
**And** when **enabled**, the registration URL and a QR code image for that URL are displayed for the admin to share
**And** when **disabled**, only the toggle is shown with status "Registration is currently closed"

**AC2: Toggling saves setting and takes immediate effect**
**Given** the admin changes the toggle and saves
**When** the POST is processed (PRG pattern)
**Then** the `settings` table is updated with no server restart required
**And** the change takes effect within one page load (FR-TOGGLE-3)
**And** `ActivityLogger` event `admin.registration_toggle_changed` is recorded with the new state

**AC3: Disabling registration closes the registration URL**
**Given** open registration is toggled off
**When** a coach visits the self-registration URL directly
**Then** they see the "Registration is currently closed" message

**AC4: Existing invitation links still work when registration is disabled**
**Given** open registration is toggled off
**When** a coach with a valid, unexpired invitation link clicks it
**Then** the registration form is shown (invitation token bypasses the open/closed check) (FR-TOGGLE-4)

**AC5: Registration URL is not discoverable**
**Given** open registration is enabled
**When** the public site navigation and site metadata are inspected
**Then** the registration URL is not linked in public navigation or indexed by search engines (NFR-SEC-6)

---

## Tasks / Subtasks

- [x] **Task 1: Add "Coach Self-Registration" section to `public/admin/settings/index.php`**
  - [x] Read current `open_registration` flag from `settings` table (key: e.g., `open_registration`)
  - [x] Render toggle control (Bootstrap toggle switch or enabled/disabled radio buttons)
  - [x] When enabled: display the full registration URL and a QR code image for that URL
  - [x] When disabled: display status text "Registration is currently closed"
  - [x] Include CSRF token; PRG pattern on POST
  - [x] Handle POST: update `settings` record, log `admin.registration_toggle_changed` with new state value, flash success

- [x] **Task 2: Generate QR code for registration URL**
  - [x] Check if a QR code library is already in the project (`composer.json`) — e.g., `endroid/qr-code` or similar
  - [x] If available: generate QR code image as data URI or file served from `public/`
  - [x] If no library available: use a third-party QR code generation API URL (e.g., Google Charts QR API) rendered as `<img src="https://api.qrserver.com/v1/create-qr-code/?data={url}&size=200x200">` — acceptable for admin-only display
  - [x] QR code image should display the full `public/coaches/register.php` URL

- [x] **Task 3: Ensure registration URL is not indexed (NFR-SEC-6)**
  - [x] Verify `public/coaches/register.php` is excluded from `robots.txt` (add `Disallow: /coaches/register.php` if not present)
  - [x] Verify the URL is not linked in any public-facing nav or page
  - [x] Add `<meta name="robots" content="noindex">` to `register.php` head

- [x] **Task 4: Verify end-to-end toggle behavior**
  - [x] Toggle on → registration URL shows form → toggle off → URL shows "closed" message
  - [x] Invitation link bypasses closed check (integration check with Story 3.3 token path)

---

## Dev Notes

### Architecture Context
- `public/admin/settings/index.php` already exists — add section, do not recreate
- Settings stored in `settings` table (key-value pairs) — follow existing pattern for reading/writing settings
- `PermissionGuard::requireRole('administrator')` already enforced on this page — no change needed

### Settings Table Key
- Check existing `settings` table structure in `database/user_accounts_schema.sql` or `includes/`
- Expected key: `open_registration` with value `1` (enabled) or `0` (disabled)
- If key doesn't exist yet: INSERT on first save; use `INSERT ... ON DUPLICATE KEY UPDATE` or `REPLACE INTO`

### QR Code
- Admin-only display — a simple `<img>` using a public QR API is acceptable
- Preferred: if `endroid/qr-code` or similar is available in `composer.json`, use it for offline generation
- Registration URL to encode: `https://{site_domain}/coaches/register.php`

### No Server Restart Requirement (FR-TOGGLE-3)
- Reading the `settings` table on each page load of `register.php` satisfies this — no caching layer involved on shared hosting

### ActivityLogger Event
- `admin.registration_toggle_changed` with context: `['new_state' => 'enabled'|'disabled', 'admin_user_id' => $adminUserId]`

---

## Dev Agent Record

### Implementation Plan
- Add open-registration toggle handling at settings controller level and surface controls in coach settings section.
- Display registration URL and generated QR image only when toggle is enabled.
- Apply no-index controls by both `robots.txt` disallow and explicit page-level robots meta on registration page.
- Confirm registration open/closed behavior is controlled by settings read on each registration page load.

### Debug Log
- Updated `public/admin/settings/index.php` with `update_open_registration` action handling and activity log event.
- Updated `public/admin/settings/sections/users-coach.php` with registration toggle, URL display, and QR code.
- Added `public/robots.txt` with register-page disallow.
- Updated `public/coaches/register.php` with `<meta name="robots" content="noindex">` and open/closed gate behavior.

### Completion Notes
- Implemented admin-controlled open registration toggle with immediate effect and PRG/CSRF-safe POST handling.
- Added admin-facing registration URL + QR code display when self-registration is enabled.
- Added registration-page crawl prevention (`robots.txt` disallow + page-level `noindex`).
- Verified invitation-token path still bypasses closed registration check in register flow.

---

## File List

- `public/admin/settings/index.php` — modify (add registration toggle section with QR code display)
- `public/admin/settings/sections/users-coach.php` — modify (render registration toggle controls/QR)
- `public/coaches/register.php` — modify (open/closed gate + noindex meta)
- `public/robots.txt` — new (add Disallow for register.php)
- `_bmad-output/implementation-artifacts/3-6-registration-toggle-qr-code-display.md` — updated (status/tasks/dev record)

---

## Change Log
- 2026-05-05: Story file created, status set to ready.
- 2026-05-06: Implemented registration toggle + QR controls, noindex controls, and moved story to review.
- 2026-05-06: Code review run (bmad-code-review, full mode); findings appended; status remains review pending fixes.

---

## Review Findings

### Decisions resolved (2026-05-06)

- **QR code provider:** Keep `api.qrserver.com` for MVP. Admin-only display; registration URL leaks to qrserver logs but never to the public; spec dev notes already mark this approach acceptable. Revisit when/if leak becomes a real concern. (No patch.)

### Patch

- [ ] [Review][Patch] **Registration URL hard-codes `/public/coaches/register.php` prefix** [`public/admin/settings/sections/users-coach.php:259`] — Production docroot IS `public/`, so the right URL is `https://site/coaches/register.php`. Currently the QR points to a 404 in production. Use `EnvLoader::isProduction() ? '/coaches/register.php' : '/public/coaches/register.php'` (or normalize via APP_URL).
- [ ] [Review][Patch] **`ActivityLogger::log('admin.registration_toggle_changed', …)` called directly from page file** [`public/admin/settings/index.php:237-240`] — Violates the documented "service classes only" contract. Move into a settings service helper (or a documented allow-listed wrapper).

### Deferred

- [x] [Review][Defer] **`robots.txt` only disallows production path `/coaches/register.php` but not the dev path `/public/coaches/register.php`** — deferred; dev environments shouldn't be crawled regardless, and a dev-only crawl exposure is low-impact.
