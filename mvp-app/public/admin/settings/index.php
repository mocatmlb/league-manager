<?php
/**
 * District 8 Travel League - Settings Management
 */

require_once '../../../includes/bootstrap.php';

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

            case 'update_coach_password':
                try {
                    $newPassword = $_POST['coach_password'];
                    $confirmPassword = $_POST['confirm_coach_password'];

                    if ($newPassword !== $confirmPassword) {
                        throw new Exception('Passwords do not match.');
                    }

                    // Hash and update coach password
                    $hashedPassword = Auth::hashPassword($newPassword);
                    updateSetting('coaches_password', $hashedPassword);

                    logActivity('coach_password_updated', 'Coach access password updated');
                    $message = 'Coach password updated successfully!';

                } catch (Exception $e) {
                    $error = 'Error updating coach password: ' . $e->getMessage();
                }
                break;
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
    'system-backup' => 'Backup & Restore'
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
    <?php include '../../../includes/nav.php'; ?>

    <div class="container-fluid">
        <div class="row">
            <!-- Settings Sidebar -->
            <div class="col-md-3 col-lg-2 d-md-block sidebar">
                <?php include '../../../includes/settings-sidebar.php'; ?>
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