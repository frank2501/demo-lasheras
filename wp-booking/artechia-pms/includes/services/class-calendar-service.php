<?php
/**
 * Calendar Service for Tape Chart.
 * 
 * Efficiently fetches room units and bookings for a specific date range.
 */
namespace Artechia\PMS\Services;

use Artechia\PMS\Repositories\BookingRepository;
use Artechia\PMS\Repositories\RoomUnitRepository;
use Artechia\PMS\Repositories\HousekeepingRepository;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class CalendarService {

    /**
     * Get calendar data (units + bookings) for a date range.
     * 
     * @param int    $property_id
     * @param string $start_date Y-m-d
     * @param int    $days
     * @return array
     */
    public function get_calendar_data( int $property_id, string $start_date, int $days ): array {
        $end_date = date( 'Y-m-d', strtotime( "$start_date +$days days" ) );

        // 1. Get Room Units (grouped by Room Type for UI).
        $unit_repo = new RoomUnitRepository();
        $units     = $unit_repo->find_all_by_property( $property_id );

        // Organize units for frontend: [ { id, name, housekeeping, room_type_name, ... } ]
        // We might want to sort by room type sort_order, then unit sort_order.
        // For now, let's just return flat list with metadata.
        
        // 2. Get Bookings in range (overlapping).
        // Query: check_in < end_date AND check_out > start_date
        // Single optimized query.
        $bookings = $this->get_bookings_in_range( $property_id, $start_date, $end_date );

        return [
            'start_date' => $start_date,
            'end_date'   => $end_date,
            'units'      => $units,
            'bookings'   => $bookings,
        ];
    }

    /**
     * Search bookings by code, guest name, email, or phone. (H9)
     */
    public function search_bookings( string $query, int $property_id ): array {
        global $wpdb;
        $b_table  = \Artechia\PMS\DB\Schema::table( 'bookings' );
        $g_table  = \Artechia\PMS\DB\Schema::table( 'guests' );
        $br_table = \Artechia\PMS\DB\Schema::table( 'booking_rooms' );

        $like = '%' . $wpdb->esc_like( $query ) . '%';

        $sql = $wpdb->prepare(
            "SELECT 
                b.id, b.booking_code, b.status, b.check_in, b.check_out, b.grand_total, b.currency,
                g.first_name, g.last_name, g.email, g.phone,
                br.room_unit_id
             FROM {$b_table} b
             JOIN {$g_table} g ON b.guest_id = g.id
             LEFT JOIN {$br_table} br ON b.id = br.booking_id
             WHERE b.property_id = %d
               AND (
                   b.booking_code LIKE %s 
                   OR g.first_name LIKE %s 
                   OR g.last_name LIKE %s 
                   OR g.email LIKE %s 
                   OR g.phone LIKE %s
               )
             ORDER BY b.check_in DESC
             LIMIT 20",
            $property_id, $like, $like, $like, $like, $like
        );

        return $wpdb->get_results( $sql, \ARRAY_A ) ?: [];
    }

    /**
     * Get bookings overlap with range.
     */
    private function get_bookings_in_range( int $property_id, string $start, string $end ): array {
        global $wpdb;
        $b_table  = \Artechia\PMS\DB\Schema::table( 'bookings' );
        $br_table = \Artechia\PMS\DB\Schema::table( 'booking_rooms' );
        $g_table  = \Artechia\PMS\DB\Schema::table( 'guests' );

        // Join booking_rooms to get assigned unit_id.
        $sql = $wpdb->prepare(
            "SELECT 
                b.id, b.booking_code, b.status, b.payment_status, b.check_in, b.check_out, b.grand_total, b.currency,
                g.first_name, g.last_name, g.email,
                br.room_unit_id, br.room_type_id
             FROM {$b_table} b
             JOIN {$g_table} g ON b.guest_id = g.id
             JOIN {$br_table} br ON b.id = br.booking_id
             WHERE b.property_id = %d
               AND b.check_in < %s
               AND b.check_out > %s
               AND b.status NOT IN ('cancelled', 'expired')
             ORDER BY b.check_in ASC",
            $property_id,
            $end,
            $start
        );

        return $wpdb->get_results( $sql, \ARRAY_A ) ?: [];
    }
}
