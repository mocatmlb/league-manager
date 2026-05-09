---
stepsCompleted: [1, 2, 3, 4, 5, 6]
date: '2026-05-04'
project: league-manager
documentsInventoried:
  prd: _bmad-output/planning-artifacts/prd.md
  architecture: _bmad-output/planning-artifacts/architecture.md
  epics: _bmad-output/planning-artifacts/epics.md
  ux: _bmad-output/planning-artifacts/ux-design-specification.md
---

# Implementation Readiness Assessment Report

**Date:** 2026-05-04
**Project:** league-manager — District 8 Travel League: Individual Coach Logins
**Assessor:** BMad Implementation Readiness Validator

---

## Document Inventory

| Document | Location | Status |
|----------|----------|--------|
| PRD | `_bmad-output/planning-artifacts/prd.md` | ✅ Found |
| Architecture | `_bmad-output/planning-artifacts/architecture.md` | ✅ Found |
| Epics & Stories | `_bmad-output/planning-artifacts/epics.md` | ✅ Found |
| UX Design | `_bmad-output/planning-artifacts/ux-design-specification.md` | ✅ Found |

No duplicate documents. No missing required documents.

---

## PRD Analysis

### Functional Requirements Summary

| Group | IDs | Count |
|-------|-----|-------|
| FR-AUTH | 1–7 | 7 |
| FR-REG | 1–12 | 12 |
| FR-LEAGUELIST | 1–5 | 5 |
| FR-INV | 1–5 | 5 |
| FR-TOGGLE | 1–4 | 4 |
| FR-TEAMREG | 1–12 | 12 |
| FR-ASSIGN | 1–7 | 7 |
| FR-SCORE | 1–7 | 7 |
| FR-RESCHED | 1–7 | 7 |
| FR-RESOURCES | 1–4 | 4 |
| FR-USERMGMT | 1–9 | 9 |
| FR-PROFILE | 1–7 | 7 |
| FR-COACHSCHEDULE | 1–6 | 6 |
| FR-RESTRICTIONS | 1–7 | 7 |
| **TOTAL** | | **99** |

### Non-Functional Requirements Summary

| Group | IDs | Count |
|-------|-----|-------|
| NFR-SEC | 1–6 | 6 |
| NFR-PERF | 1–4 | 4 |
| NFR-COMPAT | 1–3 | 3 |
| NFR-ACCESS | 1 | 1 |
| NFR-AVAIL | 1 | 1 |
| **TOTAL** | | **15** |

### PRD Completeness Assessment

The PRD is thorough and well-structured with 99 functional requirements across 14 groups and 15 NFRs. User journeys (UJ-1 through UJ-11) are comprehensive. The migration strategy through 4 phases is clearly articulated. No obvious gaps in PRD coverage.

---

## Epic Coverage Validation

### Coverage Matrix

