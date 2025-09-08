<?php
/**
 * District 8 Travel League - Coaches Contact Directory
 */

require_once '../../includes/bootstrap.php';

// Require coach authentication
Auth::requireCoach();

$db = Database::getInstance();

// Get filter values
$programId = isset($_GET['program_id']) ? (int)$_GET['program_id'] : null;
$seasonId = isset($_GET['season_id']) ? (int)$_GET['season_id'] : null;
$divisionId = isset($_GET['division_id']) ? (int)$_GET['division_id'] : null;

try {
    // Build the SQL query with filters
    $sql = "
        SELECT t.team_name, t.league_name, t.manager_first_name, t.manager_last_name, 
               t.manager_phone, t.manager_email, d.division_name, p.program_name,
               s.season_name, s.season_year
        FROM teams t
        LEFT JOIN divisions d ON t.division_id = d.division_id
        LEFT JOIN seasons s ON d.season_id = s.season_id
        LEFT JOIN programs p ON s.program_id = p.program_id
        WHERE t.active_status = 'Active'
        AND s.season_status IN ('Active', 'Registration')
    ";

    $params = [];
    
    if ($programId) {
        $sql .= " AND p.program_id = ?";
        $params[] = $programId;
    }
    
    if ($seasonId) {
        $sql .= " AND s.season_id = ?";
        $params[] = $seasonId;
    }
    
    if ($divisionId) {
        $sql .= " AND d.division_id = ?";
        $params[] = $divisionId;
    }
    
    $sql .= " ORDER BY p.program_name, s.season_year DESC, s.season_name, d.division_name, t.league_name, t.team_name";
    
    $teamContacts = $db->fetchAll($sql, $params);
    Logger::debug("Coaches contacts page loaded", ['team_contacts_count' => count($teamContacts)]);
} catch (Exception $e) {
    Logger::error("Failed to load team contacts", ['error' => $e->getMessage()]);
    $teamContacts = [];
}

