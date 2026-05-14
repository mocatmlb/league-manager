<?php
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

require_once EnvLoader::getPath('includes/admin_bootstrap.php');
require_once EnvLoader::getPath('includes/ChatService.php');

$db = Database::getInstance();
$currentUser = Auth::getCurrentUser();

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid form submission. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'save_settings') {
            $apiKey = trim($_POST['ai_api_key'] ?? '');
            $enabled = isset($_POST['ai_enabled']) ? '1' : '0';
            $dailyLimit = (int) ($_POST['ai_daily_limit_per_user'] ?? 50);
            $globalDailyLimit = (int) ($_POST['ai_global_daily_limit'] ?? 1400);
            $model = trim($_POST['ai_model'] ?? 'gemini-3.1-flash-lite');

            updateSetting('ai_api_key', $apiKey);
            updateSetting('ai_enabled', $enabled);
            updateSetting('ai_daily_limit_per_user', (string) $dailyLimit);
            updateSetting('ai_global_daily_limit', (string) $globalDailyLimit);
            updateSetting('ai_model', $model);

            logActivity('ai_settings_updated', 'AI Skipper settings updated');
            $message = 'AI Skipper settings saved successfully!';
        }
    }
}

$chat = new ChatService();
$stats = $chat->getUsageStats();

$apiKey = getSetting('ai_api_key', '');
$enabled = getSetting('ai_enabled', '0') === '1';
$dailyLimit = (int) getSetting('ai_daily_limit_per_user', '50');
$model = getSetting('ai_model', 'gemini-3.1-flash-lite');

$pageTitle = "AI Skipper - " . APP_NAME;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../../../assets/css/admin.css" rel="stylesheet">
</head>
<body>
    <?php
    $navPath = file_exists(__DIR__ . '/../../includes/nav.php')
        ? __DIR__ . '/../../includes/nav.php'
        : __DIR__ . '/../../../includes/nav.php';
    include $navPath;
    ?>

    <div class="container-fluid mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><i class="fas fa-robot"></i> AI Skipper</h1>
            <div>
                <span class="badge <?php echo $enabled ? 'bg-success' : 'bg-secondary'; ?> fs-6">
                    <?php echo $enabled ? 'Enabled' : 'Disabled'; ?>
                </span>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show"><?php echo sanitize($message); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show"><?php echo sanitize($error); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php endif; ?>

        <!-- Usage Stats -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card bg-primary text-white">
                    <div class="card-body text-center">
                        <h3><?php echo $stats['today_count']; ?></h3>
                        <div>Messages Today</div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card bg-info text-white">
                    <div class="card-body text-center">
                        <h3><?php echo $stats['total_count']; ?></h3>
                        <div>Total Messages</div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card bg-success text-white">
                    <div class="card-body text-center">
                        <h3><?php echo $stats['unique_users_today']; ?></h3>
                        <div>Active Users Today</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Settings Form -->
        <div class="card mb-4">
            <div class="card-header"><h3>Settings</h3></div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    <input type="hidden" name="action" value="save_settings">

                    <div class="mb-3">
                        <label class="form-label">Google Gemini API Key</label>
                        <input type="password" name="ai_api_key" class="form-control" value="<?php echo sanitize($apiKey); ?>" placeholder="AIzaSy...">
                        <div class="form-text">Get a free key at <a href="https://aistudio.google.com/apikey" target="_blank">aistudio.google.com/apikey</a></div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Model</label>
                            <select name="ai_model" class="form-select">
                                <option value="gemini-3.1-flash-lite" <?php echo $model === 'gemini-3.1-flash-lite' ? 'selected' : ''; ?>>Gemini 3.1 Flash Lite (500 req/day - Recommended)</option>
                                <option value="gemini-2.5-flash" <?php echo $model === 'gemini-2.5-flash' ? 'selected' : ''; ?>>Gemini 2.5 Flash (20 req/day)</option>
                                <option value="gemini-2.5-flash-lite" <?php echo $model === 'gemini-2.5-flash-lite' ? 'selected' : ''; ?>>Gemini 2.5 Flash Lite (20 req/day)</option>
                            </select>
                            <div class="form-text">3.1 Flash Lite has the highest free tier limit (500 req/day).</div>
                    </div>

                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="ai_enabled" id="ai_enabled" value="1" <?php echo $enabled ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="ai_enabled">Enable AI Skipper for coaches and admins</label>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col">
                            <label class="form-label">Messages per user per day</label>
                            <input type="number" name="ai_daily_limit_per_user" class="form-control" value="<?php echo $dailyLimit; ?>" min="1" max="500" style="width: 120px;">
                            <div class="form-text">Per-user cap. 50 is a good default.</div>
                        </div>
                        <div class="col">
                            <label class="form-label">Global daily limit (all users)</label>
                            <input type="number" name="ai_global_daily_limit" class="form-control" value="<?php echo (int)getSetting('ai_global_daily_limit', '1400'); ?>" min="1" max="5000" style="width: 140px;">
                            <div class="form-text">Set below your model's free tier limit (3.1 Flash Lite = 500/day).</div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Settings</button>
                </form>
            </div>
        </div>

        <!-- Quick Links -->
        <div class="card">
            <div class="card-header"><h3>Manage</h3></div>
            <div class="card-body">
                <a href="knowledge-base.php" class="btn btn-outline-primary">
                    <i class="fas fa-book"></i> Manage Knowledge Base
                </a>
                <p class="text-muted mt-2 mb-0">Add Little League rules, local policies, and FAQs that Skipper will use to answer questions.</p>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
