<?php
/**
 * Checkout shortcode view.
 * Rendered by [artechia_checkout]. Data loaded via JS from URL params.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
?>

<div class="artechia-checkout" id="artechia-checkout">
    <?php $artechia_step = 3; include __DIR__ . '/partials/stepper.php'; ?>
    <div class="artechia-checkout__expired" id="artechia-checkout-expired" style="display:none;">
        <h2><?php esc_html_e( 'Sesión expirada', 'artechia-pms' ); ?></h2>
        <p><?php esc_html_e( 'Tu reserva temporal ha expirado. Por favor, reiniciá la búsqueda.', 'artechia-pms' ); ?></p>
        <a href="<?php echo esc_url( get_permalink( \Artechia\PMS\Services\Settings::get( 'search_page_id', 0 ) ) ); ?>"
           class="artechia-btn artechia-btn--primary">
            <?php esc_html_e( 'Volver a buscar', 'artechia-pms' ); ?>
        </a>
    </div>

    <div class="artechia-checkout__content" id="artechia-checkout-content">
        <div class="artechia-checkout__left">
            <h2 class="artechia-checkout__title"><?php esc_html_e( 'Completá tus datos', 'artechia-pms' ); ?></h2>

            <form class="artechia-checkout__form" id="artechia-checkout-form" data-artechia-action="checkout">
                <input type="hidden" name="checkout_token" id="artechia-checkout-token">

                <div class="artechia-checkout__section">
                    <h3><?php esc_html_e( 'Datos del huésped', 'artechia-pms' ); ?></h3>

                    <div class="artechia-form-row">
                        <div class="artechia-form-field">
                            <label for="guest-first-name"><?php esc_html_e( 'Nombre', 'artechia-pms' ); ?> *</label>
                            <input type="text" id="guest-first-name" name="guest[first_name]" required>
                        </div>
                        <div class="artechia-form-field">
                            <label for="guest-last-name"><?php esc_html_e( 'Apellido', 'artechia-pms' ); ?> *</label>
                            <input type="text" id="guest-last-name" name="guest[last_name]" required>
                        </div>
                    </div>

                    <div class="artechia-form-row">
                        <div class="artechia-form-field">
                            <label for="guest-email"><?php esc_html_e( 'Email', 'artechia-pms' ); ?> *</label>
                            <input type="email" id="guest-email" name="guest[email]" required>
                        </div>
                        <div class="artechia-form-field">
                            <label for="guest-phone"><?php esc_html_e( 'Teléfono', 'artechia-pms' ); ?> *</label>
                            <input type="tel" id="guest-phone" name="guest[phone]" required pattern="\d*" title="<?php esc_attr_e( 'Solo números', 'artechia-pms' ); ?>" inputmode="numeric">
                        </div>
                    </div>

                    <div class="artechia-form-row">
                        <div class="artechia-form-field">
                            <label for="guest-doc-type"><?php esc_html_e( 'Tipo documento', 'artechia-pms' ); ?></label>
                            <select id="guest-doc-type" name="guest[document_type]">
                                <option value="DNI" selected>DNI</option>
                                <option value="passport"><?php esc_html_e( 'Pasaporte', 'artechia-pms' ); ?></option>
                                <option value="CUIT">CUIT</option>
                            </select>
                        </div>
                        <div class="artechia-form-field">
                            <label for="guest-doc-number"><?php esc_html_e( 'Nro. documento', 'artechia-pms' ); ?></label>
                            <input type="text" id="guest-doc-number" name="guest[document_number]" maxlength="8" pattern="\d*" title="<?php esc_attr_e( 'Solo números, máx 8 caracteres', 'artechia-pms' ); ?>" inputmode="numeric">
                        </div>
                    </div>

                    <div class="artechia-form-row">
                        <div class="artechia-form-field artechia-form-field--full">
                            <label for="guest-requests"><?php esc_html_e( 'Solicitudes especiales', 'artechia-pms' ); ?></label>
                            <textarea id="guest-requests" name="guest[special_requests]" rows="3"
                                      placeholder="<?php esc_attr_e( 'Llegamos tarde, cama extra...', 'artechia-pms' ); ?>"></textarea>
                        </div>
                    </div>
                </div>

                <div class="artechia-checkout__section artechia-checkout__extras" id="artechia-checkout-extras" style="display:none;">
                    <h3><?php esc_html_e( 'Extras opcionales', 'artechia-pms' ); ?></h3>
                    <div id="artechia-extras-list" class="artechia-extras-list"></div>
                </div>

                <div class="artechia-checkout__section artechia-checkout__payment">
                    <h3><?php esc_html_e( 'Forma de pago', 'artechia-pms' ); ?></h3>

                    <?php if ( \Artechia\PMS\Services\Settings::get( 'mercadopago_enabled' ) === '1' ): ?>
                    <label class="artechia-radio">
                        <input type="radio" name="payment_method" value="mercadopago" checked>
                        <span>
                            <strong><?php esc_html_e( 'Mercado Pago', 'artechia-pms' ); ?></strong>
                            <br><small><?php esc_html_e( 'Tarjetas de crédito, débito y dinero en cuenta.', 'artechia-pms' ); ?></small>
                        </span>
                    </label>
                    <?php endif; ?>

                    <?php if ( \Artechia\PMS\Services\Settings::get( 'enable_bank_transfer' ) === '1' ): ?>
                    <label class="artechia-radio">
                        <input type="radio" name="payment_method" value="bank_transfer" <?php echo \Artechia\PMS\Services\Settings::get( 'mercadopago_enabled' ) !== '1' ? 'checked' : ''; ?>>
                        <span>
                            <strong><?php esc_html_e( 'Transferencia Bancaria', 'artechia-pms' ); ?></strong>
                            <br><small><?php esc_html_e( 'Te enviaremos los datos para transferir.', 'artechia-pms' ); ?></small>
                        </span>
                    </label>
                    <?php endif; ?>
                </div>

                <?php if ( ! empty( \Artechia\PMS\Services\Settings::get( 'checkout_terms_conditions' ) ) ) : ?>
                <div class="artechia-checkout__section artechia-checkout__terms-acceptance">
                    <h3><?php esc_html_e( 'Términos y condiciones', 'artechia-pms' ); ?></h3>

                    <label class="artechia-checkbox">
                        <input type="checkbox" id="artechia-accept-terms" name="accept_terms" required>
                        <span><?php printf( 
                            esc_html__( 'He leído y acepto los %s de la reserva.', 'artechia-pms' ), 
                            '<a href="#" id="artechia-open-terms" style="text-decoration: underline;">' . esc_html__( 'términos y condiciones', 'artechia-pms' ) . '</a>'
                        ); ?> *</span>
                    </label>
                </div>
                <?php endif; ?>

                <button type="submit" class="artechia-btn artechia-btn--primary artechia-btn--large" id="artechia-checkout-submit">
                    <?php esc_html_e( 'Confirmar reserva', 'artechia-pms' ); ?>
                </button>
            </form>
        </div>

        <div class="artechia-checkout__right">
            <div class="artechia-checkout__summary" id="artechia-checkout-summary">
                <h3><?php esc_html_e( 'Resumen de tu reserva', 'artechia-pms' ); ?></h3>
                <div class="artechia-summary__room" id="artechia-summary-room"></div>
                <div class="artechia-summary__dates" id="artechia-summary-dates"></div>
                <div class="artechia-summary__guests" id="artechia-summary-guests"></div>
                <div class="artechia-summary__policy" id="artechia-summary-policy"></div>
                <hr>
                <div class="artechia-summary__line" id="artechia-summary-subtotal"></div>
                <div class="artechia-summary__line" id="artechia-summary-extras"></div>
                <div class="artechia-summary__line" id="artechia-summary-taxes"></div>
                <div class="artechia-summary__line artechia-summary__line--discount" id="artechia-summary-discount" style="display:none;"></div>


                <div class="artechia-summary__total" id="artechia-summary-total"></div>
                
                <hr>
                <div class="artechia-checkout__coupon">
                    <div class="artechia-coupon-input-group">
                        <input type="text" id="artechia-coupon-code" placeholder="<?php esc_attr_e( 'Código de cupón', 'artechia-pms' ); ?>">
                        <button type="button" class="artechia-btn artechia-btn--secondary" id="artechia-apply-coupon">
                            <?php esc_html_e( 'Aplicar', 'artechia-pms' ); ?>
                        </button>
                    </div>
                    <div id="artechia-coupon-status" class="artechia-coupon-status" style="display:none;"></div>
                </div>
            </div>

            <div class="artechia-checkout__timer" id="artechia-checkout-timer">
                <svg class="artechia-icon" width="16" height="16"><use href="#icon-clock"/></svg>
                <span id="artechia-timer-text"></span>
            </div>
        </div>
    </div>

    <div class="artechia-checkout__error" id="artechia-checkout-error" style="display:none;"></div>

    <!-- Terms & Conditions Modal -->
    <div id="artechia-terms-modal" class="artechia-modal-overlay" style="display:none;">
        <div class="artechia-modal-content">
            <div class="artechia-modal-header">
                <h3><?php esc_html_e( 'Términos y Condiciones', 'artechia-pms' ); ?></h3>
            </div>
            <div class="artechia-modal-body">
                <div class="artechia-terms-full-text" id="artechia-terms-content">
                    <!-- Populated by JS -->
                </div>
            </div>
            <div class="artechia-modal-footer">
                <button type="button" class="artechia-btn artechia-btn--primary" id="artechia-close-terms-btn"><?php esc_html_e( 'Cerrar', 'artechia-pms' ); ?></button>
            </div>
        </div>
    </div>
</div>
