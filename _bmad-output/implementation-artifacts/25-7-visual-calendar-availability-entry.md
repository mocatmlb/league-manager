# Story 25.7: Visual Calendar Availability Entry

Status: done

<!-- Note: Validation is optional. Run validate-create-story for quality check before dev-story. -->

## Story

As an umpire,
I want a visual calendar where I can quickly select the days I am available and optionally set specific hours,
so that I can maintain my availability with less date/time entry work and still give the assignor accurate availability windows.

## Acceptance Criteria

1. Given I have the `umpire` role and am authenticated, when I navigate to My Availability, then I can use a visual calendar to select one or more dates as available.
2. Given I select a date without changing the time controls, when I save, then the system creates an all-day availability window for that local calendar date using the existing `umpire_availability_windows` table and existing pool-query behavior.
3. Given I need partial-day availability, when I override the default all-day option for a selected date, then I can enter specific start and end times and the stored window reflects those hours.
4. Given I have existing availability windows, when the calendar loads, then those windows are visible on the calendar and can still be edited or removed without losing the existing table/list management path.
5. Given I select multiple dates and save them as all-day or same-hours windows, when the save completes, then each date is stored as its own availability window scoped to my user record.
6. Given I enter invalid hours, reversed times, missing dates, or attempt to mutate another umpire's window, when I submit, then the system rejects the change server-side and does not create, update, or delete unrelated availability records.
7. Given I am unauthenticated or have a non-umpire role, when I attempt to access the availability calendar, then I am denied access or redirected to login.

## Tasks / Subtasks

- [x] Extend the existing availability service without changing the data model (AC: 2, 3, 5, 6)
  - [x] Reuse `includes/UmpireAvailabilityService.php`; do not create a second availability service or a new table.
  - [x] Add a narrow batch-create helper: `createWindowsForDates(int $umpireUserId, string[] $dates, ?string $startTime, ?string $endTime, ?string $notes = null): array{created: int[], skipped: string[], errors: string[]}`. `$dates` is an array of `YYYY-MM-DD` strings. Returns: `created` = new availability_ids, `skipped` = dates where an identical window already existed, `errors` = dates that failed validation.
  - [x] **CRITICAL — Date conversion:** The existing `validateWindow()` only parses `'Y-m-d H:i:s'` and `'Y-m-d H:i'` — a raw `YYYY-MM-DD` date string will throw `"must be a valid date and time"`. Convert each selected date **before** calling `createWindow()`: all-day → `$startsAt = "$date 00:00:00"`, `$endsAt = date('Y-m-d', strtotime($date . ' +1 day')) . ' 00:00:00'`. Partial-day → `"$date $startTime:00"` and `"$date $endTime:00"`.
  - [x] **Duplicate-date behavior (decided):** If a window already exists for the umpire where `starts_at = YYYY-MM-DD 00:00:00` AND `ends_at = next-day 00:00:00` (all-day), skip that date without error and add it to the `skipped` array. Do not block the entire batch for a duplicate — create valid non-duplicate dates and report which were skipped. Surface skipped dates to the user via the AJAX response (`skipped` array).
  - [x] Cap batch size at 62 dates; reject with `InvalidArgumentException("Batch cannot exceed 62 dates.")` if exceeded.
  - [x] Keep the stable stored shape as `starts_at` and `ends_at` DATETIME rows in `umpire_availability_windows`.
  - [x] Validate selected dates as real local dates, times as `HH:MM`, and require start time before end time for partial-day windows.
  - [x] Preserve ownership checks for update/delete. Never trust `availability_id` without `umpire_user_id`.
