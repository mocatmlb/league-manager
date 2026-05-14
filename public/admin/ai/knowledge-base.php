<?php
$__dir = __DIR__;
$__found = false;
for ($__i = 0; $__i < 6; $__i++) {
    $__candidate = $__dir . '/includes/env-loader.php';
    if (file_exists($__candidate)) { require_once $__candidate; $__found = true; break; }
    $__dir = dirname($__dir);
}
if (!$__found) {
    if (!empty($_SERVER['DOCUMENT_ROOT']) && file_exists($_SERVER['DOCUMENT_ROOT'] . '/includes/env-loader.php')) {
        require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/env-loader.php';
    }
}
unset($__dir, $__found, $__i, $__candidate);

@include_once EnvLoader::getPath('includes/admin_bootstrap.php');

Auth::requireAdmin();

$db = Database::getInstance();
$currentUser = Auth::getCurrentUser();

$message = '';
$error = '';

$editEntry = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid form submission. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'add' || $action === 'edit') {
            $id = (int) ($_POST['id'] ?? 0);
            $category = trim($_POST['category'] ?? '');
            $title = trim($_POST['title'] ?? '');
            $content = trim($_POST['content'] ?? '');
            $sortOrder = (int) ($_POST['sort_order'] ?? 0);
            $isActive = isset($_POST['is_active']) ? 1 : 0;

            if (empty($title) || empty($content)) {
                $error = 'Title and content are required.';
            } else {
                try {
                    if ($action === 'add') {
                        $db->insert('knowledge_base', [
                            'category' => $category ?: 'general',
                            'title' => $title,
                            'content' => $content,
                            'sort_order' => $sortOrder,
                            'is_active' => $isActive,
                        ]);
                        logActivity('kb_entry_added', "Knowledge base entry added: {$title}");
                        $message = 'Entry added successfully!';
                    } else {
                        $db->update('knowledge_base', [
                            'category' => $category ?: 'general',
                            'title' => $title,
                            'content' => $content,
                            'sort_order' => $sortOrder,
                            'is_active' => $isActive,
                        ], 'id = ?', [$id]);
                        logActivity('kb_entry_updated', "Knowledge base entry updated: {$title}");
                        $message = 'Entry updated successfully!';
                    }
                } catch (Exception $e) {
                    $error = 'Error saving entry: ' . $e->getMessage();
                }
            }
        } elseif ($action === 'delete') {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id > 0) {
                $entry = $db->fetchOne("SELECT title FROM knowledge_base WHERE id = ?", [$id]);
                $db->delete('knowledge_base', 'id = ?', [$id]);
                logActivity('kb_entry_deleted', "Knowledge base entry deleted: " . ($entry['title'] ?? '#'.$id));
                $message = 'Entry deleted!';
            }
        } elseif ($action === 'toggle') {
            $id = (int) ($_POST['id'] ?? 0);
            $entry = $db->fetchOne("SELECT is_active FROM knowledge_base WHERE id = ?", [$id]);
            if ($entry) {
                $newStatus = $entry['is_active'] ? 0 : 1;
                $db->update('knowledge_base', ['is_active' => $newStatus], 'id = ?', [$id]);
                $message = 'Entry status toggled!';
            }
        }
    }
}

$category = $_GET['category'] ?? '';
$entries = $db->fetchAll(
    "SELECT * FROM knowledge_base" . ($category ? " WHERE category = ?" : "") . " ORDER BY category, sort_order ASC, title ASC",
    $category ? [$category] : []
);

$categories = $db->fetchAll("SELECT DISTINCT category FROM knowledge_base ORDER BY category ASC");

if (isset($_GET['edit'])) {
    $editEntry = $db->fetchOne("SELECT * FROM knowledge_base WHERE id = ?", [(int) $_GET['edit']]);
}

