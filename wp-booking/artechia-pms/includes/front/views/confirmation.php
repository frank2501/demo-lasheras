<?php
/**
 * Confirmation shortcode view.
 * Rendered by [artechia_confirmation]. Data from URL params set by JS.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
?>

<div class="artechia-confirmation" id="artechia-confirmation">
    <?php $artechia_step = 4; include __DIR__ . '/partials/stepper.php'; ?>
    <div class="artechia-confirmation__loading" id="artechia-confirmation-loading">
        <div class="artechia-spinner"></div>
    </div>

    <div class="artechia-confirmation__content" id="artechia-confirmation-content" style="display:none;">
        
        <div class="artechia-confirmation__icon" id="artechia-confirmation-main-icon">✓</div>

        <!-- Post-payment confirmation alert (moved inside for better positioning) -->
        <div id="artechia-confirmation-post-payment-alert" class="artechia-alert" style="display:none; margin-bottom: 20px; border:none; background:transparent; padding:0; text-align:center;">
            <div class="artechia-alert__content">
                <h2 id="artechia-post-confirm-alert-title" style="margin:0; font-size: 1.8em;"></h2>
                <p id="artechia-post-confirm-alert-msg" style="margin-top:10px; font-size: 1.1em; color: #666;"></p>
            </div>
            <div id="artechia-post-confirm-alert-loader" class="artechia-spinner artechia-spinner--small" style="display:none; margin: 10px auto;"></div>
        </div>

        <div id="artechia-confirmation-header">
            <h2 class="artechia-confirmation__title"><?php esc_html_e( '¡Reserva recibida!', 'artechia-pms' ); ?></h2>
            <p class="artechia-confirmation__message" id="artechia-confirmation-base-msg">
                <?php esc_html_e( 'Tu reserva fue registrada exitosamente. Te enviamos un email con los detalles.', 'artechia-pms' ); ?>
            </p>
        </div>

        <div class="artechia-confirmation__card">
            <div class="artechia-confirmation__row">
                <span class="artechia-confirmation__label"><?php esc_html_e( 'Código de reserva', 'artechia-pms' ); ?></span>
                <span class="artechia-confirmation__value artechia-confirmation__code" id="artechia-confirm-code"></span>
            </div>
            <div class="artechia-confirmation__row">
                <span class="artechia-confirmation__label"><?php esc_html_e( 'Estado', 'artechia-pms' ); ?></span>
                <span class="artechia-confirmation__value artechia-badge artechia-badge--pending"><?php esc_html_e( 'Pendiente de confirmación', 'artechia-pms' ); ?></span>
            </div>
            <div class="artechia-confirmation__row">
                <span class="artechia-confirmation__label"><?php esc_html_e( 'Total', 'artechia-pms' ); ?></span>
                <span class="artechia-confirmation__value" id="artechia-confirm-total"></span>
            </div>
        </div>

        <p class="artechia-confirmation__next-steps" id="artechia-confirmation-next-steps">
            <?php esc_html_e( 'Recibirás un email cuando tu reserva sea confirmada por el establecimiento.', 'artechia-pms' ); ?>
        </p>



        <div class="artechia-confirmation__actions">
            <a href="#" id="artechia-confirm-manage" class="artechia-btn artechia-btn--primary">
                <?php esc_html_e( 'Ver mi reserva', 'artechia-pms' ); ?>
            </a>
        </div>
    </div>
</div>
