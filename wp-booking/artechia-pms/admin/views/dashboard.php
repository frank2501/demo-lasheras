<?php
/**
 * Dashboard view — landing page for Artechia PMS.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Artechia\PMS\DB\Schema;
use Artechia\PMS\Services\Settings;

// Gather quick stats.
global $wpdb;
$today = current_time( 'Y-m-d' );

$total_rooms = (int) $wpdb->get_var( "SELECT COUNT(*) FROM " . Schema::table( 'room_units' ) );
$total_bookings = (int) $wpdb->get_var( "SELECT COUNT(*) FROM " . Schema::table( 'bookings' ) );
$arrivals_today = (int) $wpdb->get_var( $wpdb->prepare(
    "SELECT COUNT(*) FROM " . Schema::table( 'bookings' ) . " WHERE check_in = %s AND status IN ('confirmed')",
    $today
) );
$departures_today = (int) $wpdb->get_var( $wpdb->prepare(
    "SELECT COUNT(*) FROM " . Schema::table( 'bookings' ) . " WHERE check_out = %s AND status IN ('checked_in')",
    $today
) );
$occupied = (int) $wpdb->get_var( $wpdb->prepare(
    "SELECT COUNT(DISTINCT br.room_unit_id) FROM " . Schema::table( 'booking_rooms' ) . " br
     INNER JOIN " . Schema::table( 'bookings' ) . " b ON br.booking_id = b.id
     WHERE b.check_in <= %s AND b.check_out > %s AND b.status IN ('confirmed','checked_in') AND br.room_unit_id IS NOT NULL",
    $today, $today
) );
$occupancy_pct = $total_rooms > 0 ? round( ( $occupied / $total_rooms ) * 100 ) : 0;

// Upcoming Arrivals (Next 5)
$upcoming_arrivals = $wpdb->get_results( $wpdb->prepare(
    "SELECT b.*, g.first_name, g.last_name, g.email, ru.name AS unit_name
     FROM " . Schema::table( 'bookings' ) . " b
     LEFT JOIN " . Schema::table( 'guests' ) . " g ON b.guest_id = g.id
     LEFT JOIN " . Schema::table( 'booking_rooms' ) . " br ON br.booking_id = b.id
     LEFT JOIN " . Schema::table( 'room_units' ) . " ru ON ru.id = br.room_unit_id
     WHERE b.check_in >= %s
       AND b.status IN ('confirmed', 'paid', 'deposit_paid')
     ORDER BY b.check_in ASC
     LIMIT 5",
    $today
), ARRAY_A );
?>

<div class="wrap artechia-wrap">
    <h1><?php esc_html_e( 'Artechia PMS — Inicio', 'artechia-pms' ); ?></h1>

    <div class="artechia-cards">
        <div class="artechia-card artechia-card--primary">
            <div class="artechia-card__icon">🏨</div>
            <div class="artechia-card__body">
                <span class="artechia-card__number"><?php echo esc_html( $total_rooms ); ?></span>
                <span class="artechia-card__label"><?php esc_html_e( 'Habitaciones', 'artechia-pms' ); ?></span>
            </div>
        </div>

        <div class="artechia-card artechia-card--success">
            <div class="artechia-card__icon">📊</div>
            <div class="artechia-card__body">
                <span class="artechia-card__number"><?php echo esc_html( $occupancy_pct ); ?>%</span>
                <span class="artechia-card__label"><?php esc_html_e( 'Ocupación hoy', 'artechia-pms' ); ?></span>
            </div>
        </div>

        <div class="artechia-card artechia-card--info">
            <div class="artechia-card__icon">🛬</div>
            <div class="artechia-card__body">
                <span class="artechia-card__number"><?php echo esc_html( $arrivals_today ); ?></span>
                <span class="artechia-card__label"><?php esc_html_e( 'Llegadas hoy', 'artechia-pms' ); ?></span>
            </div>
        </div>

        <div class="artechia-card artechia-card--warning">
            <div class="artechia-card__icon">🛫</div>
            <div class="artechia-card__body">
                <span class="artechia-card__number"><?php echo esc_html( $departures_today ); ?></span>
                <span class="artechia-card__label"><?php esc_html_e( 'Salidas hoy', 'artechia-pms' ); ?></span>
            </div>
        </div>

        <div class="artechia-card">
            <div class="artechia-card__icon">📋</div>
            <div class="artechia-card__body">
                <span class="artechia-card__number"><?php echo esc_html( $total_bookings ); ?></span>
                <span class="artechia-card__label"><?php esc_html_e( 'Reservas totales', 'artechia-pms' ); ?></span>
            </div>
        </div>
    </div>

    <div class="artechia-panel">
            <h2><?php esc_html_e( 'Próximos Ingresos', 'artechia-pms' ); ?></h2>
            <?php if ( empty( $upcoming_arrivals ) ) : ?>
                <p class="description"><?php esc_html_e( 'No hay llegadas próximas.', 'artechia-pms' ); ?></p>
            <?php else : ?>
                <table class="artechia-table" style="width:100%">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Fecha', 'artechia-pms' ); ?></th>
                            <th><?php esc_html_e( 'Huésped', 'artechia-pms' ); ?></th>
                            <th><?php esc_html_e( 'Unidad', 'artechia-pms' ); ?></th>
                            <th><?php esc_html_e( 'Noches', 'artechia-pms' ); ?></th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $upcoming_arrivals as $ua ) : ?>
                            <tr>
                                <td><?php echo esc_html( date_i18n( 'j \d\e F', strtotime( $ua['check_in'] ) ) ); ?></td>
                                <td>
                                    <strong><?php echo esc_html( $ua['first_name'] . ' ' . $ua['last_name'] ); ?></strong><br>
                                    <span class="description"><?php echo esc_html( $ua['booking_code'] ); ?></span>
                                </td>
                                <td><?php echo esc_html( $ua['unit_name'] ?? '—' ); ?></td>
                                <td><?php echo esc_html( $ua['nights'] ); ?></td>
                                <td><a href="<?php echo esc_url( admin_url( 'admin.php?page=artechia-reservations&action=view&id=' . $ua['id'] ) ); ?>" class="button button-small">Ver</a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
    <?php endif; ?>
        </div>

    <div class="artechia-dashboard-grid">
        <div class="artechia-panel">
            <h2><?php esc_html_e( 'Accesos Rápidos', 'artechia-pms' ); ?></h2>
            <ul class="artechia-quick-links">
                <li><a href="<?php echo esc_url( admin_url( 'admin.php?page=artechia-reservations&action=new' ) ); ?>">➕ <?php esc_html_e( 'Nueva Reserva', 'artechia-pms' ); ?></a></li>
                <li><a href="<?php echo esc_url( admin_url( 'admin.php?page=artechia-calendar' ) ); ?>">📅 <?php esc_html_e( 'Calendario', 'artechia-pms' ); ?></a></li>
                <li><a href="<?php echo esc_url( admin_url( 'admin.php?page=artechia-reports' ) ); ?>">📈 <?php esc_html_e( 'Reportes', 'artechia-pms' ); ?></a></li>
            </ul>
        </div>

        <div class="artechia-panel">
            <h2><?php esc_html_e( 'Información del Plugin', 'artechia-pms' ); ?></h2>
            <table class="artechia-info-table">
                <tr>
                    <td><?php esc_html_e( 'Versión', 'artechia-pms' ); ?></td>
                    <td><strong><?php echo esc_html( ARTECHIA_PMS_VERSION ); ?></strong></td>
                </tr>
                <tr>
                    <td><?php esc_html_e( 'DB Versión', 'artechia-pms' ); ?></td>
                    <td><strong><?php echo esc_html( get_option( 'artechia_pms_db_version', '—' ) ); ?></strong></td>
                </tr>
                <tr>
                    <td><?php esc_html_e( 'Moneda', 'artechia-pms' ); ?></td>
                    <td><strong><?php echo esc_html( Settings::currency_symbol() . ' (' . Settings::currency() . ')' ); ?></strong></td>
                </tr>
                <tr>
                    <td><?php esc_html_e( 'Zona horaria', 'artechia-pms' ); ?></td>
                    <td><strong><?php echo esc_html( Settings::timezone() ); ?></strong></td>
                </tr>
                <tr>
                    <td><?php esc_html_e( 'Modo debug', 'artechia-pms' ); ?></td>
                    <td><strong><?php echo Settings::debug_mode() ? '✅ ' . esc_html__( 'Activo', 'artechia-pms' ) : '❌ ' . esc_html__( 'Inactivo', 'artechia-pms' ); ?></strong></td>
                </tr>
            </table>
        </div>
    </div>
</div>
