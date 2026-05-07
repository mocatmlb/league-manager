<?php
/**
 * District 8 Travel League - Step 2: Team Registration
 */

try {
    $bootstrapPath = file_exists(__DIR__ . '/../includes/coach_bootstrap.php')
        ? __DIR__ . '/../includes/coach_bootstrap.php'
        : __DIR__ . '/../../includes/coach_bootstrap.php';
    require_once $bootstrapPath;
} catch (Throwable $e) {
    echo '<div class="alert alert-danger">Application error: ' . htmlspecialchars($e->getMessage()) . '</div>';
    exit;
}

Auth::requireCoach();

if (!class_exists('Database')) {
    require_once __DIR__ . '/../../includes/database.php';
}
if (!class_exists('TeamRegistrationService')) {
    require_once __DIR__ . '/../../includes/TeamRegistrationService.php';
}
if (!class_exists('LeagueListManager')) {
    require_once __DIR__ . '/../../includes/LeagueListManager.php';
}

$db = Database::getInstance();
$userId = (int) ($_SESSION['coach_user_id'] ?? 0);

$currentCoach = $db->fetchOne(
    'SELECT id, first_name, last_name, email FROM users WHERE id = :id LIMIT 1',
    ['id' => $userId]
);
if ($currentCoach === false) {
    header('Location: login.php');
    exit;
}

// Redirect coaches who already own a team to their dashboard
$existingTeam = $db->fetchOne(
    'SELECT team_id FROM team_owners WHERE user_id = :uid LIMIT 1',
    ['uid' => $userId]
);
if ($existingTeam !== false) {
    header('Location: dashboard.php');
    exit;
}

$seasons = $db->fetchAll(
    "SELECT s.season_id, s.season_name, s.season_year, p.program_name
     FROM seasons s
     INNER JOIN programs p ON p.program_id = s.program_id
     WHERE s.season_status = 'Registration'
     ORDER BY p.program_name, s.season_year DESC, s.season_name"
);

$leagues = LeagueListManager::getActiveList();

