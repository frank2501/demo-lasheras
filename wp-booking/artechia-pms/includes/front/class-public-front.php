<?php
/**
 * Public-facing front-end: shortcode registration + asset enqueue.
 */
namespace Artechia\PMS\Front;

use Artechia\PMS\Services\Settings;
use Artechia\PMS\Services\MarketingService;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class PublicFront {

    public function __construct() {
        $this->register_shortcodes();
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_action( 'wp_footer', [ $this, 'inject_icons_sprite' ] );
        add_action( 'template_redirect', [ $this, 'handle_unsubscribe' ] );
    }

    /**
     * Inject SVG sprite into the footer.
     */
    public function inject_icons_sprite(): void {
        include __DIR__ . '/views/icons-sprite.php';
    }

    /**
     * Register all booking-flow shortcodes.
     */
    private function register_shortcodes(): void {
        add_shortcode( 'artechia_search',       [ $this, 'render_search' ] );
        add_shortcode( 'artechia_results',      [ $this, 'render_results' ] );
        add_shortcode( 'artechia_checkout',     [ $this, 'render_checkout' ] );
        add_shortcode( 'artechia_confirmation', [ $this, 'render_confirmation' ] );
        add_shortcode( 'artechia_my_booking',   [ $this, 'render_my_booking' ] );
        add_shortcode( 'artechia_find_booking', [ $this, 'render_find_booking' ] );
    }

    /**
     * Enqueue CSS + JS on pages that have our shortcodes.
     */
    public function enqueue_assets(): void {
        if ( is_admin() ) {
            return;
        }

        // Always enqueue on our pages (lightweight).
        $base = plugin_dir_url( __FILE__ );

        // Flatpickr (bundled locally — no CDN dependency).
        $fp_base = ARTECHIA_PMS_URL . 'includes/vendor/flatpickr/';
        wp_enqueue_style( 'flatpickr', $fp_base . 'flatpickr.min.css', [], '4.6.13' );
        wp_enqueue_script( 'flatpickr', $fp_base . 'flatpickr.min.js', [], '4.6.13', true );
        wp_enqueue_script( 'flatpickr-es', $fp_base . 'l10n/es.js', [ 'flatpickr' ], '4.6.13', true );
        wp_enqueue_script( 'flatpickr-range', $fp_base . 'plugins/rangePlugin.js', [ 'flatpickr' ], '4.6.13', true );

        wp_enqueue_style(
            'artechia-public',
            $base . 'assets/css/public.css',
            [ 'flatpickr' ],
            ARTECHIA_PMS_VERSION
        );

        wp_enqueue_script(
            'artechia-public',
            $base . 'assets/js/public.js',
            [ 'flatpickr', 'flatpickr-es', 'flatpickr-range' ],
            ARTECHIA_PMS_VERSION,
            true
        );
        // Detect pages with shortcodes automatically.
        $search_page_id       = Settings::find_page_id_by_shortcode( '[artechia_search]' );
        $results_page_id      = Settings::find_page_id_by_shortcode( '[artechia_results]' );
        $checkout_page_id     = Settings::find_page_id_by_shortcode( '[artechia_checkout]' );
        $confirmation_page_id = Settings::find_page_id_by_shortcode( '[artechia_confirmation]' );
        $my_booking_page_id   = Settings::find_page_id_by_shortcode( '[artechia_my_booking]' );

        wp_localize_script( 'artechia-public', 'artechiaConfig', [
            'restBase'         => esc_url_raw( rest_url( 'artechia/v1/public/' ) ),
            'nonce'            => wp_create_nonce( 'wp_rest' ),
            'resultsUrl'       => $results_page_id ? get_permalink( $results_page_id ) : '',
            'checkoutUrl'      => $checkout_page_id ? get_permalink( $checkout_page_id ) : '',
            'confirmationUrl'  => $confirmation_page_id ? get_permalink( $confirmation_page_id ) : '',
            'myBookingUrl'     => $my_booking_page_id ? get_permalink( $my_booking_page_id ) : '',
            'currency'         => Settings::currency(),
            'currencySymbol'   => Settings::currency_symbol(),
            'currencyPosition' => Settings::get( 'currency_position', 'before' ),
            'decimals'         => Settings::get( 'decimals', '2' ),
            'decimalSeparator' => Settings::get( 'decimal_separator', ',' ),
            'thousandSeparator'=> Settings::get( 'thousand_separator', '.' ),
            'propertyId'       => absint( Settings::get( 'default_property_id', '1' ) ),
            'checkInTime'      => Settings::check_in_time(),
            'checkOutTime'     => Settings::check_out_time(),
            'bankTransfer'     => [
                'enabled'     => Settings::get( 'enable_bank_transfer' ) === '1',
                'displayMode' => Settings::get( 'bank_transfer_display_mode', 'details' ),
                'bank'        => Settings::get( 'bank_transfer_bank' ),
                'holder'      => Settings::get( 'bank_transfer_holder' ),
                'cbu'         => Settings::get( 'bank_transfer_cbu' ),
                'alias'       => Settings::get( 'bank_transfer_alias' ),
                'cuit'        => Settings::get( 'bank_transfer_cuit' ),
            ],
            'whatsapp' => [
                'number'  => Settings::get( 'whatsapp_number' ),
                'message' => Settings::get( 'whatsapp_message' ),
            ],
            'enableCoupons'         => Settings::get( 'enable_coupons', '1' ) === '1',
            'enableSpecialRequests' => Settings::get( 'enable_special_requests', '1' ) === '1',
            'closureDates'          => Settings::get( 'closure_dates', '' ),
            'termsConditions'       => Settings::get( 'checkout_terms_conditions', '' ),
            'termsConditionsType'   => Settings::get( 'checkout_terms_conditions_type', 'text' ),
            'maxStay'               => $this->get_max_stay( absint( Settings::get( 'default_property_id', '1' ) ) ),
        ] );
    }

    /* ── Shortcode Renderers ────────────────────────── */

    public function render_search( $atts ): string {
        ob_start();
        include __DIR__ . '/views/search.php';
        return ob_get_clean();
    }

    public function render_results( $atts ): string {
        ob_start();
        include __DIR__ . '/views/results.php';
        return ob_get_clean();
    }

    public function render_checkout( $atts ): string {
        ob_start();
        include __DIR__ . '/views/checkout.php';
        return ob_get_clean();
    }

    public function render_confirmation( $atts ): string {
        ob_start();
        include __DIR__ . '/views/confirmation.php';
        return ob_get_clean();
    }

    public function render_my_booking( $atts ): string {
        ob_start();
        include __DIR__ . '/views/my-booking.php';
        return ob_get_clean();
    }

    public function render_find_booking( $atts ): string {
        ob_start();
        include __DIR__ . '/views/find-booking.php';
        return ob_get_clean();
    }

    /* ── Helpers ─────────────────────────────────────── */

    /**
     * Get the maximum max_stay value across all active rate plans for a property.
     */
    private function get_max_stay( int $property_id ): int {
        global $wpdb;
        $table = $wpdb->prefix . 'artechia_rate_plans';
        $val = $wpdb->get_var( $wpdb->prepare(
            "SELECT MAX(max_stay) FROM {$table} WHERE property_id = %d AND status = 'active'",
            $property_id
        ) );
        return $val ? (int) $val : 30;
    }

    /**
     * Handle /artechia-unsubscribe requests.
     */
    public function handle_unsubscribe(): void {
        $request_path = trim( parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ), '/' );
        if ( $request_path !== 'artechia-unsubscribe' ) {
            return;
        }

        $email = sanitize_email( $_GET['email'] ?? '' );
        $token = sanitize_text_field( $_GET['token'] ?? '' );

        $success = false;
        $message = '';

        if ( empty( $email ) || empty( $token ) ) {
            $message = 'Enlace inválido. Faltan parámetros.';
        } elseif ( ! MarketingService::verify_unsubscribe_token( $email, $token ) ) {
            $message = 'Enlace inválido o expirado.';
        } else {
            $guest_repo = new \Artechia\PMS\Repositories\GuestRepository();
            $guest = $guest_repo->find_by_email( $email );

            if ( ! $guest ) {
                $message = 'No se encontró una cuenta con este email.';
            } elseif ( ! empty( $guest['marketing_opt_out'] ) ) {
                $success = true;
                $message = 'Ya estabas dado de baja de nuestras comunicaciones.';
            } else {
                $guest_repo->update( (int) $guest['id'], [ 'marketing_opt_out' => 1 ] );
                $success = true;
                $message = 'Te has dado de baja exitosamente. No recibirás más emails promocionales.';
                \Artechia\PMS\Logger::info( 'marketing.unsubscribe', "Guest {$email} opted out.", 'marketing', (int) $guest['id'] );
            }
        }

        status_header( 200 );
        nocache_headers();
        include __DIR__ . '/views/unsubscribe.php';
        exit;
    }
}
