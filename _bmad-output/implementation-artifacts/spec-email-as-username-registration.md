---
title: 'Use Email as Username in Coach Registration'
type: 'feature'
created: '2026-05-09'
status: 'done'
baseline_commit: '6c4cd7e8217f030b8919ba00fba1c5c5430ee2e7'
context: []
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** The coach registration form requires coaches to invent a separate username, adding friction and creating a credential they must remember on top of their password. The system already supports login by email, so the username field is redundant.

**Approach:** Remove the username field from the registration form and derive username from the coach's email address at registration time. Update login page UI copy to reflect that email is the only identifier.

## Boundaries & Constraints

**Always:**
- Pass `email` as the `username` value to `RegistrationService::register()` — do not modify the service's internal storage or DB schema.
- If `DuplicateUsernameException` is thrown (fires when username/email already exists in `users.username`), map the error to the `email` field with the message "An account with this email already exists." — same UX as `DuplicateEmailException`.
- Follow existing CSRF, session, and sanitization patterns.
- Keep `autocomplete` attributes accurate for password managers.

**Ask First:**
- If any other page outside `register.php` or `login.php` renders or processes a username input for coaches (e.g., a profile edit page), halt and ask before touching it.

**Never:**
- Modify the `users` table schema or the `username` column.
- Change `RegistrationService::register()` or `AuthService::authenticate()` internals.
- Touch admin pages or non-coach auth flows.

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| Happy path registration | Valid form (no username field) | Account created with `username = email`; redirect to verify-email.php | N/A |
| Duplicate email on register | Email already in `users` table | Stay on form; email field shows "An account with this email already exists." | `DuplicateUsernameException` or `DuplicateEmailException` both map to email field error |
| Login with email after register | Coach enters email + password | Authenticates successfully via `username OR email` query | N/A |

</frozen-after-approval>

## Code Map

- `public/coaches/register.php` — registration form + POST handler; remove username field, derive username from email
- `public/coaches/login.php` — login UI only; update labels and input type to reflect email-only identifier
- `includes/RegistrationService.php` — read-only reference; `register()` accepts `username` key, no changes needed

## Tasks & Acceptance

**Execution:**
- [x] `public/coaches/register.php` -- Remove `'username' => ''` from `$formData`; remove username required validation (line 104); remove `DuplicateUsernameException` catch and replace with a catch that maps to `$fieldErrors['email']` with "An account with this email already exists."; pass `'username' => $formData['email']` in the `register()` call; remove the username HTML field block (lines 246–250)
- [x] `public/coaches/login.php` -- Change card subtitle from "Sign in with your username or email" to "Sign in with your email address"; change label from "Username or Email" to "Email"; change `type="text"` to `type="email"` on the identifier input; change `autocomplete="username"` to `autocomplete="email"`; change error string "Invalid username or password" to "Invalid email or password"

**Acceptance Criteria:**
- Given the registration form loads, when rendered, then no username field is visible.
- Given a coach submits the registration form with a valid email and all other required fields, when the form is posted, then `users.username` is set equal to `users.email` for the new row.
- Given a coach attempts to register with an email that is already in use, when the form is posted, then the email field shows "An account with this email already exists." and no account is created.
- Given a coach registered under this flow, when they log in with their email on `login.php`, then authentication succeeds.
- Given the login page loads, when rendered, then the identifier field label reads "Email", the subtitle reads "Sign in with your email address", and the error message on failed login reads "Invalid email or password".

## Spec Change Log

## Verification

**Commands:**
- `php tests/unit/run-unit-tests.php` -- expected: all tests pass (no regressions in auth/registration unit tests)

**Manual checks (if no CLI):**
- Load `register.php` in browser: confirm no username field is rendered.
- Submit the registration form with a fresh email: confirm account created and redirected to `verify-email.php`.
- Submit again with the same email: confirm email-field error appears, no second account created.
- Load `login.php`: confirm label reads "Email", subtitle updated, `autocomplete="email"` on identifier field.

## Suggested Review Order

**Registration handler — username derivation**

- Entry point: `register()` call now passes `email` as `username` value.
  [`register.php:109`](../../public/coaches/register.php#L109)

- Duplicate-email catch remapped from `username` field to `email` field.
  [`register.php:127`](../../public/coaches/register.php#L127)

- Username required validation removed; form key removed from `$formData`.
  [`register.php:38`](../../public/coaches/register.php#L38)

**Registration UI — username field removal**

- Username HTML field block removed; password field now directly follows league.
  [`register.php:241`](../../public/coaches/register.php#L241)

**Login UI — email-only copy and input type**

- Label, subtitle, input type, and autocomplete updated to reflect email-only flow.
  [`login.php:142`](../../public/coaches/login.php#L142)

- Error message and input field updated.
  [`login.php:97`](../../public/coaches/login.php#L97)

**Auth fallback string — patch applied**

- `statusMessage()` default branch updated to match new "email or password" copy.
  [`AuthService.php:365`](../../includes/AuthService.php#L365)