- [x] Replace the primary My Availability entry UI with a visual calendar workflow (AC: 1, 2, 3, 4, 5)
  - [x] Update `public/umpires/availability.php`; do not create a new route unless there is a compelling routing reason.
  - [x] Put the calendar-based selector in the primary first-screen workflow. The existing current-windows table/list can remain below it for precise review and edit/delete controls.
  - [x] **Multi-date selection (FullCalendar v5 API):** FullCalendar v5 `selectable: true` selects date ranges by drag — it does NOT support tapping individual non-contiguous dates. Use `dateClick` callback instead: maintain a JS `selectedDates` array (Set of YYYY-MM-DD strings), toggle a date in/out on each click, and apply a CSS highlight class (e.g. `.fc-day-selected`) to visually mark selected cells via `dayCellDidMount` or `dayCellContent`. Do NOT use `selectable: true` or `select` callback for this feature.
  - [x] Default selected dates to all-day availability. Provide an explicit all-day toggle or equivalent control that reveals start/end time inputs only when disabled.
  - [x] Support applying the same specific hours to every selected date in a batch save.
  - [x] **Batch save uses AJAX POST** (see Batch POST Contract in Dev Notes). On success, refresh the existing-windows table and calendar events without a full page reload. On partial success (some skipped), show the skipped dates in a dismissible alert. On error, show the error message without clearing selected dates so the user can retry.
  - [x] **Render existing windows on the calendar:** PHP encodes windows as a JSON events array inline (same pattern as `public/schedule.php`). All-day windows → `{start: "YYYY-MM-DD", end: "YYYY-MM-DD+1", allDay: true, title: "Available (all day)", backgroundColor: "#198754", borderColor: "#198754", extendedProps: {availabilityId: N}}`. Partial-day → `{start: "YYYY-MM-DD HH:mm:ss", end: "YYYY-MM-DD HH:mm:ss", allDay: false, title: "Available HH:mm–HH:mm", backgroundColor: "#0d6efd", borderColor: "#0d6efd", extendedProps: {availabilityId: N}}`. Server-side: a window is all-day if `starts_at` time component is `00:00:00` AND `ends_at` equals the next calendar day at `00:00:00`.
  - [x] **eventClick handler:** clicking an existing-window calendar event should open the existing edit modal for that window (same edit modal already on the page, triggered by `availability_id`).
  - [x] Keep Bootstrap 5 controls, Font Awesome icons, responsive mobile behavior, and 44x44px minimum touch targets for calendar actions.
- [x] Integrate calendar assets conservatively (AC: 1, 4)
  - [x] Use FullCalendar `5.11.3` — the exact same version already loaded by `public/schedule.php`. Use these CDN lines in `<head>` (before Bootstrap JS):
    ```html
    <link href='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css' rel='stylesheet' />
    ```
    And before `</body>` (after Bootstrap JS):
    ```html
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js'></script>
    ```
    Do NOT load v6 or v7 — their APIs differ and will silently fail against the v5 CDN build.
  - [x] Do not add a Node build step, Composer dependency, paid service, or background worker.
  - [x] Make the page work if calendar JavaScript fails by preserving server-rendered existing windows and a non-calendar form fallback or clear error state. The existing table/list below the calendar provides this fallback naturally — ensure the Add Window modal (or a plain HTML form) remains functional when JS is unavailable.
- [x] Preserve availability-pool and adjacent-story boundaries (AC: 2, 4, 5)
  - [x] Do not alter `getAvailableUmpireIdsForWindow()` semantics except for tests directly required by this UI.
  - [x] Do not change assignment drawer defaulting, standalone assignor availability query behavior, or third-party game behavior; those remain Stories 25.2, 25.3, 25.4, and 25.5.
  - [x] Do not rewrite existing availability rows when adding new selected dates. Create/update only the rows targeted by the request.
  - [x] Duplicate-date behavior is decided: skip identical all-day windows (same `starts_at`/`ends_at` date) and include them in the `skipped` response array. See the service task above for implementation detail.
