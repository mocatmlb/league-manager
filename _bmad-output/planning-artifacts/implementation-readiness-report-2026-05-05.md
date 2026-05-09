---
stepsCompleted:
  - step-01-document-discovery
  - step-02-prd-analysis
  - step-03-epic-coverage-validation
  - step-04-ux-alignment
  - step-05-epic-quality-review
  - step-06-final-assessment
documentsUsed:
  prd: prd.md
  architecture: architecture.md
  epics: epics.md
  ux: ux-design-specification.md
---

# Implementation Readiness Assessment Report

**Date:** 2026-05-05
**Project:** league-manager (District 8 Travel League — Individual Coach Logins)
**Assessed by:** BMad Check Implementation Readiness Workflow

---

## Document Inventory

| Document | File | Size | Last Modified |
|---|---|---|---|
| PRD | `prd.md` | 39K | May 4 22:43 |
| Architecture | `architecture.md` | 50K | May 3 12:57 |
| Epics & Stories | `epics.md` | 95K | May 4 22:45 |
| UX Design | `ux-design-specification.md` | 54K | May 4 16:48 |

No duplicates. No sharded documents. All four core artifacts present.

---

## PRD Analysis

### Functional Requirements Extracted

| ID | Requirement Summary |
|---|---|
| FR-AUTH-1 | Login with username or email + password |
| FR-AUTH-2 | Sessions expire after 60 min inactivity |
| FR-AUTH-3 | Password reset via 24-hour email link |
| FR-AUTH-4 | Account locked 15 min after 5 failed attempts |
| FR-AUTH-5 | "Remember me" — 30-day persistent cookie |
| FR-AUTH-6 | Logout invalidates session immediately |
| FR-AUTH-7 | CAPTCHA after 3 consecutive failed login attempts from same IP |
| FR-REG-1 | Open registration: any user can register when enabled |
| FR-REG-2 | Closed registration: only invitation tokens produce the form |
| FR-REG-3 | Registration form fields: name, preferred name, email, phones with types, league, username, password |
| FR-REG-4 | Unique usernames required |
| FR-REG-5 | Password complexity: ≥8 chars, uppercase, number, special char |
| FR-REG-6 | Verification email on submit; `unverified` until link clicked |
| FR-REG-7 | Verification links expire 48hrs; expired link shows resend option |
| FR-REG-8 | Verified accounts get `user` role; Team Owner only via admin |
| FR-REG-9 | Admin notified when new account verifies |
| FR-REG-10 | CAPTCHA on registration form; failed submissions rejected |
| FR-REG-11 | League dropdown with "Other" option revealing free-text field |
| FR-REG-12 | League field required |
| FR-LEAGUELIST-1 | Admin can create, edit, deactivate league entries |
| FR-LEAGUELIST-2 | Each entry is a short display name used in team name generation |
| FR-LEAGUELIST-3 | Admin can reorder league list entries |
| FR-LEAGUELIST-4 | Deactivated entries hidden from dropdown but retained historically |
| FR-LEAGUELIST-5 | "Other" always appears as last option regardless of admin config |
| FR-INV-1 | Admin can send invitation to any email via admin panel |
| FR-INV-2 | Unique, single-use URL with 14-day expiry |
| FR-INV-3 | Second invite to same email cancels prior token |
| FR-INV-4 | Admin can view all invitations with status; resend or cancel |
| FR-INV-5 | Completed tokens cannot be reused |
| FR-TOGGLE-1 | Admin can enable/disable open self-registration |
| FR-TOGGLE-2 | When enabled: admin sees registration URL + QR code |
| FR-TOGGLE-3 | Toggle effective within one page load |
| FR-TOGGLE-4 | Disabling registration does not affect active invitation links |
| FR-TEAMREG-1 | After email verification, coach sees open programs/seasons |
| FR-TEAMREG-2 | Coach selects program/season; division not selectable |
| FR-TEAMREG-3 | Team name auto-generated as `{league_name}-{coach_last_name}`; read-only |
| FR-TEAMREG-4 | If "Other" selected, manually entered league value used in name |
| FR-TEAMREG-5 | Coach can add up to 5 home field locations |
| FR-TEAMREG-6 | Home field entry requires location name; address optional |
| FR-TEAMREG-7 | Submitted team registrations in `pending` status; appear in admin queue |
| FR-TEAMREG-8 | Admin notified of new team registration |
| FR-TEAMREG-9 | Admin approval assigns submitting coach as Team Owner |
| FR-TEAMREG-10 | Coach notified when team registration approved |
| FR-TEAMREG-11 | Invitation-registered coaches cannot use self-registration team path |
| FR-TEAMREG-12 | Only admin can edit team name after auto-generation |
| FR-ASSIGN-1 | Admin can assign one or more teams to user from User Detail page |
| FR-ASSIGN-2 | First team assignment elevates role to `team_owner` |
| FR-ASSIGN-3 | ~~Multi-team assignment~~ — **Deferred** (1:1 enforced this iteration) |
| FR-ASSIGN-4 | ~~Multiple owners per team~~ — **Deferred** (1:1 enforced this iteration) |
| FR-ASSIGN-5 | Admin can remove team assignment; role reverts to `user` if no teams remain |
| FR-ASSIGN-6 | Coach notified on team assign/remove |
| FR-ASSIGN-7 | Admin can view all assignments for user and all users for team |
| FR-SCORE-1 | Team Owner can submit scores for home games |
| FR-SCORE-2 | Team Owner can submit scores for away games |
| FR-SCORE-3 | Score interface shows only past/elapsed games for coach's team |
| FR-SCORE-4 | Editing existing score requires explicit "edit score" action |
| FR-SCORE-5 | Submitted scores update standings immediately |
| FR-SCORE-6 | Admin notified on score submission |
| FR-SCORE-7 | Score submission sets game status to `completed` |
| FR-RESCHED-1 | Team Owner can submit reschedule requests for home/away games |
| FR-RESCHED-2 | Reschedule interface shows only coach's team games |
| FR-RESCHED-3 | Reschedule request requires proposed date/time and reason |
| FR-RESCHED-4 | Submitted requests appear in admin dashboard as pending |
| FR-RESCHED-5 | Coach can view status of their reschedule requests |
| FR-RESCHED-6 | Admin notified on reschedule request |
| FR-RESCHED-7 | Coach can cancel pending reschedule requests only |
| FR-RESOURCES-1 | Authenticated users can access rules/regulations documents |
| FR-RESOURCES-2 | Rules section shows documents from admin Document Management |
| FR-RESOURCES-3 | Authenticated users can access league contact directory |
| FR-RESOURCES-4 | Unauthenticated requests redirect to login |
| FR-USERMGMT-1 | Admin can view filterable, searchable, paginated user list |
| FR-USERMGMT-2 | Admin can edit user profile fields |
| FR-USERMGMT-3 | Admin can change user role |
| FR-USERMGMT-4 | Admin can disable/re-enable accounts |
| FR-USERMGMT-5 | Admin can reset user password (temp password, force change) |
| FR-USERMGMT-6 | Admin can delete user account with confirmation |
| FR-USERMGMT-7 | Admin can view pre-cutover checklist (teams with zero Team Owners) |
| FR-USERMGMT-8 | Admin can disable legacy shared coach credential |
| FR-USERMGMT-9 | Disable action requires confirmation and zero-gap checklist |
| FR-PROFILE-1 | Authenticated users can update first, last, and preferred name |
| FR-PROFILE-2 | Users can add/update primary phone with type |
| FR-PROFILE-3 | Users can add/update secondary phone with type |
| FR-PROFILE-4 | Users can remove secondary phone; primary cannot be removed |
| FR-PROFILE-5 | Users can change own password while logged in |
| FR-PROFILE-6 | New passwords must meet FR-REG-5 complexity |
| FR-PROFILE-7 | Team name is read-only for coaches; admin-only edit |
| FR-COACHSCHEDULE-1 | Team Owner can view team-filtered schedule |
| FR-COACHSCHEDULE-2 | Schedule columns: Game Number, Date, Time, Away Team, Home Team, Location, Score |
| FR-COACHSCHEDULE-3 | All columns independently sortable |
| FR-COACHSCHEDULE-4 | All columns independently filterable; Date supports date range |
| FR-COACHSCHEDULE-5 | Shows all games regardless of status |
| FR-COACHSCHEDULE-6 | Matches master public schedule column structure |
| FR-RESTRICTIONS-1 | Team Owners cannot change season status |
| FR-RESTRICTIONS-2 | Team Owners cannot change division/program for any team |
| FR-RESTRICTIONS-3 | Team Owners cannot change game status directly (exception: score submission sets `completed`) |
| FR-RESTRICTIONS-4 | Team Owners cannot submit score for game not involving their team (server-side rejected) |
| FR-RESTRICTIONS-5 | Team Owners cannot submit score for future games |
| FR-RESTRICTIONS-6 | Team Owners cannot access other coaches' profile/account info |
| FR-RESTRICTIONS-7 | Team Owners cannot perform any admin-only function |

