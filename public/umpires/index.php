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
    if (!empty($a['address'])) {
        $parts[] = $a['address'];
        if (!empty($a['city'])) $parts[] = $a['city'];
        if (!empty($a['state'])) $parts[] = $a['state'];
        if (!empty($a['zip_code'])) $parts[] = $a['zip_code'];
    } else {
        if (!empty($a['location_name'])) $parts[] = $a['location_name'];
        if (!empty($a['city'])) $parts[] = $a['city'];
        if (!empty($a['state'])) $parts[] = $a['state'];
    }
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
<?php foreach ($items as $a):
    $aId = (int) ($a['assignment_id'] ?? 0);
    $mapsUrl = umpirePortalMapsUrl($a);
    $pickDeclineLocked = empty($a['decline_allowed']);
    $lockoutMsg = 'Decline not available within ' . htmlspecialchars((string) ($a['decline_lockout_hours'] ?? 48), ENT_QUOTES, 'UTF-8') . ' hours. Contact your assignor.';
    $callContacts = [];
    if (!empty($a['assignor_phone'])) {
        $callContacts[] = ['label' => 'Assignor', 'name' => $a['assignor_name'] ?? '', 'tel' => $a['assignor_phone_tel'] ?? '', 'phone' => $a['assignor_phone'] ?? ''];
    }
    if (!empty($a['partner_user_id']) && !empty($a['partner_phone'])) {
        $callContacts[] = ['label' => 'Partner', 'name' => $a['partner_name'] ?? '', 'tel' => $a['partner_phone_tel'] ?? '', 'phone' => $a['partner_phone'] ?? ''];
    }
    if (!empty($a['home_coach_phone'])) {
        $callContacts[] = ['label' => 'Home Coach', 'name' => $a['home_coach_name'] ?? '', 'tel' => $a['home_coach_phone_tel'] ?? '', 'phone' => $a['home_coach_phone'] ?? ''];
    }
    if (!empty($a['away_coach_phone'])) {
        $callContacts[] = ['label' => 'Away Coach', 'name' => $a['away_coach_name'] ?? '', 'tel' => $a['away_coach_phone_tel'] ?? '', 'phone' => $a['away_coach_phone'] ?? ''];
    }
