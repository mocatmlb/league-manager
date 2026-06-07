<?php
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

if (!Auth::isAdmin()) {
    header('Location: ../login.php');
    exit;
}

$db = Database::getInstance();
$message = '';
$messageType = '';

if (!empty($_SESSION['flash_message'])) {
    $message     = $_SESSION['flash_message'];
    $messageType = $_SESSION['flash_type'] ?? 'success';
    unset($_SESSION['flash_message'], $_SESSION['flash_type']);
}

$allowedTypes = ['milestone', 'holiday', 'deadline', 'other'];
$typeColors   = [
    'milestone' => '#16a34a',
    'holiday'   => '#ea580c',
    'deadline'  => '#dc2626',
    'other'     => '#475569',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $message     = 'Invalid form submission.';
        $messageType = 'danger';
    } else {
        $action = $_POST['action'] ?? '';

        try {
            switch ($action) {
                case 'add_special_date':
                    $dateVal  = sanitize($_POST['date'] ?? '');
                    $label    = sanitize($_POST['label'] ?? '');
                    $dateType = sanitize($_POST['date_type'] ?? '');
                    $color    = sanitize($_POST['display_color'] ?? '');
                    $seasonId = isset($_POST['season_id']) && $_POST['season_id'] !== '' ? (int)$_POST['season_id'] : null;

                    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateVal)) {
                        throw new Exception('Date must be a valid date (YYYY-MM-DD).');
                    }
                    if ($label === '' || mb_strlen($label) > 100) {
                        throw new Exception('Label is required and must be 100 characters or fewer.');
                    }
                    if (!in_array($dateType, $allowedTypes, true)) {
                        throw new Exception('Invalid date type.');
                    }
                    if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
                        throw new Exception('Color must be a valid 7-character hex value (e.g. #16a34a).');
                    }

                    $db->insert('league_special_dates', [
                        'season_id'     => $seasonId,
                        'date'          => $dateVal,
                        'label'         => $label,
                        'date_type'     => $dateType,
                        'display_color' => $color,
                        'created_by'    => Auth::getCurrentUserId(),
                    ]);

                    $_SESSION['flash_message'] = 'Special date added successfully.';
                    $_SESSION['flash_type']    = 'success';
                    header('Location: special-dates.php');
                    exit;

                case 'update_special_date':
                    $id       = (int)($_POST['id'] ?? 0);
                    $dateVal  = sanitize($_POST['date'] ?? '');
                    $label    = sanitize($_POST['label'] ?? '');
                    $dateType = sanitize($_POST['date_type'] ?? '');
                    $color    = sanitize($_POST['display_color'] ?? '');
                    $seasonId = isset($_POST['season_id']) && $_POST['season_id'] !== '' ? (int)$_POST['season_id'] : null;

                    if ($id < 1) throw new Exception('Invalid record.');
                    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateVal)) {
                        throw new Exception('Date must be a valid date (YYYY-MM-DD).');
                    }
                    if ($label === '' || mb_strlen($label) > 100) {
                        throw new Exception('Label is required and must be 100 characters or fewer.');
                    }
                    if (!in_array($dateType, $allowedTypes, true)) {
                        throw new Exception('Invalid date type.');
                    }
                    if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
                        throw new Exception('Color must be a valid 7-character hex value (e.g. #16a34a).');
                    }

                    $db->update('league_special_dates', [
                        'season_id'     => $seasonId,
                        'date'          => $dateVal,
                        'label'         => $label,
                        'date_type'     => $dateType,
                        'display_color' => $color,
                    ], 'id = :id', ['id' => $id]);

                    $_SESSION['flash_message'] = 'Special date updated successfully.';
                    $_SESSION['flash_type']    = 'success';
                    header('Location: special-dates.php');
                    exit;

                case 'delete_special_date':
                    $id = (int)($_POST['id'] ?? 0);
                    if ($id < 1) throw new Exception('Invalid record.');

                    $db->query('DELETE FROM league_special_dates WHERE id = ?', [$id]);

                    $_SESSION['flash_message'] = 'Special date deleted.';
                    $_SESSION['flash_type']    = 'success';
                    header('Location: special-dates.php');
                    exit;

                default:
                    throw new Exception('Unknown action.');
            }
        } catch (Exception $e) {
            $message     = $e->getMessage();
            $messageType = 'danger';
        }
    }
}

