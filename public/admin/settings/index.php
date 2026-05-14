<?php
/**
 * District 8 Travel League - Settings Management
 */

// Robust EnvLoader include: locate includes/env-loader.php regardless of layout
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

@include_once EnvLoader::getPath('includes/admin_bootstrap.php');
@include_once EnvLoader::getPath('includes/ActivityLogger.php');
@include_once EnvLoader::getPath('includes/CutoverService.php');

// Require admin authentication
Auth::requireAdmin();

$db = Database::getInstance();
$currentUser = Auth::getCurrentUser();

// Get current section
$currentSection = $_GET['section'] ?? 'general';

// Handle form submissions
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid form submission. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'update_timezone':
                try {
                    $timezone = sanitize($_POST['timezone']);
                    
                    // Validate timezone
                    $availableTimezones = getAvailableTimezones();
                    if (!array_key_exists($timezone, $availableTimezones)) {
                        throw new Exception('Invalid timezone selected.');
                    }
                    
                    // Update timezone setting
                    updateSetting('timezone', $timezone);
                    
                    logActivity('timezone_updated', "Timezone changed to: $timezone");
                    $message = 'Timezone updated successfully!';
                    
                } catch (Exception $e) {
                    $error = 'Error updating timezone: ' . $e->getMessage();
                }
                break;
                
            case 'update_general':
                try {
                    // Update general settings
                    updateSetting('league_name', sanitize($_POST['league_name']));
                    updateSetting('contact_email', sanitize($_POST['contact_email']));
                    updateSetting('weather_hotline', sanitize($_POST['weather_hotline']));
                    updateSetting('field_maintenance_phone', sanitize($_POST['field_maintenance_phone']));
                    
                    logActivity('general_settings_updated', 'General settings updated');
                    $message = 'General settings updated successfully!';
                    
                } catch (Exception $e) {
                    $error = 'Error updating general settings: ' . $e->getMessage();
                }
                break;

            case 'update_open_registration':
                try {
                    require_once EnvLoader::getPath('includes/RegistrationSettingsService.php');
                    $enabled = isset($_POST['open_registration']) && $_POST['open_registration'] === '1';
                    RegistrationSettingsService::setOpenRegistration(
                        $enabled,
                        (int) ($currentUser['id'] ?? 0)
                    );
                    $message = 'Registration toggle updated successfully!';
                } catch (Exception $e) {
                    $error = 'Error updating registration toggle: ' . $e->getMessage();
                }
                break;

            case 'upload_document':
                try {
                    if (!isset($_FILES['document_file']) || $_FILES['document_file']['error'] !== UPLOAD_ERR_OK) {
                        throw new Exception('File upload failed. Please try again.');
                    }

                    $file = $_FILES['document_file'];
                    $title = sanitize($_POST['title'] ?? '');
                    $description = sanitize($_POST['description'] ?? '');
                    $isPublic = isset($_POST['is_public']) ? 1 : 0;

                    if (empty($title)) {
                        throw new Exception('Title is required.');
                    }

                    $maxSize = 10 * 1024 * 1024;
                    if ($file['size'] > $maxSize) {
                        throw new Exception('File size exceeds the 10MB limit.');
                    }

                    $allowedTypes = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'text/plain'];
                    if (!in_array($file['type'], $allowedTypes)) {
                        throw new Exception('Invalid file type. Allowed: PDF, DOC, DOCX, XLS, XLSX, TXT.');
                    }

                    $uploadDir = file_exists(__DIR__ . '/../../includes/env-loader.php')
                        ? __DIR__ . '/../../uploads/documents/'
                        : __DIR__ . '/../../../uploads/documents/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }

                    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                    $filename = uniqid('doc_') . '.' . $ext;

                    if (!move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
                        throw new Exception('Failed to save file.');
                    }

                    $db->insert('documents', [
                        'title' => $title,
                        'description' => $description,
                        'filename' => $filename,
                        'original_filename' => $file['name'],
                        'file_size' => $file['size'],
                        'file_type' => $file['type'],
                        'upload_date' => date('Y-m-d H:i:s'),
                        'is_public' => $isPublic
                    ]);

                    logActivity('document_uploaded', "Document uploaded: $title");
                    $message = 'Document uploaded successfully!';
                } catch (Exception $e) {
                    $error = 'Error uploading document: ' . $e->getMessage();
                }
                break;

            case 'delete_document':
                try {
                    $documentId = (int) ($_POST['document_id'] ?? 0);
                    if ($documentId <= 0) {
                        throw new Exception('Invalid document ID.');
                    }

                    $doc = $db->fetchOne("SELECT filename FROM documents WHERE document_id = ?", [$documentId]);
                    if ($doc) {
                        $filePath = (file_exists(__DIR__ . '/../../includes/env-loader.php')
                            ? __DIR__ . '/../../uploads/documents/'
                            : __DIR__ . '/../../../uploads/documents/') . $doc['filename'];
                        if (file_exists($filePath)) {
                            unlink($filePath);
                        }
                        $db->delete('documents', 'document_id = ?', [$documentId]);
                        logActivity('document_deleted', "Document #$documentId deleted");
                        $message = 'Document deleted successfully!';
                    } else {
                        throw new Exception('Document not found.');
                    }
                } catch (Exception $e) {
                    $error = 'Error deleting document: ' . $e->getMessage();
                }
                break;

            case 'disable_shared_credential':
                try {
                    $adminId = (int) ($currentUser['id'] ?? 0);
                    if ($adminId <= 0) {
                        throw new Exception('Invalid admin session. Please log in again.');
                    }
                    $svc = new CutoverService();
                    $svc->disableSharedCredential($adminId);
                    $_SESSION['cutover_flash_success'] =
                        'Shared credential disabled. All coach access is now through individual accounts. Rollback window: 30 days.';
                } catch (CutoverGapsRemainingException $e) {
                    $_SESSION['cutover_flash_error'] =
                        'Cannot disable — gaps were detected. Please resolve all gaps first.';
                } catch (Exception $e) {
                    $_SESSION['cutover_flash_error'] = 'Error disabling shared credential: ' . $e->getMessage();
                }
                header('Location: ?section=cutover');
                exit;
        }
    }
}