| FR Group | PRD Count | Epic Coverage | Status |
|----------|-----------|---------------|--------|
| FR-AUTH-1–7 | 7 | Epic 3 (Stories 3.4, 3.5) | ✅ Covered |
| FR-REG-1–12 | 12 | Epic 3 (Stories 3.1, 3.2, 3.6) | ✅ Covered |
| FR-LEAGUELIST-1–5 | 5 | Epic 2 (Stories 2.1, 2.2) | ✅ Covered |
| FR-INV-1–5 | 5 | Epic 3 (Story 3.3) | ✅ Covered |
| FR-TOGGLE-1–4 | 4 | Epic 3 (Story 3.6) | ✅ Covered |
| FR-TEAMREG-1–12 | 12 | Epic 4 (Stories 4.1, 4.2, 4.3) | ✅ Covered |
| FR-ASSIGN-1–7 | 7 | Epic 4 (Story 4.3) | ⚠️ Partial — see issue #1 |
| FR-SCORE-1–7 | 7 | Epic 5 (Stories 5.1, 5.2) | ✅ Covered |
| FR-RESCHED-1–7 | 7 | Epic 6 (Stories 6.1, 6.2) | ✅ Covered |
| FR-RESOURCES-1–4 | 4 | Epic 7 (Story 7.4) | ✅ Covered |
| FR-USERMGMT-1–6 | 6 | Epic 8 (Stories 8.1, 8.2, 8.3) | ✅ Covered |
| FR-USERMGMT-7–9 | 3 | Epic 9 (Stories 9.1, 9.2) | ✅ Covered |
| FR-PROFILE-1–7 | 7 | Epic 7 (Stories 7.1, 7.2) | ✅ Covered |
| FR-COACHSCHEDULE-1–6 | 6 | Epic 7 (Story 7.3) | ✅ Covered |
| FR-RESTRICTIONS-1–2, 7 | 3 | Epic 9 | ⚠️ Partial — see issue #2 |
| FR-RESTRICTIONS-3–5 | 3 | Epic 5 | ✅ Covered |
| FR-RESTRICTIONS-6 | 1 | Epic 7 | ✅ Covered |
| NFR-SEC-1–6 | 6 | Epics 1, 3 | ✅ Covered |
| NFR-PERF-1–4 | 4 | All epics | ✅ Covered |
| NFR-COMPAT-1–3 | 3 | All epics | ✅ Covered |
| NFR-ACCESS-1 | 1 | Epics 3–7 | ✅ Covered |
| NFR-AVAIL-1 | 1 | Epic 1 | ✅ Covered |

### Coverage Statistics

- **Total PRD FRs:** 99
- **FRs with clear epic coverage:** 97
- **FRs with partial/ambiguous coverage:** 2 groups (issues noted below)
- **Coverage percentage:** ~98% — but 2 structural gaps need attention

---

## Epic Coverage Issues

### Issue #1 — FR-ASSIGN-3/4 Multi-Team Coverage Conflict with Architecture

**Severity: 🔴 Critical**

**PRD says:**
- FR-ASSIGN-3: Admins can assign multiple teams to the same user
- FR-ASSIGN-4: Multiple Team Owners can be assigned to the same team

**Architecture says (Decision 3, AR-5):**
> "One user account maps to exactly one team for this iteration. Enforced at both the DB layer (`UNIQUE(user_id)` on `team_owners`) and application layer (`TeamAlreadyClaimedException`)."

**Epic 4 Story 4.3 says:**
> "A `TeamAlreadyClaimedException` is caught and a user-friendly error is shown: 'This coach already has a team assigned. Multiple team assignments are not supported in this version.'"

**The conflict:** The PRD explicitly requires multi-team support (FR-ASSIGN-3/4) but the architecture explicitly prohibits it with a DB-level UNIQUE constraint. The epic stories implement the architecture's 1:1 constraint — not the PRD requirements.

**Impact:** FR-ASSIGN-3 and FR-ASSIGN-4 are **not implemented** by the current epic plan. A coach cannot be assigned to multiple teams, and a team cannot have multiple owners, despite the PRD requiring both.

**Recommendation:** This must be resolved before implementation. Options:
1. Update the PRD to remove FR-ASSIGN-3/4 (defer multi-team to a future phase) — simplest
2. Update the architecture to remove the `UNIQUE(user_id)` constraint and revise AR-5 to allow 1:N
3. Keep the architecture as-is and explicitly mark FR-ASSIGN-3/4 as deferred in the epics

---

### Issue #2 — FR-RESTRICTIONS-1/2/7 Placed in Epic 9, But Epic 9 is the Last Epic

**Severity: 🟠 Major**

**The FR Coverage Map assigns:**
- FR-RESTRICTIONS-1 (season termination restricted) → Epic 9
- FR-RESTRICTIONS-2 (division/program changes restricted) → Epic 9
- FR-RESTRICTIONS-7 (no admin functions) → Epic 9

**The problem:** These permission boundaries must be enforced the moment a Team Owner logs in — starting with Epic 4 (dashboard). If a Team Owner account exists and can log in after Epic 4 is complete, but the server-side permission restrictions for season/division/program management aren't implemented until Epic 9, there is a **security gap for the entire duration of Epics 4–8**.

