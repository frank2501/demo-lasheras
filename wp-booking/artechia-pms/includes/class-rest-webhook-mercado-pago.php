<?php
/**
 * MercadoPago webhook handler.
 *
 * Route: POST /artechia/v1/webhooks/mercadopago
 *
 * Security:
 *   1. x-signature HMAC-SHA256 verification (if webhook_secret configured).
 *   2. Server-side fetch from MP API to confirm payment data.
 *   3. Idempotency via unique idempotency_key = "mp:{payment_id}".
 */

namespace Artechia\PMS;

use Artechia\PMS\Repositories\BookingRepository;
use Artechia\PMS\Repositories\PaymentRepository;
use Artechia\PMS\Services\BookingService;
use Artechia\PMS\Services\MercadoPagoGateway;
use Artechia\PMS\Services\Settings;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class RestWebhookMercadoPago {

    private const NS = 'artechia/v1';

    /**
     * Register webhook route.
     */
    public static function register_routes(): void {
        register_rest_route( self::NS, 'webhooks/mercadopago', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'handle' ],
            'permission_callback' => '__return_true', // Public — verified via signature
        ] );
    }

    /**
     * Handle incoming MercadoPago webhook notification.
     */
    public static function handle( \WP_REST_Request $req ): \WP_REST_Response {
        try {
        // MP sends topic + data.id or type + data.id.
        $topic   = sanitize_text_field( $req->get_param( 'topic' ) ?? $req->get_param( 'type' ) ?? '' );
        $data_id = sanitize_text_field( $req->get_param( 'data_id' ) ?? '' );

        // Also check JSON body for data.id (newer MP format).
        if ( ! $data_id ) {
            $body    = $req->get_json_params();
            $data_id = sanitize_text_field( $body['data']['id'] ?? '' );
            if ( ! $topic ) {
                $topic = sanitize_text_field( $body['type'] ?? '' );
            }
        }

        // Only process payment notifications.
        if ( ! in_array( $topic, [ 'payment', 'payment.created', 'payment.updated' ], true ) ) {
            // MP may send merchant_order or other topics — acknowledge them.
            return new \WP_REST_Response( [ 'status' => 'ignored', 'topic' => $topic ], 200 );
        }

        if ( ! $data_id ) {
            Logger::warning( 'mp.webhook_no_data_id', 'Webhook received without data.id' );
            return new \WP_REST_Response( [ 'error' => 'MISSING_DATA_ID' ], 400 );
        }

        // ── 1. Verify signature ──
        $x_signature  = sanitize_text_field( $_SERVER['HTTP_X_SIGNATURE'] ?? '' );
        $x_request_id = sanitize_text_field( $_SERVER['HTTP_X_REQUEST_ID'] ?? '' );

        $signature_valid = false;
        if ( $x_signature && $x_request_id ) {
            $signature_valid = MercadoPagoGateway::verify_webhook_signature( $x_signature, $x_request_id, $data_id );
        }

        if ( ! $signature_valid ) {
            // Check if we can bypass verification (Sandbox + Allow Unsigned).
            $is_sandbox     = ( Settings::get( 'mercadopago_sandbox', '1' ) === '1' );
            $allow_unsigned = ( Settings::get( 'mercadopago_allow_unsigned_sandbox', '0' ) === '1' );

            if ( $is_sandbox && $allow_unsigned ) {
                Logger::warning( 'mp.webhook_unsigned_allowed', "Processing unsigned webhook (Sandbox allow_unsigned=1) for {$data_id}" );
            } else {
                Logger::warning( 'mp.webhook_unauthorized', "Signature verification failed for payment {$data_id}" );
                return new \WP_REST_Response( [ 'error' => 'UNAUTHORIZED' ], 401 );
            }
        }

        // ── 2. Idempotency check ──
        $payments = new PaymentRepository();
        $idem_key = 'mp:' . $data_id;
        $existing = $payments->find_by_idempotency_key( $idem_key );

        if ( $existing && in_array( $existing['status'], [ 'approved', 'rejected', 'cancelled', 'charged_back' ], true ) ) {
            // Already fully processed — return success.
            Logger::info( 'mp.webhook_idempotent', "Payment {$data_id} already processed (status: {$existing['status']})" );
            return new \WP_REST_Response( [ 'status' => 'already_processed' ], 200 );
        }

        // ── 3. Fetch payment from MP API (second verification layer) ──
        $mp_payment = MercadoPagoGateway::fetch_payment( $data_id );
        if ( ! $mp_payment ) {
            Logger::error( 'mp.webhook_fetch_failed', "Could not fetch payment {$data_id} from MP API" );
            return new \WP_REST_Response( [ 'error' => 'FETCH_FAILED' ], 500 );
        }

        $mp_status    = sanitize_text_field( $mp_payment['status'] ?? '' );
        $mp_amount    = (float) ( $mp_payment['transaction_amount'] ?? 0 );
        $mp_currency  = sanitize_text_field( $mp_payment['currency_id'] ?? 'ARS' );
        $mp_ext_ref   = sanitize_text_field( $mp_payment['external_reference'] ?? '' );
        $mp_pay_mode  = sanitize_text_field( $mp_payment['metadata']['pay_mode'] ?? 'total' );

        // Phase 6: Handle randomized Sandbox references (e.g., "RES-123_abc" -> "RES-123")
        $clean_ref = $mp_ext_ref;
        if ( false !== strpos( $mp_ext_ref, '_' ) ) {
            $parts = explode( '_', $mp_ext_ref );
            $clean_ref = $parts[0];
        }

        // ── 4. Find the booking ──
        $bookings = new BookingRepository();
        $booking  = $bookings->find_by_code( $clean_ref );

        if ( ! $booking ) {
            Logger::error( 'mp.webhook_booking_missing', "Booking not found for external_reference: {$mp_ext_ref}" );
            return new \WP_REST_Response( [ 'error' => 'BOOKING_NOT_FOUND' ], 404 );
        }

        // ── 5. Process payment record ──
        $payment_id = MercadoPagoGateway::process_payment_update( (int) $booking['id'], $mp_payment );

        if ( ! $payment_id ) {
            Logger::error( 'mp.payment_process_failed', "Could not process payment for {$data_id}", 'booking' );
            return new \WP_REST_Response( [ 'error' => 'DB_ERROR' ], 500 );
        }

        Logger::info( 'mp.webhook_processed', "Payment {$data_id} status: {$mp_status}", 'booking', null, [
            'payment_id'   => $payment_id,
            'booking_code' => $mp_ext_ref,
            'amount'       => $mp_amount,
        ] );

        // ── 6. Handle status transitions ──
        $mapped_status = MercadoPagoGateway::map_payment_status( $mp_status );

        if ( $mapped_status === 'approved' ) {
            self::handle_approved( $booking, $bookings, $payments, $mp_amount, $mp_currency );
        } elseif ( in_array( $mapped_status, [ 'cancelled', 'rejected', 'charged_back', 'refunded' ], true ) ) {
            self::handle_failed( $booking, $bookings, $mapped_status, $data_id );
        }
        // 'pending' / 'in_process' → no action needed, booking stays pending.

        return new \WP_REST_Response( [ 'status' => 'ok' ], 200 );
        } catch ( \Throwable $e ) {
            $log_id = Logger::critical( 'rest.error', $e->getMessage(), 'webhook_mercadopago', null, [
                'file'  => $e->getFile(),
                'line'  => $e->getLine(),
                'trace' => substr( $e->getTraceAsString(), 0, 2000 ),
            ] );
            return new \WP_REST_Response( [
                'error'      => 'INTERNAL_ERROR',
                'message'    => 'An unexpected error occurred.',
                'request_id' => $log_id,
            ], 500 );
        }
    }

    /* ── Status Handlers ────────────────────────────── */

    /**
     * Handle an approved payment: update amount_paid, confirm booking.
     */
    private static function handle_approved(
        array $booking,
        BookingRepository $bookings,
        PaymentRepository $payments,
        float $amount,
        string $currency
    ): void {
        $booking_id = (int) $booking['id'];
        $service    = new BookingService();

        // 1. Currency Check
        if ( $currency !== $booking['currency'] ) {
            Logger::error( 'mp.currency_mismatch', "Payment currency {$currency} does not match booking {$booking['currency']}", 'booking', $booking_id );
            return; // Do not confirm
        }

        // 2. Update Financial Status (amount_paid, balance_due, payment_status)
        $service->update_financial_status( $booking_id );
        
        // Sum total paid for threshold check
        $total_paid = $payments->sum_paid( $booking_id );

        // 3. Amount Threshold Check (Deposit vs Total)
        $grand_total = (float) $booking['grand_total'];
        $deposit_pct = (int) Settings::get( 'mercadopago_deposit_percent', '30' );
         // If paying 'balance', $pay_mode check? logic implies we just check if enough is paid for state transition.
        $min_deposit = ( $grand_total * $deposit_pct ) / 100;
        
        $tolerance = 0.01;
        $diff_deposit = round( $total_paid - $min_deposit, 2 );
        $diff_total   = round( $total_paid - $grand_total, 2 );

        $covered_deposit = ( $diff_deposit >= -$tolerance );
        $covered_total   = ( $diff_total >= -$tolerance );

        if ( ! $covered_deposit && ! $covered_total ) {
            Logger::warning( 'mp.amount_insufficient', "Paid {$total_paid} is less than required deposit {$min_deposit} (Tol: {$tolerance})", 'booking', $booking_id );
            return; // Do not confirm (booking remains pending with partial payment)
        }

        // 4. Confirm if still pending or hold (BookingService::confirm is idempotent).
        if ( in_array( $booking['status'], [ 'pending', 'hold' ], true ) ) {
            $result = $service->confirm_booking( $booking_id );

            if ( isset( $result['error'] ) ) {
                Logger::error( 'mp.confirm_failed', "Could not confirm booking {$booking['booking_code']}: {$result['message']}", 'booking', $booking_id );
            } else {
                Logger::info( 'mp.booking_confirmed', "Booking {$booking['booking_code']} confirmed via webhook (Paid: {$total_paid})", 'booking', $booking_id, [
                   'paid'             => $total_paid,
                   'required_deposit' => $min_deposit
                ] );
            }
        } else {
            // Already confirmed (e.g. balance payment).
            Logger::info( 'mp.balance_payment', "Balance payment received for confirmed booking {$booking['booking_code']}", 'booking', $booking_id, [
                'paid' => $total_paid,
            ] );
            // Optionally trigger 'payment_received' email here in future.
        }
    }

    /**
     * Handle failed/rejected/charged_back payment.
     */
    private static function handle_failed(
        array $booking,
        BookingRepository $bookings,
        string $status,
        string $payment_id
    ): void {
        Logger::info( 'mp.payment_failed', "Payment {$payment_id} for booking {$booking['booking_code']} status: {$status}" );

        // For charged_back: check action setting.
        if ( $status === 'charged_back' ) {
            $action = Settings::get( 'mercadopago_charged_back_action', 'flag' );
            
            if ( $action === 'cancel' && $booking['status'] === 'confirmed' ) {
                $service = new BookingService();
                $service->cancel_booking( (int) $booking['id'], "Charged back (MP payment {$payment_id})" );
            } else {
                // Default: flag (add note)
                $booking_service = new BookingService();
                $booking_service->add_note( (int) $booking['id'], "[DISPUTA] Pago {$payment_id} contracargado en MercadoPago." );
            }
        } elseif ( $status === 'refunded' ) {
            // For refunded: add a note.
            $service = new BookingService();
            $service->add_note( (int) $booking['id'], "Pago {$payment_id} fue reembolsado en MercadoPago." );
        }
        // For rejected/cancelled: log only — booking remains pending for retry.
    }

    /* ── Helpers ─────────────────────────────────────── */
}
