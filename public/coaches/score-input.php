<?php
/**
 * District 8 Travel League - Score Input (Team-Scoped)
 *
 * Requires team_owner role. Uses ScoreService for all enforcement.
 */

try {
    $bootstrapPath = file_exists(__DIR__ . '/../includes/coach_bootstrap.php')
        ? __DIR__ . '/../includes/coach_bootstrap.php'   // Production
        : __DIR__ . '/../../includes/coach_bootstrap.php'; // Development
    require_once $bootstrapPath;
} catch (Throwable $e) {
    echo '<div class="alert alert-danger">Application error: ' . htmlspecialchars($e->getMessage()) . '</div>';
    exit;
}

require_once EnvLoader::getPath('includes/PermissionGuard.php');
require_once EnvLoader::getPath('includes/ActivityLogger.php');
require_once EnvLoader::getPath('includes/TeamScope.php');
require_once EnvLoader::getPath('includes/GameTimeGate.php');
require_once EnvLoader::getPath('includes/ScoreService.php');

PermissionGuard::requireRole('team_owner');

$db     = Database::getInstance();
$userId = (int) ($_SESSION['coach_user_id'] ?? 0);
$service = new ScoreService($db);

// Single CSRF token shared across all forms on this page (AC4 Story 10.1).
$csrfToken = Auth::generateCSRFToken();

// ---------------------------------------------------------------------------
// POST handler — PRG pattern (AR-10)
// ---------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash_error'] = 'Invalid form submission. Please try again.';
        header('Location: score-input.php');
        exit;
    }

    $action    = $_POST['action'] ?? 'submit';
    if (!in_array($action, ['submit', 'edit'], true)) {
        $action = 'submit';
    }
    $gameId    = (int) ($_POST['game_id'] ?? 0);
    $homeScore = (int) ($_POST['home_score'] ?? 0);
    $awayScore = (int) ($_POST['away_score'] ?? 0);

    if ($gameId <= 0) {
        $_SESSION['flash_error'] = 'Invalid game selection. Please try again.';
        header('Location: score-input.php');
        exit;
    }

    try {
        if ($action === 'edit') {
            $service->edit($userId, $gameId, $homeScore, $awayScore);
            $verb = 'updated';
        } else {
            $service->submit($userId, $gameId, $homeScore, $awayScore);
            $verb = 'submitted';
        }

        // Load game details for confirmation echo only after successful write (P6)
        $gameDetails = $db->fetchOne(
            'SELECT g.game_number, ht.team_name AS home_team_name,
                    at.team_name AS away_team_name, s.game_date
             FROM games g
             JOIN teams ht ON g.home_team_id = ht.team_id
             JOIN teams at ON g.away_team_id = at.team_id
             LEFT JOIN schedules s ON g.game_id = s.game_id
             WHERE g.game_id = :game_id',
            ['game_id' => $gameId]
        );

        // Confirmation echo (UX-DR17)
        $gameNum  = htmlspecialchars((string) ($gameDetails['game_number'] ?? ''), ENT_QUOTES, 'UTF-8');
        $gameDate = htmlspecialchars(formatDate($gameDetails['game_date'] ?? ''), ENT_QUOTES, 'UTF-8');
        $awayTeam = htmlspecialchars((string) ($gameDetails['away_team_name'] ?? 'Away'), ENT_QUOTES, 'UTF-8');
        $homeTeam = htmlspecialchars((string) ($gameDetails['home_team_name'] ?? 'Home'), ENT_QUOTES, 'UTF-8');
        $_SESSION['flash_success'] =
            "Score {$verb}. Game #{$gameNum}, {$gameDate} — {$awayTeam} {$awayScore}, {$homeTeam} {$homeScore}. Standings updated.";

        header('Location: score-input.php');
        exit;

    } catch (TeamScopeViolationException | GameNotEligibleException $e) {
        // P11 (DN-1): true 403 response — do not redirect
        http_response_code(403);
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>403 Not Authorized — District 8 Travel League</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
        </head>
        <body>
            <div class="container mt-5">
                <div class="alert alert-danger" role="alert">
                    <h4 class="alert-heading"><i class="fas fa-ban"></i> Not Authorized</h4>
                    <p>Score not submitted — you are not authorized to submit scores for this game.</p>
                    <hr>
                    <a href="score-input.php" class="btn btn-secondary">Return to Score Input</a>
                </div>
            </div>
        </body>
        </html>
        <?php
        exit;

    } catch (ScoreConflictException $e) {
        $_SESSION['flash_error'] = 'Score not saved — another submission was just processed for this game. Please reload and try again.';
        header('Location: score-input.php');
        exit;

    } catch (InvalidArgumentException $e) {
        $_SESSION['flash_error'] = 'Score not submitted — scores must be between 0 and 99.';
        $_SESSION['flash_preserved_game_id']    = $gameId;
        $_SESSION['flash_preserved_home_score'] = $homeScore;
        $_SESSION['flash_preserved_away_score'] = $awayScore;
        header('Location: score-input.php');
        exit;

    } catch (Throwable $e) {
        // UX-DR18: preserve entered scores on server/network error
        $_SESSION['flash_error'] =
            'Score not submitted — please check your connection and try again. Your scores are preserved.';
        $_SESSION['flash_preserved_game_id']    = $gameId;
        $_SESSION['flash_preserved_home_score'] = $homeScore;
        $_SESSION['flash_preserved_away_score'] = $awayScore;
        header('Location: score-input.php');
        exit;
    }
}

