# Story 3.2: Coach Registration Page (Open Self-Registration)

**Status:** review
**Epic:** 3 — Coach Registration & Authentication
**Story Key:** 3-2-coach-registration-page-open-self-registration
**Dependency:** Requires Story 2.1 (LeagueListManager Service) to be complete before implementation — the league dropdown is populated from `LeagueListManager::getActiveList()`

---

## Story

As a coach,
I want to create an individual account by filling out a registration form at the QR code URL,
So that I have a personal login for the league portal.

---

## Acceptance Criteria

**AC1: Open registration shows form with all fields**
**Given** open registration is **enabled** in `settings` and a coach visits `public/coaches/register.php`
**When** the page loads
**Then** the registration form is displayed with fields: first name, last name, preferred name (optional), email, primary phone + type, secondary phone + type (optional), league dropdown (from `LeagueListManager::getActiveList()` + static "Other" option), username, password, confirm password, and reCAPTCHA v2 widget
**And** a "Step 1 of 2: Create Your Account" progress indicator (`.reg-progress`) is shown above the form
**And** the form has a CSRF token field

**AC2: Closed registration shows message, no form**
**Given** open registration is **disabled** in `settings`
**When** a coach visits `public/coaches/register.php` without a valid invitation token
**Then** a "Registration is currently closed" message is shown and no form is rendered

**AC3: "Other" league selection reveals free-text input**
**Given** the coach selects "Other" from the league dropdown
**When** the JS runs (`coaches-registration.js`)
**Then** a free-text "Enter your league name" input is revealed inline
**And** when a named league is re-selected, the free-text input is hidden

**AC4: Successful submission creates unverified account**
**Given** the coach submits the form with all required fields valid and CAPTCHA passed
**When** the POST is processed (PRG pattern)
**Then** `RegistrationService::register()` is called and the account is created with `status = 'unverified'`
**And** the coach is redirected to a "Check your email" confirmation page (`verify-email.php`)
**And** no session is created (coach is not logged in yet)

**AC5: Duplicate username shows inline error**
**Given** the coach submits with a duplicate username
**When** the POST is processed
**Then** the form re-renders with an inline error on the username field: "This username is already taken"
**And** all other field values are preserved

**AC6: Weak password shows inline error**
**Given** the coach submits with a password that fails complexity rules (FR-REG-5)
**When** the POST is processed
**Then** the form re-renders with an inline error on the password field naming the specific rule violated
**And** the password and confirm password fields are cleared; all other values preserved

**AC7: Failed CAPTCHA rejects submission**
**Given** the CAPTCHA challenge is failed
**When** the POST is processed
**Then** the form is rejected before account creation with an inline error: "Please complete the CAPTCHA"

**AC8: Accessibility baseline met**
**Given** the form is rendered
**Then** all inputs have explicit `<label for="">` (no placeholder-only labels)
**And** error spans are linked via `aria-describedby`
**And** alert regions use `role="alert"` or `aria-live="polite"`
**And** page `<title>` includes "— District 8 Travel League" suffix
**And** all coach-facing inputs use `form-control-lg` (48px height)
**And** all primary action buttons use `btn-lg` (44px+ tap target) (UX-DR19)

---

## Tasks / Subtasks

- [x] **Task 1: Implement `public/coaches/register.php`**
  - [x] Check `settings` for open registration flag; show "closed" message if disabled and no invitation token present
  - [x] Load league list via `LeagueListManager::getActiveList()` for dropdown, append static "Other" option
  - [x] Render registration form with all required fields, CSRF token, reCAPTCHA v2 widget (always shown on registration)
  - [x] Render `.reg-progress` component (Step 1 of 2)
  - [x] Handle POST: validate CSRF, validate CAPTCHA via Google reCAPTCHA v2 API, validate inputs
  - [x] Call `RegistrationService::register()` on valid submission
  - [x] PRG redirect to `verify-email.php` on success; no session created
  - [x] On `DuplicateUsernameException`: re-render form with inline error, preserve all other values
  - [x] On `DuplicateEmailException`: re-render form with inline error, preserve all other values
  - [x] On password complexity failure: re-render form, clear only password fields, show specific rule violated

