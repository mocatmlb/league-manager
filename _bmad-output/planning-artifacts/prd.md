---
workflowType: 'prd'
workflow: 'edit'
classification:
  domain: 'sports-league-management'
  projectType: 'web-application'
  complexity: 'medium'
inputDocuments:
  - docs/Features/user-accounts/user-accounts-requirements.md
  - docs/Features/user-accounts/user-accounts-implementation.md
  - _bmad-output/project-context.md
  - docs/requirements.md
  - docs/tech.md
  - docs/architecture.md
stepsCompleted:
  - step-01-init
  - step-e-01-discovery
  - step-e-02-review
  - step-e-03-edit
lastEdited: '2026-05-09'
editHistory:
  - date: '2026-05-02'
    changes: 'Full PRD build-out for post-MVP individual coach login feature'
  - date: '2026-05-03'
    changes: 'Validation fixes: rewrote NFR-SEC as quality attributes; added NFR-ACCESS WCAG 2.1 AA; added UJ-8 admin cutover journey; added FR-USERMGMT-7/8/9 for pre-cutover checklist and shared credential disable; relocated NFR-AVAIL-2/3 content to FRs; refined NFR-AVAIL-1 with measurable availability target'
  - date: '2026-05-03'
    changes: 'Requirement additions and refinements: revised UJ-1 for dual-purpose self-registration (user + team, admin approval for Team Owner); revised UJ-2 to user-only registration; revised UJ-4 to restrict score submission to past/elapsed games; revised UJ-5 to exclude scored/cancelled games from reschedule; revised UJ-7 to clarify toggle scope; added FR-AUTH-7 CAPTCHA on login; added FR-REG-10 CAPTCHA on registration; updated FR-SCORE-3 with time-eligibility constraint; added FR-SCORE-7 game status completed on score; added FR-RESCHED-7 coach can cancel pending requests; added FR-PROFILE section (preferred name, phone management, self-service password change); added FR-COACHSCHEDULE section (team-scoped schedule view with sort/filter); added FR-RESTRICTIONS section (explicit coach permission boundaries)'
  - date: '2026-05-03'
    changes: 'Validation gap fixes: updated FR-REG-3 to include preferred name and phone type fields; added UJ-9 (Coach Manages Profile) and UJ-10 (Coach Views Team Schedule); added FR-TEAMREG section (7 FRs for coach-initiated team registration sub-flow in UJ-1); updated Product Scope in-scope list to reflect all new capabilities; fixed stale NFR-AVAIL-3 reference in Migration Strategy Phase 2 to FR-USERMGMT-7'
  - date: '2026-05-03'
    changes: 'Registration flow and team naming additions: added League field to registration form (FR-REG-11/12); added FR-LEAGUELIST section for admin-managed league dropdown; revised FR-TEAMREG with auto-generated team name rule ({league_name}-{coach_last_name}), no-division-selection constraint, home field location entry (up to 5); added FR-PROFILE-7 (team name read-only for coaches); updated UJ-1 and UJ-2 with league field, team name preview, home field steps; updated Product Scope in-scope list; validation fixes: reordered FR-REG table rows (IDs now flow 3–12); corrected out-of-scope entry for coach team registration; added UJ-11 (Admin Manages League Dropdown List) to complete FR-LEAGUELIST traceability; expanded NFR-COMPAT-1 to include team registration and profile management forms; added NFR-PERF-4 for self-registration form submission and email delivery performance'
  - date: '2026-05-09'
    changes: 'Schedule history gap addressed: added FR-RESCHED-8 (public schedule unchanged until admin approval); added FR-SCHEDHISTORY section (4 FRs for immutable original schedule record, per-approval history entries, and admin game history view); added UJ-12 (Admin Views Game Schedule History); updated Product Scope in-scope list'
---

# Product Requirements Document — District 8 Travel League: Individual Coach Logins

**Author:** Mike
**Date:** 2026-05-02
**Version:** 1.0

---

## Executive Summary

The District 8 Travel League currently operates with a single shared coach password that all coaches use to access protected features (score submission, schedule change requests, contact directory). This creates an accountability gap — there is no way to attribute actions to a specific coach, no mechanism to restrict a coach to their own team's games, and no ability to revoke access for a single coach without affecting all coaches.

This feature replaces the shared coach credential with **individual Team Owner accounts**, each tied to a specific team (or teams). Coaches self-register via a controlled URL or accept an admin-sent invitation, and an administrator assigns them to manage one or more teams. Once assigned, each coach accesses only the features and games relevant to their team.

**Target users:** Team coaches/managers ("Team Owners") in the District 8 Travel League.

**Problem solved:** No individual accountability, no per-team access control, no way to selectively revoke coach access.

