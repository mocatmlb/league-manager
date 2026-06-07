---
stepsCompleted: [1, 2, 3, 4]
status: complete
completedAt: '2026-05-04'
inputDocuments:
  - _bmad-output/planning-artifacts/prd.md
  - _bmad-output/planning-artifacts/architecture.md
  - _bmad-output/planning-artifacts/ux-design-specification.md
  - _bmad-output/project-context.md
---

# District 8 Travel League: Individual Coach Logins — Epic Breakdown

## Overview

This document provides the complete epic and story breakdown for the Individual Coach Logins feature, decomposing requirements from the PRD, Architecture, and project context into implementable stories.

---

## Requirements Inventory

### Functional Requirements

| ID | Requirement |
|----|-------------|
| FR-AUTH-1 | Users can log in with username or email + password via the coach login page |
| FR-AUTH-2 | Sessions expire after 60 minutes of inactivity |
| FR-AUTH-3 | Users can reset their password via a time-limited (24-hour) email link |
| FR-AUTH-4 | Accounts are locked for 15 minutes after 5 consecutive failed login attempts |
| FR-AUTH-5 | "Remember me" option extends session to 30 days via secure persistent cookie |
| FR-AUTH-6 | Logout invalidates the session immediately |
| FR-AUTH-7 | CAPTCHA activates after 3 consecutive failed login attempts from the same IP; must be passed before further attempts |
| FR-REG-1 | When open registration is enabled, any user can access the registration URL and create an account |
| FR-REG-2 | When open registration is disabled, the registration URL returns a closed message; only invitation tokens produce the registration form |
| FR-REG-3 | Registration form collects: first name, last name, preferred name (optional), email, primary phone with type, secondary phone with type (optional), league (dropdown), username, password, confirm password |
| FR-REG-4 | Usernames must be unique across all accounts |
| FR-REG-5 | Passwords must be ≥8 chars with at least one uppercase, one number, one special character |
| FR-REG-6 | System sends a verification email upon successful form submission; account status is `unverified` until link clicked |
| FR-REG-7 | Verification links expire after 48 hours; expired links show a resend option |
| FR-REG-8 | Newly verified accounts are assigned `user` role; Team Owner role is granted only by admin |
| FR-REG-9 | Admin receives email notification when a new account completes email verification |
| FR-REG-10 | Registration form includes CAPTCHA; submissions that fail are rejected before account creation |
| FR-REG-11 | League field is a dropdown from the admin-managed league list; includes "Other" option revealing a free-text field |
| FR-REG-12 | League field is required; registration cannot be submitted without a selection or manual entry when "Other" selected |
| FR-LEAGUELIST-1 | Admins can create, edit, and deactivate entries in the league dropdown list |
| FR-LEAGUELIST-2 | Each entry is a short display name used as-is in team name generation and the dropdown |
| FR-LEAGUELIST-3 | Admins can reorder league list entries to control dropdown display order |
| FR-LEAGUELIST-4 | Deactivated entries no longer appear in the registration dropdown but remain for historical reference |
| FR-LEAGUELIST-5 | Registration form dropdown always includes "Other" as the last entry regardless of admin configuration |
| FR-INV-1 | Admins can send a registration invitation to any email address via admin User Management panel |
| FR-INV-2 | Invitation emails contain a unique, single-use registration URL that expires after 14 days |
| FR-INV-3 | Sending a second invitation to the same email cancels the prior token and issues a new one |
| FR-INV-4 | Admins can view all pending invitations with status (pending, completed, expired) and resend or cancel them |
| FR-INV-5 | Completed invitation tokens cannot be reused |
| FR-TOGGLE-1 | Admins can enable or disable open self-registration from the Settings panel |
| FR-TOGGLE-2 | When enabled, the admin Settings panel displays the registration URL and a QR code image |
| FR-TOGGLE-3 | Toggle change takes effect within one page load with no server restart required |
| FR-TOGGLE-4 | Disabling open registration does not affect active invitation links already in circulation |
| FR-TEAMREG-1 | After email verification during self-registration, coach is presented with programs/seasons that have open registration |
| FR-TEAMREG-2 | Coach selects a program and season; division is not selectable by coach |
| FR-TEAMREG-3 | Team name is auto-generated as `{league_name}-{coach_last_name}`; displayed to coach as read-only |
| FR-TEAMREG-4 | If "Other" was selected for league, the manually entered value is used in place of `{league_name}` |
| FR-TEAMREG-5 | Coach can add up to 5 home field locations; each location saved to the system's available field locations pool |
| FR-TEAMREG-6 | Home field location entry requires at minimum a location name; additional details are optional |
| FR-TEAMREG-7 | Submitted team registrations are created in `pending` status and appear in admin dashboard for review |
| FR-TEAMREG-8 | Admin receives notification when a new team registration is submitted |
| FR-TEAMREG-9 | Upon admin approval, system assigns the submitting coach as Team Owner for that team |
| FR-TEAMREG-10 | Coach receives email notification when team registration is approved and they are assigned as Team Owner |
| FR-TEAMREG-11 | A coach who registered via invitation cannot submit a team registration through self-registration path |
| FR-TEAMREG-12 | Only an admin can edit a team name after auto-generation; coach has no ability to modify it |
| FR-ASSIGN-1 | Admins can assign one or more teams to a user account from the User Detail page |
| FR-ASSIGN-2 | Assigning the first team to a user elevates their role to `team_owner` automatically |
| FR-ASSIGN-3 | ~~Admins can assign multiple teams to the same user~~ — **Deferred to future phase.** `UNIQUE(user_id)` on `team_owners` enforces 1:1 this iteration. |
| FR-ASSIGN-4 | ~~Multiple Team Owners can be assigned to the same team~~ — **Deferred to future phase.** Same 1:1 constraint as FR-ASSIGN-3. |
| FR-ASSIGN-5 | Admins can remove a team assignment; if no remaining teams, role reverts to `user` |
| FR-ASSIGN-6 | Coach receives email notification when a team is assigned or removed |
| FR-ASSIGN-7 | Admin can view all team assignments for a user and all users assigned to a specific team |
| FR-SCORE-1 | Team Owners can submit scores for games where their assigned team is the home team |
| FR-SCORE-2 | Team Owners can submit scores for games where their assigned team is the away team |
| FR-SCORE-3 | Score submission interface displays only games where game date is in the past, or game date is today and game time is in the past |
| FR-SCORE-4 | Score submission for a game with an existing score requires an explicit "edit score" action |
| FR-SCORE-5 | Submitted scores update standings immediately upon save |
| FR-SCORE-6 | Admin receives notification for each score submission by a Team Owner |
| FR-SCORE-7 | Upon successful score submission, game status is set to `completed` |
| FR-RESCHED-1 | Team Owners can submit reschedule requests for games where their assigned team is home or away |
| FR-RESCHED-2 | Reschedule request interface displays only games belonging to the coach's assigned team(s) |
| FR-RESCHED-3 | Reschedule requests require a proposed new date/time and a reason (both required) |
| FR-RESCHED-4 | Submitted requests appear in admin dashboard as pending items for review |
| FR-RESCHED-5 | Coaches can view the status of their own submitted reschedule requests |
| FR-RESCHED-6 | Admin receives notification for each reschedule request submitted by a Team Owner |
| FR-RESCHED-7 | Coaches can cancel a reschedule request only while the request status is `pending` |
| FR-RESOURCES-1 | Any authenticated user (role ≥ `user`) can access the local rules and regulations documents section |
| FR-RESOURCES-2 | Rules section displays documents uploaded by admin via existing Document Management feature |
| FR-RESOURCES-3 | Any authenticated user (role ≥ `user`) can access the league contact directory |
| FR-RESOURCES-4 | Unauthenticated requests to rules or contact directory pages redirect to the login page |
| FR-USERMGMT-1 | Admins can view a filterable, searchable, paginated list of all user accounts |
| FR-USERMGMT-2 | Admins can edit any user's profile fields (name, email, phone, username) |
| FR-USERMGMT-3 | Admins can change a user's role (user, team_owner, administrator) |
| FR-USERMGMT-4 | Admins can disable or re-enable a user account; disabled accounts cannot log in |
| FR-USERMGMT-5 | Admins can reset a user's password, generating a temporary password and forcing change on next login |
| FR-USERMGMT-6 | Admins can delete a user account with a confirmation step; associated team assignments are removed |
| FR-USERMGMT-7 | Admins can view a pre-cutover checklist showing all active-season teams with zero assigned Team Owners |
| FR-USERMGMT-8 | Admins can disable the legacy shared coach credential from the Settings panel |
| FR-USERMGMT-9 | The shared coach credential disable action requires explicit confirmation and is only available when the pre-cutover checklist shows zero teams with no assigned Team Owner |
| FR-PROFILE-1 | Authenticated users can update their first name, last name, and preferred name from their profile page |
| FR-PROFILE-2 | Authenticated users can add or update a primary phone number with phone type (Home, Work, or Cell) |
| FR-PROFILE-3 | Authenticated users can add or update a secondary phone number with phone type |
| FR-PROFILE-4 | Authenticated users can remove a secondary phone number; primary phone cannot be removed |
| FR-PROFILE-5 | Authenticated users can change their own password while logged in; current password must be provided |
| FR-PROFILE-6 | New passwords set via FR-PROFILE-5 must meet the same complexity requirements as FR-REG-5 |
| FR-PROFILE-7 | Team name is not editable by the coach through any profile or self-service interface |
| FR-COACHSCHEDULE-1 | Authenticated Team Owners can view a schedule filtered to games involving their assigned team(s) |
| FR-COACHSCHEDULE-2 | Schedule view displays: Game Number, Date, Time, Away Team, Home Team, Location, Score |
| FR-COACHSCHEDULE-3 | All columns in the schedule view are independently sortable (ascending and descending) |
| FR-COACHSCHEDULE-4 | All columns are independently filterable; text columns support search input; Date column supports date-range filtering |
| FR-COACHSCHEDULE-5 | Schedule view displays all games for the coach's team(s) regardless of game status |
| FR-COACHSCHEDULE-6 | Schedule view presentation follows same column structure as existing master public schedule |
| FR-RESTRICTIONS-1 | Team Owners cannot terminate, close, or change the status of a season |
| FR-RESTRICTIONS-2 | Team Owners cannot change the division or program assignment for any team |
| FR-RESTRICTIONS-3 | Team Owners cannot change game status directly; sole exception is score submission setting status to `completed` |
| FR-RESTRICTIONS-4 | Team Owners cannot submit a score for a game that does not involve their assigned team(s); rejected server-side |
| FR-RESTRICTIONS-5 | Team Owners cannot submit a score for a game whose date is in the future, or whose date is today and time is in the future |
| FR-RESTRICTIONS-6 | Team Owners cannot view, edit, or access profile information of other coaches, teams, admins, or officials |
| FR-RESTRICTIONS-7 | Team Owners cannot perform any admin-only function |

---

### Non-Functional Requirements

| ID | Requirement |
|----|-------------|
| NFR-SEC-1 | User credentials stored such that plaintext passwords cannot be recovered from the database |
| NFR-SEC-2 | All state-changing operations protected against CSRF; forged requests rejected with no state change |
| NFR-SEC-3 | All user-supplied input is parameterized before database execution; SQL injection produces no unauthorized access |
| NFR-SEC-4 | Session tokens transmitted and stored to prevent client-side script access and unencrypted transmission |
| NFR-SEC-5 | Session token changes on login, logout, and privilege level change |
| NFR-SEC-6 | Open self-registration URL is not discoverable through public site navigation or search engine indexing |
| NFR-PERF-1 | Login page responds in under 2 seconds at 95th percentile under normal load on shared hosting |
| NFR-PERF-2 | Score submission completes (form submit → confirmation) in under 3 seconds at 95th percentile |
| NFR-PERF-3 | Coach dashboard (game list for assigned teams) loads in under 3 seconds for a coach assigned to up to 3 teams |
| NFR-PERF-4 | Self-registration form submission completes in under 3 seconds at 95th percentile; verification email delivered within 5 minutes |
| NFR-COMPAT-1 | All registration, login, score submission, reschedule request, team registration, and profile management forms are fully functional on mobile viewports (≥ 375px width) |
| NFR-COMPAT-2 | UI renders correctly in current versions of Chrome, Firefox, and Safari |
| NFR-COMPAT-3 | Application runs on PHP 8.1 (`ea-php81`) on cPanel shared hosting without additional server configuration |
| NFR-ACCESS-1 | All authentication, registration, and data-entry pages meet WCAG 2.1 Level AA standards; verified by automated accessibility scanning tool prior to launch |
| NFR-AVAIL-1 | During transition period, both individual login system and shared coach login remain operational in parallel; failure in either does not degrade the other |

---

### Additional Requirements (Architecture)

