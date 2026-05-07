<?php
/**
 * District 8 Travel League — Dark Coach Navbar
 *
 * Reusable nav component for coach-facing pages (Story 4.4+).
 *
 * Expected variables set by the including page:
 *   $coachName (string) — Display name of the authenticated coach
 *   $teamName  (string) — Team name, or empty string if unassigned
 *   $coachNavWebRoot (string, optional) — Web path prefix to site root (default '../../' from public/coaches/)
 */

$_rootPath = isset($coachNavWebRoot) && is_string($coachNavWebRoot) && $coachNavWebRoot !== ''
    ? $coachNavWebRoot
    : '../../';
?>
<nav class="navbar navbar-expand-lg navbar-dark" style="background-color:#212529;">
    <div class="container-fluid">
        <a class="navbar-brand" href="<?php echo $_rootPath; ?>index.php">
            <?php echo defined('APP_NAME') ? htmlspecialchars(APP_NAME) : 'D8TL'; ?>
        </a>
        <?php if (!empty($teamName)): ?>
            <span class="badge bg-secondary ms-1"><?php echo htmlspecialchars($teamName); ?></span>
        <?php endif; ?>

        <button class="navbar-toggler" type="button"
                data-bs-toggle="collapse" data-bs-target="#coachNavbar"
                aria-controls="coachNavbar" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="coachNavbar">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="coachUserMenu"
                       role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-user-circle me-1"></i><?php echo htmlspecialchars($coachName); ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="coachUserMenu">
                        <li>
                            <a class="dropdown-item" href="logout.php">
                                <i class="fas fa-sign-out-alt me-1"></i>Logout
                            </a>
                        </li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>
<?php unset($_rootPath); ?>
