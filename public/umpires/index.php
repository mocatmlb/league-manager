<?php
define('D8TL_APP', true);

$envLoader = file_exists(__DIR__ . '/../includes/env-loader.php')
    ? __DIR__ . '/../includes/env-loader.php'
    : __DIR__ . '/../../includes/env-loader.php';
require_once $envLoader;
require_once EnvLoader::getPath('includes/bootstrap.php');
require_once EnvLoader::getPath('includes/PermissionGuard.php');
require_once EnvLoader::getPath('includes/UmpireAssignmentService.php');

PermissionGuard::requireRole('umpire', '/login.php');

$userId = (int) ($_SESSION['coach_user_id'] ?? 0);
if ($userId <= 0) {
    header('Location: /login.php');
    exit;
}

$service = new UmpireAssignmentService();
$grouped = $service->getUmpireAssignmentsGrouped($userId);
$declineLog = $service->getUmpireDeclineLog($userId);
$anyAssignments = !empty($grouped['today']) || !empty($grouped['future']) || !empty($grouped['past']);
$flashSuccess = (string) ($_SESSION['flash_success'] ?? '');
$flashError = (string) ($_SESSION['flash_error'] ?? '');
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$currentUser = Auth::getCurrentUser();
$name = htmlspecialchars(trim(($currentUser['first_name'] ?? '') . ' ' . ($currentUser['last_name'] ?? '')));

function umpirePortalFormatDate(?string $date): string {
    $ts = $date !== null && trim($date) !== '' ? strtotime($date) : false;
    return $ts !== false ? date('m/d/Y', $ts) : 'TBD';
}

function umpirePortalFormatTime(?string $time): string {
    $ts = $time !== null && trim($time) !== '' ? strtotime($time) : false;
    return $ts !== false ? date('g:i A', $ts) : 'TBD';
}

function umpirePortalFormatDateTime(?string $datetime): string {
    $ts = $datetime !== null && trim($datetime) !== '' ? strtotime($datetime) : false;
    return $ts !== false ? date('m/d/Y g:i A', $ts) : 'TBD';
}