**Total FRs: 87** (across 15 groups; FR-ASSIGN-3/4 deferred)

### Non-Functional Requirements Extracted

| ID | Requirement Summary |
|---|---|
| NFR-SEC-1 | Passwords stored as one-way hash; plaintext non-recoverable |
| NFR-SEC-2 | CSRF protection on all state-changing operations |
| NFR-SEC-3 | All inputs parameterized; SQL injection blocked |
| NFR-SEC-4 | Session tokens protected from client-side scripts and unencrypted transmission |
| NFR-SEC-5 | Session token rotates on login, logout, and privilege change |
| NFR-SEC-6 | Registration URL not discoverable via public nav or search indexing |
| NFR-PERF-1 | Login page < 2s at 95th percentile |
| NFR-PERF-2 | Score submission < 3s at 95th percentile |
| NFR-PERF-3 | Coach dashboard (up to 3 teams) < 3s |
| NFR-PERF-4 | Self-registration form submit < 3s; verification email < 5 min |
| NFR-COMPAT-1 | All forms functional on mobile viewports ≥375px |
| NFR-COMPAT-2 | Renders correctly in Chrome, Firefox, Safari (current) |
| NFR-COMPAT-3 | Runs on PHP 8.1 (`ea-php81`) without additional server config |
| NFR-ACCESS-1 | WCAG 2.1 Level AA on all auth, registration, and data-entry pages |
| NFR-AVAIL-1 | During transition, both auth systems fully operational; failure in one does not degrade the other |