- **AR-1:** `LegacyAuthManager.php` must be deleted and all references removed in the first implementation PR; shared `coaches_password` credential formally deprecated via migration 006
- **AR-2:** 7 schema migrations must be applied before any feature code ships, in order: 000 (schema_migrations tracking table), 001 (league_list), 002 (login_attempts), 003 (teams.status column), 004 (locations submission columns), 005 (remember_tokens), 006 (legacy auth removal record)
- **AR-3:** `PermissionGuard::requireRole()` must be the first executable line after bootstrap on every `public/coaches/*.php` page
- **AR-4:** `TeamScope::getScopedTeams(int $userId): array` is the canonical team-scoping utility; must be used by all services that filter by team ownership; returns array always
- **AR-5:** `UNIQUE(user_id)` constraint on `team_owners` table enforces 1:1 user-to-team for this iteration; app-layer guard `TeamAlreadyClaimedException` enforced in both `RegistrationService` and `TeamAssignmentService` (within `UserManagementService`)
- **AR-6:** `GameTimeGate::isEligible($game): bool` is the canonical time-gate utility; used by `ScoreService` and `CoachScheduleService`; no inline date comparison logic in page files
- **AR-7:** `ActivityLogger::log(string $event, array $context): void` logs all 24 defined events; called from service classes only, never from page files
- **AR-8:** Google reCAPTCHA v2 on registration form (always) and login page (after 3 failed attempts from same IP); fail-open if Google endpoint unreachable; site key + secret stored in `config.prod.php` as constants
- **AR-9:** `login_attempts` table lazy-purge: `DELETE WHERE created_at < NOW() - INTERVAL 24 HOUR LIMIT 100` executed inline before each login attempt insert
- **AR-10:** PRG (Post/Redirect/Get) pattern on every form POST handler; flash messages via `$_SESSION['flash_error']` / `$_SESSION['flash_success']`; read-and-clear on render
- **AR-11:** All `DATETIME` columns store UTC; display conversion at render time using configured league timezone
- **AR-12:** Email events classified as blocking (verification link, invitation link, password reset) vs. operational (all others); blocking failures surfaced to user and logged; operational failures logged only
- **AR-13:** Migration file convention: `database/migrations/NNN_snake_case_description.sql`; `schema_migrations` tracking table records applied versions
- **AR-14:** JS enhancements in dedicated files: `coaches-registration.js`, `coaches-schedule.js`, `admin-league-list.js`; jQuery for DOM manipulation; no additional frontend libraries

---

### UX Design Requirements

| ID | Requirement |
|----|-------------|
| UX-DR1 | **Coach Identity Hero component (`.coach-hero`)**: Blue gradient hero banner on coach dashboard showing coach name, team name, season/division, and role badge. Three states: `active` (team assigned), `pending` (team registration awaiting approval, amber badge), `unassigned` (invited account, no team yet, gray). Team name as `<h1>` with appropriate contrast. |
| UX-DR2 | **VS Score Entry component (`.vs-score-entry`, `.vs-score-input`)**: Three-column grid layout (`1fr auto 1fr`) — left column: team name label + score input; center: "VS"; right column: team name label + score input. Score inputs: `font-size: 2rem`, `inputmode="numeric"`, `min="0" max="99"`, min 44px tap height. Four states: `default`, `filled` (Submit enabled), `error` (field border highlight), `auto-selected` (game info banner above). |
| UX-DR3 | **Action Card Grid component (`.coach-action-grid`, `.coach-action-card`)**: 2×2 grid overlapping over the hero banner (Direction 2 negative-margin pull). Each card: colored 44×44px icon square, label, sub-label (count or description). States: `default`, `active-count` (badge in accent color), `disabled` (grayed, non-clickable). Each card is `<a>` with descriptive `aria-label`. |
| UX-DR4 | **Registration Progress Indicator component (`.reg-progress`)**: Two-step progress tracker for the self-registration flow. Step circles (numbered), step labels, connector line, Bootstrap `progress` bar. States: `step-1-active` (bar 50%), `step-2-active` (circle 1 becomes checkmark, bar 100%). `aria-label="Registration step N of 2"` on wrapper; `aria-current="step"` on active step. |
| UX-DR5 | **Game Detail Reveal Panel component (`.game-detail-panel`)**: Light gray card that reveals on game selection showing Date, Time, Location. Hidden by default; `aria-live="polite"` for screen reader announcements. Progressive reveal via JS `display` toggle (no page reload). |
| UX-DR6 | **Admin Gap Checklist Row components (`.gap-row-covered`, `.gap-row-missing`)**: Color-coded rows in pre-cutover gap checklist table. Covered: green text + check icon; Gap: red text + times icon, `#fff9f9` row background. Status uses both color AND icon — never color alone. Columns: Team, Division, Program, Owners list, Status badge, Action link. |
| UX-DR7 | **Score submission auto-selection**: When exactly one eligible game exists, it must be pre-selected and score inputs shown immediately — the game selection dropdown is skipped entirely. Auto-selection triggers only when the coach has exactly one past/elapsed game with no recorded score. |
| UX-DR8 | **Schedule change contact pre-population**: The schedule change form (`public/coaches/schedule-change.php`) must pre-populate coach name, primary phone, and email from the authenticated coach's profile as read-only fields. A "Update in your profile →" link is shown alongside the pre-populated fields. Never require manual re-entry of contact info. |
| UX-DR9 | **Registration form "Other" league progressive reveal**: When the coach selects "Other" from the league dropdown, a free-text input field is revealed via JS `display` toggle (no page reload). When a named league is selected, the free-text field is hidden. The free-text field is required when "Other" is selected. Implemented in `coaches-registration.js`. |
| UX-DR10 | **Home field repeater UI**: During team registration (Step 2), a dynamic repeater allows coaches to add up to 5 home field location entries. Each entry block has: location name (required), address (optional), additional details (optional). "Add Another Location" button adds a new entry block; a remove button per entry removes it. Maximum 5 entries enforced client-side. Implemented in `coaches-registration.js`. |
| UX-DR11 | **Cutover confirmation modal**: The cutover button uses `modal-dialog-centered` with no × close button in the header (forces explicit choice), `data-bs-backdrop="static"` (cannot dismiss by clicking backdrop). Footer: `btn-secondary` Cancel + `btn-danger` Confirm. Cutover button is physically `disabled` attribute (not hidden) until gap checklist shows zero gaps — disabled state shows inline explanation of why it's locked. |
| UX-DR12 | **Schedule sort/filter interactions**: The coach team schedule table supports independent column sorting (ascending/descending toggle on header click) and independent column filtering (text search inputs for text columns; date-range picker for the Date column). Clear filters button returns to full unfiltered view. Implemented in `coaches-schedule.js`. |
| UX-DR13 | **Admin league list drag-reorder**: The admin league list management page supports drag-and-drop or up/down control reordering of active league entries. Order is saved on confirmation. Implemented in `admin-league-list.js` using jQuery UI sortable or equivalent. |
| UX-DR14 | **Login CAPTCHA progressive reveal**: reCAPTCHA v2 widget on the login page is hidden by default and revealed via JS after 3 consecutive failed login attempts from the same IP. The server passes the current failed-attempt count to the page; JS compares to threshold and toggles widget visibility. |
| UX-DR15 | **New status CSS classes for accounts/teams**: Implement in `assets/css/style.css`: `.status-unverified` (amber `#ffc107`), `.status-team-pending` (orange `#fd7e14`), `.status-team-owner` (green `#28a745`). Applied to account and team status badge displays. |
| UX-DR16 | **Consistent empty state pattern**: All coach-facing list/table views that can return zero results use `alert alert-info` with a specific diagnostic sentence: `ℹ [What is empty.] — [Why it's empty / what condition fills it.]`. Five defined empty states: Score Input, Schedule Change, My Schedule, Admin Invitations, User Management. |
| UX-DR17 | **Consistent confirmation echo pattern**: All successful form submissions display `alert alert-success alert-dismissible` with a data-echo sentence naming the specific object acted on (game number, date, both team names, scores). Example: "Score submitted. Game #47, May 4 — Springfield-Jones 6, Marlins 3. Standings updated." |
| UX-DR18 | **Error input preservation pattern**: All network/server error states preserve user input in the form. Error message names the failure and action to take. Coach never sees a blank form after a network error. Applied to score submission and all state-changing forms. |
| UX-DR19 | **Form accessibility baseline**: All new forms implement: explicit `<label for="">` on every input (no placeholder-only labels), `aria-describedby` linking error spans to inputs, `role="alert"` or `aria-live="polite"` on alert regions, page `<title>` with "— District 8 Travel League" suffix, `form-control-lg` (48px height) on all coach-facing inputs, `btn-lg` (44px+ tap target) on all primary actions. |
| UX-DR20 | **Dark coach navbar**: Coach pages use a dark navbar (`#212529`) with team name chip replacing the current blue gradient nav. Left: brand + team name chip; right: user dropdown; hamburger collapse on mobile. |

---

### FR Coverage Map

| FR/AR/UX-DR | Epic | Notes |
|---|---|---|
| FR-AUTH-1–7 | Epic 3 | Authentication — login, sessions, lockout, CAPTCHA, remember-me, logout |
| FR-REG-1–12 | Epic 3 | Self-registration form, verification email, league dropdown |
| FR-INV-1–5 | Epic 3 | Admin invitation system |
| FR-TOGGLE-1–4 | Epic 3 | Open registration toggle |
| FR-LEAGUELIST-1–5 | Epic 2 | Admin-managed league dropdown |
| FR-TEAMREG-1–12 | Epic 4 | Coach-initiated team registration sub-flow |
| FR-ASSIGN-1–2, 5–7 | Epic 4 | Admin team assignment (1:1 user-to-team enforced this iteration via UNIQUE constraint) |
| FR-ASSIGN-3–4 | **Deferred** | Multi-team / multi-owner deferred to future phase; AR-5 `UNIQUE(user_id)` prevents it; see PRD Out of Scope |
| FR-SCORE-1–7 | Epic 5 | Team-scoped score submission |
| FR-RESCHED-1–7 | Epic 6 | Team-scoped reschedule requests |
| FR-RESOURCES-1–4 | Epic 7 | Authenticated access to rules & contacts |
| FR-USERMGMT-1–6 | Epic 8 | Admin user CRUD |
| FR-USERMGMT-7–9 | Epic 9 | Pre-cutover checklist + shared credential disable |
| FR-PROFILE-1–7 | Epic 7 | Coach self-service profile management |
| FR-COACHSCHEDULE-1–6 | Epic 7 | Coach team schedule view |
| FR-RESTRICTIONS-1–2, 7 | Epic 9 | Admin-only boundaries enforced at cutover |
| FR-RESTRICTIONS-3–5 | Epic 5 | Game status + score submission boundaries |
| FR-RESTRICTIONS-6 | Epic 7 | Profile privacy boundary |
| NFR-SEC-1–6 | Epics 1, 3 | Security baseline built in Foundation + Registration |
| NFR-PERF-1–4 | All epics | Applied per-story as each feature is built |
| NFR-COMPAT-1–3 | All epics | Applied per-story; mobile-first Bootstrap 5 |
| NFR-ACCESS-1 | Epics 3–7 | WCAG 2.1 AA verified on data-entry pages |
| NFR-AVAIL-1 | Epic 1 | Resolved by immediate legacy removal |
| AR-1 | Epics 1, 9 | LegacyAuthManager deleted in Epic 1; formally deprecated in Epic 9 |
| AR-2 | Epic 1 | All 7 migrations applied in Foundation |
| AR-3 | Epic 1 | PermissionGuard implemented in Foundation |
| AR-4 | Epic 1 | TeamScope utility implemented in Foundation |
| AR-5 | Epics 1, 4 | 1:1 constraint enforced in Foundation; used in Epic 4 |
| AR-6 | Epics 1, 5 | GameTimeGate implemented in Foundation; used in Epic 5 |
| AR-7 | Epic 1 | ActivityLogger implemented in Foundation |
| AR-8 | Epic 3 | reCAPTCHA v2 integrated in Registration |
| AR-9 | Epic 1 | login_attempts lazy-purge strategy in Foundation |
| AR-10 | All epics | PRG pattern applied in every form story |
| AR-11 | Epic 1 | UTC datetime convention set in Foundation |
| AR-12 | Epic 3 | Email blocking/operational classification in Registration |
| AR-13 | Epic 1 | Migration file convention established in Foundation |
| AR-14 | Epics 3, 5, 6, 7 | Dedicated JS files per feature area |
| UX-DR1 | Epic 4 | Coach Identity Hero component |
| UX-DR2 | Epic 5 | VS Score Entry component |
| UX-DR3 | Epic 4 | Action Card Grid component |
| UX-DR4 | Epic 3 | Registration Progress Indicator component |
| UX-DR5 | Epic 6 | Game Detail Reveal Panel component |
| UX-DR6 | Epic 9 | Admin Gap Checklist Row component |
| UX-DR7 | Epic 5 | Score submission auto-selection |
| UX-DR8 | Epic 6 | Schedule change contact pre-population |
| UX-DR9 | Epic 3 | "Other" league progressive reveal |
| UX-DR10 | Epic 4 | Home field repeater UI |
| UX-DR11 | Epic 9 | Cutover confirmation modal |
| UX-DR12 | Epic 7 | Schedule sort/filter interactions |
| UX-DR13 | Epic 2 | Admin league list drag-reorder |
| UX-DR14 | Epic 3 | Login CAPTCHA progressive reveal |
| UX-DR15 | Epic 3 | Status CSS classes |
| UX-DR16 | Epics 5–8 | Empty state pattern applied per feature |
| UX-DR17 | Epics 5–6 | Confirmation echo pattern |
| UX-DR18 | Epics 5–6 | Error input preservation |
| UX-DR19 | Epics 3–7 | Form accessibility baseline |
| UX-DR20 | Epic 3 | Dark coach navbar |

---

## Epic List

