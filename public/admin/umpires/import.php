<?php
define('D8TL_APP', true);

$envLoader = file_exists(__DIR__ . '/../../includes/env-loader.php')
    ? __DIR__ . '/../../includes/env-loader.php'
    : __DIR__ . '/../../../includes/env-loader.php';
require_once $envLoader;
require_once EnvLoader::getPath('includes/bootstrap.php');
require_once EnvLoader::getPath('includes/PermissionGuard.php');

PermissionGuard::requireRole('umpire_assignor', '/login.php');

require_once EnvLoader::getPath('includes/UmpireRosterService.php');
require_once EnvLoader::getPath('includes/UmpireImportService.php');

$currentUser = Auth::getCurrentUser();
$actorUserId = (int) ($currentUser['id'] ?? 0);
if ($actorUserId < 1) { header('Location: /login.php'); exit; }

// ── Sample CSV download ──────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'download_sample') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="umpire_import_sample.csv"');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    echo "first_name,last_name,email,phone\r\n";
    echo "John,Smith,jsmith@example.com,555-100-0001\r\n";
    echo "Jane,Doe,jdoe@example.com,555-100-0002\r\n";
    exit;
}

// ── Cancel — clear session and redirect ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'cancel') {
    unset($_SESSION['umpire_import_preview']);
    header('Location: ' . EnvLoader::getBaseUrl() . '/admin/umpires/roster.php');
    exit;
}

// ── Header alias map for SignUpGenius CSV compatibility ──────────────────────
$headerAliases = [
    'first_name' => ['first name', 'first_name', 'firstname'],
    'last_name'  => ['last name',  'last_name',  'lastname'],
    'email'      => ['email',      'email address'],
    'phone'      => ['phone',      'phone number', 'cell', 'cell phone'],
];

// ── POST handling ────────────────────────────────────────────────────────────
$pageError   = '';
$previewRows = [];
$showPreview = false;
$previewLevel = 'Blue Shirt';

