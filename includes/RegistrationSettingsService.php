<?php
/**
 * District 8 Travel League - Registration Settings Service
 *
 * Owns the open-registration toggle and the registration-URL builder. Keeps
 * the `ActivityLogger::log()` contract ("services log; pages call services")
 * intact and centralizes the dev-vs-prod URL prefix logic so the QR code,
 * the email links, and any future surface all agree.
 */

if (!defined('D8TL_APP')) {
    die('Direct access not permitted');
}

if (!class_exists('ActivityLogger')) {
    require_once __DIR__ . '/ActivityLogger.php';
}

class RegistrationSettingsService {
    /**
     * Update the open-registration toggle and emit an audit log entry.
     * Returns the new normalized state ('1' or '0').
     */
    public static function setOpenRegistration(bool $enabled, int $adminUserId): string {
        $value = $enabled ? '1' : '0';
        if (!function_exists('updateSetting')) {
            require_once __DIR__ . '/functions.php';
        }
        updateSetting('open_registration', $value);

        ActivityLogger::log('admin.registration_toggle_changed', [
            'new_state' => $enabled ? 'enabled' : 'disabled',
            'admin_user_id' => $adminUserId,
        ]);

        return $value;
    }

    /**
     * Build the canonical registration URL. Prefers APP_URL; falls back to
     * a relative path (logged) so a misconfigured environment shows an
     * obviously-broken URL rather than a host-spoofed one.
     *
     * The "/public/" prefix is included only when running outside the
     * production environment, since production deploys serve the app from
     * the document root and the "/public/" segment is dropped by the web
     * server config.
     */
    public static function buildRegistrationUrl(): string {
        $base = defined('APP_URL') ? rtrim((string) APP_URL, '/') : '';
        $isProduction = class_exists('EnvLoader') ? EnvLoader::isProduction() : false;
        $path = $isProduction ? '/coaches/register.php' : '/public/coaches/register.php';

        if ($base === '') {
            if (class_exists('Logger')) {
                Logger::warn('APP_URL not configured; registration URL will be path-only.');
            }
            return $path;
        }
        return $base . $path;
    }
}
?>