**Primary differentiator from MVP state:** Individual identity + team-scoped permissions replace a single shared password.

---

## Success Criteria

All criteria are measurable and must be met before this feature is considered complete.

| # | Criterion | Measurement Method | Target |
|---|-----------|-------------------|--------|
| SC-1 | Each coach has a unique account | DB query: `SELECT COUNT(*) FROM users WHERE role = 'team_owner'` returns ≥ 1 row per active team | 100% of active teams have at least one assigned Team Owner |
| SC-2 | Shared coach password is decommissioned | `coaches_password` setting removed or disabled in `settings` table | 0 active logins via legacy shared credential after cutover |
| SC-3 | Score submission is team-scoped | Coach can submit scores only for games where their team is home or away | 0 unauthorized score submissions attributable to wrong-team access |
| SC-4 | Reschedule requests are team-scoped | Coach can submit reschedule requests only for games involving their assigned team(s) | 0 unauthorized reschedule requests |
| SC-5 | Self-registration toggle functions | Admin can enable/disable open registration; when disabled, only invitation links work | Feature toggle changes effective within 1 page load |
| SC-6 | Admin can assign a coach to a team | Admin UI allows assigning/removing Team Owner role for a user on a specific team | Assignment reflected in coach's accessible games within 60 seconds |
| SC-7 | Coaches can access rules & contact directory | Authenticated Team Owners can view local rules documents and contact list | 0 unauthenticated access to these pages |
| SC-8 | Registration completes end-to-end | User registers, verifies email, admin assigns team, coach logs in and sees their team's games | Full flow under 10 minutes from registration to first login |

---

## Product Scope

### This Feature (Post-MVP Phase 1: Individual Coach Logins)

Replaces the shared coach credential with individual Team Owner accounts. Scoped to the **coach persona only** — this is not a general public user system.

**In scope:**
- Configurable self-registration via a controlled URL (on/off toggle)
- Coach-initiated team registration into an open program/season (via self-registration path; division assigned by admin)
- Admin-managed league dropdown list (short display names used in team name generation)
- Auto-generated team name (`{league_name}-{coach_last_name}`); admin-editable only
- Coach-entered home field locations during registration (up to 5; saved to system field pool)
- Invitation-based registration (admin sends invite link; user account only)
- Individual coach accounts with email verification
- Admin UI to assign/remove coaches from teams
- Team-scoped score submission (home and away games; past/elapsed games only)
- Team-scoped schedule change request submission (public schedule unchanged until admin approves)
- Immutable original schedule record per game; chronological admin-visible game schedule history
- Coach self-service profile management (name, preferred name, phone numbers, password)
- Coach team schedule view (filtered from master schedule, fully sortable and filterable)
- Authenticated access to local rules & regulations documents
- Authenticated access to league contact directory
- Explicit coach permission boundaries (admin-only functions inaccessible to Team Owners)
- Deprecation of shared `coaches_password` credential

**Out of scope (future phases):**
- General public user accounts
- Team Official sub-role (assistants who are not the primary team owner)
- Role change request workflow (user-initiated role escalation)
- Coach-initiated team registration outside of the controlled self-registration flow (UJ-1)
- Audit/activity logging dashboard (logging occurs; UI reporting is future)
- Mobile app; web-responsive only
- **Multi-team assignment** — A coach owning more than one team (FR-ASSIGN-3 deferred; DB enforces 1 user : 1 team this iteration)
- **Multiple owners per team** — More than one Team Owner per team (FR-ASSIGN-4 deferred; same 1:1 constraint)

### Existing MVP Features That Remain Unchanged

- Administrator accounts and admin console
- Public schedule, standings, and home page
- Public document access
- All existing admin CRUD operations

### Migration Scope

The existing shared coach login (`coaches_password` in `settings` table) must be deprecated as part of this feature. The transition period allows both systems to operate in parallel until all active coaches have individual accounts. After cutover, the shared credential is disabled.

---

## User Journeys

### UJ-1: Coach Self-Registers and Registers Their Team via QR Code URL

**Precondition:** Admin has enabled open registration and distributed the QR code URL. At least one program/season has open registration.

1. Coach scans QR code and lands on the registration page at the designated URL.
2. Coach completes the user account form: first name, last name, preferred name (optional), email, primary phone with phone type, secondary phone with phone type (optional), league (selected from admin-managed dropdown; if "Other" is selected, a free-text field is revealed), username, password, password confirmation, and CAPTCHA.
3. System sends a verification email to the provided address.
4. Coach clicks the verification link in the email.
5. User account is created with status `active` and role `user`.
6. System displays available programs and seasons with open registration; coach selects a program and season. Division is not selectable by the coach.
7. System auto-generates a team name as `{league_name}-{coach_last_name}` and displays it to the coach as read-only.
8. Coach optionally adds up to 5 home field locations (location name required; address and additional details optional); each location is saved to the system's available field locations pool.
9. Coach submits the team registration.
10. Coach sees a confirmation screen: "Account created and team registration submitted. An administrator will review your registration and assign you to your team."
11. Admin receives a notification that a new user account is active and a team registration is pending approval.
12. Admin reviews and approves the team registration, assigns a division, and the system assigns the coach as Team Owner for that team.
13. Coach receives an email notification that they have been assigned as Team Owner.

