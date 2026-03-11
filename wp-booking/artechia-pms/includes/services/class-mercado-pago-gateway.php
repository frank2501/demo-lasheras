<?php
/**
 * MercadoPago gateway: create preferences, fetch payments, verify webhooks.
 * Uses wp_remote_* — no SDK dependency.
 */

namespace Artechia\PMS\Services;

use Artechia\PMS\Logger;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class MercadoPagoGateway {

    private const API_BASE = 'https://api.mercadopago.com';

    /* ── Configuration ──────────────────────────────── */

    /**
     * Get the correct access token based on sandbox mode.
     */
    public static function get_access_token(): string {
        $sandbox = (bool) Settings::get( 'mercadopago_sandbox', '1' );
        if ( $sandbox ) {
            $token = Settings::get( 'mercadopago_access_token_test', '' );
            // Fallback to production token if test token is empty.
            return $token ?: Settings::get( 'mercadopago_access_token', '' );
        }
        return Settings::get( 'mercadopago_access_token', '' );
    }

    /**
     * Check if MercadoPago is enabled and configured.
     */
    public static function is_enabled(): bool {
        return (bool) Settings::get( 'mercadopago_enabled', '0' )
            && self::get_access_token() !== '';
    }

    /* ── Preference (Checkout Pro) ──────────────────── */

    /**
     * Create a Checkout Pro preference for a booking.
     *
     * @param array  $booking  Booking row from DB.
     * @param string $pay_mode 'deposit' or 'total'.
     * @param float  $amount   Pre-calculated amount to charge.
     * @return array{ preference_id, init_point, sandbox_init_point } | array{ error, message }
     */
    public static function create_preference( array $booking, string $pay_mode, float $amount ): array {
        $token = self::get_access_token();
        if ( ! $token ) {
            return [ 'error' => 'MP_NOT_CONFIGURED', 'message' => 'MercadoPago not configured.' ];
        }

        $sandbox = (bool) Settings::get( 'mercadopago_sandbox', '1' );
        $currency = Settings::currency();
        
        $property_id   = (int) ( $booking['property_id'] ?? 0 );
        $property_name = self::get_property_name( $property_id );
        $booking_code  = $booking['booking_code'] ?? 'N/A';

        // Build webhook notification URL.
        $notification_url = rest_url( 'artechia/v1/webhooks/mercadopago' );

        // Build back URLs (informational only — never confirm from these).
        // If it's the initial payment (pay_mode === 'deposit' or 'total' on a new booking),
        // we prefer the confirmation page. If it's a balance payment, we stay in "My Booking".
        $redirect_url = ( $pay_mode === 'balance' ) 
            ? self::build_my_booking_url( $booking['booking_code'], $booking['access_token'] ?? '' )
            : self::build_confirmation_url( $booking );

        $back_urls = [
            'success' => $redirect_url,
            'failure' => $redirect_url,
            'pending' => $redirect_url,
        ];

        // Title.
        $title_suffix = match ( $pay_mode ) {
            'deposit' => 'Seña',
            'balance' => 'Saldo',
            default   => 'Total',
        };

        $title = sprintf(
            '%s — Reserva %s (%s)',
            $property_name,
            $booking['booking_code'],
            $title_suffix
        );

        $is_sandbox = ( strpos( $token, 'TEST-' ) === 0 );

        // Phase 4: Extreme Sandboxing
        // 1. Randomize external_reference to avoid duplicate attempt flags in MP.
        $final_reference = $is_sandbox ? $booking_code . '_' . bin2hex( random_bytes( 4 ) ) : $booking_code;

        // 2. Minimum amount for sandbox (Floor cards sometimes reject small amounts like $1)
        $unit_price = (float) round( max( $amount, 1 ), 2 );
        if ( $is_sandbox ) {
            $unit_price = max( $unit_price, 150.0 );
        }

        $body = [
            'items' => [
                [
                    'title'       => $title,
                    'quantity'    => 1,
                    'unit_price'  => $unit_price,
                    'currency_id' => $currency,
                ],
            ],
            'external_reference' => $final_reference,
            'back_urls'          => $back_urls,
            'auto_return'        => 'all',
            'binary_mode'        => false,
            'notification_url'   => $notification_url,
            'metadata'           => [
                'pay_mode' => $pay_mode,
            ],
        ];

        $response = wp_remote_post( self::API_BASE . '/checkout/preferences', [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'application/json',
            ],
            'body'    => wp_json_encode( $body ),
            'timeout' => 15,
        ] );

        if ( is_wp_error( $response ) ) {
            Logger::error( 'mp.preference_failed', 'HTTP error creating preference', 'booking', $booking['id'], [
                'error'    => $response->get_error_message(),
                'response' => $response,
                'payload'  => wp_json_encode( $body ),
            ] );
            return [ 'error' => 'MP_HTTP_ERROR', 'message' => 'Could not connect to MercadoPago.' ];
        }

        $code = wp_remote_retrieve_response_code( $response );
        $data = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code < 200 || $code >= 300 || empty( $data['id'] ) ) {
            Logger::error( 'mp.preference_failed', 'MP API returned error', 'booking', $booking['id'], [
                'http_code' => $code,
                'response'  => $data,
            ] );
            return [ 'error' => 'MP_API_ERROR', 'message' => $data['message'] ?? 'MercadoPago error.' ];
        }

        Logger::info( 'mp.preference_created', "Preference {$data['id']} for booking {$booking['booking_code']}", 'booking', $booking['id'], [
            'preference_id' => $data['id'],
            'amount'        => $amount,
            'pay_mode'      => $pay_mode,
        ] );

        return [
            'preference_id'      => $data['id'],
            'init_point'         => $is_sandbox ? ( $data['sandbox_init_point'] ?? $data['init_point'] ) : $data['init_point'],
            'sandbox_init_point' => $data['sandbox_init_point'] ?? '',
        ];
    }

    /* ── Payment Fetch ──────────────────────────────── */

    /**
     * Fetch a payment from MercadoPago API by payment ID.
     *
     * @return array|null Payment data or null on error.
     */
    public static function fetch_payment( string $payment_id ): ?array {
        $token = self::get_access_token();
        if ( ! $token ) {
            return null;
        }

        $response = wp_remote_get( self::API_BASE . '/v1/payments/' . $payment_id, [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
            ],
            'timeout' => 15,
        ] );

        if ( is_wp_error( $response ) ) {
            Logger::error( 'mp.fetch_failed', "HTTP error fetching payment {$payment_id}", [
                'error' => $response->get_error_message(),
            ] );
            return null;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $data = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code !== 200 || empty( $data['id'] ) ) {
            Logger::error( 'mp.fetch_failed', "MP API returned {$code} for payment {$payment_id}" );
            return null;
        }

        return $data;
    }

    /* ── Webhook Signature Verification ─────────────── */

    /**
     * Verify the x-signature header from a MercadoPago webhook notification.
     *
     * @param string $x_signature   Raw x-signature header value.
     * @param string $x_request_id  x-request-id header value.
     * @param string $data_id       data.id query parameter.
     * @return bool True if signature is valid.
     */
    public static function verify_webhook_signature( string $x_signature, string $x_request_id, string $data_id ): bool {
        $secret = Settings::get( 'mercadopago_webhook_secret', '' );
        if ( ! $secret ) {
            // No secret configured — cannot verify signature.
            Logger::warning( 'mp.webhook_no_secret', 'Webhook secret not configured, signature verification failed' );
            return false;
        }

        // Parse x-signature: "ts=123456789,v1=abc123..."
        $parts = [];
        foreach ( explode( ',', $x_signature ) as $segment ) {
            $kv = explode( '=', $segment, 2 );
            if ( count( $kv ) === 2 ) {
                $parts[ trim( $kv[0] ) ] = trim( $kv[1] );
            }
        }

        $ts = $parts['ts'] ?? '';
        $v1 = $parts['v1'] ?? '';

        if ( ! $ts || ! $v1 ) {
            Logger::warning( 'mp.webhook_sig_invalid', 'Missing ts or v1 in x-signature' );
            return false;
        }

        // Build manifest string.
        $manifest = "id:{$data_id};request-id:{$x_request_id};ts:{$ts};";

        // Compute HMAC-SHA256.
        $computed = hash_hmac( 'sha256', $manifest, $secret );

        if ( ! hash_equals( $computed, $v1 ) ) {
            Logger::warning( 'mp.webhook_sig_mismatch', 'Webhook signature mismatch', [
                'expected' => $v1,
                'computed' => $computed,
            ] );
            return false;
        }

        return true;
    }

    /**
     * Map MercadoPago status to our internal payment status.
     */
    public static function map_payment_status( string $mp_status ): string {
        return match ( $mp_status ) {
            'approved'                   => 'approved',
            'pending', 'in_process',
            'authorized'                 => 'pending',
            'rejected'                   => 'rejected',
            'cancelled'                  => 'cancelled',
            'refunded'                   => 'refunded',
            'charged_back'               => 'charged_back',
            default                      => 'pending',
        };
    }

    /**
     * Record or update a payment from MercadoPago data.
     */
    public static function process_payment_update( int $booking_id, array $mp_payment ): int|false {
        $payment_id    = (string) ( $mp_payment['id'] ?? '' );
        $mp_status     = (string) ( $mp_payment['status'] ?? '' );
        $mp_amount     = (float)  ( $mp_payment['transaction_amount'] ?? 0 );
        $mp_currency   = (string) ( $mp_payment['currency_id'] ?? 'ARS' );
        $mp_pay_mode   = (string) ( $mp_payment['metadata']['pay_mode'] ?? 'total' );
        
        $idem_key = 'mp:' . $payment_id;
        $payments = new \Artechia\PMS\Repositories\PaymentRepository();
        $existing = $payments->find_by_idempotency_key( $idem_key );

        $payment_data = [
            'booking_id'      => $booking_id,
            'gateway'         => 'mercadopago',
            'gateway_txn_id'  => $payment_id,
            'amount'          => $mp_amount,
            'currency'        => $mp_currency,
            'pay_mode'        => $mp_pay_mode,
            'status'          => self::map_payment_status( $mp_status ),
            'gateway_data'    => wp_json_encode( $mp_payment ),
            'idempotency_key' => $idem_key,
        ];

        if ( $existing ) {
            $payments->update( (int) $existing['id'], $payment_data );
            return (int) $existing['id'];
        } else {
            return $payments->create( $payment_data );
        }
    }

    /* ── Helpers ─────────────────────────────────────── */

    /**
     * Calculate the payment amount based on pay_mode.
     *
     * @param array  $booking   Booking row.
     * @param string $pay_mode  'deposit' or 'total'.
     * @param float  $paid      Amount already paid.
     * @return float Amount to charge (>= 1.00).
     */
    public static function calculate_amount( array $booking, string $pay_mode, float $paid = 0.0 ): float {
        $grand_total = (float) ( $booking['grand_total'] ?? 0 );
        $balance     = max( 0, $grand_total - $paid );

        if ( $pay_mode === 'deposit' ) {
            $deposit_pct = (float) Settings::get( 'mercadopago_deposit_percent', '30' );

            // Check if rate plan has a specific deposit %
            if ( ! empty( $booking['rate_plan_id'] ) ) {
                $rp_repo = new \Artechia\PMS\Repositories\RatePlanRepository();
                $rp = $rp_repo->find( (int) $booking['rate_plan_id'] );
                if ( $rp && (float) $rp['deposit_pct'] > 0 ) {
                    $deposit_pct = (float) $rp['deposit_pct'];
                }
            }

            $pct    = max( 1, (int) $deposit_pct );
            $deposit = ( $grand_total * $pct ) / 100;
            // If already paid more than deposit, charge the remaining balance.
            $amount = ( $paid >= $deposit ) ? $balance : max( $deposit - $paid, 1 );
        } else {
            $amount = $balance;
        }

        // MercadoPago minimum is ARS 1.
        return max( round( $amount, 2 ), 1.00 );
    }

    /**
     * Get property name for preference title.
     */
    private static function get_property_name( int $property_id ): string {
        $repo = new \Artechia\PMS\Repositories\PropertyRepository();
        $prop = $repo->find( $property_id );
        return $prop['name'] ?? 'Hotel';
    }

    /**
     * Build the "Confirmation" URL for back_urls.
     */
    private static function build_confirmation_url( array $booking ): string {
        $page_id = Settings::find_page_id_by_shortcode( '[artechia_confirmation]' );
        $url = $page_id ? get_permalink( $page_id ) : home_url( '/confirmacion-reserva/' );
        
        return add_query_arg( [
            'code'           => $booking['booking_code'],
            'token'          => $booking['access_token'] ?? '',
            'total'          => $booking['grand_total'] ?? 0,
            'payment_method' => 'mercadopago',
            'is_return'      => '1', // Flag to trigger status polling/verification
        ], $url );
    }

    /**
     * Build the "Mi Reserva" URL for back_urls.
     */
    private static function build_my_booking_url( string $booking_code, string $token = '' ): string {
        $page_id = Settings::find_page_id_by_shortcode( '[artechia_my_booking]' );
        $url = $page_id ? get_permalink( $page_id ) : home_url( '/mi-reserva/' );
        
        $args = [ 
            'code'      => $booking_code,
            'is_return' => '1' 
        ];
        if ( $token ) {
            $args['token'] = $token;
        }

        return add_query_arg( $args, $url );
    }
}
