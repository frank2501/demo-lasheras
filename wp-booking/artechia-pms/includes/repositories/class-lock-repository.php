<?php
/**
 * Lock repository: type-based availability locks for anti-double-booking.
 */
namespace Artechia\PMS\Repositories;

use Artechia\PMS\DB\Schema;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class LockRepository extends BaseRepository {

    protected function table_name(): string {
        return 'locks';
    }

    protected function fillable(): array {
        return [
            'lock_key', 'property_id', 'room_type_id', 'rate_plan_id',
            'check_in', 'check_out', 'qty', 'booking_id',
            'expires_at', 'meta_json', 'created_at',
        ];
    }

    protected function formats(): array {
        return [
            'property_id'  => '%d',
            'room_type_id' => '%d',
            'rate_plan_id' => '%d',
            'qty'          => '%d',
            'booking_id'   => '%d',
        ];
    }

    /**
     * Sum of locked qty that overlap [check_in, check_out) and haven't expired.
     */
    public function count_locked_qty(
        int $room_type_id,
        string $check_in,
        string $check_out
    ): int {
        $now = current_time( 'mysql', true );
        return (int) $this->db()->get_var( $this->db()->prepare(
            "SELECT COALESCE(SUM(qty), 0)
             FROM {$this->table()}
             WHERE room_type_id = %d
               AND check_in < %s
               AND check_out > %s
               AND expires_at > %s
               AND booking_id IS NULL",
            $room_type_id, $check_out, $check_in, $now
        ) );
    }

    /**
     * Create a lock and return the generated lock_key.
     */
    public function create_lock( array $data ): ?string {
        if ( empty( $data['lock_key'] ) ) {
            $data['lock_key'] = wp_generate_password( 32, false );
        }
        $id = $this->create( $data );
        return $id ? $data['lock_key'] : null;
    }

    /**
     * Delete a lock by its key.
     */
    public function delete_by_key( string $lock_key ): bool {
        return (bool) $this->db()->delete(
            $this->table(),
            [ 'lock_key' => $lock_key ],
            [ '%s' ]
        );
    }

    /**
     * Find a lock by its key.
     */
    public function find_by_key( string $lock_key ): ?array {
        $row = $this->db()->get_row( $this->db()->prepare(
            "SELECT * FROM {$this->table()} WHERE lock_key = %s",
            $lock_key
        ), \ARRAY_A );
        return $row ?: null;
    }

    /**
     * Purge all expired locks that are not tied to a booking.
     */
    public function purge_expired(): int {
        $now = current_time( 'mysql', true );
        return (int) $this->db()->query( $this->db()->prepare(
            "DELETE FROM {$this->table()} WHERE expires_at < %s AND booking_id IS NULL",
            $now
        ) );
    }

    /**
     * Find a lock by its associated booking_id.
     */
    public function find_by_booking_id( int $booking_id ): ?array {
        $row = $this->db()->get_row( $this->db()->prepare(
            "SELECT * FROM {$this->table()} WHERE booking_id = %d",
            $booking_id
        ), \ARRAY_A );
        return $row ?: null;
    }

    /**
     * Tie a lock to a booking (set booking_id on the lock row).
     */
    public function update_booking_id( string $lock_key, int $booking_id ): bool {
        return (bool) $this->db()->update(
            $this->table(),
            [ 'booking_id' => $booking_id ],
            [ 'lock_key' => $lock_key ],
            [ '%d' ],
            [ '%s' ]
        );
    }

    /**
     * Find active locks for display in admin (joined with room_types).
     */
    public function find_active_display_locks(): array {
        $now = current_time( 'mysql', true );
        $rt = Schema::table('room_types');
        
        return $this->db()->get_results( $this->db()->prepare(
            "SELECT l.*, rt.name as room_type_name
             FROM {$this->table()} l
             LEFT JOIN {$rt} rt ON l.room_type_id = rt.id
             WHERE l.expires_at > %s
               AND l.booking_id IS NULL
             ORDER BY l.expires_at ASC",
            $now
        ), \ARRAY_A ) ?: [];
    }
}
