<?php
namespace Artechia\PMS\Repositories;

use Artechia\PMS\DB\Schema;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class GuestRepository extends BaseRepository {

    protected function table_name(): string {
        return 'guests';
    }

    protected function fillable(): array {
        return [
            'first_name', 'last_name', 'email', 'phone',
            'document_type', 'document_number', 'country', 'city', 'address',
            'notes', 'is_blacklisted', 'blacklist_reason', 'marketing_opt_out', 'wp_user_id',
        ];
    }

    protected function formats(): array {
        return [
            'is_blacklisted'    => '%d',
            'marketing_opt_out' => '%d',
            'wp_user_id'        => '%d',
        ];
    }

    /**
     * Search guests by name, email, phone or document number.
     */
    public function search( string $term, int $limit = 20 ): array {
        $like = '%' . $this->db()->esc_like( $term ) . '%';
        return $this->db()->get_results( $this->db()->prepare(
            "SELECT id, first_name, last_name, email, phone, document_type, document_number, country, city, address, notes, is_blacklisted, blacklist_reason, created_at 
             FROM {$this->table()}
             WHERE first_name LIKE %s
                OR last_name LIKE %s
                OR email LIKE %s
                OR phone LIKE %s
                OR document_number LIKE %s
             ORDER BY last_name ASC, first_name ASC
             LIMIT %d",
            $like, $like, $like, $like, $like, $limit
        ), \ARRAY_A ) ?: [];
    }

    /**
     * Find a guest by email.
     */
    public function find_by_email( string $email ): ?array {
        return $this->db()->get_row( $this->db()->prepare(
            "SELECT * FROM {$this->table()} WHERE email = %s LIMIT 1",
            sanitize_email( $email )
        ), \ARRAY_A ) ?: null;
    }

    /**
     * Find or create a guest by email.
     */
    public function find_or_create_by_email( array $data ): int {
        $email = sanitize_email( $data['email'] ?? '' );
        $doc_num = sanitize_text_field( $data['document_number'] ?? '' );
        
        $existing = null;

        if ( ! empty( $email ) ) {
            $existing = $this->db()->get_row( $this->db()->prepare(
                "SELECT id FROM {$this->table()} WHERE email = %s LIMIT 1",
                $email
            ), \ARRAY_A );
        }

        if ( ! $existing && ! empty( $doc_num ) ) {
            $existing = $this->db()->get_row( $this->db()->prepare(
                "SELECT id FROM {$this->table()} WHERE document_number = %s LIMIT 1",
                $doc_num
            ), \ARRAY_A );
        }

        if ( $existing ) {
            $this->update( (int) $existing['id'], $data );
            return (int) $existing['id'];
        }

        return (int) $this->create( $data );
    }

    /**
     * Get booking history for a guest.
     */
    public function booking_history( int $guest_id ): array {
        $bookings = Schema::table( 'bookings' );
        return $this->db()->get_results( $this->db()->prepare(
            "SELECT id, booking_code, check_in, check_out, nights, status, grand_total, source, created_at
             FROM {$bookings}
             WHERE guest_id = %d
             ORDER BY check_in DESC",
            $guest_id
        ), \ARRAY_A ) ?: [];
    }

    /**
     * Get all guests with booking stats (count, total revenue, last stay).
     */
    public function get_list_with_stats( array $args = [] ): array {
        $bookings = Schema::table( 'bookings' );
        $orderby  = $args['orderby'] ?? 'last_name';
        $order    = strtoupper( $args['order'] ?? 'ASC' ) === 'DESC' ? 'DESC' : 'ASC';
        $limit    = (int) ( $args['limit'] ?? 50 );

        return $this->db()->get_results(
            "SELECT g.*, 
                    COALESCE(s.booking_count, 0) AS booking_count,
                    COALESCE(s.total_revenue, 0) AS total_revenue,
                    s.last_checkin
             FROM {$this->table()} g
             LEFT JOIN (
                 SELECT guest_id, 
                        COUNT(*) AS booking_count, 
                        SUM(amount_paid) AS total_revenue,
                        MAX(check_in) AS last_checkin
                 FROM {$bookings}
                 WHERE status IN ('confirmed','checked_in','checked_out')
                 GROUP BY guest_id
             ) s ON s.guest_id = g.id
             ORDER BY g.{$orderby} {$order}
             LIMIT {$limit}",
            \ARRAY_A
        ) ?: [];
    }

    /**
     * Search guests with booking stats.
     */
    public function search_with_stats( string $term, int $limit = 50 ): array {
        $bookings = Schema::table( 'bookings' );
        $like = '%' . $this->db()->esc_like( $term ) . '%';
        return $this->db()->get_results( $this->db()->prepare(
            "SELECT g.*, 
                    COALESCE(s.booking_count, 0) AS booking_count,
                    COALESCE(s.total_revenue, 0) AS total_revenue,
                    s.last_checkin
             FROM {$this->table()} g
             LEFT JOIN (
                 SELECT guest_id, 
                        COUNT(*) AS booking_count, 
                        SUM(amount_paid) AS total_revenue,
                        MAX(check_in) AS last_checkin
                 FROM {$bookings}
                 WHERE status IN ('confirmed','checked_in','checked_out')
                 GROUP BY guest_id
             ) s ON s.guest_id = g.id
             WHERE g.first_name LIKE %s
                OR g.last_name LIKE %s
                OR g.email LIKE %s
                OR g.phone LIKE %s
                OR g.document_number LIKE %s
             ORDER BY g.last_name ASC, g.first_name ASC
             LIMIT %d",
            $like, $like, $like, $like, $like, $limit
        ), \ARRAY_A ) ?: [];
    }
}