- [x] Add targeted tests and verification (AC: 1, 2, 3, 5, 6, 7)
  - [x] Extend `tests/unit/UmpireAvailabilityServiceTest.php`:
    - All-day date conversion: `createWindowsForDates(123, ['2026-07-10'], null, null)` stores `starts_at = '2026-07-10 00:00:00'`, `ends_at = '2026-07-11 00:00:00'`.
    - Partial-day conversion: `createWindowsForDates(123, ['2026-07-10'], '09:00', '17:00')` stores `starts_at = '2026-07-10 09:00:00'`, `ends_at = '2026-07-10 17:00:00'`.
    - Duplicate skip: if `queryRows` returns an existing window for the date, the date appears in `skipped`, `created` is empty, no `db->insert` called.
    - Batch cap: array of 63 dates throws `InvalidArgumentException("Batch cannot exceed 62 dates.")`.
    - Reversed partial-day times throw `InvalidArgumentException`.
    - Ownership scoping is unchanged for `updateWindow` / `deleteWindow`.
  - [x] Add a lightweight page/static assertion that `public/umpires/availability.php` exposes the calendar container (`availabilityCalendarEl`), all-day toggle control, `batch_create` action handler, CSRF token, and existing update/delete actions (regression: `$action === 'update'` must still be present).
  - [x] Run `php -l includes/UmpireAvailabilityService.php` and `php -l public/umpires/availability.php`.
  - [x] Run `php tests/unit/run-unit-tests.php --file=UmpireAvailabilityServiceTest.php`.
  - [x] Manually verify mobile and desktop My Availability: select one all-day date, select multiple all-day dates, save partial-day hours, reject reversed hours, edit an existing window, delete an existing window, confirm skipped-date alert when a duplicate is selected, and confirm unauthorized roles cannot access the page.

## Dev Notes

This is a UX follow-on to Story 25.1. The live system already has the availability table, service, portal route, nav entry, and tests. The goal is to make entry fast and visual, not to redesign the underlying availability model.

### Current System Context

- The current route is `public/umpires/availability.php`. It is protected with `PermissionGuard::requireRole('umpire', '/login.php')` and reads the authenticated umpire id from `$_SESSION['coach_user_id']`.
- The current page supports create/update/delete through POST actions with `Auth::verifyCSRFToken()`. It normalizes `datetime-local` values by replacing `T` with a space before calling `UmpireAvailabilityService`.
- Existing windows render in chronological order with edit and delete controls. Preserve that management path even if the calendar becomes the primary entry surface.
- `includes/UmpireAvailabilityService.php` already exposes `listForUmpire()`, `createWindow()`, `updateWindow()`, `deleteWindow()`, and `getAvailableUmpireIdsForWindow()`.
- The pool contract is `int[]` of `umpire_user_id` values. It uses covering availability windows and excludes overlapping Draft/Published assignments using `TIMESTAMP(s.game_date, COALESCE(s.game_time, '00:00:00'))`.

### Data Model Guardrails

- No migration should be required. Reuse `umpire_availability_windows` from migration `051_create_umpire_availability_windows.sql`.
- Availability identity remains `users.id` in `umpire_availability_windows.umpire_user_id`; do not point this at `umpire_profiles.id`.
- Store all-day selections as normal DATETIME windows. Recommended mapping: selected local date `2026-07-10` stores `starts_at = 2026-07-10 00:00:00` and `ends_at = 2026-07-11 00:00:00`.
- Partial-day selections should store the selected local date plus submitted `HH:MM` times. Reject same-time and reversed windows.
- Do not split or delete existing windows automatically unless the user explicitly edits or deletes that window.
- Keep ActivityLogger payloads id-based and avoid logging free-form notes text.

### Batch POST Contract

Save is an **AJAX POST** to the same page (`/umpires/availability.php`). Use `fetch()` with `Content-Type: application/x-www-form-urlencoded` or `FormData`. Required fields:

| Field | Type | Notes |
|---|---|---|
| `action` | string | `"batch_create"` |
| `csrf_token` | string | From `Auth::generateCSRFToken()` |
| `dates[]` | string[] | YYYY-MM-DD strings, one per selected day |
| `is_all_day` | int | `1` = all-day, `0` = partial-day |
| `start_time` | string | HH:MM, required when `is_all_day=0` |
| `end_time` | string | HH:MM, required when `is_all_day=0` |
| `notes` | string | Optional |

JSON response shape:
```json
{
  "success": true,
  "created": [5001, 5002],
  "skipped": ["2026-07-10"],
  "errors": []
}
```
On hard failure (CSRF, auth, all dates rejected): `{"success": false, "message": "..."}`.

