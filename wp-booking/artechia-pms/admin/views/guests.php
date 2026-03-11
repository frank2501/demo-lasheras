<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
use Artechia\PMS\Repositories\GuestRepository;
use Artechia\PMS\Logger;

$repo = new GuestRepository();
$action = sanitize_text_field( $_GET['action'] ?? 'list' );
$id = absint( $_GET['id'] ?? 0 );
$search = sanitize_text_field( $_GET['s'] ?? '' );

if ( isset( $_POST['artechia_save_guest'] ) && check_admin_referer( 'artechia_save_guest' ) ) {
    $data = [
        'first_name' => sanitize_text_field( wp_unslash( $_POST['first_name'] ?? '' ) ),
        'last_name' => sanitize_text_field( wp_unslash( $_POST['last_name'] ?? '' ) ),
        'email' => sanitize_email( wp_unslash( $_POST['email'] ?? '' ) ),
        'phone' => sanitize_text_field( wp_unslash( $_POST['phone'] ?? '' ) ),
        'document_type' => sanitize_text_field( $_POST['document_type'] ?? '' ),
        'document_number' => sanitize_text_field( wp_unslash( $_POST['document_number'] ?? '' ) ),
        'notes' => sanitize_textarea_field( wp_unslash( $_POST['notes'] ?? '' ) ),
        'is_blacklisted' => ! empty( $_POST['is_blacklisted'] ) ? 1 : 0,
        'blacklist_reason' => sanitize_textarea_field( wp_unslash( $_POST['blacklist_reason'] ?? '' ) ),
    ];
    if ( $id ) {
        $repo->update( $id, $data );
        Logger::info( 'guest.updated', "Guest #{$id} updated", 'guest', $id );
        echo '<div class="notice notice-success"><p>' . esc_html__( 'Huésped actualizado.', 'artechia-pms' ) . '</p></div>';
    } else {
        $id = $repo->create( $data );
        if ( $id ) {
            Logger::info( 'guest.created', "Guest #{$id} created", 'guest', $id );
            echo '<div class="notice notice-success"><p>' . esc_html__( 'Huésped creado.', 'artechia-pms' ) . '</p></div>';
            $action = 'edit';
        }
    }
}
if ( $action === 'delete' && $id && check_admin_referer( 'artechia_delete_guest_' . $id ) ) {
    $repo->delete( $id ); $action = 'list'; $id = 0;
}
$item = ( $action === 'edit' ) && $id ? $repo->find( $id ) : null;
$history = $item ? $repo->booking_history( $id ) : [];
?>
<div class="wrap artechia-wrap">
<?php if ( $action === 'edit' || $action === 'new' ) : ?>
<h1><?php echo $id ? esc_html__( 'Editar Huésped', 'artechia-pms' ) : esc_html__( 'Nuevo Huésped', 'artechia-pms' ); ?></h1>
<div class="artechia-panel"><form method="post" class="artechia-form"><?php wp_nonce_field( 'artechia_save_guest' ); ?>
<table class="form-table">
<tr><th><label for="first_name"><?php esc_html_e( 'Nombre', 'artechia-pms' ); ?></label></th>
<td><input type="text" id="first_name" name="first_name" value="<?php echo esc_attr( $item['first_name'] ?? '' ); ?>" class="regular-text" required></td></tr>
<tr><th><label for="last_name"><?php esc_html_e( 'Apellido', 'artechia-pms' ); ?></label></th>
<td><input type="text" id="last_name" name="last_name" value="<?php echo esc_attr( $item['last_name'] ?? '' ); ?>" class="regular-text" required></td></tr>
<tr><th><label for="email"><?php esc_html_e( 'Email', 'artechia-pms' ); ?></label></th>
<td><input type="email" id="email" name="email" value="<?php echo esc_attr( $item['email'] ?? '' ); ?>" class="regular-text"></td></tr>
<tr><th><label for="phone"><?php esc_html_e( 'Teléfono', 'artechia-pms' ); ?></label></th>
<td><input type="text" id="phone" name="phone" value="<?php echo esc_attr( $item['phone'] ?? '' ); ?>" class="regular-text"></td></tr>
<tr><th><label for="document_type"><?php esc_html_e( 'Tipo documento', 'artechia-pms' ); ?></label></th>
<td><select id="document_type" name="document_type">
<option value=""><?php esc_html_e( '—', 'artechia-pms' ); ?></option>
<option value="dni" <?php selected( strtolower( $item['document_type'] ?? '' ), 'dni' ); ?>>DNI</option>
<option value="passport" <?php selected( strtolower( $item['document_type'] ?? '' ), 'passport' ); ?>><?php esc_html_e( 'Pasaporte', 'artechia-pms' ); ?></option>
<option value="cuit" <?php selected( strtolower( $item['document_type'] ?? '' ), 'cuit' ); ?>>CUIT</option>
</select></td></tr>
<tr><th><label for="document_number"><?php esc_html_e( 'Nro documento', 'artechia-pms' ); ?></label></th>
<td><input type="text" id="document_number" name="document_number" value="<?php echo esc_attr( $item['document_number'] ?? '' ); ?>" class="regular-text"></td></tr>

