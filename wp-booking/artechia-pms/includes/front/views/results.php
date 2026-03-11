<?php
/**
 * Results shortcode view.
 * Rendered by [artechia_results]. Data loaded via JS/REST API.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
?>

<div class="artechia-results" id="artechia-results">
    <?php $artechia_step = 2; include __DIR__ . '/partials/stepper.php'; ?>
    <div class="artechia-results__search-bar" id="artechia-results-search-bar" style="display:none;">
        <div class="artechia-results__search-details">
            <div class="artechia-results__search-item">
                <svg class="artechia-icon" width="16" height="16"><use href="#icon-calendar"/></svg>
                <div>
                    <span class="artechia-results__search-label"><?php esc_html_e( 'Check-in', 'artechia-pms' ); ?></span>
                    <span class="artechia-results__search-value" id="artechia-search-checkin"></span>
                </div>
            </div>
            <div class="artechia-results__search-item">
                <svg class="artechia-icon" width="16" height="16"><use href="#icon-calendar"/></svg>
                <div>
                    <span class="artechia-results__search-label"><?php esc_html_e( 'Check-out', 'artechia-pms' ); ?></span>
                    <span class="artechia-results__search-value" id="artechia-search-checkout"></span>
                </div>
            </div>
            <div class="artechia-results__search-item">
                <svg class="artechia-icon" width="16" height="16"><use href="#icon-moon"/></svg>
                <div>
                    <span class="artechia-results__search-label"><?php esc_html_e( 'Noches', 'artechia-pms' ); ?></span>
                    <span class="artechia-results__search-value" id="artechia-search-nights"></span>
                </div>
            </div>
            <div class="artechia-results__search-item">
                <svg class="artechia-icon" width="16" height="16"><use href="#icon-users"/></svg>
                <div>
                    <span class="artechia-results__search-label"><?php esc_html_e( 'Huéspedes', 'artechia-pms' ); ?></span>
                    <span class="artechia-results__search-value" id="artechia-search-guests"></span>
                </div>
            </div>
        </div>
        <button type="button" class="artechia-results__change-btn" onclick="window.history.back()">
            <?php esc_html_e( 'Cambiar Fechas', 'artechia-pms' ); ?>
        </button>
    </div>

    <h2 class="artechia-results__title"><?php esc_html_e( 'Habitaciones disponibles', 'artechia-pms' ); ?></h2>

    <div class="artechia-results__loading" id="artechia-results-loading">
        <div class="artechia-spinner"></div>
        <p><?php esc_html_e( 'Buscando disponibilidad...', 'artechia-pms' ); ?></p>
    </div>

    <div class="artechia-results__empty" id="artechia-results-empty" style="display:none;">
        <div class="artechia-results__empty-icon">
            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                <line x1="16" y1="2" x2="16" y2="6"></line>
                <line x1="8" y1="2" x2="8" y2="6"></line>
                <line x1="3" y1="10" x2="21" y2="10"></line>
                <line x1="9" y1="16" x2="15" y2="16" stroke="#ef4444" stroke-width="2"></line>
            </svg>
        </div>
        <p class="artechia-results__empty-title"><?php esc_html_e( 'Sin disponibilidad para esas fechas', 'artechia-pms' ); ?></p>
        <p class="artechia-results__empty-text"><?php esc_html_e( 'No encontramos habitaciones disponibles. Probá con otras fechas.', 'artechia-pms' ); ?></p>
        <div class="artechia-results__alternatives" id="artechia-results-alternatives" style="display:none;">
            <h3 class="artechia-results__alternatives-title"><?php esc_html_e( 'Fechas alternativas disponibles', 'artechia-pms' ); ?></h3>
            <div class="artechia-results__alternatives-grid" id="artechia-alternatives-grid"></div>
        </div>
    </div>

    <div class="artechia-results__grid" id="artechia-results-grid">
        <!-- Room cards injected by JS -->
    </div>

    <div class="artechia-results__error" id="artechia-results-error" style="display:none;"></div>
</div>

<!-- Template for a room card (cloned by JS) -->
<template id="artechia-room-card-template">
    <div class="artechia-room-card">
        <div class="artechia-room-card__image">
            <img src="" alt="" loading="lazy">
        </div>
        <div class="artechia-room-card__body">
            <div class="artechia-room-card__info">
                <div class="artechia-room-card__badges">
                    <div class="artechia-room-card__promo-badge" data-field="promo-badge" style="display:none;"></div>
                    <div class="artechia-room-card__scarcity" data-field="scarcity" style="display:none;"></div>
                </div>
                <h3 class="artechia-room-card__name"></h3>
                <p class="artechia-room-card__desc"></p>
                
                <div class="artechia-room-card__features">
                    <div class="artechia-room-card__feature">
                        <svg class="artechia-icon" width="16" height="16"><use href="#icon-users"/></svg>
                        <span data-field="capacity"></span>
                    </div>
                    <div class="artechia-room-card__feature" data-field="beds-container">
                        <svg class="artechia-icon" width="16" height="16"><use href="#icon-bed"/></svg>
                        <span data-field="beds"></span>
                    </div>
                </div>

                <div class="artechia-room-card__amenities" data-field="amenities"></div>
            </div>

            <div class="artechia-room-card__cta">
                <div class="artechia-room-card__pricing">
                    <div class="artechia-room-card__price-container">
                        <span class="artechia-room-card__price-original" data-field="price-original" style="display:none;"></span>
                        <div class="artechia-room-card__price-now">
                            <span class="artechia-room-card__price-now-label" data-field="price-now-label" style="display:none;"><?php esc_html_e( 'Ahora:', 'artechia-pms' ); ?></span>
                            <span class="artechia-room-card__price" data-field="total"></span>
                        </div>
                    </div>
                    <span class="artechia-room-card__nights" data-field="nights-label"></span>
                    <span class="artechia-room-card__per-night" data-field="per-night"></span>
                </div>
                <button class="artechia-btn artechia-btn--primary artechia-btn--large artechia-room-card__book"
                        data-action="book"
                        data-room-type-id=""
                        data-rate-plan-id="">
                    <?php esc_html_e( 'Reservar', 'artechia-pms' ); ?>
                </button>
            </div>
        </div>
    </div>
</template>
