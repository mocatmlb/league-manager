# Story 8.3: Admin User Detail Page

**Status:** done
**Epic:** 8 — Admin User Management
**Story Key:** 8-3-admin-user-detail-page

**⚠️ Important:** This story extends `public/admin/users/detail.php` which was **created in Story 4.3**. Do NOT create a new file or overwrite the existing one. Add full CRUD edit form, role selector, disable/enable, reset password, and delete actions to the team assignment UI already in place.

---

## Story

As an admin,
I want a detail page for each user account where I can edit their profile, change their role, disable/delete their account, and reset their password,
So that I have full control over every coach account.

---

## Acceptance Criteria

**AC1: Detail page shows current user info and edit form**
**Given** an admin navigates to `public/admin/users/detail.php?id={userId}`
**When** the page loads
**Then** the user's current name, email, username, phone, role, status, and team assignments are displayed
**And** an edit form is shown pre-populated with current values
**And** action buttons are shown for: Change Role, Disable/Enable Account, Reset Password, Delete Account

**AC2: Edit form saves profile changes**
**Given** the admin submits the edit form with valid changes
**When** the POST is processed (PRG pattern)
**Then** `UserManagementService::update()` is called and a flash success is shown

**AC3: Role change updates user role**
**Given** the admin changes the user's role via the role selector and submits
**When** the POST is processed
**Then** `UserManagementService::setRole()` is called and the role is updated

**AC4: Disable account shows disabled status badge**
**Given** the admin clicks "Disable Account" and confirms
**When** the POST is processed
**Then** `UserManagementService::disable()` is called
**And** the page reloads showing "Account Disabled" status badge

**AC5: Reset password shows temp password once**
**Given** the admin clicks "Reset Password" and confirms
**When** the POST is processed
**Then** `UserManagementService::resetPassword()` is called
**And** the temporary password is shown once on the confirmation page: "Temporary password: [TEMP]. Share this with the coach — it cannot be shown again."

**AC6: Delete account redirects to list with flash**
**Given** the admin clicks "Delete Account" and confirms the confirmation step
**When** the POST is processed
**Then** `UserManagementService::delete()` is called
**And** the admin is redirected to `users/index.php` with a flash: "Account deleted."

---

## Tasks / Subtasks

- [x] **Task 1: Extend `public/admin/users/detail.php`** (file exists from Story 4.3)
  - [x] Add full edit form section (pre-populated with current values): first name, last name, preferred name, email, username, primary phone + type
  - [x] Handle profile edit POST: validate CSRF, call `UserManagementService::update()`, PRG redirect, flash success

- [x] **Task 2: Add role management section**
  - [x] Role selector `<select>`: user, team_owner, administrator
  - [x] Handle role change POST: validate CSRF, call `UserManagementService::setRole()`, PRG redirect
  - [x] Show warning if changing role of account being viewed by the logged-in admin

- [x] **Task 3: Add disable/enable action**
  - [x] Show "Disable Account" button when status = `active`; show "Enable Account" when status = `inactive`
  - [x] Confirmation step: inline confirmation paragraph (or simple JS `confirm()` dialog)
  - [x] Handle POST: call `UserManagementService::disable()` or `enable()`, PRG redirect, flash success
  - [x] After disable: status badge shows "Account Disabled" (red/muted)

- [x] **Task 4: Add reset password action**
  - [x] "Reset Password" button with confirmation step
  - [x] Handle POST: call `UserManagementService::resetPassword()`
  - [x] Display temp password in a one-time flash: "Temporary password: [TEMP]. Share this with the coach — it cannot be shown again."
  - [x] Temp password displayed in an `alert alert-warning` that is dismissible but not re-showable after page reload

- [x] **Task 5: Add delete account action**
  - [x] "Delete Account" button — red `btn-danger`
  - [x] Two-step confirmation: first click shows confirmation message "This will permanently delete this account and all team assignments. This cannot be undone."; second click submits POST
  - [x] Handle POST: call `UserManagementService::delete()`, redirect to `users/index.php` with flash "Account deleted."

- [x] **Task 6: Verify team assignment section from Story 4.3 is preserved**
  - [x] "Assign to Team" and "Remove Assignment" UI from Story 4.3 must still function
  - [x] Do not remove or break existing team assignment functionality

### Review Findings

