<?php
/**
 * Setup service: handles demo data generation and initial plugin setup.
 */

namespace Artechia\PMS\Services;

use Artechia\PMS\Repositories\PropertyRepository;
use Artechia\PMS\Repositories\RoomTypeRepository;
use Artechia\PMS\Repositories\RoomUnitRepository;
use Artechia\PMS\Repositories\RatePlanRepository;
use Artechia\PMS\Repositories\RateRepository;
use Artechia\PMS\DB\Schema;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class SetupService {

    /**
     * Generate standard demo data.
     * 
     * @return array{ ok: bool, already?: bool, property_id?: int, error?: string }
     */
    public function generate_demo_data(): array {
        global $wpdb;

        // Ensure schema is up to date (H3 additions)
        \Artechia\PMS\DB\Migrator::maybe_migrate();

        $prop_repo = new PropertyRepository();
        $slug      = 'cabanas-artechia';

        // 1. Idempotency Check & Find Property
        $existing = $prop_repo->all([ 'where' => [ 'slug' => $slug, 'is_demo' => 1 ] ]);
        $property_id = 0;
        
        if ( ! empty( $existing ) ) {
            $property_id = (int) $existing[0]['id'];
            // Ensure it's active and demo
            if ( $existing[0]['status'] !== 'active' ) {
                $prop_repo->update( $property_id, [ 'status' => 'active' ] );
            }
        }

        try {
            $wpdb->query( 'START TRANSACTION' );

            // 2. Create Property if not exists
            if ( ! $property_id ) {
                $property_id = $prop_repo->create([
                    'name'     => 'Cabañas ArtechIA',
                    'slug'     => $slug,
                    'address'  => 'Av. del Sol 123',
                    'city'     => 'Merlo',
                    'country'  => 'AR',
                    'is_demo'  => 1,
                    'status'   => 'active',
                ]);
            }

            if ( ! $property_id ) {
                throw new \Exception( 'Failed to create demo property.' );
            }

            // 3. Create Room Type if not exists
            $rt_repo = new RoomTypeRepository();
            $rt_slug = 'cabana-standard';
            $rt_exists = $wpdb->get_row( $wpdb->prepare( 
                "SELECT id FROM " . Schema::table( 'room_types' ) . " WHERE property_id = %d AND slug = %s", 
                $property_id, $rt_slug 
            ), \ARRAY_A );

            if ( ! $rt_exists ) {
                $rt_id = $rt_repo->create([
                    'property_id' => $property_id,
                    'name'        => 'Cabaña Standard',
                    'slug'        => $rt_slug,
                    'description' => 'Hermosa cabaña con vista a las sierras.',
                    'max_adults'  => 4,
                    'status'      => 'active',
                ]);
            } else {
                $rt_id = (int) $rt_exists['id'];
            }

            // 4. Create Units if not exist
            $unit_repo = new RoomUnitRepository();
            for ( $i = 1; $i <= 3; $i++ ) {
                $unit_name = 'Cabaña ' . $i;
                $u_exists = $wpdb->get_var( $wpdb->prepare( 
                    "SELECT id FROM " . Schema::table( 'room_units' ) . " WHERE property_id = %d AND name = %s", 
                    $property_id, $unit_name 
                ) );
                
                if ( ! $u_exists ) {
                    $unit_repo->create([
                        'property_id'  => $property_id,
                        'room_type_id' => $rt_id,
                        'name'         => $unit_name,
                        'status'       => 'available', 
                        'housekeeping' => 'clean',
                    ]);
                }
            }

            // 5. Create Default Rate Plan if not exists
            $rp_repo = new RatePlanRepository();
            $rp_exists = $wpdb->get_row( $wpdb->prepare(
                "SELECT id FROM " . Schema::table( 'rate_plans' ) . " WHERE property_id = %d AND is_annual = 1 LIMIT 1",
                $property_id
            ), \ARRAY_A );

            if ( ! $rp_exists ) {
                $rp_id = $rp_repo->create([
                    'property_id' => $property_id,
                    'name'        => 'Tarifa Estándar',
                    'code'        => 'STD',
                    'is_annual'   => 1,
                    'deposit_pct' => 30,
                    'status'      => 'active',
                ]);
            } else {
                $rp_id = (int) $rp_exists['id'];
            }

            // 5.1 Check if Base Rate exists
            $rate_repo = new RateRepository();
            $existing_base = $rate_repo->find_rate( $rt_id, $rp_id );
            
            if ( ! $existing_base ) {
                $base_rate_id = $rate_repo->create([
                    'room_type_id'    => $rt_id,
                    'rate_plan_id'    => $rp_id,
                    'price_per_night' => 100000.00,
                    'min_stay'        => 1
                ]);
            }

            // 6. Create Demo Rate Plan (High Season) - Next 15 days
            $start_season = new \DateTime();
            $end_season   = (clone $start_season)->modify('+15 days');
            
            // Check existence
            global $wpdb;
            $existing_demo_plan = $wpdb->get_row( $wpdb->prepare(
                "SELECT * FROM {$rp_repo->table()} WHERE code = 'HIGH' AND property_id = %d ORDER BY id DESC LIMIT 1",
                $property_id
            ), \ARRAY_A );
            
            if ( ! $existing_demo_plan ) {
                $demo_rp_id = $rp_repo->create([
                    'property_id' => $property_id,
                    'name'        => 'Plan Alta Demo',
                    'code'        => 'HIGH',
                    'deposit_pct' => 50,
                    'date_from'   => $start_season->format('Y-m-d'),
                    'date_to'     => $end_season->format('Y-m-d'),
                    'priority'    => 10,
                    'color'       => '#ef4444',
                    'status'      => 'active',
                ]);
            } else {
                $demo_rp_id = (int) $existing_demo_plan['id'];
            }

            // Sync dates to the new rate_plan_dates table ALWAYS
            if ( $demo_rp_id ) {
                $rp_repo->sync_dates( $demo_rp_id, [
                    [ 'date_from' => $start_season->format('Y-m-d'), 'date_to' => $end_season->format('Y-m-d') ]
                ] );
            }

            // 7. Create or Update Rate for this Plan
            $existing_demo_rate = $rate_repo->find_rate( $rt_id, $demo_rp_id );
            if ( ! $existing_demo_rate ) {
                $rate_repo->create([
                    'room_type_id'    => $rt_id,
                    'rate_plan_id'    => $demo_rp_id,
                    'price_per_night' => 100000.00, // High price to verify priority
                    'min_stay'        => 2
                ]);
            } else {
                $rate_repo->update( (int) $existing_demo_rate['id'], [
                    'price_per_night' => 100000.00,
                    'min_stay'        => 2
                ]);
            }


            // 8. Ensure Bank Transfer Settings are set to Test Data
            $settings_cls = \Artechia\PMS\Services\Settings::class;
            if ( class_exists( $settings_cls ) ) {
                $settings_cls::set( 'enable_bank_transfer', '1' );
                $settings_cls::set( 'bank_transfer_bank', 'Banco Galicia (TEST)' );
                $settings_cls::set( 'bank_transfer_holder', 'Artechia Hospitality S.A.' );
                $settings_cls::set( 'bank_transfer_cbu', '0070123400000012345678' );
                $settings_cls::set( 'bank_transfer_alias', 'ARTECHIA.DEMO' );
                $settings_cls::set( 'bank_transfer_cuit', '30-12345678-9' );

                // Sets professional Terms and Conditions for Demo Property
                $settings_cls::set( 'checkout_terms_conditions', "<h3>1. Proceso de Reserva</h3><p>La reserva se confirma una vez completado el pago del depósito o confirmada la transferencia bancaria por parte de la administración.</p><h3>2. Horarios</h3><p><strong>Check-in:</strong> A partir de las 14:00 hs del día de ingreso.<br><strong>Check-out:</strong> Hasta las 10:00 hs del día de salida.</p><h3>3. Política de Cancelación</h3><p>Las cancelaciones se rigen por la política seleccionada al momento de reservar. En caso de no presentarse (No-show), se aplicará la penalidad total estipulada.</p><h3>4. Convivencia y Reglas de la Casa</h3><p>Se solicita mantener el orden y la limpieza de las instalaciones. No se permiten ruidos molestos que afecten la tranquilidad de otros huéspedes.<br>No se permite fumar dentro de las habitaciones.<br>Cualquier daño a la propiedad deberá ser abonado por el responsable de la reserva.</p><h3>5. Responsabilidad</h3><p>La propiedad no se responsabiliza por la pérdida de objetos de valor no declarados.</p>" );
                $settings_cls::set( 'checkout_terms_conditions_type', 'html' );
                
                // Set Default Email Appearance Data
                $settings_cls::set( 'marketing_from_name', 'Artechia (Demo Property)' );
                $settings_cls::set( 'logo_url', 'https://www.artechia.com/images/logo-email.png' );
            }

            // 9. Ensure Extra "Desayuno"
            $extra_repo = new \Artechia\PMS\Repositories\ExtraRepository();
            $extra_exists = $extra_repo->find_by_name( $property_id, 'Desayuno' );
            
            if ( ! $extra_exists ) {
                $extra_repo->create([
                    'property_id' => $property_id,
                    'name'        => 'Desayuno',
                    'description' => 'Desayuno buffet completo.',
                    'price'       => 8500.00,
                    'price_type'  => 'per_night',
                    'status'      => 'active'
                ]);
            }

            // 10. Ensure Test Booking "TEST-CONFIRMED"
            $booking_code = 'TEST-CONFIRMED';
            $demo_token   = 'iMJaU5evs3D3wdP2UVazopDuZsLEcGu4';
            $booking_repo = new \Artechia\PMS\Repositories\BookingRepository();
            $booking_exists = $booking_repo->find_by_code( $booking_code );

            // Ensure Demo Guest for this booking
            $guest_repo = new \Artechia\PMS\Repositories\GuestRepository();
            $guest = $guest_repo->find_by_email( 'test@artechia.com' );
            
            $guest_data = [
                'first_name'      => 'Usuario',
                'last_name'       => 'Demo',
                'email'           => 'test@artechia.com',
                'phone'           => '+5491112345678',
                'document_type'   => 'DNI',
                'document_number' => '12345678',
            ];

            if ( $guest ) {
                $guest_id = (int) $guest['id'];
                $guest_repo->update( $guest_id, $guest_data );
            } else {
                $guest_id = (int) $guest_repo->create( $guest_data );
            }

            // Create or Update Demo Booking
            $check_in    = date( 'Y-m-d', strtotime( '+5 days' ) );
            $check_out   = date( 'Y-m-d', strtotime( '+8 days' ) ); // 3 nights
            $subtotal    = 200000.00;
            $grand_total = 200000.00;
            $amount_paid = 20000.00;
            $balance_due = 180000.00;

            $booking_data = [
                'source'          => 'admin',
                'booking_code'    => $booking_code,
                'property_id'     => $property_id,
                'guest_id'        => $guest_id,
                'rate_plan_id'    => $rp_id,
                'check_in'        => $check_in,
                'check_out'       => $check_out,
                'nights'          => 3,
                'adults'          => 2,
                'children'        => 0,
                'status'          => 'confirmed',
                'payment_status'  => 'deposit_paid',
                'payment_method'  => 'bank_transfer',
                'subtotal'        => $subtotal,
                'grand_total'     => $grand_total,
                'amount_paid'     => $amount_paid,
                'balance_due'     => $balance_due,
                'access_token'    => $demo_token,
            ];

            if ( ! $booking_exists ) {
                $booking_id = $booking_repo->create( $booking_data );
                \Artechia\PMS\Logger::info( 'setup.demo_booking_created', "Created demo booking #{$booking_id} with code {$booking_code}" );
            } else {
                $booking_id = (int) $booking_exists['id'];
                $booking_repo->update( $booking_id, $booking_data );
                \Artechia\PMS\Logger::info( 'setup.repairing_demo_booking', "Ensured demo booking #{$booking_id} matches demo config" );
            }

                // 11. Ensure Booking Room Unit Assignment
                // Find a unit for assignment
                $unit_ids = $wpdb->get_col( $wpdb->prepare(
                    "SELECT id FROM " . Schema::table( 'room_units' ) . " WHERE room_type_id = %d LIMIT 1",
                    $rt_id
                ) );
                $unit_id = ! empty( $unit_ids ) ? (int) $unit_ids[0] : 0;

                $br_repo = new \Artechia\PMS\Repositories\BookingRoomRepository();
                $br_table = Schema::table( 'booking_rooms' );
                
                // Check if BR exists
                $br_record = $wpdb->get_row( $wpdb->prepare(
                    "SELECT id FROM {$br_table} WHERE booking_id = %d LIMIT 1",
                    $booking_id
                ), \ARRAY_A );

                if ( ! $br_record ) {
                    $br_repo->create([
                        'booking_id'   => $booking_id,
                        'room_type_id' => $rt_id,
                        'room_unit_id' => $unit_id,
                        'adults'       => 2,
                        'children'     => 0,
                        'subtotal'     => $subtotal
                    ]);
                    \Artechia\PMS\Logger::info( 'setup.creating_missing_br', "Created booking_room for booking #{$booking_id}" );
                } else {
                    // Update existing BR to ensure unit is assigned
                    $wpdb->update(
                        $br_table,
                        [ 
                            'room_unit_id' => $unit_id,
                            'subtotal'     => $subtotal,
                            'room_type_id' => $rt_id
                        ],
                        [ 'id' => (int) $br_record['id'] ],
                        [ '%d', '%f', '%d' ],
                        [ '%d' ]
                    );
                    \Artechia\PMS\Logger::info( 'setup.updating_existing_br', "Updated booking_room #{$br_record['id']} with unit #{$unit_id}" );
                }

            // 12. Ensure Payment Record for the deposit
            $pay_repo = new \Artechia\PMS\Repositories\PaymentRepository();
            $pay_table = Schema::table( 'payments' );
            $existing_pay = $wpdb->get_row( $wpdb->prepare(
                "SELECT id FROM {$pay_table} WHERE booking_id = %d LIMIT 1",
                $booking_id
            ), \ARRAY_A );

            if ( ! $existing_pay ) {
                $deposit_pct = 30; // matches rate plan deposit_pct
                $pay_repo->create([
                    'booking_id'     => $booking_id,
                    'gateway'        => 'efectivo',
                    'gateway_txn_id' => 'DEMO-' . strtoupper( bin2hex( random_bytes(4) ) ),
                    'intent_id'      => 'DEMO-DEPOSIT',
                    'amount'         => $amount_paid,
                    'currency'       => 'ARS',
                    'status'         => 'approved',
                    'pay_mode'       => 'manual',
                    'note'           => "Confirmación de Reserva - Pago Seña ({$deposit_pct}%)",
                    'paid_at'        => current_time( 'mysql' ),
                ]);
                \Artechia\PMS\Logger::info( 'setup.demo_payment_created', "Created demo payment of {$amount_paid} for booking #{$booking_id}" );
            }

            // 13. Enable Marketing Settings
            if ( class_exists( $settings_cls ) ) {
                $settings_cls::set( 'marketing_enabled', '1' );
            }

            // 14. Create Demo Coupon
            $coupon_repo = new \Artechia\PMS\Repositories\CouponRepository();
            $existing_coupon = $coupon_repo->find_by_code( 'BIENVENIDO10' );
            if ( ! $existing_coupon ) {
                $coupon_repo->create([
                    'code'                  => 'BIENVENIDO10',
                    'type'                  => 'percentage',
                    'value'                 => 10.00,
                    'starts_at'             => date( 'Y-m-d' ),
                    'ends_at'               => date( 'Y-m-d', strtotime( '+90 days' ) ),
                    'min_nights'            => 2,
                    'room_type_ids'         => '',
                    'rate_plan_ids'         => '',
                    'usage_limit_total'     => 50,
                    'usage_limit_per_email' => 1,
                    'stackable'             => 0,
                    'applies_to'            => 'accommodation',
                    'active'                => 1,
                    'is_automatic'          => 0,
                ]);
                \Artechia\PMS\Logger::info( 'setup.demo_coupon_created', 'Created BIENVENIDO10 demo coupon (10% off)' );
            }

            $wpdb->query( 'COMMIT' );

            return [ 
                'ok'          => true, 
                'property_id' => (int) $property_id,
                'message'     => 'Demo data created successfully.'
            ];

        } catch ( \Throwable $e ) {
            $wpdb->query( 'ROLLBACK' );
            throw $e;
        }
    }
}
