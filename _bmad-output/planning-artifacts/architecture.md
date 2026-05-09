---
stepsCompleted: [1, 2, 3, 4, 5, 6, 7, 8]
lastStep: 8
status: 'complete'
completedAt: '2026-05-03'
workflowType: 'architecture'
project_name: 'league-manager'
user_name: 'Mike'
date: '2026-05-03'
inputDocuments:
  - _bmad-output/planning-artifacts/prd.md
  - docs/architecture.md
  - docs/data-models.md
  - docs/Features/user-accounts/user-accounts-implementation.md
  - _bmad-output/project-context.md
---

# Architecture Decision Document — District 8 Travel League: Individual Coach Logins

_This document builds collaboratively through step-by-step discovery. Sections are appended as we work through each architectural decision together._

---

## Project Context Analysis

### Requirements Overview

**Functional Requirements — 87 FRs across 15 groups:**

| FR Group | Count | Architectural Weight |
|----------|-------|---------------------|
| FR-AUTH | 7 | Session auth, lockout, CAPTCHA, remember-me |
| FR-REG | 12 | Multi-step registration form; email verification flow |
| FR-LEAGUELIST | 5 | Admin-managed reference data; dropdown population |
| FR-INV | 5 | Token-based invitation system; expiration management |
| FR-TOGGLE | 4 | Feature flag controlling registration URL access |
| FR-TEAMREG | 12 | Multi-step sub-flow within self-registration; pending approval queue |
| FR-ASSIGN | 7 | Admin CRUD on user↔team relationships |
| FR-SCORE | 7 | Team-scoped, time-gated score submission; standings update |
| FR-RESCHED | 7 | Team-scoped reschedule request with status lifecycle |
| FR-RESOURCES | 4 | Authenticated access to existing document/directory pages |
| FR-USERMGMT | 9 | Full admin user CRUD + pre-cutover checklist + credential disable |
| FR-PROFILE | 7 | Self-service profile edit; self-service password change |
| FR-COACHSCHEDULE | 6 | Filtered/sortable team-scoped schedule view |
| FR-RESTRICTIONS | 7 | Server-side permission enforcement across all coach actions |
| FR-LEAGUELIST (home fields) | — | Locations pool feeding existing `locations` table |

**Non-Functional Requirements — key architectural drivers:**

| NFR | Architectural Implication |
|-----|--------------------------|
| NFR-SEC-1–6 | bcrypt passwords; CSRF on all state-change forms; parameterized queries; session token rotation on privilege change; registration URL not crawlable |
| NFR-PERF-1–4 | Sub-2s login; sub-3s score/dashboard/registration; email within 5 min — all on shared hosting |
| NFR-COMPAT-1–3 | Mobile-first (≥375px), Chrome/Firefox/Safari, PHP 8.1 on cPanel ea-php81 |
| NFR-ACCESS-1 | WCAG 2.1 AA on all data-entry pages |
| NFR-AVAIL-1 | Dual auth systems must not degrade each other (resolved: legacy deprecated immediately — see decisions below) |

**Scale & Complexity:**

- Primary domain: PHP monolith web application, shared hosting
- Complexity level: Medium — significant new feature surface, layered into an existing well-structured codebase
- Estimated new service classes: ~8 (RegistrationService, TeamRegistrationService, InvitationService, LeagueListManager, CoachScheduleService, ProfileService, CaptchaService, CutoverService)

### Technical Constraints & Dependencies