<tr><th><label for="notes"><?php esc_html_e( 'Notas', 'artechia-pms' ); ?></label></th>
<td><textarea id="notes" name="notes" class="large-text" rows="3"><?php echo esc_textarea( $item['notes'] ?? '' ); ?></textarea></td></tr>
                <tr style="background:#fffafa;"><th><label for="is_blacklisted"><?php esc_html_e( 'Lista Negra', 'artechia-pms' ); ?></label></th>
<td><label><input type="checkbox" id="is_blacklisted" name="is_blacklisted" value="1" <?php checked( $item['is_blacklisted'] ?? 0, 1 ); ?>> <?php esc_html_e( 'Bloquear este huésped', 'artechia-pms' ); ?></label></td></tr>
<tr style="background:#fffafa;"><th><label for="blacklist_reason"><?php esc_html_e( 'Razón de Bloqueo', 'artechia-pms' ); ?></label></th>
<td><textarea id="blacklist_reason" name="blacklist_reason" class="large-text" rows="2"><?php echo esc_textarea( $item['blacklist_reason'] ?? '' ); ?></textarea>
<p class="description"><?php esc_html_e( 'Si se marca, el huésped no podrá realizar nuevas reservas.', 'artechia-pms' ); ?></p></td></tr>
</table>
<?php submit_button( $id ? __( 'Actualizar', 'artechia-pms' ) : __( 'Crear', 'artechia-pms' ), 'primary', 'artechia_save_guest' ); ?>
</form></div>
<?php if ( ! empty( $history ) ) : ?>
<div class="artechia-panel"><h2><?php esc_html_e( 'Historial de Reservas', 'artechia-pms' ); ?></h2>
<table class="wp-list-table widefat fixed striped"><thead><tr>
<th><?php esc_html_e( 'Código', 'artechia-pms' ); ?></th><th><?php esc_html_e( 'Check-in', 'artechia-pms' ); ?></th>
<th><?php esc_html_e( 'Check-out', 'artechia-pms' ); ?></th><th><?php esc_html_e( 'Estado', 'artechia-pms' ); ?></th>
<th><?php esc_html_e( 'Total', 'artechia-pms' ); ?></th></tr></thead><tbody>
<?php foreach ( $history as $b ) : ?><tr>
<td><a href="<?php echo esc_url( admin_url( 'admin.php?page=artechia-reservations&action=edit&id=' . $b['id'] ) ); ?>"><?php echo esc_html( $b['booking_code'] ); ?></a></td>
<td><?php echo esc_html( \Artechia\PMS\Helpers\Helpers::format_date( $b['check_in'] ) ); ?></td><td><?php echo esc_html( \Artechia\PMS\Helpers\Helpers::format_date( $b['check_out'] ) ); ?></td>
<td><span class="artechia-badge artechia-badge--<?php echo esc_attr( $b['status'] ); ?>"><?php 
                                    $s_map = [
                                        'pending' => 'Pendiente',
                                        'confirmed' => 'Confirmada',
                                        'hold' => 'HOLD',
                                        'cancelled' => 'Cancelada',
                                        'checked_in' => 'Check-in',
                                        'checked_out' => 'Check-out',
                                        'completed' => 'Completada',
                                        'noshow' => 'No Show'
                                    ];
                                    echo esc_html( $s_map[ $b['status'] ] ?? $b['status'] ); 
                                ?></span></td>
<td><?php echo esc_html( \Artechia\PMS\Helpers\Helpers::format_price( (float) $b['grand_total'] ) ); ?></td>
</tr><?php endforeach; ?></tbody></table></div>
<?php endif; ?>
<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=artechia-guests' ) ); ?>">&larr; <?php esc_html_e( 'Volver', 'artechia-pms' ); ?></a></p>
<?php else : ?>
<h1><?php esc_html_e( 'Huéspedes', 'artechia-pms' ); ?>
<a href="<?php echo esc_url( admin_url( 'admin.php?page=artechia-guests&action=new' ) ); ?>" class="page-title-action"><?php esc_html_e( 'Agregar', 'artechia-pms' ); ?></a></h1>

<?php
// Fetch data first so we can compute summary stats
$items = $search
    ? $repo->search_with_stats( $search, 50 )
    : $repo->get_list_with_stats( [ 'orderby' => 'last_name', 'order' => 'ASC', 'limit' => 50 ] );

