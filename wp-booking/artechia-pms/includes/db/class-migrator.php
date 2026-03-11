<?php
/**
 * Database migrator: runs dbDelta and tracks schema versions.
 */

namespace Artechia\PMS\DB;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Migrator {

    private const VERSION_OPTION = 'artechia_pms_db_version';

    /**
     * Run migrations if the stored version differs from the code version.
     */
    public static function maybe_migrate(): void {
        $current = get_option( self::VERSION_OPTION, '0' );

        if ( version_compare( $current, ARTECHIA_PMS_DB_VERSION, '>=' ) ) {
            return;
        }

        self::run();
        update_option( self::VERSION_OPTION, ARTECHIA_PMS_DB_VERSION );
    }

    /**
     * Execute dbDelta with the full schema.
     */
    public static function run(): void {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( Schema::get_tables() );
        self::run_data_migrations();
    }

    /**
     * Run data backfills / updates.
     */
    private static function run_data_migrations(): void {
        global $wpdb;
        $bookings = Schema::table( 'bookings' );

        // H5: Backfill balance_due and payment_status.
        // We run this on every update just to be safe, or we could condition it.
        // For simplicity and robustness (consistency), we recalc financial status for all bookings.
        $wpdb->query( "
            UPDATE {$bookings}
            SET 
                balance_due = GREATEST(0, grand_total - amount_paid),
                payment_status = CASE 
                    WHEN amount_paid >= (grand_total - 0.01) THEN 'paid'
                    WHEN amount_paid > 0 THEN 'deposit_paid'
                    ELSE 'unpaid'
                END
        " );

        // H13: Seed booking_review email template.
        $email_templates = Schema::table( 'email_templates' );
        $review_exists   = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$email_templates} WHERE event_type = %s AND property_id IS NULL",
            'booking_review'
        ) );

        if ( ! $review_exists ) {
            $wpdb->insert( $email_templates, [
                'property_id' => null,
                'event_type'  => 'booking_review',
                'subject'     => '¿Qué te pareció tu estancia en {property_name}?',
                'body_html'   => '<p>Hola {guest_name},</p>
<p>¡Gracias por elegirnos! Nos gustaría saber qué te pareció tu estadía en <strong>{property_name}</strong>.</p>
<p>Tu opinión nos ayuda a mejorar y a que otros viajeros nos conozcan.</p>
<p>Podés ver los detalles de tu reserva aquí: <a href="{my_booking_url}">Gestionar mi reserva</a></p>
<p>¡Esperamos verte pronto!</p>
<p>Saludos,<br>{property_name}</p>',
                'is_active'   => 1,
            ], [ '%d', '%s', '%s', '%s', '%d' ] );
        }
    }

    /**
     * Drop all plugin tables. Used on uninstall.
     */
    public static function drop_all(): void {
        global $wpdb;

        // phpcs:disable WordPress.DB.DirectDatabaseQuery
        foreach ( Schema::table_names() as $name ) {
            $table = Schema::table( $name );
            $wpdb->query( "DROP TABLE IF EXISTS `{$table}`" ); // phpcs:ignore WordPress.DB.PreparedSQL
        }
        // phpcs:enable

        delete_option( self::VERSION_OPTION );
    }
}