// Consume flash messages
$flashMessage = $_SESSION['flash_message'] ?? '';
$flashError   = $_SESSION['flash_error']   ?? '';
unset($_SESSION['flash_message'], $_SESSION['flash_error']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if (!Auth::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $pageError = 'Invalid form submission. Please try again.';

    } elseif ($action === 'upload') {

        unset($_SESSION['umpire_import_preview']);
        $defaultLevel = in_array($_POST['default_level'] ?? '', ['Blue Shirt', 'Black Shirt'], true)
            ? $_POST['default_level']
            : 'Blue Shirt';

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
                    $handle = fopen($file['tmp_name'], 'r');
                    if ($handle === false) {
                        $pageError = 'Unable to read the uploaded file. Please try again.';
                    } else {
                        $headerRow = fgetcsv($handle, 0, ',', '"', '\\');
                        if ($headerRow === false || $headerRow === null) {
                            $pageError = 'The file appears to be empty or unreadable.';
                        } else {
                            // Normalize raw headers + strip BOM
                            $rawHeaders = array_map(static fn($v) => trim((string) $v), $headerRow);
                            $rawHeaders[0] = preg_replace('/^\xEF\xBB\xBF/', '', $rawHeaders[0]);

                            // Map raw headers to canonical keys
                            $canonicalHeaders = [];
                            foreach ($rawHeaders as $rawHeader) {
                                $lc = strtolower(trim($rawHeader));
                                foreach ($headerAliases as $canonical => $aliases) {
                                    if (in_array($lc, $aliases, true)) {
                                        $canonicalHeaders[$rawHeader] = $canonical;
                                        break;
                                    }
                                }
                                // Unknown columns silently ignored (SignUpGenius may include extras)
                            }

                            $missingRequired = array_diff(
                                ['first_name', 'last_name', 'email', 'phone'],
                                array_values($canonicalHeaders)
                            );
                            if (!empty($missingRequired)) {
                                $pageError = 'CSV is missing required columns: '
                                    . implode(', ', $missingRequired)
                                    . '. Accepted header names are listed in the format guide below.';
                            } else {
                                // Read data rows
                                $dataRows = [];
                                while (($csvRow = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
                                    if ($csvRow === [null]) {
                                        continue; // skip blank lines
                                    }
                                    $assoc = [];
                                    foreach ($rawHeaders as $i => $rawHdr) {
                                        $canonicalKey = $canonicalHeaders[$rawHdr] ?? null;
                                        if ($canonicalKey !== null) {
                                            $assoc[$canonicalKey] = $csvRow[$i] ?? '';
                                        }
                                    }
                                    $dataRows[] = $assoc;
                                }

                                if (empty($dataRows)) {
                                    $pageError = 'The file contains no data rows.';
                                } elseif (count($dataRows) > 500) {
                                    $pageError = 'Import file exceeds the 500-row limit. Split the file and re-import.';
                                } else {
                                    $importSvc   = new UmpireImportService();
                                    $previewRows = $importSvc->previewRows($dataRows, $defaultLevel);
                                    $_SESSION['umpire_import_preview'] = [
                                        'rows'  => $previewRows,
                                        'level' => $defaultLevel,
                                    ];
                                    $showPreview  = true;
                                    $previewLevel = $defaultLevel;
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

        $sessionData = $_SESSION['umpire_import_preview'] ?? null;
        if (empty($sessionData) || !isset($sessionData['rows'])) {
            $pageError = 'No import data found. Please re-upload your CSV file.';
        } else {
            $allRows      = $sessionData['rows'];
            $defaultLevel = $sessionData['level'] ?? 'Blue Shirt';
            $willCreate   = array_filter($allRows, static fn($r) => $r['status'] === 'will_create');

            if (empty($willCreate)) {
                unset($_SESSION['umpire_import_preview']);
                $_SESSION['flash_message'] = 'Nothing to import — all rows were either skipped or had errors.';
                header('Location: ' . EnvLoader::getBaseUrl() . '/admin/umpires/import.php');
                exit;
            }

            try {
                $importSvc = new UmpireImportService();
                $result    = $importSvc->importRows(array_values($willCreate), $defaultLevel, $actorUserId);
                unset($_SESSION['umpire_import_preview']);

                $msg = "Imported {$result['created']} umpire" . ($result['created'] !== 1 ? 's' : '') . '.';
                if ($result['skipped'] > 0) {
                    $msg .= " Skipped {$result['skipped']} (already existed).";
                }
                $_SESSION['flash_message'] = $msg;
                header('Location: ' . EnvLoader::getBaseUrl() . '/admin/umpires/roster.php');
                exit;
            } catch (\Throwable $e) {
                error_log('[umpire/import.php] importRows error: ' . $e->getMessage());
                $_SESSION['flash_error'] = 'Import failed — no accounts were created. Please try again.';
                header('Location: ' . EnvLoader::getBaseUrl() . '/admin/umpires/import.php');
                exit;
            }
        }

    } else {
        $pageError = 'Invalid action.';
    }
}

// ── Restore preview from session when returning to page ──────────────────────
if (!$showPreview && !empty($_SESSION['umpire_import_preview']['rows'])) {
    $previewRows  = $_SESSION['umpire_import_preview']['rows'];
    $previewLevel = $_SESSION['umpire_import_preview']['level'] ?? 'Blue Shirt';
    $showPreview  = true;
}

// ── Preview summary counts ───────────────────────────────────────────────────
$countWillCreate = 0;
$countSkip       = 0;
$countError      = 0;
if ($showPreview) {
    foreach ($previewRows as $r) {
        if ($r['status'] === 'will_create') $countWillCreate++;
        elseif ($r['status'] === 'skip')    $countSkip++;
        else                                $countError++;
    }
}

$pageTitle = 'Import Umpires - ' . APP_NAME;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
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
                    <h1><i class="fas fa-file-csv me-2"></i>Import Umpires from CSV</h1>
                    <div class="d-flex gap-2">
                        <a href="?action=download_sample" class="btn btn-outline-secondary btn-sm">
                            <i class="fas fa-download"></i> Download Sample CSV
                        </a>
                        <a href="roster.php" class="btn btn-outline-secondary btn-sm">
                            <i class="fas fa-arrow-left"></i> Back to Roster
                        </a>
                    </div>
                </div>

                <?php if ($flashMessage): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($flashMessage); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($flashError): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($flashError); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($pageError): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($pageError); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($showPreview && !empty($previewRows)): ?>

                    <!-- Preview summary -->
                    <div class="row mb-3">
                        <div class="col-auto">
                            <span class="badge bg-success fs-6 me-1"><?php echo $countWillCreate; ?> Will create</span>
                            <span class="badge bg-warning text-dark fs-6 me-1"><?php echo $countSkip; ?> Skip</span>
                            <span class="badge bg-danger fs-6"><?php echo $countError; ?> Error</span>
                            <span class="ms-2 text-muted">— Default level: <strong><?php echo htmlspecialchars($previewLevel); ?></strong></span>
                        </div>
                    </div>

                    <!-- Preview table -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <i class="fas fa-table"></i> Import Preview — review before confirming
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>#</th>
                                            <th>Name</th>
                                            <th>Email</th>
                                            <th>Phone</th>
                                            <th>Status</th>
                                            <th>Reason</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($previewRows as $idx => $row): ?>
                                        <?php
                                        $rowClass = match($row['status']) {
                                            'will_create' => 'table-success',
                                            'skip'        => 'table-warning',
                                            'error'       => 'table-danger',
                                            default       => '',
                                        };
                                        $statusLabel = match($row['status']) {
                                            'will_create' => 'Will create',
                                            'skip'        => 'Skip',
                                            'error'       => 'Error',
                                            default       => htmlspecialchars($row['status']),
                                        };
                                        ?>
                                        <tr class="<?php echo $rowClass; ?>">
                                            <td><?php echo $idx + 2; ?></td>
                                            <td><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></td>
                                            <td><?php echo htmlspecialchars($row['email']); ?></td>
                                            <td><?php echo htmlspecialchars($row['phone'] ?? ''); ?></td>
                                            <td><strong><?php echo $statusLabel; ?></strong></td>
                                            <td><?php echo htmlspecialchars($row['reason'] ?? ''); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <?php if ($countWillCreate > 0): ?>
                    <div class="d-flex gap-2 mb-4">
                        <form method="post" action="">
                            <input type="hidden" name="csrf_token" value="<?php echo Auth::generateCSRFToken(); ?>">
                            <input type="hidden" name="action" value="confirm">
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-check"></i> Confirm Import (<?php echo $countWillCreate; ?> account<?php echo $countWillCreate !== 1 ? 's' : ''; ?>)
                            </button>
                        </form>
                        <a href="?action=cancel" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                    </div>
                    <?php else: ?>
                    <div class="alert alert-warning">
                        No accounts will be created — all rows are skipped or have errors.
                        <a href="?action=cancel" class="alert-link ms-2">Start over</a>
                    </div>
                    <?php endif; ?>

                <?php else: ?>

                    <!-- Upload form -->
                    <div class="row">
                        <div class="col-lg-8">

                            <div class="card mb-4">
                                <div class="card-header">
                                    <i class="fas fa-info-circle"></i> CSV Format Guide
                                </div>
                                <div class="card-body">
                                    <p>Upload a CSV with the following columns. Headers are matched case-insensitively and SignUpGenius export names are recognized automatically.</p>
                                    <div class="table-responsive mb-3">
                                        <table class="table table-sm table-bordered">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Field</th>
                                                    <th>Accepted Header Names</th>
                                                    <th>Notes</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr>
                                                    <td>First name</td>
                                                    <td><code>first_name</code>, <code>first name</code>, <code>firstname</code></td>
                                                    <td>Required</td>
                                                </tr>
                                                <tr>
                                                    <td>Last name</td>
                                                    <td><code>last_name</code>, <code>last name</code>, <code>lastname</code></td>
                                                    <td>Required</td>
                                                </tr>
                                                <tr>
                                                    <td>Email</td>
                                                    <td><code>email</code>, <code>email address</code></td>
                                                    <td>Required — used as the login username</td>
                                                </tr>
                                                <tr>
                                                    <td>Phone</td>
                                                    <td><code>phone</code>, <code>phone number</code>, <code>cell</code>, <code>cell phone</code></td>
                                                    <td>Required</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                    <div class="alert alert-info mb-3">
                                        <ul class="mb-0">
                                            <li>Extra columns (e.g. SignUpGenius "Team" or "Notes") are silently ignored.</li>
                                            <li>Rows with an email already in the system are skipped — the existing account is not changed.</li>
                                            <li>No welcome emails are sent for imported accounts — umpires must be notified separately.</li>
                                            <li>Maximum 500 rows per import. File must be under 1 MB.</li>
                                            <li>All imported umpires will be assigned the selected default level. Edit individual profiles afterward if needed.</li>
                                        </ul>
                                    </div>
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

                                        <div class="mb-3">
                                            <label for="default_level" class="form-label fw-semibold">Default Umpire Level</label>
                                            <select class="form-select" id="default_level" name="default_level">
                                                <option value="Blue Shirt" selected>Blue Shirt</option>
                                                <option value="Black Shirt">Black Shirt</option>
                                            </select>
                                            <div class="form-text">All imported umpires will be assigned this level.</div>
                                        </div>

                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-upload"></i> Upload &amp; Preview
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
