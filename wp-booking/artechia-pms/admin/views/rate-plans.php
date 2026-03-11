<?php
/**
 * Rate Plans admin view: list + modal create/edit form.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

use Artechia\PMS\Repositories\RatePlanRepository;
use Artechia\PMS\Repositories\PropertyRepository;
use Artechia\PMS\Repositories\RateRepository;
use Artechia\PMS\Helpers\Helpers;
use Artechia\PMS\Logger;

$repo      = new RatePlanRepository();
$rate_repo = new RateRepository();
$prop_repo = new PropertyRepository();
$action    = sanitize_text_field( $_GET['action'] ?? 'list' );
$id        = absint( $_GET['id'] ?? 0 );
$property  = $prop_repo->get_default();
$prop_id   = absint( $_GET['property_id'] ?? ( $property['id'] ?? 0 ) );
$saved_ok  = false;

// Handle save.
if ( isset( $_POST['artechia_save_rate_plan'] ) && check_admin_referer( 'artechia_save_rate_plan' ) ) {
    $data = [
        'property_id'             => $prop_id,
        'name'                    => sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) ),
        'code'                    => sanitize_text_field( wp_unslash( $_POST['code'] ?? '' ) ),
        'description'             => sanitize_textarea_field( wp_unslash( $_POST['description'] ?? '' ) ),
        'date_from'               => ! empty( $_POST['date_from'] ) ? sanitize_text_field( wp_unslash( $_POST['date_from'] ) ) : null,
        'date_to'                 => ! empty( $_POST['date_to'] ) ? sanitize_text_field( wp_unslash( $_POST['date_to'] ) ) : null,
        'is_annual'               => ! empty( $_POST['is_annual'] ) ? 1 : 0,
        'min_stay'                => absint( $_POST['min_stay'] ?? 1 ),
        'max_stay'                => absint( $_POST['max_stay'] ?? 30 ),
        'color'                   => sanitize_hex_color( wp_unslash( $_POST['color'] ?? '#3b82f6' ) ),
        'deposit_pct'             => absint( $_POST['deposit_pct'] ?? 0 ),
        'is_refundable'           => ( sanitize_text_field( $_POST['cancellation_type'] ?? 'flexible' ) === 'flexible' ) ? 1 : 0,
        'cancellation_type'       => sanitize_text_field( $_POST['cancellation_type'] ?? 'flexible' ),
        'cancellation_deadline_days' => absint( $_POST['cancellation_deadline_days'] ?? 0 ),
        'penalty_type'            => sanitize_text_field( $_POST['penalty_type'] ?? '100' ),
        'cancellation_policy_json'=> \Artechia\PMS\Helpers\Helpers::sanitize_json( wp_unslash( $_POST['cancellation_policy_json'] ?? '{}' ) ),
        'status'                  => sanitize_text_field( wp_unslash( $_POST['status'] ?? 'active' ) ),
    ];

    if ( $id ) {
        $repo->update( $id, $data );
        $repo->sync_dates( $id, wp_unslash( $_POST['rate_dates'] ?? [] ) );
        Logger::info( 'rate_plan.updated', "Rate plan #{$id} updated", 'rate_plan', $id );
        $msg = esc_html__( 'Plan de tarifa actualizado.', 'artechia-pms' );
    } else {
        $id = $repo->create( $data );
        if ( $id ) {
            $repo->sync_dates( $id, wp_unslash( $_POST['rate_dates'] ?? [] ) );
            Logger::info( 'rate_plan.created', "Rate plan #{$id} created", 'rate_plan', $id );
            $msg = esc_html__( 'Plan de tarifa creado.', 'artechia-pms' );
            $action = 'edit';
        }
    }

    if ( $id ) {
        // Also save attached rates if present
        $rates_data = $_POST['rates'] ?? [];
        $saved_rates = 0;
        foreach ( $rates_data as $rt_id => $values ) {
            $rt_id = absint( $rt_id );
            $price = Helpers::sanitize_money( $values['price'] ?? 0 );
            if ( $price <= 0 ) continue;

            $rate_data = [
                'room_type_id'    => $rt_id,
                'rate_plan_id'    => $id,
                'price_per_night' => $price,
                'extra_adult'     => Helpers::sanitize_money( $values['extra_adult'] ?? 0 ),
                'extra_child'     => Helpers::sanitize_money( $values['extra_child'] ?? 0 ),
            ];
            $rate_repo->upsert( $rate_data );
            $saved_rates++;
        }
        
        // Redirect back to list so modal closes
        wp_redirect( admin_url( 'admin.php?page=artechia-rate-plans&saved=1' ) );
        exit;
    }
}

// Handle delete.
if ( $action === 'delete' && $id && check_admin_referer( 'artechia_delete_rp_' . $id ) ) {
    $repo->delete( $id );
    Logger::info( 'rate_plan.deleted', "Rate plan #{$id} deleted", 'rate_plan', $id );
    wp_redirect( admin_url( 'admin.php?page=artechia-rate-plans&saved=1' ) );
    exit;
}

// For edit modal, load the item
$item = ( $action === 'edit' ) && $id ? $repo->find( $id ) : null;
$show_modal = ( $action === 'edit' || $action === 'new' );

// Always load list items
$items = $repo->all( [ 'where' => [ 'property_id' => $prop_id ], 'orderby' => 'name', 'order' => 'ASC', 'limit' => 50 ] );
$today_active = $repo->find_for_date( $prop_id, date('Y-m-d') );
$active_id = $today_active ? (int) $today_active['id'] : 0;

$total_plans = count( $items );
$active_plans = 0;
$default_plan = null;
foreach ( $items as $rp ) {
    if ( ($rp['status'] ?? '') === 'active' ) $active_plans++;
}
?>

<div class="wrap artechia-wrap">

<?php if ( ! empty( $_GET['saved'] ) ) : ?>
    <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Cambios guardados exitosamente.', 'artechia-pms' ); ?></p></div>
<?php endif; ?>

    <!-- Summary Cards -->
    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(160px, 1fr)); gap:12px; margin-bottom:16px;">
        <div style="background:#fff; border:1px solid #e5e7eb; border-radius:8px; padding:14px 16px; display:flex; align-items:center; gap:12px;">
            <div style="width:40px; height:40px; border-radius:10px; background:#eff6ff; display:flex; align-items:center; justify-content:center; font-size:20px;">💰</div>
            <div>
                <div style="font-size:22px; font-weight:700; color:#1e293b;"><?php echo $total_plans; ?></div>
                <div style="font-size:11px; color:#94a3b8; text-transform:uppercase; letter-spacing:0.5px;">Planes</div>
            </div>
        </div>
        <div style="background:#fff; border:1px solid #e5e7eb; border-radius:8px; padding:14px 16px; display:flex; align-items:center; gap:12px;">
            <div style="width:40px; height:40px; border-radius:10px; background:#f0fdf4; display:flex; align-items:center; justify-content:center; font-size:20px;">✅</div>
            <div>
                <div style="font-size:22px; font-weight:700; color:#16a34a;"><?php echo $active_plans; ?></div>
                <div style="font-size:11px; color:#94a3b8; text-transform:uppercase; letter-spacing:0.5px;">Activos</div>
            </div>
        </div>
        <div style="background:#fff; border:1px solid #e5e7eb; border-radius:8px; padding:14px 16px; display:flex; align-items:center; gap:12px;">
            <div style="width:40px; height:40px; border-radius:10px; background:<?php echo $active_id ? '#f0fdf4' : '#f8fafc'; ?>; display:flex; align-items:center; justify-content:center; font-size:20px;">📅</div>
            <div>
                <div style="font-size:16px; font-weight:700; color:<?php echo $active_id ? '#16a34a' : '#94a3b8'; ?>;"><?php echo $today_active ? esc_html( $today_active['name'] ) : '—'; ?></div>
                <div style="font-size:11px; color:#94a3b8; text-transform:uppercase; letter-spacing:0.5px;">Vigente hoy</div>
            </div>
        </div>
    </div>

    <?php if ( empty( $items ) ) : ?>
        <div class="artechia-panel artechia-empty">
            <div class="artechia-empty__icon">💰</div>
            <div class="artechia-empty__title"><?php esc_html_e( 'No hay planes de tarifa', 'artechia-pms' ); ?></div>
            <p><?php esc_html_e( 'Creá al menos un plan (ej: Tarifa Estándar) para configurar precios.', 'artechia-pms' ); ?></p>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=artechia-rate-plans&action=new' ) ); ?>" class="artechia-btn artechia-btn--primary"><?php esc_html_e( 'Crear Plan', 'artechia-pms' ); ?></a>
        </div>
    <?php else : ?>
        <div style="display:flex; justify-content:flex-end; margin-bottom:12px;">
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=artechia-rate-plans&action=new' ) ); ?>" class="button button-primary" style="height:36px; line-height:34px;">+ <?php esc_html_e( 'Agregar Plan', 'artechia-pms' ); ?></a>
        </div>
        <table class="wp-list-table widefat fixed striped artechia-table">
            <thead>
                <tr>
                    <th style="width:30%;"><?php esc_html_e( 'Plan', 'artechia-pms' ); ?></th>
                    <th style="width:12%;"><?php esc_html_e( 'Depósito', 'artechia-pms' ); ?></th>
                    <th><?php esc_html_e( 'Vigencia', 'artechia-pms' ); ?></th>
                    <th style="width:10%; text-align:center;"><?php esc_html_e( 'Estadía', 'artechia-pms' ); ?></th>
                    <th style="width:10%;"><?php esc_html_e( 'Estado', 'artechia-pms' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $items as $rp ) :
                    $is_active_now = ( (int) $rp['id'] === $active_id );
                ?>
                    <tr <?php echo $is_active_now ? 'style="background-color: #f0fdf4;"' : ''; ?>>
                        <td>
                            <div style="display:flex; align-items:center; gap:10px;">
                                <div style="width:36px; height:36px; border-radius:8px; background:<?php echo esc_attr( $rp['color'] ?? '#3b82f6' ); ?>; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                                    <span style="color:#fff; font-size:16px;">💰</span>
                                </div>
                                <div>
                                    <strong><a href="<?php echo esc_url( admin_url( 'admin.php?page=artechia-rate-plans&action=edit&id=' . $rp['id'] ) ); ?>"><?php echo esc_html( $rp['name'] ); ?></a></strong>
                                    <?php if ( $is_active_now ) : ?>
                                        <span style="background:#dcfce7; color:#166534; padding:1px 6px; border-radius:8px; font-size:9px; font-weight:600; margin-left:4px;">VIGENTE HOY</span>
                                    <?php endif; ?>
                                    <div class="row-actions">
                                        <span class="edit"><a href="<?php echo esc_url( admin_url( 'admin.php?page=artechia-rate-plans&action=edit&id=' . $rp['id'] ) ); ?>"><?php esc_html_e( 'Editar', 'artechia-pms' ); ?></a> | </span>
                                        <span class="delete"><a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=artechia-rate-plans&action=delete&id=' . $rp['id'] ), 'artechia_delete_rp_' . $rp['id'] ) ); ?>" class="artechia-delete"><?php esc_html_e( 'Eliminar', 'artechia-pms' ); ?></a></span>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <?php $dep = (int) ($rp['deposit_pct'] ?? 0); ?>
                            <span style="background:<?php echo $dep > 0 ? '#fef3c7' : '#f1f5f9'; ?>; color:<?php echo $dep > 0 ? '#92400e' : '#94a3b8'; ?>; padding:3px 10px; border-radius:12px; font-size:12px; font-weight:600;"><?php echo $dep; ?>%</span>
                        </td>
                        <td>
                            <?php if ( ! empty( $rp['is_annual'] ) ) : ?>
                                <span style="background:#dbeafe; color:#1e40af; padding:3px 10px; border-radius:12px; font-size:11px; font-weight:600;">📅 Anual</span>
                            <?php else : ?>
                                <?php
                                global $wpdb;
                                $rpd_table = $wpdb->prefix . 'artechia_rate_plan_dates';
                                $dates = $wpdb->get_results( $wpdb->prepare( "SELECT date_from, date_to FROM {$rpd_table} WHERE rate_plan_id = %d ORDER BY date_from ASC", $rp['id'] ), \ARRAY_A );
                                if ( ! empty( $dates ) ) :
                                    foreach ( $dates as $d ) :
                                ?>
                                    <div style="display:inline-flex; align-items:center; gap:6px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; padding:3px 10px; margin-bottom:4px; font-size:12px;">
                                        <span style="display:inline-block; width:6px; height:6px; border-radius:50%; background:<?php echo esc_attr( $rp['color'] ); ?>;"></span>
                                        <span style="color:#475569;"><?php echo esc_html( \Artechia\PMS\Helpers\Helpers::format_date( $d['date_from'] ) ); ?></span>
                                        <span style="color:#94a3b8;">→</span>
                                        <span style="color:#475569;"><?php echo esc_html( \Artechia\PMS\Helpers\Helpers::format_date( $d['date_to'] ) ); ?></span>
                                    </div>
                                <?php
                                    endforeach;
                                else :
                                ?>
                                    <span style="color:#ccc;">—</span>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                        <td style="text-align:center;">
                            <span style="font-size:12px; color:#475569;"><?php echo esc_html( (int) ($rp['min_stay'] ?? 1) . '-' . (int) ($rp['max_stay'] ?? 30) ); ?></span>
                            <div style="font-size:10px; color:#94a3b8;">noches</div>
                        </td>
                        <td>
                            <?php
                            $s = $rp['status'] ?? 'active';
                            $badge_bg = $s === 'active' ? '#dcfce7' : '#fee2e2';
                            $badge_color = $s === 'active' ? '#166534' : '#991b1b';
                            $badge_label = $s === 'active' ? 'Activo' : 'Inactivo';
                            ?>
                            <span style="background:<?php echo $badge_bg; ?>; color:<?php echo $badge_color; ?>; padding:3px 10px; border-radius:12px; font-size:11px; font-weight:600;"><?php echo esc_html( $badge_label ); ?></span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

</div>

<!-- ================================================================ -->
<!-- MODAL: Create / Edit Rate Plan                                    -->
<!-- ================================================================ -->
<?php if ( $show_modal ) :
    // Prepare data for form
    $dates = $item['dates'] ?? [];
    if ( empty( $dates ) ) $dates = [ ['date_from' => '', 'date_to' => ''] ];
    // Load rates matrix for both new and edit
    if ( $item && $id ) {
        $matrix = $rate_repo->get_matrix( $prop_id, $id );
    } else {
        // For new plans, load room types so user can set prices
        $rt_repo = new \Artechia\PMS\Repositories\RoomTypeRepository();
        $room_types_list = $rt_repo->all_with_counts( $prop_id );
        $matrix = ! empty( $room_types_list ) ? [ 'room_types' => $room_types_list, 'rates' => [] ] : null;
    }
?>
<div id="rp-modal" class="artechia-rp-modal">
    <div class="artechia-rp-modal-content">
        <!-- Header -->
        <div class="artechia-rp-modal-header">
            <div style="display:flex; align-items:center; gap:10px;">
                <span style="width:36px; height:36px; border-radius:10px; background:linear-gradient(135deg,#f59e0b,#f97316); display:flex; align-items:center; justify-content:center; font-size:16px; color:#fff;">💰</span>
                <h2 style="margin:0; font-size:18px; font-weight:700; color:#1e293b;">
                    <?php echo $id ? esc_html__( 'Editar Plan de Tarifa', 'artechia-pms' ) : esc_html__( 'Nuevo Plan de Tarifa', 'artechia-pms' ); ?>
                </h2>
            </div>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=artechia-rate-plans' ) ); ?>" class="artechia-rp-close">&times;</a>
        </div>

        <!-- Form -->
        <form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=artechia-rate-plans&action=' . ($id ? 'edit&id='.$id : 'new') ) ); ?>">
            <?php wp_nonce_field( 'artechia_save_rate_plan' ); ?>
            <div class="artechia-rp-modal-body">

                <!-- Row 1: Name + Color -->
                <div class="artechia-rp-grid">
                    <div class="artechia-rp-field" style="flex:1;">
                        <label><?php esc_html_e( 'Nombre', 'artechia-pms' ); ?></label>
                        <input type="text" name="name" value="<?php echo esc_attr( $item['name'] ?? '' ); ?>" required placeholder="Tarifa Estándar, Fin de Semana...">
                    </div>
                    <div class="artechia-rp-field" style="width:80px;">
                        <label><?php esc_html_e( 'Color', 'artechia-pms' ); ?></label>
                        <input type="color" name="color" value="<?php echo esc_attr( $item['color'] ?? '#3b82f6' ); ?>" style="width:100%; height:38px; padding:2px; border-radius:8px; cursor:pointer;">
                    </div>
                </div>

                <!-- Row 2: Vigencia -->
                <div class="artechia-rp-field">
                    <label><?php esc_html_e( 'Vigencia', 'artechia-pms' ); ?></label>
                    <label style="display:flex; align-items:center; gap:6px; font-weight:400; margin-bottom:8px; cursor:pointer;">
                        <input type="checkbox" name="is_annual" id="rp-is-annual" value="1" <?php checked( $item['is_annual'] ?? 1, 1 ); ?>>
                        <?php esc_html_e( 'Todo el año', 'artechia-pms' ); ?>
                    </label>
                    <div id="rp-date-range-fields" style="<?php echo ( ! empty( $item['is_annual'] ) || ( $item === null ) ) ? 'display:none;' : ''; ?>">
                        <div id="rp-dates-container">
                            <?php foreach ( $dates as $idx => $d ) : ?>
                            <div class="artechia-rp-date-row">
                                <input type="date" name="rate_dates[<?php echo $idx; ?>][date_from]" value="<?php echo esc_attr( $d['date_from'] ); ?>">
                                <span style="color:#94a3b8;">→</span>
                                <input type="date" name="rate_dates[<?php echo $idx; ?>][date_to]" value="<?php echo esc_attr( $d['date_to'] ); ?>">
                                <button type="button" class="artechia-rp-remove-date" <?php if ( count($dates) === 1 ) echo 'style="display:none;"'; ?>>×</button>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" id="rp-add-date" class="button button-small" style="margin-top:4px; border-radius:6px;">+ <?php esc_html_e( 'Agregar fechas', 'artechia-pms' ); ?></button>
                    </div>
                </div>

                <!-- Row 3: Estadía + Depósito -->
                <div class="artechia-rp-grid">
                    <div class="artechia-rp-field">
                        <label><?php esc_html_e( 'Estadía mínima', 'artechia-pms' ); ?></label>
                        <div style="display:flex; align-items:center; gap:6px;">
                            <input type="number" name="min_stay" value="<?php echo esc_attr( (int) ( $item['min_stay'] ?? 1 ) ); ?>" min="1" step="1" style="width:80px;">
                            <span style="color:#94a3b8; font-size:12px;">noches</span>
                        </div>
                    </div>
                    <div class="artechia-rp-field">
                        <label><?php esc_html_e( 'Estadía máxima', 'artechia-pms' ); ?></label>
                        <div style="display:flex; align-items:center; gap:6px;">
                            <input type="number" name="max_stay" value="<?php echo esc_attr( (int) ( $item['max_stay'] ?? 30 ) ); ?>" min="1" step="1" style="width:80px;">
                            <span style="color:#94a3b8; font-size:12px;">noches</span>
                        </div>
                    </div>
                    <div class="artechia-rp-field">
                        <label><?php esc_html_e( 'Depósito', 'artechia-pms' ); ?></label>
                        <div style="display:flex; align-items:center; gap:6px;">
                            <input type="number" name="deposit_pct" value="<?php echo esc_attr( (int) ( $item['deposit_pct'] ?? 0 ) ); ?>" min="0" max="100" step="1" style="width:80px;">
                            <span style="color:#94a3b8; font-size:12px;">%</span>
                        </div>
                    </div>
                </div>

                <!-- Row 4: Cancellation Policy -->
                <div class="artechia-rp-field">
                    <label><?php esc_html_e( 'Política de Cancelación', 'artechia-pms' ); ?></label>
                    <select name="cancellation_type" id="rp-cancel-type">
                        <option value="non_refundable" <?php selected( $item['cancellation_type'] ?? 'flexible', 'non_refundable' ); ?>><?php esc_html_e( 'No Reembolsable (Estricta)', 'artechia-pms' ); ?></option>
                        <option value="flexible" <?php selected( $item['cancellation_type'] ?? 'flexible', 'flexible' ); ?>><?php esc_html_e( 'Flexible (Configurable)', 'artechia-pms' ); ?></option>
                    </select>
                    <div id="rp-flex-options" style="margin-top:10px; padding:12px; background:#f8fafc; border-radius:8px; border:1px solid #e2e8f0; <?php echo ( ($item['cancellation_type'] ?? 'flexible') === 'non_refundable' ) ? 'display:none;' : ''; ?>">
                        <div style="display:flex; align-items:center; gap:8px; margin-bottom:8px;">
                            <span style="font-size:13px; color:#475569;"><?php esc_html_e( 'Cancelación gratuita hasta', 'artechia-pms' ); ?></span>
                            <input type="number" name="cancellation_deadline_days" value="<?php echo esc_attr( (int) ( $item['cancellation_deadline_days'] ?? 0 ) ); ?>" min="0" step="1" style="width:60px;">
                            <span style="font-size:13px; color:#475569;"><?php esc_html_e( 'días antes', 'artechia-pms' ); ?></span>
                        </div>
                        <div style="display:flex; align-items:center; gap:8px;">
                            <span style="font-size:13px; color:#475569;"><?php esc_html_e( 'Penalidad:', 'artechia-pms' ); ?></span>
                            <select name="penalty_type" style="width:auto;">
                                <option value="100" <?php selected( $item['penalty_type'] ?? '100', '100' ); ?>><?php esc_html_e( '100% del total', 'artechia-pms' ); ?></option>
                                <option value="50" <?php selected( $item['penalty_type'] ?? '100', '50' ); ?>><?php esc_html_e( '50% del total', 'artechia-pms' ); ?></option>
                                <option value="1_night" <?php selected( $item['penalty_type'] ?? '100', '1_night' ); ?>><?php esc_html_e( '1 Noche', 'artechia-pms' ); ?></option>
                            </select>
                        </div>
                    </div>
                    <input type="hidden" name="is_refundable" id="rp-is-refundable" value="<?php echo ( ($item['cancellation_type'] ?? 'flexible') === 'flexible' ) ? '1' : '0'; ?>">
                    <input type="hidden" name="cancellation_policy_json" value="<?php echo esc_attr( $item['cancellation_policy_json'] ?? '{}' ); ?>">
                </div>

                <!-- Row 5: Status + Default -->
                <div class="artechia-rp-grid">
                    <div class="artechia-rp-field">
                        <label><?php esc_html_e( 'Estado', 'artechia-pms' ); ?></label>
                        <select name="status">
                            <option value="active" <?php selected( $item['status'] ?? 'active', 'active' ); ?>><?php esc_html_e( 'Activo', 'artechia-pms' ); ?></option>
                            <option value="inactive" <?php selected( $item['status'] ?? '', 'inactive' ); ?>><?php esc_html_e( 'Inactivo', 'artechia-pms' ); ?></option>
                        </select>
                    </div>
                </div>

                <!-- Rates Matrix -->
                <?php if ( $matrix && ! empty( $matrix['room_types'] ) ) : ?>
                <div class="artechia-rp-field" style="margin-top:8px;">
                    <label style="margin-bottom:10px;">
                        <?php esc_html_e( 'Precios por noche', 'artechia-pms' ); ?>
                        <span style="font-size:12px; font-weight:400; color:#94a3b8; margin-left:6px;">(<?php echo esc_html( \Artechia\PMS\Services\Settings::currency_symbol() ); ?>)</span>
                    </label>
                    <div style="background:#f8fafc; border-radius:8px; border:1px solid #e2e8f0; overflow:hidden;">
                        <?php foreach ( $matrix['room_types'] as $i => $rt ) :
                            $rate = $matrix['rates'][ $rt['id'] ] ?? null;
                        ?>
                        <div style="display:flex; align-items:center; justify-content:space-between; padding:10px 14px; <?php echo $i > 0 ? 'border-top:1px solid #e2e8f0;' : ''; ?>">
                            <span style="font-size:13px; font-weight:600; color:#1e293b;"><?php echo esc_html( $rt['name'] ); ?></span>
                            <input type="text" inputmode="decimal" class="artechia-money-input"
                                name="rates[<?php echo esc_attr( $rt['id'] ); ?>][price]"
                                value="<?php echo esc_attr( $rate ? (int) $rate['price_per_night'] : '' ); ?>"
                                placeholder="0" style="width:120px; text-align:right;">
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php elseif ( empty( $matrix ) || empty( $matrix['room_types'] ) ) : ?>
                <div style="background:#fefce8; border:1px solid #fde68a; border-radius:8px; padding:12px 16px; font-size:13px; color:#92400e;">
                    ⚠️ <?php esc_html_e( 'No hay tipos de habitación creados. Creá al menos uno para configurar precios.', 'artechia-pms' ); ?>
                </div>
                <?php endif; ?>

            </div>

            <!-- Footer -->
            <div class="artechia-rp-modal-footer">
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=artechia-rate-plans' ) ); ?>" class="button" style="height:36px; line-height:34px; border-radius:6px;"><?php esc_html_e( 'Cancelar', 'artechia-pms' ); ?></a>
                <?php submit_button(
                    $id ? __( 'Guardar Cambios', 'artechia-pms' ) : __( 'Crear Plan', 'artechia-pms' ),
                    'primary', 'artechia_save_rate_plan', false,
                    [ 'style' => 'height:36px; line-height:34px; border-radius:6px;' ]
                ); ?>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- ================================================================ -->
<!-- Styles                                                            -->
<!-- ================================================================ -->
<style>
/* Block background scroll when modal is open */
body.artechia-rp-modal-open { overflow: hidden !important; }

