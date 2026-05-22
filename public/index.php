<?php
define('D8TL_APP', true);
/**
 * District 8 Travel League - Public Home Page - v2.0.0-MVP
 */

// Detect environment and set include path
$includePath = file_exists(__DIR__ . '/includes/env-loader.php') 
    ? __DIR__ . '/includes'  // Production: includes is in web root
    : __DIR__ . '/../includes';  // Development: includes is one level up

// Load environment loader first
require_once $includePath . '/env-loader.php';

// Now we can use the class
// Remove: use D8TL\EnvLoader;

// Load bootstrap
require_once $includePath . '/bootstrap.php';

// Get today's games and upcoming games
$todaysGames = getTodaysGames();
$upcomingGames = getUpcomingGames(7);

$pageTitle = "Home - " . APP_NAME;

// Weather: Syracuse, NY via Open-Meteo (free, no key). Cache 30 min in /tmp.
$weather = null;
$_wCache = sys_get_temp_dir() . '/d8tl_weather.json';
if (file_exists($_wCache) && (time() - filemtime($_wCache)) < 1800) {
    $weather = json_decode(file_get_contents($_wCache), true);
} else {
    $_wCtx = stream_context_create(['http' => ['timeout' => 3, 'ignore_errors' => true]]);
    $_wRaw = @file_get_contents(
        'https://api.open-meteo.com/v1/forecast?latitude=43.0481&longitude=-76.1474' .
        '&current_weather=true' .
        '&daily=temperature_2m_max,temperature_2m_min,precipitation_probability_max' .
        '&timezone=America%2FNew_York&forecast_days=1',
        false, $_wCtx
    );
    if ($_wRaw) {
        $_wDecoded = json_decode($_wRaw, true);
        if (!empty($_wDecoded['current_weather'])) {
            file_put_contents($_wCache, $_wRaw);
            $weather = $_wDecoded;
        }
    }
}
function _wmoInfo(int $code): array {
    $map = [
        0  => ['Clear Sky',        'fa-sun',                 'text-warning'],
        1  => ['Mainly Clear',     'fa-sun',                 'text-warning'],
        2  => ['Partly Cloudy',    'fa-cloud-sun',           'text-secondary'],
        3  => ['Overcast',         'fa-cloud',               'text-secondary'],
        45 => ['Fog',              'fa-smog',                'text-secondary'],
        48 => ['Icy Fog',          'fa-smog',                'text-secondary'],
        51 => ['Light Drizzle',    'fa-cloud-rain',          'text-info'],
        53 => ['Drizzle',          'fa-cloud-rain',          'text-info'],
        55 => ['Heavy Drizzle',    'fa-cloud-rain',          'text-info'],
        61 => ['Light Rain',       'fa-cloud-rain',          'text-primary'],
        63 => ['Rain',             'fa-cloud-rain',          'text-primary'],
        65 => ['Heavy Rain',       'fa-cloud-showers-heavy', 'text-primary'],
        71 => ['Light Snow',       'fa-snowflake',           'text-info'],
        73 => ['Snow',             'fa-snowflake',           'text-info'],
        75 => ['Heavy Snow',       'fa-snowflake',           'text-info'],
        77 => ['Snow Grains',      'fa-snowflake',           'text-info'],
        80 => ['Rain Showers',     'fa-cloud-showers-heavy', 'text-primary'],
        81 => ['Rain Showers',     'fa-cloud-showers-heavy', 'text-primary'],
        82 => ['Heavy Showers',    'fa-cloud-showers-heavy', 'text-primary'],
        85 => ['Snow Showers',     'fa-snowflake',           'text-info'],
        86 => ['Heavy Snow',       'fa-snowflake',           'text-info'],
        95 => ['Thunderstorm',     'fa-bolt',                'text-warning'],
        96 => ['Thunderstorm',     'fa-bolt',                'text-warning'],
        99 => ['Thunderstorm',     'fa-bolt',                'text-warning'],
    ];
    return $map[$code] ?? ['Unknown', 'fa-question', 'text-muted'];
}
function _cToF(float $c): int { return (int) round($c * 9 / 5 + 32); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="<?php echo dirname($_SERVER['SCRIPT_NAME']); ?>/../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <?php 
    // Include navigation - handle both development and production paths
    $navPath = file_exists(__DIR__ . '/includes/nav.php') 
        ? __DIR__ . '/includes/nav.php'  // Production: includes is in same directory
        : dirname(__DIR__) . '/includes/nav.php';  // Development: includes is one level up
    include $navPath; 
    ?>

    <!-- Main Content -->
    <div class="container mt-4">
        <!-- Welcome Section -->
        <div class="row">
            <div class="col-12">
                <div class="jumbotron bg-light p-4 rounded">
                    <h1 class="display-4">Welcome to <?php echo APP_NAME; ?></h1>
                    <p class="lead">Your source for schedules, standings, and league information.</p>
                </div>
            </div>
        </div>

        <div class="row mt-4">
            <!-- Today's Games -->
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <h3>Today's Games</h3>
                    </div>
                    <div class="card-body">
                        <?php if (empty($todaysGames)): ?>
                            <p class="text-muted">No games scheduled for today.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Time</th>
                                            <th>Away</th>
                                            <th>Home</th>
                                            <th>Location</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($todaysGames as $game): ?>
                                        <tr>
                                            <td><?php echo formatTime($game['game_time']); ?></td>
                                            <td><?php echo sanitize($game['away_team']); ?></td>
                                            <td><?php echo sanitize($game['home_team']); ?></td>
                                            <td><?php echo sanitize($game['location']); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Upcoming Games (Next 7 Days) -->
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <h3>Upcoming Games (Next 7 Days)</h3>
                    </div>
                    <div class="card-body">
                        <?php if (empty($upcomingGames)): ?>
                            <p class="text-muted">No upcoming games in the next 7 days.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Time</th>
                                            <th>Away</th>
                                            <th>Home</th>
                                            <th>Location</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($upcomingGames as $game): ?>
                                        <tr>
                                            <td><?php echo formatDate($game['game_date'], 'M j'); ?></td>
                                            <td><?php echo formatTime($game['game_time']); ?></td>
                                            <td><?php echo sanitize($game['away_team']); ?></td>
                                            <td><?php echo sanitize($game['home_team']); ?></td>
                                            <td><?php echo sanitize($game['location']); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Weather Widget -->
        <div class="row mt-4">
            <div class="col-lg-4 col-md-6">
                <div class="card h-100">
                    <div class="card-header d-flex align-items-center justify-content-between">
                        <span><i class="fas fa-map-marker-alt me-1 text-danger"></i> Syracuse, NY Weather</span>
                        <?php if ($weather): ?>
                        <small class="text-muted" style="font-size:0.7rem">
                            Updated <?php echo date('g:i a', filemtime(sys_get_temp_dir() . '/d8tl_weather.json')); ?>
                        </small>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <?php if ($weather && isset($weather['current_weather'])): ?>
                            <?php
                                $cw   = $weather['current_weather'];
                                $wmo  = _wmoInfo((int)$cw['weathercode']);
                                $temp = _cToF((float)$cw['temperature']);
                                $wind = (int)round((float)$cw['windspeed'] * 0.621371); // km/h → mph
                                $hiF  = isset($weather['daily']['temperature_2m_max'][0])
                                        ? _cToF((float)$weather['daily']['temperature_2m_max'][0]) : null;
                                $loF  = isset($weather['daily']['temperature_2m_min'][0])
                                        ? _cToF((float)$weather['daily']['temperature_2m_min'][0]) : null;
                                $pop  = $weather['daily']['precipitation_probability_max'][0] ?? null;
                            ?>
                            <div class="d-flex align-items-center mb-2">
                                <i class="fas <?php echo $wmo[1]; ?> <?php echo $wmo[2]; ?> me-3" style="font-size:2.5rem"></i>
                                <div>
                                    <div style="font-size:2rem;font-weight:600;line-height:1"><?php echo $temp; ?>°F</div>
                                    <div class="text-muted" style="font-size:0.85rem"><?php echo $wmo[0]; ?></div>
                                </div>
                            </div>
                            <hr class="my-2">
                            <div class="row text-center" style="font-size:0.82rem">
                                <?php if ($hiF !== null && $loF !== null): ?>
                                <div class="col-4">
                                    <div class="text-muted">High</div>
                                    <div class="fw-semibold"><?php echo $hiF; ?>°</div>
                                </div>
                                <div class="col-4">
                                    <div class="text-muted">Low</div>
                                    <div class="fw-semibold"><?php echo $loF; ?>°</div>
                                </div>
                                <?php endif; ?>
                                <div class="col-4">
                                    <div class="text-muted">Wind</div>
                                    <div class="fw-semibold"><?php echo $wind; ?> mph</div>
                                </div>
                                <?php if ($pop !== null): ?>
                                <div class="col-4 mt-2">
                                    <div class="text-muted"><i class="fas fa-tint"></i> Rain</div>
                                    <div class="fw-semibold"><?php echo $pop; ?>%</div>
                                </div>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-muted mb-0"><i class="fas fa-exclamation-circle me-1"></i>Weather unavailable</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-light mt-5 py-4">
        <div class="container">
            <div class="row">
                <div class="col-12 text-center">
                    <p>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8'); ?>. All rights reserved.</p>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
