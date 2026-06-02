---
workflowType: 'prd'
feature: umpire-assignment
project: league-manager
outputFile: _bmad-output/planning-artifacts/prd.md
author: Mike
date: '2026-05-28'
version: '1.0'
releaseMode: phased
classification:
  projectType: web-application
  domain: sports-league-management
  complexity: medium
  projectContext: brownfield
councilDecisions:
  mvp_boundary: C
  role_strategy: R-C
  multi_umpire: M-C
  coach_visibility: V-C
inputDocuments:
  - _bmad-output/planning-artifacts/product-brief.md
  - _bmad-output/brainstorming/brainstorming-session-2026-05-28-umpire-assignment.md
  - _bmad-output/planning-artifacts/research/council-decisions-2026-05-28.md
  - _bmad-output/planning-artifacts/research/domain-umpire-assignment-research-2026-05-28.md
  - _bmad-output/planning-artifacts/research/market-umpire-assignment-research-2026-05-28.md
  - _bmad-output/planning-artifacts/research/technical-umpire-assignment-research-2026-05-28.md
  - _bmad-output/project-context.md
  - docs/requirements.md
  - docs/tech.md
documentCounts:
  briefCount: 1
  researchCount: 4
  brainstormingCount: 1
  projectDocsCount: 3
stepsCompleted:
  - step-01-init
  - step-02-discovery
  - step-02b-vision
  - step-02c-executive-summary
  - step-03-success
  - step-04-journeys
  - step-05-domain
  - step-06-innovation
  - step-07-project-type
  - step-08-scoping
  - step-09-functional
  - step-10-nonfunctional
  - step-11-polish
  - step-12-complete
status: complete
---

# Product Requirements Document — Umpire Assignment

**Project:** District 8 Travel League — league-manager  
**Author:** Mike  
**Date:** 2026-05-28  
**Version:** 1.0  
**Feature:** Umpire Assignment Module (v2.3.0)

---

## Executive Summary

District 8 Travel League assigns **paid umpires** to 100+ interleague games each season. Today the Umpire Assignor (Jennifer Bertollini) coordinates assignments via spreadsheet, email, and phone — disconnected from the **official schedule** on district8travelleague.com. When coaches request reschedules and admins approve them, umpires are not reliably re-notified. Coaches do not know who to pay before game start. No-show and fee disputes trace back to stale unofficial copies.

This PRD defines an **umpire assignment module** built into the existing league-manager brownfield stack (PHP 8.1, MariaDB, PHPMailer, Bootstrap). Assignments bind to authoritative `games` records via foreign keys. A **draft → publish** workflow separates internal assignment work from official communications. On publish, umpires receive assignment emails; coaches see published umpire names and fee reminders on their team schedule. When `schedule_change_requests` are approved, assigned umpires receive automatic change notifications.

**Council-approved MVP boundary:** assign + email + reschedule cascade (Decision C), with dedicated `umpire_assignor` role (R-C), two slot model with warn-only partial publish (M-C), and coach visibility of names + fee text (V-C). Token confirm/decline is **P1 stretch**.

**Primary differentiator:** The only zero-cost solution that is both native to the official schedule and wired into the existing reschedule approval pipeline.

---

## Goals

### Business Goals

| # | Goal | Rationale |
|---|------|-----------|
| BG-1 | Establish league-manager as the **system of record** for umpire assignments tied to official games | D8 rules §2.4.1: assignments, no-show fees, and forfeit fees derive from official schedule |
| BG-2 | Reduce assignor coordination burden vs spreadsheet baseline | Market research: manual workflows consume 10–20 hrs/week; target ≤50% reduction |
| BG-3 | Eliminate umpire no-shows caused by uncommunicated schedule changes | Highest-ROI integration: hook reschedule approval → auto-notify |
| BG-4 | Enable coaches to pay umpires before game start without calling assignor | KB 1.15.1: payment before start; coach visibility closes operational loop |
| BG-5 | Maintain **zero marginal cost** — no new SaaS, SMS vendor, or infrastructure | Shared hosting constraint; paid alternatives $150–480+/year disqualified |

### User Goals

| Persona | Goal |
|---------|------|
| Umpire Assignor | Assign plate/base umpires quickly from phone or desktop; publish once; trust notifications go out |
| Umpire | Receive accurate assignment details; know when games move or cancel |
| Coach | See who is umpiring and what each team pays — on the same schedule they already use |
| Administrator | Override when assignor unavailable; audit who changed what |
| Division Director | Dispute resolution backed by immutable assignment history |

### Product Goals

| # | Goal | Success Signal |
|---|------|----------------|
| PG-1 | Draft/publish separation prevents accidental early notifications | Coaches never see draft assignments |
| PG-2 | Delta-only email sends on re-publish | No duplicate spam on unchanged slots |
| PG-3 | Reschedule cascade within same job as schedule approval | 100% of material changes trigger umpire email |
| PG-4 | Conflict detection prevents silent double-books | Hard block with audited override |
| PG-5 | Role-scoped assignor access without full admin keys | Jennifer operates without score/schedule-edit permissions |

---

## Personas

### P-1: Jennifer Bertollini — Umpire Assignor (Primary)

- **Role:** Umpire Assignor per D8 2026 rules §2.4.4
- **Context:** Coordinates 30–50 umpires across Intermediate, Junior, Senior divisions; works from phone at fields
- **Current tools:** Spreadsheet + email + text; parallel to official website schedule
- **Pain:** Reschedule storms; double-booking; coaches calling for assignment status; no audit trail for fee disputes
- **Needs:** Fast game-centric assignment UI, unassigned queue, publish batch, conflict warnings, reschedule auto-notify
- **Success:** Stops maintaining parallel spreadsheet by mid-season

