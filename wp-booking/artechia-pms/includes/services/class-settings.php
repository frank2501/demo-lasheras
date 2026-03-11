<?php
/**
 * Settings service: read/write from the artechia_settings table.
 */

namespace Artechia\PMS\Services;

use Artechia\PMS\DB\Schema;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Settings {

    /**
     * In-memory cache per request.
     *
     * @var array<string, array<string, string>>
     */
    private static array $cache = [];

    /**
     * Get a setting value.
     *
     * @param string $key         Setting key.
     * @param mixed  $default     Default value if not found.
     * @param int    $property_id Property ID (0 = global).
     */
    public static function get( string $key, mixed $default = '', int $property_id = 0 ): mixed {
        $cache_key = $property_id . ':' . $key;

        if ( isset( self::$cache[ $cache_key ] ) ) {
            return self::$cache[ $cache_key ];
        }

        global $wpdb;
        $table = Schema::table( 'settings' );

        $value = $wpdb->get_var( $wpdb->prepare(
            "SELECT setting_value FROM {$table} WHERE property_id = %d AND setting_key = %s LIMIT 1",
            $property_id,
            $key
        ) );

        if ( $value === null && $property_id > 0 ) {
            // Fallback to global setting.
            $value = $wpdb->get_var( $wpdb->prepare(
                "SELECT setting_value FROM {$table} WHERE property_id = 0 AND setting_key = %s LIMIT 1",
                $key
            ) );
        }

        $result = $value !== null ? $value : $default;
        self::$cache[ $cache_key ] = $result;

        return $result;
    }

    /**
     * Set a setting value (upsert).
     */
    public static function set( string $key, mixed $value, int $property_id = 0 ): void {
        global $wpdb;
        $table     = Schema::table( 'settings' );
        $str_value = is_array( $value ) || is_object( $value ) ? wp_json_encode( $value ) : (string) $value;

        $exists = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE property_id = %d AND setting_key = %s",
            $property_id,
            $key
        ) );

        if ( $exists ) {
            $wpdb->update(
                $table,
                [ 'setting_value' => $str_value ],
                [ 'property_id' => $property_id, 'setting_key' => $key ],
                [ '%s' ],
                [ '%d', '%s' ]
            );
        } else {
            $wpdb->insert( $table, [
                'property_id'   => $property_id,
                'setting_key'   => $key,
                'setting_value' => $str_value,
            ], [ '%d', '%s', '%s' ] );
        }

        // Bust cache.
        $cache_key = $property_id . ':' . $key;
        self::$cache[ $cache_key ] = $str_value;
    }

    /**
     * Delete a setting.
     */
    public static function delete( string $key, int $property_id = 0 ): void {
        global $wpdb;
        $table = Schema::table( 'settings' );

        $wpdb->delete( $table, [
            'property_id' => $property_id,
            'setting_key' => $key,
        ], [ '%d', '%s' ] );

        unset( self::$cache[ $property_id . ':' . $key ] );
    }

    /**
     * Get all settings for a property (merged with globals).
     */
    public static function all( int $property_id = 0 ): array {
        global $wpdb;
        $table = Schema::table( 'settings' );

        // Get global settings.
        $globals = $wpdb->get_results(
            "SELECT setting_key, setting_value FROM {$table} WHERE property_id = 0",
            \ARRAY_A
        ) ?: [];

        $result = [];
        foreach ( $globals as $row ) {
            $result[ $row['setting_key'] ] = $row['setting_value'];
        }

        // Override with property-specific settings.
        if ( $property_id > 0 ) {
            $props = $wpdb->get_results( $wpdb->prepare(
                "SELECT setting_key, setting_value FROM {$table} WHERE property_id = %d",
                $property_id
            ), \ARRAY_A ) ?: [];

            foreach ( $props as $row ) {
                $result[ $row['setting_key'] ] = $row['setting_value'];
            }
        }

        return $result;
    }

    /**
     * Clear in-memory cache.
     */
    public static function flush_cache(): void {
        self::$cache = [];
    }

    /**
     * Quick accessors for common settings.
     */
    public static function currency(): string {
        return self::get( 'currency', 'ARS' );
    }

    public static function currency_symbol(): string {
        return self::get( 'currency_symbol', '$' );
    }

    public static function timezone(): string {
        return self::get( 'timezone', 'America/Argentina/Buenos_Aires' );
    }

    public static function tax_rate(): float {
        return (float) self::get( 'tax_rate', '21' );
    }

    public static function tax_included(): bool {
        return (bool) self::get( 'tax_included', '1' );
    }

    public static function check_in_time(): string {
        return self::get( 'check_in_time', '14:00' );
    }

    public static function check_out_time(): string {
        return self::get( 'check_out_time', '10:00' );
    }

    public static function debug_mode(): bool {
        return (bool) self::get( 'debug_mode', '0' );
    }

    /**
     * Finds the first page ID that contains a specific shortcode.
     * Use sparingly as it performs a LIKE query on wp_posts.
     */
    public static function find_page_id_by_shortcode( string $shortcode ): int {
        global $wpdb;
        $id = $wpdb->get_var( $wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} WHERE post_content LIKE %s AND post_status = 'publish' AND post_type = 'page' LIMIT 1",
            '%' . $shortcode . '%'
        ) );
        return (int) ( $id ?? 0 );
    }
}
