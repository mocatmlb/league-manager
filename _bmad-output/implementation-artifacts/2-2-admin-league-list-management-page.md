# Story 2.2: Admin League List Management Page

**Status:** done
**Epic:** 2 — Admin League List Management
**Story Key:** 2-2-admin-league-list-management-page

---

## Story

As an admin,
I want a management page to create, edit, reorder, and deactivate league entries,
So that the registration form dropdown always reflects the correct league options.

---

## Acceptance Criteria

**AC1: Page load shows active and deactivated entries**
**Given** an admin is logged in and navigates to `admin/league-list/index.php`
**When** the page loads
**Then** all active league entries are displayed in a sortable table in their configured display order
**And** deactivated entries are shown in a separate section below with a visual indicator (muted/strikethrough)
**And** a "Add League" form/button is visible at the top

**AC2: Add League creates new entry**
**Given** the admin clicks "Add League" and enters a short display name and submits
**When** the form POSTs successfully (PRG pattern)
**Then** the new entry appears at the bottom of the active list
**And** a flash success message confirms the addition
**And** an `ActivityLogger` event `admin.league_list_created` is recorded

**AC3: Edit updates display name**
**Given** the admin clicks an entry's edit control and changes the display name
**When** the form POSTs successfully
**Then** the updated name appears in the list
**And** a flash success message confirms the update
**And** an `ActivityLogger` event `admin.league_list_edited` is recorded

**AC4: Deactivate moves entry to deactivated section**
**Given** the admin clicks "Deactivate" on an active entry
**When** they confirm the action
**Then** the entry moves to the deactivated section
**And** a flash success message confirms
**And** an `ActivityLogger` event `admin.league_list_deactivated` is recorded

**AC5: Reactivate restores entry to active list**
**Given** the admin clicks "Reactivate" on a deactivated entry
**When** the action completes
**Then** the entry returns to the active list at the bottom
**And** an `ActivityLogger` event `admin.league_list_reactivated` is recorded

**AC6: Reorder saves new order**
**Given** the admin drags entries to reorder them (or uses up/down controls) and saves order
**When** the reorder POST completes
**Then** `getActiveList()` returns entries in the new order
**And** the page reloads showing the saved order

**AC7: Empty state when no active entries**
**Given** no active entries exist
**When** the page loads
**Then** an appropriate empty state message is shown ("No leagues configured yet. Add the first one above.")

---

## Tasks / Subtasks

- [x] **Task 1: Create directory and page file**
  - [x] Create `public/admin/league-list/` directory
  - [x] Create `public/admin/league-list/index.php` with admin bootstrap and auth

- [x] **Task 2: Implement GET/display logic**
  - [x] Load active entries via `LeagueListManager::getActiveList()`
  - [x] Load all entries via `LeagueListManager::getAll()` for deactivated section
  - [x] Display active entries in sortable table (drag handles via admin-league-list.js)
  - [x] Display deactivated entries in separate section with muted/strikethrough style
  - [x] Show empty state when no active entries
  - [x] Render flash messages from `$_SESSION['flash_success']` / `$_SESSION['flash_error']`

- [x] **Task 3: Implement POST handlers (PRG pattern)**
  - [x] `action=add`: validate display_name, call `create()`, log event, redirect with flash
  - [x] `action=edit`: validate id + display_name, call `update()`, log event, redirect with flash
  - [x] `action=deactivate`: validate id, call `deactivate()`, log event, redirect with flash
  - [x] `action=reactivate`: validate id, call `reactivate()`, log event, redirect with flash
  - [x] `action=reorder`: validate ordered_ids array, call `reorder()`, redirect with flash
  - [x] CSRF validation on all POST actions

- [x] **Task 4: Add nav link to admin sidebar/header**
  - [x] Add "League List" nav item to Management dropdown in nav.php

- [x] **Task 5: Implement `public/assets/js/admin-league-list.js`**
  - [x] SortableJS drag-and-drop on active entries list for drag-reorder (UX-DR13)
  - [x] On drop, update hidden input with serialized order
  - [x] "Save Order" button POSTs reorder action (enabled only when order changes)
  - [x] Fallback up/down controls for non-drag environments

- [x] **Task 6: Run full test suite, verify no regressions**
  - [x] All 52 existing tests pass (0 failures)
  - [x] PHP syntax checks pass on all new/modified files

### Review Findings

- [x] [Review][Patch] Add/edit handlers sanitize input before persistence, causing HTML-encoded values to be stored [public/admin/league-list/index.php]
- [x] [Review][Patch] Non-drag reorder fallback is missing in rendered UI despite JS handlers for up/down controls [public/admin/league-list/index.php]
- [x] [Review][Patch] Reorder POST accepts tamperable ordered_ids without server-side set validation against active IDs [public/admin/league-list/index.php]

---

## Dev Notes

### Architecture Context
- Follows same admin page bootstrap pattern as `public/admin/teams/index.php`
- Uses `admin_bootstrap.php` include for auth guard + CSRF token
- PRG pattern: POST → process → redirect → GET display
- Flash messages: `$_SESSION['flash_success']` / `$_SESSION['flash_error']`, read-and-clear on render
- CSRF validated on all POST actions via `Auth::validateCSRFToken()`
- `ActivityLogger::log()` called from page file ONLY for admin action events (admin UI layer, not a service method)

### Page Structure
- Bootstrap 5 cards layout
- Active entries: Bootstrap table with drag handle column, name, edit button, deactivate button
- Deactivated entries: Separate Bootstrap table, muted/strikethrough row, reactivate button
- "Add League" form: inline form at top with display_name input + submit

### JS (admin-league-list.js)
- jQuery UI Sortable (already available via CDN in admin pages) on `#active-leagues-table tbody`
- On `stop` event (drag complete): serialize item IDs into hidden field `#ordered-ids`
- "Save Order" button: submit the reorder form
- Up/Down arrow buttons per row as non-JS fallback for accessibility

### Nav Addition
- Add "League List" link to admin sidebar or nav; check `includes/admin_header.php` structure

---

## Dev Agent Record

### Implementation Plan
Create `public/admin/league-list/index.php` following the existing admin page pattern. Implement all CRUD POST handlers with PRG. Add jQuery UI Sortable for drag-reorder. Add nav link.

### Debug Log

### Completion Notes
- Admin page created at `public/admin/league-list/index.php` following existing admin page patterns
- All 5 POST actions (add, edit, deactivate, reactivate, reorder) implemented with PRG pattern + CSRF
- Flash messages read-and-clear from session on GET
- `ActivityLogger` events recorded for all 5 admin actions
- SortableJS (CDN) used instead of jQuery UI — lighter dependency, same UX-DR13 requirement
- "Save Order" button only enabled when drag order actually changes from original
- Nav link added to Management dropdown in `includes/nav.php` under League Setup section
- Active dropdown detection updated to include `league-list` directory
- 52/52 unit tests pass, no regressions

---

## File List

- `public/admin/league-list/index.php` — new
- `public/assets/js/admin-league-list.js` — new
- `includes/nav.php` — modified (add League List nav item to Management dropdown)
- `_bmad-output/implementation-artifacts/2-2-admin-league-list-management-page.md` — new (this file)

---

## Change Log
- 2026-05-05: Story file created, status set to backlog.
- 2026-05-05: Implementation complete. All tasks done, 52/52 tests pass. Status set to review.
