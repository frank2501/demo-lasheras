<?php
/**
 * Service for iCal Import/Export and Synchronization.
 */
namespace Artechia\PMS\Services;

use Artechia\PMS\Repositories\BookingRepository;
use Artechia\PMS\Repositories\ICalFeedRepository;
use Artechia\PMS\Repositories\ICalEventRepository;
use Artechia\PMS\Repositories\ConflictRepository;
use Artechia\PMS\Repositories\RoomUnitRepository;
use Artechia\PMS\Logger;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class ICalService {

    private $feeds;
    private $events;
    private $conflicts;
    private $bookings;

    public function __construct() {
        $this->feeds     = new ICalFeedRepository();
        $this->events    = new ICalEventRepository();
        $this->conflicts = new ConflictRepository();
        $this->bookings  = new BookingRepository();
    }

    /* ── Export ─────────────────────────────────────── */

    public function generate_feed( int $unit_id ): string {
        $repo = new RoomUnitRepository();
        $unit = $repo->find( $unit_id );
        if ( ! $unit ) return '';

        // Fetch active bookings
        $bookings = $this->bookings->find_for_calendar( 
            (int) $unit['property_id'], 
            date('Y-m-d', strtotime('-1 month')), 
            date('Y-m-d', strtotime('+1 year')) 
        );

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Artechia PMS//NONSGML v1.0//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
        ];

        foreach ( $bookings as $b ) {
            // Only include relevant statuses
            if ( ! in_array( $b['status'], ['confirmed', 'checked_in', 'paid', 'deposit_paid'] ) ) {
                continue;
            }
            // Only for this unit
            if ( (int) $b['room_unit_id'] !== $unit_id ) {
                continue;
            }

            $uid = 'art-' . $b['booking_code'] . '@' . parse_url( site_url(), PHP_URL_HOST );
            $dtstart = date( 'Ymd', strtotime( $b['check_in'] ) );
            $dtend   = date( 'Ymd', strtotime( $b['check_out'] ) ); // Exclusive in iCal (check)
            // iCal dates are inclusive start, exclusive end. Our DB check_out is the departure day.
            // So a booking 10-12 means nights of 10 and 11. Checkout 12.
            // iCal DTSTART:20230110 DTEND:20230112 is correct (10 full day, 11 full day, ends 12 start of day).
            
            $lines[] = 'BEGIN:VEVENT';
            $lines[] = 'UID:' . $uid;
            $lines[] = 'DTSTART;VALUE=DATE:' . $dtstart;
            $lines[] = 'DTEND;VALUE=DATE:' . $dtend;
            $lines[] = 'SUMMARY:Booked';
            $lines[] = 'DESCRIPTION:Artechia Booking ' . $b['booking_code'];
            $lines[] = 'END:VEVENT';
        }

        $lines[] = 'END:VCALENDAR';
        return implode( "\r\n", $lines );
    }

    /* ── Import / Sync ──────────────────────────────── */

    /* ── Import / Sync ──────────────────────────────── */

    /**
     * Sync all active feeds.
     */
    public function sync_all(): array {
        $results = [];
        global $wpdb;
        $table = $this->feeds->table();
        $feeds = $wpdb->get_results( "SELECT * FROM {$table} WHERE is_active = 1", \ARRAY_A );

        foreach ( $feeds as $feed ) {
            // Check interval (skip if synced recently, e.g. < 5 min ago even if cron is faster)
            // But let's just rely on cron schedule for now.
            $results[ $feed['id'] ] = $this->fetch_and_sync( (int) $feed['id'] );
        }
        return $results;
    }

    /**
     * Process a single feed.
     */
    public function fetch_and_sync( int $feed_id ): array {
        // 1. Transient Lock for Concurrency
        $lock_key = 'artechia_ical_sync_feed_' . $feed_id;
        if ( get_transient( $lock_key ) ) {
            Logger::info( 'ical.sync_lock', "Skipping sync for feed {$feed_id} due to active lock.", 'ical', $feed_id );
            return [ 'error' => 'Sync in progress' ];
        }
        set_transient( $lock_key, 1, 60 ); // 1 minute lock

        try {
            $feed = $this->feeds->find( $feed_id );
            if ( ! $feed ) return [ 'error' => 'Feed not found' ];

            // Fetch
            $response = wp_remote_get( $feed['url'], [ 'timeout' => 15, 'sslverify' => false ] );
            if ( is_wp_error( $response ) ) {
                $this->feeds->update( $feed_id, [
                    'last_sync_at'     => current_time( 'mysql' ),
                    'last_sync_status' => 'error',
                    'last_error'       => $response->get_error_message(),
                ]);
                return [ 'error' => $response->get_error_message() ];
            }

            $body = wp_remote_retrieve_body( $response );
            if ( empty( $body ) ) {
                $this->feeds->update( $feed_id, [ 'last_sync_status' => 'error', 'last_error' => 'Empty body' ] );
                return [ 'error' => 'Empty body' ];
            }

            // Safety: Check for basic iCal structure
            if ( strpos( $body, 'BEGIN:VCALENDAR' ) === false ) {
                 $this->feeds->update( $feed_id, [ 'last_sync_status' => 'error', 'last_error' => 'Invalid iCal format' ] );
                 return [ 'error' => 'Invalid iCal format' ];
            }

            // Parse
            $events = $this->parse_ics( $body );
            $stats  = [ 'processed' => 0, 'created' => 0, 'updated' => 0, 'conflicts' => 0, 'removed' => 0 ];
            $now    = current_time( 'mysql' ); // usage for last_seen

            // If no events are parsed, it might be an empty feed or parsing error.
            // We should not remove existing events if the feed is empty.
            if ( empty( $events ) ) {
                Logger::warning( 'ical.empty_feed', "No events parsed from feed {$feed_id}. Skipping event processing and removal.", 'ical', $feed_id );
                $this->feeds->update( $feed_id, [
                    'last_sync_at'     => $now,
                    'last_sync_status' => 'ok', // Consider it 'ok' if no events, not an error.
                    'last_error'       => 'No events found in feed.',
                ]);
                return [ 'processed' => 0, 'created' => 0, 'updated' => 0, 'conflicts' => 0, 'removed' => 0, 'warning' => 'No events found in feed.' ];
            }

            // Use transaction? WP doesn't support nested well, but we can wrap entire feed logic.
            // For now, atomic operations per event + idempotency via UPSERT logic.

            foreach ( $events as $event ) {
                $stats['processed']++;
                $hash = md5( serialize( [ $event['start'], $event['end'] ] ) ); // Simple hash of dates
                
                // Find existing
                $existing = $this->events->find_by_uid( $feed_id, $event['uid'] );
                
                if ( $existing ) {
                    // Update last_seen
                    $this->events->update( $existing['id'], [ 'last_seen_at' => $now ] );
                    
                    // Check change
                    if ( $existing['event_hash'] !== $hash ) {
                        // Changed dates
                        $this->process_change( $feed, $existing, $event );
                        $this->events->update( $existing['id'], [
                            'start_date' => $event['start'],
                            'end_date'   => $event['end'],
                            'event_hash' => $hash,
                            'summary'    => substr( $event['summary'], 0, 255 ),
                        ]);
                        $stats['updated']++;
                    }
                } else {
                    // New event
                    // Use insert + ignore if race condition? Repo creates regularly.
                    // We rely on UNIQUE KEY exception handling if needed, but Repo logic is simple.
                    // Let's assume low concurrency risk with lock.
                    $event_id = $this->events->create([
                        'feed_id'      => $feed_id,
                        'external_uid' => $event['uid'],
                        'start_date'   => $event['start'],
                        'end_date'     => $event['end'],
                        'summary'      => substr( $event['summary'], 0, 255 ),
                        'description'  => '', 
                        'event_hash'   => $hash,
                        'last_seen_at' => $now,
                    ]);
                    
                    if ( $event_id ) {
                        $conflict = $this->process_new( $feed, $event_id, $event );
                        if ( $conflict ) $stats['conflicts']++;
                        else $stats['created']++;
                    } else {
                        // Likely duplicate key (race condition despite lock?) or DB error
                        Logger::warning( 'ical.duplicate', "Duplicate UID ignored: {$event['uid']}", 'ical', $feed_id );
                    }
                }
            }

            // Removal (not seen in this sync)
            // Only remove if events were successfully parsed from the feed.
            // This prevents mass deletions if the remote iCal file is temporarily empty or malformed.
            $removed = $this->process_removals( $feed, $now );
            $stats['removed'] = $removed;

            // Update feed status
            $this->feeds->update( $feed_id, [
                'last_sync_at'     => $now,
                'last_sync_status' => 'ok',
                'last_error'       => '',
            ]);

            return $stats;

        } finally {
            delete_transient( $lock_key );
        }
    }

    private function parse_ics( string $content ): array {
        $events = [];
        // Limit content size to avoid regex DoS (e.g. 2MB)
        if ( strlen( $content ) > 2 * 1024 * 1024 ) {
             Logger::error( 'ical.parse', 'Feed too large, truncating or skipping.', 'ical' );
             return [];
        }

        // Regex to find VEVENT blocks
        preg_match_all( '/BEGIN:VEVENT(.*?)END:VEVENT/s', $content, $matches );
        
        foreach ( $matches[1] as $block ) {
            $uid     = $this->extract_property( $block, 'UID' );
            $dtstart = $this->extract_date( $block, 'DTSTART' );
            $dtend   = $this->extract_date( $block, 'DTEND' );
            $summary = $this->extract_property( $block, 'SUMMARY' );

            if ( $uid && $dtstart && $dtend ) {
                $events[] = [
                    'uid'     => $uid,
                    'start'   => $dtstart,
                    'end'     => $dtend,
                    'summary' => $summary ?: 'External Event',
                ];
            }
        }
        return $events;
    }

    private function extract_property( string $block, string $prop ): string {
        if ( preg_match( "/^{$prop}(?:;[^:]*)?:(.*)$/m", $block, $m ) ) {
            return trim( $m[1] );
        }
        return '';
    }

    private function extract_date( string $block, string $prop ): ?string {
        $val = $this->extract_property( $block, $prop );
        if ( ! $val ) return null;
        // Simple YYYYMMDD parsing (value=DATE)
        if ( strlen( $val ) === 8 ) {
            return date( 'Y-m-d', strtotime( $val ) );
        }
        // DateTime YYYYMMDDTHHMMSS...
        return date( 'Y-m-d', strtotime( substr( $val, 0, 8 ) ) ); 
    }

    /* ── Logic ── */

    private function process_new( array $feed, int $event_id, array $event ): bool {
        // Check overlap
        $is_blocked = ! $this->bookings->is_unit_available(
            (int) $feed['room_unit_id'],
            (int) $feed['property_id'],
            $event['start'],
            $event['end']
        );

        if ( $is_blocked ) {
            if ( $feed['conflict_policy'] === 'mark_conflict' || $feed['conflict_policy'] === 'mark_conflict_only' ) {
                // Try to identify blocking booking
                // We'll perform a quick query to find bookings overlapping
                // Overlap: (StartA <= EndB) and (EndA >= StartB)
                // Use `find_overlapping` logic if available or direct query.
                // Assuming BookingRepository has `find_overlapping`. If not, we fall back to NULL.
                // Or implementing a quick one-off here.
                global $wpdb;
                $t  = $this->bookings->table();
                $br = \Artechia\PMS\DB\Schema::table( 'booking_rooms' );
                $local_id = $wpdb->get_var( $wpdb->prepare(
                    "SELECT b.id FROM {$t} b
                     JOIN {$br} br ON b.id = br.booking_id
                     WHERE br.room_unit_id = %d 
                       AND b.status IN ('confirmed','checked_in'" . ( BookingRepository::pending_blocks_unit() ? ",'pending'" : '' ) . ",'hold')
                       AND b.check_in < %s AND b.check_out > %s
                     LIMIT 1",
                    $feed['room_unit_id'],
                    $event['end'],
                    $event['start']
                ) );

                $this->conflicts->create([
                    'property_id'   => $feed['property_id'],
                    'room_unit_id'  => $feed['room_unit_id'],
                    'local_booking_id' => $local_id, // Now populated
                    'ical_event_id' => $event_id,
                    'start_date'    => $event['start'],
                    'end_date'      => $event['end'],
                    'type'          => 'overlap',
                ]);
                Logger::warning( 'ical.conflict', "Overlap detected for event {$event['uid']}", 'ical', $feed['id'] );
                return true;
            }
            // policy: skip
            Logger::info( 'ical.skip', "Skipping overlap for event {$event['uid']}", 'ical', $feed['id'] );
            return false;
        }

        // Create Booking
        $this->create_ical_booking( $feed, $event_id, $event );
        return false;
    }

    private function process_change( array $feed, array $db_event, array $new_event ): void {
        if ( $db_event['booking_id'] ) {
            $b = $this->bookings->find( (int) $db_event['booking_id'] );
            if ( $b ) {
                // Update booking dates
                $this->bookings->update( $b['id'], [
                    'check_in'  => $new_event['start'],
                    'check_out' => $new_event['end'],
                    'updated_at' => current_time( 'mysql' ),
                ]);
                Logger::info( 'ical.update', "Updated booking dates for {$b['booking_code']}", 'ical', $feed['id'] );
            }
        }
    }

    private function process_removals( array $feed, string $sync_time ): int {
        global $wpdb;
        $t = $this->events->table();
        // Find events not seen since sync_time (allow 30s buffer for execution time)
        $cutoff = date('Y-m-d H:i:s', strtotime( $sync_time ) - 30 );
        
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$t} WHERE feed_id = %d AND last_seen_at < %s",
            $feed['id'], $cutoff
        ), \ARRAY_A );

        $count = 0;
        foreach ( $rows as $row ) {
            if ( $row['booking_id'] ) {
                $b = $this->bookings->find( (int) $row['booking_id'] );
                // SAFETY: Only cancel if source is ical
                if ( $b && $b['source'] === 'ical' ) {
                    $this->bookings->update_status( (int) $row['booking_id'], 'cancelled', [
                        'cancel_reason' => 'iCal event removed from feed',
                        'cancelled_at'  => current_time( 'mysql' ),
                    ]);
                    Logger::info( 'ical.remove', "Cancelled booking for removed event {$row['external_uid']}", 'ical', $feed['id'] );
                } else {
                     Logger::warning( 'ical.remove_skip', "Skipping cancellation for non-ical booking {$row['booking_id']}", 'ical', $feed['id'] );
                }
                
                // unlink regardless to prevent future confusion
                $this->events->update( $row['id'], [ 'booking_id' => null ] );
            }
            $count++;
        }
        return $count;
    }

    private function create_ical_booking( array $feed, int $event_id, array $event ): void {
        // Get unit info for room_type_id
        $unit_repo = new RoomUnitRepository();
        $unit = $unit_repo->find( (int) $feed['room_unit_id'] );
        if ( ! $unit ) {
             Logger::error( 'ical.create_failed', "Unit not found for feed {$feed['id']}" );
             return;
        }

        $booking_code = 'ICAL-' . substr( md5( $event['uid'] . microtime() ), 0, 8 );
        
        $booking_id = $this->bookings->create([
            'booking_code' => $booking_code,
            'property_id'  => $feed['property_id'],
            'guest_id'     => 0, // System/External
            'check_in'     => $event['start'],
            'check_out'    => $event['end'],
            'nights'       => (strtotime($event['end']) - strtotime($event['start'])) / 86400,
            'status'       => 'confirmed', // Blocked
            'source'       => 'ical',
            'source_ref'   => $feed['name'] . ' (' . $feed['channel_name'] . ')',
            'grand_total'  => 0,
            'access_token' => hash('sha256', 'ical-' . $event_id . uniqid()),
        ]);

        if ( $booking_id ) {
            // Assign Unit
            $this->bookings->create_room([
                'booking_id' => $booking_id,
                'room_type_id' => (int) $unit['room_type_id'],
                'room_unit_id' => (int) $feed['room_unit_id'],
                'subtotal' => 0
            ]);
            
            // Link event
            $this->events->update( $event_id, [ 'booking_id' => $booking_id ] );
            Logger::info( 'ical.created', "Created booking {$booking_code}", 'ical', $feed['id'] );
        }
    }
}