function umpirePortalMapsUrl(array $a): string {
    $parts = [];
    if (!empty($a['location_name'])) $parts[] = $a['location_name'];
    if (!empty($a['address'])) $parts[] = $a['address'];
    if (!empty($a['city'])) $parts[] = $a['city'];
    if (!empty($a['state'])) $parts[] = $a['state'];
    if (!empty($a['zip_code'])) $parts[] = $a['zip_code'];
    if (empty($parts)) return '';
    return 'https://maps.google.com/?q=' . urlencode(implode(', ', $parts));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Assignments — District 8 Travel League</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../../assets/css/style.css" rel="stylesheet">
    <style>
        .decline-action {
            min-height: 44px;
            min-width: 44px;
        }
        .section-heading {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .section-heading .badge {
            font-size: 0.75rem;
        }
    </style>
</head>
<body class="bg-light">

    <?php
    $navPath = file_exists(__DIR__ . '/../includes/nav.php')
        ? __DIR__ . '/../includes/nav.php'
        : __DIR__ . '/../../includes/nav.php';
    include $navPath;
    ?>

    <div class="container mt-4">
        <div class="row">
            <div class="col-12">
                <h1 class="mb-4">My Assignments</h1>

<?php if ($flashSuccess): ?>
                <div class="alert alert-success" role="alert"><?= htmlspecialchars($flashSuccess, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>
<?php if ($flashError): ?>
                <div class="alert alert-danger" role="alert"><?= htmlspecialchars($flashError, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

<?php if (!$anyAssignments): ?>
                <div class="alert alert-info">You have no published assignments.</div>
<?php else: ?>

<?php
$sections = [
    'today' => ['label' => "Today's Assignments", 'icon' => 'fa-calendar-day'],
    'future' => ['label' => 'Future Assignments', 'icon' => 'fa-calendar-alt'],
    'past' => ['label' => 'Past Assignments', 'icon' => 'fa-calendar-check'],
];

foreach ($sections as $key => $section):
    $items = $grouped[$key] ?? [];
?>
                <div class="mb-4">
                    <h2 class="section-heading h4 mb-3">
                        <i class="fas <?= $section['icon'] ?> text-secondary"></i>
                        <?= $section['label'] ?>
                        <span class="badge bg-secondary rounded-pill"><?= count($items) ?></span>
                    </h2>

<?php if (empty($items)): ?>
                    <div class="alert alert-info py-2">No games <?= $key === 'today' ? 'today' : ($key === 'future' ? 'upcoming' : 'in the past') ?>.</div>
<?php else: ?>
                    <div class="d-lg-none">
<?php foreach ($items as $a): ?>
                        <div class="mobile-game-card">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <strong><?= htmlspecialchars(umpirePortalFormatDate($a['game_date'] ?? null)) ?></strong><br>
                                    <small class="text-muted"><?= htmlspecialchars(umpirePortalFormatTime($a['game_time'] ?? null)) ?></small>
                                </div>
                                <span class="badge bg-info"><?= htmlspecialchars($a['slot_label'] ?? '') ?></span>
                            </div>
                            <div class="game-meta">
                                <?php $mapsUrl = umpirePortalMapsUrl($a); if ($mapsUrl): ?>
                                    <a href="<?= $mapsUrl ?>" target="_blank" rel="noopener noreferrer">
                                        <i class="fas fa-map-marker-alt text-danger me-1"></i><?= htmlspecialchars($a['location_name'] ?? '') ?>
                                    </a>
                                <?php else: ?>
                                    <?= htmlspecialchars($a['location_name'] ?? '') ?>
                                <?php endif; ?>
                                &middot; <?= htmlspecialchars($a['division_name'] ?? '') ?>
                                &middot; <?= htmlspecialchars($a['fee_text'] ?? '') ?>
                            </div>
                            <div class="small mt-1">
                                <strong>Assignor:</strong>
                                <?php $assignorName = htmlspecialchars($a['assignor_name'] ?? 'Contact your assignor'); ?>
                                <?= $assignorName ?>
                                <?php if (!empty($a['assignor_email'])): ?>
                                    &middot; <a href="mailto:<?= htmlspecialchars($a['assignor_email']) ?>"><?= htmlspecialchars($a['assignor_email']) ?></a>
                                <?php endif; ?>
                                <?php if (!empty($a['assignor_phone'])): ?>
                                    &middot; <a href="<?= htmlspecialchars($a['assignor_phone_tel']) ?>"><?= htmlspecialchars($a['assignor_phone']) ?></a>
                                <?php endif; ?>
                            </div>
                            <div class="small">
                                <strong>Partner:</strong>
                                <?php if (!empty($a['partner_user_id'])): ?>
                                    <?= htmlspecialchars($a['partner_name'] ?: 'Partner Umpire') ?>
                                    <?php if (!empty($a['partner_email'])): ?>
                                        &middot; <a href="mailto:<?= htmlspecialchars($a['partner_email']) ?>"><?= htmlspecialchars($a['partner_email']) ?></a>
                                    <?php endif; ?>
                                    <?php if (!empty($a['partner_phone'])): ?>
                                        &middot; <a href="<?= htmlspecialchars($a['partner_phone_tel']) ?>"><?= htmlspecialchars($a['partner_phone']) ?></a>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-muted">Not yet assigned</span>
                                <?php endif; ?>
                            </div>
                            <div class="small">
                                <strong>Home:</strong>
                                <?php if (!empty($a['home_coach_name'])): ?>
                                    <?= htmlspecialchars($a['home_coach_name']) ?>
                                    <?php if (!empty($a['home_coach_email'])): ?>
                                        &middot; <a href="mailto:<?= htmlspecialchars($a['home_coach_email']) ?>"><?= htmlspecialchars($a['home_coach_email']) ?></a>
                                    <?php endif; ?>
                                    <?php if (!empty($a['home_coach_phone'])): ?>
                                        &middot; <a href="<?= htmlspecialchars($a['home_coach_phone_tel']) ?>"><?= htmlspecialchars($a['home_coach_phone']) ?></a>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-muted">N/A</span>
                                <?php endif; ?>
                            </div>
                            <div class="small">
                                <strong>Away:</strong>
                                <?php if (!empty($a['away_coach_name'])): ?>
                                    <?= htmlspecialchars($a['away_coach_name']) ?>
                                    <?php if (!empty($a['away_coach_email'])): ?>
                                        &middot; <a href="mailto:<?= htmlspecialchars($a['away_coach_email']) ?>"><?= htmlspecialchars($a['away_coach_email']) ?></a>
                                    <?php endif; ?>
                                    <?php if (!empty($a['away_coach_phone'])): ?>
                                        &middot; <a href="<?= htmlspecialchars($a['away_coach_phone_tel']) ?>"><?= htmlspecialchars($a['away_coach_phone']) ?></a>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-muted">N/A</span>
                                <?php endif; ?>
                            </div>
                            <div class="mt-2">
                                <?php if (!empty($a['decline_allowed'])): ?>
                                    <a class="btn btn-outline-danger btn-sm decline-action d-inline-flex align-items-center"
                                       href="/umpires/decline.php?assignment_id=<?= htmlspecialchars((string) ($a['assignment_id'] ?? 0), ENT_QUOTES, 'UTF-8') ?>">
                                        <i class="fas fa-times-circle me-1"></i>Decline
                                    </a>
                                <?php else: ?>
                                    <span class="d-block small text-muted mb-1" tabindex="0">
                                        Decline not available within <?= htmlspecialchars((string) ($a['decline_lockout_hours'] ?? 48), ENT_QUOTES, 'UTF-8') ?> hours. Contact your assignor.
                                    </span>
                                    <button type="button" class="btn btn-outline-secondary btn-sm decline-action" disabled aria-disabled="true">Decline</button>
                                <?php endif; ?>
                            </div>
                        </div>
<?php endforeach; ?>
                    </div>
                    <div class="table-responsive d-none d-lg-block">
                        <table class="table umpire-assignments-table">
                            <thead>
                                <tr>
                                    <th style="width:20px"></th>
                                    <th>Date</th>
                                    <th>Time</th>
                                    <th>Location</th>
                                    <th>Div</th>
                                    <th>Role</th>
                                    <th>Fee</th>
                                    <th style="width:90px">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
<?php foreach ($items as $a): ?>
                                <tr class="core-row" tabindex="0" role="button" aria-expanded="false" onclick="toggleAssignmentDetail(this)" onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();toggleAssignmentDetail(this)}">
                                    <td><i class="fas fa-chevron-right expand-toggle" aria-hidden="true"></i></td>
                                    <td style="font-weight:500"><?= htmlspecialchars(umpirePortalFormatDate($a['game_date'] ?? null)) ?></td>
                                    <td><?= htmlspecialchars(umpirePortalFormatTime($a['game_time'] ?? null)) ?></td>
                                    <td><?php $mapsUrl = umpirePortalMapsUrl($a); if ($mapsUrl): ?>
                                        <a href="<?= $mapsUrl ?>" target="_blank" rel="noopener noreferrer" onclick="event.stopPropagation()">
                                            <i class="fas fa-map-marker-alt text-danger me-1"></i><?= htmlspecialchars($a['location_name'] ?? '') ?>
                                        </a>
                                    <?php else: ?>
                                        <?= htmlspecialchars($a['location_name'] ?? '') ?>
                                    <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($a['division_name'] ?? '') ?></td>
                                    <td><span class="badge bg-info"><?= htmlspecialchars($a['slot_label'] ?? '') ?></span></td>
                                    <td><?= htmlspecialchars($a['fee_text'] ?? '') ?></td>
                                    <td>
                                        <?php if (!empty($a['decline_allowed'])): ?>
                                            <a class="btn btn-outline-danger btn-sm decline-action d-inline-flex align-items-center"
                                               href="/umpires/decline.php?assignment_id=<?= htmlspecialchars((string) ($a['assignment_id'] ?? 0), ENT_QUOTES, 'UTF-8') ?>"
                                               onclick="event.stopPropagation()">
                                                <i class="fas fa-times-circle me-1"></i>Decline
                                            </a>
                                        <?php else: ?>
                                            <span class="d-block small text-muted mb-1" tabindex="0">
                                                Decline not available within <?= htmlspecialchars((string) ($a['decline_lockout_hours'] ?? 48), ENT_QUOTES, 'UTF-8') ?> hours. Contact your assignor.
                                            </span>
                                            <button type="button" class="btn btn-outline-secondary btn-sm decline-action" disabled aria-disabled="true" onclick="event.stopPropagation()">Decline</button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr class="detail-row" aria-hidden="true">
                                    <td colspan="8">
                                        <div class="detail-grid">
                                            <div class="detail-card">
                                                <h6><i class="fas fa-user-tie me-1"></i>Assignor</h6>
                                                <div class="contact-name"><?= htmlspecialchars($a['assignor_name'] ?? 'Contact your assignor') ?></div>
                                                <?php if (!empty($a['assignor_email'])): ?>
                                                <div class="contact-line"><a href="mailto:<?= htmlspecialchars($a['assignor_email']) ?>"><?= htmlspecialchars($a['assignor_email']) ?></a></div>
                                                <?php endif; ?>
                                                <?php if (!empty($a['assignor_phone'])): ?>
                                                <div class="contact-line"><a href="<?= htmlspecialchars($a['assignor_phone_tel']) ?>"><?= htmlspecialchars($a['assignor_phone']) ?></a></div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="detail-card">
                                                <h6><i class="fas fa-handshake me-1"></i>Partner Umpire</h6>
                                                <?php if (!empty($a['partner_user_id'])): ?>
                                                <div class="contact-name"><?= htmlspecialchars($a['partner_name'] ?: 'Partner Umpire') ?></div>
                                                    <?php if (!empty($a['partner_email'])): ?>
                                                <div class="contact-line"><a href="mailto:<?= htmlspecialchars($a['partner_email']) ?>"><?= htmlspecialchars($a['partner_email']) ?></a></div>
                                                    <?php endif; ?>
                                                    <?php if (!empty($a['partner_phone'])): ?>
                                                <div class="contact-line"><a href="<?= htmlspecialchars($a['partner_phone_tel']) ?>"><?= htmlspecialchars($a['partner_phone']) ?></a></div>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                <div class="contact-line text-muted">Not yet assigned</div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="detail-card">
                                                <h6><i class="fas fa-users me-1"></i>Matchup</h6>
                                                <div class="contact-name" style="font-size:0.85rem;overflow-wrap:break-word;"><?= htmlspecialchars($a['home_team'] ?? '') ?> vs <?= htmlspecialchars($a['away_team'] ?? '') ?></div>
                                                <div class="contact-line" style="margin-top:0.2rem;">
                                                    <strong>Home:</strong>
                                                    <?php if (!empty($a['home_coach_name'])): ?>
                                                        <?= htmlspecialchars($a['home_coach_name']) ?>
                                                        <?php if (!empty($a['home_coach_email'])): ?> &middot; <a href="mailto:<?= htmlspecialchars($a['home_coach_email']) ?>"><?= htmlspecialchars($a['home_coach_email']) ?></a><?php endif; ?>
                                                        <?php if (!empty($a['home_coach_phone'])): ?> &middot; <a href="<?= htmlspecialchars($a['home_coach_phone_tel']) ?>"><?= htmlspecialchars($a['home_coach_phone']) ?></a><?php endif; ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">N/A</span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="contact-line">
                                                    <strong>Away:</strong>
                                                    <?php if (!empty($a['away_coach_name'])): ?>
                                                        <?= htmlspecialchars($a['away_coach_name']) ?>
                                                        <?php if (!empty($a['away_coach_email'])): ?> &middot; <a href="mailto:<?= htmlspecialchars($a['away_coach_email']) ?>"><?= htmlspecialchars($a['away_coach_email']) ?></a><?php endif; ?>
                                                        <?php if (!empty($a['away_coach_phone'])): ?> &middot; <a href="<?= htmlspecialchars($a['away_coach_phone_tel']) ?>"><?= htmlspecialchars($a['away_coach_phone']) ?></a><?php endif; ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">N/A</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
<?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
<?php endif; ?>
                </div>
<?php endforeach; ?>
<?php endif; ?>

<?php if (!empty($declineLog)): ?>
                <div class="mb-4">
                    <h2 class="section-heading h4 mb-3">
                        <i class="fas fa-history text-secondary"></i>
                        Decline History
                        <span class="badge bg-secondary rounded-pill"><?= count($declineLog) ?></span>
                    </h2>
                    <div class="d-lg-none">
<?php foreach ($declineLog as $d): ?>
                        <div class="mobile-game-card">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <strong><?= htmlspecialchars(umpirePortalFormatDate($d['game_date'] ?? null)) ?></strong><br>
                                    <small class="text-muted"><?= htmlspecialchars(umpirePortalFormatTime($d['game_time'] ?? null)) ?></small>
                                </div>
                                <span class="badge bg-secondary"><?= htmlspecialchars($d['slot_label'] ?? '') ?></span>
                            </div>
                            <div class="game-meta">
                                <?= htmlspecialchars($d['location_name'] ?? '') ?>
                                &middot; <?= htmlspecialchars($d['division_name'] ?? '') ?>
                            </div>
                            <div class="small mt-1">
                                <strong>Declined:</strong> <?= htmlspecialchars(umpirePortalFormatDateTime($d['declined_at'] ?? null)) ?>
                                &middot; <?= htmlspecialchars((string) ($d['hours_until_game_start'] ?? 0)) ?>h before game
                            </div>
                        </div>
<?php endforeach; ?>
                    </div>
                    <div class="table-responsive d-none d-lg-block">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Time</th>
                                    <th>Location</th>
                                    <th>Division</th>
                                    <th>Role</th>
                                    <th>Declined On</th>
                                    <th>Hours Before Game</th>
                                </tr>
                            </thead>
                            <tbody>
<?php foreach ($declineLog as $d): ?>
                                <tr>
                                    <td><?= htmlspecialchars(umpirePortalFormatDate($d['game_date'] ?? null)) ?></td>
                                    <td><?= htmlspecialchars(umpirePortalFormatTime($d['game_time'] ?? null)) ?></td>
                                    <td><?= htmlspecialchars($d['location_name'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($d['division_name'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($d['slot_label'] ?? '') ?></td>
                                    <td><?= htmlspecialchars(umpirePortalFormatDateTime($d['declined_at'] ?? null)) ?></td>
                                    <td><?= htmlspecialchars((string) ($d['hours_until_game_start'] ?? 0)) ?></td>
                                </tr>
<?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
<?php endif; ?>

            </div>
        </div>
    </div>

    <footer class="bg-light mt-5 py-4">
        <div class="container text-center">
            <p class="mb-0">&copy; <?= date('Y') ?> <?= htmlspecialchars(APP_NAME ?? 'District 8 Travel League', ENT_QUOTES, 'UTF-8') ?>. All rights reserved.</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function toggleAssignmentDetail(row) {
        var detail = row.nextElementSibling;
        var icon = row.querySelector('.expand-toggle');
        if (detail && detail.classList.contains('detail-row')) {
            var expanded = detail.classList.toggle('show');
            row.classList.toggle('active-row');
            row.setAttribute('aria-expanded', expanded);
            detail.setAttribute('aria-hidden', !expanded);
            if (icon) {
                icon.classList.toggle('open');
            }
        }
    }
    </script>
</body>
</html>
