# Story 7.4: Authenticated Access to Rules & Contact Directory

**Status:** ready-for-dev
**Epic:** 7 — Coach Profile, Team Schedule & Authenticated Resources
**Story Key:** 7-4-authenticated-access-rules-contact-directory

---

## Story

As an authenticated coach,
I want to access the league rules documents and contact directory after logging in,
so that I can find the information I need without the shared password.

---

## Acceptance Criteria

**AC1: rules.php requires authentication and shows documents**
**Given** an authenticated user navigates to `public/coaches/rules.php`
**When** the page loads
**Then** `PermissionGuard::requireRole('user')` is enforced
**And** the page displays a card for each document from the `documents` table (`is_public = 1`), with title, description, and a Download link to `../../uploads/documents/{filename}`
**And** if no documents exist: `alert alert-info` "No documents have been uploaded yet. Check back soon."

**AC2: contacts.php requires authentication — content unchanged**
**Given** an authenticated user navigates to `public/coaches/contacts.php`
**When** the page loads
**Then** `PermissionGuard::requireRole('user')` is enforced
**And** the existing contacts page content is displayed exactly as before (team directory, league officials, guidelines)

**AC3: Unauthenticated access redirects to login with intended URL (FR-RESOURCES-4)**
**Given** an unauthenticated user visits either page directly
**When** the page loads
**Then** `coach_bootstrap.php` → `Auth::requireCoach()` fires first, stores `$_SESSION['intended_url'] = $_SERVER['REQUEST_URI']`, and redirects to login
**And** after successful login, `login.php` reads and clears `intended_url` and redirects the user to the originally requested page

---

## Tasks / Subtasks

- [ ] **Task 1: Create `public/coaches/rules.php`**
  - [ ] Bootstrap (env-loader pattern — same as schedule-change.php):
    ```php
    require_once __DIR__ . '/../../includes/env-loader.php';
    require_once EnvLoader::getPath('includes/coach_bootstrap.php');
    require_once EnvLoader::getPath('includes/PermissionGuard.php');
    ```
  - [ ] Auth: `PermissionGuard::requireRole('user', '/coaches/login.php')`
  - [ ] `$db = Database::getInstance(); $userId = (int) ($_SESSION['coach_user_id'] ?? 0);`
  - [ ] Load nav vars:
    ```php
    $user = $db->fetchOne('SELECT first_name, last_name FROM users WHERE id = :id', ['id' => $userId]);
    $teamRow = $db->fetchOne('SELECT t.team_name FROM teams t JOIN team_owners o ON t.team_id = o.team_id WHERE o.user_id = :id LIMIT 1', ['id' => $userId]);
    $coachName = htmlspecialchars(trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')));
    $teamName  = htmlspecialchars((string) ($teamRow['team_name'] ?? ''));
    ```
  - [ ] Load documents: `$documents = $db->fetchAll('SELECT document_id, title, description, filename, original_filename, file_size, file_type, upload_date FROM documents WHERE is_public = 1 ORDER BY upload_date DESC', []);`
  - [ ] `$pageTitle = 'Rules & Regulations — District 8 Travel League'`
  - [ ] HTML: Bootstrap 5.1.3 + FA 6.0.0 CDNs (same as other coach pages); include `coaches_nav.php`
  - [ ] If `empty($documents)`: render `<div class="alert alert-info">No documents have been uploaded yet. Check back soon.</div>`
  - [ ] Else: render each document as a Bootstrap card in a `row row-cols-1 row-cols-md-3 g-3` grid:
    ```
    card body:
      h5.card-title = doc['title']
      p.card-text.text-muted = doc['description'] (if non-empty)
      small.text-muted = "Uploaded {upload_date formatted}"
      a.btn.btn-primary.btn-sm target="_blank" href="../../uploads/documents/{filename}" = "Download"
    ```
  - [ ] Filename in href: `htmlspecialchars($doc['filename'])` — do NOT use `rawurlencode` (filenames in the DB are already safe; match the pattern from `index.php`)
  - [ ] Inline footer + Bootstrap JS CDN at bottom

