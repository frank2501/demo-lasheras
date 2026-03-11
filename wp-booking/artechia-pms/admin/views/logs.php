<?php
/**
 * Visor de logs de auditoría.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Artechia\PMS\Logger;

// Filters.
$severity    = sanitize_text_field( $_GET['severity'] ?? '' );
$event_type  = sanitize_text_field( $_GET['event_type'] ?? '' );
$entity_type = sanitize_text_field( $_GET['entity_type'] ?? '' );
$search      = sanitize_text_field( $_GET['search'] ?? '' );
$date_from   = sanitize_text_field( $_GET['date_from'] ?? '' );
$date_to     = sanitize_text_field( $_GET['date_to'] ?? '' );
$page_num    = max( 1, absint( $_GET['paged'] ?? 1 ) );
$per_page    = 30;

$query_args = [
    'limit'  => $per_page,
    'offset' => ( $page_num - 1 ) * $per_page,
];
if ( $severity )    $query_args['severity']    = $severity;
if ( $event_type )  $query_args['event_type']  = $event_type;
if ( $entity_type ) $query_args['entity_type'] = $entity_type;
if ( $search )      $query_args['search']      = $search;
if ( $date_from )   $query_args['date_from']   = $date_from . ' 00:00:00';
if ( $date_to )     $query_args['date_to']     = $date_to . ' 23:59:59';

$logs  = Logger::query( $query_args );
$total = Logger::count( $query_args );
$pages = ceil( $total / $per_page );

$severity_colors = [
    'info'     => '#3b82f6',
    'warning'  => '#f59e0b',
    'error'    => '#ef4444',
    'critical' => '#dc2626',
];

$sev_labels = [
    'info'     => 'Información',
    'warning'  => 'Advertencia',
    'error'    => 'Error',
    'critical' => 'Crítico',
];

// Grouped event types for dropdown
$event_groups = [
    'Email' => [
        'email.sent'           => 'Email enviado',
        'email.failed'         => 'Error al enviar email',
        'email.queued'         => 'Email en cola',
        'email.no_booking'     => 'Reserva no encontrada para email',
        'email.no_template'    => 'Sin plantilla de email',
        'email.no_guest_email' => 'Sin email del huésped',
    ],
    'Reservas' => [
        'booking.created'    => 'Reserva creada',
        'booking.confirmed'  => 'Reserva confirmada',
        'booking.cancelled'  => 'Reserva cancelada',
        'booking.checked_in' => 'Check-in realizado',
        'booking.checked_out'=> 'Check-out realizado',
        'booking.updated'    => 'Reserva actualizada',
        'booking.moved'      => 'Reserva movida',
        'booking.resized'    => 'Reserva redimensionada',
    ],
    'Mercado Pago' => [
        'mp.preference_created'      => 'Preferencia MP creada',
        'mp.preference_failed'       => 'Error creando preferencia MP',
        'mp.webhook_processed'       => 'Webhook MP procesado',
        'mp.webhook_booking_missing' => 'Reserva no encontrada (webhook)',
        'mp.booking_confirmed'       => 'Reserva confirmada vía MP',
        'mp.payment_process_failed'  => 'Error procesando pago',
    ],
    'iCal' => [
        'ical.created'    => 'Reserva iCal creada',
        'ical.update'     => 'Reserva iCal actualizada',
        'ical.remove'     => 'Reserva iCal cancelada',
        'ical.conflict'   => 'Conflicto iCal',
        'ical.sync_lock'  => 'Sincronización iCal bloqueada',
        'ical.empty_feed' => 'Feed iCal vacío',
    ],
    'Marketing' => [
        'marketing.email_sent'    => 'Email marketing enviado',
        'marketing.campaign_sent' => 'Campaña enviada',
    ],
    'Sistema' => [
        'plugin.activated'   => 'Plugin activado',
        'plugin.deactivated' => 'Plugin desactivado',
        'portal.get_booking_request' => 'Consulta al portal',
        'portal.token_mismatch'      => 'Token inválido en portal',
    ],
];

// Flat event labels for table display
$event_labels = [];
foreach ( $event_groups as $group => $items ) {
    foreach ( $items as $key => $label ) {
        $event_labels[ $key ] = $label;
    }
}

// Translation map for entity_type codes
$entity_labels = [
    'booking'   => 'Reserva',
    'guest'     => 'Huésped',
    'payment'   => 'Pago',
    'room'      => 'Habitación',
    'room_unit' => 'Unidad',
    'rate_plan' => 'Plan tarifario',
    'property'  => 'Propiedad',
    'ical'      => 'iCal',
    'coupon'    => 'Cupón',
    'email'     => 'Email',
];

$has_filters = $severity || $event_type || $entity_type || $search || $date_from || $date_to;
?>

<div class="wrap artechia-wrap">
    <h1><?php esc_html_e( 'Registro de Auditoría', 'artechia-pms' ); ?></h1>

    <!-- Filters -->
    <div class="artechia-filters" style="margin-bottom: 15px;">
        <form method="get" class="artechia-filter-form" style="display:flex; flex-wrap:wrap; gap:8px; align-items:flex-end;">
            <input type="hidden" name="page" value="artechia-logs">

            <div>
                <label style="display:block; font-size:12px; font-weight:600; margin-bottom:3px;"><?php esc_html_e( 'Severidad', 'artechia-pms' ); ?></label>
                <select name="severity">
                    <option value=""><?php esc_html_e( 'Todas', 'artechia-pms' ); ?></option>
                    <option value="info" <?php selected( $severity, 'info' ); ?>><?php esc_html_e( 'Información', 'artechia-pms' ); ?></option>
                    <option value="warning" <?php selected( $severity, 'warning' ); ?>><?php esc_html_e( 'Advertencia', 'artechia-pms' ); ?></option>
                    <option value="error" <?php selected( $severity, 'error' ); ?>><?php esc_html_e( 'Error', 'artechia-pms' ); ?></option>
                    <option value="critical" <?php selected( $severity, 'critical' ); ?>><?php esc_html_e( 'Crítico', 'artechia-pms' ); ?></option>
                </select>
            </div>

            <div>
                <label style="display:block; font-size:12px; font-weight:600; margin-bottom:3px;"><?php esc_html_e( 'Tipo de Evento', 'artechia-pms' ); ?></label>
                <select name="event_type">
                    <option value=""><?php esc_html_e( 'Todos los eventos', 'artechia-pms' ); ?></option>
                    <?php foreach ( $event_groups as $group_name => $items ) : ?>
                        <optgroup label="<?php echo esc_attr( $group_name ); ?>">
                            <?php foreach ( $items as $key => $label ) : ?>
                                <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $event_type, $key ); ?>><?php echo esc_html( $label ); ?></option>
                            <?php endforeach; ?>
                        </optgroup>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label style="display:block; font-size:12px; font-weight:600; margin-bottom:3px;"><?php esc_html_e( 'Entidad', 'artechia-pms' ); ?></label>
                <select name="entity_type">
                    <option value=""><?php esc_html_e( 'Todas', 'artechia-pms' ); ?></option>
                    <?php foreach ( $entity_labels as $key => $label ) : ?>
                        <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $entity_type, $key ); ?>><?php echo esc_html( $label ); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label style="display:block; font-size:12px; font-weight:600; margin-bottom:3px;"><?php esc_html_e( 'Buscar en mensaje', 'artechia-pms' ); ?></label>
                <input type="text" name="search" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Ej: booking, error...', 'artechia-pms' ); ?>" style="min-width:180px;">
            </div>

            <div>
                <label style="display:block; font-size:12px; font-weight:600; margin-bottom:3px;"><?php esc_html_e( 'Desde', 'artechia-pms' ); ?></label>
                <input type="date" name="date_from" value="<?php echo esc_attr( $date_from ); ?>">
            </div>

            <div>
                <label style="display:block; font-size:12px; font-weight:600; margin-bottom:3px;"><?php esc_html_e( 'Hasta', 'artechia-pms' ); ?></label>
                <input type="date" name="date_to" value="<?php echo esc_attr( $date_to ); ?>">
            </div>

            <div style="display:flex; gap:6px; align-items:center;">
                <?php submit_button( __( 'Filtrar', 'artechia-pms' ), 'primary', 'filter', false ); ?>
                <?php if ( $has_filters ) : ?>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=artechia-logs' ) ); ?>" class="button"><?php esc_html_e( 'Limpiar', 'artechia-pms' ); ?></a>
                <?php endif; ?>
            </div>
        </form>

        <div style="margin-top:8px;">
            <span class="artechia-filter-count" style="color:#666;">
                <?php printf( esc_html__( '%d resultados', 'artechia-pms' ), $total ); ?>
                <?php if ( $has_filters ) : ?>
                    <span style="color:#999;"> — <?php esc_html_e( 'filtrados', 'artechia-pms' ); ?></span>
                <?php endif; ?>
            </span>
        </div>
    </div>

    <!-- Log Table -->
    <table class="wp-list-table widefat fixed striped artechia-table">
        <thead>
            <tr>
                <th style="width:150px;"><?php esc_html_e( 'Fecha', 'artechia-pms' ); ?></th>
                <th style="width:100px;"><?php esc_html_e( 'Severidad', 'artechia-pms' ); ?></th>
                <th style="width:200px;"><?php esc_html_e( 'Tipo de Evento', 'artechia-pms' ); ?></th>
                <th><?php esc_html_e( 'Mensaje', 'artechia-pms' ); ?></th>
                <th style="width:120px;"><?php esc_html_e( 'Entidad', 'artechia-pms' ); ?></th>
                <th style="width:100px;"><?php esc_html_e( 'Usuario', 'artechia-pms' ); ?></th>
                <th style="width:120px;"><?php esc_html_e( 'IP', 'artechia-pms' ); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if ( empty( $logs ) ) : ?>
                <tr>
                    <td colspan="7"><?php esc_html_e( 'No hay registros.', 'artechia-pms' ); ?></td>
                </tr>
            <?php else : ?>
                <?php foreach ( $logs as $log ) : ?>
                    <tr>
                        <td>
                            <?php
                            $ts = strtotime( $log['created_at'] );
                            echo esc_html( date_i18n( 'j M Y, H:i', $ts ) );
                            ?>
                        </td>
                        <td>
                            <span class="artechia-badge" style="background:<?php echo esc_attr( $severity_colors[ $log['severity'] ] ?? '#888' ); ?>;">
                                <?php echo esc_html( $sev_labels[ $log['severity'] ] ?? strtoupper( $log['severity'] ) ); ?>
                            </span>
                        </td>
                        <td>
                            <?php
                            $evt = $log['event_type'];
                            $evt_label = $event_labels[ $evt ] ?? $evt;
                            ?>
                            <span title="<?php echo esc_attr( $evt ); ?>"><?php echo esc_html( $evt_label ); ?></span>
                        </td>
                        <td><?php echo esc_html( $log['message'] ); ?></td>
                        <td>
                            <?php if ( $log['entity_type'] ) : ?>
                                <?php
                                $ent_label = $entity_labels[ $log['entity_type'] ] ?? ucfirst( $log['entity_type'] );
                                ?>
                                <span><?php echo esc_html( $ent_label ); ?></span>
                                <?php if ( $log['entity_id'] ) : ?>
                                    <small>#<?php echo esc_html( $log['entity_id'] ); ?></small>
                                <?php endif; ?>
                            <?php else : ?>
                                <span style="color:#999;">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                            if ( $log['user_id'] ) {
                                $user = get_userdata( (int) $log['user_id'] );
                                echo esc_html( $user ? $user->display_name : '#' . $log['user_id'] );
                            } else {
                                echo '<em>Sistema</em>';
                            }
                            ?>
                        </td>
                        <td><small><?php echo esc_html( $log['ip_address'] ); ?></small></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- Pagination -->
    <?php if ( $pages > 1 ) : ?>
        <div class="tablenav bottom">
            <div class="tablenav-pages">
                <?php
                echo paginate_links( [ // phpcs:ignore WordPress.Security.EscapeOutput
                    'base'      => add_query_arg( 'paged', '%#%' ),
                    'format'    => '',
                    'current'   => $page_num,
                    'total'     => $pages,
                    'prev_text' => '&laquo;',
                    'next_text' => '&raquo;',
                ] );
                ?>
            </div>
        </div>
    <?php endif; ?>
</div>
