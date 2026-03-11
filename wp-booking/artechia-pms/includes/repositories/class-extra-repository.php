<?php
namespace Artechia\PMS\Repositories;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class ExtraRepository extends BaseRepository {

    protected function table_name(): string {
        return 'extras';
    }

    protected function fillable(): array {
        return [
            'property_id', 'name', 'description', 'price', 'price_type',
            'max_qty', 'is_mandatory', 'tax_included', 'status', 'sort_order',
        ];
    }

    protected function formats(): array {
        return [
            'property_id'  => '%d',
            'price'        => '%f',
            'max_qty'      => '%d',
            'is_mandatory' => '%d',
            'tax_included' => '%d',
            'sort_order'   => '%d',
        ];
    }

    /**
     * Find an extra by name for a property.
     */
    public function find_by_name( int $property_id, string $name ): ?array {
        return $this->db()->get_row( $this->db()->prepare(
            "SELECT * FROM {$this->table()} WHERE property_id = %d AND name = %s LIMIT 1",
            $property_id,
            $name
        ), \ARRAY_A ) ?: null;
    }

    /**
     * Get active extras for a property.
     */
    public function active_for_property( int $property_id ): array {
        return $this->db()->get_results( $this->db()->prepare(
            "SELECT * FROM {$this->table()}
             WHERE property_id = %d AND status = 'active'
             ORDER BY sort_order ASC, name ASC",
            $property_id
        ), \ARRAY_A ) ?: [];
    }
}
