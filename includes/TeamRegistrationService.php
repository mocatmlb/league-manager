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

        // 3. Determine league name — 'other' sentinel means use manually-entered value
        $leagueName = (strtolower(trim((string) ($data['league_name'] ?? ''))) === 'other')
            ? trim((string) ($data['other_league'] ?? ''))
            : trim((string) ($data['league_name'] ?? ''));

        // 4. Auto-generate team name: {league_name}-{last_name}
        $teamName = $leagueName . '-' . $user['last_name'];

        // 5–6. INSERT team and locations in one transaction (activity log runs after commit)
        $conn = $this->db->getConnection();
        $conn->beginTransaction();
        try {
            $this->db->query(
                "INSERT INTO teams (season_id, league_name, team_name, status,
                                    manager_first_name, manager_last_name, manager_email,
                                    created_date)
                 VALUES (:season_id, :league_name, :team_name, 'pending',
                         :manager_first_name, :manager_last_name, :manager_email,
                         NOW())",
                [
                    'season_id'           => (int) $data['season_id'],
                    'league_name'         => $leagueName,
                    'team_name'           => $teamName,
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

        // 7. Log and return
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

    public function getPendingRegistrations(): array {
        return $this->db->fetchAll(
            "SELECT t.team_id, t.team_name, t.league_name, t.season_id,
                    t.manager_first_name, t.manager_last_name, t.manager_email,
                    t.created_date,
                    u.first_name AS submitter_first_name,
                    u.last_name  AS submitter_last_name
             FROM teams t
             LEFT JOIN users u ON u.email = t.manager_email
             WHERE t.status = 'pending'
             ORDER BY t.created_date ASC"
        );
    }
}
