<?php
/**
 * Coach Access Section
 */
?>

<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">Coach Access Password</h5>
    </div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="action" value="update_coach_password">
            <input type="hidden" name="csrf_token" value="<?php echo Auth::generateCSRFToken(); ?>">
            
            <div class="mb-3">
                <label class="form-label">New Password</label>
                <input type="password" name="coach_password" class="form-control" required>
                <div class="form-text">
                    This password will be used by all coaches to access the system.
                </div>
            </div>
            
            <div class="mb-3">
                <label class="form-label">Confirm Password</label>
                <input type="password" name="confirm_coach_password" class="form-control" required>
            </div>
            
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-key"></i> Update Coach Password
            </button>
        </form>
    </div>
</div>

<div class="card mt-4">
    <div class="card-header">
        <h5 class="card-title mb-0">Coach Access Information</h5>
    </div>
    <div class="card-body">
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i> Additional coach access management features will be implemented in the next update.
        </div>
    </div>
</div>