### P-2: Crew Umpire — Paid Official (Secondary)

- **Profile:** Mix of adult and teen umpires; email-primary; may not have app login
- **Pain:** Wrong field/time after reschedule; email in spam; no way to decline without calling
- **Needs:** Clear assignment email with game #, date, time, field, role (plate/base), fee note, assignor contact, maps link
- **P1 needs:** One-click confirm/decline via signed token (no login)

### P-3: Team Coach — Team Owner (Secondary)

- **Context:** Uses coach schedule view for games; must pay umpire before game start
- **Pain:** Doesn't know assigned umpire until assignor texts; fee split confusion
- **Needs:** Read-only published umpire names + fee reminder on team schedule
- **Constraint:** Cannot assign or modify umpires (ROL-35)

### P-4: Mike O'Connell — Division Director / Admin (Tertiary)

- **Context:** Schedule authority; adjudicates reschedules and fee disputes
- **Needs:** Admin override with mandatory reason; activity log; read-only audit of assignment history
- **Use case:** Assignor vacation coverage; assignor-error fee waiver per KB 1.15.4–1.15.5

### P-5: Jack Kaplan — Umpire-in-Chief (Tertiary, P2)

- **Context:** Program quality oversight; not day-to-day assignor
- **Needs:** Read-only view of all assignments and audit trail (ROL-33) — deferred to P2

---

## Success Criteria

| # | Criterion | Measurement | Target |
|---|-----------|-------------|--------|
| SC-1 | Published assignments exist for upcoming games | Query: games in next 14 days with ≥1 published slot / total games | ≥80% by week 6 of season |
| SC-2 | Reschedule approval triggers umpire notification | Integration test + email queue log for approved changes affecting assigned games | 100% |
| SC-3 | Draft assignments invisible to coaches | Coach schedule API/view returns no umpire data where `published=0` | 0 leaks |
| SC-4 | Double-book blocked without override | Service test: overlapping datetime same umpire | Block + require reason |
| SC-5 | Assignor can complete assign+publish without admin role | Permission test: `umpire_assignor` routes only | Pass |
| SC-6 | Coach schedule shows umpire names + fee text when published | UI test on coach team schedule | Pass for Intermediate/Junior/Senior |
| SC-7 | Material schedule change resets tentative confirmation | P1: status returns to Pending after approved reschedule | Pass |
| SC-8 | Activity log captures publish and override events | ActivityLogger entries for `umpire.published`, `umpire.reassigned` | Pass |
| SC-9 | Assignor weekly time self-reported vs baseline | Stakeholder check-in at 4 and 8 weeks | ≤50% of spreadsheet hours |
| SC-10 | Zero umpire no-shows attributed to stale schedule post go-live | Qualitative season tracking | 0 confirmed incidents |

---

## User Journeys

### UJ-1: Assignor Assigns and Publishes Umpires for a Game

1. Assignor logs in with `umpire_assignor` role.
2. Opens **Unassigned Games** queue (next 14 days) or admin games list.
3. Selects game → **Assign Umpires** drawer opens with game details (date, time, field, teams, division).
4. Selects umpire for **Plate** slot; optionally **Base** slot.
5. System checks double-book conflict; warns if division expects 2 umpires but only 1 assigned.
6. Saves → status `Draft`, `published=0`.
7. Clicks **Publish Assignments** (single game or batch) → delta emails sent to affected umpires; slots marked `published=1`, `Pending` (or `Confirmed` if P1 token flow active).
8. Coach schedule now shows published umpire names + fee reminder.

### UJ-2: Assignor Handles Unassigned Game Triage

1. Assignor views dashboard widget: games in next 14 days with zero published assignments, sorted by date.
2. Filters by division or date range.
3. Assigns from queue using same drawer flow as UJ-1.

### UJ-3: Reschedule Approved — Umpire Auto-Notify (MVP)

1. Coach submits `schedule_change_request` for game with published assignments.
2. Assignor board shows **TENTATIVE** badge on affected game (RES-37).
3. Admin approves change in schedule management UI.
4. `UmpireAssignmentService::onScheduleChanged($gameId)` runs:
   - Detects material change (date, time, or location).
   - Sets assignment status to `Pending` (clears prior confirm if P1).
   - Sends `onUmpireAssignmentChanged` email to each assigned umpire.
   - Sends assignor alert email (RES-39).
5. Umpire receives updated details; assignor verifies crew still available.

### UJ-4: Coach Views Umpire on Team Schedule (MVP)

1. Coach logs in, navigates to team schedule.
2. For each game with published assignments, sees:
   - Plate umpire name
   - Base umpire name (if assigned)
   - Fee reminder text per division (e.g., "Each team pays one umpire $50")
3. Draft or unpublished assignments: no umpire names shown.

### UJ-5: Admin Overrides Assignment (MVP)

1. Admin opens game assignment drawer (broader permission than assignor).
2. Reassigns umpire; system requires **override reason** text.
3. ActivityLogger records `umpire.reassigned` with reason.
4. If published, delta notification sent to old and new umpire.

### UJ-6: Umpire Confirms or Declines via Email Token (P1)

