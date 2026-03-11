<?php
/**
 * Payment repository: queries on the payments table.
 */

namespace Artechia\PMS\Repositories;

use Artechia\PMS\DB\Schema;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PaymentRepository extends BaseRepository {

    protected function table_name(): string {
        return 'payments';
    }

    protected function fillable(): array {
        return [
            'booking_id', 'gateway', 'gateway_txn_id', 'intent_id',
            'amount', 'currency', 'pay_mode', 'type', 'status',
            'gateway_data', 'notes', 'idempotency_key', 'created_at',
        ];
    }

    protected function formats(): array {
        return [
            'booking_id' => '%d',
            'amount'     => '%f',
        ];
    }

    /* ── Finders ─────────────────────────────────────── */

    /**
     * Find payment by gateway + intent_id (preference_id).
     */
    public function find_by_intent_id( string $gateway, string $intent_id ): ?array {
        $row = $this->db()->get_row( $this->db()->prepare(
            "SELECT * FROM {$this->table()} WHERE gateway = %s AND intent_id = %s LIMIT 1",
            $gateway,
            $intent_id
        ), \ARRAY_A );
        return $row ?: null;
    }

    /**
     * Find payment by gateway + transaction ID (MP payment id).
     */
    public function find_by_gateway_txn( string $gateway, string $txn_id ): ?array {
        $row = $this->db()->get_row( $this->db()->prepare(
            "SELECT * FROM {$this->table()} WHERE gateway = %s AND gateway_txn_id = %s LIMIT 1",
            $gateway,
            $txn_id
        ), \ARRAY_A );
        return $row ?: null;
    }

    /**
     * Find payment by idempotency key.
     */
    public function find_by_idempotency_key( string $key ): ?array {
        $row = $this->db()->get_row( $this->db()->prepare(
            "SELECT * FROM {$this->table()} WHERE idempotency_key = %s LIMIT 1",
            $key
        ), \ARRAY_A );
        return $row ?: null;
    }

    /**
     * Sum of approved payments for a booking.
     */
    public function sum_paid( int $booking_id ): float {
        $total = $this->db()->get_var( $this->db()->prepare(
            "SELECT COALESCE(SUM(amount), 0) FROM {$this->table()}
             WHERE booking_id = %d AND status = 'approved'",
            $booking_id
        ) );
        return (float) $total;
    }

    /**
     * Get all payments for a booking.
     */
    public function get_for_booking( int $booking_id ): array {
        return $this->db()->get_results( $this->db()->prepare(
            "SELECT * FROM {$this->table()} WHERE booking_id = %d ORDER BY created_at DESC",
            $booking_id
        ), \ARRAY_A ) ?: [];
    }
    /**
     * Find a pending/created payment for a booking and pay_mode.
     * Used to preventing duplicate intents.
     */
    public function find_pending_payment( int $booking_id, string $pay_mode ): ?array {
        $row = $this->db()->get_row( $this->db()->prepare(
            "SELECT * FROM {$this->table()} 
             WHERE booking_id = %d 
               AND pay_mode = %s 
               AND status IN ('created', 'pending') 
             ORDER BY created_at DESC 
             LIMIT 1",
            $booking_id,
            $pay_mode
        ), \ARRAY_A );
        return $row ?: null;
    }
    /**
     * Find any pending/created payment for a booking (any pay_mode).
     */
    public function find_any_pending_payment( int $booking_id ): ?array {
        $row = $this->db()->get_row( $this->db()->prepare(
            "SELECT * FROM {$this->table()} 
             WHERE booking_id = %d 
               AND status IN ('created', 'pending') 
             ORDER BY created_at DESC 
             LIMIT 1",
            $booking_id
        ), \ARRAY_A );
        return $row ?: null;
    }
}