### Epic 1: Foundation — Database, Migrations & Cross-Cutting Utilities
All schema migrations applied, legacy shared auth removed, and all cross-cutting utility classes implemented. The codebase is structurally ready for every feature epic that follows.
**ARs covered:** AR-1, AR-2, AR-3, AR-4, AR-5, AR-6, AR-7, AR-9, AR-11, AR-13
**Note:** 8 migrations total (000–007); migration 007 creates `activity_log` table required by AR-7 (ActivityLogger)
**NFRs covered:** NFR-AVAIL-1

### Epic 2: Admin League List Management
Admins can create, edit, reorder, and deactivate entries in the league dropdown list, making it ready for the coach registration form.
**FRs covered:** FR-LEAGUELIST-1–5
**UX-DRs covered:** UX-DR13

### Epic 3: Coach Registration & Authentication
Coaches can create individual accounts via open self-registration or admin invitation, verify their email, and log in with full auth controls (lockout, CAPTCHA, remember-me, password reset).
**FRs covered:** FR-AUTH-1–7, FR-REG-1–12, FR-INV-1–5, FR-TOGGLE-1–4
**UX-DRs covered:** UX-DR4, UX-DR9, UX-DR14, UX-DR15, UX-DR19, UX-DR20
**ARs covered:** AR-8, AR-10, AR-12
**Dependency:** Story 3.2 requires Epic 2 (LeagueListManager) to be complete first

### Epic 4: Team Registration & Coach Assignment
Coaches can register their team through the self-registration path (with home field locations and auto-generated team name), and admins can assign coaches to teams — giving coaches their Team Owner identity and a personalized dashboard.
**FRs covered:** FR-TEAMREG-1–12, FR-ASSIGN-1–2, FR-ASSIGN-5–7 *(FR-ASSIGN-3/4 deferred — 1:1 user-to-team enforced this iteration)*
**UX-DRs covered:** UX-DR1, UX-DR3, UX-DR10

### Epic 5: Team-Scoped Score Submission
Team Owner coaches can submit and edit scores for their assigned team's past/elapsed games, with standings updated immediately and full server-side permission enforcement.
**FRs covered:** FR-SCORE-1–7, FR-RESTRICTIONS-3–5
**UX-DRs covered:** UX-DR2, UX-DR7, UX-DR16, UX-DR17, UX-DR18

### Epic 6: Team-Scoped Reschedule Requests
Team Owner coaches can submit, view, and cancel reschedule requests for their team's games, with admin notifications and full status tracking (Pending / Approved / Denied).
**FRs covered:** FR-RESCHED-1–7
**UX-DRs covered:** UX-DR5, UX-DR8, UX-DR16, UX-DR17, UX-DR18

### Epic 7: Coach Profile, Team Schedule & Authenticated Resources
Coaches can manage their profile (name, phone, password), view their team's full schedule with sort and filter, and access authenticated league resources (rules documents and contact directory).
**FRs covered:** FR-PROFILE-1–7, FR-COACHSCHEDULE-1–6, FR-RESOURCES-1–4, FR-RESTRICTIONS-6
**UX-DRs covered:** UX-DR12, UX-DR19

### Epic 8: Admin User Management
Admins have full CRUD control over all user accounts — view, search, filter, edit, disable/re-enable, delete, reset passwords, and manage roles.
**FRs covered:** FR-USERMGMT-1–6
**UX-DRs covered:** UX-DR16

### Epic 9: Migration Cutover & Shared Credential Deprecation
Admins can monitor coach onboarding progress via the pre-cutover gap checklist and, once all active-season teams have assigned Team Owners, permanently disable the shared coach credential to complete the migration.
**FRs covered:** FR-USERMGMT-7–9, FR-RESTRICTIONS-1–2, FR-RESTRICTIONS-7
**UX-DRs covered:** UX-DR6, UX-DR11
**ARs covered:** AR-1 (formal removal record)

---

## Epic 1: Foundation — Database, Migrations & Cross-Cutting Utilities

All schema migrations applied, legacy shared auth removed, and all cross-cutting utility classes implemented. The codebase is structurally ready for every feature epic that follows.

### Story 1.1: Apply Database Migrations

As a developer,
I want all required schema migrations applied to the database in sequence,
So that the database structure is ready for all Individual Coach Login feature work.

**Acceptance Criteria:**

**Given** the project has no `schema_migrations` tracking table
**When** migration `000_create_schema_migrations.sql` is applied
**Then** a `schema_migrations` table exists with `version VARCHAR(20) PRIMARY KEY` and `applied_at DATETIME` columns
**And** the version `000` is recorded in `schema_migrations`

**Given** migration 000 has been applied
**When** migrations 001 through 006 are applied in sequence
**Then** the `league_list` table exists with columns: `id`, `display_name`, `sort_order`, `is_active`, `created_at`, `updated_at`, and index `idx_league_list_active_order`
**And** the `login_attempts` table exists with columns: `id`, `identifier`, `ip_address`, `attempted_at`, and indexes on `(ip_address, attempted_at)` and `(identifier, attempted_at)`
**And** the `teams` table has a `status ENUM('pending','active','inactive') NOT NULL DEFAULT 'active'` column added after `division_id`
**And** all existing rows in `teams` have `status = 'active'`
**And** the `locations` table has `submitted_by_user_id INT UNSIGNED NULL` and `status ENUM('pending','active','inactive') NOT NULL DEFAULT 'active'` columns added, with FK `fk_locations_submitted_by` referencing `users(id) ON DELETE SET NULL`
**And** all existing rows in `locations` have `status = 'active'`
**And** the `remember_tokens` table exists with columns: `id`, `user_id`, `token_hash VARCHAR(64)`, `expires_at`, `created_at`, unique key `uq_remember_token`, index `idx_remember_tokens_user`, and FK `fk_remember_tokens_user` referencing `users(id) ON DELETE CASCADE`
**And** migration `006_remove_legacy_auth.sql` is applied and its version recorded (formal deprecation record; no destructive DDL required at this step)
**And** all 7 versions (000–006) appear in the `schema_migrations` table

**Given** migration 006 has been applied
**When** migration `007_create_activity_log.sql` is applied
**Then** the `activity_log` table exists with columns: `id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY`, `event VARCHAR(100) NOT NULL`, `context JSON NULL`, `created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP`, and index `idx_activity_log_event (event)`
**And** the version `007` is recorded in `schema_migrations`
**And** all 8 versions (000–007) appear in the `schema_migrations` table

**Given** a migration file has already been applied
**When** it is run a second time
**Then** it produces no error and no duplicate `schema_migrations` entry (idempotent where possible via `IF NOT EXISTS` / `IF EXISTS`)

**Files to create:**
- `database/migrations/000_create_schema_migrations.sql`
- `database/migrations/001_add_league_list.sql`
- `database/migrations/002_add_login_attempts.sql`
- `database/migrations/003_add_teams_status_column.sql`
- `database/migrations/004_add_locations_submission_columns.sql`
- `database/migrations/005_add_remember_tokens.sql`
- `database/migrations/006_remove_legacy_auth.sql`
- `database/migrations/007_create_activity_log.sql`

---

### Story 1.2: Remove Legacy Auth

As a developer,
I want `LegacyAuthManager` and all shared-password code paths removed from the codebase,
So that there is no ambiguity about which auth system is active and no dead code to maintain.

**Acceptance Criteria:**

**Given** `LegacyAuthManager.php` exists in `includes/`
**When** this story is complete
**Then** `LegacyAuthManager.php` no longer exists in the repository
**And** no `require` or `require_once` of `LegacyAuthManager.php` exists in any file
**And** no reference to `LegacyAuthManager` class exists in any PHP file
**And** no reference to `LEGACY_SHARED_PASSWORD` constant exists in `config.php`, `config.prod.php`, `config.staging.php`, or `.env.example`
**And** no `is_legacy_session` branch exists in session handling code
**And** `coaches_password` is not actively used in any auth flow (the setting may remain in the DB as a disabled record per migration 006, but no code reads it for authentication)

**Given** an existing admin or coach session
**When** a user visits any protected page after this change
**Then** they are authenticated via the standard `AuthService` path without error
**And** no PHP warnings or notices are thrown due to missing legacy class references

**Given** unit tests previously testing `LegacyAuthManager`
**When** this story is complete
**Then** those test files are deleted and the test runner reports no failures from missing files

**Files to delete:**
- `includes/LegacyAuthManager.php`
- Any associated test file (e.g., `tests/unit/LegacyAuthManagerTest.php`) if it exists

**Files to modify:**
- `includes/bootstrap.php` — remove `require` of `LegacyAuthManager.php`
- `includes/auth.php` — remove `is_legacy_session` branch and any `coaches_password` lookup
- `includes/config.php` — remove `LEGACY_SHARED_PASSWORD` constant
- `includes/config.prod.php`, `includes/config.staging.php` — remove legacy constant if present
- `.env.example` — remove legacy constant entry if present

---

### Story 1.3: Implement Cross-Cutting Utility Classes

As a developer,
I want `PermissionGuard`, `TeamScope`, `GameTimeGate`, and `ActivityLogger` utility classes implemented,
So that all feature services and page files have a consistent, tested foundation for permission checks, team scoping, time-gating, and audit logging.

**Acceptance Criteria:**

**Given** a page file includes bootstrap and calls `PermissionGuard::requireRole('team_owner')`
**When** the current session user does not have the `team_owner` role
**Then** the user is redirected to the login page with no page content rendered
**And** when the session user does have the required role, execution continues normally

**Given** a service class calls `TeamScope::getScopedTeams(int $userId)`
**When** the user has one team assigned in `team_owners`
**Then** the method returns an array with one element containing the team's data
**And** when the user has no teams assigned, it returns an empty array `[]`
**And** the method never returns `null`

**Given** a game array with `game_date` and `game_time` fields
**When** `GameTimeGate::isEligible($game)` is called
**Then** it returns `true` if the game date is in the past (any time)
**And** it returns `true` if the game date is today and the game time is in the past (server UTC time)
**And** it returns `false` if the game date is today and the game time is in the future
**And** it returns `false` if the game date is in the future

**Given** a service class calls `ActivityLogger::log('auth.login_success', ['user_id' => 1, 'ip' => '127.0.0.1'])`
**When** the database is available
**Then** a row is inserted into `activity_log` with the event name and JSON-encoded context
**And** when called from a page file (not a service), a coding standards note is flagged (enforced by convention, not runtime exception)

**Given** the unit test suite is run via `php tests/unit/run-unit-tests.php`
**When** this story is complete
**Then** unit tests for all four utility classes pass:
- `PermissionGuardTest.php` — tests role check pass and redirect behavior
- `TeamScopeTest.php` — tests array return, empty case, and no-null guarantee
- `GameTimeGateTest.php` — tests all four eligibility conditions including today/boundary
- `ActivityLoggerTest.php` — tests successful insert and graceful DB-unavailable handling

**Files to create:**
- `includes/PermissionGuard.php`
- `includes/TeamScope.php`
- `includes/GameTimeGate.php`
- `includes/ActivityLogger.php`
- `tests/unit/PermissionGuardTest.php`
- `tests/unit/TeamScopeTest.php`
- `tests/unit/GameTimeGateTest.php`
- `tests/unit/ActivityLoggerTest.php`

---

## Epic 2: Admin League List Management

Admins can create, edit, reorder, and deactivate entries in the league dropdown list, making it ready for the coach registration form.

### Story 2.1: LeagueListManager Service

As a developer,
I want a `LeagueListManager` service class that encapsulates all CRUD operations on the `league_list` table,
So that admin page files have a clean, tested API for managing league entries without raw SQL.

**Acceptance Criteria:**

**Given** the `league_list` table exists (from migration 001)
**When** `LeagueListManager::getActiveList()` is called
**Then** it returns an array of active entries ordered by `sort_order` ascending
**And** deactivated entries (`is_active = 0`) are excluded

**Given** a valid display name string
**When** `LeagueListManager::create(string $displayName)` is called
**Then** a new row is inserted with `is_active = 1` and `sort_order` set to `MAX(sort_order) + 1`
**And** the new entry's `id` is returned

**Given** an existing active league entry
**When** `LeagueListManager::update(int $id, string $displayName)` is called
**Then** the entry's `display_name` is updated and `updated_at` is refreshed
**And** calling with a non-existent `id` returns `false` without throwing

**Given** an existing active entry
**When** `LeagueListManager::deactivate(int $id)` is called
**Then** the entry's `is_active` is set to `0`
**And** the entry is excluded from `getActiveList()` results
**And** the entry remains in the database for historical reference

**Given** a previously deactivated entry
**When** `LeagueListManager::reactivate(int $id)` is called
**Then** the entry's `is_active` is set to `1` and it appears at the bottom of `getActiveList()` results

**Given** an ordered array of entry IDs
**When** `LeagueListManager::reorder(array $orderedIds)` is called
**Then** each entry's `sort_order` is updated to match the position in the array (1-indexed)
**And** the change is reflected immediately in subsequent `getActiveList()` calls

**Given** the unit test suite is run
**When** this story is complete
**Then** `LeagueListManagerTest.php` passes all cases including empty list, create, update, deactivate, reactivate, and reorder

**Files to create:**
- `includes/LeagueListManager.php`
- `tests/unit/LeagueListManagerTest.php`

---

### Story 2.2: Admin League List Management Page

