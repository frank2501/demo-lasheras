<?php
/**
 * WP-CLI commands for Artechia PMS.
 */

namespace Artechia\PMS;

use Artechia\PMS\DB\Schema;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CLI {

    /**
     * Register WP-CLI commands.
     */
    public static function register(): void {
        if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
            return;
        }

        \WP_CLI::add_command( 'artechia backup', [ __CLASS__, 'backup' ] );
    }

    /**
     * Export critical PMS tables as a JSON backup.
     *
     * ## EXAMPLES
     *
     *     wp artechia backup
     *
     * @param array $args       Positional arguments.
     * @param array $assoc_args Named arguments.
     */
    public static function backup( array $args, array $assoc_args ): void {
        global $wpdb;

        $tables = [ 'bookings', 'payments', 'coupons', 'coupon_redemptions', 'guests' ];
        $data   = [];

        foreach ( $tables as $name ) {
            $table         = Schema::table( $name );
            $rows          = $wpdb->get_results( "SELECT * FROM {$table}", \ARRAY_A ); // phpcs:ignore
            $data[ $name ] = $rows;

            \WP_CLI::log( sprintf( '  %s: %d rows', $name, count( $rows ) ) );
        }

        // Write to backup directory.
        $dir = WP_CONTENT_DIR . '/artechia-backups';
        if ( ! is_dir( $dir ) ) {
            wp_mkdir_p( $dir );
        }

        // Add .htaccess protection.
        $htaccess = $dir . '/.htaccess';
        if ( ! file_exists( $htaccess ) ) {
            file_put_contents( $htaccess, "Deny from all\n" ); // phpcs:ignore
        }

        $filename = sprintf( 'backup-%s.json', gmdate( 'Y-m-d_His' ) );
        $filepath = $dir . '/' . $filename;

        $bytes = file_put_contents( $filepath, wp_json_encode( $data, JSON_PRETTY_PRINT ) ); // phpcs:ignore

        if ( $bytes === false ) {
            \WP_CLI::error( "Failed to write backup to {$filepath}" );
            return;
        }

        $size_kb = round( $bytes / 1024, 1 );
        \WP_CLI::success( "Backup saved: {$filepath} ({$size_kb} KB)" );
    }
}
