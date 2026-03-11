<?php
/**
 * Clean uninstall: remove all plugin data.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Autoloader.
require_once __DIR__ . '/includes/class-autoloader.php';
Artechia\PMS\Autoloader::register();

// Define constants needed.
if ( ! defined( 'ARTECHIA_PMS_DIR' ) ) {
    define( 'ARTECHIA_PMS_DIR', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'ARTECHIA_PMS_PREFIX' ) ) {
    define( 'ARTECHIA_PMS_PREFIX', 'artechia_' );
}
if ( ! defined( 'ARTECHIA_PMS_DB_VERSION' ) ) {
    define( 'ARTECHIA_PMS_DB_VERSION', '1.0.0' );
}

// Drop all tables.
Artechia\PMS\DB\Migrator::drop_all();

// Remove roles.
Artechia\PMS\Roles::remove();

// Remove options.
$options = [
    'artechia_pms_db_version',
    'artechia_pms_debug_mode',
    'artechia_pms_search_page_id',
    'artechia_pms_results_page_id',
    'artechia_pms_checkout_page_id',
    'artechia_pms_confirmation_page_id',
    'artechia_pms_my_booking_page_id',
];

foreach ( $options as $opt ) {
    delete_option( $opt );
}

// Optionally delete created pages.
$page_options = [
    'artechia_pms_search_page_id',
    'artechia_pms_results_page_id',
    'artechia_pms_checkout_page_id',
    'artechia_pms_confirmation_page_id',
    'artechia_pms_my_booking_page_id',
];

foreach ( $page_options as $opt ) {
    $page_id = get_option( $opt, 0 );
    if ( $page_id ) {
        wp_delete_post( $page_id, true );
    }
}

// Clear transients.
global $wpdb;
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_artechia_%'" );
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_artechia_%'" );
