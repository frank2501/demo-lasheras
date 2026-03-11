<?php
namespace Artechia\PMS\Repositories;

use Artechia\PMS\DB\Schema;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class RateRepository extends BaseRepository {

    protected function table_name(): string {
        return 'rates';
    }

    protected function fillable(): array {
        return [
            'room_type_id', 'rate_plan_id', 'price_per_night',
            'extra_adult', 'extra_child', 'single_use_discount',
            'min_stay', 'max_stay', 'closed_to_arrival', 'closed_to_departure',
        ];
    }

    protected function formats(): array {
        return [
            'room_type_id'       => '%d',
            'rate_plan_id'       => '%d',
            'price_per_night'    => '%f',
            'extra_adult'        => '%f',
            'extra_child'        => '%f',
            'single_use_discount'=> '%f',
            'min_stay'           => '%d',
            'max_stay'           => '%d',
            'closed_to_arrival'  => '%d',
            'closed_to_departure'=> '%d',
        ];
    }

    /**
     * Get the rate for a specific room type × rate plan.
     */
    public function find_rate( int $room_type_id, int $rate_plan_id ): ?array {
        return $this->db()->get_row( $this->db()->prepare(
            "SELECT * FROM {$this->table()}
             WHERE room_type_id = %d AND rate_plan_id = %d",
            $room_type_id, $rate_plan_id
        ), \ARRAY_A ) ?: null;
    }

    /**
     * Upsert a rate (insert or update).
     */
    public function upsert( array $data ): int|false {
        $existing = $this->find_rate(
            (int) $data['room_type_id'],
            (int) $data['rate_plan_id']
        );

        if ( $existing ) {
            $this->update( (int) $existing['id'], $data );
            return (int) $existing['id'];
        }

        return $this->create( $data );
    }

    /**
     * Build the rate matrix: rows = room types, columns = rate plans.
     */
    public function get_matrix( int $property_id, int $rate_plan_id ): array {
        $rt_table = Schema::table( 'room_types' );

        $room_types = $this->db()->get_results( $this->db()->prepare(
            "SELECT id, name FROM {$rt_table} WHERE property_id = %d AND status = 'active' ORDER BY sort_order ASC",
            $property_id
        ), \ARRAY_A ) ?: [];

        $rates = $this->db()->get_results( $this->db()->prepare(
            "SELECT * FROM {$this->table()} WHERE rate_plan_id = %d",
            $rate_plan_id
        ), \ARRAY_A ) ?: [];

        // Index by room_type_id
        $indexed = [];
        foreach ( $rates as $r ) {
            $indexed[ $r['room_type_id'] ] = $r;
        }

        return [
            'room_types' => $room_types,
            'rates'      => $indexed,
        ];
    }
}
