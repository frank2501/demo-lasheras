<?php
/**
 * Extras / Services admin view: list + modal create/edit.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
use Artechia\PMS\Repositories\ExtraRepository;
use Artechia\PMS\Repositories\PropertyRepository;
use Artechia\PMS\Logger;

$repo = new ExtraRepository();
$prop_repo = new PropertyRepository();
$action = sanitize_text_field( $_GET['action'] ?? 'list' );
$id = absint( $_GET['id'] ?? 0 );
$property = $prop_repo->get_default();
$prop_id = absint( $_GET['property_id'] ?? ( $property['id'] ?? 0 ) );

$price_types = [
    'per_night'     => __( 'Por noche', 'artechia-pms' ),
    'per_stay'      => __( 'Por estadía', 'artechia-pms' ),
    'per_person'    => __( 'Por persona', 'artechia-pms' ),
    'per_pax_night' => __( 'Por persona/noche', 'artechia-pms' ),
];

$price_type_icons = [
    'per_night'     => '🌙',
    'per_stay'      => '🏠',
    'per_person'    => '👤',
    'per_pax_night' => '👥',
];

// Handle save
if ( isset( $_POST['artechia_save_extra'] ) && check_admin_referer( 'artechia_save_extra' ) ) {
    $data = [
        'property_id' => $prop_id,
        'name'        => sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) ),
        'description' => sanitize_textarea_field( wp_unslash( $_POST['description'] ?? '' ) ),
        'price'       => floatval( $_POST['price'] ?? 0 ),
        'price_type'  => sanitize_text_field( $_POST['price_type'] ?? 'per_stay' ),
        'max_qty'     => absint( $_POST['max_qty'] ?? 1 ),
        'is_mandatory'=> ! empty( $_POST['is_mandatory'] ) ? 1 : 0,
        'tax_included'=> ! empty( $_POST['tax_included'] ) ? 1 : 0,
        'sort_order'  => absint( $_POST['sort_order'] ?? 0 ),
        'status'      => sanitize_text_field( wp_unslash( $_POST['status'] ?? 'active' ) ),
    ];
    if ( $id ) {
        $repo->update( $id, $data );
        Logger::info( 'extra.updated', "Extra #{$id} updated", 'extra', $id );
    } else {
        $id = $repo->create( $data );
        if ( $id ) Logger::info( 'extra.created', "Extra #{$id} created", 'extra', $id );
    }
    wp_redirect( admin_url( 'admin.php?page=artechia-extras&saved=1' ) );
    exit;
}

// Handle delete
if ( $action === 'delete' && $id && check_admin_referer( 'artechia_delete_extra_' . $id ) ) {
    $repo->delete( $id );
    Logger::info( 'extra.deleted', "Extra #{$id} deleted", 'extra', $id );
    wp_redirect( admin_url( 'admin.php?page=artechia-extras&saved=1' ) );
    exit;
}

$item = ( $action === 'edit' ) && $id ? $repo->find( $id ) : null;
$show_modal = ( $action === 'edit' || $action === 'new' );
$items = $repo->all( [ 'where' => [ 'property_id' => $prop_id ], 'orderby' => 'sort_order', 'order' => 'ASC' ] );

// Stats
$total_extras = count( $items );
$active_extras = 0;
$mandatory_count = 0;
foreach ( $items as $e ) {
    if ( ($e['status'] ?? '') === 'active' ) $active_extras++;
    if ( ! empty( $e['is_mandatory'] ) ) $mandatory_count++;
}
?>

<div class="wrap artechia-wrap">

<?php if ( ! empty( $_GET['saved'] ) ) : ?>
    <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Cambios guardados exitosamente.', 'artechia-pms' ); ?></p></div>
<?php endif; ?>

    <!-- Summary Cards -->
    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(160px, 1fr)); gap:12px; margin-bottom:16px;">
        <div style="background:#fff; border:1px solid #e5e7eb; border-radius:8px; padding:14px 16px; display:flex; align-items:center; gap:12px;">
            <div style="width:40px; height:40px; border-radius:10px; background:#eff6ff; display:flex; align-items:center; justify-content:center; font-size:20px;">🎁</div>
            <div>
                <div style="font-size:22px; font-weight:700; color:#1e293b;"><?php echo $total_extras; ?></div>
                <div style="font-size:11px; color:#94a3b8; text-transform:uppercase; letter-spacing:0.5px;">Extras</div>
            </div>
        </div>
        <div style="background:#fff; border:1px solid #e5e7eb; border-radius:8px; padding:14px 16px; display:flex; align-items:center; gap:12px;">
            <div style="width:40px; height:40px; border-radius:10px; background:#f0fdf4; display:flex; align-items:center; justify-content:center; font-size:20px;">✅</div>
            <div>
                <div style="font-size:22px; font-weight:700; color:#16a34a;"><?php echo $active_extras; ?></div>
                <div style="font-size:11px; color:#94a3b8; text-transform:uppercase; letter-spacing:0.5px;">Activos</div>
            </div>
        </div>
        <div style="background:#fff; border:1px solid #e5e7eb; border-radius:8px; padding:14px 16px; display:flex; align-items:center; gap:12px;">
            <div style="width:40px; height:40px; border-radius:10px; background:#fef3c7; display:flex; align-items:center; justify-content:center; font-size:20px;">⚡</div>
            <div>
                <div style="font-size:22px; font-weight:700; color:#92400e;"><?php echo $mandatory_count; ?></div>
                <div style="font-size:11px; color:#94a3b8; text-transform:uppercase; letter-spacing:0.5px;">Obligatorios</div>
            </div>
        </div>
    </div>

    <?php if ( empty( $items ) ) : ?>
        <div class="artechia-panel artechia-empty">
            <div class="artechia-empty__icon">🎁</div>
            <div class="artechia-empty__title"><?php esc_html_e( 'No hay extras', 'artechia-pms' ); ?></div>
            <p><?php esc_html_e( 'Creá extras como desayuno, estacionamiento, etc.', 'artechia-pms' ); ?></p>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=artechia-extras&action=new' ) ); ?>" class="artechia-btn artechia-btn--primary"><?php esc_html_e( 'Crear Extra', 'artechia-pms' ); ?></a>
        </div>
    <?php else : ?>
        <div style="display:flex; justify-content:flex-end; margin-bottom:12px;">
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=artechia-extras&action=new' ) ); ?>" class="button button-primary" style="height:36px; line-height:34px;">+ <?php esc_html_e( 'Agregar Extra', 'artechia-pms' ); ?></a>
        </div>
        <table class="wp-list-table widefat fixed striped artechia-table">
            <thead>
                <tr>
                    <th style="width:30%;"><?php esc_html_e( 'Extra', 'artechia-pms' ); ?></th>
                    <th style="width:15%;"><?php esc_html_e( 'Precio', 'artechia-pms' ); ?></th>
                    <th><?php esc_html_e( 'Tipo', 'artechia-pms' ); ?></th>
                    <th style="width:10%; text-align:center;"><?php esc_html_e( 'Máx', 'artechia-pms' ); ?></th>
                    <th style="width:12%;"><?php esc_html_e( 'Opciones', 'artechia-pms' ); ?></th>
                    <th style="width:10%;"><?php esc_html_e( 'Estado', 'artechia-pms' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php
                $extra_colors = ['#8b5cf6','#3b82f6','#10b981','#f59e0b','#ec4899','#14b8a6','#ef4444','#f97316'];
                foreach ( $items as $i => $e ) :
                    $color = $extra_colors[ $i % count($extra_colors) ];
                ?>
                    <tr>
                        <td>
                            <div style="display:flex; align-items:center; gap:10px;">
                                <div style="width:36px; height:36px; border-radius:8px; background:<?php echo esc_attr($color); ?>; color:#fff; display:flex; align-items:center; justify-content:center; font-size:16px; flex-shrink:0;">🎁</div>
                                <div>
                                    <strong><a href="<?php echo esc_url( admin_url( 'admin.php?page=artechia-extras&action=edit&id=' . $e['id'] ) ); ?>"><?php echo esc_html( $e['name'] ); ?></a></strong>
                                    <?php if ( $e['description'] ?? '' ) : ?>
                                        <div style="font-size:11px; color:#94a3b8; margin-top:1px; max-width:250px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"><?php echo esc_html( $e['description'] ); ?></div>
                                    <?php endif; ?>
                                    <div class="row-actions">
                                        <span class="edit"><a href="<?php echo esc_url( admin_url( 'admin.php?page=artechia-extras&action=edit&id=' . $e['id'] ) ); ?>"><?php esc_html_e( 'Editar', 'artechia-pms' ); ?></a> | </span>
                                        <span class="delete"><a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=artechia-extras&action=delete&id=' . $e['id'] ), 'artechia_delete_extra_' . $e['id'] ) ); ?>" class="artechia-delete"><?php esc_html_e( 'Eliminar', 'artechia-pms' ); ?></a></span>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span style="font-size:14px; font-weight:700; color:#1e293b;"><?php echo esc_html( \Artechia\PMS\Helpers\Helpers::format_price( (float) $e['price'] ) ); ?></span>
                        </td>
                        <td>
                            <?php $pt = $e['price_type'] ?? 'per_stay'; if ($pt === 'fixed') $pt = 'per_stay'; ?>
                            <span style="background:#f1f5f9; color:#475569; padding:4px 10px; border-radius:8px; font-size:11px; font-weight:500; display:inline-flex; align-items:center; gap:6px; white-space:nowrap;">
                                <span><?php echo $price_type_icons[$pt] ?? '🏠'; ?></span>
                                <span><?php echo esc_html( $price_types[$pt] ?? __( 'Por estadía', 'artechia-pms' ) ); ?></span>
                            </span>
                        </td>
                        <td style="text-align:center;">
                            <span style="font-size:13px; color:#475569;"><?php echo (int) ($e['max_qty'] ?? 1); ?></span>
                        </td>
                        <td>
                            <?php if ( ! empty( $e['is_mandatory'] ) ) : ?>
                                <span style="background:#fef3c7; color:#92400e; padding:2px 8px; border-radius:8px; font-size:10px; font-weight:600;">Obligatorio</span>
                            <?php endif; ?>
                            <?php if ( ! empty( $e['tax_included'] ) ) : ?>
                                <span style="background:#dbeafe; color:#1e40af; padding:2px 8px; border-radius:8px; font-size:10px; font-weight:600;">IVA incl.</span>
                            <?php endif; ?>
                            <?php if ( empty( $e['is_mandatory'] ) && empty( $e['tax_included'] ) ) : ?>
                                <span style="color:#ccc;">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                            $s = $e['status'] ?? 'active';
                            $bg = $s === 'active' ? '#dcfce7' : '#fee2e2';
                            $cl = $s === 'active' ? '#166534' : '#991b1b';
                            $lb = $s === 'active' ? 'Activo' : 'Inactivo';
                            ?>
                            <span style="background:<?php echo $bg; ?>; color:<?php echo $cl; ?>; padding:3px 10px; border-radius:12px; font-size:11px; font-weight:600;"><?php echo esc_html( $lb ); ?></span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

</div>

<!-- ================================================================ -->
<!-- MODAL: Create / Edit Extra                                        -->
<!-- ================================================================ -->
<?php if ( $show_modal ) : ?>
<div id="extra-modal" class="artechia-ext-modal">
    <div class="artechia-ext-modal-content">
        <!-- Header -->
        <div class="artechia-ext-modal-header">
            <div style="display:flex; align-items:center; gap:10px;">
                <span style="width:36px; height:36px; border-radius:10px; background:linear-gradient(135deg,#8b5cf6,#a855f7); display:flex; align-items:center; justify-content:center; font-size:16px; color:#fff;">🎁</span>
                <h2 style="margin:0; font-size:18px; font-weight:700; color:#1e293b;">
                    <?php echo $id ? esc_html__( 'Editar Extra', 'artechia-pms' ) : esc_html__( 'Nuevo Extra', 'artechia-pms' ); ?>
                </h2>
            </div>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=artechia-extras' ) ); ?>" class="artechia-ext-close">&times;</a>
        </div>

        <!-- Form -->
        <form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=artechia-extras&action=' . ($id ? 'edit&id='.$id : 'new') ) ); ?>">
            <?php wp_nonce_field( 'artechia_save_extra' ); ?>
            <div class="artechia-ext-modal-body">

                <!-- Name -->
                <div class="artechia-ext-field">
                    <label><?php esc_html_e( 'Nombre', 'artechia-pms' ); ?></label>
                    <input type="text" name="name" value="<?php echo esc_attr( $item['name'] ?? '' ); ?>" required placeholder="Desayuno, Estacionamiento, Late Checkout...">
                </div>

                <!-- Description -->
                <div class="artechia-ext-field">
                    <label><?php esc_html_e( 'Descripción', 'artechia-pms' ); ?></label>
                    <textarea name="description" rows="2" placeholder="<?php esc_attr_e( 'Descripción breve del extra...', 'artechia-pms' ); ?>"><?php echo esc_textarea( $item['description'] ?? '' ); ?></textarea>
                </div>

                <!-- Price + Type + Max -->
                <div class="artechia-ext-grid">
                    <div class="artechia-ext-field">
                        <label><?php esc_html_e( 'Precio', 'artechia-pms' ); ?></label>
                        <div style="display:flex; align-items:center; gap:6px;">
                            <span style="color:#94a3b8; font-size:14px;"><?php echo esc_html( \Artechia\PMS\Services\Settings::currency_symbol() ); ?></span>
                            <input type="number" name="price" value="<?php echo esc_attr( $item['price'] ?? 0 ); ?>" step="0.01" min="0" style="width:100px;">
                        </div>
                    </div>
                    <div class="artechia-ext-field">
                        <label><?php esc_html_e( 'Tipo de precio', 'artechia-pms' ); ?></label>
                        <select name="price_type">
                            <?php foreach ( $price_types as $val => $label ) : ?>
                                <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $item['price_type'] ?? 'per_stay', $val ); ?>><?php echo esc_html( $label ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="artechia-ext-field">
                        <label><?php esc_html_e( 'Cant. máx', 'artechia-pms' ); ?></label>
                        <input type="number" name="max_qty" value="<?php echo esc_attr( $item['max_qty'] ?? 1 ); ?>" min="1" style="width:70px;">
                    </div>
                </div>

                <!-- Options row -->
                <div class="artechia-ext-grid">
                    <div class="artechia-ext-field">
                        <label><?php esc_html_e( 'Estado', 'artechia-pms' ); ?></label>
                        <select name="status">
                            <option value="active" <?php selected( $item['status'] ?? 'active', 'active' ); ?>><?php esc_html_e( 'Activo', 'artechia-pms' ); ?></option>
                            <option value="inactive" <?php selected( $item['status'] ?? '', 'inactive' ); ?>><?php esc_html_e( 'Inactivo', 'artechia-pms' ); ?></option>
                        </select>
                    </div>
                    <div class="artechia-ext-field">
                        <label><?php esc_html_e( 'Orden', 'artechia-pms' ); ?></label>
                        <input type="number" name="sort_order" value="<?php echo esc_attr( $item['sort_order'] ?? 0 ); ?>" min="0" style="width:70px;">
                    </div>
                </div>

                <!-- Checkboxes -->
                <div style="display:flex; gap:20px; padding:12px 16px; background:#f8fafc; border-radius:8px; border:1px solid #e2e8f0;">
                    <label style="display:flex; align-items:center; gap:6px; font-weight:400; cursor:pointer; font-size:13px; color:#475569;">
                        <input type="checkbox" name="is_mandatory" value="1" <?php checked( $item['is_mandatory'] ?? 0, 1 ); ?>>
                        ⚡ <?php esc_html_e( 'Obligatorio', 'artechia-pms' ); ?>
                    </label>
                    <label style="display:flex; align-items:center; gap:6px; font-weight:400; cursor:pointer; font-size:13px; color:#475569;">
                        <input type="checkbox" name="tax_included" value="1" <?php checked( $item['tax_included'] ?? 0, 1 ); ?>>
                        🧾 <?php esc_html_e( 'Impuesto incluido', 'artechia-pms' ); ?>
                    </label>
                </div>

            </div>

            <!-- Footer -->
            <div class="artechia-ext-modal-footer">
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=artechia-extras' ) ); ?>" class="button" style="height:36px; line-height:34px; border-radius:6px;"><?php esc_html_e( 'Cancelar', 'artechia-pms' ); ?></a>
                <?php submit_button(
                    $id ? __( 'Guardar Cambios', 'artechia-pms' ) : __( 'Crear Extra', 'artechia-pms' ),
                    'primary', 'artechia_save_extra', false,
                    [ 'style' => 'height:36px; line-height:34px; border-radius:6px;' ]
                ); ?>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- Styles -->
<style>
body.artechia-ext-modal-open { overflow: hidden !important; }

.artechia-ext-modal {
    position: fixed; z-index: 99999; left: 0; top: 0; width: 100%; height: 100%;
    background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px);
    display: flex; align-items: center; justify-content: center;
    animation: ext-fade-in 0.2s ease-out;
}
@keyframes ext-fade-in { from { opacity: 0; } to { opacity: 1; } }

.artechia-ext-modal-content {
    background: #fff; border-radius: 12px; width: 560px; max-width: 95vw;
    max-height: 90vh; display: flex; flex-direction: column; overflow: hidden;
    box-shadow: 0 20px 60px rgba(0,0,0,0.2), 0 0 0 1px rgba(0,0,0,0.05);
    animation: ext-slide-in 0.25s ease-out;
}
@keyframes ext-slide-in { from { transform: translateY(20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }

.artechia-ext-modal-content form {
    display: flex; flex-direction: column; flex: 1; min-height: 0; overflow: hidden;
}

.artechia-ext-modal-header {
    display: flex; justify-content: space-between; align-items: center;
    padding: 20px 24px; border-bottom: 1px solid #e5e7eb; flex-shrink: 0;
}

.artechia-ext-close {
    background: none; border: none; font-size: 24px; cursor: pointer; color: #94a3b8;
    width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center;
    justify-content: center; transition: all 0.2s; text-decoration: none;
}
.artechia-ext-close:hover { color: #1e293b; background: #f1f5f9; }

.artechia-ext-modal-body {
    flex: 1; overflow-y: auto; padding: 20px 24px; display: flex; flex-direction: column; gap: 16px; min-height: 0;
}

.artechia-ext-modal-footer {
    display: flex; justify-content: flex-end; gap: 8px;
    padding: 16px 24px; border-top: 1px solid #e5e7eb; flex-shrink: 0;
}

.artechia-ext-grid { display: flex; gap: 14px; }

.artechia-ext-field label {
    display: block; font-weight: 600; margin-bottom: 6px; color: #475569; font-size: 13px;
}
.artechia-ext-field input[type="text"],
.artechia-ext-field input[type="number"],
.artechia-ext-field select,
.artechia-ext-field textarea {
    width: 100%; border: 1px solid #e2e8f0; border-radius: 8px;
    padding: 8px 12px; font-size: 13px; color: #1e293b; background: #fff;
    transition: border-color 0.2s, box-shadow 0.2s; box-sizing: border-box;
    font-family: inherit;
}
.artechia-ext-field input[type="text"],
.artechia-ext-field input[type="number"],
.artechia-ext-field select { height: 38px; padding: 0 12px; }
.artechia-ext-field input:focus,
.artechia-ext-field select:focus,
.artechia-ext-field textarea:focus {
    border-color: #8b5cf6; box-shadow: 0 0 0 3px rgba(139,92,246,0.1); outline: none;
}
</style>

<!-- JS -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('extra-modal');
    if (modal) {
        document.body.classList.add('artechia-ext-modal-open');

        // ESC to close
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') window.location.href = '<?php echo esc_url( admin_url( 'admin.php?page=artechia-extras' ) ); ?>';
        });

        // Click backdrop to close
        modal.addEventListener('click', function(e) {
            if (e.target === modal) window.location.href = '<?php echo esc_url( admin_url( 'admin.php?page=artechia-extras' ) ); ?>';
        });
    }
});
</script>