The `PermissionGuard` is built in Epic 1. But Epic 9 is described as the *cutover* epic — it's framed around the migration panel, not as the "add permission checks" epic.

**Recommendation:** Clarify in the epics that FR-RESTRICTIONS-1/2/7 are enforced by the fact that Team Owners have no UI routes to admin functions (which is true from Epic 4 onward) and that the server-side PermissionGuard at the top of each coach page prevents escalation. If this is already guaranteed by architecture, add an explicit note in Epic 9's story 9.1 ACs to confirm the enforcement chain — currently it's implicit.

---

## Schema Conflicts Between Existing Codebase and Architecture

**Severity: 🔴 Critical — These will cause implementation failures if not resolved in Story 1.1**

These conflicts were identified by examining `database/user_accounts_schema.sql` (existing, possibly applied) against `architecture.md` (target spec):

### Conflict A — `login_attempts` Table Column Name Mismatch

| | Existing `user_accounts_schema.sql` | Architecture Spec |
|--|-------------------------------------|-------------------|
| Column | `username VARCHAR(100)` | `identifier VARCHAR(255)` |
| Extra column | `success BOOLEAN` | Not present |
| Index names | `idx_username`, `idx_ip_address` (separate) | `idx_login_attempts_ip_time (ip_address, attempted_at)` composite |

**Impact:** If `user_accounts_schema.sql` is already applied to the shared hosting DB, migration 002 (`CREATE TABLE IF NOT EXISTS`) will silently no-op, leaving the old schema. Story 3.4 code that reads/writes `identifier` will fail with column-not-found errors.

**Resolution needed in Story 1.1:** Migration 002 must detect and ALTER the existing table if it already exists with the old column names.

### Conflict B — `remember_tokens` Table Column Design Mismatch

| | Existing `user_accounts_schema.sql` | Architecture Spec |
|--|-------------------------------------|-------------------|
| Token column | `token VARCHAR(100)` (plain token stored) | `token_hash VARCHAR(64)` (SHA-256 hash) |
| Security | Token visible in DB if compromised | Hash only — token never stored plaintext |

**Impact:** If the existing `remember_tokens` table is in place, migration 005 no-ops. Story 3.4 code that stores `token_hash` will fail. More critically, if someone wrote interim code against the old schema, it uses plain token storage — a security regression.

**Resolution needed in Story 1.1:** Migration 005 must add `token_hash VARCHAR(64)` via `ALTER TABLE IF NOT EXISTS` with `ADD COLUMN IF NOT EXISTS`.

### Conflict C — `teams` Table PK Column Name

| | `schema.sql` | Architecture Patterns |
|--|---|---|
| PK column | `team_id` | Architecture examples reference `id` |

**Impact:** Any migration or service class that writes `WHERE id = ?` against the `teams` table will fail. Story 4.1 `TeamRegistrationService` must use `team_id` not `id`.

**Resolution:** Architecture patterns doc says `teams.id` in some pseudo-code — the actual column is `team_id`. Dev agents on Epics 4–9 stories must be explicitly warned. **Story 1.1 migration 003 must use `ALTER TABLE teams ADD COLUMN status` — this is safe. But all subsequent stories touching `teams` need the `team_id` reminder.**

### Conflict D — `locations` Table PK Column Name

Same pattern as above: `schema.sql` uses `location_id`, not `id`.

---

## UX Alignment Assessment

### UX Document Status

✅ Found — `ux-design-specification.md` (fully complete, 13 steps completed)

### UX ↔ PRD Alignment

