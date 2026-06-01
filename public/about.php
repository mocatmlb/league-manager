<?php
define('D8TL_APP', true);

$includePath = file_exists(__DIR__ . '/includes/env-loader.php')
    ? __DIR__ . '/includes'
    : __DIR__ . '/../includes';

require_once $includePath . '/env-loader.php';
require_once EnvLoader::getPath('includes/bootstrap.php');

$pageTitle = "About - " . APP_NAME;
$aboutContent = getSetting('about_page_content', '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="<?php echo dirname($_SERVER['SCRIPT_NAME']); ?>/../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <?php
    $navPath = file_exists(__DIR__ . '/includes/nav.php')
        ? __DIR__ . '/includes/nav.php'
        : dirname(__DIR__) . '/includes/nav.php';
    include $navPath;
    ?>

    <div class="container mt-4 mb-5">
        <div class="row justify-content-center">
            <div class="col-lg-9">
                <h1 class="mb-4">About <?php echo htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8'); ?></h1>

                <div class="about-content">
                    <?php if (!empty($aboutContent)): ?>
                        <?php echo $aboutContent; ?>
                    <?php else: ?>
                        <p class="text-muted">About content coming soon.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <footer class="bg-light py-4">
        <div class="container">
            <div class="row">
                <div class="col-12 text-center">
                    <p class="mb-1">&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8'); ?>. All rights reserved.</p>
                    <p class="mb-0 small">
                        <a href="about.php" class="text-muted me-2">About</a>
                        <a href="privacy-policy.php" class="text-muted me-2">Privacy Policy</a>
                        <a href="terms.php" class="text-muted">Terms &amp; Conditions</a>
                    </p>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
