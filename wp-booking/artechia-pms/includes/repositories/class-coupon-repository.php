<?php
/**
 * CouponRepository: CRUD for promotional offers.
 */
namespace Artechia\PMS\Repositories;

use Artechia\PMS\DB\Schema;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class CouponRepository extends BaseRepository {

    protected function table_name(): string {
        return 'coupons';
    }

    protected function fillable(): array {
        return [
            'code', 'type', 'value', 'starts_at', 'ends_at', 'min_nights',
            'room_type_ids', 'rate_plan_ids', 'usage_limit_total',
            'usage_limit_per_email', 'stackable', 'applies_to', 'active', 'is_automatic', 'created_at'
        ];
    }

    protected function formats(): array {
        return [
            'code'                  => '%s',
            'type'                  => '%s',
            'value'                 => '%f',
            'starts_at'             => '%s',
            'ends_at'               => '%s',
            'min_nights'            => '%d',
            'room_type_ids'         => '%s',
            'rate_plan_ids'         => '%s',
            'usage_limit_total'     => '%d',
            'usage_limit_per_email' => '%d',
            'stackable'             => '%d',
            'applies_to'            => '%s',
            'active'                => '%d',
            'is_automatic'          => '%d',
            'created_at'            => '%s'
        ];
    }

    /**
     * Find an active coupon by its code (case-insensitive).
     */
    public function find_by_code( string $code ): ?array {
        global $wpdb;
        $table = $this->table();
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE UPPER(code) = UPPER(%s) AND active = 1",
            $code
        ), \ARRAY_A );
    }

    /**
     * Count redemptions for a specific email.
     */
    public function get_usage_count_by_email( int $coupon_id, string $email ): int {
        global $wpdb;
        $table = Schema::table( 'coupon_redemptions' );
        return (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE coupon_id = %d AND email = %s",
            $coupon_id, $email
        ) );
    }

    /**
     * Count total redemptions for a coupon.
     */
    public function get_total_usage_count( int $coupon_id ): int {
        global $wpdb;
        $table = Schema::table( 'coupon_redemptions' );
        return (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE coupon_id = %d",
            $coupon_id
        ) );
    }

    /**
     * Record a successful redemption with atomic limit validation.
     */
    public function redeem( int $coupon_id, int $booking_id, string $email, float $amount ): array {
        global $wpdb;
        $coupons_table     = $this->table();
        $redemptions_table = Schema::table( 'coupon_redemptions' );

        $wpdb->query( 'START TRANSACTION' );

        // Lock the coupon row to prevent concurrent redemptions from bypassing limits
        $coupon = $wpdb->get_row( $wpdb->prepare(
            "SELECT id, usage_limit_total, usage_limit_per_email FROM {$coupons_table} WHERE id = %d FOR UPDATE",
            $coupon_id
        ), \ARRAY_A );

        if ( ! $coupon ) {
            $wpdb->query( 'ROLLBACK' );
            return [ 'error' => 'NOT_FOUND' ];
        }

        // 1. Check Total Limit
        if ( $coupon['usage_limit_total'] ) {
            $total_used = $this->get_total_usage_count( $coupon_id );
            if ( $total_used >= $coupon['usage_limit_total'] ) {
                $wpdb->query( 'ROLLBACK' );
                return [ 'error' => 'LIMIT_REACHED' ];
            }
        }

        // 2. Check Per Email Limit
        if ( $coupon['usage_limit_per_email'] && $email ) {
            $user_used = $this->get_usage_count_by_email( $coupon_id, $email );
            if ( $user_used >= $coupon['usage_limit_per_email'] ) {
                $wpdb->query( 'ROLLBACK' );
                return [ 'error' => 'USER_LIMIT_REACHED' ];
            }
        }

        // 3. Prevent duplicate redemption for same booking (Idempotency)
        $exists = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$redemptions_table} WHERE booking_id = %d AND coupon_id = %d",
            $booking_id, $coupon_id
        ) );
        if ( $exists ) {
            $wpdb->query( 'ROLLBACK' );
            return [ 'success' => true, 'already' => true ];
        }

        $success = $wpdb->insert( $redemptions_table, [
            'coupon_id'       => $coupon_id,
            'booking_id'      => $booking_id,
            'email'           => $email,
            'amount_discount' => $amount,
            'created_at'      => \current_time( 'mysql' )
        ] );

        if ( $success ) {
            $wpdb->query( 'COMMIT' );
            return [ 'success' => true ];
        } else {
            $wpdb->query( 'ROLLBACK' );
            return [ 'error' => 'DB_ERROR' ];
        }
    }

    /**
     * Find all active automatic coupons.
     */
    public function find_automatic(): array {
        global $wpdb;
        $table = $this->table();
        $now   = \current_time( 'mysql' );

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$table} 
             WHERE active = 1 AND is_automatic = 1
             AND (starts_at IS NULL OR starts_at <= %s)
             AND (ends_at IS NULL OR ends_at >= %s)
             ORDER BY value DESC",
            $now, $now
        ), \ARRAY_A ) ?: [];
    }
}
