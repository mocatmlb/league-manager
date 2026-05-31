<?php
/**
 * Schedule Changes Settings Section
 */
?>

<div class="card">
    <div class="card-header">
        <h5 class="mb-0">Schedule Change Request Windows</h5>
    </div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="action" value="update_schedule_changes">
            <input type="hidden" name="csrf_token" value="<?php echo Auth::generateCSRFToken(); ?>">

            <div class="mb-3">
                <label class="form-label">Pre-game blackout (hours before game)</label>
                <input type="number" name="reschedule_pre_game_hours" class="form-control"
                       min="0" style="max-width: 160px;"
                       value="<?php echo (int) $reschedulePreGameHours; ?>">
                <div class="form-text">
                    Coaches cannot submit a request within this many hours of the game's scheduled start.
                    Set to <strong>0</strong> to disable.
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Post-game blackout (hours after game)</label>
                <input type="number" name="reschedule_post_game_hours" class="form-control"
                       min="0" style="max-width: 160px;"
                       value="<?php echo (int) $reschedulePostGameHours; ?>">
                <div class="form-text">
                    Coaches can still submit a request up to this many hours after the game's scheduled start.
                    Set to <strong>0</strong> to disable (no post-game cutoff).
                </div>
            </div>

            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> Save Changes
            </button>
        </form>
    </div>
</div>
