<?php
/**
 * District 8 Travel League - Activity Logger
 *
 * Single audit-trail insertion point for all application events.
 * Must be called from service classes only — never from page files.
 *
 * Usage: ActivityLogger::log('auth.login_success', ['user_id' => 1, 'ip' => '127.0.0.1']);
 */

if (!defined('D8TL_APP')) {
    die('Direct access not permitted');
}

class ActivityLogger {

    /**
     * Log an application event to the activity_log table.
     *
     * Writes the canonical `event` + JSON `context` columns (migration 007) and
     * also populates legacy NOT NULL columns (`user_type`, `action`) from
     * schema.sql so strict MySQL does not reject the INSERT.
     *
     * On DB failure: logs via error_log() and returns silently — never throws.
     *
     * @param string $event    Dot-notation event name (e.g. 'auth.login_success')
     * @param array  $context  Arbitrary context data; stored as JSON in activity_log.context
     */
    public static function log(string $event, array $context = []): void {
        try {
            $db = Database::getInstance();
            $json = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $legacyAction = substr($event, 0, 100);
            $legacyUserType = self::legacyUserType($event);
            $legacyUserId = self::legacyUserId($context);
            $ip = isset($context['ip']) ? substr((string) $context['ip'], 0, 45) : null;

            $db->query(
                'INSERT INTO activity_log (
                    user_id, user_type, action, details, ip_address, user_agent,
                    event, context, created_at
                 ) VALUES (
                    :user_id, :user_type, :action, :details, :ip_address, :user_agent,
                    :event, :context, NOW()
                 )',
                [
                    'user_id'     => $legacyUserId,
                    'user_type'   => $legacyUserType,
                    'action'      => $legacyAction,
                    'details'     => null,
                    'ip_address'  => $ip,
                    'user_agent'  => null,
                    'event'       => $event,
                    'context'     => $json,
                ]
            );
        } catch (Throwable $e) {
            error_log('[ActivityLogger] Failed to write audit log entry — event=' . $event . ' error=' . $e->getMessage());
        }
    }

    /**
     * Best-effort mapping for legacy activity_log.user_type (NOT NULL).
     */
    private static function legacyUserType(string $event): string {
        if (str_starts_with($event, 'admin.')
            || str_starts_with($event, 'registration.invitation')) {
            return 'admin';
        }
        if (str_starts_with($event, 'auth.') || str_starts_with($event, 'registration.')) {
            return 'coach';
        }
        return 'public';
    }

    /**
     * Prefer explicit user identifiers from context for legacy activity_log.user_id.
     */
    private static function legacyUserId(array $context): ?int {
        foreach (['user_id', 'admin_user_id'] as $key) {
            if (!isset($context[$key])) {
                continue;
            }
            $id = (int) $context[$key];
            if ($id > 0) {
                return $id;
            }
        }
        return null;
    }
}