1. Umpire receives assignment email with **Confirm** / **Decline** links.
2. GET link shows preview page; POST confirms action (prevents email prefetch consuming token).
3. Confirm → status `Confirmed`; Decline → status `Declined`, assignor dashboard alert, optional alt-list prompt (DEC-50).
4. 48h before game: unconfirmed slots alert assignor (DEC-52).

### UJ-7: Game Cancelled — Umpire Released (P1)

1. Admin marks game cancelled/postponed.
2. System sends `onUmpireGameCancelled` to assigned umpires.
3. Assignment slots set to `Cancelled`.

---

## Domain Requirements

Derived from D8 2026 rules (KB migration 024), Little League norms, and domain research.

| # | Requirement | Source |
|---|-------------|--------|
| DR-1 | Assignments must reference games on the **official schedule** only | D8 §2.4.1 |
| DR-2 | Umpires are **paid before game start**; fee amounts vary by division and crew size | D8 §1.15.1–1.15.2 |
| DR-3 | Intermediate: 1 umpire $70 ($35/team) or 2×$50 each; Junior/Senior: 1×$100 or 2×$80 each | D8 §1.15.1–1.15.2 |
| DR-4 | Home team must notify assignor ≥1.5 hours before same-day cancellation | D8 §2.4.4 |
| DR-5 | Reschedule changes not official until approved on website | D8 §2.5 |
| DR-6 | Volunteer umpire fallback if assigned umpire no-shows — no fee to volunteer | D8 §1.15.3 |
| DR-7 | Forfeit/no-contest fee rules apply per plate umpire declaration | D8 §1.15.4–1.15.5 |
| DR-8 | Assignor/Division Director scheduling error can waive no-contest fees | D8 §1.15.5 |
| DR-9 | Crew model: 1 or 2 paid umpires (plate + base) by division agreement | LL Rule 1.01; D8 fees |
| DR-10 | Unofficial schedule copies do not govern — display official schedule banner on umpire comms | DOM-58 |
| DR-11 | Future: JDP background check + Abuse Awareness Training gates for roster eligibility | LL child protection; P2 |

---

## Project Scoping & Phased Development

### MVP Strategy

**Approach:** Problem-solving MVP — replace spreadsheet assignment + manual reschedule notify with authoritative, integrated workflow.

**Philosophy:** Ship the smallest set that makes the assignor say "I can stop using my parallel spreadsheet for new games." Reschedule cascade is MVP (council Decision C), not optional.

**Resource estimate:** 1 developer, ~3–4 weeks (Size M per technical research); +3–5 days for P1 token flow.

### MVP (Phase 1 — v2.3.0)

**Must ship:**

| Area | Capabilities |
|------|-------------|
| Data | `game_umpire_assignments`, `umpire_assignor` role, umpire roster |
| Assignor UI | Game-centric drawer on admin games; unassigned queue widget |
| Slots | Plate + Base; warn (not block) on partial two-umpire crew |
| Workflow | Draft save; publish (single + batch); delta-only emails |
| Conflicts | Double-book hard block; override with reason + audit |
| Reschedule | Hook schedule approval → `onScheduleChanged`; tentative badge during pending request |
| Coach | Published names + fee reminder on team schedule |
| Comms | `onUmpireAssignmentPublished`, `onUmpireAssignmentChanged` templates |
| Audit | ActivityLogger events; assignment history (no hard delete) |
| Roles | `umpire_assignor` role routes; admin override |

### P1 — Communication Loop Stretch (v2.3.x)

| Capability | Ideas |
|------------|-------|
| Token confirm/decline | DEC-49, DEC-52 |
| Cancellation release email | COM-22 |
| Assignor alert on reschedule approval | RES-39 |
| No-response escalation | 48h before game |
| Re-confirm after material reschedule | RES-37 elicitation |

### P2 — Self-Service & Operations (v2.4+)

| Capability | Ideas |
|------------|-------|
| Umpire portal / `umpire` role | ROL-32 |
| Availability / blackout dates | CON-26 |
| Self-assign with skill gating | WF-03, market Phase 2 |
| UIC read-only oversight | ROL-33 |
| Travel time warning | CON-24 |
| Eligibility gates (JDP/training) | DR-11 |
| Pay report CSV export | AUD-48 |
| ICS calendar attachment | COM-15 |
| Printable weekly crew sheet PDF | WF-10 |

### P3 — Deferred / Out of Scope