| UX-DR | PRD Traceability | Status |
|--------|-----------------|--------|
| UX-DR1 (Coach Identity Hero) | UJ-1 step 10, UJ-3 step 7 | ✅ Aligned |
| UX-DR2 (VS Score Entry) | FR-SCORE-1/2, UJ-4 | ✅ Aligned |
| UX-DR3 (Action Card Grid) | UJ-1/3 dashboard, FR-SCORE/RESCHED | ✅ Aligned |
| UX-DR4 (Registration Progress Indicator) | UJ-1 steps 1–5 | ✅ Aligned |
| UX-DR5 (Game Detail Reveal Panel) | UJ-5, FR-RESCHED-3 | ✅ Aligned |
| UX-DR6 (Admin Gap Checklist Rows) | FR-USERMGMT-7, UJ-8 | ✅ Aligned |
| UX-DR7 (Score auto-selection) | FR-SCORE-3, UJ-4 step 2 | ✅ Aligned |
| UX-DR8 (Schedule change pre-population) | UJ-5 context, FR-RESCHED-3 | ✅ Aligned |
| UX-DR9 ("Other" league reveal) | FR-REG-11, UJ-1 step 2 | ✅ Aligned |
| UX-DR10 (Home field repeater) | FR-TEAMREG-5/6, UJ-1 step 8 | ✅ Aligned |
| UX-DR11 (Cutover modal) | FR-USERMGMT-9, UJ-8 steps 3–5 | ✅ Aligned |
| UX-DR12 (Schedule sort/filter) | FR-COACHSCHEDULE-3/4, UJ-10 | ✅ Aligned |
| UX-DR13 (League list drag-reorder) | FR-LEAGUELIST-3, UJ-11 | ✅ Aligned |
| UX-DR14 (Login CAPTCHA reveal) | FR-AUTH-7 | ✅ Aligned |
| UX-DR15 (Status CSS classes) | FR-TEAMREG-7, FR-USERMGMT-4 | ✅ Aligned |
| UX-DR16 (Empty state pattern) | FR-SCORE, FR-RESCHED, FR-USERMGMT | ✅ Aligned |
| UX-DR17 (Confirmation echo) | FR-SCORE-7, UJ-4 step 6 | ✅ Aligned |
| UX-DR18 (Error preservation) | FR-SCORE, FR-RESCHED | ✅ Aligned |
| UX-DR19 (Form accessibility baseline) | NFR-ACCESS-1 | ✅ Aligned |
| UX-DR20 (Dark coach navbar) | UJ-1/2/3/4/5 portal nav | ✅ Aligned |

### UX ↔ Architecture Alignment

✅ All UX-DRs have corresponding architectural component entries and are mapped to their implementation epics in `epics.md`.

### UX Warning — Preferred Name Not Surfaced in Coach Identity Hero

**Severity: 🟡 Minor**

FR-PROFILE-1 and UJ-9 both reference "preferred name" as an updatable field. UX-DR1 (Coach Identity Hero) shows "coach name" — but doesn't specify whether this uses preferred name when set, or always uses first name. The architecture and epics are silent on this display logic.

**Recommendation:** Clarify in Story 4.4 ACs: "Coach name displayed in `.coach-hero` uses preferred name if set, otherwise first name."

---

## Epic Quality Review

### Epic Structure Validation

#### Epic 1 — Foundation (Database, Migrations & Cross-Cutting Utilities)

**⚠️ Quality Flag: Technical milestone epic, not user-value epic**

This is the most common violation the validator flags — an entire epic delivering zero visible user value (migrations, utility classes). This is **intentional and appropriate for brownfield projects** where shared infrastructure must be laid before any feature can work. The validator flags it for awareness:

- Users cannot benefit from Epic 1 alone
- Epic 2+ all depend on Epic 1 being complete (PermissionGuard, database tables, etc.)
- This is a legitimate brownfield pattern — not a defect — but it is a risk: if Epic 1 slips, all subsequent epics are blocked

**Recommendation:** No structural change needed, but the sprint plan should treat Epic 1 as a hard gate. No Epic 2+ stories should be started until Epic 1 is fully `done`.

#### Epic 2 — Admin League List Management

✅ Delivers user value (admin can manage league dropdown)
✅ Independent — only depends on Epic 1 DB tables (`league_list`)
✅ Properly sized (2 stories — service + UI)

#### Epic 3 — Coach Registration & Authentication

✅ Delivers user value (coaches can create accounts and log in)
✅ Depends on Epic 1 (migrations, PermissionGuard, ActivityLogger) and Epic 2 (league_list for dropdown)
✅ 6 stories — appropriate for the scope

