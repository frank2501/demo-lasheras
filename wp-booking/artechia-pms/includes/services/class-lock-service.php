<?php
/**
 * LockService: manages availability locks for anti-double-booking.
 */
namespace Artechia\PMS\Services;

use Artechia\PMS\Repositories\LockRepository;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class LockService {

    private LockRepository      $locks;
    private AvailabilityService $availability;

    public function __construct() {
        $this->locks        = new LockRepository();
        $this->availability = new AvailabilityService();
    }

    /**
     * Acquire a lock for a room type.
     *
     * @param int    $property_id
     * @param int    $room_type_id
     * @param int    $rate_plan_id
     * @param string $check_in
     * @param string $check_out
     * @param int    $qty          Number of units to lock (default 1).
     * @param array  $meta         Optional metadata.
     * @return array{ lock_key: string, expires_at: string } | array{ error: string, message: string }
     */
    public function acquire(
        int $property_id,
        int $room_type_id,
        int $rate_plan_id,
        string $check_in,
        string $check_out,
        int $qty = 1,
        array $meta = [],
        bool $is_admin = false
    ): array {
        $nights = $this->count_nights( $check_in, $check_out );
        if ( $nights < 1 ) {
            return [ 'error' => 'INVALID_DATES', 'message' => 'Invalid date range.' ];
        }

        // Advisory lock to prevent TOCTOU race condition.
        // Scoped per room_type + date range so only competing requests serialize.
        global $wpdb;
        $lock_name = 'artechia_lock_' . $room_type_id . '_' . $check_in . '_' . $check_out;
        $lock_name = substr( $lock_name, 0, 64 ); // MySQL limit
        $got_lock  = $wpdb->get_var( $wpdb->prepare(
            "SELECT GET_LOCK(%s, 5)", $lock_name
        ) );

        if ( ! $got_lock ) {
            return [ 'error' => 'LOCK_TIMEOUT', 'message' => 'Server busy, try again.' ];
        }

        try {
            // Check availability under advisory lock.
            $avail = $this->availability->check_room_type(
                $property_id, $room_type_id, $rate_plan_id,
                $check_in, $check_out, $nights
            );

            if ( $avail['available'] < $qty ) {
                return [
                    'error'     => 'NO_AVAILABILITY',
                    'message'   => 'Not enough availability.',
                    'available' => $avail['available'],
                    'requested' => $qty,
                ];
            }

            if ( ! $avail['bookable'] && ! $is_admin ) {
                return [
                    'error'   => 'NOT_BOOKABLE',
                    'message' => 'Restrictions prevent booking.',
                    'flags'   => [
                        'stop_sell'   => $avail['stop_sell'],
                        'has_rate'    => $avail['has_rate'],
                        'min_stay_ok' => $avail['min_stay_ok'],
                        'max_stay_ok' => $avail['max_stay_ok'],
                        'cta_ok'      => $avail['cta_ok'],
                        'ctd_ok'      => $avail['ctd_ok'],
                    ],
                ];
            }

            // TTL from settings.
            $ttl_minutes = (int) Settings::get( 'mercadopago_timeout_minutes', '15' );
            if ( $ttl_minutes < 1 ) $ttl_minutes = 15;

            $expires_at = gmdate( 'Y-m-d H:i:s', time() + $ttl_minutes * 60 );

            $lock_key = $this->locks->create_lock( [
                'lock_key'     => wp_generate_password( 32, false ),
                'property_id'  => $property_id,
                'room_type_id' => $room_type_id,
                'rate_plan_id' => $rate_plan_id,
                'check_in'     => $check_in,
                'check_out'    => $check_out,
                'qty'          => $qty,
                'expires_at'   => $expires_at,
                'meta_json'    => ! empty( $meta ) ? wp_json_encode( $meta ) : null,
                'created_at'   => current_time( 'mysql', true ),
            ] );

            if ( ! $lock_key ) {
                return [ 'error' => 'LOCK_FAILED', 'message' => 'Could not create lock.' ];
            }

            return [
                'lock_key'    => $lock_key,
                'expires_at'  => $expires_at . 'Z', // Append Z to enforce UTC parsing in frontend
                'ttl_minutes' => $ttl_minutes,
            ];
        } finally {
            $wpdb->query( $wpdb->prepare( "SELECT RELEASE_LOCK(%s)", $lock_name ) );
        }
    }

    /**
     * Release a lock.
     */
    public function release( string $lock_key ): bool {
        return $this->locks->delete_by_key( $lock_key );
    }

    /**
     * Tie a lock to a booking.
     */
    public function update_booking_id( string $lock_key, int $booking_id ): bool {
        return $this->locks->update_booking_id( $lock_key, $booking_id );
    }

    /**
     * Purge expired locks.
     */
    public function purge(): int {
        return $this->locks->purge_expired();
    }

    /**
     * Get lock info.
     */
    public function info( string $lock_key ): ?array {
        $lock = $this->locks->find_by_key( $lock_key );
        if ( ! $lock ) return null;

        // Check expiry (UTC comparison).
        $now = gmdate( 'Y-m-d H:i:s' );
        if ( $lock['expires_at'] < $now ) {
            return null; // expired
        }

        return $lock;
    }

    private function count_nights( string $ci, string $co ): int {
        $d1 = strtotime( $ci );
        $d2 = strtotime( $co );
        return ( $d1 && $d2 && $d2 > $d1 ) ? (int) ( ( $d2 - $d1 ) / 86400 ) : 0;
    }
}