**Total NFRs: 15**

### Additional Architecture Requirements (AR-1 through AR-14)

All 14 architectural requirements are explicitly documented in `epics.md` Additional Requirements section and traced to epics.

---

## Epic Coverage Validation

### Coverage Matrix

| FR Group | PRD FRs | Epic Coverage | Status |
|---|---|---|---|
| FR-AUTH-1–7 | 7 FRs | Epic 3 (Stories 3.4, 3.5) | ✅ Covered |
| FR-REG-1–12 | 12 FRs | Epic 3 (Stories 3.1, 3.2) | ✅ Covered |
| FR-LEAGUELIST-1–5 | 5 FRs | Epic 2 (Stories 2.1, 2.2) | ✅ Covered |
| FR-INV-1–5 | 5 FRs | Epic 3 (Story 3.3) | ✅ Covered |
| FR-TOGGLE-1–4 | 4 FRs | Epic 3 (Story 3.6) | ✅ Covered |
| FR-TEAMREG-1–12 | 12 FRs | Epic 4 (Stories 4.1, 4.2, 4.3) | ✅ Covered |
| FR-ASSIGN-1–2, 5–7 | 5 FRs | Epic 4 (Story 4.3) | ✅ Covered |
| FR-ASSIGN-3–4 | 2 FRs | **Deferred** (documented in PRD + epics) | ✅ Acknowledged |
| FR-SCORE-1–7 | 7 FRs | Epic 5 (Stories 5.1, 5.2) | ✅ Covered |
| FR-RESCHED-1–7 | 7 FRs | Epic 6 (Stories 6.1, 6.2) | ✅ Covered |
| FR-RESOURCES-1–4 | 4 FRs | Epic 7 (Story 7.4) | ✅ Covered |
| FR-USERMGMT-1–6 | 6 FRs | Epic 8 (Stories 8.1, 8.2, 8.3) | ✅ Covered |
| FR-USERMGMT-7–9 | 3 FRs | Epic 9 (Stories 9.1, 9.2) | ✅ Covered |
| FR-PROFILE-1–7 | 7 FRs | Epic 7 (Stories 7.1, 7.2) | ✅ Covered |
| FR-COACHSCHEDULE-1–6 | 6 FRs | Epic 7 (Story 7.3) | ✅ Covered |
| FR-RESTRICTIONS-1–2, 7 | 3 FRs | Epic 9 (Story 9.1 note) | ✅ Covered |
| FR-RESTRICTIONS-3–5 | 3 FRs | Epic 5 (Story 5.1) | ✅ Covered |
| FR-RESTRICTIONS-6 | 1 FR | Epic 7 | ✅ Covered |
| NFR-SEC-1–6 | 6 NFRs | Epics 1, 3 | ✅ Covered |
| NFR-PERF-1–4 | 4 NFRs | All epics (per-story) | ✅ Covered |
| NFR-COMPAT-1–3 | 3 NFRs | All epics (per-story) | ✅ Covered |
| NFR-ACCESS-1 | 1 NFR | Epics 3–7 | ✅ Covered |
| NFR-AVAIL-1 | 1 NFR | Epic 1 | ✅ Covered |
| AR-1–14 | 14 ARs | Epics 1, 3, 5, 6, 7 | ✅ Covered |
| UX-DR1–20 | 20 UX-DRs | Epics 3–9 | ✅ Covered |