?>
                        <div class="mobile-game-card assignment-mobile-card">
                            <div class="assignment-mobile-header" onclick="toggleMobileAssignmentCardFromHeader(event, this)">
                                <div class="assignment-mobile-summary">
                                    <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <strong><?= htmlspecialchars(umpirePortalFormatDate($a['game_date'] ?? null)) ?></strong><br>
                                    <small class="text-muted"><?= htmlspecialchars(umpirePortalFormatTime($a['game_time'] ?? null)) ?></small>
                                </div>
                                <span class="badge bg-info"><?= htmlspecialchars($a['slot_label'] ?? '') ?></span>
                                    </div>
                                    <div class="game-meta">
                                <?php if ($mapsUrl): ?>
                                    <a href="<?= $mapsUrl ?>" target="_blank" rel="noopener noreferrer">
                                        <i class="fas fa-map-marker-alt text-danger me-1"></i><?= htmlspecialchars($a['location_name'] ?? '') ?>
                                    </a>
                                <?php else: ?>
                                    <?= htmlspecialchars($a['location_name'] ?? '') ?>
                                <?php endif; ?>
                                &middot; <?= htmlspecialchars($a['division_name'] ?? '') ?>
                                &middot; <?= htmlspecialchars($a['fee_text'] ?? '') ?>
                                    </div>
                                </div>
                                <button class="assignment-mobile-toggle" type="button" aria-expanded="false" aria-controls="assignment-mobile-details-<?= $aId ?>" onclick="toggleMobileAssignmentCard(this)">
                                    <i class="fas fa-chevron-down assignment-mobile-toggle__icon" aria-hidden="true"></i>
                                    <span class="visually-hidden">Toggle assignment details</span>
                                </button>
                            </div>
                            <div class="assignment-mobile-card-body" id="assignment-mobile-details-<?= $aId ?>" hidden>
                            <div class="assignment-mobile-details">
                                <div class="assignment-detail-card">
                                    <div class="assignment-detail-card__header"><i class="fas fa-user-tie"></i> Assignor</div>
                                    <div class="assignment-detail-card__body">
                                        <div class="contact-name"><?= htmlspecialchars($a['assignor_name'] ?? 'Contact your assignor') ?></div>
                                        <?php if (!empty($a['assignor_email'])): ?>
                                        <div class="contact-line"><a href="mailto:<?= htmlspecialchars($a['assignor_email']) ?>"><?= htmlspecialchars($a['assignor_email']) ?></a></div>
                                        <?php endif; ?>
                                        <?php if (!empty($a['assignor_phone'])): ?>
                                        <div class="contact-line"><a href="<?= htmlspecialchars($a['assignor_phone_tel']) ?>"><?= htmlspecialchars($a['assignor_phone']) ?></a></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="assignment-detail-card">
                                    <div class="assignment-detail-card__header"><i class="fas fa-handshake"></i> Partner</div>
                                    <div class="assignment-detail-card__body">
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
                                </div>
                                <div class="assignment-detail-card">
                                    <div class="assignment-detail-card__header"><i class="fas fa-users"></i> Game</div>
                                    <div class="assignment-detail-card__body">
                                        <div class="contact-name-matchup"><?= htmlspecialchars($a['home_team'] ?? '') ?> vs <?= htmlspecialchars($a['away_team'] ?? '') ?></div>
                                        <div class="contact-line contact-sub">
                                            <strong>Home:</strong>
                                            <?php if (!empty($a['home_coach_name'])): ?>
                                                <?= htmlspecialchars($a['home_coach_name']) ?>
                                                <?php if (!empty($a['home_coach_email'])): ?> &middot; <a href="mailto:<?= htmlspecialchars($a['home_coach_email']) ?>"><?= htmlspecialchars($a['home_coach_email']) ?></a><?php endif; ?>
                                                <?php if (!empty($a['home_coach_phone'])): ?> &middot; <a href="<?= htmlspecialchars($a['home_coach_phone_tel']) ?>"><?= htmlspecialchars($a['home_coach_phone']) ?></a><?php endif; ?>
                                            <?php else: ?><span class="text-muted">N/A</span><?php endif; ?>
                                        </div>
                                        <div class="contact-line">
                                            <strong>Away:</strong>
                                            <?php if (!empty($a['away_coach_name'])): ?>
                                                <?= htmlspecialchars($a['away_coach_name']) ?>
                                                <?php if (!empty($a['away_coach_email'])): ?> &middot; <a href="mailto:<?= htmlspecialchars($a['away_coach_email']) ?>"><?= htmlspecialchars($a['away_coach_email']) ?></a><?php endif; ?>
                                                <?php if (!empty($a['away_coach_phone'])): ?> &middot; <a href="<?= htmlspecialchars($a['away_coach_phone_tel']) ?>"><?= htmlspecialchars($a['away_coach_phone']) ?></a><?php endif; ?>
                                            <?php else: ?><span class="text-muted">N/A</span><?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="assignment-mobile-actions">
                                <?php if ($mapsUrl): ?>
                                <a class="btn btn-outline-secondary btn-sm" href="<?= $mapsUrl ?>" target="_blank" rel="noopener noreferrer">
                                    <i class="fas fa-map-marker-alt me-1"></i>Map
                                </a>
                                <?php endif; ?>
                                <button class="btn btn-outline-secondary btn-sm" type="button" onclick="toggleCallPicker(<?= $aId ?>)">
                                    <i class="fas fa-phone me-1"></i>Call
                                </button>
                                <?php if (!$pickDeclineLocked): ?>
                                <button class="btn btn-outline-danger btn-sm" type="button" onclick="toggleDeclineConfirm(<?= $aId ?>)">
                                    <i class="fas fa-times-circle me-1"></i>Decline
                                </button>
                                <?php else: ?>
                                <span class="d-block small text-muted w-100" tabindex="0"><?= $lockoutMsg ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="assignment-panel" id="call-picker-<?= $aId ?>">
                                <div class="assignment-panel__title"><i class="fas fa-phone me-1"></i>Call someone about this game</div>
                                <?php if (!empty($callContacts)): ?>
                                <?php foreach ($callContacts as $cc): ?>
                                <div class="assignment-panel__row">
                                    <span class="assignment-panel__row-label"><?= htmlspecialchars($cc['label']) ?></span>
                                    <span class="assignment-panel__row-name"><?= htmlspecialchars($cc['name']) ?></span>
                                    <span class="assignment-panel__row-phone"><a href="<?= htmlspecialchars($cc['tel']) ?>"><?= htmlspecialchars($cc['phone']) ?></a></span>
                                </div>
                                <?php endforeach; ?>
                                <?php else: ?>
                                <div class="assignment-panel__empty">No phone numbers available for this assignment.</div>
                                <?php endif; ?>
                            </div>
                            <div class="assignment-panel assignment-decline-confirm" id="decline-confirm-<?= $aId ?>">
                                <div class="assignment-panel__title">Decline this assignment?</div>
                                <div class="confirm-game-detail">
                                    <?= htmlspecialchars($a['home_team'] ?? '') ?> vs <?= htmlspecialchars($a['away_team'] ?? '') ?><br>
                                    <?= htmlspecialchars(umpirePortalFormatDate($a['game_date'] ?? null)) ?> at <?= htmlspecialchars(umpirePortalFormatTime($a['game_time'] ?? null)) ?><br>
                                    <?= htmlspecialchars($a['location_name'] ?? '') ?>
                                </div>
                                <div class="d-flex gap-2">
                                    <a class="btn btn-danger btn-sm" href="/umpires/decline.php?assignment_id=<?= $aId ?>">
                                        <i class="fas fa-check-circle me-1"></i>Yes, decline
                                    </a>
                                    <button class="btn btn-outline-secondary btn-sm" type="button" onclick="toggleDeclineConfirm(<?= $aId ?>)">
                                        <i class="fas fa-times me-1"></i>Keep assignment
                                    </button>
                                </div>
                            </div>
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
<?php foreach ($items as $a):
    $aId = (int) ($a['assignment_id'] ?? 0);
    $mapsUrl = umpirePortalMapsUrl($a);
    $pickDeclineLocked = empty($a['decline_allowed']);
    $lockoutMsg = 'Decline not available within ' . htmlspecialchars((string) ($a['decline_lockout_hours'] ?? 48), ENT_QUOTES, 'UTF-8') . ' hours. Contact your assignor.';
    $callContacts = [];
    if (!empty($a['assignor_phone'])) {
        $callContacts[] = ['label' => 'Assignor', 'name' => $a['assignor_name'] ?? '', 'tel' => $a['assignor_phone_tel'] ?? '', 'phone' => $a['assignor_phone'] ?? ''];
    }
    if (!empty($a['partner_user_id']) && !empty($a['partner_phone'])) {
        $callContacts[] = ['label' => 'Partner', 'name' => $a['partner_name'] ?? '', 'tel' => $a['partner_phone_tel'] ?? '', 'phone' => $a['partner_phone'] ?? ''];
    }
    if (!empty($a['home_coach_phone'])) {
        $callContacts[] = ['label' => 'Home Coach', 'name' => $a['home_coach_name'] ?? '', 'tel' => $a['home_coach_phone_tel'] ?? '', 'phone' => $a['home_coach_phone'] ?? ''];
    }
    if (!empty($a['away_coach_phone'])) {
        $callContacts[] = ['label' => 'Away Coach', 'name' => $a['away_coach_name'] ?? '', 'tel' => $a['away_coach_phone_tel'] ?? '', 'phone' => $a['away_coach_phone'] ?? ''];
    }
