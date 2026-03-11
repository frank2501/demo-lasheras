<?php
/**
 * Unified Property admin view with tabs:
 *   - Propiedad (properties)
 *   - Tipos de Habitación (room-types)
 *   - Habitaciones (room-units)
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$current_tab = sanitize_text_field( $_GET['tab'] ?? 'properties' );
$tabs = [
    'properties' => __( 'Propiedad', 'artechia-pms' ),
    'room-types' => __( 'Tipos de Habitación', 'artechia-pms' ),
    'room-units' => __( 'Habitaciones', 'artechia-pms' ),
];
?>

<div class="wrap artechia-wrap">
    <h1><?php esc_html_e( 'Propiedad', 'artechia-pms' ); ?></h1>
    <nav class="nav-tab-wrapper" style="margin-bottom: 20px;">
        <?php foreach ( $tabs as $slug => $label ) : ?>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=artechia-property&tab=' . $slug ) ); ?>"
               class="nav-tab <?php echo $current_tab === $slug ? 'nav-tab-active' : ''; ?>">
                <?php echo esc_html( $label ); ?>
            </a>
        <?php endforeach; ?>
    </nav>
</div>

<?php
// Include the corresponding sub-view
switch ( $current_tab ) {
    case 'room-types':
        include ARTECHIA_PMS_DIR . 'admin/views/room-types.php';
        break;
    case 'room-units':
        include ARTECHIA_PMS_DIR . 'admin/views/room-units.php';
        break;
    default:
        include ARTECHIA_PMS_DIR . 'admin/views/properties.php';
        break;
}
