<?php
/**
 * Shared game status eligibility rules.
 */

if (!defined('D8TL_APP')) {
    die('Direct access not permitted');
}

class GameStatusEligibility {
    public static function canAdminCancel(array $game): bool {
        $status = (string)($game['game_status'] ?? '');

        if ($status === 'Completed' || $status === 'Cancelled') {
            return false;
        }

        return ($game['home_score'] ?? null) === null
            && ($game['away_score'] ?? null) === null;
    }
}
