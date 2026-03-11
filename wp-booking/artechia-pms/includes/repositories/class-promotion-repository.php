<?php
namespace Artechia\PMS\Repositories;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class PromotionRepository extends BaseRepository {

    protected function table_name(): string {
        return 'promotions';
    }

    protected function fillable(): array {
        return [
            'name', 'rule_type', 'rule_value', 'min_nights',
            'starts_at', 'ends_at', 'property_id', 'room_type_ids', 'active', 'created_at'
        ];
    }

    protected function formats(): array {
        return [
            'name'          => '%s',
            'rule_type'     => '%s',
            'rule_value'    => '%s',
            'min_nights'    => '%d',
            'starts_at'             => '%s',
            'ends_at'               => '%s',
            'property_id'   => '%d',
            'room_type_ids' => '%s',
            'active'        => '%d',
            'created_at'    => '%s'
        ];
    }

    public function find_active_for_search( int $property_id, string $check_in, string $check_out ): array {
        global $wpdb;
        $table = $this->table();
        
        $query = "SELECT * FROM {$table} 
                 WHERE active = 1 
                 AND (property_id = %d OR property_id IS NULL)
                 AND (starts_at IS NULL OR starts_at <= %s)
                 AND (ends_at IS NULL OR ends_at >= %s)";

        return $wpdb->get_results( $wpdb->prepare( $query, $property_id, $check_out, $check_in ), \ARRAY_A ) ?: [];
    }
}
