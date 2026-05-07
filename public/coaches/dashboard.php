<?php
/**
 * District 8 Travel League - Coaches Dashboard
 */

// Preserve bootstrap detection block — do NOT modify this try/catch
try {
    $bootstrapPath = file_exists(__DIR__ . '/../includes/coach_bootstrap.php')
        ? __DIR__ . '/../includes/coach_bootstrap.php'  // Production: includes is one level up
        : __DIR__ . '/../../includes/coach_bootstrap.php';  // Development: includes is two levels up
    require_once $bootstrapPath;
} catch (Throwable $e) {
    echo '<div class="alert alert-danger">Application error: ' . htmlspecialchars($e->getMessage()) . '</div>';
    exit;
}

// Auth: coach_bootstrap already called Auth::requireCoach() (sets intended_url when redirecting guests).

$db = Database::getInstance();

$userId = (int) ($_SESSION['coach_user_id'] ?? 0);
if ($userId === 0 && Auth::isAdmin()) {
    $userId = (int) ($_SESSION['admin_id'] ?? 0);
}

$user = $db->fetchOne(
    'SELECT id, first_name, last_name FROM users WHERE id = :id LIMIT 1',
    ['id' => $userId]
);
$coachName = ($user !== false)
    ? sanitize($user['first_name'] . ' ' . $user['last_name'])
    : 'Coach';

// ---------------------------------------------------------------------------
// Active assignments — direct DB only (do not use TeamScope::getScopedTeams()).
// Multiple teams: session coach_dashboard_team_id + picker UX (code review 4.4).
// ---------------------------------------------------------------------------
$assignRows = $db->fetchAll(
    'SELECT t.team_id, t.team_name, t.league_name,
            s.season_name, s.season_year,
            d.division_name
     FROM team_owners o
     INNER JOIN teams t ON t.team_id = o.team_id
     LEFT JOIN seasons s ON s.season_id = t.season_id
     LEFT JOIN divisions d ON d.division_id = t.division_id
     WHERE o.user_id = :uid
     ORDER BY t.team_name ASC',
    ['uid' => $userId]
);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (Auth::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $act = (string) ($_POST['coach_team_action'] ?? '');
        $n = count($assignRows);
        if ($act === 'select' && $n > 1) {
            $tid = (int) ($_POST['team_id'] ?? 0);
            foreach ($assignRows as $r) {
                if ((int) $r['team_id'] === $tid) {
                    $_SESSION['coach_dashboard_team_id'] = $tid;
                    break;
                }
            }
        } elseif ($act === 'clear' && $n > 1) {
            unset($_SESSION['coach_dashboard_team_id']);
        }
    }
    header('Location: dashboard.php');
    exit;
}

$heroState     = 'unassigned';
$teamName      = '';
$leagueName    = '';
$seasonLabel   = '';
$divLabel      = '';
$assignment    = false;
$needsTeamPick = false;

$nAssignments = count($assignRows);
if ($nAssignments === 0) {
    unset($_SESSION['coach_dashboard_team_id']);
}

if ($nAssignments === 1) {
    $_SESSION['coach_dashboard_team_id'] = (int) $assignRows[0]['team_id'];
    $assignment = $assignRows[0];
} elseif ($nAssignments > 1) {
    $picked = (int) ($_SESSION['coach_dashboard_team_id'] ?? 0);
    foreach ($assignRows as $r) {
        if ((int) $r['team_id'] === $picked) {
            $assignment = $r;
            break;
        }
    }
    if ($assignment === false) {
        $needsTeamPick = true;
    }
}

if ($assignment !== false) {
    $heroState   = 'active';
    $teamName    = (string) ($assignment['team_name'] ?? '');
    $leagueName  = (string) ($assignment['league_name'] ?? '');
    $seasonLabel = trim(($assignment['season_name'] ?? '') . ' ' . ($assignment['season_year'] ?? ''));
    $divLabel    = (string) ($assignment['division_name'] ?? '');
} elseif (!$needsTeamPick) {
    $pending = $db->fetchOne(
        "SELECT team_id, team_name FROM teams
         WHERE status = 'pending' AND submitted_by_user_id = :uid
         LIMIT 1",
        ['uid' => $userId]
    );

    if ($pending !== false) {
        $heroState = 'pending';
        $teamName  = (string) ($pending['team_name'] ?? '');
    }
}

$pageTitle = 'Coaches Dashboard — ' . APP_NAME;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/css/style.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>

<?php
// Set vars for coaches_nav.php before including it (AC5)
$coachNavWebRoot = '../../';
$_coachNavPath = file_exists(__DIR__ . '/../../includes/coaches_nav.php')
    ? __DIR__ . '/../../includes/coaches_nav.php'
    : __DIR__ . '/../includes/coaches_nav.php';
include $_coachNavPath;
unset($_coachNavPath, $coachNavWebRoot);
?>

