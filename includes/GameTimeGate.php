<?php
/**
 * District 8 Travel League - Game Time Gate Utility
 *
 * Determines whether a game is eligible for score submission or display
 * based on its scheduled date and time (server UTC).
 *
 * Usage: if (GameTimeGate::isEligible($game)) { ... }
 */

if (!defined('D8TL_APP')) {
    die('Direct access not permitted');
}

class GameTimeGate {

    /**
     * Determine if a game has passed its scheduled start time and is eligible.
     *
     * A game is eligible when:
     *   - game_date is in the past (any time), OR
     *   - game_date is today AND game_time is in the past (server UTC)
     *
     * A game is NOT eligible when:
     *   - game_date is today AND game_time is in the future, OR
     *   - game_date is in the future
     *
     * @param array $game  Must contain 'game_date' (Y-m-d) and 'game_time' (H:i:s or H:i)
     * @return bool
     */
    public static function isEligible(array $game): bool {
        $tz = new DateTimeZone('UTC');
        $now = new DateTime('now', $tz);

        $today = $now->format('Y-m-d');
        $gameDate = $game['game_date'];

        if ($gameDate < $today) {
            return true;
        }

        if ($gameDate > $today) {
            return false;
        }

        // Same day: compare times
        $nowTime = $now->format('H:i:s');
        $gameTime = $game['game_time'];

        // Normalise to H:i:s for consistent comparison
        if (strlen($gameTime) === 5) {
            $gameTime .= ':00';
        }

        return $gameTime <= $nowTime;
    }
}
