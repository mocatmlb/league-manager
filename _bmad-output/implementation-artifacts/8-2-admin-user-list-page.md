# Story 8.2: Admin User List Page

**Status:** ready
**Epic:** 8 — Admin User Management
**Story Key:** 8-2-admin-user-list-page

---

## Story

As an admin,
I want to view, search, and filter all user accounts in a paginated list,
So that I can quickly find any coach and take action on their account.

---

## Acceptance Criteria

**AC1: User list loads with paginated table and filter controls**
**Given** an admin navigates to `public/admin/users/index.php`
**When** the page loads
**Then** a paginated table of all user accounts is shown with columns: Name, Username, Email, Role, Status, Registered Date, Actions
**And** filter inputs are shown for: search (name/username/email), role dropdown, status dropdown
**And** the table uses `table-sm` compact density for desktop

**AC2: Search filter matches name, username, or email**
**Given** the admin enters a search term and submits the filter form
**When** the GET request is processed
**Then** only matching accounts are shown (name OR username OR email contains search term)
**And** the applied filters are preserved in the form inputs

**AC3: Zero results shows empty state**
**Given** zero users match the filters
**When** the page loads
**Then** an appropriate empty state is shown: "No users match your search. Try adjusting the filter." (UX-DR16)

**AC4: View link navigates to user detail**
**Given** the admin clicks "View" on any user row
**When** the link is followed
**Then** they are taken to `admin/users/detail.php` for that user

---

## Tasks / Subtasks

- [ ] **Task 1: Create `public/admin/users/index.php`**
  - [ ] Enforce admin authentication at top (check if `PermissionGuard::requireRole('administrator')` is already the pattern; if not use existing admin auth check)
  - [ ] Read filter GET params: `search`, `role`, `status`, `page` (default 1), `per_page` (default 25)
  - [ ] Call `UserManagementService::getList($filters, $page, $perPage)` to get paginated results
  - [ ] Render filter form: text search input, role `<select>` (all/user/team_owner/administrator), status `<select>` (all/active/inactive/unverified), submit button
  - [ ] Preserve filter values in form inputs after submit
  - [ ] Render table (`table table-sm table-striped`): Name (preferred/first + last), Username, Email, Role badge, Status badge, Registered Date, "View" link → `detail.php?id={id}`
  - [ ] Render pagination controls below table using `total_count` and `$perPage`
  - [ ] Render empty state `alert alert-info` when zero results (UX-DR16)

- [ ] **Task 2: Add status and role badge display**
  - [ ] Role badges: use `badge` classes — `administrator` (red), `team_owner` (green `.status-team-owner`), `user` (gray)
  - [ ] Status badges: `active` (green), `inactive` (red/muted), `unverified` (amber `.status-unverified`)

- [ ] **Task 3: Verify admin navigation includes Users link**
  - [ ] Confirm admin nav/sidebar has a "User Management" or "Users" link pointing to `admin/users/index.php`
  - [ ] Add link if missing

---

## Dev Notes

### Architecture Context
- New file `public/admin/users/index.php`
- Admin auth: follow existing admin auth pattern (check `public/admin/` pages for how auth is enforced)
- Filters are GET params (not POST) so filter state is preserved in the URL and browser Back button works

### Pagination
- Simple LIMIT/OFFSET pagination: show "Page X of Y" and Previous/Next links
- Pass `page` param in filter form or as separate pagination links with current filters preserved in query string

### Name Display
- Show `preferred_name` if set, else `first_name`, plus `last_name`

### Role/Status Dropdowns
- Role options: "All Roles", "User", "Team Owner", "Administrator"
- Status options: "All Statuses", "Active", "Inactive", "Unverified"
- Values must match the `users.role` and `users.status` column values in the DB

---

## Dev Agent Record

### Implementation Plan

### Debug Log

### Completion Notes

---

## File List

- `public/admin/users/index.php` — new
- `_bmad-output/implementation-artifacts/8-2-admin-user-list-page.md` — new (this file)

---

## Change Log
- 2026-05-05: Story file created, status set to ready.
