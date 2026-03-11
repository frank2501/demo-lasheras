<?php
/**
 * MarketingService: handles guest segmentation and email campaigns.
 */
namespace Artechia\PMS\Services;

use Artechia\PMS\Repositories\GuestRepository;
use Artechia\PMS\Repositories\EmailTemplateRepository;
use Artechia\PMS\Logger;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class MarketingService {

    /**
     * Generate an HMAC-signed unsubscribe URL for a given email.
     */
    public static function unsubscribe_url( string $email ): string {
        $token = hash_hmac( 'sha256', strtolower( trim( $email ) ), wp_salt( 'auth' ) );
        return home_url( '/artechia-unsubscribe?email=' . urlencode( $email ) . '&token=' . $token );
    }

    /**
     * Verify an unsubscribe token for the given email.
     */
    public static function verify_unsubscribe_token( string $email, string $token ): bool {
        $expected = hash_hmac( 'sha256', strtolower( trim( $email ) ), wp_salt( 'auth' ) );
        return hash_equals( $expected, $token );
    }

    /**
     * Filter guests based on various criteria.
     */
    public function filter_guests( array $filters ): array {
        global $wpdb;
        $guests_table = \Artechia\PMS\DB\Schema::table( 'guests' );
        
        if ( ! empty( $filters['property_id'] ) ) {
            $bookings_table = \Artechia\PMS\DB\Schema::table( 'bookings' );
            $query = "SELECT DISTINCT g.* FROM {$guests_table} g 
                      JOIN {$bookings_table} b ON g.id = b.guest_id 
                      WHERE b.property_id = %d AND g.marketing_opt_out = 0";
            return $wpdb->get_results( $wpdb->prepare( $query, (int) $filters['property_id'] ), \ARRAY_A ) ?: [];
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        return $wpdb->get_results( "SELECT * FROM {$guests_table} WHERE marketing_opt_out = 0", \ARRAY_A ) ?: [];
    }

    /**
     * Send a marketing campaign to a list of guests.
     */
    public function send_campaign( int $template_id, array $guest_ids, array $extra_placeholders = [] ): array {
        $template_repo = new EmailTemplateRepository();
        $template = $template_repo->find( $template_id );

        if ( ! (bool) Settings::get( 'marketing_enabled', '0' ) ) {
            Logger::warning( 'marketing.disabled_exit', "Marketing is disabled in settings. Cannot send campaign." );
            return [ 'error' => 'MARKETING_DISABLED', 'message' => 'El sistema de marketing está deshabilitado.' ];
        }

        if ( ! $template ) {
            Logger::error( 'marketing.campaign_start_fail', "Template {$template_id} not found." );
            return [ 'error' => 'TEMPLATE_NOT_FOUND' ];
        }

        Logger::info( 'marketing.campaign_start', "Starting campaign with template {$template['event_type']} to " . count( $guest_ids ) . " guests." );

        // Generate unique campaign ID for tracking
        $campaign_id = uniqid( 'camp_', true );

        $guest_repo = new GuestRepository();
        $email_svc  = new EmailService();
        $sent_count = 0;
        $errors     = [];

        // Common placeholders for the campaign
        $promo_code = $extra_placeholders['promo_code'] ?? '';
        $promo_desc = $extra_placeholders['promo_description'] ?? '';
        
        // Get property name if template is property-specific.
        $property_name = get_bloginfo( 'name' );
        if ( ! empty( $template['property_id'] ) ) {
            $prop = ( new \Artechia\PMS\Repositories\PropertyRepository() )->find( (int) $template['property_id'] );
            if ( $prop ) {
                $property_name = $prop['name'];
            }
        }

        $search_page_id = (int) Settings::get( 'search_page_id', 0 );
        $booking_url    = $search_page_id ? get_permalink( $search_page_id ) : home_url( '/' );

        foreach ( $guest_ids as $guest_id ) {
            $guest = $guest_repo->find( (int) $guest_id );
            if ( ! $guest || ! $guest['email'] ) {
                continue;
            }

            // Skip opted-out guests
            if ( ! empty( $guest['marketing_opt_out'] ) ) {
                continue;
            }

            $placeholders = array_merge( [
                '{guest_name}'        => esc_html( trim( ( $guest['first_name'] ?? '' ) . ' ' . ( $guest['last_name'] ?? '' ) ) ),
                '{promo_code}'        => esc_html( $promo_code ),
                '{promo_description}' => esc_html( $promo_desc ),
                '{booking_url}'       => esc_url( $booking_url ),
                '{unsubscribe_url}'   => esc_url( self::unsubscribe_url( $guest['email'] ) ),
                '{property_name}'     => esc_html( $property_name ),
            ], $extra_placeholders );

            $event_type_str = $template['event_type'] ?? 'marketing_custom';
            
            // Pass the template content directly to avoid redundant lookups in EmailService
            $extra_data = array_merge( $placeholders, [
                'guest'            => $guest,
                'property_id'      => $template['property_id'] ?? 0,
                'custom_subject'   => $template['subject'] ?? '',
                'custom_body_html' => $template['body_html'] ?? '',
                'campaign_id'      => $campaign_id,
                'tracking_email'   => $guest['email'],
            ]);

            $result = $email_svc->send_marketing( $guest['email'], $event_type_str, $extra_data );
            
            if ( $result ) {
                $sent_count++;
            } else {
                $errors[] = "Failed to send to {$guest['email']}";
                Logger::warning( 'marketing.guest_send_fail', "Failed to send marketing email to {$guest['email']} using template {$event_type_str}" );
            }
        }

        // Collect recipient emails for history
        $recipient_emails = [];
        foreach ( $guest_ids as $gid ) {
            $g = $guest_repo->find( (int) $gid );
            if ( $g && $g['email'] ) {
                $recipient_emails[] = $g['email'];
            }
        }

        Logger::info( 'marketing.campaign_finished', "Campaign finished. Sent: {$sent_count}, Errors: " . count( $errors ), 'marketing', null, [
            'campaign_id'   => $campaign_id,
            'template_name' => $template['subject'] ?? $template['event_type'] ?? '—',
            'promo_code'    => $promo_code ?: null,
            'recipients'    => $recipient_emails,
            'error_details' => $errors ?: null,
        ] );

        return [
            'success'    => ( $sent_count > 0 || empty( $guest_ids ) ),
            'sent_count' => $sent_count,
            'errors'     => $errors
        ];
    }
}