### Coverage Statistics

- **Total PRD FRs:** 87 (85 active + 2 deferred)
- **FRs covered in epics:** 85 active — 100%
- **FRs deferred:** 2 (FR-ASSIGN-3/4) — explicitly documented as out-of-scope in both PRD and epics
- **NFRs covered:** 15/15 — 100%
- **ARs covered:** 14/14 — 100%
- **UX-DRs covered:** 20/20 — 100%

### Missing FR Coverage: **NONE**

All requirements are traceable to at least one epic and story. The two deferred requirements (FR-ASSIGN-3/4) are explicitly called out in the PRD out-of-scope section, the epics FR coverage map, and story 4.3.

### One Discrepancy Noted

**⚠️ MINOR — FR-ASSIGN-3/4 Text in Epics Requirements Inventory**

The epics document `Requirements Inventory` section includes FR-ASSIGN-3 ("Admins can assign multiple teams to the same user") and FR-ASSIGN-4 ("Multiple Team Owners can be assigned to the same team") **without the deferred/strikethrough language** that appears in the PRD and in the FR Coverage Map table. A developer reading only the requirements inventory table might implement these as active requirements. The coverage map table correctly marks them as Deferred. This is a clarity issue, not a coverage gap — but it is a defect worth noting.

---

## UX Alignment Assessment

### UX Document Status

**Found** — `ux-design-specification.md` (54K, comprehensive)

### UX ↔ PRD Alignment

All 11 User Journeys in the PRD (UJ-1 through UJ-11) are addressed in the UX spec. Four are given full Mermaid flow diagrams (UJ-1, UJ-4, UJ-5, UJ-8). UJ-2/UJ-3 are combined into a single flow. UJ-6, UJ-7, UJ-9, UJ-10, UJ-11 are covered implicitly through component strategy, navigation patterns, and UX-DRs in the epics.

All 20 UX Design Requirements (UX-DR1–DR20) traced from the epics document are grounded in the UX spec.

**Alignment Issues:**

