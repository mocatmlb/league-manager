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
     * Inserts a row with the event name and JSON-encoded context.
     * On DB failure: logs via error_log() and returns silently — never throws.
     *
     * Convention: Call from service classes only. Page files must not call this
     * method directly; route all audit logging through the appropriate service.
     *
     * @param string $event    Dot-notation event name (e.g. 'auth.login_success')
     * @param array  $context  Arbitrary context data; stored as JSON in activity_log.context
     */
    public static function log(string $event, array $context = []): void {
        try {
            $db = Database::getInstance();
            $db->query(
                'INSERT INTO activity_log (event, context, created_at) VALUES (:event, :context, NOW())',
                [
                    'event'   => $event,
                    'context' => json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ]
            );
        } catch (Throwable $e) {
            error_log('[ActivityLogger] Failed to write audit log entry — event=' . $event . ' error=' . $e->getMessage());
        }
    }
}