$total_guests = count( $items );
$with_bookings = 0;
$total_revenue = 0;
$blocked_count = 0;
foreach ( $items as $g ) {
    if ( (int) ($g['booking_count'] ?? 0) > 0 ) $with_bookings++;
    $total_revenue += (float) ($g['total_revenue'] ?? 0);
    if ( ! empty( $g['is_blacklisted'] ) ) $blocked_count++;
}
?>

<!-- Summary Cards -->
<div style="display:grid; grid-template-columns:repeat(4, 1fr); gap:12px; margin-bottom:16px;">
    <div style="background:#fff; border:1px solid #e5e7eb; border-radius:8px; padding:14px 16px; display:flex; align-items:center; gap:12px;">
        <div style="width:40px; height:40px; border-radius:10px; background:#eff6ff; display:flex; align-items:center; justify-content:center; font-size:20px;">👤</div>
        <div>
            <div style="font-size:22px; font-weight:700; color:#1e293b;"><?php echo $total_guests; ?></div>
            <div style="font-size:11px; color:#94a3b8; text-transform:uppercase; letter-spacing:0.5px;">Huéspedes</div>
        </div>
    </div>
    <div style="background:#fff; border:1px solid #e5e7eb; border-radius:8px; padding:14px 16px; display:flex; align-items:center; gap:12px;">
        <div style="width:40px; height:40px; border-radius:10px; background:#f0fdf4; display:flex; align-items:center; justify-content:center; font-size:20px;">📅</div>
        <div>
            <div style="font-size:22px; font-weight:700; color:#1e293b;"><?php echo $with_bookings; ?></div>
            <div style="font-size:11px; color:#94a3b8; text-transform:uppercase; letter-spacing:0.5px;">Con reservas</div>
        </div>
    </div>
    <div style="background:#fff; border:1px solid #e5e7eb; border-radius:8px; padding:14px 16px; display:flex; align-items:center; gap:12px;">
        <div style="width:40px; height:40px; border-radius:10px; background:#f0fdf4; display:flex; align-items:center; justify-content:center; font-size:20px;">💰</div>
        <div>
            <div style="font-size:22px; font-weight:700; color:#16a34a;"><?php echo esc_html( \Artechia\PMS\Helpers\Helpers::format_price( $total_revenue ) ); ?></div>
            <div style="font-size:11px; color:#94a3b8; text-transform:uppercase; letter-spacing:0.5px;">Ingresos totales</div>
        </div>
    </div>
    <div style="background:#fff; border:1px solid #e5e7eb; border-radius:8px; padding:14px 16px; display:flex; align-items:center; gap:12px;">
        <div style="width:40px; height:40px; border-radius:10px; background:<?php echo $blocked_count > 0 ? '#fef2f2' : '#f8fafc'; ?>; display:flex; align-items:center; justify-content:center; font-size:20px;">🚫</div>
        <div>
            <div style="font-size:22px; font-weight:700; color:<?php echo $blocked_count > 0 ? '#dc2626' : '#1e293b'; ?>;"><?php echo $blocked_count; ?></div>
            <div style="font-size:11px; color:#94a3b8; text-transform:uppercase; letter-spacing:0.5px;">Bloqueados</div>
        </div>
    </div>
</div>

<!-- Search Bar -->
<div class="artechia-panel" style="margin-bottom:0;">
    <form method="get" class="artechia-filter-form" style="display:flex; align-items:center; gap:8px;">
        <input type="hidden" name="page" value="artechia-guests">
        <div style="position:relative; flex:1; max-width:320px;">
            <span style="position:absolute; left:10px; top:50%; transform:translateY(-50%); color:#94a3b8; font-size:14px;">🔍</span>
            <input type="text" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Buscar por nombre, email, teléfono o documento...', 'artechia-pms' ); ?>" style="padding-left:32px; width:100%; height:36px; border-radius:6px;">
        </div>
        <button type="submit" class="button" style="height:36px;"><?php esc_html_e( 'Buscar', 'artechia-pms' ); ?></button>
        <?php if ( $search ) : ?>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=artechia-guests' ) ); ?>" class="button" style="height:36px; line-height:34px;"><?php esc_html_e( 'Limpiar', 'artechia-pms' ); ?></a>
        <?php endif; ?>
    </form>
</div>

