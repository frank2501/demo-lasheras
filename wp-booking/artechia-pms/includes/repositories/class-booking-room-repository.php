<?php
namespace Artechia\PMS\Repositories;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class BookingRoomRepository extends BaseRepository {

    protected function table_name(): string {
        return 'booking_rooms';
    }

    protected function fillable(): array {
        return [
            'booking_id', 'room_type_id', 'room_unit_id', 'adults', 'children',
            'rate_per_night_json', 'subtotal'
        ];
    }

    protected function formats(): array {
        return [
            'booking_id'   => '%d',
            'room_type_id' => '%d',
            'room_unit_id' => '%d',
            'adults'       => '%d',
            'children'     => '%d',
            'subtotal'     => '%f',
        ];
    }
}
