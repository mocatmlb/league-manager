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

// Weather: multi-location via Open-Meteo (free, no key). Cache 30 min in /tmp.
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
function _fetchWeather(float $lat, float $lon): ?array {
    $cacheKey = sys_get_temp_dir() . '/d8tl_weather_' . substr(md5("v4:lat={$lat},lon={$lon},days=7"), 0, 10) . '.json';
    if (file_exists($cacheKey) && (time() - filemtime($cacheKey)) < 1800) {
        return json_decode(file_get_contents($cacheKey), true);
    }
    $ctx = stream_context_create(['http' => ['timeout' => 3, 'ignore_errors' => true]]);
    $raw = @file_get_contents(
        'https://api.open-meteo.com/v1/forecast'
        . '?latitude=' . $lat . '&longitude=' . $lon
        . '&current_weather=true'
        . '&hourly=temperature_2m,weathercode,precipitation_probability'
        . '&daily=temperature_2m_max,temperature_2m_min,weathercode,precipitation_probability_max'
        . '&timezone=America%2FNew_York&forecast_days=7',
        false, $ctx
    );
    if ($raw) {
        $decoded = json_decode($raw, true);
        if (!empty($decoded['current_weather'])) {
            file_put_contents($cacheKey, $raw);
            return $decoded;
        }
    }
    return null;
}
function _weatherCacheTime(float $lat, float $lon): ?int {
    $cacheKey = sys_get_temp_dir() . '/d8tl_weather_' . substr(md5("v4:lat={$lat},lon={$lon},days=7"), 0, 10) . '.json';
    return file_exists($cacheKey) ? filemtime($cacheKey) : null;
}

$_defaultLocations = [['name' => 'Syracuse, NY', 'lat' => 43.0481, 'lon' => -76.1474]];
$_locJson = getSetting('weather_locations', '');
$weatherLocations = (!empty($_locJson) && is_array(json_decode($_locJson, true)))
    ? array_values(array_filter(json_decode($_locJson, true), fn($l) => !empty($l['name'])))
    : $_defaultLocations;
if (empty($weatherLocations)) $weatherLocations = $_defaultLocations;

