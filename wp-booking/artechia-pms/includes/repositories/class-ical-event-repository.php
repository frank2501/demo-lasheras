<?php
/**
 * Repository for iCal events (imported).
 */
namespace Artechia\PMS\Repositories;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class ICalEventRepository extends BaseRepository {

    protected function table_name(): string {
        return 'ical_events';
    }

    protected function fillable(): array {
        return [
            'feed_id', 'external_uid', 'booking_id',
            'start_date', 'end_date', 'summary', 'description',
            'event_hash', 'last_seen_at',
        ];
    }

    protected function formats(): array {
        return [
            'feed_id'    => '%d',
            'booking_id' => '%d',
        ];
    }

    public function find_by_uid( int $feed_id, string $uid ): ?array {
        $row = $this->db()->get_row( $this->db()->prepare(
            "SELECT * FROM {$this->table()} WHERE feed_id = %d AND external_uid = %s",
            $feed_id, $uid
        ), \ARRAY_A );
        return $row ?: null;
    }

    /**
     * Mark all events for a feed as "unseen" (update last_seen_at to old date? 
     * Or better, we update last_seen_at during sync and then find those NOT updated).
     * 
     * Actually, we don't need a specific method here, logic will be in Service.
     */
}
