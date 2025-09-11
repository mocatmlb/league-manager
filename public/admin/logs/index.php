<?php
require_once dirname(dirname(dirname(__DIR__))) . '/includes/bootstrap.php';

// Check admin authentication
if (!Auth::isAdmin()) {
    header('Location: ../login.php');
    exit;
}

// Handle AJAX requests
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    switch ($_GET['action']) {
        case 'get_log_content':
            $filename = $_GET['filename'] ?? '';
            $lines = (int)($_GET['lines'] ?? 1000);
            $level = $_GET['level'] ?? null;
            
            $content = Logger::readLogFile($filename, $lines, $level);
            echo json_encode(['success' => true, 'content' => $content]);
            exit;
            
        case 'get_log_stats':
            $stats = Logger::getLogStats();
            echo json_encode(['success' => true, 'stats' => $stats]);
            exit;
            
        case 'cleanup_logs':
            Logger::forceCleanup();
            echo json_encode(['success' => true, 'message' => 'Log cleanup completed']);
            exit;
            
        case 'set_log_level':
            if (isset($_POST['level'])) {
                Logger::setLogLevel($_POST['level']);
                echo json_encode(['success' => true, 'message' => 'Log level updated']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Level not specified']);
            }
            exit;
    }
}

$logFiles = Logger::getLogFiles();
$logStats = Logger::getLogStats();
$logLevels = Logger::getLogLevels();
$currentLevel = Logger::getCurrentLogLevel();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Application Logs - District 8 Travel League</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="../../assets/css/admin.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .log-content {
            background-color: #1e1e1e;
            color: #d4d4d4;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            line-height: 1.4;
            max-height: 600px;
            overflow-y: auto;
            white-space: pre-wrap;
            word-wrap: break-word;
        }
        .log-level-DEBUG { color: #569cd6; }
        .log-level-INFO { color: #4ec9b0; }
        .log-level-WARN { color: #dcdcaa; }
        .log-level-ERROR { color: #f44747; }
        .log-level-FATAL { color: #ff6b6b; font-weight: bold; }
        .log-stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .log-file-card {
            transition: transform 0.2s;
        }
        .log-file-card:hover {
            transform: translateY(-2px);
        }
        .refresh-btn {
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <?php include '../../../includes/nav.php'; ?>

    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1><i class="fas fa-file-alt"></i> Application Logs</h1>
                    <div>
                        <button type="button" class="btn btn-outline-primary" onclick="refreshStats()">
                            <i class="fas fa-sync-alt" id="refreshIcon"></i> Refresh
                        </button>
                        <button type="button" class="btn btn-outline-warning" onclick="cleanupLogs()">
                            <i class="fas fa-trash"></i> Cleanup Old Logs
                        </button>
                    </div>
                </div>

                <!-- Log Statistics -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card log-stats-card">
                            <div class="card-body text-center">
                                <h5 class="card-title">Total Files</h5>
                                <h2 id="totalFiles"><?php echo $logStats['total_files']; ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card log-stats-card">
                            <div class="card-body text-center">
                                <h5 class="card-title">Total Size</h5>
                                <h2 id="totalSize"><?php echo $logStats['readable_size']; ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card log-stats-card">
                            <div class="card-body text-center">
                                <h5 class="card-title">Current Level</h5>
                                <h2 id="currentLevel"><?php echo $currentLevel; ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card log-stats-card">
                            <div class="card-body text-center">
                                <h5 class="card-title">Recent Errors</h5>
                                <h2 id="recentErrors"><?php echo ($logStats['level_counts']['ERROR'] + $logStats['level_counts']['FATAL']); ?></h2>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Log Level Configuration -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-cog"></i> Log Level Configuration</h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label class="form-label">Current Log Level</label>
                                    <select id="logLevelSelect" class="form-select" onchange="updateLogLevel()">
                                        <?php foreach ($logLevels as $level => $name): ?>
                                            <option value="<?php echo $name; ?>" <?php echo $name === $currentLevel ? 'selected' : ''; ?>>
                                                <?php echo $name; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="form-text">Changes take effect immediately. Lower levels include higher priority messages.</div>
                                </div>
                                <div class="alert alert-info">
                                    <strong>Log Levels:</strong><br>
                                    <span class="log-level-DEBUG">DEBUG</span> - Detailed diagnostic information<br>
                                    <span class="log-level-INFO">INFO</span> - General application flow<br>
                                    <span class="log-level-WARN">WARN</span> - Warning conditions<br>
                                    <span class="log-level-ERROR">ERROR</span> - Error conditions<br>
                                    <span class="log-level-FATAL">FATAL</span> - Critical errors
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-chart-bar"></i> Log Level Distribution</h5>
                            </div>
                            <div class="card-body">
                                <?php foreach ($logStats['level_counts'] as $level => $count): ?>
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span class="log-level-<?php echo $level; ?>"><?php echo $level; ?></span>
                                        <span class="badge bg-secondary"><?php echo $count; ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Log Files List -->
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-list"></i> Log Files</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($logFiles)): ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i> No log files found. Logs will be created when the application starts logging.
                            </div>
                        <?php else: ?>
                            <div class="row">
                                <?php foreach ($logFiles as $file): ?>
                                    <div class="col-md-6 col-lg-4 mb-3">
                                        <div class="card log-file-card h-100">
                                            <div class="card-body">
                                                <h6 class="card-title">
                                                    <i class="fas fa-file-alt"></i> <?php echo htmlspecialchars($file['filename']); ?>
                                                </h6>
                                                <p class="card-text">
                                                    <small class="text-muted">
                                                        Size: <?php echo $file['readable_size']; ?><br>
                                                        Modified: <?php echo $file['readable_date']; ?>
                                                    </small>
                                                </p>
                                                <button type="button" class="btn btn-primary btn-sm" 
                                                        onclick="viewLogFile('<?php echo htmlspecialchars($file['filename']); ?>')">
                                                    <i class="fas fa-eye"></i> View
                                                </button>
                                                <button type="button" class="btn btn-outline-secondary btn-sm" 
                                                        onclick="downloadLogFile('<?php echo htmlspecialchars($file['filename']); ?>')">
                                                    <i class="fas fa-download"></i> Download
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Log Viewer Modal -->
    <div class="modal fade" id="logViewerModal" tabindex="-1" aria-labelledby="logViewerModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="logViewerModalLabel">Log Viewer</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label class="form-label">Lines to show</label>
                            <select id="linesSelect" class="form-select" onchange="refreshLogContent()">
                                <option value="100">Last 100 lines</option>
                                <option value="500">Last 500 lines</option>
                                <option value="1000" selected>Last 1000 lines</option>
                                <option value="5000">Last 5000 lines</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Filter by level</label>
                            <select id="levelFilter" class="form-select" onchange="refreshLogContent()">
                                <option value="">All levels</option>
                                <?php foreach ($logLevels as $level => $name): ?>
                                    <option value="<?php echo $name; ?>"><?php echo $name; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">&nbsp;</label>
                            <div>
                                <button type="button" class="btn btn-outline-primary" onclick="refreshLogContent()">
                                    <i class="fas fa-sync-alt"></i> Refresh
                                </button>
                                <button type="button" class="btn btn-outline-secondary" onclick="copyLogContent()">
                                    <i class="fas fa-copy"></i> Copy
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="log-content" id="logContent">
                        Loading...
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    
    <script>
        let currentLogFile = '';
        
        function viewLogFile(filename) {
            currentLogFile = filename;
            document.getElementById('logViewerModalLabel').textContent = 'Log Viewer - ' + filename;
            
            const modal = new bootstrap.Modal(document.getElementById('logViewerModal'));
            modal.show();
            
            refreshLogContent();
        }
        
        function refreshLogContent() {
            if (!currentLogFile) return;
            
            const lines = document.getElementById('linesSelect').value;
            const level = document.getElementById('levelFilter').value;
            
            document.getElementById('logContent').textContent = 'Loading...';
            
            fetch(`?action=get_log_content&filename=${encodeURIComponent(currentLogFile)}&lines=${lines}&level=${encodeURIComponent(level)}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const content = data.content || 'No content found';
                        document.getElementById('logContent').innerHTML = formatLogContent(content);
                    } else {
                        document.getElementById('logContent').textContent = 'Error loading log content';
                    }
                })
                .catch(error => {
                    document.getElementById('logContent').textContent = 'Error: ' + error.message;
                });
        }
        
        function formatLogContent(content) {
            // Add syntax highlighting for log levels
            return content
                .replace(/\[DEBUG\]/g, '<span class="log-level-DEBUG">[DEBUG]</span>')
                .replace(/\[INFO\]/g, '<span class="log-level-INFO">[INFO]</span>')
                .replace(/\[WARN\]/g, '<span class="log-level-WARN">[WARN]</span>')
                .replace(/\[ERROR\]/g, '<span class="log-level-ERROR">[ERROR]</span>')
                .replace(/\[FATAL\]/g, '<span class="log-level-FATAL">[FATAL]</span>');
        }
        
        function copyLogContent() {
            const content = document.getElementById('logContent').textContent;
            navigator.clipboard.writeText(content).then(() => {
                alert('Log content copied to clipboard');
            });
        }
        
        function downloadLogFile(filename) {
            window.open(`../../../logs/${filename}`, '_blank');
        }
        
        function updateLogLevel() {
            const level = document.getElementById('logLevelSelect').value;
            
            fetch('?action=set_log_level', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `level=${encodeURIComponent(level)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('currentLevel').textContent = level;
                    alert('Log level updated successfully');
                } else {
                    alert('Error updating log level: ' + data.message);
                }
            })
            .catch(error => {
                alert('Error: ' + error.message);
            });
        }
        
        function refreshStats() {
            const icon = document.getElementById('refreshIcon');
            icon.classList.add('refresh-btn');
            
            fetch('?action=get_log_stats')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const stats = data.stats;
                        document.getElementById('totalFiles').textContent = stats.total_files;
                        document.getElementById('totalSize').textContent = stats.readable_size;
                        document.getElementById('currentLevel').textContent = stats.current_level;
                        document.getElementById('recentErrors').textContent = 
                            (stats.level_counts.ERROR + stats.level_counts.FATAL);
                        
                        // Refresh the page to update file list
                        setTimeout(() => location.reload(), 1000);
                    }
                })
                .catch(error => {
                    alert('Error refreshing stats: ' + error.message);
                })
                .finally(() => {
                    icon.classList.remove('refresh-btn');
                });
        }
        
        function cleanupLogs() {
            if (confirm('This will delete log files older than 14 days. Continue?')) {
                fetch('?action=cleanup_logs', { method: 'POST' })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert('Log cleanup completed');
                            location.reload();
                        } else {
                            alert('Error during cleanup: ' + data.message);
                        }
                    })
                    .catch(error => {
                        alert('Error: ' + error.message);
                    });
            }
        }
    </script>
</body>
</html>
