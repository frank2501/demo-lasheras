<?php
/**
 * Email service: load templates, replace placeholders, send via wp_mail.
 */
namespace Artechia\PMS\Services;

use Artechia\PMS\DB\Schema;
use Artechia\PMS\Logger;
use Artechia\PMS\Repositories\BookingRepository;
use Artechia\PMS\Helpers\Helpers;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class EmailService {

    private BookingRepository $bookings;

    public function __construct() {
        $this->bookings = new BookingRepository();
    }

    /**
     * Send an email for a booking event.
     *
     * @param string $event_type  e.g. 'booking_pending', 'booking_confirmed'
     * @param int    $booking_id
     * @param array  $extra_data  Additional placeholder overrides.
     */
    public function send( string $event_type, int $booking_id, array $extra_data = [] ): bool {
        $booking = $this->bookings->find( $booking_id );
        if ( ! $booking ) {
            Logger::warning( 'email.no_booking', "Cannot send email: booking {$booking_id} not found" );
            return false;
        }

        $property_id = (int) $booking['property_id'];
        $template = $this->load_template( $event_type, $property_id );
        if ( ! $template || ! $template['is_active'] ) {
            Logger::info( 'email.no_template', "No active template for {$event_type}" );
            return false;
        }

        $raw_subject = $template['subject'] ?? '';
        $raw_body    = $template['body_html'] ?? '';

        $placeholders = $this->build_placeholders( $booking, $extra_data );
        // Sanitize subject: strip HTML and prevent header injection.
        $subject = wp_strip_all_tags( $this->replace( $raw_subject, $placeholders ) );
        $subject = preg_replace( '/[\r\n]+/', ' ', $subject ); // prevent header injection
        
        $inner_body = $this->replace( $raw_body, $placeholders );
        $body       = $this->wrap_template( $inner_body, $subject, $property_id );

        // Get guest email.
        $guest = ( new \Artechia\PMS\Repositories\GuestRepository() )->find( (int) $booking['guest_id'] );
        if ( ! $guest || empty( $guest['email'] ) ) {
            Logger::warning( 'email.no_guest_email', "No email for guest {$booking['guest_id']}" );
            return false;
        }

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
        ];

        $attachments = [];

        // ── Calendar Integration (ICS Attachment) ──
        if ( 'booking_confirmed' === $event_type ) {
            $ics_path = $this->generate_ics_attachment( $booking );
            if ( $ics_path ) {
                $attachments[] = $ics_path;
            }
        }

        // Also send copy to admin if configured.
        $admin_email = get_option( 'admin_email' );

        $sent = $this->execute_wp_mail( $guest['email'], $subject, $body, $headers, $attachments );

        // BCC to admin for confirmed/cancelled.
        if ( in_array( $event_type, [ 'booking_confirmed', 'booking_cancelled' ], true ) && $admin_email ) {
            $this->execute_wp_mail( $admin_email, '[Admin Copy] ' . $subject, $body, $headers, $attachments );
        }

        if ( $sent ) {
            Logger::info( 'email.sent', "Email {$event_type} sent for booking {$booking['booking_code']}", 'booking', (int) $booking['id'], [
                'to' => $guest['email'],
            ] );
        } else {
            Logger::warning( 'email.failed', "Failed to send {$event_type} for booking {$booking['booking_code']}" );
        }

        return $sent;
    }

    /**
     * Send a marketing email without a booking context.
     *
     * @param string $to
     * @param string $event_type
     * @param array  $extra_data
     */
    public function send_marketing( string $to, string $event_type, array $extra_data = [] ): bool {
        Logger::info( 'email.marketing_send_start', "Starting send_marketing to {$to} (event: {$event_type})" );
        $has_custom_template = ! empty( $extra_data['custom_subject'] ) && ! empty( $extra_data['custom_body_html'] );
        $template = null;
        
        if ( ! $has_custom_template ) {
            $property_id = (int) ( $extra_data['property_id'] ?? 0 );
            $template = $this->load_template( $event_type, $property_id );
            
            if ( ! $template || ! $template['is_active'] ) {
                Logger::info( 'email.no_marketing_template', "No active marketing template for {$event_type} (property: {$property_id})" );
                return false;
            }
        }

        $placeholders = array_merge( $this->sample_placeholders(), $extra_data );
        
        $raw_subject = $extra_data['custom_subject'] ?? ( $template ? $template['subject'] : '' );
        $raw_body    = $extra_data['custom_body_html'] ?? ( $template ? $template['body_html'] : '' );

        $subject = wp_strip_all_tags( $this->replace( $raw_subject, $placeholders ) );
        $subject = preg_replace( '/[\r\n]+/', ' ', $subject );
        
        $inner_body = $this->replace( $raw_body, $placeholders );
        $body       = $this->wrap_template( $inner_body, $subject );

        // ── Email Tracking (open pixel + click rewriting) ──
        $campaign_id    = $extra_data['campaign_id'] ?? null;
        $tracking_email = $extra_data['tracking_email'] ?? $to;

        if ( $campaign_id ) {
            $token = base64_encode( wp_json_encode( [ 'c' => $campaign_id, 'e' => $tracking_email ] ) );
            $rest_url = rest_url( 'artechia/v1/public/track/' );

            // 1) Open tracking pixel — single query param to avoid &amp; encoding issues
            $pixel_url = home_url( '/?artechia_open=' . urlencode( $token ) );
            $pixel_img = '<img src="' . esc_url( $pixel_url ) . '" width="1" height="1" alt="" style="display:none;border:0;" />';

            if ( stripos( $body, '</body>' ) !== false ) {
                $body = str_ireplace( '</body>', $pixel_img . '</body>', $body );
            } else {
                $body .= $pixel_img;
            }

            // 2) Click tracking — rewrite all <a href="..."> links
            $click_base = $rest_url . 'click?t=' . urlencode( $token ) . '&url=';
            $body = preg_replace_callback(
                '/<a\s([^>]*?)href=["\']([^"\']+)["\']/i',
                function ( $m ) use ( $click_base, $rest_url ) {
                    $original_url = $m[2];
                    // Don't rewrite tracking URLs, mailto:, tel:, or anchors
                    if (
                        str_starts_with( $original_url, $rest_url ) ||
                        str_starts_with( $original_url, 'mailto:' ) ||
                        str_starts_with( $original_url, 'tel:' ) ||
                        str_starts_with( $original_url, '#' )
                    ) {
                        return $m[0];
                    }
                    $tracked_url = $click_base . urlencode( $original_url );
                    return '<a ' . $m[1] . 'href="' . esc_url( $tracked_url ) . '"';
                },
                $body
            );
        }

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
        ];

        $from_name   = Settings::get( 'marketing_from_name' );
        $from_email  = Settings::get( 'marketing_from_email' );
        $admin_email = get_bloginfo( 'admin_email' );

        $from_email = $from_email ?: $admin_email;
        $from_name  = $from_name ?: get_bloginfo( 'name' );

        if ( is_email( $from_email ) ) {
            $headers[] = sprintf( 'From: %s <%s>', $from_name, $from_email );
        }

        $sent = $this->execute_wp_mail( $to, $subject, $body, $headers );

        if ( $sent ) {
            Logger::info( 'marketing.email_sent', "Marketing email {$event_type} sent to {$to}" );
        }

        return $sent;
    }

    private function execute_wp_mail( string $to, string $subject, string $body, array $headers, array $attachments = [] ): bool {
        // Log attempt.
        Logger::info( 'email.attempt', "Attempting to send email to {$to}. Subject: {$subject}", 'system' );
        
        // Find if we have an ICS attachment
        $ics_path = null;
        $other_attachments = [];
        foreach ( $attachments as $file ) {
            if ( str_ends_with( strtolower( $file ), '.ics' ) ) {
                $ics_path = $file;
            } else {
                $other_attachments[] = $file;
            }
        }

        // If we have an ICS, we intercept PHPMailer to add it with the correct Content-Type so Gmail parses it.
        if ( $ics_path ) {
            $phpmailer_action = function( $phpmailer ) use ( $ics_path ) {
                $phpmailer->addStringAttachment(
                    file_get_contents( $ics_path ),
                    basename( $ics_path ),
                    'base64',
                    'text/calendar; charset=utf-8; method=REQUEST'
                );
            };
            \add_action( 'phpmailer_init', $phpmailer_action );
        }

        $sent = wp_mail( $to, $subject, $body, $headers, $other_attachments );
        
        // Remove the action immediately so it doesn't affect other emails
        if ( $ics_path && isset( $phpmailer_action ) ) {
            \remove_action( 'phpmailer_init', $phpmailer_action );
        }

        if ( ! $sent ) {
            Logger::error( 'email.failed', "wp_mail returned false for {$to}. Subject: {$subject}. Headers: " . json_encode($headers), 'system' );
            do_action( 'artechia_pms_email_failed', $to, $subject, $headers );
        } else {
            Logger::info( 'email.sent_success', "Email successfully sent to {$to}", 'system' );
        }
        
        return $sent;
    }

    /**
     * Schedule an email to be sent asynchronously via WP-Cron.
     *
     * @param string $event_type
     * @param int    $booking_id
     * @param array  $extra_data
     * @param int    $delay_seconds Optional delay in seconds.
     */
    public function send_async( string $event_type, int $booking_id, array $extra_data = [], int $delay_seconds = 0 ): void {
        $timestamp = time() + $delay_seconds;
        wp_schedule_single_event( $timestamp, 'artechia_pms_send_email_async', [ $event_type, $booking_id, $extra_data ] );
        
        $msg = "Queued async email {$event_type} for booking {$booking_id}";
        if ( $delay_seconds > 0 ) {
            $msg .= " with delay of {$delay_seconds}s";
        }
        Logger::info( 'email.queued', $msg );
    }

    /**
     * Callback for async email sending.
     */
    public static function handle_async_email( string $event_type, int $booking_id, array $extra_data = [] ): void {
        $service = new self();
        $service->send( $event_type, $booking_id, $extra_data );
    }

    /**
     * Render a template for admin preview.
     */
    public function render( string $event_type, array $sample_data = [] ): ?array {
        $template = $this->load_template( $event_type );
        if ( ! $template ) return null;

        $placeholders = array_merge( $this->sample_placeholders(), $sample_data );
        $subject = $this->replace( $template['subject'], $placeholders );
        $inner_body = $this->replace( $template['body_html'], $placeholders );
        
        return [
            'subject' => $subject,
            'body'    => $this->wrap_template( $inner_body, $subject ),
        ];
    }

    /**
     * Render a custom template (e.g. for preview) using real booking data.
     */
    public function render_custom( int $booking_id, string $subject, string $body ): array {
        $booking = $this->bookings->find( $booking_id );
        if ( ! $booking ) {
            // Even if no booking found, wrap it? Or maybe just return raw for debugging?
            // Let's wrap it for consistency if we have a subject title.
            return [ 
                'subject' => $subject, 
                'body'    => $this->wrap_template( $body, $subject ) 
            ];
        }

        $placeholders = $this->build_placeholders( $booking );
        
        $replaced_subject = $this->replace( $subject, $placeholders );
        $inner_body       = $this->replace( $body, $placeholders );

        return [
            'subject' => $replaced_subject,
            'body'    => $this->wrap_template( $inner_body, $replaced_subject, (int) $booking['property_id'] ),
        ];
    }

    /**
     * Get available placeholders for a booking (or sample).
     */
    public function get_placeholders( ?int $booking_id = null ): array {
        if ( $booking_id ) {
            $booking = $this->bookings->find( $booking_id );
            if ( $booking ) {
                return $this->build_placeholders( $booking );
            }
        }
        return $this->sample_placeholders();
    }

    /* ── Template loading ───────────────────────────── */

    /**
     * Load template: property-specific first, then global (property_id IS NULL).
     */
    private function load_template( string $event_type, int $property_id = 0 ): ?array {
        global $wpdb;
        $table = Schema::table( 'email_templates' );

        // Try property-specific first.
        if ( $property_id > 0 ) {
            $row = $wpdb->get_row( $wpdb->prepare(
                "SELECT * FROM {$table} WHERE property_id = %d AND event_type = %s LIMIT 1",
                $property_id, $event_type
            ), \ARRAY_A );
            if ( $row ) return $row;
        }

        // Fallback to global (property_id 0 or NULL).
        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE (property_id = 0 OR property_id IS NULL) AND event_type = %s LIMIT 1",
            $event_type
        ), \ARRAY_A );

        return $row ?: null;
    }

    /* ── Placeholder building ───────────────────────── */

    /**
     * Build placeholder map from booking data.
     */
    private function build_placeholders( array $booking, array $extra = [] ): array {
        $booking_id = (int) ( $booking['id'] ?? 0 );
        $guest_id   = (int) ( $booking['guest_id'] ?? 0 );
        $prop_id    = (int) ( $booking['property_id'] ?? 0 );

        $guest = $guest_id ? ( new \Artechia\PMS\Repositories\GuestRepository() )->find( $guest_id ) : null;

        // Room type name.
        $room_type_name = '';
        if ( $booking_id > 0 ) {
            $rooms = $this->bookings->get_rooms( $booking_id );
            if ( ! empty( $rooms ) ) {
                $rt = ( new \Artechia\PMS\Repositories\RoomTypeRepository() )->find( (int) $rooms[0]['room_type_id'] );
                $room_type_name = $rt['name'] ?? '';
            }
        }

        // Property name.
        $property_name = '';
        if ( $prop_id > 0 ) {
            $prop = ( new \Artechia\PMS\Repositories\PropertyRepository() )->find( $prop_id );
            $property_name = $prop['name'] ?? '';
        }

        // Manage URL.
        $manage_url = '';
        if ( ! empty( $booking['booking_code'] ) ) {
            $page_id = (int) Settings::get( 'my_booking_page_id', 0 );
            $base_url = $page_id ? get_permalink( $page_id ) : home_url( '/mi-reserva/' );
            $manage_url = add_query_arg( [
                'code'  => $booking['booking_code'],
                'token' => $booking['access_token'] ?? '',
            ], $base_url );
        }

        // Format values.
        $total = Helpers::format_price( (float) ( $booking['grand_total'] ?? 0 ) );
        
        $pricing_snapshot = ! empty( $booking['pricing_snapshot'] ) ? json_decode( $booking['pricing_snapshot'], true ) : [];
        $deposit_pct      = (float) ( $pricing_snapshot['totals']['deposit_pct'] ?? 0 );
        $deposit_due      = (float) ( $pricing_snapshot['totals']['deposit_due'] ?? 0 );
        $deposit_formatted = Helpers::format_price( $deposit_due );

        $date_format = Settings::get( 'date_format', 'd/m/Y' );
        $check_in_formatted  = ! empty( $booking['check_in'] ) ? date_i18n( $date_format, strtotime( $booking['check_in'] ) ) : '';
        $check_out_formatted = ! empty( $booking['check_out'] ) ? date_i18n( $date_format, strtotime( $booking['check_out'] ) ) : '';

        $placeholders = [
            '{booking_code}'   => esc_html( $booking['booking_code'] ?? '—' ),
            '{guest_name}'     => esc_html( trim( ( $guest['first_name'] ?? '' ) . ' ' . ( $guest['last_name'] ?? '' ) ) ),
            '{check_in}'       => esc_html( $check_in_formatted ),
            '{check_out}'      => esc_html( $check_out_formatted ),
            '{nights}'         => esc_html( $booking['nights'] ?? 0 ),
            '{room_type}'      => esc_html( $room_type_name ),
            '{grand_total}'    => esc_html( $total ),
            '{deposit_pct}'    => esc_html( $deposit_pct ),
            '{deposit_amount}' => esc_html( $deposit_formatted ),
            '{property_name}'  => esc_html( $property_name ),
            '{property_logo}'  => ! empty( Settings::get( 'logo_url' ) ) ? '<img src="' . esc_url( Settings::get( 'logo_url' ) ) . '" alt="' . esc_attr( $property_name ) . '" style="max-height: 50px;">' : '',
            '{my_booking_url}' => esc_url( $manage_url ),
            '{status}'         => esc_html( $booking['status'] ?? '' ),
            '{adults}'         => esc_html( $booking['adults'] ?? 0 ),
            '{children}'       => esc_html( $booking['children'] ?? 0 ),
        ];

        // Payment status placeholders.
        if ( ! isset( $extra['{amount}'] ) || ! isset( $extra['{balance}'] ) ) {
            $latest_amount = 0.0;
            $latest_method = 'N/A';
            $total_paid    = 0.0;

            if ( $booking_id > 0 ) {
                $payments = ( new \Artechia\PMS\Repositories\PaymentRepository() )->all( [ 'where' => [ 'booking_id' => $booking_id ] ] );
                foreach ( $payments as $p ) {
                    if ( 'completed' === $p['status'] ) {
                        $total_paid += (float) $p['amount'];
                        $latest_amount = (float) $p['amount'];
                        $latest_method = $p['payment_method'];
                    }
                }
            }
            
            $balance = max( 0.0, (float) ( $booking['grand_total'] ?? 0 ) - $total_paid );
            
            $placeholders = array_merge( $placeholders, [
                '{amount}'         => esc_html( Helpers::format_price( $latest_amount ) ),
                '{payment_method}' => esc_html( ucfirst( $latest_method ) ),
                '{balance}'        => esc_html( Helpers::format_price( $balance ) ),
            ] );
        }

        return array_merge( $placeholders, $extra );
    }

    private function sample_placeholders(): array {
        return [
            '{booking_code}'   => 'ART260301X4K2R',
            '{guest_name}'     => 'Juan Pérez',
            '{check_in}'       => '01/03/2026',
            '{check_out}'      => '04/03/2026',
            '{nights}'         => '3',
            '{room_type}'      => 'Habitación Doble',
            '{grand_total}'    => '$45.000,00',
            '{deposit_pct}'    => '30',
            '{deposit_amount}' => '$13.500,00',
            '{property_name}'  => 'Hotel Demo',
            '{property_logo}'  => '<img src="https://www.artechia.com/images/logo-email.png" alt="Hotel Demo" style="max-height: 50px;">',
            '{my_booking_url}' => home_url( '/mi-reserva/?code=ART260301X4K2R&token=SAMPLE' ),
            '{status}'         => 'confirmed',
            '{adults}'         => '2',
            '{children}'       => '0',
            '{amount}'         => '$45.000,00',
            '{payment_method}' => 'MercadoPago',
            '{balance}'        => '$0,00',
        ];
    }

    /* ── Helpers ─────────────────────────────────────── */

    private function replace( string $template, array $placeholders ): string {
        $valid_placeholders = [];
        foreach ( $placeholders as $k => $v ) {
            if ( is_string( $k ) && str_starts_with( $k, '{' ) && str_ends_with( $k, '}' ) && is_scalar( $v ) ) {
                $valid_placeholders[$k] = (string) $v;
            }
        }

        return str_replace(
            array_keys( $valid_placeholders ),
            array_values( $valid_placeholders ),
            $template
        );
    }

    /**
     * Pass-through wrapper for email content.
     *
     * DB-stored templates already contain the complete HTML structure (header,
     * footer, styles), so no additional wrapping is needed. Kept as a method
     * for future extensibility (e.g., injecting global footers or analytics).
     */
    private function wrap_template( string $content, string $title, ?int $property_id = null ): string {
        return $content;
    }

    /**
     * Generate an ICS calendar file and return its temporary path.
     */
    public function generate_ics_attachment( array $booking ): ?string {
        if ( ! Settings::get( 'calendar_json_ld_enabled', '1' ) ) {
            return null; // Keep using the same setting toggle for simplicity
        }

        $property_id = (int) $booking['property_id'];
        $property    = ( new \Artechia\PMS\Repositories\PropertyRepository() )->find( $property_id );
        $guest       = ( new \Artechia\PMS\Repositories\GuestRepository() )->find( (int) $booking['guest_id'] );
        
        if ( ! $property ) {
            Logger::warning( 'email.ics_fail', "Missing property data for booking {$booking['booking_code']}", 'booking', (int) $booking['id'] );
            return null;
        }

        $check_in_time  = Settings::get( 'check_in_time', '14:00', $property_id );
        $check_out_time = Settings::get( 'check_out_time', '10:00', $property_id );
        $whatsapp       = Settings::get( 'whatsapp_number', '', $property_id );
        $url            = get_site_url();

        // ICS dates require UTC format (YYYYMMDDTHHmmssZ)
        $dt_start = gmdate( 'Ymd\THis\Z', strtotime( $booking['check_in'] . ' ' . $check_in_time . ':00-03:00' ) );
        $dt_end   = gmdate( 'Ymd\THis\Z', strtotime( $booking['check_out'] . ' ' . $check_out_time . ':00-03:00' ) );
        
        $summary = sprintf( 'Reserva de Alojamiento: %s', $property['name'] );
        
        // Build plain text description
        $guest_name = trim( ( $guest['first_name'] ?? '' ) . ' ' . ( $guest['last_name'] ?? '' ) );
        $desc_lines = [
            "Propiedad: {$property['name']}",
            "A nombre de: {$guest_name}",
            "Reserva Web: #{$booking['booking_code']}",
            "Huéspedes: {$booking['adults']} Adultos, {$booking['children']} Niños",
        ];

        // Add room details
        $rooms = ( new \Artechia\PMS\Repositories\BookingRepository() )->get_rooms( (int) $booking['id'] );
        $room_text = '';
        if ( ! empty( $rooms ) ) {
            $room_names = array_map( function($r) { return $r['room_type_name']; }, $rooms );
            $room_text = implode(', ', $room_names);
            $desc_lines[] = "Habitación: " . $room_text;
        }

        if ( $whatsapp ) {
            $desc_lines[] = "Contacto WhatsApp: wa.me/" . preg_replace('/[^0-9]/', '', $whatsapp);
        }
        
        $desc_lines[] = "Web: " . $url;

        // ICS plain text description (using literal \n for ICS newlines)
        $description = implode( "\\n", $desc_lines );
        
        // Build HTML alternative description
        $html_desc = "<h3>Reserva en {$property['name']}</h3>";
        $html_desc .= "<p><strong>Reservado por:</strong> {$guest_name}<br>";
        $html_desc .= "<strong>Código:</strong> #{$booking['booking_code']}<br>";
        $html_desc .= "<strong>Huéspedes:</strong> {$booking['adults']} Adultos, {$booking['children']} Niños<br>";
        if ( $room_text ) {
            $html_desc .= "<strong>Habitación:</strong> {$room_text}</p>";
        } else {
            $html_desc .= "</p>";
        }
        
        if ( $whatsapp ) {
            $wa_url = "https://wa.me/" . preg_replace('/[^0-9]/', '', $whatsapp);
            $html_desc .= "<p><a href=\"{$wa_url}\" style=\"color:#25D366;font-weight:bold;\">💬 Escribinos por WhatsApp</a></p>";
        }
        $html_desc .= "<p><a href=\"{$url}\">🌐 Visitar Sitio Web</a></p>";
        
        // Get property address
        $property_address = !empty($property['address']) ? $property['address'] : (!empty($property['name']) ? $property['name'] : 'Tu Propiedad, Ciudad, País');

        $uid  = md5( uniqid( mt_rand(), true ) ) . '@artechia-pms.local';
        
        // Gmail requires ORGANIZER and ATTENDEE to show the RSVP buttons
        $organizer_name  = $this->escape_ics_string( Settings::get( 'marketing_from_name', $property['name'] ) );
        $organizer_email = Settings::get( 'marketing_from_email', \get_bloginfo( 'admin_email' ) );
        $attendee_email  = !empty($guest['email']) ? $guest['email'] : 'huesped@example.com';
        
        // Note: For a strictly standard ICS, lines should be folded and capped at 75 chars,
        // but modern clients handle longer lines well.
        $ics_content = "BEGIN:VCALENDAR\n"
                     . "VERSION:2.0\n"
                     . "PRODID:-//Artechia PMS//NONSGML v1.0//EN\n"
                     . "CALSCALE:GREGORIAN\n"
                     . "METHOD:REQUEST\n" // This indicates to the client it's an invitation
                     . "BEGIN:VEVENT\n"
                     . "UID:" . $uid . "\n"
                     . "DTSTAMP:" . gmdate('Ymd\THis\Z') . "\n"
                     . "DTSTART:" . $dt_start . "\n"
                     . "DTEND:" . $dt_end . "\n"
                     . "ORGANIZER;CN=\"{$organizer_name}\":mailto:{$organizer_email}\n"
                     . "ATTENDEE;ROLE=REQ-PARTICIPANT;PARTSTAT=NEEDS-ACTION;RSVP=TRUE:mailto:{$attendee_email}\n"
                     . "SUMMARY:" . $this->escape_ics_string( $summary ) . "\n"
                     . "DESCRIPTION:" . $this->escape_ics_string( $description ) . "\n"
                     . "X-ALT-DESC;FMTTYPE=text/html:" . $this->escape_ics_string( $html_desc ) . "\n"
                     . "LOCATION:" . $this->escape_ics_string( $property_address ) . "\n"
                     . "URL:" . $url . "\n"
                     . "STATUS:CONFIRMED\n"
                     . "SEQUENCE:0\n"
                     . "BEGIN:VALARM\n"
                     . "TRIGGER:-PT24H\n"
                     . "ACTION:DISPLAY\n"
                     . "DESCRIPTION:Recordatorio de Check-in mañana\n"
                     . "END:VALARM\n"
                     . "END:VEVENT\n"
                     . "END:VCALENDAR";

        // Save to temporary file in WP Uploads
        $upload_dir = wp_upload_dir();
        $temp_dir   = trailingslashit( $upload_dir['basedir'] ) . 'artechia-temp';
        
        if ( ! file_exists( $temp_dir ) ) {
            wp_mkdir_p( $temp_dir );
        }

        $file_name = 'reserva-' . strtolower( $booking['booking_code'] ) . '.ics';
        $ics_path = $temp_dir . '/' . $file_name;

        if ( false === file_put_contents( $ics_path, $ics_content ) ) {
            return null;
        }

        return $ics_path;
    }

    private function escape_ics_string( string $string ): string {
        return preg_replace( '/([\,;])/','\\\$1', $string );
    }
}