$pageTitle = "Knowledge Base - " . APP_NAME;
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
            <h1><i class="fas fa-book"></i> Skipper's Knowledge Base</h1>
            <a href="index.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left"></i> Back to Settings</a>
        </div>

        <p class="text-muted">This is what Skipper knows. Add Little League rules, local policies, website guides, and FAQs here.</p>

        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show"><?php echo sanitize($message); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show"><?php echo sanitize($error); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php endif; ?>

        <div class="row">
            <!-- Add/Edit Form -->
            <div class="col-md-5">
                <div class="card mb-4">
                    <div class="card-header">
                        <h3><?php echo $editEntry ? 'Edit Entry' : 'Add Entry'; ?></h3>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                            <input type="hidden" name="action" value="<?php echo $editEntry ? 'edit' : 'add'; ?>">
                            <?php if ($editEntry): ?>
                                <input type="hidden" name="id" value="<?php echo $editEntry['id']; ?>">
                            <?php endif; ?>

                            <div class="mb-3">
                                <label class="form-label">Category</label>
                                <select name="category" class="form-select">
                                    <option value="rules" <?php echo ($editEntry['category'] ?? '') === 'rules' ? 'selected' : ''; ?>>Little League Rules</option>
                                    <option value="local_rules" <?php echo ($editEntry['category'] ?? '') === 'local_rules' ? 'selected' : ''; ?>>Local Rules</option>
                                    <option value="website_guide" <?php echo ($editEntry['category'] ?? '') === 'website_guide' ? 'selected' : ''; ?>>Website Guide</option>
                                    <option value="faq" <?php echo ($editEntry['category'] ?? '') === 'faq' ? 'selected' : ''; ?>>FAQ</option>
                                    <option value="contacts" <?php echo ($editEntry['category'] ?? '') === 'contacts' ? 'selected' : ''; ?>>Contacts</option>
                                    <option value="general" <?php echo ($editEntry['category'] ?? '') === 'general' ? 'selected' : ''; ?>>General</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Title</label>
                                <input type="text" name="title" class="form-control" value="<?php echo sanitize($editEntry['title'] ?? ''); ?>" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Content</label>
                                <textarea name="content" class="form-control" rows="10" required><?php echo sanitize($editEntry['content'] ?? ''); ?></textarea>
                                <div class="form-text">Write in plain text. The more detail you include, the better Skipper can answer.</div>
                            </div>

                            <div class="row mb-3">
                                <div class="col">
                                    <label class="form-label">Sort Order</label>
                                    <input type="number" name="sort_order" class="form-control" value="<?php echo $editEntry['sort_order'] ?? 0; ?>" min="0" style="width: 80px;">
                                </div>
                                <div class="col pt-4">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1" <?php echo (!isset($editEntry) || $editEntry['is_active']) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="is_active">Active</label>
                                    </div>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> <?php echo $editEntry ? 'Update' : 'Add'; ?> Entry
                            </button>
                            <?php if ($editEntry): ?>
                                <a href="knowledge-base.php" class="btn btn-secondary">Cancel</a>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Entries List -->
            <div class="col-md-7">
                <!-- Category Filter -->
                <div class="mb-3">
                    <a href="knowledge-base.php" class="btn btn-sm <?php echo !$category ? 'btn-primary' : 'btn-outline-secondary'; ?>">All</a>
                    <?php foreach ($categories as $cat): ?>
                        <a href="?category=<?php echo urlencode($cat['category']); ?>" class="btn btn-sm <?php echo $category === $cat['category'] ? 'btn-primary' : 'btn-outline-secondary'; ?>"><?php echo sanitize($cat['category']); ?></a>
                    <?php endforeach; ?>
                </div>

                <?php if (empty($entries)): ?>
                    <div class="alert alert-info">No knowledge base entries yet. Add one to get started!</div>
                <?php else: ?>
                    <?php $currentCategory = ''; ?>
                    <?php foreach ($entries as $entry): ?>
                        <?php if ($entry['category'] !== $currentCategory): ?>
                            <?php $currentCategory = $entry['category']; ?>
                            <h5 class="mt-3 mb-2 text-muted text-uppercase" style="font-size: 0.85rem;"><?php echo sanitize($currentCategory); ?></h5>
                        <?php endif; ?>
                        <div class="card mb-2 <?php echo !$entry['is_active'] ? 'opacity-50' : ''; ?>">
                            <div class="card-body py-2 d-flex justify-content-between align-items-center">
                                <div>
                                    <strong><?php echo sanitize($entry['title']); ?></strong>
                                    <span class="text-muted ms-2" style="font-size: 0.8rem;">#<?php echo $entry['id']; ?></span>
                                    <?php if (!$entry['is_active']): ?>
                                        <span class="badge bg-secondary">Disabled</span>
                                    <?php endif; ?>
                                </div>
                                <div class="btn-group">
                                    <a href="?edit=<?php echo $entry['id']; ?>" class="btn btn-sm btn-outline-primary" title="Edit"><i class="fas fa-edit"></i></a>
                                    <form method="POST" style="display:inline" onsubmit="return confirm('Toggle this entry?')">
                                        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                        <input type="hidden" name="action" value="toggle">
                                        <input type="hidden" name="id" value="<?php echo $entry['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-secondary" title="<?php echo $entry['is_active'] ? 'Disable' : 'Enable'; ?>">
                                            <i class="fas <?php echo $entry['is_active'] ? 'fa-eye' : 'fa-eye-slash'; ?>"></i>
                                        </button>
                                    </form>
                                    <form method="POST" style="display:inline" onsubmit="return confirm('Delete this entry?')">
                                        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo $entry['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete"><i class="fas fa-trash"></i></button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
