<?php
namespace Artechia\PMS\Repositories;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class RoomTypeRepository extends BaseRepository {

    protected function table_name(): string {
        return 'room_types';
    }

    protected function fillable(): array {
        return [
            'property_id', 'name', 'slug', 'description',
            'max_adults', 'max_children', 'max_occupancy', 'base_occupancy',
            'amenities_json', 'photos_json',
            'sort_order', 'status',
        ];
    }

    protected function formats(): array {
        return [
            'property_id'   => '%d',
            'max_adults'    => '%d',
            'max_children'  => '%d',
            'max_occupancy' => '%d',
            'base_occupancy'=> '%d',
            'sort_order'    => '%d',
        ];
    }

    public function create( array $data ): int|false {
        if ( empty( $data['slug'] ) && ! empty( $data['name'] ) ) {
            $data['slug'] = \Artechia\PMS\Helpers\Helpers::generate_slug( $data['name'] );
        }
        return parent::create( $data );
    }

    /**
     * Get room types with their unit counts.
     */
    public function all_with_counts( int $property_id ): array {
        $rt = $this->table();
        $ru = \Artechia\PMS\DB\Schema::table( 'room_units' );

        return $this->db()->get_results( $this->db()->prepare(
            "SELECT rt.*, COUNT(ru.id) as unit_count
             FROM {$rt} rt
             LEFT JOIN {$ru} ru ON ru.room_type_id = rt.id
             WHERE rt.property_id = %d
             GROUP BY rt.id
             ORDER BY rt.sort_order ASC, rt.name ASC",
            $property_id
        ), \ARRAY_A ) ?: [];
    }
}
