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
                <label class="form-label">League Tagline</label>
                <input type="text" name="league_tagline" class="form-control"
                       value="<?php echo sanitize($leagueTagline); ?>"
                       placeholder="Your source for schedules, standings, and league information.">
                <div class="form-text">
                    Displayed below the league name on the public home page.
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

<!-- Weather Locations -->
<?php
$_wLocJson  = getSetting('weather_locations', '');
$_wLocSaved = (!empty($_wLocJson) && is_array(json_decode($_wLocJson, true)))
    ? json_decode($_wLocJson, true)
    : [['name' => 'Syracuse, NY', 'lat' => '43.0481', 'lon' => '-76.1474']];
while (count($_wLocSaved) < 4) $_wLocSaved[] = ['name' => '', 'lat' => '', 'lon' => ''];
?>
<div class="card mt-3">
    <div class="card-header d-flex align-items-center justify-content-between">
        <span><i class="fas fa-cloud-sun me-1 text-info"></i> Weather Widget Locations</span>
        <small class="text-muted">Up to 4 locations — shown as tabs on the home page</small>
    </div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="action" value="update_weather_locations">
            <input type="hidden" name="csrf_token" value="<?php echo Auth::generateCSRFToken(); ?>">
            <div class="row g-2 mb-1" style="font-size:0.8rem;color:#888">
                <div class="col-4">Location Name</div>
                <div class="col-3">Latitude</div>
                <div class="col-3">Longitude</div>
            </div>
            <?php for ($i = 0; $i < 4; $i++):
                $wl = $_wLocSaved[$i];
            ?>
            <div class="row g-2 mb-2 align-items-center">
                <div class="col-4">
                    <input type="text" name="loc_name[]" class="form-control form-control-sm"
                           placeholder="e.g. Utica, NY"
                           value="<?php echo htmlspecialchars($wl['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="col-3">
                    <input type="text" name="loc_lat[]" class="form-control form-control-sm"
                           placeholder="43.1009"
                           value="<?php echo htmlspecialchars($wl['lat'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="col-3">
                    <input type="text" name="loc_lon[]" class="form-control form-control-sm"
                           placeholder="-75.2327"
                           value="<?php echo htmlspecialchars($wl['lon'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="col-2 text-muted" style="font-size:0.75rem">Slot <?php echo $i+1; ?></div>
            </div>
            <?php endfor; ?>
            <div class="form-text mb-3">
                Find lat/lon at <a href="https://www.latlong.net/" target="_blank" rel="noopener">latlong.net</a>.
                Leave Name blank to hide a slot.
            </div>
            <button type="submit" class="btn btn-sm btn-primary">
                <i class="fas fa-save"></i> Save Locations
            </button>
        </form>
    </div>
</div>