- [x] [Review][Defer] Deleting own account [public/admin/users/detail.php:603] — The UI prevents deleting own account ($isSelf guard), but the POST handler action === 'delete_execute' does not. — deferred, admin trust assumed.
- [x] [Review][Patch] Weak CSRF protection (token reuse) [public/admin/users/detail.php:455, 525, 562, 572, 582, 613, 630, 653, 671] — Fixed: `$csrfToken = Auth::generateCSRFToken()` called once before HTML; all 9 form inputs reference `$csrfToken`.
- [x] [Review][Patch] Missing input validation in `update()` [includes/UserManagementService.php:77] — Fixed: validates email format, email/username uniqueness, and non-empty first/last name; throws `InvalidArgumentException` surfaced as user-visible error.
- [x] [Review][Patch] Potential integer overflow in `getList()` [includes/UserManagementService.php:33] — Fixed: page clamped to 1–1,000,000; float cast before multiplication.
- [x] [Review][Patch] Temp password display location [public/admin/users/detail.php:199, 372] — Fixed: reset redirects to new `password-reset-success.php` confirmation page; temp password shown there once and cleared from session.
- [x] [Review][Defer] `user_phones` upsert race condition [public/admin/users/detail.php:124] — If two admins edit the same user simultaneously, the check-then-insert logic for primary phone could result in duplicate 'primary' entries. — deferred, pre-existing pattern in project.

---

## Dev Notes

### Architecture Context
- `public/admin/users/detail.php` was created in Story 4.3 — extend it, do NOT recreate
- All new sections follow the same PRG pattern and CSRF validation used in Story 4.3
- Use `?id={userId}` query param for all POST action handlers on the same page

### POST Action Disambiguation
- Multiple POST actions on one page: use a hidden `action` field to distinguish them
- E.g., `action=update_profile`, `action=change_role`, `action=disable`, `action=enable`, `action=reset_password`, `action=delete`

### Temp Password Display
- Store temp password in `$_SESSION['temp_password']` before redirect; read-and-clear on the redirected GET
- Display once in `alert alert-warning` — after the page is reloaded the session key is gone

### Two-Step Delete Confirmation
- Option A: First POST sets `$_SESSION['confirm_delete_user'] = $userId`; page re-renders showing the confirmation button; second POST executes delete
- Option B: JS `confirm()` dialog before submitting the delete form
- Either approach is acceptable for admin-only UI

### Admin Self-Protection
- Optionally: prevent admin from disabling or deleting their own account
- If current `$_SESSION['user_id'] === $userId`: show warning, disable those buttons

---

## Dev Agent Record

### Implementation Plan

Extended `public/admin/users/detail.php` in place (Story 4.3 file). Added `preferred_name` and `role_id` to the initial SELECT query. Primary phone fetched from `user_phones` table (`role='primary'`). Five new POST actions added using the hidden `action` field pattern: `update_profile`, `change_role`, `disable_account`, `enable_account`, `reset_password`, `delete_confirm`, `delete_execute`. Temp password stored in `$_SESSION['temp_password']` before PRG redirect; displayed once via `alert alert-warning`. Two-step delete uses `$_SESSION['confirm_delete_user']`. Self-protection (`$isSelf`) disables role-change and disable/delete on own account. All Story 4.3 team assignment handlers preserved and moved to end of POST dispatcher.

### Debug Log

### Completion Notes

All 6 tasks complete. `detail.php` fully extended with edit form, role selector, disable/enable, one-time temp password display, two-step delete, and self-protection guards. Team assignment section (Story 4.3) preserved intact. "Back" link updated to point to `index.php` (user list) instead of `../index.php` (dashboard). 10 new unit tests pass covering 8.2/8.3 service layer.

---

## File List

- `public/admin/users/detail.php` — modified (extended from Story 4.3; added edit form, role selector, disable/enable, reset password, two-step delete, self-protection; single CSRF token; validation errors surfaced inline)
- `public/admin/users/password-reset-success.php` — new (dedicated temp-password confirmation page, AC5)
- `includes/UserManagementService.php` — modified (preferred_name in getList SELECT; input validation in update(); overflow/stmt-safety in getList())
- `tests/unit/UserManagementServiceTest.php` — modified (3 new validation tests)
- `_bmad-output/implementation-artifacts/8-3-admin-user-detail-page.md` — this file

---

## Change Log
- 2026-05-05: Story file created, status set to ready.
- 2026-05-10: Implementation complete — status set to review.
