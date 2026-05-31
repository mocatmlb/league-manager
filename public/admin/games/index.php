<?php
/**
 * District 8 Travel League - Games Management
 */

// Robust EnvLoader include: locate includes/env-loader.php regardless of layout
$__dir = __DIR__;
$__found = false;
for ($__i = 0; $__i < 6; $__i++) {
    $__candidate = $__dir . '/includes/env-loader.php';
    if (file_exists($__candidate)) {
        require_once $__candidate;
        $__found = true;
        break;
    }
    $__dir = dirname($__dir);
}
if (!$__found) {
    if (!empty($_SERVER['DOCUMENT_ROOT']) && file_exists($_SERVER['DOCUMENT_ROOT'] . '/includes/env-loader.php')) {
        require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/env-loader.php';
        $__found = true;
    }
}
if (!$__found) {
    error_log('D8TL ERROR: Unable to locate includes/env-loader.php from ' . __FILE__);
    http_response_code(500);
    exit('Configuration error: env-loader not found');
}
unset($__dir, $__found, $__i, $__candidate);

@include_once EnvLoader::getPath('includes/admin_bootstrap.php');

// Require admin authentication
Auth::requireAdmin();

$db = Database::getInstance();
$currentUser = Auth::getCurrentUser();

// Handle AJAX requests for schedule history
if (isset($_GET['action']) && $_GET['action'] === 'get_change_history' && isset($_GET['game_id'])) {
    $gameId = (int)$_GET['game_id'];
    
    // Get complete schedule history from the new schedule_history table
    $scheduleHistory = $db->fetchAll("
        SELECT 
            sh.history_id,
            sh.version_number,
            sh.schedule_type,
            sh.game_date,
            sh.game_time,
            sh.location,
            sh.is_current,
            sh.created_at,
            sh.notes,
            sh.user_notes,
            sh.change_request_id,
            scr.request_type,
            scr.requested_by,
            scr.reason,
            scr.request_status,
            scr.reviewed_at,
            scr.review_notes,
            COALESCE(au.username, CONCAT(u.first_name, ' ', u.last_name)) as reviewed_by_username
        FROM schedule_history sh
        LEFT JOIN schedule_change_requests scr ON sh.change_request_id = scr.request_id
        LEFT JOIN admin_users au ON scr.reviewed_by = au.id
        LEFT JOIN users u ON scr.reviewed_by = u.id AND au.id IS NULL
        WHERE sh.game_id = ?
        ORDER BY sh.version_number ASC
    ", [$gameId]);
    
    // Add timezone information to each history entry
    foreach ($scheduleHistory as &$history) {
        // Only format valid dates
        if ($history['game_date'] && $history['game_date'] !== '0000-00-00') {
            $history['game_date_tz'] = formatDateForJS($history['game_date']);
        }
        if ($history['game_date'] && $history['game_time'] && $history['game_date'] !== '0000-00-00') {
            $history['game_time_tz'] = formatDateForJS($history['game_date'] . ' ' . $history['game_time']);
        }
        if ($history['created_at'] && $history['created_at'] !== '0000-00-00 00:00:00') {
            $history['created_at_tz'] = formatDateForJS($history['created_at']);
        }
        if ($history['reviewed_at'] && $history['reviewed_at'] !== '0000-00-00 00:00:00') {
            $history['reviewed_at_tz'] = formatDateForJS($history['reviewed_at']);
        }
    }
    
    header('Content-Type: application/json');
    echo json_encode($scheduleHistory);
    exit;
}

/**
 * Atomically increment the sequence for the current year and return the generated
 * game number in YYYYNNNN format. Must be called inside an open transaction so that
 * a failed game INSERT rolls back the sequence counter too.
 */
function autoGenerateGameNumber(Database $db): string {
    $year = (int)date('Y');
    $db->query(
        "INSERT INTO game_number_sequences (seq_year, last_seq) VALUES (?, 1)
         ON DUPLICATE KEY UPDATE last_seq = last_seq + 1",
        [$year]
    );
    $row = $db->fetchOne(
        "SELECT last_seq FROM game_number_sequences WHERE seq_year = ?",
        [$year]
    );
    $lastSeq = (int)($row['last_seq'] ?? 0);
    if ($lastSeq < 1 || $lastSeq > 9999) {
        throw new RuntimeException("Unable to generate game number for {$year}: yearly sequence exceeded YYYYNNNN limits.");
    }
    return sprintf('%04d%04d', $year, $lastSeq);
}

// Handle form submissions
$message = $_SESSION['flash_success'] ?? '';
$error   = $_SESSION['flash_error']   ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid form submission. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'add_game':
                try {
                    $homeTeamId = (int)$_POST['home_team_id'];
                    $awayTeamId = (int)$_POST['away_team_id'];

                    if ($homeTeamId <= 0 || $awayTeamId <= 0) {
                        throw new Exception('Please select both a home team and an away team.');
                    }

                    if ($homeTeamId === $awayTeamId) {
                        throw new Exception('Home team and away team cannot be the same.');
                    }

                    $db->beginTransaction();

                    // Validate that both teams are active
                    $homeTeam = $db->fetchOne("SELECT team_name, active_status FROM teams WHERE team_id = ?", [$homeTeamId]);
                    $awayTeam = $db->fetchOne("SELECT team_name, active_status FROM teams WHERE team_id = ?", [$awayTeamId]);
                    
                    if (!$homeTeam || $homeTeam['active_status'] !== 'Active') {
                        throw new Exception('Home team is not active and cannot be assigned to games.');
                    }
                    
                    if (!$awayTeam || $awayTeam['active_status'] !== 'Active') {
                        throw new Exception('Away team is not active and cannot be assigned to games.');
                    }
                    
                    Logger::debug("Creating game with active teams", [
                        'home_team' => $homeTeam['team_name'],
                        'away_team' => $awayTeam['team_name'],
                        'admin_user' => $_SESSION['admin_username'] ?? 'unknown'
                    ]);
                    
                    // Create game record — game_number is auto-generated inside the transaction
                    $gameData = [
                        'game_number' => autoGenerateGameNumber($db),
                        'season_id' => (int)$_POST['season_id'],
                        'division_id' => !empty($_POST['division_id']) ? (int)$_POST['division_id'] : null,
                        'home_team_id' => $homeTeamId,
                        'away_team_id' => $awayTeamId,
                        'game_status' => 'Created',
                        'created_date' => date('Y-m-d H:i:s')
                    ];
                    
                    $gameId = $db->insert('games', $gameData);
                    
                    // Resolve location
                    $locationIdRaw = $_POST['location_id'] ?? '';
                    if (trim($locationIdRaw) === 'not-listed') {
                        $newName = sanitize(trim((string)($_POST['location_name_new'] ?? '')));
                        if ($newName === '') {
                            throw new Exception('Location name is required when selecting "Not Listed".');
                        }
                        $newCity = sanitize(trim((string)($_POST['location_city_new'] ?? '')));
                        if ($newCity === '') {
                            throw new Exception('City is required when adding a new location.');
                        }
                        $newState = sanitize(trim((string)($_POST['location_state_new'] ?? '')));
                        if ($newState === '') {
                            throw new Exception('State is required when adding a new location.');
                        }
                        $newAddress = sanitize(trim((string)($_POST['location_address_new'] ?? '')));
                        $newZip = sanitize(trim((string)($_POST['location_zip_new'] ?? '')));
                        $locData = [
                            'location_name' => $newName,
                            'address' => $newAddress,
                            'city' => $newCity,
                            'state' => $newState,
                            'zip_code' => $newZip,
                            'active_status' => 'Active'
                        ];
                        $resolvedLocationId = $db->insert('locations', $locData);
                        $resolvedLocationText = $newName;
                        logActivity('location_created', "Location added from game form: {$newName}");
                    } else {
                        $resolvedLocationId = (int)$locationIdRaw;
                        $locRow = $db->fetchOne("SELECT location_name FROM locations WHERE location_id = ?", [$resolvedLocationId]);
                        $resolvedLocationText = ($locRow && isset($locRow['location_name'])) ? $locRow['location_name'] : '';
                    }

                    // Create schedule record
                    $scheduleData = [
                        'game_id' => $gameId,
                        'game_date' => sanitize($_POST['game_date']),
                        'game_time' => sanitize($_POST['game_time']),
                        'location' => $resolvedLocationText,
                        'location_id' => $resolvedLocationId,
                        'created_date' => date('Y-m-d H:i:s')
                    ];

                    $db->insert('schedules', $scheduleData);

                    // Create initial schedule history entry
                    $historyData = [
                        'game_id' => $gameId,
                        'version_number' => 1,
                        'schedule_type' => 'Original',
                        'game_date' => sanitize($_POST['game_date']),
                        'game_time' => sanitize($_POST['game_time']),
                        'location' => $resolvedLocationText,
                        'location_id' => $resolvedLocationId,
                        'is_current' => 1,
                        'created_at' => date('Y-m-d H:i:s'),
                        'notes' => 'Initial game schedule'
                    ];
                    
                    $db->insert('schedule_history', $historyData);

                    $addGameNotes = trim($_POST['game_notes'] ?? '');
                    if ($addGameNotes !== '') {
                        $db->update('schedule_history', ['user_notes' => $addGameNotes], 'game_id = ? AND is_current = 1', [$gameId]);
                    }

                    // Update game status to Scheduled if schedule data is provided
                    if (!empty($_POST['game_date'])) {
                        $db->update('games', ['game_status' => 'Scheduled'], 'game_id = ?', [$gameId]);
                    }

                    $db->commit();
                    
                    logActivity('game_created', "Game {$gameData['game_number']} created: {$awayTeam['team_name']} vs {$homeTeam['team_name']}");
                    $message = "Game {$gameData['game_number']} created successfully!";
                    
                } catch (Exception $e) {
                    try { $db->rollback(); } catch (Exception $ignored) {}
                    $error = 'Error creating game: ' . $e->getMessage();
                }
                break;
                
            case 'update_score':
                try {
                    $gameId = (int)$_POST['game_id'];
                    $awayScore = (int)$_POST['away_score'];
                    $homeScore = (int)$_POST['home_score'];
                    
                    $gameData = [
                        'away_score' => $awayScore,
                        'home_score' => $homeScore,
                        'game_status' => 'Completed',
                        'score_submitted_by' => $currentUser['username'] ?? 'Admin',
                        'score_submitted_at' => date('Y-m-d H:i:s'),
                        'modified_date' => date('Y-m-d H:i:s')
                    ];
                    
                    $db->update('games', $gameData, 'game_id = ?', [$gameId]);
                    
                    logActivity('score_updated', "Score updated for game ID $gameId: Away $awayScore, Home $homeScore");
                    $message = 'Score updated successfully!';
                    
                } catch (Exception $e) {
                    $error = 'Error updating score: ' . $e->getMessage();
                }
                break;
                
            case 'delete_score':
                try {
                    $gameId = (int)$_POST['game_id'];
                    
                    // Get current game info for logging and status determination
                    $gameInfo = $db->fetchOne("
                        SELECT g.game_number, g.away_score, g.home_score, g.game_status, 
                               (SELECT COUNT(*) FROM schedule_change_requests scr WHERE scr.game_id = g.game_id AND scr.request_status = 'Pending') as pending_changes,
                               (SELECT COUNT(*) FROM schedules s WHERE s.game_id = g.game_id) as has_schedule
                        FROM games g 
                        WHERE g.game_id = ?
                    ", [$gameId]);
                    
                    // Use query method with positional parameters
                    $now = date('Y-m-d H:i:s');
                    
                    $newStatus = 'Created';
                    if ($gameInfo['pending_changes'] > 0) {
                        $newStatus = 'Pending Change';
                    } else if ($gameInfo['has_schedule'] > 0) {
                        $newStatus = 'Scheduled';
                    }
                    
                    $db->query("
                        UPDATE games 
                        SET away_score = NULL, 
                            home_score = NULL, 
                            game_status = ?, 
                            score_submitted_by = NULL,
                            score_submitted_at = NULL,
                            modified_date = ? 
                        WHERE game_id = ?
                    ", [$newStatus, $now, $gameId]);
                    
                    logActivity('score_deleted', "Score deleted for game {$gameInfo['game_number']} (ID: $gameId): was {$gameInfo['away_score']}-{$gameInfo['home_score']}");
                    $message = 'Score deleted successfully!';
                    
                } catch (Exception $e) {
                    $error = 'Error deleting score: ' . $e->getMessage();
                }
                break;
                
            case 'update_game':
                try {
                    $gameId = (int)$_POST['game_id'];
                    $homeTeamId = (int)$_POST['home_team_id'];
                    $awayTeamId = (int)$_POST['away_team_id'];

                    if ($homeTeamId <= 0 || $awayTeamId <= 0) {
                        throw new Exception('Please select both a home team and an away team.');
                    }

                    if ($homeTeamId === $awayTeamId) {
                        throw new Exception('Home team and away team cannot be the same.');
                    }

                    $db->beginTransaction();
                    
                    // Get current schedule for comparison
                    $currentSchedule = $db->fetchOne("SELECT * FROM schedules WHERE game_id = ?", [$gameId]);
                    
                    // Get current game status
                    $currentGame = $db->fetchOne("
                        SELECT g.game_status,
                               (SELECT COUNT(*) FROM schedule_change_requests scr 
                                WHERE scr.game_id = g.game_id AND scr.request_status = 'Pending') as pending_changes,
                               (SELECT COUNT(*) FROM schedules s WHERE s.game_id = g.game_id) as has_schedule
                        FROM games g 
                        WHERE g.game_id = ?
                    ", [$gameId]);

                    // Determine new status automatically (not user-selectable)
                    if ($currentGame['pending_changes'] > 0) {
                        $newStatus = 'Pending Change';
                    } else if ($currentGame['has_schedule'] > 0) {
                        $newStatus = 'Scheduled';
                    } else {
                        $newStatus = 'Created';
                    }
                    
                    // Update game record using positional parameters
                    $db->query("
                        UPDATE games 
                        SET season_id = ?,
                            division_id = ?,
                            home_team_id = ?,
                            away_team_id = ?,
                            game_status = ?,
                            modified_date = ?
                        WHERE game_id = ?
                    ", [
                        (int)$_POST['season_id'],
                        (int)$_POST['division_id'],
                        $homeTeamId,
                        $awayTeamId,
                        $newStatus,
                        date('Y-m-d H:i:s'),
                        $gameId
                    ]);
                    
                    // Check if schedule changed
                    $newDate = sanitize($_POST['game_date']);
                    $newTime = sanitize($_POST['game_time']);
                    
                    // Resolve location
                    $locationIdRaw = $_POST['location_id'] ?? '';
                    if (trim($locationIdRaw) === 'not-listed') {
                        $newName = sanitize(trim((string)($_POST['location_name_new'] ?? '')));
                        if ($newName === '') {
                            throw new Exception('Location name is required when selecting "Not Listed".');
                        }
                        $newCity = sanitize(trim((string)($_POST['location_city_new'] ?? '')));
                        if ($newCity === '') {
                            throw new Exception('City is required when adding a new location.');
                        }
                        $newState = sanitize(trim((string)($_POST['location_state_new'] ?? '')));
                        if ($newState === '') {
                            throw new Exception('State is required when adding a new location.');
                        }
                        $newAddress = sanitize(trim((string)($_POST['location_address_new'] ?? '')));
                        $newZip = sanitize(trim((string)($_POST['location_zip_new'] ?? '')));
                        $locData = [
                            'location_name' => $newName,
                            'address' => $newAddress,
                            'city' => $newCity,
                            'state' => $newState,
                            'zip_code' => $newZip,
                            'active_status' => 'Active'
                        ];
                        $resolvedLocationId = $db->insert('locations', $locData);
                        $resolvedLocationText = $newName;
                        logActivity('location_created', "Location added from game edit form: {$newName}");
                    } else {
                        $resolvedLocationId = (int)$locationIdRaw;
                        $locRow = $db->fetchOne("SELECT location_name FROM locations WHERE location_id = ?", [$resolvedLocationId]);
                        $resolvedLocationText = ($locRow && isset($locRow['location_name'])) ? $locRow['location_name'] : '';
                    }

                    $scheduleChanged = ($currentSchedule['game_date'] !== $newDate || 
                                     $currentSchedule['game_time'] !== $newTime || 
                                     $currentSchedule['location'] !== $resolvedLocationText ||
                                     (int)$currentSchedule['location_id'] !== $resolvedLocationId);

                    if ($scheduleChanged) {
                        // Update schedule record
                        $scheduleData = [
                            'game_date' => $newDate,
                            'game_time' => $newTime,
                            'location' => $resolvedLocationText,
                            'location_id' => $resolvedLocationId,
                            'modified_date' => date('Y-m-d H:i:s')
                        ];

                        $db->update('schedules', $scheduleData, 'game_id = ?', [$gameId]);

                        // Mark current history as not current
                        $db->query("UPDATE schedule_history SET is_current = 0 WHERE game_id = ?", [$gameId]);

                        // Get next version number
                        $maxVersion = $db->fetchOne("SELECT MAX(version_number) as max_version FROM schedule_history WHERE game_id = ?", [$gameId]);
                        $nextVersion = ($maxVersion['max_version'] ?? 0) + 1;

                        // Create new schedule history entry
                        $historyData = [
                            'game_id' => $gameId,
                            'version_number' => $nextVersion,
                            'schedule_type' => 'Changed',
                            'game_date' => $newDate,
                            'game_time' => $newTime,
                            'location' => $resolvedLocationText,
                            'location_id' => $resolvedLocationId,
                            'is_current' => 1,
                            'created_at' => date('Y-m-d H:i:s'),
                            'notes' => 'Schedule updated via admin'
                        ];
                        
                        $db->insert('schedule_history', $historyData);
                    }
                    
                    $updateGameNotes = trim($_POST['game_notes'] ?? '') ?: null;
                    $db->update('schedule_history', ['user_notes' => $updateGameNotes], 'game_id = ? AND is_current = 1', [$gameId]);

                    $db->commit();

                    logActivity('game_updated', "Game ID $gameId updated");
                    $message = 'Game updated successfully!';
                    
                } catch (Exception $e) {
                    try { $db->rollback(); } catch (Exception $ignored) {}
                    $error = 'Error updating game: ' . $e->getMessage();
                }
                break;
                
            case 'cancel_game':
                try {
                    $gameId = (int)$_POST['game_id'];
                    $reason = sanitize($_POST['cancel_reason']);
                    
                    $db->beginTransaction();
                    
                    // Update game status
                    $gameData = [
                        'game_status' => 'Cancelled',
                        'modified_date' => date('Y-m-d H:i:s')
                    ];
                    
                    $db->update('games', $gameData, 'game_id = ?', [$gameId]);
                    
                    // Mark current history as not current
                    $db->query("UPDATE schedule_history SET is_current = 0 WHERE game_id = ?", [$gameId]);
                    
                    // Get next version number
                    $maxVersion = $db->fetchOne("SELECT MAX(version_number) as max_version FROM schedule_history WHERE game_id = ?", [$gameId]);
                    $nextVersion = ($maxVersion['max_version'] ?? 0) + 1;
                    
                    // Get current schedule for the cancelled entry
                    $currentSchedule = $db->fetchOne("SELECT * FROM schedules WHERE game_id = ?", [$gameId]);
                    
                    // Create cancellation history entry
                    $historyData = [
                        'game_id' => $gameId,
                        'version_number' => $nextVersion,
                        'schedule_type' => 'Changed',
                        'game_date' => $currentSchedule['game_date'],
                        'game_time' => $currentSchedule['game_time'],
                        'location' => $currentSchedule['location'],
                        'is_current' => 1,
                        'created_at' => date('Y-m-d H:i:s'),
                        'notes' => 'Game cancelled: ' . $reason
                    ];
                    
                    $db->insert('schedule_history', $historyData);
                    
                    $db->commit();
                    
                    logActivity('game_cancelled', "Game ID $gameId cancelled: $reason");
                    $message = 'Game cancelled successfully!';
                    
                } catch (Exception $e) {
                    try { $db->rollback(); } catch (Exception $ignored) {}
                    $error = 'Error cancelling game: ' . $e->getMessage();
                }
                break;
                
            case 'delete_game':
                try {
                    $gameId = (int)$_POST['game_id'];

                    $db->beginTransaction();

                    $gameInfo = $db->fetchOne("
                        SELECT g.game_number,
                               CASE WHEN ht.team_name IS NOT NULL AND ht.team_name != '' THEN ht.team_name
                                    ELSE CONCAT(ht.league_name, '-', ht.manager_last_name) END as home_team_name,
                               CASE WHEN at.team_name IS NOT NULL AND at.team_name != '' THEN at.team_name
                                    ELSE CONCAT(at.league_name, '-', at.manager_last_name) END as away_team_name
                        FROM games g
                        JOIN teams ht ON g.home_team_id = ht.team_id
                        JOIN teams at ON g.away_team_id = at.team_id
                        WHERE g.game_id = ?
                    ", [$gameId]);

                    if (!$gameInfo) {
                        throw new Exception('Game not found.');
                    }

                    $db->query("DELETE FROM games WHERE game_id = ?", [$gameId]);

                    $db->commit();

                    logActivity('game_deleted', "Game {$gameInfo['game_number']} deleted: {$gameInfo['away_team_name']} vs {$gameInfo['home_team_name']} (ID: $gameId)");
                    $message = 'Game deleted successfully!';

                } catch (Exception $e) {
                    try { $db->rollback(); } catch (Exception $ignored) {}
                    $error = 'Error deleting game: ' . $e->getMessage();
                }
                break;

            case 'postpone_game':
                try {
                    $gameId = (int)$_POST['game_id'];
                    $reason = sanitize($_POST['postpone_reason']);
                    
                    $db->beginTransaction();
                    
                    // Update game status
                    $gameData = [
                        'game_status' => 'Postponed',
                        'modified_date' => date('Y-m-d H:i:s')
                    ];
                    
                    $db->update('games', $gameData, 'game_id = ?', [$gameId]);
                    
                    // Mark current history as not current
                    $db->query("UPDATE schedule_history SET is_current = 0 WHERE game_id = ?", [$gameId]);
                    
                    // Get next version number
                    $maxVersion = $db->fetchOne("SELECT MAX(version_number) as max_version FROM schedule_history WHERE game_id = ?", [$gameId]);
                    $nextVersion = ($maxVersion['max_version'] ?? 0) + 1;
                    
                    // Get current schedule for the postponed entry
                    $currentSchedule = $db->fetchOne("SELECT * FROM schedules WHERE game_id = ?", [$gameId]);
                    
                    // Create postponement history entry
                    $historyData = [
                        'game_id' => $gameId,
                        'version_number' => $nextVersion,
                        'schedule_type' => 'Changed',
                        'game_date' => $currentSchedule['game_date'],
                        'game_time' => $currentSchedule['game_time'],
                        'location' => $currentSchedule['location'],
                        'is_current' => 1,
                        'created_at' => date('Y-m-d H:i:s'),
                        'notes' => 'Game postponed: ' . $reason
                    ];
                    
                    $db->insert('schedule_history', $historyData);
                    
                    $db->commit();
                    
                    logActivity('game_postponed', "Game ID $gameId postponed: $reason");
                    $message = 'Game postponed successfully!';
                    
                } catch (Exception $e) {
                    try { $db->rollback(); } catch (Exception $ignored) {}
                    $error = 'Error postponing game: ' . $e->getMessage();
                }
                break;
        }
    }
}

// Initialize filter helpers and get filter values
FilterHelpers::init();
$filters = FilterHelpers::getFilterValues();
$showInactive = filter_input(INPUT_GET, 'show_inactive', FILTER_VALIDATE_BOOLEAN) ?: false;

// Build filter conditions
$filterSql = FilterHelpers::buildFilterConditions($filters);
$conditions = $filterSql['conditions'];
$params = $filterSql['params'];

// Add season status condition if not showing inactive
if (!$showInactive) {
    $conditions .= " AND s.season_status IN ('Active', 'Planning', 'Registration')";
}

// Get games with team names, schedule info, and change counts
$sql = "SELECT g.*, sch.game_date, sch.game_time, sch.location,
           CASE 
               WHEN ht.team_name IS NOT NULL AND ht.team_name != '' THEN ht.team_name 
               ELSE CONCAT(ht.league_name, '-', ht.manager_last_name) 
           END as home_team_name, 
           CASE 
               WHEN at.team_name IS NOT NULL AND at.team_name != '' THEN at.team_name 
               ELSE CONCAT(at.league_name, '-', at.manager_last_name) 
           END as away_team_name,
           s.season_name, s.season_year, s.season_status,
           d.division_name,
           p.program_name,
           (SELECT COUNT(*) FROM schedule_history sh WHERE sh.game_id = g.game_id AND sh.version_number > 1) as change_count,
           (SELECT COUNT(*) FROM schedule_history sh WHERE sh.game_id = g.game_id AND sh.user_notes IS NOT NULL AND sh.user_notes != '') as has_notes,
           (SELECT sh2.user_notes FROM schedule_history sh2 WHERE sh2.game_id = g.game_id AND sh2.is_current = 1 LIMIT 1) as current_user_notes
    FROM games g
    JOIN schedules sch ON g.game_id = sch.game_id
    JOIN teams ht ON g.home_team_id = ht.team_id
    JOIN teams at ON g.away_team_id = at.team_id
    JOIN seasons s ON g.season_id = s.season_id
    JOIN programs p ON s.program_id = p.program_id
    LEFT JOIN divisions d ON g.division_id = d.division_id
    WHERE 1=1" . $conditions . "
    ORDER BY sch.game_date ASC, sch.game_time ASC, sch.location ASC";

$games = $db->fetchAll($sql, $params);

// Get data for dropdowns
$seasons = $db->fetchAll("SELECT * FROM seasons ORDER BY season_year DESC");
$divisions = $db->fetchAll("SELECT * FROM divisions ORDER BY division_name");
$teams = $db->fetchAll("
    SELECT team_id, 
           CASE 
               WHEN team_name IS NOT NULL AND team_name != '' THEN team_name 
               ELSE CONCAT(league_name, '-', manager_last_name) 
           END as display_name,
           league_name, team_name
    FROM teams 
    WHERE active_status = 'Active' 
    ORDER BY display_name
");
$locations = $db->fetchAll("SELECT location_id, location_name, address, city, state, zip_code FROM locations WHERE active_status = 'Active' ORDER BY location_name");

$pageTitle = "Games Management - " . APP_NAME;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../../assets/css/style.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <style>
        .game-row {
            cursor: pointer;
            transition: background-color 0.2s ease;
        }
        .game-row:hover {
            background-color: #f8f9fa;
        }
        .expand-icon {
            transition: transform 0.2s ease;
            color: #6c757d;
        }
        .expand-icon.expanded {
            transform: rotate(90deg);
        }
        .clickable-hint {
            font-size: 0.8rem;
            color: #6c757d;
            font-style: italic;
        }
        .schedule-history {
            background-color: #f8f9fa;
            border-left: 3px solid #007bff;
        }
        .change-count-badge {
            background-color: #dc3545;
            color: white;
            border-radius: 50%;
            padding: 2px 6px;
            font-size: 0.7rem;
            font-weight: bold;
            margin-left: 5px;
        }
        .dropdown-menu {
            min-width: 160px;
        }
        .dropdown-item {
            padding: 0.5rem 1rem;
        }
        .dropdown-item i {
            width: 16px;
            margin-right: 8px;
        }
        .dropdown-item.text-danger:hover {
            background-color: #f8d7da;
            color: #721c24;
        }
    </style>
</head>
<body>
    <?php
    // Include nav with environment-aware path
    $__nav = file_exists(__DIR__ . '/../../includes/nav.php')
        ? __DIR__ . '/../../includes/nav.php'      // Production: /admin/games -> ../../includes
        : __DIR__ . '/../../../includes/nav.php';  // Development: /public/admin/games -> ../../../includes
    include $__nav;
    unset($__nav);
    ?>

    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1>Games Management</h1>
                    <div class="d-flex gap-2">
                        <a href="import.php" class="btn btn-outline-secondary">
                            <i class="fas fa-file-import"></i> Import Games
                        </a>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addGameModal">
                            <i class="fas fa-plus"></i> Add New Game
                        </button>
                    </div>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="fas fa-check-circle"></i> <?php echo $message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Filter Component -->
                <?php
                // Include admin filter component with environment-aware path
                $__filter = file_exists(__DIR__ . '/../../includes/admin_filter_component.php')
                    ? __DIR__ . '/../../includes/admin_filter_component.php'      // Production: /admin/games -> ../../includes
                    : __DIR__ . '/../../../includes/admin_filter_component.php';  // Development: /public/admin/games -> ../../../includes
                include $__filter;
                unset($__filter);
                ?>

                <!-- Games Table -->
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                        <table id="gamesTable" class="table table-striped">
                            <thead>
                                <tr>
                                    <th width="30"></th>
                                    <th>Game #</th>
                                    <th>Program</th>
                                    <th>Season</th>
                                    <th>Date</th>
                                    <th>Time</th>
                                    <th>Away Team</th>
                                    <th>Home Team</th>
                                    <th>Location</th>
                                    <th>Score</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($games as $game): ?>
                                    <tr class="game-row" data-game-id="<?php echo $game['game_id']; ?>" onclick="toggleGameDetails(<?php echo $game['game_id']; ?>)">
                                        <td class="text-center">
                                            <i class="fas fa-chevron-right expand-icon" id="icon-<?php echo $game['game_id']; ?>"></i>
                                        </td>
                                        <td>
                                            <?php echo sanitize($game['game_number']); ?>
                                            <?php if ($game['change_count'] > 0): ?>
                                                <span class="change-count-badge"><?php echo $game['change_count']; ?></span>
                                            <?php endif; ?>
                                            <?php if (!empty($game['has_notes'])): ?>
                                                <i class="fas fa-sticky-note text-warning ms-1" title="This game has notes — expand history to view"></i>
                                            <?php endif; ?>
                                            <br><small class="clickable-hint">Click to view history</small>
                                        </td>
                                        <td><?php echo sanitize($game['program_name']); ?></td>
                                        <td>
                                            <?php echo sanitize($game['season_name'] . ' ' . $game['season_year']); ?>
                                            <?php if ($game['season_status'] !== 'Active'): ?>
                                                <br><small class="text-muted">(<?php echo $game['season_status']; ?>)</small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo formatDate($game['game_date']); ?></td>
                                        <td><?php echo formatTime($game['game_time']); ?></td>
                                        <td><?php echo sanitize($game['away_team_name']); ?></td>
                                        <td><?php echo sanitize($game['home_team_name']); ?></td>
                                        <td><?php echo sanitize($game['location']); ?></td>
                                        <td>
                                            <?php if ($game['game_status'] === 'Completed'): ?>
                                                <?php echo $game['away_score'] . ' - ' . $game['home_score']; ?>
                                            <?php else: ?>
                                                <span class="text-muted">Not played</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $game['game_status'] === 'Completed' ? 'success' : ($game['game_status'] === 'Cancelled' ? 'danger' : ($game['game_status'] === 'Postponed' ? 'warning' : 'primary')); ?>">
                                                <?php echo $game['game_status']; ?>
                                            </span>
                                        </td>
                                        <td onclick="event.stopPropagation();">
                                            <div class="btn-group" role="group">
                                                <?php 
                                                // Show score button for games without scores on current date or in the past
                                                $gameDate = new DateTime($game['game_date']);
                                                $today = new DateTime();
                                                $showScoreButton = (($game['game_status'] === 'Scheduled' || $game['game_status'] === 'Pending Change') && $gameDate <= $today);
                                                
                                                if ($showScoreButton): ?>
                                                    <button class="btn btn-sm btn-success" onclick="updateScore(<?php echo $game['game_id']; ?>, '<?php echo addslashes($game['away_team_name']); ?>', '<?php echo addslashes($game['home_team_name']); ?>')">
                                                        <i class="fas fa-edit"></i> Score
                                                    </button>
                                                <?php endif; ?>
                                                
                                                <!-- Edit dropdown button -->
                                                <div class="btn-group" role="group">
                                                    <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                                        <i class="fas fa-cog"></i> Edit
                                                    </button>
                                                    <ul class="dropdown-menu">
                                                        <?php if ($game['game_status'] === 'Completed'): ?>
                                                            <li><a class="dropdown-item" href="#" onclick="editScore(<?php echo (int)$game['game_id']; ?>, '<?php echo addslashes($game['away_team_name']); ?>', '<?php echo addslashes($game['home_team_name']); ?>', <?php echo (int)$game['away_score']; ?>, <?php echo (int)$game['home_score']; ?>); return false;">
                                                                <i class="fas fa-edit"></i> Edit Score
                                                            </a></li>
                                                            <li><a class="dropdown-item text-danger" href="#" onclick="deleteScore(<?php echo (int)$game['game_id']; ?>, '<?php echo addslashes($game['game_number']); ?>', <?php echo (int)$game['away_score']; ?>, <?php echo (int)$game['home_score']; ?>); return false;">
                                                                <i class="fas fa-trash"></i> Delete Score
                                                            </a></li>
                                                        <?php endif; ?>
                                                        
                                                        <li><a class="dropdown-item" href="#" onclick="editGame(<?php echo htmlspecialchars(json_encode($game)); ?>)">
                                                            <i class="fas fa-cog"></i> Edit Game Details
                                                        </a></li>
                                                        
                                                        <?php if ($game['game_status'] === 'Scheduled' || $game['game_status'] === 'Created' || $game['game_status'] === 'Pending Change'): ?>
                                                            <li><hr class="dropdown-divider"></li>
                                                            <li><a class="dropdown-item" href="#" onclick="postponeGame(<?php echo $game['game_id']; ?>, '<?php echo addslashes($game['game_number']); ?>')">
                                                                <i class="fas fa-clock"></i> Postpone Game
                                                            </a></li>
                                                            <li><a class="dropdown-item text-danger" href="#" onclick="cancelGame(<?php echo $game['game_id']; ?>, '<?php echo addslashes($game['game_number']); ?>')">
                                                                <i class="fas fa-times"></i> Cancel Game
                                                            </a></li>
                                                        <?php endif; ?>
                                                        <li><hr class="dropdown-divider"></li>
                                                        <li><a class="dropdown-item text-danger" href="#" onclick="deleteGame(<?php echo (int)$game['game_id']; ?>, '<?php echo addslashes($game['game_number']); ?>', '<?php echo addslashes($game['away_team_name']); ?>', '<?php echo addslashes($game['home_team_name']); ?>'); return false;">
                                                            <i class="fas fa-trash-alt"></i> Delete Game
                                                        </a></li>
                                                    </ul>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr id="details-<?php echo $game['game_id']; ?>" class="schedule-history" style="display: none;">
                                        <td colspan="10">
                                            <div class="p-3">
                                                <h6><i class="fas fa-history"></i> Schedule History & Change Details</h6>
                                                <div id="history-content-<?php echo $game['game_id']; ?>">
                                                    <div class="text-center">
                                                        <div class="spinner-border spinner-border-sm" role="status">
                                                            <span class="visually-hidden">Loading...</span>
                                                        </div>
                                                        Loading schedule history...
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        </div><!-- /.table-responsive -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Game Modal -->
    <div class="modal fade" id="addGameModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Add New Game</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_game">
                        <input type="hidden" name="csrf_token" value="<?php echo Auth::generateCSRFToken(); ?>">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Season</label>
                                    <select name="season_id" class="form-select" required>
                                        <option value="">Select Season</option>
                                        <?php foreach ($seasons as $season): ?>
                                            <option value="<?php echo $season['season_id']; ?>">
                                                <?php echo sanitize($season['season_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Division</label>
                                    <select name="division_id" class="form-select">
                                        <option value="">Select Division (Optional)</option>
                                        <?php foreach ($divisions as $division): ?>
                                            <option value="<?php echo $division['division_id']; ?>">
                                                <?php echo sanitize($division['division_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Away Team</label>
                                    <select name="away_team_id" id="awayTeamId" class="form-select" required>
                                        <option value="">Select Away Team</option>
                                        <?php foreach ($teams as $team): ?>
                                            <option value="<?php echo $team['team_id']; ?>">
                                                <?php echo sanitize($team['display_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Home Team</label>
                                    <select name="home_team_id" id="homeTeamId" class="form-select" required>
                                        <option value="">Select Home Team</option>
                                        <?php foreach ($teams as $team): ?>
                                            <option value="<?php echo $team['team_id']; ?>">
                                                <?php echo sanitize($team['display_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Game Date</label>
                                    <input type="date" name="game_date" class="form-control" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Game Time</label>
                                    <input type="time" name="game_time" class="form-control" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Location</label>
                            <select name="location_id" id="addLocationSelect" class="form-select" required>
                                <option value="">-- Select Location --</option>
                                <?php foreach ($locations as $location): ?>
                                    <option value="<?php echo (int)$location['location_id']; ?>"><?php echo sanitize($location['location_name']); ?></option>
                                <?php endforeach; ?>
                                <option value="not-listed">(Not Listed)</option>
                            </select>
                            <div id="addLocationFields" style="display:none; margin-top: 8px;" class="p-3 border rounded bg-light">
                                <div class="mb-2">
                                    <label class="form-label">Location Name</label>
                                    <input type="text" name="location_name_new" id="addLocationNameNew" class="form-control form-control-sm">
                                </div>
                                <div class="mb-2">
                                    <label class="form-label">Address</label>
                                    <input type="text" name="location_address_new" class="form-control form-control-sm">
                                </div>
                                <div class="row">
                                    <div class="col-md-4 mb-2">
                                        <label class="form-label">City</label>
                                        <input type="text" name="location_city_new" id="addLocationCityNew" class="form-control form-control-sm" required>
                                    </div>
                                    <div class="col-md-4 mb-2">
                                        <label class="form-label">State</label>
                                        <input type="text" name="location_state_new" id="addLocationStateNew" class="form-control form-control-sm" required>
                                    </div>
                                    <div class="col-md-4 mb-2">
                                        <label class="form-label">Zip</label>
                                        <input type="text" name="location_zip_new" class="form-control form-control-sm">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3 px-3">
                        <label class="form-label">Notes <small class="text-muted">(admin-visible only, optional)</small></label>
                        <textarea name="game_notes" class="form-control" rows="3" placeholder="Enter any ancillary notes about this game..."></textarea>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Game</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Game Modal -->
    <div class="modal fade" id="editGameModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Game</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_game">
                        <input type="hidden" name="csrf_token" value="<?php echo Auth::generateCSRFToken(); ?>">
                        <input type="hidden" name="game_id" id="editGameId">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Game Number</label>
                                    <input type="text" id="editGameNumber" class="form-control" readonly>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Season</label>
                                    <select name="season_id" id="editSeasonId" class="form-select" required>
                                        <?php foreach ($seasons as $season): ?>
                                            <option value="<?php echo $season['season_id']; ?>">
                                                <?php echo sanitize($season['season_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Division</label>
                            <select name="division_id" id="editDivisionId" class="form-select">
                                <option value="">Select Division (Optional)</option>
                                <?php foreach ($divisions as $division): ?>
                                    <option value="<?php echo $division['division_id']; ?>">
                                        <?php echo sanitize($division['division_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Away Team</label>
                                    <select name="away_team_id" id="editAwayTeamId" class="form-select" required>
                                        <?php foreach ($teams as $team): ?>
                                            <option value="<?php echo $team['team_id']; ?>">
                                                <?php echo sanitize($team['display_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Home Team</label>
                                    <select name="home_team_id" id="editHomeTeamId" class="form-select" required>
                                        <?php foreach ($teams as $team): ?>
                                            <option value="<?php echo $team['team_id']; ?>">
                                                <?php echo sanitize($team['display_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Game Date</label>
                                    <input type="date" name="game_date" id="editGameDate" class="form-control" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Game Time</label>
                                    <input type="time" name="game_time" id="editGameTime" class="form-control" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Location</label>
                            <select name="location_id" id="editLocationSelect" class="form-select" required>
                                <option value="">-- Select Location --</option>
                                <?php foreach ($locations as $location): ?>
                                    <option value="<?php echo (int)$location['location_id']; ?>"><?php echo sanitize($location['location_name']); ?></option>
                                <?php endforeach; ?>
                                <option value="not-listed">(Not Listed)</option>
                            </select>
                            <div id="editLocationFields" style="display:none; margin-top: 8px;" class="p-3 border rounded bg-light">
                                <div class="mb-2">
                                    <label class="form-label">Location Name</label>
                                    <input type="text" name="location_name_new" id="editLocationNameNew" class="form-control form-control-sm">
                                </div>
                                <div class="mb-2">
                                    <label class="form-label">Address</label>
                                    <input type="text" name="location_address_new" class="form-control form-control-sm">
                                </div>
                                <div class="row">
                                    <div class="col-md-4 mb-2">
                                        <label class="form-label">City</label>
                                        <input type="text" name="location_city_new" id="editLocationCityNew" class="form-control form-control-sm" required>
                                    </div>
                                    <div class="col-md-4 mb-2">
                                        <label class="form-label">State</label>
                                        <input type="text" name="location_state_new" id="editLocationStateNew" class="form-control form-control-sm" required>
                                    </div>
                                    <div class="col-md-4 mb-2">
                                        <label class="form-label">Zip</label>
                                        <input type="text" name="location_zip_new" class="form-control form-control-sm">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3 px-3">
                        <label class="form-label">Notes <small class="text-muted">(admin-visible only, optional)</small></label>
                        <textarea name="game_notes" id="editGameNotes" class="form-control" rows="3" placeholder="Enter any ancillary notes about this game..."></textarea>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Game</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Score Update Modal -->
    <div class="modal fade" id="scoreModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Update Game Score</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_score">
                        <input type="hidden" name="csrf_token" value="<?php echo Auth::generateCSRFToken(); ?>">
                        <input type="hidden" name="game_id" id="scoreGameId">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label" id="awayTeamLabel">Away Team Score</label>
                                    <input type="number" name="away_score" id="awayScore" class="form-control" min="0" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label" id="homeTeamLabel">Home Team Score</label>
                                    <input type="number" name="home_score" id="homeScore" class="form-control" min="0" required>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">Update Score</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Cancel Game Modal -->
    <div class="modal fade" id="cancelGameModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Cancel Game</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="cancel_game">
                        <input type="hidden" name="csrf_token" value="<?php echo Auth::generateCSRFToken(); ?>">
                        <input type="hidden" name="game_id" id="cancelGameId">
                        
                        <p>Are you sure you want to cancel game <strong id="cancelGameNumber"></strong>?</p>
                        
                        <div class="mb-3">
                            <label class="form-label">Reason for Cancellation</label>
                            <textarea name="cancel_reason" class="form-control" rows="3" required 
                                      placeholder="Enter reason for cancelling this game..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Cancel Game</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Postpone Game Modal -->
    <div class="modal fade" id="postponeGameModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Postpone Game</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="postpone_game">
                        <input type="hidden" name="csrf_token" value="<?php echo Auth::generateCSRFToken(); ?>">
                        <input type="hidden" name="game_id" id="postponeGameId">
                        
                        <p>Are you sure you want to postpone game <strong id="postponeGameNumber"></strong>?</p>
                        
                        <div class="mb-3">
                            <label class="form-label">Reason for Postponement</label>
                            <textarea name="postpone_reason" class="form-control" rows="3" required 
                                      placeholder="Enter reason for postponing this game..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning">Postpone Game</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Delete Score Modal -->
    <div class="modal fade" id="deleteScoreModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Delete Game Score</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="delete_score">
                        <input type="hidden" name="csrf_token" value="<?php echo Auth::generateCSRFToken(); ?>">
                        <input type="hidden" name="game_id" id="deleteScoreGameId">
                        
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i> 
                            <strong>Warning:</strong> You are about to delete the score for game <strong id="deleteScoreGameNumber"></strong>.
                        </div>
                        
                        <p>Current score: <strong id="deleteScoreCurrentScore"></strong></p>
                        <p>This action will remove the score and change the game status back to "Active". Are you sure?</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Delete Score</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Game Modal -->
    <div class="modal fade" id="deleteGameModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Delete Game</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="delete_game">
                        <input type="hidden" name="csrf_token" value="<?php echo Auth::generateCSRFToken(); ?>">
                        <input type="hidden" name="game_id" id="deleteGameId">

                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle"></i>
                            <strong>Warning:</strong> You are about to permanently delete game <strong id="deleteGameNumber"></strong>.
                        </div>

                        <p><strong>Away:</strong> <span id="deleteGameAwayTeam"></span></p>
                        <p><strong>Home:</strong> <span id="deleteGameHomeTeam"></span></p>

                        <p class="text-danger"><strong>This action cannot be undone.</strong> The game record, any recorded scores, and all related schedule history and change requests will be permanently deleted.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger"><i class="fas fa-trash-alt"></i> Delete Game</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <script src="../../assets/js/timezone.js"></script>
    
    <?php outputTimezoneJS(); ?>
    
    <script>
        // Validate teams on form submission (not on individual change events)
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('#addGameModal form, #editGameModal form').forEach(function(form) {
                form.addEventListener('submit', function(e) {
                    var homeSelect = this.querySelector('select[name="home_team_id"]');
                    var awaySelect = this.querySelector('select[name="away_team_id"]');
                    if (!homeSelect || !awaySelect) return;

                    if (!homeSelect.value || !awaySelect.value) {
                        e.preventDefault();
                        alert('Please select both a home team and an away team.');
                        return;
                    }

                    if (homeSelect.value === awaySelect.value) {
                        e.preventDefault();
                        alert('Home team and away team cannot be the same.');
                    }
                });
            });
        });
    </script>
    
    <script>
        function toggleLocationFields(selectId, fieldsId, nameInputId, cityInputId, stateInputId) {
            var sel = document.getElementById(selectId);
            if (!sel) return;
            var fields = document.getElementById(fieldsId);
            var nameInput = document.getElementById(nameInputId);
            var cityInput = cityInputId ? document.getElementById(cityInputId) : null;
            var stateInput = stateInputId ? document.getElementById(stateInputId) : null;
            if (sel.value === 'not-listed') {
                if (fields) fields.style.display = 'block';
                if (nameInput) nameInput.required = true;
                if (cityInput) cityInput.required = true;
                if (stateInput) stateInput.required = true;
            } else {
                if (fields) fields.style.display = 'none';
                if (nameInput) nameInput.required = false;
                if (cityInput) cityInput.required = false;
                if (stateInput) stateInput.required = false;
            }
        }

        $(document).ready(function() {
            // Debug timezone functions
            console.log('formatDateTZ available:', typeof formatDateTZ);
            console.log('formatTimeTZ available:', typeof formatTimeTZ);
            console.log('appTimezone:', typeof appTimezone !== 'undefined' ? appTimezone : 'undefined');
            
            $('#gamesTable').DataTable({
                order: [[4, 'asc'], [5, 'asc'], [8, 'asc']], // Sort by Date, Time, Location ascending
                pageLength: 25,
                columnDefs: [
                    { orderable: false, targets: [0, 11] }, // Expand icon and Actions columns
                    { width: "30px", targets: [0] }, // Fixed width for expand icon column
                    { width: "120px", targets: [2] }, // Program column
                    { width: "150px", targets: [3] }, // Season column
                    { width: "100px", targets: [4, 5] } // Date and Time columns
                ],
                stateSave: false,
                stateDuration: 0 // Session storage (cleared when browser closes)
            });

            $('#addLocationSelect').on('change', function() {
                toggleLocationFields('addLocationSelect', 'addLocationFields', 'addLocationNameNew', 'addLocationCityNew', 'addLocationStateNew');
            });

            $('#editLocationSelect').on('change', function() {
                toggleLocationFields('editLocationSelect', 'editLocationFields', 'editLocationNameNew', 'editLocationCityNew', 'editLocationStateNew');
            });
        });

        // Native fallback: bind location toggle independently of jQuery/DataTable init
        document.addEventListener('DOMContentLoaded', function() {
            var addSel = document.getElementById('addLocationSelect');
            if (addSel) {
                addSel.addEventListener('change', function() {
                    toggleLocationFields('addLocationSelect', 'addLocationFields', 'addLocationNameNew', 'addLocationCityNew', 'addLocationStateNew');
                });
            }
            var editSel = document.getElementById('editLocationSelect');
            if (editSel) {
                editSel.addEventListener('change', function() {
                    toggleLocationFields('editLocationSelect', 'editLocationFields', 'editLocationNameNew', 'editLocationCityNew', 'editLocationStateNew');
                });
            }
        });
        
        function toggleGameDetails(gameId) {
            const detailsRow = document.getElementById(`details-${gameId}`);
            const expandIcon = document.getElementById(`icon-${gameId}`);
            const isVisible = detailsRow.style.display !== 'none';
            
            if (isVisible) {
                detailsRow.style.display = 'none';
                expandIcon.classList.remove('expanded');
            } else {
                detailsRow.style.display = 'table-row';
                expandIcon.classList.add('expanded');
                loadScheduleHistory(gameId);
            }
        }
        
        function loadScheduleHistory(gameId) {
            const contentDiv = document.getElementById(`history-content-${gameId}`);
            
            fetch(`?action=get_change_history&game_id=${gameId}`)
                .then(response => {
                    console.log('Response status:', response.status);
                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('Schedule history data:', data);
                    if (data.length === 0) {
                        contentDiv.innerHTML = '<p class="text-muted"><i class="fas fa-info-circle"></i> No schedule history found.</p>';
                        return;
                    }
                    
                    let html = '<div class="table-responsive"><table class="table table-sm">';
                    html += '<thead><tr><th>Version</th><th>Type</th><th>Date & Time</th><th>Location</th><th>Request ID</th><th>Notes</th></tr></thead><tbody>';
                    
                    data.forEach(history => {
                        const typeClass = history.schedule_type === 'Original' ? 'success' : 'info';
                        const currentBadge = history.is_current == 1 ? ' <span class="badge bg-primary">Current</span>' : '';
                        
                        html += `<tr>`;
                        html += `<td><strong>v${history.version_number}</strong>${currentBadge}</td>`;
                        
                        const typeHtml = `<span class="badge bg-${typeClass}">${history.schedule_type}</span>`;
                        html += `<td>${typeHtml}</td>`;
                        
                        // Date & Time
                        const formattedDate = typeof formatDateTZ === 'function' ? formatDateTZ(history.game_date) : history.game_date;
                        const formattedTime = typeof formatTimeTZ === 'function' ? formatTimeTZ(history.game_time) : history.game_time;
                        html += `<td><strong>${formattedDate}</strong><br>`;
                        html += `<small class="text-muted">${formattedTime}</small></td>`;
                        
                        // Location
                        html += `<td>${history.location || 'TBD'}</td>`;
                        
                        // Request ID
                        if (history.change_request_id) {
                            html += `<td><span class="badge bg-secondary">#${history.change_request_id}</span></td>`;
                        } else {
                            html += `<td><span class="text-muted">—</span></td>`;
                        }
                        
                        // Notes
                        let notes = history.notes || '';
                        if (history.reason) {
                            notes += (notes ? '<br>' : '') + '<strong>Reason:</strong> ' + history.reason;
                        }
                        if (history.request_status) {
                            const statusClass = history.request_status === 'Approved' ? 'success' : 
                                              history.request_status === 'Denied' ? 'danger' : 'warning';
                            notes += (notes ? '<br>' : '') + '<span class="badge bg-' + statusClass + '">' + history.request_status + '</span>';
                        }
                        let notesCell = `<small>${notes}</small>`;
                        if (history.user_notes) {
                            const escapedNote = history.user_notes.replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
                            notesCell += ` <i class="fas fa-sticky-note text-warning" title="${escapedNote}" style="cursor:pointer;"></i>`;
                        }
                        html += `<td>${notesCell}</td>`;
                        html += `</tr>`;
                    });
                    
                    html += '</tbody></table></div>';
                    contentDiv.innerHTML = html;
                })
                .catch(error => {
                    console.error('Error loading schedule history:', error);
                    contentDiv.innerHTML = '<p class="text-danger"><i class="fas fa-exclamation-triangle"></i> Error loading schedule history.</p>';
                });
        }
        
        function editGame(game) {
            document.getElementById('editGameId').value = game.game_id;
            document.getElementById('editGameNumber').value = game.game_number;
            document.getElementById('editSeasonId').value = game.season_id;
            document.getElementById('editDivisionId').value = game.division_id || '';
            
            
            document.getElementById('editAwayTeamId').value = game.away_team_id;
            document.getElementById('editHomeTeamId').value = game.home_team_id;
            document.getElementById('editGameDate').value = game.game_date;
            document.getElementById('editGameTime').value = game.game_time;
            
            // Set location select by matching game.location against option text
            var sel = document.getElementById('editLocationSelect');
            var matched = false;
            for (var i = 0; i < sel.options.length; i++) {
                if (sel.options[i].text === game.location) {
                    sel.selectedIndex = i;
                    matched = true;
                    break;
                }
            }
            if (!matched) {
                sel.value = 'not-listed';
                document.getElementById('editLocationNameNew').value = game.location || '';
            }
            sel.dispatchEvent(new Event('change'));
            
            document.getElementById('editGameNotes').value = game.current_user_notes || '';

            var editModal = new bootstrap.Modal(document.getElementById('editGameModal'));
            editModal.show();
        }
        
        function updateScore(gameId, awayTeam, homeTeam) {
            document.getElementById('scoreGameId').value = gameId;
            document.getElementById('awayTeamLabel').textContent = awayTeam + ' Score';
            document.getElementById('homeTeamLabel').textContent = homeTeam + ' Score';
            document.getElementById('awayScore').value = '';
            document.getElementById('homeScore').value = '';
            
            var scoreModal = new bootstrap.Modal(document.getElementById('scoreModal'));
            scoreModal.show();
        }
        
        function editScore(gameId, awayTeam, homeTeam, awayScore, homeScore) {
            document.getElementById('scoreGameId').value = gameId;
            document.getElementById('awayTeamLabel').textContent = awayTeam + ' Score';
            document.getElementById('homeTeamLabel').textContent = homeTeam + ' Score';
            document.getElementById('awayScore').value = awayScore;
            document.getElementById('homeScore').value = homeScore;
            
            var scoreModal = new bootstrap.Modal(document.getElementById('scoreModal'));
            scoreModal.show();
        }
        
        function cancelGame(gameId, gameNumber) {
            document.getElementById('cancelGameId').value = gameId;
            document.getElementById('cancelGameNumber').textContent = gameNumber;
            
            var cancelModal = new bootstrap.Modal(document.getElementById('cancelGameModal'));
            cancelModal.show();
        }
        
        function postponeGame(gameId, gameNumber) {
            document.getElementById('postponeGameId').value = gameId;
            document.getElementById('postponeGameNumber').textContent = gameNumber;
            
            var postponeModal = new bootstrap.Modal(document.getElementById('postponeGameModal'));
            postponeModal.show();
        }
        
        function deleteScore(gameId, gameNumber, awayScore, homeScore) {
            document.getElementById('deleteScoreGameId').value = gameId;
            document.getElementById('deleteScoreGameNumber').textContent = gameNumber;
            document.getElementById('deleteScoreCurrentScore').textContent = awayScore + ' - ' + homeScore;

            var deleteScoreModal = new bootstrap.Modal(document.getElementById('deleteScoreModal'));
            deleteScoreModal.show();
        }

        function deleteGame(gameId, gameNumber, awayTeam, homeTeam) {
            document.getElementById('deleteGameId').value = gameId;
            document.getElementById('deleteGameNumber').textContent = gameNumber;
            document.getElementById('deleteGameAwayTeam').textContent = awayTeam;
            document.getElementById('deleteGameHomeTeam').textContent = homeTeam;

            var deleteGameModal = new bootstrap.Modal(document.getElementById('deleteGameModal'));
            deleteGameModal.show();
        }
    </script>
</body>
</html>