- [ ] **Task 2: Update `public/coaches/contacts.php`**
  - [ ] Replace the old try/catch bootstrap block with env-loader pattern:
    ```php
    require_once __DIR__ . '/../../includes/env-loader.php';
    require_once EnvLoader::getPath('includes/coach_bootstrap.php');
    require_once EnvLoader::getPath('includes/PermissionGuard.php');
    ```
  - [ ] Replace `Auth::requireCoach()` with `PermissionGuard::requireRole('user', '/coaches/login.php')`
  - [ ] Replace the public nav include (`include '../../includes/nav.php'`) with `coaches_nav.php`:
    - Add nav var setup before include: `$coachName`, `$teamName` (same pattern as rules.php)
    - `include __DIR__ . '/../../includes/coaches_nav.php'`
  - [ ] All other content — the filter form, team contacts table, league officials section, guidelines card — **unchanged**

- [ ] **Task 3: Verify**
  - [ ] `php tests/unit/run-unit-tests.php` — full suite passes (no unit tests for these pages, just regression check)
  - [ ] Manual: visit rules.php unauthenticated → redirects to login; login → lands on rules.php (intended_url working)
  - [ ] Manual: visit contacts.php authenticated → same content as before, coaches nav now shown
  - [ ] Manual: rules.php with at least one document in DB → card renders with working Download link

---

## Dev Notes

### FR-RESOURCES-4 (intended URL) is already satisfied by coach_bootstrap.php

The original story draft suggested PermissionGuard needs to store `intended_url`. It doesn't — that's already handled. The flow is:

1. Unauthenticated user visits `/public/coaches/rules.php`
2. `coach_bootstrap.php` runs → calls `Auth::requireCoach()` (`includes/auth.php:137`)
3. `Auth::requireCoach()` sets `$_SESSION['intended_url'] = $_SERVER['REQUEST_URI']` and redirects to login
4. After login, `login.php` (line 89-90) reads and clears `intended_url` and redirects back

PermissionGuard only runs for authenticated users who fail the role check — that case doesn't apply here since 'user' accepts any coach session. No changes to PermissionGuard or login.php are needed for this story.

### Document file path — matches index.php pattern exactly

`index.php` serves documents with:
```php
href="<?php echo dirname($_SERVER['SCRIPT_NAME']); ?>/../uploads/documents/<?php echo sanitize($doc['filename']); ?>"
```
From `public/coaches/rules.php`, the equivalent relative path is `../../uploads/documents/{filename}`. Use a simple hardcoded relative path rather than `dirname($_SERVER['SCRIPT_NAME'])` to keep the coach page consistent with other coach pages:
```php
href="../../uploads/documents/<?= htmlspecialchars($doc['filename']) ?>"
```

### contacts.php — minimal surgical changes only

The existing `contacts.php` is 220+ lines of working content. Only three things change:
1. The bootstrap block (6 lines) → env-loader pattern (3 lines)
2. `Auth::requireCoach()` → `PermissionGuard::requireRole('user', '/coaches/login.php')`
3. The nav include → coaches_nav.php (requires adding `$coachName`/`$teamName` vars before it)

Do not touch the SQL queries, filter logic, grouping logic, or any HTML below the nav. The existing contacts content is correct and complete.

### Nav change in contacts.php — coaches_nav, not nav.php

`contacts.php` currently includes the public `nav.php`. Since the page is now authenticated-only and part of the coach portal, switch to `coaches_nav.php`. This is consistent with every other coach-authenticated page (score-input, schedule-change, profile, schedule). Set `$coachName` and `$teamName` the same way as rules.php Task 1 above.

### No unit tests — these are UI pages with no service layer

Both pages are read-only UI pages with no new service classes. Run the full unit test suite to confirm no regressions, but no new test files are needed.

### documents table query — is_public flag only

The documents table has `is_public BOOLEAN DEFAULT TRUE`. The rules page shows all public documents:
```sql
SELECT document_id, title, description, filename, original_filename,
       file_size, file_type, upload_date
FROM documents
WHERE is_public = 1
ORDER BY upload_date DESC
```
Do not add a category or type filter — the admin document management system doesn't have document categories. Show everything that's marked public.

---

## Files

| File | Action |
|------|--------|
| `public/coaches/rules.php` | NEW |
| `public/coaches/contacts.php` | UPDATE (bootstrap swap + PermissionGuard + nav swap; content unchanged) |

**Depends on:** Story 7.2 (PermissionGuard role-hierarchy update for `requireRole('user')`)

---

## Dev Agent Record

### Agent Model Used

claude-sonnet-4-6

### Debug Log References

### Completion Notes List

### File List