**Exit:** Coach cannot access team-specific features (score submission, reschedule requests) until admin approves the team registration and assigns them as Team Owner.

---

### UJ-2: Admin Sends Invitation for User Registration

**Precondition:** Admin is logged in. Open registration may be on or off.

1. Admin navigates to User Management → Invitations → Send Invitation.
2. Admin enters the coach's email address.
3. System generates a unique registration URL with a 14-day expiration token and sends it to the coach.
4. Coach receives the email, clicks the link, and completes the user account registration form (first name, last name, preferred name (optional), email, primary phone with phone type, secondary phone with phone type (optional), league (dropdown with "Other" option), username, password, password confirmation, and CAPTCHA).
5. Account is created with status `active` and role `user`.
6. Admin receives notification of completed registration.

**Exit:** Invitation-based registration creates a user account only; team assignment is handled separately via UJ-3.

---

### UJ-3: Admin Assigns Coach to a Team

**Precondition:** Coach has a verified, active account. Admin is logged in.

1. Admin navigates to User Management → Users and finds the coach's account.
2. Admin clicks "Assign to Team."
3. Admin selects one or more teams from the list of active-season teams.
4. System assigns the Team Owner role to the user for the selected team(s).
5. Coach's account role is updated to `team_owner` (if not already).
6. Coach receives an email notification: "You have been assigned to manage [Team Name]."
7. On next login, coach sees their team's games in their dashboard.

**Exit:** Coach can now submit scores and reschedule requests for their assigned team(s).

---

### UJ-4: Coach Submits a Game Score

**Precondition:** Coach is logged in with Team Owner role and at least one team assignment.

1. Coach navigates to their dashboard or the Scores section.
2. System displays only games where the coach's assigned team(s) are the home or away team, and where the game date is in the past OR the game date is today and the game time is in the past. Games with a future date or a future time on the current date are not displayed for score entry.
3. Coach selects an eligible game with no score recorded (or selects an existing score for edit via explicit "edit score" action).
4. Coach enters the home team score and away team score.
5. Coach submits. System records the score, marks the game status as `completed`, and updates standings.
6. Confirmation displayed. Admin receives notification of score submission.

**Exit:** Score recorded; game marked completed; standings updated.

---

### UJ-5: Coach Submits a Reschedule Request

**Precondition:** Coach is logged in with Team Owner role and at least one team assignment.

1. Coach navigates to Schedule or their dashboard.
2. System displays only games where the coach's assigned team(s) are involved, excluding games that already have a score reported or have been marked `cancelled` by an administrator.
3. Coach selects an eligible game and clicks "Request Reschedule."
4. Coach enters the requested new date/time and reason.
5. Coach submits. System creates a pending reschedule request.
6. Admin receives notification. Coach sees request status as "Pending."
7. If the coach wishes to withdraw the request before admin action, they can cancel it while status is `pending` (see FR-RESCHED-7).

**Exit:** Reschedule request queued for admin review, or withdrawn by coach if still pending.

---

### UJ-6: Coach Views Rules & Contact Directory

**Precondition:** Coach is logged in (any active authenticated user).

1. Coach navigates to "League Resources" or equivalent section.
2. System displays links to uploaded rules and regulation documents (managed by admin via Document Management).
3. Coach clicks a document link and views or downloads it.
4. Coach navigates to "Contact Directory."
5. System displays the league contact list (coaches, admins, officials as configured).

**Exit:** Coach has accessed the information they need.

---

### UJ-7: Admin Enables/Disables Open Self-Registration

**Precondition:** Admin is logged in.

1. Admin navigates to Settings → Registration.
2. Admin toggles "Open Self-Registration" on or off.
3. When **on**: the designated QR code registration URL (UJ-1) is active; a QR code link is displayed for the admin to share.
4. When **off**: the QR code registration URL returns a "Registration is currently closed" message; the self-registration + team registration flow (UJ-1) is disabled.
5. Change takes effect immediately on save.
6. This toggle does not affect invitation-based registration (UJ-2); admin-sent invitation links remain functional regardless of this setting.

**Exit:** Open self-registration access matches the admin's intended state; invitation-based registration is unaffected.

---

### UJ-8: Admin Disables Shared Credential and Completes Cutover

