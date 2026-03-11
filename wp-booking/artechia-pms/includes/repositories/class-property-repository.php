<?php
namespace Artechia\PMS\Repositories;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class PropertyRepository extends BaseRepository {

    protected function table_name(): string {
        return 'properties';
    }

    protected function fillable(): array {
        return [
            'name', 'slug', 'description', 'address', 'timezone', 'currency',
            'settings_json', 'is_demo', 'status',
        ];
    }

    protected function formats(): array {
        return [ 'status' => '%s', 'country' => '%s', 'is_demo' => '%d' ];
    }

    /**
     * Get the active property (first active or first overall).
     */
    public function get_default(): ?array {
        $row = $this->db()->get_row(
            "SELECT * FROM {$this->table()} WHERE status = 'active' ORDER BY id ASC LIMIT 1",
            \ARRAY_A
        );
        return $row ?: $this->db()->get_row( "SELECT * FROM {$this->table()} ORDER BY id ASC LIMIT 1", \ARRAY_A );
    }

    /**
     * Ensure slug is unique before insert/update.
     */
    public function create( array $data ): int|false {
        if ( empty( $data['slug'] ) && ! empty( $data['name'] ) ) {
            $data['slug'] = \Artechia\PMS\Helpers\Helpers::generate_slug( $data['name'] );
        }
        $data['slug'] = $this->unique_slug( $data['slug'] );
        return parent::create( $data );
    }

    public function update( int $id, array $data ): bool {
        if ( isset( $data['name'] ) && empty( $data['slug'] ) ) {
            $data['slug'] = \Artechia\PMS\Helpers\Helpers::generate_slug( $data['name'] );
        }
        if ( isset( $data['slug'] ) ) {
            $data['slug'] = $this->unique_slug( $data['slug'], $id );
        }
        return parent::update( $id, $data );
    }

    private function unique_slug( string $slug, ?int $exclude_id = null ): string {
        $original = $slug;
        $counter  = 1;

        while ( true ) {
            $sql = $this->db()->prepare(
                "SELECT COUNT(*) FROM {$this->table()} WHERE slug = %s" .
                ( $exclude_id ? $this->db()->prepare( ' AND id != %d', $exclude_id ) : '' ),
                $slug
            );
            if ( ! (int) $this->db()->get_var( $sql ) ) {
                return $slug;
            }
            $slug = $original . '-' . ( ++$counter );
        }
    }
}