?>
                                <tr class="core-row" tabindex="0" role="button" aria-expanded="false" onclick="toggleAssignmentDetail(this)" onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();toggleAssignmentDetail(this)}">
                                    <td><i class="fas fa-chevron-right expand-toggle" aria-hidden="true"></i></td>
                                    <td style="font-weight:500"><?= htmlspecialchars(umpirePortalFormatDate($a['game_date'] ?? null)) ?></td>
                                    <td><?= htmlspecialchars(umpirePortalFormatTime($a['game_time'] ?? null)) ?></td>
                                    <td><?php if ($mapsUrl): ?>
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
                                            <div class="assignment-detail-card">
                                                <div class="assignment-detail-card__header"><i class="fas fa-user-tie"></i> Assignor</div>
                                                <div class="assignment-detail-card__body">
                                                    <div class="contact-name"><?= htmlspecialchars($a['assignor_name'] ?? 'Contact your assignor') ?></div>
                                                    <?php if (!empty($a['assignor_email'])): ?>
                                                    <div class="contact-line"><a href="mailto:<?= htmlspecialchars($a['assignor_email']) ?>"><?= htmlspecialchars($a['assignor_email']) ?></a></div>
                                                    <?php endif; ?>
                                                    <?php if (!empty($a['assignor_phone'])): ?>
                                                    <div class="contact-line"><a href="<?= htmlspecialchars($a['assignor_phone_tel']) ?>"><?= htmlspecialchars($a['assignor_phone']) ?></a></div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="assignment-detail-card">
                                                <div class="assignment-detail-card__header"><i class="fas fa-handshake"></i> Partner</div>
                                                <div class="assignment-detail-card__body">
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
                                            </div>
                                            <div class="assignment-detail-card">
                                                <div class="assignment-detail-card__header"><i class="fas fa-users"></i> Matchup</div>
                                                <div class="assignment-detail-card__body">
                                                    <div class="contact-name-matchup"><?= htmlspecialchars($a['home_team'] ?? '') ?> vs <?= htmlspecialchars($a['away_team'] ?? '') ?></div>
                                                    <div class="contact-line contact-sub">
                                                        <strong>Home:</strong>
                                                        <?php if (!empty($a['home_coach_name'])): ?>
                                                            <?= htmlspecialchars($a['home_coach_name']) ?>
                                                            <?php if (!empty($a['home_coach_email'])): ?> &middot; <a href="mailto:<?= htmlspecialchars($a['home_coach_email']) ?>"><?= htmlspecialchars($a['home_coach_email']) ?></a><?php endif; ?>
                                                            <?php if (!empty($a['home_coach_phone'])): ?> &middot; <a href="<?= htmlspecialchars($a['home_coach_phone_tel']) ?>"><?= htmlspecialchars($a['home_coach_phone']) ?></a><?php endif; ?>
                                                        <?php else: ?><span class="text-muted">N/A</span><?php endif; ?>
                                                    </div>
                                                    <div class="contact-line">
                                                        <strong>Away:</strong>
                                                        <?php if (!empty($a['away_coach_name'])): ?>
                                                            <?= htmlspecialchars($a['away_coach_name']) ?>
                                                            <?php if (!empty($a['away_coach_email'])): ?> &middot; <a href="mailto:<?= htmlspecialchars($a['away_coach_email']) ?>"><?= htmlspecialchars($a['away_coach_email']) ?></a><?php endif; ?>
                                                            <?php if (!empty($a['away_coach_phone'])): ?> &middot; <a href="<?= htmlspecialchars($a['away_coach_phone_tel']) ?>"><?= htmlspecialchars($a['away_coach_phone']) ?></a><?php endif; ?>
                                                        <?php else: ?><span class="text-muted">N/A</span><?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="assignment-action-row">
                                            <?php if ($mapsUrl): ?>
                                            <a class="btn btn-outline-secondary btn-sm" href="<?= $mapsUrl ?>" target="_blank" rel="noopener noreferrer">
                                                <i class="fas fa-map-marker-alt me-1"></i>Open Map
                                            </a>
                                            <?php endif; ?>
                                            <?php if (!empty($a['assignor_email'])): ?>
                                            <a class="btn btn-outline-secondary btn-sm" href="mailto:<?= htmlspecialchars($a['assignor_email']) ?>">
                                                <i class="fas fa-envelope me-1"></i>Email Assignor
                                            </a>
                                            <?php endif; ?>
                                            <button class="btn btn-outline-secondary btn-sm" type="button" onclick="toggleCallPicker(<?= $aId ?>)">
                                                <i class="fas fa-phone me-1"></i>Call
                                            </button>
                                            <?php if (!$pickDeclineLocked): ?>
                                            <button class="btn btn-outline-danger btn-sm" type="button" onclick="toggleDeclineConfirm(<?= $aId ?>)">
                                                <i class="fas fa-times-circle me-1"></i>Decline assignment
                                            </button>
                                            <?php else: ?>
                                            <span class="d-block small text-muted" tabindex="0"><?= $lockoutMsg ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="assignment-panel" id="call-picker-desktop-<?= $aId ?>">
                                            <div class="assignment-panel__title"><i class="fas fa-phone me-1"></i>Call someone about this game</div>
                                            <?php if (!empty($callContacts)): ?>
                                            <?php foreach ($callContacts as $cc): ?>
                                            <div class="assignment-panel__row">
                                                <span class="assignment-panel__row-label"><?= htmlspecialchars($cc['label']) ?></span>
                                                <span class="assignment-panel__row-name"><?= htmlspecialchars($cc['name']) ?></span>
                                                <span class="assignment-panel__row-phone"><a href="<?= htmlspecialchars($cc['tel']) ?>"><?= htmlspecialchars($cc['phone']) ?></a></span>
                                            </div>
                                            <?php endforeach; ?>
                                            <?php else: ?>
                                            <div class="assignment-panel__empty">No phone numbers available for this assignment.</div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="assignment-panel assignment-decline-confirm" id="decline-confirm-desktop-<?= $aId ?>">
                                            <div class="assignment-panel__title">Decline this assignment?</div>
                                            <div class="confirm-game-detail">
                                                <?= htmlspecialchars($a['home_team'] ?? '') ?> vs <?= htmlspecialchars($a['away_team'] ?? '') ?><br>
                                                <?= htmlspecialchars(umpirePortalFormatDate($a['game_date'] ?? null)) ?> at <?= htmlspecialchars(umpirePortalFormatTime($a['game_time'] ?? null)) ?><br>
                                                <?= htmlspecialchars($a['location_name'] ?? '') ?>
                                            </div>
                                            <div class="d-flex gap-2">
                                                <a class="btn btn-danger btn-sm" href="/umpires/decline.php?assignment_id=<?= $aId ?>">
                                                    <i class="fas fa-check-circle me-1"></i>Yes, decline
                                                </a>
                                                <button class="btn btn-outline-secondary btn-sm" type="button" onclick="toggleDeclineConfirm(<?= $aId ?>)">
                                                    <i class="fas fa-times me-1"></i>Keep assignment
                                                </button>
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
            if (!expanded) {
                closeAllPanels(detail);
            }
        }
    }
    function toggleMobileAssignmentCard(button) {
        var bodyId = button.getAttribute('aria-controls');
        var body = bodyId ? document.getElementById(bodyId) : null;
        if (!body) return;

        var expanded = button.getAttribute('aria-expanded') === 'true';
        button.setAttribute('aria-expanded', !expanded);
        body.hidden = expanded;

        if (expanded) {
            closeAllPanels(body);
        }
    }
    function toggleMobileAssignmentCardFromHeader(event, header) {
        if (event.target.closest('a, button')) return;
        var button = header.querySelector('.assignment-mobile-toggle');
        if (button) {
            toggleMobileAssignmentCard(button);
        }
    }
    function closeAllPanels(container) {
        var panels = container.querySelectorAll('.assignment-panel.open');
        for (var i = 0; i < panels.length; i++) {
            panels[i].classList.remove('open');
        }
    }
    function getPanel(id) {
        return document.getElementById(id);
    }
    function toggleCallPicker(aId) {
        var picker = getPanel('call-picker-' + aId);
        var pickerD = getPanel('call-picker-desktop-' + aId);
        var decline = getPanel('decline-confirm-' + aId);
        var declineD = getPanel('decline-confirm-desktop-' + aId);
        if (!picker && !pickerD) return;
        [decline, declineD].forEach(function(p) { if (p) p.classList.remove('open'); });
        [picker, pickerD].forEach(function(p) { if (p) p.classList.toggle('open'); });
    }
    function toggleDeclineConfirm(aId) {
        var decline = getPanel('decline-confirm-' + aId);
        var declineD = getPanel('decline-confirm-desktop-' + aId);
        var picker = getPanel('call-picker-' + aId);
        var pickerD = getPanel('call-picker-desktop-' + aId);
        if (!decline && !declineD) return;
        [picker, pickerD].forEach(function(p) { if (p) p.classList.remove('open'); });
        [decline, declineD].forEach(function(p) { if (p) p.classList.toggle('open'); });
    }
    </script>
</body>
</html>
