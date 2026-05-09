### Acceptance Auditor Findings

1. **AC1 Violation: Missing validation for "scored or cancelled" games in `submit()`**
   - **AC1 Requirement:** "Submit creates a pending request for an eligible game ... game involving their team that is not scored or cancelled"
   - **Violation:** `RescheduleService::submit()` only verifies team scope via `TeamScope::getScopedTeams()`. It does not check `game_status` of the target game. A coach can manually POST a `game_id` for a completed or cancelled game and the service will accept it, violating the "not scored or cancelled" constraint.
   - **Evidence:** `includes/RescheduleService.php` lines 42-60.

2. **AC3 Violation: `getEligibleGames()` does not explicitly re-include games after cancellation**
   - **AC3 Requirement:** "And on next `getEligibleGames()` call the game re-appears if still eligible"
   - **Violation:** While `getEligibleGames()` filters by `game_status`, if it also filters out games with existing pending requests (as suggested in some Dev Notes), it must ensure that a 'Denied' (cancelled) request allows the game to return. The current implementation in `RescheduleService.php` doesn't seem to check for existing requests at all, which might actually satisfy this AC by accident (it never hides them), but violates the implied requirement to hide games with *active* pending requests.
   - **Evidence:** `includes/RescheduleService.php` lines 151-177.

3. **AC7 Violation: Missing `403` status on `TeamScopeViolationException` in some paths?**
   - **AC7 Requirement:** "TeamScopeViolationException returns 403 ... a 403 status is set"
   - **Violation:** `public/coaches/schedule-change.php` catches the exception and calls `http_response_code(403)`, but then continues to render the page instead of exiting or showing a dedicated 403 response. While it "sets" the status, it might not be the intended "403 response" behavior for a security violation.
   - **Evidence:** `public/coaches/schedule-change.php` lines 72-74.

4. **Missing Implementation: `request_type = 'Reschedule'`**
   - **AC1 Requirement:** "And a new `schedule_change_requests` row is created with ... `request_type = 'Reschedule'`"
   - **Violation:** The diff for `RescheduleService.php` shows `'Reschedule'` being inserted, which is correct. Verified.
   - **Evidence:** `includes/RescheduleService.php` line 90.

5. **Deviation from Spec: `PermissionGuard` redirect path**
   - **Spec Requirement (Dev Notes):** "Match the pattern from `public/coaches/score-input.php` ... not the admin login."
   - **Violation:** `public/coaches/schedule-change.php` uses `PermissionGuard::requireRole('team_owner', '/admin/login.php')`. If a coach fails this check, they are sent to the admin login, which they cannot use. This contradicts the Dev Note instruction.
   - **Evidence:** `public/coaches/schedule-change.php` line 15.
