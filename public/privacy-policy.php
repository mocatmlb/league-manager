<?php
define('D8TL_APP', true);

$includePath = file_exists(__DIR__ . '/includes/env-loader.php')
    ? __DIR__ . '/includes'
    : __DIR__ . '/../includes';

require_once $includePath . '/env-loader.php';
require_once EnvLoader::getPath('includes/bootstrap.php');

$pageTitle = "Privacy Policy - " . APP_NAME;
$lastUpdated = "May 25, 2026";
$contactEmail = defined('SMTP_FROM_EMAIL') ? SMTP_FROM_EMAIL : 'info@district8travelleague.com';
$appUrl = defined('APP_URL') ? APP_URL : 'https://district8travelleague.com';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="<?php echo dirname($_SERVER['SCRIPT_NAME']); ?>/../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <?php
    $navPath = file_exists(__DIR__ . '/includes/nav.php')
        ? __DIR__ . '/includes/nav.php'
        : dirname(__DIR__) . '/includes/nav.php';
    include $navPath;
    ?>

    <div class="container mt-4 mb-5">
        <div class="row justify-content-center">
            <div class="col-lg-9">
                <h1 class="mb-1">Privacy Policy</h1>
                <p class="text-muted mb-4"><em>Last updated: <?php echo $lastUpdated; ?></em></p>

                <p><?php echo htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8'); ?> ("<strong>we</strong>," "<strong>us</strong>," or "<strong>our</strong>") operates the league management platform at <a href="<?php echo htmlspecialchars($appUrl, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($appUrl, ENT_QUOTES, 'UTF-8'); ?></a>. This Privacy Policy explains how we collect, use, and protect information about coaches, administrators, and visitors who use our platform.</p>

                <hr>

                <h2 class="h4 mt-4">1. Information We Collect</h2>
                <p>We collect the following types of information:</p>
                <ul>
                    <li><strong>Account information:</strong> name, email address, and password when you register or are invited to the platform.</li>
                    <li><strong>Team and coaching information:</strong> team names, division, and season details you provide during registration.</li>
                    <li><strong>Phone number:</strong> if you opt in to receive SMS notifications, we collect your mobile phone number for that purpose.</li>
                    <li><strong>Usage data:</strong> pages visited, actions taken, and log data generated when you interact with the platform.</li>
                </ul>

                <h2 class="h4 mt-4">2. How We Use Your Information</h2>
                <p>We use the information we collect to:</p>
                <ul>
                    <li>Operate and maintain the league management platform.</li>
                    <li>Send transactional communications such as game schedule updates, reschedule notices, score confirmations, and league announcements.</li>
                    <li>Send SMS text message notifications to users who have opted in to receive them (see Section 4).</li>
                    <li>Authenticate users and protect the security of accounts.</li>
                    <li>Comply with applicable legal obligations.</li>
                </ul>

                <h2 class="h4 mt-4">3. Information Sharing and Disclosure</h2>
                <p>We do not sell, rent, or trade your personal information to third parties for marketing purposes. We may share information only in these limited circumstances:</p>
                <ul>
                    <li><strong>Service providers:</strong> We use third-party services (such as Twilio for SMS delivery and an email SMTP provider) to facilitate communications. These providers receive only the minimum information necessary to deliver the service and are prohibited from using it for other purposes.</li>
                    <li><strong>Legal requirements:</strong> We may disclose information when required by law or to protect the rights and safety of our users or the public.</li>
                </ul>

                <h2 class="h4 mt-4">4. SMS / Text Message Communications</h2>
                <p>If you opt in to receive SMS notifications from <?php echo htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8'); ?>, please note the following:</p>
                <ul>
                    <li><strong>Program description:</strong> You will receive text messages about game schedule changes, reschedule requests, game reminders, and other league operational updates.</li>
                    <li><strong>Opt-in:</strong> By providing your phone number and consenting to SMS communications, you agree to receive these messages.</li>
                    <li><strong>Opt-out:</strong> You can cancel SMS notifications at any time by replying <strong>STOP</strong> to any message we send. After texting STOP, you will receive one final confirmation message and no further messages will be sent to that number.</li>
                    <li><strong>Help:</strong> Reply <strong>HELP</strong> to any message for assistance, or contact us at <a href="mailto:<?php echo htmlspecialchars($contactEmail, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($contactEmail, ENT_QUOTES, 'UTF-8'); ?></a>.</li>
                    <li><strong>Message frequency:</strong> Message frequency varies based on league activity.</li>
                    <li><strong>Message and data rates:</strong> Message and data rates may apply. Check your mobile plan for details.</li>
                    <li><strong>Carriers:</strong> Carriers are not liable for delayed or undelivered messages.</li>
                    <li><strong>No sharing:</strong> Your phone number will never be shared with third parties for their marketing purposes.</li>
                </ul>

                <h2 class="h4 mt-4">5. Data Retention</h2>
                <p>We retain your personal information for as long as your account is active or as needed to provide services. You may request deletion of your account and associated data by contacting us at <a href="mailto:<?php echo htmlspecialchars($contactEmail, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($contactEmail, ENT_QUOTES, 'UTF-8'); ?></a>.</p>

                <h2 class="h4 mt-4">6. Security</h2>
                <p>We implement reasonable technical and organizational measures to protect your information from unauthorized access, disclosure, or loss. No method of transmission over the internet is 100% secure, however, and we cannot guarantee absolute security.</p>

                <h2 class="h4 mt-4">7. Children's Privacy</h2>
                <p>Our platform is not directed at children under 13. We do not knowingly collect personal information from children under 13. If you believe a child has provided us personal information, please contact us so we can delete it.</p>

                <h2 class="h4 mt-4">8. Changes to This Policy</h2>
                <p>We may update this Privacy Policy from time to time. We will post the updated policy on this page with a revised "Last updated" date. Continued use of the platform after changes constitutes acceptance of the revised policy.</p>

                <h2 class="h4 mt-4">9. Contact Us</h2>
                <p>If you have questions or concerns about this Privacy Policy, please contact us:</p>
                <address>
                    <strong><?php echo htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8'); ?></strong><br>
                    Email: <a href="mailto:<?php echo htmlspecialchars($contactEmail, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($contactEmail, ENT_QUOTES, 'UTF-8'); ?></a>
                </address>

                <hr class="mt-5">
                <p class="text-muted small">
                    <a href="terms.php">Terms &amp; Conditions</a> &middot;
                    <a href="index.php">Home</a>
                </p>
            </div>
        </div>
    </div>

    <footer class="bg-light py-4">
        <div class="container">
            <div class="row">
                <div class="col-12 text-center">
                    <p class="mb-1">&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8'); ?>. All rights reserved.</p>
                    <p class="mb-0 small">
                        <a href="privacy-policy.php" class="text-muted me-2">Privacy Policy</a>
                        <a href="terms.php" class="text-muted">Terms &amp; Conditions</a>
                    </p>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
