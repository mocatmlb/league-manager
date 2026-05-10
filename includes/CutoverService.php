<?php
/**
 * District 8 Travel League — Cutover Service
 *
 * Story 9.1: Pre-cutover gap checklist and shared credential disable operation.
 * Provides the safe, tested API for the admin cutover panel (Story 9.2).
 */

if (!defined('D8TL_APP')) {
    die('Direct access not permitted');
}

if (!class_exists('ActivityLogger')) {
    require_once __DIR__ . '/ActivityLogger.php';
}

/**
 * Thrown by disableSharedCredential() when one or more active-season teams
 * have no assigned Team Owner.
 */
class CutoverGapsRemainingException extends RuntimeException {
    private array $gaps;

    public function __construct(string $message, array $gaps = []) {
        parent::__construct($message);
        $this->gaps = $gaps;
    }

    public function getGaps(): array {
        return $this->gaps;
    }
}

class CutoverService {

    private Database $db;

    public function __construct(?Database $db = null) {
        $this->db = $db ?? Database::getInstance();
    }

    /**
     * Return all active-season teams with gap status.
     *
     * Each element contains:
     *   team_id, team_name, division_name, program_name,
     *   owners (array of ['user_id', 'first_name', 'last_name', 'email']),
     *   has_gap (bool — true when owners is empty)
     *
     * "Active-season" = team belongs to a season with season_status = 'Active'.
     */
    public function getGapChecklist(): array {
        try {
            // Fetch all active-season teams with division and program names.
            $teams = $this->db->fetchAll(
                "SELECT t.team_id,
                        COALESCE(t.team_name, t.league_name) AS team_name,
                        COALESCE(d.division_name, '') AS division_name,
                        COALESCE(p.program_name, '') AS program_name
                 FROM teams t
                 INNER JOIN seasons s ON s.season_id = t.season_id
                                     AND s.season_status = 'Active'
                 LEFT JOIN divisions d ON d.division_id = t.division_id
                 LEFT JOIN programs p ON p.program_id = s.program_id
                 ORDER BY p.program_name, d.division_name, t.team_id"
            );

            if (empty($teams)) {
                return [];
            }

            // Fetch all team_owners for these teams.
            $teamIds = array_column($teams, 'team_id');
            $placeholders = implode(',', array_fill(0, count($teamIds), '?'));
            $ownerRows = $this->db->fetchAll(
                "SELECT o.team_id, o.user_id,
                        u.first_name, u.last_name, u.email
                 FROM team_owners o
                 LEFT JOIN users u ON u.id = o.user_id
                 WHERE o.team_id IN ({$placeholders})",
                $teamIds
            );

            // Index owners by team_id.
            $ownersByTeam = [];
            if (is_array($ownerRows)) {
                foreach ($ownerRows as $row) {
                    $ownersByTeam[(int) $row['team_id']][] = [
                        'user_id'    => (int) $row['user_id'],
                        'first_name' => $row['first_name'],
                        'last_name'  => $row['last_name'],
                        'email'      => $row['email'],
                    ];
                }
            }

            $result = [];
            foreach ($teams as $team) {
                $tid    = (int) $team['team_id'];
                $owners = $ownersByTeam[$tid] ?? [];
                $result[] = [
                    'team_id'      => $tid,
                    'team_name'    => $team['team_name'],
                    'division_name'=> $team['division_name'],
                    'program_name' => $team['program_name'],
                    'owners'       => $owners,
                    'has_gap'      => count($owners) === 0,
                ];
            }

            return $result;
        } catch (Exception $e) {
            // Wrap PDO/DB exceptions in a domain-friendly message if needed, 
            // but here we just re-throw or log and return empty.
            // For this app's style, we'll log (if logger existed) and re-throw 
            // for the caller to catch.
            throw new RuntimeException("Error fetching gap checklist: " . $e->getMessage());
        }
    }

    /**
     * Return the count of active-season teams with zero assigned Team Owners.
     */
    public function getGapCount(): int {
        $row = $this->db->fetchOne(
            "SELECT COUNT(*) AS cnt
             FROM teams t
             INNER JOIN seasons s ON s.season_id = t.season_id
                                 AND s.season_status = 'Active'
             WHERE NOT EXISTS (
                 SELECT 1 FROM team_owners o WHERE o.team_id = t.team_id
             )"
        );
        return (int) ($row['cnt'] ?? 0);
    }

    /**
     * Disable the shared coaches credential.
     *
     * Sets the `coaches_password` setting to NULL so no auth path can use it.
     * Logs `admin.shared_credential_disabled`.
     *
     * @throws CutoverGapsRemainingException if any active-season team has no owner.
     */
    public function disableSharedCredential(int $adminUserId): bool {
        if ($adminUserId <= 0) {
            throw new InvalidArgumentException('Invalid admin user ID');
        }

        $checklist = $this->getGapChecklist();
        $gaps = array_filter($checklist, fn($t) => $t['has_gap']);

        if (!empty($gaps)) {
            throw new CutoverGapsRemainingException(
                'Cannot disable shared credential: one or more active-season teams have no assigned Team Owner.',
                array_values($gaps)
            );
        }

        $this->db->beginTransaction();
        try {
            $this->db->query(
                "UPDATE settings SET setting_value = NULL WHERE setting_key = 'coaches_password'"
            );

            ActivityLogger::log('admin.shared_credential_disabled', [
                'admin_user_id' => $adminUserId,
                'disabled_at'   => gmdate('Y-m-d H:i:s'), // UTC
            ]);

            $this->db->commit();
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }

        return true;
    }

    /**
     * Return true if the shared coaches credential is still active (non-null, non-empty).
     */
    public function isSharedCredentialActive(): bool {
        $row = $this->db->fetchOne(
            "SELECT setting_value FROM settings WHERE setting_key = 'coaches_password'"
        );
        if ($row === false || $row === null) {
            return false;
        }
        $value = $row['setting_value'] ?? null;
        return $value !== null && $value !== '';
    }
}