**Precondition:** All active-season teams have at least one assigned Team Owner. Admin is logged in.

1. Admin navigates to Settings → Migration or equivalent cutover panel.
2. System displays the pre-cutover checklist; all teams show at least one assigned Team Owner (checklist reads zero gaps).
3. Admin clicks "Disable Shared Coach Login."
4. System presents a confirmation dialog: "This will permanently disable the shared coach password. All coaches must use their individual accounts. This cannot be automatically undone."
5. Admin confirms.
6. System disables the `coaches_password` credential; any login attempt using the shared password returns a "Coach login has been updated — please use your individual account" message.
7. Admin sees a success confirmation and a note that the setting can be re-enabled manually during the rollback window.

**Exit:** Shared credential is disabled; all coach access is exclusively through individual accounts.

---

### UJ-9: Coach Manages Their Profile

**Precondition:** Coach is logged in with an active account.

1. Coach navigates to their profile or account settings page.
2. Coach updates name fields: first name, last name, and/or preferred name.
3. Coach submits name changes; system saves and confirms update.
4. Coach adds or updates their primary phone number, selecting a phone type (Home, Work, or Cell).
5. Coach optionally adds or updates a secondary phone number with phone type, or removes an existing secondary phone number.
6. Coach saves phone changes; system confirms.
7. To change password, coach clicks "Change Password," enters their current password, then enters and confirms the new password.
8. System validates current password, validates new password complexity (FR-REG-5), and saves; coach sees a confirmation.

**Exit:** Profile reflects the coach's updated information; password change (if made) takes effect on the current session.

---

### UJ-10: Coach Views Their Team's Schedule

**Precondition:** Coach is logged in with Team Owner role and at least one team assignment.

1. Coach navigates to the Schedule section or their dashboard.
2. System displays a schedule table filtered to all games involving the coach's assigned team(s), regardless of game status.
3. Table columns displayed: Game Number, Date, Time, Away Team, Home Team, Location, Score.
4. Coach clicks any column header to sort ascending; clicks again to sort descending.
5. Coach uses column filter inputs to filter by any combination of columns (e.g., filter Date to a specific date range, or filter by team name).
6. Coach can clear filters to return to the full team schedule view.

**Exit:** Coach has reviewed the schedule for their assigned team(s) with the desired sort and filter applied.

---

### UJ-11: Admin Manages the League Dropdown List

**Precondition:** Admin is logged in.

1. Admin navigates to Settings → Leagues (or equivalent admin configuration panel).
2. System displays the current league list: all active entries in display order, with inactive entries shown below with a visual indicator.
3. Admin clicks "Add League" and enters a short display name (e.g., "Springfield"); confirms.
4. New entry appears at the bottom of the active list.
5. Admin reorders entries by dragging or using up/down controls; order is saved on confirmation.
6. To edit an entry, admin clicks the entry name, updates the display name, and saves.
7. To deactivate an entry, admin clicks "Deactivate" on the entry; system confirms the action. The entry is removed from the registration dropdown but retained in the system for historical reference.
8. Admin can reactivate a previously deactivated entry; it returns to the active list at the bottom by default.
9. Admin verifies the dropdown preview to confirm the list order and the presence of the static "Other" option at the end.

**Exit:** The league dropdown on the registration form reflects the active entries in the configured display order; deactivated entries no longer appear in the dropdown.

---

### UJ-12: Admin Views Game Schedule History

**Precondition:** Admin is logged in. At least one game exists in the system.

1. Admin navigates to a game's detail or management view in the admin console.
2. System displays the game's complete chronological schedule history, from oldest to newest:
   - **Original schedule entry:** the date, time, and location the game was first created with.
   - **For each approved reschedule:** the previous date/time/location, the new date/time/location, the reason provided by the coach, the admin who approved it, and the approval timestamp.
3. Pending reschedule requests are also visible in the history panel with their current status (Pending / Approved / Denied), requested date/time, and reason — giving the admin full context before acting.
4. Admin can see at a glance how many times a game has been rescheduled and what the original schedule was, regardless of how many iterations have occurred.

**Exit:** Admin has a complete audit trail for the game's scheduling lifecycle.

---

## Functional Requirements

### FR-AUTH: Authentication

| ID | Requirement |
|----|-------------|
| FR-AUTH-1 | Users can log in with username or email + password via the coach login page |
| FR-AUTH-2 | Sessions expire after 60 minutes of inactivity |
| FR-AUTH-3 | Users can reset their password via a time-limited (24-hour) email link |
| FR-AUTH-4 | Accounts are locked for 15 minutes after 5 consecutive failed login attempts |
| FR-AUTH-5 | "Remember me" option extends session to 30 days via secure persistent cookie |
| FR-AUTH-6 | Logout invalidates the session immediately |
| FR-AUTH-7 | The login page includes a CAPTCHA or equivalent automated bot-detection challenge that activates after 3 consecutive failed login attempts from the same IP address; challenge must be passed before further login attempts are accepted |

