<?php
/**
 * Admin Filter Component
 * Provides advanced filtering for admin pages with program, season, and division filters
 */

// Initialize filter helpers if not already done
if (!isset($filterHelpers)) {
    FilterHelpers::init();
}

// Get filter values
$filters = FilterHelpers::getFilterValues();

// Add show_inactive filter
$showInactive = filter_input(INPUT_GET, 'show_inactive', FILTER_VALIDATE_BOOLEAN) ?: false;

// Get filter options
$programs = FilterHelpers::getActivePrograms();
$seasons = FilterHelpers::getSeasons($filters['program_id']);
$divisions = FilterHelpers::getDivisions($filters['season_id']);

// Build the current URL without filter parameters
$baseUrl = strtok($_SERVER["REQUEST_URI"], '?');
?>

<!-- Advanced Filters -->
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="fas fa-filter"></i> Filters</h5>
        <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#filterCollapse">
            <i class="fas fa-chevron-down"></i>
        </button>
    </div>
    <div class="collapse show" id="filterCollapse">
        <div class="card-body">
            <form id="filterForm" method="get" class="row g-3">
                <!-- Program Filter -->
                <div class="col-md-3">
                    <label for="program" class="form-label">Program</label>
                    <select name="program" id="program" class="form-select">
                        <option value="">All Programs</option>
                        <?php foreach ($programs as $program): ?>
                            <option value="<?php echo $program['program_id']; ?>"
                                    <?php echo $filters['program_id'] == $program['program_id'] ? 'selected' : ''; ?>>
                                <?php echo sanitize($program['program_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Season Filter -->
                <div class="col-md-3">
                    <label for="season" class="form-label">Season</label>
                    <select name="season" id="season" class="form-select" <?php echo empty($filters['program_id']) ? 'disabled' : ''; ?>>
                        <option value="">All Seasons</option>
                        <?php foreach ($seasons as $season): ?>
                            <option value="<?php echo $season['season_id']; ?>"
                                    <?php echo $filters['season_id'] == $season['season_id'] ? 'selected' : ''; ?>>
                                <?php echo sanitize($season['season_name'] . ' ' . $season['season_year']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Division Filter -->
                <div class="col-md-3">
                    <label for="division" class="form-label">Division</label>
                    <select name="division" id="division" class="form-select" <?php echo empty($filters['season_id']) ? 'disabled' : ''; ?>>
                        <option value="">All Divisions</option>
                        <?php foreach ($divisions as $division): ?>
                            <option value="<?php echo $division['division_id']; ?>"
                                    <?php echo $filters['division_id'] == $division['division_id'] ? 'selected' : ''; ?>>
                                <?php echo sanitize($division['division_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Show Inactive Toggle -->
                <div class="col-md-3">
                    <label class="form-label d-block">Additional Options</label>
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="showInactive" name="show_inactive" value="1" 
                               <?php echo $showInactive ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="showInactive">
                            Show Inactive Seasons
                        </label>
                    </div>
                </div>

                <!-- Filter Actions -->
                <div class="col-12">
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter"></i> Apply Filters
                        </button>
                        <a href="<?php echo $baseUrl; ?>" class="btn btn-outline-secondary">
                            <i class="fas fa-times"></i> Clear Filters
                        </a>
                        <?php if ($showInactive): ?>
                            <a href="<?php echo FilterHelpers::buildFilterUrl($baseUrl, array_merge($filters, ['show_inactive' => false])); ?>" 
                               class="btn btn-outline-warning">
                                <i class="fas fa-eye-slash"></i> Hide Inactive
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Filter change handlers
    const programSelect = document.getElementById('program');
    const seasonSelect = document.getElementById('season');
    const divisionSelect = document.getElementById('division');
    
    programSelect.addEventListener('change', function() {
        const programId = this.value;
        updateSeasonDropdown(programId);
    });
    
    seasonSelect.addEventListener('change', function() {
        const seasonId = this.value;
        updateDivisionDropdown(seasonId);
    });
    
    // Function to update season dropdown based on program selection
    function updateSeasonDropdown(programId) {
        const seasonSelect = document.getElementById('season');
        seasonSelect.disabled = !programId;
        seasonSelect.value = '';
        
        // Also reset and disable division dropdown
        const divisionSelect = document.getElementById('division');
        divisionSelect.disabled = true;
        divisionSelect.value = '';
        
        if (programId) {
            // Submit form to update available seasons
            document.getElementById('filterForm').submit();
        }
    }
    
    // Function to update division dropdown based on season selection
    function updateDivisionDropdown(seasonId) {
        const divisionSelect = document.getElementById('division');
        divisionSelect.disabled = !seasonId;
        divisionSelect.value = '';
        
        if (seasonId) {
            // Submit form to update available divisions
            document.getElementById('filterForm').submit();
        }
    }
});
</script>
