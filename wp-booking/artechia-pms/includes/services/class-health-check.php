<?php
/**
 * Health Check service — returns non-sensitive system diagnostics.
 */

namespace Artechia\PMS\Services;

use Artechia\PMS\DB\Schema;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class HealthCheck {

    /**
     * Public health ping — only { ok, timestamp }.
     */
    public function run_public(): array {
        return [
            'ok'        => true,
            'timestamp' => gmdate( 'c' ),
        ];
    }

    /**
     * Admin health check — full diagnostics.
     */
    public function run_admin(): array {
        return [
            'status'         => 'ok',
            'plugin_version' => ARTECHIA_PMS_VERSION,
            'db_version'     => ARTECHIA_PMS_DB_VERSION,
            'db_connection'  => $this->check_db(),
            'cron_running'   => $this->check_cron(),
            'last_ical_sync' => $this->last_ical_sync(),
            'last_webhook'   => $this->last_webhook(),
            'disk_space'     => $this->check_disk(),
            'timestamp'      => gmdate( 'c' ),
        ];
    }

    /**
     * Verify database connectivity.
     */
    private function check_db(): string {
        global $wpdb;
        $result = $wpdb->get_var( 'SELECT 1' );
        return ( $result == 1 ) ? 'ok' : 'fail';
    }

    /**
     * Check if WP cron events are scheduled.
     */
    private function check_cron(): array {
        return [
            'stale_bookings' => (bool) wp_next_scheduled( 'artechia_cancel_stale_pending' ),
            'ical_sync'      => (bool) wp_next_scheduled( 'artechia_ical_sync' ),
            'log_cleanup'    => (bool) wp_next_scheduled( 'artechia_log_cleanup' ),
        ];
    }

    /**
     * Get the timestamp of the last iCal sync.
     */
    private function last_ical_sync(): ?string {
        global $wpdb;
        $table = Schema::table( 'audit_log' );
        return $wpdb->get_var(
            "SELECT created_at FROM {$table} WHERE event_type = 'ical.sync_complete' ORDER BY id DESC LIMIT 1"
        ); // phpcs:ignore
    }

    /**
     * Get the timestamp of the last payment webhook.
     */
    private function last_webhook(): ?string {
        global $wpdb;
        $table = Schema::table( 'audit_log' );
        return $wpdb->get_var(
            "SELECT created_at FROM {$table} WHERE event_type LIKE 'mp.webhook%' ORDER BY id DESC LIMIT 1"
        ); // phpcs:ignore
    }

    /**
     * Check available disk space.
     */
    private function check_disk(): array {
        $free = disk_free_space( ABSPATH );
        $mb   = $free !== false ? round( $free / 1048576 ) : null;

        return [
            'free_mb' => $mb,
            'warning' => ( $mb !== null && $mb < 100 ),
        ];
    }
}