As an admin,
I want a management page to create, edit, reorder, and deactivate league entries,
So that the registration form dropdown always reflects the correct league options.

**Acceptance Criteria:**

**Given** an admin is logged in and navigates to `admin/league-list/index.php`
**When** the page loads
**Then** all active league entries are displayed in a sortable table in their configured display order
**And** deactivated entries are shown in a separate section below with a visual indicator (muted/strikethrough)
**And** a "Add League" form/button is visible at the top

**Given** the admin clicks "Add League" and enters a short display name and submits
**When** the form POSTs successfully (PRG pattern)
**Then** the new entry appears at the bottom of the active list
**And** a flash success message confirms the addition
**And** an `ActivityLogger` event `admin.league_list_created` is recorded

**Given** the admin clicks an entry's edit control and changes the display name
**When** the form POSTs successfully
**Then** the updated name appears in the list
**And** a flash success message confirms the update
**And** an `ActivityLogger` event `admin.league_list_edited` is recorded

**Given** the admin clicks "Deactivate" on an active entry
**When** they confirm the action
**Then** the entry moves to the deactivated section
**And** a flash success message confirms
**And** an `ActivityLogger` event `admin.league_list_deactivated` is recorded

**Given** the admin clicks "Reactivate" on a deactivated entry
**When** the action completes
**Then** the entry returns to the active list at the bottom
**And** an `ActivityLogger` event `admin.league_list_reactivated` is recorded

**Given** the admin drags entries to reorder them (or uses up/down controls) and saves order
**When** the reorder POST completes
**Then** `getActiveList()` returns entries in the new order
**And** the page reloads showing the saved order

**Given** no active entries exist
**When** the page loads
**Then** an appropriate empty state message is shown ("No leagues configured yet. Add the first one above.")

**Files to create:**
- `public/admin/league-list/index.php`
- `public/assets/js/admin-league-list.js`

---

## Epic 3: Coach Registration & Authentication

Coaches can create individual accounts via open self-registration or admin invitation, verify their email, and log in with full auth controls (lockout, CAPTCHA, remember-me, password reset).

**⚠️ Sprint Sequencing Gate:** Story 3.2 (Coach Registration Page) requires `LeagueListManager` from Epic 2 Story 2.1 to be **done** before it can be built — the registration form dropdown is populated from `LeagueListManager::getActiveList()`. Epic 2 must be fully complete before starting Epic 3 Story 3.2. Stories 3.1, 3.3, 3.4, 3.5 have no Epic 2 dependency and can begin as soon as Epic 1 is done.

### Story 3.1: Registration Service & Email Verification Backend

As a developer,
I want a `RegistrationService` class that handles account creation, email verification token generation, and verification link processing,
So that registration page files have a clean, tested API for the full account creation flow.

**Acceptance Criteria:**

**Given** valid registration form data (all required fields, passing complexity rules)
**When** `RegistrationService::register(array $data)` is called
**Then** a new row is inserted into `users` with `status = 'unverified'` and `role = 'user'`
**And** the password is stored as a bcrypt hash (plaintext is never stored)
**And** a unique verification token is generated and stored with a 48-hour expiry
**And** a verification email is sent via `EmailService` (blocking — failure surfaces an error)
**And** an `ActivityLogger` event `registration.verification_email_sent` is recorded
**And** the new user's ID is returned

**Given** a duplicate username is submitted
**When** `RegistrationService::register()` is called
**Then** a `DuplicateUsernameException` is thrown and no user row is created

**Given** a duplicate email is submitted
**When** `RegistrationService::register()` is called
**Then** a `DuplicateEmailException` is thrown and no user row is created

**Given** a valid, unexpired verification token
**When** `RegistrationService::verifyEmail(string $token)` is called
**Then** the user's `status` is updated to `'active'`
**And** the token is consumed (cannot be reused)
**And** an `ActivityLogger` event `registration.account_verified` is recorded
**And** an operational notification email is sent to admin (failure logged, not surfaced)
**And** the user's ID is returned

**Given** an expired verification token (> 48 hours old)
**When** `RegistrationService::verifyEmail()` is called
**Then** an `ExpiredTokenException` is thrown

**Given** a user with an expired verification token requests resend
**When** `RegistrationService::resendVerification(int $userId)` is called
**Then** a new token is generated replacing the old one and a new verification email is sent

**Given** the unit test suite is run
**When** this story is complete
**Then** `RegistrationServiceTest.php` passes all cases including success, duplicate username, duplicate email, expired token, and resend

**Files to create:**
- `includes/RegistrationService.php`
- `tests/unit/RegistrationServiceTest.php`

---

### Story 3.2: Coach Registration Page (Open Self-Registration)

As a coach,
I want to create an individual account by filling out a registration form at the QR code URL,
So that I have a personal login for the league portal.

**Acceptance Criteria:**

**Given** open registration is **enabled** in `settings` and a coach visits `public/coaches/register.php`
**When** the page loads
**Then** the registration form is displayed with fields: first name, last name, preferred name (optional), email, primary phone + type, secondary phone + type (optional), league dropdown (from `LeagueListManager::getActiveList()` + static "Other" option), username, password, confirm password, and reCAPTCHA v2 widget
**And** a "Step 1 of 2: Create Your Account" progress indicator (`.reg-progress`) is shown above the form
**And** the form has CSRF token field

**Given** open registration is **disabled** in `settings`
**When** a coach visits `public/coaches/register.php` without a valid invitation token
**Then** a "Registration is currently closed" message is shown and no form is rendered

**Given** the coach selects "Other" from the league dropdown
**When** the JS runs (`coaches-registration.js`)
**Then** a free-text "Enter your league name" input is revealed inline
**And** when a named league is re-selected, the free-text input is hidden

**Given** the coach submits the form with all required fields valid and CAPTCHA passed
**When** the POST is processed (PRG pattern)
**Then** `RegistrationService::register()` is called and the account is created with `status = 'unverified'`
**And** the coach is redirected to a "Check your email" confirmation page
**And** no session is created (coach is not logged in yet)

**Given** the coach submits with a duplicate username
**When** the POST is processed
**Then** the form re-renders with an inline error on the username field: "This username is already taken"
**And** all other field values are preserved

**Given** the coach submits with a password that fails complexity rules (FR-REG-5)
**When** the POST is processed
**Then** the form re-renders with an inline error on the password field naming the specific rule violated
**And** the password and confirm password fields are cleared; all other values preserved

**Given** the CAPTCHA challenge is failed
**When** the POST is processed
**Then** the form is rejected before account creation with an inline error: "Please complete the CAPTCHA"

**Files to create:**
- `public/coaches/register.php`
- `public/coaches/verify-email.php`
- `public/assets/js/coaches-registration.js` (initial version — "Other" reveal only; home field repeater added in Epic 4)

**Files to modify:**
- `assets/css/style.css` — add `.reg-progress`, `.status-unverified`, `.status-team-pending`, `.status-team-owner` CSS classes (UX-DR15)

---

### Story 3.3: Invitation Service & Admin Invitation Management

As an admin,
I want to send registration invitations to coaches by email and manage pending invitations,
So that coaches can register even when open self-registration is disabled.

**Acceptance Criteria:**

**Given** an admin enters a coach's email and clicks "Send Invitation"
**When** `InvitationService::send(string $email, int $adminUserId)` is called
**Then** a unique single-use token is generated with a 14-day expiry
**And** an invitation email is sent with the unique registration URL (blocking — failure surfaces error to admin)
**And** the invitation is stored with status `pending`
**And** an `ActivityLogger` event `registration.invitation_sent` is recorded

**Given** an invitation is sent to an email that already has a pending invitation
**When** `InvitationService::send()` is called
**Then** the prior token is cancelled and a new one is issued
**And** the new invitation email is sent

**Given** an invitation email address already has a registered account
**When** `InvitationService::send()` is called
**Then** an `EmailAlreadyRegisteredException` is thrown and no invitation is sent

**Given** a coach clicks an invitation link with a valid, unexpired token
**When** `InvitationService::validate(string $token)` is called
**Then** it returns the associated email address and marks the token as consumed on registration completion
**And** a valid token produces the registration form pre-filled with the email (read-only)

**Given** an expired invitation token (> 14 days old)
**When** a coach clicks the link
**Then** an "Invitation expired" page is shown with a message to contact the admin

**Given** an admin views `admin/users/invitations.php`
**When** the page loads
**Then** all pending invitations are listed with: email, sent date, expiry date, status (pending/completed/expired)
**And** each pending invitation has "Resend" and "Cancel" actions

**Given** an admin clicks "Resend" on a pending invitation
**When** the action completes (PRG pattern)
**Then** the old token is replaced with a new 14-day token and a new email is sent
**And** flash success confirms resend

**Implementation Scope — 2 distinct work areas (dev agent must complete both):**

**Area A — Backend Service (`includes/InvitationService.php` + tests)**
- `send(string $email, int $adminUserId)` — generate token, send blocking email, log event
- `validate(string $token)` — returns email, marks consumed on registration completion
- `cancel(int $invitationId, int $adminUserId)` — cancels pending token
- `resend(int $invitationId, int $adminUserId)` — replaces token, sends new email
- `getPendingList()` — returns all invitations with status
- Unit tests cover: success, duplicate email error, expired token, resend replaces token

**Area B — Admin UI (`public/admin/users/invitations.php`)**
- List all invitations with email, sent date, expiry, status badge
- "Send Invitation" form at top; PRG pattern on POST
- "Resend" and "Cancel" actions per pending row
- Empty state when no pending invitations exist

**Files to create:**
- `includes/InvitationService.php`
- `public/admin/users/invitations.php`
- `tests/unit/InvitationServiceTest.php`

---

### Story 3.4: Coach Login Page with Auth Controls

As a coach,
I want to log in with my username or email and password, with account lockout, remember-me, and CAPTCHA protection,
So that my account is secure and convenient to access.

**Acceptance Criteria:**

**Given** a coach visits `public/coaches/login.php`
**When** the page loads
**Then** the existing login form renders with username/email and password fields
**And** the reCAPTCHA widget is hidden (not yet visible)
**And** a "Remember me" checkbox is present

**Given** a coach submits valid credentials
**When** the POST is processed
**Then** a new session is created with session token rotation (NFR-SEC-5)
**And** `ActivityLogger` event `auth.login_success` is recorded
**And** the coach is redirected to `public/coaches/dashboard.php`

**Given** a coach submits invalid credentials
**When** the POST is processed
**Then** a generic error is shown: "Invalid username or password"
**And** a row is inserted into `login_attempts` with lazy-purge executed first (AR-9)
**And** `ActivityLogger` event `auth.login_failure` is recorded

**Given** the same IP has 3 or more failed attempts in the `login_attempts` table within 24 hours
**When** the login page renders
**Then** the reCAPTCHA v2 widget is revealed via the server passing the failed-attempt count to the page and `coaches-registration.js` toggling visibility (UX-DR14)
**And** the login POST is rejected with "Please complete the CAPTCHA" if CAPTCHA is not passed

**Given** 5 consecutive failed login attempts are recorded for an account
**When** a 6th attempt is made within 15 minutes
**Then** the login is rejected with "Account locked — please try again in 15 minutes"
**And** `ActivityLogger` event `auth.account_lockout` is recorded

**Given** a coach checks "Remember me" and logs in successfully
**When** the session is created
**Then** a secure persistent cookie is set with a hashed token stored in `remember_tokens` with 30-day expiry
**And** on subsequent visits with the cookie (after session expiry), the coach is re-authenticated without re-entering credentials

**Given** a coach clicks "Logout"
**When** `public/coaches/logout.php` is processed
**Then** the session is destroyed immediately
**And** any active remember-me token for that user is invalidated in `remember_tokens`
**And** `ActivityLogger` event `auth.logout` is recorded
**And** the coach is redirected to the login page

**Given** 60 minutes of inactivity
**When** the coach next makes a request
**Then** the session is expired and the coach is redirected to login

**Files to modify:**
- `public/coaches/login.php` — add lockout, CAPTCHA reveal, remember-me, session rotation
- `public/coaches/logout.php` — add remember-me token invalidation
- `public/assets/js/coaches-registration.js` — add CAPTCHA reveal logic (UX-DR14)

---

### Story 3.5: Password Reset Flow

As a coach,
I want to reset my password via a time-limited email link,
So that I can regain access to my account if I forget my password.

**Acceptance Criteria:**

**Given** a coach submits their email on the "Forgot Password" page
**When** `RegistrationService::requestPasswordReset(string $email)` is called
**Then** a unique reset token with 24-hour expiry is generated and stored
**And** a password reset email is sent with the reset link (blocking — failure surfaces error)
**And** `ActivityLogger` event `auth.password_reset_requested` is recorded
**And** if the email does not match any account, the same "check your email" confirmation is shown (no account enumeration)

**Given** a coach clicks a valid, unexpired reset link
**When** the reset form page loads
**Then** a form is displayed with new password and confirm password fields

**Given** the coach submits a valid new password (meeting FR-REG-5 complexity)
**When** the POST is processed
**Then** the password is updated as a bcrypt hash
**And** the reset token is consumed
**And** all active sessions for the user are invalidated
**And** `ActivityLogger` event `auth.password_reset_completed` is recorded
**And** the coach is redirected to login with a flash: "Password updated — please log in"

**Given** an expired reset token
**When** the coach clicks the link
**Then** an "This link has expired" page is shown with a link to request a new reset