**Hard constraints (non-negotiable):**
- PHP 8.1, cPanel `ea-php81`, shared hosting — no daemons, no Redis, no queues
- PDO only via `Database::getInstance()` — no new MySQLi
- Bootstrap 5 + jQuery + vanilla JS — no Node bundler
- All new classes in `D8TL\` namespace under `includes/`
- Entry points in `public/coaches/` (coach-facing) and `public/admin/` (admin-facing)

**Existing infrastructure to leverage:**
- `AuthService` + `LegacyAuthManager` + `UserAccountManager` — partially implemented; LegacyAuthManager will be removed (see decisions)
- `EmailService` (PHPMailer, template-driven) — ready for new email templates
- `users`, `roles`, `permissions`, `role_permissions`, `team_owners`, `user_invitations` tables — exist in schema
- `locations` table — home field entries go here
- `teams` table — new team registrations are rows here with `pending` status
- `settings` table — feature toggle + legacy credential disable stored here
- `activity_log` — audit trail already in place

**New schema additions needed:**
- `league_list` table — admin-managed reference data for registration dropdown
- `pending` status on `teams` — for FR-TEAMREG approval queue (or a `team_registrations` bridge table)
- `login_attempts` table — for FR-AUTH-4/7 lockout + CAPTCHA trigger (with lazy-purge strategy)
- `remember_tokens` table — for FR-AUTH-5 persistent sessions

### Cross-Cutting Concerns

1. **Permission enforcement** — FR-RESTRICTIONS-1–7 must be server-side checked on every coach-accessible endpoint; single `PermissionGuard::check()` call at file top, not scattered UI hiding
2. **Team-scoping** — `getScopedTeams($userId)` utility, returns array always; DB `UNIQUE` constraint enforces 1:1 for this iteration
3. **CAPTCHA** — needed on login (conditional after 3 failures) and registration (unconditional); single integration point, must degrade gracefully if third-party unreachable
4. **Email notifications** — 8 distinct events; 3 are hard blockers (verification, invitation, password reset), 5 are operational notifications
5. **Feature toggle** — reads from `settings` table; must be cache-free (effective within 1 page load)
6. **Time-gating** — score submission and schedule display both compare server time vs. game date+time; single `GameTimeGate::isEligible()` utility

---

## Technical Foundation (Brownfield Baseline)

_This is an existing codebase. No starter initialization is required. The baseline below documents the established technical stack that all new work extends._

### Existing Stack

| Layer | Technology | Version | Notes |
|-------|-----------|---------|-------|
| Runtime | PHP | 8.1 (`ea-php81`) | Production constraint; match local selector |
| Package manager | Composer | — | PSR-4: `D8TL\\` → `includes/`; `district8/travel-league-mvp` |
| Database | MariaDB/MySQL | — | PDO via `Database::getInstance()` prepared statements only |
| Mail | PHPMailer | ^6.8 | SMTP; template-driven via `EmailService` |
| Web server | Apache | — | Docroot `public/`; `.htaccess` rewrites |
| Frontend | Bootstrap 5 + jQuery | — | No Node bundler; CDN/local assets |
| Local dev | `php -S localhost:8000 -t public/` | — | |
| Deployment | cPanel Git + `.cpanel.yml` | — | Shared hosting (A Small Orange–style) |

### Established Architectural Patterns

- **Layered server pages:** `public/**/*.php` (presentation + thin orchestration) → `includes/*.php` (services, auth, email, helpers) → PDO database
- **Bootstrap chain:** `includes/bootstrap.php` → security, config, compat shims, database, auth, functions, session
- **Separate entry trees:** `public/admin/` (admin portal), `public/coaches/` (coach portal), `public/` (public pages + auth flows)
- **Service classes:** PascalCase in `includes/` under `D8TL\` namespace; procedural/legacy remains in lower/snake includes

### Key Pre-Existing Services

| Class | Location | Role |
|-------|----------|------|
| `AuthService` | `includes/` | Unified auth — routes new vs. legacy sessions |
| `LegacyAuthManager` | `includes/` | Shared-password wrapper — **to be removed** |
| `UserAccountManager` | `includes/` | New user account operations |
| `EmailService` | `includes/EmailService.php` | PHPMailer wrapper; template-driven |
| `Database` | `includes/database.php` | PDO singleton; `Database::getInstance()` |
| `TeamRelationshipManager` | `includes/` | User↔team assignment operations |

---

## Pre-Implementation Decisions (Party Mode Outcomes)

The following decisions were made collaboratively before architectural specification began. They are foundational and constrain all subsequent decisions.

### Decision 1: Legacy Auth Deprecated Immediately

**Decision:** `LegacyAuthManager` and the shared `coaches_password` credential are removed in the first implementation PR. No parallel operation period.

**Rationale:** No active production users exist; only test data is in production. Parallel operation doubles the permission enforcement surface and creates a permanent code smell. Ripping it out now makes `AuthController` → `SessionAuthenticator` a straight line with no branching.

**Impact:**
- Delete `LegacyAuthManager.php` and its bootstrap `require`
- Delete `LEGACY_SHARED_PASSWORD` constant from config and `.env.example`
- Remove `is_legacy_session` branch from session handling
- Net negative line count; all legacy test doubles deleted

### Decision 2: Team Scoping via `getScopedTeams($userId)` — Returns Array Always

**Decision:** A single `getScopedTeams(int $userId): array` utility provides the canonical answer to "which teams does this user own?" for all permission checks, score scoping, schedule scoping, and reschedule scoping.

**Rationale:** The team-scoping query appears in 3+ feature areas and will grow. Centralizing prevents inconsistent null-handling, missed enforcement points, and future refactor debt.

**Implementation:**
```php
public function getScopedTeams(int $userId): array
{
    $stmt = $this->pdo->prepare(
        'SELECT t.* FROM teams t
         INNER JOIN team_owners o ON o.team_id = t.id
         WHERE o.user_id = :user_id'
    );
    $stmt->execute(['user_id' => $userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
```

Returns array always — even with 1:1 constraint active. Callers take `[0]` for a single value. This keeps future relaxation of the 1:1 constraint to a one-line change per caller, not a refactor.

Session stores resolved `teamId` post-login.

### Decision 3: 1:1 User-to-Team Constraint (This Iteration)

**Decision:** One user account maps to exactly one team for this iteration. Enforced at both the DB layer and application layer.

**DB enforcement:** `UNIQUE(user_id)` on `team_owners` table. PDO throws `PDOException` (SQLSTATE `23000`) on violation — no application bug can bypass it.

**Application enforcement:** Single `TeamAlreadyClaimedException` class; thrown from both `RegistrationService` and `TeamAssignmentService` before any INSERT:
```php
if ($this->userRepo->findByTeamId($teamId) !== null) {
    throw new TeamAlreadyClaimedException($teamId);
}
```

**Future note:** The `getScopedTeams` query layer is intentionally written to return a collection so relaxing this constraint in a future sprint requires no query-layer changes — only removing the `UNIQUE` constraint and the app-layer guard.

### Decision 4: Permission Enforcement via `PermissionGuard::check()` at File Top

**Decision:** Every coach-accessible endpoint calls `PermissionGuard::check()` as the first executable line after bootstrap. No magic, no base controller. Grep-able and auditable.

**Rationale:** PHP monolith without a router means middleware is not available as a pattern. File-top enforcement is the most explicit, testable, and reviewable option. Every endpoint: one line, one audit trace.

### Decision 5: PHPMailer Retained; Email Events Classified by Blocking Status

**Decision:** PHPMailer stays. No queue infrastructure. Email events classified:

| # | Event | Type | Failure behavior |
|---|-------|------|-----------------|
| 1 | Email verification link | **Blocker** | User sees error + resend option |
| 2 | Invitation link | **Blocker** | Admin sees failure; resend available |
| 3 | Password reset link | **Blocker** | User sees error + retry option |
| 4 | Admin: new account verified | Operational | Logged; silent failure acceptable |
| 5 | Admin: new team submission | Operational | Logged; silent failure acceptable |
| 6 | Coach: registration approved / Team Owner assigned | Operational | Logged; silent failure acceptable |
| 7 | Coach: team assigned or removed | Operational | Logged; silent failure acceptable |
| 8 | Reschedule/cancellation → umpires (+recipients TBD) | **Must-have operational** | Logged; admin can re-trigger |

**Open thread (deferred):** Reschedule notification recipient list — umpires confirmed; coaches and/or opposing team contacts to be confirmed before FR-RESCHED stories are written. Architecture is unchanged (dynamic recipient list on the same email event).

### Decision 6: `login_attempts` Table with Lazy Purge

**Decision:** Brute-force protection and CAPTCHA trigger are stateful in the `login_attempts` DB table. No daemon required. Lazy cleanup on each login attempt:

```sql
DELETE FROM login_attempts
WHERE created_at < NOW() - INTERVAL 24 HOUR
LIMIT 100;
```

Executed inline before each insert. Keeps table bounded without requiring a cron daemon.

---

## Core Architectural Decisions

### Decision Priority Analysis

**Critical Decisions (block implementation):**
- Team pending state model (1a) — affects every query touching `teams`
- Home field location staging model (1b) — affects registration flow and `locations` table
- CAPTCHA provider (2) — required before registration/login pages can be built
- Schema migration convention (3) — must be set before any DDL is written

**Important Decisions (shape architecture):**
- Frontend JS approach (4) — affects all new form pages
- Logging scope (5) — affects `activity_log` write points across all new services

**Deferred Decisions (post this feature):**
- Reschedule notification full recipient list (umpires confirmed; coaches/opposing team TBD before FR-RESCHED stories)
- 1:1 team constraint relaxation (future sprint when multi-team ownership is needed)
- Cron-based `login_attempts` / `remember_tokens` cleanup (acceptable as lazy purge for now; revisit if table growth becomes measurable)

---

### Data Architecture

#### 1a — Team Registration Pending State: `status` column on `teams`

**Decision:** Add a `status` column to the existing `teams` table.

**Values:** `pending` | `active` | `inactive`

**Rationale:** Simpler than a separate bridge table. One table to query, one migration, no approval-copy step. Risk of existing code accidentally including `pending` teams is mitigated by ensuring all queries that should return only active teams explicitly filter `WHERE status = 'active'` — this is enforced as a code review gate.

**Migration file:** `database/migrations/003_add_teams_status_column.sql`

```sql
ALTER TABLE teams
  ADD COLUMN status ENUM('pending','active','inactive') NOT NULL DEFAULT 'active'
  AFTER division_id;

UPDATE teams SET status = 'active' WHERE status = 'active'; -- no-op; existing rows default correctly
```

**Impact:** All existing queries that read `teams` must be audited and updated to add `WHERE teams.status = 'active'` where appropriate. This audit is a mandatory task in the first FR-TEAMREG implementation story.

#### 1b — Home Field Locations: Insert directly into `locations` table

**Decision:** Coaches' submitted home field locations are inserted directly into the existing `locations` table with a `submitted_by_user_id` FK and initial `status = 'pending'`.

**Rationale:** Reuses existing infrastructure; no new staging table. Admin can review and activate locations from the existing locations management UI (or a new review queue). Avoids a two-table promotion flow.

**New columns on `locations`:**
```sql
ALTER TABLE locations
  ADD COLUMN submitted_by_user_id INT UNSIGNED NULL,
  ADD COLUMN status ENUM('pending','active','inactive') NOT NULL DEFAULT 'active',
  ADD CONSTRAINT fk_locations_submitted_by
    FOREIGN KEY (submitted_by_user_id) REFERENCES users(id) ON DELETE SET NULL;
```

**Migration file:** `database/migrations/004_add_locations_submission_columns.sql`

**Impact:** Coach-submitted locations default to `pending`; admin review activates them. All scheduling queries that pull available locations must filter `WHERE locations.status = 'active'`.

#### New Tables Required

**Migration file naming convention:** `database/migrations/NNN_description.sql` (zero-padded, sequential, manually applied)

| # | File | Table / Change | Purpose |
|---|------|---------------|---------|
| 001 | `001_add_league_list.sql` | `league_list` | Admin-managed dropdown entries for registration form |
| 002 | `002_add_login_attempts.sql` | `login_attempts` | Brute-force tracking + CAPTCHA trigger |
| 003 | `003_add_teams_status_column.sql` | `teams.status` | Pending/active/inactive team state |
| 004 | `004_add_locations_submission_columns.sql` | `locations.submitted_by_user_id`, `locations.status` | Coach-submitted home fields |
| 005 | `005_add_remember_tokens.sql` | `remember_tokens` | FR-AUTH-5 persistent session |
| 006 | `006_remove_legacy_auth.sql` | `settings.coaches_password` disable | Formal deprecation record |

**`league_list` table schema:**
```sql
CREATE TABLE league_list (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  display_name VARCHAR(100) NOT NULL,
  sort_order INT UNSIGNED NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_league_list_active_order (is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**`login_attempts` table schema:**
```sql
CREATE TABLE login_attempts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  identifier VARCHAR(255) NOT NULL,  -- username or email submitted
  ip_address VARCHAR(45) NOT NULL,
  attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_login_attempts_ip_time (ip_address, attempted_at),
  INDEX idx_login_attempts_identifier_time (identifier, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**`remember_tokens` table schema:**
```sql
CREATE TABLE remember_tokens (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  token_hash VARCHAR(64) NOT NULL,  -- SHA-256 of the raw token stored in cookie
  expires_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_remember_token (token_hash),
  INDEX idx_remember_tokens_user (user_id),
  CONSTRAINT fk_remember_tokens_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

### Authentication & Security

#### 2 — CAPTCHA: Google reCAPTCHA v2

**Decision:** Google reCAPTCHA v2 ("I'm not a robot" checkbox).

**Rationale:** Well-established, free tier sufficient, simple HTML embed + server-side token verify via PHP `curl`/`file_get_contents`. Consistent with shared hosting constraints. No server-side daemon required.

**Integration points:**
- **Registration form** (`public/register.php` or equivalent): reCAPTCHA widget always rendered; server-side token verification before account creation (FR-REG-10)
- **Login page** (`public/coaches/login.php`): reCAPTCHA widget revealed via JS after 3 failed attempts from the same IP; server-side verification required before further attempts accepted (FR-AUTH-7)

**Graceful degradation:** If Google's verification endpoint is unreachable, log the failure and allow the request through (fail-open). Bot traffic is still deterred by the client-side widget; silent backend failure should not block legitimate users on shared hosting where outbound HTTP latency is variable.

**Configuration:** reCAPTCHA site key + secret key stored in `config.php` / `config.prod.php` as constants. Not committed to version control.

**PHP verification:**
```php
function verifyCaptcha(string $token): bool {
    $response = file_get_contents(
        'https://www.google.com/recaptcha/api/siteverify?secret='
        . RECAPTCHA_SECRET . '&response=' . urlencode($token)
    );
    $data = json_decode($response, true);
    return $data['success'] ?? false;
}
```

---

### Schema Migration Convention

#### 3 — Numbered Sequential Migration Files

**Decision:** `database/migrations/NNN_description.sql` — zero-padded sequential numbering, manually applied via cPanel phpMyAdmin or SSH.

**Rationale:** Consistent with existing ad-hoc `.sql` file pattern but adds explicit ordering and traceability. No new tooling required. Migrations are append-only; never modify an existing migration file once applied.

**Convention rules:**
- Filename: `NNN_snake_case_description.sql` (e.g., `001_add_league_list.sql`)
- Each file is idempotent where possible (use `IF NOT EXISTS`, `IF EXISTS`)
- Each file begins with a comment block: date, author, description, affected tables
- Applied in numeric order; record applied migrations in a `schema_migrations` tracking table (simple `version` + `applied_at` columns)

**`schema_migrations` tracking table** (migration 000):
```sql
CREATE TABLE IF NOT EXISTS schema_migrations (
  version VARCHAR(20) NOT NULL PRIMARY KEY,
  applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

### Frontend Architecture

#### 4 — Server-Side Rendered PHP with Progressive JS Enhancement

**Decision:** All new pages are server-rendered PHP (consistent with existing project). JavaScript enhancement is added via a dedicated file per feature area rather than inline scripts.

**JS file convention:**
- `public/assets/js/coaches-registration.js` — registration + team registration form behaviors
- `public/assets/js/admin-league-list.js` — league list drag-reorder and CRUD interactions
- `public/assets/js/coaches-schedule.js` — schedule sort/filter interactions

**Behaviors requiring JS:**
- "Other" league dropdown → reveal free-text field (FR-REG-11)
- Team name auto-preview as read-only display (FR-TEAMREG-3)
- Home field repeater: add/remove up to 5 location entry blocks (FR-TEAMREG-5)
- Login CAPTCHA: reveal widget after 3 failed attempts (FR-AUTH-7) — requires a server-side failed-attempt count passed to the page
- Schedule table sort + filter (FR-COACHSCHEDULE-3/4)
- League list drag-reorder (FR-LEAGUELIST-3)

**Pattern:** jQuery for DOM manipulation (already in stack). No additional libraries introduced.

**CSRF:** All POST forms include `<?= csrfTokenField() ?>`. All state-changing AJAX calls (if any) pass CSRF token in a request header or POST body.

---

### Infrastructure & Deployment

No changes to hosting, deployment, or CI/CD infrastructure. Feature deploys via existing cPanel Git + `.cpanel.yml` workflow.

Environment-specific config (reCAPTCHA keys, SMTP credentials) added to `config.prod.php` and `config.staging.php` following existing pattern. Keys are never committed to the repository.

---

### Observability & Logging

#### 5 — Full Logging Scope

**Decision:** All events across the feature are logged to `activity_log`.

**Rationale:** Small team, no dedicated monitoring infrastructure. Full logging provides the only audit trail for accountability (which is the primary business driver of this feature — replacing "the password did it" with individual attribution).

**Events logged to `activity_log`:**

| Category | Event | Logged fields |
|----------|-------|---------------|
| Auth | Login (success) | user_id, ip, timestamp |
| Auth | Login (failure) | identifier attempted, ip, timestamp |
| Auth | Account lockout triggered | ip, identifier, timestamp |
| Auth | Logout | user_id, ip, timestamp |
| Auth | Password reset requested | user_id or email, ip |
| Auth | Password reset completed | user_id |
| Auth | Remember-me token issued | user_id |
| Registration | Account created (email verified) | user_id, timestamp |
| Registration | Invitation sent | admin_user_id, recipient_email |
| Registration | Invitation accepted | user_id, invitation_id |
| Team | Team registration submitted | user_id, team_id (pending) |
| Team | Team registration approved | admin_user_id, team_id, user_id |
| Team | Team Owner assigned | admin_user_id, user_id, team_id |
| Team | Team Owner removed | admin_user_id, user_id, team_id |
| Score | Score submitted | user_id, game_id, scores, timestamp |
| Score | Score edited | user_id, game_id, old/new scores |
| Reschedule | Request submitted | user_id, game_id, proposed date/time |
| Reschedule | Request cancelled by coach | user_id, request_id |
| Profile | Name fields updated | user_id, changed fields (no values) |
| Profile | Phone updated | user_id |
| Profile | Password changed (self-service) | user_id |
| Admin | User account disabled/enabled | admin_user_id, target_user_id |
| Admin | User account deleted | admin_user_id, target_user_id |
| Admin | Shared credential disabled | admin_user_id, timestamp |
| Admin | Registration toggle changed | admin_user_id, new_state |
| Admin | League list entry created/edited/deactivated | admin_user_id, entry_id |

**Implementation:** A single `ActivityLogger::log(string $event, array $context): void` static method wraps all inserts. Called from service classes, not from page files.

---

### Decision Impact Analysis

**Implementation sequence (decisions must be applied in this order):**

1. Migration `000` — create `schema_migrations` tracking table
2. Migration `001–006` — apply all schema changes before any feature code ships
3. Remove `LegacyAuthManager` + shared credential — first code PR, clears the auth surface
4. Implement `PermissionGuard`, `getScopedTeams`, `GameTimeGate`, `ActivityLogger` — cross-cutting utilities; all feature services depend on these
5. Implement `LeagueListManager` + admin UI — registration form depends on it
6. Implement `RegistrationService` + `InvitationService` — depends on league list and CAPTCHA
7. Implement `TeamRegistrationService` — depends on registration service
8. Implement remaining coach features (score, reschedule, schedule, profile) — depend on auth + team scoping
9. Implement `CutoverService` (shared credential disable, pre-cutover checklist) — last; depends on all coach onboarding being testable

**Cross-component dependencies:**

```
league_list table
    └── RegistrationService (reads for dropdown)
    └── TeamRegistrationService (reads for team name generation)

getScopedTeams($userId)
    └── ScoreService (filters games)
    └── RescheduleService (filters games)
    └── CoachScheduleService (filters schedule)
    └── PermissionGuard (validates team ownership)

ActivityLogger
    └── All service classes (write-only dependency)

PermissionGuard
    └── Every public/coaches/*.php entry point

teams.status column
    └── TeamRegistrationService (sets pending)
    └── Admin approval flow (sets active)
    └── All existing team queries (must add WHERE status = 'active' filter)

locations.status + submitted_by_user_id
    └── TeamRegistrationService (writes pending locations)
    └── Admin location review (activates)
    └── All scheduling queries (must filter WHERE status = 'active')
```

---

## Implementation Patterns & Consistency Rules

### Naming Patterns

**Database — `snake_case` everywhere:**

| Element | Convention | Example |
|---------|-----------|---------|
| Tables | plural, lowercase, snake_case | `league_list`, `login_attempts`, `remember_tokens` |
| Columns | singular, lowercase, snake_case | `user_id`, `is_active`, `submitted_by_user_id` |
| Foreign keys | `{referenced_table_singular}_id` | `user_id`, `team_id`, `game_id` |
| Indexes | `idx_{table}_{columns}` | `idx_login_attempts_ip_time` |
| Unique constraints | `uq_{table}_{column}` | `uq_remember_token` |
| FK constraints | `fk_{table}_{column}` | `fk_locations_submitted_by` |

**PHP classes — PascalCase under `D8TL\` namespace:**

| Type | Convention | Example |
|------|-----------|---------|
| Services | `{Domain}Service` | `RegistrationService`, `TeamRegistrationService` |
| Managers | `{Domain}Manager` | `LeagueListManager` |
| Utilities / Guards | `{Name}` | `PermissionGuard`, `GameTimeGate`, `ActivityLogger` |
| Exceptions | `{Description}Exception` | `TeamAlreadyClaimedException` |
| Repositories | `{Entity}Repository` | `UserRepository` |

**File naming:**
- PHP class files: match class name exactly (`RegistrationService.php`)
- Page files: lowercase kebab-case (`public/coaches/register.php`, `public/admin/league-list.php`)
- JS files: kebab-case, scope-prefixed (`coaches-registration.js`, `admin-league-list.js`)
- Migration files: `NNN_snake_case_description.sql` (`001_add_league_list.sql`)

---

### Structure Patterns

| Artifact type | Location |
|--------------|---------|
| New service / manager / utility classes | `includes/{ClassName}.php` (flat, no subdirectories) |
| Coach-facing pages | `public/coaches/{feature}.php` |
| Admin-facing pages | `public/admin/{feature}.php` |
| Shared auth / account pages | `public/{feature}.php` |
| JS enhancements | `public/assets/js/{scope}-{feature}.js` |
| Unit tests | `tests/unit/` |
| Integration / functional tests | `tests/` root |
| Schema migrations | `database/migrations/NNN_description.sql` |

---

### Format Patterns

**Form submission — POST/Redirect/Get (PRG) always:**

Every POST handler ends with a redirect, never renders HTML after POST:
```php
// On failure:
$_SESSION['flash_error'] = 'Message here';
header('Location: page.php'); exit;

// On success:
header('Location: success-page.php'); exit;
```

**Flash messages — session-based, read-and-clear on render:**
```php
$error = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_error']);
```

**Date/time — store UTC, display in configured league timezone:**
- All `DATETIME` columns: UTC
- PHP `date_default_timezone_set('UTC')` in bootstrap
- Conversion to display timezone at render time only

**DB boolean columns — `TINYINT(1)` with `1`/`0`:** Never `true`/`false` strings or `yes`/`no`

**Status values — lowercase strings in `ENUM`:** `'pending'`, `'active'`, `'inactive'`, `'completed'`, `'cancelled'`

---

### Process Patterns

**Permission check — first executable line after bootstrap on every protected page:**
```php
<?php
require_once '../../includes/bootstrap.php';
PermissionGuard::requireRole('team_owner'); // blocks and redirects if not authorized
```

**Team-scoped queries — always via `getScopedTeams()`:**
```php
// CORRECT:
$teams = $teamScope->getScopedTeams($_SESSION['user_id']);
$teamIds = array_column($teams, 'id');

// WRONG — never write this inline:
// JOIN team_owners ON team_owners.team_id = games.team_id WHERE team_owners.user_id = ?
```

**CSRF — every state-changing form:**
```php
// In form template:
<?= csrfTokenField() ?>

// In handler, before any DB write:
if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
    $_SESSION['flash_error'] = 'Invalid security token.';
    header('Location: ' . $_SERVER['HTTP_REFERER']); exit;
}
```

**Database access — `Database::getInstance()` only:**
```php
// CORRECT:
$pdo = Database::getInstance()->getConnection();

// WRONG:
// $db = new Database();
// $db = new mysqli(...);
```

**Activity logging — in service classes only, never in page files:**
```php
// CORRECT (inside RegistrationService::create()):
ActivityLogger::log('registration.account_created', ['user_id' => $userId]);

// WRONG (in public/register.php):
// ActivityLogger::log(...);
```

**Email — always via `EmailService`, never direct PHPMailer:**
```php
$emailService = new EmailService();
$emailService->sendEmail($to, $subject, 'template_name', $data);
```

**Blocking email failure (verification, invitation, password reset) — surface to user and log:**
```php
$sent = $emailService->sendEmail($to, $subject, 'verify_email', $data);
if (!$sent) {
    ActivityLogger::log('email.delivery_failed', ['template' => 'verify_email', 'to' => $to]);
    $_SESSION['flash_error'] = 'Account created but verification email failed. Please contact the administrator.';
    header('Location: register.php?step=verify'); exit;
}
```

**Operational email failure — log only, do not surface to user:**
```php
$emailService->sendEmail($adminEmail, $subject, 'admin_new_registration', $data);
// No failure check in page — EmailService logs internally
```

**Game time eligibility — always via `GameTimeGate::isEligible()`:**
```php
// CORRECT:
if (!GameTimeGate::isEligible($game)) { /* exclude from list */ }

