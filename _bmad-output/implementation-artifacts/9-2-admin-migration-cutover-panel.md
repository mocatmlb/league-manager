# Story 9.2: Admin Migration Cutover Panel

**Status:** ready
**Epic:** 9 — Migration Cutover & Shared Credential Deprecation
**Story Key:** 9-2-admin-migration-cutover-panel

---

## Story

As an admin,
I want a migration panel showing team onboarding status and a "Disable Shared Login" button that is locked until all gaps are resolved,
So that I can complete the transition to individual coach accounts with full confidence.

---

## Acceptance Criteria

**AC1: Panel shows summary stats and gap checklist**
**Given** an admin navigates to the migration panel (within `admin/settings/` section)
**When** the page loads
**Then** a summary stat row shows 3 cards: "Teams Covered", "Teams with Gaps", "Active Team Owners"
**And** a pre-cutover gap checklist table (`.gap-row-covered` / `.gap-row-missing`) shows all active-season teams with their assignment status (UX-DR6)
**And** each gap row has an "Assign Coach →" action link pointing to that team's admin management page

**AC2: Warning banner and locked button shown when gaps exist**
**Given** one or more teams have no assigned Team Owner (gap count > 0)
**When** the page loads
**Then** an `alert alert-warning` banner is shown: "X active-season team(s) have no assigned Team Owner. Resolve all gaps before disabling the shared credential."
**And** the "Disable Shared Login" button has the `disabled` attribute with inline text: "All teams must have at least one assigned Team Owner before you can disable the shared login."

**AC3: Button enabled and checklist all-green when no gaps**
**Given** all active-season teams have at least one assigned Team Owner (gap count = 0)
**When** the page loads
**Then** the warning banner is cleared
**And** all gap checklist rows show `.gap-row-covered` (green text + check icon)
**And** the "Disable Shared Login" button is enabled (`btn-danger`)

**AC4: Confirmation modal is shown on button click**
**Given** the admin clicks the enabled "Disable Shared Login" button
**When** the modal appears
**Then** the `modal-dialog-centered` cutover confirmation modal is shown with `data-bs-backdrop="static"` (no backdrop dismiss, no × close) (UX-DR11)
**And** the modal body reads: "This will permanently disable the shared coach password. All coaches must use their individual accounts. This cannot be automatically undone."
**And** the footer has `btn-secondary` Cancel and `btn-danger` Confirm

**AC5: Confirm action disables credential and shows success**
**Given** the admin clicks "Confirm" in the modal
**When** the POST is processed
**Then** `CutoverService::disableSharedCredential()` is called
**And** an `alert alert-success` is shown: "Shared credential disabled. All coach access is now through individual accounts. Rollback window: 30 days."
**And** the "Disable Shared Login" button is replaced with a "Cutover Complete" success badge
**And** `ActivityLogger` event `admin.shared_credential_disabled` is recorded

**AC6: Old shared password login shows appropriate message**
**Given** the shared credential has been disabled and a coach attempts to log in using the old shared password
**When** the login POST is processed
**Then** they receive: "Coach login has been updated — please use your individual account."

**AC7: Panel shows Cutover Complete when credential is already disabled**
**Given** the shared credential has been disabled and the admin navigates back to the cutover panel
**When** the page loads
**Then** the panel shows "Cutover Complete" status and the "Disable Shared Login" button is not shown

---

## Tasks / Subtasks

