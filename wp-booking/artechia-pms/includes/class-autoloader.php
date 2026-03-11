<?php
/**
 * PSR-4 autoloader for the Artechia PMS namespace.
 *
 * Maps Artechia\PMS\… → includes/…
 *
 * Sub-namespaces (Admin, Front, Services, Repositories, DB, Helpers)
 * resolve automatically via namespace → directory conversion.
 */

namespace Artechia\PMS;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Autoloader {

    private static bool $registered = false;

    /**
     * Namespace → directory map (relative to plugin root).
     *
     * A single base mapping — sub-namespaces (Admin, Front, Services,
     * Repositories, DB, Helpers) resolve via the path conversion logic.
     */
    private static array $map = [
        'Artechia\\PMS\\'  => 'includes/',
    ];

    public static function register(): void {
        if ( self::$registered ) {
            return;
        }
        spl_autoload_register( [ __CLASS__, 'load' ] );
        self::$registered = true;
    }

    public static function load( string $class ): void {
        foreach ( self::$map as $prefix => $dir ) {
            if ( str_starts_with( $class, $prefix ) ) {
                $relative = substr( $class, strlen( $prefix ) );
                // Convert namespace separators and StudlyCaps to path.
                $parts    = explode( '\\', $relative );
                $filename = 'class-' . strtolower(
                    preg_replace( '/([a-z])([A-Z])/', '$1-$2', array_pop( $parts ) )
                ) . '.php';

                $subdir = '';
                if ( $parts ) {
                    $subdir = implode(
                        '/',
                        array_map(
                            fn( $p ) => strtolower( preg_replace( '/([a-z])([A-Z])/', '$1-$2', $p ) ),
                            $parts
                        )
                    ) . '/';
                }

                $file = ARTECHIA_PMS_DIR . $dir . $subdir . $filename;
                if ( file_exists( $file ) ) {
                    require_once $file;
                }
                return;
            }
        }
    }
}
