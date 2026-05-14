<?php
/**
 * District 8 Travel League - Rules & Regulations
 *
 * Authenticated access to league documents. Requires any coach role.
 */

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

require_once EnvLoader::getPath('includes/coach_bootstrap.php');
require_once EnvLoader::getPath('includes/PermissionGuard.php');

PermissionGuard::requireRole('user', '/coaches/login.php');

$db = Database::getInstance();
$userId = (int) ($_SESSION['coach_user_id'] ?? 0);

$user = $db->fetchOne('SELECT first_name, last_name FROM users WHERE id = :id', ['id' => $userId]);
$teamRow = $db->fetchOne('SELECT t.team_name FROM teams t JOIN team_owners o ON t.team_id = o.team_id WHERE o.user_id = :id LIMIT 1', ['id' => $userId]);
$coachName = htmlspecialchars(trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')));
$teamName  = htmlspecialchars((string) ($teamRow['team_name'] ?? ''));

$documents = $db->fetchAll('SELECT document_id, title, description, filename, original_filename, file_size, file_type, upload_date FROM documents WHERE is_public = 1 ORDER BY upload_date DESC', []);

$pageTitle = 'Rules & Regulations — District 8 Travel League';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <?php include __DIR__ . '/../../includes/coaches_nav.php'; ?>

    <div class="container mt-4">
        <div class="row">
            <div class="col-12">
                <h1>Rules & Regulations</h1>
                <a href="dashboard.php" class="btn btn-outline-secondary btn-sm mb-3"><i class="fas fa-arrow-left me-1"></i>Back to Dashboard</a>

<?php if (empty($documents)): ?>
                <div class="alert alert-info">No documents have been uploaded yet. Check back soon.</div>
<?php else: ?>
                <div class="row row-cols-1 row-cols-md-3 g-3">
<?php foreach ($documents as $doc): ?>
                    <div class="col">
                        <div class="card h-100">
                            <div class="card-body">
                                <h5 class="card-title"><?php echo htmlspecialchars($doc['title']); ?></h5>
<?php if (!empty($doc['description'])): ?>
                                <p class="card-text text-muted"><?php echo htmlspecialchars($doc['description']); ?></p>
<?php endif; ?>
                                <small class="text-muted">Uploaded <?php echo htmlspecialchars(formatDate($doc['upload_date'] ?? '')); ?></small>
                                <div class="mt-2">
                                    <a href="../download-document.php?id=<?php echo (int) $doc['document_id']; ?>" class="btn btn-primary btn-sm" target="_blank">Download</a>
                                </div>
                            </div>
                        </div>
                    </div>
<?php endforeach; ?>
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
</body>
</html>
