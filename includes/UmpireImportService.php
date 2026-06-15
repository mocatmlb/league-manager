<?php
if (!defined('D8TL_APP')) { die('Direct access not permitted'); }

if (!class_exists('DuplicateEmailException')) {
    class DuplicateEmailException extends \RuntimeException {}
}

class UmpireImportService {

    private Database $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Classify raw CSV rows without modifying the DB.
     *
     * @param array  $rawRows      Rows already normalized to keys: first_name, last_name, email, phone
     * @param string $defaultLevel 'Blue Shirt' or 'Black Shirt'
     * @return array Preview rows, each with: status, first_name, last_name, email, phone, reason
     */
    public function previewRows(array $rawRows, string $defaultLevel): array {
        // Pre-load all existing emails in one query to avoid N+1
        $existingEmails = [];
        $rows = $this->db->fetchAll("SELECT LOWER(email) AS email FROM users");
        foreach ($rows as $r) {
            $existingEmails[$r['email']] = true;
        }

        $seenEmails = []; // tracks emails seen so far within this CSV
        $preview    = [];

        foreach ($rawRows as $idx => $raw) {
            $firstName = trim((string) ($raw['first_name'] ?? ''));
            $lastName  = trim((string) ($raw['last_name']  ?? ''));
            $email     = trim(strtolower((string) ($raw['email'] ?? '')));
            $phone     = trim((string) ($raw['phone'] ?? ''));
            $rowNum    = $idx + 2; // 1-based, row 1 is header

            $entry = [
                'status'     => '',
                'first_name' => $firstName,
                'last_name'  => $lastName,
                'email'      => $email,
                'phone'      => $phone,
                'reason'     => '',
            ];

            // 1. Email required
            if ($email === '') {
                $entry['status'] = 'error';
                $entry['reason'] = 'Email is required';
                $preview[] = $entry;
                continue;
            }

            // 2. Other required fields
            if ($firstName === '' || $lastName === '' || $phone === '') {
                $entry['status'] = 'error';
                $entry['reason'] = 'First name, last name, and phone are required';
                $preview[] = $entry;
                continue;
            }

            // 3. Email format
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $entry['status'] = 'error';
                $entry['reason'] = 'Invalid email address';
                $preview[] = $entry;
                continue;
            }

            // 4. Duplicate within CSV
            if (isset($seenEmails[$email])) {
                $firstRowNum = $seenEmails[$email];
                $entry['status'] = 'error';
                $entry['reason'] = "Duplicate email in CSV (appears on row {$firstRowNum})";
                $preview[] = $entry;
                continue;
            }
            $seenEmails[$email] = $rowNum;

            // 5. Already exists in DB
            if (isset($existingEmails[$email])) {
                $entry['status'] = 'skip';
                $entry['reason'] = 'Email already exists in the system';
                $preview[] = $entry;
                continue;
            }

            $entry['status'] = 'will_create';
            $preview[] = $entry;
        }

        return $preview;
    }

    /**
     * Insert all will_create rows inside one transaction (AC 4 — all-or-nothing).
     *
     * @param array  $willCreateRows Only the 'will_create' rows from previewRows()
     * @param string $defaultLevel   Umpire level applied to all imported accounts
     * @param int    $actorUserId    Admin/assignor performing the import
     * @return array ['created' => int, 'skipped' => int, 'errors' => [...]]
     * @throws \Throwable on transaction failure (caller must surface error)
     */
    public function importRows(array $willCreateRows, string $defaultLevel, int $actorUserId): array {
        $created = 0;
        $skipped = 0;
        $errors  = [];

        if (!class_exists('UmpireRosterService')) {
            require_once __DIR__ . '/UmpireRosterService.php';
        }
        $rosterSvc = new UmpireRosterService();

        // migration mode is always active during import (AC 5) — suppresses welcome email in createUmpire()
        $rosterSvc->enableMigrationMode();

        $this->db->beginTransaction();
        try {
            foreach ($willCreateRows as $row) {
                try {
                    // Race-condition guard: re-check email just before insert
                    $exists = $this->db->fetchOne(
                        'SELECT id FROM users WHERE email = :e LIMIT 1',
                        ['e' => trim(strtolower((string) $row['email']))]
                    );
                    if ($exists !== false) {
                        $skipped++;
                        continue;
                    }

                    $rosterSvc->createUmpire([
                        'first_name'   => trim((string) $row['first_name']),
                        'last_name'    => trim((string) $row['last_name']),
                        'email'        => trim((string) $row['email']),
                        'phone'        => trim((string) ($row['phone'] ?? '')),
                        'umpire_level' => $defaultLevel,
                        'is_under_18'  => false,
                    ], $actorUserId);

                    $created++;
                } catch (DuplicateEmailException $e) {
                    $skipped++;
                } catch (\Throwable $e) {
                    // Rethrow — triggers outer rollback (AC 4)
                    throw $e;
                }
            }

            $this->db->commit();
        } catch (\Throwable $e) {
            try { $this->db->rollback(); } catch (\Throwable $ignored) {}
            $rosterSvc->disableMigrationMode();
            throw $e;
        }

        $rosterSvc->disableMigrationMode();

        return ['created' => $created, 'skipped' => $skipped, 'errors' => $errors];
    }
}