| # | Issue | Severity |
|---|---|---|
| UX-A1 | **UJ-6, UJ-9, UJ-10, UJ-11 have no detailed flow diagrams in UX spec.** UJ-6 (rules/contacts access), UJ-9 (profile management), UJ-10 (team schedule view), and UJ-11 (admin league dropdown management) are described in the PRD but receive no dedicated UX flow or screen-level detail in the UX spec. The components that serve them are documented (UX-DR12, UX-DR13), but the end-to-end journey is not diagrammed. This is a low implementation risk for experienced developers but a potential ambiguity for dev agents. | 🟡 Minor |
| UX-A2 | **UX spec Component Implementation Strategy is slightly inconsistent with epics.** The UX spec states "JavaScript behavior uses vanilla JS inline `<script>` blocks per page" (Component Implementation Strategy, Phase 1). However, the epics and architecture specify dedicated JS files (`coaches-registration.js`, `coaches-schedule.js`, `admin-league-list.js`) — no inline scripts. The epics/architecture are the later, more refined documents and the correct source of truth. The UX spec language is a legacy statement from earlier in its workflow. | 🟡 Minor |
| UX-A3 | **UX-DR8 contact pre-population scope is broader in epics than in UX spec.** UX-DR8 specifies pre-population of "coach name, primary phone, and email." Story 6.2 adds "new location" and "reason" field requirements to the same form. These are not pre-populated but are new fields the UX spec flow does not show. Not a conflict — the fields are additive — but a dev agent implementing strictly from the UX spec flow diagram might miss the full field set. The PRD FR-RESCHED-3 is clear; the story AC is clear. | 🟡 Minor |

### UX ↔ Architecture Alignment

The UX spec is well-aligned with the architecture. Bootstrap 5 components, CDN delivery, jQuery, PHP server-side rendering, `style.css` for overrides — all consistent. No UX requirement implies a technology the architecture does not support.

**One gap worth noting:**

| # | Issue | Severity |
|---|---|---|
| UX-A4 | **Admin sidebar for Migration/Settings section** — UX spec specifies "Sidebar navigation added to admin layout for the Settings/Migration section" at `lg` breakpoint. The architecture file structure shows `public/admin/settings/sections/users-coach.php` and `public/admin/settings/index.php` but does not include a sidebar layout file or specify where the sidebar markup lives. Story 9.2 says "within `admin/settings/` section" but does not define a sidebar layout component or template. A dev agent could interpret this as adding a nav list inside the existing settings page rather than a true sidebar. | 🟠 Major |

---

## Epic Quality Review

### Epic Structure Validation

#### User Value Focus

| Epic | Title | User Value Assessment |
|---|---|---|
| Epic 1 | Foundation — Database, Migrations & Cross-Cutting Utilities | ⚠️ **Technical epic** — no direct user value. See detailed note below. |
| Epic 2 | Admin League List Management | ✅ Admin value: manage the league dropdown |
| Epic 3 | Coach Registration & Authentication | ✅ Coach value: register and log in |
| Epic 4 | Team Registration & Coach Assignment | ✅ Coach + admin value: team identity established |
| Epic 5 | Team-Scoped Score Submission | ✅ Coach value: submit scores |
| Epic 6 | Team-Scoped Reschedule Requests | ✅ Coach value: request reschedules |
| Epic 7 | Coach Profile, Team Schedule & Authenticated Resources | ✅ Coach value: manage account + view schedule |
| Epic 8 | Admin User Management | ✅ Admin value: manage all user accounts |
| Epic 9 | Migration Cutover & Shared Credential Deprecation | ✅ Admin value: complete the migration safely |

**Epic 1 — Technical Epic Note:**

Epic 1 ("Foundation — Database, Migrations & Cross-Cutting Utilities") is by definition a technical enablement epic with no direct user-facing value. Per create-epics-and-stories standards, this is a recognized pattern deviation. However, for a brownfield PHP project of this complexity, it is the **correct and practical approach** because:
1. All 7 schema migrations must be applied before any feature code can run.
2. The 4 cross-cutting utility classes (`PermissionGuard`, `TeamScope`, `GameTimeGate`, `ActivityLogger`) are dependencies for every subsequent story across all other epics.
3. `LegacyAuthManager` removal in the same PR prevents dual-auth test complexity.

**Finding:** Epic 1 is structurally sound and pragmatically justified for a brownfield project. The deviation from "user value per epic" is acknowledged and accepted. **No remediation required.**

#### Epic Independence Validation

