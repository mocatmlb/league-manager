<?php
define('D8TL_APP', true);

$includePath = file_exists(__DIR__ . '/includes/env-loader.php')
    ? __DIR__ . '/includes'
    : __DIR__ . '/../includes';

require_once $includePath . '/env-loader.php';
require_once EnvLoader::getPath('includes/bootstrap.php');

$pageTitle = "Terms & Conditions - " . APP_NAME;
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
                <h1 class="mb-1">Terms &amp; Conditions</h1>
                <p class="text-muted mb-4"><em>Last updated: <?php echo $lastUpdated; ?></em></p>

                <p>Please read these Terms &amp; Conditions carefully before using the <?php echo htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8'); ?> platform ("<strong>Platform</strong>") operated at <a href="<?php echo htmlspecialchars($appUrl, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($appUrl, ENT_QUOTES, 'UTF-8'); ?></a>. By accessing or using the Platform, you agree to be bound by these Terms.</p>

                <hr>

                <h2 class="h4 mt-4">1. Acceptance of Terms</h2>
                <p>By creating an account, logging in, or otherwise using the Platform, you agree to these Terms &amp; Conditions and our <a href="privacy-policy.php">Privacy Policy</a>. If you do not agree, do not use the Platform.</p>

                <h2 class="h4 mt-4">2. Use of the Platform</h2>
                <p>The Platform is provided for the purpose of managing youth and amateur travel league operations, including scheduling, standings, score reporting, and team communications. You agree to:</p>
                <ul>
                    <li>Use the Platform only for its intended league management purposes.</li>
                    <li>Provide accurate information when creating or updating your account.</li>
                    <li>Keep your login credentials confidential and notify us immediately of any unauthorized access.</li>
                    <li>Not attempt to disrupt, reverse-engineer, or gain unauthorized access to the Platform or its underlying systems.</li>
                </ul>

                <h2 class="h4 mt-4">3. SMS Messaging Terms</h2>
                <p>By opting in to SMS notifications, you agree to the following terms governing our text message program:</p>
                <ul>
                    <li><strong>Program description:</strong> <?php echo htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8'); ?> sends SMS text messages for game schedule updates, reschedule notices, game reminders, and other league operational communications.</li>
                    <li><strong>Opt-in:</strong> You opt in to our SMS program by providing your mobile phone number and consenting to receive text messages. Consent is not a condition of using the Platform or participating in the league.</li>
                    <li><strong>Opt-out / STOP:</strong> You may opt out of SMS messages at any time by replying <strong>STOP</strong> to any message. You will receive a single confirmation that your number has been removed. No further messages will be sent unless you re-enroll.</li>
                    <li><strong>Help / HELP:</strong> Reply <strong>HELP</strong> to any message for support information, or contact us directly at <a href="mailto:<?php echo htmlspecialchars($contactEmail, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($contactEmail, ENT_QUOTES, 'UTF-8'); ?></a>.</li>
                    <li><strong>Message frequency:</strong> Message frequency varies depending on league schedule activity. You may receive multiple messages per week during active periods.</li>
                    <li><strong>Message and data rates:</strong> Message and data rates may apply based on your mobile carrier and plan. <?php echo htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8'); ?> is not responsible for charges incurred from your carrier.</li>
                    <li><strong>Supported carriers:</strong> Major US carriers are supported. Carrier availability may vary. Carriers are not liable for delayed or undelivered messages.</li>
                    <li><strong>Privacy:</strong> We will not share your phone number with third parties for marketing purposes. See our <a href="privacy-policy.php">Privacy Policy</a> for details.</li>
                </ul>

                <h2 class="h4 mt-4">4. Accounts and Access</h2>
                <p>Access to the Platform is granted by invitation or administrator approval. Accounts are personal and non-transferable. We reserve the right to suspend or terminate accounts that violate these Terms or are used in a manner inconsistent with the Platform's purpose.</p>

                <h2 class="h4 mt-4">5. Content and Data</h2>
                <p>You retain ownership of any data you submit to the Platform (team names, scores, schedule information, etc.). By submitting content, you grant us a limited license to store and display it as necessary to operate the Platform. We do not claim ownership of your data.</p>

                <h2 class="h4 mt-4">6. Disclaimer of Warranties</h2>
                <p>The Platform is provided "<strong>as is</strong>" and "<strong>as available</strong>" without warranties of any kind, express or implied. We do not warrant that the Platform will be uninterrupted, error-free, or free of harmful components. Your use of the Platform is at your own risk.</p>

                <h2 class="h4 mt-4">7. Limitation of Liability</h2>
                <p>To the fullest extent permitted by applicable law, <?php echo htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8'); ?> shall not be liable for any indirect, incidental, special, consequential, or punitive damages arising from your use of, or inability to use, the Platform.</p>

                <h2 class="h4 mt-4">8. Changes to These Terms</h2>
                <p>We may update these Terms from time to time. We will post the updated Terms on this page with a revised "Last updated" date. Continued use of the Platform after changes constitutes acceptance of the revised Terms.</p>

                <h2 class="h4 mt-4">9. Governing Law</h2>
                <p>These Terms are governed by the laws of the applicable jurisdiction without regard to conflict of law principles.</p>

                <h2 class="h4 mt-4">10. Contact Us</h2>
                <p>Questions about these Terms? Contact us at:</p>
                <address>
                    <strong><?php echo htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8'); ?></strong><br>
                    Email: <a href="mailto:<?php echo htmlspecialchars($contactEmail, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($contactEmail, ENT_QUOTES, 'UTF-8'); ?></a>
                </address>

                <hr class="mt-5">
                <p class="text-muted small">
                    <a href="privacy-policy.php">Privacy Policy</a> &middot;
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
