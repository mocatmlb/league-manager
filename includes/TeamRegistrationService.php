<?php
/**
 * District 8 Travel League - Team Registration Service
 *
 * Handles pending team creation, home field location submission, and admin approval.
 */

if (!defined('D8TL_APP')) {
    die('Direct access not permitted');
}

if (!class_exists('ActivityLogger')) {
    require_once __DIR__ . '/ActivityLogger.php';
}
if (!class_exists('Logger')) {
    require_once __DIR__ . '/Logger.php';
}

class InvitationRegisteredUserException extends RuntimeException {}
class TeamAlreadyClaimedException extends RuntimeException {}

class TeamRegistrationService {
    private Database $db;
    private object $emailService;

    public function __construct(?Database $db = null, ?object $emailService = null) {
        $this->db = $db ?? Database::getInstance();
        if ($emailService !== null) {
            $this->emailService = $emailService;
            return;
        }
        if (!class_exists('EmailService')) {
            require_once __DIR__ . '/EmailService.php';
        }
        $this->emailService = new EmailService();
    }

    public function submit(int $userId, array $data): int {
        // 1. Fetch coach from users table
        $user = $this->db->fetchOne(
            'SELECT id, first_name, last_name, email FROM users WHERE id = :id LIMIT 1',
            ['id' => $userId]
        );
        if ($user === false) {
            throw new RuntimeException('User not found.');
        }

        // 2. Detect invitation-registered user (AC2)
        $invite = $this->db->fetchOne(
            "SELECT id FROM user_invitations WHERE email = :email AND status = 'completed' LIMIT 1",
            ['email' => $user['email']]
        );
        if ($invite !== false) {
            throw new InvitationRegisteredUserException('Invitation-registered coaches cannot self-register a team.');
        }

        // 3. Guard: one team per season (pending or active records block a new submission)
        $dupCheck = $this->db->fetchOne(
            "SELECT COUNT(*) AS cnt FROM teams
             WHERE submitted_by_user_id = :uid AND season_id = :sid AND status IN ('pending', 'active')",
            ['uid' => $userId, 'sid' => (int) $data['season_id']]
        );
        if ((int) ($dupCheck['cnt'] ?? 0) > 0) {
            throw new RuntimeException('You already have a team registration for this season.');
        }

        // 4. Determine league name — 'other' sentinel means use manually-entered value
        $leagueName = (strtolower(trim((string) ($data['league_name'] ?? ''))) === 'other')
            ? trim((string) ($data['other_league'] ?? ''))
            : trim((string) ($data['league_name'] ?? ''));

        // 5. Auto-generate team name: {league_name}-{last_name}
        $teamName = $leagueName . '-' . $user['last_name'];

        // 6–7. INSERT team and locations in one transaction (activity log runs after commit)
        $conn = $this->db->getConnection();
        $conn->beginTransaction();
        try {
            $this->db->query(
                "INSERT INTO teams (season_id, league_name, team_name, status,
                                    submitted_by_user_id,
                                    manager_first_name, manager_last_name, manager_email,
                                    created_date)
                 VALUES (:season_id, :league_name, :team_name, 'pending',
                         :submitted_by_user_id,
                         :manager_first_name, :manager_last_name, :manager_email,
                         NOW())",
                [
                    'season_id'           => (int) $data['season_id'],
                    'league_name'         => $leagueName,
                    'team_name'           => $teamName,
                    'submitted_by_user_id' => $userId,
                    'manager_first_name'  => $user['first_name'],
                    'manager_last_name'   => $user['last_name'],
                    'manager_email'       => $user['email'],
                ]
            );
            $teamId = (int) $conn->lastInsertId();

            $locations = array_slice((array) ($data['locations'] ?? []), 0, 5);
            foreach ($locations as $loc) {
                $locName = trim((string) ($loc['name'] ?? ''));
                if ($locName === '') continue;
                $this->db->query(
                    "INSERT INTO locations (location_name, address, notes,
                                            submitted_by_user_id, status, created_date)
                     VALUES (:location_name, :address, :notes,
                             :submitted_by_user_id, 'pending', NOW())",
                    [
                        'location_name'        => $locName,
                        'address'              => trim((string) ($loc['address'] ?? '')) ?: null,
                        'notes'                => trim((string) ($loc['notes'] ?? '')) ?: null,
                        'submitted_by_user_id' => $userId,
                    ]
                );
            }

