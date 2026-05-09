# Story 7.4: Authenticated Access to Rules & Contact Directory

**Status:** ready
**Epic:** 7 — Coach Profile, Team Schedule & Authenticated Resources
**Story Key:** 7-4-authenticated-access-rules-contact-directory

---

## Story

As an authenticated coach,
I want to access the league rules documents and contact directory after logging in,
So that I can find the information I need without the shared password.

---

## Acceptance Criteria

**AC1: Rules page requires authentication and shows documents**
**Given** an authenticated user (role ≥ `user`) navigates to `public/coaches/rules.php`
**When** the page loads
**Then** `PermissionGuard::requireRole('user')` is enforced
**And** the page displays links to all documents uploaded via the existing admin Document Management feature

**AC2: Contacts page requires authentication**
**Given** an authenticated user navigates to `public/coaches/contacts.php`
**When** the page loads
**Then** `PermissionGuard::requireRole('user')` is enforced
**And** the existing contacts page content is displayed (no content changes; auth gate added only)

**AC3: Unauthenticated access redirects to login with return URL**
**Given** an unauthenticated user visits either page directly
**When** `PermissionGuard::requireRole('user')` fires
**Then** they are redirected to the login page
**And** the intended URL is stored in session so they land on the intended page after login (FR-RESOURCES-4)

**AC4: Privacy boundary enforced**
**Given** a Team Owner coach is logged in
**When** they access the rules or contacts page
**Then** they cannot see the profile information of other coaches, teams, admins, or officials (FR-RESTRICTIONS-6)
**And** the pages only show the shared public resources (documents, contacts) — no user-specific data from other accounts

---

## Tasks / Subtasks

- [ ] **Task 1: Create `public/coaches/rules.php`**
  - [ ] Enforce `PermissionGuard::requireRole('user')` at top; store intended URL in session for post-login redirect
  - [ ] Query existing document management system for uploaded rules/regulations documents
  - [ ] Render list of documents with download/view links
  - [ ] Empty state if no documents uploaded: "No documents available yet — check back soon."
  - [ ] Page `<title>`: "Rules & Regulations — District 8 Travel League"

- [ ] **Task 2: Modify `public/coaches/contacts.php`**
  - [ ] Add `PermissionGuard::requireRole('user')` at the very top of the file (before any output)
  - [ ] Store intended URL in session before redirect (consistent with PermissionGuard behavior)
  - [ ] No content changes — existing contacts display is preserved

- [ ] **Task 3: Verify post-login redirect works**
  - [ ] `PermissionGuard::requireRole()` stores `$_SERVER['REQUEST_URI']` in `$_SESSION['redirect_after_login']`
  - [ ] Login handler reads and clears this session key after successful authentication and redirects there
  - [ ] Verify this pattern is already implemented in `public/coaches/login.php` (from Story 3.4) — if not, add it

- [ ] **Task 4: Add links in coach nav/dashboard**
  - [ ] "Rules & Regulations" link in coach nav pointing to `rules.php`
  - [ ] "Contacts" link in coach nav pointing to `contacts.php`
  - [ ] Verify "Contacts" action card on dashboard (Story 4.4) links correctly to `contacts.php`

---

## Dev Notes

### Architecture Context
- `public/coaches/rules.php` — new file; `public/coaches/contacts.php` — already exists, add one guard line at top
- Document Management system: locate the admin document upload feature in existing codebase; query the same table for the rules page
- No new DB tables needed

### Document Query
- Find existing `documents` table or equivalent in the codebase (check admin Document Management pages)
- Query for documents relevant to "rules" or "all public documents" — align with however the existing feature categorizes docs

### Post-Login Redirect (FR-RESOURCES-4)
- Standard pattern: store `$_SERVER['REQUEST_URI']` in session before redirecting to login
- After login, check for stored URL and redirect there (clear session key after use)
- `PermissionGuard::requireRole()` should handle this internally — verify its implementation from Story 1.3

### Privacy (FR-RESTRICTIONS-6)
- Rules and contacts pages only show shared/public content — no user-specific data from other accounts
- No join with coach-specific tables needed for these pages

---

## Dev Agent Record

### Implementation Plan

### Debug Log

### Completion Notes

---

## File List

- `public/coaches/rules.php` — new
- `public/coaches/contacts.php` — modify (add `PermissionGuard::requireRole('user')` at top)
- `_bmad-output/implementation-artifacts/7-4-authenticated-access-rules-contact-directory.md` — new (this file)

---

## Change Log
- 2026-05-05: Story file created, status set to ready.
