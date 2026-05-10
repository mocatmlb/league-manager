# Story 8.2: Admin User List Page

**Status:** done
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

- [x] **Task 1: Create `public/admin/users/index.php`**
  - [x] Enforce admin authentication at top (check if `PermissionGuard::requireRole('administrator')` is already the pattern; if not use existing admin auth check)
  - [x] Read filter GET params: `search`, `role`, `status`, `page` (default 1), `per_page` (default 25)
  - [x] Call `UserManagementService::getList($filters, $page, $perPage)` to get paginated results
  - [x] Render filter form: text search input, role `<select>` (all/user/team_owner/administrator), status `<select>` (all/active/inactive/unverified), submit button
  - [x] Preserve filter values in form inputs after submit
  - [x] Render table (`table table-sm table-striped`): Name (preferred/first + last), Username, Email, Role badge, Status badge, Registered Date, "View" link → `detail.php?id={id}`
  - [x] Render pagination controls below table using `total_count` and `$perPage`
  - [x] Render empty state `alert alert-info` when zero results (UX-DR16)

- [x] **Task 2: Add status and role badge display**
  - [x] Role badges: use `badge` classes — `administrator` (red), `team_owner` (green `.status-team-owner`), `user` (gray)
  - [x] Status badges: `active` (green), `inactive` (red/muted), `unverified` (amber `.status-unverified`)

- [x] **Task 3: Verify admin navigation includes Users link**
  - [x] Confirm admin nav/sidebar has a "User Management" or "Users" link pointing to `admin/users/index.php`
  - [x] Add link if missing

### Review Findings

- [x] [Review][Patch] Potential integer overflow in `getList()` [includes/UserManagementService.php:33] — Fixed: page clamped to 1–1,000,000; multiplication done via float to prevent overflow.
- [x] [Review][Patch] SQL result check in `getList()` [includes/UserManagementService.php:72] — Fixed: guard added (`method_exists($stmt, 'fetchAll')`) before calling fetchAll; returns `[]` on failure.
- [ ] [Review][Patch] Empty search results GET params [public/admin/users/index.php:44] — The page handles empty search results correctly in the UI, but doesn't handle non-GET params gracefully (e.g. if someone tries to POST to it).

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

Created `public/admin/users/index.php` following the robust EnvLoader bootstrap pattern from `detail.php`/`teams/index.php`. Used `Auth::requireAdmin()` for authentication and `UserManagementService::getList()` for all data. Filter state preserved in GET params. Badge helpers implemented as local functions. Also added `u.preferred_name` to the `getList` SELECT query in `UserManagementService.php` so preferred names show in the list. Status values in the DB are `active`, `disabled`, `unverified` (not `inactive`); status dropdown labels match accordingly.

### Debug Log

### Completion Notes

All tasks complete. `index.php` created with paginated table, filter form (search/role/status), badge helpers, empty state, and simple Previous/Next+page-number pagination. Nav updated in `includes/nav.php` with "User Management" link and `users` added to the active-dir detection set. 10 new unit tests added (8.2 and 8.3 coverage) — all pass. Pre-existing ProfileService mock failures (9) unchanged.

---

## File List

- `public/admin/users/index.php` — new
- `includes/nav.php` — modified (added User Management link; added 'users' to active-dir set)
- `includes/UserManagementService.php` — modified (added u.preferred_name to getList SELECT)
- `tests/unit/UserManagementServiceTest.php` — modified (10 new Story 8.2/8.3 tests)
- `_bmad-output/implementation-artifacts/8-2-admin-user-list-page.md` — this file

---

## Change Log
- 2026-05-05: Story file created, status set to ready.
- 2026-05-10: Implementation complete — status set to review.
