<?php
/**
 * General Settings Section
 */
?>

<div class="card">
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="action" value="update_general">
            <input type="hidden" name="csrf_token" value="<?php echo Auth::generateCSRFToken(); ?>">
            
            <div class="mb-3">
                <label class="form-label">League Name</label>
                <input type="text" name="league_name" class="form-control" 
                       value="<?php echo sanitize($leagueName); ?>" required>
                <div class="form-text">
                    This name will be displayed throughout the application.
                </div>
            </div>
            
            <div class="mb-3">
                <label class="form-label">Contact Email</label>
                <input type="email" name="contact_email" class="form-control" 
                       value="<?php echo sanitize($contactEmail); ?>">
                <div class="form-text">
                    Primary contact email for league inquiries.
                </div>
            </div>
            
            <div class="mb-3">
                <label class="form-label">Weather Hotline</label>
                <input type="text" name="weather_hotline" class="form-control" 
                       value="<?php echo sanitize($weatherHotline); ?>">
                <div class="form-text">
                    Phone number for weather-related game status updates.
                </div>
            </div>
            
            <div class="mb-3">
                <label class="form-label">Field Maintenance Phone</label>
                <input type="text" name="field_maintenance_phone" class="form-control" 
                       value="<?php echo sanitize($fieldMaintenancePhone); ?>">
                <div class="form-text">
                    Contact number for field maintenance issues.
                </div>
            </div>
            
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> Save Changes
            </button>
        </form>
    </div>
</div>
