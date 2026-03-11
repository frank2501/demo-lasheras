<?php
/**
 * Helper utilities: formatting, slug generation, sanitization.
 */

namespace Artechia\PMS\Helpers;

use Artechia\PMS\Services\Settings;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Helpers {

    /**
     * Format a price amount according to plugin settings.
     */
    public static function format_price( float $amount, ?string $symbol = null ): string {
        $sym    = $symbol ?? Settings::currency_symbol();
        $dec    = (int) Settings::get( 'decimals', '2' );
        $dec_s  = Settings::get( 'decimal_separator', ',' );
        $tho_s  = Settings::get( 'thousand_separator', '.' );
        $pos    = Settings::get( 'currency_position', 'before' );

        $formatted = number_format( $amount, $dec, $dec_s, $tho_s );

        return $pos === 'before'
            ? $sym . $formatted
            : $formatted . $sym;
    }

    /**
     * Sanitize a money input string into a float.
     * Uses settings to determine decimal separator.
     */
    public static function sanitize_money( $amount ): float {
        if ( is_float( $amount ) || is_int( $amount ) ) {
            return (float) $amount;
        }
        $amount = (string) $amount;
        $amount = trim( $amount );
        
        if ( '' === $amount ) {
            return 0.0;
        }

        $dec_s = Settings::get( 'decimal_separator', ',' );
        
        if ( ',' === $dec_s ) {
            // European/Latin format: 1.000,00 -> 1000.00
            $amount = str_replace( '.', '', $amount ); // Remove thousands
            $amount = str_replace( ',', '.', $amount ); // Convert decimal
        } else {
            // US format: 1,000.00 -> 1000.00
            $amount = str_replace( ',', '', $amount );
        }
        
        return (float) $amount;
    }

    /**
     * Format a date string according to plugin settings.
     */
    public static function format_date( string $date, ?string $format = null ): string {
        $fmt = $format ?? Settings::get( 'date_format', 'd/m/Y' );
        $ts  = strtotime( $date );
        return $ts ? date( $fmt, $ts ) : $date;
    }

    /**
     * Generate a URL-friendly slug from a name.
     */
    public static function generate_slug( string $name ): string {
        if ( function_exists( 'sanitize_title' ) ) {
            return sanitize_title( $name );
        }
        $slug = strtolower( trim( $name ) );
        $slug = preg_replace( '/[^a-z0-9\-]/', '-', $slug );
        $slug = preg_replace( '/-+/', '-', $slug );
        return trim( $slug, '-' );
    }

    /**
     * Sanitize a JSON field input (ensures it's a valid JSON string).
     */
    public static function sanitize_json( mixed $input ): string {
        if ( is_string( $input ) ) {
            $decoded = json_decode( $input, true );
            if ( json_last_error() === JSON_ERROR_NONE ) {
                return wp_json_encode( $decoded );
            }
            return '[]';
        }
        if ( is_array( $input ) || is_object( $input ) ) {
            return wp_json_encode( $input );
        }
        return '[]';
    }

    /**
     * Generate a unique booking code.
     * Format: PREFIX-YYMMDD-XXXX (4 random alphanumeric chars).
     */
    public static function generate_booking_code( ?string $prefix = null ): string {
        $pfx  = $prefix ?? Settings::get( 'booking_code_prefix', 'ART' );
        $date = current_time( 'ymd' );
        $rand = strtoupper( substr( bin2hex( random_bytes( 2 ) ), 0, 4 ) );
        return "{$pfx}{$date}{$rand}";
    }

    /**
     * Generate a secure random token (for booking access).
     */
    public static function generate_token( int $length = 32 ): string {
        return bin2hex( random_bytes( $length ) );
    }

    /**
     * Calculate number of nights between two dates.
     */
    public static function nights( string $check_in, string $check_out ): int {
        $d1 = new \DateTime( $check_in );
        $d2 = new \DateTime( $check_out );
        $diff = $d1->diff( $d2 );
        return max( 1, $diff->days );
    }

    /**
     * Build a WhatsApp URL with pre-filled message.
     */
    public static function whatsapp_url( string $booking_code = '' ): string {
        $phone = Settings::get( 'whatsapp_number', '' );
        if ( ! $phone ) {
            return '';
        }
        $msg = Settings::get( 'whatsapp_message', '' );
        $msg = str_replace( '{booking_code}', $booking_code, $msg );
        return 'https://wa.me/' . preg_replace( '/[^0-9]/', '', $phone ) . '?text=' . rawurlencode( $msg );
    }
}
