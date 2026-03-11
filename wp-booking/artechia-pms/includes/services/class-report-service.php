<?php
/**
 * Report Service
 *
 * Handles aggregated data retrieval for Dashboard and Reports.
 */

namespace Artechia\PMS\Services;

use Artechia\PMS\DB\Schema;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ReportService {

    /**
     * Get high-level stats for the Dashboard cards.
     *
     * @return array
     */
    public function get_dashboard_stats(): array {
        global $wpdb;
        $today = current_time( 'Y-m-d' );
        $month_start = current_time( 'Y-m-01' );
        $month_end   = current_time( 'Y-m-t' );

        // Tables
        $t_bookings = Schema::table( 'bookings' );
        $t_rooms    = Schema::table( 'room_units' );
        $t_payments = Schema::table( 'payments' );
        $t_brooms   = Schema::table( 'booking_rooms' );

        // Total Rooms (Excluding out_of_service)
        $total_rooms = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t_rooms} WHERE status != 'out_of_service'" );
        
        // Occupied Rooms (Count distinct units booked for today)
        // Logic: booking overlaps 'today' (start <= today < end) AND is valid status.
        $occupied = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(DISTINCT br.room_unit_id) 
             FROM {$t_brooms} br
             INNER JOIN {$t_bookings} b ON br.booking_id = b.id
             WHERE b.check_in <= %s AND b.check_out > %s 
               AND b.status IN ('hold','pending','confirmed','checked_in') 
               AND br.room_unit_id IS NOT NULL",
            $today, $today
        ) );
        
        $occupancy_pct = $total_rooms > 0 ? round( ( $occupied / $total_rooms ) * 100 ) : 0;

        // 2. Arrivals / Departures Today
        $arrivals = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$t_bookings} WHERE check_in = %s AND status IN ('hold','pending','confirmed')",
            $today
        ) );
        $departures = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$t_bookings} WHERE check_out = %s AND status IN ('checked_in')",
            $today
        ) );

        // 3. Revenue Month (Approved Payments only)
        $revenue_month = (float) $wpdb->get_var( $wpdb->prepare(
            "SELECT SUM(amount) FROM {$t_payments} 
             WHERE status = 'approved' 
               AND created_at >= %s AND created_at <= %s",
            $month_start . ' 00:00:00',
            $month_end . ' 23:59:59'
        ) );

        // 4. Outstanding Balance (Pending collections from confirmed/checked_in bookings)
        // We only care about active bookings, not cancelled/completed ones? 
        // Let's say all 'confirmed' or 'checked_in' or 'checked_out' (if not paid yet).
        $outstanding = (float) $wpdb->get_var( 
            "SELECT SUM(balance_due) FROM {$t_bookings} 
             WHERE status IN ('hold','pending','confirmed','checked_in','checked_out') AND balance_due > 0"
        );

        // 5. Disputes / Chargebacks (All time or monthly? Let's do All Time for alert)
        $disputes = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t_payments} WHERE status = 'charged_back'" );

        // 6. Chart Data: Occupancy and Revenue last 30 days
        $chart_days = 30;
        $chart_start = date( 'Y-m-d', strtotime( "-{$chart_days} days" ) );
        $chart_end   = $today;

        // Fetch bookings affecting this range for occupancy
        $chart_bookings = $wpdb->get_results( $wpdb->prepare(
            "SELECT b.check_in, b.check_out, COUNT(br.room_unit_id) as units
             FROM {$t_bookings} b
             LEFT JOIN {$t_brooms} br ON b.id = br.booking_id
             WHERE b.status IN ('hold','pending','confirmed','checked_in','checked_out')
               AND b.check_in < %s AND b.check_out > %s
             GROUP BY b.id",
            $chart_end, $chart_start
        ), \ARRAY_A );

        $occupancy_trend = [];
        $revenue_trend   = [];
        // Init maps
        $d = $chart_start;
        while ( $d <= $chart_end ) {
            $occupancy_trend[ $d ] = 0;
            $revenue_trend[ $d ]   = 0.0;
            $d = date( 'Y-m-d', strtotime( $d . ' +1 day' ) );
        }

        foreach ( $chart_bookings as $b ) {
            $s = max( $chart_start, $b['check_in'] );
            $e = min( $chart_end, date('Y-m-d', strtotime($b['check_out'] . ' -1 day')) ); // check_out is exclusive
            
            if ( $s > $e ) continue;

            $curr = $s;
            while ( $curr <= $e ) {
                if ( isset( $occupancy_trend[ $curr ] ) ) {
                    $occupancy_trend[ $curr ] += (int) $b['units'];
                }
                $curr = date( 'Y-m-d', strtotime( $curr . ' +1 day' ) );
            }
        }

        // Fetch payments affecting this range for revenue
        $chart_payments = $wpdb->get_results( $wpdb->prepare(
            "SELECT DATE(created_at) as pay_date, SUM(amount) as daily_total
             FROM {$t_payments}
             WHERE status = 'approved'
               AND created_at >= %s AND created_at <= %s
             GROUP BY DATE(created_at)",
            $chart_start . ' 00:00:00', $chart_end . ' 23:59:59'
        ), \ARRAY_A );

        foreach ( $chart_payments as $p ) {
            if ( isset( $revenue_trend[ $p['pay_date'] ] ) ) {
                $revenue_trend[ $p['pay_date'] ] += (float) $p['daily_total'];
            }
        }

        // Convert to arrays
        $occ_trend_data = [];
        $rev_trend_data = [];
        foreach ( $occupancy_trend as $date => $count ) {
             $pct = $total_rooms > 0 ? round( ( $count / $total_rooms ) * 100 ) : 0;
             $occ_trend_data[] = [ 'date' => $date, 'value' => $pct ];
        }
        foreach ( $revenue_trend as $date => $amount ) {
             $rev_trend_data[] = [ 'date' => $date, 'value' => round( $amount, 2 ) ];
        }

        return [
            'total_rooms'   => $total_rooms,
            'occupancy_pct' => $occupancy_pct,
            'occupied'      => $occupied,
            'arrivals'      => $arrivals,
            'departures'    => $departures,
            'revenue_month' => $revenue_month,
            'outstanding'   => $outstanding,
            'disputes'      => $disputes,
            'occupancy_trend' => $occ_trend_data,
            'revenue_trend'   => $rev_trend_data
        ];
    }

    /**
     * Get Occupancy Report (ADR, RevPAR).
     *
     * @param string $start_date Y-m-d
     * @param string $end_date Y-m-d (exclusive)
     */
    public function get_occupancy_report( string $start_date, string $end_date, $room_type_id = null ): array {
        global $wpdb;
        $t_bookings = Schema::table( 'bookings' );
        $t_rooms    = Schema::table( 'room_units' );
        $t_rtypes   = Schema::table( 'room_types' );
        
        $rt_filter = '';
        if ( ! empty( $room_type_id ) ) {
            $rt_filter = $wpdb->prepare( ' AND room_type_id = %d', $room_type_id );
        }
        $total_rooms = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t_rooms} WHERE status != 'out_of_service' {$rt_filter}" );
        
        // Validate dates
        $start = empty($start_date) ? date('Y-m-01') : $start_date;
        $end   = empty($end_date)   ? date('Y-m-t') : $end_date;
        
        // Calculate days in range
        $diff = ( strtotime($end) - strtotime($start) ) / 86400;
        $total_available_nights = $total_rooms * max(1, $diff);

        // Fetch bookings interacting with range
        // We need robust "nights sold" within range.
        // A booking from Jan 1 to Jan 5 (4 nights). Range Jan 3 to Jan 10.
        // Intersection: Jan 3, Jan 4 (2 nights).
        
        // This query fetches bookings that overlap, and we calculate intersection in PHP or SQL.
        // Let's try SQL for sum of nights.
        // GREATEST(start, check_in) ... LEAST(end, check_out)
        
        // To get the room type names, let's fetch them
        $room_types = $wpdb->get_results( "SELECT id, name FROM {$t_rtypes}", \ARRAY_A );
        $rt_map = [];
        foreach( $room_types as $rt ) $rt_map[$rt['id']] = $rt['name'];

        $booking_rt_filter = '';
        if ( ! empty( $room_type_id ) ) {
            // Usually, b.room_type stores the ID or name. Let's assume ID if it's numeric, or we just filter in PHP
            // The schema says `room_type` varchar. Let's filter in PHP to be safe.
        }

        // Fetch bookings — only confirmed/checked count as sold nights
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT id, check_in, check_out, extras_total, amount_paid,
                    DATEDIFF(check_out, check_in) as total_nights
             FROM {$t_bookings}
             WHERE status IN ('confirmed','checked_in','checked_out')
               AND check_in < %s AND check_out > %s",
            $end, $start
        ), \ARRAY_A );

        // Fetch approved payment totals per booking (separate query)
        $t_payments = Schema::table( 'payments' );
        $payment_map = [];
        $pay_rows = $wpdb->get_results(
            "SELECT booking_id, SUM(amount) as paid FROM {$t_payments} WHERE status = 'approved' GROUP BY booking_id",
            \ARRAY_A
        );
        foreach ( $pay_rows as $pr ) {
            $payment_map[ $pr['booking_id'] ] = (float) $pr['paid'];
        }

        // Fetch room_type_id per booking from booking_rooms
        $t_brooms = Schema::table( 'booking_rooms' );
        $rt_booking_map = []; // booking_id => room_type_id
        $br_rows = $wpdb->get_results(
            "SELECT booking_id, room_type_id FROM {$t_brooms}",
            \ARRAY_A
        );
        foreach ( $br_rows as $br ) {
            $rt_booking_map[ $br['booking_id'] ] = (int) $br['room_type_id'];
        }
        
        $nights_sold = 0;
        $revenue_attributed = 0.0;
        $breakdown = []; // [ rt_id => [ nights_sold, revenue, capacity ] ]

        // Init breakdown capacities
        $rt_capacities = [];
        $rt_units = $wpdb->get_results( "SELECT room_type_id, COUNT(*) as c FROM {$t_rooms} WHERE status != 'out_of_service' GROUP BY room_type_id", \ARRAY_A );
        foreach( $rt_units as $u ) {
            $rt_capacities[$u['room_type_id']] = (int)$u['c'] * max(1, $diff);
            $breakdown[$u['room_type_id']] = [
                'room_type_name' => $rt_map[$u['room_type_id']] ?? 'Desconocido',
                'nights_sold' => 0,
                'revenue' => 0.0,
                'capacity_nights' => $rt_capacities[$u['room_type_id']]
            ];
        }

        foreach ( $rows as $row ) {
            // Get room type from booking_rooms map
            $matched_rt_id = $rt_booking_map[ $row['id'] ] ?? null;

            if ( ! empty( $room_type_id ) && $matched_rt_id !== (int)$room_type_id ) {
                continue; // Skip if it doesn't match filter
            }

            // Intersection
            $s = max( strtotime($start), strtotime($row['check_in']) );
            $e = min( strtotime($end),   strtotime($row['check_out']) );
            
            $days_overlap = max( 0, ($e - $s) / 86400 );
            
            if ( $days_overlap > 0 ) {
                $nights_sold += $days_overlap;
                
                // Attribute revenue proportionally using PAID amount only
                $paid_total     = $payment_map[ $row['id'] ] ?? 0;
                if ($paid_total <= 0) $paid_total = (float) ($row['amount_paid'] ?? 0);
                $booking_nights = (int) $row['total_nights'];
                $daily_rate = $booking_nights > 0 ? $paid_total / $booking_nights : 0;
                
                $rev = $daily_rate * $days_overlap;
                $revenue_attributed += $rev;

                if ( $matched_rt_id && isset($breakdown[$matched_rt_id]) ) {
                    $breakdown[$matched_rt_id]['nights_sold'] += $days_overlap;
                    $breakdown[$matched_rt_id]['revenue'] += $rev;
                }
            }
        }

        $occupancy_pct = $total_available_nights > 0 ? ($nights_sold / $total_available_nights) * 100 : 0;
        $adr = $nights_sold > 0 ? $revenue_attributed / $nights_sold : 0;
        $revpar = $total_available_nights > 0 ? $revenue_attributed / $total_available_nights : 0; 

        // Finalize breakdown
        $final_breakdown = [];
        foreach ( $breakdown as $rt_id => $data ) {
            // If filtering, only include the filtered one
            if ( ! empty( $room_type_id ) && $rt_id !== (int)$room_type_id ) continue;

            $cap = $data['capacity_nights'];
            $ns = $data['nights_sold'];
            $pct = $cap > 0 ? ($ns / $cap) * 100 : 0;
            $rt_adr = $ns > 0 ? $data['revenue'] / $ns : 0;
            $data['occupancy_pct'] = round($pct, 2);
            $data['adr'] = round($rt_adr, 2);
            $data['revenue'] = round($data['revenue'], 2);
            $final_breakdown[] = $data;
        }

        return [
            'start' => $start,
            'end'   => $end,
            'total_rooms'      => $total_rooms,
            'days_in_range'    => $diff,
            'capacity_nights'  => $total_available_nights,
            'nights_sold'      => $nights_sold,
            'occupancy_pct'    => round( $occupancy_pct, 2 ),
            'revenue_generated'=> round( $revenue_attributed, 2 ),
            'adr'              => round( $adr, 2 ),
            'revpar'           => round( $revpar, 2 ),
            'breakdown'        => $final_breakdown
        ];
    }

    /**
     * Financial Report.
     */
    public function get_financial_report( string $start_date, string $end_date ): array {
        global $wpdb;
        $t_payments = Schema::table( 'payments' );
        $t_bookings = Schema::table( 'bookings' );

         // Validate dates
        $start = empty($start_date) ? date('Y-m-01') : $start_date;
        $end   = empty($end_date)   ? date('Y-m-t') : $end_date;

        // 1. Collected Revenue (Payments created in range)
        // Group by pay_mode and gateway
        $collected_rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT pay_mode, gateway, SUM(amount) as total, COUNT(*) as txn_count
             FROM {$t_payments}
             WHERE status = 'approved'
               AND created_at >= %s AND created_at <= %s
             GROUP BY pay_mode, gateway",
            $start . ' 00:00:00', $end . ' 23:59:59'
        ), \ARRAY_A );

        $total_collected = 0.0;
        foreach($collected_rows as $c) $total_collected += $c['total'];

        // 2. Breakdown Accommodation vs Extras
        // Fetch all approved payments in range with their booking IDs
        $payments = $wpdb->get_results( $wpdb->prepare(
            "SELECT p.amount, p.booking_id, b.grand_total, b.extras_total
             FROM {$t_payments} p
             LEFT JOIN {$t_bookings} b ON p.booking_id = b.id
             WHERE p.status = 'approved'
               AND p.created_at >= %s AND p.created_at <= %s",
             $start . ' 00:00:00', $end . ' 23:59:59'
        ), \ARRAY_A );

        $accommodation_revenue = 0.0;
        $extras_revenue = 0.0;

        foreach ( $payments as $p ) {
            $amt = (float) $p['amount'];
            $grand = (float) $p['grand_total'];
            $extr  = (float) $p['extras_total'];

            if ( $grand > 0 && $extr > 0 ) {
                $extras_ratio = $extr / $grand;
                $portion_extras = $amt * $extras_ratio;
                $portion_accom  = $amt - $portion_extras;
                
                $extras_revenue += $portion_extras;
                $accommodation_revenue += $portion_accom;
            } else {
                // If no extras, all goes to accommodation
                $accommodation_revenue += $amt;
            }
        }

        return [
            'period' => [ 'start' => $start, 'end' => $end ],
            'breakdown' => $collected_rows,
            'total_collected' => round($total_collected, 2),
            'accommodation_revenue' => round($accommodation_revenue, 2),
            'extras_revenue' => round($extras_revenue, 2)
        ];
    }

    /**
     * Source Report.
     */
    public function get_source_report( string $start_date, string $end_date ): array {
        global $wpdb;
        $t_bookings = Schema::table( 'bookings' );

        $start = empty($start_date) ? date('Y-m-01') : $start_date;
        $end   = empty($end_date)   ? date('Y-m-t') : $end_date;

        // Count bookings created in range (Sales pace) OR Bookings staying in range?
        // Usually Source reports track "Sales generated". So date_created.
        
        // Fetch bookings by source (simple query, no joins)
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT id, source FROM {$t_bookings}
             WHERE status NOT IN ('cancelled') 
               AND created_at >= %s AND created_at <= %s",
            $start . ' 00:00:00', $end . ' 23:59:59'
        ), \ARRAY_A );

        // Fetch approved payment totals per booking
        $t_payments = Schema::table( 'payments' );
        $payment_map = [];
        $pay_rows = $wpdb->get_results(
            "SELECT booking_id, SUM(amount) as paid FROM {$t_payments} WHERE status = 'approved' GROUP BY booking_id",
            \ARRAY_A
        );
        foreach ( $pay_rows as $pr ) {
            $payment_map[ $pr['booking_id'] ] = (float) $pr['paid'];
        }

        // Aggregate by source in PHP
        $source_data = [];
        foreach ( $rows as $row ) {
            $src = $row['source'] ?: 'direct';
            if ( ! isset( $source_data[$src] ) ) {
                $source_data[$src] = [ 'source' => $src, 'count' => 0, 'revenue' => 0 ];
            }
            $source_data[$src]['count']++;
            $source_data[$src]['revenue'] += $payment_map[ $row['id'] ] ?? 0;
        }

        return array_values( $source_data );
    }
}
