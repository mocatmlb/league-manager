# Story 7.2: Coach Profile Page

**Status:** ready
**Epic:** 7 — Coach Profile, Team Schedule & Authenticated Resources
**Story Key:** 7-2-coach-profile-page

---

## Story

As an authenticated coach,
I want to update my name, phone numbers, and password from a profile page,
So that my account information stays current and my password remains secure.

---

## Acceptance Criteria

**AC1: Profile page loads with current values and permission enforced**
**Given** an authenticated coach navigates to `public/coaches/profile.php`
**When** the page loads
**Then** `PermissionGuard::requireRole('user')` is enforced (any authenticated user, not just Team Owner)
**And** the form displays current values for: first name, last name, preferred name, primary phone + type, secondary phone + type
**And** the team name (if assigned) is shown as a read-only field with label "Team Name (managed by admin)" (FR-PROFILE-7)
**And** a separate "Change Password" section is shown with: current password, new password, confirm new password

**AC2: Name update calls service and shows flash**
**Given** the coach updates name fields and submits
**When** the POST is processed (PRG pattern, CSRF validated)
**Then** `ProfileService::updateName()` is called
**And** a flash success: "Profile updated."

**AC3: Primary phone update saves correctly**
**Given** the coach adds or updates their primary phone and type
**When** the POST is processed
**Then** `ProfileService::updatePhone()` is called and the record is saved

**AC4: Secondary phone removal works**
**Given** the coach submits a secondary phone removal (clears the secondary phone field)
**When** the POST is processed
**Then** `ProfileService::removeSecondaryPhone()` is called

**AC5: Password change with correct current password succeeds**
**Given** the coach submits the Change Password section with correct current password and a valid new password
**When** the POST is processed
**Then** `ProfileService::changePassword()` is called and the password is updated
**And** a flash success: "Password changed."

**AC6: Wrong current password shows inline error**
**Given** the coach provides an incorrect current password
**When** the POST is processed
**Then** the form re-renders with an inline error on the current password field: "Current password is incorrect"

**AC7: Accessibility baseline met (UX-DR19)**
**Given** the profile page is rendered
**Then** all inputs have explicit `<label for="">`
**And** error spans linked via `aria-describedby`
**And** alert regions use `role="alert"` or `aria-live="polite"`
**And** page `<title>` includes "— District 8 Travel League" suffix
**And** all inputs use `form-control-lg`; primary action buttons use `btn-lg`

---

## Tasks / Subtasks

- [ ] **Task 1: Create `public/coaches/profile.php`**
  - [ ] Enforce `PermissionGuard::requireRole('user')` at top
  - [ ] Load current user record and phone records for display
  - [ ] Render profile form: first name, last name, preferred name, primary phone + type selector (Home/Work/Cell), secondary phone + type selector (optional)
  - [ ] Show team name as read-only if coach has an assigned team (FR-PROFILE-7)
  - [ ] Render "Change Password" section: current password, new password, confirm new password
  - [ ] CSRF token on all forms

- [ ] **Task 2: Handle profile form POST (name + phone)**
  - [ ] Validate CSRF
  - [ ] Call `ProfileService::updateName()` with name fields
  - [ ] Call `ProfileService::updatePhone()` for primary phone
  - [ ] If secondary phone field is cleared (empty + previously existed): call `ProfileService::removeSecondaryPhone()`
  - [ ] If secondary phone field has a value: call `ProfileService::updatePhone($userId, $phone, $type, 'secondary')`
  - [ ] PRG redirect with `$_SESSION['flash_success'] = 'Profile updated.'`

- [ ] **Task 3: Handle password change POST**
  - [ ] Validate CSRF
  - [ ] Confirm new password === confirm password; show inline error if mismatch
  - [ ] Call `ProfileService::changePassword()`
  - [ ] On success: PRG redirect with flash "Password changed."
  - [ ] On `IncorrectCurrentPasswordException`: re-render with inline error on current password field: "Current password is incorrect"
  - [ ] On `WeakPasswordException`: re-render with inline error naming the specific rule violated

- [ ] **Task 4: Add "Profile" link to coach navbar**
  - [ ] Ensure the coach nav/layout includes a link to `profile.php` in the user dropdown

---

## Dev Notes

### Architecture Context
- New file `public/coaches/profile.php` — include bootstrap, define `D8TL_APP`
- `PermissionGuard::requireRole('user')` — any logged-in coach can access (not Team Owner only)
- PRG pattern on all POST actions (AR-10)

### Phone Type Options
- Dropdown with: `Home`, `Work`, `Cell` (matches FR-REG-3 and FR-PROFILE-2/3)
- Align with `user_phones.type` column values in schema

### Team Name (FR-PROFILE-7)
- Show team name only if `TeamScope::getScopedTeams($userId)` returns a non-empty result
- Rendered as `<input type="text" readonly>` with label "Team Name (managed by admin)"

### Two Separate POST Handlers or One?
- Option A: Two separate `<form>` elements — one for profile info, one for password
- Option B: One form with a hidden `action` field
- Prefer Option A for cleanliness — profile info and password are semantically distinct

### Privacy Note
- `ProfileService::changePassword()` does not return or log the password value — only logs the event

---

## Dev Agent Record

### Implementation Plan

### Debug Log

### Completion Notes

---

## File List

- `public/coaches/profile.php` — new
- `_bmad-output/implementation-artifacts/7-2-coach-profile-page.md` — new (this file)

---

## Change Log
- 2026-05-05: Story file created, status set to ready.
