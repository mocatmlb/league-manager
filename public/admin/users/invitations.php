<?php
/**
 * District 8 Travel League - Admin Coach Invitations
 */

require_once __DIR__ . '/../../../includes/env-loader.php';
require_once EnvLoader::getPath('includes/admin_bootstrap.php');
require_once EnvLoader::getPath('includes/PermissionGuard.php');
require_once EnvLoader::getPath('includes/InvitationService.php');

PermissionGuard::requireRole('administrator', '/public/admin/login.php');

$service = new InvitationService();
$currentUser = Auth::getCurrentUser();
$adminUserId = (int) ($currentUser['id'] ?? 0);
$message = '';
$error = '';
$devInvitationUrl = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid form submission. Please try again.';
    } else {
        $action = (string) ($_POST['action'] ?? '');

        try {
            if ($action === 'send') {
                $invitationUrl = $service->send((string) ($_POST['email'] ?? ''), $adminUserId);
                if (defined('EMAIL_DEV_LOG_ONLY') && EMAIL_DEV_LOG_ONLY === true) {
                    $_SESSION['flash_success'] = 'Invitation created (dev mode — no email sent).';
                    $_SESSION['flash_dev_invitation_url'] = $invitationUrl;
                } else {
                    $_SESSION['flash_success'] = 'Invitation sent successfully.';
                }
            } elseif ($action === 'resend') {
                $invitationUrl = $service->resend((int) ($_POST['invitation_id'] ?? 0), $adminUserId);
                if (defined('EMAIL_DEV_LOG_ONLY') && EMAIL_DEV_LOG_ONLY === true) {
                    $_SESSION['flash_success'] = 'Invitation recreated (dev mode — no email sent).';
                    $_SESSION['flash_dev_invitation_url'] = $invitationUrl;
                } else {
                    $_SESSION['flash_success'] = 'Invitation resent successfully.';
                }
            } elseif ($action === 'cancel') {
                $service->cancel((int) ($_POST['invitation_id'] ?? 0), $adminUserId);
                $_SESSION['flash_success'] = 'Invitation cancelled successfully.';
            }
            header('Location: invitations.php');
            exit;
        } catch (EmailAlreadyRegisteredException $e) {
            $error = $e->getMessage();
        } catch (Throwable $e) {
            Logger::error('Invitation action failed', ['action' => $action, 'error' => $e->getMessage()]);
            $error = 'Unable to process invitation action right now.';
        }
    }
}

if (isset($_SESSION['flash_success'])) {
    $message = (string) $_SESSION['flash_success'];
    unset($_SESSION['flash_success']);
}
if (isset($_SESSION['flash_dev_invitation_url'])) {
    $devInvitationUrl = (string) $_SESSION['flash_dev_invitation_url'];
    unset($_SESSION['flash_dev_invitation_url']);
}

$rows = $service->getPendingList();
$pageTitle = 'Coach Invitations — District 8 Travel League';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo sanitize($pageTitle); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../../assets/css/admin.css" rel="stylesheet">
</head>
<body>
<?php include EnvLoader::getPath('includes/nav.php'); ?>
<div class="container py-4">
    <h1 class="h4 mb-3">Coach Invitations</h1>

    <?php if ($message !== ''): ?>
        <div class="alert alert-success" role="alert"><?php echo sanitize($message); ?></div>
    <?php endif; ?>
    <?php if ($devInvitationUrl !== ''): ?>
        <div class="alert alert-warning" role="alert">
            <strong>Dev mode:</strong> No email was sent. Share this link manually:<br>
            <a href="<?php echo htmlspecialchars($devInvitationUrl, ENT_QUOTES, 'UTF-8'); ?>" class="font-monospace small">
                <?php echo htmlspecialchars($devInvitationUrl, ENT_QUOTES, 'UTF-8'); ?>
            </a>
        </div>
    <?php endif; ?>
    <?php if ($error !== ''): ?>
        <div class="alert alert-danger" role="alert"><?php echo sanitize($error); ?></div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-header">Send Invitation</div>
        <div class="card-body">
            <form method="POST" class="row g-3">
                <input type="hidden" name="csrf_token" value="<?php echo sanitize(Auth::generateCSRFToken()); ?>">
                <input type="hidden" name="action" value="send">
                <div class="col-md-8">
                    <label class="form-label" for="email">Coach Email</label>
                    <input id="email" name="email" type="email" class="form-control form-control-lg" required>
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary btn-lg w-100">Send Invitation</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">Invitations</div>
        <div class="card-body">
            <?php if (empty($rows)): ?>
                <div class="text-muted">No invitations sent yet. Use the form above to invite a coach.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead>
                        <tr>
                            <th>Email</th>
                            <th>Sent</th>
                            <th>Expiry</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($rows as $row): ?>
                            <?php $status = (string) ($row['computed_status'] ?? $row['status']); ?>
                            <tr>
                                <td><?php echo sanitize((string) $row['email']); ?></td>
                                <td><?php echo sanitize((string) $row['created_at']); ?></td>
                                <td><?php echo sanitize((string) $row['expires_at']); ?></td>
                                <td><span class="badge status-<?php echo sanitize($status); ?>"><?php echo sanitize(ucfirst($status)); ?></span></td>
                                <td class="text-end">
                                    <?php if ($status === 'pending'): ?>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="csrf_token" value="<?php echo sanitize(Auth::generateCSRFToken()); ?>">
                                            <input type="hidden" name="action" value="resend">
                                            <input type="hidden" name="invitation_id" value="<?php echo (int) $row['id']; ?>">
                                            <button class="btn btn-sm btn-outline-primary" type="submit">Resend</button>
                                        </form>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="csrf_token" value="<?php echo sanitize(Auth::generateCSRFToken()); ?>">
                                            <input type="hidden" name="action" value="cancel">
                                            <input type="hidden" name="invitation_id" value="<?php echo (int) $row['id']; ?>">
                                            <button class="btn btn-sm btn-outline-danger" type="submit">Cancel</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>
