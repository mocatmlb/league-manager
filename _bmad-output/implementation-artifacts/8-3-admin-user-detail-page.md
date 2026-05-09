# Story 8.3: Admin User Detail Page

**Status:** ready
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

- [ ] **Task 1: Extend `public/admin/users/detail.php`** (file exists from Story 4.3)
  - [ ] Add full edit form section (pre-populated with current values): first name, last name, preferred name, email, username, primary phone + type
  - [ ] Handle profile edit POST: validate CSRF, call `UserManagementService::update()`, PRG redirect, flash success

- [ ] **Task 2: Add role management section**
  - [ ] Role selector `<select>`: user, team_owner, administrator
  - [ ] Handle role change POST: validate CSRF, call `UserManagementService::setRole()`, PRG redirect
  - [ ] Show warning if changing role of account being viewed by the logged-in admin

- [ ] **Task 3: Add disable/enable action**
  - [ ] Show "Disable Account" button when status = `active`; show "Enable Account" when status = `inactive`
  - [ ] Confirmation step: inline confirmation paragraph (or simple JS `confirm()` dialog)
  - [ ] Handle POST: call `UserManagementService::disable()` or `enable()`, PRG redirect, flash success
  - [ ] After disable: status badge shows "Account Disabled" (red/muted)

- [ ] **Task 4: Add reset password action**
  - [ ] "Reset Password" button with confirmation step
  - [ ] Handle POST: call `UserManagementService::resetPassword()`
  - [ ] Display temp password in a one-time flash: "Temporary password: [TEMP]. Share this with the coach — it cannot be shown again."
  - [ ] Temp password displayed in an `alert alert-warning` that is dismissible but not re-showable after page reload

- [ ] **Task 5: Add delete account action**
  - [ ] "Delete Account" button — red `btn-danger`
  - [ ] Two-step confirmation: first click shows confirmation message "This will permanently delete this account and all team assignments. This cannot be undone."; second click submits POST
  - [ ] Handle POST: call `UserManagementService::delete()`, redirect to `users/index.php` with flash "Account deleted."

- [ ] **Task 6: Verify team assignment section from Story 4.3 is preserved**
  - [ ] "Assign to Team" and "Remove Assignment" UI from Story 4.3 must still function
  - [ ] Do not remove or break existing team assignment functionality

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

### Debug Log

### Completion Notes

---

## File List

- `public/admin/users/detail.php` — modify (extend file from Story 4.3; add edit form, role selector, disable/enable, reset password, delete)
- `_bmad-output/implementation-artifacts/8-3-admin-user-detail-page.md` — new (this file)

---

## Change Log
- 2026-05-05: Story file created, status set to ready.
