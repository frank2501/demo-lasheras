<?php
/**
 * RateResolver: resolves the effective rate for a single night
 * by merging daily_rates overrides with base rates per rate plan.
 */
namespace Artechia\PMS\Services;

use Artechia\PMS\DB\Schema;
use Artechia\PMS\Repositories\RatePlanRepository;
use Artechia\PMS\Repositories\RateRepository;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class RateResolver {

    private RatePlanRepository $rate_plans;
    private RateRepository     $rates;

    /** Fields that can be overridden by daily_rates (NULL = inherit). */
    private const MERGE_FIELDS = [
        'price_per_night', 'extra_adult', 'extra_child',
        'min_stay', 'max_stay_override', 'closed_to_arrival', 'closed_to_departure',
        'closed',
    ];

    public function __construct() {
        $this->rate_plans = new RatePlanRepository();
        $this->rates      = new RateRepository();
    }

    /**
     * Resolve the effective rate for a given night.
     */
    public function resolve(
        int $property_id,
        int $room_type_id,
        ?int $rate_plan_id, // can be passed or we resolve it by date if null
        string $date
    ): array {
        // 1. Determine active Rate Plan.
        // If a specific rate plan was requested, use it. Otherwise find the active one for this date.
        $plan = null;
        if ( $rate_plan_id ) {
            $plan = $this->rate_plans->find( $rate_plan_id );
        }
        
        if ( ! $plan ) {
            $plan = $this->rate_plans->find_for_date( $property_id, $date );
        }

        $resolved_plan_id = $plan ? (int) $plan['id'] : 0;

        // 2. Get daily override (if exists).
        $daily = $this->get_daily( $room_type_id, $resolved_plan_id, $date );

        // 3. Get base rate.
        $base = $this->rates->find_rate( $room_type_id, $resolved_plan_id );

        $has_rate = ( $base !== null );

        // 4. Build defaults from rate plan constraints.
        // Note: per-rate min_stay in `rates` table is ignored because the admin UI
        // only exposes the plan-level min_stay. Using the stale per-rate value would
        // override what the user actually configured.
        $plan_min_stay = $plan ? (int) ( $plan['min_stay'] ?? 1 ) : 1;
        $plan_max_stay = $plan ? (int) ( $plan['max_stay'] ?? 30 ) : 30;

        $result = [
            'price_per_night'     => $base ? (float) $base['price_per_night'] : null,
            'extra_adult'         => $base ? (float) $base['extra_adult'] : 0.0,
            'extra_child'         => $base ? (float) $base['extra_child'] : 0.0,
            'single_use_discount' => $base ? (float) $base['single_use_discount'] : 0.0,
            'min_stay'            => $plan_min_stay,
            'max_stay'            => $plan_max_stay,
            'closed_to_arrival'   => $base ? (bool) (int) $base['closed_to_arrival'] : false,
            'closed_to_departure' => $base ? (bool) (int) $base['closed_to_departure'] : false,
            'closed'              => false,
            'stop_sell'           => false,
            'available_units'     => null,
            'has_rate'            => $has_rate,
            'rate_plan_id'        => $resolved_plan_id,
            'rate_plan_name'      => $plan['name'] ?? '',
            'deposit_pct'         => $plan ? (float) ($plan['deposit_pct'] ?? 0) : 0,
            'cancellation_type'   => $plan['cancellation_type'] ?? 'flexible',
        ];

        // 5. Merge daily overrides (non-NULL fields win).
        $source = 'base';

        if ( $daily ) {
            $is_daily_override = false;
            foreach ( self::MERGE_FIELDS as $field ) {
                if ( $daily[ $field ] !== null && $daily[ $field ] !== '' ) {
                    $is_daily_override = true;
                    if ( in_array( $field, [ 'closed_to_arrival', 'closed_to_departure', 'closed' ], true ) ) {
                        $result[ $field ] = (bool) (int) $daily[ $field ];
                    } elseif ( $field === 'max_stay_override' ) {
                        $result['max_stay'] = (int) $daily[ $field ];
                    } elseif ( in_array( $field, [ 'min_stay' ], true ) ) {
                        $result[ $field ] = (int) $daily[ $field ];
                    } else {
                        $result[ $field ] = (float) $daily[ $field ];
                    }
                }
            }
            $result['stop_sell']       = (bool) (int) ( $daily['stop_sell'] ?? 0 );
            $result['available_units'] = ( $daily['available_units'] !== null && $daily['available_units'] !== '' )
                ? (int) $daily['available_units']
                : null;
            
            if ( $is_daily_override || $result['stop_sell'] || $result['available_units'] !== null ) {
                $source = 'daily';
            }
        }

        $result['source'] = $source;

        return $result;
    }

    /**
     * Resolve rates for every night in a stay.
     */
    public function resolve_range(
        int $property_id,
        int $room_type_id,
        ?int $rate_plan_id,
        string $check_in,
        string $check_out
    ): array {
        $results = [];
        $current = $check_in;
        while ( $current < $check_out ) {
            $results[ $current ] = $this->resolve( $property_id, $room_type_id, $rate_plan_id, $current );
            $current = date( 'Y-m-d', strtotime( $current . ' +1 day' ) );
        }
        return $results;
    }

    /**
     * Get daily rate row (if exists).
     */
    private function get_daily( int $room_type_id, int $rate_plan_id, string $date ): ?array {
        global $wpdb;
        $table = Schema::table( 'daily_rates' );
        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$table}
             WHERE room_type_id = %d AND rate_plan_id = %d AND rate_date = %s",
            $room_type_id, $rate_plan_id, $date
        ), \ARRAY_A );
        return $row ?: null;
    }
}