$pageTitle = 'Register Your Team — District 8 Travel League';
$globalError = '';
$fieldErrors = [];
$formData = ['season_id' => '', 'league_name' => '', 'other_league' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $globalError = 'Form submission error. Please try again.';
    } else {
        $formData['season_id']   = trim((string) ($_POST['season_id'] ?? ''));
        $formData['league_name'] = trim((string) ($_POST['league_name'] ?? ''));
        $formData['other_league'] = trim((string) ($_POST['other_league'] ?? ''));

        if ($formData['season_id'] === '') {
            $fieldErrors['season_id'] = 'Please select a program/season.';
        }
        if ($formData['league_name'] === '') {
            $fieldErrors['league_name'] = 'League selection is required.';
        }
        if ($formData['league_name'] === 'other' && $formData['other_league'] === '') {
            $fieldErrors['other_league'] = 'Enter your league name.';
        }

        $locationNames     = (array) ($_POST['location_name'] ?? []);
        $locationAddresses = (array) ($_POST['location_address'] ?? []);
        $locationNotes     = (array) ($_POST['location_notes'] ?? []);
        $locations = [];
        foreach (array_slice($locationNames, 0, 5) as $i => $name) {
            $name = trim((string) $name);
            if ($name !== '') {
                $locations[] = [
                    'name'    => $name,
                    'address' => trim((string) ($locationAddresses[$i] ?? '')),
                    'notes'   => trim((string) ($locationNotes[$i] ?? '')),
                ];
            }
        }

        if ($globalError === '' && empty($fieldErrors)) {
            try {
                $service = new TeamRegistrationService();
                $service->submit($userId, [
                    'season_id'    => (int) $formData['season_id'],
                    'league_name'  => $formData['league_name'],
                    'other_league' => $formData['other_league'],
                    'locations'    => $locations,
                ]);
                header('Location: team-register-confirm.php');
                exit;
            } catch (InvitationRegisteredUserException $e) {
                $globalError = 'Team self-registration is not available for invitation-registered accounts. Contact your administrator.';
            } catch (Throwable $e) {
                $globalError = 'An error occurred. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo sanitize($pageTitle); ?></title>
    <meta name="robots" content="noindex">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/css/style.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <?php include '../../includes/nav.php'; ?>

    <div class="container py-4">
        <div class="reg-progress step-2-active mb-4" aria-label="Registration step 2 of 2">
            <div class="progress mb-3">
                <div class="progress-bar" role="progressbar" style="width:100%" aria-valuenow="100" aria-valuemin="0" aria-valuemax="100"></div>
            </div>
            <div class="d-flex justify-content-between">
                <span class="step step-done">&#10003; Account Created</span>
                <span class="step step-active" aria-current="step">Step 2: Register Your Team</span>
            </div>
        </div>

        <?php if ($globalError !== ''): ?>
            <div class="alert alert-danger" role="alert"><?php echo sanitize($globalError); ?></div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <h1 class="h4 mb-0">Step 2 of 2: Register Your Team</h1>
            </div>
            <div class="card-body">
                <form method="POST" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo sanitize(Auth::generateCSRFToken()); ?>">

                    <div class="row g-3">
                        <!-- Program / Season -->
                        <div class="col-12">
                            <label for="season_id" class="form-label">Program / Season <span class="text-danger">*</span></label>
                            <select class="form-select" id="season_id" name="season_id" required
                                    aria-describedby="season_id_error">
                                <option value="">Select a program/season</option>
                                <?php foreach ($seasons as $season): ?>
                                    <option value="<?php echo (int) $season['season_id']; ?>"
                                        <?php echo $formData['season_id'] === (string) $season['season_id'] ? 'selected' : ''; ?>>
                                        <?php echo sanitize($season['program_name'] . ' — ' . $season['season_name'] . ' ' . $season['season_year']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div id="season_id_error" class="text-danger small"><?php echo sanitize($fieldErrors['season_id'] ?? ''); ?></div>
                        </div>

                        <!-- League -->
                        <div class="col-md-6">
                            <label for="league_name" class="form-label">Your League <span class="text-danger">*</span></label>
                            <select class="form-select" id="league_name" name="league_name" required
                                    aria-describedby="league_name_error">
                                <option value="">Select league</option>
                                <?php foreach ($leagues as $league): ?>
                                    <option value="<?php echo sanitize($league['display_name']); ?>"
                                        <?php echo $formData['league_name'] === $league['display_name'] ? 'selected' : ''; ?>>
                                        <?php echo sanitize($league['display_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                                <option value="other" <?php echo $formData['league_name'] === 'other' ? 'selected' : ''; ?>>Other</option>
                            </select>
                            <div id="league_name_error" class="text-danger small"><?php echo sanitize($fieldErrors['league_name'] ?? ''); ?></div>
                        </div>

                        <div class="col-md-6 d-none" id="league-other-container">
                            <label for="other_league" class="form-label">Enter your league name</label>
                            <input type="text" class="form-control" id="other_league" name="other_league"
                                   value="<?php echo sanitize($formData['other_league']); ?>"
                                   aria-describedby="other_league_error">
                            <div id="other_league_error" class="text-danger small"><?php echo sanitize($fieldErrors['other_league'] ?? ''); ?></div>
                        </div>

                        <!-- Team name preview -->
                        <div class="col-12">
                            <label class="form-label">Your Team Name (auto-generated)</label>
                            <?php
                                $leagueForPreview = ($formData['league_name'] === 'other')
                                    ? $formData['other_league']
                                    : $formData['league_name'];
                            ?>
                            <p class="form-control-plaintext fw-bold" id="team-name-preview"
                               data-last-name="<?php echo sanitize($currentCoach['last_name']); ?>">
                                <?php echo sanitize(($leagueForPreview ?: '—') . '-' . $currentCoach['last_name']); ?>
                            </p>
                            <small class="text-muted">Format: {league}-{your last name} (not editable)</small>
                        </div>

                        <!-- Home field locations -->
                        <div class="col-12">
                            <h5 class="mt-2">Home Field Location(s)</h5>
                            <p class="text-muted small">Add up to 5 home field locations. At least one name is required.</p>

                            <div id="location-repeater">
                                <div class="location-block card mb-2 p-3">
                                    <div class="mb-2">
                                        <label for="location_name_0" class="form-label">Location Name <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="location_name_0" name="location_name[0]">
                                    </div>
                                    <div class="mb-2">
                                        <label for="location_address_0" class="form-label">Address (optional)</label>
                                        <input type="text" class="form-control" id="location_address_0" name="location_address[0]">
                                    </div>
                                    <div class="mb-2">
                                        <label for="location_notes_0" class="form-label">Additional Details (optional)</label>
                                        <input type="text" class="form-control" id="location_notes_0" name="location_notes[0]">
                                    </div>
                                    <button type="button" class="btn btn-sm btn-outline-danger remove-location-btn" style="display:none">Remove</button>
                                </div>
                            </div>
                            <button type="button" id="add-location-btn" class="btn btn-outline-secondary btn-sm mt-1">
                                + Add Another Location
                            </button>
                        </div>

                        <div class="col-12 mt-3">
                            <button type="submit" class="btn btn-primary btn-lg">Submit Team Registration</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <footer class="bg-light mt-5 py-4">
        <div class="container">
            <div class="row">
                <div class="col-12 text-center">
                    <p>&copy; <?php echo date('Y'); ?> <?php echo defined('APP_NAME') ? sanitize(APP_NAME) : 'District 8 Travel League'; ?>. All rights reserved.</p>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../assets/js/coaches-registration.js"></script>
</body>
</html>
