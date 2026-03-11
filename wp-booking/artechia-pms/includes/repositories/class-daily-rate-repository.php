<?php
namespace Artechia\PMS\Repositories;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class DailyRateRepository extends BaseRepository {

    protected function table_name(): string {
        return 'daily_rates';
    }

    protected function fillable(): array {
        return [
            'room_type_id', 'rate_plan_id', 'rate_date', 'price_per_night',
            'extra_adult', 'extra_child', 'min_stay',
            'closed_to_arrival', 'closed_to_departure', 'available_units', 'stop_sell',
        ];
    }

    protected function formats(): array {
        return [
            'room_type_id'    => '%d',
            'rate_plan_id'    => '%d',
            'price_per_night' => '%f',
            'extra_adult'     => '%f',
            'extra_child'     => '%f',
            'min_stay'        => '%d',
            'closed_to_arrival'  => '%d',
            'closed_to_departure'=> '%d',
            'available_units' => '%d',
            'stop_sell'       => '%d',
        ];
    }

    /**
     * Get daily override for a specific date.
     */
    public function find_for_date( int $room_type_id, int $rate_plan_id, string $date ): ?array {
        $row = $this->db()->get_row( $this->db()->prepare(
            "SELECT * FROM {$this->table()}
             WHERE room_type_id = %d AND rate_plan_id = %d AND rate_date = %s",
            $room_type_id, $rate_plan_id, $date
        ), \ARRAY_A );
        return $row ?: null;
    }

    /**
     * Get overrides for a date range.
     */
    public function for_range( int $room_type_id, int $rate_plan_id, string $from, string $to ): array {
        return $this->db()->get_results( $this->db()->prepare(
            "SELECT * FROM {$this->table()}
             WHERE room_type_id = %d AND rate_plan_id = %d AND rate_date BETWEEN %s AND %s
             ORDER BY rate_date ASC",
            $room_type_id, $rate_plan_id, $from, $to
        ), \ARRAY_A ) ?: [];
    }

    /**
     * Bulk upsert daily rates for a date range.
     */
    public function bulk_upsert( int $room_type_id, int $rate_plan_id, string $from, string $to, array $values ): int {
        $current = new \DateTime( $from );
        $end     = new \DateTime( $to );
        $count   = 0;

        while ( $current <= $end ) {
            $date = $current->format( 'Y-m-d' );
            $data = array_merge( $values, [
                'room_type_id' => $room_type_id,
                'rate_plan_id' => $rate_plan_id,
                'rate_date'    => $date,
            ] );

            $existing = $this->find_for_date( $room_type_id, $rate_plan_id, $date );
            if ( $existing ) {
                $this->update( (int) $existing['id'], $data );
            } else {
                $this->create( $data );
            }

            $count++;
            $current->modify( '+1 day' );
        }

        return $count;
    }
}
