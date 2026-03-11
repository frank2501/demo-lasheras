<?php
/**
 * Plugin activator: create tables, roles, default settings, auto-create pages.
 */

namespace Artechia\PMS;

use Artechia\PMS\DB\Migrator;
use Artechia\PMS\DB\Schema;
use Artechia\PMS\Services\Settings;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Activator {

    /**
     * Activation callback.
     */
    public static function activate(): void {
        // 1. Create / update DB tables.
        Migrator::run();
        update_option( 'artechia_pms_db_version', ARTECHIA_PMS_DB_VERSION );

        // 2. Create roles & capabilities.
        Roles::create();

        // 3. Seed default settings.
        self::seed_defaults();

        // 4. Auto-create front-end pages.
        self::create_pages();

        // 5. Seed default email templates.
        self::seed_email_templates();

        // 6. Log activation.
        Logger::info( 'plugin.activated', 'Artechia PMS activated — v' . ARTECHIA_PMS_VERSION );

        // Flush rewrite rules for any custom endpoints.
        flush_rewrite_rules();
    }

    /**
     * Public hook for admin_init to ensure pages exist.
     */
    public static function on_admin_init(): void {
        self::create_pages();
    }

    /**
     * Insert default settings if they don't exist.
     */
    private static function seed_defaults(): void {
        $defaults = [
            'currency'          => 'ARS',
            'currency_symbol'   => '$',
            'currency_position' => 'before',   // before | after
            'decimal_separator' => ',',
            'thousand_separator'=> '.',
            'decimals'          => '2',
            'timezone'          => 'America/Argentina/Buenos_Aires',
            'check_in_time'     => '14:00',
            'check_out_time'    => '10:00',
            'date_format'       => 'd/m/Y',
            'tax_enabled'       => '1',
            'tax_name'          => 'IVA',
            'tax_rate'          => '21',          // percentage
            'tax_included'      => '1',           // prices include tax
            'tax_per_night'     => '0',           // flat per night
            'tax_per_person'    => '0',           // flat per person
            'lock_ttl_minutes'     => '15',
            'pending_expiry_hours' => '24',
            'booking_code_prefix'  => 'ART',
            'debug_mode'        => '0',
            'mercadopago_enabled'   => '0',
            'mercadopago_sandbox'   => '1',
            'mercadopago_public_key' => '',
            'mercadopago_access_token' => '',
            'mercadopago_access_token_test' => '',
            'mercadopago_webhook_secret' => '',
            'mercadopago_deposit_percent' => '30',
            'mercadopago_charged_back_action' => 'flag', // flag | cancel
            'mercadopago_allow_unsigned_sandbox' => '0',
            'whatsapp_number'   => '',
            'whatsapp_message'  => __( 'Hola! Quiero consultar por mi reserva {booking_code}', 'artechia-pms' ),
            // Bank Transfer Defaults - REMOVED (Set via Demo Data or manually)
            'enable_bank_transfer' => '0',
            'bank_transfer_bank'   => '',
            'bank_transfer_holder' => '',
            'bank_transfer_cbu'    => '',
            'bank_transfer_alias'  => '',
            'bank_transfer_cuit'   => '',
            'ical_sync_frequency'  => '15',
        ];

        global $wpdb;
        $table = Schema::table( 'settings' );

        foreach ( $defaults as $key => $value ) {
            // Only insert if key doesn't exist for property_id = 0 (global).
            $exists = $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE property_id = 0 AND setting_key = %s",
                $key
            ) );

            if ( ! $exists ) {
                $wpdb->insert( $table, [
                    'property_id'   => 0,
                    'setting_key'   => $key,
                    'setting_value' => $value,
                ], [ '%d', '%s', '%s' ] );
            }
        }

        // Trigger Demo Data Seeding - REMOVED for manual trigger
        // self::seed_demo_data();
    }

    /**
     * Auto-create pages with shortcodes.
     */
    public static function create_pages(): void {
        $pages = [
            'search_page'       => [
                'title'     => __( 'Buscar Disponibilidad', 'artechia-pms' ),
                'content'   => '[artechia_search]',
                'option'    => 'search_page_id',
            ],
            'results_page'      => [
                'title'     => __( 'Resultados', 'artechia-pms' ),
                'content'   => '[artechia_results]',
                'option'    => 'results_page_id',
            ],
            'checkout_page'     => [
                'title'     => __( 'Checkout', 'artechia-pms' ),
                'content'   => '[artechia_checkout]',
                'option'    => 'checkout_page_id',
            ],
            'confirmation_page' => [
                'title'     => __( 'Confirmación de Reserva', 'artechia-pms' ),
                'content'   => '[artechia_confirmation]',
                'option'    => 'confirmation_page_id',
            ],
            'my_booking_page'   => [
                'title'     => __( 'Mi Reserva', 'artechia-pms' ),
                'content'   => '[artechia_my_booking]',
                'option'    => 'my_booking_page_id',
            ],
        ];

        foreach ( $pages as $key => $page_def ) {
            $page_id = 0;

            // 1. Try to find by meta key.
            $meta_query = new \WP_Query( [
                'post_type'      => 'page',
                'post_status'    => [ 'publish', 'draft' ],
                'meta_key'       => '_artechia_page_key',
                'meta_value'     => $key,
                'posts_per_page' => 1,
                'fields'         => 'ids',
            ] );

            if ( ! empty( $meta_query->posts ) ) {
                $page_id = $meta_query->posts[0];
            }

            // 2. Try to find by shortcode content (fallback if meta not set).
            if ( ! $page_id ) {
                $content_query = new \WP_Query( [
                    'post_type'      => 'page',
                    'post_status'    => [ 'publish', 'draft' ],
                    's'              => $page_def['content'],
                    'posts_per_page' => 1,
                    'fields'         => 'ids',
                ] );

                // verify it EXACTLY contains the shortcode to avoid false positives with similar text
                if ( ! empty( $content_query->posts ) ) {
                    $found_post = get_post( $content_query->posts[0] );
                    if ( strpos( $found_post->post_content, $page_def['content'] ) !== false ) {
                        $page_id = $found_post->ID;
                        // Assign meta for future detection
                        update_post_meta( $page_id, '_artechia_page_key', $key );
                    }
                }
            }

            // 3. Create if not found.
            if ( ! $page_id ) {
                $page_id = wp_insert_post( [
                    'post_title'   => $page_def['title'],
                    'post_content' => $page_def['content'],
                    'post_status'  => 'publish',
                    'post_type'    => 'page',
                    'post_author'  => get_current_user_id() ?: 1,
                ] );

                if ( $page_id && ! is_wp_error( $page_id ) ) {
                    update_post_meta( $page_id, '_artechia_page_key', $key );
                }
            }

            // 4. Update setting with the ID.
            if ( $page_id && ! is_wp_error( $page_id ) ) {
                Settings::set( $page_def['option'], $page_id );
            }
        }
    }

    /**
     * Seed default email templates.
     */
    private static function seed_email_templates(): void {
        global $wpdb;
        $table = Schema::table( 'email_templates' );

        $booking_placeholders = [
            '{booking_code}', '{guest_name}', '{check_in}', '{check_out}',
            '{nights}', '{adults}', '{children}', '{room_type}', '{grand_total}', 
            '{deposit_pct}', '{deposit_amount}', '{property_name}', '{my_booking_url}', '{status}'
        ];

        $templates = [
            [
                'event_type'   => 'booking_confirmed',
                'subject'      => __( 'Reserva Confirmada — {booking_code}', 'artechia-pms' ),
                'body_html'    => self::default_email_body( 'confirmed' ),
                'placeholders' => wp_json_encode( $booking_placeholders ),
            ],
            [
                'event_type'   => 'booking_pending',
                'subject'      => __( 'Reserva Recibida — {booking_code}', 'artechia-pms' ),
                'body_html'    => self::default_email_body( 'pending' ),
                'placeholders' => wp_json_encode( $booking_placeholders ),
            ],
            [
                'event_type'   => 'booking_cancelled',
                'subject'      => __( 'Reserva Cancelada — {booking_code}', 'artechia-pms' ),
                'body_html'    => self::default_email_body( 'cancelled' ),
                'placeholders' => wp_json_encode( $booking_placeholders ),
            ],
            [
                'event_type'   => 'payment_received',
                'subject'      => __( 'Pago Recibido — {booking_code}', 'artechia-pms' ),
                'body_html'    => self::default_email_body( 'payment' ),
                'placeholders' => wp_json_encode( array_merge( $booking_placeholders, [
                    '{amount}', '{payment_method}', '{balance}'
                ] ) ),
            ],
            [
                'event_type'   => 'marketing_promo',
                'subject'      => __( '¡Tenemos una oferta exclusiva para vos! — {property_name}', 'artechia-pms' ),
                'body_html'    => self::default_email_body( 'marketing_promo' ),
                'placeholders' => wp_json_encode( [
                    '{guest_name}', '{promo_code}', '{promo_description}', 
                    '{booking_url}', '{property_name}', '{unsubscribe_url}'
                ] ),
            ],
            [
                'event_type'   => 'booking_review',
                'subject'      => __( '¿Cómo estuvo tu estadía en {property_name}?', 'artechia-pms' ),
                'body_html'    => self::default_email_body( 'review' ),
                'placeholders' => wp_json_encode( [
                    '{booking_code}', '{guest_name}', '{property_name}', '{my_booking_url}'
                ] ),
            ],
        ];

        foreach ( $templates as $tpl ) {
            $existing_id = $wpdb->get_var( $wpdb->prepare(
                "SELECT id FROM {$table} WHERE property_id IS NULL AND event_type = %s",
                $tpl['event_type']
            ) );

            if ( $existing_id ) {
                // Force update with the new design since the user requested to redo all templates
                $wpdb->update( $table, [
                    'subject'      => $tpl['subject'],
                    'body_html'    => $tpl['body_html'],
                    'placeholders' => $tpl['placeholders'],
                ], [ 'id' => $existing_id ] );
            } else {
                $wpdb->insert( $table, array_merge( $tpl, [
                    'property_id' => null,
                    'is_active'   => 1,
                ] ) );
            }
        }
    }

    /**
     * Simple default email body generator.
     */
    private static function default_email_body( string $type ): string {
        $header = '<div style="background-color:#ffffff;color:#1e293b;padding:30px 20px;text-align:center;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif;border-bottom:4px solid #2563eb;">';
        $header .= '<div style="margin-bottom:15px;">{property_logo}</div>';
        $header .= '<h1 style="margin:0;font-size:26px;font-weight:800;letter-spacing:-0.5px;color:#2563eb;">{property_name}</h1></div>';
        $footer = '<div style="text-align:center;padding:30px 20px;color:#64748b;font-size:13px;background-color:#f8fafc;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif;">';
        $footer .= '&copy; ' . date('Y') . ' {property_name}. Todos los derechos reservados.';
        $footer .= '<br><br><a href="{my_booking_url}" style="color:#2563eb;text-decoration:none;">Acceder a mi portal de reservas</a></div>';

        $body_start = '<div style="background-color:#f8fafc;padding:20px 0;"><div style="padding:40px 30px;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif;color:#334155;line-height:1.6;max-width:600px;margin:0 auto;background:#ffffff;border-radius:12px;box-shadow:0 1px 3px rgba(0,0,0,0.1);">';
        $body_end   = '</div></div>';

        return match ( $type ) {
            'confirmed' => $header . $body_start
                . '<h2 style="color:#0f172a;margin-top:0;">¡Tu reserva está confirmada!</h2>'
                . '<p>Hola <strong>{guest_name}</strong>,</p>'
                . '<p>Nos alegra confirmar tu estadía en <strong>{property_name}</strong>. A continuación, los detalles de tu reserva <strong>{booking_code}</strong>:</p>'
                . '<div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;padding:20px;margin:24px 0;">'
                . '<ul style="list-style:none;padding:0;margin:0;">'
                . '<li style="margin-bottom:8px;"><strong>📅 Check-in:</strong> {check_in}</li>'
                . '<li style="margin-bottom:8px;"><strong>📅 Check-out:</strong> {check_out} ({nights} noches)</li>'
                . '<li style="margin-bottom:8px;"><strong>🛏️ Habitación:</strong> {room_type}</li>'
                . '<li><strong>💳 Total a pagar:</strong> {grand_total}</li>'
                . '</ul></div>'
                . '<p>Para ver el estado actualizado o gestionar los pagos, podés acceder a tu portal:</p>'
                . '<div style="text-align:center;margin:30px 0;"><a href="{my_booking_url}" style="display:inline-block;background:#2563eb;color:#ffffff;padding:14px 28px;text-decoration:none;border-radius:6px;font-weight:600;">Ver mi reserva</a></div>'
                . '<p>¡Te esperamos pronto!</p>'
                . $body_end . $footer,

            'pending' => $header . $body_start
                . '<h2 style="color:#0f172a;margin-top:0;">Recibimos tu solicitud de reserva</h2>'
                . '<p>Hola <strong>{guest_name}</strong>,</p>'
                . '<p>Tu reserva <strong>{booking_code}</strong> en <strong>{property_name}</strong> se encuentra en estado pendiente. Estamos procesándola.</p>'
                . '<div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;padding:20px;margin:24px 0;">'
                . '<ul style="list-style:none;padding:0;margin:0;">'
                . '<li style="margin-bottom:8px;"><strong>📅 Check-in:</strong> {check_in}</li>'
                . '<li style="margin-bottom:8px;"><strong>📅 Check-out:</strong> {check_out} ({nights} noches)</li>'
                . '<li style="margin-bottom:8px;"><strong>🛏️ Habitación:</strong> {room_type}</li>'
                . '<li><strong>💳 Total a pagar:</strong> {grand_total}</li>'
                . '</ul></div>'
                . '<p>Si elegiste un método de pago manual (como transferencia bancaria), recordá seguir los pasos indicados en tu portal para confirmar la reserva:</p>'
                . '<div style="text-align:center;margin:30px 0;"><a href="{my_booking_url}" style="display:inline-block;background:#2563eb;color:#ffffff;padding:14px 28px;text-decoration:none;border-radius:6px;font-weight:600;">Ver información de pago</a></div>'
                . $body_end . $footer,

            'cancelled' => $header . $body_start
                . '<h2 style="color:#b91c1c;margin-top:0;">Reserva cancelada</h2>'
                . '<p>Hola <strong>{guest_name}</strong>,</p>'
                . '<p>Queremos informarte que tu reserva <strong>{booking_code}</strong> en <strong>{property_name}</strong> ha sido cancelada.</p>'
                . '<p>Las fechas de tu estadía eran del <strong>{check_in}</strong> al <strong>{check_out}</strong>.</p>'
                . '<p>Si creés que esto es un error o querés realizar una nueva reserva, no dudes en contactarnos.</p>'
                . '<p>Esperamos recibirte en otra oportunidad.</p>'
                . $body_end . $footer,

            'payment' => $header . $body_start
                . '<h2 style="color:#15803d;margin-top:0;">Pago recibido exitosamente</h2>'
                . '<p>Hola <strong>{guest_name}</strong>,</p>'
                . '<p>Confirmamos la recepción de un pago por tu estadía en <strong>{property_name}</strong>.</p>'
                . '<div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:20px;margin:24px 0;">'
                . '<ul style="list-style:none;padding:0;margin:0;">'
                . '<li style="margin-bottom:8px;"><strong>Reserva:</strong> {booking_code}</li>'
                . '<li style="margin-bottom:8px;"><strong>Monto pagado:</strong> {amount}</li>'
                . '<li style="margin-bottom:8px;"><strong>Método:</strong> {payment_method}</li>'
                . '<li><strong>Saldo pendiente:</strong> {balance}</li>'
                . '</ul></div>'
                . '<p>Podés revisar los detalles de tu cuenta y los comprobantes desde tu portal:</p>'
                . '<div style="text-align:center;margin:30px 0;"><a href="{my_booking_url}" style="display:inline-block;background:#2563eb;color:#ffffff;padding:14px 28px;text-decoration:none;border-radius:6px;font-weight:600;">Ver estado de cuenta</a></div>'
                . $body_end . $footer,

            'marketing_promo' => '<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { margin: 0; padding: 0; background-color: #f8fafc; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; color: #1e293b; line-height: 1.6; }
        .wrapper { width: 100%; table-layout: fixed; background-color: #f8fafc; padding-bottom: 40px; }
        .main { background-color: #ffffff; margin: 20px auto; width: 100%; max-width: 600px; border-spacing: 0; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .header { background-color: #ffffff; padding: 30px 20px; text-align: center; border-bottom: 4px solid #2563eb; }
        .header h1 { color: #2563eb; margin: 0; font-size: 26px; font-weight: 800; }
        .content { padding: 40px 30px; }
        .promo-card { background-color: #eff6ff; border: 2px dashed #93c5fd; border-radius: 12px; padding: 24px; text-align: center; margin: 24px 0; }
        .promo-code { font-family: monospace; font-size: 32px; font-weight: 800; color: #1d4ed8; letter-spacing: 2px; margin: 12px 0; display: block; }
        .btn { display: inline-block; background: #2563eb; color: #ffffff !important; padding: 16px 32px; border-radius: 8px; text-decoration: none; font-weight: 700; }
        .footer { text-align: center; padding: 30px; color: #64748b; font-size: 14px; background-color: #f8fafc; }
    </style>
</head>
<body>
    <div class="wrapper">
        <table class="main">
            <tr><td class="header"><div style="margin-bottom:15px;">{property_logo}</div><h1>{property_name}</h1></td></tr>
            <tr>
                <td class="content">
                    <h2 style="margin-top:0;">¡Tenemos una oferta exclusiva para vos!</h2>
                    <p>Hola <strong>{guest_name}</strong>,</p>
                    <p>En <strong>{property_name}</strong> queremos que vuelvas a disfrutar de la mejor experiencia. Por eso, diseñamos una promoción especial para tu próxima estadía.</p>
                    <div class="promo-card">
                        <p style="margin: 0; font-weight: 600; color: #1e3a8a;">Usá este código al reservar:</p>
                        <span class="promo-code">{promo_code}</span>
                        <p style="margin: 0; font-size: 14px; color: #1e40af;">{promo_description}</p>
                    </div>
                    <p>No dejes pasar esta oportunidad. Las fechas aplican sujetas a disponibilidad.</p>
                    <div style="text-align: center; margin-top: 30px;"><a href="{booking_url}" class="btn">Reservar ahora</a></div>
                </td>
            </tr>
        </table>
        <table style="margin: 0 auto; width: 100%; max-width: 600px;">
            <tr>
                <td class="footer">
                    <p>&copy; 2026 {property_name}. Todos los derechos reservados.</p>
                    <p><a href="{unsubscribe_url}" style="color: #64748b;">Darse de baja</a></p>
                </td>
            </tr>
        </table>
    </div>
</body>
</html>',

            'review' => $header . $body_start
                . '<h2 style="color:#0f172a;margin-top:0;">¿Cómo estuvo tu estadía?</h2>'
                . '<p>Hola <strong>{guest_name}</strong>,</p>'
                . '<p>Esperamos que hayas tenido un excelente viaje y que hayas disfrutado tu tiempo en <strong>{property_name}</strong>.</p>'
                . '<p>Para nosotros es muy importante conocer tu opinión, ya que nos ayuda a seguir mejorando para ofrecerte siempre el mejor servicio.</p>'
                . '<p>¿Nos dedicarías un minuto para contarnos cómo te fue?</p>'
                . '<div style="text-align:center;margin:30px 0;"><a href="{my_booking_url}" style="display:inline-block;background:#2563eb;color:#ffffff;padding:14px 28px;text-decoration:none;border-radius:6px;font-weight:600;">Dejar una reseña</a></div>'
                . '<p>¡Esperamos volver a verte pronto!</p>'
                . $body_end . $footer,

            default => '',
        };
    }
}
