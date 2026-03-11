<?php
namespace Artechia\PMS\Repositories;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class RatePlanRepository extends BaseRepository {

    protected function table_name(): string {
        return 'rate_plans';
    }

    protected function fillable(): array {
        return [
            'property_id', 'name', 'code', 'description',
            'date_from', 'date_to', 'min_stay', 'max_stay', 'is_annual', 'color',
            'is_refundable', 'cancellation_type', 'cancellation_deadline_days',
            'penalty_type', 'penalty_value', 'deposit_pct',
            'cancellation_policy_json', 'status',
        ];
    }

    protected function formats(): array {
        return [
            'property_id'                => '%d',
            'date_from'                  => '%s',
            'date_to'                    => '%s',
            'min_stay'                   => '%d',
            'max_stay'                   => '%d',
            'is_annual'                  => '%d',
            'color'                      => '%s',
            'cancellation_type'          => '%s',
            'cancellation_deadline_days' => '%d',
            'penalty_type'               => '%s',
            'is_refundable'              => '%d',
            'deposit_pct'                => '%d',
        ];
    }

    /**
     * Get the base rate plan for a property (annual / catch-all plan).
     * Prefers annual plans, falls back to the first active plan.
     */
    public function get_default( int $property_id ): ?array {
        // Prefer the annual (catch-all) plan
        $row = $this->db()->get_row( $this->db()->prepare(
            "SELECT * FROM {$this->table()}
             WHERE property_id = %d AND is_annual = 1 AND status = 'active'
             ORDER BY id ASC LIMIT 1",
            $property_id
        ), \ARRAY_A );

        if ( ! $row ) {
            // Fallback: first active plan
            $row = $this->db()->get_row( $this->db()->prepare(
                "SELECT * FROM {$this->table()} WHERE property_id = %d AND status = 'active' ORDER BY id ASC LIMIT 1",
                $property_id
            ), \ARRAY_A );
        }

        return $row ?: null;
    }

    /**
     * Find the active rate plan for a specific date, ordered by priority (highest first).
     * Falls back to the default rate plan if no specific dates match.
     */
    public function find_for_date( int $property_id, string $date ): ?array {
        $rpd_table = $this->db()->prefix . 'artechia_rate_plan_dates';
        $row = $this->db()->get_row( $this->db()->prepare(
            "SELECT rp.* FROM {$this->table()} rp
             LEFT JOIN {$rpd_table} rpd 
               ON rpd.rate_plan_id = rp.id AND rpd.date_from <= %s AND rpd.date_to >= %s
             WHERE rp.property_id = %d 
               AND rp.status = 'active'
               AND (rpd.id IS NOT NULL OR rp.is_annual = 1)
             ORDER BY 
                CASE WHEN rpd.id IS NOT NULL THEN 1 ELSE 0 END DESC,
                rp.id DESC
             LIMIT 1",
            $date, $date, $property_id
        ), \ARRAY_A );

        if ( $row ) {
            return $row;
        }

        return $this->get_default( $property_id );
    }

    public function find( int $id ): ?array {
        $row = parent::find( $id );
        if ( $row ) {
            $rpd_table = $this->db()->prefix . 'artechia_rate_plan_dates';
            $row['dates'] = $this->db()->get_results( $this->db()->prepare(
                "SELECT date_from, date_to FROM {$rpd_table} WHERE rate_plan_id = %d ORDER BY date_from ASC",
                $id
            ), \ARRAY_A );
        }
        return $row;
    }

    public function sync_dates( int $id, array $dates ): void {
        $rpd_table = $this->db()->prefix . 'artechia_rate_plan_dates';
        $this->db()->query( $this->db()->prepare( "DELETE FROM {$rpd_table} WHERE rate_plan_id = %d", $id ) );
        
        foreach ( $dates as $d ) {
            if ( ! empty( $d['date_from'] ) && ! empty( $d['date_to'] ) ) {
                $this->db()->insert( $rpd_table, [
                    'rate_plan_id' => $id,
                    'date_from'    => $d['date_from'],
                    'date_to'      => $d['date_to']
                ], [ '%d', '%s', '%s' ] );
            }
        }
    }

    public function delete( $id ): bool {
        $rpd_table = $this->db()->prefix . 'artechia_rate_plan_dates';
        $this->db()->query( $this->db()->prepare( "DELETE FROM {$rpd_table} WHERE rate_plan_id = %d", $id ) );
        return parent::delete( $id );
    }
}
