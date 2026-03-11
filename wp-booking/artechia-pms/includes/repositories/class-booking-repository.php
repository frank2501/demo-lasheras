<?php
/**
 * Booking repository: queries on bookings + booking_rooms for availability.
 */
namespace Artechia\PMS\Repositories;

use Artechia\PMS\DB\Schema;
use Artechia\PMS\Services\Settings;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class BookingRepository extends BaseRepository {

    public function table_name(): string {
        return 'bookings';
    }

    /**
     * Override find to include guest data by default.
     */
    public function find( int $id ): ?array {
        $row = $this->find_by_id( $id );
        return $row ? (array)$row : null;
    }

    protected function fillable(): array {
        return [
            'booking_code', 'property_id', 'guest_id', 'rate_plan_id',
            'check_in', 'check_out', 'nights', 'adults', 'children',
            'status', 'source', 'source_ref',
            'subtotal', 'extras_total', 'taxes_total', 'discount_total',
            'grand_total', 'amount_paid', 'balance_due', 'payment_status', 'payment_method', 'currency',
            'pricing_snapshot', 'coupon_code', 'special_requests',
            'internal_notes', 'access_token', 'cancellation_policy_json',
            'cancelled_at', 'cancel_reason',
            'checked_in_at', 'checked_out_at',
            'created_at',
        ];
    }

    protected function formats(): array {
        return [
            'property_id'  => '%d',
            'guest_id'     => '%d',
            'rate_plan_id' => '%d',
            'nights'       => '%d',
            'adults'       => '%d',
            'children'     => '%d',
            'subtotal'     => '%f',
            'extras_total' => '%f',
            'taxes_total'  => '%f',
            'discount_total'=> '%f',
            'grand_total'  => '%f',
            'amount_paid'  => '%f',
            'balance_due'  => '%f',
        ];
    }

    /**
     * Statuses that ALWAYS consume inventory.
     * Web pending bookings are excluded (they have locks).
     * Admin pending bookings are included (no lock).
     */
    private const CONFIRMED_STATUSES = [ 'confirmed', 'checked_in', 'deposit_paid', 'paid', 'hold' ];

    /** All active statuses (for unit assignment exclusion). */
    private const ALL_ACTIVE_STATUSES = [ 'pending', 'confirmed', 'checked_in', 'hold' ];

    /**
     * Check if pending bookings should block units (cached per request).
     */
    public static function pending_blocks_unit(): bool {
        static $cached = null;
        if ( $cached === null ) {
            $cached = Settings::get( 'pending_blocks_unit', '0' ) === '1';
        }
        return $cached;
    }

    /**
     * Count how many booking_rooms overlap a date range for a given room type.
     * Overlap: booking.check_in < $check_out AND booking.check_out > $check_in
     *
     * @param int      $room_type_id
     * @param string   $check_in   Y-m-d
     * @param string   $check_out  Y-m-d
     * @param int|null $exclude_booking_id  Exclude a specific booking (for updates).
     * @return int
     */
    public function count_booked_units(
        int $room_type_id,
        string $check_in,
        string $check_out,
        ?int $exclude_booking_id = null
    ): int {
        $b  = Schema::table( 'bookings' );
        $br = Schema::table( 'booking_rooms' );
        
        $statuses = "'" . implode( "','", self::CONFIRMED_STATUSES ) . "'";
        $pending_sql = self::pending_blocks_unit() ? "OR b.status = 'pending'" : '';

        $exclude_sql = '';
        $values = [ $room_type_id, $check_out, $check_in ];

        if ( $exclude_booking_id ) {
            $exclude_sql = 'AND b.id != %d';
            $values[] = $exclude_booking_id;
        }

        return (int) $this->db()->get_var( $this->db()->prepare(
            "SELECT COUNT(*)
             FROM {$br} br
             JOIN {$b} b ON b.id = br.booking_id
             WHERE br.room_type_id = %d
               AND b.check_in < %s
               AND b.check_out > %s
               AND (b.status IN ({$statuses})
                    {$pending_sql})
               {$exclude_sql}",
            ...$values
        ) );
    }

    /**
     * Find first available room unit for a type in a date range.
     *
     * @return array|null  Room unit row or null.
     */
    public function get_free_unit_for_type(
        int $property_id,
        int $room_type_id,
        string $check_in,
        string $check_out
    ): ?array {
        $ru = Schema::table( 'room_units' );
        $br = Schema::table( 'booking_rooms' );
        $b  = Schema::table( 'bookings' );
        // Use confirmed statuses; add pending only if setting says so.
        $pool = self::pending_blocks_unit() ? self::ALL_ACTIVE_STATUSES : self::CONFIRMED_STATUSES;
        $statuses = "'" . implode( "','", $pool ) . "'";

        $row = $this->db()->get_row( $this->db()->prepare(
            "SELECT u.* FROM {$ru} u
             WHERE u.room_type_id = %d
               AND u.property_id = %d
               AND u.status = 'available'
               AND u.id NOT IN (
                   SELECT br2.room_unit_id FROM {$br} br2
                   JOIN {$b} b2 ON b2.id = br2.booking_id
                   WHERE br2.room_type_id = %d
                     AND br2.room_unit_id IS NOT NULL
                     AND b2.check_in < %s
                     AND b2.check_out > %s
                     AND b2.status IN ({$statuses})
               )
             ORDER BY u.sort_order ASC, u.id ASC
             LIMIT 1",
            $room_type_id, $property_id, $room_type_id, $check_out, $check_in
        ), \ARRAY_A );

        return $row ?: null;
    }

    /**
     * Check if a specific unit is available for a date range.
     */
    public function is_unit_available(
        int $unit_id,
        int $property_id,
        string $check_in,
        string $check_out,
        ?int $exclude_booking_id = null
    ): bool {
        $br = Schema::table( 'booking_rooms' );
        $b  = Schema::table( 'bookings' );
        $pool = self::pending_blocks_unit() ? self::ALL_ACTIVE_STATUSES : self::CONFIRMED_STATUSES;
        $statuses = "'" . implode( "','", $pool ) . "'";
        $exclude_sql = '';
        $values = [ $unit_id, $check_out, $check_in ];

        if ( $exclude_booking_id ) {
            $exclude_sql = 'AND b.id != %d';
            $values[] = $exclude_booking_id;
        }

        $count = (int) $this->db()->get_var( $this->db()->prepare(
            "SELECT COUNT(*)
             FROM {$br} br
             JOIN {$b} b ON b.id = br.booking_id
             WHERE br.room_unit_id = %d
               AND b.check_in < %s
               AND b.check_out > %s
               AND b.status IN ({$statuses})
               {$exclude_sql}",
            ...$values
        ) );

        return $count === 0;
    }

    /**
     * Assign a room unit to a booking's booking_rooms on confirmation.
     */
    public function assign_unit_on_confirm( int $booking_id ): bool {
        $booking = $this->find( $booking_id );
        if ( ! $booking ) return false;

        $br = Schema::table( 'booking_rooms' );
        $rooms = $this->db()->get_results( $this->db()->prepare(
            "SELECT * FROM {$br} WHERE booking_id = %d AND (room_unit_id IS NULL OR room_unit_id = 0)",
            $booking_id
        ), \ARRAY_A );

        if ( empty( $rooms ) ) return true; // already assigned

        foreach ( $rooms as $room ) {
            // Lock by property + room type + dates to prevent race condition on unit selection.
            // Lock name: artechia_assign_{property_id}_{room_type_id}_{check_in}_{check_out}
            $lock_name = "artechia_assign_{$booking['property_id']}_{$room['room_type_id']}_{$booking['check_in']}_{$booking['check_out']}";
            
            // Try to acquire lock for 5 seconds.
            $locked = $this->db()->get_var( $this->db()->prepare( "SELECT GET_LOCK(%s, 5)", $lock_name ) );

            if ( ! $locked ) {
                return false; // Could not acquire lock, fail assignment.
            }

            try {
                // Double check if still unassigned (though we query room_unit_id IS NULL above, 
                // in a race inside this loop it matters less than the unit selection).
                
                $unit = $this->get_free_unit_for_type(
                    (int) $booking['property_id'],
                    (int) $room['room_type_id'],
                    $booking['check_in'],
                    $booking['check_out']
                );

                if ( ! $unit ) {
                    // No free unit found.
                    return false; 
                }

                // Assign the unit.
                $this->db()->update(
                    $br,
                    [ 'room_unit_id' => $unit['id'] ],
                    [ 'id' => $room['id'] ],
                    [ '%d' ],
                    [ '%d' ]
                );

            } finally {
                // Always release lock.
                $this->db()->query( $this->db()->prepare( "SELECT RELEASE_LOCK(%s)", $lock_name ) );
            }
        }

        return true;
    }

    /* ── Finders ─────────────────────────────────────── */

    /**
     * Find booking by booking_code, including guest data.
     */
    public function find_by_code( string $code ): ?array {
        $b = $this->table();
        $g = Schema::table( 'guests' );
        
        $sql = "SELECT b.*, 
                       g.first_name as guest_first_name, 
                       g.last_name as guest_last_name, 
                       g.email as guest_email,
                       g.phone as guest_phone,
                       g.document_type as guest_document_type,
                       g.document_number as guest_document_number,
                       COALESCE(b.grand_total, 0) as grand_total,
                       COALESCE(b.amount_paid, 0) as amount_paid,
                       COALESCE(b.balance_due, 0) as balance_due
                FROM {$b} b
                LEFT JOIN {$g} g ON g.id = b.guest_id
                WHERE b.booking_code = %s";
        
        $row = $this->db()->get_row( $this->db()->prepare( $sql, $code ), \ARRAY_A );
        return $row ?: null;
    }

    /**
     * Find booking by access_token.
     */
    public function find_by_token( string $token ): ?array {
        $row = $this->db()->get_row( $this->db()->prepare(
            "SELECT * FROM {$this->table()} WHERE access_token = %s",
            $token
        ), \ARRAY_A );
        return $row ?: null;
    }

    /**
     * Update booking status with optional extra columns.
     */
    public function update_status( int $id, string $status, array $extra = [] ): bool {
        $data = array_merge( $extra, [ 'status' => $status ] );
        return $this->update( $id, $data );
    }

    /* ── Child inserts ───────────────────────────────── */

    /**
     * Insert a booking_room row.
     */
    public function create_room( array $data ): int|false {
        $table  = Schema::table( 'booking_rooms' );
        $values = [
            'booking_id'          => (int) $data['booking_id'],
            'room_type_id'        => (int) $data['room_type_id'],
            'adults'              => (int) ( $data['adults'] ?? 1 ),
            'children'            => (int) ( $data['children'] ?? 0 ),
            'rate_per_night_json' => $data['rate_per_night_json'] ?? null,
            'subtotal'            => (float) ( $data['subtotal'] ?? 0 ),
        ];
        $formats = [ '%d', '%d', '%d', '%d', '%s', '%f' ];

        if ( ! empty( $data['room_unit_id'] ) ) {
            $values['room_unit_id'] = (int) $data['room_unit_id'];
            $formats[] = '%d';
        }

        $result = $this->db()->insert( $table, $values, $formats );
        return $result ? (int) $this->db()->insert_id : false;
    }

    /**
     * Insert a booking_extra row.
     */
    public function create_extra( array $data ): int|false {
        $table = Schema::table( 'booking_extras' );
        $result = $this->db()->insert( $table, [
            'booking_id'  => (int) $data['booking_id'],
            'extra_id'    => (int) $data['extra_id'],
            'quantity'    => (int) ( $data['quantity'] ?? 1 ),
            'unit_price'  => (float) $data['unit_price'],
            'total_price' => (float) $data['total_price'],
        ], [ '%d', '%d', '%d', '%f', '%f' ] );
        return $result ? (int) $this->db()->insert_id : false;
    }

    /**
     * Get booking rooms for a booking with hydrated unit and room type names.
     */
    public function get_rooms( int $booking_id ): array {
        $br = Schema::table( 'booking_rooms' );
        $rt = Schema::table( 'room_types' );
        $ru = Schema::table( 'room_units' );
        
        return $this->db()->get_results( $this->db()->prepare(
            "SELECT br.*, 
                    COALESCE(rt.name, '—') as room_type_name,
                    COALESCE(ru.name, '—') as unit_name,
                    COALESCE(br.subtotal, 0) as subtotal
             FROM {$br} br
             LEFT JOIN {$rt} rt ON rt.id = br.room_type_id
             LEFT JOIN {$ru} ru ON ru.id = br.room_unit_id
             WHERE br.booking_id = %d",
            $booking_id
        ), \ARRAY_A ) ?: [];
    }

    /**
     * Get booking extras for a booking with hydrated extra names.
     */
    public function get_extras( int $booking_id ): array {
        $be = Schema::table( 'booking_extras' );
        $ex = Schema::table( 'extras' );
        
        return $this->db()->get_results( $this->db()->prepare(
            "SELECT be.*, 
                    COALESCE(ex.name, 'Extra') as name,
                    COALESCE(be.unit_price, 0) as unit_price,
                    COALESCE(be.total_price, 0) as subtotal
             FROM {$be} be
             LEFT JOIN {$ex} ex ON ex.id = be.extra_id
             WHERE be.booking_id = %d",
            $booking_id
        ), \ARRAY_A ) ?: [];
    }

    /**
     * Find a hydrated booking by ID.
     */
    public function find_by_id( int $id ): ?object {
        $b = $this->table();
        $g = Schema::table( 'guests' );
        
        $sql = "SELECT b.*, 
                       g.first_name as guest_first_name, 
                       g.last_name as guest_last_name, 
                       g.email as guest_email,
                       g.phone as guest_phone,
                       g.document_type as guest_document_type,
                       g.document_number as guest_document_number,
                       COALESCE(b.grand_total, 0) as grand_total,
                       COALESCE(b.amount_paid, 0) as amount_paid,
                       COALESCE(b.balance_due, 0) as balance_due,
                       COALESCE(b.subtotal, 0) as subtotal,
                       COALESCE(b.extras_total, 0) as extras_total,
                       COALESCE(b.taxes_total, 0) as taxes_total,
                       COALESCE(b.discount_total, 0) as discount_total
                FROM {$b} b
                LEFT JOIN {$g} g ON g.id = b.guest_id
                WHERE b.id = %d";
                
        return $this->db()->get_row( $this->db()->prepare( $sql, $id ) );
    }

    /**
     * Find bookings with filters and pagination.
     *
     * @param array $args {
     *     @type int    $property_id
     *     @type string $status
     *     @type string $date_from      Check-in >= date
     *     @type string $date_to        Check-in <= date
     *     @type string $source
     *     @type string $payment_status
     *     @type string $search         Search in code, guest_name, guest_email
     *     @type int    $page
     *     @type int    $per_page
     * }
     * @return array { data: array, total: int, pages: int }
     */
    public function find_all( array $args = [] ): array {
        global $wpdb;
        $table = $this->table(); // wp_artechia_bookings
        
        $where = [ '1=1' ];
        $query_args = [];
        $join = '';

        // Tables
        $g_table = \Artechia\PMS\DB\Schema::table( 'guests' );
        $br_table = \Artechia\PMS\DB\Schema::table( 'booking_rooms' );
        $rt_table = \Artechia\PMS\DB\Schema::table( 'room_types' );
        $ru_table = \Artechia\PMS\DB\Schema::table( 'room_units' );

        // Filters - Strict checks to avoid default '0' or empty string filtering
        if ( isset( $args['property_id'] ) && is_numeric( $args['property_id'] ) && (int) $args['property_id'] > 0 ) {
            $where[] = 'b.property_id = %d';
            $query_args[] = (int) $args['property_id'];
        }
        if ( ! empty( $args['status'] ) && is_string( $args['status'] ) ) {
            $where[] = 'b.status = %s';
            $query_args[] = $args['status'];
        }
        if ( ! empty( $args['source'] ) && is_string( $args['source'] ) ) {
            $where[] = 'b.source = %s';
            $query_args[] = $args['source'];
        }
        if ( ! empty( $args['payment_status'] ) && is_string( $args['payment_status'] ) ) {
            $where[] = 'b.payment_status = %s';
            $query_args[] = $args['payment_status'];
        }
        if ( ! empty( $args['date_from'] ) ) {
            $where[] = 'b.check_in >= %s';
            $query_args[] = $args['date_from'];
        }
        if ( ! empty( $args['date_to'] ) ) {
            $where[] = 'b.check_in <= %s';
            $query_args[] = $args['date_to'];
        }
        
        // Search - Needs JOIN with guests
        if ( ! empty( $args['search'] ) ) {
            $term = '%' . $wpdb->esc_like( $args['search'] ) . '%';
            
            // JOIN only when searching to avoid performance hit/complexity on default list
            // (Note: We now join guests always for name display, so this search join logic needs adjustment to avoid double join or just rely on the main join)
            // Since we are moving to ALWAYS join guests for display, we don't need a specific join just for search, 
            // but we need the where clause.
            
            // The main query now has LEFT JOIN guests g. So we can validly use g.first_name here.
            
            $where[] = '(b.booking_code LIKE %s OR g.first_name LIKE %s OR g.last_name LIKE %s OR g.email LIKE %s OR g.phone LIKE %s)';
            $query_args[] = $term; 
            $query_args[] = $term; 
            $query_args[] = $term; 
            $query_args[] = $term; 
            $query_args[] = $term; 
        }

        $where_sql = implode( ' AND ', $where );

        // Count
        $count_sql = "SELECT COUNT(*) FROM {$table} b WHERE {$where_sql}";
        // Note: if searching by guest, this count might be wrong without join? 
        // Ah, if searching, we need the guest join even for count.
        if ( ! empty( $args['search'] ) ) {
             $count_sql = "SELECT COUNT(*) FROM {$table} b LEFT JOIN {$g_table} g ON b.guest_id = g.id WHERE {$where_sql}";
        }
        
        if ( ! empty( $query_args ) ) {
            $total = (int) $wpdb->get_var( $wpdb->prepare( $count_sql, ...$query_args ) );
        } else {
            $total = (int) $wpdb->get_var( $count_sql );
        }

        // Pagination
        $page     = max( 1, (int) ( $args['page'] ?? 1 ) );
        $per_page = max( 1, (int) ( $args['per_page'] ?? 20 ) );
        $offset   = ( $page - 1 ) * $per_page;
        $pages    = $per_page > 0 ? ceil( $total / $per_page ) : 0;

        // Data
        $query_args[] = $per_page;
        $query_args[] = $offset;
        
        // Join for Room/Unit names (MVP: take first room)
        // We use a subquery or join. Since we already have joins, let's just join booking_rooms via subquery or direct join?
        // Direct join might duplicate rows if multiple rooms. 
        // For MVP (1 room), direct join is fine OR we can use GROUP BY b.id.
        // Let's use a subselect or left join with GROUP BY to be safe?
        // Actually, easiest is just LEFT JOIN and if duplicates, we group by b.id.
        
        $sql = "SELECT b.*, 
                       g.first_name, g.last_name, g.email as guest_email,
                       CONCAT(g.first_name, ' ', g.last_name) as guest_name,
                       rt.name as room_type_name,
                       ru.name as room_unit_name,
                       COALESCE(b.grand_total, 0) as grand_total, 
                       COALESCE(b.grand_total, 0) as total_cost, 
                       COALESCE(b.amount_paid, 0) as amount_paid, 
                       COALESCE(b.balance_due, 0) as balance_due 
                FROM {$table} b 
                LEFT JOIN {$g_table} g ON b.guest_id = g.id
                LEFT JOIN {$br_table} br ON br.booking_id = b.id
                LEFT JOIN {$rt_table} rt ON br.room_type_id = rt.id
                LEFT JOIN {$ru_table} ru ON br.room_unit_id = ru.id
                WHERE {$where_sql} 
                GROUP BY b.id
                ORDER BY b.created_at DESC LIMIT %d OFFSET %d";

        $data = $wpdb->get_results( $wpdb->prepare( $sql, ...$query_args ) );
        
        if ( $wpdb->last_error ) {
            \Artechia\PMS\Logger::error( 'db.find_all_error', $wpdb->last_error, 'bookings', null, [ 'sql' => $sql ] );
        }

        return [
            'data'  => $data,
            'total' => $total,
            'pages' => $pages,
        ];
    }

    /**
     * Get last checkout date for a unit.
     */
    public function get_last_checkout_for_unit( int $unit_id ): ?string {
        $b  = $this->table();
        $br = Schema::table( 'booking_rooms' );
        $today = current_time( 'Y-m-d' );

        $sql = "SELECT b.check_out 
                FROM {$b} b
                JOIN {$br} br ON br.booking_id = b.id
                WHERE br.room_unit_id = %d
                AND b.status IN ('confirmed', 'checked_in', 'checked_out')
                AND b.check_out <= %s
                ORDER BY b.check_out DESC
                LIMIT 1";
        
        return $this->db()->get_var( $this->db()->prepare( $sql, $unit_id, $today ) );
    }

    /**
     * Get next arrival date for a unit.
     */
    public function get_next_arrival_for_unit( int $unit_id ): ?string {
        $b  = $this->table();
        $br = Schema::table( 'booking_rooms' );
        $today = current_time( 'Y-m-d' );

        $sql = "SELECT b.check_in 
                FROM {$b} b
                JOIN {$br} br ON br.booking_id = b.id
                WHERE br.room_unit_id = %d
                AND b.status IN ('confirmed', 'deposit_paid', 'paid'" . ( self::pending_blocks_unit() ? ", 'pending'" : '' ) . ")
                AND b.check_in >= %s
                ORDER BY b.check_in ASC
                LIMIT 1";
        
        return $this->db()->get_var( $this->db()->prepare( $sql, $unit_id, $today ) );
    }

    /**
     * Get updated housekeeping stats (last checkout, next arrival) for multiple units.
     * Prevents N+1 query problem.
     * 
     * @param array $unit_ids List of unit IDs.
     * @return array [ unit_id => [ 'last_checkout' => date|null, 'next_arrival' => date|null ] ]
     */
    public function get_housekeeping_stats( array $unit_ids ): array {
        if ( empty( $unit_ids ) ) return [];

        $unit_ids_str = implode( ',', array_map( 'intval', $unit_ids ) );
        $today = current_time( 'Y-m-d' );
        
        $br = Schema::table( 'booking_rooms' );
        $b  = $this->table();

        // Q1: Last checkouts
        // We want the LATEST checkout date <= today for each unit.
        // Status: 'checked_out', 'checked_in', 'confirmed' (if they are leaving today?)
        // The original logic was: 'confirmed', 'checked_in', 'checked_out' AND check_out <= today.
        $sql_checkout = "SELECT br.room_unit_id as unit_id, MAX(b.check_out) as last_checkout
                         FROM {$br} br
                         JOIN {$b} b ON b.id = br.booking_id
                         WHERE br.room_unit_id IN ({$unit_ids_str})
                         AND b.status IN ('confirmed', 'checked_in', 'checked_out', 'deposit_paid', 'paid')
                         AND b.check_out <= %s
                         GROUP BY br.room_unit_id";
        
        $checkouts = $this->db()->get_results( $this->db()->prepare( $sql_checkout, $today ), \ARRAY_A );
        
        // Q2: Next arrivals
        // We want the EARLIEST arrival date >= today for each unit.
        // Status: 'confirmed', 'deposit_paid', 'paid', 'checked_in' (if extending?) 
        // Original logic: 'confirmed', 'pending'. 
        // Let's stick to safe "incoming" statuses: confirmed, deposit_paid, paid, pending.
        $arrival_statuses = "'confirmed', 'deposit_paid', 'paid'" . ( self::pending_blocks_unit() ? ", 'pending'" : '' );
        $sql_arrival = "SELECT br.room_unit_id as unit_id, MIN(b.check_in) as next_arrival
                        FROM {$br} br
                        JOIN {$b} b ON b.id = br.booking_id
                        WHERE br.room_unit_id IN ({$unit_ids_str})
                        AND b.status IN ({$arrival_statuses})
                        AND b.check_in >= %s
                        GROUP BY br.room_unit_id";

        $arrivals = $this->db()->get_results( $this->db()->prepare( $sql_arrival, $today ), \ARRAY_A );

        // Map results
        $stats = [];
        foreach ( $unit_ids as $uid ) {
            $stats[$uid] = [ 'last_checkout' => null, 'next_arrival' => null ];
        }

        foreach ( $checkouts as $row ) {
            $stats[$row['unit_id']]['last_checkout'] = $row['last_checkout'];
        }
        foreach ( $arrivals as $row ) {
            $stats[$row['unit_id']]['next_arrival'] = $row['next_arrival'];
        }

        return $stats;
    }

    /**
     * Check if a unit is currently occupied or has an arrival today.
     * Used to prevent setting 'out_of_service'.
     */
    public function has_active_or_incoming_booking( int $unit_id ): bool {
        $today = current_time( 'Y-m-d' );
        $br = Schema::table( 'booking_rooms' );
        $b  = $this->table();

        // 1. Currently Occupied (Checked In, overlapping today)
        // Check-in <= Today AND Check-out > Today
        $sql_occupied = "SELECT COUNT(*) FROM {$b} b
                         JOIN {$br} br ON br.booking_id = b.id
                         WHERE br.room_unit_id = %d
                         AND b.status = 'checked_in'
                         AND b.check_in <= %s
                         AND b.check_out > %s";
        
        $is_occupied = (int) $this->db()->get_var( $this->db()->prepare( $sql_occupied, $unit_id, $today, $today ) );
        if ( $is_occupied > 0 ) return true;

        // 2. Arrival Today (Confirmed/Paid, Check-in == Today)
        $sql_arrival = "SELECT COUNT(*) FROM {$b} b
                        JOIN {$br} br ON br.booking_id = b.id
                        WHERE br.room_unit_id = %d
                        AND b.status IN ('confirmed', 'deposit_paid', 'paid')
                        AND b.check_in = %s";

        $has_arrival = (int) $this->db()->get_var( $this->db()->prepare( $sql_arrival, $unit_id, $today ) );
        
        return $has_arrival > 0;
    }

    /**
     * Get bookings overlapping with range for a property.
     * 
     * @param int    $property_id
     * @param string $start_date Y-m-d
     * @param string $end_date   Y-m-d
     * @return array
     */
    public function find_for_calendar( int $property_id, string $start_date, string $end_date ): array {
        $b  = $this->table();
        $br = Schema::table( 'booking_rooms' );
        $g  = Schema::table( 'guests' );

        $sql = "SELECT b.id, b.booking_code, b.status, b.payment_status, b.check_in, b.check_out,
                       b.property_id, 
                       g.first_name, g.last_name,
                       br.room_unit_id, br.room_type_id
                FROM {$b} b
                JOIN {$g} g ON b.guest_id = g.id
                JOIN {$br} br ON b.id = br.booking_id
                WHERE b.property_id = %d
                  AND b.check_in < %s
                  AND b.check_out > %s
                  AND b.status NOT IN ('cancelled', 'expired')
                ORDER BY b.check_in ASC";

        return $this->db()->get_results( $this->db()->prepare( $sql, $property_id, $end_date, $start_date ), \ARRAY_A ) ?: [];
    }
}