// WRONG:
// if ($game['date'] < date('Y-m-d') || ...) { ... }
```

---

### Anti-Patterns — Agents Must Never Do These

| Anti-pattern | Correct approach |
|---|---|
| Inline SQL in page files | Move to service class method |
| `new Database()` or `new mysqli()` | `Database::getInstance()->getConnection()` |
| Role check via `$_SESSION['role']` in page | `PermissionGuard::requireRole()` at file top |
| Ad-hoc team filter `WHERE user_id = ?` on `team_owners` | `getScopedTeams($userId)` |
| Hardcoded league name strings | Query `league_list` table |
| Direct PHPMailer instantiation | `EmailService::sendEmail()` |
| Rendering HTML after a POST | PRG pattern — always redirect |
| `ActivityLogger::log()` called from page file | Call from service class only |
| Inline game time eligibility check | `GameTimeGate::isEligible($game)` |
| MySQLi usage anywhere in new code | PDO via `Database::getInstance()` |

---

## Project Structure & Boundaries

### Complete Project Directory Structure

```
league-manager/
│
├── includes/                          ← Service layer (flat, no subdirectories)
│   │
│   │   ── EXISTING (unchanged) ──
│   ├── auth.php
│   ├── bootstrap.php
│   ├── coach_bootstrap.php            ← Will add PermissionGuard call convention
│   ├── admin_bootstrap.php
│   ├── security_bootstrap.php
│   ├── database.php                   ← Database::getInstance() — never bypass
│   ├── EmailService.php               ← All email goes through here
│   ├── functions.php
│   ├── config.php / config.prod.php / config.staging.php
│   ├── Logger.php
│   │
│   │   ── TO BE REMOVED ──
│   ├── [LegacyAuthManager.php]        ← DELETE in first PR
│   │
│   │   ── NEW: Cross-cutting utilities ──
│   ├── PermissionGuard.php            ← requireRole(); called top of every protected page
│   ├── GameTimeGate.php               ← isEligible($game); score + schedule filtering
│   ├── ActivityLogger.php             ← log($event, $context); called from services only
│   ├── TeamScope.php                  ← getScopedTeams($userId); returns array always
│   │
│   │   ── NEW: Feature services (FR group → service) ──
│   ├── RegistrationService.php        ← FR-REG, FR-AUTH (account creation, verification)
│   ├── InvitationService.php          ← FR-INV (token generation, expiry, send)
│   ├── TeamRegistrationService.php    ← FR-TEAMREG (pending team submit, approval)
│   ├── LeagueListManager.php          ← FR-LEAGUELIST (CRUD on league_list table)
│   ├── ProfileService.php             ← FR-PROFILE (name, phone, password change)
│   ├── ScoreService.php               ← FR-SCORE (time-gated team-scoped submission)
│   ├── RescheduleService.php          ← FR-RESCHED (team-scoped request lifecycle)
│   ├── CoachScheduleService.php       ← FR-COACHSCHEDULE (filtered+sorted schedule)
│   ├── CutoverService.php             ← FR-USERMGMT-7/8/9 (checklist + credential disable)
│   └── UserManagementService.php      ← FR-USERMGMT-1–6 (admin user CRUD)
│
├── public/
│   │
│   ├── coaches/                       ← Coach portal entry points
│   │   │
│   │   │   ── EXISTING (modified) ──
│   │   ├── login.php                  ← Add CAPTCHA reveal after 3 failures (FR-AUTH-7)
│   │   ├── logout.php
│   │   ├── dashboard.php              ← Add team-scoped game list
│   │   ├── score-input.php            ← Add time-gate + team-scope (FR-SCORE)
│   │   ├── schedule-change.php        ← Add team-scope + cancel flow (FR-RESCHED)
│   │   ├── contacts.php               ← Gate with PermissionGuard (FR-RESOURCES)
│   │   │
│   │   │   ── NEW ──
│   │   ├── register.php               ← FR-REG, FR-REG-11/12 (self-registration form)
│   │   ├── verify-email.php           ← FR-REG-6/7 (verification link handler)
│   │   ├── team-register.php          ← FR-TEAMREG (program/season select, home fields)
│   │   ├── team-register-confirm.php  ← FR-TEAMREG-7 (confirmation screen)
│   │   ├── profile.php                ← FR-PROFILE (name, phone, password)
│   │   ├── schedule.php               ← FR-COACHSCHEDULE (team-scoped filtered view)
│   │   └── rules.php                  ← FR-RESOURCES-1/2 (authenticated doc access)
│   │
│   ├── admin/
│   │   │
│   │   │   ── EXISTING (unchanged unless noted) ──
│   │   ├── index.php
│   │   ├── login.php
│   │   ├── teams/index.php            ← Add pending team registration queue
│   │   ├── locations/index.php        ← Add pending location review queue
│   │   ├── settings/
│   │   │   ├── index.php              ← Add registration toggle section (FR-TOGGLE)
│   │   │   ├── sections/users-coach.php ← Add cutover checklist + disable button
│   │   │   └── sections/...           ← Unchanged
│   │   │
│   │   │   ── NEW ──
│   │   ├── users/
│   │   │   ├── index.php              ← FR-USERMGMT-1 (user list, search, filter)
│   │   │   ├── detail.php             ← FR-USERMGMT-2/3/4/5/6 + team assignment
│   │   │   └── invitations.php        ← FR-INV-1/4 (send invite, view pending)
│   │   └── league-list/
│   │       └── index.php              ← FR-LEAGUELIST-1/2/3/4 (admin manages dropdown)
│   │
│   ├── assets/
│   │   └── js/
│   │       │   ── NEW JS enhancement files ──
│   │       ├── coaches-registration.js  ← "Other" reveal, team name preview, home field repeater
│   │       ├── coaches-schedule.js      ← Sort + filter table (FR-COACHSCHEDULE-3/4)
│   │       └── admin-league-list.js     ← Drag-reorder + CRUD interactions (FR-LEAGUELIST-3)
│   │
│   └── [public pages unchanged]
│       ├── index.php
│       ├── schedule.php
│       └── standings.php
│
├── database/
│   ├── schema.sql                     ← MVP core (unchanged)
│   ├── user_accounts_schema.sql       ← Existing user accounts schema (unchanged)
│   └── migrations/                    ← NEW: numbered sequential migrations
│       ├── 000_create_schema_migrations.sql
│       ├── 001_add_league_list.sql
│       ├── 002_add_login_attempts.sql
│       ├── 003_add_teams_status_column.sql
│       ├── 004_add_locations_submission_columns.sql
│       ├── 005_add_remember_tokens.sql
│       └── 006_remove_legacy_auth.sql
│
└── tests/
    ├── unit/
    │   ├── PermissionGuardTest.php
    │   ├── GameTimeGateTest.php
    │   ├── TeamScopeTest.php
    │   ├── RegistrationServiceTest.php
    │   ├── InvitationServiceTest.php
    │   ├── TeamRegistrationServiceTest.php
    │   ├── LeagueListManagerTest.php
    │   ├── ProfileServiceTest.php
    │   ├── ScoreServiceTest.php
    │   ├── RescheduleServiceTest.php
    │   └── CoachScheduleServiceTest.php
    ├── test-web-functionality.php     ← Existing (extend for new pages)
    └── test-phase2-functionality.php  ← NEW: end-to-end smoke tests for this feature
