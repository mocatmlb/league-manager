<?php
/**
 * Timezone Test Page
 */

require_once 'includes/bootstrap.php';

$db = Database::getInstance();

// Test dates from game 2024065
$testDates = [
    '2024-07-15' => 'Game 2024065 v1 (Original)',
    '2024-07-16' => 'Game 2024065 v2 (Current)',
    '2024-07-14' => 'Test date that might show as 7/13',
    '2024-12-31' => 'New Year\'s Eve test'
];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Timezone Test - District 8 Travel League</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h1>Timezone Test</h1>
        <p><strong>Application Timezone:</strong> <?php echo getAppTimezone(); ?></p>
        
        <div class="row">
            <div class="col-md-6">
                <h3>PHP Formatting (Server-side)</h3>
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Raw Date</th>
                            <th>PHP formatDate()</th>
                            <th>Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($testDates as $date => $description): ?>
                        <tr>
                            <td><?php echo $date; ?></td>
                            <td><?php echo formatDate($date, 'm/d/y'); ?></td>
                            <td><?php echo $description; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="col-md-6">
                <h3>JavaScript Formatting (Client-side)</h3>
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Raw Date</th>
                            <th>JS formatDate()</th>
                            <th>JS formatDateTZ()</th>
                        </tr>
                    </thead>
                    <tbody id="jsResults">
                        <!-- Populated by JavaScript -->
                    </tbody>
                </table>
            </div>
        </div>
        
        <div class="mt-4">
            <h3>Game 2024065 Test</h3>
            <div id="gameTest">
                <!-- Populated by JavaScript -->
            </div>
        </div>
        
        <div class="mt-4">
            <h3>Browser Information</h3>
            <p><strong>User Agent:</strong> <span id="userAgent"></span></p>
            <p><strong>Browser Timezone:</strong> <span id="browserTimezone"></span></p>
            <p><strong>Current Time:</strong> <span id="currentTime"></span></p>
        </div>
    </div>

    <script src="public/assets/js/timezone.js"></script>
    <?php outputTimezoneJS(); ?>
    
    <script>
        // Test dates
        const testDates = <?php echo json_encode(array_keys($testDates)); ?>;
        const descriptions = <?php echo json_encode(array_values($testDates)); ?>;
        
        // Populate JavaScript results
        const jsResults = document.getElementById('jsResults');
        testDates.forEach((date, index) => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${date}</td>
                <td>${formatDate(date)}</td>
                <td>${formatDateTZ(date, {month: 'numeric', day: 'numeric', year: '2-digit'})}</td>
            `;
            jsResults.appendChild(row);
        });
        
        // Test game 2024065 scenario
        const gameTest = document.getElementById('gameTest');
        gameTest.innerHTML = `
            <div class="alert alert-info">
                <h5>Game 2024065 Schedule History Test:</h5>
                <p><strong>v1 (Original):</strong> ${formatDateTZ('2024-07-15', {month: 'numeric', day: 'numeric', year: '2-digit'})}</p>
                <p><strong>v2 (Current):</strong> ${formatDateTZ('2024-07-16', {month: 'numeric', day: 'numeric', year: '2-digit'})}</p>
                <p><strong>Expected:</strong> v1 = 7/15/24, v2 = 7/16/24</p>
            </div>
        `;
        
        // Browser information
        document.getElementById('userAgent').textContent = navigator.userAgent;
        document.getElementById('browserTimezone').textContent = Intl.DateTimeFormat().resolvedOptions().timeZone;
        document.getElementById('currentTime').textContent = new Date().toString();
    </script>
</body>
</html>