// Get current settings
$currentTimezone = getSetting('timezone', 'America/New_York');
$leagueName = getSetting('league_name', 'District 8 Travel League');
$contactEmail = getSetting('contact_email', '');
$weatherHotline = getSetting('weather_hotline', '');
$fieldMaintenancePhone = getSetting('field_maintenance_phone', '');

$availableTimezones = getAvailableTimezones();

// Get section title
$sectionTitles = [
    'general' => 'General Settings',
    'contacts' => 'League Contacts',
    'email-setup' => 'Email Setup',
    'email-templates' => 'Email Templates',
    'email-recipients' => 'Email Recipients',
    'users-admin' => 'Admin Users',
    'users-coach' => 'Coach Access',
    'system-timezone' => 'Timezone Settings',
    'system-backup' => 'Backup & Restore',
    'documents' => 'Documents',
    'cutover' => 'Migration Cutover',
];

$pageTitle = ($sectionTitles[$currentSection] ?? 'Settings') . " - " . APP_NAME;
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
    <style>
        .settings-content {
            min-height: calc(100vh - 56px);
            padding: 1.5rem;
        }
        .section-header {
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #dee2e6;
        }
    </style>
</head>
<body>
    <?php
    $__nav = file_exists(__DIR__ . '/../../includes/nav.php')
        ? __DIR__ . '/../../includes/nav.php'      // Production: /admin/settings -> ../../includes
        : __DIR__ . '/../../../includes/nav.php';  // Development: /public/admin/settings -> ../../../includes
    include $__nav;
    unset($__nav);
    ?>

    <div class="container-fluid">
        <div class="row">
            <!-- Settings Sidebar -->
            <div class="col-md-3 col-lg-2 d-md-block sidebar">
                <?php
                $__settings_sidebar = file_exists(__DIR__ . '/../../includes/settings-sidebar.php')
                    ? __DIR__ . '/../../includes/settings-sidebar.php'      // Production: /admin/settings -> ../../includes
                    : __DIR__ . '/../../../includes/settings-sidebar.php';  // Development: /public/admin/settings -> ../../../includes
                include $__settings_sidebar;
                unset($__settings_sidebar);
                ?>
                </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 ms-sm-auto px-md-4">
                <div class="settings-content">
                    <!-- Section Header -->
                    <div class="section-header">
                        <h1 class="h2"><?php echo $sectionTitles[$currentSection] ?? 'Settings'; ?></h1>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle"></i> <?php echo sanitize($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-triangle"></i> <?php echo sanitize($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

                    <!-- Section Content -->
                    <?php
                    switch ($currentSection):
                        case 'general':
                            include 'sections/general.php';
                            break;
                        case 'contacts':
                            include 'sections/contacts.php';
                            break;
                        case 'email-setup':
                            include 'sections/email-setup.php';
                            break;
                        case 'email-templates':
                            include 'sections/email-templates.php';
                            break;
                        case 'email-recipients':
                            include 'sections/email-recipients.php';
                            break;
                        case 'users-admin':
                            include 'sections/users-admin.php';
                            break;
                        case 'users-coach':
                            include 'sections/users-coach.php';
                            break;
                        case 'system-timezone':
                            include 'sections/system-timezone.php';
                            break;
                        case 'system-backup':
                            include 'sections/system-backup.php';
                            break;
                        case 'documents':
                            include 'sections/documents.php';
                            break;
                        case 'cutover':
                            include 'sections/cutover.php';
                            break;
                        default:
                            echo '<div class="alert alert-warning">Unknown settings section.</div>';
                    endswitch;
                    ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../../assets/js/timezone.js"></script>
    <?php outputTimezoneJS(); ?>
</body>
</html>