$weatherData = [];
foreach ($weatherLocations as $loc) {
    $weatherData[] = [
        'loc'  => $loc,
        'data' => _fetchWeather((float)$loc['lat'], (float)$loc['lon']),
    ];
}
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
            <div class="col-lg-7 col-md-9">
                <div class="card">
                    <div class="card-header d-flex align-items-center justify-content-between py-2">
                        <span><i class="fas fa-cloud-sun me-1 text-info"></i> <strong>Weather</strong></span>
                    </div>

                    <?php if (count($weatherData) > 1): ?>
                    <!-- Location tabs -->
                    <div class="border-bottom px-3 pt-2" style="background:#f8f9fa">
                        <ul class="nav nav-tabs border-0" id="weatherTabs" role="tablist" style="flex-wrap:nowrap;overflow-x:auto">
                            <?php foreach ($weatherData as $wi => $wd): ?>
                            <li class="nav-item flex-shrink-0" role="presentation">
                                <button class="nav-link <?php echo $wi === 0 ? 'active' : ''; ?> px-3 py-1"
                                        id="wtab-<?php echo $wi; ?>-tab"
                                        data-bs-toggle="tab"
                                        data-bs-target="#wtab-<?php echo $wi; ?>"
                                        type="button" role="tab"
                                        style="font-size:0.82rem;white-space:nowrap">
                                    <i class="fas fa-map-marker-alt me-1 text-danger" style="font-size:0.7rem"></i>
                                    <?php echo htmlspecialchars($wd['loc']['name'], ENT_QUOTES, 'UTF-8'); ?>
                                </button>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; ?>

                    <div class="tab-content" id="weatherTabsContent">
                    <?php foreach ($weatherData as $wi => $wd):
                        $loc     = $wd['loc'];
                        $weather = $wd['data'];
                        $cacheTs = _weatherCacheTime((float)$loc['lat'], (float)$loc['lon']);
                        $tabActive = $wi === 0 ? 'show active' : '';
                    ?>
                    <div class="tab-pane fade <?php echo $tabActive; ?>" id="wtab-<?php echo $wi; ?>" role="tabpanel">

                        <?php if (count($weatherData) === 1): ?>
                        <!-- Single-location header bar -->
                        <div class="px-3 pt-2 pb-1 d-flex align-items-center justify-content-between" style="background:#f8f9fa;border-bottom:1px solid #dee2e6">
                            <small style="font-size:0.78rem"><i class="fas fa-map-marker-alt me-1 text-danger"></i><?php echo htmlspecialchars($loc['name'], ENT_QUOTES, 'UTF-8'); ?></small>
                            <?php if ($cacheTs): ?>
                            <small class="text-muted" style="font-size:0.68rem">Updated <?php echo date('g:i a', $cacheTs); ?></small>
                            <?php endif; ?>
                        </div>
                        <?php else: ?>
                        <!-- Updated timestamp for multi-location -->
                        <?php if ($cacheTs): ?>
                        <div class="px-3 pt-1" style="font-size:0.68rem;color:#aaa;text-align:right">Updated <?php echo date('g:i a', $cacheTs); ?></div>
                        <?php endif; ?>
                        <?php endif; ?>

                        <?php if ($weather && isset($weather['current_weather'])): ?>
                        <?php
                            $cw   = $weather['current_weather'];
                            $wmo  = _wmoInfo((int)$cw['weathercode']);
                            $temp = _cToF((float)$cw['temperature']);
                            $wind = (int)round((float)$cw['windspeed'] * 0.621371);
                            $hiF  = isset($weather['daily']['temperature_2m_max'][0])
                                    ? _cToF((float)$weather['daily']['temperature_2m_max'][0]) : null;
                            $loF  = isset($weather['daily']['temperature_2m_min'][0])
                                    ? _cToF((float)$weather['daily']['temperature_2m_min'][0]) : null;
                            $pop  = $weather['daily']['precipitation_probability_max'][0] ?? null;

                            // Hourly: current hour through end of today
                            $nowHour   = substr($cw['time'], 0, 13) . ':00';
                            $hourTimes = $weather['hourly']['time'] ?? [];
                            $hourStart = array_search($nowHour, $hourTimes);
                            if ($hourStart === false) $hourStart = 0;
                            $todayDate = substr($cw['time'], 0, 10);
                            $hourEnd   = $hourStart;
                            foreach ($hourTimes as $hi => $ht) {
                                if ($hi >= $hourStart && substr($ht, 0, 10) === $todayDate) {
                                    $hourEnd = $hi;
                                }
                            }
                            $hourSlice = array_slice($hourTimes, $hourStart, $hourEnd - $hourStart + 1, true);
                        ?>
                        <!-- Current Conditions -->
                        <div class="card-body pb-2">
                            <div class="d-flex align-items-center mb-2">
                                <i class="fas <?php echo $wmo[1]; ?> <?php echo $wmo[2]; ?> me-3" style="font-size:2.5rem"></i>
                                <div>
                                    <div style="font-size:2rem;font-weight:600;line-height:1"><?php echo $temp; ?>°F</div>
                                    <div class="text-muted" style="font-size:0.85rem"><?php echo $wmo[0]; ?></div>
                                </div>
                                <div class="ms-auto text-end" style="font-size:0.82rem">
                                    <?php if ($hiF !== null): ?>
                                    <div><span class="text-muted">H</span> <strong><?php echo $hiF; ?>°</strong> &nbsp; <span class="text-muted">L</span> <strong><?php echo $loF; ?>°</strong></div>
                                    <?php endif; ?>
                                    <div><span class="text-muted">Wind</span> <strong><?php echo $wind; ?> mph</strong></div>
                                    <?php if ($pop !== null): ?>
                                    <div><span class="text-muted"><i class="fas fa-tint"></i></span> <strong><?php echo $pop; ?>%</strong></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Hourly Forecast (rest of today) -->
                        <div class="border-top border-bottom px-3 py-2" style="background:#f8f9fa">
                            <div class="text-muted mb-1" style="font-size:0.7rem;text-transform:uppercase;letter-spacing:.05em">Today — Hourly</div>
                            <div class="d-flex gap-2 overflow-auto pb-1">
                                <?php foreach ($hourSlice as $i => $hTime):
                                    $hTemp = isset($weather['hourly']['temperature_2m'][$i])
                                             ? _cToF((float)$weather['hourly']['temperature_2m'][$i]) : '--';
                                    $hCode = (int)($weather['hourly']['weathercode'][$i] ?? 0);
                                    $hWmo  = _wmoInfo($hCode);
                                    $hPop  = $weather['hourly']['precipitation_probability'][$i] ?? null;
                                    $hLabel = ($i === $hourStart) ? 'Now' : date('g a', strtotime($hTime));
                                ?>
                                <div class="text-center flex-shrink-0" style="min-width:46px;font-size:0.78rem">
                                    <div class="text-muted"><?php echo $hLabel; ?></div>
                                    <i class="fas <?php echo $hWmo[1]; ?> <?php echo $hWmo[2]; ?> my-1" style="font-size:1rem"></i>
                                    <div class="fw-semibold"><?php echo $hTemp; ?>°</div>
                                    <?php if ($hPop !== null && $hPop > 0): ?>
                                    <div style="font-size:0.68rem;color:#6ba3be"><i class="fas fa-tint"></i><?php echo $hPop; ?>%</div>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- 7-Day Daily Forecast -->
                        <div class="card-body pt-2 pb-2">
                            <div class="text-muted mb-2" style="font-size:0.7rem;text-transform:uppercase;letter-spacing:.05em">7-Day Forecast</div>
                            <?php
                                $dayTimes = $weather['daily']['time'] ?? [];
                                foreach ($dayTimes as $d => $dDate):
                                    $dWmo  = _wmoInfo((int)($weather['daily']['weathercode'][$d] ?? 0));
                                    $dHi   = isset($weather['daily']['temperature_2m_max'][$d])
                                             ? _cToF((float)$weather['daily']['temperature_2m_max'][$d]) : '--';
                                    $dLo   = isset($weather['daily']['temperature_2m_min'][$d])
                                             ? _cToF((float)$weather['daily']['temperature_2m_min'][$d]) : '--';
                                    $dPop  = $weather['daily']['precipitation_probability_max'][$d] ?? null;
                                    $dLabel = $d === 0 ? 'Today' : date('D M j', strtotime($dDate));
                            ?>
                            <div class="d-flex align-items-center justify-content-between py-1 <?php echo $d < count($dayTimes)-1 ? 'border-bottom' : ''; ?>" style="font-size:0.84rem">
                                <div style="width:72px" class="fw-semibold"><?php echo $dLabel; ?></div>
                                <i class="fas <?php echo $dWmo[1]; ?> <?php echo $dWmo[2]; ?>" style="font-size:1.1rem;width:22px;text-align:center"></i>
                                <div class="text-muted flex-grow-1 ms-2" style="font-size:0.78rem"><?php echo $dWmo[0]; ?></div>
                                <?php if ($dPop !== null): ?>
                                <div style="font-size:0.78rem;min-width:38px;text-align:right;color:#6ba3be" class="me-3">
                                    <i class="fas fa-tint"></i> <?php echo $dPop; ?>%
                                </div>
                                <?php endif; ?>
                                <div style="min-width:70px;text-align:right">
                                    <strong><?php echo $dHi; ?>°</strong>
                                    <span class="text-muted ms-1"><?php echo $dLo; ?>°</span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                        <div class="card-body">
                            <p class="text-muted mb-0"><i class="fas fa-exclamation-circle me-1"></i>Weather unavailable</p>
                        </div>
                        <?php endif; ?>

                    </div><!-- /.tab-pane -->
                    <?php endforeach; ?>
                    </div><!-- /.tab-content -->

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