---

### FR-REG: Registration

| ID | Requirement |
|----|-------------|
| FR-REG-1 | When open registration is **enabled**, any user can access the registration URL and create an account |
| FR-REG-2 | When open registration is **disabled**, the registration URL returns a closed message; only invitation token URLs produce the registration form |
| FR-REG-3 | Registration form collects: first name, last name, preferred name (optional), email, primary phone with phone type (Home / Work / Cell), secondary phone with phone type (optional), league (dropdown — see FR-LEAGUELIST), username, password, confirm password |
| FR-REG-4 | Usernames must be unique across all accounts; duplicate submissions return a field-level error |
| FR-REG-5 | Passwords must be at least 8 characters and include at least one uppercase letter, one number, and one special character |
| FR-REG-6 | System sends a verification email upon successful form submission; account status is `unverified` until the link is clicked |
| FR-REG-7 | Verification links expire after 48 hours; expired links display an option to resend verification |
| FR-REG-8 | Newly verified accounts are assigned `user` role; Team Owner role is granted only by admin |
| FR-REG-9 | Admin receives an email notification when a new account completes email verification |
| FR-REG-10 | The registration form includes a CAPTCHA or equivalent automated bot-detection challenge; submissions that fail the challenge are rejected before account creation |
| FR-REG-11 | The League field on the registration form is a dropdown populated from the admin-managed league list (FR-LEAGUELIST); the dropdown includes an "Other" option that, when selected, reveals a free-text field for the coach to enter their league name manually |
| FR-REG-12 | The League field is required; registration cannot be submitted without a league selection or a manually entered value when "Other" is selected |

---

### FR-LEAGUELIST: League List Management (Admin)

| ID | Requirement |
|----|-------------|
| FR-LEAGUELIST-1 | Admins can create, edit, and deactivate entries in the league dropdown list used on the registration form |
| FR-LEAGUELIST-2 | Each league list entry is a short display name (e.g., "Springfield") used as-is in team name generation and in the registration form dropdown |
| FR-LEAGUELIST-3 | Admins can reorder league list entries to control the display order in the dropdown |
| FR-LEAGUELIST-4 | Deactivated league entries no longer appear in the registration dropdown but remain in the system for historical reference and existing team records |
| FR-LEAGUELIST-5 | The registration form dropdown always includes an "Other" option as the last entry regardless of admin configuration |

---

### FR-INV: Invitation System

| ID | Requirement |
|----|-------------|
| FR-INV-1 | Admins can send a registration invitation to any email address via the admin User Management panel |
| FR-INV-2 | Invitation emails contain a unique, single-use registration URL that expires after 14 days |
| FR-INV-3 | Sending a second invitation to an email that has a pending invitation cancels the prior token and issues a new one |
| FR-INV-4 | Admins can view all pending invitations with status (pending, completed, expired) and resend or cancel them |
| FR-INV-5 | Completed invitation tokens cannot be reused |

---

### FR-TOGGLE: Registration Toggle

| ID | Requirement |
|----|-------------|
| FR-TOGGLE-1 | Admins can enable or disable open self-registration from the Settings panel |
| FR-TOGGLE-2 | When enabled, the admin Settings panel displays the registration URL and a QR code image for that URL |
| FR-TOGGLE-3 | Toggle change takes effect within one page load with no server restart required |
| FR-TOGGLE-4 | Disabling open registration does not affect active invitation links already in circulation |

---

### FR-TEAMREG: Coach-Initiated Team Registration (Self-Registration Path)

| ID | Requirement |
|----|-------------|
| FR-TEAMREG-1 | After completing email verification during self-registration (UJ-1), the coach is presented with a list of programs and seasons that currently have open registration; only seasons within their active registration period are displayed |
| FR-TEAMREG-2 | The coach selects a program and season from the available open-registration options; division is not selectable by the coach and is assigned by an admin after approval |
| FR-TEAMREG-3 | The team name is auto-generated by the system at submission as `{league_name}-{coach_last_name}` using the league value from the coach's registration and the coach's last name; this field is displayed to the coach as read-only during registration and is not editable by the coach |
| FR-TEAMREG-4 | If the coach selected "Other" for their league, the manually entered league value is used in place of `{league_name}` in the auto-generated team name |
| FR-TEAMREG-5 | During team registration, the coach can add up to 5 home field locations; each location entry is saved to the system's available field locations pool for use in game scheduling |
| FR-TEAMREG-6 | Home field location entry requires at minimum a location name; additional details (address, GPS coordinates, notes) are optional at registration time |
| FR-TEAMREG-7 | Submitted team registrations are created in a `pending` status and appear in the admin dashboard as items requiring review |
| FR-TEAMREG-8 | Admin receives a notification when a new team registration is submitted via the self-registration path |
| FR-TEAMREG-9 | Upon admin approval of a team registration, the system assigns the submitting coach as Team Owner for that team (equivalent to FR-ASSIGN-1/2) |
| FR-TEAMREG-10 | The coach receives an email notification when their team registration is approved and they have been assigned as Team Owner |
| FR-TEAMREG-11 | A coach who registered via invitation (UJ-2) cannot submit a team registration through the self-registration path; team assignment for invitation-registered coaches is handled exclusively via UJ-3 |
| FR-TEAMREG-12 | Only an admin can edit a team name after it has been auto-generated; the coach has no ability to modify the team name through any coach-accessible interface |