| Check | Result |
|---|---|
| Epic 1 stands alone | ✅ Yes — only requires existing codebase |
| Epic 2 requires only Epic 1 | ✅ Yes — `LeagueListManager` uses the `league_list` table created in migration 001 (Epic 1) |
| Epic 3 requires Epic 1 + dependency on Epic 2 | ⚠️ **Partially** — Story 3.2 explicitly requires Epic 2 complete (documented in sprint sequencing gate note). Stories 3.1, 3.3, 3.4, 3.5, 3.6 have no Epic 2 dependency. This is correctly documented. |
| Epic 4 requires Epic 3 | ✅ Yes — requires coach accounts to exist |
| Epic 5 requires Epic 4 | ✅ Yes — requires Team Owner role and team assignments |
| Epic 6 requires Epic 4 | ✅ Yes — requires Team Owner role |
| Epic 7 requires Epic 3 (auth) | ✅ Yes — requires authenticated sessions |
| Epic 8 requires Epic 1 (DB schema) | ✅ Yes |
| Epic 9 requires Epic 4 (team assignments exist to check) | ✅ Yes |
| Circular dependencies | ✅ None found |

### Story Quality Assessment

#### Acceptance Criteria Structure

All stories use Given/When/Then (BDD) format throughout. All acceptance criteria are:
- **Testable** — each specifies observable outcomes
- **Specific** — references exact class names, method signatures, page paths, and status values
- **Error coverage** — all stories include error/boundary cases (duplicate usernames, expired tokens, locked accounts, team scope violations, etc.)

No vague criteria ("user can login") found. All ACs name specific classes, methods, HTTP status codes, or UI elements.

#### Story Sizing

All stories in the epics are appropriately sized. Each represents a coherent, deliverable unit of work. No stories are epic-sized (i.e., none would take weeks of work to complete in isolation).

**One story worth flagging:**

| # | Issue | Severity |
|---|---|---|
| EQ-1 | **Story 4.3 has 3 distinct work areas** explicitly called out ("Area A — Pending Team Registration Queue", "Area B — Team Assignment UI", "Area C — UserManagementService Bootstrap"). This is large for a single story. A dev agent may implement only one area and mark the story done. The three-area delineation is good documentation but the story could optionally be split into 4.3a, 4.3b, 4.3c. That said, the three areas share a tightly coupled domain (team assignment) and the story is internally well-scoped. | 🟡 Minor |
| EQ-2 | **Story 8.3 Admin User Detail Page has a consolidation note** ("consolidates with team assignment UI from Epic 4 Story 4.3"). This creates an implicit forward dependency where Story 4.3 creates `detail.php` and Story 8.3 expands it. A dev agent implementing 8.3 without awareness of 4.3's output could create a duplicate file. The note is present but its implementation implication is important: Story 8.3 **modifies** the file created in Story 4.3, not creates it fresh. | 🟠 Major |

#### Dependency Analysis

**Within-Epic Dependencies (verified):**

| Epic | Dependency Chain | Compliant? |
|---|---|---|
| Epic 1 | 1.1 → 1.2 → 1.3 (migrations before removal; removal before utilities use migrations) | ✅ |
| Epic 2 | 2.1 (service) → 2.2 (UI uses service) | ✅ |
| Epic 3 | 3.1 (service) → 3.2 (page uses service); 3.3, 3.4, 3.5, 3.6 independent | ✅ |
| Epic 4 | 4.1 (service) → 4.2 (page uses service) → 4.3 (admin UI uses both); 4.4 (dashboard requires team assignment from 4.3) | ✅ |
| Epic 5 | 5.1 (service) → 5.2 (page uses service) | ✅ |
| Epic 6 | 6.1 (service) → 6.2 (page uses service) | ✅ |
| Epic 7 | 7.1 (service) → 7.2 (page); 7.3, 7.4 independent | ✅ |
| Epic 8 | 8.1 (service expansion) → 8.2 (list page uses service) → 8.3 (detail page uses service) | ✅ |
| Epic 9 | 9.1 (service) → 9.2 (page uses service) | ✅ |

**Database/Entity Creation Timing:**

All tables are created in Epic 1 via the migration files (migrations 000–006). This is a deliberate architectural decision documented in AR-2 — "7 schema migrations must be applied before any feature code ships." This is appropriate for this brownfield project since many tables already exist and the new tables are all prerequisites for multiple epics. This is the correct pattern for this codebase.

**Activity Log Table Gap — Notable:**