**Files to create:**
- `public/coaches/forgot-password.php`
- `public/coaches/reset-password.php`

**Files to modify:**
- `includes/RegistrationService.php` — add `requestPasswordReset()` and `completePasswordReset()` methods

---

### Story 3.6: Registration Toggle & QR Code Display

As an admin,
I want to enable or disable open coach self-registration from the Settings panel, with a QR code displayed when enabled,
So that I control when coaches can self-register.

**Acceptance Criteria:**

**Given** an admin navigates to `admin/settings/index.php`
**When** the page loads
**Then** a "Coach Self-Registration" section is visible with a toggle (enabled/disabled)
**And** when **enabled**, the registration URL and a QR code image for that URL are displayed for the admin to share
**And** when **disabled**, only the toggle is shown with status "Registration is currently closed"

**Given** the admin changes the toggle and saves
**When** the POST is processed (PRG pattern)
**Then** the `settings` table is updated with no server restart required
**And** the change takes effect within one page load (FR-TOGGLE-3)
**And** `ActivityLogger` event `admin.registration_toggle_changed` is recorded with the new state

**Given** open registration is toggled off
**When** a coach visits the self-registration URL directly
**Then** they see the "Registration is currently closed" message
**And** existing valid invitation links still produce the registration form (FR-TOGGLE-4)

**Files to modify:**
- `public/admin/settings/index.php` — add registration toggle section with QR code display

---

## Epic 4: Team Registration & Coach Assignment

Coaches can register their team through the self-registration path (with home field locations and auto-generated team name), and admins can assign coaches to teams — giving coaches their Team Owner identity and a personalized dashboard.

### Story 4.1: TeamRegistrationService Backend

As a developer,
I want a `TeamRegistrationService` that handles pending team creation, home field location submission, and admin approval,
So that the team registration pages have a clean, tested API.

**Acceptance Criteria:**

**Given** a verified coach user (status `active`, role `user`) with valid team registration data
**When** `TeamRegistrationService::submit(int $userId, array $data)` is called
**Then** a new row is inserted into `teams` with `status = 'pending'`
**And** the team name is auto-generated as `{league_name}-{coach_last_name}` using the coach's registration league value and last name
**And** if the coach selected "Other" for league, the manually entered value is used in place of `{league_name}`
**And** up to 5 home field location entries are inserted into `locations` with `status = 'pending'` and `submitted_by_user_id` set

**Given** a coach who registered via invitation attempts team registration
**When** `TeamRegistrationService::submit()` is called
**Then** an `InvitationRegisteredUserException` is thrown and no team is created (FR-TEAMREG-11)

**Given** an admin calls `TeamRegistrationService::approve(int $teamId, int $adminUserId, int $divisionId)`
**When** the approval completes
**Then** the team's `status` is updated to `'active'` and `division_id` is set
**And** the submitting coach is assigned as Team Owner (equivalent to `UserManagementService::assignTeam()`)
**And** a notification email is sent to the coach (operational — failure logged only)
**And** `ActivityLogger` events `team.registration_approved` and `team.owner_assigned` are recorded

**Given** `TeamRegistrationService::getPendingRegistrations()` is called
**When** pending team registrations exist
**Then** it returns an array of teams with `status = 'pending'` including the submitting user's name

**Files to create:**
- `includes/TeamRegistrationService.php`
- `tests/unit/TeamRegistrationServiceTest.php`

---

### Story 4.2: Coach Team Registration Pages (Step 2 of Self-Registration)

As a coach,
I want to select a program/season and optionally add home field locations after verifying my email,
So that my team is submitted for admin review and I understand what happens next.

**Acceptance Criteria:**

**Given** a coach has just verified their email (or is logged in as `user` role with no team) and visits `public/coaches/team-register.php`
**When** the page loads
**Then** a "Step 2 of 2: Register Your Team" progress indicator is shown
**And** a list of programs/seasons with open registration is displayed for selection (division field is not shown)
**And** the auto-generated team name preview (`{league_name}-{coach_last_name}`) is shown read-only
**And** a home field location repeater is shown with one empty entry block (location name required, address optional)
**And** an "Add Another Location" button is present (disabled when 5 entries exist)

**Given** the coach clicks "Add Another Location"
**When** the JS runs (`coaches-registration.js` home field repeater — UX-DR10)
**Then** a new entry block is added up to a maximum of 5
**And** each entry block has a remove button that collapses the block (minimum 1 entry always visible)

**Given** the coach submits the team registration form
**When** the POST is processed (PRG pattern)
**Then** `TeamRegistrationService::submit()` is called
**And** the coach is redirected to `public/coaches/team-register-confirm.php`

**Given** a coach arrives at `public/coaches/team-register-confirm.php`
**When** the page loads
**Then** the confirmation message reads: "Account created and team registration submitted. An administrator will review your registration and assign you to your team."
**And** the coach is redirected to the public site (not the coach portal, since they have no team yet)
**And** no coach portal nav items requiring Team Owner are accessible

**Files to create:**
- `public/coaches/team-register.php`
- `public/coaches/team-register-confirm.php`

**Files to modify:**
- `public/assets/js/coaches-registration.js` — add home field repeater logic (UX-DR10)

---

### Story 4.3: Admin Team Assignment & Pending Queue

As an admin,
I want to view pending team registrations and assign coaches to teams,
So that coaches gain their Team Owner identity and can access team features.

**⚠️ Completion Rule:** This story is only complete when ALL THREE work areas (A, B, and C) are fully implemented and all acceptance criteria pass. Completing one or two areas does not constitute story completion.

**Acceptance Criteria:**

**Given** pending team registrations exist and an admin views `admin/teams/index.php`
**When** the page loads
**Then** a "Pending Team Registrations" section is shown above the existing active teams list
**And** each pending entry shows: coach name, auto-generated team name, submitted league, program/season requested, submitted date, and an "Approve" action

**Given** an admin clicks "Approve" on a pending team registration
**When** they select a division and confirm
**Then** `TeamRegistrationService::approve()` is called
**And** the team status changes to `active` with the selected division assigned
**And** the coach's role is elevated to `team_owner`
**And** the coach receives an email notification
**And** the pending entry disappears from the queue

**Given** an admin navigates to `admin/users/detail.php` for a verified coach account
**When** the admin clicks "Assign to Team"
**Then** a multi-select list of active-season teams is shown
**And** selecting one or more teams and confirming calls the assignment logic
**And** the coach's role is elevated to `team_owner` if this is their first team (FR-ASSIGN-2)
**And** a notification email is sent to the coach
**And** `ActivityLogger` event `team.owner_assigned` is recorded

**Given** an admin removes a team assignment from a coach
**When** the removal is confirmed
**Then** the `team_owners` record is deleted
**And** if the coach has no remaining teams, their role reverts to `user` (FR-ASSIGN-5)
**And** a notification email is sent to the coach
**And** `ActivityLogger` event `team.owner_removed` is recorded

**Given** a coach already has a team assigned and an admin attempts to assign a second team
**When** the assignment is attempted
**Then** a `TeamAlreadyClaimedException` is caught and a user-friendly error is shown: "This coach already has a team assigned. Multiple team assignments are not supported in this version."

**Implementation Scope — 3 distinct work areas (dev agent must complete all three):**

**Area A — Pending Team Registration Queue (`admin/teams/index.php`)**
- Add a "Pending Team Registrations" section above the existing active teams list
- Each row: coach name, auto-generated team name, submitted league, program/season, submitted date, "Approve" action
- Approve action triggers division selection modal → calls `TeamRegistrationService::approve()`

**Area B — Team Assignment UI (`admin/users/detail.php`)**
- "Assign to Team" button opens a team selector (active-season teams only)
- Assignment calls `UserManagementService::assignTeam()` → elevates role to `team_owner` if first team
- "Remove Assignment" triggers `UserManagementService::removeTeam()` → reverts role to `user` if no remaining teams
- Both actions send email notification and log `ActivityLogger` event

**Area C — UserManagementService Bootstrap (`includes/UserManagementService.php`)**
- Initial version: implement `assignTeam(int $userId, int $teamId, int $adminUserId)` and `removeTeam(int $userId, int $teamId, int $adminUserId)` only
- Enforce `TeamAlreadyClaimedException` before INSERT (FR-ASSIGN-3/4 deferred; 1:1 constraint active)
- Full CRUD expanded in Epic 8 Story 8.1 — do NOT implement those methods here

**Files to create:**
- `includes/UserManagementService.php` (initial version — `assignTeam` and `removeTeam` only; full CRUD in Epic 8)

**Files to modify:**
- `public/admin/teams/index.php` — add pending team registration queue section (Area A)
- `public/admin/users/detail.php` — create file or add team assignment section, may be new (Area B)

---

### Story 4.4: Coach Dashboard with Team Identity Hero

As a coach,
I want my dashboard to show my team name, season, and role status immediately after login,
So that I know I'm in the right place and can quickly navigate to my team's tools.

**Acceptance Criteria:**

**Given** a coach with Team Owner role and an assigned team logs in and visits `public/coaches/dashboard.php`
**When** the page loads
**Then** the Coach Identity Hero (`.coach-hero`) banner is shown with: coach's name (small, muted), team name (large, bold as `<h1>`), season/division (small), and "Team Owner" role badge (green `.status-team-owner`)
**And** the coach's displayed name uses their **preferred name** if set, otherwise their first name (e.g., "Coach Jones" where "Jones" is last name, and "preferred name" or "first name" is the given name portion)
**And** the Action Card Grid (`.coach-action-grid`) shows cards for: Score Input, Schedule Change, My Schedule, Contacts
**And** each action card has a colored icon, label, and sub-label

**Given** a coach whose team registration is `pending` (no Team Owner role yet) logs in
**When** the dashboard loads
**Then** the `.coach-hero` banner shows the `pending` state: amber badge, "Pending Team Approval" message, and the informational text: "Your team registration is pending admin review. You'll receive an email when approved."
**And** Score Input, Schedule Change, and My Schedule action cards are shown as `disabled` (grayed, not clickable)

**Given** a coach registered via invitation with no team assigned logs in
**When** the dashboard loads
**Then** the `.coach-hero` banner shows the `unassigned` state: gray, "No team assigned — contact your admin"
**And** Score Input, Schedule Change, and My Schedule action cards are shown as `disabled`

**Given** `PermissionGuard::requireRole('user')` is called at the top of `dashboard.php`
**When** an unauthenticated user visits the page
**Then** they are redirected to the login page with the intended URL stored in session for post-login redirect

**Files to modify:**
- `public/coaches/dashboard.php` — replace current card layout with hero + action card grid
- `assets/css/style.css` — add `.coach-hero`, `.coach-action-grid`, `.coach-action-card` CSS (UX-DR1, UX-DR3)

---

## Epic 5: Team-Scoped Score Submission

Team Owner coaches can submit and edit scores for their assigned team's past/elapsed games, with standings updated immediately and full server-side permission enforcement.

### Story 5.1: ScoreService Backend

As a developer,
I want a `ScoreService` class that enforces team-scoping, time-gating, and standings updates for score submission,
So that score submission page files have a clean, tested API with all permission rules enforced server-side.

**Acceptance Criteria:**

**Given** a Team Owner submits a score for a game involving their assigned team that is past/elapsed
**When** `ScoreService::submit(int $userId, int $gameId, int $homeScore, int $awayScore)` is called
**Then** `TeamScope::getScopedTeams($userId)` is called to verify the game involves the coach's team
**And** `GameTimeGate::isEligible($game)` is called to verify the game is past/elapsed
**And** the home and away scores are saved to the game record
**And** the game `status` is set to `'completed'` (FR-SCORE-7)
**And** standings are updated immediately (existing standings update logic reused)
**And** an operational notification email is sent to admin (failure logged only)
**And** `ActivityLogger` event `score.submitted` is recorded with `user_id`, `game_id`, and both scores

**Given** the coach attempts to submit a score for a game not involving their team
**When** `ScoreService::submit()` is called
**Then** a `TeamScopeViolationException` is thrown and no score is saved (FR-RESTRICTIONS-4)

**Given** the coach attempts to submit a score for a future game
**When** `ScoreService::submit()` is called
**Then** a `GameNotEligibleException` is thrown and no score is saved (FR-RESTRICTIONS-5)

**Given** a game already has a recorded score and the coach submits an edit
**When** `ScoreService::edit(int $userId, int $gameId, int $homeScore, int $awayScore)` is called
**Then** the score is updated and standings recalculated
**And** `ActivityLogger` event `score.edited` is recorded with old and new score values

**Given** `ScoreService::getEligibleGames(int $userId)` is called
**When** the coach has assigned teams
**Then** it returns only games where: the game involves the coach's team AND `GameTimeGate::isEligible()` returns true AND game status is not `completed`

**Files to create:**
- `includes/ScoreService.php`
- `tests/unit/ScoreServiceTest.php`

---

### Story 5.2: Score Submission Page

As a Team Owner coach,
I want to submit scores for my team's past games using a large, mobile-friendly score entry interface,
So that game results are recorded quickly and standings are updated immediately.

**Acceptance Criteria:**

**Given** a Team Owner coach navigates to `public/coaches/score-input.php`
**When** the page loads
**Then** `PermissionGuard::requireRole('team_owner')` is enforced at the top of the file
**And** `ScoreService::getEligibleGames($userId)` is called to build the game list