---

### FR-ASSIGN: Team Assignment (Admin)

| ID | Requirement |
|----|-------------|
| FR-ASSIGN-1 | Admins can assign one or more teams to a user account from the User Detail page |
| FR-ASSIGN-2 | Assigning the first team to a user elevates their role to `team_owner` automatically |
| FR-ASSIGN-3 | ~~Admins can assign multiple teams to the same user~~ — **Deferred to future phase.** This iteration enforces 1:1 user-to-team via `UNIQUE(user_id)` on `team_owners`. Multi-team ownership is architecturally supported in the query layer but the DB constraint prevents it. |
| FR-ASSIGN-4 | ~~Multiple Team Owners can be assigned to the same team~~ — **Deferred to future phase.** Same constraint as FR-ASSIGN-3; a single coach per team is enforced this iteration. |
| FR-ASSIGN-5 | Admins can remove a team assignment from a user; if the user has no remaining teams, their role reverts to `user` |
| FR-ASSIGN-6 | Coach receives an email notification when a team is assigned or removed |
| FR-ASSIGN-7 | Admin can view all team assignments for a user and all users assigned to a specific team |

---

### FR-SCORE: Score Submission (Team-Scoped)

| ID | Requirement |
|----|-------------|
| FR-SCORE-1 | Team Owners can submit scores for games where their assigned team is the home team |
| FR-SCORE-2 | Team Owners can submit scores for games where their assigned team is the away team |
| FR-SCORE-3 | The score submission interface displays only games belonging to the coach's assigned team(s) where the game date is in the past, or the game date is the current date and the game time is in the past; games with a future date or a future time on the current date are excluded |
| FR-SCORE-4 | Score submission for a game that already has a recorded score requires an explicit "edit score" action (not the default submit flow) |
| FR-SCORE-5 | Submitted scores update standings immediately upon save |
| FR-SCORE-6 | Admin receives a notification for each score submission by a Team Owner |
| FR-SCORE-7 | Upon successful score submission, the game status is set to `completed` |

---

### FR-RESCHED: Reschedule Requests (Team-Scoped)

| ID | Requirement |
|----|-------------|
| FR-RESCHED-1 | Team Owners can submit reschedule requests for games where their assigned team is the home or away team |
| FR-RESCHED-2 | The reschedule request interface displays only games belonging to the coach's assigned team(s) |
| FR-RESCHED-3 | Reschedule requests require a proposed new date/time and a reason (both required) |
| FR-RESCHED-4 | Submitted requests appear in the admin dashboard as pending items for review |
| FR-RESCHED-5 | Coaches can view the status of their own submitted reschedule requests (Pending / Approved / Denied) |
| FR-RESCHED-6 | Admin receives a notification for each reschedule request submitted by a Team Owner |
| FR-RESCHED-7 | Coaches can cancel a reschedule request they submitted only while the request status is `pending`; requests with status `approved` or `denied` cannot be cancelled by the coach |
| FR-RESCHED-8 | The public-facing schedule is not updated with a new date, time, or location until an admin explicitly approves the reschedule request; the original scheduled date/time/location remains on the public schedule while a request is `pending` |

---

### FR-SCHEDHISTORY: Game Schedule History (Admin)

| ID | Requirement |
|----|-------------|
| FR-SCHEDHISTORY-1 | When a game is first scheduled, the system records the original date, time, and location as an immutable history entry so the original schedule is never lost regardless of subsequent changes |
| FR-SCHEDHISTORY-2 | When an admin approves a reschedule request, the system creates a history entry capturing: the previous date/time/location, the new date/time/location, the reason provided by the coach, the approving admin's identity, and the approval timestamp |
| FR-SCHEDHISTORY-3 | Admins can view the complete chronological schedule history for any game, from original schedule through all approved reschedules, in a dedicated history panel on the game detail or management screen |
| FR-SCHEDHISTORY-4 | The game history panel also displays all reschedule requests (pending, approved, and denied) with their status, requested date/time, coach-provided reason, and submission timestamp — giving admins full context for pending decisions |

