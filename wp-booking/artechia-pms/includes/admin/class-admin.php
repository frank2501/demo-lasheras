<?php
/**
 * Admin area controller: registers menus, enqueues assets, routes pages.
 */

namespace Artechia\PMS\Admin;

use Artechia\PMS\Roles;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Admin {

    public function init(): void {
        add_action( 'admin_menu', [ $this, 'register_menus' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_action( 'admin_post_artechia_export_payments', [ $this, 'export_payments_csv' ] );
        add_action( 'admin_post_artechia_export_report', [ $this, 'export_report_csv' ] );
    }

    /**
     * Handle CSV export of payments.
     */
    public function export_payments_csv(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }

        global $wpdb;
        $table = \Artechia\PMS\DB\Schema::table( 'payments' );

        // Reuse filters from GET
        $status_filter = sanitize_text_field( $_GET['status'] ?? '' );
        $date_from     = sanitize_text_field( $_GET['date_from'] ?? '' );
        $date_to       = sanitize_text_field( $_GET['date_to'] ?? '' );

        $where = [ '1=1' ];
        $args  = [];

        if ( $status_filter ) {
            $where[] = 'status = %s';
            $args[]  = $status_filter;
        }
        if ( $date_from ) {
            $where[] = 'created_at >= %s';
            $args[]  = $date_from . ' 00:00:00';
        }
        if ( $date_to ) {
            $where[] = 'created_at <= %s';
            $args[]  = $date_to . ' 23:59:59';
        }

        $query = "SELECT * FROM {$table} WHERE " . implode( ' AND ', $where ) . " ORDER BY created_at DESC";
        if ( ! empty( $args ) ) {
            $query = $wpdb->prepare( $query, ...$args );
        }

        $rows = $wpdb->get_results( $query, \ARRAY_A );

        header( 'Content-Type: text/csv' );
        header( 'Content-Disposition: attachment; filename="pagos_' . date( 'Y-m-d' ) . '.csv"' );

        $out = fopen( 'php://output', 'w' );
        fputcsv( $out, [ 'ID', 'Booking ID', 'Gateway', 'Transaction ID', 'Amount', 'Currency', 'Mode', 'Status', 'Date' ] );

        foreach ( $rows as $row ) {
            fputcsv( $out, [
                $row['id'],
                $row['booking_id'],
                $row['gateway'],
                $row['gateway_txn_id'],
                $row['amount'],
                $row['currency'],
                $row['pay_mode'],
                $row['status'],
                $row['created_at'],
            ] );
        }

        fclose( $out );
        exit;
    }

    /**
     * Handle CSV export of reports (Occupancy/Financial).
     */
    public function export_report_csv(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }

        $type  = sanitize_text_field( $_GET['type'] ?? '' );
        $start = sanitize_text_field( $_GET['start'] ?? '' );
        $end   = sanitize_text_field( $_GET['end'] ?? '' );

        $service = new \Artechia\PMS\Services\ReportService();

        if ( $type === 'occupancy' ) {
            $data = $service->get_occupancy_report( $start, $end );
            header( 'Content-Type: text/csv' );
            header( 'Content-Disposition: attachment; filename="reporte_ocupacion_' . $start . '_to_' . $end . '.csv"' );
            $out = fopen( 'php://output', 'w' );
            fputcsv( $out, [ 'Metrica', 'Valor' ] );
            fputcsv( $out, [ 'Inicio', $data['start'] ] );
            fputcsv( $out, [ 'Fin', $data['end'] ] );
            fputcsv( $out, [ 'Habitaciones Totales', $data['total_rooms'] ] );
            fputcsv( $out, [ 'Noches Vendidas', $data['nights_sold'] ] );
            fputcsv( $out, [ 'Ocupacion %', $data['occupancy_pct'] . '%' ] );
            fputcsv( $out, [ 'ADR', $data['adr'] ] );
            fputcsv( $out, [ 'RevPAR', $data['revpar'] ] );
            fputcsv( $out, [ 'Ingresos Est.', $data['revenue_generated'] ] );
            fclose( $out );
        } elseif ( $type === 'financial' ) {
            $data = $service->get_financial_report( $start, $end );
            header( 'Content-Type: text/csv' );
            header( 'Content-Disposition: attachment; filename="reporte_financiero_' . $start . '_to_' . $end . '.csv"' );
            $out = fopen( 'php://output', 'w' );
            fputcsv( $out, [ 'Pasarela', 'Metodo', 'Transacciones', 'Total' ] );
            foreach ( $data['breakdown'] as $row ) {
                fputcsv( $out, [ $row['gateway'], $row['pay_mode'], $row['txn_count'], $row['total'] ] );
            }
            fputcsv( $out, [ '', '', 'TOTAL COLLECTED', $data['total_collected'] ] );
            fclose( $out );
        }

        exit;
    }

    /**
     * Register the admin menu and submenus.
     */
    public function register_menus(): void {

        // Top-level menu.
        add_menu_page(
            __( 'Artechia PMS', 'artechia-pms' ),
            __( 'Artechia PMS', 'artechia-pms' ),
            'artechia_view_calendar',
            'artechia-pms',
            [ $this, 'render_dashboard' ],
            'dashicons-building',
            26
        );

        // Dashboard (same slug as top-level → default landing).
        add_submenu_page(
            'artechia-pms',
            __( 'Inicio', 'artechia-pms' ),
            __( 'Inicio', 'artechia-pms' ),
            'artechia_view_calendar',
            'artechia-pms',
            [ $this, 'render_dashboard' ]
        );

        // Reservations.
        add_submenu_page(
            'artechia-pms',
            __( 'Reservas', 'artechia-pms' ),
            __( 'Reservas', 'artechia-pms' ),
            'artechia_manage_bookings',
            'artechia-reservations',
            [ $this, 'render_page' ]
        );

        // Calendar.
        add_submenu_page(
            'artechia-pms',
            __( 'Calendario', 'artechia-pms' ),
            __( 'Calendario', 'artechia-pms' ),
            'artechia_view_calendar',
            'artechia-calendar',
            [ $this, 'render_page' ]
        );

        // Property (unified: property + room types + room units).
        add_submenu_page(
            'artechia-pms',
            __( 'Propiedad', 'artechia-pms' ),
            __( 'Propiedad', 'artechia-pms' ),
            'manage_artechia_properties',
            'artechia-property',
            [ $this, 'render_page' ]
        );


        // Rate Plans.
        add_submenu_page(
            'artechia-pms',
            __( 'Planes Tarifarios', 'artechia-pms' ),
            __( 'Planes Tarifarios', 'artechia-pms' ),
            'artechia_manage_rates',
            'artechia-rate-plans',
            [ $this, 'render_page' ]
        );



        // Extras.
        add_submenu_page(
            'artechia-pms',
            __( 'Extras / Servicios', 'artechia-pms' ),
            __( 'Extras / Servicios', 'artechia-pms' ),
            'artechia_manage_extras',
            'artechia-extras',
            [ $this, 'render_page' ]
        );

        // Guests.
        add_submenu_page(
            'artechia-pms',
            __( 'Huéspedes', 'artechia-pms' ),
            __( 'Huéspedes', 'artechia-pms' ),
            'artechia_manage_guests',
            'artechia-guests',
            [ $this, 'render_page' ]
        );


        // Coupons (Now Promociones).
        add_submenu_page(
            'artechia-pms',
            __( 'Promociones', 'artechia-pms' ),
            __( 'Promociones', 'artechia-pms' ),
            'artechia_manage_coupons',
            'artechia-coupons',
            [ $this, 'render_page' ]
        );

        // Payments.
        add_submenu_page(
            'artechia-pms',
            __( 'Pagos', 'artechia-pms' ),
            __( 'Pagos', 'artechia-pms' ),
            'artechia_manage_payments',
            'artechia-payments',
            [ $this, 'render_page' ]
        );

        // iCal Feeds.
        add_submenu_page(
            'artechia-pms',
            __( 'iCal / Canales', 'artechia-pms' ),
            __( 'iCal / Canales', 'artechia-pms' ),
            'artechia_manage_ical',
            'artechia-ical',
            [ $this, 'render_page' ]
        );

        // Email Templates.
        add_submenu_page(
            'artechia-pms',
            __( 'Emails', 'artechia-pms' ),
            __( 'Emails', 'artechia-pms' ),
            'artechia_manage_email_tpl',
            'artechia-emails',
            [ $this, 'render_page' ]
        );

        // Reports.
        add_submenu_page(
            'artechia-pms',
            __( 'Reportes', 'artechia-pms' ),
            __( 'Reportes', 'artechia-pms' ),
            'artechia_view_reports',
            'artechia-reports',
            [ $this, 'render_page' ]
        );

        // Settings.
        add_submenu_page(
            'artechia-pms',
            __( 'Ajustes', 'artechia-pms' ),
            __( 'Ajustes', 'artechia-pms' ),
            'manage_artechia_settings',
            'artechia-settings',
            [ $this, 'render_settings' ]
        );

        // Logs.
        add_submenu_page(
            'artechia-pms',
            __( 'Logs', 'artechia-pms' ),
            __( 'Logs', 'artechia-pms' ),
            'artechia_view_logs',
            'artechia-logs',
            [ $this, 'render_logs' ]
        );
    }

    /**
     * Enqueue admin styles and scripts only on plugin pages.
     */
    public function enqueue_assets( string $hook ): void {
        // Only load on our pages — check hook suffix AND $_GET['page'] fallback.
        $page = sanitize_text_field( wp_unslash( $_GET['page'] ?? '' ) );
        $is_our_page = ( strpos( $hook, 'artechia' ) !== false )
                     || ( strpos( $page, 'artechia-' ) === 0 );

        if ( ! $is_our_page ) {
            return;
        }

        wp_enqueue_style(
            'artechia-pms-admin',
            ARTECHIA_PMS_URL . 'admin/assets/css/admin.css',
            [],
            ARTECHIA_PMS_VERSION
        );

        // Flatpickr (bundled locally — no CDN dependency)
        $fp_base = ARTECHIA_PMS_URL . 'includes/vendor/flatpickr/';
        wp_enqueue_style( 'flatpickr', $fp_base . 'flatpickr.min.css', [], '4.6.13' );
        wp_enqueue_script( 'flatpickr', $fp_base . 'flatpickr.min.js', [], '4.6.13', true );

        wp_enqueue_script(
            'artechia-pms-admin',
            ARTECHIA_PMS_URL . 'admin/assets/js/admin.js',
            [],
            ARTECHIA_PMS_VERSION,
            false // Must load in <head> so wp_localize_script data is available for inline scripts in views.
        );

        // Enqueue WP Pointers for help tips
        wp_enqueue_style( 'wp-pointer' );
        wp_enqueue_script( 'wp-pointer' );

        // Enqueue Media Uploader
        wp_enqueue_media();

        wp_localize_script( 'artechia-pms-admin', 'artechiaPMS', [
            'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
            'restUrl'  => rest_url( 'artechia/v1/' ),
            'nonce'    => wp_create_nonce( 'wp_rest' ),
            'adminUrl' => admin_url(),
            'format'   => [
                'currency_symbol'    => \Artechia\PMS\Services\Settings::currency_symbol(),
                'decimals'           => (int) \Artechia\PMS\Services\Settings::get( 'decimals', '2' ),
                'decimal_separator'  => \Artechia\PMS\Services\Settings::get( 'decimal_separator', ',' ),
                'thousand_separator' => \Artechia\PMS\Services\Settings::get( 'thousand_separator', '.' ),
                'currency_position'  => \Artechia\PMS\Services\Settings::get( 'currency_position', 'before' ),
                'date_format'        => \Artechia\PMS\Services\Settings::get( 'date_format', 'd/m/Y' ),
            ],
            'i18n'     => [
                'saved'          => __( 'Guardado', 'artechia-pms' ),
                'error'          => __( 'Error al guardar', 'artechia-pms' ),
            ],
            'settings' => [
                'marketing_enabled' => \Artechia\PMS\Services\Settings::get( 'marketing_enabled', '0' ),
                'logo_url'          => \Artechia\PMS\Services\Settings::get( 'logo_url', '' ),
            ],
        ] );

        // Calendar-specific assets.
        if ( $page === 'artechia-calendar' || strpos( $hook, 'artechia-calendar' ) !== false ) {
            wp_enqueue_style(
                'artechia-calendar-css',
                ARTECHIA_PMS_URL . 'admin/assets/css/calendar.css',
                [],
                ARTECHIA_PMS_VERSION
            );

            wp_enqueue_script(
                'artechia-calendar-js',
                ARTECHIA_PMS_URL . 'admin/assets/js/calendar.js',
                [ 'jquery' ], // although using vanilla JS, good practice to keep dep if needed later
                ARTECHIA_PMS_VERSION,
                true
            );

            wp_localize_script( 'artechia-calendar-js', 'artechia_pms_vars', [
                'api_root'    => rest_url(),
                'nonce'       => wp_create_nonce( 'wp_rest' ),
                'property_id' => 1, // Default for now
            ] );
        }
        // Reports-specific assets.
        if ( $page === 'artechia-reports' || strpos( $hook, 'artechia-reports' ) !== false ) {
            wp_enqueue_style(
                'artechia-reports-css',
                ARTECHIA_PMS_URL . 'admin/assets/css/reports.css',
                [],
                ARTECHIA_PMS_VERSION
            );

            wp_enqueue_script(
                'artechia-reports-js',
                ARTECHIA_PMS_URL . 'admin/assets/js/reports.js',
                [],
                ARTECHIA_PMS_VERSION,
                true
            );
        }
    }

    /**
     * Render the dashboard page.
     */
    public function render_dashboard(): void {
        include ARTECHIA_PMS_DIR . 'admin/views/dashboard.php';
    }

    /**
     * Render a generic admin page based on the current slug.
     */
    public function render_page(): void {
        $page = sanitize_file_name( str_replace( 'artechia-', '', $_GET['page'] ?? '' ) );
        $file = ARTECHIA_PMS_DIR . 'admin/views/' . $page . '.php';

        if ( file_exists( $file ) ) {
            include $file;
        } else {
            echo '<div class="wrap"><h1>' . esc_html__( 'Próximamente', 'artechia-pms' ) . '</h1>';
            echo '<p>' . esc_html__( 'Esta sección estará disponible en un próximo hito.', 'artechia-pms' ) . '</p></div>';
        }
    }

    /**
     * Render the settings page.
     */
    public function render_settings(): void {
        include ARTECHIA_PMS_DIR . 'admin/views/settings.php';
    }

    /**
     * Render the logs page.
     */
    public function render_logs(): void {
        include ARTECHIA_PMS_DIR . 'admin/views/logs.php';
    }
}
