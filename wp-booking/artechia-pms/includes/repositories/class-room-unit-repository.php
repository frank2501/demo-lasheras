<?php
namespace Artechia\PMS\Repositories;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class RoomUnitRepository extends BaseRepository {

    protected function table_name(): string {
        return 'room_units';
    }

    protected function fillable(): array {
        return [
            'room_type_id', 'property_id', 'name', 'notes',
            'status', 'housekeeping', 'sort_order',
        ];
    }

    protected function formats(): array {
        return [
            'room_type_id' => '%d',
            'property_id'  => '%d',
            'sort_order'   => '%d',
        ];
    }

    /**
     * Get units with their room type name.
     */
    public function all_with_type( array $args = [] ): array {
        $ru = $this->table();
        $rt = \Artechia\PMS\DB\Schema::table( 'room_types' );

        $where  = [ '1=1' ];
        $values = [];

        if ( ! empty( $args['where']['property_id'] ) ) {
            $where[]  = 'ru.property_id = %d';
            $values[] = (int) $args['where']['property_id'];
        }
        if ( ! empty( $args['where']['room_type_id'] ) ) {
            $where[]  = 'ru.room_type_id = %d';
            $values[] = (int) $args['where']['room_type_id'];
        }
        if ( ! empty( $args['where']['status'] ) ) {
            $where[]  = 'ru.status = %s';
            $values[] = $args['where']['status'];
        }
        if ( ! empty( $args['where']['housekeeping'] ) ) {
            $where[]  = 'ru.housekeeping = %s';
            $values[] = $args['where']['housekeeping'];
        }

        $where_sql = 'WHERE ' . implode( ' AND ', $where );
        $limit     = isset( $args['limit'] ) ? absint( $args['limit'] ) : 100;
        $offset    = isset( $args['offset'] ) ? absint( $args['offset'] ) : 0;

        $sql = "SELECT ru.*, rt.name as room_type_name
                FROM {$ru} ru
                LEFT JOIN {$rt} rt ON rt.id = ru.room_type_id
                {$where_sql}
                ORDER BY ru.sort_order ASC, ru.name ASC
                LIMIT %d OFFSET %d";
        $values[] = $limit;
        $values[] = $offset;

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        return $this->db()->get_results( $this->db()->prepare( $sql, $values ), \ARRAY_A ) ?: [];
    }

    /**
     * Find all units for a property (for calendar/tape chart).
     */
    public function find_all_by_property( int $property_id ): array {
        return $this->all_with_type( [
            'where' => [ 'property_id' => $property_id ],
            'limit' => 500, // Reasonable limit for a property
        ] );
    }
}