| # | Issue | Severity |
|---|---|---|
| EQ-3 | **Migration 007 for `activity_log` table is referenced in Epic 1 header note but not in Story 1.1.** Story 1.1 lists 7 migration files (000–006). However, Epic 1's overview says "8 migrations total (000–007); migration 007 creates `activity_log` table required by AR-7 (ActivityLogger)." Story 1.1's acceptance criteria only validates migrations 000–006. Migration 007 (`007_create_activity_log.sql`) is **not listed in Story 1.1's "Files to create"** section and is not included in the AC's `schema_migrations` version check (which checks for versions 000–006 only). If a dev agent implements Story 1.1 strictly from its AC, they will not create migration 007, and Story 1.3 (`ActivityLogger`) will fail because the `activity_log` table won't exist. | 🔴 **Critical** |

**Forward References Check:**

| Check | Result |
|---|---|
| Stories referencing features not yet built | ✅ None found — all references are backward |
| Stories assuming future stories will exist | Story 8.3 references Story 4.3's output (see EQ-2 above) | 
| Epic 3 sprint gate on Epic 2 | ✅ Correctly documented |

### Special Checks

**Brownfield Project Indicators:**

All required brownfield considerations are present:
- ✅ Integration points with existing system (`AuthService`, `LegacyAuthManager`, `UserAccountManager`, `EmailService`, existing tables)
- ✅ Migration strategy from shared credential to individual accounts explicitly documented
- ✅ `LegacyAuthManager` removal story (1.2) addresses compatibility
- ✅ `WHERE status = 'active'` audit requirement for existing team queries called out in architecture

**Reschedule Notification Recipient List (Open Thread):**

Architecture Decision 5 explicitly defers the reschedule notification recipient list ("umpires confirmed; coaches and/or opposing team contacts TBD before FR-RESCHED stories are written"). Story 6.1 sends notifications to admin only (operational, logged). This is a known open item. Epic 6 can proceed with admin-only notification as a safe default, but the open thread should be resolved before Sprint 2 if applicable.

---

## Summary and Recommendations

### Overall Readiness Status

## ✅ READY FOR IMPLEMENTATION — WITH ONE CRITICAL FIX REQUIRED

The planning artifacts for District 8 Travel League Individual Coach Logins are exceptionally thorough. The PRD, Architecture, Epics, and UX Design documents are comprehensive, well-aligned, and demonstrate a high level of planning maturity. Requirements traceability is essentially complete (100% of active FRs covered). The architecture is well-suited to the brownfield codebase and hosting constraints.

**One critical defect must be addressed before a dev agent begins Epic 1 Story 1.1.** All other findings are minor or informational.

---

### Issues by Severity

#### 🔴 Critical — Must Fix Before Implementation

**C-1: Migration 007 (`activity_log` table) is missing from Story 1.1**

The Epic 1 overview states there are 8 migrations (000–007), but Story 1.1 only lists 7 (000–006) in its acceptance criteria and "Files to create" list. Migration 007, which creates the `activity_log` table for `ActivityLogger`, is absent from Story 1.1. If a dev agent implements Story 1.1 as written, Story 1.3's `ActivityLogger` will fail because its target table will not exist.

**Fix:** Add `database/migrations/007_create_activity_log.sql` to Story 1.1's Files to create list and add a corresponding AC checking that the `activity_log` table exists and version `007` appears in `schema_migrations` after migration 007 is applied.

---

#### 🟠 Major — Should Fix Before Sprint Start

**M-1: Admin sidebar layout component undefined (Story 9.2)**

The UX spec and Architecture reference a sidebar navigation for the admin migration/settings section, but no PHP layout file (e.g., `includes/admin_sidebar_layout.php` or similar) is defined in the project structure, nor is one listed in any story's "Files to create." Story 9.2 says it creates `public/admin/settings/sections/cutover.php` — but where does the sidebar markup live?

**Fix:** Add a note to Story 9.2 (or Story 2.2 if admin sidebar is introduced earlier) defining where the sidebar layout markup lives and which file(s) are created/modified to support it. Alternatively, clarify in Story 9.2 that the sidebar is a collapsible `nav` added directly to `admin/settings/index.php` rather than a separate layout file.

**M-2: Story 8.3 forward dependency on Story 4.3's `admin/users/detail.php`**

