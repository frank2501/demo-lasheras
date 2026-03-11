<?php
/**
 * SelfCheck: in-memory test harness for the H2 engine.
 *
 * Runs 10 test cases against virtual data to validate
 * RateResolver, AvailabilityService, PricingService logic.
 * All data is created AND cleaned up within the test run.
 */
namespace Artechia\PMS\Services;

use Artechia\PMS\DB\Schema;
use Artechia\PMS\Repositories\{
    PropertyRepository, RoomTypeRepository, RoomUnitRepository,
    RatePlanRepository, RateRepository,
    BookingRepository, LockRepository, ExtraRepository
};

if ( ! defined( 'ABSPATH' ) ) { exit; }

class SelfCheck {

    /** IDs created during the test, for cleanup. */
    private array $cleanup = [];

    /**
     * Run all tests and return results.
     */
    public function run(): array {
        $results = [];
        try {
            $this->setup_fixtures();
            $results[] = $this->test_daily_override_price();
            $results[] = $this->test_partial_override();
            $results[] = $this->test_stop_sell_blocks();
            $results[] = $this->test_cta_blocks();
            $results[] = $this->test_ctd_blocks();
            $results[] = $this->test_min_stay_blocks();
            $results[] = $this->test_available_units_cap();
            $results[] = $this->test_locks_reduce_capacity();
            $results[] = $this->test_occupancy_pricing();
            $results[] = $this->test_single_use_discount();
        } catch ( \Throwable $e ) {
            $results[] = [
                'test'   => 'SETUP_ERROR',
                'pass'   => false,
                'error'  => $e->getMessage(),
            ];
        } finally {
            $this->teardown();
        }

        $passed = count( array_filter( $results, fn( $r ) => $r['pass'] ) );
        return [
            'passed' => $passed,
            'failed' => count( $results ) - $passed,
            'total'  => count( $results ),
            'tests'  => $results,
        ];
    }

    /* ── Fixtures ────────────────────────────────────── */

    private int $prop_id     = 0;
    private int $rt_id       = 0;
    private int $rp_id       = 0;
    private int $extra_id    = 0;
    private array $unit_ids  = [];

    private function setup_fixtures(): void {
        // Property.
        $prop_repo = new PropertyRepository();
        $this->prop_id = $prop_repo->create( [
            'name' => '__selfcheck_prop__',
            'slug' => '__selfcheck-prop-' . wp_rand(),
        ] );
        $this->cleanup['properties'][] = $this->prop_id;

        // Tax setting.
        Settings::set( 'tax_pct', '21', $this->prop_id );

        // Room type: base_occ=2, max_adults=4, max_children=2, max_occ=6
        $rt_repo = new RoomTypeRepository();
        $this->rt_id = $rt_repo->create( [
            'property_id'   => $this->prop_id,
            'name'          => '__selfcheck_rt__',
            'slug'          => '__selfcheck-rt-' . wp_rand(),
            'base_occupancy'=> 2,
            'max_adults'    => 4,
            'max_children'  => 2,
            'max_occupancy' => 6,
        ] );
        $this->cleanup['room_types'][] = $this->rt_id;

        // 3 room units.
        $ru_repo = new RoomUnitRepository();
        for ( $i = 1; $i <= 3; $i++ ) {
            $uid = $ru_repo->create( [
                'room_type_id' => $this->rt_id,
                'property_id'  => $this->prop_id,
                'name'         => "SC-$i",
            ] );
            $this->unit_ids[] = $uid;
            $this->cleanup['room_units'][] = $uid;
        }

        // Rate plan (default, 30% deposit, covering June 2026).
        $rp_repo = new RatePlanRepository();
        $this->rp_id = $rp_repo->create( [
            'property_id'  => $this->prop_id,
            'name'         => '__selfcheck_rp__',
            'is_annual'    => 1,
            'deposit_pct'  => 30,
            'date_from'   => '2026-06-01',
            'date_to'     => '2026-06-30',
            'priority'    => 10,
        ] );
        $this->cleanup['rate_plans'][] = $this->rp_id;

        // Base rate: 100/night, extra_adult=25, extra_child=15, discount=10%, min_stay=1.
        $r_repo = new RateRepository();
        $rate_id = $r_repo->create( [
            'room_type_id'       => $this->rt_id,
            'rate_plan_id'       => $this->rp_id,
            'price_per_night'    => 100.00,
            'extra_adult'        => 25.00,
            'extra_child'        => 15.00,
            'single_use_discount'=> 10.00,
            'min_stay'           => 1,
            'max_stay'           => 30,
            'closed_to_arrival'  => 0,
            'closed_to_departure'=> 0,
        ] );
        $this->cleanup['rates'][] = $rate_id;

        // Extra: per_night, 20/night, tax not included.
        $e_repo = new ExtraRepository();
        $this->extra_id = $e_repo->create( [
            'property_id'  => $this->prop_id,
            'name'         => '__selfcheck_extra__',
            'price'        => 20.00,
            'price_type'   => 'per_night',
            'is_mandatory' => 0,
            'tax_included' => 0,
        ] );
        $this->cleanup['extras'][] = $this->extra_id;
    }