**⚠️ Dependency concern:** Story 3.2 (Registration Page) depends on `LeagueListManager` from Epic 2 Story 2.1. This is a cross-epic dependency. If Epic 2 is not done, Epic 3 Story 3.2 cannot be built.

**Recommendation:** Sprint plan should enforce Epic 2 completion before starting Epic 3 Story 3.2.

#### Epic 4 — Team Registration & Coach Assignment

✅ Delivers user value (coaches get their team identity)
✅ Depends on Epic 3 (user accounts exist) and Epic 1 (PermissionGuard, TeamScope)
✅ 4 stories — appropriate

#### Epics 5–9

All follow the established pattern correctly. Each delivers discrete user value, builds on prior epics, and contains appropriately sized stories.

### Story Quality Assessment

#### Story Sizing Issues

**🟠 Story 3.3 (Invitation Service & Admin Invitation Management) — Potentially oversized**

Story 3.3 creates both the `InvitationService` backend AND the admin invitations management page (`admin/users/invitations.php`). This is two stories worth of work bundled into one. Not a blocker, but the dev agent will need context for both service and UI layers simultaneously.

**🟠 Story 4.3 (Admin Team Assignment & Pending Queue) — Oversized and touches multiple admin pages**

This story touches: `admin/teams/index.php` (pending queue section), `admin/users/detail.php` (team assignment UI), and introduces `UserManagementService` (initial version). This is three distinct areas bundled into one story. High risk of scope creep.

**Recommendation:** No structural change needed for now, but story files for 3.3 and 4.3 should explicitly call out the multiple work areas and provide clear boundaries.

#### Acceptance Criteria Quality

All stories use proper BDD Given/When/Then format. ✅

**🟡 Minor gap — Story 1.3 ActivityLogger AC doesn't specify the `activity_log` table schema**

Story 1.3's AC says: "a row is inserted into `activity_log`" — but neither the epics nor architecture define the `activity_log` table DDL. The existing `user_accounts_schema.sql` has `user_activity_log` (different name). The architecture references `activity_log` but never provides its CREATE TABLE statement.

**Impact:** The dev agent implementing Story 1.3 will need to create the `activity_log` table — but there's no migration for it (migrations 000–006 only). Either a migration 007 is needed, or the table must be created inline in `ActivityLogger.php` or a setup script.

**Recommendation:** Add `database/migrations/007_add_activity_log.sql` to Story 1.3's file list, with the table schema explicitly defined.

#### Dependency Validation

| Story | Forward Dependency Check | Status |
|-------|--------------------------|--------|
| 1.1 Apply Migrations | None — standalone DDL | ✅ |
| 1.2 Remove Legacy Auth | Depends on 1.1 (DB ready) | ✅ |
| 1.3 Cross-Cutting Utilities | Depends on 1.1 (activity_log table) | ⚠️ Missing table DDL |
| 2.1 LeagueListManager | Depends on 1.1 (league_list table) | ✅ |
| 2.2 Admin League List Page | Depends on 2.1 | ✅ |
| 3.1 RegistrationService | Depends on 1.3 (ActivityLogger, PermissionGuard) | ✅ |
| 3.2 Coach Registration Page | Depends on 3.1 + **2.1 (LeagueListManager)** | ⚠️ Cross-epic dep |
| All other stories | Dependencies chain correctly | ✅ |

---

## Summary and Recommendations

### Overall Readiness Status

**🟠 NEEDS WORK — 2 critical issues, 3 major issues, 3 minor concerns**

The planning artifacts are exceptionally thorough and well-structured. The major risks are all **resolvable before implementation starts** without restructuring the epic plan.

---

### Critical Issues Requiring Immediate Action

**1. FR-ASSIGN-3/4 vs Architecture AR-5 Contradiction**
The PRD requires multi-team assignment and multiple owners per team. The architecture enforces a hard 1:1 UNIQUE constraint. The epics implement the architecture (not the PRD). These two requirements are mutually exclusive. Decide which is correct and update the other artifact to match.