- [x] **Task 2: Implement `public/coaches/verify-email.php`**
  - [x] Show "Check your email" confirmation page after registration
  - [x] Handle verification token from email link: call `RegistrationService::verifyEmail()`
  - [x] On success: show "Email verified — your account is active" message, link to login
  - [x] On `ExpiredTokenException`: show "Link expired" with option to resend (form/button calling `resendVerification()`)

- [x] **Task 3: Implement "Other" reveal in `public/assets/js/coaches-registration.js`**
  - [x] Create `coaches-registration.js` (initial version)
  - [x] On league dropdown `change`: if value is "other", reveal free-text input; else hide it
  - [x] Free-text input must be required when "Other" is selected (toggle `required` attribute)

- [x] **Task 4: Add CSS classes to `assets/css/style.css`**
  - [x] `.reg-progress` — two-step progress tracker: step circles, labels, connector line, Bootstrap `progress` bar (UX-DR4)
  - [x] `.status-unverified` — amber `#ffc107` (UX-DR15)
  - [x] `.status-team-pending` — orange `#fd7e14` (UX-DR15)
  - [x] `.status-team-owner` — green `#28a745` (UX-DR15)

- [x] **Task 5: Verify accessibility requirements (UX-DR19)**
  - [x] All inputs have `<label for="">` matching input `id`
  - [x] Error spans use `aria-describedby`
  - [x] Alert regions use `role="alert"` or `aria-live="polite"`
  - [x] Page `<title>` ends with "— District 8 Travel League"
  - [x] All inputs use `form-control-lg`; primary button uses `btn-lg`

---

## Dev Notes

### Architecture Context
- Entry point in `public/coaches/` — must include bootstrap and define `D8TL_APP`
- `PermissionGuard` NOT required here — this is a public-facing page (unauthenticated)
- PRG pattern: POST handler redirects on success; re-renders form on failure
- Flash messages via `$_SESSION['flash_error']` / `$_SESSION['flash_success']`; read-and-clear on render (AR-10)
- CSRF token: generate on GET, validate on POST using existing session-based CSRF pattern in `includes/`

