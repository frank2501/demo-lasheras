<?php
/**
 * Plugin deactivator.
 */

namespace Artechia\PMS;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Deactivator {

    /**
     * Deactivation callback.
     * Does NOT remove data — that's for uninstall.php.
     */
    public static function deactivate(): void {
        // Clear scheduled cron events.
        wp_clear_scheduled_hook( 'artechia_pms_cleanup_locks' );
        wp_clear_scheduled_hook( 'artechia_pms_ical_sync' );

        // Flush rewrite rules.
        flush_rewrite_rules();

        // Log deactivation (if table still exists).
        try {
            Logger::info( 'plugin.deactivated', 'Artechia PMS deactivated' );
        } catch ( \Throwable $e ) {
            // Table might not exist on a broken install.
        }
    }
}