- [ ] **Task 1: Create migration cutover panel page**
  - [ ] Create `public/admin/settings/sections/cutover.php` (or integrate as a section in `admin/settings/index.php`)
  - [ ] Enforce admin authentication
  - [ ] Call `CutoverService::getGapChecklist()` and `getGapCount()`
  - [ ] Call `CutoverService::isSharedCredentialActive()` — if false, show "Cutover Complete" state, hide Disable button
  - [ ] Render 3 summary stat cards: "Teams Covered" (gap count 0), "Teams with Gaps" (gap count > 0), "Active Team Owners" (total count)
  - [ ] Render gap checklist table with `.gap-row-covered` or `.gap-row-missing` per row (UX-DR6)
    - Covered row: green text + ✓ icon
    - Missing row: red text + ✗ icon, `#fff9f9` row background
    - Columns: Team, Division, Program, Owners list, Status badge, "Assign Coach →" action link
  - [ ] If gap count > 0: render `alert alert-warning` with count and message
  - [ ] Render "Disable Shared Login" button: `disabled` attribute when gaps exist with inline explanation; `btn-danger` when all covered
  - [ ] Render Bootstrap confirmation modal (UX-DR11): `modal-dialog-centered`, `data-bs-backdrop="static"`, no × close button, Cancel + Confirm in footer

- [ ] **Task 2: Handle "Disable Shared Login" POST**
  - [ ] Handle POST from modal Confirm button: validate CSRF
  - [ ] Call `CutoverService::disableSharedCredential($adminUserId)`
  - [ ] On success: PRG redirect back to cutover panel with flash success message
  - [ ] On `CutoverGapsRemainingException` (race condition): show flash error "Cannot disable — gaps were detected. Please resolve all gaps first."

- [ ] **Task 3: Add disabled-credential login message to `public/coaches/login.php`**
  - [ ] In login POST handler: call `CutoverService::isSharedCredentialActive()`
  - [ ] If false AND the submitted credentials match the old shared password pattern (or a known shared credential check): show "Coach login has been updated — please use your individual account."
  - [ ] Alternatively: if `coaches_password` setting is null/empty, any login attempt using it will simply fail — ensure the failure message is distinct: "Coach login has been updated — please use your individual account."

- [ ] **Task 4: Add CSS for gap checklist rows (UX-DR6)**
  - [ ] `.gap-row-covered` — green text (`color: #28a745`), ✓ icon (Bootstrap Icons or Unicode ✓)
  - [ ] `.gap-row-missing` — red text (`color: #dc3545`), ✗ icon (Bootstrap Icons or Unicode ✗), `background-color: #fff9f9`
  - [ ] Both use color AND icon — never color alone (accessibility)

- [ ] **Task 5: Add link to cutover panel in admin settings**
  - [ ] Add link to migration cutover panel in `admin/settings/index.php` nav or card section

---

## Dev Notes

### Architecture Context
- `public/admin/settings/index.php` already exists — add link/section for the cutover panel
- Cutover panel page: `public/admin/settings/sections/cutover.php` is the recommended location; alternatively integrate inline into settings

### UX-DR11: Cutover Confirmation Modal
- Bootstrap 5 modal with:
  - `class="modal-dialog modal-dialog-centered"`
  - `data-bs-backdrop="static"` (cannot dismiss by clicking backdrop)
  - No × close button in the header (omit the `<button class="btn-close">` element)
  - Footer: `<button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>` + `<form method="POST"><button class="btn btn-danger">Confirm</button></form>`

### Rollback Window Note
- The "30 days rollback window" in the success message is informational — no automatic rollback mechanism is built
- Admin can manually re-enable via direct DB update or future admin tool

### Detecting Old Shared Password Login (AC6)
- If `settings.coaches_password` is NULL/empty, attempting to use it for login will fail naturally via whatever auth check is in place
- In `login.php`: if `isSharedCredentialActive()` returns `false`, after failed auth check, show the custom message instead of generic "Invalid username or password"
- This avoids needing to know the old shared password in code

---

## Dev Agent Record

### Implementation Plan

### Debug Log

### Completion Notes

---

## File List

- `public/admin/settings/sections/cutover.php` — new (or section in `admin/settings/index.php`)
- `public/admin/settings/index.php` — modify (add link to cutover panel)
- `public/coaches/login.php` — modify (add disabled-shared-credential check with appropriate message)
- `assets/css/style.css` — modify (add `.gap-row-covered`, `.gap-row-missing` CSS)
- `_bmad-output/implementation-artifacts/9-2-admin-migration-cutover-panel.md` — new (this file)

---

## Change Log
- 2026-05-05: Story file created, status set to ready.
