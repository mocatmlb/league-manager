<?php
/**
 * District 8 Travel League - Bulk Game Import
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
require_once EnvLoader::getPath('includes/GameImportService.php');

// Require admin authentication
Auth::requireAdmin();

$db          = Database::getInstance();
$currentUser = Auth::getCurrentUser();

// ── Sample CSV download ─────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'download_sample') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="game_import_sample.csv"');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    echo "season_year,season_name,division_name,home_team,away_team,game_date,game_time,location_name\r\n";
    echo "2026,Spring 2026,Majors,Springfield Marlins,Eastside Tigers,2026-06-15,10:00,Thornden Park\r\n";
    exit;
}

// ── Cancel — clear session and redirect ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'cancel') {
    unset($_SESSION['import_preview']);
    header('Location: ' . EnvLoader::getBaseUrl() . '/admin/games/');
    exit;
}

// ── POST handling ──────────────────────────────────────────────────────────
$pageError    = '';
$rowErrors    = [];   // validation error rows: [['row' => N, 'errors' => [...]], ...]
$previewRows  = [];   // validated rows ready for display / confirm
$showPreview  = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // CSRF check applies to all POST actions
    if (!Auth::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $pageError = 'Invalid form submission. Please try again.';
    } elseif ($action === 'upload') {

        // ── Step 1: upload & validate ──────────────────────────────────────
        unset($_SESSION['import_preview']);
        $file = $_FILES['import_file'] ?? null;

        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            $uploadErrMap = [
                UPLOAD_ERR_INI_SIZE   => 'The file exceeds the server upload size limit.',
                UPLOAD_ERR_FORM_SIZE  => 'The file exceeds the form upload size limit.',
                UPLOAD_ERR_PARTIAL    => 'The file was only partially uploaded.',
                UPLOAD_ERR_NO_FILE    => 'No file was selected for upload.',
                UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder.',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
                UPLOAD_ERR_EXTENSION  => 'A PHP extension stopped the upload.',
            ];
            $pageError = $uploadErrMap[$file['error'] ?? UPLOAD_ERR_NO_FILE] ?? 'File upload failed.';
        } else {
            $maxSize = 1 * 1024 * 1024; // 1 MB
            if ($file['size'] > $maxSize) {
                $pageError = 'File size exceeds the 1 MB limit. Please reduce the file size and try again.';
            } else {
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                if ($ext !== 'csv') {
                    $pageError = 'Only .csv files are accepted.';
                } else {
                    // Parse CSV
                    $handle = fopen($file['tmp_name'], 'r');
                    if ($handle === false) {
                        $pageError = 'Unable to read the uploaded file. Please try again.';
                    } else {
                        $headerRow = fgetcsv($handle);
                        if ($headerRow === false || $headerRow === null) {
                            $pageError = 'The file appears to be empty or unreadable.';
                        } else {
                            // Normalize headers (trim + BOM-safe first column handling)
                            $headers = array_map(
                                static fn($value) => trim((string)$value),
                                $headerRow
                            );
                            if (isset($headers[0])) {
                                $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', $headers[0]);
                            }

                            $requiredHeaders = [
                                'season_year', 'season_name', 'division_name',
                                'home_team', 'away_team', 'game_date', 'game_time', 'location_name',
                            ];
                            $missingHeaders = array_diff($requiredHeaders, $headers);
                            $unexpectedHeaders = array_diff($headers, $requiredHeaders);
                            if (!empty($missingHeaders) || !empty($unexpectedHeaders)) {
                                $details = [];
                                if (!empty($missingHeaders)) {
                                    $details[] = 'Missing required column(s): ' . implode(', ', $missingHeaders);
                                }
                                if (!empty($unexpectedHeaders)) {
                                    $details[] = 'Unexpected column(s): ' . implode(', ', $unexpectedHeaders);
                                }
                                $pageError = implode('. ', $details) . '. Please check the format guide and re-upload.';
                            } elseif ($headers !== $requiredHeaders) {
                                $pageError = 'CSV headers must exactly match the required columns in this order: '
                                    . implode(',', $requiredHeaders) . '.';
                            } else {
                                // Read data rows
                                $dataRows = [];
                                while (($csvRow = fgetcsv($handle)) !== false) {
                                    if ($csvRow === [null]) {
                                        continue; // skip blank lines
                                    }
                                    $assoc = [];
                                    foreach ($headers as $i => $hdr) {
                                        $assoc[$hdr] = $csvRow[$i] ?? '';
                                    }
                                    $dataRows[] = $assoc;
                                }
                                fclose($handle);

                                if (empty($dataRows)) {
                                    $pageError = 'The file contains no data rows.';
                                } elseif (count($dataRows) > 500) {
                                    $pageError = 'Import file exceeds the 500-row limit. Split the file and re-import.';
                                } else {
                                    // Validate
                                    $service = new \D8TL\GameImportService($db);
                                    $result  = $service->validateRows($dataRows);

                                    if (!empty($result['errors'])) {
                                        $rowErrors = $result['errors'];
                                    } else {
                                        // Store validated rows in session; show preview
                                        $_SESSION['import_preview'] = $result['validated'];
                                        $previewRows  = $result['validated'];
                                        $showPreview  = true;
                                    }
                                }
                            }
                        }
                        if (isset($handle) && is_resource($handle)) {
                            fclose($handle);
                        }
                    }
                }
            }
        }

    } elseif ($action === 'confirm') {

        // ── Step 2: confirm & insert ───────────────────────────────────────
        $validatedRows = $_SESSION['import_preview'] ?? null;
        if (empty($validatedRows)) {
            $pageError = 'No import data found. Please re-upload your CSV file.';
        } else {
            try {
                $service = new \D8TL\GameImportService($db);
                $count   = $service->importRows($validatedRows);
                unset($_SESSION['import_preview']);
                $_SESSION['flash_success'] = "Successfully imported {$count} game" . ($count !== 1 ? 's' : '') . '.';
                header('Location: ' . EnvLoader::getBaseUrl() . '/admin/games/');
                exit;
            } catch (\Throwable $e) {
                unset($_SESSION['import_preview']);
                error_log('GameImport error: ' . $e->getMessage());
                $pageError = 'Import failed due to a server error. No games were inserted. Please try again.';
            }
        }

    } else {
        $pageError = 'Invalid action.';
    }
}

// ── Restore preview from session if returning to the page ─────────────────
if (!$showPreview && !empty($_SESSION['import_preview'])) {
    $previewRows = $_SESSION['import_preview'];
    $showPreview = true;
}

$pageTitle = 'Import Games - ' . APP_NAME;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="../../../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <?php
    $__nav = file_exists(__DIR__ . '/../../includes/nav.php')
        ? __DIR__ . '/../../includes/nav.php'
        : __DIR__ . '/../../../includes/nav.php';
    include $__nav;
    unset($__nav);
    ?>

    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1><i class="fas fa-file-import me-2"></i>Import Games</h1>
                    <a href="<?php echo EnvLoader::getBaseUrl(); ?>/admin/games/" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Games
                    </a>
                </div>

                <?php if ($pageError): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($pageError); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (!empty($rowErrors)): ?>
                    <div class="alert alert-danger">
                        <h5><i class="fas fa-exclamation-triangle"></i> Validation Errors — No games were imported</h5>
                        <p>Fix the errors below and re-upload your CSV file.</p>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered mb-0">
                                <thead class="table-dark">
                                    <tr>
                                        <th style="width:80px;">Row #</th>
                                        <th>Errors</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($rowErrors as $re): ?>
                                        <tr>
                                            <td><?php echo (int)$re['row']; ?></td>
                                            <td>
                                                <ul class="mb-0 ps-3">
                                                    <?php foreach ($re['errors'] as $msg): ?>
                                                        <li><?php echo htmlspecialchars($msg); ?></li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($showPreview && !empty($previewRows)): ?>
                    <!-- Preview table -->
                    <div class="card mb-4">
                        <div class="card-header bg-success text-white">
                            <i class="fas fa-check-circle"></i>
                            Validation passed — <?php echo count($previewRows); ?> game<?php echo count($previewRows) !== 1 ? 's' : ''; ?> ready to import
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-sm table-striped table-hover mb-0">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>Row #</th>
                                            <th>Season</th>
                                            <th>Division</th>
                                            <th>Away Team</th>
                                            <th>Home Team</th>
                                            <th>Date</th>
                                            <th>Time</th>
                                            <th>Location</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($previewRows as $i => $pr): ?>
                                            <tr>
                                                <td><?php echo $i + 2; ?></td>
                                                <td><?php echo htmlspecialchars($pr['season_display']); ?></td>
                                                <td><?php echo htmlspecialchars($pr['division_name']); ?></td>
                                                <td><?php echo htmlspecialchars($pr['away_team_name']); ?></td>
                                                <td><?php echo htmlspecialchars($pr['home_team_name']); ?></td>
                                                <td><?php echo htmlspecialchars($pr['game_date']); ?></td>
                                                <td><?php echo htmlspecialchars($pr['game_time']); ?></td>
                                                <td><?php echo htmlspecialchars($pr['location_name']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex gap-2 mb-4">
                        <form method="post" action="">
                            <input type="hidden" name="csrf_token" value="<?php echo Auth::generateCSRFToken(); ?>">
                            <input type="hidden" name="action" value="confirm">
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-check"></i> Confirm Import
                            </button>
                        </form>
                        <a href="?action=cancel" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                    </div>
                <?php else: ?>
                    <!-- Format guide + upload form -->
                    <div class="row">
                        <div class="col-lg-8">
                            <div class="card mb-4">
                                <div class="card-header">
                                    <i class="fas fa-info-circle"></i> CSV Format Guide
                                </div>
                                <div class="card-body">
                                    <p>Required CSV format — the first row must be the header row with exact column names:</p>
                                    <div class="table-responsive mb-3">
                                        <table class="table table-sm table-bordered">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Column</th>
                                                    <th>Format / Notes</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr><td><code>season_year</code></td><td>4-digit year (e.g., <code>2026</code>)</td></tr>
                                                <tr><td><code>season_name</code></td><td>Exact name from Seasons list (e.g., <code>Spring 2026</code>)</td></tr>
                                                <tr><td><code>division_name</code></td><td>Exact division name (e.g., <code>Majors</code>, <code>Minors</code>)</td></tr>
                                                <tr><td><code>home_team</code></td><td>Exact team name as it appears in the system</td></tr>
                                                <tr><td><code>away_team</code></td><td>Exact team name (must differ from home_team)</td></tr>
                                                <tr><td><code>game_date</code></td><td>YYYY-MM-DD (e.g., <code>2026-06-15</code>)</td></tr>
                                                <tr><td><code>game_time</code></td><td>HH:MM 24-hour (e.g., <code>10:00</code>, <code>18:30</code>)</td></tr>
                                                <tr><td><code>location_name</code></td><td>Exact location name from active locations list</td></tr>
                                            </tbody>
                                        </table>
                                    </div>
                                    <div class="alert alert-info mb-3">
                                        <strong>Notes:</strong>
                                        <ul class="mb-0">
                                            <li>Game numbers are assigned automatically — do not include a <code>game_number</code> column.</li>
                                            <li>Teams and locations must already exist in the system and be <strong>Active</strong>.</li>
                                            <li>A team must belong to the specified season.</li>
                                            <li>Season + division must match an existing division in that season.</li>
                                            <li>Maximum 500 data rows per import.</li>
                                        </ul>
                                    </div>
                                    <a href="?action=download_sample" class="btn btn-outline-secondary btn-sm">
                                        <i class="fas fa-download"></i> Download Sample CSV
                                    </a>
                                </div>
                            </div>

                            <div class="card">
                                <div class="card-header">
                                    <i class="fas fa-upload"></i> Upload CSV File
                                </div>
                                <div class="card-body">
                                    <form method="post" action="" enctype="multipart/form-data">
                                        <input type="hidden" name="csrf_token" value="<?php echo Auth::generateCSRFToken(); ?>">
                                        <input type="hidden" name="action" value="upload">
                                        <div class="mb-3">
                                            <label for="import_file" class="form-label fw-semibold">Select CSV File</label>
                                            <input class="form-control" type="file" id="import_file" name="import_file" accept=".csv,text/csv" required>
                                            <div class="form-text">Maximum file size: 1 MB. Only .csv files accepted.</div>
                                        </div>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-upload"></i> Upload &amp; Validate
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