            $conn->commit();
        } catch (Throwable $e) {
            $conn->rollBack();
            throw $e;
        }

        // 8. Log and return
        ActivityLogger::log('team.registration_submitted', ['user_id' => $userId, 'team_id' => $teamId]);
        return $teamId;
    }

    public function approve(int $teamId, int $adminUserId, int $divisionId): void {
        // 1. Load pending team and resolve coach before mutating rows
        $team = $this->db->fetchOne(
            'SELECT team_id, team_name, manager_email, status, season_id FROM teams WHERE team_id = :id LIMIT 1',
            ['id' => $teamId]
        );
        if ($team === false) {
            throw new RuntimeException('Team not found.');
        }
        if (($team['status'] ?? '') !== 'pending') {
            throw new RuntimeException('Team is not pending approval.');
        }

        $teamSeasonId = (int) ($team['season_id'] ?? 0);
        $divisionOk = $this->db->fetchOne(
            'SELECT division_id FROM divisions WHERE division_id = :division_id AND season_id = :season_id LIMIT 1',
            ['division_id' => $divisionId, 'season_id' => $teamSeasonId]
        );
        if ($divisionOk === false) {
            throw new RuntimeException('Selected division is not valid for this team\'s season.');
        }

        $coachUser = $this->db->fetchOne(
            'SELECT id, first_name, email FROM users WHERE email = :email LIMIT 1',
            ['email' => $team['manager_email']]
        );
        if ($coachUser === false) {
            throw new RuntimeException('Coach account not found for team.');
        }
        $coachUserId = (int) $coachUser['id'];

        // 2. Guard: enforce 1:1 user-to-team at app layer (AR-5)
        $existing = $this->db->fetchOne(
            'SELECT team_id FROM team_owners WHERE user_id = :user_id LIMIT 1',
            ['user_id' => $coachUserId]
        );
        if ($existing !== false) {
            throw new TeamAlreadyClaimedException('This coach is already assigned to a team.');
        }

        // 3. Activate team and assign Team Owner
        $this->db->query(
            "UPDATE teams SET status = 'active', division_id = :division_id WHERE team_id = :team_id",
            ['division_id' => $divisionId, 'team_id' => $teamId]
        );

        $this->db->query(
            'INSERT INTO team_owners (user_id, team_id, assigned_by, created_at)
             VALUES (:user_id, :team_id, :assigned_by, NOW())',
            ['user_id' => $coachUserId, 'team_id' => $teamId, 'assigned_by' => $adminUserId]
        );

        // 4. Audit log
        ActivityLogger::log('team.registration_approved', [
            'team_id'       => $teamId,
            'admin_user_id' => $adminUserId,
            'division_id'   => $divisionId,
        ]);
        ActivityLogger::log('team.owner_assigned', [
            'user_id'       => $coachUserId,
            'team_id'       => $teamId,
            'admin_user_id' => $adminUserId,
        ]);

        // 5. Operational email — failure is logged only, never surfaced (AR-12)
        try {
            $this->emailService->triggerNotificationToAddress(
                'team_registration_approved',
                $coachUser['email'],
                ['user_id' => $coachUserId, 'team_id' => $teamId, 'first_name' => $coachUser['first_name']]
            );
        } catch (Throwable $e) {
            error_log('[TeamRegistrationService] Approval email failed: ' . $e->getMessage());
        }
    }

    public function reject(int $teamId, int $adminUserId, string $reason = ''): void {
        $team = $this->db->fetchOne(
            'SELECT team_id, team_name, manager_first_name, manager_email, status
             FROM teams WHERE team_id = :id LIMIT 1',
            ['id' => $teamId]
        );
        if ($team === false) {
            throw new RuntimeException('Team not found.');
        }
        if (($team['status'] ?? '') !== 'pending') {
            throw new RuntimeException('Team is not pending approval.');
        }

        $conn = $this->db->getConnection();
        $conn->beginTransaction();
        try {
            $this->db->query(
                "UPDATE teams SET status = 'rejected', modified_date = NOW() WHERE team_id = :team_id",
                ['team_id' => $teamId]
            );

            ActivityLogger::log('team.registration_rejected', [
                'team_id'       => $teamId,
                'admin_user_id' => $adminUserId,
            ]);

            $conn->commit();
        } catch (Throwable $e) {
            $conn->rollBack();
            throw $e;
        }

        try {
            $this->emailService->triggerNotificationToAddress(
                'team_registration_rejected',
                $team['manager_email'],
                [
                    'first_name' => $team['manager_first_name'],
                    'team_name'  => $team['team_name'],
                    'reason'     => $reason !== '' ? $reason : 'No reason provided.',
                ]
            );
        } catch (Throwable $e) {
            error_log('[TeamRegistrationService] Rejection email failed: ' . $e->getMessage());
        }
    }

    public function adminCreate(int $targetUserId, array $data, int $adminUserId): int {
        $user = $this->db->fetchOne(
            'SELECT id, first_name, last_name, email, phone FROM users WHERE id = :id LIMIT 1',
            ['id' => $targetUserId]
        );
        if ($user === false) {
            throw new RuntimeException('User not found.');
        }

        $leagueName = trim((string) ($data['league_name'] ?? ''));
        $teamName   = trim((string) ($data['team_name'] ?? ''));
        if ($teamName === '') {
            $teamName = $leagueName . '-' . $user['last_name'];
        }

        $conn = $this->db->getConnection();
        $conn->beginTransaction();
        try {
            $this->db->query(
                "INSERT INTO teams (season_id, league_name, team_name, status,
                                    submitted_by_user_id,
                                    manager_first_name, manager_last_name, manager_email,
                                    manager_phone,
                                    created_date)
                 VALUES (:season_id, :league_name, :team_name, 'pending',
                         :submitted_by_user_id,
                         :manager_first_name, :manager_last_name, :manager_email,
                         :manager_phone,
                         NOW())",
                [
                    'season_id'            => (int) $data['season_id'],
                    'league_name'          => $leagueName,
                    'team_name'            => $teamName,
                    'submitted_by_user_id' => $targetUserId,
                    'manager_first_name'   => $user['first_name'],
                    'manager_last_name'    => $user['last_name'],
                    'manager_email'        => $user['email'],
                    'manager_phone'        => $user['phone'] ?? null,
                ]
            );
            $teamId = (int) $conn->lastInsertId();
            $conn->commit();
        } catch (Throwable $e) {
            $conn->rollBack();
            throw $e;
        }

        ActivityLogger::log('admin.team_registration_created', [
            'team_id'       => $teamId,
            'user_id'       => $targetUserId,
            'admin_user_id' => $adminUserId,
        ]);
        return $teamId;
    }

    public function update(int $teamId, array $data, int $adminUserId): void {
        $team = $this->db->fetchOne(
            'SELECT team_id, status FROM teams WHERE team_id = :id LIMIT 1',
            ['id' => $teamId]
        );
        if ($team === false) {
            throw new RuntimeException('Team not found.');
        }
        if (($team['status'] ?? '') === 'active') {
            throw new RuntimeException('Only pending or rejected registrations can be edited.');
        }

        $this->db->query(
            'UPDATE teams SET team_name = :team_name, season_id = :season_id,
                              league_name = :league_name,
                              submitted_by_user_id = :submitted_by_user_id,
                              modified_date = NOW()
             WHERE team_id = :team_id',
            [
                'team_name'            => trim((string) ($data['team_name'] ?? '')),
                'season_id'            => (int) ($data['season_id'] ?? 0),
                'league_name'          => trim((string) ($data['league_name'] ?? '')),
                'submitted_by_user_id' => (int) ($data['submitted_by_user_id'] ?? 0),
                'team_id'              => $teamId,
            ]
        );

        ActivityLogger::log('admin.team_registration_updated', [
            'team_id'       => $teamId,
            'admin_user_id' => $adminUserId,
        ]);
    }

    public function deleteRegistration(int $teamId, int $adminUserId): void {
        $team = $this->db->fetchOne(
            'SELECT team_id FROM teams WHERE team_id = :id LIMIT 1',
            ['id' => $teamId]
        );
        if ($team === false) {
            throw new RuntimeException('Team not found.');
        }

        $gameCount = $this->db->fetchOne(
            'SELECT COUNT(*) AS cnt FROM games WHERE home_team_id = :id OR away_team_id = :id2',
            ['id' => $teamId, 'id2' => $teamId]
        );
        if ((int) ($gameCount['cnt'] ?? 0) > 0) {
            throw new RuntimeException('Cannot delete: team has game assignments.');
        }

        $conn = $this->db->getConnection();
        $conn->beginTransaction();
        try {
            // Delete associated locations (Story 11.7 Patch 4)
            $this->db->query(
                'DELETE FROM team_locations WHERE team_id = :team_id',
                ['team_id' => $teamId]
            );

            $owner = $this->db->fetchOne(
                'SELECT user_id FROM team_owners WHERE team_id = :team_id LIMIT 1',
                ['team_id' => $teamId]
            );

            if ($owner !== false) {
                $ownerId = (int) $owner['user_id'];
                $this->db->query(
                    'DELETE FROM team_owners WHERE team_id = :team_id',
                    ['team_id' => $teamId]
                );

                $remaining = $this->db->fetchOne(
                    'SELECT COUNT(*) AS cnt FROM team_owners WHERE user_id = :user_id',
                    ['user_id' => $ownerId]
                );
                if ((int) ($remaining['cnt'] ?? 0) === 0) {
                    $roleRow = $this->db->fetchOne(
                        "SELECT id FROM roles WHERE name = 'user' LIMIT 1"
                    );
                    if ($roleRow !== false) {
                        $this->db->query(
                            'UPDATE users SET role_id = :role_id WHERE id = :id',
                            ['role_id' => (int) $roleRow['id'], 'id' => $ownerId]
                        );
                    }
                }
            }

            $this->db->query(
                'DELETE FROM teams WHERE team_id = :team_id',
                ['team_id' => $teamId]
            );

            $conn->commit();
        } catch (Throwable $e) {
            $conn->rollBack();
            throw $e;
        }

        ActivityLogger::log('admin.team_registration_deleted', [
            'team_id'       => $teamId,
            'admin_user_id' => $adminUserId,
        ]);
    }

    public function getPendingRegistrations(): array {
        return $this->db->fetchAll(
            "SELECT t.team_id, t.team_name, t.league_name, t.season_id,
                    t.manager_first_name, t.manager_last_name, t.manager_email,
                    t.submitted_by_user_id,
                    t.created_date,
                    u.first_name  AS submitter_first_name,
                    u.last_name   AS submitter_last_name,
                    s.season_name, s.season_year,
                    p.program_name
             FROM teams t
             LEFT JOIN users u    ON u.id            = t.submitted_by_user_id
             LEFT JOIN seasons s  ON s.season_id     = t.season_id
             LEFT JOIN programs p ON p.program_id    = s.program_id
             WHERE t.status = 'pending'
             ORDER BY t.created_date ASC"
        );
    }

    public function getRejectedRegistrations(): array {
        return $this->db->fetchAll(
            "SELECT t.team_id, t.team_name, t.league_name, t.season_id,
                    t.submitted_by_user_id,
                    t.modified_date,
                    u.first_name  AS submitter_first_name,
                    u.last_name   AS submitter_last_name,
                    s.season_name, s.season_year,
                    p.program_name
             FROM teams t
             LEFT JOIN users u    ON u.id            = t.submitted_by_user_id
             LEFT JOIN seasons s  ON s.season_id     = t.season_id
             LEFT JOIN programs p ON p.program_id    = s.program_id
             WHERE t.status = 'rejected'
             ORDER BY t.modified_date DESC"
        );
    }
}
