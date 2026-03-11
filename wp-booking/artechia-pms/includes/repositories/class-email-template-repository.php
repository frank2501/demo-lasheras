<?php
namespace Artechia\PMS\Repositories;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class EmailTemplateRepository extends BaseRepository {

    protected function table_name(): string {
        return 'email_templates';
    }

    protected function fillable(): array {
        return [
            'property_id', 'event_type', 'subject', 'body_html', 
            'placeholders', 'is_active',
        ];
    }

    protected function formats(): array {
        return [
            'property_id' => '%d',
            'is_active'   => '%d',
        ];
    }

    /**
     * Find all templates.
     */
    public function find_all( array $args = [] ): array {
        $table = $this->table();
        $where = ['1=1'];
        $values = [];

        if ( isset( $args['where']['property_id'] ) ) {
            $prop_id = $args['where']['property_id'];
            if ( is_null( $prop_id ) || 0 === (int) $prop_id ) {
                $where[] = '(property_id IS NULL OR property_id = 0)';
            } else {
                $where[]  = 'property_id = %d';
                $values[] = (int) $prop_id;
            }
        }

        $where_sql = 'WHERE ' . implode( ' AND ', $where );
        
        $sql = "SELECT * FROM {$table} {$where_sql} ORDER BY event_type ASC";
        
        if ( ! empty( $values ) ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            return $this->db()->get_results( $this->db()->prepare( $sql, $values ), \ARRAY_A ) ?: [];
        }

        return $this->db()->get_results( $sql, \ARRAY_A ) ?: [];
    }

    /**
     * Get or create a template for an event type if it doesn't exist.
     */
    public function ensure_template( string $event_type, int $property_id = 0 ): ?array {
        $existing = $this->find_by_event( $event_type, $property_id );
        if ( $existing ) {
            return $existing;
        }

        // Defaults could be handled here or via a seeder.
        // For now we just return null or create a blank one? 
        // Better to let the seeder handle defaults.
        return null;
    }

    public function find_by_event( string $event_type, int $property_id = 0 ) {
        $table = $this->table();
        $sql = "SELECT * FROM {$table} WHERE event_type = %s AND ";
        $values = [ $event_type ];

        if ( $property_id > 0 ) {
            $sql .= "property_id = %d";
            $values[] = $property_id;
        } else {
            $sql .= "(property_id IS NULL OR property_id = 0)";
        }

        return $this->db()->get_row( $this->db()->prepare( $sql . " LIMIT 1", $values ), \ARRAY_A );
    }
}
