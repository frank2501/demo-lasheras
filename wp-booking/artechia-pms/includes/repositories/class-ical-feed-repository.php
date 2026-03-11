<?php
/**
 * Repository for iCal feeds.
 */
namespace Artechia\PMS\Repositories;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class ICalFeedRepository extends BaseRepository {

    protected function table_name(): string {
        return 'ical_feeds';
    }

    protected function fillable(): array {
        return [
            'property_id', 'room_unit_id', 'name', 'url', 'export_token',
            'conflict_policy', 'sync_interval', 'last_sync_at',
            'last_sync_status', 'last_error', 'is_active',
        ];
    }

    protected function formats(): array {
        return [
            'property_id'   => '%d',
            'room_unit_id'  => '%d',
            'sync_interval' => '%d',
            'is_active'     => '%d',
        ];
    }

    public function find_by_unit( int $unit_id ): array {
        return $this->db()->get_results( $this->db()->prepare(
            "SELECT * FROM {$this->table()} WHERE room_unit_id = %d ORDER BY created_at DESC",
            $unit_id
        ), \ARRAY_A ) ?: [];
    }

    public function find_by_export_token( string $token ): ?array {
        $row = $this->db()->get_row( $this->db()->prepare(
            "SELECT * FROM {$this->table()} WHERE export_token = %s",
            $token
        ), \ARRAY_A );
        return $row ?: null;
    }
}
