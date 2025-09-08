<?php
/**
 * Filter Helper Functions
 * Handles program, division, and season filtering
 */

// Prevent direct access
if (!defined('D8TL_APP')) {
    die('Direct access not permitted');
}

class FilterHelpers {
    private static $db;

    public static function init() {
        self::$db = Database::getInstance();
    }

    /**
     * Get all active programs
     */
    public static function getActivePrograms() {
        return self::$db->fetchAll("
            SELECT DISTINCT p.*
            FROM programs p
            JOIN seasons s ON p.program_id = s.program_id
            WHERE p.active_status = 'Active'
            ORDER BY p.program_name
        ");
    }

    /**
     * Get seasons for a program
     */
    public static function getSeasons($programId = null) {
        $sql = "
            SELECT s.*, p.program_name
            FROM seasons s
            JOIN programs p ON s.program_id = p.program_id
            WHERE s.season_status IN ('Active', 'Planning', 'Registration')
        ";
        $params = [];

        if ($programId) {
            $sql .= " AND s.program_id = ?";
            $params[] = $programId;
        }

        $sql .= " ORDER BY s.season_year DESC, s.season_name";
        return self::$db->fetchAll($sql, $params);
    }

    /**
     * Get divisions for a season
     */
    public static function getDivisions($seasonId = null) {
        $sql = "
            SELECT d.*, s.season_name
            FROM divisions d
            JOIN seasons s ON d.season_id = s.season_id
        ";
        $params = [];

        if ($seasonId) {
            $sql .= " AND d.season_id = ?";
            $params[] = $seasonId;
        }

        $sql .= " ORDER BY d.division_name";
        return self::$db->fetchAll($sql, $params);
    }

    /**
     * Build filter SQL conditions
     */
    public static function buildFilterConditions($filters) {
        $conditions = [];
        $params = [];

        if (!empty($filters['program_id'])) {
            $conditions[] = "p.program_id = ?";
            $params[] = $filters['program_id'];
        }

        if (!empty($filters['season_id'])) {
            $conditions[] = "g.season_id = ?";
            $params[] = $filters['season_id'];
        }

        if (!empty($filters['division_id'])) {
            $conditions[] = "g.division_id = ?";
            $params[] = $filters['division_id'];
        }

        return [
            'conditions' => $conditions ? ' AND ' . implode(' AND ', $conditions) : '',
            'params' => $params
        ];
    }

    /**
     * Get filter values from URL parameters
     */
    public static function getFilterValues() {
        return [
            'program_id' => filter_input(INPUT_GET, 'program', FILTER_VALIDATE_INT) ?: null,
            'season_id' => filter_input(INPUT_GET, 'season', FILTER_VALIDATE_INT) ?: null,
            'division_id' => filter_input(INPUT_GET, 'division', FILTER_VALIDATE_INT) ?: null
        ];
    }

    /**
     * Build filter URL
     */
    public static function buildFilterUrl($baseUrl, $filters) {
        $params = [];
        foreach ($filters as $key => $value) {
            if ($value) {
                $params[] = str_replace('_id', '', $key) . '=' . urlencode($value);
            }
        }
        return $baseUrl . ($params ? '?' . implode('&', $params) : '');
    }

    /**
     * Validate filter values against database
     */
    public static function validateFilters($filters) {
        $valid = true;
        $errors = [];

        if ($filters['program_id']) {
            $program = self::$db->fetchOne(
                "SELECT program_id FROM programs WHERE program_id = ? AND active_status = 'Active'",
                [$filters['program_id']]
            );
            if (!$program) {
                $valid = false;
                $errors[] = "Invalid program selected";
            }
        }

        if ($filters['season_id']) {
            $season = self::$db->fetchOne(
                "SELECT season_id FROM seasons WHERE season_id = ? AND season_status IN ('Active', 'Planning', 'Registration')",
                [$filters['season_id']]
            );
            if (!$season) {
                $valid = false;
                $errors[] = "Invalid season selected";
            }
        }

        if ($filters['division_id']) {
            $division = self::$db->fetchOne(
                "SELECT division_id FROM divisions WHERE division_id = ?",
                [$filters['division_id']]
            );
            if (!$division) {
                $valid = false;
                $errors[] = "Invalid division selected";
            }
        }

        return [
            'valid' => $valid,
            'errors' => $errors
        ];
    }
}
