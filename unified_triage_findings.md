### Unified Triage Findings

| ID | Source | Title | Detail | Location | Category |
|---|---|---|---|---|---|
| 1 | auditor+edge | Missing game status validation in `submit()` | AC1 requires games to be "not scored or cancelled". `RescheduleService::submit()` only checks team scope, allowing requests for completed/cancelled games. | `includes/RescheduleService.php:42-60` | patch |
| 2 | blind+edge | Missing input validation in `RescheduleService::submit()` | `$requestData` keys are accessed directly without checking existence or emptiness. Can lead to PHP warnings/errors and invalid DB data. | `includes/RescheduleService.php:80-83` | patch |
| 3 | edge | Missing non-existent game guard in `submit()` | `RescheduleService::submit()` does not check if the game exists before accessing its properties, potentially leading to undefined array key errors. | `includes/RescheduleService.php:51-60` | patch |
| 4 | edge | Missing non-existent request guard in `cancel()` | `RescheduleService::cancel()` does not verify the request exists before proceeding, potentially causing property fetch errors on NULL. | `includes/RescheduleService.php:123` | patch |
| 5 | auditor | Deviation from Spec: `PermissionGuard` redirect path | `schedule-change.php` redirects to `/admin/login.php` instead of the coach login, contradicting Dev Notes to match `score-input.php`. | `public/coaches/schedule-change.php:15` | patch |
| 6 | edge | Missing `game_id` guard in `schedule-change.php` | POST `game_id` is not validated before being passed to the service, causing a misleading "Not authorized" error if 0 or missing. | `public/coaches/schedule-change.php:54` | patch |
| 7 | blind+auditor | `schedule-change.php` continues after 403 status | When `TeamScopeViolationException` is caught, 403 is set but the rest of the page still renders. Should likely stop or show error-only view. | `public/coaches/schedule-change.php:72-74` | patch |
| 8 | blind | Semantic confusion mapping cancellation to `Denied` | Coach-initiated cancellation is mapped to `'Denied'` in the database. While required by schema, it may trigger incorrect downstream logic/emails. | `includes/RescheduleService.php:123` | defer |
| 9 | blind+edge | `RescheduleService::cancel()` Race Condition | Concurrent cancel requests can both pass the status check and update the same row. | `includes/RescheduleService.php:123` | defer |
| 10 | edge | JavaScript `onGameSelected` missing null guard | JS function might fail if called when no option is selected or select is empty. | `public/coaches/schedule-change.php:261` | patch |
| 11 | blind | `getCoachRequests()` potentially hides requests | JOIN to `schedules` might exclude requests for games that don't have a schedule row yet. | `includes/RescheduleService.php:191` | patch |
| 12 | auditor | `getEligibleGames()` re-inclusion behavior ambiguous | AC3 requires games to "re-appear" after cancellation. Service doesn't hide games with pending requests currently, which might be a different issue. | `includes/RescheduleService.php:151-177` | decision_needed |

**Dismissed:** 3 (False positives or minor style points)
- SQL Injection in `getEligibleGames` (Handled by placeholders)
- Migration constraint check (Handled by IF NOT EXISTS guard)
- User not found in `submit()` (User is authenticated, risk is extremely low)
