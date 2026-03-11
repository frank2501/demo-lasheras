<?php
/**
 * Search form shortcode view.
 * Rendered by [artechia_search].
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$check_in_time = \Artechia\PMS\Services\Settings::check_in_time();
$check_out_time = \Artechia\PMS\Services\Settings::check_out_time();
$min_date = wp_date( 'Y-m-d' );
$default_checkout = wp_date( 'Y-m-d', strtotime( '+1 day' ) );
?>

<div class="artechia-search" id="artechia-search-form">
    <h2 class="artechia-search__title"><?php esc_html_e( 'Reservá tu estadía', 'artechia-pms' ); ?></h2>
    <form class="artechia-search__form" data-artechia-action="search">
        <div class="artechia-search__row">
            <div class="artechia-search__field artechia-search__field--dates">
                <label for="artechia-dates"><?php esc_html_e( 'Fechas', 'artechia-pms' ); ?></label>
                <div class="artechia-date-wrap">
                    <svg class="artechia-date-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    <input type="text" id="artechia-dates" class="artechia-daterange-input"
                           placeholder="<?php esc_attr_e( 'Check-in - Check-out', 'artechia-pms' ); ?>"
                           readonly>
                    <input type="hidden" name="check_in" id="artechia-checkin">
                    <input type="hidden" name="check_out" id="artechia-checkout">
                </div>
                <span class="artechia-nights-count" id="artechia-nights-count" style="display:none;"></span>
            </div>

            <div class="artechia-search__field artechia-search__field--small">
                <label for="artechia-adults"><?php esc_html_e( 'Adultos', 'artechia-pms' ); ?></label>
                <select id="artechia-adults" name="adults">
                    <?php for ( $i = 1; $i <= 10; $i++ ) : ?>
                        <option value="<?php echo $i; ?>" <?php selected( $i, 2 ); ?>><?php echo $i; ?></option>
                    <?php endfor; ?>
                </select>
            </div>

            <div class="artechia-search__field artechia-search__field--small">
                <label for="artechia-children"><?php esc_html_e( 'Niños', 'artechia-pms' ); ?></label>
                <select id="artechia-children" name="children">
                    <?php for ( $i = 0; $i <= 6; $i++ ) : ?>
                        <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
                    <?php endfor; ?>
                </select>
            </div>
        </div>

        <button type="submit" class="artechia-btn artechia-btn--primary" id="artechia-search-submit">
            <?php esc_html_e( 'Buscar disponibilidad', 'artechia-pms' ); ?>
        </button>
    </form>
    <div class="artechia-search__error" id="artechia-search-error" style="display:none;"></div>
</div>