---

### FR-RESOURCES: Authenticated Coach Resources

| ID | Requirement |
|----|-------------|
| FR-RESOURCES-1 | Any authenticated user (role ≥ `user`) can access the local rules and regulations documents section |
| FR-RESOURCES-2 | The rules section displays documents uploaded by admin via the existing Document Management feature |
| FR-RESOURCES-3 | Any authenticated user (role ≥ `user`) can access the league contact directory |
| FR-RESOURCES-4 | Unauthenticated requests to the rules or contact directory pages redirect to the login page |

---

### FR-USERMGMT: User Management (Admin)

| ID | Requirement |
|----|-------------|
| FR-USERMGMT-1 | Admins can view a filterable, searchable, paginated list of all user accounts |
| FR-USERMGMT-2 | Admins can edit any user's profile fields (name, email, phone, username) |
| FR-USERMGMT-3 | Admins can change a user's role (user, team_owner, administrator) |
| FR-USERMGMT-4 | Admins can disable or re-enable a user account; disabled accounts cannot log in |
| FR-USERMGMT-5 | Admins can reset a user's password, generating a temporary password and forcing a change on next login |
| FR-USERMGMT-6 | Admins can delete a user account with a confirmation step; associated team assignments are removed |
| FR-USERMGMT-7 | Admins can view a pre-cutover checklist showing all active-season teams that have zero assigned Team Owners, allowing gap identification before disabling the shared credential |
| FR-USERMGMT-8 | Admins can disable the legacy shared coach credential from the Settings panel; once disabled, the shared credential no longer grants access to any protected page |
| FR-USERMGMT-9 | The shared coach credential disable action requires an explicit confirmation step and is only available when the pre-cutover checklist shows zero teams with no assigned Team Owner |

---

### FR-PROFILE: Coach Self-Service Profile Management

| ID | Requirement |
|----|-------------|
| FR-PROFILE-1 | Authenticated users can update their first name, last name, and preferred name from their profile page |
| FR-PROFILE-2 | Authenticated users can add or update a primary phone number on their profile; a phone type (Home, Work, or Cell) is required for each number |
| FR-PROFILE-3 | Authenticated users can add or update a secondary phone number on their profile; a phone type (Home, Work, or Cell) is required for each number |
| FR-PROFILE-4 | Authenticated users can remove a secondary phone number from their profile; the primary phone number cannot be removed |
| FR-PROFILE-5 | Authenticated users can change their own password from their profile page while logged in; the current password must be provided before a new password is accepted |
| FR-PROFILE-6 | New passwords set via FR-PROFILE-5 must meet the same complexity requirements as FR-REG-5 |
| FR-PROFILE-7 | Team name is not editable by the coach through any profile or self-service interface; the team name field is read-only for all coach roles and is editable only by admins (FR-TEAMREG-12) |

---

### FR-COACHSCHEDULE: Coach Team Schedule View

| ID | Requirement |
|----|-------------|
| FR-COACHSCHEDULE-1 | Authenticated Team Owners can view a schedule filtered to games involving their assigned team(s) |
| FR-COACHSCHEDULE-2 | The schedule view displays the following columns for each game: Game Number, Date, Time, Away Team, Home Team, Location, Score |
| FR-COACHSCHEDULE-3 | All columns in the schedule view are independently sortable (ascending and descending) |
| FR-COACHSCHEDULE-4 | All columns in the schedule view are independently filterable; text columns support search input; the Date column supports date-range filtering |
| FR-COACHSCHEDULE-5 | The schedule view displays all games for the coach's assigned team(s) regardless of game status (scheduled, completed, cancelled) |
| FR-COACHSCHEDULE-6 | The schedule view presentation follows the same column structure and interaction patterns as the existing master public schedule |

---

### FR-RESTRICTIONS: Coach Permission Boundaries

| ID | Requirement |
|----|-------------|
| FR-RESTRICTIONS-1 | Team Owners cannot terminate, close, or change the status of a season; season lifecycle management is admin-only |
| FR-RESTRICTIONS-2 | Team Owners cannot change the division or program assignment for any team, including their own assigned team(s); division and program assignment is admin-only |
| FR-RESTRICTIONS-3 | Team Owners cannot change game status directly; game status transitions (including marking a game cancelled) are admin-only. The sole exception is that submitting a score via the score submission flow sets game status to `completed` (FR-SCORE-7) |
| FR-RESTRICTIONS-4 | Team Owners cannot submit a score for a game that does not involve their assigned team(s); the system rejects any such submission server-side regardless of client input |
| FR-RESTRICTIONS-5 | Team Owners cannot submit a score for a game whose date is in the future, or whose date is the current date and whose scheduled time is in the future |
| FR-RESTRICTIONS-6 | Team Owners cannot view, edit, or access profile information, account settings, or contact details of other coaches, other teams, administrators, or league officials |
| FR-RESTRICTIONS-7 | Team Owners cannot perform any admin-only function (including but not limited to: user management, team/division/program management, season management, game creation/editing, schedule management, document management, and system settings) |