/* Modal Overlay */
.artechia-rp-modal {
    position: fixed; z-index: 99999; left: 0; top: 0; width: 100%; height: 100%;
    background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px);
    display: flex; align-items: center; justify-content: center;
    animation: rp-fade-in 0.2s ease-out;
}
@keyframes rp-fade-in { from { opacity: 0; } to { opacity: 1; } }

/* Modal Box */
.artechia-rp-modal-content {
    background: #fff; border-radius: 12px; width: 640px; max-width: 95vw;
    max-height: 90vh; display: flex; flex-direction: column; overflow: hidden;
    box-shadow: 0 20px 60px rgba(0,0,0,0.2), 0 0 0 1px rgba(0,0,0,0.05);
    animation: rp-slide-in 0.25s ease-out;
}
@keyframes rp-slide-in { from { transform: translateY(20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }

/* Header */
.artechia-rp-modal-header {
    display: flex; justify-content: space-between; align-items: center;
    padding: 20px 24px; border-bottom: 1px solid #e5e7eb; flex-shrink: 0;
}

/* Close Button */
.artechia-rp-close {
    background: none; border: none; font-size: 24px; cursor: pointer; color: #94a3b8;
    width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center;
    justify-content: center; transition: all 0.2s; text-decoration: none;
}
.artechia-rp-close:hover { color: #1e293b; background: #f1f5f9; }

/* Form must be flex too so body can scroll */
.artechia-rp-modal-content form {
    display: flex; flex-direction: column; flex: 1; min-height: 0; overflow: hidden;
}

/* Body – scrollable */
.artechia-rp-modal-body {
    flex: 1; overflow-y: auto; padding: 20px 24px; display: flex; flex-direction: column; gap: 16px;
    min-height: 0;
}

/* Footer */
.artechia-rp-modal-footer {
    display: flex; justify-content: flex-end; gap: 8px;
    padding: 16px 24px; border-top: 1px solid #e5e7eb; flex-shrink: 0;
}

/* Grid */
.artechia-rp-grid { display: flex; gap: 14px; }

/* Fields */
.artechia-rp-field label {
    display: block; font-weight: 600; margin-bottom: 6px; color: #475569; font-size: 13px;
}
.artechia-rp-field input[type="text"],
.artechia-rp-field input[type="number"],
.artechia-rp-field input[type="date"],
.artechia-rp-field select {
    width: 100%; height: 38px; border: 1px solid #e2e8f0; border-radius: 8px;
    padding: 0 12px; font-size: 13px; color: #1e293b; background: #fff;
    transition: border-color 0.2s, box-shadow 0.2s; box-sizing: border-box;
}
.artechia-rp-field input:focus,
.artechia-rp-field select:focus {
    border-color: #f59e0b; box-shadow: 0 0 0 3px rgba(245,158,11,0.1); outline: none;
}

/* Date rows */
.artechia-rp-date-row {
    display: flex; align-items: center; gap: 8px; margin-bottom: 6px;
}
.artechia-rp-date-row input[type="date"] {
    height: 36px; border: 1px solid #e2e8f0; border-radius: 6px; padding: 0 8px; font-size: 13px;
}
.artechia-rp-remove-date {
    background: #fef2f2; border: 1px solid #fecaca; color: #dc2626; cursor: pointer;
    width: 28px; height: 28px; border-radius: 6px; display: flex; align-items: center;
    justify-content: center; font-size: 16px; transition: all 0.2s;
}
.artechia-rp-remove-date:hover { background: #fee2e2; }

/* Money inputs in rates matrix */
.artechia-rp-modal .artechia-money-input {
    height: 36px; border: 1px solid #e2e8f0; border-radius: 6px; padding: 0 10px;
    font-size: 13px; color: #1e293b; background: #fff;
    transition: border-color 0.2s, box-shadow 0.2s;
}
.artechia-rp-modal .artechia-money-input:focus {
    border-color: #f59e0b; box-shadow: 0 0 0 3px rgba(245,158,11,0.1); outline: none;
}
</style>

<!-- ================================================================ -->
<!-- JavaScript                                                        -->
<!-- ================================================================ -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Block background scroll when modal is open
    const rpModal = document.getElementById('rp-modal');
    if (rpModal) document.body.classList.add('artechia-rp-modal-open');

    // Toggle annual / date range
    const isAnnualCheck = document.getElementById('rp-is-annual');
    const dateFields = document.getElementById('rp-date-range-fields');
    if (isAnnualCheck && dateFields) {
        isAnnualCheck.addEventListener('change', function() {
            dateFields.style.display = this.checked ? 'none' : 'block';
        });
    }

    // Cancellation type toggle
    const cancelType = document.getElementById('rp-cancel-type');
    const flexOpts = document.getElementById('rp-flex-options');
    const isRefInput = document.getElementById('rp-is-refundable');
    if (cancelType && flexOpts) {
        function updateCancelUI() {
            if (cancelType.value === 'flexible') {
                flexOpts.style.display = 'block';
                if (isRefInput) isRefInput.value = '1';
            } else {
                flexOpts.style.display = 'none';
                if (isRefInput) isRefInput.value = '0';
            }
        }
        cancelType.addEventListener('change', updateCancelUI);
        updateCancelUI();
    }

    // Date range add/remove
    const addDateBtn = document.getElementById('rp-add-date');
    const datesContainer = document.getElementById('rp-dates-container');
    if (addDateBtn && datesContainer) {
        addDateBtn.addEventListener('click', function() {
            const rows = datesContainer.querySelectorAll('.artechia-rp-date-row');
            const lastInput = rows.length ? rows[rows.length-1].querySelector('input') : null;
            const nextIdx = lastInput ? (parseInt(lastInput.name.match(/\[(\d+)\]/)[1]) + 1) : 0;
            
            const newRow = document.createElement('div');
            newRow.className = 'artechia-rp-date-row';
            newRow.innerHTML = `
                <input type="date" name="rate_dates[${nextIdx}][date_from]">
                <span style="color:#94a3b8;">→</span>
                <input type="date" name="rate_dates[${nextIdx}][date_to]">
                <button type="button" class="artechia-rp-remove-date">×</button>
            `;
            datesContainer.appendChild(newRow);
            updateDateButtons();
        });

        datesContainer.addEventListener('click', function(e) {
            if (e.target.classList.contains('artechia-rp-remove-date')) {
                e.target.closest('.artechia-rp-date-row').remove();
                updateDateButtons();
            }
        });

        function updateDateButtons() {
            const rows = datesContainer.querySelectorAll('.artechia-rp-date-row');
            rows.forEach(r => {
                const btn = r.querySelector('.artechia-rp-remove-date');
                if (btn) btn.style.display = rows.length > 1 ? 'flex' : 'none';
            });
        }
    }

    // Money input formatting
    const decS = (window.artechiaPMS && window.artechiaPMS.format) ? window.artechiaPMS.format.decimal_separator : ',';
    const thoS = (window.artechiaPMS && window.artechiaPMS.format) ? window.artechiaPMS.format.thousand_separator : '.';

    function formatMoneyInput(val) {
        if (!val) return '';
        let clean = String(val).replace(/[^\d.,\-]/g, '');
        const lastDot = clean.lastIndexOf('.');
        const lastComma = clean.lastIndexOf(',');
        if (lastDot > -1 && lastComma > -1) {
            if (lastDot > lastComma) { clean = clean.replace(/,/g, ''); }
            else { clean = clean.replace(/\./g, '').replace(',', '.'); }
        } else if (lastComma > -1) {
            const afterComma = clean.substring(lastComma + 1);
            if (afterComma.length <= 2) { clean = clean.replace(',', '.'); }
            else { clean = clean.replace(/,/g, ''); }
        } else if (lastDot > -1) {
            const dotCount = (clean.match(/\./g) || []).length;
            const afterDot = clean.substring(lastDot + 1);
            if (!(dotCount === 1 && afterDot.length <= 2)) { clean = clean.replace(/\./g, ''); }
        }
        let f = parseFloat(clean);
        if (isNaN(f)) return '';
        let parts = f.toFixed(2).split('.');
        parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, thoS);
        return parts.join(decS);
    }

    document.querySelectorAll('.artechia-money-input').forEach(el => {
        if (el.value) el.value = formatMoneyInput(el.value);
        el.addEventListener('blur', function() { this.value = formatMoneyInput(this.value); });
        el.addEventListener('focus', function() {
            if (!this.value) return;
            if (decS === ',') { this.value = this.value.replace(/\./g, ''); }
            else { this.value = this.value.replace(/,/g, ''); }
        });
    });

    // ESC to close
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            const modal = document.getElementById('rp-modal');
            if (modal) window.location.href = '<?php echo esc_url( admin_url( 'admin.php?page=artechia-rate-plans' ) ); ?>';
        }
    });

    // Click backdrop to close
    const modal = document.getElementById('rp-modal');
    if (modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                window.location.href = '<?php echo esc_url( admin_url( 'admin.php?page=artechia-rate-plans' ) ); ?>';
            }
        });
    }
});
</script>