See [Out of Scope](#out-of-scope) section.

---

## Functional Requirements

### Umpire Roster Management

- **FR-ROSTER-1:** Admin or assignor can create and maintain an umpire roster entry (name, email, phone, active status).
- **FR-ROSTER-2:** Admin or assignor can link a roster entry to an existing user account (`users.id`) when the umpire has login credentials.
- **FR-ROSTER-3:** Admin or assignor can mark an umpire as **volunteer** (no-pay) so fee reminders are suppressed in coach and umpire communications.
- **FR-ROSTER-4:** Admin can deactivate an umpire; deactivated umpires cannot be newly assigned but historical assignments remain visible.
- **FR-ROSTER-5:** Admin can assign the `umpire_assignor` role to a user account.

### Assignment Operations

- **FR-ASSIGN-1:** Assignor can view a list of scheduled games filterable by date range and division.
- **FR-ASSIGN-2:** Assignor can open an **Assign Umpires** interface from a game row showing game number, date, time, location, home/away teams, and division.
- **FR-ASSIGN-3:** Assignor can assign one umpire to the **Plate** slot and optionally one to the **Base** slot for a game.
- **FR-ASSIGN-4:** Assignor can save assignments in **Draft** state without sending notifications or exposing names to coaches.
- **FR-ASSIGN-5:** Assignor can **Publish** assignments for one or more games; publish sends notifications only for slots that changed since last publish (delta).
- **FR-ASSIGN-6:** Assignor can add optional per-game **assignment notes** included in umpire notification emails.
- **FR-ASSIGN-7:** Assignor can clear or reassign an umpire from a slot; republish triggers appropriate delta notifications.
- **FR-ASSIGN-8:** System displays a warning when publishing a game in a division configured for two umpires but fewer than two slots are filled; assignor can proceed with documented override reason.
- **FR-ASSIGN-9:** Assignor can view an **Unassigned Games** report of games within a configurable window (default 14 days) with no published assignments.

### Conflict Detection

- **FR-CONFLICT-1:** System blocks saving or publishing an assignment if the same umpire is assigned to overlapping games (same date/time window) unless admin or assignor provides an override reason.
- **FR-CONFLICT-2:** System logs all conflict overrides via ActivityLogger with umpire, games, and reason.

### Publish & Notification State

- **FR-PUBLISH-1:** Only assignments with `published=1` are visible on coach schedule views and considered official for communications.
- **FR-PUBLISH-2:** System tracks `last_notified_at` per assignment slot to support delta-only email sends.
- **FR-PUBLISH-3:** Assignor dashboard shows count of games with unpublished draft assignments.

### Reschedule Integration

- **FR-RESCHED-1:** When a `schedule_change_request` is **pending** for a game, assignor UI displays assignments as **TENTATIVE**.
- **FR-RESCHED-2:** When a schedule change is **approved** and affects date, time, or location of a game with published assignments, system automatically sends change notification emails to assigned umpires.
- **FR-RESCHED-3:** After material schedule change approval, assignment status returns to `Pending` (clears prior confirmation when P1 token flow is enabled).
- **FR-RESCHED-4:** When a schedule change is **denied**, no umpire notifications are sent and assignments remain on the original slot.
- **FR-RESCHED-5:** On material schedule change approval, system sends an alert email to the assignor listing affected games and assigned umpires.
- **FR-RESCHED-6:** System does not send umpire change notifications for immaterial edits (e.g., note-only) — configurable material-change threshold defaults to any date/time/location change.

### Coach Visibility

- **FR-COACH-1:** Coach team schedule displays **published** plate and base umpire names for each game involving the coach's team.
- **FR-COACH-2:** Coach team schedule displays division-appropriate **fee reminder text** alongside umpire names (per KB 1.15.1–1.15.2), suppressed for volunteer umpires.
- **FR-COACH-3:** Coaches cannot assign, edit, or remove umpire assignments.
- **FR-COACH-4:** Coach schedule does not display draft or unpublished assignments.

### Admin Override & Audit

- **FR-ADMIN-1:** Administrator can assign or reassign umpires on any game regardless of assignor ownership.
- **FR-ADMIN-2:** Administrator reassignment requires a mandatory **reason** text field.
- **FR-ADMIN-3:** System records ActivityLogger events for: assign, publish, reassign, decline (P1), and conflict override.
- **FR-ADMIN-4:** Administrator can view assignment history for a game (current and prior slot states retained).

### Token Confirm / Decline (P1)

- **FR-TOKEN-1:** Published assignment emails include signed, single-use token links for Confirm and Decline actions.
- **FR-TOKEN-2:** Umpire can confirm or decline without logging in; token binds to `umpire_user_id` + `game_id` + slot.
- **FR-TOKEN-3:** Decline notifies assignor and sets slot status to `Declined`.
- **FR-TOKEN-4:** System sends assignor alert for slots still unconfirmed 48 hours before game time.
- **FR-TOKEN-5:** Confirm action uses POST after GET preview to prevent email scanner token consumption.

### Game Cancellation (P1)

- **FR-CANCEL-1:** When game status becomes Cancelled or Postponed, system sends release notification to assigned umpires and sets assignment status to `Cancelled`.

---

## Non-Functional Requirements

### Performance (NFR-PERF)

- **NFR-PERF-1:** Assignor games list with assignment status loads within **3 seconds** on shared hosting for a 4-week window (~200 games).
- **NFR-PERF-2:** Single-game assignment save (including conflict check) completes within **2 seconds** at p95.
- **NFR-PERF-3:** Publish batch of up to 20 games completes within **30 seconds** including email queue enqueue.

### Security (NFR-SEC)

- **NFR-SEC-1:** All assignment mutations require authenticated session with appropriate role and CSRF token per existing app patterns.
- **NFR-SEC-2:** Confirm/decline tokens are cryptographically random, expire after **7 days** or game datetime (whichever is sooner), and are single-use.
- **NFR-SEC-3:** Assignor routes are allowlisted; assignor role cannot access score submission, user management, or unrestricted schedule edit routes.
- **NFR-SEC-4:** Umpire PII (email, phone) exposed only to assignor, admin, and assigned umpire notification — not on public schedule.

### Reliability (NFR-REL)

- **NFR-REL-1:** Reschedule-triggered umpire notifications execute in the same request or queued job as schedule approval — no silent skip.
- **NFR-REL-2:** Email send failures are logged in email queue with retrievable status for support/debug (AUD-47).
- **NFR-REL-3:** Assignment database records are the source of truth; email is a notification channel, not the authority.

### Compatibility (NFR-COMPAT)

- **NFR-COMPAT-1:** Assignor UI is responsive (Bootstrap 5) and usable on mobile viewport ≥320px width.
- **NFR-COMPAT-2:** No new runtime dependencies beyond existing stack (PHP 8.1, MariaDB, PHPMailer).
- **NFR-COMPAT-3:** Feature degrades gracefully if email SMTP unavailable — assignments save; publish reports email failure to assignor.

### Maintainability (NFR-MAINT)

- **NFR-MAINT-1:** Umpire assignment logic lives in `UmpireAssignmentService` under `includes/` following existing service patterns (`RescheduleService`, `EmailService`).
- **NFR-MAINT-2:** Database changes delivered via numbered migration in `database/migrations/`.
- **NFR-MAINT-3:** Division fee reminder text sourced from configuration or KB seed — not hardcoded in views.

### Accessibility (NFR-ACCESS)

- **NFR-ACCESS-1:** Assignor form controls meet WCAG 2.1 AA contrast and label requirements consistent with existing admin pages.
- **NFR-ACCESS-2:** P1 confirm/decline action buttons have minimum 44×44px touch targets.

---

## Data Model Summary

### New Table: `game_umpire_assignments`

| Column | Type | Notes |
|--------|------|-------|
| `assignment_id` | INT PK AUTO_INCREMENT | |
| `game_id` | INT FK → `games.game_id` | ON DELETE CASCADE |
| `umpire_user_id` | INT FK → `users.id` NULL | NULL = open slot |
| `slot` | ENUM('Plate','Base') | UNIQUE (`game_id`, `slot`) |
| `assignment_status` | ENUM('Open','Draft','Pending','Confirmed','Declined','Cancelled') | |
| `published` | TINYINT(1) DEFAULT 0 | Coach visibility gate |
| `assigned_by_user_id` | INT FK NULL | |
| `assigned_at` | DATETIME NULL | |
| `responded_at` | DATETIME NULL | P1 token flow |
| `last_notified_at` | DATETIME NULL | Delta email support |
| `response_token` | VARCHAR(64) UNIQUE NULL | P1 |
| `token_expires_at` | DATETIME NULL | P1 |
| `notes` | TEXT NULL | Assignor notes in email |
| `override_reason` | TEXT NULL | Conflict/partial crew override |
| `created_at` / `modified_at` | DATETIME | Standard audit timestamps |

**History pattern:** Updates to published assignments insert history row or retain prior state in append-only log table (AUD-46) — implementation choice: either `game_umpire_assignment_history` child table or soft-version via `modified_at` + ActivityLogger; PRD requires dispute-grade provenance.

### New or Extended: Umpire Roster

Option A (MVP): Umpires are `users` with role flag + profile fields (`phone`, `volunteer_flag`, `active`).

Option B: Separate `umpires` table with optional `user_id` link — use if many roster members lack logins.

**Recommendation (MVP):** Extend `users` / profile for linked accounts; `umpire_roster` table for non-login contacts with `email`, `phone`, `name`, `volunteer`, `active`.

### Division Configuration Extension

| Field | Purpose |
|-------|---------|
| `default_umpire_count` | 1 or 2 — drives publish warning |
| `fee_one_umpire_total` | e.g., 70 / 100 |
| `fee_two_umpire_each` | e.g., 50 / 80 |
| `fee_reminder_template` | Coach display text |

Seed from KB migration 024 rules for Intermediate, Junior, Senior.

### Existing Tables (Read/Write Integration)

| Table | Integration |
|-------|-------------|
| `games` | FK target; `game_status` drives cancellation flow |
| `schedules` | Date/time/location for conflict check and email content |
| `schedule_change_requests` | Pending → tentative UI; approval → notification hook |
| `schedule_history` | Optional cross-reference for audit |
| `email_templates` | New template rows for umpire notifications |
| `users` | Umpire and assignor accounts; role column extended |

### Entity Relationship (Conceptual)

```
games 1──* game_umpire_assignments *──1 users (umpire)
games 1──1 schedules
games 1──* schedule_change_requests
users (assignor) 1──* game_umpire_assignments (assigned_by)
divisions 1──* games (fee/crew defaults)
```

---

## Email & Notification Specifications

All templates use existing `EmailService::triggerNotificationToAddress()` and `email_templates` table. Plain-text alternative required for each HTML template.

### Template Catalog

| Template Key | Trigger | Recipients | Priority |
|--------------|---------|------------|----------|
| `onUmpireAssignmentPublished` | Assignor publishes new/changed slot | Assigned umpire email | MVP |
| `onUmpireAssignmentChanged` | Approved schedule change (material) | Assigned umpire(s) | MVP |
| `onUmpireAssignmentChangedAssignorAlert` | Approved schedule change (material) | Assignor email | MVP |
| `onUmpireGameCancelled` | Game cancelled/postponed | Assigned umpire(s) | P1 |
| `onUmpireAssignmentDeclined` | Token decline | Assignor email | P1 |
| `onUmpireUnconfirmedReminder` | 48h before game, status Pending | Assignor email | P1 |

### `onUmpireAssignmentPublished` — Content Requirements

| Field | Source |
|-------|--------|
| Subject | Neutral: `D8 Assignment: {date} {time} — {slot}` (no team names in subject — COM-13 pre-mortem) |
| Game number | `games.game_number` |
| Date / time | `schedules` |
| Location name + maps link | `locations` (existing maps link pattern from Epic 14) |
| Home vs away teams | `games` |
| Division | `divisions` |
| Slot role | Plate or Base |
| Fee note | Division fee text ("Payment collected at plate before start") |
| Assignor contact | KB assignor phone + Reply-To assignor email (COM-16, COM-17) |
| Assignment notes | `game_umpire_assignments.notes` |
| Official schedule banner | DOM-58 text + link |
| Confirm/Decline links | P1 only — token URLs |
| `tel:` links | Assignor phone, home coach if available (MOB-44) |

### `onUmpireAssignmentChanged` — Content Requirements

Same as published plus:

- **Change summary:** "Was: {old date/time/field} → Now: {new date/time/field}"
- **Action line:** "Please confirm you can still work this assignment" (P1) or "Contact assignor if unavailable" (MVP)

### Delta Send Rules (COM-14)

Send email when any of: umpire identity changed, slot newly filled, date/time/location changed, game cancelled, publish transitions draft→published. **Do not send** when only internal assignor notes change unless explicitly "re-notify" selected.

### Email Infrastructure

- **From:** Existing league SMTP From address
- **Reply-To:** Assignor email (COM-16)
- **Footer:** Day-of issues: call/text assignor per KB §2.4.4 (COM-17) — no Twilio in MVP
- **Logging:** Record `email_queue` entry ID on assignment for AUD-47

---

## Role Permissions

### Role Matrix

| Capability | `umpire_assignor` | `admin` | `team_owner` (coach) | `umpire` (P2 portal) | `user` / public |
|------------|:-----------------:|:-------:|:--------------------:|:--------------------:|:---------------:|
| View assignor board / unassigned queue | ✅ | ✅ | ❌ | ❌ | ❌ |
| Assign / draft / publish umpires | ✅ | ✅ | ❌ | ❌ | ❌ |
| Manage umpire roster | ✅ | ✅ | ❌ | ❌ | ❌ |
| Override conflict with reason | ✅ | ✅ | ❌ | ❌ | ❌ |
| Reassign with mandatory reason | ❌ | ✅ | ❌ | ❌ | ❌ |
| View all assignment audit history | ✅ | ✅ | ❌ | ❌ | ❌ |
| View published umpires on team schedule | ❌ | ✅ | ✅ (own team) | ❌ | ❌ |
| Confirm/decline via token | ❌ | ❌ | ❌ | ✅ (email only) | ❌ |
| Edit game schedule / scores | ❌ | ✅ | ❌ | ❌ | ❌ |
| Approve schedule change requests | ❌ | ✅ | ❌ | ❌ | ❌ |

### PermissionGuard Implementation

Extend `PermissionGuard::$ROLE_SATISFIES`:

```php
'umpire_assignor' => ['umpire_assignor', 'admin'],
'umpire' => ['umpire', 'umpire_assignor', 'admin'],  // P2
```

**Route allowlist for assignor:**

- `/admin/umpire-assignments/*` (board, queue, publish)
- `/admin/umpire-roster/*`
- Read-only access to admin games list fields needed for assignment drawer

**Explicit deny:** assignor cannot access `/admin/schedules/approve`, `/admin/users`, score routes, or coach management.

### Interim Migration

If assignor account not yet provisioned: admin can operate assignment UI until `umpire_assignor` user seeded — document in deployment checklist.

---

## Coach Visibility Specification

Per council decision **V-C**: coaches see **names + fee reminder** on published schedule only.

### Display Rules

| Condition | Coach Team Schedule Shows |
|-----------|---------------------------|
| `published=1` and Plate assigned | Plate umpire display name |
| `published=1` and Base assigned | Base umpire display name |
| `published=1`, division 2-umpire, only 1 slot | Single name + fee text + optional "(Base TBD)" if assignor enabled |
| `published=0` or Draft | No umpire section |
| Volunteer umpire | Names shown; fee reminder replaced with "Volunteer umpire — no fee" |
| Game cancelled | Umpire section hidden or "Released" after P1 |

### Fee Reminder Text Examples

| Division | Crew | Coach Display |
|----------|------|---------------|
| Intermediate | 2 umpires | "Each team pays one umpire $50 before game start." |
| Intermediate | 1 umpire | "Each team pays $35 before game start ($70 total)." |
| Junior/Senior | 2 umpires | "Each team pays one umpire $80 before game start." |
| Junior/Senior | 1 umpire | "Each team pays $50 before game start ($100 total)." |

### Privacy

- Umpire email and phone **not** shown to coaches on schedule (names only).
- Public schedule page: umpire names **hidden** in MVP (COM-19 deferred — policy decision).

---

## Reschedule Integration Specification

### Hook Point

In schedule change approval handler (`public/admin/schedules/index.php` or `RescheduleService` approval method), after successful commit:

```php
UmpireAssignmentService::onScheduleChanged($gameId, $changeContext);
```

### Behavior Matrix

| Event | Assignment UI | Umpire Email | Assignor Email | Coach View |
|-------|---------------|--------------|----------------|------------|
| Change request submitted | TENTATIVE badge | None | None | Unchanged until approved |
| Change approved (material) | Status → Pending | `onUmpireAssignmentChanged` | Alert template | Updates to new datetime + same umpire names |
| Change approved (immaterial) | No change | None | None | Unchanged |
| Change denied | Tentative cleared | None | None | Unchanged |
| Game cancelled | Slots → Cancelled (P1) | `onUmpireGameCancelled` (P1) | Optional | Umpire section removed |

### Material Change Definition

Material = any change to `schedule_date`, `schedule_time`, or `location_id`. Future: configurable minimum time delta (e.g., >15 min) — default all changes material for MVP.

### Transaction Safety

Notification enqueue must not roll back schedule approval if email fails; log failure and surface assignor retry action (NFR-REL-2).

---

## Acceptance Criteria

### MVP Release Checklist

#### Assignment Core

- [ ] **AC-MVP-01:** Assignor can assign Plate and Base umpires to a game and save as Draft without email sent.
- [ ] **AC-MVP-02:** Assignor can Publish assignments; assigned umpires receive email within 5 minutes (SMTP permitting).
- [ ] **AC-MVP-03:** Re-publish unchanged slots does not send duplicate emails.
- [ ] **AC-MVP-04:** Re-publish with different umpire sends email only to old and new umpire as appropriate.
- [ ] **AC-MVP-05:** Unassigned queue lists games in next 14 days with zero published assignments.

#### Multi-Umpire (M-C)

- [ ] **AC-MVP-06:** Intermediate game configured for 2 umpires shows warning when publishing with only Plate filled; assignor can override with reason.
- [ ] **AC-MVP-07:** Both Plate and Base can be assigned to different umpires on same game.

#### Conflicts

- [ ] **AC-MVP-08:** Assigning same umpire to overlapping games is blocked until override reason entered.
- [ ] **AC-MVP-09:** Override reason appears in ActivityLogger.

#### Reschedule (C)

- [ ] **AC-MVP-10:** Pending schedule change shows TENTATIVE on assignor view.
- [ ] **AC-MVP-11:** Approved date change sends `onUmpireAssignmentChanged` to all published assigned umpires.
- [ ] **AC-MVP-12:** Denied schedule change sends no umpire emails.
- [ ] **AC-MVP-13:** Assignor receives alert email on material approved change affecting assigned game.

#### Coach Visibility (V-C)

- [ ] **AC-MVP-14:** Coach team schedule shows published umpire names for their games.
- [ ] **AC-MVP-15:** Coach team schedule shows correct fee reminder for division and crew size.
- [ ] **AC-MVP-16:** Draft assignments not visible to coach.

#### Roles (R-C)

- [ ] **AC-MVP-17:** User with `umpire_assignor` role can access assignment UI but not admin user management or schedule approval.
- [ ] **AC-MVP-18:** Admin can override assignment with mandatory reason.

#### Audit

- [ ] **AC-MVP-19:** Publish event logged in ActivityLogger with game IDs and actor.
- [ ] **AC-MVP-20:** Assignment history retrievable for dispute ("who was assigned when").

#### Email Quality

- [ ] **AC-MVP-21:** Assignment email contains game number, datetime, field, teams, role, assignor contact, maps link.
- [ ] **AC-MVP-22:** Plain-text part renders correctly in Gmail and Outlook spot check.
- [ ] **AC-MVP-23:** Reply-To is assignor address.

### P1 Release Checklist

- [ ] **AC-P1-01:** Confirm token sets status Confirmed; Decline sets Declined and alerts assignor.
- [ ] **AC-P1-02:** Token is single-use; expired token shows friendly error.
- [ ] **AC-P1-03:** POST required for confirm after GET preview.
- [ ] **AC-P1-04:** 48h unconfirmed alert sent to assignor.
- [ ] **AC-P1-05:** Game cancellation sends release email to umpires.
- [ ] **AC-P1-06:** Material reschedule clears Confirmed → Pending.

---

## Out of Scope

The following are explicitly **not** in MVP, P1, or P2 unless separately approved:

| Item | Rationale |
|------|-----------|
| Payment processing (Stripe, Venmo, field collection tracking) | Teams pay cash at field; KB enforcement is operational |
| SMS / Twilio notifications | Cost constraint; email + phone in footer sufficient |
| Umpire self-service portal (full login experience) | P2; email-first for season 1 |
| Auto-assign / AI scheduling engine | Complexity; manual assign sufficient at D8 scale |
| Public schedule umpire names | Privacy policy unset; coach-only visibility for MVP |
| Certification / JDP training tracking | Compliance important but P2 eligibility gate |
| ICS calendar feeds | P2 nice-to-have |
| Fee ledger / treasurer reconciliation export | P2 (AUD-48) |
| Open slot broadcast to crew (DEC-51) | High risk of race conditions |
| Native mobile apps | Responsive web only |
| Integration with external SaaS (UmpireScheduler, Assignr) | Zero-cost constraint |
| Tournament-specific two-umpire enforcement rules | Regular season focus; tournament ops manual |

---

## Risks & Mitigations

| ID | Risk | Likelihood | Impact | Mitigation |
|----|------|------------|--------|------------|
| R-1 | Assignor abandons tool for spreadsheet | Medium | High | UJ-1 ≤5 clicks; unassigned queue; parallel run first 2 weeks |
| R-2 | Email deliverability / spam | Medium | High | Plain-text alt; SPF/DKIM existing; test major clients pre-season |
| R-3 | PermissionGuard role gap causes security hole | Medium | High | Route allowlist tests; assignor cannot hit admin POST endpoints |
| R-4 | Reschedule hook missed on edge approval path | Low | High | Single service method; integration test on all approval code paths |
| R-5 | Partial two-umpire crews at field | Medium | Medium | Warn on publish; override audit; coach sees TBD |
| R-6 | Teen umpires no-show (no P1 confirm) | Medium | Medium | Ship P1 token confirm if capacity; MVP: assignor phone fallback |
| R-7 | Shared hosting performance on batch publish | Low | Medium | Batch limit 20; async email queue if needed |
| R-8 | Token security incident (P1) | Low | High | Single-use, short expiry, POST confirm, bind to umpire+slot |
| R-9 | Data migration on live season | Medium | Medium | Additive migration only; nullable slots; no breaking FK on games |
| R-10 | Scope creep into payment processing | Medium | High | PRD out-of-scope gate; fee text read-only only |
| R-11 | Coach privacy concern publishing names | Low | Low | Council chose V-C; no public display in MVP |
| R-12 | Assignor unavailable mid-season | Low | Medium | Admin override path documented; seed backup assignor role |

---

## Innovation & Differentiation

| Differentiator | vs Spreadsheet | vs Paid SaaS |
|----------------|----------------|--------------|
| Native official schedule FK | Manual sync eliminated | CSV import/sync still required |
| Reschedule cascade on approval | Manual re-email | Depends on import freshness |
| Zero marginal cost | Same | $150–480+/year |
| Coach fee reminder on same schedule view | Separate comms | Rare in standalone tools |
| Draft/publish comms control | Informal | Some SaaS has similar |

**Core insight:** Schedule-change integration (COM-21) delivers more reliability per dollar than any standalone assignor tool for this brownfield deployment.

---

## Technical Constraints (Brownfield)

| Constraint | Implication |
|------------|-------------|
| PHP 8.1, MariaDB, shared cPanel hosting | No Redis, no workers; email via PHPMailer queue or inline |
| PDO via `Database::getInstance()` | All new queries use prepared statements |
| Bootstrap 5 + vanilla JS | No React; drawer/modal on admin games page |
| Existing `ActivityLogger` | Reuse event naming convention |
| Existing `email_templates` | Seed new rows via migration |
| `PermissionGuard` partial roles | Must extend for `umpire_assignor` |

### Key Integration Files

| File | Change |
|------|--------|
| `includes/UmpireAssignmentService.php` | **New** — core logic |
| `includes/PermissionGuard.php` | Add role |
| `public/admin/games/index.php` | Assignment drawer |
| `public/admin/schedules/index.php` | Approval hook |
| `public/coaches/schedule.php` (or equivalent) | Coach umpire display |
| `database/migrations/0xx_umpire_assignments.sql` | Schema |

---

## Test Strategy Summary

| Category | Approach |
|----------|----------|
| Unit | `UmpireAssignmentService` conflict check, delta send logic, material change detection |
| Integration | Reschedule approval → email triggered; publish → coach view updated |
| Regression | Existing admin games, coach schedule, schedule change flows unchanged |
| Manual UI | Assignor mobile viewport; coach schedule display; email render spot check |
| Security | Role route enumeration; CSRF on publish; token expiry |

---

## Open Questions

| # | Question | Owner | Default if Unanswered |
|---|----------|-------|----------------------|
| OQ-1 | Exact % of 2026 games requiring 2 umpires by division | Assignor / SME | Warn-only (M-C); division defaults = 2 for Intermediate+ |
| OQ-2 | Roster table vs users-only for umpires without login | Architect | Separate `umpire_roster` with email for MVP |
| OQ-3 | Material change time threshold | PM | Any date/time/location change is material |
| OQ-4 | Show "(Base TBD)" to coaches on partial crew | PM | Yes, when division expects 2 |
| OQ-5 | P1 token confirm in same release as MVP | PM | Ship MVP first; P1 within 2 weeks if capacity |

---

## Document History

| Date | Version | Change |
|------|---------|--------|
| 2026-05-28 | 1.0 | Initial full PRD from product brief, brainstorming, council decisions, domain/market/technical research |

---

## Appendix A: Council Decision Traceability

| Decision | Choice | PRD Sections |
|----------|--------|--------------|
| DECISION_REQUIRED-1 MVP boundary | **C** — assign + email + reschedule cascade | Reschedule Integration, MVP scope, FR-RESCHED-* |
| DECISION_REQUIRED-2 Role strategy | **R-C** — assignor role; umpires email/token | Role Permissions, FR-ROSTER-5 |
| DECISION_REQUIRED-3 Multi-umpire | **M-C** — two slots; warn if partial | Data Model, FR-ASSIGN-8, AC-MVP-06 |
| DECISION_REQUIRED-4 Coach visibility | **V-C** — names + fee reminder | Coach Visibility, FR-COACH-* |
| PM stretch | **B** — token confirm/decline as P1 | FR-TOKEN-*, P1 checklist |

---

## Appendix B: Related Artifacts

| Artifact | Path |
|----------|------|
| Product Brief | `_bmad-output/planning-artifacts/product-brief.md` |
| Brainstorming Session | `_bmad-output/brainstorming/brainstorming-session-2026-05-28-umpire-assignment.md` |
| Council Decisions | `_bmad-output/planning-artifacts/research/council-decisions-2026-05-28.md` |
| Domain Research | `_bmad-output/planning-artifacts/research/domain-umpire-assignment-research-2026-05-28.md` |
| Market Research | `_bmad-output/planning-artifacts/research/market-umpire-assignment-research-2026-05-28.md` |
| Technical Research | `_bmad-output/planning-artifacts/research/technical-umpire-assignment-research-2026-05-28.md` |
| Project Context | `_bmad-output/project-context.md` |

---

*PRD workflow complete. Ready for UX design, architecture, and epic/story breakdown.*
