<?php
/**
 * AvailabilityService: checks room type availability for a date range.
 */
namespace Artechia\PMS\Services;

use Artechia\PMS\DB\Schema;
use Artechia\PMS\Repositories\BookingRepository;
use Artechia\PMS\Repositories\LockRepository;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class AvailabilityService {

    private RateResolver      $resolver;
    private BookingRepository $bookings;
    private LockRepository    $locks;

    public function __construct() {
        $this->resolver = new RateResolver();
        $this->bookings = new BookingRepository();
        $this->locks    = new LockRepository();
    }

    /**
     * Search available room types for a property and date range.
     *
     * @param int    $property_id
     * @param string $check_in     Y-m-d
     * @param string $check_out    Y-m-d
     * @param int    $adults
     * @param int    $children
     * @param array  $filters      Optional: room_type_id, rate_plan_id
     * @return array  List of room types with availability info.
     */
    public function search(
        int $property_id,
        string $check_in,
        string $check_out,
        int $adults = 2,
        int $children = 0,
        array $filters = []
    ): array {
        $nights = $this->count_nights( $check_in, $check_out );
        if ( $nights < 1 ) return [];

        // Check for closure dates.
        if ( $this->is_range_closed( $check_in, $check_out ) ) {
            return [];
        }

        // Get property and check active status.
        global $wpdb;
        $prop_table = Schema::table( 'properties' );
        $property = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$prop_table} WHERE id = %d AND status = 'active'", $property_id ), \ARRAY_A );
        if ( ! $property ) return [];

        // Get active room types for property.
        $room_types = $this->get_room_types( $property_id, $filters );
        if ( empty( $room_types ) ) return [];

        // Determine rate plan.
        $rate_plan_id = $this->resolve_rate_plan_id( $property_id, $filters );

        $results = [];
        foreach ( $room_types as $rt ) {
            $rt_id = (int) $rt['id'];

            // Check occupancy capacity.
            if ( $adults > (int) $rt['max_adults'] || $children > (int) $rt['max_children'] ) {
                continue;
            }
            if ( ( $adults + $children ) > (int) $rt['max_occupancy'] ) {
                continue;
            }

            $result = $this->check_room_type(
                $property_id, $rt_id, $rate_plan_id,
                $check_in, $check_out, $nights, $rt
            );

            $results[] = $result;
        }

        // Diagnostic logging if requested or global constant set.
        if ( ! empty( $filters['debug'] ) || ( defined( 'ARTECHIA_DEBUG_AVAILABILITY' ) && ARTECHIA_DEBUG_AVAILABILITY ) ) {
            error_log( 'Artechia Availability Search: ' . wp_json_encode([
                'property_id' => $property_id,
                'dates'       => "$check_in - $check_out",
                'adults'      => $adults,
                'children'    => $children,
                'filters'     => $filters,
                'results'     => count($results),
                'breakdown'   => $results
            ]));
        }

        return $results;
    }

    /**
     * Check availability for a single room type.
     */
    public function check_room_type(
        int $property_id,
        int $room_type_id,
        int $rate_plan_id,
        string $check_in,
        string $check_out,
        int $nights,
        ?array $rt_data = null
    ): array {
        // Room type data
        if ( ! $rt_data ) {
            global $wpdb;
            $rt_table = Schema::table( 'room_types' );
            $rt_data = $wpdb->get_row( $wpdb->prepare(
                "SELECT * FROM {$rt_table} WHERE id = %d", $room_type_id
            ), \ARRAY_A );
        }

        // Base inventory: count of room_units.
        $base_units = $this->count_units( $room_type_id );

        // Booked units (active bookings).
        $booked = $this->bookings->count_booked_units(
            $room_type_id, $check_in, $check_out
        );

        // Locked qty (non-expired, no booking).
        $locked = $this->locks->count_locked_qty(
            $room_type_id, $check_in, $check_out
        );

        $inventory_available = max( 0, $base_units - $booked - $locked );

        // Resolve rates for each night.
        $nightly = $this->resolver->resolve_range(
            $property_id, $room_type_id, $rate_plan_id, $check_in, $check_out
        );

        // Check restrictions and daily caps.
        $stop_sell   = false;
        $closed      = false;
        $daily_cap   = null; // null = no limit
        $has_rate    = true;
        $cta_ok      = true;
        $ctd_ok      = true;
        $min_stay_ok = true;
        $max_stay_ok = true;

        $dates  = array_keys( $nightly );
        $first  = $dates[0] ?? null;
        $last   = $dates[ count( $dates ) - 1 ] ?? null;

        $fail_reasons = [];

        // Initial restrictions from check-in night or global defaults
        $min_stay = 1;
        $max_stay = 999;

        foreach ( $nightly as $date => $rate ) {
            // Stop sell or Closed: any night blocks everything.
            if ( $rate['stop_sell'] ) {
                $stop_sell = true;
                $fail_reasons[] = 'STOP_SELL';
            }
            if ( isset( $rate['closed'] ) && $rate['closed'] ) {
                $closed = true;
                $fail_reasons[] = 'CLOSED';
            }

            // No rate defined.
            if ( ! $rate['has_rate'] ) {
                $has_rate = false;
                $fail_reasons[] = 'NO_RATE';
            }

            // Aggregate restrictions: most restrictive across the stay.
            if ( isset( $rate['min_stay'] ) ) {
                $min_stay = max( $min_stay, (int) $rate['min_stay'] );
            }
            if ( isset( $rate['max_stay_override'] ) && (int) $rate['max_stay_override'] > 0 ) {
                $max_stay = min( $max_stay, (int) $rate['max_stay_override'] );
            } elseif ( isset( $rate['max_stay'] ) ) {
                $max_stay = min( $max_stay, (int) $rate['max_stay'] );
            }

            // Daily available_units cap.
            if ( $rate['available_units'] !== null ) {
                $cap = (int) $rate['available_units'];
                $daily_cap = ( $daily_cap === null ) ? $cap : min( $daily_cap, $cap );
            }

            // CTA: check on check-in date.
            if ( $date === $first && $rate['closed_to_arrival'] ) {
                $cta_ok = false;
                $fail_reasons[] = 'CTA';
            }

            // CTD: check on last night date.
            if ( $date === $last && $rate['closed_to_departure'] ) {
                $ctd_ok = false;
                $fail_reasons[] = 'CTD';
            }
        }

        $min_stay_ok = $nights >= $min_stay;
        $max_stay_ok = $nights <= $max_stay;

        if ( ! $min_stay_ok ) $fail_reasons[] = 'MIN_STAY';
        if ( ! $max_stay_ok ) $fail_reasons[] = 'MAX_STAY';

        // Effective available.
        $effective = $inventory_available;
        if ( $daily_cap !== null ) {
            $effective = min( $effective, $daily_cap );
        }
        if ( $stop_sell || $closed ) {
            $effective = 0;
        }

        if ( $effective <= 0 && ! $stop_sell && ! $closed ) {
            $fail_reasons[] = 'NO_INVENTORY';
        }

        $fail_reasons = array_unique( $fail_reasons );

        return [
            'room_type_id'   => (int) ( $rt_data['id'] ?? $room_type_id ),
            'room_type_name' => $rt_data['name'] ?? '',
            'base_units'     => $base_units,
            'booked'         => $booked,
            'locked'         => $locked,
            'available'      => max( 0, $effective ),
            'stop_sell'      => $stop_sell,
            'closed'         => $closed,
            'has_rate'       => $has_rate,
            'min_stay_ok'    => $min_stay_ok,
            'max_stay_ok'    => $max_stay_ok,
            'cta_ok'         => $cta_ok,
            'ctd_ok'         => $ctd_ok,
            'fail_reasons'   => $fail_reasons,
            'bookable'       => $effective > 0 && $has_rate && $min_stay_ok && $max_stay_ok && $cta_ok && $ctd_ok && ! $stop_sell && ! $closed,
            // Added fields for frontend rendering
            'max_occupancy'  => (int) ( $rt_data['max_occupancy'] ?? 2 ),
            'base_occupancy' => (int) ( $rt_data['base_occupancy'] ?? 2 ),
            'max_adults'     => (int) ( $rt_data['max_adults'] ?? 2 ),
            'max_children'   => (int) ( $rt_data['max_children'] ?? 0 ),
            'bed_config'     => $rt_data['bed_config'] ?? '',
            'description'    => $rt_data['description'] ?? '',
            'photos_json'    => $rt_data['photos_json'] ?? '[]',
            'amenities_json' => $rt_data['amenities_json'] ?? '[]',
        ];
    }

    /* ── Helpers ─────────────────────────────────────── */

    private function count_nights( string $check_in, string $check_out ): int {
        $d1 = strtotime( $check_in );
        $d2 = strtotime( $check_out );
        return ( $d1 && $d2 && $d2 > $d1 ) ? (int) ( ( $d2 - $d1 ) / 86400 ) : 0;
    }

    private function count_units( int $room_type_id ): int {
        global $wpdb;
        $table = Schema::table( 'room_units' );
        // Base inventory pool: all units except those specifically set to OOS or Maintenance.
        // 'available' and 'occupied' both count as base inventory because 'occupied' is 
        // subtraction-based (we subtract current bookings from the total pool).
        return (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table}
             WHERE room_type_id = %d AND status NOT IN ('out_of_service', 'maintenance')",
            $room_type_id
        ) );
    }

    private function get_room_types( int $property_id, array $filters ): array {
        global $wpdb;
        $table = Schema::table( 'room_types' );
        $ru    = Schema::table( 'room_units' );

        $sql = "SELECT rt.* FROM {$table} rt
                 WHERE rt.property_id = %d
                   AND rt.status = 'active'
                   AND EXISTS (
                       SELECT 1 FROM {$ru} u
                       WHERE u.room_type_id = rt.id
                         AND u.property_id  = rt.property_id
                         AND u.status       = 'available'
                   )";
        $values = [ $property_id ];

        if ( ! empty( $filters['room_type_id'] ) ) {
            $sql .= ' AND rt.id = %d';
            $values[] = (int) $filters['room_type_id'];
        }

        $sql .= ' ORDER BY rt.sort_order ASC';

        return $wpdb->get_results( $wpdb->prepare( $sql, ...$values ), \ARRAY_A ) ?: [];
    }

    private function resolve_rate_plan_id( int $property_id, array $filters ): int {
        if ( ! empty( $filters['rate_plan_id'] ) ) {
            return (int) $filters['rate_plan_id'];
        }
        // Get default rate plan.
        $rp_repo = new \Artechia\PMS\Repositories\RatePlanRepository();
        $default = $rp_repo->get_default( $property_id );
        return $default ? (int) $default['id'] : 0;
    }

    /**
     * Check if a date range overlaps with configured closure dates.
     */
    private function is_range_closed( string $check_in, string $check_out ): bool {
        $closure_setting = Settings::get( 'closure_dates', '' );
        if ( empty( $closure_setting ) ) {
            return false;
        }

        $closure_parts = array_map( 'trim', explode( ',', $closure_setting ) );
        $check_in_ts  = strtotime( $check_in );
        $check_out_ts = strtotime( $check_out );

        foreach ( $closure_parts as $part ) {
            if ( empty( $part ) ) {
                continue;
            }

            // Handle range YYYY-MM-DD:YYYY-MM-DD
            if ( strpos( $part, ':' ) !== false ) {
                list( $start, $end ) = array_map( 'trim', explode( ':', $part ) );
                $start_ts = strtotime( $start );
                $end_ts   = strtotime( $end );

                if ( $start_ts && $end_ts ) {
                    // Overlap check: (StartA <= EndB) and (EndA >= StartB)
                    // Note: check_out is the departure day, so usually the last night is check_out - 1 day.
                    // But if ANY day of the stay is closed, we block.
                    // A closure of 2024-05-10:2024-05-12 means 10, 11, and 12 are closed.
                    if ( $check_in_ts <= $end_ts && $check_out_ts > $start_ts ) {
                        return true;
                    }
                }
            } else {
                // Handle single date YYYY-MM-DD
                $date_ts = strtotime( $part );
                if ( $date_ts ) {
                    if ( $check_in_ts <= $date_ts && $check_out_ts > $date_ts ) {
                        return true;
                    }
                }
            }
        }

        return false;
    }
}