```

### FR Group → File Mapping

| FR Group | Service class | Page file(s) |
|----------|-------------|-------------|
| FR-AUTH | `RegistrationService` | `coaches/login.php` (modified) |
| FR-REG | `RegistrationService` | `coaches/register.php`, `coaches/verify-email.php` |
| FR-LEAGUELIST | `LeagueListManager` | `admin/league-list/index.php` |
| FR-INV | `InvitationService` | `admin/users/invitations.php` |
| FR-TOGGLE | _(settings table read)_ | `admin/settings/index.php` (modified) |
| FR-TEAMREG | `TeamRegistrationService` | `coaches/team-register.php`, `coaches/team-register-confirm.php`, `admin/teams/index.php` (modified) |
| FR-ASSIGN | `UserManagementService` | `admin/users/detail.php` |
| FR-SCORE | `ScoreService` | `coaches/score-input.php` (modified) |
| FR-RESCHED | `RescheduleService` | `coaches/schedule-change.php` (modified) |
| FR-RESOURCES | _(auth gate only)_ | `coaches/rules.php`, `coaches/contacts.php` (modified) |
| FR-USERMGMT | `UserManagementService`, `CutoverService` | `admin/users/index.php`, `admin/users/detail.php`, `admin/settings/sections/users-coach.php` (modified) |
| FR-PROFILE | `ProfileService` | `coaches/profile.php` |
| FR-COACHSCHEDULE | `CoachScheduleService` | `coaches/schedule.php` |
| FR-RESTRICTIONS | `PermissionGuard` | Every `coaches/` page (top of file) |

### Architectural Boundaries & Integration Points

**Permission boundary:** `PermissionGuard::requireRole()` is the hard wall between public/anonymous and authenticated coach access. Nothing inside `public/coaches/` renders content without passing through it.

**Data boundary:** All DB reads/writes for new features go through service classes in `includes/`. Page files (`public/coaches/*.php`, `public/admin/*.php`) contain zero raw SQL — they instantiate services, call methods, and render results.

**Email boundary:** `EmailService` is the only path to PHPMailer. No page file or service instantiates PHPMailer directly.

**Audit boundary:** `ActivityLogger` is the only path to `activity_log`. Service classes call it; page files do not.

**Team scope boundary:** `TeamScope::getScopedTeams()` is the only authoritative source of a user's teams. Repeated inline queries against `team_owners` are forbidden.

**Legacy boundary:** After migration 006 is applied and `LegacyAuthManager.php` is deleted, there is no code path that accepts the shared `coaches_password`. Any attempt to restore it requires explicit action.

---

## Architecture Validation Results

### Coherence Validation ✅

**Decision Compatibility — all decisions are compatible:**
- PHP 8.1 + PDO + Bootstrap 5 + PHPMailer + reCAPTCHA v2 — no version conflicts, all run on shared hosting without additional infrastructure
- `PermissionGuard` (file-top pattern) is consistent with a router-less PHP monolith
- `getScopedTeams()` returning an array is compatible with the 1:1 DB `UNIQUE` constraint — callers take `[0]`, relaxing the constraint later requires no query changes
- PRG pattern is consistent with server-rendered PHP and Bootstrap 5 forms
- Lazy-purge on `login_attempts` is compatible with shared hosting (no daemon required)
- reCAPTCHA fail-open degradation is compatible with shared hosting outbound HTTP variability

**Pattern Consistency — no contradictions:**
- All naming patterns (snake_case DB, PascalCase PHP classes, kebab-case files) are internally consistent and match existing codebase conventions
- `ActivityLogger` called from services only — consistent with the service-layer boundary
- PRG/flash pattern consistent across all new form pages
- Migration file convention (`NNN_description.sql`) consistently applied to all 7 new migration files

**Structure Alignment — structure supports all decisions:**
- `includes/` flat structure matches existing pattern; no new subdirectory conventions introduced
- `public/coaches/` and `public/admin/` separation cleanly enforces the auth boundary
- JS files scoped by `{area}-{feature}.js` avoids global namespace conflicts in Bootstrap 5 context

**Clarification:** `coach_bootstrap.php` has PermissionGuard added as a call convention — page files call `PermissionGuard::requireRole()` directly after bootstrap inclusion, not inside bootstrap. This is intentional (different pages require different roles) and consistent with the file-top pattern decision.

---

### Requirements Coverage Validation ✅

**FR Coverage — all 15 FR groups architecturally supported:**

| FR Group | Coverage | Notes |
|----------|---------|-------|
| FR-AUTH | ✅ | `RegistrationService` + `login_attempts` table + reCAPTCHA + `remember_tokens` |
| FR-REG | ✅ | `RegistrationService` + `coaches/register.php` + `league_list` table |
| FR-LEAGUELIST | ✅ | `LeagueListManager` + `admin/league-list/index.php` |
| FR-INV | ✅ | `InvitationService` + `admin/users/invitations.php` |
| FR-TOGGLE | ✅ | `settings` table read at page load; no cache layer needed |
| FR-TEAMREG | ✅ | `TeamRegistrationService` + `teams.status` column + `locations` additions |
| FR-ASSIGN | ✅ | `UserManagementService` + `admin/users/detail.php` |
| FR-SCORE | ✅ | `ScoreService` + `GameTimeGate` + `TeamScope` |
| FR-RESCHED | ✅ | `RescheduleService` + `TeamScope` |
| FR-RESOURCES | ✅ | `PermissionGuard` gates `coaches/rules.php` + `coaches/contacts.php` |
| FR-USERMGMT | ✅ | `UserManagementService` + `CutoverService` + `admin/users/` |
| FR-PROFILE | ✅ | `ProfileService` + `coaches/profile.php`; FR-PROFILE-7 enforced by absence of any edit path |
| FR-COACHSCHEDULE | ✅ | `CoachScheduleService` + `coaches-schedule.js` |
| FR-RESTRICTIONS | ✅ | `PermissionGuard` at every coach page + server-side validation in each service |
| FR-TEAMREG home fields | ✅ | `locations` table additions + home field repeater in `coaches-registration.js` |

**NFR Coverage — all 5 NFR groups addressed:**

| NFR Group | Coverage | Notes |
|-----------|---------|-------|
| NFR-SEC | ✅ | bcrypt; CSRF on all forms; PDO prepared statements; session rotation on role change; registration URL not linked publicly |
| NFR-PERF | ✅ | All new queries indexed; `getScopedTeams()` result cacheable in session post-login |
| NFR-COMPAT | ✅ | PHP 8.1 confirmed; Bootstrap 5 in stack; no Node required; all new forms inherit mobile-responsive Bootstrap grid |
| NFR-ACCESS | ✅ | Bootstrap 5 form controls (keyboard navigable, screen-reader compatible); WCAG contrast is Bootstrap default; automated scan required pre-launch |
| NFR-AVAIL | ✅ | Resolved by removing legacy auth immediately — no dual-system complexity |

---

### Gap Analysis Results

**Critical gaps: 0**

**Important gaps (informational, not blocking implementation):**

1. **`getScopedTeams()` session caching** — mentioned in NFR-PERF but not yet specified. Recommend: store `$_SESSION['team_ids']` on login and invalidate on team assignment change. Add to `TeamScope` implementation story.

2. **Reschedule notification recipient list** — explicitly deferred. Must be resolved before FR-RESCHED stories are written. Umpires confirmed; coaches and/or opposing team contacts TBD.

3. **`coaches/index.php` disposition** — existing file not mapped. Either redirect to `coaches/dashboard.php` or update in-place. Decide during coach dashboard story.

**Nice-to-have gaps:**

4. **Email template names** — define the named template list as part of `InvitationService` / `RegistrationService` stories so DB seed data is written alongside code.

5. **Migration apply script** — simple shell script listing unapplied migrations would reduce human error. Low priority; out of scope.

---

### Architecture Completeness Checklist

**Requirements Analysis**
- [x] Project context thoroughly analyzed (87 FRs, 12 NFRs, 15 FR groups)
- [x] Scale and complexity assessed (Medium — brownfield PHP monolith)
- [x] Technical constraints identified (shared hosting, PHP 8.1, PDO, no daemons)
- [x] Cross-cutting concerns mapped (6 concerns, each with canonical implementation location)

**Architectural Decisions**
- [x] Legacy auth deprecated immediately
- [x] Team scoping centralized in `getScopedTeams()`
- [x] 1:1 user-to-team enforced at DB + app layer
- [x] Permission enforcement via `PermissionGuard` at file top
- [x] PHPMailer retained; 8 email events classified by blocking status
- [x] `login_attempts` lazy purge strategy
- [x] `teams.status` column for pending state
- [x] `locations` table additions for home fields
- [x] Google reCAPTCHA v2 with fail-open degradation
- [x] Numbered sequential migration files (`NNN_description.sql`)
- [x] Server-rendered PHP + dedicated JS enhancement files per feature area
- [x] Full activity logging scope, 24 defined events

**Implementation Patterns**
- [x] Naming conventions: DB snake_case, PHP PascalCase, files kebab-case
- [x] Structure patterns: service layer, page files, JS files, tests, migrations
- [x] Format patterns: PRG, flash messages, UTC dates, TINYINT(1), lowercase ENUMs
- [x] Process patterns: PermissionGuard, TeamScope, CSRF, Database, ActivityLogger, EmailService, GameTimeGate
- [x] Anti-patterns table: 10 entries covering all critical failure modes

**Project Structure**
- [x] Complete directory tree with all 34 new/modified files identified
- [x] FR group → service class → page file mapping complete
- [x] Cross-cutting concern locations defined
- [x] Architectural boundaries documented
- [x] All 7 migration files named and sequenced

---

### Architecture Readiness Assessment

**Overall Status: READY FOR IMPLEMENTATION**

**Confidence Level: High**

**Key Strengths:**
- Brownfield-first design — every decision extends existing patterns; agents inherit a clear playbook with no new conventions to learn
- Zero ambiguity on the three highest-risk areas: dual auth (removed), team scoping (centralized utility), permission enforcement (file-top pattern)
- Every FR group has a named service class, named page file(s), and a named migration — implementation stories can be written directly from this map
- Anti-patterns table gives agents explicit "never do this" guidance that prevents the most common PHP monolith drift patterns

**Areas for Future Enhancement:**
- Session caching of `getScopedTeams()` result (performance, nice-to-have)
- Migration apply script (developer ergonomics)
- Full reschedule notification recipient list (must resolve before FR-RESCHED stories)
- Email template name registry (define during story writing)

---

### Implementation Handoff

**AI Agent Guidelines:**
- Follow all architectural decisions exactly as documented
- Use implementation patterns consistently across all components
- Respect project structure and boundaries — no new `includes/` subdirectories, no raw SQL in page files
- Refer to this document for all architectural questions before making implementation choices
- When in doubt, match the nearest existing file in `public/coaches/` or `public/admin/` as the style reference

**Implementation Sequence (first stories should follow this order):**
1. Apply all 7 schema migrations (000–006)
2. Delete `LegacyAuthManager.php` and clean up all references
3. Implement `PermissionGuard`, `TeamScope`, `GameTimeGate`, `ActivityLogger` (cross-cutting utilities — all feature services depend on these)
4. Implement `LeagueListManager` + `admin/league-list/index.php` (registration form depends on it)
5. Implement `RegistrationService` + `InvitationService` + registration pages
6. Implement `TeamRegistrationService` + team registration pages
7. Implement remaining coach features (score, reschedule, schedule, profile) in any order
8. Implement `CutoverService` + cutover UI (last — depends on all coach onboarding being testable)
