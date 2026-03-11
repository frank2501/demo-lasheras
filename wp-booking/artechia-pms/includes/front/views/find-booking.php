<?php
/**
 * Find Booking shortcode view.
 * Rendered by [artechia_find_booking].
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
?>

<div class="artechia-find-booking">
    <div class="artechia-card">
        <h3><?php esc_html_e( 'Buscar mi reserva', 'artechia-pms' ); ?></h3>
        <p><?php esc_html_e( 'Ingresá tu código de confirmación y el email que usaste para reservar.', 'artechia-pms' ); ?></p>
        
        <form id="artechia-find-booking-form" class="artechia-form">
            <div class="artechia-form-row">
                <label for="find-booking-code"><?php esc_html_e( 'Código de confirmación', 'artechia-pms' ); ?></label>
                <input type="text" id="find-booking-code" name="code" required placeholder="Ej: ABC-123">
            </div>

            <div class="artechia-form-row">
                <label for="find-booking-email"><?php esc_html_e( 'Email', 'artechia-pms' ); ?></label>
                <input type="email" id="find-booking-email" name="email" required placeholder="email@ejemplo.com">
            </div>

            <div class="artechia-form-actions">
                <button type="submit" class="artechia-btn artechia-btn--primary" id="artechia-find-booking-submit">
                    <?php esc_html_e( 'Ver mi reserva', 'artechia-pms' ); ?>
                </button>
            </div>

            <p id="artechia-find-booking-error" class="artechia-error" style="display:none;"></p>
        </form>
    </div>
</div>
