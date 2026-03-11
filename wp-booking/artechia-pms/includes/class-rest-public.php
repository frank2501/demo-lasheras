<?php
/**
 * Public REST Controller (H11).
 * 
 * Handles Guest-facing API endpoints: Quote re-calc, Cancellation, etc.
 */
namespace Artechia\PMS;

use Artechia\PMS\Services\PricingService;
use Artechia\PMS\Services\BookingService;
use Artechia\PMS\Logger;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class RestPublic {

    private $namespace = 'artechia/v1';

    public function register_routes() {
        // POST /public/quote/apply-coupon (Dynamic re-calculation for checkout)
        register_rest_route( $this->namespace, '/public/quote/apply-coupon', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'apply_coupon' ],
            'permission_callback' => '__return_true',
        ] );

        // POST /public/booking/(?P<code>[A-Za-z0-9]+)/cancel (Guest cancellation with code/token)
        register_rest_route( $this->namespace, '/public/booking/(?P<code>[A-Za-z0-9\-]+)/cancel', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'cancel_booking' ],
            'permission_callback' => '__return_true',
            'args'                => [
                'token' => [ 'required' => true, 'type' => 'string' ],
            ],
        ] );

        // GET /public/booking/(?P<code>[A-Za-z0-9]+)
        register_rest_route( $this->namespace, '/public/booking/(?P<code>[A-Za-z0-9\-]+)', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'get_booking' ],
            'permission_callback' => '__return_true',
            'args'                => [
                'token' => [ 'required' => true, 'type' => 'string' ],
            ],
        ] );

        // POST /public/booking/find
        register_rest_route( $this->namespace, '/public/booking/find', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'find_booking' ],
            'permission_callback' => '__return_true',
        ] );

        // POST /public/availability
        register_rest_route( $this->namespace, '/public/availability', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'get_availability' ],
            'permission_callback' => '__return_true',
        ] );

        // POST /public/quote
        register_rest_route( $this->namespace, '/public/quote', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'get_quote' ],
            'permission_callback' => '__return_true',
        ] );

        // POST /public/checkout/start
        register_rest_route( $this->namespace, '/public/checkout/start', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'start_checkout' ],
            'permission_callback' => '__return_true',
        ] );

        // POST /public/checkout/confirm (Alias for create_booking, but semantic)
        register_rest_route( $this->namespace, '/public/checkout/confirm', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'create_booking' ],
            'permission_callback' => '__return_true',
        ] );

        // GET /public/ical/export/unit/(?P<id>\d+)
        register_rest_route( $this->namespace, '/public/ical/export/unit/(?P<id>\d+)', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'export_ical' ],
            'permission_callback' => '__return_true',
            'args'                => [
                'token' => [ 'required' => true, 'type' => 'string' ],
            ],
        ] );

        // POST /public/booking/(?P<code>[A-Za-z0-9\-]+)/verify-payment
        register_rest_route( $this->namespace, '/public/booking/(?P<code>[A-Za-z0-9\-]+)/verify-payment', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'verify_payment' ],
            'permission_callback' => '__return_true',
            'args'                => [
                'token'      => [ 'required' => true, 'type' => 'string' ],
                'payment_id' => [ 'required' => true, 'type' => 'string' ],
            ],
        ] );

        // GET /public/property/(?P<id>\d+)/extras
        register_rest_route( $this->namespace, '/public/property/(?P<id>\d+)/extras', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'get_extras' ],
            'permission_callback' => '__return_true',
        ] );



        // GET /public/calendar-hints — Availability + promo data for calendar coloring
        register_rest_route( $this->namespace, '/public/calendar-hints', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'get_calendar_hints' ],
            'permission_callback' => '__return_true',
        ] );

        // GET /public/track/click — Email click tracking redirect
        register_rest_route( $this->namespace, '/public/track/click', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'track_click' ],
            'permission_callback' => '__return_true',
        ] );
    }

    /**
     * Wrap a callback in try/catch. Logs errors, never exposes stack traces.
     */
    private function safe_response( callable $fn ): \WP_REST_Response {
        try {
            return $fn();
        } catch ( \Throwable $e ) {
            $log_id = Logger::critical( 'rest.error', $e->getMessage(), 'rest_public', null, [
                'file'  => $e->getFile(),
                'line'  => $e->getLine(),
                'trace' => substr( $e->getTraceAsString(), 0, 2000 ),
            ] );
            return new \WP_REST_Response( [
                'error'      => 'INTERNAL_ERROR',
                'message'    => 'An unexpected error occurred. Please try again or contact support.',
                'request_id' => $log_id,
            ], 500 );
        }
    }

    /**
     * Search availability + quotes.
     */
    public function get_availability( \WP_REST_Request $request ) {
        return $this->safe_response( function () use ( $request ) {
            $params = $request->get_json_params();
            
            $property_id = absint( $params['property_id'] ?? 0 );
            $check_in    = sanitize_text_field( $params['check_in'] ?? '' );
            $check_out   = sanitize_text_field( $params['check_out'] ?? '' );
            $adults      = absint( $params['adults'] ?? 2 );
            $children    = absint( $params['children'] ?? 0 );

            if ( ! $property_id || ! $check_in || ! $check_out ) {
                return new \WP_REST_Response( [ 'error' => 'MISSING_PARAMS' ], 400 );
            }

            $pricing_svc = new PricingService();
            
            $avail_svc = new \Artechia\PMS\Services\AvailabilityService();

            // 0. Preliminary Global Booking Block Check (matches PricingService logic)
            $quote_test = $pricing_svc->quote( $property_id, 0, 0, $check_in, $check_out );
            if ( isset( $quote_test['error'] ) && $quote_test['error'] === 'BOOKINGS_DISABLED' ) {
                return new \WP_REST_Response( [ 
                    'error'   => 'BOOKINGS_DISABLED',
                    'message' => $quote_test['message'],
                    'redirect'=> $quote_test['redirect'] ?? null
                ], 200 );
            }

            // Check specific closure block
            $closure_setting = \Artechia\PMS\Services\Settings::get( 'closure_dates', '' );
            if ( ! empty( $closure_setting ) ) {
                $c_in  = new \DateTime( $check_in );
                $c_out = new \DateTime( $check_out );
                $c_out->modify( '-1 day' ); // Stay is inclusive of check-in, exclusive of check-out night
                $is_closed = false;

                $parts = explode( ',', $closure_setting );
                foreach ( $parts as $part ) {
                    $part = trim( $part );
                    if ( empty( $part ) ) continue;
                    if ( strpos( $part, ':' ) !== false ) {
                        list( $from_str, $to_str ) = explode( ':', $part );
                        $f = new \DateTime( trim( $from_str ) );
                        $t = new \DateTime( trim( $to_str ) );
                        if ( $c_in <= $t && $c_out >= $f ) {
                            $is_closed = true; break;
                        }
                    } else {
                        $d = new \DateTime( $part );
                        if ( $c_in <= $d && $c_out >= $d ) {
                            $is_closed = true; break;
                        }
                    }
                }

                if ( $is_closed ) {
                    $closure_mode = \Artechia\PMS\Services\Settings::get( 'closure_mode', 'simple' );
                    $message = '';
                    $redirect = null;

                    if ( $closure_mode === 'simple' ) {
                        $message = \Artechia\PMS\Services\Settings::get( 'closure_reason', __( 'Las fechas seleccionadas no están disponibles.', 'artechia-pms' ) );
                    } else {
                        $page_id = \Artechia\PMS\Services\Settings::get( 'closure_page' );
                        if ( $page_id ) {
                            $redirect = get_permalink( $page_id );
                            $message  = sprintf( __( 'Por favor visite <a href="%s">esta página</a> para más información sobre disponibilidad.', 'artechia-pms' ), $redirect );
                        } else {
                            $message = __( 'Las fechas seleccionadas no están disponibles.', 'artechia-pms' );
                        }
                    }

                    return new \WP_REST_Response( [ 
                        'error'   => 'DATES_CLOSED',
                        'message' => $message,
                        'redirect'=> $redirect
                    ], 200 );
                }
            }
            $filters   = [];
            if ( ! empty( $params['debug'] ) ) {
                $filters['debug'] = true;
            }
            $final_results = [];
            $all_failed_reasons = [];

            // Resolve proper Rate Plan ID for quoting
            $rate_plan_id_resolved = $rate_plan_id = absint( $params['rate_plan_id'] ?? 0 );
            if ( ! $rate_plan_id_resolved ) {
                $rp_repo = new \Artechia\PMS\Repositories\RatePlanRepository();
                
                // First try to find the active plan for the check-in date
                $active_plan = $rp_repo->find_for_date( $property_id, $check_in );
                if ( $active_plan ) {
                    $rate_plan_id_resolved = (int) $active_plan['id'];
                } else {
                    // Fallback to default
                    $def = $rp_repo->get_default( $property_id );
                    if ( $def ) {
                        $rate_plan_id_resolved = (int) $def['id'];
                    }
                }
            }
            
            // Pass the resolved active plan ID into the search filters
            $filters['rate_plan_id'] = $rate_plan_id_resolved;

            $room_types = $avail_svc->search( $property_id, $check_in, $check_out, $adults, $children, $filters );

            foreach ( $room_types as $rt ) {
                $all_failed_reasons = array_merge( $all_failed_reasons, $rt['fail_reasons'] ?? [] );

                // If bookable, get quote.
                if ( $rt['bookable'] ) {
                    $quote = $pricing_svc->quote(
                        $property_id,
                        (int) $rt['room_type_id'],
                        $rate_plan_id_resolved, // Use resolved ID
                        $check_in,
                        $check_out,
                        $adults,
                        $children
                    );


                    if ( ! isset( $quote['error'] ) ) {
                        $rt['quote'] = $quote['totals']; 
                        // Inject detailed quote info for frontend (nights array is used for count)
                        $rt['quote']['nights']       = $quote['nights'];
                        $rt['quote']['nights_count'] = $quote['nights_count'];
                        $rt['quote']['adults']       = $quote['adults'];
                        $rt['quote']['children']     = $quote['children'];

                        // Decode photos for JS (expects array)
                        if ( ! empty( $rt['photos_json'] ) ) {
                            $decoded = json_decode( $rt['photos_json'] );
                            $rt['photos'] = is_array( $decoded ) ? $decoded : [];
                        } else {
                            $rt['photos'] = [];
                        }

                        // Also useful to send rate plan ID used
                        $rt['rate_plan_id'] = $quote['rate_plan_id'];
                        $final_results[] = $rt;
                    } else {
                        // Quote failed
                        $rt['bookable'] = false;
                        $rt['fail_reasons'][] = 'QUOTE_ERROR';
                    }
                }
                
                // For admins, we might want to see the non-bookable ones in the response if debug is on
                if ( ! $rt['bookable'] && ! empty( $filters['debug'] ) ) {
                    $final_results[] = $rt;
                }
            }

            if ( empty( $final_results ) ) {
                $all_failed_reasons = array_unique( $all_failed_reasons );
                $error_code = 'NO_AVAILABILITY';
                
                if ( in_array( 'NO_RATE', $all_failed_reasons, true ) ) {
                    $error_code = 'NO_RATES';
                }

                return new \WP_REST_Response( [ 
                    'error'        => $error_code,
                    'fail_reasons' => $all_failed_reasons 
                ], 200 ); // Returning 200 with error property is common in this JS
            }

            return new \WP_REST_Response( [ 'room_types' => $final_results ], 200 );
        } );
    }

    /**
     * Get a quote.
     */
    public function get_quote( \WP_REST_Request $request ) {
        return $this->safe_response( function () use ( $request ) {
            $params = $request->get_json_params();

            $property_id = absint( $params['property_id'] ?? 0 );
            $room_type_id= absint( $params['room_type_id'] ?? 0 );
            $rate_plan_id= absint( $params['rate_plan_id'] ?? 0 );
            $check_in    = sanitize_text_field( $params['check_in'] ?? '' );
            $check_out   = sanitize_text_field( $params['check_out'] ?? '' );
            $adults      = absint( $params['adults'] ?? 2 );
            $children    = absint( $params['children'] ?? 0 );
            $extras      = isset( $params['extras'] ) && is_array( $params['extras'] ) ? $params['extras'] : [];
            $coupon_code = sanitize_text_field( $params['coupon_code'] ?? '' );
            $email       = sanitize_email( $params['guest_email'] ?? '' );

            $pricing_svc = new PricingService();
            $quote = $pricing_svc->quote(
                $property_id, $room_type_id, $rate_plan_id,
                $check_in, $check_out, $adults, $children,
                $extras, $coupon_code, $email
            );

            if ( isset( $quote['error'] ) ) {
                return new \WP_REST_Response( $quote, 400 );
            }

            return new \WP_REST_Response( $quote, 200 );
        } );
    }

    /**
     * Re-calculate quote with a coupon.
     */
    public function apply_coupon( \WP_REST_Request $request ) {
        return $this->safe_response( function () use ( $request ) {
            $params = $request->get_json_params();
            
            if ( empty( $params['quote'] ) || empty( $params['coupon_code'] ) ) {
                return new \WP_REST_Response( [ 'error' => 'MISSING_PARAMS' ], 400 );
            }

            $pricing = new PricingService();
            $quote = $params['quote'];
            $email = sanitize_email( $params['guest_email'] ?? '' );
            
            $new_quote = $pricing->apply_promotion( $quote, sanitize_text_field( $params['coupon_code'] ), $email );
            
            return new \WP_REST_Response( $new_quote, 200 );
        } );
    }

    /**
     * Guest-initiated cancellation.
     */
    public function cancel_booking( \WP_REST_Request $request ) {
        return $this->safe_response( function () use ( $request ) {
            $code  = sanitize_text_field( $request['code'] );
            $token = sanitize_text_field( $request['token'] );

            $bookings_repo = new \Artechia\PMS\Repositories\BookingRepository();
            $booking = $bookings_repo->find_by_code( $code );

            if ( ! $booking ) {
                return new \WP_REST_Response( [ 'error' => 'NOT_FOUND' ], 404 );
            }

            // Validate token (H3: access_token is a sha256 hash in DB)
            // UPDATE: We store raw tokens/hash in DB directly exposed in link. Compare directly.
            if ( ! hash_equals( $booking['access_token'], $token ) ) {
                return new \WP_REST_Response( [ 'error' => 'INVALID_TOKEN' ], 403 );
            }

            $service = new BookingService();
            $result  = $service->cancel_booking( (int) $booking['id'], 'Guest cancelled via portal.' );

            if ( isset( $result['error'] ) ) {
                return new \WP_REST_Response( $result, 400 );
            }

            return new \WP_REST_Response( $result, 200 );
        } );
    }

    /**
     * Initialize a checkout session (H3).
     */
    public function start_checkout( \WP_REST_Request $request ) {
        return $this->safe_response( function () use ( $request ) {
            $params  = $request->get_json_params();
            $service = new BookingService();
            $result = $service->start_checkout( $params );

            if ( isset( $result['error'] ) ) {
                return new \WP_REST_Response( $result, 400 );
            }
            return new \WP_REST_Response( $result, 200 );
        } );
    }

    /**
     * Finalize booking creation from a checkout token.
     */
    public function create_booking( \WP_REST_Request $request ) {
        // Rate limit: max 5 booking attempts per minute per IP
        if ( ! RateLimiter::check( 'public_booking', 5, 60 ) ) {
            return RateLimiter::limited_response();
        }

        return $this->safe_response( function () use ( $request ) {
            $params  = $request->get_json_params();
            $service = new BookingService();
            
            // Normalize guest params (public.js sends 'guest', service expects 'guest_data')
            $guest_data = $params['guest_data'] ?? $params['guest'] ?? [];

            $payment_method = sanitize_text_field( $params['payment_method'] ?? 'mercadopago' );
            if ( ! in_array( $payment_method, [ 'mercadopago', 'bank_transfer' ] ) ) {
                 $payment_method = 'mercadopago';
            }

            // Extras selected on checkout page (not in token — token was issued before extras step)
            $extras = isset( $params['extras'] ) && is_array( $params['extras'] ) ? $params['extras'] : [];

            $result = $service->create_booking(
                sanitize_text_field( $params['checkout_token'] ?? '' ),
                (array) $guest_data,
                (bool) ( $params['accept_terms'] ?? false ),
                sanitize_text_field( $params['coupon_code'] ?? '' ),
                $payment_method,
                $extras
            );

            if ( isset( $result['error'] ) ) {
                return new \WP_REST_Response( $result, 400 );
            }
            return new \WP_REST_Response( $result, 200 );
        } );
    }
    /**
     * Get booking details for My Booking page (H3).
     */
    public function get_booking( \WP_REST_Request $request ) {
        return $this->safe_response( function () use ( $request ) {
            $code  = sanitize_text_field( $request['code'] );
            $token = sanitize_text_field( $request['token'] );

            \Artechia\PMS\Logger::info( 'portal.get_booking_request', "Attempting to load booking code: {$code}", 'booking', null, [ 'token_provided' => $token ] );

            $bookings_repo = new \Artechia\PMS\Repositories\BookingRepository();
            $booking = $bookings_repo->find_by_code( $code );

            if ( ! $booking ) {
                \Artechia\PMS\Logger::warning( 'portal.get_booking_failed', "Booking code: {$code} not found in DB.", 'booking' );
                return new \WP_REST_Response( [ 'error' => 'NOT_FOUND' ], 404 );
            }

            // Validate access token.
            if ( ! hash_equals( (string)$booking['access_token'], (string)$token ) ) {
                \Artechia\PMS\Logger::warning( 'portal.token_mismatch', "Token mismatch for booking {$code}. Provided: {$token}, Expected: {$booking['access_token']}", 'booking', (int)$booking['id'] );
                return new \WP_REST_Response( [ 'error' => 'INVALID_TOKEN' ], 403 );
            }

            // Get Guest.
            $guest_repo = new \Artechia\PMS\Repositories\GuestRepository();
            $guest = $guest_repo->find( (int) $booking['guest_id'] );

            // Get Room & Extras for details.
            $rooms_data  = $bookings_repo->get_rooms( (int) $booking['id'] );
            $extras_data = $bookings_repo->get_extras( (int) $booking['id'] );

            // Rate Plan cancellation policy.
            $rp_repo = new \Artechia\PMS\Repositories\RatePlanRepository();
            $rp = $rp_repo->find( (int) $booking['rate_plan_id'] );
            
            // Format rooms for frontend
            $rooms = array_map( function($r) {
                return [
                   'room_type_id' => $r['room_type_id'],
                   'room_type'    => $r['room_type_name'],
                   'room_unit'    => ( $r['unit_name'] && $r['unit_name'] !== '—' ) ? $r['unit_name'] : null,
                   'subtotal'     => (float) $r['subtotal'],
                   'adults'       => (int) $r['adults'],
                   'children'     => (int) $r['children']
                ];
            }, $rooms_data );

            // Format extras
            $extras = array_map( function($e) {
                return [
                    'name'     => $e['name'],
                    'quantity' => (int) $e['quantity'],
                    'total'    => (float) $e['total_price']
                ];
            }, $extras_data );

            // Property.
            $prop_repo = new \Artechia\PMS\Repositories\PropertyRepository();
            $property = $prop_repo->find( (int) $booking['property_id'] );

            // Format for frontend.
            $pricing_snapshot = !empty($booking['pricing_snapshot']) ? json_decode($booking['pricing_snapshot'], true) : [];
            $deposit_pct = (float) ($pricing_snapshot['totals']['deposit_pct'] ?? 0);
            $deposit_due = (float) ($pricing_snapshot['totals']['deposit_due'] ?? 0);
            
            $data = [
                'id'              => (int) $booking['id'],
                'booking_code'    => $booking['booking_code'],
                'status'          => $booking['status'],
                'payment_status'  => $booking['payment_status'] ?? 'unpaid',
                'property'        => $property['name'] ?? '—',
                'check_in'        => $booking['check_in'],
                'check_out'       => $booking['check_out'],
                'nights'          => (int) $booking['nights'],
                'adults'          => (int) $booking['adults'],
                'children'        => (int) $booking['children'],
                'grand_total'     => (float) $booking['grand_total'],
                'amount_paid'     => (float) $booking['amount_paid'],
                'balance_due'     => (float) $booking['balance_due'],
                'deposit_pct'     => $deposit_pct,
                'deposit_due'     => $deposit_due,
                'subtotal'        => (float) $booking['subtotal'],
                'extras_total'    => (float) $booking['extras_total'],
                'taxes_total'     => (float) $booking['taxes_total'],
                'discount_total'  => (float) ($booking['discount_total'] ?? 0),
                'coupon_code'     => $booking['coupon_code'] ?? '',
                'coupon_type'     => $pricing_snapshot['coupon']['type'] ?? null,
                'coupon_value'    => isset($pricing_snapshot['coupon']['value']) ? (int) $pricing_snapshot['coupon']['value'] : null,
                'currency'        => $booking['currency'],
                'created_at'      => $booking['created_at'],
                'guest'           => [
                    'first_name' => $guest['first_name'] ?? '',
                    'last_name'  => $guest['last_name'] ?? '',
                    'email'      => $guest['email'] ?? '',
                    'phone'      => $guest['phone'] ?? '',
                ],
                'rooms'           => $rooms,
                'extras'          => $extras,
                'special_requests'=> $booking['special_requests'],
                'payment_method'  => $booking['payment_method'] ?? 'mercadopago',
                'bank_data'       => ( ($booking['payment_method'] ?? '') === 'bank_transfer' ) ? [
                    'bank'   => \Artechia\PMS\Services\Settings::get('bank_transfer_bank'),
                    'holder' => \Artechia\PMS\Services\Settings::get('bank_transfer_holder'),
                    'cbu'    => \Artechia\PMS\Services\Settings::get('bank_transfer_cbu'),
                    'alias'  => \Artechia\PMS\Services\Settings::get('bank_transfer_alias'),
                    'cuit'   => \Artechia\PMS\Services\Settings::get('bank_transfer_cuit'),
                ] : null,
                'cancellation_policy' => [
                    'is_refundable' => (bool) ( $rp['is_refundable'] ?? 1 ),
                    'deadline_days' => (int) ( $rp['cancellation_deadline_days'] ?? 0 ),
                    'penalty_type'  => $rp['penalty_type'] ?? 'none',
                    'policy_json'   => $rp['cancellation_policy_json'] ?? '{}',
                ],
                'whatsapp_url'    => \Artechia\PMS\Helpers\Helpers::whatsapp_url( $booking['booking_code'] ),
            ];
            
            return new \WP_REST_Response( $data, 200 );
        } );
    }

    /**
     * Export iCal feed for a specific unit.
     */
    public function export_ical( \WP_REST_Request $request ) {
        return $this->safe_response( function () use ( $request ) {
            $unit_id = (int) $request['id'];
            $token   = sanitize_text_field( $request->get_param( 'token' ) );

            // Validate token
            $repo = new \Artechia\PMS\Repositories\ICalFeedRepository();
            $feed = $repo->find_by_export_token( $token );

            // Check if feed exists and belongs to the requested unit
            if ( ! $feed || (int) $feed['room_unit_id'] !== $unit_id ) {
                return new \WP_REST_Response( [ 
                    'error'   => 'UNAUTHORIZED', 
                    'message' => 'Invalid token or unit ID.' 
                ], 403 );
            }

            // Generate iCal
            $service = new \Artechia\PMS\Services\ICalService();
            $ical    = $service->generate_feed( $unit_id );

            if ( empty( $ical ) ) {
                return new \WP_REST_Response( [ 
                    'error'   => 'NOT_FOUND', 
                    'message' => 'Feed content not available for this unit.' 
                ], 404 );
            }

            // Return as iCal file
            if ( ! headers_sent() ) {
                header( 'Content-Type: text/calendar; charset=utf-8' );
                header( 'Content-Disposition: attachment; filename="room-unit-' . $unit_id . '.ics"' );
            }
            echo $ical;
            exit;
        } );
    }

    /**
     * Get active extras for a property.
     */
    public function get_extras( \WP_REST_Request $request ) {
        return $this->safe_response( function () use ( $request ) {
            $property_id = (int) $request['id'];
            
            $repo = new \Artechia\PMS\Repositories\ExtraRepository();
            $extras = $repo->active_for_property( $property_id );

            // Filter optional only (mandatory are auto-added by PricingService)
            $optional = array_filter( $extras, function( $e ) {
                return (int) $e['is_mandatory'] === 0;
            } );

            $data = array_map( function( $e ) {
                return [
                    'id'          => (int) $e['id'],
                    'name'        => $e['name'],
                    'description' => $e['description'],
                    'price'       => (float) $e['price'],
                    'price_type'  => $e['price_type'],
                    'max_qty'     => (int) $e['max_qty'],
                ];
            }, array_values( $optional ) );

            return new \WP_REST_Response( $data, 200 );
        } );
    }

    /**
     * POST /public/booking/find
     * Securely find a booking by code + email to get access token.
     */
    public function find_booking( \WP_REST_Request $request ) {
        $code  = sanitize_text_field( $request->get_param( 'code' ) );
        $email = sanitize_email( $request->get_param( 'email' ) );

        if ( empty( $code ) || empty( $email ) ) {
            return new \WP_REST_Response( [
                'success' => false,
                'message' => 'Código y email son requeridos.'
            ], 400 );
        }

        $repo = new \Artechia\PMS\Repositories\BookingRepository();
        $booking = $repo->find_by_code( $code );

        if ( ! $booking ) {
            return new \WP_REST_Response( [
                'success' => false,
                'message' => 'No se encontró ninguna reserva con ese código.'
            ], 404 );
        }

        // Verify email (case-insensitive)
        if ( strtolower( $booking['guest_email'] ) !== strtolower( $email ) ) {
            return new \WP_REST_Response( [
                'success' => false,
                'message' => 'El código y el email no coinciden.'
            ], 403 );
        }

        return new \WP_REST_Response( [
            'success'      => true,
            'booking_code' => $booking['booking_code'],
            'access_token' => $booking['access_token']
        ], 200 );
    }

    /**
     * Proactively verify a payment ID with Mercado Pago (Fallback for missing webhooks).
     */
    public function verify_payment( \WP_REST_Request $request ) {
        return $this->safe_response( function () use ( $request ) {
            $code       = sanitize_text_field( $request['code'] );
            $token      = sanitize_text_field( $request->get_param( 'token' ) );
            $payment_id = sanitize_text_field( $request->get_param( 'payment_id' ) );

            $repo = new \Artechia\PMS\Repositories\BookingRepository();
            $booking = $repo->find_by_code( $code );

            if ( ! $booking ) {
                return new \WP_REST_Response( [ 'error' => 'NOT_FOUND' ], 404 );
            }

            if ( ! hash_equals( (string)$booking['access_token'], (string)$token ) ) {
                return new \WP_REST_Response( [ 'error' => 'INVALID_TOKEN' ], 403 );
            }

            // Fetch payment from MP
            $mp_payment = \Artechia\PMS\Services\MercadoPagoGateway::fetch_payment( $payment_id );
            if ( ! $mp_payment ) {
                return new \WP_REST_Response( [ 'error' => 'PAYMENT_NOT_FOUND' ], 404 );
            }

            // Basic validation: does it match our booking?
            $mp_ext_ref = $mp_payment['external_reference'] ?? '';
            if ( strpos( $mp_ext_ref, $code ) !== 0 ) {
                return new \WP_REST_Response( [ 'error' => 'REFERENCE_MISMATCH' ], 400 );
            }

            // If it matches and is approved, we can trigger the same logic as the webhook.
            $status = $mp_payment['status'] ?? '';
            if ( $status === 'approved' ) {
                // Record/Update the payment in our DB first!
                \Artechia\PMS\Services\MercadoPagoGateway::process_payment_update( (int) $booking['id'], $mp_payment );

                $service = new BookingService();
                $service->update_financial_status( (int) $booking['id'] );
                $result = $service->confirm_booking( (int) $booking['id'] );
                
                // Return updated booking data
                return $this->get_booking( $request );
            }

            return new \WP_REST_Response( [ 
                'status'  => $status,
                'message' => 'Payment status is: ' . $status
            ], 200 );
        } );
    }

    /**
     * Calendar hints: per-day availability status + active promotions.
     * Returns data for the next N months to color-code the public calendar.
     */
    public function get_calendar_hints( \WP_REST_Request $request ) {
        return $this->safe_response( function () use ( $request ) {
            $property_id = absint( $request->get_param( 'property_id' ) );
            $months      = min( absint( $request->get_param( 'months' ) ?: 3 ), 6 );

            if ( ! $property_id ) {
                return new \WP_REST_Response( [ 'error' => 'MISSING_PARAMS' ], 400 );
            }

            // Cache key per property (include pending_blocks_unit so toggling setting invalidates cache)
            $pbu = \Artechia\PMS\Repositories\BookingRepository::pending_blocks_unit() ? '1' : '0';
            $cache_key = 'artechia_cal_hints_' . $property_id . '_' . $months . '_pbu' . $pbu;
            $cached    = get_transient( $cache_key );
            if ( $cached !== false ) {
                return new \WP_REST_Response( $cached, 200 );
            }

            global $wpdb;

            $today    = date( 'Y-m-d' );
            $end_date = date( 'Y-m-d', strtotime( "+{$months} months" ) );

            // 1. Get total available units for this property (excluding OOS/maintenance)
            $units_table = \Artechia\PMS\DB\Schema::table( 'room_units' );
            $total_units = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$units_table}
                 WHERE property_id = %d AND status NOT IN ('out_of_service', 'maintenance')",
                $property_id
            ) );

            if ( $total_units === 0 ) {
                return new \WP_REST_Response( [ 'days' => [] ], 200 );
            }

            // 2. Get booked unit counts per day using a date iteration approach
            //    We query all active bookings that overlap our date range
            $b_table  = \Artechia\PMS\DB\Schema::table( 'bookings' );
            $br_table = \Artechia\PMS\DB\Schema::table( 'booking_rooms' );

            $pending_sql = \Artechia\PMS\Repositories\BookingRepository::pending_blocks_unit()
                ? "OR b.status = 'pending'"
                : '';

            $bookings = $wpdb->get_results( $wpdb->prepare(
                "SELECT b.check_in, b.check_out, COUNT(br.id) AS units
                 FROM {$b_table} b
                 JOIN {$br_table} br ON br.booking_id = b.id
                 WHERE b.property_id = %d
                   AND b.check_in < %s
                   AND b.check_out > %s
                   AND (b.status IN ('confirmed','checked_in','checked_out','hold')
                        {$pending_sql})
                 GROUP BY b.id",
                $property_id, $end_date, $today
            ), \ARRAY_A );

            // Build a per-day count of booked units
            $booked_per_day = [];
            foreach ( $bookings as $bk ) {
                $ci = max( $bk['check_in'], $today );
                $co = min( $bk['check_out'], $end_date );
                $d  = new \DateTime( $ci );
                $de = new \DateTime( $co );
                while ( $d < $de ) {
                    $ds = $d->format( 'Y-m-d' );
                    $booked_per_day[ $ds ] = ( $booked_per_day[ $ds ] ?? 0 ) + (int) $bk['units'];
                    $d->modify( '+1 day' );
                }
            }

            // 3. Get active promotions (percent type) for the calendar
            $promo_ranges = [];
            $promo_repo = new \Artechia\PMS\Repositories\PromotionRepository();
            $active_promos = $promo_repo->find_active_for_search( $property_id, $today, $end_date );
            foreach ( $active_promos as $p ) {
                if ( ( $p['rule_type'] ?? '' ) !== 'percent' ) continue;
                // Skip promos without explicit dates — an always-active promo shouldn't color the entire calendar
                if ( empty( $p['starts_at'] ) && empty( $p['ends_at'] ) ) continue;
                $promo_ranges[] = [
                    'starts' => $p['starts_at'] ? substr( $p['starts_at'], 0, 10 ) : $today,
                    'ends'   => $p['ends_at'] ? substr( $p['ends_at'], 0, 10 ) : $end_date,
                    'pct'    => (int) $p['rule_value'],
                    'code'   => $p['name'] ?? '',
                ];
            }

            // 4. Also check closure dates
            $closure_setting = \Artechia\PMS\Services\Settings::get( 'closure_dates', '' );
            $closed_days = [];
            if ( ! empty( $closure_setting ) ) {
                $parts = array_map( 'trim', explode( ',', $closure_setting ) );
                foreach ( $parts as $part ) {
                    if ( empty( $part ) ) continue;
                    if ( strpos( $part, ':' ) !== false ) {
                        list( $from_str, $to_str ) = explode( ':', $part );
                        $f = new \DateTime( trim( $from_str ) );
                        $t = new \DateTime( trim( $to_str ) );
                        $t->modify( '+1 day' ); // include end date
                        while ( $f < $t ) {
                            $closed_days[ $f->format( 'Y-m-d' ) ] = true;
                            $f->modify( '+1 day' );
                        }
                    } else {
                        $closed_days[ trim( $part ) ] = true;
                    }
                }
            }

            // 5. Build the hints array
            $days   = [];
            $cursor = new \DateTime( $today );
            $limit  = new \DateTime( $end_date );
            $low_threshold = max( 1, (int) ceil( $total_units * 0.3 ) );

            while ( $cursor < $limit ) {
                $ds     = $cursor->format( 'Y-m-d' );
                $booked = $booked_per_day[ $ds ] ?? 0;
                $avail  = max( 0, $total_units - $booked );

                // Determine status
                if ( isset( $closed_days[ $ds ] ) || $avail === 0 ) {
                    $status = 'full';
                } elseif ( $avail <= $low_threshold ) {
                    $status = 'low';
                } else {
                    $status = 'available';
                }

                // Check promos for this day
                $promo = null;
                foreach ( $promo_ranges as $pr ) {
                    if ( $ds >= $pr['starts'] && $ds <= $pr['ends'] ) {
                        $promo = [ 'pct' => $pr['pct'] ];
                        break; // first match wins (highest value from ORDER BY)
                    }
                }

                // Only include days that have info (full, low, or promo)
                if ( $status !== 'available' || $promo ) {
                    $entry = [ 's' => $status ];
                    if ( $promo ) {
                        $entry['p'] = $promo['pct'];
                    }
                    $days[ $ds ] = $entry;
                }

                $cursor->modify( '+1 day' );
            }

            $result = [ 'days' => $days, 'total_units' => $total_units ];

            // Cache for 5 minutes
            set_transient( $cache_key, $result, 5 * MINUTE_IN_SECONDS );

            return new \WP_REST_Response( $result, 200 );
        } );
    }

    /**
     * Decode a tracking token: base64( json({ c: campaign_id, e: email }) ).
     */
    private function decode_tracking_token( string $token ): ?array {
        $json = base64_decode( $token, true );
        if ( ! $json ) return null;
        $data = json_decode( $json, true );
        if ( ! $data || empty( $data['c'] ) || empty( $data['e'] ) ) return null;
        return $data;
    }



    /**
     * Email click tracking — logs the click and redirects to the original URL.
     */
    public function track_click( \WP_REST_Request $request ) {
        $token = sanitize_text_field( $request->get_param( 't' ) ?? '' );
        $url   = esc_url_raw( $request->get_param( 'url' ) ?? '' );
        $data  = $this->decode_tracking_token( $token );

        if ( $data && $url ) {
            global $wpdb;
            $log_table = \Artechia\PMS\DB\Schema::table( 'audit_log' );

            // Deduplicate: only log once per campaign+email+url
            $exists = $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$log_table} WHERE event_type = 'emailclick' AND message = %s LIMIT 1",
                "Click: {$data['e']} → {$url} (campaign: {$data['c']})"
            ) );

            if ( ! $exists ) {
                Logger::info( 'email.click', "Click: {$data['e']} → {$url} (campaign: {$data['c']})", 'marketing', null, [
                    'campaign_id' => $data['c'],
                    'email'       => $data['e'],
                    'url'         => $url,
                ] );
            }
        }

        // Redirect to original URL (or home if missing)
        $redirect = $url ?: home_url( '/' );
        if ( ! headers_sent() ) {
            header( 'Location: ' . $redirect, true, 302 );
        }
        exit;
    }
}
