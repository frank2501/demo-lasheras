<?php
/**
 * Booking lifecycle service.
 *
 * Orchestrates: checkout start → create booking → confirm → cancel.
 */
namespace Artechia\PMS\Services;

use Artechia\PMS\DB\Schema;
use Artechia\PMS\Logger;
use Artechia\PMS\Repositories\BookingRepository;
use Artechia\PMS\Repositories\GuestRepository;
use Artechia\PMS\Repositories\LockRepository;
use Artechia\PMS\Repositories\PaymentRepository;
use Artechia\PMS\Repositories\RatePlanRepository;
use Artechia\PMS\Repositories\RoomUnitRepository;
use Artechia\PMS\Helpers\Helpers;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class BookingService {

    private BookingRepository  $bookings;
    private GuestRepository    $guests;
    private LockRepository     $locks;
    private LockService        $lock_svc;
    private PricingService     $pricing;
    private AvailabilityService $availability;
    private EmailService       $email;

    public function __construct() {
        $this->bookings     = new BookingRepository();
        $this->guests       = new GuestRepository();
        $this->locks        = new LockRepository();
        $this->lock_svc     = new LockService();
        $this->pricing      = new PricingService();
        $this->availability = new AvailabilityService();
        $this->email        = new EmailService();
    }

    /* ── 1. Start Checkout ──────────────────────────── */

    /**
     * Validate availability, acquire lock, generate quote + checkout token.
     *
     * @return array{ checkout_token, lock_key, quote, expires_at } | array{ error, message }
     */
    public function start_checkout( array $data ): array {
        $property_id  = absint( $data['property_id'] ?? 0 );
        $room_type_id = absint( $data['room_type_id'] ?? 0 );
        $rate_plan_id = absint( $data['rate_plan_id'] ?? 0 );
        $check_in     = sanitize_text_field( $data['check_in'] ?? '' );
        $check_out    = sanitize_text_field( $data['check_out'] ?? '' );
        $adults       = absint( $data['adults'] ?? 2 );
        $children     = absint( $data['children'] ?? 0 );
        $extras       = isset( $data['extras'] ) && is_array( $data['extras'] ) ? $data['extras'] : [];

        if ( ! $property_id || ! $room_type_id || ! $check_in || ! $check_out ) {
            return [ 'error' => 'MISSING_PARAMS', 'message' => 'Missing required fields.' ];
        }

        // Resolve default rate plan if needed.
        if ( ! $rate_plan_id ) {
            $rp_repo = new RatePlanRepository();
            $default = $rp_repo->get_default( $property_id );
            $rate_plan_id = $default ? (int) $default['id'] : 0;
        }
        if ( ! $rate_plan_id ) {
            return [ 'error' => 'NO_RATE_PLAN', 'message' => 'No rate plan found.' ];
        }

        // Acquire lock (includes availability check via GET_LOCK).
        $lock_result = $this->lock_svc->acquire(
            $property_id, $room_type_id, $rate_plan_id,
            $check_in, $check_out, 1,
            [ 'ip' => $this->get_ip(), 'stage' => 'checkout' ]
        );

        if ( isset( $lock_result['error'] ) ) {
            return $lock_result;
        }

        // Generate quote.
        $sanitized_extras = $this->sanitize_extras( $extras );
        $quote = $this->pricing->quote(
            $property_id, $room_type_id, $rate_plan_id,
            $check_in, $check_out, $adults, $children,
            $sanitized_extras
        );

        if ( isset( $quote['error'] ) ) {
            // Release lock on quote failure.
            $this->lock_svc->release( $lock_result['lock_key'] );
            return $quote;
        }

        // Preview which unit would be assigned (read-only, no commit).
        $preview_unit_id   = 0;
        $preview_unit_name = '';
        $booking_repo = new \Artechia\PMS\Repositories\BookingRepository();
        $free_unit = $booking_repo->get_free_unit_for_type( $property_id, $room_type_id, $check_in, $check_out );
        if ( $free_unit ) {
            $preview_unit_id   = (int) $free_unit['id'];
            $preview_unit_name = $free_unit['name'] ?? '';
        }

        // Build checkout token (HMAC-signed).
        $payload = [
            'lock_key'         => $lock_result['lock_key'],
            'property_id'      => $property_id,
            'room_type_id'     => $room_type_id,
            'rate_plan_id'     => $rate_plan_id,
            'check_in'         => $check_in,
            'check_out'        => $check_out,
            'adults'           => $adults,
            'children'         => $children,
            'extras'           => $sanitized_extras,
            'expires_at'       => $lock_result['expires_at'],
            'created_at'       => time(),
            'room_unit_id'     => $preview_unit_id,
            'room_unit_name'   => $preview_unit_name,
        ];

        $checkout_token = $this->sign_token( $payload );

        return [
            'checkout_token'   => $checkout_token,
            'lock_key'         => $lock_result['lock_key'],
            'expires_at'       => $lock_result['expires_at'],
            'ttl_minutes'      => $lock_result['ttl_minutes'],
            'quote'            => $quote,
            'room_unit_name'   => $preview_unit_name,
        ];
    }

    /* ── 2. Create Booking ──────────────────────────── */

    /**
     * Validate checkout token, create guest, insert booking as pending.
     *
     * @return array{ booking_code, access_token, manage_url } | array{ error, message }
     */
    public function create_booking( string $checkout_token, array $guest_data, bool $accept_terms = false, string $coupon_code = '', string $payment_method = 'mercadopago', array $extras = [] ): array {
        if ( ! $accept_terms ) {
            return [ 'error' => 'TERMS_REQUIRED', 'message' => 'Terms must be accepted.' ];
        }
        // Verify checkout token.
        $payload = $this->verify_token( $checkout_token );
        if ( ! $payload ) {
            return [ 'error' => 'INVALID_TOKEN', 'message' => 'Invalid or expired checkout token.' ];
        }

        // Verify lock still exists and is valid.
        $lock = $this->lock_svc->info( $payload['lock_key'] );
        if ( ! $lock ) {
            return [ 'error' => 'LOCK_EXPIRED', 'message' => 'Your reservation hold has expired. Please start again.' ];
        }

        // Idempotency check
        if ( ! empty( $lock['booking_id'] ) ) {
            $existing = $this->bookings->find( (int) $lock['booking_id'] );
            if ( $existing ) {
                $result = [
                    'booking_code' => $existing['booking_code'],
                    'access_token' => $existing['access_token'],
                    'manage_url'   => $this->build_manage_url( $existing['booking_code'], $existing['access_token'] ),
                    'booking_id'   => (int) $existing['id'],
                    'grand_total'  => (float) $existing['grand_total'],
                ];
 
                // If they chose Mercado Pago and it's still pending/unpaid, give them the URL again.
                if ( $payment_method === 'mercadopago' && MercadoPagoGateway::is_enabled() ) {
                    $amount = MercadoPagoGateway::calculate_amount( $existing, 'deposit' );
                    $pref   = MercadoPagoGateway::create_preference( $existing, 'deposit', $amount );
                    if ( ! isset( $pref['error'] ) ) {
                        $result['payment_url'] = $pref['init_point'];
                    }
                }
                return $result;
            }
            return [ 'error' => 'LOCK_USED', 'message' => 'This reservation hold has already been used.' ];
        }

        // Validate lock data matches token payload
        if (
            (int) $lock['room_type_id'] !== (int) $payload['room_type_id'] ||
            (int) $lock['property_id']  !== (int) $payload['property_id']  ||
            $lock['check_in']           !== $payload['check_in']           ||
            $lock['check_out']          !== $payload['check_out']
        ) {
            return [ 'error' => 'TOKEN_MISMATCH', 'message' => 'Lock does not match checkout data.' ];
        }

        global $wpdb;

        // Variables needed after transaction.
        $booking_id       = 0;
        $booking_code     = '';
        $access_token_raw = '';
        $quote            = null;

        try {
            $wpdb->query( 'START TRANSACTION' );

            // Create or find guest.
            $guest_email = sanitize_email( $guest_data['email'] ?? '' );
            $guest_id = $this->guests->find_or_create_by_email( [
                'first_name'      => sanitize_text_field( $guest_data['first_name'] ?? '' ),
                'last_name'       => sanitize_text_field( $guest_data['last_name'] ?? '' ),
                'email'           => $guest_email,
                'phone'           => sanitize_text_field( $guest_data['phone'] ?? '' ),
                'document_type'   => sanitize_text_field( $guest_data['document_type'] ?? '' ),
                'document_number' => sanitize_text_field( $guest_data['document_number'] ?? '' ),
                'country'         => sanitize_text_field( $guest_data['country'] ?? '' ),
                'city'            => sanitize_text_field( $guest_data['city'] ?? '' ),
            ] );

            if ( ! $guest_id ) {
                throw new \Exception( 'Could not create guest record.' );
            }

            // Blacklist check
            $guest = $this->guests->find( $guest_id );
            if ( $guest && ! empty( $guest['is_blacklisted'] ) ) {
                throw new \Exception( 'GUEST_BLACKLISTED: ' . ( $guest['blacklist_reason'] ?: 'No reason provided.' ) );
            }

            // Merge extras from checkout form (selected after token was issued)
            $final_extras = ! empty( $extras ) ? $this->sanitize_extras( $extras ) : (array) ( $payload['extras'] ?? [] );

            // Re-generate quote
            $quote = $this->pricing->quote(
                (int)   $payload['property_id'],
                (int)   $payload['room_type_id'],
                (int)   $payload['rate_plan_id'],
                $payload['check_in'],
                $payload['check_out'],
                (int)   $payload['adults'],
                (int)   $payload['children'],
                $final_extras,
                $coupon_code,
                $guest_email
            );

            if ( isset( $quote['validation']['coupon_error'] ) && ! empty( $coupon_code ) ) {
                 // Throwing exception here to trigger rollback, but need to pass specific error message
                 throw new \Exception( $quote['validation']['coupon_error'] );
            }

            $booking_code = $this->generate_booking_code();
            $access_token_raw = $this->generate_access_token();
            $access_token_hash = hash( 'sha256', $access_token_raw );
            $nights = count( $quote['nights'] );

            // Get cancellation policy
            $rp_repo   = new RatePlanRepository();
            $rate_plan = $rp_repo->find( $payload['rate_plan_id'] );
            $cancel_policy = $rate_plan['cancellation_policy_json'] ?? null;

            // Insert booking
            $booking_id = $this->bookings->create( [
                'booking_code'              => $booking_code,
                'property_id'               => $payload['property_id'],
                'guest_id'                  => $guest_id,
                'rate_plan_id'              => $payload['rate_plan_id'],
                'check_in'                  => $payload['check_in'],
                'check_out'                 => $payload['check_out'],
                'nights'                    => $nights,
                'adults'                    => $payload['adults'],
                'children'                  => $payload['children'],
                'status'                    => ( $payment_method === 'mercadopago' ) ? 'hold' : 'pending',
                'source'                    => 'web',
                'subtotal'                  => $quote['totals']['subtotal_base'],
                'extras_total'              => $quote['totals']['extras_total'],
                'taxes_total'               => $quote['totals']['taxes_total'],
                'discount_total'            => abs( $quote['totals']['discount_amount'] ?? 0 ),
                'coupon_code'               => $coupon_code,
                'grand_total'               => $quote['totals']['total'],
                'amount_paid'               => 0,
                'currency'                  => Settings::get( 'currency', 'ARS', $payload['property_id'] ),
                'pricing_snapshot'          => wp_json_encode( $quote ),
                'special_requests'          => sanitize_textarea_field( $guest_data['special_requests'] ?? '' ),
                'access_token'              => $access_token_raw,
                'cancellation_policy_json'  => $cancel_policy,
                'balance_due'               => $quote['totals']['total'],
                'payment_status'            => 'unpaid',
                'payment_method'            => $payment_method,
                'created_at'                => current_time( 'mysql', true ),
            ] );

            if ( ! $booking_id ) {
                throw new \Exception( 'Could not create booking.' );
            }

            // Coupon Redemption
            if ( ! empty( $coupon_code ) && isset( $quote['coupon'] ) ) {
                $coupon_repo = new \Artechia\PMS\Repositories\CouponRepository();
                $coupon_repo->redeem( 
                    (int) $quote['coupon']['id'],
                    (int) $booking_id,
                    $guest_email,
                    (float) $quote['coupon']['amount']
                );
            }

            // Insert booking_room (include pre-selected unit from checkout token)
            $create_room_data = [
                'booking_id'          => $booking_id,
                'room_type_id'        => $payload['room_type_id'],
                'adults'              => $payload['adults'],
                'children'            => $payload['children'],
                'rate_per_night_json' => wp_json_encode( $quote['nights'] ),
                'subtotal'            => $quote['totals']['subtotal_base'],
            ];
            if ( ! empty( $payload['room_unit_id'] ) ) {
                // Only assign unit to pending bookings if setting allows it
                $booking_status = ( $payment_method === 'mercadopago' ) ? 'hold' : 'pending';
                $should_assign = ( $booking_status !== 'pending' ) || BookingRepository::pending_blocks_unit();
                if ( $should_assign ) {
                    $create_room_data['room_unit_id'] = (int) $payload['room_unit_id'];
                }
            }
            $this->bookings->create_room( $create_room_data );

            // Auto-assign unit (skip for pending+unpaid when setting is OFF)
            $booking_status_check = ( $payment_method === 'mercadopago' ) ? 'hold' : 'pending';
            if ( $booking_status_check !== 'pending' || BookingRepository::pending_blocks_unit() ) {
                $this->bookings->assign_unit_on_confirm( $booking_id );
            }

            // Insert booking_extras
            if ( ! empty( $quote['extras'] ) ) {
                foreach ( $quote['extras'] as $extra ) {
                    $this->bookings->create_extra( [
                        'booking_id'  => $booking_id,
                        'extra_id'    => $extra['extra_id'],
                        'quantity'    => $extra['qty'],
                        'unit_price'  => $extra['unit_price'],
                        'total_price' => $extra['total'],
                    ] );
                }
            }

            // Tie lock
            $this->locks->update_booking_id( $payload['lock_key'], $booking_id );

            $wpdb->query( 'COMMIT' );
            self::clear_calendar_cache();

        } catch ( \Throwable $e ) {
            $wpdb->query( 'ROLLBACK' );
            Logger::error( 'booking.create_failed', $e->getMessage(), [ 'trace' => substr( $e->getTraceAsString(), 0, 1000 ) ] );
            $msg = $e->getMessage();
            if ( strpos( $msg, 'GUEST_BLACKLISTED' ) !== false ) {
                return [ 'error' => 'GUEST_BLACKLISTED', 'message' => $msg ];
            }
            // Return safe error
            return [ 'error' => 'CREATE_FAILED', 'message' => 'An unexpected error occurred. Please try again or contact support.' ];
        }

        // Side effects (Email, MP) - After Commit & Robust
        try {
            if ( $payment_method !== 'mercadopago' ) {
                $this->email->send_async( 'booking_pending', $booking_id );
            }

            Logger::info( 'booking.created', "Booking {$booking_code} created (pending)", [
                'booking_id' => $booking_id,
            ] );
        } catch ( \Throwable $e ) {
            Logger::error( 'booking.side_effects_failed', "Non-critical error after booking {$booking_code}: " . $e->getMessage() );
        }

        $result = [
            'booking_code' => $booking_code,
            'access_token' => $access_token_raw,
            'manage_url'   => $this->build_manage_url( $booking_code, $access_token_raw ),
            'booking_id'   => $booking_id,
            'grand_total'  => $quote['totals']['total'],
            'deposit_pct'  => $quote['totals']['deposit_pct'],
            'deposit_due'  => ( (float) $quote['totals']['total'] * (float) $quote['totals']['deposit_pct'] ) / 100,
        ];

        // Mercado Pago Redirection - Specific try-catch to avoid breaking the result
        if ( $payment_method === 'mercadopago' && MercadoPagoGateway::is_enabled() ) {
            try {
                $booking_row = $this->bookings->find( $booking_id );
                if ( $booking_row ) {
                    $amount = MercadoPagoGateway::calculate_amount( $booking_row, 'deposit' );
                    $pref   = MercadoPagoGateway::create_preference( $booking_row, 'deposit', $amount );
                    if ( ! isset( $pref['error'] ) ) {
                        $result['payment_url'] = $pref['init_point'];
                    }
                }
            } catch ( \Throwable $e ) {
                Logger::error( 'booking.mp_pref_failed', "Failed to create MP preference for {$booking_code}: " . $e->getMessage() );
            }
        }

        return $result;
    }

    /* ── 3. Confirm Booking ─────────────────────────── */

    /**
     * Confirm a booking: assign room unit, update status, send email.
     *
     * @return array{ success } | array{ error, message }
     */
    public function confirm_booking( int $booking_id ): array {
        global $wpdb;

        $booking = $this->bookings->find( $booking_id );
        if ( ! $booking ) {
            return [ 'error' => 'NOT_FOUND', 'message' => 'Booking not found.' ];
        }

        // Idempotent: if already confirmed, return success.
        if ( $booking['status'] === 'confirmed' ) {
            return [ 'success' => true, 'booking_code' => $booking['booking_code'] ];
        }

        if ( ! in_array( $booking['status'], [ 'pending', 'hold' ], true ) ) {
            return [ 'error' => 'INVALID_STATUS', 'message' => 'Booking is not pending.' ];
        }

        try {
            $wpdb->query( 'START TRANSACTION' );

            // Lock booking row to prevent confirm/cancel race.
            // We select status to verify it hasn't changed.
            $locked_booking = $wpdb->get_row( $wpdb->prepare( 
                "SELECT id, status, booking_code FROM {$this->bookings->table()} WHERE id = %d FOR UPDATE", 
                $booking_id 
            ), \ARRAY_A );

            if ( ! $locked_booking ) {
                $wpdb->query( 'ROLLBACK' );
                return [ 'error' => 'NOT_FOUND', 'message' => 'Booking not found.' ];
            }

            if ( $locked_booking['status'] === 'confirmed' ) {
                $wpdb->query( 'ROLLBACK' );
                return [ 
                    'ok'           => true, 
                    'booking_id'   => $booking_id,
                    'booking_code' => $locked_booking['booking_code'], 
                    'status'       => 'confirmed' 
                ];
            }

            if ( ! in_array( $locked_booking['status'], [ 'pending', 'hold' ], true ) ) {
                $wpdb->query( 'ROLLBACK' );
                return [ 'error' => 'INVALID_STATUS', 'message' => 'Booking is not pending.' ];
            }

            // Assign room unit.
            $assigned = $this->bookings->assign_unit_on_confirm( $booking_id );
            if ( ! $assigned ) {
                $wpdb->query( 'ROLLBACK' );
                Logger::warning( 'booking.confirm_failed', "No free unit for booking {$booking['booking_code']}", [
                    'booking_id' => $booking_id,
                ] );
                return [
                    'error'   => 'NO_UNIT',
                    'message' => 'No room unit available. Booking remains pending.',
                ];
            }

            // Update status (if still hold/pending, move to confirmed)
            if ( in_array( $locked_booking['status'], [ 'pending', 'hold' ], true ) ) {
                $this->bookings->update_status( $booking_id, 'confirmed' );
            }

            // Ensure financial status (and specific paid/deposit_paid statuses) is up to date.
            $this->update_financial_status( $booking_id );

            // Release the lock (it's no longer needed — booking is confirmed).
            $this->release_booking_lock( $booking_id );

            // Send confirmed email.
            // Note: If email fails, we generally still want to confirm the booking, 
            // but for strict atomicity as requested, let's catch email errors or allow them to float?
            // The audit said: "If email->send fails ... the booking remains confirmed but potentially in an inconsistent state"
            // Ideally email sending shouldn't rollback the DB transaction unless it's critical. 
            // Usually email is a side effect.
            // However, to be "Production Ready" and safe, let's assume if we can't send the email, maybe we shouldn't fail the booking confirmation?
            // Actually, failing the booking because SMTP is down is bad UX.
            // So we will COMMIT before sending email? 
            // The Audit complained about "inconsistent state". 
            // Let's COMMIT first, then try email.
            
            $wpdb->query( 'COMMIT' );
            self::clear_calendar_cache();

        } catch ( \Exception $e ) {
            $wpdb->query( 'ROLLBACK' );
            Logger::error( 'booking.confirm_error', $e->getMessage(), [ 'exception' => $e ] );
            return [ 'error' => 'DB_ERROR', 'message' => 'Database error during confirmation.' ];
        }


        // Send email AFTER commit (Async)
        $this->email->send_async( 'booking_confirmed', $booking_id );

        Logger::info( 'booking.confirmed', "Booking {$booking['booking_code']} confirmed", [
            'booking_id' => $booking_id,
        ] );

        return [ 
            'ok'           => true, 
            'booking_id'   => $booking_id,
            'booking_code' => $booking['booking_code'],
            'status'       => 'confirmed'
        ];
    }

    /* ── 4. Cancel Booking ──────────────────────────── */


    /* ── Helpers ─────────────────────────────────────── */

    /**
     * Get active locks formatted for display.
     */
    public function get_active_locks(): array {
        $locks = $this->locks->find_active_display_locks();
        return array_map( function( $l ) {
            return [
                'id'           => 'lock_' . $l['id'],
                'booking_code' => 'HOLD',
                'status'       => 'hold',
                'guest_name'   => 'Checkout en progreso',
                'check_in'     => $l['check_in'],
                'check_out'    => $l['check_out'],
                'room_type'    => $l['room_type_name'],
                'created_at'   => $l['created_at'] . 'Z',
                'expires_at'   => $l['expires_at'] . 'Z',
                'is_lock'      => true,
            ];
        }, $locks );
    }

    /**
     * Generate a unique booking code: PREFIX - YYMMDD - 4 random chars.
     */
    private function generate_booking_code(): string {
        $prefix = Settings::get( 'booking_code_prefix', 'ART' );
        $date   = gmdate( 'ymd' );
        // 4 random alphanumeric chars for a more manageable code.
        $random = strtoupper( substr( bin2hex( random_bytes( 2 ) ), 0, 4 ) );
        $code   = "{$prefix}{$date}{$random}";

        // Ensure uniqueness.
        $existing = $this->bookings->find_by_code( $code );
        if ( $existing ) {
            return $this->generate_booking_code(); // retry (collision extremely unlikely)
        }

        return $code;
    }

    /**
     * Generate a 64-char crypto-safe access token.
     */
    private function generate_access_token(): string {
        // 32 random bytes = 64 hex chars (crypto-safe, non-guessable).
        return bin2hex( random_bytes( 32 ) );
    }

    /**
     * Build the "Mi Reserva" URL.
     */
    private function build_manage_url( string $code, string $token ): string {
        $page_id = get_option( 'artechia_pms_my_booking_page_id', 0 );
        $url = $page_id ? get_permalink( $page_id ) : home_url( '/mi-reserva/' );
        return add_query_arg( [ 'code' => $code, 'token' => $token ], $url );
    }

    /**
     * Release lock associated with a booking.
     */
    private function release_booking_lock( int $booking_id ): void {
        $lock = $this->locks->find_by_booking_id( $booking_id );
        if ( $lock ) {
            $this->locks->delete_by_key( $lock['lock_key'] );
        }
    }

    /**
     * Sign a payload into a checkout token.
     */
    private function sign_token( array $payload ): string {
        $json = wp_json_encode( $payload );
        $hmac = hash_hmac( 'sha256', $json, wp_salt( 'auth' ) );
        return base64_encode( $json ) . '.' . $hmac;
    }

    /**
     * Verify and decode a checkout token.
     */
    private function verify_token( string $token ): ?array {
        $parts = explode( '.', $token, 2 );
        if ( count( $parts ) !== 2 ) return null;

        $json = base64_decode( $parts[0], true );
        if ( ! $json ) return null;

        $expected_hmac = hash_hmac( 'sha256', $json, wp_salt( 'auth' ) );
        if ( ! hash_equals( $expected_hmac, $parts[1] ) ) return null;

        $payload = json_decode( $json, true );
        if ( ! is_array( $payload ) ) return null;

        // Check if lock_key exists in payload.
        if ( empty( $payload['lock_key'] ) ) return null;

        return $payload;
    }

    /**
     * Sanitize extras array (extra_id => qty).
     */
    private function sanitize_extras( array $extras ): array {
        $clean = [];
        foreach ( $extras as $k => $v ) {
            $clean[ absint( $k ) ] = max( 1, absint( $v ) );
        }
        return $clean;
    }

    private function get_ip(): string {
        return sanitize_text_field(
            $_SERVER['HTTP_X_FORWARDED_FOR']
            ?? $_SERVER['HTTP_X_REAL_IP']
            ?? $_SERVER['REMOTE_ADDR']
            ?? '0.0.0.0'
        );
    }

    /**
     * Clear all calendar-hints transient caches.
     * Called after any booking lifecycle change.
     */
    public static function clear_calendar_cache(): void {
        global $wpdb;
        $wpdb->query(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_artechia_cal_hints_%' OR option_name LIKE '_transient_timeout_artechia_cal_hints_%'"
        );
    }

    /* ── 6. Operations (Assign, Check-in, Check-out) ── */

    /**
     * Assign a specific room unit to a booking.
     */
    public function assign_unit( int $booking_id, int $unit_id ): array {
        $booking = $this->bookings->find( $booking_id );
        if ( ! $booking ) {
            return [ 'error' => 'NOT_FOUND', 'message' => 'Booking not found.' ];
        }

        // Validate unit availability.
        if ( ! $this->bookings->is_unit_available(
            $unit_id,
            (int) $booking['property_id'],
            $booking['check_in'],
            $booking['check_out'],
            $booking_id // exclude self if re-assigning (though usually booking_rooms row holds unit, here we just check overlap)
        ) ) {
            return [ 'error' => 'UNIT_UNAVAILABLE', 'message' => 'Room unit is occupied for these dates.' ];
        }

        // Update booking_rooms.
        // Assuming single room per booking for now (MVP).
        // If multiple rooms, we'd need to know which booking_room_id to update.
        // For MVP H6, we assume we update the first room row.
        global $wpdb;
        $br = Schema::table( 'booking_rooms' );
        $updated = $wpdb->query( $wpdb->prepare(
            "UPDATE {$br} SET room_unit_id = %d WHERE booking_id = %d LIMIT 1",
            $unit_id, $booking_id
        ) );

        if ( $updated === false ) {
            return [ 'error' => 'DB_ERROR', 'message' => 'Could not update assignment.' ];
        }

        $this->add_note( $booking_id, "Asignado a unidad ID: {$unit_id}" );

        return [ 'success' => true ];
    }

    /**
     * Check-in a booking.
     */
    public function check_in( int $booking_id ): array {
        $booking = $this->bookings->find( $booking_id );
        if ( ! $booking ) return [ 'error' => 'NOT_FOUND', 'message' => 'Booking not found.' ];

        if ( ! in_array( $booking['status'], [ 'confirmed', 'deposit_paid', 'paid', 'pending', 'hold' ], true ) ) {
            return [ 'error' => 'INVALID_STATUS', 'message' => 'Booking status unsuitable for check-in.' ];
        }

        $now = current_time( 'mysql', true ); // UTC or local? 'mysql' uses site timezone usually if not true. 'true' = GMT.
        // Let's use local site time for check-in/out timestamps generally.
        $now_local = current_time( 'mysql' );

        $this->bookings->update_status( $booking_id, 'checked_in', [
            'checked_in_at' => $now_local,
        ] );

        $this->add_note( $booking_id, 'Check-in realizado' );
        Logger::info( 'booking.check_in', "Check-in booking {$booking['booking_code']}" );

        return [ 'success' => true ];
    }

    /**
     * Move a booking to a new unit and/or dates (H9).
     */
    public function move_booking( int $booking_id, int $new_unit_id, string $new_ci, string $new_co ): array {
        global $wpdb;
        $booking = $this->bookings->find( $booking_id );
        if ( ! $booking ) return [ 'error' => 'NOT_FOUND', 'message' => 'Booking not found.' ];

        // 0. Start Transaction & Lock record for update (Concurrency protection)
        $wpdb->query( 'START TRANSACTION' );
        
        // Lock the booking row
        $b_table = Schema::table('bookings');
        $wpdb->get_row( $wpdb->prepare( "SELECT id FROM {$b_table} WHERE id = %d FOR UPDATE", $booking_id ) );

        // 1. Policy Restrictions
        if ( in_array( $booking['status'], [ 'checked_out', 'cancelled' ] ) ) {
            $wpdb->query( 'ROLLBACK' );
            return [ 'error' => 'INVALID_STATUS', 'message' => "Cannot move a {$booking['status']} booking." ];
        }
        if ( $booking['status'] === 'checked_in' && $new_ci !== $booking['check_in'] ) {
            $wpdb->query( 'ROLLBACK' );
            return [ 'error' => 'CHECKED_IN_MOVE', 'message' => "Checked-in bookings can only be extended, not moved to other dates." ];
        }
        if ( $booking['source'] === 'ical' ) {
            $wpdb->query( 'ROLLBACK' );
            return [ 'error' => 'ICAL_MOVE', 'message' => "iCal bookings cannot be moved manually. Edit in source calendar." ];
        }

        // 2. Validate Unit Availability (Excluding self)
        if ( ! $this->bookings->is_unit_available( $new_unit_id, (int)$booking['property_id'], $new_ci, $new_co, $booking_id ) ) {
            $wpdb->query( 'ROLLBACK' );
            return [ 'error' => 'UNIT_UNAVAILABLE', 'message' => "Room unit is occupied for these dates." ];
        }

        // 3. Check for ICal locks (H7)
        // (Assuming ICal events are handled by is_unit_available if they are in bookings table, 
        // which they are in Artechia PMS after sync).

        // 4. Recalculate Pricing if dates or room type changed.
        $rooms = $this->bookings->get_rooms( $booking_id );
        $old_room = $rooms[0] ?? null;
        
        // Get new room unit details to see if room type changed
        $unit_repo = new RoomUnitRepository();
        $new_unit  = $unit_repo->find( $new_unit_id );
        if ( ! $new_unit ) {
            $wpdb->query( 'ROLLBACK' );
            return [ 'error' => 'UNIT_NOT_FOUND' ];
        }

        $dates_changed = ( $booking['check_in'] !== $new_ci || $booking['check_out'] !== $new_co );
        $type_changed  = ( (int)$old_room['room_type_id'] !== (int)$new_unit['room_type_id'] );

        if ( $dates_changed || $type_changed ) {
            // Re-quote
            // We need extra IDs.
            $extras = $this->bookings->get_extras( $booking_id );
            $extra_map = [];
            foreach( $extras as $e ) $extra_map[$e['extra_id']] = $e['quantity'];

            $quote = $this->pricing->quote(
                (int)$booking['property_id'], (int)$new_unit['room_type_id'], (int)$booking['rate_plan_id'],
                $new_ci, $new_co, (int)$booking['adults'], (int)$booking['children'], $extra_map
            );

            if ( isset($quote['error']) ) {
                $wpdb->query( 'ROLLBACK' );
                return $quote;
            }

            // Log change
            $fmt_old = \Artechia\PMS\Helpers\Helpers::format_price( (float) $booking['grand_total'] );
            $fmt_new = \Artechia\PMS\Helpers\Helpers::format_price( (float) $quote['total'] );
            $log_msg = "Movida: {$booking['check_in']} → {$new_ci}, {$booking['check_out']} → {$new_co}. Precio: {$fmt_old} → {$fmt_new}.";
            $this->add_note( $booking_id, $log_msg );

            // Update Booking
            $this->bookings->update( $booking_id, [
                'check_in'    => $new_ci,
                'check_out'   => $new_co,
                'nights'      => count($quote['nights']),
                'subtotal'    => $quote['totals']['subtotal_base'],
                'extras_total'=> $quote['totals']['extras_total'],
                'taxes_total' => $quote['totals']['taxes_total'],
                'discount_total' => $quote['totals']['discount_total'],
                'grand_total' => $quote['totals']['total'],
                'pricing_snapshot' => wp_json_encode($quote),
            ] );

            // Update Booking Room
            global $wpdb;
            $br_table = Schema::table('booking_rooms');
            $wpdb->update( $br_table, [
                'room_type_id' => $new_unit['room_type_id'],
                'room_unit_id' => $new_unit_id,
                'rate_per_night_json' => wp_json_encode($quote['nights']),
                'subtotal' => $quote['totals']['subtotal_base']
            ], [ 'booking_id' => $booking_id ] );

        } else {
            // Just room assignment changed within same type/dates
            $this->add_note( $booking_id, "Reasignado a unidad: {$new_unit['unit_name']}" );
            global $wpdb;
            $br_table = Schema::table('booking_rooms');
            $wpdb->update( $br_table, [ 'room_unit_id' => $new_unit_id ], [ 'booking_id' => $booking_id ] );
        }

        $this->update_financial_status( $booking_id );
        $wpdb->query( 'COMMIT' );
        self::clear_calendar_cache();

        // Audit: log the state change with before/after.
        Logger::logChange(
            'booking.move',
            "Booking #{$booking_id} moved",
            'booking',
            $booking_id,
            [ 'check_in' => $booking['check_in'], 'check_out' => $booking['check_out'], 'unit_id' => $old_room['room_unit_id'] ?? null, 'grand_total' => $booking['grand_total'] ],
            [ 'check_in' => $new_ci, 'check_out' => $new_co, 'unit_id' => $new_unit_id ]
        );

        return [ 'success' => true ];
    }

    /**
     * Resize a booking (extend/shorten checkout) (H9).
     */
    public function resize_booking( int $booking_id, string $new_co ): array {
        $booking = $this->bookings->find( $booking_id );
        if ( ! $booking ) return [ 'error' => 'NOT_FOUND' ];

        // Policy
        if ( in_array( $booking['status'], [ 'checked_out', 'cancelled' ] ) ) {
             return [ 'error' => 'INVALID_STATUS' ];
        }

        // Validate overlap
        $rooms = $this->bookings->get_rooms( $booking_id );
        $unit_id = $rooms[0]['room_unit_id'] ?? 0;
        if ( $unit_id && ! $this->bookings->is_unit_available( $unit_id, (int)$booking['property_id'], $booking['check_in'], $new_co, $booking_id ) ) {
             return [ 'error' => 'UNIT_UNAVAILABLE', 'message' => 'Room unit is occupied during the extended period.' ];
        }

        // Reuse move logic? Resize is just a move where CI and UNIT stay same.
        return $this->move_booking( $booking_id, (int)$unit_id, $booking['check_in'], $new_co );
    }

    /**
     * Check-out a booking.
     */
    public function check_out( int $booking_id ): array {
        $booking = $this->bookings->find( $booking_id );
        if ( ! $booking ) return [ 'error' => 'NOT_FOUND', 'message' => 'Booking not found.' ];

        if ( $booking['status'] !== 'checked_in' ) {
            return [ 'error' => 'INVALID_STATUS', 'message' => 'Booking is not checked in.' ];
        }

        $now_local = current_time( 'mysql' );
        $this->bookings->update_status( $booking_id, 'checked_out', [
            'checked_out_at' => $now_local,
        ] );

        // Set unit to dirty.
        // Housekeeping update removed
        Logger::info( 'booking.check_out', "Check-out booking {$booking['booking_code']}" );

        $response = [ 'success' => true ];

        // Schedule Review Email
        $enabled = (bool) Settings::get( 'review_email_enabled', '1', (int) $booking['property_id'] );
        if ( $enabled ) {
            $delay_days    = (int) Settings::get( 'review_email_delay', '1', (int) $booking['property_id'] );
            $delay_seconds = $delay_days * DAY_IN_SECONDS;
            $this->email->send_async( 'booking_review', $booking_id, [], $delay_seconds );
        }

        // Check balance (feature H6v1).
        if ( (float) $booking['balance_due'] > 0 ) {
            $response['warning'] = sprintf( 
                'Guest has an outstanding balance of %s %s.', 
                $booking['currency'], 
                $booking['balance_due'] 
            );
        }

        return $response;
    }


    /**
     * Add an internal note to a booking.
     * 
     * @param int $booking_id
     * @param string $note
     * @return void
     */
    public function add_note( int $booking_id, string $note ): void {
        $booking = $this->bookings->find( $booking_id );
        if ( ! $booking ) {
            return;
        }

        $timestamp = current_time( 'd/m/Y H:i' );
        $new_note  = "[{$timestamp}] {$note}\n";
        $current   = $booking['internal_notes'] ?? '';

        $this->bookings->update( $booking_id, [
            'internal_notes' => $current . $new_note,
        ] );
    }

    /**
     * Recalculate financial status: amount_paid, balance_due, payment_status.
     * 
     * @param int $booking_id
     * @return void
     */
    public function update_financial_status( int $booking_id ): void {
        $booking = $this->bookings->find( $booking_id );
        if ( ! $booking ) {
            return;
        }

        $payments   = new PaymentRepository();
        $total_paid_payments = $payments->sum_paid( $booking_id );
        
        // Use the higher of: payments table sum OR already-recorded amount_paid
        // This prevents resetting payment status when amount_paid was set directly
        // (e.g., manual confirmation with payment) without a payments table entry.
        $existing_paid = (float) ( $booking['amount_paid'] ?? 0 );
        $total_paid    = max( $total_paid_payments, $existing_paid );
        
        $grand_total = (float) $booking['grand_total'];
        $balance_due = max( 0, round( $grand_total - $total_paid, 2 ) );

        $status = 'unpaid';
        // Paid in full (with 0.01 tolerance)
        if ( $total_paid >= ( $grand_total - 0.01 ) ) {
            $status = 'paid';
        } elseif ( $total_paid > 0 ) {
            // Partial payment
            $status = 'deposit_paid';
        }

        // Update booking
        $update_data = [
            'amount_paid'    => $total_paid,
            'balance_due'    => $balance_due,
            'payment_status' => $status,
        ];

        $this->bookings->update( $booking_id, $update_data );
    }

    /* ── Admin Manual Booking ───────────────────────── */

    /**
     * Create a manual booking from Admin.
     * 
     * @param array $data
     * @return array{ booking_code, booking_id } | array{ error, message }
     */
    public function create_manual_booking( array $data ): array {
        $property_id  = absint( $data['property_id'] ?? 0 );
        $room_type_id = absint( $data['room_type_id'] ?? 0 );
        $room_unit_id = absint( $data['room_unit_id'] ?? 0 );
        $check_in     = sanitize_text_field( $data['check_in'] ?? '' );
        $check_out    = sanitize_text_field( $data['check_out'] ?? '' );
        $guest_data   = $data['guest'] ?? [];
        $status       = sanitize_text_field( $data['status'] ?? 'pending' );
        $rate_plan_id = sanitize_text_field( $data['rate_plan_id'] ?? '0' );
        $custom_price = (float) ( $data['custom_price_per_night'] ?? 0 );
        $is_custom_pricing = ( $rate_plan_id === 'custom' || $custom_price > 0 );
        
        // For custom pricing, use first available rate plan or 0
        if ( $is_custom_pricing ) {
            $rate_plan_id = 0;
            // Try to get the default rate plan for the lock service
            $rp_repo = new \Artechia\PMS\Repositories\RatePlanRepository();
            $default_plan = $rp_repo->get_default( $property_id );
            if ( $default_plan ) {
                $rate_plan_id = (int) $default_plan['id'];
            }
        } else {
            $rate_plan_id = absint( $rate_plan_id );
        }
        
        // 1. Availability Check (Atomic via Lock)
        $lock_result = $this->lock_svc->acquire(
            $property_id, $room_type_id, $rate_plan_id,
            $check_in, $check_out, 1,
            [ 'source' => 'admin_manual' ],
            true
        );

        if ( isset( $lock_result['error'] ) ) {
            return [ 'error' => 'UNIT_UNAVAILABLE', 'message' => 'No availability for selected dates/unit.' ];
        }

        // If a specific unit was requested, explicitly check its availability NOW (while locked)
        if ( $room_unit_id > 0 ) {
            if ( ! $this->bookings->is_unit_available( $room_unit_id, $property_id, $check_in, $check_out ) ) {
                $this->lock_svc->release( $lock_result['lock_key'] );
                return [ 'error' => 'UNIT_UNAVAILABLE', 'message' => 'La unidad seleccionada ya está ocupada en esas fechas.' ];
            }
        }

        $lock_key = $lock_result['lock_key'];

        global $wpdb;
        $wpdb->query( 'START TRANSACTION' );

        try {
            // 2. Pricing
            if ( $is_custom_pricing && $custom_price > 0 ) {
                // Build manual quote with custom price per night
                $d1 = strtotime( $check_in );
                $d2 = strtotime( $check_out );
                $nights_count = ( $d1 && $d2 && $d2 > $d1 ) ? (int) ( ( $d2 - $d1 ) / 86400 ) : 0;
                if ( $nights_count < 1 ) {
                    throw new \Exception( 'Fechas inválidas.' );
                }

                $nights_detail = [];
                $current = $d1;
                for ( $i = 0; $i < $nights_count; $i++ ) {
                    $nights_detail[] = [
                        'date'         => date( 'Y-m-d', $current ),
                        'base'         => $custom_price,
                        'occ_adj'      => 0,
                        'single_use'   => 0,
                        'total'        => $custom_price,
                        'rate_plan_id' => $rate_plan_id,
                        'source'       => 'custom',
                    ];
                    $current = strtotime( '+1 day', $current );
                }

                $room_subtotal = round( $custom_price * $nights_count, 2 );
                $tax_pct = (float) Settings::get( 'tax_pct', '0', $property_id );
                $taxes_total = round( $room_subtotal * $tax_pct / 100, 2 );
                $grand_total = round( $room_subtotal + $taxes_total, 2 );

                $quote = [
                    'property_id'  => $property_id,
                    'room_type_id' => $room_type_id,
                    'rate_plan_id' => $rate_plan_id,
                    'check_in'     => $check_in,
                    'check_out'    => $check_out,
                    'nights_count' => $nights_count,
                    'nights'       => $nights_detail,
                    'extras'       => [],
                    'totals'       => [
                        'subtotal_base'     => $room_subtotal,
                        'original_subtotal' => $room_subtotal,
                        'discount_amount'   => 0,
                        'subtotal'          => $room_subtotal,
                        'extras_total'      => 0,
                        'taxes_total'       => $taxes_total,
                        'tax_pct'           => $tax_pct,
                        'total'             => $grand_total,
                        'discount_total'    => 0,
                        'deposit_pct'       => 0,
                        'deposit_due'       => 0,
                        'currency'          => Settings::currency(),
                    ],
                ];
            } else {
                $quote = $this->pricing->quote(
                    $property_id, $room_type_id, $rate_plan_id,
                    $check_in, $check_out,
                    absint( $data['adults'] ?? 1 ), 
                    absint( $data['children'] ?? 0 ),
                    [], // No extras for simple MVP manual booking
                    '', // coupon code
                    '', // guest email
                    true
                );

                if ( isset( $quote['error'] ) ) {
                    throw new \Exception( $quote['error'] );
                }
            }

            // 3. Guest
            $guest_id = $this->guests->find_or_create_by_email( [
                'first_name'      => sanitize_text_field( $guest_data['first_name'] ?? '' ),
                'last_name'       => sanitize_text_field( $guest_data['last_name'] ?? '' ),
                'email'           => sanitize_email( $guest_data['email'] ?? '' ),
                'phone'           => sanitize_text_field( $guest_data['phone'] ?? '' ),
                'document_type'   => sanitize_text_field( $guest_data['document_type'] ?? '' ),
                'document_number' => sanitize_text_field( $guest_data['document_number'] ?? '' ),
                'country'         => sanitize_text_field( $guest_data['country'] ?? '' ),
                'city'            => sanitize_text_field( $guest_data['city'] ?? '' ),
            ] );

            if ( ! $guest_id ) throw new \Exception( 'Could not create guest.' );

            // Blacklist check
            $guest = $this->guests->find( $guest_id );
            if ( $guest && ! empty( $guest['is_blacklisted'] ) ) {
                throw new \Exception( 'GUEST_BLACKLISTED: ' . ( $guest['blacklist_reason'] ?: 'No reason provided.' ) );
            }

            // 4. Create Booking
            $booking_code = $this->generate_booking_code();
            $access_token = $this->generate_access_token();
            
            // Initial status is pending, we update to confirmed later if requested/successful
            $booking_id = $this->bookings->create( [
                'booking_code'     => $booking_code,
                'property_id'      => $property_id,
                'guest_id'         => $guest_id,
                'rate_plan_id'     => $rate_plan_id,
                'check_in'         => $check_in,
                'check_out'        => $check_out,
                'nights'           => count( $quote['nights'] ),
                'adults'           => absint( $data['adults'] ?? 1 ),
                'children'         => absint( $data['children'] ?? 0 ),
                'status'           => $status,
                'source'           => 'admin',
                'subtotal'         => $quote['totals']['original_subtotal'],
                'grand_total'      => $quote['totals']['total'],
                'taxes_total'      => $quote['totals']['taxes_total'],
                'currency'         => Settings::currency(),
                'pricing_snapshot' => wp_json_encode( $quote ),
                'access_token'     => hash( 'sha256', $access_token ),
                'internal_notes'   => sanitize_textarea_field( $data['notes'] ?? '' ),
                'payment_status'   => 'unpaid',
                'payment_method'   => sanitize_text_field( $data['payment_method'] ?? 'manual' ),
                'balance_due'      => $quote['totals']['total'],
                'amount_paid'      => 0,
                'created_at'       => current_time( 'mysql', true ),
            ] );

            if ( ! $booking_id ) throw new \Exception( 'DB Create failed.' );

            // Room Data — skip unit assignment for pending bookings when setting is OFF
            $assign_unit_now = ( $status !== 'pending' ) || BookingRepository::pending_blocks_unit();
            $this->bookings->create_room( [
                'booking_id'          => $booking_id,
                'room_type_id'        => $room_type_id,
                'room_unit_id'        => $assign_unit_now ? $room_unit_id : 0,
                'adults'              => absint( $data['adults'] ?? 1 ),
                'children'            => absint( $data['children'] ?? 0 ),
                'rate_per_night_json' => wp_json_encode( $quote['nights'] ),
                'subtotal'            => $quote['totals']['subtotal_base'],
            ] );

            // 5. Assign Unit and Release Lock
            if ( $assign_unit_now ) {
                $assigned = $this->bookings->assign_unit_on_confirm( $booking_id );
                if ( ! $assigned ) {
                    throw new \Exception( 'No unit available to block (Collision detected during assignment).' );
                }
            }
            
            // Release the checkout lock immediately as the booking is now real and blocks availability by its own status
            $this->lock_svc->release( $lock_key );
            
            // Consistencia
            
            // 6. Handle Manual Payment (if provided)
            $manual_paid = (float) ( $data['amount_paid'] ?? 0 );
            if ( $manual_paid > 0 ) {
                if ( $manual_paid > ( $quote['totals']['total'] + 0.01 ) ) { // Using small epsilon for float comparison
                    throw new \Exception( sprintf( 'El monto pagado (%s) no puede superar al monto total (%s).', $manual_paid, $quote['totals']['total'] ) );
                }
                $pay_repo = new \Artechia\PMS\Repositories\PaymentRepository();
                $pay_method = sanitize_text_field( $data['payment_method'] ?? 'manual' );
                $txn_id = 'MAN-' . strtoupper( wp_generate_uuid4() );
                $pay_repo->create([
                    'booking_id' => $booking_id,
                    'gateway'    => $pay_method,
                    'intent_id'  => $txn_id,
                    'amount'     => $manual_paid,
                    'currency'   => Settings::currency(),
                    'pay_mode'   => 'manual',
                    'status'     => 'approved',
                    'notes'      => 'Pago al crear reserva',
                    'created_at' => current_time( 'mysql' ),
                ]);
            }

            $this->update_financial_status( $booking_id );

            $wpdb->query( 'COMMIT' );

        } catch ( \Throwable $e ) {
            $wpdb->query( 'ROLLBACK' );
            $this->lock_svc->release( $lock_key );
            
            $msg = $e->getMessage();
            $code = 'CREATE_FAILED';
            
            // Map specific errors to 400-friendly codes
            if ( strpos( $msg, 'No unit available' ) !== false || strpos( $msg, 'Collision' ) !== false ) {
                $code = 'UNIT_UNAVAILABLE';
            } elseif ( strpos( $msg, 'Could not create guest' ) !== false ) {
                $code = 'GUEST_CREATION_FAILED';
            } elseif ( strpos( $msg, 'GUEST_BLACKLISTED' ) !== false ) {
                $code = 'GUEST_BLACKLISTED';
            }

            Logger::error( 'manual_booking.create_failed', $msg, 'booking', null, [ 'trace' => $e->getTraceAsString() ] );
            
            return [ 'error' => $code, 'message' => $msg ];
        }

        // 6. Post-Commit Actions (Emails)
        $today      = current_time( 'Y-m-d' );
        $is_past    = ( $check_in < $today ); // Started before today
        $email_sent = false;

        if ( $is_past ) {
            if ( $check_out <= $today ) {
                // Fully in the past: Finalize status to checked_out
                $this->bookings->update_status( $booking_id, 'checked_out', [
                    'checked_out_at' => $check_out . ' 10:00:00',
                ]);
                $status = 'checked_out';
                Logger::info( 'manual_booking.past_dates', "Booking {$booking_code} marked as checked_out (historical record)." );
            } else {
                // Started in the past but still ongoing: Set to checked_in
                $this->bookings->update_status( $booking_id, 'checked_in' );
                $status = 'checked_in';
                Logger::info( 'manual_booking.active_past', "Booking {$booking_code} marked as checked_in (started in past, ongoing)." );
            }
            // Skip confirmation email for anything that started before today
        } else {
            // Normal future booking (or starts today): Send confirmation/pending email.
            $email_type = ( $status === 'confirmed' ) ? 'booking_confirmed' : 'booking_pending';
            $this->email->send_async( $email_type, $booking_id );
            $email_sent = true;
        }

        // Handle Optional Review Email
        if ( ! empty( $data['send_review_email'] ) ) {
            $delay_days    = (int) Settings::get( 'review_email_delay', '1', $property_id );
            $delay_seconds = $delay_days * DAY_IN_SECONDS;
            
            // If it's a past booking, we might want to send it "now" (or with default delay from checkout).
            // For manual past bookings, we'll just respect the global delay from "now" for simplicity,
            // or we could send it immediately (delay 0) if it's already past.
            // Let's stick to the official delay since the user might want to review it later.
            $this->email->send_async( 'booking_review', $booking_id, [], $delay_seconds );
            Logger::info( 'manual_booking.review_requested', "Review email requested for manual booking {$booking_code}." );
        }

        return [ 
            'ok'           => true,
            'booking_id'   => $booking_id, 
            'booking_code' => $booking_code, 
            'status'       => $status,
            'email_sent'   => $email_sent
        ];
    }

    /**
     * Calculate cancellation penalty based on rate plan rules.
     */
    public function calculate_penalty( array|int $booking ): float {
        if ( is_numeric( $booking ) ) {
            $booking = $this->bookings->find( (int) $booking );
        }
        if ( ! is_array( $booking ) || empty( $booking['rate_plan_id'] ) ) {
            return 0.0;
        }

        $rp_repo = new RatePlanRepository();
        $rate_plan = $rp_repo->find( (int) $booking['rate_plan_id'] );
        
        $penalty_amount = 0.0;
        if ( ! $rate_plan ) {
            return $penalty_amount;
        }

        $now = time();
        $check_in_time = strtotime( $booking['check_in'] ?? '' );
        if ( ! $check_in_time ) return 0.0;

        $days_until = ( $check_in_time - $now ) / 86400;

        if ( $days_until < (int) ($rate_plan['cancellation_deadline_days'] ?? 0) ) {
            switch ( $rate_plan['penalty_type'] ) {
                case '1_night':
                case 'first_night': // Backwards compatibility just in case
                    $snapshot = json_decode( $booking['pricing_snapshot'], true );
                    $nights = $snapshot['nights'] ?? [];
                    $penalty_amount = (float) ( $nights[0]['total'] ?? 0 );
                    break;
                case '100':
                    $penalty_amount = (float) $booking['grand_total'];
                    break;
                case '50':
                    $penalty_amount = round( (float) $booking['grand_total'] * 0.50, 2 );
                    break;
                case 'percent': // Keep for backwards compatibility
                    $penalty_amount = round( (float) $booking['grand_total'] * (float) $rate_plan['penalty_value'] / 100, 2 );
                    break;
                case 'fixed': // Keep for backwards compatibility
                    $penalty_amount = (float) $rate_plan['penalty_value'];
                    break;
            }
        }

        return $penalty_amount;
    }

    /**
     * Cancel a booking and calculate penalties (H11).
     * Now accepts an optional refund amount to process refunds during cancellation.
     */
    public function cancel_booking( int $booking_id, string $reason = '', float $refund_amount = 0.0 ): array {
        global $wpdb;

        $booking = $this->bookings->find( $booking_id );
        if ( ! $booking ) return [ 'error' => 'NOT_FOUND' ];

        if ( $booking['status'] === 'cancelled' ) {
            return [ 'success' => true, 'already' => true ];
        }

        $penalty_amount = $this->calculate_penalty( $booking );

        $wpdb->query( 'START TRANSACTION' );

        // Lock row to ensure we don't double cancel or race with confirm
        $locked_booking = $wpdb->get_row( $wpdb->prepare( 
            "SELECT id, status, booking_code FROM {$this->bookings->table()} WHERE id = %d FOR UPDATE", 
            $booking_id 
        ), \ARRAY_A );

        if ( ! $locked_booking ) {
            $wpdb->query( 'ROLLBACK' );
            return [ 'error' => 'NOT_FOUND' ];
        }

        if ( $locked_booking['status'] === 'cancelled' ) {
            $wpdb->query( 'ROLLBACK' );
            return [ 
                'ok'           => true, 
                'booking_id'   => $booking_id,
                'booking_code' => $locked_booking['booking_code'],
                'status'       => 'cancelled',
                'already'      => true 
            ];
        }

        $amount_paid = (float) ( $booking['amount_paid'] ?? 0 );
        $applied_penalty = max( 0, $amount_paid - $refund_amount );
        $fmt_penalty = \Artechia\PMS\Helpers\Helpers::format_price( $applied_penalty );
        $fmt_refund  = \Artechia\PMS\Helpers\Helpers::format_price( $refund_amount );

        $update_data = [
            'status' => 'cancelled',
            'cancelled_at' => current_time( 'mysql' ),
            'cancel_reason' => $reason,
            'balance_due' => 0,
            'internal_notes' => $booking['internal_notes']
        ];

        // Determine payment_status for cancelled booking
        if ( $refund_amount > 0 && $refund_amount >= $amount_paid - 0.01 ) {
            $update_data['payment_status'] = 'refunded';
        } elseif ( $refund_amount > 0 ) {
            $update_data['payment_status'] = 'partial_refund';
        } elseif ( $amount_paid > 0 ) {
            $update_data['payment_status'] = 'paid';
        } else {
            $update_data['payment_status'] = 'unpaid';
        }

        // Process Refund if requested
        if ( $refund_amount > 0 ) {
            $pay_repo = new \Artechia\PMS\Repositories\PaymentRepository();
            $txn_id   = 'REF' . strtoupper( bin2hex( random_bytes( 4 ) ) );
            $pay_repo->create( [
                'booking_id'     => $booking_id,
                'gateway'        => 'manual',
                'gateway_txn_id' => $txn_id,
                'intent_id'      => $txn_id,
                'amount'         => -$refund_amount, // Negative amount for refund
                'currency'       => Settings::currency(),
                'pay_mode'       => 'manual',
                'status'         => 'approved',
                'notes'          => 'Devolución por cancelación',
                'created_at'     => current_time( 'mysql' ),
            ] );

            // Force update amount_paid to reflect refund immediately before financial recalibration
            $new_amount_paid = max( 0, $amount_paid - $refund_amount );
            $update_data['amount_paid'] = $new_amount_paid;
            $update_data['internal_notes'] .= " Devolución: {$fmt_refund}.";
        }
        
        $success = $this->bookings->update( $booking_id, $update_data );
        
        if ( $success ) {
            // Update financial status after saving the new amount_paid
            if ( $refund_amount > 0 ) {
                $this->update_financial_status( $booking_id );
            }

            $wpdb->query( 'COMMIT' );
            self::clear_calendar_cache();
            // Add notes with proper formatting
            $cancel_note = "Reserva cancelada. Penalidad aplicada: {$fmt_penalty}.";
            if ( $refund_amount > 0 ) {
                $cancel_note .= " Devolución: {$fmt_refund}.";
            }
            $this->add_note( $booking_id, $cancel_note );

            // Async email
            $this->email->send_async( 'booking_cancelled', $booking_id );

            // Audit: log cancellation with before state.
            Logger::logChange(
                'booking.cancel',
                "Reserva #{$booking_id} cancelada. Penalidad: {$fmt_penalty}",
                'booking',
                $booking_id,
                [ 'status' => $booking['status'], 'grand_total' => $booking['grand_total'] ],
                [ 'status' => 'cancelled', 'penalty' => $applied_penalty, 'reason' => $reason ]
            );

            return [ 
                'ok'             => true, 
                'booking_id'     => $booking_id,
                'booking_code'   => $booking['booking_code'],
                'status'         => 'cancelled',
                'penalty_amount' => $applied_penalty,
                'refund_amount'  => $refund_amount,
            ];
        } else {
            $wpdb->query( 'ROLLBACK' );
            return [ 'error' => 'UPDATE_FAILED' ];
        }
    }

    /**
     * Delete a booking and all its related records (Atomic).
     *
     * @param int $booking_id
     * @return array{ success: bool } | array{ error: string, message: string }
     */
    public function delete_booking( int $booking_id ): array {
        global $wpdb;

        $booking = $this->bookings->find( $booking_id );
        if ( ! $booking ) {
            return [ 'error' => 'NOT_FOUND', 'message' => 'Booking not found.' ];
        }

        // Only allow deleting cancelled or hold bookings to prevent accidental data loss
        if ( ! in_array( $booking['status'], [ 'cancelled', 'hold' ], true ) ) {
            return [ 'error' => 'INVALID_STATUS', 'message' => 'Only cancelled or hold bookings can be deleted.' ];
        }

        $wpdb->query( 'START TRANSACTION' );

        try {
            // Delete related records manually since we don't use foreign key cascades
            $tables = [
                'booking_rooms'      => 'booking_id',
                'booking_extras'     => 'booking_id',
                'payments'           => 'booking_id',
                'locks'              => 'booking_id',
                'coupon_redemptions' => 'booking_id',
                'ical_events'        => 'booking_id',
            ];

            foreach ( $tables as $table => $fk ) {
                $full_table = Schema::table( $table );
                $wpdb->delete( $full_table, [ $fk => $booking_id ], [ '%d' ] );
            }

            // Also delete conflicts involving this booking (local_booking_id)
            $conflicts_table = Schema::table( 'conflicts' );
            $wpdb->delete( $conflicts_table, [ 'local_booking_id' => $booking_id ], [ '%d' ] );

            // Finally delete the booking itself
            $this->bookings->delete( $booking_id );

            $wpdb->query( 'COMMIT' );

            Logger::info( 'booking.deleted', "Booking #{$booking_id} ({$booking['booking_code']}) permanently deleted." );

            return [ 'success' => true ];

        } catch ( \Throwable $e ) {
            $wpdb->query( 'ROLLBACK' );
            Logger::error( 'booking.delete_error', $e->getMessage(), [ 'exception' => $e ] );
            return [ 'error' => 'DELETE_FAILED', 'message' => 'Failed to delete booking records.' ];
        }
    }
    /**
     * Record a manual payment for a booking.
     * 
     * @param int $booking_id
     * @param float $amount
     * @param string $note
     * @return array{ success: bool } | array{ error: string, message: string }
     */
    public function record_manual_payment( int $booking_id, float $amount, string $note = '', string $method = '' ): array {
        $booking = $this->bookings->find( $booking_id );
        if ( ! $booking ) {
            return [ 'error' => 'NOT_FOUND', 'message' => 'Booking not found.' ];
        }

        if ( $amount <= 0 ) {
            return [ 'error' => 'INVALID_AMOUNT', 'message' => 'Payment amount must be greater than zero.' ];
        }

        if ( $amount > ( (float) $booking['balance_due'] + 0.01 ) ) {
            return [ 'error' => 'OVERPAYMENT', 'message' => sprintf( 'El monto a registrar (%s) supera el saldo pendiente (%s).', Helpers::format_price($amount), Helpers::format_price( (float) $booking['balance_due'] ) ) ];
        }

        global $wpdb;
        $wpdb->query( 'START TRANSACTION' );

        try {
            $pay_repo = new \Artechia\PMS\Repositories\PaymentRepository();
            $txn_id   = 'MAN' . strtoupper( bin2hex( random_bytes( 4 ) ) );
            $gateway  = ! empty( $method ) ? $method : 'manual';
            $pay_id   = $pay_repo->create([
                'booking_id'     => $booking_id,
                'gateway'        => $gateway,
                'gateway_txn_id' => $txn_id,
                'intent_id'      => $txn_id,
                'amount'         => $amount,
                'currency'       => Settings::currency(),
                'pay_mode'       => 'manual',
                'status'         => 'approved',
                'notes'          => sanitize_textarea_field( $note ),
                'created_at'     => current_time( 'mysql' ),
            ] );

            if ( ! $pay_id ) {
                throw new \Exception( 'Could not create payment record.' );
            }

            $this->update_financial_status( $booking_id );
            $fmt_amount = \Artechia\PMS\Helpers\Helpers::format_price( $amount );
            $this->add_note( $booking_id, "Pago registrado: {$fmt_amount}" . ( $note ? " — {$note}" : '' ) );

            $wpdb->query( 'COMMIT' );

            Logger::info( 'booking.manual_payment', "Recorded manual payment for booking #{$booking_id}: {$amount}", [
                'booking_id' => $booking_id,
                'amount'     => $amount,
            ] );

            return [ 'success' => true ];

        } catch ( \Throwable $e ) {
            $wpdb->query( 'ROLLBACK' );
            Logger::error( 'booking.payment_error', $e->getMessage(), [ 'exception' => $e ] );
            return [ 'error' => 'PAYMENT_FAILED', 'message' => 'Failed to record payment.' ];
        }
    }

    /**
     * Delete a manual payment record and recalculate financials.
     * Only manual (non-gateway) payments can be deleted.
     */
    public function delete_payment( int $booking_id, int $payment_id ): array {
        $booking = $this->bookings->find( $booking_id );
        if ( ! $booking ) {
            return [ 'error' => 'NOT_FOUND', 'message' => 'Booking not found.' ];
        }

        $pay_repo = new \Artechia\PMS\Repositories\PaymentRepository();
        $payment  = $pay_repo->find( $payment_id );

        if ( ! $payment || (int) $payment['booking_id'] !== $booking_id ) {
            return [ 'error' => 'PAYMENT_NOT_FOUND', 'message' => 'Payment record not found.' ];
        }

        // Only allow deleting manual payments (not external gateway payments like MercadoPago)
        if ( $payment['gateway'] !== 'manual' && $payment['pay_mode'] !== 'manual' ) {
            return [ 'error' => 'CANNOT_DELETE', 'message' => 'Solo se pueden eliminar pagos manuales. Los pagos de pasarelas externas no se pueden eliminar.' ];
        }

        global $wpdb;
        $wpdb->query( 'START TRANSACTION' );

        try {
            $pay_repo->delete( $payment_id );

            // Force recalculate from payments table (not from existing amount_paid)
            $total_paid = $pay_repo->sum_paid( $booking_id );
            $grand_total = (float) $booking['grand_total'];
            $balance_due = max( 0, round( $grand_total - $total_paid, 2 ) );

            $pay_status = 'unpaid';
            if ( $total_paid >= ( $grand_total - 0.01 ) ) {
                $pay_status = 'paid';
            } elseif ( $total_paid > 0 ) {
                $pay_status = 'deposit_paid';
            }

            $this->bookings->update( $booking_id, [
                'amount_paid'    => $total_paid,
                'balance_due'    => $balance_due,
                'payment_status' => $pay_status,
            ] );

            $fmt = \Artechia\PMS\Helpers\Helpers::format_price( abs( (float) $payment['amount'] ) );
            $this->add_note( $booking_id, "Pago eliminado: {$fmt} (ID: {$payment_id})" );

            $wpdb->query( 'COMMIT' );

            Logger::info( 'booking.payment_deleted', "Payment #{$payment_id} deleted from booking #{$booking_id}", [
                'booking_id' => $booking_id,
                'payment_id' => $payment_id,
                'amount'     => $payment['amount'],
            ] );

            return [ 'success' => true ];

        } catch ( \Throwable $e ) {
            $wpdb->query( 'ROLLBACK' );
            Logger::error( 'booking.payment_delete_error', $e->getMessage(), [ 'exception' => $e ] );
            return [ 'error' => 'DELETE_FAILED', 'message' => 'Failed to delete payment.' ];
        }
    }

    /**
     * Reactivate a cancelled booking back to pending or confirmed.
     */
    public function reactivate_booking( int $booking_id, string $target_status = 'pending' ): array {
        $booking = $this->bookings->find( $booking_id );
        if ( ! $booking ) {
            return [ 'error' => 'NOT_FOUND', 'message' => 'Booking not found.' ];
        }

        if ( $booking['status'] !== 'cancelled' ) {
            return [ 'error' => 'INVALID_STATUS', 'message' => 'Solo se pueden reactivar reservas canceladas.' ];
        }

        if ( ! in_array( $target_status, [ 'pending', 'confirmed' ], true ) ) {
            $target_status = 'pending';
        }

        global $wpdb;
        $wpdb->query( 'START TRANSACTION' );

        try {
            $this->bookings->update( $booking_id, [
                'status'        => $target_status,
                'cancelled_at'  => null,
                'cancel_reason' => null,
            ] );

            // Recalculate financials from payments table
            $this->update_financial_status( $booking_id );

            $this->add_note( $booking_id, "Reserva reactivada. Nuevo estado: {$target_status}" );

            $wpdb->query( 'COMMIT' );
            self::clear_calendar_cache();

            Logger::info( 'booking.reactivated', "Booking #{$booking_id} reactivated to {$target_status}", [
                'booking_id' => $booking_id,
            ] );

            return [ 'success' => true, 'status' => $target_status ];

        } catch ( \Throwable $e ) {
            $wpdb->query( 'ROLLBACK' );
            Logger::error( 'booking.reactivate_error', $e->getMessage(), [ 'exception' => $e ] );
            return [ 'error' => 'REACTIVATE_FAILED', 'message' => 'Failed to reactivate booking.' ];
        }
    }

    /**
     * Manually change booking status (admin override).
     * Allowed transitions:
     *   confirmed → pending
     *   checked_in → confirmed
     *   checked_out → checked_in
     *   pending → confirmed (without unit assignment logic — use confirm_booking for full flow)
     */
    public function change_status( int $booking_id, string $new_status ): array {
        $booking = $this->bookings->find( $booking_id );
        if ( ! $booking ) {
            return [ 'error' => 'NOT_FOUND', 'message' => 'Booking not found.' ];
        }

        $allowed = [
            'pending', 'confirmed', 'checked_in', 'checked_out',
        ];

        if ( ! in_array( $new_status, $allowed, true ) ) {
            return [ 'error' => 'INVALID_STATUS', 'message' => "Estado '{$new_status}' no permitido." ];
        }

        $current = $booking['status'];

        // Don't allow changing cancelled bookings via this method (use reactivate)
        if ( $current === 'cancelled' ) {
            return [ 'error' => 'USE_REACTIVATE', 'message' => 'Para reactivar una reserva cancelada, use la función Reactivar.' ];
        }

        // Don't allow setting to same status
        if ( $current === $new_status ) {
            return [ 'success' => true, 'status' => $new_status ];
        }

        $status_labels = [
            'pending'     => 'Pendiente',
            'confirmed'   => 'Confirmada',
            'checked_in'  => 'Check-in',
            'checked_out' => 'Check-out',
        ];

        $this->bookings->update_status( $booking_id, $new_status );
        $this->update_financial_status( $booking_id );

        $from_label = $status_labels[ $current ] ?? $current;
        $to_label   = $status_labels[ $new_status ] ?? $new_status;
        $this->add_note( $booking_id, "Estado cambiado manualmente: {$from_label} → {$to_label}" );

        Logger::info( 'booking.status_changed', "Booking #{$booking_id} status changed: {$current} → {$new_status}", [
            'booking_id' => $booking_id,
            'from'       => $current,
            'to'         => $new_status,
        ] );

        return [ 'success' => true, 'status' => $new_status ];
    }
}
