<?php
/**
 * Logger: structured audit log + debug output.
 */

namespace Artechia\PMS;

use Artechia\PMS\DB\Schema;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Logger {

    public const INFO     = 'info';
    public const WARNING  = 'warning';
    public const ERROR    = 'error';
    public const CRITICAL = 'critical';

    /**
     * Log an event to the audit_log table.
     */
    public static function log(
        string $event_type,
        string $message,
        string $severity = self::INFO,
        $entity_type = '',
        ?int $entity_id = null,
        ?int $user_id = null,
        array $context = []
    ): int {
        global $wpdb;

        // Defensive: If $entity_type is passed as an array (common mistake), 
        // treat it as context and reset entity_type.
        if ( is_array( $entity_type ) ) {
            $context     = $entity_type;
            $entity_type = 'unknown';
        }

        if ( $user_id === null ) {
            $user_id = get_current_user_id();
        }

        $ip = '';
        if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
            $ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
        }

        $wpdb->insert(
            Schema::table( 'audit_log' ),
            [
                'event_type'   => sanitize_key( $event_type ),
                'severity'     => $severity,
                'entity_type'  => sanitize_key( $entity_type ),
                'entity_id'    => $entity_id,
                'user_id'      => $user_id,
                'ip_address'   => $ip,
                'message'      => sanitize_text_field( $message ),
                'context_json' => ! empty( $context ) ? wp_json_encode( $context ) : null,
            ],
            [ '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s' ]
        );

        // Also log to WP debug if debug mode is active.
        if ( self::is_debug() ) {
            error_log( sprintf(
                '[Artechia PMS][%s] %s: %s | entity=%s:%d | user=%d',
                strtoupper( $severity ),
                $event_type,
                $message,
                $entity_type,
                $entity_id ?? 0,
                $user_id
            ) );
        }

        return (int) $wpdb->insert_id;
    }

    public static function info( string $event, string $msg, $entity_type = '', ?int $entity_id = null, array $ctx = [] ): int {
        return self::log( $event, $msg, self::INFO, $entity_type, $entity_id, null, $ctx );
    }

    public static function warning( string $event, string $msg, $entity_type = '', ?int $entity_id = null, array $ctx = [] ): int {
        return self::log( $event, $msg, self::WARNING, $entity_type, $entity_id, null, $ctx );
    }

    public static function error( string $event, string $msg, $entity_type = '', ?int $entity_id = null, array $ctx = [] ): int {
        return self::log( $event, $msg, self::ERROR, $entity_type, $entity_id, null, $ctx );
    }

    public static function critical( string $event, string $msg, $entity_type = '', ?int $entity_id = null, array $ctx = [] ): int {
        return self::log( $event, $msg, self::CRITICAL, $entity_type, $entity_id, null, $ctx );
    }

    /**
     * Log a state change with before/after JSON snapshots.
     *
     * @param string      $action      Short action key, e.g. 'booking.move', 'coupon.redeem'
     * @param string      $message     Human-readable description
     * @param string      $entity_type Entity type, e.g. 'booking', 'coupon'
     * @param int|null    $entity_id   Entity ID
     * @param array|null  $before      State before the change
     * @param array|null  $after       State after the change
     * @param array       $context     Extra context data
     */
    public static function logChange(
        string $action,
        string $message,
        string $entity_type = '',
        ?int $entity_id = null,
        ?array $before = null,
        ?array $after = null,
        array $context = []
    ): int {
        global $wpdb;

        $user_id = get_current_user_id();
        $ip = '';
        if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
            $ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
        }

        $wpdb->insert(
            Schema::table( 'audit_log' ),
            [
                'event_type'   => 'change',
                'severity'     => self::INFO,
                'entity_type'  => sanitize_key( $entity_type ),
                'entity_id'    => $entity_id,
                'user_id'      => $user_id,
                'ip_address'   => $ip,
                'message'      => sanitize_text_field( $message ),
                'context_json' => ! empty( $context ) ? wp_json_encode( $context ) : null,
                'action'       => sanitize_key( $action ),
                'before_json'  => $before !== null ? wp_json_encode( $before ) : null,
                'after_json'   => $after !== null ? wp_json_encode( $after ) : null,
            ],
            [ '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s' ]
        );

        if ( self::is_debug() ) {
            error_log( sprintf(
                '[Artechia PMS][CHANGE] %s: %s | entity=%s:%d',
                $action,
                $message,
                $entity_type,
                $entity_id ?? 0
            ) );
        }

        return (int) $wpdb->insert_id;
    }

    /**
     * Whether debug logging is enabled in plugin settings.
     */
    private static function is_debug(): bool {
        return (bool) get_option( 'artechia_pms_debug_mode', false );
    }

    /**
     * Get log entries with optional filters.
     *
     * @param array $args Filters: severity, event_type, entity_type, entity_id, limit, offset, date_from, date_to
     * @return array
     */
    public static function query( array $args = [] ): array {
        global $wpdb;
        $table = Schema::table( 'audit_log' );

        $where  = [];
        $values = [];

        if ( ! empty( $args['severity'] ) ) {
            $where[]  = 'severity = %s';
            $values[] = $args['severity'];
        }
        if ( ! empty( $args['event_type'] ) ) {
            $where[]  = 'event_type = %s';
            $values[] = $args['event_type'];
        }
        if ( ! empty( $args['entity_type'] ) ) {
            $where[]  = 'entity_type = %s';
            $values[] = $args['entity_type'];
        }
        if ( ! empty( $args['entity_id'] ) ) {
            $where[]  = 'entity_id = %d';
            $values[] = (int) $args['entity_id'];
        }
        if ( ! empty( $args['date_from'] ) ) {
            $where[]  = 'created_at >= %s';
            $values[] = $args['date_from'];
        }
        if ( ! empty( $args['date_to'] ) ) {
            $where[]  = 'created_at <= %s';
            $values[] = $args['date_to'];
        }
        if ( ! empty( $args['search'] ) ) {
            $where[]  = 'message LIKE %s';
            $values[] = '%' . $wpdb->esc_like( $args['search'] ) . '%';
        }

        $where_sql = $where ? 'WHERE ' . implode( ' AND ', $where ) : '';
        $limit     = isset( $args['limit'] ) ? absint( $args['limit'] ) : 50;
        $offset    = isset( $args['offset'] ) ? absint( $args['offset'] ) : 0;

        $sql = "SELECT * FROM {$table} {$where_sql} ORDER BY created_at DESC LIMIT %d OFFSET %d";
        $values[] = $limit;
        $values[] = $offset;

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        return $wpdb->get_results( $wpdb->prepare( $sql, $values ), \ARRAY_A ) ?: [];
    }

    /**
     * Count log entries with optional filters.
     */
    public static function count( array $args = [] ): int {
        global $wpdb;
        $table = Schema::table( 'audit_log' );

        $where  = [];
        $values = [];

        if ( ! empty( $args['severity'] ) ) {
            $where[]  = 'severity = %s';
            $values[] = $args['severity'];
        }
        if ( ! empty( $args['event_type'] ) ) {
            $where[]  = 'event_type = %s';
            $values[] = $args['event_type'];
        }
        if ( ! empty( $args['entity_type'] ) ) {
            $where[]  = 'entity_type = %s';
            $values[] = $args['entity_type'];
        }
        if ( ! empty( $args['date_from'] ) ) {
            $where[]  = 'created_at >= %s';
            $values[] = $args['date_from'];
        }
        if ( ! empty( $args['date_to'] ) ) {
            $where[]  = 'created_at <= %s';
            $values[] = $args['date_to'];
        }
        if ( ! empty( $args['search'] ) ) {
            $where[]  = 'message LIKE %s';
            $values[] = '%' . $wpdb->esc_like( $args['search'] ) . '%';
        }

        $where_sql = $where ? 'WHERE ' . implode( ' AND ', $where ) : '';

        if ( $values ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} {$where_sql}", $values ) );
        }
        return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore
    }
}
