<?php
/**
 * Unit Tests: GameStatusEligibility
 */

require_once __DIR__ . '/../../includes/GameStatusEligibility.php';

function gseGame(string $status, $homeScore = null, $awayScore = null): array {
    return [
        'game_status' => $status,
        'home_score' => $homeScore,
        'away_score' => $awayScore,
    ];
}

register_test('Admin cancel eligibility allows unplayed postponed games', function () {
    assert_true(
        GameStatusEligibility::canAdminCancel(gseGame('Postponed')),
        'Postponed games without scores must be cancellable by admins'
    );
});

register_test('Admin cancel eligibility allows unplayed scheduled games', function () {
    assert_true(
        GameStatusEligibility::canAdminCancel(gseGame('Scheduled')),
        'Scheduled games without scores must be cancellable by admins'
    );
});

register_test('Admin cancel eligibility rejects completed games', function () {
    assert_true(
        !GameStatusEligibility::canAdminCancel(gseGame('Completed', 2, 1)),
        'Completed games must not be cancellable'
    );
});

register_test('Admin cancel eligibility rejects any scored game', function () {
    assert_true(
        !GameStatusEligibility::canAdminCancel(gseGame('Scheduled', 2, null)),
        'Games with a home score must not be cancellable'
    );
    assert_true(
        !GameStatusEligibility::canAdminCancel(gseGame('Postponed', null, 1)),
        'Games with an away score must not be cancellable'
    );
});
