<?php
/**
 * Transient-based rate limiter for critical admin endpoints.
 */

namespace Artechia\PMS;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class RateLimiter {

    /**
     * Identify the current caller: user_id if logged-in, else IP + UA hash.
     */
    public static function caller_key(): string {
        $uid = get_current_user_id();
        if ( $uid ) {
            return 'u' . $uid;
        }

        $ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) );
        $ua = sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ?? '' ) );
        return 'anon_' . md5( $ip . '|' . $ua );
    }

    /**
     * Check if the action is within rate limit.
     *
     * Returns true if the action is ALLOWED. False if rate limited.
     *
     * @param string $action        Action name (e.g., 'move_booking'). Caller key is appended automatically.
     * @param int    $max           Maximum number of attempts allowed in the window.
     * @param int    $window_secs   Time window in seconds.
     * @return bool True if allowed, false if rate-limited.
     */
    public static function check( string $action, int $max = 10, int $window_secs = 60 ): bool {
        $transient_key = 'artechia_rl_' . md5( $action . '_' . self::caller_key() );
        $current       = (int) get_transient( $transient_key );

        if ( $current >= $max ) {
            return false; // Rate limited.
        }

        set_transient( $transient_key, $current + 1, $window_secs );
        return true;
    }

    /**
     * Return a standard rate-limited WP_REST_Response.
     *
     * @return \WP_REST_Response
     */
    public static function limited_response(): \WP_REST_Response {
        return new \WP_REST_Response( [
            'error'   => 'RATE_LIMITED',
            'message' => 'Too many requests. Please wait before retrying.',
        ], 429 );
    }
}
