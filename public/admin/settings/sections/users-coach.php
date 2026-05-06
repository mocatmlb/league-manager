<?php
/**
 * Coach Access Section
 */
?>

<?php
require_once EnvLoader::getPath('includes/RegistrationSettingsService.php');
$openRegistrationEnabled = getSetting('open_registration', '0') === '1';
$registerUrl = RegistrationSettingsService::buildRegistrationUrl();
$qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . urlencode($registerUrl);
?>

<div class="card mb-4">
    <div class="card-header">
        <h5 class="card-title mb-0">Coach Self-Registration</h5>
    </div>
    <div class="card-body">
        <form method="POST" class="mb-3">
            <input type="hidden" name="csrf_token" value="<?php echo sanitize(Auth::generateCSRFToken()); ?>">
            <input type="hidden" name="action" value="update_open_registration">
            <div class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" id="open_registration" name="open_registration" value="1" <?php echo $openRegistrationEnabled ? 'checked' : ''; ?>>
                <label class="form-check-label" for="open_registration">Enable Open Self-Registration</label>
            </div>
            <button type="submit" class="btn btn-primary">Save Registration Setting</button>
        </form>

        <?php if ($openRegistrationEnabled): ?>
            <div class="alert alert-success mb-3">Open registration is enabled.</div>
            <p class="mb-1"><strong>Registration URL:</strong></p>
            <p><a href="<?php echo sanitize($registerUrl); ?>" target="_blank" rel="noopener"><?php echo sanitize($registerUrl); ?></a></p>
            <img src="<?php echo sanitize($qrUrl); ?>" alt="QR code for coach registration URL" width="200" height="200">
        <?php else: ?>
            <div class="alert alert-warning mb-0">Registration is currently closed.</div>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">Coach Access</h5>
    </div>
    <div class="card-body">
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle"></i>
            <strong>Shared coach password login has been retired.</strong>
            Coaches must use individual accounts.
        </div>
    </div>
</div>
