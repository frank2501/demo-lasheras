<?php
/**
 * Admin View: Payments
 */

use Artechia\PMS\DB\Schema;
use Artechia\PMS\Helpers\Helpers;

defined( 'ABSPATH' ) || exit;

global $wpdb;
$table = Schema::table( 'payments' );
$bookings_table = Schema::table( 'bookings' );

// ── Handle Filters ──
$status_filter = sanitize_text_field( $_GET['status'] ?? '' );
$search_filter = sanitize_text_field( $_GET['search'] ?? '' );
$date_from     = sanitize_text_field( $_GET['date_from'] ?? '' );
$date_to       = sanitize_text_field( $_GET['date_to'] ?? '' );
$paged         = max( 1, absint( $_GET['paged'] ?? 1 ) );
$per_page      = 20;
$offset        = ( $paged - 1 ) * $per_page;

$where = [ '1=1' ];
$args  = [];

if ( $status_filter ) {
    $where[] = 'p.status = %s';
    $args[]  = $status_filter;
}

if ( $search_filter ) {
    $where[] = 'b.booking_code LIKE %s';
    $args[]  = '%' . $wpdb->esc_like( $search_filter ) . '%';
}

if ( $date_from ) {
    $where[] = 'p.created_at >= %s';
    $args[]  = $date_from . ' 00:00:00';
}

if ( $date_to ) {
    $where[] = 'p.created_at <= %s';
    $args[]  = $date_to . ' 23:59:59';
}

$where_sql = implode( ' AND ', $where );

// ── Query Total Rows ──
$total_rows = $wpdb->get_var( $wpdb->prepare( 
    "SELECT COUNT(*) 
     FROM {$table} p 
     LEFT JOIN {$bookings_table} b ON p.booking_id = b.id
     WHERE {$where_sql}", 
    ...$args 
) );
$total_pages = ceil( $total_rows / $per_page );

// ── Query Data ──
$data_args = array_merge( $args, [ $per_page, $offset ] );
$payments  = $wpdb->get_results( $wpdb->prepare(
    "SELECT p.*, b.booking_code 
     FROM {$table} p 
     LEFT JOIN {$bookings_table} b ON p.booking_id = b.id
     WHERE {$where_sql} 
     ORDER BY p.created_at DESC LIMIT %d OFFSET %d",
    ...$data_args
) );

// ── Summary Stats ──
$stats = $wpdb->get_results( "
    SELECT 
        SUM(CASE WHEN DATE(created_at) = CURDATE() AND status = 'approved' AND amount > 0 THEN amount ELSE 0 END) as today_income,
        SUM(CASE WHEN DATE(created_at) = CURDATE() AND status = 'approved' AND amount < 0 THEN ABS(amount) ELSE 0 END) as today_refunds,
        SUM(CASE WHEN MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE()) AND status = 'approved' AND amount > 0 THEN amount ELSE 0 END) as month_income,
        SUM(CASE WHEN MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE()) AND status = 'approved' AND amount < 0 THEN ABS(amount) ELSE 0 END) as month_refunds,
        COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_count,
        COUNT(CASE WHEN status IN ('charged_back', 'dispute') THEN 1 END) as disputed_count
    FROM {$table}
" );
$stat = $stats[0] ?? (object) [ 'today_income' => 0, 'today_refunds' => 0, 'month_income' => 0, 'month_refunds' => 0, 'pending_count' => 0, 'disputed_count' => 0 ];

$today_net = (float) $stat->today_income - (float) $stat->today_refunds;
$month_net = (float) $stat->month_income - (float) $stat->month_refunds;

// Status translations
$status_labels = [
    'approved'     => 'Aprobado',
    'pending'      => 'Pendiente',
    'rejected'     => 'Rechazado',
    'cancelled'    => 'Cancelado',
    'refunded'     => 'Reembolsado',
    'charged_back' => 'Contracargo',
];

// Gateway translations
$gateway_labels = [ 'mercadopago' => 'Mercado Pago', 'manual' => 'Manual' ];

