<?php
/**
 * Repository for booking conflicts.
 */
namespace Artechia\PMS\Repositories;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class ConflictRepository extends BaseRepository {

    protected function table_name(): string {
        return 'conflicts';
    }

    protected function fillable(): array {
        return [
            'property_id', 'room_unit_id', 'local_booking_id', 'ical_event_id',
            'start_date', 'end_date', 'type', 'resolved', 'resolved_at', 'resolved_note',
        ];
    }

    protected function formats(): array {
        return [
            'property_id'      => '%d',
            'room_unit_id'     => '%d',
            'local_booking_id' => '%d',
            'ical_event_id'    => '%d',
            'resolved'         => '%d',
        ];
    }

    public function find_unresolved( int $property_id ): array {
        return $this->db()->get_results( $this->db()->prepare(
            "SELECT *, CAST(resolved AS UNSIGNED) as resolved FROM {$this->table()} WHERE property_id = %d AND resolved = 0 ORDER BY start_date ASC",
            $property_id
        ), \ARRAY_A ) ?: [];
    }
}
