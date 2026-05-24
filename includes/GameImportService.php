<?php

namespace D8TL;

use Database;

/**
 * Handles validation and bulk insertion of games imported from CSV.
 */
class GameImportService
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /**
     * Validate parsed CSV rows (associative arrays keyed by header name).
     *
     * Returns:
     *   [
     *     'errors'    => [['row' => N, 'errors' => ['msg', ...]], ...],
     *     'validated' => [['season_id' => ..., 'division_id' => ..., ...], ...]
     *   ]
     * 'validated' is populated only when 'errors' is empty.
     */
    public function validateRows(array $rows): array
    {
        $requiredHeaders = [
            'season_year', 'season_name', 'division_name',
            'home_team', 'away_team', 'game_date', 'game_time', 'location_name',
        ];
        // 'notes' is optional — present or absent, blank or filled, all are valid

        // Pre-fetch lookup tables once to avoid N+1 queries
        $seasons   = $this->db->fetchAll("SELECT season_id, season_name, season_year FROM seasons");
        $divisions = $this->db->fetchAll("SELECT division_id, division_name, season_id FROM divisions");
        $teams     = $this->db->fetchAll("SELECT team_id, team_name, season_id FROM teams WHERE active_status = 'Active'");
        $locations = $this->db->fetchAll("SELECT location_id, location_name FROM locations WHERE active_status = 'Active'");
        $existingFixtures = $this->db->fetchAll(
            "SELECT g.season_id, g.division_id, g.home_team_id, g.away_team_id, s.game_date, s.game_time, s.location_id
             FROM games g
             INNER JOIN schedules s ON s.game_id = g.game_id"
        );

        // Build indexed lookup maps
        // Seasons: "year|name" => season_id
        $seasonMap = [];
        foreach ($seasons as $s) {
            $key = $s['season_year'] . '|' . mb_strtolower(trim($s['season_name']));
            $seasonMap[$key] = (int)$s['season_id'];
        }

        // Divisions: "season_id|division_name" => division_id
        $divisionMap = [];
        foreach ($divisions as $d) {
            $key = $d['season_id'] . '|' . mb_strtolower(trim($d['division_name']));
            $divisionMap[$key] = (int)$d['division_id'];
        }

        // Teams: "season_id|team_name" => team_id, or '__DUPLICATE__' if multiple
        $teamMap = [];
        foreach ($teams as $t) {
            $key = $t['season_id'] . '|' . mb_strtolower(trim($t['team_name']));
            if (isset($teamMap[$key])) {
                $teamMap[$key] = '__DUPLICATE__';
            } else {
                $teamMap[$key] = (int)$t['team_id'];
            }
        }
        // Locations: location_name (lowercase) => location_id
        $locationMap = [];
        foreach ($locations as $l) {
            $locationMap[mb_strtolower(trim($l['location_name']))] = (int)$l['location_id'];
        }

        // Existing fixtures: signature => true
        $existingFixtureMap = [];
        foreach ($existingFixtures as $fixture) {
            $signature = $this->buildFixtureSignature(
                (int)$fixture['season_id'],
                isset($fixture['division_id']) ? (int)$fixture['division_id'] : null,
                (int)$fixture['home_team_id'],
                (int)$fixture['away_team_id'],
                (string)$fixture['game_date'],
                (string)$fixture['game_time'],
                isset($fixture['location_id']) ? (int)$fixture['location_id'] : null
            );
            $existingFixtureMap[$signature] = true;
        }

        // Track duplicates within the uploaded CSV: signature => first row number
        $importFixtureMap = [];

        $errors    = [];
        $validated = [];

        foreach ($rows as $idx => $row) {
            $rowNum    = $idx + 2; // 1-based data row (row 1 is header)
            $rowErrors = [];

            // Check all required columns present and non-empty
            foreach ($requiredHeaders as $col) {
                if (!isset($row[$col]) || trim($row[$col]) === '') {
                    $rowErrors[] = "Column '{$col}' is missing or empty.";
                }
            }

            if (!empty($rowErrors)) {
                $errors[] = ['row' => $rowNum, 'errors' => $rowErrors];
                continue;
            }

            $seasonYear   = trim($row['season_year']);
            $seasonName   = trim($row['season_name']);
            $divisionName = trim($row['division_name']);
            $homeTeamName = trim($row['home_team']);
            $awayTeamName = trim($row['away_team']);
            $gameDate     = trim($row['game_date']);
            $gameTime     = trim($row['game_time']);
            $locationName = trim($row['location_name']);

            // season_year: 4-digit year
            if (!preg_match('/^\d{4}$/', $seasonYear)) {
                $rowErrors[] = "season_year '{$seasonYear}' is not a valid 4-digit year.";
            }

            // game_date: YYYY-MM-DD
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $gameDate)) {
                $rowErrors[] = "game_date '{$gameDate}' must be in YYYY-MM-DD format.";
            } else {
                [$y, $m, $d] = explode('-', $gameDate);
                if (!checkdate((int)$m, (int)$d, (int)$y)) {
                    $rowErrors[] = "game_date '{$gameDate}' is not a valid calendar date.";
                }
            }

            // game_time: HH:MM (00:00–23:59)
            if (!preg_match('/^\d{2}:\d{2}$/', $gameTime)) {
                $rowErrors[] = "game_time '{$gameTime}' must be in HH:MM (24-hour) format.";
            } else {
                [$h, $min] = explode(':', $gameTime);
                if ((int)$h > 23 || (int)$min > 59) {
                    $rowErrors[] = "game_time '{$gameTime}' is out of range (00:00–23:59).";
                }
            }

            // Resolve season
            $seasonKey = $seasonYear . '|' . mb_strtolower($seasonName);
            $seasonId  = $seasonMap[$seasonKey] ?? null;
            if ($seasonId === null) {
                $rowErrors[] = "Season '{$seasonName} {$seasonYear}' not found.";
            }

            // Resolve division (only if season resolved)
            $divisionId = null;
            if ($seasonId !== null) {
                $divKey     = $seasonId . '|' . mb_strtolower($divisionName);
                $divisionId = $divisionMap[$divKey] ?? null;
                if ($divisionId === null) {
                    $rowErrors[] = "Division '{$divisionName}' not found in season '{$seasonName} {$seasonYear}'.";
                }
            }

            // Resolve home team
            $homeTeamId = null;
            if ($seasonId !== null) {
                $homeKey    = $seasonId . '|' . mb_strtolower($homeTeamName);
                $homeTeamId = $teamMap[$homeKey] ?? null;
                if ($homeTeamId === null) {
                    $rowErrors[] = "Home team '{$homeTeamName}' not found as an active team in season '{$seasonName} {$seasonYear}'.";
                } elseif ($homeTeamId === '__DUPLICATE__') {
                    $rowErrors[] = "Multiple teams found matching '{$homeTeamName}' in {$seasonName} {$seasonYear} — team names must be unique within a season for import to work.";
                    $homeTeamId = null;
                }
            }

            // Resolve away team
            $awayTeamId = null;
            if ($seasonId !== null) {
                $awayKey    = $seasonId . '|' . mb_strtolower($awayTeamName);
                $awayTeamId = $teamMap[$awayKey] ?? null;
                if ($awayTeamId === null) {
                    $rowErrors[] = "Away team '{$awayTeamName}' not found as an active team in season '{$seasonName} {$seasonYear}'.";
                } elseif ($awayTeamId === '__DUPLICATE__') {
                    $rowErrors[] = "Multiple teams found matching '{$awayTeamName}' in {$seasonName} {$seasonYear} — team names must be unique within a season for import to work.";
                    $awayTeamId = null;
                }
            }

            // Home ≠ away (only meaningful when both resolved)
            if ($homeTeamId !== null && $awayTeamId !== null && $homeTeamId === $awayTeamId) {
                $rowErrors[] = "Home team and away team cannot be the same (both resolved to '{$homeTeamName}').";
            }

            // Resolve location
            $locationId  = $locationMap[mb_strtolower($locationName)] ?? null;
            if ($locationId === null) {
                $rowErrors[] = "Location '{$locationName}' not found in active locations.";
            }

            // Enforce strict duplicate prevention (same teams, date/time, location, season/division)
            if (empty($rowErrors)) {
                $fixtureSignature = $this->buildFixtureSignature(
                    $seasonId,
                    $divisionId,
                    $homeTeamId,
                    $awayTeamId,
                    $gameDate,
                    $gameTime,
                    $locationId
                );

                if (isset($importFixtureMap[$fixtureSignature])) {
                    $firstRowNum = $importFixtureMap[$fixtureSignature];
                    $rowErrors[] = "Duplicate fixture in CSV — this row matches row {$firstRowNum}.";
                } elseif (isset($existingFixtureMap[$fixtureSignature])) {
                    $rowErrors[] = 'Duplicate fixture already exists in the schedule.';
                } else {
                    $importFixtureMap[$fixtureSignature] = $rowNum;
                }
            }

            if (!empty($rowErrors)) {
                $errors[] = ['row' => $rowNum, 'errors' => $rowErrors];
                continue;
            }

            $userNotes = (isset($row['notes']) && trim($row['notes']) !== '') ? trim($row['notes']) : null;

            $validated[] = [
                'season_id'       => $seasonId,
                'division_id'     => $divisionId,
                'home_team_id'    => $homeTeamId,
                'away_team_id'    => $awayTeamId,
                'location_id'     => $locationId,
                'game_date'       => $gameDate,
                'game_time'       => $gameTime,
                'user_notes'      => $userNotes,
                // Display fields for preview
                'season_display'  => $seasonName . ' ' . $seasonYear,
                'division_name'   => $divisionName,
                'home_team_name'  => $homeTeamName,
                'away_team_name'  => $awayTeamName,
                'location_name'   => $locationName,
            ];
        }

        return ['errors' => $errors, 'validated' => $validated];
    }

    private function buildFixtureSignature(
        int $seasonId,
        ?int $divisionId,
        int $homeTeamId,
        int $awayTeamId,
        string $gameDate,
        string $gameTime,
        ?int $locationId
    ): string {
        $normalizedTime = substr(trim($gameTime), 0, 5);

        return implode('|', [
            $seasonId,
            $divisionId ?? 'null',
            $homeTeamId,
            $awayTeamId,
            trim($gameDate),
            $normalizedTime,
            $locationId ?? 'null',
        ]);
    }

    /**
     * Insert all validated rows inside a single transaction.
     * Returns the count of games inserted.
     * Throws on any failure (caller should catch and display error).
     */
    public function importRows(array $validatedRows): int
    {
        $this->db->beginTransaction();
        $count = 0;

        try {
            foreach ($validatedRows as $row) {
                // Auto-generate game number (same pattern as games/index.php)
                $year = (int)date('Y');
                $this->db->query(
                    "INSERT INTO game_number_sequences (seq_year, last_seq) VALUES (?, 1)
                     ON DUPLICATE KEY UPDATE last_seq = last_seq + 1",
                    [$year]
                );
                $seqRow     = $this->db->fetchOne(
                    "SELECT last_seq FROM game_number_sequences WHERE seq_year = ?",
                    [$year]
                );
                $lastSeq    = (int)($seqRow['last_seq'] ?? 0);
                if ($lastSeq < 1 || $lastSeq > 9999) {
                    throw new \RuntimeException("Unable to generate game number for {$year}: yearly sequence exceeded YYYYNNNN limits.");
                }
                $gameNumber = sprintf('%04d%04d', $year, $lastSeq);

                $gameId = $this->db->insert('games', [
                    'game_number'  => $gameNumber,
                    'season_id'    => $row['season_id'],
                    'division_id'  => $row['division_id'],
                    'home_team_id' => $row['home_team_id'],
                    'away_team_id' => $row['away_team_id'],
                    'game_status'  => 'Scheduled',
                    'created_date' => date('Y-m-d H:i:s'),
                ]);

                $this->db->insert('schedules', [
                    'game_id'      => $gameId,
                    'game_date'    => $row['game_date'],
                    'game_time'    => $row['game_time'],
                    'location'     => $row['location_name'],
                    'location_id'  => $row['location_id'],
                    'created_date' => date('Y-m-d H:i:s'),
                ]);

                $this->db->insert('schedule_history', [
                    'game_id'        => $gameId,
                    'version_number' => 1,
                    'schedule_type'  => 'Original',
                    'game_date'      => $row['game_date'],
                    'game_time'      => $row['game_time'],
                    'location'       => $row['location_name'],
                    'location_id'    => $row['location_id'],
                    'is_current'     => 1,
                    'created_at'     => date('Y-m-d H:i:s'),
                    'notes'          => 'Initial game schedule',
                    'user_notes'     => $row['user_notes'] ?? null,
                ]);

                logActivity(
                    'game_imported',
                    "Game {$gameNumber} imported via bulk import: {$row['away_team_name']} vs {$row['home_team_name']}"
                );

                $count++;
            }

            $this->db->commit();
        } catch (\Throwable $e) {
            try { $this->db->rollback(); } catch (\Throwable $ignored) {}
            throw $e;
        }

        return $count;
    }
}