// Get filter options
try {
    $programs = $db->fetchAll("
        SELECT DISTINCT p.program_id, p.program_name
        FROM programs p
        JOIN seasons s ON p.program_id = s.program_id
        WHERE s.season_status IN ('Active', 'Registration')
        ORDER BY p.program_name
    ");

    $seasons = $db->fetchAll("
        SELECT s.season_id, s.season_name, s.season_year, p.program_name
        FROM seasons s
        JOIN programs p ON s.program_id = p.program_id
        WHERE s.season_status IN ('Active', 'Registration')
        ORDER BY s.season_year DESC, s.season_name
    ");

    $divisions = $db->fetchAll("
        SELECT d.division_id, d.division_name, s.season_name, p.program_name
        FROM divisions d
        JOIN seasons s ON d.season_id = s.season_id
        JOIN programs p ON s.program_id = p.program_id
        WHERE s.season_status IN ('Active', 'Registration')
        ORDER BY p.program_name, s.season_name, d.division_name
    ");
} catch (Exception $e) {
    Logger::error("Failed to load filter options", ['error' => $e->getMessage()]);
    $programs = [];
    $seasons = [];
    $divisions = [];
}

// Get league officials that should be displayed
try {
    $leagueOfficials = $db->fetchAll("
        SELECT role, name, phone, email, sort_order
        FROM league_officials 
        WHERE display_on_contact_page = 1 AND active_status = 'Active'
        ORDER BY sort_order, name
    ");
} catch (Exception $e) {
    Logger::error("Failed to load league officials", ['error' => $e->getMessage()]);
    $leagueOfficials = [];
}

// Group team contacts by program, season, and division
$contactsByGroup = [];
foreach ($teamContacts as $team) {
    $program = $team['program_name'];
    $season = $team['season_name'] . ' ' . $team['season_year'];
    $division = $team['division_name'] ?? 'Unassigned';
    
    if (!isset($contactsByGroup[$program])) {
        $contactsByGroup[$program] = [];
    }
    if (!isset($contactsByGroup[$program][$season])) {
        $contactsByGroup[$program][$season] = [];
    }
    if (!isset($contactsByGroup[$program][$season][$division])) {
        $contactsByGroup[$program][$season][$division] = [];
    }
    
    $contactsByGroup[$program][$season][$division][] = $team;
}

$pageTitle = "Contact Directory - " . APP_NAME;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/css/style.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="../index.php"><?php echo APP_NAME; ?></a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="../index.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../schedule.php">Schedule</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../standings.php">Standings</a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link active" href="dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">Logout</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container mt-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h1>Contact Directory</h1>
                        <p class="text-muted mb-0">Access league contact information and team directories</p>
                    </div>
                </div>

                <!-- Filter Card -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="get" class="row g-3">
                            <!-- Program Filter -->
                            <div class="col-md-4">
                                <label class="form-label">Program</label>
                                <select name="program_id" class="form-select" onchange="this.form.submit()">
                                    <option value="">All Programs</option>
                                    <?php foreach ($programs as $program): ?>
                                        <option value="<?php echo $program['program_id']; ?>" 
                                                <?php echo $programId == $program['program_id'] ? 'selected' : ''; ?>>
                                            <?php echo sanitize($program['program_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Season Filter -->
                            <div class="col-md-4">
                                <label class="form-label">Season</label>
                                <select name="season_id" class="form-select" onchange="this.form.submit()">
                                    <option value="">All Seasons</option>
                                    <?php foreach ($seasons as $season): ?>
                                        <option value="<?php echo $season['season_id']; ?>"
                                                <?php echo $seasonId == $season['season_id'] ? 'selected' : ''; ?>>
                                            <?php echo sanitize($season['season_name'] . ' ' . $season['season_year'] . 
                                                  ' (' . $season['program_name'] . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Division Filter -->
                            <div class="col-md-4">
                                <label class="form-label">Division</label>
                                <select name="division_id" class="form-select" onchange="this.form.submit()">
                                    <option value="">All Divisions</option>
                                    <?php foreach ($divisions as $division): ?>
                                        <option value="<?php echo $division['division_id']; ?>"
                                                <?php echo $divisionId == $division['division_id'] ? 'selected' : ''; ?>>
                                            <?php echo sanitize($division['division_name'] . ' (' . 
                                                  $division['program_name'] . ' - ' . $division['season_name'] . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <?php if ($programId || $seasonId || $divisionId): ?>
                                <div class="col-12">
                                    <a href="contacts.php" class="btn btn-outline-secondary">
                                        <i class="fas fa-times"></i> Clear Filters
                                    </a>
                                </div>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>

                <!-- Information Card -->
                <div class="card mb-4">
                    <div class="card-body">
                        <h5><i class="fas fa-info-circle text-info"></i> Contact Information</h5>
                        <p class="mb-0">
                            This directory contains contact information for team managers and league officials.
                            Use this information for schedule coordination, game-related communications, and league business inquiries.
                        </p>
                    </div>
                </div>

                <!-- Team Contacts by Program, Season, and Division -->
                <?php if (!empty($contactsByGroup)): ?>
                <div class="card mb-4">
                    <div class="card-header bg-success text-white">
                        <h3><i class="fas fa-address-book"></i> Team Manager Directory</h3>
                    </div>
                    <div class="card-body">
                        <?php foreach ($contactsByGroup as $program => $seasons): ?>
                            <div class="mb-5">
                                <h3 class="text-primary mb-4">
                                    <i class="fas fa-project-diagram"></i> <?php echo sanitize($program); ?>
                                </h3>
                                
                                <?php foreach ($seasons as $season => $divisions): ?>
                                    <div class="mb-4">
                                        <h4 class="text-info mb-3">
                                            <i class="fas fa-calendar-alt"></i> <?php echo sanitize($season); ?>
                                        </h4>
                                        
                                        <?php foreach ($divisions as $division => $teams): ?>
                                            <div class="mb-4">
                                                <h5 class="text-success mb-3">
                                                    <i class="fas fa-layer-group"></i> <?php echo sanitize($division); ?>
                                                </h5>
                                                <div class="table-responsive">
                                                    <table class="table table-striped table-hover">
                                                        <thead class="table-success">
                                                            <tr>
                                                                <th>Team</th>
                                                                <th>League</th>
                                                                <th>Manager</th>
                                                                <th>Contact Information</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php foreach ($teams as $team): ?>
                                                            <tr>
                                                                <td>
                                                                    <strong><?php echo sanitize($team['team_name']); ?></strong>
                                                                </td>
                                                                <td><?php echo sanitize($team['league_name']); ?></td>
                                                                <td>
                                                                    <?php echo sanitize($team['manager_first_name'] . ' ' . $team['manager_last_name']); ?>
                                                                </td>
                                                                <td>
                                                                    <?php if ($team['manager_phone']): ?>
                                                                        <div class="mb-1">
                                                                            <i class="fas fa-phone"></i> 
                                                                            <a href="tel:<?php echo sanitize($team['manager_phone']); ?>">
                                                                                <?php echo sanitize($team['manager_phone']); ?>
                                                                            </a>
                                                                        </div>
                                                                    <?php endif; ?>
                                                                    <?php if ($team['manager_email']): ?>
                                                                        <div class="mb-0">
                                                                            <i class="fas fa-envelope"></i> 
                                                                            <a href="mailto:<?php echo sanitize($team['manager_email']); ?>">
                                                                                <?php echo sanitize($team['manager_email']); ?>
                                                                            </a>
                                                                        </div>
                                                                    <?php else: ?>
                                                                        <span class="text-muted">Not provided</span>
                                                                    <?php endif; ?>
                                                                </td>
                                                            </tr>
                                                            <?php endforeach; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php else: ?>
                <div class="card mb-4">
                    <div class="card-body text-center">
                        <i class="fas fa-users fa-3x text-muted mb-3"></i>
                        <h4>No Team Contacts Available</h4>
                        <p class="text-muted">There are currently no active teams with contact information.</p>
                        <?php if ($programId || $seasonId || $divisionId): ?>
                            <p class="text-muted">Try adjusting or clearing your filters.</p>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- League Officials -->
                <?php if (!empty($leagueOfficials)): ?>
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h3><i class="fas fa-users-cog"></i> League Administration Officials</h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php foreach ($leagueOfficials as $official): ?>
                            <div class="col-md-6 mb-3">
                                <div class="d-flex align-items-start">
                                    <div class="flex-shrink-0 me-3">
                                        <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" 
                                             style="width: 50px; height: 50px;">
                                            <i class="fas fa-user-tie"></i>
                                        </div>
                                    </div>
                                    <div class="flex-grow-1">
                                        <h5 class="mb-1"><?php echo sanitize($official['name']); ?></h5>
                                        <p class="text-muted mb-1"><strong><?php echo sanitize($official['role']); ?></strong></p>
                                        <?php if ($official['phone']): ?>
                                            <p class="mb-1">
                                                <i class="fas fa-phone text-primary"></i> 
                                                <a href="tel:<?php echo sanitize($official['phone']); ?>">
                                                    <?php echo sanitize($official['phone']); ?>
                                                </a>
                                            </p>
                                        <?php endif; ?>
                                        <?php if ($official['email']): ?>
                                            <p class="mb-0">
                                                <i class="fas fa-envelope text-primary"></i> 
                                                <a href="mailto:<?php echo sanitize($official['email']); ?>">
                                                    <?php echo sanitize($official['email']); ?>
                                                </a>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Contact Usage Guidelines -->
                <div class="card">
                    <div class="card-header">
                        <h3>Contact Usage Guidelines</h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h5 class="text-success">Appropriate Use</h5>
                                <ul>
                                    <li>Schedule coordination</li>
                                    <li>Game-related communications</li>
                                    <li>League business inquiries</li>
                                    <li>Sportsmanship discussions</li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <h5 class="text-danger">Inappropriate Use</h5>
                                <ul>
                                    <li>Personal or commercial solicitation</li>
                                    <li>Harassment or threatening messages</li>
                                    <li>Sharing contact information with non-league members</li>
                                    <li>Excessive or unnecessary communications</li>
                                    <li>Disputes that should go through league officials</li>
                                </ul>
                            </div>
                        </div>
                        
                        <div class="alert alert-info mt-3">
                            <i class="fas fa-info-circle"></i>
                            <strong>Note:</strong> If you need to update your team's contact information, 
                            please contact the league administrator or use the admin console if you have access.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
