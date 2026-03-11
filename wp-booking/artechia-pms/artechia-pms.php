<?php
/**
 * Plugin Name:       Artechia PMS & Booking
 * Plugin URI:        https://artechia.com/pms
 * Description:       Motor de reservas + PMS completo para hoteles y cabañas. Disponibilidad, tarifas, checkout, calendario, pagos MercadoPago, iCal, reportes.
 * Version:           0.0.1
 * Requires at least: 6.0
 * Requires PHP:      8.1
 * Author:            ArtechIA
 * Author URI:        https://artechia.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       artechia-pms
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* ───────────────────────── Constants ───────────────────────── */

define( 'ARTECHIA_PMS_VERSION',     '0.0.1' );
define( 'ARTECHIA_PMS_DB_VERSION',  '1.8.0' );
define( 'ARTECHIA_PMS_FILE',        __FILE__ );
define( 'ARTECHIA_PMS_DIR',         plugin_dir_path( __FILE__ ) );
define( 'ARTECHIA_PMS_URL',         plugin_dir_url( __FILE__ ) );
define( 'ARTECHIA_PMS_BASENAME',    plugin_basename( __FILE__ ) );
define( 'ARTECHIA_PMS_TEXT_DOMAIN', 'artechia-pms' );
define( 'ARTECHIA_PMS_PREFIX',      'artechia_' );

/* ───────────────────────── Autoloader ──────────────────────── */

require_once ARTECHIA_PMS_DIR . 'includes/class-autoloader.php';
Artechia\PMS\Autoloader::register();

/* ───────────────────── Activation / Deactivation ──────────── */

register_activation_hook( __FILE__, [ 'Artechia\\PMS\\Activator', 'activate' ] );
register_deactivation_hook( __FILE__, [ 'Artechia\\PMS\\Deactivator', 'deactivate' ] );

/* ───────────────────────── Boot ───────────────────────────── */

add_action( 'plugins_loaded', function () {
    // Load text domain.
    load_plugin_textdomain(
        ARTECHIA_PMS_TEXT_DOMAIN,
        false,
        dirname( ARTECHIA_PMS_BASENAME ) . '/languages'
    );

    // Boot the plugin.
    Artechia\PMS\Plugin::instance()->init();
} );

/* ─────────────────────── WP-CLI Commands ──────────────────── */

if ( defined( 'WP_CLI' ) && WP_CLI ) {
    Artechia\PMS\Plugin::register_cli();
}
