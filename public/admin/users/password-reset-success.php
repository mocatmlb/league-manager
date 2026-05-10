<?php
/**
 * District 8 Travel League — Password Reset Confirmation
 *
 * One-time page that shows the temp password after an admin-initiated reset.
 * The temp password is read-and-cleared from session; refreshing shows nothing.
 */

$__dir   = __DIR__;
$__found = false;
for ($__i = 0; $__i < 6; $__i++) {
    if (file_exists($__dir . '/includes/env-loader.php')) {
        require_once $__dir . '/includes/env-loader.php';
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
    http_response_code(500);
    exit('Configuration error: env-loader not found');
}
unset($__dir, $__found, $__i);

@include_once EnvLoader::getPath('includes/admin_bootstrap.php');
Auth::requireAdmin();

$userId      = (int) ($_GET['id'] ?? 0);
$tempPassword = $_SESSION['temp_password'] ?? null;
unset($_SESSION['temp_password']);

// If there's no temp password in session (e.g. direct nav or refresh), redirect to detail
if ($tempPassword === null || $userId === 0) {
    header('Location: ' . ($userId > 0 ? 'detail.php?id=' . $userId : 'index.php'));
    exit;
}

$db = Database::getInstance();
$user = $db->fetchOne(
    'SELECT u.first_name, u.last_name FROM users u WHERE u.id = :id LIMIT 1',
    ['id' => $userId]
);
if ($user === false) {
    header('Location: index.php');
    exit;
}

$pageTitle = 'Password Reset — ' . sanitize($user['first_name'] . ' ' . $user['last_name']) . ' — ' . APP_NAME;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../../assets/css/style.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <?php
    $__nav = file_exists(__DIR__ . '/../../includes/nav.php')
        ? __DIR__ . '/../../includes/nav.php'
        : __DIR__ . '/../../../includes/nav.php';
    include $__nav;
    unset($__nav);
    ?>

    <div class="container mt-4" style="max-width: 640px;">
        <div class="mb-3">
            <a href="detail.php?id=<?php echo $userId; ?>" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-arrow-left"></i> Back to User Detail
            </a>
        </div>

        <div class="card border-warning">
            <div class="card-header bg-warning text-dark">
                <h5 class="mb-0">
                    <i class="fas fa-key me-1"></i>
                    Password Reset — <?php echo sanitize($user['first_name'] . ' ' . $user['last_name']); ?>
                </h5>
            </div>
            <div class="card-body">
                <div class="alert alert-warning mb-4">
                    <strong><i class="fas fa-exclamation-triangle me-1"></i>One-time display only.</strong>
                    This temporary password will not be shown again once you leave this page.
                </div>

                <p class="mb-1 text-muted">Temporary password:</p>
                <div class="input-group mb-3">
                    <input type="text" id="tempPwd" class="form-control form-control-lg font-monospace fw-bold"
                           value="<?php echo sanitize($tempPassword); ?>" readonly>
                    <button class="btn btn-outline-secondary" type="button" onclick="copyTempPwd()" title="Copy">
                        <i class="fas fa-copy"></i>
                    </button>
                </div>
                <p class="text-muted small mb-0">
                    Share this with the coach directly. They will be required to change it on their next login.
                </p>
            </div>
            <div class="card-footer text-end">
                <a href="detail.php?id=<?php echo $userId; ?>" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to User Detail
                </a>
                <a href="index.php" class="btn btn-outline-secondary ms-2">
                    <i class="fas fa-users"></i> User List
                </a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function copyTempPwd() {
            const el = document.getElementById('tempPwd');
            el.select();
            document.execCommand('copy');
            const btn = el.nextElementSibling;
            btn.innerHTML = '<i class="fas fa-check"></i>';
            setTimeout(() => { btn.innerHTML = '<i class="fas fa-copy"></i>'; }, 2000);
        }
    </script>
</body>
</html>
