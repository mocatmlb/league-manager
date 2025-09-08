<?php
/**
 * Timezone Settings Section
 */
?>

<div class="row">
    <div class="col-lg-8">
        <div class="card mb-4">
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="update_timezone">
                    <input type="hidden" name="csrf_token" value="<?php echo Auth::generateCSRFToken(); ?>">
                    
                    <div class="mb-3">
                        <label class="form-label">Application Timezone</label>
                        <select name="timezone" class="form-select" required>
                            <?php foreach ($availableTimezones as $tz => $label): ?>
                                <option value="<?php echo $tz; ?>" <?php echo $tz === $currentTimezone ? 'selected' : ''; ?>>
                                    <?php echo sanitize($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">
                            This timezone will be used for displaying all dates and times in the application.
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Current Time Preview</label>
                        <div class="form-control-plaintext" id="currentTimePreview">
                            <?php echo formatDateTime(date('Y-m-d H:i:s')); ?>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update Timezone
                    </button>
                </form>
            </div>
        </div>

        <!-- Timezone Information -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Timezone Information</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6>Server Information</h6>
                        <ul class="list-unstyled">
                            <li><strong>PHP Default Timezone:</strong> <?php echo date_default_timezone_get(); ?></li>
                            <li><strong>Server Time:</strong> <?php echo date('Y-m-d H:i:s'); ?></li>
                            <li><strong>Application Time:</strong> <span id="appTime"></span></li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <h6>Client Information</h6>
                        <ul class="list-unstyled" id="clientInfo">
                            <li><strong>Browser Timezone:</strong> <span id="browserTimezone"></span></li>
                            <li><strong>Local Time:</strong> <span id="localTime"></span></li>
                            <li><strong>UTC Offset:</strong> <span id="utcOffset"></span></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <!-- Timezone Help -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">About Timezones</h5>
            </div>
            <div class="card-body">
                <h6>Important Notes</h6>
                <ul class="mb-4">
                    <li>All game times are stored in the application timezone</li>
                    <li>Times are converted to local time for display when possible</li>
                    <li>Changing the timezone will affect how all dates and times are displayed</li>
                    <li>Consider your users' locations when selecting a timezone</li>
                </ul>

                <h6>Common Issues</h6>
                <div class="accordion" id="timezoneHelp">
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#tzHelp1">
                                Times Display Incorrectly
                            </button>
                        </h2>
                        <div id="tzHelp1" class="accordion-collapse collapse" data-bs-parent="#timezoneHelp">
                            <div class="accordion-body">
                                If times are displaying incorrectly:
                                <ol>
                                    <li>Verify the application timezone setting</li>
                                    <li>Check that game times were entered correctly</li>
                                    <li>Ensure user browsers have correct local time</li>
                                </ol>
                            </div>
                        </div>
                    </div>
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#tzHelp2">
                                Daylight Saving Time
                            </button>
                        </h2>
                        <div id="tzHelp2" class="accordion-collapse collapse" data-bs-parent="#timezoneHelp">
                            <div class="accordion-body">
                                The application automatically handles daylight saving time transitions. No special action is needed for DST changes.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Update time displays
function updateTimes() {
    const now = new Date();
    
    // Update current time preview
    document.getElementById('currentTimePreview').textContent = formatDateTimeTZ(now.toISOString());
    
    // Update app time
    document.getElementById('appTime').textContent = formatDateTimeTZ(now.toISOString());
    
    // Update client info
    document.getElementById('browserTimezone').textContent = Intl.DateTimeFormat().resolvedOptions().timeZone;
    document.getElementById('localTime').textContent = now.toLocaleString();
    document.getElementById('utcOffset').textContent = now.getTimezoneOffset() / -60;
}

// Update times every second
setInterval(updateTimes, 1000);
updateTimes(); // Initial update
</script>