### reCAPTCHA v2 (AR-8)
- Always shown on registration form (unlike login page where it's revealed progressively)
- Site key from `config.prod.php` constant
- Server-side validation: POST to `https://www.google.com/recaptcha/api/siteverify`
- Fail-open if Google endpoint is unreachable (AR-8)

### Settings Check
- Check `settings` table for `open_registration` flag (or equivalent key) before rendering form
- If `open_registration = 0` AND no valid invitation token in URL query string → show closed message

### Invitation Token Path
- If a valid `?token=...` is present in the URL, bypass the open/closed check and show the form
- Pre-fill email as read-only from the invitation record
- Token validation via `InvitationService::validate()` (Story 3.3 — implement after 3.3 is done, or handle gracefully if not yet available)

### JS File Note
- `coaches-registration.js` will be extended in Story 4.2 for the home field repeater and in Story 3.4 for login CAPTCHA reveal — do NOT implement those features here

### UX-DR4: Registration Progress Indicator
- Step 1 (this story): step 1 circle active, connector line, step 2 circle inactive; Bootstrap progress bar at 50%
- Step 2 (Story 4.2): step 1 circle becomes checkmark, bar at 100%

### Mobile Compatibility (NFR-COMPAT-1)
- All form inputs must work on viewports ≥ 375px width
- Use Bootstrap 5 grid/responsive classes throughout

---

## Dev Agent Record

### Implementation Plan
- Implement registration and verification pages with PRG, CSRF, CAPTCHA validation, and RegistrationService integration.
- Add invitation-token aware flow hooks so Story 3.3 can prefill read-only email and bypass closed registration.
- Add shared coach registration JS for "Other" league reveal and keep room for login CAPTCHA reveal extension.
- Add registration progress/status styling in `assets/css/style.css`.

### Debug Log
- Added `public/coaches/register.php`.
- Added `public/coaches/verify-email.php`.
- Added `public/assets/js/coaches-registration.js`.
- Updated `assets/css/style.css` with registration progress + status badges.
- Ran `php tests/unit/run-unit-tests.php` successfully (full suite passes).

### Completion Notes
- Implemented open self-registration form with required fields, CSRF, reCAPTCHA handling, league list + "Other" reveal, inline validation, and PRG redirect on success.
- Implemented verification page with success flow, invalid/expired token handling, and resend trigger path.
- Preserved field values on validation errors while clearing password fields on password validation failures.
- Applied UX-DR19-related form sizing, button sizing, title suffix, labels, and accessible error/alert regions.

---

## File List

- `public/coaches/register.php` — new
- `public/coaches/verify-email.php` — new
- `public/assets/js/coaches-registration.js` — new (initial version: "Other" reveal only)
- `assets/css/style.css` — modify (add `.reg-progress`, `.status-unverified`, `.status-team-pending`, `.status-team-owner`)
- `_bmad-output/implementation-artifacts/3-2-coach-registration-page-open-self-registration.md` — updated (status/tasks/dev record)

---

## Change Log
- 2026-05-05: Story file created, status set to ready.
- 2026-05-06: Implemented registration + verification pages, JS/CSS updates, and moved story to review.
- 2026-05-06: Code review run (bmad-code-review, full mode); findings appended; status remains review pending fixes.

---

## Review Findings

### Decisions resolved (2026-05-06)

- **Phone schema:** Drop secondary phone + type fields from the form and service. Keep `users.phone VARCHAR(20)` as-is for the primary phone. (Patch added below.)

### Patch

- [ ] [Review][Patch] **`verify-email.php` exposes user_id via hidden form field on expired token** [`public/coaches/verify-email.php`] — Anyone with an expired/guessed token sees the actual `user_id` rendered as `<input type="hidden" name="user_id">` and can POST `resendVerification` for arbitrary user_ids (1..N). Combined with `RegistrationService::resendVerification` accepting any user_id with no auth check (Story 3-1 finding), enables verification-email bombing of any user. Resend should require either: re-validating the original token, or accepting the email as input and looking up the unverified user from there.
- [ ] [Review][Patch] **CSRF failure does not abort POST** [`public/coaches/register.php` ~line 1907] — Sets `$globalError` then continues into invitation-token validation, CAPTCHA verify (hits Google), field validation. Allows CSRF-less probing of invitation-token email pre-fill. Return early on CSRF failure.
- [ ] [Review][Patch] **CAPTCHA fail-opens when site key is missing** [`public/coaches/register.php` ~line 2107 + `includes/AuthService.php` `verifyRecaptcha()`] — Spec AR-8 only mandates fail-open when Google is unreachable, NOT when config keys are absent. Missing `RECAPTCHA_SITE_KEY` / `RECAPTCHA_SECRET` silently disables protection in production. Fail-closed when secret missing.
- [ ] [Review][Patch] **No rate-limiting on registration POST** [`public/coaches/register.php`] — Open self-registration is unrate-limited. Add lazy-purge + IP-based throttle similar to `login_attempts` (or reuse same table with action='register').
- [ ] [Review][Patch] **`<meta name="robots" content="noindex">` missing on `verify-email.php`, `forgot-password.php`, `reset-password.php`** — only `register.php` has it; tokens-in-URL pages should also not be indexed. `robots.txt` only disallows `/coaches/register.php`.
- [ ] [Review][Patch] **Specific password-rule error message not produced** [`public/coaches/register.php` ~line 1965] — AC6 requires "the specific rule violated"; receives generic `'Password does not meet complexity requirements.'` (Story 3-1 service finding). Patch in 3-1 enables proper messaging here.
- [ ] [Review][Patch] **Drop secondary phone fields per decision** [`public/coaches/register.php`] — Remove `secondary_phone`, `secondary_phone_type`, and `primary_phone_type` form fields. Stop concatenating with `' | '`. Pass only the primary phone string to `RegistrationService::register()`. Update AC1 prose during the next story-edit pass to reflect the simplified field set.

### Deferred

- [x] [Review][Defer] **`reg-progress` always shows step 1 active** — current behavior is correct for step 1; cosmetic concern about step-2 rendering belongs to Story 4.2.
