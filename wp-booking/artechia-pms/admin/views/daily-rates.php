<?php
/**
 * Daily Rate Overrides admin view.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

use Artechia\PMS\Repositories\DailyRateRepository;
use Artechia\PMS\Repositories\RoomTypeRepository;
use Artechia\PMS\Repositories\RatePlanRepository;
use Artechia\PMS\Repositories\PropertyRepository;
use Artechia\PMS\Helpers\Helpers;
use Artechia\PMS\Logger;

$repo      = new DailyRateRepository();
$rt_repo   = new RoomTypeRepository();
$rp_repo   = new RatePlanRepository();
$prop_repo = new PropertyRepository();

$property     = $prop_repo->get_default();
$prop_id      = absint( $_GET['property_id'] ?? ( $property['id'] ?? 0 ) );
$room_type_id = absint( $_GET['room_type_id'] ?? 0 );
$rate_plan_id = absint( $_GET['rate_plan_id'] ?? 0 );

$room_types = $rt_repo->all_with_counts( $prop_id );
$rate_plans = $rp_repo->all( [ 'where' => [ 'property_id' => $prop_id ], 'orderby' => 'name', 'order' => 'ASC' ] );

if ( ! $room_type_id && ! empty( $room_types ) ) {
    $room_type_id = (int) $room_types[0]['id'];
}
if ( ! $rate_plan_id ) {
    $default_rp = $rp_repo->get_default( $prop_id );
    $rate_plan_id = $default_rp ? (int) $default_rp['id'] : ( ! empty( $rate_plans ) ? (int) $rate_plans[0]['id'] : 0 );
}

// Handle bulk save.
if ( isset( $_POST['artechia_save_daily'] ) && check_admin_referer( 'artechia_save_daily' ) ) {
    $from  = sanitize_text_field( $_POST['date_from'] ?? '' );
    $to    = sanitize_text_field( $_POST['date_to'] ?? '' );
    $price = floatval( $_POST['price_per_night'] ?? 0 );

    if ( $from && $to && $price > 0 ) {
        $values = [
            'price_per_night' => $price,
            'extra_adult'     => floatval( $_POST['extra_adult'] ?? 0 ),
            'extra_child'     => floatval( $_POST['extra_child'] ?? 0 ),
            'min_stay'        => absint( $_POST['min_stay'] ?? 1 ),
            'stop_sell'       => ! empty( $_POST['stop_sell'] ) ? 1 : 0,
        ];

        $count = $repo->bulk_upsert( $room_type_id, $rate_plan_id, $from, $to, $values );
        Logger::info( 'daily_rates.updated', "Saved {$count} daily overrides", 'room_type', $room_type_id );
        echo '<div class="notice notice-success"><p>' . sprintf( esc_html__( '%d días actualizados.', 'artechia-pms' ), $count ) . '</p></div>';
    } else {
        echo '<div class="notice notice-error"><p>' . esc_html__( 'Completá fechas y precio.', 'artechia-pms' ) . '</p></div>';
    }
}

// Show current month overrides.
$month = sanitize_text_field( $_GET['month'] ?? date( 'Y-m' ) );
$month_from = $month . '-01';
$month_to   = date( 'Y-m-t', strtotime( $month_from ) );
$overrides = ( $room_type_id && $rate_plan_id )
    ? $repo->for_range( $room_type_id, $rate_plan_id, $month_from, $month_to )
    : [];

// Index by date.
$by_date = [];
foreach ( $overrides as $o ) {
    $by_date[ $o['rate_date'] ] = $o;
}
?>

<div class="wrap artechia-wrap">
    <h1><?php esc_html_e( 'Tarifas Diarias (Overrides)', 'artechia-pms' ); ?></h1>

    <!-- Filters -->
    <div class="artechia-panel">
        <form method="get" class="artechia-filter-form">
            <input type="hidden" name="page" value="artechia-daily-rates">
            <label><strong><?php esc_html_e( 'Tipo:', 'artechia-pms' ); ?></strong>
                <select name="room_type_id" onchange="this.form.submit()">
                    <?php foreach ( $room_types as $rt ) : ?>
                        <option value="<?php echo esc_attr( $rt['id'] ); ?>" <?php selected( $room_type_id, $rt['id'] ); ?>><?php echo esc_html( $rt['name'] ); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label><strong><?php esc_html_e( 'Plan:', 'artechia-pms' ); ?></strong>
                <select name="rate_plan_id" onchange="this.form.submit()">
                    <?php foreach ( $rate_plans as $rp ) : ?>
                        <option value="<?php echo esc_attr( $rp['id'] ); ?>" <?php selected( $rate_plan_id, $rp['id'] ); ?>><?php echo esc_html( $rp['name'] ); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label><strong><?php esc_html_e( 'Mes:', 'artechia-pms' ); ?></strong>
                <input type="month" name="month" value="<?php echo esc_attr( $month ); ?>" onchange="this.form.submit()">
            </label>
        </form>
    </div>

    <!-- Calendar view of existing overrides -->
    <?php if ( $room_type_id && $rate_plan_id ) : ?>
        <div class="artechia-panel">
            <h2><?php echo esc_html( date( 'F Y', strtotime( $month_from ) ) ); ?></h2>

            <div class="artechia-matrix">
                <table>
                    <thead>
                        <tr>
                            <?php foreach ( ['Lun','Mar','Mié','Jue','Vie','Sáb','Dom'] as $d ) : ?>
                                <th><?php echo esc_html( $d ); ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $first_day = (int) date( 'N', strtotime( $month_from ) ); // 1=Mon
                        $days_in_month = (int) date( 't', strtotime( $month_from ) );
                        $day = 1;
                        for ( $week = 0; $week < 6 && $day <= $days_in_month; $week++ ) :
                        ?>
                            <tr>
                                <?php for ( $dow = 1; $dow <= 7; $dow++ ) : ?>
                                    <?php if ( ( $week === 0 && $dow < $first_day ) || $day > $days_in_month ) : ?>
                                        <td style="background:#f9fafb;"></td>
                                    <?php else :
                                        $date_str = $month . '-' . str_pad( $day, 2, '0', STR_PAD_LEFT );
                                        $override = $by_date[ $date_str ] ?? null;
                                    ?>
                                        <td style="<?php echo $override ? 'background:#fef3c7;' : ''; ?>">
                                            <small style="color:#6b7280;"><?php echo $day; ?></small><br>
                                            <?php if ( $override ) : ?>
                                                <strong><?php echo esc_html( Helpers::format_price( (float) $override['price_per_night'] ) ); ?></strong>
                                                <?php if ( $override['stop_sell'] ) : ?><br><span style="color:#ef4444;font-size:0.75em;">STOP</span><?php endif; ?>
                                            <?php else : ?>
                                                <span style="color:#d1d5db;">—</span>
                                            <?php endif; ?>
                                        </td>
                                    <?php $day++; endif; ?>
                                <?php endfor; ?>
                            </tr>
                        <?php endfor; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

    <!-- Bulk override form -->
    <div class="artechia-panel">
        <h2><?php esc_html_e( 'Aplicar override por rango', 'artechia-pms' ); ?></h2>

        <form method="post" class="artechia-form">
            <?php wp_nonce_field( 'artechia_save_daily' ); ?>

            <table class="form-table">
                <tr>
                    <th><?php esc_html_e( 'Rango de fechas', 'artechia-pms' ); ?></th>
                    <td>
                        <input type="date" name="date_from" required> — <input type="date" name="date_to" required>
                    </td>
                </tr>
                <tr>
                    <th><label for="price_per_night"><?php esc_html_e( 'Precio por noche', 'artechia-pms' ); ?></label></th>
                    <td><input type="number" name="price_per_night" step="0.01" min="0" class="regular-text" required></td>
                </tr>
                <tr>
                    <th><label for="extra_adult"><?php esc_html_e( 'Adulto extra', 'artechia-pms' ); ?></label></th>
                    <td><input type="number" name="extra_adult" step="0.01" min="0" class="regular-text" value="0"></td>
                </tr>
                <tr>
                    <th><label for="min_stay"><?php esc_html_e( 'Estadía mínima', 'artechia-pms' ); ?></label></th>
                    <td><input type="number" name="min_stay" min="1" class="small-text" value="1"></td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Opciones', 'artechia-pms' ); ?></th>
                    <td><label><input type="checkbox" name="stop_sell" value="1"> <?php esc_html_e( 'Stop Sell (cerrar venta)', 'artechia-pms' ); ?></label></td>
                </tr>
            </table>

            <?php submit_button( __( 'Aplicar Override', 'artechia-pms' ), 'primary', 'artechia_save_daily' ); ?>
        </form>
    </div>
</div>