---

## Non-Functional Requirements

### NFR-SEC: Security

| ID | Requirement |
|----|-------------|
| NFR-SEC-1 | User credentials are stored such that plaintext passwords cannot be recovered from the database under any circumstance, verified by security review prior to launch |
| NFR-SEC-2 | All state-changing operations (form submissions, role changes, team assignments) are protected against cross-site request forgery; forged requests are rejected with no state change |
| NFR-SEC-3 | All user-supplied input is parameterized before database execution; SQL injection attacks produce no unauthorized data access or modification |
| NFR-SEC-4 | Session tokens are transmitted and stored in a manner that prevents access by client-side scripts and transmission over unencrypted connections |
| NFR-SEC-5 | A user's session token changes on login, logout, and privilege level change, preventing session fixation and privilege escalation via stolen pre-auth tokens |
| NFR-SEC-6 | The open self-registration URL is not discoverable through public site navigation, search engine indexing, or automated scanning; access is limited to holders of the distributed QR code |

---

### NFR-PERF: Performance

| ID | Requirement |
|----|-------------|
| NFR-PERF-1 | Login page responds in under 2 seconds at 95th percentile under normal load on shared hosting |
| NFR-PERF-2 | Score submission completes (form submit → confirmation page) in under 3 seconds at 95th percentile |
| NFR-PERF-3 | Coach dashboard (game list for assigned teams) loads in under 3 seconds for a coach assigned to up to 3 teams |
| NFR-PERF-4 | Self-registration form submission (user account creation trigger) completes in under 3 seconds at 95th percentile; verification email is delivered within 5 minutes under normal mail server conditions |

---

### NFR-COMPAT: Compatibility

| ID | Requirement |
|----|-------------|
| NFR-COMPAT-1 | All registration, login, score submission, reschedule request, team registration, and profile management forms are fully functional on mobile viewports (≥ 375px width) |
| NFR-COMPAT-2 | UI renders correctly in current versions of Chrome, Firefox, and Safari |
| NFR-COMPAT-3 | Application runs on PHP 8.1 (`ea-php81`) on cPanel shared hosting without additional server configuration |

---

### NFR-ACCESS: Accessibility

| ID | Requirement |
|----|-------------|
| NFR-ACCESS-1 | All authentication, registration, and data-entry pages (login, registration, score submission, reschedule request) meet WCAG 2.1 Level AA standards for keyboard navigation, screen reader compatibility, and a minimum 4.5:1 color contrast ratio, verified by automated accessibility scanning tool prior to launch |

---

### NFR-AVAIL: Availability & Migration

| ID | Requirement |
|----|-------------|
| NFR-AVAIL-1 | During the transition period, both the new individual login system and the existing shared coach login remain fully operational in parallel; a failure in either system does not degrade the other, maintaining 100% login availability for all current users throughout the transition |

---

## Migration Strategy

### Current State

The MVP uses a single `coaches_password` value stored in the `settings` table. All coaches share this password to access score submission, reschedule requests, and the contact directory. No individual identity is tracked.

### Transition Approach

**Phase 1 — Parallel operation:**
- New individual login system is deployed alongside the existing shared credential.
- Coaches who have registered individually use the new login; coaches who have not yet registered continue using the shared password.
- Admin dashboard shows a banner: "X teams have no assigned Team Owner — shared login still active."

**Phase 2 — Coach onboarding:**
- Admin uses the invitation system or enables open registration to onboard all active team coaches.
- Admin assigns each registered coach to their team(s).
- Admin monitors the gap checklist (FR-USERMGMT-7) until it reads zero unassigned teams.

**Phase 3 — Cutover:**
- Admin disables open registration (if it was on).
- Admin disables the shared `coaches_password` credential via the Settings panel.
- All coach access is now exclusively through individual accounts.
- Shared credential setting remains in the database in a disabled state for rollback capability during a defined rollback window (suggested: 30 days).

**Phase 4 — Cleanup (future):**
- After the rollback window closes, the `coaches_password` setting and legacy auth path can be removed in a subsequent maintenance release.

### Data Migration

No existing coach data needs to be migrated. The current system holds no individual coach records — only a shared password. All Team Owner accounts are net-new records created during Phase 2 onboarding.

---
