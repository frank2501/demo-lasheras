<?php
/**
 * Admin REST Controller.
 * 
 * Handles Admin-specific API endpoints: Calendar, Operations, etc.
 */
namespace Artechia\PMS\Admin;

use Artechia\PMS\Services\CalendarService;
use Artechia\PMS\Services\BookingService;
use Artechia\PMS\RateLimiter;
use Artechia\PMS\Repositories\RoomUnitRepository;
use Artechia\PMS\Repositories\ICalFeedRepository;
use Artechia\PMS\Repositories\ConflictRepository;
use Artechia\PMS\Services\ICalService;
use Artechia\PMS\Services\ReportService;
use Artechia\PMS\Repositories\CouponRepository;
use Artechia\PMS\Repositories\PromotionRepository;
use Artechia\PMS\Logger;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class RestAdmin {

    private $namespace = 'artechia/v1';

    public function register_routes() {
        // GET /admin/calendar
        register_rest_route( $this->namespace, '/admin/calendar', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'get_calendar' ],
            'permission_callback' => [ $this, 'check_admin_permission' ],
            'args'                => [
                'property_id' => [ 'required' => true, 'validate_callback' => function($param) { return is_numeric($param); } ],
                'start_date'  => [ 'required' => true ], // Y-m-d
                'days'        => [ 'default'  => 30, 'validate_callback' => function($param) { return is_numeric($param); } ],
            ],
        ] );

        // POST /admin/booking/{id}/assign-unit
        register_rest_route( $this->namespace, '/admin/booking/(?P<id>\d+)/assign-unit', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'assign_unit' ],
            'permission_callback' => function() { return current_user_can( 'artechia_manage_bookings' ); },
            'args'                => [
                'room_unit_id' => [ 'required' => true, 'validate_callback' => function($param) { return is_numeric($param); } ],
            ],
        ] );

        // POST /admin/booking/{id}/checkin
        register_rest_route( $this->namespace, '/admin/booking/(?P<id>\d+)/checkin', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'check_in' ],
            'permission_callback' => function() { return current_user_can( 'artechia_manage_checkin' ); },
        ] );

        // POST /admin/booking/{id}/checkout
        register_rest_route( $this->namespace, '/admin/booking/(?P<id>\d+)/checkout', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'check_out' ],
            'permission_callback' => function() { return current_user_can( 'artechia_manage_checkin' ); },
        ] );

        // POST /admin/room-unit/{id}/housekeeping
        register_rest_route( $this->namespace, '/admin/room-unit/(?P<id>\d+)/housekeeping', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'update_housekeeping' ],
            'permission_callback' => function() { return current_user_can( 'artechia_manage_housekeeping' ); },
            'args'                => [
                'status' => [ 'required' => true, 'enum' => [ 'clean', 'dirty', 'inspecting', 'out_of_service' ] ],
            ],
        ] );

        // ── H7: iCal Management ──
        
        // GET /admin/ical/feeds?unit_id=X
        register_rest_route( $this->namespace, '/admin/ical/feeds', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'get_ical_feeds' ],
            'permission_callback' => function() { return current_user_can( 'artechia_manage_bookings' ); },
        ] );

        // POST /admin/ical/feeds (Create)
        register_rest_route( $this->namespace, '/admin/ical/feeds', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'create_ical_feed' ],
            'permission_callback' => function() { return current_user_can( 'artechia_manage_bookings' ); },
        ] );

        // POST /admin/ical/feeds/{id}/sync
        register_rest_route( $this->namespace, '/admin/ical/feeds/(?P<id>\d+)/sync', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'sync_ical_feed' ],
            'permission_callback' => function() { return current_user_can( 'artechia_manage_bookings' ); },
        ] );

        // DELETE /admin/ical/feeds/{id}
        register_rest_route( $this->namespace, '/admin/ical/feeds/(?P<id>\d+)', [
            'methods'             => 'DELETE',
            'callback'            => [ $this, 'delete_ical_feed' ],
            'permission_callback' => function() { return current_user_can( 'artechia_manage_bookings' ); },
        ] );

        // GET /admin/ical/conflicts
        register_rest_route( $this->namespace, '/admin/ical/conflicts', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'get_conflicts' ],
            'permission_callback' => function() { return current_user_can( 'artechia_manage_bookings' ); },
        ] );

        // POST /admin/ical/conflicts/{id}/resolve
        register_rest_route( $this->namespace, '/admin/ical/conflicts/(?P<id>\d+)/resolve', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'resolve_conflict' ],
            'permission_callback' => function() { return current_user_can( 'artechia_manage_bookings' ); },
        ] );

        // POST /admin/room-unit/{id}/ical-token (Regenerate export token/enable export)
        register_rest_route( $this->namespace, '/admin/room-unit/(?P<id>\d+)/ical-token', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'regenerate_export_token' ],
            'permission_callback' => function() { return current_user_can( 'artechia_manage_bookings' ); },
        ] );

        // ── H8: Reports ──

        // GET /admin/reports/dashboard
        register_rest_route( $this->namespace, '/admin/reports/dashboard', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'get_dashboard_stats' ],
            'permission_callback' => [ $this, 'check_admin_permission' ],
        ]);

        // GET /admin/reports/occupancy
        register_rest_route( $this->namespace, '/admin/reports/occupancy', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'get_occupancy_report' ],
            'permission_callback' => [ $this, 'check_admin_permission' ],
        ]);

        // GET /admin/reports/financial
        register_rest_route( $this->namespace, '/admin/reports/financial', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'get_financial_report' ],
            'permission_callback' => [ $this, 'check_admin_permission' ],
        ]);

        // GET /admin/reports/sources
        register_rest_route( $this->namespace, '/admin/reports/sources', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'get_source_report' ],
            'permission_callback' => [ $this, 'check_admin_permission' ],
        ]);


        // ── Housekeeping ──

        // GET /admin/housekeeping
        register_rest_route( $this->namespace, '/admin/housekeeping', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'get_housekeeping' ],
            'permission_callback' => [ $this, 'check_admin_permission' ],
        ]);

        // POST /admin/room-unit/{id}/housekeeping
        register_rest_route( $this->namespace, '/admin/room-unit/(?P<id>\d+)/housekeeping', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'update_housekeeping' ],
            'permission_callback' => [ $this, 'check_admin_permission' ],
        ]);

        // GET /admin/email-templates
        register_rest_route( $this->namespace, '/admin/email-templates', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'get_email_templates' ],
            'permission_callback' => [ $this, 'check_admin_permission' ],
        ]);

        // POST /admin/email-templates
        register_rest_route( $this->namespace, '/admin/email-templates', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'create_email_template' ],
            'permission_callback' => [ $this, 'check_admin_permission' ],
        ]);

        // GET /admin/email-templates/{id}
        register_rest_route( $this->namespace, '/admin/email-templates/(?P<id>\d+)', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'get_email_template' ],
            'permission_callback' => [ $this, 'check_admin_permission' ],
        ]);

        // POST /admin/email-templates/{id}
        register_rest_route( $this->namespace, '/admin/email-templates/(?P<id>\d+)', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'update_email_template' ],
            'permission_callback' => [ $this, 'check_admin_permission' ],
        ]);

        // POST /admin/email-templates/{id}/preview
        register_rest_route( $this->namespace, '/admin/email-templates/(?P<id>\d+)/preview', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'preview_email_template' ],
            'permission_callback' => [ $this, 'check_admin_permission' ],
        ]);
        // ── Promociones ──

        // GET /admin/promotions
        register_rest_route( $this->namespace, '/admin/promotions', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'get_promotions' ],
            'permission_callback' => [ $this, 'check_admin_permission' ],
        ]);

        // POST /admin/promotions
        register_rest_route( $this->namespace, '/admin/promotions', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'create_promotion' ],
            'permission_callback' => [ $this, 'check_admin_permission' ],
        ]);

        // PUT /admin/promotions/{id}
        register_rest_route( $this->namespace, '/admin/promotions/(?P<id>\d+)', [
            'methods'             => 'PUT',
            'callback'            => [ $this, 'update_promotion' ],
            'permission_callback' => [ $this, 'check_admin_permission' ],
        ]);

        // DELETE /admin/promotions/{id}
        register_rest_route( $this->namespace, '/admin/promotions/(?P<id>\d+)', [
            'methods'             => 'DELETE',
            'callback'            => [ $this, 'delete_promotion' ],
            'permission_callback' => [ $this, 'check_admin_permission' ],
        ]);

        // POST /admin/email-templates/{id}/send-test
        register_rest_route( $this->namespace, '/admin/email-templates/(?P<id>\d+)/send-test', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'send_test_email' ],
            'permission_callback' => [ $this, 'check_admin_permission' ],
        ]);

        // ── Marketing ──

        register_rest_route( $this->namespace, '/admin/marketing/guests/count', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'get_marketing_guest_count' ],
            'permission_callback' => function() { return current_user_can( 'artechia_manage_coupons' ); },
        ]);

        register_rest_route( $this->namespace, '/admin/marketing/guests', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'get_marketing_guests' ],
            'permission_callback' => function() { return current_user_can( 'artechia_manage_coupons' ); },
        ]);

        register_rest_route( $this->namespace, '/admin/marketing/send', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'send_marketing_campaign' ],
            'permission_callback' => function() { return current_user_can( 'artechia_manage_coupons' ); },
        ]);

        // GET /admin/marketing/history
        register_rest_route( $this->namespace, '/admin/marketing/history', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'get_marketing_history' ],
            'permission_callback' => function() { return current_user_can( 'artechia_manage_coupons' ); },
        ]);

        // ── H9: Calendar PRO ──

        // POST /admin/calendar/booking/{id}/move
        register_rest_route( $this->namespace, '/admin/calendar/booking/(?P<id>\d+)/move', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'move_booking' ],
            'permission_callback' => function() {
                // Rate Limit: 20 requests per minute per user (Calendar Ops)
                if ( ! \Artechia\PMS\RateLimiter::check( 'calendar_ops', 20, 60 ) ) {
                    return new \WP_Error( 'rate_limit', 'Too many calendar operations.', [ 'status' => 429 ] );
                }
                return current_user_can( 'artechia_manage_bookings' );
            },
            'args'                => [
                'room_unit_id' => [ 'required' => true, 'type' => 'integer' ],
                'check_in'     => [ 'required' => true, 'type' => 'string' ],
                'check_out'    => [ 'required' => true, 'type' => 'string' ],
            ],
        ] );

        // POST /admin/calendar/booking/{id}/resize
        register_rest_route( $this->namespace, '/admin/calendar/booking/(?P<id>\d+)/resize', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'resize_booking' ],
            'permission_callback' => function() {
                if ( ! \Artechia\PMS\RateLimiter::check( 'calendar_ops', 20, 60 ) ) {
                    return new \WP_Error( 'rate_limit', 'Too many calendar operations.', [ 'status' => 429 ] );
                }
                return current_user_can( 'artechia_manage_bookings' );
            },
            'args'                => [
                'check_out' => [ 'required' => true, 'type' => 'string' ],
            ],
        ] );

        // GET /admin/calendar/search
        register_rest_route( $this->namespace, '/admin/calendar/search', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'search_calendar' ],
            'permission_callback' => function() { return current_user_can( 'artechia_manage_bookings' ); },
            'args'                => [
                'q'           => [ 'required' => true, 'type' => 'string' ],
                'property_id' => [ 'required' => true, 'type' => 'integer' ],
            ],
        ] );

        // ── H11: Coupons CRUD ──

        // GET /admin/coupons
        register_rest_route( $this->namespace, '/admin/coupons', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'get_coupons' ],
            'permission_callback' => [ $this, 'check_admin_permission' ],
        ] );

        // POST /admin/coupons (Create)
        register_rest_route( $this->namespace, '/admin/coupons', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'create_coupon' ],
            'permission_callback' => [ $this, 'check_admin_permission' ],
        ] );

        // POST /admin/coupons/{id} (Update)
        register_rest_route( $this->namespace, '/admin/coupons/(?P<id>\d+)', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'update_coupon' ],
            'permission_callback' => [ $this, 'check_admin_permission' ],
        ] );

        // DELETE /admin/coupons/{id}
        register_rest_route( $this->namespace, '/admin/coupons/(?P<id>\d+)', [
            'methods'             => 'DELETE',
            'callback'            => [ $this, 'delete_coupon' ],
            'permission_callback' => [ $this, 'check_admin_permission' ],
        ] );

        // GET /admin/bookings
        register_rest_route( $this->namespace, '/admin/bookings', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'get_bookings' ],
            'permission_callback' => [ $this, 'check_admin_permission' ],
            'args'                => [
                'page'           => [ 'default' => 1 ],
                'per_page'       => [ 'default' => 20 ],
                'property_id'    => [],
                'status'         => [],
                'source'         => [],
                'payment_status' => [],
                'date_from'      => [], // Y-m-d
                'date_to'        => [], // Y-m-d
                'search'         => [],
                'include_locks'  => [ 'default' => 1 ],
            ],
        ] );

        // GET /admin/bookings/{code}
        register_rest_route( $this->namespace, '/admin/bookings/(?P<code>[A-Z0-9-]+)', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'get_booking' ],
            'permission_callback' => [ $this, 'check_admin_permission' ],
        ] );

        // POST /admin/bookings/{code}/confirm
        register_rest_route( $this->namespace, '/admin/bookings/(?P<code>[A-Z0-9-]+)/confirm', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'confirm_booking' ],
            'permission_callback' => [ $this, 'check_admin_permission' ],
        ] );

        // PUT /admin/bookings/{code}/dates
        register_rest_route( $this->namespace, '/admin/bookings/(?P<code>[a-zA-Z0-9_-]+)/dates', [
            'methods'             => 'PUT',
            'callback'            => [ $this, 'update_booking_dates' ],
            'permission_callback' => [ $this, 'check_admin_permission' ],
        ]);

        // GET /admin/bookings/{code}/penalty
        register_rest_route( $this->namespace, '/admin/bookings/(?P<code>[a-zA-Z0-9_-]+)/penalty', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'get_cancellation_penalty' ],
            'permission_callback' => [ $this, 'check_admin_permission' ],
        ]);

        // POST /admin/bookings/{code}/cancel
        register_rest_route( $this->namespace, '/admin/bookings/(?P<code>[A-Z0-9-]+)/cancel', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'cancel_booking' ],
            'permission_callback' => [ $this, 'check_admin_permission' ],
        ] );

        // POST /admin/bookings/{code}/payment
        register_rest_route( $this->namespace, '/admin/bookings/(?P<code>[A-Z0-9-]+)/payment', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'record_booking_payment' ],
            'permission_callback' => [ $this, 'check_admin_permission' ],
            'args'                => [
                'amount' => [ 'required' => true, 'type' => 'number' ],
                'note'   => [ 'type' => 'string' ],
            ],
        ] );

        // DELETE /admin/bookings/{code}/payment/{id}
        register_rest_route( $this->namespace, '/admin/bookings/(?P<code>[A-Z0-9-]+)/payment/(?P<payment_id>\d+)', [
            'methods'             => 'DELETE',
            'callback'            => [ $this, 'delete_booking_payment' ],
            'permission_callback' => [ $this, 'check_admin_permission' ],
        ] );

        // POST /admin/bookings/{code}/reactivate
        register_rest_route( $this->namespace, '/admin/bookings/(?P<code>[A-Z0-9-]+)/reactivate', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'reactivate_booking' ],
            'permission_callback' => [ $this, 'check_admin_permission' ],
        ] );

        // POST /admin/bookings/{code}/status
        register_rest_route( $this->namespace, '/admin/bookings/(?P<code>[A-Z0-9-]+)/status', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'change_booking_status' ],
            'permission_callback' => [ $this, 'check_admin_permission' ],
            'args'                => [
                'status' => [ 'required' => true, 'type' => 'string' ],
            ],
        ] );

        // DELETE /admin/bookings/{code}
        register_rest_route( $this->namespace, '/admin/bookings/(?P<code>[A-Z0-9-]+)', [
            'methods'             => 'DELETE',
            'callback'            => [ $this, 'delete_booking' ],
            'permission_callback' => [ $this, 'check_admin_permission' ],
        ] );

        // PUT /admin/bookings/{code} (Edit)
        register_rest_route( $this->namespace, '/admin/bookings/(?P<code>[A-Z0-9-]+)', [
            'methods'             => 'PUT',
            'callback'            => [ $this, 'update_booking' ],
            'permission_callback' => [ $this, 'check_admin_permission' ],
        ] );

        // POST /admin/bookings/manual-create (H_Manual)
        register_rest_route( $this->namespace, '/admin/bookings/manual-create', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'create_manual_booking' ],
            'permission_callback' => [ $this, 'check_admin_permission' ],
        ] );

        // GET /admin/setup/room-types
        register_rest_route( $this->namespace, '/admin/setup/room-types', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'get_setup_room_types' ],
            'permission_callback' => [ $this, 'check_admin_permission' ],
        ] );

        // GET /admin/setup/rate-plans
        register_rest_route( $this->namespace, '/admin/setup/rate-plans', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'get_setup_rate_plans' ],
            'permission_callback' => [ $this, 'check_admin_permission' ],
        ] );

        // GET /admin/setup/room-units
        register_rest_route( $this->namespace, '/admin/setup/room-units', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'get_setup_room_units' ],
            'permission_callback' => [ $this, 'check_admin_permission' ],
        ] );

        // GET /admin/setup/properties
        register_rest_route( $this->namespace, '/admin/setup/properties', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'get_setup_properties' ],
            'permission_callback' => [ $this, 'check_admin_permission' ],
        ] );

        register_rest_route( $this->namespace, '/admin/setup/demo-data', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'populate_demo_data' ],
            'permission_callback' => [ $this, 'check_admin_permission' ],
        ] );

        /* ── Property CRUD ──────────────────────────────── */
        register_rest_route( $this->namespace, '/admin/properties', [
            [
                'methods'             => 'GET',
                'callback'            => [ $this, 'list_properties' ],
                'permission_callback' => [ $this, 'check_admin_permission' ],
            ],
            [
                'methods'             => 'POST',
                'callback'            => [ $this, 'save_property' ],
                'permission_callback' => [ $this, 'check_admin_permission' ],
            ],
        ] );
        register_rest_route( $this->namespace, '/admin/properties/(?P<id>\d+)', [
            [
                'methods'             => 'GET',
                'callback'            => [ $this, 'get_property' ],
                'permission_callback' => [ $this, 'check_admin_permission' ],
            ],
            [
                'methods'             => 'PUT',
                'callback'            => [ $this, 'save_property' ],
                'permission_callback' => [ $this, 'check_admin_permission' ],
            ],
            [
                'methods'             => 'DELETE',
                'callback'            => [ $this, 'delete_property' ],
                'permission_callback' => [ $this, 'check_admin_permission' ],
            ],
        ] );

        /* ── Room Type CRUD ─────────────────────────────── */
        register_rest_route( $this->namespace, '/admin/room-types', [
            [
                'methods'             => 'GET',
                'callback'            => [ $this, 'list_room_types' ],
                'permission_callback' => [ $this, 'check_admin_permission' ],
            ],
            [
                'methods'             => 'POST',
                'callback'            => [ $this, 'save_room_type' ],
                'permission_callback' => [ $this, 'check_admin_permission' ],
            ],
        ] );
        register_rest_route( $this->namespace, '/admin/room-types/(?P<id>\d+)', [
            [
                'methods'             => 'GET',
                'callback'            => [ $this, 'get_room_type' ],
                'permission_callback' => [ $this, 'check_admin_permission' ],
            ],
            [
                'methods'             => 'PUT',
                'callback'            => [ $this, 'save_room_type' ],
                'permission_callback' => [ $this, 'check_admin_permission' ],
            ],
            [
                'methods'             => 'DELETE',
                'callback'            => [ $this, 'delete_room_type' ],
                'permission_callback' => [ $this, 'check_admin_permission' ],
            ],
        ] );

        /* ── Room Unit CRUD ─────────────────────────────── */
        register_rest_route( $this->namespace, '/admin/room-units', [
            [
                'methods'             => 'GET',
                'callback'            => [ $this, 'list_room_units' ],
                'permission_callback' => [ $this, 'check_admin_permission' ],
            ],
            [
                'methods'             => 'POST',
                'callback'            => [ $this, 'save_room_unit' ],
                'permission_callback' => [ $this, 'check_admin_permission' ],
            ],
        ] );
        register_rest_route( $this->namespace, '/admin/room-units/(?P<id>\d+)', [
            [
                'methods'             => 'GET',
                'callback'            => [ $this, 'get_room_unit' ],
                'permission_callback' => [ $this, 'check_admin_permission' ],
            ],
            [
                'methods'             => 'PUT',
                'callback'            => [ $this, 'save_room_unit' ],
                'permission_callback' => [ $this, 'check_admin_permission' ],
            ],
            [
                'methods'             => 'DELETE',
                'callback'            => [ $this, 'delete_room_unit' ],
                'permission_callback' => [ $this, 'check_admin_permission' ],
            ],
        ] );

        // GET /admin/debug/logs
        register_rest_route( $this->namespace, '/admin/debug/logs', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'get_debug_logs' ],
            'permission_callback' => function() { return current_user_can( 'artechia_view_logs' ); },
        ] );
    }

    /**
     * DELETE /admin/bookings/{id}
     */
    public function delete_booking( \WP_REST_Request $request ) {
        return $this->safe_response( function() use ( $request ) {
            $code = $request['code'];
            $repo = new \Artechia\PMS\Repositories\BookingRepository();
            $booking = $repo->find_by_code( $code );

            if ( ! $booking ) {
                return new \WP_REST_Response( [ 'error' => 'NOT_FOUND', 'message' => 'Reserva no encontrada' ], 404 );
            }

            $svc = new \Artechia\PMS\Services\BookingService();
            $res = $svc->delete_booking( $booking['id'] );

            if ( isset( $res['error'] ) ) {
                return new \WP_REST_Response( $res, 400 );
            }

            return new \WP_REST_Response( $res, 200 );
        } );
    }

    /* ── Safe Response Wrapper ──────────────────────── */

    /**
     * Wrap a callback in try/catch. Logs errors, never exposes stack traces.
     *
     * @return \WP_REST_Response|\WP_Error
     */
    private function safe_response( callable $fn ) {
        $request_id = wp_generate_uuid4();
        
        try {
            $result = $fn();
            if ( is_array( $result ) && isset( $result['error'] ) ) {
                Logger::warning( 'rest.response_error', "REST error result for {$_SERVER['REQUEST_URI']}: " . $result['error'], 'rest_api', null, [ 'request_id' => $request_id ] );
            }
            return $result;
        } catch ( \Throwable $e ) {
            // Log with explicit request_id
            Logger::critical( 'rest.error', $e->getMessage(), 'rest_api', null, [
                'request_id' => $request_id,
                'file'       => $e->getFile(),
                'line'       => $e->getLine(),
                'trace'      => substr( $e->getTraceAsString(), 0, 1000 ),
            ] );
            
            error_log( sprintf( '[Artechia REST] Error %s: %s in %s:%d', $request_id, $e->getMessage(), $e->getFile(), $e->getLine() ) );

            $response = [
                'error'      => 'INTERNAL_ERROR',
                'message'    => 'An unexpected error occurred. Please try again.',
                'request_id' => $request_id,
            ];

            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                $response['error_class']   = get_class( $e );
                $response['error_message'] = $e->getMessage();
            }

            return new \WP_REST_Response( $response, 500 );
        }
    }

    /* ── Callbacks ──────────────────────────────────── */

    public function get_calendar( \WP_REST_Request $request ) {
        return $this->safe_response( function () use ( $request ) {
            $property_id = (int) $request->get_param( 'property_id' );
            $start_date  = sanitize_text_field( $request->get_param( 'start_date' ) ?? '' );
            $days        = (int) $request->get_param( 'days' );

            $service = new CalendarService();
            $data    = $service->get_calendar_data( $property_id, $start_date, $days );

            return new \WP_REST_Response( $data, 200 );
        } );
    }

    public function assign_unit( \WP_REST_Request $request ) {
        return $this->safe_response( function () use ( $request ) {
            $booking_id = (int) $request->get_param( 'id' );
            $unit_id    = (int) $request->get_param( 'room_unit_id' );

            $service = new BookingService();
            $result  = $service->assign_unit( $booking_id, $unit_id );

            if ( isset( $result['error'] ) ) {
                return new \WP_REST_Response( $result, 400 );
            }
            return new \WP_REST_Response( $result, 200 );
        } );
    }

    public function check_in( \WP_REST_Request $request ) {
        return $this->safe_response( function () use ( $request ) {
            $booking_id = (int) $request->get_param( 'id' );
            $service    = new BookingService();
            $result     = $service->check_in( $booking_id );

            if ( isset( $result['error'] ) ) {
                return new \WP_REST_Response( $result, 400 );
            }
            return new \WP_REST_Response( $result, 200 );
        } );
    }

    public function check_out( \WP_REST_Request $request ) {
        return $this->safe_response( function () use ( $request ) {
            $booking_id = (int) $request->get_param( 'id' );
            $service    = new BookingService();
            $result     = $service->check_out( $booking_id );

            if ( isset( $result['error'] ) ) {
                return new \WP_REST_Response( $result, 400 );
            }
            return new \WP_REST_Response( $result, 200 );
        } );
    }

    public function update_housekeeping( \WP_REST_Request $request ) {
        return $this->safe_response( function () use ( $request ) {
            $unit_id = (int) $request->get_param( 'id' );
            $status  = sanitize_text_field( $request->get_param( 'status' ) ?? '' );

            $repo = new RoomUnitRepository();
            $unit = $repo->find( $unit_id );
            
            if ( ! $unit ) {
                return new \WP_REST_Response( [ 'error' => 'NOT_FOUND', 'message' => 'Unit not found.' ], 404 );
            }

            // Business Rule: Cannot set to out_of_service if occupied or has arrival today
            if ( $status === 'out_of_service' ) {
                $booking_repo = new \Artechia\PMS\Repositories\BookingRepository();
                if ( $booking_repo->has_active_or_incoming_booking( $unit_id ) ) {
                    return new \WP_REST_Response( [ 
                        'error'   => 'UNIT_OCCUPIED', 
                        'message' => 'Cannot mark out_of_service: unit is occupied or has arrival today.' 
                    ], 409 );
                }
            }

            $updated = $repo->update_housekeeping( $unit_id, $status );

            if ( ! $updated ) {
                return new \WP_REST_Response( [ 'error' => 'UPDATE_FAILED', 'message' => 'Could not update status.' ], 400 );
            }
            return new \WP_REST_Response( [ 'success' => true ], 200 );
        } );
    }

    public function get_housekeeping( \WP_REST_Request $request ) {
        return $this->safe_response( function () use ( $request ) {
            $repo = new RoomUnitRepository();
            $args = [
                'where' => []
            ];

            if ( $pid = $request->get_param( 'property_id' ) ) {
                $args['where']['property_id'] = (int) $pid;
            }
            if ( $rtid = $request->get_param( 'room_type_id' ) ) {
                $args['where']['room_type_id'] = (int) $rtid;
            }

            $units = $repo->all_with_type( $args );

            if ( empty( $units ) ) {
                 return new \WP_REST_Response( [], 200 );
            }

            // Gather Unit IDs for bulk query
            $unit_ids = array_column( $units, 'id' );
            
            // Fetch stats in bulk (Fixes N+1 problem)
            $booking_repo = new \Artechia\PMS\Repositories\BookingRepository();
            $stats = $booking_repo->get_housekeeping_stats( $unit_ids );

            // Merge stats
            foreach ( $units as &$unit ) {
                $uid = $unit['id'];
                $unit['last_checkout'] = $stats[$uid]['last_checkout'] ?? null;
                $unit['next_arrival']  = $stats[$uid]['next_arrival'] ?? null;
            }

            return new \WP_REST_Response( $units, 200 );
        } );
    }

    /* ── H7 Handlers ────────────────────────────────── */

    public function get_ical_feeds( \WP_REST_Request $request ) {
        return $this->safe_response( function () use ( $request ) {
            $unit_id = (int) $request->get_param( 'unit_id' );
            if ( ! $unit_id ) {
                return new \WP_REST_Response( [ 'error' => 'MISSING_PARAM', 'message' => 'unit_id required' ], 400 );
            }
            $repo = new ICalFeedRepository();
            $feeds = $repo->find_by_unit( $unit_id );
            return new \WP_REST_Response( $feeds, 200 );
        } );
    }

    public function create_ical_feed( \WP_REST_Request $request ) {
        return $this->safe_response( function () use ( $request ) {
            $data = $request->get_json_params();
            if ( empty( $data['room_unit_id'] ) || empty( $data['url'] ) ) {
                return new \WP_REST_Response( [ 'error' => 'MISSING_PARAMS', 'message' => 'room_unit_id and url required' ], 400 );
            }

            $repo = new ICalFeedRepository();
            $id = $repo->create([
                'property_id'     => (int) ($data['property_id'] ?? 0),
                'room_unit_id'    => (int) $data['room_unit_id'],
                'name'            => sanitize_text_field( $data['name'] ?? 'Import' ),
                'url'             => esc_url_raw( $data['url'] ),
                'conflict_policy' => sanitize_text_field( $data['conflict_policy'] ?? 'mark_conflict' ),
                'sync_interval'   => 15,
                'is_active'       => 1,
            ]);

            if ( ! $id ) return new \WP_REST_Response( [ 'error' => 'DB_ERROR' ], 500 );
            return new \WP_REST_Response( $repo->find( $id ), 201 );
        } );
    }

    public function delete_ical_feed( \WP_REST_Request $request ) {
        return $this->safe_response( function () use ( $request ) {
            $id = (int) $request['id'];
            $repo = new ICalFeedRepository();
            $repo->delete( $id );
            return new \WP_REST_Response( null, 204 );
        } );
    }

    public function sync_ical_feed( \WP_REST_Request $request ) {
        return $this->safe_response( function () use ( $request ) {
            $id = (int) $request['id'];
            $service = new ICalService();
            $result = $service->fetch_and_sync( $id );
            return new \WP_REST_Response( $result, 200 );
        } );
    }

    public function get_conflicts( \WP_REST_Request $request ) {
        return $this->safe_response( function () use ( $request ) {
            $property_id = (int) $request->get_param( 'property_id' );
            $repo = new ConflictRepository();
            $conflicts = $repo->find_unresolved( $property_id );
            return new \WP_REST_Response( $conflicts, 200 );
        } );
    }

    public function resolve_conflict( \WP_REST_Request $request ) {
        return $this->safe_response( function () use ( $request ) {
            $id = (int) $request['id'];
            $action = $request->get_param( 'action' );
            
            $repo = new ConflictRepository();
            $conflict = $repo->find( $id );
            if ( ! $conflict ) return new \WP_REST_Response( [ 'error' => 'NOT_FOUND' ], 404 );
            
            $repo->update( $id, [
                'resolved' => 1,
                'resolved_at' => current_time( 'mysql' ),
                'resolved_note' => "Resolved via API action: $action"
            ]);
            
            return new \WP_REST_Response( [ 'success' => true ], 200 );
        } );
    }

    public function regenerate_export_token( \WP_REST_Request $request ) {
        return $this->safe_response( function () use ( $request ) {
            $unit_id = (int) $request['id'];
            $repo = new ICalFeedRepository();
            
            $feeds = $repo->find_by_unit( $unit_id );
            $export_feed = null;
            foreach ( $feeds as $f ) {
                 if ( ! empty( $f['export_token'] ) ) {
                     $export_feed = $f;
                     break;
                 }
            }

            $token = hash('sha256', uniqid() . microtime());
            
            if ( $export_feed ) {
                $repo->update( $export_feed['id'], [ 'export_token' => $token ] );
            } else {
                $repo->create([
                    'room_unit_id' => $unit_id,
                    'property_id' => 0,
                    'name' => 'Export Config',
                    'url' => '',
                    'export_token' => $token,
                    'is_active' => 1
                ]);
            }

            return new \WP_REST_Response( [ 'token' => $token ], 200 );
        } );
    }

    /* ── H8 Report Handlers ─────────────────────────── */

    public function get_dashboard_stats( \WP_REST_Request $request ) {
        return $this->safe_response( function () {
            $service = new ReportService();
            $data = $service->get_dashboard_stats();
            return new \WP_REST_Response( $data, 200 );
        } );
    }

    public function get_occupancy_report( \WP_REST_Request $request ) {
        return $this->safe_response( function () use ( $request ) {
            $start = sanitize_text_field( $request->get_param('start_date') );
            $end   = sanitize_text_field( $request->get_param('end_date') );
            $room_type_id = sanitize_text_field( $request->get_param('room_type_id') );
            $service = new ReportService();
            $data = $service->get_occupancy_report( $start, $end, $room_type_id );
            return new \WP_REST_Response( $data, 200 );
        } );
    }

    public function get_financial_report( \WP_REST_Request $request ) {
        return $this->safe_response( function () use ( $request ) {
            $start = sanitize_text_field( $request->get_param('start_date') );
            $end   = sanitize_text_field( $request->get_param('end_date') );
            $service = new ReportService();
            $data = $service->get_financial_report( $start, $end );
            return new \WP_REST_Response( $data, 200 );
        } );
    }

    public function get_source_report( \WP_REST_Request $request ) {
        return $this->safe_response( function () use ( $request ) {
            $start = sanitize_text_field( $request->get_param('start_date') );
            $end   = sanitize_text_field( $request->get_param('end_date') );
            $service = new ReportService();
            $data = $service->get_source_report( $start, $end );
            return new \WP_REST_Response( $data, 200 );
        } );
    }

    /* ── H9 Calendar PRO Handlers ────────────────── */

    public function move_booking( \WP_REST_Request $request ) {
        if ( ! RateLimiter::check( 'move_booking', 20, 60 ) ) {
            return RateLimiter::limited_response();
        }
        return $this->safe_response( function () use ( $request ) {
            $id      = (int) $request['id'];
            $unit_id = (int) $request['room_unit_id'];
            $ci      = sanitize_text_field( $request['check_in'] ?? '' );
            $co      = sanitize_text_field( $request['check_out'] ?? '' );

            $service = new BookingService();
            $result  = $service->move_booking( $id, $unit_id, $ci, $co );

            if ( isset( $result['error'] ) ) {
                return new \WP_REST_Response( $result, 400 );
            }
            return new \WP_REST_Response( $result, 200 );
        } );
    }

    public function resize_booking( \WP_REST_Request $request ) {
        if ( ! RateLimiter::check( 'resize_booking', 20, 60 ) ) {
            return RateLimiter::limited_response();
        }
        return $this->safe_response( function () use ( $request ) {
            $id = (int) $request['id'];
            $co = sanitize_text_field( $request['check_out'] ?? '' );

            $service = new BookingService();
            $result  = $service->resize_booking( $id, $co );

            if ( isset( $result['error'] ) ) {
                return new \WP_REST_Response( $result, 400 );
            }
            return new \WP_REST_Response( $result, 200 );
        } );
    }

    public function get_coupons( \WP_REST_Request $request ) {
        return $this->safe_response( function () {
            global $wpdb;
            $repo = new CouponRepository();
            $coupons = $repo->all();

            // Batch usage counts in a single query instead of N queries
            $redemptions_table = \Artechia\PMS\DB\Schema::table( 'coupon_redemptions' );
            $usage_map = [];
            $rows = $wpdb->get_results(
                "SELECT coupon_id, COUNT(*) AS cnt FROM {$redemptions_table} GROUP BY coupon_id",
                ARRAY_A
            );
            foreach ( $rows as $r ) {
                $usage_map[ (int) $r['coupon_id'] ] = (int) $r['cnt'];
            }
            foreach ( $coupons as &$c ) {
                $c['usage_count'] = $usage_map[ (int) $c['id'] ] ?? 0;
            }

            return new \WP_REST_Response( $coupons, 200 );
        } );
    }


    public function create_coupon( \WP_REST_Request $request ) {
        if ( ! RateLimiter::check( 'create_coupon', 10, 60 ) ) {
            return RateLimiter::limited_response();
        }
        return $this->safe_response( function () use ( $request ) {
            $params = $request->get_json_params();
            
            // 1. Validation
            if ( empty( $params['code'] ) || ! isset( $params['value'] ) ) {
                return new \WP_REST_Response( [ 
                    'error' => 'VALIDATION_ERROR', 
                    'fields' => [ 
                        'code' => empty($params['code']), 
                        'value' => !isset($params['value']) 
                    ] 
                ], 400 );
            }

            $repo = new CouponRepository();
            $code = strtoupper( sanitize_text_field( $params['code'] ) );

            // 2. Duplicate Check
            $existing = $repo->find_by_code( $code );
            if ( $existing ) {
                return new \WP_REST_Response( [ 'error' => 'COUPON_EXISTS' ], 409 );
            }
            
            $id = $repo->create([
                'code'                  => $code,
                'type'                  => sanitize_text_field( $params['type'] ?? 'percent' ),
                'value'                 => (float) ( $params['value'] ?? 0 ),
                'starts_at'             => !empty($params['starts_at']) ? sanitize_text_field($params['starts_at']) : null,
                'ends_at'               => !empty($params['ends_at']) ? sanitize_text_field($params['ends_at']) : null,
                'min_nights'            => (int) ( $params['min_nights'] ?? 0 ),
                'room_type_ids'         => !empty($params['room_type_ids']) ? wp_json_encode($params['room_type_ids']) : null,
                'rate_plan_ids'         => !empty($params['rate_plan_ids']) ? wp_json_encode($params['rate_plan_ids']) : null,
                'usage_limit_total'     => !empty($params['usage_limit_total']) ? (int) $params['usage_limit_total'] : null,
                'usage_limit_per_email' => !empty($params['usage_limit_per_email']) ? (int) $params['usage_limit_per_email'] : null,
                'stackable'             => (int) ($params['stackable'] ?? 0),
                'applies_to'            => sanitize_text_field( $params['applies_to'] ?? 'room_only' ),
                'active'                => 1,
                'is_automatic'          => (int) ($params['is_automatic'] ?? 0)
            ]);

            if ( ! $id ) return new \WP_REST_Response( [ 'error' => 'DB_ERROR' ], 500 );
            $this->clear_calendar_cache();
            return new \WP_REST_Response( $repo->find( $id ), 201 );
        } );
    }

    public function update_coupon( \WP_REST_Request $request ) {
        return $this->safe_response( function () use ( $request ) {
            $id     = (int) $request['id'];
            $params = $request->get_json_params();
            $repo   = new CouponRepository();

            $data = [];
            if ( isset( $params['active'] ) )     $data['active']     = (int) $params['active'];
            if ( isset( $params['value'] ) )      $data['value']      = (float) $params['value'];
            if ( isset( $params['type'] ) )       $data['type']       = sanitize_text_field( $params['type'] );
            if ( isset( $params['code'] ) )       $data['code']       = strtoupper( sanitize_text_field( $params['code'] ) );
            if ( isset( $params['min_nights'] ) ) $data['min_nights'] = (int) $params['min_nights'];
            if ( array_key_exists( 'starts_at', $params ) ) {
                $data['starts_at'] = ! empty( $params['starts_at'] ) ? sanitize_text_field( $params['starts_at'] ) : null;
            }
            if ( array_key_exists( 'ends_at', $params ) ) {
                $data['ends_at'] = ! empty( $params['ends_at'] ) ? sanitize_text_field( $params['ends_at'] ) : null;
            }

            if ( empty( $data ) ) return new \WP_REST_Response( [ 'error' => 'NO_DATA' ], 400 );

            $repo->update( $id, $data );
            $this->clear_calendar_cache();
            return new \WP_REST_Response( $repo->find( $id ), 200 );
        } );
    }

    public function delete_coupon( \WP_REST_Request $request ) {
        return $this->safe_response( function () use ( $request ) {
            $id = (int) $request['id'];
            ( new CouponRepository() )->delete( $id );
            $this->clear_calendar_cache();
            return new \WP_REST_Response( null, 204 );
        } );
    }

    /* ── Bookings ───────────────────────────────────── */

    public function get_bookings( \WP_REST_Request $request ) {
        return $this->safe_response( function () use ( $request ) {
            $repo = new \Artechia\PMS\Repositories\BookingRepository();
            
            $page     = (int) $request->get_param( 'page' );
            $per_page = (int) $request->get_param( 'per_page' );

            $args = [
                'page'           => $page > 0 ? $page : 1,
                'per_page'       => $per_page > 0 ? $per_page : 20,
                'property_id'    => (int) $request->get_param( 'property_id' ),
                'status'         => sanitize_text_field( $request->get_param( 'status' ) ?? '' ),
                'source'         => sanitize_text_field( $request->get_param( 'source' ) ?? '' ),
                'payment_status' => sanitize_text_field( $request->get_param( 'payment_status' ) ?? '' ),
                'date_from'      => sanitize_text_field( $request->get_param( 'date_from' ) ?? '' ),
                'date_to'        => sanitize_text_field( $request->get_param( 'date_to' ) ?? '' ),
                'search'         => sanitize_text_field( $request->get_param( 'search' ) ?? '' ),
            ];

            $result = $repo->find_all( $args );

            // Merge Active Checkout Locks if requested
            if ( (int) $request->get_param( 'include_locks' ) !== 0 && $args['page'] === 1 ) {
                $service = new BookingService();
                $locks   = $service->get_active_locks();
                
                if ( ! empty( $locks ) ) {
                    // Convert locks to objects if result items are objects, or vice versa
                    // In find_all, we return an array of objects (using wpdb->get_results)
                    $lock_objects = array_map( function($l) { return (object) $l; }, $locks );
                    
                    // Merge at the beginning of the list
                    $result['data'] = array_merge( $lock_objects, $result['data'] );
                }
            }
            
            // Hydrate Status Label, Deposit, Refund for real bookings (skip lock objects)
            if ( ! empty( $result['data'] ) ) {
                $status_labels = [
                    'pending'      => 'Pendiente',
                    'confirmed'    => 'Confirmada',
                    'checked_in'   => 'In-House',
                    'checked_out'  => 'Finalizada',
                    'cancelled'    => 'Cancelada',
                    'no_show'      => 'No Show',
                ];

                global $wpdb;

                foreach ( $result['data'] as $k => $row ) {
                    // Skip lock objects — they don't have booking properties
                    if ( ! empty( $row->is_lock ) ) continue;

                    // Hydrate Status Label
                    $result['data'][$k]->status_label = $status_labels[$row->status] ?? ucfirst( $row->status ?? '' );

                    // Extract deposit_due from pricing_snapshot
                    $deposit_due = 0;
                    if ( ! empty( $row->pricing_snapshot ) ) {
                        $snap = json_decode( $row->pricing_snapshot, true );
                        if ( is_array( $snap ) && isset( $snap['totals']['deposit_due'] ) ) {
                            $deposit_due = (float) $snap['totals']['deposit_due'];
                        }
                    }
                    $result['data'][$k]->deposit_due = $deposit_due;

                    // Hydrate refund / penalty for cancelled bookings
                    if ( ($row->status ?? '') === 'cancelled' ) {
                        $pay_table = \Artechia\PMS\DB\Schema::table('payments');
                        $refund_total = (float) $wpdb->get_var( $wpdb->prepare(
                            "SELECT COALESCE(ABS(SUM(amount)), 0) FROM {$pay_table} WHERE booking_id = %d AND amount < 0 AND status = 'approved'",
                            $row->id
                        ) );
                        $result['data'][$k]->refund_amount  = $refund_total;
                        $result['data'][$k]->penalty_amount = (float) ($row->amount_paid ?? 0);
                    }

                    // Standardize created_at to UTC 'Z' format
                    if ( ! empty( $row->created_at ) && substr( (string)$row->created_at, -1 ) !== 'Z' ) {
                        $result['data'][$k]->created_at .= 'Z';
                    }
                }
            }

            // ── Overbooking Warnings ──────────────────────────────
            // When pending_blocks_unit is OFF, detect potential overbookings
            $result['overbooking_warnings'] = [];
            if ( $args['page'] === 1 && ! \Artechia\PMS\Repositories\BookingRepository::pending_blocks_unit() ) {
                $b_tbl  = \Artechia\PMS\DB\Schema::table( 'bookings' );
                $br_tbl = \Artechia\PMS\DB\Schema::table( 'booking_rooms' );
                $rt_tbl = \Artechia\PMS\DB\Schema::table( 'room_types' );
                $ru_tbl = \Artechia\PMS\DB\Schema::table( 'room_units' );

                $today = current_time( 'Y-m-d' );
                $pending_bookings = $wpdb->get_results( $wpdb->prepare(
                    "SELECT b.id, b.booking_code, b.check_in, b.check_out,
                            br.room_type_id, rt.name as room_type_name
                     FROM {$b_tbl} b
                     JOIN {$br_tbl} br ON br.booking_id = b.id
                     JOIN {$rt_tbl} rt ON rt.id = br.room_type_id
                     WHERE b.status = 'pending'
                       AND b.payment_status = 'unpaid'
                       AND b.check_out > %s
                     ORDER BY b.check_in ASC",
                    $today
                ), \ARRAY_A );

                if ( ! empty( $pending_bookings ) ) {
                    $by_type = [];
                    foreach ( $pending_bookings as $pb ) {
                        $by_type[ $pb['room_type_id'] ][] = $pb;
                    }

                    foreach ( $by_type as $rt_id => $pbs ) {
                        $total_units = (int) $wpdb->get_var( $wpdb->prepare(
                            "SELECT COUNT(*) FROM {$ru_tbl}
                             WHERE room_type_id = %d AND status NOT IN ('out_of_service', 'maintenance')",
                            $rt_id
                        ) );

                        foreach ( $pbs as $pb ) {
                            $overlapping = (int) $wpdb->get_var( $wpdb->prepare(
                                "SELECT COUNT(DISTINCT br2.booking_id)
                                 FROM {$br_tbl} br2
                                 JOIN {$b_tbl} b2 ON b2.id = br2.booking_id
                                 WHERE br2.room_type_id = %d
                                   AND b2.check_in < %s
                                   AND b2.check_out > %s
                                   AND b2.status IN ('confirmed','checked_in','deposit_paid','paid','hold','pending')",
                                $rt_id, $pb['check_out'], $pb['check_in']
                            ) );

                            if ( $overlapping > $total_units ) {
                                $result['overbooking_warnings'][] = [
                                    'booking_code' => $pb['booking_code'],
                                    'room_type'    => $pb['room_type_name'],
                                    'check_in'     => $pb['check_in'],
                                    'check_out'    => $pb['check_out'],
                                    'overlapping'  => $overlapping,
                                    'total_units'  => $total_units,
                                ];
                            }
                        }
                    }

                    // Deduplicate by booking_code
                    $seen = [];
                    $result['overbooking_warnings'] = array_values( array_filter(
                        $result['overbooking_warnings'],
                        function( $w ) use ( &$seen ) {
                            if ( isset( $seen[ $w['booking_code'] ] ) ) return false;
                            $seen[ $w['booking_code'] ] = true;
                            return true;
                        }
                    ) );
                }
            }

            return new \WP_REST_Response( $result, 200 );
        } );
    }

    public function get_booking( \WP_REST_Request $request ) {
        return $this->safe_response( function () use ( $request ) {
            $code = $request['code'];
            $repo = new \Artechia\PMS\Repositories\BookingRepository();
            $row  = $repo->find_by_code( $code );

            if ( ! $row ) {
                return new \WP_Error( 'not_found', 'Booking not found', [ 'status' => 404 ] );
            }

            $id = $row['id'];
            $row = (object) $row; // Ensure it's an object for consistency with original logic

            // Attach rooms and extras
            $row->rooms  = $repo->get_rooms( $id );
            $row->extras = $repo->get_extras( $id );

            // Attach payment history
            $pay_repo = new \Artechia\PMS\Repositories\PaymentRepository();
            $row->payments = $pay_repo->get_for_booking( $id );
            
            // UI Helpers
            $row->guest_name = trim( ($row->guest_first_name ?? '') . ' ' . ($row->guest_last_name ?? '') ) ?: '—';

            return new \WP_REST_Response( $row, 200 );
        } );
    }

    public function confirm_booking( \WP_REST_Request $request ) {
        return $this->safe_response( function () use ( $request ) {
            $code    = $request['code'];
            $repo    = new \Artechia\PMS\Repositories\BookingRepository();
            $booking = $repo->find_by_code( $code );

            if ( ! $booking ) {
                return new \WP_Error( 'not_found', 'Reserva no encontrada', [ 'status' => 404 ] );
            }

            $params         = $request->get_json_params() ?: [];
            $payment_amount = (float) ( $params['payment_amount'] ?? 0 );
            $balance_due    = (float) ( $booking['balance_due'] ?? $booking['grand_total'] );

            if ( $payment_amount > $balance_due + 0.01 ) {
                 return new \WP_Error( 'overpayment', 'El monto a registrar (' . $payment_amount . ') supera el saldo adeudado (' . $balance_due . ').', [ 'status' => 400 ] );
            }

            $service = new BookingService();
            $result  = $service->confirm_booking( $booking['id'] );

            if ( isset( $result['error'] ) ) {
                return new \WP_Error( 'booking_error', $result['message'], [ 'status' => 400 ] );
            }

            // After confirming logically, apply any payment made in the modal
            if ( $payment_amount > 0 ) {
                // Build descriptive note
                $grand_total = (float) $booking['grand_total'];
                $deposit_pct = 0;
                $snapshot = json_decode( $booking['pricing_snapshot'] ?? '{}', true );
                if ( ! empty( $snapshot['totals']['deposit_pct'] ) ) {
                    $deposit_pct = (int) $snapshot['totals']['deposit_pct'];
                } elseif ( $grand_total > 0 ) {
                    $deposit_pct = round( ($payment_amount / $grand_total) * 100 );
                }
                $pay_note = 'Confirmación de Reserva';
                if ( $deposit_pct > 0 && $deposit_pct < 100 ) {
                    $pay_note .= " - Pago Seña ({$deposit_pct}%)";
                } elseif ( $payment_amount >= $grand_total - 0.01 ) {
                    $pay_note .= ' - Pago Total';
                } else {
                    $pay_note .= ' - Pago parcial';
                }
                $pay_result = $service->record_manual_payment( $booking['id'], $payment_amount, $pay_note );
                if ( isset( $pay_result['error'] ) ) {
                    \Artechia\PMS\Logger::warning( 'booking.confirm_payment_failed', 'Payment recording failed during confirm: ' . ($pay_result['message'] ?? $pay_result['error']), [
                        'booking_id' => $booking['id'],
                        'amount'     => $payment_amount,
                        'error'      => $pay_result['error'],
                    ] );
                    $result['payment_warning'] = $pay_result['message'] ?? 'Error registrando el pago';
                }
            }

            return new \WP_REST_Response( $result, 200 );
        } );
    }

    public function cancel_booking( \WP_REST_Request $request ) {
        return $this->safe_response( function () use ( $request ) {
            $code   = $request['code'];
            $repo   = new \Artechia\PMS\Repositories\BookingRepository();
            $booking = $repo->find_by_code( $code );

            if ( ! $booking ) {
                return new \WP_REST_Response( [ 'ok' => false, 'error' => 'NOT_FOUND', 'message' => 'Reserva no encontrada' ], 404 );
            }

            $reason = sanitize_text_field( $request->get_param( 'reason' ) ?? '' );
            $refund_amount = (float) ( $request->get_param( 'refund_amount' ) ?? 0 );

            $service = new BookingService();
            $result  = $service->cancel_booking( $booking['id'], $reason, $refund_amount );

            if ( isset( $result['error'] ) ) {
                return new \WP_REST_Response( [
                    'ok'      => false,
                    'error'   => $result['error'],
                    'message' => $result['message'],
                ], 400 );
            }

            return new \WP_REST_Response( array_merge( [ 'ok' => true ], $result ), 200 );
        } );
    }

    public function get_cancellation_penalty( \WP_REST_Request $request ) {
        return $this->safe_response( function () use ( $request ) {
            $code   = $request['code'];
            $repo   = new \Artechia\PMS\Repositories\BookingRepository();
            $booking = $repo->find_by_code( $code );

            if ( ! $booking ) {
                return new \WP_REST_Response( [ 'ok' => false, 'error' => 'NOT_FOUND', 'message' => 'Reserva no encontrada' ], 404 );
            }

            $service = new BookingService();
            $penalty = $service->calculate_penalty( $booking );

            $policy_info = null;
            $is_custom_pricing = false;

            // Detect custom pricing from snapshot
            $snapshot = json_decode( $booking['pricing_snapshot'] ?? '{}', true );
            if ( ! empty( $snapshot['nights'] ) ) {
                foreach ( $snapshot['nights'] as $n ) {
                    if ( ( $n['source'] ?? '' ) === 'custom' ) {
                        $is_custom_pricing = true;
                        break;
                    }
                }
            }

            if ( $is_custom_pricing ) {
                // For custom-priced bookings, suggest full paid amount as penalty
                $paid = (float) ( $booking['amount_paid'] ?? 0 );
                if ( $penalty <= 0 && $paid > 0 ) {
                    $penalty = $paid;
                }
                $policy_info = [
                    'cancellation_type'          => 'custom',
                    'cancellation_deadline_days' => 0,
                    'penalty_type'               => '100',
                    'penalty_value'              => 100,
                    'is_refundable'              => 1,
                ];
            } elseif ( ! empty( $booking['rate_plan_id'] ) ) {
                $rp_repo = new \Artechia\PMS\Repositories\RatePlanRepository();
                $rate_plan = $rp_repo->find( (int) $booking['rate_plan_id'] );
                if ( $rate_plan ) {
                    $policy_info = [
                        'cancellation_type'          => $rate_plan['cancellation_type'],
                        'cancellation_deadline_days' => $rate_plan['cancellation_deadline_days'],
                        'penalty_type'               => $rate_plan['penalty_type'],
                        'penalty_value'              => $rate_plan['penalty_value'],
                        'is_refundable'              => $rate_plan['is_refundable']
                    ];
                }
            }

            return new \WP_REST_Response( [ 'ok' => true, 'penalty_amount' => $penalty, 'policy' => $policy_info ], 200 );
        } );
    }

    public function record_booking_payment( \WP_REST_Request $request ) {
        return $this->safe_response( function () use ( $request ) {
            $code   = $request['code'];
            $repo   = new \Artechia\PMS\Repositories\BookingRepository();
            $booking = $repo->find_by_code( $code );

            if ( ! $booking ) {
                return new \WP_REST_Response( [ 'error' => 'NOT_FOUND', 'message' => 'Reserva no encontrada' ], 404 );
            }

            $amount = (float) $request->get_param( 'amount' );
            $note   = sanitize_text_field( $request->get_param( 'note' ) ?? '' );
            $method = sanitize_text_field( $request->get_param( 'method' ) ?? '' );

            $service = new BookingService();
            $result  = $service->record_manual_payment( $booking['id'], $amount, $note, $method );

            if ( isset( $result['error'] ) ) {
                return new \WP_REST_Response( $result, 400 );
            }

            return new \WP_REST_Response( $result, 200 );
        } );
    }

    public function delete_booking_payment( \WP_REST_Request $request ) {
        return $this->safe_response( function () use ( $request ) {
            $code       = $request['code'];
            $payment_id = (int) $request['payment_id'];
            $repo       = new \Artechia\PMS\Repositories\BookingRepository();
            $booking    = $repo->find_by_code( $code );

            if ( ! $booking ) {
                return new \WP_REST_Response( [ 'error' => 'NOT_FOUND', 'message' => 'Reserva no encontrada' ], 404 );
            }

            $service = new BookingService();
            $result  = $service->delete_payment( $booking['id'], $payment_id );

            if ( isset( $result['error'] ) ) {
                return new \WP_REST_Response( $result, 400 );
            }

            return new \WP_REST_Response( $result, 200 );
        } );
    }

    public function reactivate_booking( \WP_REST_Request $request ) {
        return $this->safe_response( function () use ( $request ) {
            $code   = $request['code'];
            $repo   = new \Artechia\PMS\Repositories\BookingRepository();
            $booking = $repo->find_by_code( $code );

            if ( ! $booking ) {
                return new \WP_REST_Response( [ 'error' => 'NOT_FOUND', 'message' => 'Reserva no encontrada' ], 404 );
            }

            $params = $request->get_json_params() ?: [];
            $target = sanitize_text_field( $params['status'] ?? 'confirmed' );

            $service = new BookingService();
            $result  = $service->reactivate_booking( $booking['id'], $target );

            if ( isset( $result['error'] ) ) {
                return new \WP_REST_Response( $result, 400 );
            }

            return new \WP_REST_Response( $result, 200 );
        } );
    }

    public function change_booking_status( \WP_REST_Request $request ) {
        return $this->safe_response( function () use ( $request ) {
            $code   = $request['code'];
            $repo   = new \Artechia\PMS\Repositories\BookingRepository();
            $booking = $repo->find_by_code( $code );

            if ( ! $booking ) {
                return new \WP_REST_Response( [ 'error' => 'NOT_FOUND', 'message' => 'Reserva no encontrada' ], 404 );
            }

            $params     = $request->get_json_params() ?: [];
            $new_status = sanitize_text_field( $params['status'] ?? '' );

            $service = new BookingService();
            $result  = $service->change_status( $booking['id'], $new_status );

            if ( isset( $result['error'] ) ) {
                return new \WP_REST_Response( $result, 400 );
            }

            return new \WP_REST_Response( $result, 200 );
        } );
    }

    // ── Email Templates Implementation ──

    public function get_email_templates( \WP_REST_Request $request ) {
        $repo = new \Artechia\PMS\Repositories\EmailTemplateRepository();
        $args = [];
        if ( $request->get_param( 'property_id' ) !== null ) {
            $args['where']['property_id'] = $request->get_param( 'property_id' );
        }
        return new \WP_REST_Response( $repo->find_all( $args ), 200 );
    }

    public function get_email_template( \WP_REST_Request $request ) {
        $id   = (int) $request['id'];
        $repo = new \Artechia\PMS\Repositories\EmailTemplateRepository();
        $item = $repo->find( $id );
        if ( ! $item ) {
            return new \WP_Error( 'not_found', 'Template not found', [ 'status' => 404 ] );
        }
        return new \WP_REST_Response( $item, 200 );
    }

    public function create_email_template( \WP_REST_Request $request ) {
        return $this->safe_response( function () use ( $request ) {
            $repo = new \Artechia\PMS\Repositories\EmailTemplateRepository();

            $data = [
                'event_type' => isset( $request['event_type'] ) ? sanitize_text_field( $request['event_type'] ?? '' ) : 'marketing_custom',
                'is_active'  => 1,
            ];

            if ( isset( $request['subject'] ) ) {
                $data['subject'] = sanitize_text_field( $request['subject'] ?? '' );
            }
            if ( isset( $request['body_html'] ) ) {
                // Ensure WP keeps the HTML tags
                $data['body_html'] = wp_unslash( $request['body_html'] );
            }

            $id = $repo->create( $data );
            if ( false === $id ) {
                return new \WP_Error( 'create_failed', 'Could not create template.', [ 'status' => 400 ] );
            }

            return new \WP_REST_Response( [ 'success' => true, 'id' => $id ], 201 );
        } );
    }

    public function update_email_template( \WP_REST_Request $request ) {
        return $this->safe_response( function () use ( $request ) {
            $id   = (int) $request['id'];
            $repo = new \Artechia\PMS\Repositories\EmailTemplateRepository();

            $data = [];
            if ( isset( $request['subject'] ) ) {
                $data['subject'] = sanitize_text_field( $request['subject'] ?? '' );
            }
            if ( isset( $request['body_html'] ) ) {
                $data['body_html'] = wp_unslash( $request['body_html'] );
            }
            if ( isset( $request['is_active'] ) ) {
                $data['is_active'] = (int) $request['is_active'];
            }

            $updated = $repo->update( $id, $data );
            if ( false === $updated ) {
                return new \WP_Error( 'update_failed', 'Could not update template.', [ 'status' => 400 ] );
            }
            return new \WP_REST_Response( [ 'success' => true ], 200 );
        } );
    }

    public function preview_email_template( \WP_REST_Request $request ) {
        $id           = (int) $request['id'];
        $booking_code = sanitize_text_field( $request->get_param( 'booking_code' ) ?? '' );
        $subject      = sanitize_text_field( $request->get_param( 'subject' ) ?? '' );
        $body         = wp_unslash( $request->get_param( 'body_html' ) );

        $booking_repo = new \Artechia\PMS\Repositories\BookingRepository();
        $booking      = $booking_repo->find_by_code( $booking_code );

        if ( ! $booking ) {
            return new \WP_Error( 'not_found', 'Booking not found', [ 'status' => 404 ] );
        }

        $service = new \Artechia\PMS\Services\EmailService();
        // Fallback to DB if subject/body not provided.
        if ( empty( $subject ) || empty( $body ) ) {
            $repo = new \Artechia\PMS\Repositories\EmailTemplateRepository();
            $tmpl = $repo->find( $id );
            if ( $tmpl ) {
                $subject = $subject ?: ( $tmpl['subject'] ?: 'Sin Asunto' );
                $body    = $body ?: ( $tmpl['body_html'] ?: '<p>Preview test.</p>' );
            }
        }

        $preview = $service->render_custom( $booking['id'], $subject, $body );
        return new \WP_REST_Response( $preview, 200 );
    }

    public function send_test_email( \WP_REST_Request $request ) {
        return $this->safe_response( function () use ( $request ) {
            // Rate Limit: 10 per minute per user
            if ( ! \Artechia\PMS\RateLimiter::check( 'email_test_send', 10, 60 ) ) {
                return new \WP_REST_Response( [ 
                    'error'   => 'RATE_LIMITED', 
                    'message' => 'Too many test emails. Try again in a minute.' 
                ], 429 );
            }

            $id           = (int) $request['id'];
            $booking_code = sanitize_text_field( $request->get_param( 'booking_code' ) ?? '' );
            $to           = sanitize_email( $request->get_param( 'to_email' ) );

            if ( empty( $to ) ) {
                $to = get_option( 'admin_email' ) ?: 'test@artechia.com';
            }

            $booking_repo = new \Artechia\PMS\Repositories\BookingRepository();
            $booking      = $booking_repo->find_by_code( $booking_code );
            if ( ! $booking ) {
                return new \WP_Error( 'not_found', 'Booking not found', [ 'status' => 404 ] );
            }

            $repo = new \Artechia\PMS\Repositories\EmailTemplateRepository();
            $tmpl = $repo->find( $id );
            if ( ! $tmpl ) {
                return new \WP_Error( 'not_found', 'Template not found', [ 'status' => 404 ] );
            }

            $subject = ! empty( $tmpl['subject'] ) ? $tmpl['subject'] : 'Asunto de Prueba';
            $body    = ! empty( $tmpl['body_html'] ) ? $tmpl['body_html'] : '<p>Mensaje de prueba.</p>';

            $service = new \Artechia\PMS\Services\EmailService();
            $render  = $service->render_custom( $booking['id'], $subject, $body );

            $attachments = [];
            if ( isset( $tmpl['event_type'] ) && $tmpl['event_type'] === 'booking_confirmed' ) {
                $ics_path = $service->generate_ics_attachment( $booking );
                if ( $ics_path ) {
                    $attachments[] = $ics_path;
                }
            }

            $sent = wp_mail( $to, '[TEST] ' . $render['subject'], $render['body'], [ 'Content-Type: text/html; charset=UTF-8' ], $attachments );

            return new \WP_REST_Response( [ 'sent' => $sent, 'to' => $to, 'message' => $sent ? '' : 'WP_Mail failed.' ], 200 );
        } );
    }

    /* ── Manual Booking Setup ───────────────────────── */

    public function get_setup_room_types( \WP_REST_Request $request ) {
        return $this->safe_response( function () {
            global $wpdb;
            $table = \Artechia\PMS\DB\Schema::table( 'room_types' );
            $rows  = $wpdb->get_results( "SELECT id, property_id, name FROM {$table} WHERE status = 'active' ORDER BY name ASC", \ARRAY_A );
            
            $data = array_map( function( $r ) {
                return [
                    'id'          => (int) $r['id'],
                    'property_id' => (int) $r['property_id'],
                    'name'        => $r['name'],
                ];
            }, $rows ?: [] );

            return new \WP_REST_Response( $data, 200 );
        } );
    }

    public function get_setup_room_units( \WP_REST_Request $request ) {
        return $this->safe_response( function () {
            $repo = new \Artechia\PMS\Repositories\RoomUnitRepository();
            $units = $repo->all_with_type( [ 'limit' => 500 ] );
            
            $data = array_map( function( $u ) {
                return [
                    'id'             => (int) $u['id'],
                    'room_type_id'   => (int) $u['room_type_id'],
                    'property_id'    => (int) ( $u['property_id'] ?? 0 ),
                    'name'           => $u['name'],
                    'room_type_name' => $u['room_type_name'] ?? '',
                ];
            }, $units ?: [] );

            return new \WP_REST_Response( $data, 200 );
        } );
    }

    public function get_setup_rate_plans( \WP_REST_Request $request ) {
        return $this->safe_response( function () {
            global $wpdb;
            $table = \Artechia\PMS\DB\Schema::table( 'rate_plans' );
            $rows  = $wpdb->get_results( "SELECT id, property_id, name FROM {$table} WHERE status = 'active' ORDER BY name ASC", \ARRAY_A );
            
            $data = array_map( function( $r ) {
                return [
                    'id'          => (int) $r['id'],
                    'property_id' => (int) $r['property_id'],
                    'name'        => $r['name'],
                ];
            }, $rows ?: [] );

            return new \WP_REST_Response( $data, 200 );
        } );
    }

    public function populate_demo_data( \WP_REST_Request $request ) {
        return $this->safe_response( function () {
            $setup_svc = new \Artechia\PMS\Services\SetupService();
            $result    = $setup_svc->generate_demo_data();
            
            if ( ! empty( $result['already'] ) ) {
                return new \WP_REST_Response( [ 
                    'ok'          => true, 
                    'already'     => true, 
                    'property_id' => $result['property_id'],
                    'message'     => 'Demo data already exists.' 
                ], 200 );
            }

            return new \WP_REST_Response( array_merge( [ 'ok' => true ], $result ), 201 );
        } );
    }

    public function get_setup_properties( \WP_REST_Request $request ) {
        return $this->safe_response( function () use ( $request ) {
            $include_demo = (bool) $request->get_param( 'include_demo' );
            $repo = new \Artechia\PMS\Repositories\PropertyRepository();
            
            $where = [ 'status' => 'active' ];
            if ( ! $include_demo ) {
                $where['is_demo'] = 0;
            }

            $props = $repo->all( [ 'where' => $where, 'orderby' => 'name', 'order' => 'ASC' ] );
            
            // Map to simplified structure
            $data = array_map( function( $p ) {
                $logo_url = \Artechia\PMS\Services\Settings::get( 'logo_url', '', (int) $p['id'] );
                if ( ! $logo_url ) {
                    $logo_url = \Artechia\PMS\Services\Settings::get( 'logo_url', '' ); // Fallback to global
                }
                
                return [
                    'id'       => (int) $p['id'],
                    'name'     => $p['name'],
                    'logo_url' => $logo_url,
                    'is_demo'  => (bool) (int) ($p['is_demo'] ?? 0),
                ];
            }, $props );

            return new \WP_REST_Response( $data, 200 );
        } );
    }

    public function create_manual_booking( \WP_REST_Request $request ) {
        return $this->safe_response( function () use ( $request ) {
            $service = new BookingService();
            $params  = $request->get_params();

            // Map flat request params to service expectations
            $data = [
                'property_id'   => $params['property_id'] ?? 0,
                'room_type_id'  => $params['room_type_id'] ?? 0,
                'room_unit_id'  => $params['room_unit_id'] ?? null,
                'rate_plan_id'  => $params['rate_plan_id'] ?? 0,
            'custom_price_per_night' => $params['custom_price_per_night'] ?? 0,
                'check_in'      => $params['check_in'] ?? '',
                'check_out'     => $params['check_out'] ?? '',
                'adults'        => $params['adults'] ?? 1,
                'children'      => $params['children'] ?? 0,
                'status'        => $params['status'] ?? 'pending',
                'notes'         => $params['notes'] ?? '',
                'amount_paid'   => $params['amount_paid'] ?? 0,
                'payment_status'=> $params['payment_status'] ?? 'unpaid',
                'payment_method'=> $params['payment_method'] ?? 'manual',
                'send_review_email' => ! empty( $params['send_review_email'] ),
                'guest'         => [
                    'first_name'      => $params['guest_first_name'] ?? '',
                    'last_name'       => $params['guest_last_name'] ?? '',
                    'email'           => $params['guest_email'] ?? '',
                    'phone'           => $params['guest_phone'] ?? '',
                    'document_type'   => $params['guest_document_type'] ?? '',
                    'document_number' => $params['guest_document_number'] ?? '',
                ],
            ];

            $result = $service->create_manual_booking( $data );

            if ( isset( $result['error'] ) ) {
                return new \WP_REST_Response( [
                    'error'        => $result['error'],
                    'message'      => $result['message'],
                    'request_id'   => wp_generate_uuid4(),
                ], 400 );
            }

            return new \WP_REST_Response( $result, 201 );
        } );
    }

    /**
     * Update an existing booking (admin edit).
     */
    public function update_booking( \WP_REST_Request $request ) {
        return $this->safe_response( function () use ( $request ) {
            $code   = $request['code'];
            $params = $request->get_params();
            $repo   = new \Artechia\PMS\Repositories\BookingRepository();
            $booking = $repo->find_by_code( $code );

            if ( ! $booking ) {
                return new \WP_Error( 'not_found', 'Booking not found', [ 'status' => 404 ] );
            }

            $id = (int) $booking['id'];

            // Prevent editing finalized bookings
            if ( in_array( $booking['status'], [ 'checked_out', 'cancelled' ] ) ) {
                return new \WP_REST_Response( [
                    'error' => 'INVALID_STATUS',
                    'message' => 'No se puede editar una reserva finalizada o cancelada.',
                ], 400 );
            }

            // 1. Update guest fields
            $guest_data = [];
            foreach ( [ 'guest_first_name', 'guest_last_name', 'guest_email', 'guest_phone', 'guest_document_type', 'guest_document_number' ] as $field ) {
                if ( isset( $params[ $field ] ) ) {
                    $guest_data[ $field ] = sanitize_text_field( $params[ $field ] );
                }
            }
            if ( ! empty( $guest_data ) ) {
                $repo->update( $id, $guest_data );
            }

            // 2. Update adults/children
            $booking_data = [];
            if ( isset( $params['adults'] ) )   $booking_data['adults']   = (int) $params['adults'];
            if ( isset( $params['children'] ) ) $booking_data['children'] = (int) $params['children'];
            if ( ! empty( $booking_data ) ) {
                $repo->update( $id, $booking_data );
            }

            // 2b. Append new note with timestamp
            if ( ! empty( $params['notes'] ) ) {
                $svc = new BookingService();
                $svc->add_note( $id, sanitize_textarea_field( $params['notes'] ) );
            }

            // 3. Update dates if changed
            $new_ci = isset( $params['check_in'] )  ? sanitize_text_field( $params['check_in'] )  : $booking['check_in'];
            $new_co = isset( $params['check_out'] ) ? sanitize_text_field( $params['check_out'] ) : $booking['check_out'];
            $dates_changed = ( $new_ci !== $booking['check_in'] || $new_co !== $booking['check_out'] );

            if ( $dates_changed ) {
                // Validate availability (excluding self)
                $rooms = $repo->get_rooms( $id );
                $room  = $rooms[0] ?? null;
                $unit_id = $room ? (int) $room['room_unit_id'] : 0;

                if ( $unit_id && ! $repo->is_unit_available( $unit_id, (int) $booking['property_id'], $new_ci, $new_co, $id ) ) {
                    return new \WP_REST_Response( [
                        'error'   => 'UNAVAILABLE',
                        'message' => 'La unidad no está disponible para esas fechas.',
                    ], 400 );
                }

                // Re-quote pricing
                $service = new BookingService();
                $pricing = new \Artechia\PMS\Services\PricingService();
                $extras  = $repo->get_extras( $id );
                $extra_map = [];
                foreach ( $extras as $e ) $extra_map[ $e['extra_id'] ] = $e['quantity'];

                $room_type_id = $room ? (int) $room['room_type_id'] : 0;
                $adults   = $booking_data['adults']   ?? (int) $booking['adults'];
                $children = $booking_data['children'] ?? (int) $booking['children'];

                $quote = $pricing->quote(
                    (int) $booking['property_id'],
                    $room_type_id,
                    (int) $booking['rate_plan_id'],
                    $new_ci, $new_co,
                    $adults, $children,
                    $extra_map
                );

                if ( isset( $quote['error'] ) ) {
                    return new \WP_REST_Response( $quote, 400 );
                }

                $repo->update( $id, [
                    'check_in'         => $new_ci,
                    'check_out'        => $new_co,
                    'nights'           => count( $quote['nights'] ),
                    'subtotal'         => $quote['totals']['subtotal_base'],
                    'extras_total'     => $quote['totals']['extras_total'],
                    'taxes_total'      => $quote['totals']['taxes_total'],
                    'discount_total'   => $quote['totals']['discount_total'],
                    'grand_total'      => $quote['totals']['total'],
                    'pricing_snapshot' => wp_json_encode( $quote ),
                ] );

                // Update booking room pricing
                if ( $room ) {
                    global $wpdb;
                    $br_table = \Artechia\PMS\DB\Schema::table('booking_rooms');
                    $wpdb->update( $br_table, [
                        'rate_per_night_json' => wp_json_encode( $quote['nights'] ),
                        'subtotal'           => $quote['totals']['subtotal_base'],
                    ], [ 'booking_id' => $id ] );
                }

                $service->add_note( $id, "Editado: fechas cambiadas a {$new_ci} → {$new_co}" );
            }

            // 4. Update room unit if changed
            if ( ! empty( $params['room_unit_id'] ) ) {
                $new_unit_id = (int) $params['room_unit_id'];
                $rooms = $repo->get_rooms( $id );
                $room  = $rooms[0] ?? null;
                $current_unit_id = $room ? (int) $room['room_unit_id'] : 0;

                if ( $new_unit_id !== $current_unit_id ) {
                    $ci = $new_ci ?? $booking['check_in'];
                    $co = $new_co ?? $booking['check_out'];

                    if ( ! $repo->is_unit_available( $new_unit_id, (int) $booking['property_id'], $ci, $co, $id ) ) {
                        return new \WP_REST_Response( [
                            'error'   => 'UNAVAILABLE',
                            'message' => 'La unidad seleccionada no está disponible para esas fechas.',
                        ], 400 );
                    }

                    global $wpdb;
                    $br_table = \Artechia\PMS\DB\Schema::table('booking_rooms');
                    $wpdb->update( $br_table, [
                        'room_unit_id' => $new_unit_id,
                    ], [ 'booking_id' => $id ] );

                    $unit_repo = new \Artechia\PMS\Repositories\RoomUnitRepository();
                    $unit = $unit_repo->find( $new_unit_id );
                    $unit_name = $unit ? $unit['name'] : "#{$new_unit_id}";
                    $service = isset( $service ) ? $service : new BookingService();
                    $service->add_note( $id, "Unidad cambiada a: {$unit_name}" );
                }
            }

            // Refresh and return updated booking
            $updated = $repo->find_by_code( $code );
            return new \WP_REST_Response( $updated, 200 );
        } );
    }

    /**
     * Get count of guests for marketing.
     */
    public function get_marketing_guest_count( \WP_REST_Request $request ) {
        return $this->safe_response( function () use ( $request ) {
            $filters = [
                'property_id' => $request->get_param( 'property_id' )
            ];
            $marketing_svc = new \Artechia\PMS\Services\MarketingService();
            $guests = $marketing_svc->filter_guests( $filters );
            return new \WP_REST_Response( [ 'count' => count( $guests ) ], 200 );
        } );
    }

    /**
     * Get full list of guests for marketing with name, email, last booking.
     */
    public function get_marketing_guests( \WP_REST_Request $request ) {
        return $this->safe_response( function () use ( $request ) {
            global $wpdb;
            $filters = [
                'property_id' => $request->get_param( 'property_id' )
            ];
            $marketing_svc = new \Artechia\PMS\Services\MarketingService();
            $guests = $marketing_svc->filter_guests( $filters );

            // Batch last-booking lookup in a single query instead of N queries
            $b_table = \Artechia\PMS\DB\Schema::table( 'bookings' );
            $last_booking_map = [];
            $rows = $wpdb->get_results(
                "SELECT guest_id, MAX(check_in) AS last_ci FROM {$b_table} GROUP BY guest_id",
                ARRAY_A
            );
            foreach ( $rows as $r ) {
                $last_booking_map[ (int) $r['guest_id'] ] = $r['last_ci'];
            }
            foreach ( $guests as &$g ) {
                $g['last_booking'] = $last_booking_map[ (int) $g['id'] ] ?? null;
            }

            return new \WP_REST_Response( $guests, 200 );
        } );
    }

    /**
     * Send marketing campaign.
     */
    public function send_marketing_campaign( \WP_REST_Request $request ) {
        Logger::info( 'rest.marketing_send_request', 'Received request to send marketing campaign', 'rest_api' );
        return $this->safe_response( function () use ( $request ) {
            $params      = $request->get_json_params();
            $template_id = (int) ( $params['template_id'] ?? 0 );
            $property_id = (int) ( $params['property_id'] ?? 0 );
            $guest_ids   = $params['guest_ids'] ?? [];
            $subject     = sanitize_text_field( $params['subject'] ?? '' );
            // Avoid wp_kses_post for marketing emails because it strips <head>, <style>, and <body> tags, breaking templates or causing them to be blank.
            $body_html   = current_user_can('unfiltered_html') ? ( $params['body_html'] ?? '' ) : wp_kses_post( $params['body_html'] ?? '' );
            $promo_code  = sanitize_text_field( $params['promo_code'] ?? '' );
            $promo_desc  = sanitize_text_field( $params['promo_description'] ?? '' );

            if ( ! $template_id ) {
                return new \WP_REST_Response( [ 'error' => 'MISSING_TEMPLATE', 'message' => 'Selecciona una plantilla.' ], 400 );
            }

            $marketing_svc = new \Artechia\PMS\Services\MarketingService();

            // If specific guest IDs were provided, use those; otherwise fall back to property filter
            if ( ! empty( $guest_ids ) ) {
                $guest_ids = array_map( 'intval', $guest_ids );
            } else {
                $guests    = $marketing_svc->filter_guests( [ 'property_id' => $property_id ] );
                $guest_ids = array_map( fn($g) => (int) $g['id'], $guests );
            }

            if ( empty( $guest_ids ) ) {
                return new \WP_REST_Response( [ 'error' => 'NO_GUESTS', 'message' => 'No hay destinatarios seleccionados.' ], 400 );
            }

            $extra = [];
            if ( $promo_code )  $extra['promo_code']        = $promo_code;
            if ( $promo_desc )  $extra['promo_description'] = $promo_desc;
            if ( $subject )     $extra['custom_subject']    = $subject;
            if ( $body_html )   $extra['custom_body_html']  = $body_html;

            $result = $marketing_svc->send_campaign( $template_id, $guest_ids, $extra );
            return new \WP_REST_Response( $result, 200 );
        } );
    }

    /**
     * Get marketing campaign history from audit log.
     */
    public function get_marketing_history( \WP_REST_Request $request ) {
        return $this->safe_response( function () {
            global $wpdb;
            $log_table = \Artechia\PMS\DB\Schema::table( 'audit_log' );
            
            $rows = $wpdb->get_results(
                "SELECT id, message, context_json, created_at 
                 FROM {$log_table} 
                 WHERE event_type = 'marketingcampaign_finished' 
                 ORDER BY created_at DESC 
                 LIMIT 50",
                ARRAY_A
            );

            // Collect all campaign_ids, then batch-fetch opens + clicks in ONE query
            $parsed_rows = [];
            $campaign_ids = [];
            foreach ( $rows as $row ) {
                $sent = 0;
                $errors = 0;
                if ( preg_match( '/Sent:\s*(\d+)/', $row['message'], $m ) ) {
                    $sent = (int) $m[1];
                }
                if ( preg_match( '/Errors:\s*(\d+)/', $row['message'], $m ) ) {
                    $errors = (int) $m[1];
                }
                $ctx = ! empty( $row['context_json'] ) ? json_decode( $row['context_json'], true ) : [];
                $cid = $ctx['campaign_id'] ?? null;
                if ( $cid ) {
                    $campaign_ids[] = $cid;
                }
                $parsed_rows[] = [ 'row' => $row, 'sent' => $sent, 'errors' => $errors, 'ctx' => $ctx, 'cid' => $cid ];
            }

            // Single query: count opens & clicks per campaign_id
            $tracking_map = []; // campaign_id => [ opens, clicks ]
            if ( $campaign_ids ) {
                $like_clauses = [];
                foreach ( $campaign_ids as $cid ) {
                    $like_clauses[] = $wpdb->prepare( 'context_json LIKE %s', '%' . $wpdb->esc_like( $cid ) . '%' );
                }
                $or_clause = implode( ' OR ', $like_clauses );
                $tracking_rows = $wpdb->get_results(
                    "SELECT event_type, context_json
                     FROM {$log_table}
                     WHERE event_type IN ('emailopen','emailclick') AND ({$or_clause})",
                    ARRAY_A
                );
                foreach ( $tracking_rows as $tr ) {
                    foreach ( $campaign_ids as $cid ) {
                        if ( strpos( $tr['context_json'], $cid ) !== false ) {
                            if ( ! isset( $tracking_map[ $cid ] ) ) {
                                $tracking_map[ $cid ] = [ 'opens' => 0, 'clicks' => 0 ];
                            }
                            if ( $tr['event_type'] === 'emailopen' ) {
                                $tracking_map[ $cid ]['opens']++;
                            } else {
                                $tracking_map[ $cid ]['clicks']++;
                            }
                            break;
                        }
                    }
                }
            }

            $campaigns = [];
            foreach ( $parsed_rows as $p ) {
                $cid = $p['cid'];
                $campaigns[] = [
                    'id'            => $p['row']['id'],
                    'sent_count'    => $p['sent'],
                    'errors'        => $p['errors'],
                    'opens'         => $cid ? ( $tracking_map[ $cid ]['opens'] ?? 0 ) : 0,
                    'clicks'        => $cid ? ( $tracking_map[ $cid ]['clicks'] ?? 0 ) : 0,
                    'created_at'    => $p['row']['created_at'],
                    'message'       => $p['row']['message'],
                    'template_name' => $p['ctx']['template_name'] ?? null,
                    'promo_code'    => $p['ctx']['promo_code'] ?? null,
                    'recipients'    => $p['ctx']['recipients'] ?? [],
                    'error_details' => $p['ctx']['error_details'] ?? [],
                ];
            }

            return new \WP_REST_Response( $campaigns, 200 );
        } );
    }
    public function get_promotions( \WP_REST_Request $request ) {
        return $this->safe_response( function() {
            global $wpdb;
            $repo = new PromotionRepository();
            $promos = $repo->all();

            // Batch usage counts in a single query instead of N queries
            $bookings_table = \Artechia\PMS\DB\Schema::table( 'bookings' );
            $usage_map = [];
            $rows = $wpdb->get_results(
                "SELECT coupon_code, COUNT(*) AS cnt FROM {$bookings_table} WHERE coupon_code IS NOT NULL AND coupon_code != '' AND status NOT IN ('cancelled') GROUP BY coupon_code",
                ARRAY_A
            );
            foreach ( $rows as $r ) {
                $usage_map[ $r['coupon_code'] ] = (int) $r['cnt'];
            }
            foreach ( $promos as &$p ) {
                $p['usage_count'] = $usage_map[ $p['name'] ] ?? 0;
            }

            return new \WP_REST_Response( $promos, 200 );
        } );
    }

    public function create_promotion( \WP_REST_Request $request ) {
        return $this->safe_response( function() use ( $request ) {
            $params = $request->get_json_params();
            $repo = new PromotionRepository();
            $id = $repo->create( $params );
            $this->clear_calendar_cache();
            return new \WP_REST_Response( [ 'id' => $id ], 201 );
        } );
    }

    public function update_promotion( \WP_REST_Request $request ) {
        return $this->safe_response( function() use ( $request ) {
            $id = (int) $request->get_param( 'id' );
            $params = $request->get_json_params();
            $repo = new PromotionRepository();
            $repo->update( $id, $params );
            $this->clear_calendar_cache();
            return new \WP_REST_Response( [ 'success' => true ], 200 );
        } );
    }

    public function delete_promotion( \WP_REST_Request $request ) {
        return $this->safe_response( function() use ( $request ) {
            $id = (int) $request->get_param( 'id' );
            $repo = new PromotionRepository();
            $repo->delete( $id );
            $this->clear_calendar_cache();
            return new \WP_REST_Response( [ 'success' => true ], 200 );
        } );
    }

    /**
     * Clear all calendar-hints transient caches.
     */
    private function clear_calendar_cache(): void {
        global $wpdb;
        $wpdb->query(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_artechia_cal_hints_%' OR option_name LIKE '_transient_timeout_artechia_cal_hints_%'"
        );
    }

    /* ── Permissions ────────────────────────────────── */

    public function check_admin_permission() {
        return \Artechia\PMS\Plugin::instance()->check_admin_permission();
    }

    /**
     * GET /admin/debug/logs
     */
    public function get_debug_logs( \WP_REST_Request $request ) {
        return $this->safe_response( function() {
            return \Artechia\PMS\Logger::query( [ 'limit' => 50 ] );
        } );
    }

    /* ── Property CRUD ──────────────────────────────── */

    public function list_properties( \WP_REST_Request $request ) {
        return $this->safe_response( function () {
            $repo = new \Artechia\PMS\Repositories\PropertyRepository();
            return $repo->all( [ 'orderby' => 'name', 'order' => 'ASC', 'limit' => 100 ] );
        } );
    }

    public function get_property( \WP_REST_Request $request ) {
        return $this->safe_response( function () use ( $request ) {
            $repo = new \Artechia\PMS\Repositories\PropertyRepository();
            $item = $repo->find( (int) $request['id'] );
            if ( ! $item ) return new \WP_Error( 'not_found', 'Propiedad no encontrada', [ 'status' => 404 ] );
            return $item;
        } );
    }

    public function save_property( \WP_REST_Request $request ) {
        return $this->safe_response( function () use ( $request ) {
            $repo = new \Artechia\PMS\Repositories\PropertyRepository();
            $params = $request->get_json_params() ?: $request->get_params();
            $data = [
                'name'        => sanitize_text_field( $params['name'] ?? '' ),
                'address'     => sanitize_text_field( $params['address'] ?? '' ),
                'description' => sanitize_textarea_field( $params['description'] ?? '' ),
                'status'      => sanitize_text_field( $params['status'] ?? 'active' ),
            ];
            if ( empty( $data['name'] ) ) {
                return new \WP_Error( 'missing_name', 'El nombre es obligatorio', [ 'status' => 400 ] );
            }
            $id = (int) ( $request['id'] ?? 0 );
            if ( $id ) {
                $repo->update( $id, $data );
                \Artechia\PMS\Logger::info( 'property.updated', "Property #{$id} updated", 'property', $id );
            } else {
                $id = $repo->create( $data );
                \Artechia\PMS\Logger::info( 'property.created', "Property #{$id} created", 'property', $id );
            }
            return new \WP_REST_Response( [ 'ok' => true, 'id' => $id ], 200 );
        } );
    }

    public function delete_property( \WP_REST_Request $request ) {
        return $this->safe_response( function () use ( $request ) {
            $repo = new \Artechia\PMS\Repositories\PropertyRepository();
            $id = (int) $request['id'];
            $repo->delete( $id );
            \Artechia\PMS\Logger::info( 'property.deleted', "Property #{$id} deleted", 'property', $id );
            return new \WP_REST_Response( [ 'ok' => true ], 200 );
        } );
    }

    /* ── Room Type CRUD ─────────────────────────────── */

    public function list_room_types( \WP_REST_Request $request ) {
        return $this->safe_response( function () use ( $request ) {
            $prop_repo = new \Artechia\PMS\Repositories\PropertyRepository();
            $property  = $prop_repo->get_default();
            $prop_id   = (int) ( $request->get_param( 'property_id' ) ?: ( $property['id'] ?? 0 ) );
            $repo      = new \Artechia\PMS\Repositories\RoomTypeRepository();
            return $repo->all_with_counts( $prop_id );
        } );
    }

    public function get_room_type( \WP_REST_Request $request ) {
        return $this->safe_response( function () use ( $request ) {
            $repo = new \Artechia\PMS\Repositories\RoomTypeRepository();
            $item = $repo->find( (int) $request['id'] );
            if ( ! $item ) return new \WP_Error( 'not_found', 'Tipo de habitación no encontrado', [ 'status' => 404 ] );
            return $item;
        } );
    }

    public function save_room_type( \WP_REST_Request $request ) {
        return $this->safe_response( function () use ( $request ) {
            $repo = new \Artechia\PMS\Repositories\RoomTypeRepository();
            $params = $request->get_json_params() ?: $request->get_params();

            // Resolve property_id
            $prop_id = absint( $params['property_id'] ?? 0 );
            if ( ! $prop_id ) {
                $prop_repo = new \Artechia\PMS\Repositories\PropertyRepository();
                $property  = $prop_repo->get_default();
                $prop_id   = (int) ( $property['id'] ?? 0 );
            }

            $data = [
                'property_id'    => $prop_id,
                'name'           => sanitize_text_field( $params['name'] ?? '' ),
                'description'    => sanitize_textarea_field( $params['description'] ?? '' ),
                'max_adults'     => absint( $params['max_adults'] ?? 2 ),
                'max_children'   => absint( $params['max_children'] ?? 0 ),
                'max_occupancy'  => absint( $params['max_occupancy'] ?? 2 ),
                'base_occupancy' => absint( $params['base_occupancy'] ?? 2 ),
                'amenities_json' => wp_json_encode( $params['amenities'] ?? [] ),
                'photos_json'    => wp_json_encode( $params['photos'] ?? [] ),
                'sort_order'     => absint( $params['sort_order'] ?? 0 ),
                'status'         => sanitize_text_field( $params['status'] ?? 'active' ),
            ];
            if ( empty( $data['name'] ) ) {
                return new \WP_Error( 'missing_name', 'El nombre es obligatorio', [ 'status' => 400 ] );
            }
            $id = (int) ( $request['id'] ?? 0 );
            if ( $id ) {
                $repo->update( $id, $data );
                \Artechia\PMS\Logger::info( 'room_type.updated', "Room type #{$id} updated", 'room_type', $id );
            } else {
                $id = $repo->create( $data );
                \Artechia\PMS\Logger::info( 'room_type.created', "Room type #{$id} created", 'room_type', $id );
            }
            return new \WP_REST_Response( [ 'ok' => true, 'id' => $id ], 200 );
        } );
    }

    public function delete_room_type( \WP_REST_Request $request ) {
        return $this->safe_response( function () use ( $request ) {
            $repo = new \Artechia\PMS\Repositories\RoomTypeRepository();
            $id   = (int) $request['id'];
            $repo->delete( $id );
            \Artechia\PMS\Logger::info( 'room_type.deleted', "Room type #{$id} deleted", 'room_type', $id );
            return new \WP_REST_Response( [ 'ok' => true ], 200 );
        } );
    }

    /* ── Room Unit CRUD ─────────────────────────────── */

    public function list_room_units( \WP_REST_Request $request ) {
        return $this->safe_response( function () use ( $request ) {
            $prop_repo = new \Artechia\PMS\Repositories\PropertyRepository();
            $property  = $prop_repo->get_default();
            $prop_id   = (int) ( $request->get_param( 'property_id' ) ?: ( $property['id'] ?? 0 ) );
            $repo      = new \Artechia\PMS\Repositories\RoomUnitRepository();
            return $repo->all_with_type( [ 'where' => [ 'property_id' => $prop_id ] ] );
        } );
    }

    public function get_room_unit( \WP_REST_Request $request ) {
        return $this->safe_response( function () use ( $request ) {
            $repo = new \Artechia\PMS\Repositories\RoomUnitRepository();
            $item = $repo->find( (int) $request['id'] );
            if ( ! $item ) return new \WP_Error( 'not_found', 'Habitación no encontrada', [ 'status' => 404 ] );
            return $item;
        } );
    }

    public function save_room_unit( \WP_REST_Request $request ) {
        return $this->safe_response( function () use ( $request ) {
            $repo   = new \Artechia\PMS\Repositories\RoomUnitRepository();
            $params = $request->get_json_params() ?: $request->get_params();

            // Resolve property_id
            $prop_id = absint( $params['property_id'] ?? 0 );
            if ( ! $prop_id ) {
                $prop_repo = new \Artechia\PMS\Repositories\PropertyRepository();
                $property  = $prop_repo->get_default();
                $prop_id   = (int) ( $property['id'] ?? 0 );
            }

            $data = [
                'room_type_id' => absint( $params['room_type_id'] ?? 0 ),
                'property_id'  => $prop_id,
                'name'         => sanitize_text_field( $params['name'] ?? '' ),
                'notes'        => sanitize_textarea_field( $params['notes'] ?? '' ),
                'status'       => sanitize_text_field( $params['status'] ?? 'available' ),
                'sort_order'   => absint( $params['sort_order'] ?? 0 ),
            ];
            if ( empty( $data['name'] ) ) {
                return new \WP_Error( 'missing_name', 'El nombre es obligatorio', [ 'status' => 400 ] );
            }
            if ( empty( $data['room_type_id'] ) ) {
                return new \WP_Error( 'missing_type', 'Seleccioná un tipo de habitación', [ 'status' => 400 ] );
            }
            $id = (int) ( $request['id'] ?? 0 );
            if ( $id ) {
                $repo->update( $id, $data );
                \Artechia\PMS\Logger::info( 'room_unit.updated', "Room unit #{$id} updated", 'room_unit', $id );
            } else {
                $id = $repo->create( $data );
                \Artechia\PMS\Logger::info( 'room_unit.created', "Room unit #{$id} created", 'room_unit', $id );
            }
            return new \WP_REST_Response( [ 'ok' => true, 'id' => $id ], 200 );
        } );
    }

    public function delete_room_unit( \WP_REST_Request $request ) {
        return $this->safe_response( function () use ( $request ) {
            $repo = new \Artechia\PMS\Repositories\RoomUnitRepository();
            $id   = (int) $request['id'];
            $repo->delete( $id );
            \Artechia\PMS\Logger::info( 'room_unit.deleted', "Room unit #{$id} deleted", 'room_unit', $id );
            return new \WP_REST_Response( [ 'ok' => true ], 200 );
        } );
    }
}