**Given** zero eligible games exist
**When** the page loads
**Then** an `alert alert-info` empty state is shown: "No games currently need a score — games must be past their scheduled time to be eligible." (UX-DR16)

**Given** exactly one eligible game exists
**When** the page loads
**Then** the game is auto-selected (UX-DR7) — no dropdown is shown
**And** the VS Score Entry layout (`.vs-score-entry`) is immediately visible with the away team name labeling the left input and the home team name labeling the right input
**And** both score inputs use `font-size: 2rem`, `inputmode="numeric"`, `min="0" max="99"`, and a minimum 44px tap height

**Given** multiple eligible games exist
**When** the page loads
**Then** a game selection dropdown is shown with each option displaying: Game #, date, Away @ Home
**And** selecting a game reveals the VS Score Entry layout below

**Given** the coach enters valid scores and clicks "Submit Score"
**When** the POST is processed (PRG pattern with CSRF validation)
**Then** `ScoreService::submit()` is called server-side
**And** on success, an `alert alert-success` is shown: "Score submitted. Game #[N], [Date] — [Away Team] [score], [Home Team] [score]. Standings updated." (UX-DR17)
**And** the scored game no longer appears in the eligible list

**Given** a network/server error occurs during submission
**When** the error response is returned
**Then** an `alert alert-danger` is shown: "Score not submitted — please check your connection and try again. Your scores are preserved." (UX-DR18)
**And** the entered scores remain in the input fields

**Given** a coach attempts to submit a score for a game not in their eligible list (server-side bypass attempt)
**When** `ScoreService::submit()` throws `TeamScopeViolationException` or `GameNotEligibleException`
**Then** a `403` response is returned and an error flash is shown; no score is saved

**Files to modify:**
- `public/coaches/score-input.php` — replace current implementation with team-scoped, time-gated VS layout
- `assets/css/style.css` — add `.vs-score-entry`, `.vs-score-input` CSS classes (UX-DR2)

---

## Epic 6: Team-Scoped Reschedule Requests

Team Owner coaches can submit, view, and cancel reschedule requests for their team's games, with admin notifications and full status tracking.

### Story 6.1: RescheduleService Backend

As a developer,
I want a `RescheduleService` class that handles team-scoped reschedule request creation, status tracking, and cancellation,
So that the reschedule pages have a clean, tested API with all scoping rules enforced server-side.

**Acceptance Criteria:**

**Given** a Team Owner submits a reschedule request for a game involving their team that is not scored or cancelled
**When** `RescheduleService::submit(int $userId, int $gameId, array $requestData)` is called
**Then** `TeamScope::getScopedTeams($userId)` verifies the game involves the coach's team
**And** a new reschedule request record is created with status `pending` and the proposed date/time and reason stored
**And** an operational notification email is sent to admin (failure logged only)
**And** `ActivityLogger` event `reschedule.request_submitted` is recorded

**Given** the coach attempts to submit a reschedule request for a game not involving their team
**When** `RescheduleService::submit()` is called
**Then** a `TeamScopeViolationException` is thrown and no request is created

**Given** a coach calls `RescheduleService::cancel(int $requestId, int $userId)`
**When** the request status is `pending`
**Then** the request status is updated to `cancelled`
**And** `ActivityLogger` event `reschedule.request_cancelled` is recorded
**And** the game returns to the eligible list on next page load

**Given** a coach attempts to cancel a request with status `approved` or `denied`
**When** `RescheduleService::cancel()` is called
**Then** a `RequestNotCancellableException` is thrown (FR-RESCHED-7)

**Given** `RescheduleService::getEligibleGames(int $userId)` is called
**When** the coach has assigned teams
**Then** it returns only games where: the game involves the coach's team AND game status is not `completed` AND game status is not `cancelled`

**Given** `RescheduleService::getCoachRequests(int $userId)` is called
**Then** it returns all reschedule requests submitted by the coach with their current status

**Files to create:**
- `includes/RescheduleService.php`
- `tests/unit/RescheduleServiceTest.php`

---

### Story 6.2: Reschedule Request Page

As a Team Owner coach,
I want to submit a reschedule request for one of my team's games, with my contact info pre-filled,
So that I can request a schedule change without re-entering information I've already provided.

**Acceptance Criteria:**

**Given** a Team Owner coach navigates to `public/coaches/schedule-change.php`
**When** the page loads
**Then** `PermissionGuard::requireRole('team_owner')` is enforced
**And** `RescheduleService::getEligibleGames($userId)` builds the game dropdown

**Given** zero eligible games exist for reschedule
**When** the page loads
**Then** an `alert alert-info` empty state is shown: "No games are available to reschedule — scored and cancelled games are not eligible." (UX-DR16)

**Given** the coach selects a game from the dropdown
**When** the selection JS fires
**Then** the Game Detail Reveal Panel (`.game-detail-panel`) shows the current game details: date, time, location (UX-DR5, `aria-live="polite"`)
**And** the request form fields are revealed: new date (required), new time (required), new location (required), reason (required)
**And** the contact info section is pre-populated (read-only) from the coach's profile: full name, primary phone, email (UX-DR8)
**And** a "Update in your profile →" link is shown alongside the pre-populated fields

**Given** the coach submits the form with all required fields valid and CSRF token present
**When** the POST is processed (PRG pattern)
**Then** `RescheduleService::submit()` is called
**And** on success, an `alert alert-success` is shown: "Request submitted. You will receive an email when your request is reviewed." (UX-DR17)

**Given** a network/server error during submission
**When** the error is returned
**Then** an `alert alert-danger` preserves the form input (UX-DR18)

**Given** the coach has pending reschedule requests visible in the schedule view
**When** a coach clicks the cancel button on a pending request
**Then** a confirmation is shown: "Cancel this request?"
**And** on confirmation, `RescheduleService::cancel()` is called
**And** the request disappears from the pending list and a flash success is shown

**Files to modify:**
- `public/coaches/schedule-change.php` — add team-scoping, contact pre-population, Game Detail Reveal Panel, cancel flow

---

## Epic 7: Coach Profile, Team Schedule & Authenticated Resources

Coaches can manage their profile, view their team's schedule with sort and filter, and access authenticated league resources.

### Story 7.1: ProfileService Backend

As a developer,
I want a `ProfileService` class that handles profile field updates and self-service password changes,
So that the coach profile page has a clean, tested API.

**Acceptance Criteria:**

**Given** a coach submits updated name fields (first, last, preferred)
**When** `ProfileService::updateName(int $userId, array $nameData)` is called
**Then** the `users` table is updated with the new values
**And** `ActivityLogger` event `profile.name_updated` is recorded (field names only, no values logged)

**Given** a coach submits an updated primary phone number and type
**When** `ProfileService::updatePhone(int $userId, string $phone, string $type, string $role = 'primary')` is called
**Then** the phone record is updated or created with the correct type (Home/Work/Cell)
**And** `ActivityLogger` event `profile.phone_updated` is recorded

**Given** a coach submits a secondary phone number removal
**When** `ProfileService::removeSecondaryPhone(int $userId)` is called
**Then** the secondary phone record is deleted
**And** the primary phone is unaffected (FR-PROFILE-4)

**Given** a coach submits a password change with correct current password and valid new password
**When** `ProfileService::changePassword(int $userId, string $currentPassword, string $newPassword)` is called
**Then** `password_verify()` confirms the current password against the stored hash
**And** the new password is stored as a bcrypt hash
**And** `ActivityLogger` event `profile.password_changed` is recorded

**Given** the coach provides an incorrect current password
**When** `ProfileService::changePassword()` is called
**Then** a `IncorrectCurrentPasswordException` is thrown and no password is changed

**Given** the new password fails FR-REG-5 complexity rules
**When** `ProfileService::changePassword()` is called
**Then** a `WeakPasswordException` is thrown

**Files to create:**
- `includes/ProfileService.php`
- `tests/unit/ProfileServiceTest.php`

---

### Story 7.2: Coach Profile Page

As an authenticated coach,
I want to update my name, phone numbers, and password from a profile page,
So that my account information stays current and my password remains secure.

**Acceptance Criteria:**

**Given** an authenticated coach navigates to `public/coaches/profile.php`
**When** the page loads
**Then** `PermissionGuard::requireRole('user')` is enforced (any authenticated user, not just Team Owner)
**And** the form displays current values for: first name, last name, preferred name, primary phone + type, secondary phone + type
**And** the team name (if assigned) is shown as a read-only field with label "Team Name (managed by admin)" (FR-PROFILE-7)
**And** a separate "Change Password" section is shown with: current password, new password, confirm new password

**Given** the coach updates name fields and submits
**When** the POST is processed (PRG pattern, CSRF validated)
**Then** `ProfileService::updateName()` is called
**And** a flash success: "Profile updated."

**Given** the coach adds or updates their primary phone and type
**When** the POST is processed
**Then** `ProfileService::updatePhone()` is called and the record is saved

**Given** the coach submits a secondary phone removal (clears the secondary phone field)
**When** the POST is processed
**Then** `ProfileService::removeSecondaryPhone()` is called

**Given** the coach submits the Change Password section with correct current password and a valid new password
**When** the POST is processed
**Then** `ProfileService::changePassword()` is called and the password is updated
**And** a flash success: "Password changed."

**Given** the coach provides an incorrect current password
**When** the POST is processed
**Then** the form re-renders with an inline error on the current password field: "Current password is incorrect"

**Files to create:**
- `public/coaches/profile.php`

---

### Story 7.3: Coach Team Schedule View

As a Team Owner coach,
I want to view my team's full schedule with sortable and filterable columns,
So that I can quickly find specific games or review the full season at a glance.

**Acceptance Criteria:**

**Given** a Team Owner coach navigates to `public/coaches/schedule.php`
**When** the page loads
**Then** `PermissionGuard::requireRole('team_owner')` is enforced
**And** `CoachScheduleService::getTeamSchedule($userId)` returns all games for the coach's assigned team(s) regardless of game status
**And** the schedule table displays columns: Game Number, Date, Time, Away Team, Home Team, Location, Score
**And** the column structure matches the existing master public schedule (FR-COACHSCHEDULE-6)

**Given** the coach clicks a column header
**When** the sort JS fires (`coaches-schedule.js`, UX-DR12)
**Then** the table rows are sorted ascending by that column
**And** clicking the same header again sorts descending

**Given** the coach types in a column filter input
**When** the filter JS fires
**Then** only rows matching the filter are shown in that column
**And** filtering multiple columns simultaneously applies all filters (AND logic)

**Given** the Date column filter is used
**When** the coach enters a date range
**Then** only games within that range are shown

**Given** the coach clicks "Clear Filters"
**When** the action fires
**Then** all filters are cleared and the full team schedule is restored

**Given** no games are scheduled for the coach's team
**When** the page loads
**Then** an `alert alert-info` empty state is shown: "No games scheduled for your team yet. Check back after your team assignment is confirmed." (UX-DR16)

**Files to create:**
- `includes/CoachScheduleService.php`
- `public/coaches/schedule.php`
- `public/assets/js/coaches-schedule.js` (UX-DR12)

---

### Story 7.4: Authenticated Access to Rules & Contact Directory

As an authenticated coach,
I want to access the league rules documents and contact directory after logging in,
So that I can find the information I need without the shared password.

**Acceptance Criteria:**

**Given** an authenticated user (role ≥ `user`) navigates to `public/coaches/rules.php`
**When** the page loads
**Then** `PermissionGuard::requireRole('user')` is enforced
**And** the page displays links to all documents uploaded via the existing admin Document Management feature

**Given** an authenticated user navigates to `public/coaches/contacts.php`
**When** the page loads
**Then** `PermissionGuard::requireRole('user')` is enforced
**And** the existing contacts page content is displayed (no content changes; auth gate added)

**Given** an unauthenticated user visits either page directly
**When** `PermissionGuard::requireRole('user')` fires
**Then** they are redirected to the login page
**And** the intended URL is stored in session so they land on the intended page after login (FR-RESOURCES-4)

**Files to create:**
- `public/coaches/rules.php`

**Files to modify:**
- `public/coaches/contacts.php` — add `PermissionGuard::requireRole('user')` at the top

---

## Epic 8: Admin User Management

Admins have full CRUD control over all user accounts — view, search, filter, edit, disable/re-enable, delete, reset passwords, and manage roles.

### Story 8.1: UserManagementService Full CRUD

As a developer,
I want `UserManagementService` expanded with full user CRUD, role management, disable/enable, delete, and password reset operations,
So that admin user management pages have a clean, tested API for all account operations.

**Acceptance Criteria:**

**Given** `UserManagementService::getList(array $filters, int $page, int $perPage)` is called
**When** filters include search term, role, and status
**Then** it returns a paginated array of users matching all active filters
**And** `total_count` is included for pagination rendering

**Given** `UserManagementService::update(int $userId, array $data)` is called with valid name/email/phone/username fields
**When** the update completes
**Then** the `users` table is updated and `ActivityLogger` event `admin.user_edited` is recorded

**Given** `UserManagementService::setRole(int $userId, string $role, int $adminUserId)` is called
**When** the role is valid (`user`, `team_owner`, `administrator`)
**Then** the user's role is updated in the `roles` table
**And** `ActivityLogger` event `admin.user_role_changed` is recorded