// Pay mode translations
$mode_labels = [ 'total' => 'Pago Total', 'deposit' => 'Seña', 'manual' => 'Manual' ];

// Export URL
$export_url = admin_url( 'admin-post.php?action=artechia_export_payments' );
?>

<div class="wrap">
    <h1 class="wp-heading-inline"><?php esc_html_e( 'Pagos', 'artechia-pms' ); ?></h1>
    <a href="<?php echo esc_url( $export_url ); ?>" class="page-title-action"><?php esc_html_e( 'Exportar CSV', 'artechia-pms' ); ?></a>
    <hr class="wp-header-end">

    <!-- Stats Cards -->
    <div style="display:grid; grid-template-columns:repeat(4, 1fr); gap:12px; margin-bottom:16px;">
        <div style="background:#fff; border:1px solid #e5e7eb; border-radius:8px; padding:14px 16px; display:flex; align-items:center; gap:12px;">
            <div style="width:40px; height:40px; border-radius:10px; background:#f0fdf4; display:flex; align-items:center; justify-content:center; font-size:20px;">💰</div>
            <div>
                <div style="font-size:20px; font-weight:700; color:#16a34a;"><?php echo esc_html( Helpers::format_price( $today_net ) ); ?></div>
                <div style="font-size:11px; color:#94a3b8; text-transform:uppercase; letter-spacing:0.5px;">Recaudado Hoy</div>
                <?php if ( (float) $stat->today_refunds > 0 ) : ?>
                    <div style="font-size:10px; color:#dc2626;">Devoluciones: <?php echo esc_html( Helpers::format_price( (float) $stat->today_refunds ) ); ?></div>
                <?php endif; ?>
            </div>
        </div>
        <div style="background:#fff; border:1px solid #e5e7eb; border-radius:8px; padding:14px 16px; display:flex; align-items:center; gap:12px;">
            <div style="width:40px; height:40px; border-radius:10px; background:#eff6ff; display:flex; align-items:center; justify-content:center; font-size:20px;">📊</div>
            <div>
                <div style="font-size:20px; font-weight:700; color:#1e293b;"><?php echo esc_html( Helpers::format_price( $month_net ) ); ?></div>
                <div style="font-size:11px; color:#94a3b8; text-transform:uppercase; letter-spacing:0.5px;">Recaudado Mes</div>
                <?php if ( (float) $stat->month_refunds > 0 ) : ?>
                    <div style="font-size:10px; color:#dc2626;">Devoluciones: <?php echo esc_html( Helpers::format_price( (float) $stat->month_refunds ) ); ?></div>
                <?php endif; ?>
            </div>
        </div>
        <div style="background:#fff; border:1px solid #e5e7eb; border-radius:8px; padding:14px 16px; display:flex; align-items:center; gap:12px;">
            <div style="width:40px; height:40px; border-radius:10px; background:<?php echo (int) $stat->pending_count > 0 ? '#fef9c3' : '#f8fafc'; ?>; display:flex; align-items:center; justify-content:center; font-size:20px;">⏳</div>
            <div>
                <div style="font-size:20px; font-weight:700; color:<?php echo (int) $stat->pending_count > 0 ? '#d97706' : '#1e293b'; ?>;"><?php echo esc_html( $stat->pending_count ); ?></div>
                <div style="font-size:11px; color:#94a3b8; text-transform:uppercase; letter-spacing:0.5px;">Pendientes</div>
            </div>
        </div>
        <div style="background:#fff; border:1px solid #e5e7eb; border-radius:8px; padding:14px 16px; display:flex; align-items:center; gap:12px;">
            <div style="width:40px; height:40px; border-radius:10px; background:<?php echo (int) $stat->disputed_count > 0 ? '#fef2f2' : '#f8fafc'; ?>; display:flex; align-items:center; justify-content:center; font-size:20px;">🚫</div>
            <div>
                <div style="font-size:20px; font-weight:700; color:<?php echo (int) $stat->disputed_count > 0 ? '#dc2626' : '#1e293b'; ?>;"><?php echo esc_html( $stat->disputed_count ); ?></div>
                <div style="font-size:11px; color:#94a3b8; text-transform:uppercase; letter-spacing:0.5px;">Disputas</div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="artechia-panel" style="margin-bottom:16px; padding:12px 16px;">
        <form method="get" style="display:flex; flex-wrap:wrap; gap:8px; align-items:center;">
            <input type="hidden" name="page" value="artechia-payments">
            <div style="position:relative; flex:1; min-width:180px; max-width:260px;">
                <span style="position:absolute; left:10px; top:50%; transform:translateY(-50%); color:#94a3b8; font-size:14px;">🔍</span>
                <input type="search" name="search" value="<?php echo esc_attr( $search_filter ); ?>" placeholder="<?php esc_attr_e( 'Buscar código de reserva...', 'artechia-pms' ); ?>" style="padding-left:32px; width:100%; height:36px; border-radius:6px;">
            </div>
            <select name="status" style="height:36px; border-radius:6px;">
                <option value=""><?php esc_html_e( 'Todos los estados', 'artechia-pms' ); ?></option>
                <?php foreach ( $status_labels as $key => $label ) : ?>
                    <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $status_filter, $key ); ?>>
                        <?php echo esc_html( $label ); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <input type="date" name="date_from" value="<?php echo esc_attr( $date_from ); ?>" style="height:36px; border-radius:6px;">
            <input type="date" name="date_to" value="<?php echo esc_attr( $date_to ); ?>" style="height:36px; border-radius:6px;">
            <button type="submit" class="button button-primary" style="height:36px;"><?php esc_html_e( 'Filtrar', 'artechia-pms' ); ?></button>
            <a href="<?php echo admin_url( 'admin.php?page=artechia-payments' ); ?>" class="button" style="height:36px; line-height:34px;"><?php esc_html_e( 'Limpiar', 'artechia-pms' ); ?></a>
        </form>
    </div>

    <!-- Results count -->
    <p style="color:#64748b; font-size:13px; margin:0 0 8px;"><?php printf( '%d pago(s) encontrado(s)', $total_rows ); ?></p>

    <!-- Table -->
    <table class="wp-list-table widefat fixed striped artechia-table">
        <thead>
            <tr>
                <th style="width:40px;">#</th>
                <th style="width:150px;"><?php esc_html_e( 'Reserva', 'artechia-pms' ); ?></th>
                <th><?php esc_html_e( 'Pasarela', 'artechia-pms' ); ?></th>
                <th style="width:120px;"><?php esc_html_e( 'Monto', 'artechia-pms' ); ?></th>
                <th style="width:100px;"><?php esc_html_e( 'Estado', 'artechia-pms' ); ?></th>
                <th style="width:130px;"><?php esc_html_e( 'Fecha', 'artechia-pms' ); ?></th>
                <th><?php esc_html_e( 'Nota', 'artechia-pms' ); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if ( ! empty( $payments ) ) : ?>
                <?php 
                $row_num = $offset;
                foreach ( $payments as $row ) :
                    $row_num++;
                    $is_refund = ( (float) $row->amount ) < 0;
                    $amount = (float) $row->amount;
                    $gateway_name = $gateway_labels[$row->gateway] ?? ucfirst( $row->gateway );
                    $mode_name = $mode_labels[$row->pay_mode] ?? ucfirst( $row->pay_mode );
                    $status_name = $status_labels[$row->status] ?? ucfirst( $row->status );

                    // Format date
                    $date_formatted = '';
                    if ( $row->created_at ) {
                        $ts = strtotime( $row->created_at );
                        $date_formatted = date( 'd/m/Y H:i', $ts );
                    }

                    // Row style
                    $row_style = '';
                    if ( $is_refund ) {
                        $row_style = 'border-left:4px solid #3b82f6; background:#eff6ff;';
                    } elseif ( $row->status === 'pending' ) {
                        $row_style = 'border-left:4px solid #f59e0b;';
                    } elseif ( in_array( $row->status, ['rejected', 'charged_back'] ) ) {
                        $row_style = 'border-left:4px solid #dc2626; background:#fef2f2;';
                    } elseif ( $row->status === 'approved' ) {
                        $row_style = 'border-left:4px solid #16a34a;';
                    }

                    // Status badge colors
                    $badge_colors = [
                        'approved'     => 'background:#dcfce7; color:#16a34a;',
                        'pending'      => 'background:#fef9c3; color:#d97706;',
                        'rejected'     => 'background:#fef2f2; color:#dc2626;',
                        'cancelled'    => 'background:#f1f5f9; color:#64748b;',
                        'refunded'     => 'background:#dbeafe; color:#2563eb;',
                        'charged_back' => 'background:#fef2f2; color:#dc2626;',
                    ];
                    $badge_style = $badge_colors[$row->status] ?? 'background:#f1f5f9; color:#64748b;';
                    ?>
                    <tr style="<?php echo esc_attr( $row_style ); ?>">
                        <td style="color:#94a3b8; font-size:12px;"><?php echo $row_num; ?></td>
                        <td>
                            <?php if ( $row->booking_code ) : ?>
                                <a href="<?php echo admin_url( 'admin.php?page=artechia-reservations&booking_code=' . $row->booking_code ); ?>" style="font-weight:600;">
                                    <?php echo esc_html( $row->booking_code ); ?>
                                </a>
                            <?php else : ?>
                                <span style="color:#94a3b8;">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div style="font-size:13px;"><?php echo esc_html( $gateway_name ); ?></div>
                            <?php if ( $row->gateway_txn_id ) : ?>
                                <div style="font-size:10px; color:#94a3b8;" title="<?php echo esc_attr( $row->gateway_txn_id ); ?>">
                                    <?php echo esc_html( substr( $row->gateway_txn_id, 0, 12 ) ); ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ( $is_refund ) : ?>
                                <div style="font-size:14px; font-weight:700; color:#2563eb;">
                                    −<?php echo esc_html( Helpers::format_price( abs( $amount ) ) ); ?>
                                </div>
                                <div style="font-size:10px; color:#2563eb; font-weight:600;">DEVOLUCIÓN</div>
                            <?php else : ?>
                                <div style="font-size:14px; font-weight:600; color:#1e293b;">
                                    <?php echo esc_html( Helpers::format_price( $amount ) ); ?>
                                </div>
                                <?php if ( $mode_name !== 'Manual' ) : ?>
                                    <div style="font-size:10px; color:#94a3b8;"><?php echo esc_html( $mode_name ); ?></div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span style="display:inline-block; padding:2px 8px; border-radius:10px; font-size:11px; font-weight:600; <?php echo esc_attr( $badge_style ); ?>">
                                <?php echo esc_html( $status_name ); ?>
                            </span>
                        </td>
                        <td style="font-size:12px; color:#64748b;">
                            <?php echo esc_html( $date_formatted ); ?>
                        </td>
                        <td style="font-size:12px; color:#64748b;">
                            <?php 
                            $note = $row->notes ?? '';
                            if ( $note ) {
                                echo esc_html( $note );
                            } elseif ( $is_refund ) {
                                echo '<em style="color:#2563eb;">Devolución por cancelación</em>';
                            } else {
                                echo '<span style="color:#ccc;">—</span>';
                            }
                            ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else : ?>
                <tr>
                    <td colspan="7" style="text-align:center; padding:20px; color:#94a3b8;">
                        <?php esc_html_e( 'No se encontraron pagos.', 'artechia-pms' ); ?>
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- Pagination -->
    <?php if ( $total_pages > 1 ) : ?>
        <div class="tablenav bottom">
            <div class="tablenav-pages">
                <?php
                echo paginate_links( [
                    'base'    => add_query_arg( 'paged', '%#%' ),
                    'format'  => '',
                    'current' => $paged,
                    'total'   => $total_pages,
                ] );
                ?>
            </div>
        </div>
    <?php endif; ?>
</div>