Story 8.3 says it "consolidates with team assignment UI from Epic 4 Story 4.3" and lists `public/admin/users/detail.php` as a "Files to create." Story 4.3 already creates this file. A dev agent implementing 8.3 without cross-referencing 4.3 may create a new file from scratch rather than extending the existing one, resulting in two versions or overwriting 4.3's work.

**Fix:** Change Story 8.3's "Files to create" entry for `detail.php` to "Files to modify" with an explicit note: "Extends the file created in Story 4.3 — do not overwrite." Alternatively, note in Story 4.3 that it creates a stub to be expanded in Story 8.3.

---

#### 🟡 Minor — Consider Before Sprint or During Implementation

**m-1: FR-ASSIGN-3/4 appear in Epics Requirements Inventory without deferred language**

The epics requirements inventory table lists FR-ASSIGN-3 and FR-ASSIGN-4 as active requirements without the strikethrough/deferred annotation used in the PRD and the epics FR Coverage Map. The Coverage Map correctly marks them as deferred, but the inventory table could mislead a developer.

**Fix:** Add the deferred annotation and strikethrough to FR-ASSIGN-3/4 in the epics requirements inventory table, matching the style used in the PRD.

**m-2: Story 4.3 has 3 distinct work areas; risk of incomplete delivery**

Story 4.3 defines Areas A, B, and C. A dev agent could complete one area and consider the story done. The story is appropriately sized but the three-area structure deserves explicit instruction to complete all three.

**Fix:** Add a line to Story 4.3's preamble: "This story is complete only when ALL THREE work areas (A, B, and C) are implemented and all acceptance criteria pass."

**m-3: UX flows missing for UJ-6, UJ-9, UJ-10, UJ-11**

No Mermaid flow diagrams exist for Rules/Contacts access (UJ-6), Profile Management (UJ-9), Team Schedule View (UJ-10), and Admin League Dropdown Management (UJ-11). These are relatively straightforward flows and the relevant stories have sufficient AC detail to implement without diagrams.

**No remediation required** — this is informational. The stories are clear enough.

**m-4: UX spec Component Strategy language inconsistency (inline JS vs. dedicated files)**

UX spec states "vanilla JS inline `<script>` blocks per page" but the architecture and epics specify dedicated JS files. The epics/architecture are the authoritative source; the UX spec language is a minor leftover inconsistency.

**Fix:** Informational only — dev agents should follow the epics/architecture JS file convention, not the UX spec inline script language.

**m-5: Reschedule notification recipient list open thread**

Architecture Decision 5 explicitly defers the full reschedule notification recipient list. Story 6.1 defaults to admin-only notification. This is safe and documented.

**No immediate fix required** — resolve before Epic 6 development if the recipient list has been confirmed since architecture was written.

---

### Recommended Next Steps

1. **[BEFORE Story 1.1] Fix Critical Issue C-1:** Add migration 007 to Story 1.1's acceptance criteria and files list. This is a single targeted edit to `epics.md`.

2. **[BEFORE Epic 4/8] Fix Major Issue M-2:** Clarify Story 8.3 file list (modify vs. create for `detail.php`) to prevent double-creation or overwrite.

3. **[BEFORE Epic 9] Fix Major Issue M-1:** Clarify where the admin sidebar layout markup lives and which story creates it.

4. **[Optional cleanup] Address minor issues m-1 through m-4** in the epics document for clarity — none block implementation.

5. **[Begin Epic 1]** Once C-1 is fixed, development can begin. The epic sequence (1 → 2 → 3 → 4 → 5/6/7 in parallel → 8 → 9) is sound.

---

### Final Note

This assessment identified **6 issues** across **4 categories**:
- 1 critical (migration 007 missing from Story 1.1)
- 2 major (admin sidebar undefined; Story 8.3/4.3 file ambiguity)
- 3 minor (FR-ASSIGN deferred language, Story 4.3 completion instruction, UX spec JS inconsistency)

The planning artifacts are of high quality. The critical issue is a targeted, easily fixed gap in Story 1.1. Once addressed, the project is ready to proceed to implementation with high confidence. The architecture is appropriately detailed, the stories are well-structured with testable acceptance criteria, and the requirements coverage is essentially complete.

**Assessment Date:** 2026-05-05