**Given** `UserManagementService::disable(int $userId, int $adminUserId)` is called
**When** the operation completes
**Then** the user's `status` is set to `'inactive'`; any active sessions are invalidated
**And** subsequent login attempts by that user return: "Your account has been disabled — contact the league administrator"
**And** `ActivityLogger` event `admin.user_disabled` is recorded

**Given** `UserManagementService::enable(int $userId, int $adminUserId)` is called
**Then** the user's `status` is set to `'active'` and login is restored

**Given** `UserManagementService::delete(int $userId, int $adminUserId)` is called
**When** the admin has confirmed the action
**Then** all `team_owners` records for that user are removed (assignments cleared)
**And** the user row is deleted
**And** `ActivityLogger` event `admin.user_deleted` is recorded

**Given** `UserManagementService::resetPassword(int $userId, int $adminUserId)` is called
**Then** a temporary password is generated and stored as a bcrypt hash with a `force_password_change` flag
**And** the temporary password is returned to the admin (displayed once, not stored in plaintext)
**And** on the user's next login, they are forced to set a new password before accessing any page

**Files to modify:**
- `includes/UserManagementService.php` — expand with full CRUD, disable, delete, reset-password methods
- Add `tests/unit/UserManagementServiceTest.php`

---

### Story 8.2: Admin User List Page

As an admin,
I want to view, search, and filter all user accounts in a paginated list,
So that I can quickly find any coach and take action on their account.

**Acceptance Criteria:**

**Given** an admin navigates to `public/admin/users/index.php`
**When** the page loads
**Then** a paginated table of all user accounts is shown with columns: Name, Username, Email, Role, Status, Registered Date, Actions
**And** filter inputs are shown for: search (name/username/email), role dropdown, status dropdown
**And** the table uses `table-sm` compact density for desktop

**Given** the admin enters a search term and submits the filter form
**When** the GET request is processed
**Then** only matching accounts are shown (name OR username OR email contains search term)
**And** the applied filters are preserved in the form inputs

**Given** zero users match the filters
**When** the page loads
**Then** an appropriate empty state is shown: "No users match your search. Try adjusting the filter." (UX-DR16)

**Given** the admin clicks "View" on any user row
**When** the link is followed
**Then** they are taken to `admin/users/detail.php` for that user

**Files to create:**
- `public/admin/users/index.php`

---

### Story 8.3: Admin User Detail Page

As an admin,
I want a detail page for each user account where I can edit their profile, change their role, disable/delete their account, and reset their password,
So that I have full control over every coach account.

**Acceptance Criteria:**

**Given** an admin navigates to `public/admin/users/detail.php?id={userId}`
**When** the page loads
**Then** the user's current name, email, username, phone, role, status, and team assignments are displayed
**And** an edit form is shown pre-populated with current values
**And** action buttons are shown for: Change Role, Disable/Enable Account, Reset Password, Delete Account

**Given** the admin submits the edit form with valid changes
**When** the POST is processed (PRG pattern)
**Then** `UserManagementService::update()` is called and a flash success is shown

**Given** the admin changes the user's role via the role selector and submits
**When** the POST is processed
**Then** `UserManagementService::setRole()` is called and the role is updated

**Given** the admin clicks "Disable Account" and confirms
**When** the POST is processed
**Then** `UserManagementService::disable()` is called
**And** the page reloads showing "Account Disabled" status badge

**Given** the admin clicks "Reset Password" and confirms
**When** the POST is processed
**Then** `UserManagementService::resetPassword()` is called
**And** the temporary password is shown once on the confirmation page: "Temporary password: [TEMP]. Share this with the coach — it cannot be shown again."

**Given** the admin clicks "Delete Account" and confirms the confirmation step
**When** the POST is processed
**Then** `UserManagementService::delete()` is called
**And** the admin is redirected to `users/index.php` with a flash: "Account deleted."

**Files to modify:**
- `public/admin/users/detail.php` — **Extends the file created in Story 4.3. Do NOT create a new file or overwrite the existing one.** Add full CRUD edit form, role selector, disable/enable, reset password, and delete actions to the team assignment UI already in place from Story 4.3.

---

## Epic 9: Migration Cutover & Shared Credential Deprecation

Admins can monitor coach onboarding progress via the pre-cutover gap checklist and, once all active-season teams have assigned Team Owners, permanently disable the shared coach credential to complete the migration.

### Story 9.1: CutoverService Backend

As a developer,
I want a `CutoverService` class that provides the pre-cutover gap checklist and the shared credential disable operation,
So that the admin cutover panel has a safe, tested API for the most consequential action in the system.

**Acceptance Criteria:**

**Given** `CutoverService::getGapChecklist()` is called
**When** active-season teams exist
**Then** it returns an array of all active-season teams, each with: team name, division, program, list of assigned Team Owners (may be empty), and a boolean `has_gap`
**And** teams with zero assigned Team Owners have `has_gap = true`

**Given** `CutoverService::getGapCount()` is called
**Then** it returns the integer count of active-season teams with zero assigned Team Owners

**Given** `CutoverService::disableSharedCredential(int $adminUserId)` is called
**When** `getGapCount()` returns 0
**Then** the `coaches_password` setting in the `settings` table is set to a disabled/null state so no auth path can use it
**And** `ActivityLogger` event `admin.shared_credential_disabled` is recorded with admin user ID and timestamp
**And** `true` is returned

**Given** `CutoverService::disableSharedCredential()` is called
**When** `getGapCount()` returns > 0
**Then** a `CutoverGapsRemainingException` is thrown and no change is made (FR-USERMGMT-9)

**Given** `CutoverService::isSharedCredentialActive()` is called
**Then** it returns `true` if the shared credential is still enabled, `false` if disabled

**Note on FR-RESTRICTIONS-1/2/7 enforcement:** These restrictions (no season termination, no division/program changes, no admin functions for Team Owners) are **NOT new code in this epic**. They are enforced from Epic 4 onward by two mechanisms already in place:
1. `PermissionGuard::requireRole('administrator')` is called at the top of every admin-only page — Team Owners have no route to those pages.
2. Team Owner session role (`team_owner`) never grants `administrator`-level access via the roles/permissions tables set up in the foundation schema.

Story 9.1 and 9.2 do **not** need to add new permission checks for FR-RESTRICTIONS-1/2/7. Their scope is solely the gap checklist and shared credential disable. This note exists to prevent the dev agent from writing redundant enforcement code in Epic 9.

**Files to create:**
- `includes/CutoverService.php`
- `tests/unit/CutoverServiceTest.php`

---

### Story 9.2: Admin Migration Cutover Panel

As an admin,
I want a migration panel showing team onboarding status and a "Disable Shared Login" button that is locked until all gaps are resolved,
So that I can complete the transition to individual coach accounts with full confidence.

**Acceptance Criteria:**

**Given** an admin navigates to the migration panel (within `admin/settings/` section)
**When** the page loads
**Then** a summary stat row shows 3 cards: "Teams Covered", "Teams with Gaps", "Active Team Owners"
**And** a pre-cutover gap checklist table (`.gap-row-covered` / `.gap-row-missing`) shows all active-season teams with their assignment status (UX-DR6)
**And** each gap row has an "Assign Coach →" action link pointing to that team's admin management page

**Given** one or more teams have no assigned Team Owner (gap count > 0)
**When** the page loads
**Then** an `alert alert-warning` banner is shown: "X active-season team(s) have no assigned Team Owner. Resolve all gaps before disabling the shared credential."
**And** the "Disable Shared Login" button has the `disabled` attribute with inline text: "All teams must have at least one assigned Team Owner before you can disable the shared login."

**Given** all active-season teams have at least one assigned Team Owner (gap count = 0)
**When** the page loads
**Then** the warning banner is cleared
**And** all gap checklist rows show `.gap-row-covered` (green text + check icon)
**And** the "Disable Shared Login" button is enabled (`btn-danger`)

**Given** the admin clicks the enabled "Disable Shared Login" button
**When** the modal appears
**Then** the `modal-dialog-centered` cutover confirmation modal is shown with `data-bs-backdrop="static"` (no backdrop dismiss, no × close) (UX-DR11)
**And** the modal body reads: "This will permanently disable the shared coach password. All coaches must use their individual accounts. This cannot be automatically undone."
**And** the footer has `btn-secondary` Cancel and `btn-danger` Confirm

**Given** the admin clicks "Confirm" in the modal
**When** the POST is processed
**Then** `CutoverService::disableSharedCredential()` is called
**And** on success: an `alert alert-success` is shown: "Shared credential disabled. All coach access is now through individual accounts. Rollback window: 30 days."
**And** the "Disable Shared Login" button is replaced with a "Cutover Complete" success badge
**And** `ActivityLogger` event `admin.shared_credential_disabled` is recorded

**Given** the shared credential has been disabled and a coach attempts to log in using the old shared password
**When** the login POST is processed
**Then** they receive: "Coach login has been updated — please use your individual account."

**Given** the shared credential has been disabled and the admin navigates back to the cutover panel
**When** the page loads
**Then** the panel shows "Cutover Complete" status and the "Disable Shared Login" button is not shown

**Files to create:**
- `includes/CutoverService.php` *(defined in Story 9.1)*
- `public/admin/settings/sections/cutover.php` (or equivalent section within settings)

**Files to modify:**
- `public/admin/settings/index.php` — add link/section for migration cutover panel
- `public/coaches/login.php` — add disabled-shared-credential check with appropriate message

---

## Epic 10: Post-Launch Hardening

Address race conditions, missing transactions, and performance issues identified during code reviews. Non-blocking for go-live.

### Story 10.1: Post-Launch Hardening

As a developer,
I want to resolve race conditions in score editing and reschedule cancellation, add transaction safety to reschedule submission, optimize CSRF token usage, and fix the N+1 query on pending registrations,
So that the application is resilient under concurrent use and performs well as data grows.

**Acceptance Criteria:**

**Given** two concurrent `edit()` POST requests for the same game
**When** both pass `enforceCompletedForEdit`
**Then** only one succeeds; the second receives a conflict error (optimistic lock or row versioning)

**Given** `RescheduleService::submit()` inserts a reschedule request
**When** a downstream failure occurs (notification or ActivityLogger)
**Then** the DB row is rolled back — no orphaned rows

**Given** two concurrent cancel requests for the same reschedule
**When** both pass the status check
**Then** only one succeeds; the second receives a conflict error

**Given** the score submission page loads with up to 20 completed games
**When** the page renders edit sections
**Then** a single CSRF token is reused across forms, not one per row

**Given** the admin pending queue page loads
**When** pending registrations exist
**Then** season and program data is fetched via JOIN, not N+1 per-row queries

**Files to modify:**
- `includes/ScoreService.php` — optimistic lock on edit
- `includes/RescheduleService.php` — transaction wrapping, cancel lock
- `public/coaches/scores.php` — CSRF token reuse
- `public/admin/teams/index.php` — JOIN optimization
- New migration for `games.updated_at` or version column

---

## Epic 11: Team Registration Rework

**Goal:** Decouple team registration from user registration, enforce per-season limits, and give admins full CRUD control over team registrations (create, approve, reject, edit, delete) with open/close season gates.

**Stories:**

### Story 11.1: Separate Registration Flows
As a coach, I want user registration and team registration to be separate flows, so that I am not blocked on team registration if I have not yet verified my email.

**Acceptance Criteria:**
- Remove "Step 1 of 2" / "Step 2 of 2" framing from user registration and email verification pages
- Update `verify-email.php` success redirect to go to login rather than team registration
- Add "Register a Team" nav link to `coaches_nav.php` (conditional: only shown when coach has no `team_owners` row)
- Coach can reach team registration at any time post-login via the nav link

**Files to modify:**
- `public/register.php`
- `public/verify-email.php`
- `includes/coaches_nav.php` (or equivalent nav partial)

### Story 11.2: User Team Self-Registration
As a coach, I want to register my team from the coach dashboard or nav, so that team registration is accessible independent of user registration.

**Acceptance Criteria:**
- If coach has no active team, dashboard shows a CTA card for team registration
- `team-register.php` renders pending-registration guard if coach already has a pending team (pending message + no form)
- If no seasons have `season_status = 'Registration'`, display "No seasons are currently open for registration" (no form)
- Depends on Story 11.1

**Files to modify:**
- `public/coaches/dashboard.php`
- `public/coaches/team-register.php`

### Story 11.3: One Team Per Season Limit
As an admin, I want to limit a user's ability to register teams, so that a user can only register one team per season.

**Acceptance Criteria:**
- `TeamRegistrationService::submit()` throws `RuntimeException('You already have a team registration for this season.')` if user has a `pending` or `active` team for the season
- Rejected registrations do not block re-registration for the same season
- `team-register.php` surfaces the error inline

**Files to modify:**
- `includes/TeamRegistrationService.php`
- `tests/unit/TeamRegistrationServiceTest.php`

### Story 11.4: Admin Team Registration Approval
As an admin, I want the ability to approve all team registrations, so that team registration is not unchecked.

**Acceptance Criteria:**
- Admin can reject a pending registration (new `reject()` method + UI button + flash)
- Rejected registrations panel shown in `admin/teams/index.php` (collapsible, shows team name, coach, season, date)
- Rejection email sent to coach (new email template migration if needed)
- Approved registration removed from pending queue

**Files to modify:**
- `includes/TeamRegistrationService.php`
- `public/admin/teams/index.php`
- `tests/unit/TeamRegistrationServiceTest.php`
- `database/migrations/017_seed_team_rejection_email_template.sql` (if needed)

