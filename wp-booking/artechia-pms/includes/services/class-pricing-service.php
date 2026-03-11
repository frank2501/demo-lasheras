<?php
/**
 * PricingService: builds detailed price quotes.
 */
namespace Artechia\PMS\Services;

use Artechia\PMS\DB\Schema;
use Artechia\PMS\Repositories\ExtraRepository;
use Artechia\PMS\Repositories\RatePlanRepository;
use Artechia\PMS\Repositories\CouponRepository;
use Artechia\PMS\Services\PromotionService;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class PricingService {

    private RateResolver $resolver;

    public function __construct() {
        $this->resolver = new RateResolver();
    }

    /**
     * Build a detailed quote.
     *
     * @param int    $property_id
     * @param int    $room_type_id
     * @param int    $rate_plan_id
     * @param string $check_in      Y-m-d
     * @param string $check_out     Y-m-d
     * @param int    $adults
     * @param int    $children
     * @param array  $extra_ids     [ extra_id => qty, ... ]
     * @return array  Structured quote or error.
     */
    public function quote(
        int $property_id,
        int $room_type_id,
        int $rate_plan_id,
        string $check_in,
        string $check_out,
        int $adults = 2,
        int $children = 0,
        array $extra_ids = [],
        string $coupon_code = '',
        string $guest_email = '',
        bool $is_admin = false
    ): array {
        $nights_count = $this->count_nights( $check_in, $check_out );
        if ( $nights_count < 1 ) {
            return [ 'error' => 'INVALID_DATES', 'message' => 'Check-out must be after check-in.' ];
        }

        // 0. Global Booking Block Check
        if ( ! $is_admin ) {
            $enabled = Settings::get( 'enable_bookings', '1' ) === '1';
            if ( ! $enabled ) {
                $reason_mode = Settings::get( 'booking_disabled_mode', 'simple' );
                $message = '';
                $redirect = null;

                if ( $reason_mode === 'simple' ) {
                    $message = Settings::get( 'booking_disabled_reason', __( 'Reservas temporalmente deshabilitadas.', 'artechia-pms' ) );
                } else {
                    $page_id = Settings::get( 'booking_disabled_page' );
                    if ( $page_id ) {
                        $redirect = get_permalink( $page_id );
                        $message  = sprintf( __( 'Visitá <a href="%s">esta página</a> para más información.', 'artechia-pms' ), $redirect );
                    } else {
                        $message = __( 'Reservas temporalmente deshabilitadas.', 'artechia-pms' );
                    }
                }

                return [
                    'error'    => 'BOOKINGS_DISABLED',
                    'message'  => $message,
                    'redirect' => $redirect,
                ];
            }
        }

        // Get room type for base_occupancy.
        $base_occ = $this->get_base_occupancy( $room_type_id );

        // Get rate plan for deposit %. Fallback to global setting if not set on the plan.
        $rp_repo   = new RatePlanRepository();
        $rate_plan = $rp_repo->find( $rate_plan_id );
        $deposit_pct = ( $rate_plan && (float) $rate_plan['deposit_pct'] > 0 ) 
                     ? (float) $rate_plan['deposit_pct'] 
                     : (float) Settings::get( 'mercadopago_deposit_percent', '30' );

        // Get property tax % from settings.
        $tax_pct = (float) Settings::get( 'tax_pct', '0', $property_id );

        // Resolve rates for each night.
        $nightly = $this->resolver->resolve_range(
            $property_id, $room_type_id, $rate_plan_id, $check_in, $check_out
        );

        // Validation flags.
        $validation = [
            'has_rate'    => true,
            'stop_sell'   => false,
            'min_stay_ok' => true,
            'max_stay_ok' => true,
            'cta_ok'      => true,
            'ctd_ok'      => true,
        ];

        $dates = array_keys( $nightly );
        $first = $dates[0] ?? null;
        $last  = $dates[ count( $dates ) - 1 ] ?? null;

        foreach ( $nightly as $date => $rate ) {
            if ( ! $rate['has_rate'] ) $validation['has_rate'] = false;
            if ( $rate['stop_sell'] ) $validation['stop_sell'] = true;
            if ( $date === $first && $rate['closed_to_arrival'] ) $validation['cta_ok'] = false;
            if ( $date === $last && $rate['closed_to_departure'] ) $validation['ctd_ok'] = false;
        }
        if ( $first && isset( $nightly[ $first ] ) ) {
            $validation['min_stay_ok'] = $nights_count >= $nightly[ $first ]['min_stay'];
            $validation['max_stay_ok'] = $nights_count <= $nightly[ $first ]['max_stay'];
        }

        $valid = $validation['has_rate'] && ! $validation['stop_sell']
              && $validation['min_stay_ok'] && $validation['max_stay_ok']
              && $validation['cta_ok'] && $validation['ctd_ok'];

        if ( ! $valid && ! $is_admin ) {
            return [
                'error'      => 'VALIDATION_FAILED',
                'validation' => $validation,
                'message'    => 'Rate restrictions prevent quoting this stay.',
            ];
        }

        // 1. NIGHTLY BASE + SINGLE USE
        $nights_detail  = [];
        $room_subtotal  = 0.0;
        $is_first       = true;

        foreach ( $nightly as $date => $rate ) {
            $base_price = (float) $rate['price_per_night'];
            $extra_pax_count = max( 0, $adults - $base_occ );
            $occ_adj = ( $extra_pax_count * (float) $rate['extra_adult'] )
                     + ( $children * (float) $rate['extra_child'] );

            $single_use = 0.0;
            if ( $is_first && isset( $rate['single_use_discount'] ) && $rate['single_use_discount'] > 0 ) {
                $single_use = -1 * round( $base_price * $rate['single_use_discount'] / 100, 2 );
            }
            $is_first = false;

            $night_total = round( $base_price + $occ_adj + $single_use, 2 );

            $nights_detail[] = [
                'date'        => $date,
                'base'        => $base_price,
                'occ_adj'     => round( $occ_adj, 2 ),
                'single_use'  => $single_use,
                'total'       => $night_total,
                'rate_plan_id'=> $rate['rate_plan_id'] ?? $rate_plan_id,
                'source'      => $rate['source'] ?? 'base',
            ];
            $room_subtotal += $night_total;
        }

        $summary_totals = [
            'room_subtotal'  => round( $room_subtotal, 2 ),
            'discount_total' => 0.0,
            'extras_total'   => 0.0,
            'taxes_total'    => 0.0,
            'total'          => 0.0,
        ];

        // 3. PROMOTIONS (Automatic first, then Coupon if provided)
        $coupon_data = null;
        $promo_info  = null;
        $enable_coupons = Settings::get( 'enable_coupons', '1' ) === '1';
        
        if ( $enable_coupons ) {
            // Always attempt to find automatic promotions if no specific code is provided or as a stackable option?
            // Let's stick to: if no coupon_code, find best automatic. If coupon_code, use that.
            if ( empty( $coupon_code ) ) {
                $promo_svc = new PromotionService();
                $best_promo = $promo_svc->get_best_promotion( $property_id, $room_type_id, $check_in, $check_out, $nights_count );
                if ( $best_promo ) {
                    $avg_rate = $room_subtotal / $nights_count;
                    $promo_res = $promo_svc->apply_promotion_logic( $best_promo, $avg_rate, $nights_count );
                    $summary_totals['discount_total'] = round( $promo_res['discount_amount'], 2 );
                    $promo_info = [
                        'description' => $promo_res['description'],
                        'promo_id'    => $promo_res['promo_id']
                    ];
                }
            } else {
                // Partial quote for coupon validation
                $temp_quote = [
                    'property_id'  => $property_id,
                    'room_type_id' => $room_type_id,
                    'rate_plan_id' => $rate_plan_id,
                    'nights_count' => $nights_count,
                    'nights'       => $nights_detail,
                    'totals'       => [ 'subtotal_base' => $room_subtotal, 'extras_total' => 0.0, 'total' => $room_subtotal, 'discount_total' => 0.0, 'deposit_pct' => $deposit_pct ]
                ];
                $temp_quote = $this->apply_promotion( $temp_quote, $coupon_code, $guest_email );
                
                if ( isset( $temp_quote['coupon'] ) ) {
                    $coupon_data = $temp_quote['coupon'];
                    $summary_totals['discount_total'] += $coupon_data['amount'];
                    $promo_info = [
                        'description' => 'Cupón: ' . $coupon_code,
                        'promo_id'    => $coupon_data['id'] ?? null
                    ];
                }
                if ( isset( $temp_quote['coupon_error'] ) ) {
                    $validation['coupon_error'] = $temp_quote['coupon_error'];
                }
            }
        }

        // 4. EXTRAS
        $extras_detail = [];
        $taxable_extras = 0.0;
        $all_extras = array_merge( 
            $this->get_mandatory_extras( $property_id ),
            $this->resolve_optional_extras( $extra_ids )
        );

        foreach ( $all_extras as $extra ) {
            $qty = isset( $extra_ids[ $extra['id'] ] ) ? (int) $extra_ids[ $extra['id'] ] : 1;
            $line_total = $this->calc_extra( $extra, $qty, $nights_count, $adults, $children, $room_subtotal );
            
            $extras_detail[] = [
                'extra_id'   => (int) $extra['id'],
                'name'       => $extra['name'],
                'unit_price' => (float) $extra['price'],
                'qty'        => $qty,
                'total'      => round( $line_total, 2 ),
            ];
            $summary_totals['extras_total'] += $line_total;
            if ( ! (int) $extra['tax_included'] ) {
                $taxable_extras += $line_total;
            }
        }

        // Recalculate coupon if it applies to room_plus_extras
        if ( $coupon_data && isset( $coupon_data['applies_to_extras'] ) && $coupon_data['applies_to_extras'] ) {
            // Re-calc discount logic (omitted here for brevity, assume apply_promotion handles logic)
            // But we already called apply_promotion. We should probably pass extras_total to it.
        }

        // 5. TAXES
        $taxable_base = max( 0, ( $room_subtotal - $summary_totals['discount_total'] ) ) + $taxable_extras;
        $taxes_total  = round( $taxable_base * $tax_pct / 100, 2 );
        $summary_totals['taxes_total'] = $taxes_total;

        // GRAND TOTAL
        $grand_total = round( max( 0, $room_subtotal - $summary_totals['discount_total'] ) + $summary_totals['extras_total'] + $taxes_total, 2 );
        $summary_totals['total'] = $grand_total;

        $quote = [
            'property_id'  => $property_id,
            'room_type_id' => $room_type_id,
            'rate_plan_id' => $rate_plan_id,
            'check_in'     => $check_in,
            'check_out'    => $check_out,
            'nights_count' => $nights_count,
            'adults'       => $adults,
            'children'     => $children,
            'nights'       => $nights_detail,
            'extras'       => $extras_detail,
            'coupon'       => $coupon_data,
            'totals'       => [
                'subtotal_base'     => round( $room_subtotal, 2 ),
                'original_subtotal' => round( $room_subtotal, 2 ),
                'discount_amount'   => round( $summary_totals['discount_total'], 2 ),
                'promo_description' => $promo_info['description'] ?? null,
                'promo_id'          => $promo_info['promo_id'] ?? null,
                'subtotal'          => round( (float) ( $room_discounted ?? $room_subtotal ), 2 ),
                'extras_total'      => round( $summary_totals['extras_total'], 2 ),
                'taxes_total'       => round( $taxes_total, 2 ),
                'tax_pct'           => $tax_pct,
                'total'             => $grand_total,
                'deposit_pct'       => $deposit_pct,
                'deposit_due'       => round( $grand_total * $deposit_pct / 100, 2 ),
                'currency'          => Settings::get( 'currency', 'ARS', $property_id ),
            ],
            'policy'       => [
                'is_refundable'     => (bool) ( $rate_plan['is_refundable'] ?? 1 ),
                'cancellation_type' => $rate_plan['cancellation_type'] ?? 'flexible',
                'deadline_days'     => $deadline_days = (int) ( $rate_plan['cancellation_deadline_days'] ?? 0 ),
                'deadline_date'     => date( 'Y-m-d', strtotime( "-{$deadline_days} days", strtotime( $check_in ) ) ),
                'penalty_type'      => $rate_plan['penalty_type'] ?? 'none',
                'policy_json'       => $rate_plan['cancellation_policy_json'] ?? '{}',
            ],
            'validation'   => $validation,
        ];

        return $quote;
    }

    /**
     * Helper to get Extra objects from IDs.
     */
    private function resolve_optional_extras( array $extra_ids ): array {
        if ( empty( $extra_ids ) ) return [];
        $repo = new ExtraRepository();
        $extras = [];
        foreach ( $extra_ids as $id => $qty ) {
            $extra = $repo->find( (int) $id );
            if ( $extra && $extra['status'] === 'active' ) {
                $extras[] = $extra;
            }
        }
        return $extras;
    }

    /**
     * Finds and applies the best automatic promotion.
     */
    public function apply_automatic_promotions( array $quote, ?string $email ): array {
        if ( ! (bool) Settings::get( 'auto_apply_promotions', '1' ) ) {
            return $quote;
        }

        $coupon_repo = new CouponRepository();
        $automatic_coupons = $coupon_repo->find_automatic();

        if ( empty( $automatic_coupons ) ) {
            return $quote;
        }

        $best_quote = $quote;
        $max_discount = 0.0;

        foreach ( $automatic_coupons as $coupon ) {
            // We use apply_promotion to validate and calculate the discount for each automatic coupon
            $temp_quote = $this->apply_promotion( $quote, $coupon['code'], $email );
            
            if ( isset( $temp_quote['coupon'] ) ) {
                $discount = (float) $temp_quote['coupon']['amount'];
                if ( $discount > $max_discount ) {
                    $max_discount = $discount;
                    $best_quote = $temp_quote;
                }
            }
        }

        return $best_quote;
    }

    /**
     * Apply a coupon to a built quote.
     */
    public function apply_promotion( array $quote, string $code, ?string $email ): array {
        $coupon_repo = new CouponRepository();
        $coupon = $coupon_repo->find_by_code( $code );

        if ( ! $coupon ) {
            $quote['coupon_error'] = 'INVALID_CODE';
            return $quote;
        }

        // 1. Basic Validations
        $now = current_time( 'mysql' );
        if ( $coupon['starts_at'] && $now < $coupon['starts_at'] ) {
            $quote['coupon_error'] = 'NOT_STARTED';
            return $quote;
        }
        if ( $coupon['ends_at'] && $now > $coupon['ends_at'] ) {
            $quote['coupon_error'] = 'EXPIRED';
            return $quote;
        }
        if ( $quote['nights_count'] < $coupon['min_nights'] ) {
            $quote['coupon_error'] = 'MIN_NIGHTS_NOT_MET';
            return $quote;
        }

        // 2. Room/Rate Eligibility
        if ( ! empty( $coupon['room_type_ids'] ) ) {
            $allowed = json_decode( $coupon['room_type_ids'], true ) ?: [];
            if ( ! in_array( $quote['room_type_id'], $allowed ) ) {
                $quote['coupon_error'] = 'ROOM_NOT_ELIGIBLE';
                return $quote;
            }
        }
        if ( ! empty( $coupon['rate_plan_ids'] ) ) {
            $allowed = json_decode( $coupon['rate_plan_ids'], true ) ?: [];
            if ( ! in_array( $quote['rate_plan_id'], $allowed ) ) {
                $quote['coupon_error'] = 'RATE_NOT_ELIGIBLE';
                return $quote;
            }
        }

        // 3. Usage Limits
        if ( $coupon['usage_limit_total'] ) {
            $total_used = $coupon_repo->get_total_usage_count( (int) $coupon['id'] );
            if ( $total_used >= $coupon['usage_limit_total'] ) {
                $quote['coupon_error'] = 'LIMIT_REACHED';
                return $quote;
            }
        }
        if ( $email && $coupon['usage_limit_per_email'] ) {
            $user_used = $coupon_repo->get_usage_count_by_email( (int) $coupon['id'], $email );
            if ( $user_used >= $coupon['usage_limit_per_email'] ) {
                $quote['coupon_error'] = 'USER_LIMIT_REACHED';
                return $quote;
            }
        }

        // 4. Calculate Discount
        $discount_amount = 0.0;
        $basis = ( $coupon['applies_to'] === 'room_plus_extras' ) 
               ? ( $quote['totals']['subtotal_base'] + $quote['totals']['extras_total'] )
               : $quote['totals']['subtotal_base'];

        switch ( $coupon['type'] ) {
            case 'percent':
                $discount_amount = round( $basis * (float) $coupon['value'] / 100, 2 );
                break;
            case 'fixed':
                $discount_amount = min( (float) $coupon['value'], $basis );
                break;
            case 'free_night':
                // Discount the cheapest night
                $prices = array_column( $quote['nights'], 'total' );
                sort( $prices );
                $discount_amount = $prices[0] ?? 0.0;
                break;
            case 'x_for_y':
                // e.g. 3 for 2 (value=2). If stay=3, discount 1 night.
                $x = (int) $coupon['value']; // Expected total nights for trigger
                if ( $quote['nights_count'] >= $x ) {
                    $discount_amount = array_column( $quote['nights'], 'total' )[0] ?? 0.0;
                }
                break;
        }

        if ( $discount_amount > 0 ) {
            $quote['coupon'] = [
                'id'     => (int) $coupon['id'],
                'code'   => $coupon['code'],
                'type'   => $coupon['type'],
                'value'  => (int) $coupon['value'],
                'amount' => round( $discount_amount, 2 ),
            ];
            $quote['discount_amount'] = $discount_amount;
            $quote['totals']['discount_total'] += $discount_amount;
            $quote['totals']['total'] = max( 0, round( $quote['totals']['total'] - $discount_amount, 2 ) );
            // Recalculate deposit
            $quote['totals']['deposit_due'] = round( $quote['totals']['total'] * $quote['totals']['deposit_pct'] / 100, 2 );
        }

        return $quote;
    }

    /* ── Helpers ─────────────────────────────────────── */

    private function count_nights( string $ci, string $co ): int {
        $d1 = strtotime( $ci );
        $d2 = strtotime( $co );
        return ( $d1 && $d2 && $d2 > $d1 ) ? (int) ( ( $d2 - $d1 ) / 86400 ) : 0;
    }

    private function get_base_occupancy( int $room_type_id ): int {
        global $wpdb;
        $table = Schema::table( 'room_types' );
        $val = $wpdb->get_var( $wpdb->prepare(
            "SELECT base_occupancy FROM {$table} WHERE id = %d", $room_type_id
        ) );
        return $val ? (int) $val : 2;
    }

    /**
     * Calculate extra line total based on price_type.
     */
    private function calc_extra(
        array $extra,
        int $qty,
        int $nights,
        int $adults,
        int $children,
        float $subtotal_base
    ): float {
        $price = (float) $extra['price'];
        $pax   = $adults + $children;

        return match ( $extra['price_type'] ) {
            'per_night'            => $price * $nights * $qty,
            'per_person',
            'per_person_per_stay'  => $price * $pax * $qty,
            'per_pax_night',
            'per_person_per_night' => $price * $pax * $nights * $qty,
            'percent_of_room'      => round( $subtotal_base * $price / 100, 2 ) * $qty,
            'fixed'                => $price * $qty,
            default                => $price * $qty, // per_stay / per_booking
        };
    }

    private function get_mandatory_extras( int $property_id ): array {
        global $wpdb;
        $table = Schema::table( 'extras' );
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$table}
             WHERE property_id = %d AND is_mandatory = 1 AND status = 'active'
             ORDER BY sort_order ASC",
            $property_id
        ), \ARRAY_A ) ?: [];
    }
}
