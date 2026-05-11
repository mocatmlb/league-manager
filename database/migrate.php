<?php
/**
 * Database Migration Runner
 *
 * Automatically detects and executes pending SQL migrations.
 * Referenced in .cpanel.yml for automated deployment.
 *
 * Handles DELIMITER directives so stored-procedure migrations execute correctly
 * through PDO (which does not understand the MySQL CLI DELIMITER command).
 */

define('D8TL_APP', true);

require_once __DIR__ . '/../includes/env-loader.php';
require_once EnvLoader::getPath('includes/bootstrap.php');

class MigrationRunner {
    private $db;
    private $migrationsDir;

    public function __construct() {
        $this->db = Database::getInstance();
        $this->migrationsDir = __DIR__ . '/migrations';
        $this->ensureMigrationsTable();
    }

    private function ensureMigrationsTable() {
        $this->db->query("
            CREATE TABLE IF NOT EXISTS schema_migrations (
                version VARCHAR(100) PRIMARY KEY,
                applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
    }

    public function run() {
        echo "Starting database migrations...\n";

        $files = glob($this->migrationsDir . '/*.sql');
        sort($files);

        $appliedCount = 0;
        foreach ($files as $file) {
            $version = basename($file, '.sql');

            if ($this->isApplied($version)) {
                echo "Skipping $version (already applied)\n";
                continue;
            }

            echo "Applying $version...";
            $this->apply($file, $version);
            echo " DONE\n";
            $appliedCount++;
        }

        echo "Migration process finished. Applied $appliedCount migrations.\n";
    }

    private function isApplied($version) {
        $row = $this->db->fetchOne(
            "SELECT version FROM schema_migrations WHERE version = ?",
            [$version]
        );
        return (bool) $row;
    }

    private function apply($file, $version) {
        $sql = file_get_contents($file);
        if ($sql === false) {
            throw new Exception("Could not read migration file: $file");
        }

        try {
            $pdo = $this->db->getConnection();
            foreach ($this->parseStatements($sql) as $stmt) {
                $stmt = trim($stmt);
                if ($stmt === '') continue;
                $pdo->exec($stmt);
            }
            $this->db->insert('schema_migrations', ['version' => $version]);
        } catch (Exception $e) {
            echo " FAILED\n";
            throw new Exception("Migration $version failed: " . $e->getMessage());
        }
    }

    /**
     * Split a SQL file into individual statements, respecting DELIMITER directives.
     *
     * MySQL CLI uses DELIMITER to allow ';' inside stored-procedure bodies.
     * PDO has no such concept — each exec() call is one statement. This method
     * translates the file into a flat list of statements PDO can execute one by one.
     */
    private function parseStatements(string $sql): array {
        $delimiter  = ';';
        $statements = [];
        $buffer     = '';

        foreach (preg_split('/\r?\n/', $sql) as $line) {
            // Handle DELIMITER directive (MySQL CLI command, not valid SQL)
            if (preg_match('/^\s*DELIMITER\s+(\S+)\s*$/i', trim($line), $m)) {
                if (trim($buffer) !== '') {
                    $statements[] = trim($buffer);
                    $buffer = '';
                }
                $delimiter = $m[1];
                continue;
            }

            $buffer .= $line . "\n";

            // Flush when the accumulated buffer ends with the active delimiter
            $check = rtrim($buffer);
            if ($check !== '' && str_ends_with($check, $delimiter)) {
                $stmt = trim(substr($check, 0, -strlen($delimiter)));
                if ($stmt !== '') {
                    $statements[] = $stmt;
                }
                $buffer = '';
            }
        }

        if (trim($buffer) !== '') {
            $statements[] = trim($buffer);
        }

        return array_values(array_filter($statements, fn($s) => trim($s) !== ''));
    }
}

try {
    $runner = new MigrationRunner();
    $runner->run();
    exit(0);
} catch (Exception $e) {
    error_log("Migration Error: " . $e->getMessage());
    echo "\nERROR: " . $e->getMessage() . "\n";
    exit(1);
}