// ---------------------------------------------------------------------------
// GET — read flash, load data
// ---------------------------------------------------------------------------
$flashSuccess = '';
$flashError   = '';
$preservedGameId    = null;
$preservedHomeScore = '';
$preservedAwayScore = '';

if (isset($_SESSION['flash_success'])) {
    $flashSuccess = (string) $_SESSION['flash_success'];
    unset($_SESSION['flash_success']);
}
if (isset($_SESSION['flash_error'])) {
    $flashError = (string) $_SESSION['flash_error'];
    unset($_SESSION['flash_error']);
}
if (isset($_SESSION['flash_preserved_game_id'])) {
    $preservedGameId    = (int) $_SESSION['flash_preserved_game_id'];
    $preservedHomeScore = (string) ($_SESSION['flash_preserved_home_score'] ?? '');
    $preservedAwayScore = (string) ($_SESSION['flash_preserved_away_score'] ?? '');
    unset(
        $_SESSION['flash_preserved_game_id'],
        $_SESSION['flash_preserved_home_score'],
        $_SESSION['flash_preserved_away_score']
    );
}

$eligibleGames  = $service->getEligibleGames($userId);
$completedGames = $service->getCompletedGames($userId);

$gameCount    = count($eligibleGames);
$autoSelected = ($gameCount === 1) ? $eligibleGames[0] : null;

// P9: pre-populate team labels from eligibleGames for the multi-game error-restore case
$preservedGame = null;
if ($preservedGameId !== null && $gameCount > 1) {
    foreach ($eligibleGames as $eg) {
        if ((int) $eg['game_id'] === $preservedGameId) {
            $preservedGame = $eg;
            break;
        }
    }
}