    private function teardown(): void {
        global $wpdb;
        // Delete daily_rates created during tests.
        $dr_table = Schema::table( 'daily_rates' );
        $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$dr_table} WHERE room_type_id = %d AND rate_plan_id = %d",
            $this->rt_id, $this->rp_id
        ) );

        // Delete locks.
        $l_table = Schema::table( 'locks' );
        $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$l_table} WHERE room_type_id = %d", $this->rt_id
        ) );

        $tables = [ 'extras', 'rates', 'rate_plans', 'room_units', 'room_types', 'properties' ];
        foreach ( $tables as $t ) {
            if ( empty( $this->cleanup[ $t ] ) ) continue;
            $table = Schema::table( $t );
            foreach ( $this->cleanup[ $t ] as $id ) {
                $wpdb->delete( $table, [ 'id' => $id ], [ '%d' ] );
            }
        }

        // Clear tax setting.
        $s_table = Schema::table( 'settings' );
        $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$s_table} WHERE setting_key = 'tax_pct' AND property_id = %d",
            $this->prop_id
        ) );
    }

    /* ── Tests ──────────────────────────────────────── */

    /** 1) Daily override of price. */
    private function test_daily_override_price(): array {
        $this->insert_daily( '2026-06-10', [ 'price_per_night' => 150.00 ] );
        $resolver = new RateResolver();
        $rate = $resolver->resolve( $this->prop_id, $this->rt_id, $this->rp_id, '2026-06-10' );
        $pass = abs( $rate['price_per_night'] - 150.00 ) < 0.01;
        $this->delete_daily( '2026-06-10' );
        return [ 'test' => '1_daily_override_price', 'pass' => $pass, 'expected' => 150.00, 'actual' => $rate['price_per_night'] ];
    }

    /** 2) Partial override: NULL inherits base. */
    private function test_partial_override(): array {
        // Override only price, leave extra_adult NULL → should inherit 25.
        $this->insert_daily( '2026-06-11', [ 'price_per_night' => 120.00 ] );
        $resolver = new RateResolver();
        $rate = $resolver->resolve( $this->prop_id, $this->rt_id, $this->rp_id, '2026-06-11' );
        $pass = abs( $rate['price_per_night'] - 120.00 ) < 0.01
             && abs( $rate['extra_adult'] - 25.00 ) < 0.01;
        $this->delete_daily( '2026-06-11' );
        return [ 'test' => '2_partial_override', 'pass' => $pass, 'expected' => '120/25', 'actual' => $rate['price_per_night'] . '/' . $rate['extra_adult'] ];
    }

    /** 3) Stop sell blocks. */
    private function test_stop_sell_blocks(): array {
        $this->insert_daily( '2026-06-12', [ 'stop_sell' => 1 ] );
        $avail = new AvailabilityService();
        $result = $avail->check_room_type(
            $this->prop_id, $this->rt_id, $this->rp_id,
            '2026-06-12', '2026-06-13', 1
        );
        $pass = $result['available'] === 0 && $result['stop_sell'] === true;
        $this->delete_daily( '2026-06-12' );
        return [ 'test' => '3_stop_sell_blocks', 'pass' => $pass, 'available' => $result['available'], 'stop_sell' => $result['stop_sell'] ];
    }

    /** 4) CTA blocks arrival. */
    private function test_cta_blocks(): array {
        $this->insert_daily( '2026-06-14', [ 'closed_to_arrival' => 1 ] );
        $avail = new AvailabilityService();
        $result = $avail->check_room_type(
            $this->prop_id, $this->rt_id, $this->rp_id,
            '2026-06-14', '2026-06-16', 2
        );
        $pass = $result['cta_ok'] === false && $result['bookable'] === false;
        $this->delete_daily( '2026-06-14' );
        return [ 'test' => '4_cta_blocks', 'pass' => $pass, 'cta_ok' => $result['cta_ok'], 'bookable' => $result['bookable'] ];
    }

    /** 5) CTD blocks departure (last night). */
    private function test_ctd_blocks(): array {
        // Stay: 06-15 → 06-17 (2 nights). Last night = 06-16.
        $this->insert_daily( '2026-06-16', [ 'closed_to_departure' => 1 ] );
        $avail = new AvailabilityService();
        $result = $avail->check_room_type(
            $this->prop_id, $this->rt_id, $this->rp_id,
            '2026-06-15', '2026-06-17', 2
        );
        $pass = $result['ctd_ok'] === false && $result['bookable'] === false;
        $this->delete_daily( '2026-06-16' );
        return [ 'test' => '5_ctd_blocks', 'pass' => $pass, 'ctd_ok' => $result['ctd_ok'], 'bookable' => $result['bookable'] ];
    }

    /** 6) Min stay blocks short stays. */
    private function test_min_stay_blocks(): array {
        // Set min_stay=3 on check-in night.
        $this->insert_daily( '2026-06-18', [ 'min_stay' => 3 ] );
        $avail = new AvailabilityService();
        // 2-night stay should fail.
        $result = $avail->check_room_type(
            $this->prop_id, $this->rt_id, $this->rp_id,
            '2026-06-18', '2026-06-20', 2
        );
        $pass = $result['min_stay_ok'] === false && $result['bookable'] === false;
        $this->delete_daily( '2026-06-18' );
        return [ 'test' => '6_min_stay_blocks', 'pass' => $pass, 'min_stay_ok' => $result['min_stay_ok'], 'bookable' => $result['bookable'] ];
    }

    /** 7) Available units cap limits by date. */
    private function test_available_units_cap(): array {
        // We have 3 units. Set daily cap to 1 on one date.
        $this->insert_daily( '2026-06-20', [ 'available_units' => 1 ] );
        $avail = new AvailabilityService();
        $result = $avail->check_room_type(
            $this->prop_id, $this->rt_id, $this->rp_id,
            '2026-06-20', '2026-06-22', 2
        );
        $pass = $result['available'] <= 1;
        $this->delete_daily( '2026-06-20' );
        return [ 'test' => '7_available_units_cap', 'pass' => $pass, 'available' => $result['available'] ];
    }

    /** 8) Locks reduce capacity. */
    private function test_locks_reduce_capacity(): array {
        $lock_repo = new LockRepository();
        $key = $lock_repo->create_lock( [
            'property_id'  => $this->prop_id,
            'room_type_id' => $this->rt_id,
            'rate_plan_id' => $this->rp_id,
            'check_in'     => '2026-06-22',
            'check_out'    => '2026-06-24',
            'qty'          => 2,
            'expires_at'   => gmdate( 'Y-m-d H:i:s', time() + 3600 ),
        ] );

        $avail = new AvailabilityService();
        $result = $avail->check_room_type(
            $this->prop_id, $this->rt_id, $this->rp_id,
            '2026-06-22', '2026-06-24', 2
        );
        // 3 units - 0 booked - 2 locked = 1
        $pass = $result['available'] === 1 && $result['locked'] === 2;

        // Cleanup lock.
        if ( $key ) $lock_repo->delete_by_key( $key );

        return [ 'test' => '8_locks_reduce_capacity', 'pass' => $pass, 'available' => $result['available'], 'locked' => $result['locked'] ];
    }

    /** 9) Occupancy extra adult/child pricing. */
    private function test_occupancy_pricing(): array {
        $pricing = new PricingService();
        // 3 adults (1 extra), 1 child: occ_adj = 1×25 + 1×15 = 40
        $quote = $pricing->quote(
            $this->prop_id, $this->rt_id, $this->rp_id,
            '2026-06-24', '2026-06-25', 3, 1
        );

        if ( isset( $quote['error'] ) ) {
            return [ 'test' => '9_occupancy_pricing', 'pass' => false, 'error' => $quote['error'] ];
        }

        $night = $quote['nights'][0] ?? [];
        $pass = abs( ( $night['occ_adj'] ?? 0 ) - 40.00 ) < 0.01;
        return [ 'test' => '9_occupancy_pricing', 'pass' => $pass, 'expected_occ_adj' => 40.00, 'actual' => $night['occ_adj'] ?? 'N/A' ];
    }

    /** 10) Single-use discount applies once. */
    private function test_single_use_discount(): array {
        $pricing = new PricingService();
        // 2-night stay, 2 adults (base). discount = 10% of 100 = 10 on first night only.
        $quote = $pricing->quote(
            $this->prop_id, $this->rt_id, $this->rp_id,
            '2026-06-25', '2026-06-27', 2, 0
        );

        if ( isset( $quote['error'] ) ) {
            return [ 'test' => '10_single_use_discount', 'pass' => false, 'error' => $quote['error'] ];
        }

        $n1 = $quote['nights'][0] ?? [];
        $n2 = $quote['nights'][1] ?? [];
        $pass = abs( ( $n1['single_use'] ?? 0 ) - (-10.00) ) < 0.01
             && abs( ( $n2['single_use'] ?? 0 ) - 0.00 ) < 0.01;
        return [
            'test'    => '10_single_use_discount',
            'pass'    => $pass,
            'night_1' => $n1['single_use'] ?? 'N/A',
            'night_2' => $n2['single_use'] ?? 'N/A',
        ];
    }

    /* ── Daily rate helpers ──────────────────────────── */

    private function insert_daily( string $date, array $overrides ): void {
        global $wpdb;
        $table = Schema::table( 'daily_rates' );
        $data = array_merge( [
            'room_type_id'  => $this->rt_id,
            'rate_plan_id'  => $this->rp_id,
            'rate_date'     => $date,
        ], $overrides );
        $wpdb->insert( $table, $data );
    }

    private function delete_daily( string $date ): void {
        global $wpdb;
        $table = Schema::table( 'daily_rates' );
        $wpdb->delete( $table, [
            'room_type_id' => $this->rt_id,
            'rate_plan_id' => $this->rp_id,
            'rate_date'    => $date,
        ] );
    }
}