### Story 11.5: Admin Season Registration Open/Close
As an admin, I want the ability to open and close team registration for a given season, so that team registration can only occur during defined time periods.

**Acceptance Criteria:**
- One-click "Open Registration" button sets `season_status = 'Registration'`; "Close Registration" sets it back to `'Active'`
- Buttons appear in `admin/seasons/index.php` action column (replace full edit form for this action)
- Coach `team-register.php` already gates on `season_status = 'Registration'` — no change needed

**Files to modify:**
- `public/admin/seasons/index.php`

### Story 11.6: Admin Create Team Registration
As an admin, I want the ability to create a team registration and assign a user, so that I can register teams on behalf of a user.

**Acceptance Criteria:**
- New `adminCreate()` method in `TeamRegistrationService` (skips invitation check and 1-per-season limit)
- Admin form in `admin/teams/index.php` with user picker, season, league, and team name (auto-populated via JS)
- Admin-created registrations appear in the pending queue

**Files to modify:**
- `includes/TeamRegistrationService.php`
- `public/admin/teams/index.php`

### Story 11.7: Admin Edit, Update, and Delete Team Registrations
As an admin, I want the ability to edit, update, and delete a team registration, so that I have full control over registration records.

**Acceptance Criteria:**
- `update()` edits `pending` or `rejected` registrations (throws for `active`)
- `deleteRegistration()` deletes any registration transactionally (team_locations + team_owners cascade, role revert if owner loses last team), blocked if game assignments exist
- Edit and Delete buttons visible in pending registrations panel
- Existing `delete_team` action refactored to call `deleteRegistration()` so game-assignment logic lives in one place

**Files to modify:**
- `includes/TeamRegistrationService.php`
- `public/admin/teams/index.php`
- `tests/unit/TeamRegistrationServiceTest.php`


## Epic 14: Location Management & Google Maps Integration

**Epic Goal:** Integrate Google Maps into location entry and schedule viewing flows to improve usability, prevent duplicate location entries, streamline game location selection, and allow users to easily get directions to game fields.

**Business Value:** Reduces data quality issues from duplicate locations, speeds up game scheduling with a curated location dropdown, and gives coaches/parents a one-click path to directions.

**Scope:** Team registration location entry, admin game entry, public and coach schedule views.

---

### Story 14.1: Location Entry with Google Maps Preview & Duplicate Detection

As a team owner,
I want to see a Google Maps preview when I enter a home field location and be warned if it looks like a duplicate,
so that I can verify the address is correct and avoid creating redundant location records.

**Acceptance Criteria:**
1. On the team registration page (`coaches/team-register.php`), each location block gains a "Preview on Map" button that opens a Google Maps embed/link in a modal or new tab using the entered name + address.
2. When the registration form is submitted with at least one location, the server checks the `locations` table for potential duplicates using name similarity (PHP `similar_text()` ≥ 70%) or exact address match.
3. If a potential duplicate is found, the user is presented with the candidate matches and asked to confirm whether their location is the same (in which case the existing record is reused or the new submission is discarded) or different (proceeds normally).
4. If the user confirms it is a duplicate, the new location is not inserted; the team registration still proceeds normally using the existing location.
5. Config gains `GOOGLE_MAPS_API_KEY` constant loaded via `EnvLoader::get('GOOGLE_MAPS_API_KEY', '')` in `config.prod.php` and `config.staging.php`.

**Files to modify:**
- `public/coaches/team-register.php` — add map preview button + duplicate confirmation UI
- `public/assets/js/coaches-registration.js` — map preview JS logic
- `includes/TeamRegistrationService.php` — add `findDuplicateCandidates()` + duplicate-aware location insert
- `includes/config.prod.php` / `includes/config.staging.php` — add `GOOGLE_MAPS_API_KEY`

---

### Story 14.2: Game Location Selection Dropdown with "Not Listed" Flow

As an admin,
I want to select an existing location from a dropdown when entering a new game, with the ability to add a new location inline if it's not listed,
so that game locations are consistent and tied to the `locations` table rather than free-text entries.

**Acceptance Criteria:**
1. The "Add Game" form in `public/admin/games/index.php` replaces the free-text location field with a `<select>` populated from active `locations` records (`active_status = 'Active'`).
2. The dropdown includes a `(Not Listed)` option at the end.
3. When `(Not Listed)` is selected, an additional inline form section appears (location name, address, city, state, zip) and the user must fill in at least the location name.
4. On save, if `(Not Listed)` was chosen and details were provided, a new `locations` record is inserted (status `active`) and its `location_id` is used for the schedule.
5. The Edit Game form applies the same dropdown logic (existing value pre-selected; `(Not Listed)` falls back gracefully if the location no longer exists).
6. Both `schedules.location` (text) and `schedules.location_id` (FK) are populated together on save.

**Files to modify:**
- `public/admin/games/index.php` — replace location text input with dropdown + inline "Not Listed" form
- `includes/` — no new service required; DB writes handled inline or via existing pattern

---

### Story 14.3: Schedule Location Google Maps Links

As a user (coach or public visitor),
I want to click on a game location in the schedule and have it open Google Maps with that address,
so that I can easily get turn-by-turn directions to the field.

**Acceptance Criteria:**
1. In `public/schedule.php`, location cells that have a non-empty address (joined from `locations` via `location_id`, or derived from the text `location` field) render as a clickable link: `<a href="https://maps.google.com/?q=..." target="_blank" rel="noopener">`.
2. In `public/coaches/schedule.php`, the same clickable link treatment is applied to the Location column.
3. If no address is available (location text only, no structured address), the link still works using the location name as the query string.
4. Links open in a new tab (`target="_blank" rel="noopener"`).
5. Locations with no location info at all display as plain text (no broken link).

**Files to modify:**
- `public/schedule.php`
- `public/coaches/schedule.php`

---

## Epic 11: Story 11.8 — Allow Invitation-Registered Users to Self-Register a Team

*(Added to Epic 11 — Team Registration Rework)*

### Story 11.8: Allow Invitation-Registered Users to Self-Register a Team

As an invitation-registered coach,
I want to be able to register a team through the self-registration path,
so that coaches onboarded via invitation are not blocked from team registration.

**Status:** done
**Story Key:** 11-8-allow-invitation-users-self-register-team

---

## Epic 12: Auth / Login UX

**Epic Goal:** Unify coach and admin login under a single entry point, automatically routing users to the correct dashboard based on role.

**Stories:**

### Story 12.1: Unified Login Page

As a product owner, I want coaches and admins to use the same login page (`/login.php`) so the app presents a single entry point and routes users based on their role.

**Status:** done
**Story Key:** 12-1-unified-login-page

---

## Epic 13: Admin Tools & Schedule Management

**Epic Goal:** Give admins additional power tools — user impersonation for support, admin-initiated account creation, and a batch of schedule management quality-of-life bug fixes.

**Stories:**

### Story 13.1: Admin User Impersonation

As an admin, I want to impersonate any coach account to see the app exactly as that user sees it, for troubleshooting and verification without credential sharing.

**Status:** done
**Story Key:** 13-1-admin-user-impersonation

### Story 13.2: Admin Create User Account

As an admin, I want to create a user account directly from the admin panel (without requiring the coach to self-register), so I can onboard coaches who skip the self-registration flow.

**Status:** done
**Story Key:** 13-2-admin-create-user-account

### Story 13.3: Schedule Management Bug Fixes

A batch of schedule management quality-of-life fixes: reschedule history display, postponement admin flow, SCR original date/time/location tracking, and notification accuracy improvements.

**Status:** done
**Story Key:** 13-3-schedule-management-bugfixes

---

## Epic 15: Game & Location Data Quality

**Epic Goal:** Improve data quality and admin efficiency for game entry — auto-generate game numbers, enhance location management with deduplication and map preview, and support bulk game import via CSV.

**Stories:**

### Story 15.1: Game Number Auto-Generation

As an admin, I want game numbers auto-generated in `YYYYNNNN` format so every game has a unique, consistent identifier without manual entry.

**Status:** done
**Story Key:** 15-1-game-number-auto-generation

### Story 15.2: Enhanced Location Management

Admin location management improvements: deduplication detection, location status management, and a cleaner admin locations page.

**Status:** done
**Story Key:** 15-2-enhanced-location-management

### Story 15.3: Bulk Game Import (CSV)

As an admin, I want to import multiple games at once via a CSV upload so I can populate a full season schedule without entering games one by one.

**Status:** done
**Story Key:** 15-3-bulk-game-import

---

## Epic 16: Twilio SMS Notifications

**Epic Goal:** Add optional SMS text message notifications for reschedule events so coaches receive timely alerts on their phones.

**Stories:**

### Story 16.1: Twilio SMS Notifications for Reschedule Events (Coach Opt-In)

As a coach, I want to opt in to SMS notifications for reschedule events so I receive timely alerts on my phone without checking email.

**Status:** ready-for-dev
**Story Key:** 16-1-twilio-sms-reschedule-notifications

---

## Epic 17: Mobile Responsiveness & UX Polish

**Epic Goal:** Make the entire league manager web app fully usable on mobile phones — coaches, admins, and public visitors can use every feature without horizontal scrolling or broken layouts.

**Stories:**

### Story 17.1: Mobile Responsive UI

As a coach or league participant, I want the app to be fully usable on a mobile phone so I can check schedules, submit scores, and manage my team from any device.

**Status:** done
**Story Key:** 17-1-mobile-responsive-ui

---

## Epic 18: Coach-Initiated Game Postponement

**Epic Goal:** Give coaches a self-service way to flag that a game isn't happening as scheduled without needing a proposed reschedule date. Includes email/SMS notifications on postponement and cancellation events.

**Stories:**

### Story 18.1: Coach-Initiated Game Postponement

As a coach, I want to postpone a game (no reschedule date yet) so I can communicate a cancellation without knowing when it will be rescheduled.

**Status:** review
**Story Key:** 18-1-coach-game-postponement

### Story 18.2: Postponement & Cancellation Notifications

As a coach or admin, I want email (and SMS) notifications sent when a game is postponed or cancelled so all parties are informed automatically.

**Status:** review
**Story Key:** 18-2-postponement-cancellation-notifications

---

## Epic 19: Communications Center

**Epic Goal:** Consolidate all email/SMS communication management into a single admin Communications Center — send logs, notification groups, and a compose-and-send interface for admin-initiated messages.

**Stories:**

### Story 19.1: Communications Center

As an admin, I want a centralized Communications page showing all sent email logs, message history, and communication settings in one place.

**Status:** ready-for-dev
**Story Key:** 19-1-communications-center

### Story 19.2: Notification Groups

As an admin, I want to define named notification groups (e.g., "All Team Owners", "Division A Coaches") so I can target communications to a subset of users without building a new recipient list each time.

**Status:** ready-for-dev
**Story Key:** 19-2-notification-groups

### Story 19.3: Admin Compose & Send Email

As an admin, I want to compose and send an email from the Communications Center to a notification group or all coaches, so I can send announcements and important updates directly from the app.

**Status:** ready-for-dev
**Story Key:** 19-3-admin-compose-send

---

## Epic 20: Schedule Conflict Detection

**Epic Goal:** Automatically detect and surface scheduling conflicts — team double-bookings and location overlaps — so admins and coaches catch problems before they cause issues on game day.

**Stories:**

### Story 20.1: Conflict Detection Service & Admin Game Badges

As an admin, I want the Games Management page to flag potential scheduling conflicts (team double-bookings, location overlaps) directly on each game row so I can spot them at a glance.

**Status:** ready-for-dev
**Story Key:** 20-1-game-scr-conflict-detection

### Story 20.2: SCR Conflict Warnings on Coach Schedule-Change Page

As a coach, I want the schedule change request form to warn me when my proposed date/time/location conflicts with an existing game, so I can flag it for the admin rather than submitting an obviously-rejected request.

**Status:** ready-for-dev
**Depends on:** Story 20.1
**Story Key:** 20-2-scr-conflict-warnings

---

## Epic 21: Public Schedule UX

**Epic Goal:** Redesign the public schedule page with tabs, a calendar view, special date marking, and reschedule blackout date enforcement — making it easier for coaches and fans to find the games they care about.

**Stories:**

### Story 21.1: Public Schedule UX Enhancements (4-Tab Layout)

As a league participant, I want the public schedule page to organize games into four tabs (Upcoming, Completed, Awaiting Results, Postponed) so I can immediately find what I'm looking for.

**Status:** done
**Story Key:** 21-1-public-schedule-enhancements

### Story 21.2: Calendar View for Schedule Page

As a league participant, I want to view the game schedule in a monthly calendar layout so I can see which days have games and plan around them.

**Status:** ready-for-dev
**Story Key:** 21-2-calendar-view

### Story 21.3: Special Date Marking on Calendar

As an admin, I want to mark special dates (e.g., holidays, league events) on the public calendar so participants are aware of non-game days that affect scheduling.

**Status:** ready-for-dev
**Story Key:** 21-3-special-date-marking

### Story 21.4: Reschedule Blackout Dates

As an admin, I want to configure blackout date ranges during which coaches cannot submit reschedule requests, so the league can enforce scheduling windows around holidays and season boundaries.

**Status:** ready-for-dev
**Story Key:** 21-4-reschedule-blackout-dates
