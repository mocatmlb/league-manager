<?php
/**
 * Unit Tests: GameTimeGate
 *
 * Story 1.3 — Implement Cross-Cutting Utility Classes
 * AC3: GameTimeGate::isEligible() evaluates all four date/time conditions
 */

if (!defined('D8TL_APP')) {
    define('D8TL_APP', true);
}

require_once __DIR__ . '/test-helpers.php';
require_once __DIR__ . '/../../includes/GameTimeGate.php';

// ---------------------------------------------------------------------------
// AC3-P0: past date → eligible (true)
// ---------------------------------------------------------------------------

register_test('AC3-P0: isEligible - game date in the past returns true', function () {
    $tz = new DateTimeZone('UTC');
    $game = [
        'game_date' => (new DateTime('-1 day', $tz))->format('Y-m-d'),
        'game_time' => '23:59:59',
    ];

    $result = GameTimeGate::isEligible($game);
    assert_true($result === true, 'isEligible must return true for a past game date');
});

// ---------------------------------------------------------------------------
// AC3-P1: future date → not eligible (false)
// ---------------------------------------------------------------------------

register_test('AC3-P1: isEligible - game date in the future returns false', function () {
    $tz = new DateTimeZone('UTC');
    $game = [
        'game_date' => (new DateTime('+1 day', $tz))->format('Y-m-d'),
        'game_time' => '00:00:00',
    ];

    $result = GameTimeGate::isEligible($game);
    assert_true($result === false, 'isEligible must return false for a future game date');
});

// ---------------------------------------------------------------------------
// AC3-P2: today + past time → eligible (true)
// ---------------------------------------------------------------------------

register_test('AC3-P2: isEligible - today with past game time returns true', function () {
    // Use midnight UTC — always in the past at any time of day
    $tz = new DateTimeZone('UTC');
    $game = [
        'game_date' => (new DateTime('now', $tz))->format('Y-m-d'),
        'game_time' => '00:00:00',
    ];

    $result = GameTimeGate::isEligible($game);
    assert_true($result === true, 'isEligible must return true when game is today and time is in the past (00:00:00)');
});

// ---------------------------------------------------------------------------
// AC3-P3: today + future time → not eligible (false)
// ---------------------------------------------------------------------------

register_test('AC3-P3: isEligible - today with future game time returns false', function () {
    // Compute a time 2 hours ahead in UTC (same timezone GameTimeGate uses)
    $futureUtc = new DateTime('+2 hours', new DateTimeZone('UTC'));
    $futureTime = $futureUtc->format('H:i:s');
    $todayUtc   = (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d');

    $game = [
        'game_date' => $todayUtc,
        'game_time' => $futureTime,
    ];

    $result = GameTimeGate::isEligible($game);
    assert_true($result === false, 'isEligible must return false when game is today and time is in the future');
});

// ---------------------------------------------------------------------------
// AC3-P4: today + exact current time (boundary: game_time == now) → eligible
// ---------------------------------------------------------------------------

register_test('AC3-P4: isEligible - today with game time equal to now returns true (boundary)', function () {
    // game_time <= now → eligible; this tests the <= boundary (using UTC consistent with GameTimeGate)
    $tz = new DateTimeZone('UTC');
    $now = new DateTime('now', $tz);
    $nowTime = $now->format('H:i:s');

    $game = [
        'game_date' => $now->format('Y-m-d'),
        'game_time' => $nowTime,
    ];

    // At boundary (game_time == current second), should be eligible
    $result = GameTimeGate::isEligible($game);
    assert_true($result === true, 'isEligible must return true when game time equals current server time (boundary condition)');
});

// ---------------------------------------------------------------------------
// AC3-P5: H:i format (no seconds) is handled correctly
// ---------------------------------------------------------------------------

register_test('AC3-P5: isEligible - accepts H:i format game_time without seconds', function () {
    $tz = new DateTimeZone('UTC');
    $game = [
        'game_date' => (new DateTime('-1 day', $tz))->format('Y-m-d'),
        'game_time' => '10:00',  // no seconds component
    ];

    $result = GameTimeGate::isEligible($game);
    assert_true($result === true, 'isEligible must handle H:i format game_time (past date always eligible)');
});