<?php if ( empty( $items ) ) : ?>
<div class="artechia-panel artechia-empty"><div class="artechia-empty__icon">👤</div>
<div class="artechia-empty__title"><?php esc_html_e( 'No hay huéspedes', 'artechia-pms' ); ?></div></div>
<?php else : ?>
<table class="wp-list-table widefat fixed striped artechia-table">
<thead><tr>
<th style="width:22%;"><?php esc_html_e( 'Huésped', 'artechia-pms' ); ?></th>
<th><?php esc_html_e( 'Contacto', 'artechia-pms' ); ?></th>
<th style="width:12%;"><?php esc_html_e( 'Documento', 'artechia-pms' ); ?></th>
<th style="width:8%; text-align:center;"><?php esc_html_e( 'Reservas', 'artechia-pms' ); ?></th>
<th style="width:12%;"><?php esc_html_e( 'Última estadía', 'artechia-pms' ); ?></th>
<th style="width:12%; text-align:right;"><?php esc_html_e( 'Ingresos', 'artechia-pms' ); ?></th>
</tr></thead><tbody>
<?php 
$colors = ['#3b82f6','#10b981','#f59e0b','#ef4444','#8b5cf6','#ec4899','#14b8a6','#f97316'];
foreach ( $items as $i => $g ) : 
    $is_blocked = ! empty( $g['is_blacklisted'] );
    $initials = strtoupper( mb_substr( $g['first_name'] ?? '', 0, 1 ) . mb_substr( $g['last_name'] ?? '', 0, 1 ) );
    $color = $is_blocked ? '#d63638' : $colors[ $i % count($colors) ];
    $booking_count = (int) ($g['booking_count'] ?? 0);
    $total_revenue = (float) ($g['total_revenue'] ?? 0);
    $last_checkin = $g['last_checkin'] ?? null;
?>
<tr <?php echo $is_blocked ? 'style="background:#fff5f5;"' : ''; ?>>
<td>
    <div style="display:flex; align-items:center; gap:10px;">
        <div style="width:36px; height:36px; border-radius:50%; background:<?php echo esc_attr($color); ?>; color:#fff; display:flex; align-items:center; justify-content:center; font-size:13px; font-weight:700; flex-shrink:0;">
            <?php echo esc_html( $initials ); ?>
        </div>
        <div>
            <strong><a href="<?php echo esc_url( admin_url( 'admin.php?page=artechia-guests&action=edit&id=' . $g['id'] ) ); ?>"><?php echo esc_html( $g['first_name'] . ' ' . $g['last_name'] ); ?></a></strong>
            <?php if ( $is_blocked ) : ?>
                <span style="background:#d63638; color:#fff; font-size:9px; padding:1px 5px; border-radius:3px; margin-left:4px; font-weight:600;" title="<?php echo esc_attr( $g['blacklist_reason'] ); ?>">BLOQUEADO</span>
            <?php endif; ?>
            <div class="row-actions"><span class="edit"><a href="<?php echo esc_url( admin_url( 'admin.php?page=artechia-guests&action=edit&id=' . $g['id'] ) ); ?>"><?php esc_html_e( 'Editar', 'artechia-pms' ); ?></a> | </span>
            <span class="delete"><a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=artechia-guests&action=delete&id=' . $g['id'] ), 'artechia_delete_guest_' . $g['id'] ) ); ?>" class="artechia-delete"><?php esc_html_e( 'Eliminar', 'artechia-pms' ); ?></a></span></div>
        </div>
    </div>
</td>
<td>
    <div style="font-size:12px; white-space:nowrap;"><?php echo esc_html( $g['email'] ); ?></div>
    <?php if ( ! empty( $g['phone'] ) ) : ?>
        <div style="font-size:11px; color:#888; margin-top:2px;">📞 <?php echo esc_html( $g['phone'] ); ?></div>
    <?php endif; ?>
</td>
<td><?php echo ! empty( $g['document_number'] ) ? esc_html( strtoupper( $g['document_type'] ?? '' ) . ' ' . $g['document_number'] ) : '<span style="color:#ccc;">—</span>'; ?></td>
<td style="text-align:center;">
    <?php if ( $booking_count > 0 ) : ?>
        <span style="background:#e0f2fe; color:#0369a1; padding:2px 8px; border-radius:10px; font-size:12px; font-weight:600;"><?php echo $booking_count; ?></span>
    <?php else : ?>
        <span style="color:#ccc;">0</span>
    <?php endif; ?>
</td>
<td>
    <?php if ( $last_checkin ) : ?>
        <span style="font-size:12px;"><?php echo esc_html( \Artechia\PMS\Helpers\Helpers::format_date( $last_checkin ) ); ?></span>
    <?php else : ?>
        <span style="color:#ccc;">—</span>
    <?php endif; ?>
</td>
<td style="text-align:right;">
    <?php if ( $total_revenue > 0 ) : ?>
        <span style="font-size:12px; font-weight:600; color:#16a34a;"><?php echo esc_html( \Artechia\PMS\Helpers\Helpers::format_price( $total_revenue ) ); ?></span>
    <?php else : ?>
        <span style="color:#ccc;">—</span>
    <?php endif; ?>
</td>
</tr>
<?php endforeach; ?></tbody></table>
<?php endif; endif; ?>
</div>
