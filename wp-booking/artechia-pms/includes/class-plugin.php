<?php
/**
 * Main plugin orchestrator.
 */

namespace Artechia\PMS;

use Artechia\PMS\DB\Migrator;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Plugin {

    private static ?Plugin $instance = null;

    public static function instance(): Plugin {
        if ( self::$instance === null ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Initialize all plugin components.
     */
    public function init(): void {
        // Run migrations if needed.
        Migrator::maybe_migrate();

        // Boot runtime capability filters (admin pages + manage_options fallback).
        Roles::boot();

        // Register custom cron intervals (must happen before scheduling).
        add_filter( 'cron_schedules', function ( array $schedules ): array {
            $schedules['five_minutes'] = [
                'interval' => 300,
                'display'  => __( 'Every 5 Minutes', 'artechia-pms' ),
            ];

            // Register dynamic iCal sync schedule based on setting
            $ical_freq_mins = (int) Services\Settings::get( 'ical_sync_frequency', '15' );
            if ( $ical_freq_mins < 5 ) {
                $ical_freq_mins = 15; // fallback
            }
            $schedules["artechia_ical_{$ical_freq_mins}m"] = [
                'interval' => $ical_freq_mins * 60,
                'display'  => sprintf( __( 'iCal Sync (%d min)', 'artechia-pms' ), $ical_freq_mins ),
            ];
            
            return $schedules;
        } );

        // Schedule cron events.
        $this->schedule_crons();

        // Admin area.
        if ( is_admin() ) {
            $admin = new Admin\Admin();
            $admin->init();

            // Auto-ensure pages are linked on admin load (idempotent).
            add_action( 'admin_init', [ Activator::class, 'on_admin_init' ] );
        }

        // Front-end shortcodes (registered globally to support editor previews).
        new Front\PublicFront();

        // REST API.
        add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );

        // Email open tracking — intercept very early, before any output buffering.
        add_action( 'init', [ $this, 'handle_email_tracking' ], 1 );
    }

    /**
     * Handle email open tracking via a single query parameter.
     * URL format: /?artechia_open=TOKEN (avoids &amp; HTML encoding issues).
     * This runs at 'init' priority 1, before any theme or REST API output.
     */
    public function handle_email_tracking(): void {
        if ( empty( $_GET['artechia_open'] ) ) {
            return;
        }

        $token = sanitize_text_field( wp_unslash( $_GET['artechia_open'] ) );

        $json = base64_decode( $token, true );
        if ( ! $json ) {
            $this->serve_pixel();
            return;
        }
        $data = json_decode( $json, true );
        if ( ! $data || empty( $data['c'] ) || empty( $data['e'] ) ) {
            $this->serve_pixel();
            return;
        }

        global $wpdb;
        $log_table = DB\Schema::table( 'audit_log' );

        // Deduplicate by campaign + email in context_json
        $exists = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$log_table} WHERE event_type = 'emailopen' AND context_json LIKE %s AND context_json LIKE %s",
            '%' . $wpdb->esc_like( $data['c'] ) . '%',
            '%' . $wpdb->esc_like( $data['e'] ) . '%'
        ) );

        if ( ! $exists ) {
            Logger::info( 'email.open', "Open: {$data['e']} (campaign: {$data['c']})", 'marketing', null, [
                'campaign_id' => $data['c'],
                'email'       => $data['e'],
            ] );
        }

        $this->serve_pixel();
    }

    /**
     * Output a 1x1 transparent GIF and exit.
     */
    private function serve_pixel(): void {
        $pixel = base64_decode( 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7' );
        status_header( 200 );
        header( 'Content-Type: image/gif' );
        header( 'Content-Length: ' . strlen( $pixel ) );
        header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0' );
        header( 'Expires: 0' );
        echo $pixel;
        exit;
    }

    /**
     * Schedule recurring cron jobs.
     */
    private function schedule_crons(): void {
        // Lock cleanup every 5 minutes.
        if ( ! wp_next_scheduled( 'artechia_pms_cleanup_locks' ) ) {
            wp_schedule_event( time(), 'five_minutes', 'artechia_pms_cleanup_locks' );
        }
        add_action( 'artechia_pms_cleanup_locks', [ $this, 'cleanup_expired_locks' ] );

        // iCal sync (configurable frequency).
        $ical_freq_mins = (int) Services\Settings::get( 'ical_sync_frequency', '15' );
        if ( $ical_freq_mins < 5 ) {
            $ical_freq_mins = 15;
        }
        $recurrence = "artechia_ical_{$ical_freq_mins}m";

        $scheduled = wp_next_scheduled( 'artechia_pms_ical_sync' );
        
        if ( ! $scheduled ) {
            wp_schedule_event( time(), $recurrence, 'artechia_pms_ical_sync' );
        } else {
            // Check if the frequency has changed
            $event = wp_get_scheduled_event( 'artechia_pms_ical_sync' );
            if ( $event && $event->schedule !== $recurrence ) {
                wp_clear_scheduled_hook( 'artechia_pms_ical_sync' );
                wp_schedule_event( time(), $recurrence, 'artechia_pms_ical_sync' );
            }
        }
        
        add_action( 'artechia_pms_ical_sync', [ $this, 'run_ical_sync' ] );

        // Async email sending.
        add_action( 'artechia_pms_send_email_async', [ Services\EmailService::class, 'handle_async_email' ], 10, 3 );
    }

    /**
     * Delete expired availability locks.
     */
    public function cleanup_expired_locks(): void {
        global $wpdb;
        $table = DB\Schema::table( 'locks' );
        $now   = current_time( 'mysql', true );

        $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$table} WHERE expires_at < %s AND booking_id IS NULL",
            $now
        ) );

        // Also cleanup expired 'hold' bookings
        $bookings_table = DB\Schema::table( 'bookings' );
        $timeout_mins   = (int) Services\Settings::get( 'mercadopago_timeout_minutes', '15' );
        $timeout_sec    = max( 60, $timeout_mins * 60 ); // Min 1 minute
        $expired_time   = date( 'Y-m-d H:i:s', time() - $timeout_sec );
        
        $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$bookings_table} WHERE status = 'hold' AND created_at < %s",
            $expired_time
        ) );
    }

    /**
     * Run iCal sync for all properties.
     */
    public function run_ical_sync(): void {
        $service = new Services\ICalService();
        $service->sync_all();
    }

    /**
     * Register REST API routes.
     */
    public function register_rest_routes(): void {
        ( new Admin\RestAdmin() )->register_routes();
        ( new RestPublic() )->register_routes();
        RestWebhookMercadoPago::register_routes();

        // Self-check endpoint (admin-only).
        register_rest_route( 'artechia/v1', 'admin/self-check', [
            'methods'             => 'GET',
            'callback'            => function () {
                $check = new Services\SelfCheck();
                return new \WP_REST_Response( $check->run(), 200 );
            },
            'permission_callback' => [ $this, 'check_admin_permission' ],
        ] );

        // Public health ping — { ok, timestamp } only.
        register_rest_route( 'artechia/v1', 'health', [
            'methods'             => 'GET',
            'callback'            => function () {
                $check = new Services\HealthCheck();
                return new \WP_REST_Response( $check->run_public(), 200 );
            },
            'permission_callback' => '__return_true',
        ] );

        // Admin health check — full diagnostics.
        register_rest_route( 'artechia/v1', 'admin/health', [
            'methods'             => 'GET',
            'callback'            => function () {
                $check = new Services\HealthCheck();
                return new \WP_REST_Response( $check->run_admin(), 200 );
            },
            'permission_callback' => [ $this, 'check_admin_permission' ],
        ] );
    }

    /**
     * Shared permission callback for admin-only REST endpoints.
     *
     * WordPress cookie authentication requires a valid nonce
     * (X-WP-Nonce header or _wpnonce query parameter).  Without it, WP
     * intentionally does NOT authenticate the user from cookies (CSRF
     * protection), so current_user_can() returns false even with valid
     * login cookies.
     *
     * From JavaScript, send the nonce provided by artechiaPMS.nonce:
     *   fetch( url, { headers: { 'X-WP-Nonce': artechiaPMS.nonce } } )
     *
     * For browser testing, append ?_wpnonce=<value> to the URL.
     *
     * @return true|\WP_Error
     */
    public function check_admin_permission() {
        // Diagnostic logging (WP_DEBUG only).
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            $logged_in  = is_user_logged_in();
            $uid        = get_current_user_id();
            $can_manage = current_user_can( 'manage_options' );
            error_log( sprintf(
                '[Artechia PMS][REST auth] endpoint=%s logged_in=%s uid=%d manage_options=%s',
                sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) ),
                $logged_in ? 'yes' : 'no',
                $uid,
                $can_manage ? 'yes' : 'no'
            ) );
        }

        if ( current_user_can( 'manage_options' ) ) {
            return true;
        }

        Logger::warning( 'rest.permission_denied', "REST access denied for {$_SERVER['REQUEST_URI']}. Required: manage_options" );

        return new \WP_Error(
            'rest_forbidden',
            __(
                'You need manage_options capability. If you are logged in, ensure the request includes a valid nonce (X-WP-Nonce header or _wpnonce parameter) and uses the same scheme/host as wp-admin.',
                'artechia-pms'
            ),
            [ 'status' => 401 ]
        );
    }

    /**
     * Register WP-CLI commands.
     */
    public static function register_cli(): void {
        CLI::register();
    }
}