**Suggested resolution:** Add a note to the epics explicitly deferring FR-ASSIGN-3/4 to a future phase, and update the epics FR Coverage Map to note the deferral. Update the PRD to move these to "Out of Scope" for this phase.

**2. Schema Conflicts Between `user_accounts_schema.sql` and Architecture**
Three concrete conflicts will cause runtime failures:
- `login_attempts.username` vs `login_attempts.identifier` (column name)
- `remember_tokens.token` vs `remember_tokens.token_hash` (column design + security)
- `teams.team_id` / `locations.location_id` vs generic `id` references in architecture pseudocode

Story 1.1 must explicitly handle ALTER TABLE for the first two. All subsequent stories must explicitly use `team_id` and `location_id`.

**Story 1.1 has already been created with these conflict warnings embedded.** ✅ Verified.

---

### Major Issues

**3. `activity_log` Table Has No Migration**
The `ActivityLogger` (Story 1.3) writes to `activity_log` but no migration creates this table. Neither schema file defines it (the existing `user_activity_log` table has a different name and structure). Story 1.3's file list must include `database/migrations/007_add_activity_log.sql`.

**4. FR-RESTRICTIONS-1/2/7 Enforcement Timing Ambiguity**
These restrictions are assigned to Epic 9 but must be enforced from Epic 4 onward. Confirm that `PermissionGuard` + the absence of coach-accessible admin routes provides coverage, and document this explicitly in Story 9's context so dev agents don't assume new enforcement code must be written in Epic 9.

**5. Story 4.3 is Oversized (3 distinct work areas)**
Stories 4.3 combines pending team queue, user detail team assignment, and `UserManagementService` bootstrapping. High complexity and risk for a single story. Consider splitting, or ensure the story file provides extremely explicit task breakdown.

---

### Minor Concerns

**6. Preferred Name Display Logic in Coach Hero Not Specified**
Story 4.4 AC doesn't clarify whether "coach name" in the hero banner uses preferred name when set. Add explicit AC: display preferred name if set, otherwise first name.

**7. Story 3.3 is Dense (backend service + admin UI)**
Not a structural problem, but the story file should explicitly partition backend tasks from UI tasks.

**8. Epic 3 Cross-Epic Dependency on Epic 2**
Story 3.2 cannot be built until Epic 2 Story 2.1 (`LeagueListManager`) is complete. This is correct architecturally but should be called out in the sprint plan as a sequencing gate.

---

### Recommended Next Steps

1. **Resolve FR-ASSIGN-3/4 vs AR-5 conflict** — Update PRD to defer multi-team to a future phase, or update architecture to remove the 1:1 constraint. Do this before creating Story 2.1.

2. **Add `007_add_activity_log.sql` to Story 1.1's file list** — Or create a Story 1.3 amendment note before dev work starts. The `activity_log` DDL must be defined before `ActivityLogger` can be implemented.

3. **Create Story 1.2 next** — The Story 1.1 file correctly handles the schema conflict warnings. Proceed with implementing 1.1 via `dev-story`, then create 1.2 which removes legacy auth.

4. **When creating Story 4.3** — Explicitly split the task list into three labeled sections: (a) pending team queue on admin/teams/index.php, (b) team assignment on admin/users/detail.php, (c) UserManagementService bootstrap.

5. **When creating Story 4.4** — Add AC clarifying preferred name display logic in Coach Identity Hero.

---

### Final Note

This assessment identified **8 issues** across **3 categories** (2 critical, 3 major, 3 minor). The planning documents are of high quality — the issues found are the kind that cause implementation failures and security regressions if uncaught, not the kind that indicate poor planning. Addressing the two critical issues (FR-ASSIGN conflict and `activity_log` missing migration) before implementation begins will significantly reduce rework risk.

The schema conflict issues surfaced by the create-story workflow for Story 1.1 were **real conflicts** that this assessment independently confirmed. The Story 1.1 file already contains the correct mitigation guidance.

**Report saved to:** `_bmad-output/planning-artifacts/implementation-readiness-report-2026-05-04.md`
