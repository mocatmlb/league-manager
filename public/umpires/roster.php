<?php
define('D8TL_APP', true);

$envLoader = file_exists(__DIR__ . '/../includes/env-loader.php')
    ? __DIR__ . '/../includes/env-loader.php'
    : __DIR__ . '/../../includes/env-loader.php';
require_once $envLoader;
require_once EnvLoader::getPath('includes/bootstrap.php');
require_once EnvLoader::getPath('includes/PermissionGuard.php');
require_once EnvLoader::getPath('includes/UmpireRosterService.php');

PermissionGuard::requireRole('umpire', '/login.php');

$userId = (int) ($_SESSION['coach_user_id'] ?? 0);
if ($userId <= 0) {
    header('Location: /login.php');
    exit;
}

$rosterService = new UmpireRosterService();
$roster = $rosterService->getRoster(true);

$currentUser = Auth::getCurrentUser();
$name = htmlspecialchars(trim(($currentUser['first_name'] ?? '') . ' ' . ($currentUser['last_name'] ?? '')));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Umpire Roster — District 8 Travel League</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../../assets/css/style.css" rel="stylesheet">
</head>
<body class="bg-light">

    <?php
    $navPath = file_exists(__DIR__ . '/../includes/nav.php')
        ? __DIR__ . '/../includes/nav.php'
        : __DIR__ . '/../../includes/nav.php';
    include $navPath;
    ?>

    <div class="container mt-4">
        <div class="row">
            <div class="col-12">
                <h1 class="mb-3">Umpire Roster</h1>
                <p class="text-muted">Full list of registered umpires.</p>

<?php if (empty($roster)): ?>
                <div class="alert alert-info">No umpires found.</div>
<?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Level</th>
                                <th>Email</th>
                                <th>Phone</th>
                            </tr>
                        </thead>
                        <tbody>
<?php foreach ($roster as $u): ?>
                            <tr>
                                <td><?= htmlspecialchars(trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? ''))) ?></td>
                                <td><?= htmlspecialchars($u['umpire_level'] ?? '') ?></td>
                                <td><a href="mailto:<?= htmlspecialchars($u['email'] ?? '') ?>"><?= htmlspecialchars($u['email'] ?? '') ?></a></td>
                                <td><?= htmlspecialchars($u['phone'] ?? '') ?></td>
                            </tr>
<?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <p class="text-muted small"><?= count($roster) ?> umpire(s) listed.</p>
<?php endif; ?>

            </div>
        </div>
    </div>

    <footer class="bg-light mt-5 py-4">
        <div class="container text-center">
            <p class="mb-0">&copy; <?= date('Y') ?> <?= htmlspecialchars(APP_NAME ?? 'District 8 Travel League', ENT_QUOTES, 'UTF-8') ?>. All rights reserved.</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
