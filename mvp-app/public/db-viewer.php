<?php
/**
 * Simple Database Viewer - FOR DEVELOPMENT ONLY
 * DO NOT USE IN PRODUCTION
 */

require_once '../includes/bootstrap.php';

// Simple authentication - CHANGE THIS PASSWORD
$admin_password = 'dbview123';
$authenticated = false;

if (isset($_POST['password']) && $_POST['password'] === $admin_password) {
    $_SESSION['db_authenticated'] = true;
}

if (isset($_SESSION['db_authenticated']) && $_SESSION['db_authenticated']) {
    $authenticated = true;
}

if (isset($_GET['logout'])) {
    unset($_SESSION['db_authenticated']);
    header('Location: db-viewer.php');
    exit;
}

$db = Database::getInstance();
$selectedTable = $_GET['table'] ?? '';
$action = $_GET['action'] ?? 'view';

// Validate table name against allowed tables
$allowedTables = $db->fetchAll("SHOW TABLES");
$tableNames = array_column($allowedTables, 'Tables_in_d8tl_mvp');

if (!in_array($selectedTable, $tableNames)) {
    $selectedTable = '';
    $error = 'Invalid table selected.';
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Viewer - D8TL MVP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .table-container { max-height: 600px; overflow-y: auto; }
        .sql-query { background: #f8f9fa; padding: 10px; border-radius: 5px; font-family: monospace; }
        .warning { background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="container-fluid mt-3">
        <?php if (!$authenticated): ?>
            <div class="row justify-content-center">
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <h5>Database Viewer Access</h5>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-warning">
                                <strong>Development Tool Only!</strong><br>
                                Password: <code>dbview123</code>
                            </div>
                            <form method="POST">
                                <div class="mb-3">
                                    <label class="form-label">Password:</label>
                                    <input type="password" name="password" class="form-control" required>
                                </div>
                                <button type="submit" class="btn btn-primary">Access Database</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="warning">
                <strong>⚠️ Development Tool Warning:</strong> This is a development-only database viewer. 
                Never use this in production! <a href="?logout=1" class="btn btn-sm btn-outline-danger">Logout</a>
            </div>

            <div class="row">
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-header">
                            <h6>Tables (<?php echo count($tableNames); ?>)</h6>
                        </div>
                        <div class="card-body p-0">
                            <div class="list-group list-group-flush">
                                <?php foreach ($tableNames as $table): ?>
                                    <a href="?table=<?php echo $table; ?>" 
                                       class="list-group-item list-group-item-action <?php echo $selectedTable === $table ? 'active' : ''; ?>">
                                        <?php echo $table; ?>
                                        <?php
                                        $count = $db->fetchOne("SELECT COUNT(*) as count FROM `$table`");
                                        echo "<small class='text-muted'>({$count['count']} rows)</small>";
                                        ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-9">
                    <?php if ($selectedTable): ?>
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5>Table: <?php echo $selectedTable; ?></h5>
                                <div>
                                    <a href="?table=<?php echo $selectedTable; ?>&action=structure" 
                                       class="btn btn-sm btn-outline-info <?php echo $action === 'structure' ? 'active' : ''; ?>">Structure</a>
                                    <a href="?table=<?php echo $selectedTable; ?>&action=view" 
                                       class="btn btn-sm btn-outline-primary <?php echo $action === 'view' ? 'active' : ''; ?>">Data</a>
                                </div>
                            </div>
                            <div class="card-body">
                                <?php if ($action === 'structure'): ?>
                                    <h6>Table Structure</h6>
                                    <div class="sql-query mb-3">DESCRIBE `<?php echo htmlspecialchars($selectedTable); ?>`;</div>
                                    <?php
                                    // Use prepared statement with table name validation
                                    if (in_array($selectedTable, $tableNames)) {
                                        $structure = $db->fetchAll("DESCRIBE `" . $selectedTable . "`");
                                    } else {
                                        $structure = [];
                                    }
                                    ?>
                                    <div class="table-responsive">
                                        <table class="table table-sm table-striped">
                                            <thead>
                                                <tr>
                                                    <th>Field</th>
                                                    <th>Type</th>
                                                    <th>Null</th>
                                                    <th>Key</th>
                                                    <th>Default</th>
                                                    <th>Extra</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($structure as $field): ?>
                                                    <tr>
                                                        <td><strong><?php echo $field['Field']; ?></strong></td>
                                                        <td><?php echo $field['Type']; ?></td>
                                                        <td><?php echo $field['Null']; ?></td>
                                                        <td><?php echo $field['Key']; ?></td>
                                                        <td><?php echo $field['Default'] ?? '<em>NULL</em>'; ?></td>
                                                        <td><?php echo $field['Extra']; ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <h6>Table Data</h6>
                                    <div class="sql-query mb-3">SELECT * FROM `<?php echo htmlspecialchars($selectedTable); ?>` LIMIT 100;</div>
                                    <?php
                                    // Use prepared statement with table name validation
                                    if (in_array($selectedTable, $tableNames)) {
                                        $data = $db->fetchAll("SELECT * FROM `" . $selectedTable . "` LIMIT 100");
                                    } else {
                                        $data = [];
                                    }
                                    ?>
                                    <?php if (empty($data)):
                                    ?>
                                        <div class="alert alert-info">No data found in this table.</div>
                                    <?php else: ?>
                                        <div class="table-container">
                                            <table class="table table-sm table-striped table-hover">
                                                <thead class="table-dark sticky-top">
                                                    <tr>
                                                        <?php foreach (array_keys($data[0]) as $column): ?>
                                                            <th><?php echo $column; ?></th>
                                                        <?php endforeach; ?>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($data as $row): ?>
                                                        <tr>
                                                            <?php foreach ($row as $value): ?>
                                                                <td>
                                                                    <?php 
                                                                    if ($value === null) {
                                                                        echo '<em class="text-muted">NULL</em>';
                                                                    } elseif (strlen($value) > 50) {
                                                                        echo substr(htmlspecialchars($value), 0, 50) . '...';
                                                                    } else {
                                                                        echo htmlspecialchars($value);
                                                                    }
                                                                    ?>
                                                                </td>
                                                            <?php endforeach; ?>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                        <?php if (count($data) === 100): ?>
                                            <div class="alert alert-info mt-3">
                                                <small>Showing first 100 rows only. Use MySQL command line for full data access.</small>
                                            </div>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="card">
                            <div class="card-body text-center">
                                <h5>Welcome to Database Viewer</h5>
                                <p>Select a table from the left panel to view its structure and data.</p>
                                <div class="row mt-4">
                                    <div class="col-md-4">
                                        <div class="card bg-light">
                                            <div class="card-body">
                                                <h6>Core Tables</h6>
                                                <small>teams, games, schedules</small>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="card bg-light">
                                            <div class="card-body">
                                                <h6>Configuration</h6>
                                                <small>programs, seasons, divisions</small>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="card bg-light">
                                            <div class="card-body">
                                                <h6>System</h6>
                                                <small>admin_users, settings</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
