<?php
/**
 * Base repository: common CRUD operations over a custom DB table.
 */

namespace Artechia\PMS\Repositories;

use Artechia\PMS\DB\Schema;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

abstract class BaseRepository {

    /**
     * Table short name (e.g. 'properties').
     */
    abstract protected function table_name(): string;

    /**
     * Columns allowed for insert/update (white-list).
     */
    abstract protected function fillable(): array;

    /**
     * Column format map for $wpdb placeholders.
     * Default: '%s' for all. Override per column as needed.
     */
    protected function formats(): array {
        return [];
    }

    /* ── Helpers ─────────────────────────────────────── */

    public function table(): string {
        return Schema::table( $this->table_name() );
    }

    protected function db(): \wpdb {
        global $wpdb;
        return $wpdb;
    }

    /**
     * Get the format placeholder for a column.
     */
    protected function format_for( string $col ): ?string {
        $map = $this->formats();
        if ( array_key_exists( $col, $map ) ) {
            return $map[ $col ];
        }
        return '%s';
    }

    /**
     * Filter data to only fillable columns and build formats array.
     */
    protected function prepare_data( array $data ): array {
        $clean   = [];
        $formats = [];

        foreach ( $this->fillable() as $col ) {
            if ( array_key_exists( $col, $data ) ) {
                $clean[ $col ]  = $data[ $col ];
                $formats[]      = $this->format_for( $col );
            }
        }

        return [ $clean, $formats ];
    }

    /* ── CRUD ────────────────────────────────────────── */

    /**
     * Find a single row by ID.
     */
    public function find( int $id ): ?array {
        $row = $this->db()->get_row(
            $this->db()->prepare( "SELECT * FROM {$this->table()} WHERE id = %d", $id ),
            \ARRAY_A
        );
        return $row ?: null;
    }

    /**
     * Get all rows with optional filters.
     *
     * @param array $args {
     *     @type array  $where       Associative [ column => value ].
     *     @type string $orderby     Column name.
     *     @type string $order       ASC or DESC.
     *     @type int    $limit       Max rows.
     *     @type int    $offset      Offset.
     *     @type string $search      Search term (searches 'name' column by default).
     *     @type string $search_cols Comma-separated columns to search.
     * }
     */
    public function all( array $args = [] ): array {
        $where_clauses = [];
        $values        = [];

        // Simple equality filters.
        if ( ! empty( $args['where'] ) && is_array( $args['where'] ) ) {
            foreach ( $args['where'] as $col => $val ) {
                $col = sanitize_key( $col );
                if ( in_array( $col, $this->fillable(), true ) || $col === 'id' ) {
                    $where_clauses[] = "`{$col}` = " . $this->format_for( $col );
                    $values[]        = $val;
                }
            }
        }

        // Search.
        if ( ! empty( $args['search'] ) ) {
            $search_cols = ! empty( $args['search_cols'] )
                ? array_map( 'sanitize_key', explode( ',', $args['search_cols'] ) )
                : [ 'name' ];
            $like        = '%' . $this->db()->esc_like( $args['search'] ) . '%';
            $or_parts    = [];
            foreach ( $search_cols as $col ) {
                $or_parts[] = "`{$col}` LIKE %s";
                $values[]   = $like;
            }
            $where_clauses[] = '(' . implode( ' OR ', $or_parts ) . ')';
        }

        $where_sql = $where_clauses ? 'WHERE ' . implode( ' AND ', $where_clauses ) : '';

        // Order.
        $orderby = ! empty( $args['orderby'] ) ? sanitize_key( $args['orderby'] ) : 'id';
        $order   = ! empty( $args['order'] ) && strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';

        // Limit / offset.
        $limit  = isset( $args['limit'] ) ? absint( $args['limit'] ) : 50;
        $offset = isset( $args['offset'] ) ? absint( $args['offset'] ) : 0;

        $sql  = "SELECT * FROM {$this->table()} {$where_sql} ORDER BY `{$orderby}` {$order} LIMIT %d OFFSET %d";
        $values[] = $limit;
        $values[] = $offset;

        if ( $values ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            return $this->db()->get_results( $this->db()->prepare( $sql, $values ), \ARRAY_A ) ?: [];
        }

        return $this->db()->get_results( $sql, \ARRAY_A ) ?: [];
    }

    /**
     * Count rows with optional filters.
     */
    public function count( array $args = [] ): int {
        $where_clauses = [];
        $values        = [];

        if ( ! empty( $args['where'] ) && is_array( $args['where'] ) ) {
            foreach ( $args['where'] as $col => $val ) {
                $col = sanitize_key( $col );
                if ( in_array( $col, $this->fillable(), true ) || $col === 'id' ) {
                    $where_clauses[] = "`{$col}` = " . $this->format_for( $col );
                    $values[]        = $val;
                }
            }
        }

        if ( ! empty( $args['search'] ) ) {
            $search_cols = ! empty( $args['search_cols'] )
                ? array_map( 'sanitize_key', explode( ',', $args['search_cols'] ) )
                : [ 'name' ];
            $like     = '%' . $this->db()->esc_like( $args['search'] ) . '%';
            $or_parts = [];
            foreach ( $search_cols as $col ) {
                $or_parts[] = "`{$col}` LIKE %s";
                $values[]   = $like;
            }
            $where_clauses[] = '(' . implode( ' OR ', $or_parts ) . ')';
        }

        $where_sql = $where_clauses ? 'WHERE ' . implode( ' AND ', $where_clauses ) : '';
        $sql       = "SELECT COUNT(*) FROM {$this->table()} {$where_sql}";

        if ( $values ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            return (int) $this->db()->get_var( $this->db()->prepare( $sql, $values ) );
        }

        return (int) $this->db()->get_var( $sql );
    }

    /**
     * Insert a new row.
     *
     * @return int|false Inserted ID or false on failure.
     */
    public function create( array $data ): int|false {
        [ $clean, $formats ] = $this->prepare_data( $data );

        if ( empty( $clean ) ) {
            return false;
        }

        $result = $this->db()->insert( $this->table(), $clean, $formats );
        return $result ? (int) $this->db()->insert_id : false;
    }

    /**
     * Update an existing row.
     */
    public function update( int $id, array $data ): bool {
        [ $clean, $formats ] = $this->prepare_data( $data );

        if ( empty( $clean ) ) {
            return false;
        }

        $result = $this->db()->update(
            $this->table(),
            $clean,
            [ 'id' => $id ],
            $formats,
            [ '%d' ]
        );

        return $result !== false;
    }

    /**
     * Delete a row.
     */
    public function delete( int $id ): bool {
        return (bool) $this->db()->delete( $this->table(), [ 'id' => $id ], [ '%d' ] );
    }
}
