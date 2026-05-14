<?php
/**
 * District 8 Travel League — Dark Coach Navbar
 *
 * Reusable nav component for coach-facing pages.
 *
 * Expected variables set by the including page (optional — fetched from DB if absent):
 *   $coachName (string) — Display name of the authenticated coach
 *   $teamName  (string) — Team name, or empty string if unassigned
 *   $coachNavWebRoot (string, optional) — Web path prefix to site root (default '../../' from public/coaches/)
 */

$_rootPath = isset($coachNavWebRoot) && is_string($coachNavWebRoot) && $coachNavWebRoot !== ''
    ? $coachNavWebRoot
    : '../../';

// Self-sufficient name lookup when the including page hasn't set $coachName/$teamName
if (!isset($coachName) || !isset($teamName)) {
    $_coachId = (int) ($_SESSION['coach_user_id'] ?? 0);
    $_cn = '';
    $_tn = '';
    if ($_coachId > 0 && class_exists('Database')) {
        try {
            $_db  = Database::getInstance();
            $_row = $_db->fetchOne(
                'SELECT u.first_name, u.last_name, t.team_name
                   FROM users u
                   LEFT JOIN team_owners o ON o.user_id = u.id
                   LEFT JOIN teams t ON t.team_id = o.team_id
                  WHERE u.id = :id
                  LIMIT 1',
                ['id' => $_coachId]
            );
            if ($_row) {
                $_cn = htmlspecialchars(trim(($_row['first_name'] ?? '') . ' ' . ($_row['last_name'] ?? '')), ENT_QUOTES, 'UTF-8');
                $_tn = htmlspecialchars((string) ($_row['team_name'] ?? ''), ENT_QUOTES, 'UTF-8');
            }
        } catch (Throwable $_e) {
            // Nav degrades gracefully if DB is unavailable
        }
        unset($_db, $_row, $_e);
    }
    if (!isset($coachName)) $coachName = $_cn;
    if (!isset($teamName))  $teamName  = $_tn;
    unset($_coachId, $_cn, $_tn);
}

// Determine whether to show "Register Team" link:
// shown only when user is active, has no row in team_owners, and has no pending registration
$_navUserId = (int) ($_SESSION['coach_user_id'] ?? 0);
$_navShowRegisterTeam = false;
if ($_navUserId > 0 && class_exists('Database')) {
    try {
        $_navDb = Database::getInstance();
        $_navUser = $_navDb->fetchOne(
            'SELECT status FROM users WHERE id = :id LIMIT 1',
            ['id' => $_navUserId]
        );
        if ($_navUser && ($_navUser['status'] ?? '') === 'active') {
            $_navHasTeam = $_navDb->fetchOne(
                'SELECT 1 FROM team_owners WHERE user_id = :uid LIMIT 1',
                ['uid' => $_navUserId]
            ) !== false;
            $_navHasPending = $_navDb->fetchOne(
                "SELECT 1 FROM teams WHERE submitted_by_user_id = :uid AND status = 'pending' LIMIT 1",
                ['uid' => $_navUserId]
            ) !== false;
            $_navShowRegisterTeam = !$_navHasTeam && !$_navHasPending;
        }
        unset($_navDb, $_navUser, $_navHasTeam, $_navHasPending);
    } catch (Throwable $_navE) {
        // Degrades gracefully — link simply won't appear
        unset($_navE);
    }
}
unset($_navUserId);

// Active-page detection
$_currentScript = basename($_SERVER['PHP_SELF'] ?? '', '.php');

function _coachNavActive(string $page): string {
    global $_currentScript;
    return $_currentScript === $page ? 'active" aria-current="page' : '';
}
?>
<nav class="navbar navbar-expand-lg navbar-dark" style="background-color:#212529;">
    <div class="container-fluid">
        <a class="navbar-brand" href="<?php echo $_rootPath; ?>index.php">
            <?php echo defined('APP_NAME') ? htmlspecialchars(APP_NAME) : 'D8TL'; ?>
        </a>
        <?php if (!empty($teamName)): ?>
            <span class="badge bg-secondary ms-2"><?php echo strtoupper($teamName); ?></span>
        <?php endif; ?>

        <button class="navbar-toggler" type="button"
                data-bs-toggle="collapse" data-bs-target="#coachNavbar"
                aria-controls="coachNavbar" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="coachNavbar">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link <?php echo _coachNavActive('dashboard'); ?>"
                       href="<?php echo $_rootPath; ?>coaches/dashboard.php">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo _coachNavActive('schedule'); ?>"
                       href="<?php echo $_rootPath; ?>coaches/schedule.php">
                        <i class="fas fa-list-ul"></i> My Schedule
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo _coachNavActive('score-input'); ?>"
                       href="<?php echo $_rootPath; ?>coaches/score-input.php">
                        <i class="fas fa-baseball-ball"></i> Score Input
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo _coachNavActive('schedule-change'); ?>"
                       href="<?php echo $_rootPath; ?>coaches/schedule-change.php">
                        <i class="fas fa-calendar-alt"></i> Schedule Change
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo _coachNavActive('contacts'); ?>"
                       href="<?php echo $_rootPath; ?>coaches/contacts.php">
                        <i class="fas fa-address-book"></i> Contacts
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo _coachNavActive('rules'); ?>"
                       href="<?php echo $_rootPath; ?>coaches/rules.php">
                        <i class="fas fa-book"></i> Rules
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link"
                       href="<?php echo $_rootPath; ?>schedule.php">
                        <i class="fas fa-calendar-alt"></i> Full Schedule
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link"
                       href="<?php echo $_rootPath; ?>standings.php">
                        <i class="fas fa-table"></i> Standings
                    </a>
                </li>
                <?php if ($_navShowRegisterTeam): ?>
                <li class="nav-item">
                    <a class="nav-link <?php echo _coachNavActive('team-register'); ?>"
                       href="<?php echo $_rootPath; ?>coaches/team-register.php">
                        <i class="fas fa-users"></i> Register Team
                    </a>
                </li>
                <?php endif; ?>
            </ul>

            <ul class="navbar-nav">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="coachUserMenu"
                       role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-user-circle me-1"></i><?php echo $coachName ?: 'Coach'; ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="coachUserMenu">
                        <li>
                            <a class="dropdown-item <?php echo _coachNavActive('profile'); ?>"
                               href="<?php echo $_rootPath; ?>coaches/profile.php">
                                <i class="fas fa-user-edit me-1"></i>My Profile
                            </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item" href="<?php echo $_rootPath; ?>coaches/logout.php">
                                <i class="fas fa-sign-out-alt me-1"></i>Logout
                            </a>
                        </li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>
<?php
// AI Chatbot Widget (Skipper)
$_skipperWidget = __DIR__ . '/ai-chat-widget.php';
if (file_exists($_skipperWidget)) {
    include $_skipperWidget;
}
unset($_skipperWidget);

unset($_rootPath, $_currentScript, $_navShowRegisterTeam); ?>
