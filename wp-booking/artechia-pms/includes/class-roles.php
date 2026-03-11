<?php
/**
 * Roles and capabilities management for Artechia PMS.
 */

namespace Artechia\PMS;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Roles {

    /**
     * All plugin capabilities.
     */
    public const CAPS = [
        // Full admin.
        'manage_artechia_settings'      => true,
        'manage_artechia_properties'    => true,
        'manage_artechia_delete_data'   => true,

        // Hotel manager.
        'artechia_manage_rooms'         => true,
        'artechia_manage_rates'         => true,
        'artechia_manage_extras'        => true,
        'artechia_manage_coupons'       => true,
        'artechia_manage_promotions'    => true,
        'artechia_manage_email_tpl'     => true,
        'artechia_manage_ical'          => true,
        'artechia_view_reports'         => true,
        'artechia_view_logs'            => true,

        // Receptionist.
        'artechia_manage_bookings'      => true,
        'artechia_manage_guests'        => true,
        'artechia_view_calendar'        => true,
        'artechia_manage_checkin'       => true,
        'artechia_manage_payments'      => true,

        // Housekeeping.
        'artechia_manage_housekeeping'  => true,
        'artechia_view_rooms'           => true,
    ];

    /**
     * Role definitions with their capabilities.
     */
    private static function role_definitions(): array {
        $all_caps = array_keys( self::CAPS );

        return [
            'artechia_manager' => [
                'label' => __( 'Hotel Manager', 'artechia-pms' ),
                'caps'  => array_diff( $all_caps, [
                    'manage_artechia_delete_data',
                ] ),
            ],
            'artechia_receptionist' => [
                'label' => __( 'Receptionist', 'artechia-pms' ),
                'caps'  => [
                    'artechia_manage_bookings',
                    'artechia_manage_guests',
                    'artechia_view_calendar',
                    'artechia_manage_checkin',
                    'artechia_manage_payments',
                    'artechia_view_rooms',
                ],
            ],
            'artechia_housekeeping' => [
                'label' => __( 'Housekeeping', 'artechia-pms' ),
                'caps'  => [
                    'artechia_manage_housekeeping',
                    'artechia_view_rooms',
                ],
            ],
        ];
    }

    /**
     * Create custom roles and add caps to the administrator role.
     */
    public static function create(): void {
        // Give administrator all caps.
        $admin = get_role( 'administrator' );
        if ( $admin ) {
            foreach ( self::CAPS as $cap => $grant ) {
                $admin->add_cap( $cap, $grant );
            }
        }

        // Create custom roles.
        foreach ( self::role_definitions() as $role_slug => $def ) {
            // Start with read capability (required for WP login).
            $caps = [ 'read' => true ];
            foreach ( $def['caps'] as $cap ) {
                $caps[ $cap ] = true;
            }

            // Remove existing role first (to update caps on re-activation).
            remove_role( $role_slug );
            add_role( $role_slug, $def['label'], $caps );
        }
    }

    /**
     * Remove custom roles and caps from administrator.
     */
    public static function remove(): void {
        $admin = get_role( 'administrator' );
        if ( $admin ) {
            foreach ( self::CAPS as $cap => $grant ) {
                $admin->remove_cap( $cap );
            }
        }

        foreach ( array_keys( self::role_definitions() ) as $role_slug ) {
            remove_role( $role_slug );
        }
    }

    /**
     * Check if the current user has a specific Artechia capability.
     */
    public static function current_user_can( string $cap ): bool {
        return current_user_can( $cap );
    }

    /**
     * Boot runtime capability filters.
     *
     * Call this during plugin init so that Administrators always pass
     * capability checks for plugin pages, even when the custom caps
     * were not persisted to the database on activation.
     */
    public static function boot(): void {
        add_filter( 'user_has_cap', [ __CLASS__, 'grant_admin_caps' ], 10, 4 );
    }

    /**
     * Dynamically grant all Artechia caps to users who already have manage_options.
     *
     * @param array $allcaps All capabilities the user has.
     * @param array $caps    Required primitive caps for the current check.
     * @param array $args    Arguments: [0] = requested cap, [1] = user id.
     * @param \WP_User $user The user object.
     * @return array
     */
    public static function grant_admin_caps( array $allcaps, array $caps, array $args, $user ): array {
        if ( ! empty( $allcaps['manage_options'] ) ) {
            foreach ( self::CAPS as $cap => $grant ) {
                $allcaps[ $cap ] = true;
            }
        }
        return $allcaps;
    }
}