PHP response: output `Content-Type: application/json`, then `json_encode(...)`, then `exit`. Handle the `batch_create` action in the existing POST block before any HTML output.

After a successful AJAX response: refresh the existing-windows table (simplest: `window.location.reload()` or re-fetch via a second AJAX call to load updated windows HTML). If `skipped` is non-empty, show a dismissible alert listing the skipped dates before reloading.

### All-Day Detection Helper

Server-side helper to classify existing windows for calendar rendering:

```php
function isAllDayWindow(string $startsAt, string $endsAt): bool {
    $start = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $startsAt);
    $end   = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $endsAt);
    if (!$start || !$end) return false;
    $expectedEnd = $start->modify('+1 day')->setTime(0, 0, 0);
    return $start->format('H:i:s') === '00:00:00' && $end == $expectedEnd;
}
```

Use this to set `allDay: true` and pick the green color (`#198754`) for all-day vs blue (`#0d6efd`) for partial-day in the events array passed to FullCalendar.

### FullCalendar v5 Initialization

Follow the schedule.php pattern (`public/schedule.php` lines 840–870). For this page, the relevant options are:

```javascript
var availabilityEvents = <?php echo json_encode($calendarEvents); ?>;
var selectedDates = new Set(); // YYYY-MM-DD strings

document.addEventListener('DOMContentLoaded', function() {
    var calEl = document.getElementById('availabilityCalendarEl');
    var calendar = new FullCalendar.Calendar(calEl, {
        initialView: 'dayGridMonth',
        selectable: false,          // do NOT use selectable; use dateClick instead
        events: availabilityEvents,
        dateClick: function(info) {
            var d = info.dateStr; // YYYY-MM-DD
            if (selectedDates.has(d)) {
                selectedDates.delete(d);
                info.dayEl.classList.remove('fc-day-selected');
            } else {
                selectedDates.add(d);
                info.dayEl.classList.add('fc-day-selected');
            }
            updateSelectionSummary();
        },
        eventClick: function(info) {
            var id = info.event.extendedProps.availabilityId;
            // open existing edit modal: $('#editWindowModal' + id).modal('show')
            var modal = document.getElementById('editWindowModal' + id);
            if (modal) bootstrap.Modal.getOrCreateInstance(modal).show();
        }
    });
    calendar.render();
});
```

Add a CSS rule so selected day cells are visually distinct:
```css
.fc-day-selected { background-color: rgba(13, 110, 253, 0.15) !important; }
```

### UI / UX Guardrails

- First screen should be usable, not explanatory. Calendar selection and save controls should be immediately available.
- Avoid a modal-only workflow for the quick path. A modal can be used for editing details, but the main value is quick day selection.
- Make all-day the default state. Time inputs should not compete visually until the umpire chooses specific hours.
- Batch save button should show how many dates are selected (e.g. "Save 3 Dates") and whether all-day or partial hours will be applied.
- Existing availability should be visible on the calendar and in the existing table/list so users can cross-check what is stored.
- Use responsive constraints so the calendar does not force horizontal scrolling on mobile.
- Keep the existing "Add Window" modal for single-entry as the JS-disabled fallback path.

### Latest Technical Notes

- FullCalendar official docs currently show v7 as the latest release, with v6 and v5 docs still available. Because `public/schedule.php` already loads FullCalendar `5.11.3` from CDN, the lowest-risk implementation is to reuse the same major version on this page unless the developer intentionally upgrades and verifies both schedule and availability calendar behavior.
- FullCalendar date ranges use exclusive `end` values in documented APIs. That aligns with the recommended all-day storage convention of selected date midnight through next date midnight, while the availability service can still satisfy its existing `ends_at >= requested_end` covering-window check.

### Security / Validation

- Every POST action must verify CSRF.
- Server-side validation is authoritative. Client-side calendar constraints are only convenience.
- Date lists from the browser must be treated as untrusted input. Parse each date, reject malformed entries, and cap the batch size to prevent accidental huge inserts. A cap around 62 selected dates is sufficient for a two-month planning burst unless product decides otherwise.
- Preserve role isolation: only the authenticated `umpire` can manage their own availability.
- Escape all user-visible notes and calendar event titles with the existing `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')` pattern when rendering server-side data.