$pageTitle = 'Score Input — District 8 Travel League';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/css/style.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <?php include EnvLoader::getPath('includes/coaches_nav.php'); ?>

    <div class="container mt-4">
        <div class="row">
            <div class="col-12">

                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1>Score Input</h1>
                    <a href="dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                </div>

                <!-- Flash messages (role="alert" per UX-DR19) -->
                <?php if ($flashSuccess): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars($flashSuccess, ENT_QUOTES, 'UTF-8'); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>

                <?php if ($flashError): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?php echo htmlspecialchars($flashError, ENT_QUOTES, 'UTF-8'); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>

                <!-- =========================================================
                     SUBMIT SCORE SECTION
                     ========================================================= -->
                <?php if ($gameCount === 0): ?>
                    <!-- AC2: Empty state -->
                    <div class="alert alert-info" role="alert">
                        <i class="fas fa-info-circle"></i>
                        No games currently need a score — games must be past their scheduled time to be eligible.
                    </div>

                <?php else: ?>
                <div class="card mb-4">
                    <div class="card-header bg-success text-white">
                        <h3 class="mb-0"><i class="fas fa-baseball-ball"></i> Submit Game Score</h3>
                    </div>
                    <div class="card-body">

                        <?php if ($gameCount > 1): ?>
                        <!-- AC4: Game selection dropdown -->
                        <div class="mb-4">
                            <label for="gameSelect" class="form-label fw-bold">Select a game</label>
                            <select id="gameSelect" class="form-select form-select-lg" onchange="onGameSelected(this)">
                                <option value="">— choose a game —</option>
                                <?php foreach ($eligibleGames as $g): ?>
                                <option value="<?php echo (int) $g['game_id']; ?>"
                                        data-away="<?php echo htmlspecialchars($g['away_team_name'], ENT_QUOTES, 'UTF-8'); ?>"
                                        data-home="<?php echo htmlspecialchars($g['home_team_name'], ENT_QUOTES, 'UTF-8'); ?>"
                                        data-gameid="<?php echo (int) $g['game_id']; ?>"
                                        <?php if ($preservedGameId === (int) $g['game_id']): ?>selected<?php endif; ?>>
                                     Game #<?php echo htmlspecialchars($g['game_number'], ENT_QUOTES, 'UTF-8'); ?>,
                                    <?php echo htmlspecialchars(formatDate($g['game_date']), ENT_QUOTES, 'UTF-8'); ?>,
                                    <?php echo htmlspecialchars($g['away_team_name'], ENT_QUOTES, 'UTF-8'); ?>
                                    @ <?php echo htmlspecialchars($g['home_team_name'], ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>

                        <?php if ($autoSelected): ?>
                        <!-- AC3: Auto-selected game banner -->
                        <div class="alert alert-secondary d-flex align-items-center gap-2 mb-4" role="status">
                            <i class="fas fa-calendar-day"></i>
                            <span>
                                <strong>Game #<?php echo htmlspecialchars($autoSelected['game_number'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                &mdash; <?php echo htmlspecialchars(formatDate($autoSelected['game_date']), ENT_QUOTES, 'UTF-8'); ?>
                                &mdash; <?php echo htmlspecialchars($autoSelected['away_team_name'], ENT_QUOTES, 'UTF-8'); ?>
                                @ <?php echo htmlspecialchars($autoSelected['home_team_name'], ENT_QUOTES, 'UTF-8'); ?>
                            </span>
                        </div>
                        <?php endif; ?>

                        <!-- Score entry form -->
                        <form id="scoreForm" method="POST"
                              <?php if ($gameCount > 1 && !$preservedGameId): ?>style="display:none"<?php endif; ?>>
                            <input type="hidden" name="csrf_token"
                                   value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="action" value="submit">
                            <input type="hidden" name="game_id" id="formGameId"
                                   value="<?php echo $autoSelected ? (int) $autoSelected['game_id'] : ($preservedGameId ?? ''); ?>">

                            <!-- VS Score Entry layout (UX-DR2, UX-DR7) -->
                            <div class="vs-score-entry mb-4">
                                <div class="text-center">
                                     <label class="form-label fw-bold" id="awayLabel" for="away_score">
                                        <?php
                                        $awayLabelText = $autoSelected['away_team_name']
                                            ?? ($preservedGame['away_team_name'] ?? 'Away Team');
                                        echo htmlspecialchars($awayLabelText, ENT_QUOTES, 'UTF-8');
                                        ?>
                                    </label>
                                    <input type="number"
                                           id="away_score"
                                           name="away_score"
                                           class="vs-score-input form-control text-center"
                                           inputmode="numeric"
                                           min="0"
                                           max="99"
                                           required
                                           value="<?php echo htmlspecialchars($preservedAwayScore, ENT_QUOTES, 'UTF-8'); ?>">
                                </div>
                                <div class="vs-label">VS</div>
                                <div class="text-center">
                                     <label class="form-label fw-bold" id="homeLabel" for="home_score">
                                        <?php
                                        $homeLabelText = $autoSelected['home_team_name']
                                            ?? ($preservedGame['home_team_name'] ?? 'Home Team');
                                        echo htmlspecialchars($homeLabelText, ENT_QUOTES, 'UTF-8');
                                        ?>
                                    </label>
                                    <input type="number"
                                           id="home_score"
                                           name="home_score"
                                           class="vs-score-input form-control text-center"
                                           inputmode="numeric"
                                           min="0"
                                           max="99"
                                           required
                                           value="<?php echo htmlspecialchars($preservedHomeScore, ENT_QUOTES, 'UTF-8'); ?>">
                                </div>
                            </div>

                            <div class="text-center">
                                <button type="submit" class="btn btn-success btn-lg">
                                    <i class="fas fa-save"></i> Submit Score
                                </button>
                            </div>
                        </form>

                    </div>
                </div>
                <?php endif; ?>

                <!-- =========================================================
                     EDIT SCORE SECTION (AC8 — FR-SCORE-4)
                     ========================================================= -->
                <?php if (!empty($completedGames)): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <button class="btn btn-link p-0 text-decoration-none text-dark fw-bold"
                                    type="button"
                                    data-bs-toggle="collapse"
                                    data-bs-target="#editScorePanel"
                                    aria-expanded="false">
                                <i class="fas fa-pencil-alt"></i> Edit a Previous Score
                            </button>
                        </h5>
                    </div>
                    <div class="collapse" id="editScorePanel">
                        <div class="card-body">
                            <p class="text-muted small mb-3">
                                Select a completed game below to update its recorded score.
                            </p>
                            <?php foreach ($completedGames as $cg): ?>
                            <details class="border rounded p-3 mb-3">
                                <summary class="fw-bold" style="cursor:pointer;">
                                    Game #<?php echo htmlspecialchars($cg['game_number'], ENT_QUOTES, 'UTF-8'); ?>
                                    &mdash; <?php echo htmlspecialchars(formatDate($cg['game_date']), ENT_QUOTES, 'UTF-8'); ?>
                                    &mdash; <?php echo htmlspecialchars($cg['away_team_name'], ENT_QUOTES, 'UTF-8'); ?>
                                    @ <?php echo htmlspecialchars($cg['home_team_name'], ENT_QUOTES, 'UTF-8'); ?>
                                    &nbsp;<span class="badge bg-secondary"><?php echo (int) $cg['away_score']; ?> – <?php echo (int) $cg['home_score']; ?></span>
                                </summary>
                                <form method="POST" class="mt-3">
                                    <input type="hidden" name="csrf_token"
                                           value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="action" value="edit">
                                    <input type="hidden" name="game_id" value="<?php echo (int) $cg['game_id']; ?>">

                                    <div class="vs-score-entry mb-3">
                                        <div class="text-center">
                                            <label class="form-label fw-bold"
                                                   for="away_score_<?php echo (int) $cg['game_id']; ?>">
                                                <?php echo htmlspecialchars($cg['away_team_name'], ENT_QUOTES, 'UTF-8'); ?>
                                            </label>
                                            <input type="number"
                                                   id="away_score_<?php echo (int) $cg['game_id']; ?>"
                                                   name="away_score"
                                                   class="vs-score-input form-control text-center"
                                                   inputmode="numeric"
                                                   min="0" max="99" required
                                                   value="<?php echo (int) $cg['away_score']; ?>">
                                        </div>
                                        <div class="vs-label">VS</div>
                                        <div class="text-center">
                                            <label class="form-label fw-bold"
                                                   for="home_score_<?php echo (int) $cg['game_id']; ?>">
                                                <?php echo htmlspecialchars($cg['home_team_name'], ENT_QUOTES, 'UTF-8'); ?>
                                            </label>
                                            <input type="number"
                                                   id="home_score_<?php echo (int) $cg['game_id']; ?>"
                                                   name="home_score"
                                                   class="vs-score-input form-control text-center"
                                                   inputmode="numeric"
                                                   min="0" max="99" required
                                                   value="<?php echo (int) $cg['home_score']; ?>">
                                        </div>
                                    </div>
                                    <div class="text-center">
                                        <button type="submit" class="btn btn-outline-primary btn-lg">
                                            <i class="fas fa-save"></i> Update Score
                                        </button>
                                    </div>
                                </form>
                            </details>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

            </div>
        </div>
    </div>

    <footer class="bg-light mt-5 py-4">
        <div class="container text-center">
            <p class="mb-0">&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8'); ?>. All rights reserved.</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // AC4: reveal score form when a game is selected from the dropdown
    function onGameSelected(select) {
        const form = document.getElementById('scoreForm');
        const opt  = select.options[select.selectedIndex];

        if (!opt.value) {
            form.style.display = 'none';
            return;
        }

        document.getElementById('formGameId').value = opt.value;
        document.getElementById('awayLabel').textContent = opt.dataset.away;
        document.getElementById('homeLabel').textContent = opt.dataset.home;
        document.getElementById('away_score').value = '';
        document.getElementById('home_score').value = '';

        form.style.display = '';
        document.getElementById('away_score').focus();
    }

    <?php if ($gameCount > 1 && $preservedGameId): ?>
    // Restore preserved game selection after error redirect
    (function () {
        const sel = document.getElementById('gameSelect');
        if (sel) {
            sel.value = <?php echo (int) $preservedGameId; ?>;
            onGameSelected(sel);
            document.getElementById('away_score').value = <?php echo json_encode($preservedAwayScore); ?>;
            document.getElementById('home_score').value = <?php echo json_encode($preservedHomeScore); ?>;
        }
    })();
    <?php endif; ?>
    </script>
</body>
</html>