<!-- Coach Identity Hero (AC1 / AC2 / AC3) -->
<div class="coach-hero <?php echo htmlspecialchars($needsTeamPick ? 'choosing' : $heroState); ?>">
    <div class="container py-3">
        <?php if ($needsTeamPick): ?>
            <p class="coach-name-line"><?php echo $coachName; ?></p>
            <h2 class="h4 text-white mb-2">Select a team</h2>
            <p class="mb-3" style="font-size:0.9rem;opacity:0.9;">You are listed as owner for more than one team. Choose one to continue.</p>
            <form method="post" action="dashboard.php" class="bg-white p-3 rounded shadow-sm" style="max-width:28rem;">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(Auth::generateCSRFToken()); ?>" />
                <input type="hidden" name="coach_team_action" value="select" />
                <label for="coach_pick_team_id" class="form-label text-dark mb-1">Team</label>
                <select name="team_id" id="coach_pick_team_id" class="form-select mb-3" required>
                    <?php foreach ($assignRows as $pickRow): ?>
                        <option value="<?php echo (int) $pickRow['team_id']; ?>">
                            <?php
                            $optMeta = trim(
                                (string) ($pickRow['league_name'] ?? '')
                                . (isset($pickRow['season_name']) ? ' · ' . (string) $pickRow['season_name'] : '')
                            );
                            echo htmlspecialchars(($pickRow['team_name'] ?? 'Team') . ($optMeta !== '' ? ' — ' . $optMeta : ''));
                            ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-primary">Continue</button>
            </form>

        <?php elseif ($heroState === 'active'): ?>
            <div class="coach-name-line"><?php echo $coachName; ?></div>
            <h1 class="coach-hero-team"><?php echo sanitize($teamName); ?></h1>
            <div class="coach-hero-meta">
                <?php echo sanitize($leagueName); ?>
                <?php if ($seasonLabel): ?> · <?php echo sanitize($seasonLabel); ?><?php endif; ?>
                <?php if ($divLabel): ?> · <?php echo sanitize($divLabel); ?><?php endif; ?>
            </div>
            <span class="badge status-team-owner mt-2">Team Owner</span>
            <?php if ($nAssignments > 1): ?>
                <form method="post" action="dashboard.php" class="mt-2">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(Auth::generateCSRFToken()); ?>" />
                    <input type="hidden" name="coach_team_action" value="clear" />
                    <button type="submit" class="btn btn-sm btn-outline-light">Switch team</button>
                </form>
            <?php endif; ?>

        <?php elseif ($heroState === 'pending'): ?>
            <div class="coach-name-line"><?php echo $coachName; ?></div>
            <h1 class="coach-hero-team"><?php echo sanitize($teamName ?: 'Team Registration'); ?></h1>
            <span class="badge status-team-pending mt-1">Pending Team Approval</span>
            <p class="mt-2 mb-0" style="font-size:0.9rem;opacity:0.9;">
                Your team registration is pending admin review. You'll receive an email when approved.
            </p>

        <?php else: /* unassigned */ ?>
            <div class="coach-name-line"><?php echo $coachName; ?></div>
            <h1 class="coach-hero-team">No team assigned</h1>
            <p class="mt-1 mb-0" style="font-size:0.9rem;opacity:0.85;">
                No team assigned — contact your admin
            </p>
        <?php endif; ?>
    </div>
</div>

<!-- Action Card Grid (AC1 / AC2 / AC3) -->
<?php
$isActive = ($heroState === 'active' && !$needsTeamPick);
// [href, fa-icon-class, icon-bg-color, label, sub-label, disabled?]
$cards = [
    ['score-input.php',     'fas fa-baseball-ball', '#28a745', 'Score Input',     'Submit game scores',       !$isActive],
    ['schedule-change.php', 'fas fa-calendar-alt',  '#fd7e14', 'Schedule Change', 'Request game reschedule',  !$isActive],
    ['schedule.php',        'fas fa-list-ul',        '#007bff', 'My Schedule',     'View team games',          !$isActive],
    ['contacts.php',        'fas fa-address-book',   '#6f42c1', 'Contacts',        'League contact directory', false],
];
?>
<div class="container">
    <div class="coach-action-grid">
        <?php foreach ($cards as [$href, $icon, $color, $label, $sub, $disabled]): ?>
            <a href="<?php echo $disabled ? '#' : htmlspecialchars($href); ?>"
               class="coach-action-card<?php echo $disabled ? ' disabled' : ''; ?>"
               aria-label="<?php echo htmlspecialchars($label); ?>"
               <?php echo $disabled ? 'aria-disabled="true" tabindex="-1"' : ''; ?>>
                <div class="card-icon" style="background-color:<?php echo $color; ?>;">
                    <i class="<?php echo $icon; ?>"></i>
                </div>
                <div>
                    <div class="fw-semibold"><?php echo htmlspecialchars($label); ?></div>
                    <div class="text-muted" style="font-size:0.8rem;"><?php echo htmlspecialchars($sub); ?></div>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
</div>

<!-- Footer -->
<footer class="bg-light mt-5 py-4">
    <div class="container">
        <div class="row">
            <div class="col-12 text-center">
                <p>&copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. All rights reserved.</p>
                <p><small>Version <?php echo APP_VERSION; ?></small></p>
            </div>
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