### Previous Story Intelligence

- Story 25.1 review fixed schema and query assumptions. Do not invent `game_date_time` or `gua.status`; the live schedule timestamp is `TIMESTAMP(s.game_date, COALESCE(s.game_time, '00:00:00'))`, and assignment status is `assignment_status`.
- Story 25.2 consumed the pool contract from `UmpireAvailabilityService`; keep that method stable so assignment drawer behavior does not regress.
- Recent commits were story-scoped service/page/test changes. Keep this implementation similarly narrow: `public/umpires/availability.php`, `includes/UmpireAvailabilityService.php` if needed, and targeted tests.
- **Story 25.6 coordination (both are ready-for-dev):** Story 25.6 adds optional `$actorUserId` and `$source` parameters to `createWindow()`, `updateWindow()`, `deleteWindow()`. Since this story is umpire self-service, do **not** add those parameters here — call `$service->createWindow($userId, $startsAt, $endsAt, $notes)` with the existing 4-arg signature. If Story 25.6 is implemented first, its new params default to `null`/`'self'` so the call remains compatible. If this story is implemented first, Story 25.6 will extend the method signatures non-breakingly. No coordination action required; just don't add actor params to the self-service calls.

### Project Structure Notes

- Existing files expected to update:
  - `public/umpires/availability.php`
  - `includes/UmpireAvailabilityService.php` only if batch/date helpers are added
  - `tests/unit/UmpireAvailabilityServiceTest.php`
- Existing files likely not needed:
  - `includes/nav.php`; the My Availability link already exists from Story 25.1.
  - `database/migrations/*`; the table already exists.
  - `public/admin/umpires/assignment-drawer.js`; drawer behavior is outside this story.
  - `public/admin/umpires/*`; assignor availability views are outside this story.
- Keep CSS local to the page unless a reusable style is clearly needed. If shared CSS is changed, update only the active public asset path used by the app and verify no unrelated asset deletions are staged.

### References

- _bmad-output/project-context.md#Technology-Stack--Versions
- _bmad-output/project-context.md#Critical-Implementation-Rules
- _bmad-output/planning-artifacts/prd-umpire-assignment.md#Journey-7-Umpire-Enters-and-Manages-Availability
- _bmad-output/planning-artifacts/prd-umpire-assignment.md#Availability-Stretch-P1
- _bmad-output/planning-artifacts/epics-umpire-assignment.md#Story-251-Umpire-Availability-Entry
- _bmad-output/planning-artifacts/epics-umpire-assignment.md#Story-252-Availability-Pool-in-Assignment-Drawer
- _bmad-output/planning-artifacts/architecture-umpire-assignment.md#Architecture-Validation-Results
- public/umpires/availability.php
- includes/UmpireAvailabilityService.php
- tests/unit/UmpireAvailabilityServiceTest.php
- public/schedule.php
- FullCalendar docs: https://fullcalendar.io/docs
- FullCalendar v6 upgrade notes: https://fullcalendar.io/docs/upgrading-from-v5

## Dev Agent Record

### Agent Model Used

GPT-5 Codex

### Debug Log References

- 2026-06-28: RED focused test run failed as expected: missing `createWindowsForDates()` and missing calendar/page contract.
- 2026-06-28: `php -l includes/UmpireAvailabilityService.php` passed.
- 2026-06-28: `php -l public/umpires/availability.php` passed.
- 2026-06-28: `php tests/unit/run-unit-tests.php --file=UmpireAvailabilityServiceTest.php` passed: 13 passed, 0 failed.
- 2026-06-28: Browser/local route verification used `php -S 127.0.0.1:8077 -t public`, temporary `PHPSESSID=codex257`, and local umpire user `9001`; applied existing migration `051_create_umpire_availability_windows.sql` to local `moc835_d8tl_prod` because the local DB was behind the story prerequisite.
- 2026-06-28: Desktop browser check rendered My Availability, FullCalendar month view, all-day toggle, fallback Add Window modal, and current-windows card.
- 2026-06-28: Browser/curl verification covered all-day save, duplicate skipped response, partial-day create, reversed-time rejection, update, delete, unauthenticated redirect, non-umpire redirect, and mobile 390px render without document-level horizontal overflow.
- 2026-06-28: Full suite `php tests/unit/run-unit-tests.php` remains baseline red: 407 passed, 38 failed. Failures are outside Story 25.7 areas (CutoverService/ProfileService/RegistrationService/RescheduleService/ScoreService/Story 24.2 migration template/Story 23.7 eligibility tests).