$specialDates = $db->fetchAll(
    "SELECT sd.*, s.season_name, p.program_name
     FROM league_special_dates sd
     LEFT JOIN seasons s ON sd.season_id = s.season_id
     LEFT JOIN programs p ON s.program_id = p.program_id
     ORDER BY sd.date ASC"
);

$seasons = $db->fetchAll(
    "SELECT s.season_id, s.season_name, p.program_name
     FROM seasons s
     JOIN programs p ON s.program_id = p.program_id
     ORDER BY p.program_name, s.season_name"
);

$typeLabels = [
    'milestone' => 'Milestone',
    'holiday'   => 'Holiday',
    'deadline'  => 'Deadline',
    'other'     => 'Other',
];

$csrfToken = Auth::generateCSRFToken();
$rootPath  = '../../';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Special Dates – Admin</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo $rootPath; ?>assets/css/style.css">
</head>
<body>
<?php include EnvLoader::getPath('includes/nav.php'); ?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-12">
            <h2><i class="fas fa-star me-2"></i>Special Dates</h2>
            <p class="text-muted">Manage calendar markers — Opening Day, holidays, deadlines, and other milestones.</p>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo htmlspecialchars($messageType, ENT_QUOTES, 'UTF-8'); ?> alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Add New Special Date -->
            <div class="card mb-4">
                <div class="card-header"><strong>Add New Special Date</strong></div>
                <div class="card-body">
                    <form method="post" action="special-dates.php">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="action" value="add_special_date">
                        <div class="row g-3 align-items-end">
                            <div class="col-md-2">
                                <label class="form-label">Date</label>
                                <input type="date" name="date" class="form-control" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Label</label>
                                <input type="text" name="label" class="form-control" maxlength="100" required placeholder="e.g. Opening Day">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Type</label>
                                <select name="date_type" id="date_type" class="form-select" required>
                                    <?php foreach ($typeLabels as $val => $lbl): ?>
                                        <option value="<?php echo $val; ?>"><?php echo $lbl; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-1">
                                <label class="form-label">Color</label>
                                <input type="color" name="display_color" id="display_color" class="form-control form-control-color" value="#475569">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Season</label>
                                <select name="season_id" class="form-select">
                                    <option value="">All Seasons / Global</option>
                                    <?php foreach ($seasons as $s): ?>
                                        <option value="<?php echo (int)$s['season_id']; ?>">
                                            <?php echo htmlspecialchars($s['program_name'] . ' — ' . $s['season_name'], ENT_QUOTES, 'UTF-8'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-plus me-1"></i> Add Date
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Special Dates Table -->
            <div class="card">
                <div class="card-header"><strong>Existing Special Dates</strong></div>
                <div class="card-body p-0">
                    <?php if (empty($specialDates)): ?>
                        <p class="text-muted p-3 mb-0">No special dates have been added yet.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Date</th>
                                        <th>Label</th>
                                        <th>Type</th>
                                        <th>Color</th>
                                        <th>Season</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($specialDates as $sd): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars(date('M j, Y', strtotime($sd['date'])), ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?php echo htmlspecialchars($sd['label'], ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?php echo htmlspecialchars($typeLabels[$sd['date_type']] ?? $sd['date_type'], ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td>
                                                <span class="d-inline-block border rounded" style="width:24px;height:24px;background:<?php echo htmlspecialchars($sd['display_color'], ENT_QUOTES, 'UTF-8'); ?>;vertical-align:middle;"></span>
                                                <small class="ms-1 text-muted"><?php echo htmlspecialchars($sd['display_color'], ENT_QUOTES, 'UTF-8'); ?></small>
                                            </td>
                                            <td>
                                                <?php if ($sd['season_id']): ?>
                                                    <?php echo htmlspecialchars($sd['program_name'] . ' — ' . $sd['season_name'], ENT_QUOTES, 'UTF-8'); ?>
                                                <?php else: ?>
                                                    <span class="text-muted">Global</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-outline-secondary me-1"
                                                    data-bs-toggle="modal" data-bs-target="#editModal"
                                                    data-id="<?php echo (int)$sd['id']; ?>"
                                                    data-date="<?php echo htmlspecialchars($sd['date'], ENT_QUOTES, 'UTF-8'); ?>"
                                                    data-label="<?php echo htmlspecialchars($sd['label'], ENT_QUOTES, 'UTF-8'); ?>"
                                                    data-type="<?php echo htmlspecialchars($sd['date_type'], ENT_QUOTES, 'UTF-8'); ?>"
                                                    data-color="<?php echo htmlspecialchars($sd['display_color'], ENT_QUOTES, 'UTF-8'); ?>"
                                                    data-season="<?php echo htmlspecialchars((string)($sd['season_id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                                                    <i class="fas fa-edit"></i> Edit
                                                </button>
                                                <form method="post" action="special-dates.php" class="d-inline"
                                                      onsubmit="return confirm('Delete this special date?');">
                                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                                                    <input type="hidden" name="action" value="delete_special_date">
                                                    <input type="hidden" name="id" value="<?php echo (int)$sd['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                                        <i class="fas fa-trash"></i> Delete
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" action="special-dates.php">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="action" value="update_special_date">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-header">
                    <h5 class="modal-title" id="editModalLabel">Edit Special Date</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Date</label>
                        <input type="date" name="date" id="edit_date" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Label</label>
                        <input type="text" name="label" id="edit_label" class="form-control" maxlength="100" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Type</label>
                        <select name="date_type" id="edit_date_type" class="form-select" required>
                            <?php foreach ($typeLabels as $val => $lbl): ?>
                                <option value="<?php echo $val; ?>"><?php echo $lbl; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Color</label>
                        <input type="color" name="display_color" id="edit_display_color" class="form-control form-control-color">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Season</label>
                        <select name="season_id" id="edit_season_id" class="form-select">
                            <option value="">All Seasons / Global</option>
                            <?php foreach ($seasons as $s): ?>
                                <option value="<?php echo (int)$s['season_id']; ?>">
                                    <?php echo htmlspecialchars($s['program_name'] . ' — ' . $s['season_name'], ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
var TYPE_COLORS = {
    milestone: '#16a34a',
    holiday:   '#ea580c',
    deadline:  '#dc2626',
    other:     '#475569',
};

document.getElementById('date_type').addEventListener('change', function() {
    var colorInput = document.getElementById('display_color');
    if (TYPE_COLORS[this.value]) colorInput.value = TYPE_COLORS[this.value];
});

var editModal = document.getElementById('editModal');
editModal.addEventListener('show.bs.modal', function(event) {
    var btn = event.relatedTarget;
    document.getElementById('edit_id').value        = btn.dataset.id;
    document.getElementById('edit_date').value      = btn.dataset.date;
    document.getElementById('edit_label').value     = btn.dataset.label;
    document.getElementById('edit_date_type').value = btn.dataset.type;
    document.getElementById('edit_display_color').value = btn.dataset.color;
    document.getElementById('edit_season_id').value = btn.dataset.season;
});

document.getElementById('edit_date_type').addEventListener('change', function() {
    var colorInput = document.getElementById('edit_display_color');
    if (TYPE_COLORS[this.value]) colorInput.value = TYPE_COLORS[this.value];
});
</script>
</body>
</html>
