<?php
/**
 * Migration Cutover Panel Section
 *
 * Story 9.2: Admin panel for pre-cutover gap checklist and shared credential disable.
 */

require_once EnvLoader::getPath('includes/CutoverService.php');

$cutoverService       = new CutoverService();
$checklist            = $cutoverService->getGapChecklist();
$gapCount             = $cutoverService->getGapCount();
$credentialActive     = $cutoverService->isSharedCredentialActive();

$totalTeams    = count($checklist);
$teamsCovered  = $totalTeams - $gapCount;
$totalOwners   = 0;
foreach ($checklist as $row) {
    $totalOwners += count($row['owners']);
}

// Flash messages produced by the POST handler in settings/index.php
$flashSuccess = $_SESSION['cutover_flash_success'] ?? '';
$flashError   = $_SESSION['cutover_flash_error'] ?? '';
unset($_SESSION['cutover_flash_success'], $_SESSION['cutover_flash_error']);
?>

<?php if ($flashSuccess): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle"></i> <?php echo sanitize($flashSuccess); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($flashError): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-triangle"></i> <?php echo sanitize($flashError); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (!$credentialActive): ?>
<!-- ===================== CUTOVER COMPLETE STATE ===================== -->
<div class="card border-success mb-4">
    <div class="card-body text-center py-5">
        <div class="mb-3">
            <i class="fas fa-check-circle text-success" style="font-size: 3rem;"></i>
        </div>
        <h3 class="text-success">Cutover Complete</h3>
        <p class="text-muted mb-0">
            The shared coach credential has been disabled. All coach access is now through individual accounts.
        </p>
    </div>
</div>

<?php else: ?>
<!-- ===================== PRE-CUTOVER STATE ===================== -->

<!-- Summary stat cards -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card text-center border-success">
            <div class="card-body">
                <div class="h2 mb-0 text-success">
                    <i class="fas fa-check-circle"></i> <?php echo (int) $teamsCovered; ?>
                </div>
                <div class="card-title text-muted small mt-1">Teams Covered</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card text-center <?php echo $gapCount > 0 ? 'border-danger' : 'border-success'; ?>">
            <div class="card-body">
                <div class="h2 mb-0 <?php echo $gapCount > 0 ? 'text-danger' : 'text-success'; ?>">
                    <i class="fas <?php echo $gapCount > 0 ? 'fa-exclamation-triangle' : 'fa-check-circle'; ?>"></i>
                    <?php echo (int) $gapCount; ?>
                </div>
                <div class="card-title text-muted small mt-1">Teams with Gaps</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card text-center border-primary">
            <div class="card-body">
                <div class="h2 mb-0 text-primary"><?php echo (int) $totalOwners; ?></div>
                <div class="card-title text-muted small mt-1">Active Team Owners</div>
            </div>
        </div>
    </div>
</div>

<!-- Warning banner -->
<?php if ($gapCount > 0): ?>
    <div class="alert alert-warning" role="alert">
        <i class="fas fa-exclamation-triangle"></i>
        <strong><?php echo (int) $gapCount; ?> active-season team(s) have no assigned Team Owner.</strong>
        Resolve all gaps before disabling the shared credential.
    </div>
<?php endif; ?>

<!-- Gap checklist table -->
<?php if ($totalTeams > 0): ?>
<div class="card mb-4">
    <div class="card-header">
        <h5 class="card-title mb-0">
            <i class="fas fa-clipboard-check"></i> Pre-Cutover Gap Checklist
        </h5>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Team</th>
                    <th>Division</th>
                    <th>Program</th>
                    <th>Assigned Owner(s)</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($checklist as $row): ?>
                <tr class="<?php echo $row['has_gap'] ? 'gap-row-missing' : 'gap-row-covered'; ?>">
                    <td><?php echo sanitize($row['team_name']); ?></td>
                    <td><?php echo sanitize($row['division_name'] ?: '—'); ?></td>
                    <td><?php echo sanitize($row['program_name'] ?: '—'); ?></td>
                    <td>
                        <?php if (!empty($row['owners'])): ?>
                            <?php foreach ($row['owners'] as $owner): ?>
                                <div>
                                    <?php echo sanitize($owner['first_name'] . ' ' . $owner['last_name']); ?>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($row['has_gap']): ?>
                            <span class="gap-row-missing">&#10007; No Owner</span>
                        <?php else: ?>
                            <span class="gap-row-covered">&#10003; Covered</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($row['has_gap']): ?>
                            <a href="../users/" class="btn btn-sm btn-outline-primary">
                                Assign Coach &rarr;
                            </a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php else: ?>
<div class="alert alert-info">
    <i class="fas fa-info-circle"></i> No active-season teams found.
</div>
<?php endif; ?>

<!-- Disable Shared Login button / panel -->
<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">
            <i class="fas fa-lock"></i> Disable Shared Login
        </h5>
    </div>
    <div class="card-body">
        <?php if ($gapCount > 0): ?>
            <p class="text-muted mb-3">
                All teams must have at least one assigned Team Owner before you can disable the shared login.
            </p>
            <button type="button" class="btn btn-danger" disabled>
                <i class="fas fa-lock"></i> Disable Shared Login
            </button>
        <?php else: ?>
            <p class="text-muted mb-3">
                All active-season teams have an assigned Team Owner. You may now disable the shared credential.
            </p>
            <button type="button" class="btn btn-danger"
                    data-bs-toggle="modal" data-bs-target="#cutoverConfirmModal">
                <i class="fas fa-lock"></i> Disable Shared Login
            </button>
        <?php endif; ?>
    </div>
</div>

<?php endif; /* credentialActive */ ?>

<!-- ===================== CONFIRMATION MODAL (UX-DR11) ===================== -->
<div class="modal fade" id="cutoverConfirmModal" tabindex="-1"
     data-bs-backdrop="static" aria-labelledby="cutoverConfirmModalLabel" aria-modal="true" role="dialog">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="cutoverConfirmModalLabel">
                    <i class="fas fa-exclamation-triangle text-danger"></i> Confirm Cutover
                </h5>
                <!-- No × close button per UX-DR11 -->
            </div>
            <div class="modal-body">
                This will permanently disable the shared coach password. All coaches must use their individual
                accounts. This cannot be automatically undone.
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form method="POST" action="?section=cutover">
                    <input type="hidden" name="csrf_token" value="<?php echo Auth::generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="disable_shared_credential">
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-lock"></i> Confirm
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