### Completion Notes List

- Ultimate context engine analysis completed - comprehensive developer guide created.
- Story added as a UX follow-on because sprint status had no backlog story and the request is not covered by the existing Story 25.1 date/time form.
- Numbered as Story 25.7 to avoid colliding with the existing Story 25.6 manual availability-management artifact.
- Checklist validation applied: 5 critical issues, 5 enhancements, 2 optimizations resolved — batch POST contract specified, FullCalendar v5 dateClick multi-select pattern documented, date-to-datetime conversion added to tasks, duplicate-date behavior decided, all-day detection helper and event structure specified, Story 25.6 service coordination noted.
- Added `createWindowsForDates()` to the existing availability service with local date-to-DATETIME conversion, partial-day HH:MM validation, 62-date batch cap, duplicate exact-window skip handling, and unchanged ownership-scoped update/delete behavior.
- Reworked `public/umpires/availability.php` so the primary workflow is a FullCalendar v5 date-click calendar with selected-date highlighting, all-day default, optional shared start/end times, AJAX batch save, skipped/error alerts, refreshed calendar/table content, and existing Add/Edit/Delete fallback controls retained.
- Added targeted service and static page tests for all-day conversion, partial-day conversion, duplicate skip, batch cap, reversed times, calendar container/assets, CSRF, batch action, and existing update/delete regressions.
- Fixed runtime loading for `ActivityLogger` inside `UmpireAvailabilityService`; browser verification found the existing create path could otherwise fatal when called from the umpire portal.

### File List

- `_bmad-output/implementation-artifacts/25-7-visual-calendar-availability-entry.md`
- `_bmad-output/implementation-artifacts/sprint-status.yaml`
- `includes/UmpireAvailabilityService.php`
- `public/umpires/availability.php`
- `tests/unit/UmpireAvailabilityServiceTest.php`

### Change Log

- 2026-06-28: Implemented visual calendar availability entry and batch creation; story moved to review.

### Review Findings

- [x] [Review][Defer] Out-of-scope mega-menu CSS changes bundled with story 25.7 [`public/assets/css/style.css`] — deferred, out of scope for this story; handle in a separate commit
- [x] [Review][Patch] `dayCellDidMount` UTC/local mismatch — selected dates not re-highlighted after month navigation in UTC-offset timezones [`public/umpires/availability.php:528`]
- [x] [Review][Patch] Bootstrap dismiss removes alert element from DOM — subsequent `showAvailabilityAlert` calls throw TypeError on null [`public/umpires/availability.php:442-447`]
- [x] [Review][Patch] `refreshAvailabilityContent` rejection overwrites success message — if the re-fetch or JSON parse fails after a successful save, the `.catch()` replaces the success/warning alert with a confusing error [`public/umpires/availability.php:472-497`]
- [x] [Review][Defer] CSRF token not refreshed in batch form after `refreshAvailabilityContent` [`public/umpires/availability.php:266`] — deferred, session-based CSRF tokens do not rotate per-request in current auth implementation; low risk
- [x] [Review][Defer] Batch cap enforced before `array_unique` — 63 identical dates hit the cap before dedup [`includes/UmpireAvailabilityService.php:69-71`] — deferred, pre-existing by design per spec
- [x] [Review][Defer] All-invalid dates batch returns generic "No availability windows were created" without per-date error detail [`public/umpires/availability.php:59-61`] — deferred, minor UX improvement, not a defect
