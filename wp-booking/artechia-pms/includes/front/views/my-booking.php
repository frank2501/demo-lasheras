<?php
/**
 * "Mi Reserva" shortcode view.
 * Rendered by [artechia_my_booking]. Data loaded via JS from URL params.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
?>

<div class="artechia-my-booking" id="artechia-my-booking">
    <div class="artechia-my-booking__loading" id="artechia-mybooking-loading">
        <div class="artechia-spinner"></div>
        <p><?php esc_html_e( 'Cargando tu reserva...', 'artechia-pms' ); ?></p>
    </div>

    <div class="artechia-my-booking__not-found" id="artechia-mybooking-notfound" style="display:none;">
        <h2><?php esc_html_e( 'Reserva no encontrada', 'artechia-pms' ); ?></h2>
        <p><?php esc_html_e( 'No pudimos encontrar la reserva. Verificá el enlace que recibiste por email.', 'artechia-pms' ); ?></p>
    </div>

    <div class="artechia-my-booking__content" id="artechia-mybooking-content" style="display:none;">
        <h2 class="artechia-my-booking__title">
            <?php esc_html_e( 'Mi Reserva', 'artechia-pms' ); ?>
            <span class="artechia-badge" id="artechia-mybooking-status"></span>
        </h2>

        <!-- Post-payment confirmation alert -->
        <div id="artechia-mybooking-confirmation-alert" class="artechia-alert" style="display:none; margin-bottom: 20px;">
            <div class="artechia-alert__icon">
                <svg class="artechia-icon" width="20" height="20"><use href="#icon-check"/></svg>
            </div>
            <div class="artechia-alert__content">
                <strong id="artechia-confirm-alert-title"></strong>
                <p id="artechia-confirm-alert-msg"></p>
            </div>
            <div id="artechia-confirm-alert-loader" class="artechia-spinner artechia-spinner--small" style="display:none;"></div>
        </div>

        <div class="artechia-my-booking__grid">
            <div class="artechia-my-booking__main">
                <div class="artechia-detail-card" id="artechia-mybooking-bank-details" style="display: none;">
                    <h3><?php esc_html_e( 'Datos de transferencia', 'artechia-pms' ); ?></h3>
                    <div class="artechia-bank-info-grid">
                        <div class="bank-row">
                            <strong id="artechia-mybooking-bank-amount-label"><?php esc_html_e( 'Monto a transferir:', 'artechia-pms' ); ?></strong> <span class="artechia-price-highlight" id="artechia-mybooking-bank-amount">—</span>
                        </div>
                        <div class="bank-row">
                            <strong><?php esc_html_e( 'Banco:', 'artechia-pms' ); ?></strong> <span id="artechia-mybooking-bank-name">—</span>
                        </div>
                        <div class="bank-row">
                            <strong><?php esc_html_e( 'Titular:', 'artechia-pms' ); ?></strong> <span id="artechia-mybooking-bank-holder">—</span>
                        </div>
                        <div class="bank-row">
                            <strong><?php esc_html_e( 'CBU/CVU:', 'artechia-pms' ); ?></strong> <span id="artechia-mybooking-bank-cbu">—</span>
                        </div>
                        <div class="bank-row">
                            <strong><?php esc_html_e( 'Alias:', 'artechia-pms' ); ?></strong> <span id="artechia-mybooking-bank-alias">—</span>
                        </div>
                        <div class="bank-row">
                            <strong><?php esc_html_e( 'CUIT/CUIL:', 'artechia-pms' ); ?></strong> <span id="artechia-mybooking-bank-cuit">—</span>
                        </div>
                    </div>
                    <p class="artechia-bank-note" id="artechia-mybooking-bank-note" style="margin-top: 15px; font-size: 0.9em; opacity: 0.8;">
                        <?php esc_html_e( 'Una vez realizada la transferencia, por favor envíe el comprobante respondiendo al mail de confirmación.', 'artechia-pms' ); ?>
                    </p>
                </div>

                <div class="artechia-detail-card" id="artechia-mybooking-bank-whatsapp" style="display:none; text-align: center;">
                    <svg width="40" height="40" viewBox="0 0 24 24" fill="#25D366" style="margin-bottom: 15px;"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                    <h3 style="margin-top: 0; margin-bottom: 10px;"><?php esc_html_e( 'Continuar por WhatsApp', 'artechia-pms' ); ?></h3>
                    <p style="margin-bottom: 15px; color: #555;"><?php esc_html_e( 'Contactanos por WhatsApp para coordinar el pago y confirmar tu reserva.', 'artechia-pms' ); ?></p>
                    <div style="font-size: 1.1em; margin-bottom: 20px;">
                        <strong><?php esc_html_e('Monto a transferir:', 'artechia-pms'); ?></strong> 
                        <span class="artechia-price-highlight" id="artechia-mybooking-whatsapp-amount">—</span>
                    </div>
                    <a href="#" target="_blank" id="artechia-mybooking-whatsapp-btn" class="artechia-btn" style="background-color: #25D366; color: white; border: none; display: inline-flex; align-items: center; gap: 8px; text-decoration: none; font-size: 16px; padding: 12px 24px; font-weight: 600;">
                        <?php esc_html_e( 'Contactar por WhatsApp', 'artechia-pms' ); ?>
                    </a>
                </div>

                <div class="artechia-detail-card">
                    <h3><?php esc_html_e( 'Detalles de la reserva', 'artechia-pms' ); ?></h3>
                    <div class="artechia-detail-grid">
                        <div class="artechia-detail-grid__item">
                            <svg class="artechia-detail-grid__icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 10h18"/></svg>
                            <div>
                                <span class="artechia-detail-grid__label"><?php esc_html_e( 'Código', 'artechia-pms' ); ?></span>
                                <span class="artechia-detail-grid__value artechia-detail-grid__value--code" id="artechia-mybooking-code"></span>
                            </div>
                        </div>
                        <div class="artechia-detail-grid__item" id="artechia-mybooking-property-row" style="display:none;">
                            <svg class="artechia-detail-grid__icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                            <div>
                                <span class="artechia-detail-grid__label"><?php esc_html_e( 'Propiedad', 'artechia-pms' ); ?></span>
                                <span class="artechia-detail-grid__value" id="artechia-mybooking-property"></span>
                            </div>
                        </div>
                        <div class="artechia-detail-grid__item">
                            <svg class="artechia-detail-grid__icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 3v4M9 3v4M4 11h16M5 7h14a2 2 0 012 2v10a2 2 0 01-2 2H5a2 2 0 01-2-2V9a2 2 0 012-2z"/></svg>
                            <div>
                                <span class="artechia-detail-grid__label"><?php esc_html_e( 'Check-in', 'artechia-pms' ); ?></span>
                                <span class="artechia-detail-grid__value" id="artechia-mybooking-checkin"></span>
                            </div>
                        </div>
                        <div class="artechia-detail-grid__item">
                            <svg class="artechia-detail-grid__icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 3v4M9 3v4M4 11h16M5 7h14a2 2 0 012 2v10a2 2 0 01-2 2H5a2 2 0 01-2-2V9a2 2 0 012-2z"/></svg>
                            <div>
                                <span class="artechia-detail-grid__label"><?php esc_html_e( 'Check-out', 'artechia-pms' ); ?></span>
                                <span class="artechia-detail-grid__value" id="artechia-mybooking-checkout"></span>
                            </div>
                        </div>
                        <div class="artechia-detail-grid__item">
                            <svg class="artechia-detail-grid__icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                            <div>
                                <span class="artechia-detail-grid__label"><?php esc_html_e( 'Estadía', 'artechia-pms' ); ?></span>
                                <span class="artechia-detail-grid__value" id="artechia-mybooking-nights"></span>
                            </div>
                        </div>
                        <div class="artechia-detail-grid__item">
                            <svg class="artechia-detail-grid__icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg>
                            <div>
                                <span class="artechia-detail-grid__label"><?php esc_html_e( 'Huéspedes', 'artechia-pms' ); ?></span>
                                <span class="artechia-detail-grid__value" id="artechia-mybooking-guests"></span>
                            </div>
                        </div>
                        <div class="artechia-detail-grid__item">
                            <svg class="artechia-detail-grid__icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
                            <div>
                                <span class="artechia-detail-grid__label"><?php esc_html_e( 'Método de pago', 'artechia-pms' ); ?></span>
                                <span class="artechia-detail-grid__value" id="artechia-mybooking-payment-method">—</span>
                            </div>
                        </div>
                        <div class="artechia-detail-grid__item">
                            <svg class="artechia-detail-grid__icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 7v11a2 2 0 002 2h14a2 2 0 002-2V7M3 7l9-4 9 4"/><path d="M9 22V12h6v10"/></svg>
                            <div>
                                <span class="artechia-detail-grid__label"><?php esc_html_e( 'Habitación', 'artechia-pms' ); ?></span>
                                <span class="artechia-detail-grid__value" id="artechia-mybooking-rooms"></span>
                            </div>
                        </div>
                        <div class="artechia-detail-grid__item artechia-detail-grid__item--full">
                            <svg class="artechia-detail-grid__icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                            <div>
                                <span class="artechia-detail-grid__label"><?php esc_html_e( 'Política de cancelación', 'artechia-pms' ); ?></span>
                                <span class="artechia-detail-grid__value" id="artechia-mybooking-policy"></span>
                            </div>
                        </div>

                        <!-- Guest info -->
                        <div class="artechia-detail-grid__item artechia-detail-grid__item--full artechia-detail-grid__separator"></div>
                        <div class="artechia-detail-grid__item">
                            <svg class="artechia-detail-grid__icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                            <div>
                                <span class="artechia-detail-grid__label"><?php esc_html_e( 'Huésped', 'artechia-pms' ); ?></span>
                                <span class="artechia-detail-grid__value" id="artechia-mybooking-guestname"></span>
                            </div>
                        </div>
                        <div class="artechia-detail-grid__item">
                            <svg class="artechia-detail-grid__icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                            <div>
                                <span class="artechia-detail-grid__label"><?php esc_html_e( 'Email', 'artechia-pms' ); ?></span>
                                <span class="artechia-detail-grid__value" id="artechia-mybooking-guestemail"></span>
                            </div>
                        </div>
                        <div class="artechia-detail-grid__item">
                            <svg class="artechia-detail-grid__icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6A19.79 19.79 0 012.12 4.18 2 2 0 014.11 2h3a2 2 0 012 1.72c.127.96.361 1.903.7 2.81a2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0122 16.92z"/></svg>
                            <div>
                                <span class="artechia-detail-grid__label"><?php esc_html_e( 'Teléfono', 'artechia-pms' ); ?></span>
                                <span class="artechia-detail-grid__value" id="artechia-mybooking-guestphone"></span>
                            </div>
                        </div>
                        <div class="artechia-detail-grid__item" id="artechia-mybooking-whatsapp-container" style="display:none;">
                            <svg class="artechia-detail-grid__icon" width="18" height="18" viewBox="0 0 24 24" fill="#25D366"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                            <div>
                                <a href="#" id="artechia-mybooking-whatsapp-link" target="_blank" style="color: #25D366; text-decoration: none; font-weight: 600; font-size: 14px;"><?php esc_html_e( 'Contactar por WhatsApp', 'artechia-pms' ); ?></a>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="artechia-detail-card" id="artechia-mybooking-extras-card" style="display:none;">
                    <h3><?php esc_html_e( 'Extras', 'artechia-pms' ); ?></h3>
                    <div id="artechia-mybooking-extras"></div>
                </div>

                <div class="artechia-detail-card" id="artechia-mybooking-requests-card" style="display:none;">
                    <h3><?php esc_html_e( 'Solicitudes especiales', 'artechia-pms' ); ?></h3>
                    <p id="artechia-mybooking-requests"></p>
                </div>
            </div>

            <div class="artechia-my-booking__sidebar">
                <div class="artechia-detail-card artechia-detail-card--highlight">
                    <h3><?php esc_html_e( 'Resumen de pago', 'artechia-pms' ); ?></h3>
                    <dl class="artechia-dl">
                        <dt>
                            <?php esc_html_e( 'Subtotal', 'artechia-pms' ); ?>
                            <small id="artechia-mybooking-subtotal-nights"></small>
                        </dt>
                        <dd id="artechia-mybooking-subtotal"></dd>

                        <dt><?php esc_html_e( 'Extras', 'artechia-pms' ); ?></dt>
                        <dd id="artechia-mybooking-extras-total"></dd>

                        <dt><?php esc_html_e( 'Impuestos', 'artechia-pms' ); ?></dt>
                        <dd id="artechia-mybooking-taxes-total"></dd>

                        <dt id="artechia-mybooking-discount-label" style="display:none;"><?php esc_html_e( 'Descuento', 'artechia-pms' ); ?></dt>
                        <dd id="artechia-mybooking-discount-total" style="display:none;"></dd>
                    </dl>
                    <hr>
                    <dl class="artechia-dl artechia-dl--total">
                        <dt><?php esc_html_e( 'Total', 'artechia-pms' ); ?></dt>
                        <dd id="artechia-mybooking-total"></dd>

                        <dt id="artechia-mybooking-paid-label"><?php esc_html_e( 'Pagado', 'artechia-pms' ); ?></dt>
                        <dd id="artechia-mybooking-paid"></dd>

                        <dt id="artechia-mybooking-balance-label"><?php esc_html_e( 'Saldo', 'artechia-pms' ); ?></dt>
                        <dd id="artechia-mybooking-balance"></dd>
                    </dl>

                    <!-- H4: Payment actions (shown by JS when pending + MP enabled) -->
                    <div id="artechia-mybooking-payment-actions" style="display:none;">
                        <hr>
                        <h4><?php esc_html_e( 'Pagar reserva', 'artechia-pms' ); ?></h4>
                        <p class="artechia-pay-help" id="artechia-mybooking-deposit-label"></p>
                        <div class="artechia-pay-buttons">
                            <button type="button" class="artechia-btn artechia-btn--secondary" id="artechia-pay-deposit">
                                <?php esc_html_e( 'Pagar seña', 'artechia-pms' ); ?>
                                <span id="artechia-pay-deposit-amount"></span>
                            </button>
                            <button type="button" class="artechia-btn artechia-btn--primary" id="artechia-pay-total">
                                <?php esc_html_e( 'Pagar total', 'artechia-pms' ); ?>
                                <span id="artechia-pay-total-amount"></span>
                            </button>
                        </div>
                        <p class="artechia-error" id="artechia-mybooking-pay-error" style="display:none;"></p>
                    </div>
                </div>


            </div>
        </div>
    </div>
</div>
