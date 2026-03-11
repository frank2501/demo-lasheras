<?php
/**
 * Admin View: Unified Coupons and Promotions management.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
?>

<div class="wrap artechia-admin-page" id="artechia-coupons-app">
    <!-- Header -->
    <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:20px;">
        <div>
            <h1 class="wp-heading-inline" style="display:flex; align-items:center; gap:10px; margin:0;">
                <span style="width:38px; height:38px; border-radius:10px; background:linear-gradient(135deg,#6366f1,#8b5cf6); display:flex; align-items:center; justify-content:center; font-size:18px; color:#fff;">🏷️</span>
                <?php esc_html_e( 'Promociones y Cupones', 'artechia-pms' ); ?>
            </h1>
            <p style="color:#64748b; font-size:13px; margin:6px 0 0;"><?php esc_html_e( 'Gestiona descuentos automáticos, cupones con código y envía campañas de marketing.', 'artechia-pms' ); ?></p>
        </div>
        <div style="display:flex; gap:8px; align-items:center; flex-shrink:0;">
            <button type="button" class="button" id="open-marketing-btn" style="display:inline-flex; align-items:center; gap:6px; height:36px; border-radius:6px;">
                <span class="dashicons dashicons-email-alt" style="font-size:16px; width:16px; height:16px;"></span> <?php esc_html_e( 'Email Marketing', 'artechia-pms' ); ?>
            </button>
            <button type="button" class="button button-primary" id="add-coupon-btn" style="display:inline-flex; align-items:center; gap:6px; height:36px; border-radius:6px;">
                <span class="dashicons dashicons-plus" style="font-size:16px; width:16px; height:16px;"></span> <?php esc_html_e( 'Nuevo Descuento', 'artechia-pms' ); ?>
            </button>
        </div>
    </div>
    <hr class="wp-header-end">

    <!-- Summary Cards -->
    <div id="promo-stats" style="display:grid; grid-template-columns:repeat(4, 1fr); gap:12px; margin-bottom:16px;">
        <div style="background:#fff; border:1px solid #e5e7eb; border-radius:8px; padding:14px 16px; display:flex; align-items:center; gap:12px;">
            <div style="width:40px; height:40px; border-radius:10px; background:#eff6ff; display:flex; align-items:center; justify-content:center; font-size:20px;">📋</div>
            <div>
                <div id="pstat-total" style="font-size:22px; font-weight:700; color:#1e293b;">—</div>
                <div style="font-size:11px; color:#94a3b8; text-transform:uppercase; letter-spacing:0.5px;">Total Descuentos</div>
            </div>
        </div>
        <div style="background:#fff; border:1px solid #e5e7eb; border-radius:8px; padding:14px 16px; display:flex; align-items:center; gap:12px;">
            <div style="width:40px; height:40px; border-radius:10px; background:#f0fdf4; display:flex; align-items:center; justify-content:center; font-size:20px;">📈</div>
            <div>
                <div id="pstat-usage" style="font-size:22px; font-weight:700; color:#16a34a;">—</div>
                <div style="font-size:11px; color:#94a3b8; text-transform:uppercase; letter-spacing:0.5px;">Usos Totales</div>
            </div>
        </div>
        <div style="background:#fff; border:1px solid #e5e7eb; border-radius:8px; padding:14px 16px; display:flex; align-items:center; gap:12px;">
            <div style="width:40px; height:40px; border-radius:10px; background:#fdf4ff; display:flex; align-items:center; justify-content:center; font-size:20px;">🎟️</div>
            <div>
                <div id="pstat-coupons" style="font-size:22px; font-weight:700; color:#a855f7;">—</div>
                <div style="font-size:11px; color:#94a3b8; text-transform:uppercase; letter-spacing:0.5px;">Cupones</div>
            </div>
        </div>
        <div style="background:#fff; border:1px solid #e5e7eb; border-radius:8px; padding:14px 16px; display:flex; align-items:center; gap:12px;">
            <div style="width:40px; height:40px; border-radius:10px; background:#fef9c3; display:flex; align-items:center; justify-content:center; font-size:20px;">⚡</div>
            <div>
                <div id="pstat-promos" style="font-size:22px; font-weight:700; color:#d97706;">—</div>
                <div style="font-size:11px; color:#94a3b8; text-transform:uppercase; letter-spacing:0.5px;">Promociones</div>
            </div>
        </div>
    </div>

    <!-- Table -->
    <div style="background:#fff; border:1px solid #e5e7eb; border-radius:8px; overflow:hidden;">
        <table class="wp-list-table widefat fixed striped" style="border:none; border-radius:0;">
            <thead>
                <tr style="background:#f8fafc;">
                    <th style="font-weight:600; color:#475569; padding:12px 16px;"><?php esc_html_e( 'Nombre / Código', 'artechia-pms' ); ?></th>
                    <th style="font-weight:600; color:#475569; padding:12px 16px; width:140px;"><?php esc_html_e( 'Tipo', 'artechia-pms' ); ?></th>
                    <th style="font-weight:600; color:#475569; padding:12px 16px; width:160px;"><?php esc_html_e( 'Descuento', 'artechia-pms' ); ?></th>
                    <th style="font-weight:600; color:#475569; padding:12px 16px; width:160px;"><?php esc_html_e( 'Validez', 'artechia-pms' ); ?></th>
                    <th style="font-weight:600; color:#475569; padding:12px 16px; width:80px; text-align:center;"><?php esc_html_e( 'Estado', 'artechia-pms' ); ?></th>
                    <th style="font-weight:600; color:#475569; padding:12px 16px; width:140px;"><?php esc_html_e( 'Acciones', 'artechia-pms' ); ?></th>
                </tr>
            </thead>
            <tbody id="discounts-list">
                <tr><td colspan="6" style="text-align:center; padding:40px 20px; color:#94a3b8;"><span style="font-size:24px;">⏳</span><br><?php esc_html_e( 'Cargando descuentos...', 'artechia-pms' ); ?></td></tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: New/Edit Discount -->
<div id="coupon-modal" class="artechia-modal" style="display:none;">
    <div class="artechia-modal-content" style="width:580px;">
        <div class="artechia-modal-header">
            <div style="display:flex; align-items:center; gap:10px;">
                <span style="width:36px; height:36px; border-radius:10px; background:linear-gradient(135deg,#6366f1,#8b5cf6); display:flex; align-items:center; justify-content:center; font-size:16px; color:#fff;">🏷️</span>
                <h2 id="modal-title" style="margin:0; font-size:18px;"><?php esc_html_e( 'Nuevo Descuento', 'artechia-pms' ); ?></h2>
            </div>
            <button type="button" class="close-modal">&times;</button>
        </div>
        <form id="coupon-form" style="display:flex; flex-direction:column; flex-grow:1; min-height:0;">
            <input type="hidden" name="id" id="field-id">
            <input type="hidden" name="table_type" id="field-table-type" value="coupon">

            <div style="flex-grow:1; overflow-y:auto; padding-right:10px;">
                <!-- Mode selector -->
                <div class="artechia-field" id="mode-selector-wrap" style="margin-bottom:20px;">
                    <label style="font-size:12px; text-transform:uppercase; letter-spacing:0.5px; color:#94a3b8; margin-bottom:8px;"><?php esc_html_e( 'Modo de Aplicación', 'artechia-pms' ); ?></label>
                    <div style="display:flex; gap:10px; margin-top:4px;">
                        <label class="artechia-mode-card">
                            <input type="radio" name="app_mode" value="coupon" checked style="display:none;">
                            <div class="artechia-mode-inner">
                                <span style="font-size:20px;">🎟️</span>
                                <div>
                                    <div style="font-weight:600; font-size:13px;"><?php esc_html_e( 'Cupón con Código', 'artechia-pms' ); ?></div>
                                    <div style="font-size:11px; color:#94a3b8;">El cliente ingresa un código</div>
                                </div>
                            </div>
                        </label>
                        <label class="artechia-mode-card">
                            <input type="radio" name="app_mode" value="promo" style="display:none;">
                            <div class="artechia-mode-inner">
                                <span style="font-size:20px;">⚡</span>
                                <div>
                                    <div style="font-weight:600; font-size:13px;"><?php esc_html_e( 'Promoción Automática', 'artechia-pms' ); ?></div>
                                    <div style="font-size:11px; color:#94a3b8;">Se aplica sola si cumple reglas</div>
                                </div>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Section: Descuento -->
                <div style="font-size:12px; text-transform:uppercase; letter-spacing:0.5px; color:#94a3b8; margin-bottom:10px; display:flex; align-items:center; gap:6px;">
                    <span style="font-size:14px;">💰</span> Configuración del Descuento
                </div>
                <div class="artechia-form-grid">
                    <div class="artechia-field" id="wrap-code">
                        <label><?php esc_html_e( 'Código del Cupón', 'artechia-pms' ); ?> <span style="color:#dc2626;">*</span></label>
                        <input type="text" name="code" id="field-code" placeholder="EJ: VERANO2026" style="text-transform:uppercase;">
                    </div>
                    <div class="artechia-field" id="wrap-name" style="display:none;">
                        <label><?php esc_html_e( 'Nombre de la Promoción', 'artechia-pms' ); ?> <span style="color:#dc2626;">*</span></label>
                        <input type="text" name="name" id="field-name" placeholder="EJ: Descuento de Apertura">
                    </div>
                    
                    <div class="artechia-field">
                        <label><?php esc_html_e( 'Tipo de Descuento', 'artechia-pms' ); ?></label>
                        <select name="type" id="field-type">
                            <option value="percent"><?php esc_html_e( 'Porcentaje (%)', 'artechia-pms' ); ?></option>
                            <option value="fixed"><?php esc_html_e( 'Monto Fijo ($)', 'artechia-pms' ); ?></option>
                            <option value="stay_pay" class="only-promo" style="display:none;"><?php esc_html_e( 'StayPay (ej: 3/2)', 'artechia-pms' ); ?></option>
                        </select>
                    </div>

                    <div class="artechia-field" id="wrap-value">
                        <label id="label-value"><?php esc_html_e( 'Valor', 'artechia-pms' ); ?></label>
                        <input type="text" name="value" id="field-value" required>
                    </div>

                    <div class="artechia-field" id="wrap-stay-pay" style="display:none; grid-column:span 2;">
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                            <div>
                                <label><?php esc_html_e( 'Paga: X noches', 'artechia-pms' ); ?></label>
                                <input type="number" id="stay-pay-pay" min="1">
                            </div>
                            <div>
                                <label><?php esc_html_e( 'Y quédate: Y noches', 'artechia-pms' ); ?></label>
                                <input type="number" id="stay-pay-stay" min="1">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section: Restricciones -->
                <div style="font-size:12px; text-transform:uppercase; letter-spacing:0.5px; color:#94a3b8; margin:16px 0 10px; display:flex; align-items:center; gap:6px;">
                    <span style="font-size:14px;">📅</span> Restricciones
                </div>
                <div class="artechia-form-grid">
                    <div class="artechia-field" id="wrap-min-nights">
                        <label><?php esc_html_e( 'Mínimo Noches', 'artechia-pms' ); ?></label>
                        <input type="number" name="min_nights" id="field-min-nights" placeholder="0">
                    </div>
                    <div></div>
                    <div class="artechia-field">
                        <label><?php esc_html_e( 'Válido Desde', 'artechia-pms' ); ?></label>
                        <input type="date" name="starts_at" id="field-starts-at">
                    </div>
                    <div class="artechia-field">
                        <label><?php esc_html_e( 'Válido Hasta', 'artechia-pms' ); ?></label>
                        <input type="date" name="ends_at" id="field-ends-at">
                    </div>
                </div>
            </div>

            <div class="artechia-modal-footer">
                <button type="button" class="button close-modal" style="border-radius:6px; height:36px; padding:0 16px; line-height:36px;">Cancelar</button>
                <button type="submit" class="button button-primary" style="border-radius:6px; height:36px; padding:0 20px; font-weight:600;">💾 <?php esc_html_e( 'Guardar', 'artechia-pms' ); ?></button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Email Marketing -->
<div id="marketing-modal" class="artechia-modal" style="display:none;">
    <div class="artechia-modal-content" style="width:1100px; max-width:95vw; height:95vh; max-height:95vh; display:flex; flex-direction:column; padding:24px; overflow:hidden;">
        <div class="artechia-modal-header" style="flex-shrink:0;">
            <div style="display:flex; align-items:center; gap:10px;">
                <span style="width:36px; height:36px; border-radius:10px; background:linear-gradient(135deg,#2563eb,#3b82f6); display:flex; align-items:center; justify-content:center; font-size:16px; color:#fff;">📧</span>
                <h2><?php esc_html_e( 'Email Marketing', 'artechia-pms' ); ?></h2>
            </div>
            <div style="display:flex; align-items:center; gap:12px;">
                <div id="m-tabs" style="display:flex; gap:4px; background:#f1f5f9; border-radius:8px; padding:3px;">
                    <button type="button" class="m-tab active" data-tab="campaign" style="padding:6px 16px; border:none; border-radius:6px; font-size:13px; font-weight:600; cursor:pointer; background:#fff; color:#1e293b; box-shadow:0 1px 2px rgba(0,0,0,0.05);">📨 Nueva Campaña</button>
                    <button type="button" class="m-tab" data-tab="history" style="padding:6px 16px; border:none; border-radius:6px; font-size:13px; font-weight:600; cursor:pointer; background:transparent; color:#64748b;">📋 Historial</button>
                </div>
                <button type="button" class="close-modal">&times;</button>
            </div>
        </div>
        
        <!-- Campaign History Panel (hidden by default) -->
        <div id="m-history-panel" style="display:none; flex-grow:1; overflow-y:auto; padding-bottom:20px;">
            <div id="m-history-list" style="display:flex; flex-direction:column; gap:10px; padding:10px 0;">
                <div style="text-align:center; padding:40px; color:#94a3b8;">
                    <span style="font-size:24px;">⏳</span><br>Cargando historial...
                </div>
            </div>
        </div>

        <div id="m-campaign-panel" class="artechia-grid" style="display: flex; gap: 20px; flex-grow: 1; min-height: 0; padding-bottom: 20px;">
            <!-- Left Column: Content Configuration -->
            <div style="flex-grow:1; overflow-y:auto; padding-right:15px; min-height:0; width:50%;">
                <form id="marketing-form" style="display:flex; flex-direction:column; gap:12px;">
                    <div style="font-size:12px; text-transform:uppercase; letter-spacing:0.5px; color:#94a3b8; margin-bottom:2px; display:flex; align-items:center; gap:6px;">
                        <span style="font-size:14px;">⚙️</span> Configuración
                    </div>
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; flex-shrink:0;">
                        <div class="artechia-field">
                            <label><?php esc_html_e( 'Plantilla Base', 'artechia-pms' ); ?></label>
                            <select name="template_id" id="m-field-template" required style="width: 100%;">
                                <option value=""><?php esc_html_e( '-- Selecciona --', 'artechia-pms' ); ?></option>
                            </select>
                        </div>
                        <div class="artechia-field" id="m-field-promo-wrap" style="display: none;">
                            <label><?php esc_html_e( 'Descuento Vinculado', 'artechia-pms' ); ?></label>
                            <select name="promo_code" id="m-field-promo" style="width: 100%;">
                                <option value=""><?php esc_html_e( '-- Personalizado (Sin descuento) --', 'artechia-pms' ); ?></option>
                            </select>
                            <p class="description" style="margin-top:4px; font-size:11px;"><?php esc_html_e( 'Reemplaza {promo_code}.', 'artechia-pms' ); ?></p>
                            <div id="m-no-discounts-alert" style="display:none; background:#fff3cd; border:1px solid #ffc107; border-radius:6px; padding:10px 14px; margin-top:8px; font-size:12px; color:#856404; line-height:1.5;">
                                <strong>⚠️ <?php esc_html_e( 'Esta plantilla requiere un Cupón o Promoción.', 'artechia-pms' ); ?></strong><br>
                                <?php esc_html_e( 'Creá al menos un cupón o promoción desde la pestaña correspondiente para poder usar esta plantilla.', 'artechia-pms' ); ?>
                            </div>
                        </div>
                    </div>

                    <div class="artechia-field" id="m-field-promo-desc-wrap" style="display: none;">
                        <label><?php esc_html_e( 'Descripción Promocional', 'artechia-pms' ); ?></label>
                        <input type="text" id="m-field-promo-desc" style="width: 100%;" placeholder="Ej: Disfruta de esta promo exclusiva...">
                        <p class="description" style="margin-top:4px; font-size:11px;"><?php esc_html_e( 'Modifica este texto para personalizar qué se mostrará como {promo_description}.', 'artechia-pms' ); ?></p>
                    </div>

                    <div id="m-content-header-wrap" style="display:flex; justify-content:space-between; align-items:center; margin-top:8px; flex-shrink:0;">
                        <div style="font-size:12px; text-transform:uppercase; letter-spacing:0.5px; color:#94a3b8; display:flex; align-items:center; gap:6px;">
                            <span style="font-size:14px;">✉️</span> Contenido del Email
                        </div>
                        <button type="button" id="m-toggle-html-btn" class="button button-small" style="display:inline-flex; align-items:center; gap:4px; border-radius:6px;">
                            <span class="dashicons dashicons-edit" style="font-size:14px; width:14px; height:14px;"></span> <?php esc_html_e( 'Editar Contenido', 'artechia-pms' ); ?>
                        </button>
                    </div>

                    <!-- Empty State Placeholder -->
                    <div id="m-empty-state" style="text-align: center; padding: 40px 20px; background: #f9f9f9; border: 1px dashed #ccd0d4; border-radius: 4px; margin-top: 15px;">
                        <span class="dashicons dashicons-email-alt" style="font-size: 48px; width: 48px; height: 48px; color: #cbd5e1; margin-bottom: 15px;"></span>
                        <h3 style="margin: 0 0 10px 0; color: #334155; font-size: 16px;"><?php esc_html_e( 'Diseña tu Campaña', 'artechia-pms' ); ?></h3>
                        <p style="color: #64748b; margin: 0; font-size: 13px;">
                            <?php esc_html_e( 'Selecciona una plantilla de la lista superior o crea una nueva para empezar a editar el contenido de tu email.', 'artechia-pms' ); ?>
                        </p>
                    </div>

                    <!-- Live Preview Box -->
                    <div id="m-preview-container" style="background: #f9f9f9; border: 1px solid #ddd; padding: 15px; border-radius: 4px; min-height: 250px;">
                        <div id="m-campaign-preview">
                            <p class="description"><?php esc_html_e( 'Selecciona una plantilla para ver la previsualización.', 'artechia-pms' ); ?></p>
                        </div>
                    </div>

                    <!-- Simple Editor for New Templates -->
                    <div id="m-simple-editor-wrapper" class="artechia-field" style="display: none; flex-direction: column; gap: 10px; margin-top: 15px;">
                        <div class="artechia-field" style="flex-shrink: 0;">
                            <label><?php esc_html_e( 'Asunto', 'artechia-pms' ); ?></label>
                            <input type="text" id="m-field-simple-subject" style="width: 100%; font-weight: bold;" placeholder="Ej: ¡Tenemos una oferta exclusiva para vos!">
                        </div>
                        <div class="artechia-field">
                            <label style="display:flex; justify-content:space-between; align-items:flex-end;">
                                <span><?php esc_html_e( 'Mensaje', 'artechia-pms' ); ?></span>
                            </label>
                            <textarea id="m-field-simple-body" style="width: 100%; min-height: 120px; font-family: sans-serif; font-size: 13px; padding: 10px;" placeholder="Escribí el texto de tu email acá..."></textarea>
                        </div>
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-top: 5px;">
                            <button type="button" id="m-switch-to-advanced-btn" class="button button-small" style="color: #666;">
                                <span class="dashicons dashicons-editor-code"></span> Editar Contenido HTML (Avanzado)
                            </button>
                            <button type="button" id="m-save-simple-btn" class="button button-primary button-small">
                                <span class="dashicons dashicons-saved"></span> Guardar Nueva Plantilla
                            </button>
                        </div>
                    </div>

                    <!-- Hidden HTML Editor -->
                    <div id="m-html-editor-wrapper" class="artechia-field" style="display: none; flex-direction: column; gap: 10px; margin-top: 15px;">
                        <div class="artechia-field" style="flex-shrink: 0;">
                            <label><?php esc_html_e( 'Asunto del Email', 'artechia-pms' ); ?></label>
                            <input type="text" id="m-field-subject" required style="width: 100%; font-weight: bold;">
                        </div>
                        <label style="display:flex; justify-content:space-between; align-items:flex-end; margin-bottom: 5px;">
                            <span><?php esc_html_e( 'Contenido HTML', 'artechia-pms' ); ?></span>
                            <span style="font-size: 11px; color:#666; font-weight:normal;">
                                Placeholders: 
                                <a href="#" class="m-placeholder" data-insert="{guest_name}">{guest_name}</a>
                                <a href="#" class="m-placeholder" data-insert="{promo_code}">{promo_code}</a>
                                <a href="#" class="m-placeholder" data-insert="{promo_description}">{promo_description}</a>
                                <a href="#" class="m-placeholder" data-insert="{booking_url}">{booking_url}</a>
                            </span>
                        </label>
                        <textarea id="m-field-body" required style="width: 100%; min-height: 350px; font-family: monospace; font-size: 13px; padding: 10px;"></textarea>
                        
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-top: 10px;">
                            <label style="display: inline-flex; align-items: center; gap: 6px; font-weight: normal; font-size: 12px; color: #d63638;">
                                <input type="checkbox" id="m-save-template-check"> <?php esc_html_e( 'Guardar cambios en la plantilla base de forma permanente', 'artechia-pms' ); ?>
                            </label>
                            <button type="button" id="m-save-html-btn" class="button button-primary button-small">
                                <span class="dashicons dashicons-saved"></span> Guardar y Volver
                            </button>
                        </div>
                    </div>

                    <style>
                        .m-placeholder { display: inline-block; background: #eee; padding: 2px 6px; border-radius: 3px; color: #2271b1; text-decoration: none; margin-left: 4px; border: 1px solid #ccc; transition: background 0.2s; }
                        .m-placeholder:hover { background: #e0e0e0; }
                    </style>

            </div>

            <!-- Right Column: Audience / Setup -->
            <div style="width:350px; flex-shrink:0; background:#f8fafc; padding:16px; border-radius:10px; border:1px solid #e5e7eb; display:flex; flex-direction:column; min-height:0;">
                <div style="margin-bottom:14px; flex-shrink:0;">
                    <h3 style="margin:0 0 10px; font-size:14px; color:#1e293b; display:flex; align-items:center; gap:6px;"><span style="font-size:16px;">👥</span> <?php esc_html_e( 'Audiencia', 'artechia-pms' ); ?></h3>
                    
                    <div class="artechia-field" style="margin-top:8px;">
                        <label style="font-size:12px;"><?php esc_html_e( 'Filtrar por Propiedad', 'artechia-pms' ); ?></label>
                        <select id="m-field-property" style="width:100%;">
                            <option value=""><?php esc_html_e( 'Todas las propiedades', 'artechia-pms' ); ?></option>
                        </select>
                    </div>
                </div>

                <div style="flex-grow:1; display:flex; flex-direction:column; background:#fff; border:1px solid #e5e7eb; border-radius:8px; overflow:hidden; min-height:0;">
                    <div style="padding:10px 12px; background:#f8fafc; border-bottom:1px solid #e5e7eb; display:flex; justify-content:space-between; align-items:center; flex-shrink:0;">
                        <label style="font-weight:600; display:flex; align-items:center; gap:6px; font-size:13px;">
                            <input type="checkbox" id="m-select-all" checked> <?php esc_html_e( 'Destinatarios', 'artechia-pms' ); ?>
                        </label>
                        <span id="m-selected-count" style="font-size:11px; color:#6366f1; font-weight:700; background:#eef2ff; padding:2px 8px; border-radius:10px;">0 / 0</span>
                    </div>
                    <div id="m-guest-list" style="flex-grow:1; overflow-y:auto; padding:0;">
                        <!-- Guest list will be injected here -->
                        <div style="padding:20px; text-align:center; color:#94a3b8; font-size:13px;">
                            ⏳ <?php esc_html_e( 'Cargando lista de huéspedes...', 'artechia-pms' ); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div style="display:flex; justify-content:flex-end; border-top:1px solid #e5e7eb; padding:16px 0 0; flex-shrink:0;">
            <button type="button" class="button button-primary" id="m-send-btn" disabled style="padding:8px 24px; font-size:14px; border-radius:8px; font-weight:600; display:inline-flex; align-items:center; gap:6px;">
                🚀 <?php esc_html_e( 'Enviar Campaña Ahora', 'artechia-pms' ); ?>
            </button>
        </div>
    </div>
</div>

<style>
/* Modal Base */
.artechia-modal {
    position: fixed; z-index: 99999; left: 0; top: 0; width: 100%; height: 100%;
    background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px);
    display: flex; align-items: center; justify-content: center;
    animation: modal-fade-in 0.2s ease-out;
}
@keyframes modal-fade-in { from { opacity: 0; } to { opacity: 1; } }

.artechia-modal-content {
    background: #fff; padding: 24px; border-radius: 12px; width: 550px; max-width: 95%;
    max-height: 90vh; display: flex; flex-direction: column; overflow: hidden;
    box-shadow: 0 20px 60px rgba(0,0,0,0.2), 0 0 0 1px rgba(0,0,0,0.05);
    animation: modal-slide-in 0.25s ease-out;
}
@keyframes modal-slide-in { from { transform: translateY(20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }

.artechia-modal-header {
    display: flex; justify-content: space-between; align-items: center;
    border-bottom: 1px solid #e5e7eb; margin-bottom: 20px; padding-bottom: 14px; flex-shrink: 0;
}
.artechia-modal-header h2 { margin: 0; font-size: 18px; font-weight: 700; color: #1e293b; }

/* Form Grid */
.artechia-form-grid {
    display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 10px;
}
.artechia-field label {
    display: block; font-weight: 600; margin-bottom: 6px; color: #475569; font-size: 13px;
}
.artechia-field input[type="text"],
.artechia-field input[type="number"],
.artechia-field input[type="date"],
.artechia-field select {
    width: 100%; height: 38px; border: 1px solid #e2e8f0; border-radius: 8px;
    padding: 0 12px; font-size: 13px; color: #1e293b; background: #fff;
    transition: border-color 0.2s, box-shadow 0.2s;
}
.artechia-field input:focus, .artechia-field select:focus {
    border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,0.1); outline: none;
}

/* Mode Cards */
.artechia-mode-card {
    flex: 1; cursor: pointer; font-weight: normal !important; margin: 0 !important;
}
.artechia-mode-inner {
    display: flex; align-items: center; gap: 10px; padding: 12px 14px;
    border: 2px solid #e5e7eb; border-radius: 10px; transition: all 0.2s;
    background: #fff;
}
.artechia-mode-card:hover .artechia-mode-inner { border-color: #c7d2fe; background: #faf5ff; }
.artechia-mode-card input:checked + .artechia-mode-inner {
    border-color: #6366f1; background: #eef2ff; box-shadow: 0 0 0 3px rgba(99,102,241,0.1);
}

/* Footer */
.artechia-modal-footer {
    display: flex; justify-content: flex-end; gap: 8px;
    border-top: 1px solid #e5e7eb; padding-top: 16px; margin-top: 8px;
}

/* Close Button */
.close-modal {
    background: none; border: none; font-size: 24px; cursor: pointer; color: #94a3b8;
    width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center;
    justify-content: center; transition: all 0.2s; padding: 0;
}
.close-modal:hover { color: #1e293b; background: #f1f5f9; }
/* Restore button appearance for footer cancel button */
.artechia-modal-footer .close-modal.button {
    width: auto; font-size: 13px; display: inline-flex; padding: 0 16px;
    color: #1e293b; border: 1px solid #e2e8f0; background: #fff;
}
.artechia-modal-footer .close-modal.button:hover {
    background: #f1f5f9; border-color: #cbd5e1;
}

/* Status Tags */
.status-tag { border-radius: 10px; padding: 2px 10px; font-size: 11px; font-weight: 600; }
.status-tag.active { background: #dcfce7; color: #16a34a; }
.status-tag.inactive { background: #fef2f2; color: #dc2626; }
.status-tag.accent { background: #eff6ff; color: #2563eb; }

</style>



<script>
document.addEventListener('DOMContentLoaded', function() {
    const app = {
        coupons: [],
        promos: [],
        templates: [],

        init() {
            this.cacheDOM();
            this.bindEvents();
            this.fetchData();
        },

        cacheDOM() {
            this.list = document.getElementById('discounts-list');
            
            // Coupon Modal
            this.couponModal = document.getElementById('coupon-modal');
            this.couponForm = document.getElementById('coupon-form');
            this.addBtn = document.getElementById('add-coupon-btn');
            
            // Marketing Modal
            this.marketingModal = document.getElementById('marketing-modal');
            this.marketingBtn = document.getElementById('open-marketing-btn');
            this.marketingForm = document.getElementById('marketing-form');
            this.mTemplateSelect = document.getElementById('m-field-template');
            this.mPropertySelect = document.getElementById('m-field-property');
            this.mPreviewBox = document.getElementById('m-campaign-preview');
            this.mCountDisplay = document.getElementById('m-guest-count');
            this.mSendBtn = document.getElementById('m-send-btn');

            // Stay-Pay fields
            this.wrapValue = document.getElementById('wrap-value');
            this.wrapStayPay = document.getElementById('wrap-stay-pay');
            this.wrapMinNights = document.getElementById('wrap-min-nights');
            this.stayPayPay = document.getElementById('stay-pay-pay');
            this.stayPayStay = document.getElementById('stay-pay-stay');
            this.typeSelect = document.getElementById('field-type');
            this.minNightsInput = document.getElementById('field-min-nights');
            this.valueInput = document.getElementById('field-value');
            
            this.closeBtns = document.querySelectorAll('.close-modal');
        },

        bindEvents() {
            // New Discount
            this.addBtn.onclick = () => this.openCouponModal();
            
            // Marketing
            this.marketingBtn.onclick = () => this.openMarketingModal();
            this.mTemplateSelect.onchange = () => this.updateMarketingPreview();
            this.mPropertySelect.onchange = () => {
                this.updateMarketingAudience();
                this.updateMarketingPreview();
                
                // If simple editor is active, trigger input to update its preview
                const simpleBody = document.getElementById('m-field-simple-body');
                if (simpleBody && simpleBody.parentElement.parentElement.style.display !== 'none') {
                    simpleBody.dispatchEvent(new Event('input'));
                }
            };
            this.mSendBtn.addEventListener('click', (e) => this.handleMarketingSubmit(e));

            // Toggle promo description based on selected discount & insert placeholders
            const promoSelect = document.getElementById('m-field-promo');
            const promoDescWrap = document.getElementById('m-field-promo-desc-wrap');
            if (promoSelect && promoDescWrap) {
                promoSelect.addEventListener('change', (e) => {
                    const tId = document.getElementById('m-field-template').value;
                    const selectedTemplate = this.templates?.find(x => x.id == tId);
                    const isPromoTemplate = selectedTemplate && selectedTemplate.event_type === 'marketing_promo';
                    const sendBtn = document.getElementById('m-send-btn');
                    const selectedOption = e.target.options[e.target.selectedIndex];
                    const discountType = selectedOption ? selectedOption.dataset.type : null; // 'promo' or 'coupon'
                    
                    if (e.target.value === '') {
                        promoDescWrap.style.display = 'none';
                        if (isPromoTemplate) {
                            sendBtn.disabled = true;
                            const errorMsg = 'Elegí un Descuento Vinculado para usar la plantilla Promocional.';
                            if (window.artechiaPMS && window.artechiaPMS.toast) {
                                window.artechiaPMS.toast.show(errorMsg, 'info');
                            } else {
                                window.ArtechiaDiscounts.showToast(errorMsg, 'info');
                            }
                        }
                        
                        // Clean up simple editor text only
                        const simpleBody = document.getElementById('m-field-simple-body');
                        if (simpleBody && (simpleBody.value.includes('{promo_code}') || simpleBody.value.includes('{promo_description}'))) {
                            simpleBody.value = simpleBody.value.replace(/\n\nUsá el siguiente código: \{promo_code\}\n\{promo_description\}/g, '');
                            simpleBody.value = simpleBody.value.replace(/\n\n🎉 \{promo_description\}\nEsta promoción se aplica automáticamente a tu reserva\./g, '');
                            simpleBody.dispatchEvent(new Event('input'));
                        }
                    } else {
                        promoDescWrap.style.display = 'block';
                        sendBtn.disabled = false;
                        
                        const isCoupon = discountType === 'coupon';
                        
                        // Update simple editor text only (don't touch the HTML template body)
                        const simpleBody = document.getElementById('m-field-simple-body');
                        if (simpleBody) {
                            // Remove any previous promo text
                            simpleBody.value = simpleBody.value.replace(/\n\nUsá el siguiente código: \{promo_code\}\n\{promo_description\}/g, '');
                            simpleBody.value = simpleBody.value.replace(/\n\n🎉 \{promo_description\}\nEsta promoción se aplica automáticamente a tu reserva\./g, '');
                            
                            // Insert the correct block
                            if (isCoupon) {
                                simpleBody.value += '\n\nUsá el siguiente código: {promo_code}\n{promo_description}';
                            } else {
                                simpleBody.value += '\n\n🎉 {promo_description}\nEsta promoción se aplica automáticamente a tu reserva.';
                            }
                            simpleBody.dispatchEvent(new Event('input'));
                        }
                    }
                    
                    // Refresh preview to reflect the discount type change
                    this.updateMarketingPreview();
                });
            }

            // Generic Modal Close
            this.closeBtns.forEach(btn => btn.onclick = () => {
                this.couponModal.style.display = 'none';
                this.marketingModal.style.display = 'none';
                document.body.style.overflow = '';
            });

            // Marketing Modal Tabs
            document.querySelectorAll('.m-tab').forEach(tab => {
                tab.addEventListener('click', () => {
                    document.querySelectorAll('.m-tab').forEach(t => {
                        t.style.background = 'transparent';
                        t.style.color = '#64748b';
                        t.style.boxShadow = 'none';
                        t.classList.remove('active');
                    });
                    tab.style.background = '#fff';
                    tab.style.color = '#1e293b';
                    tab.style.boxShadow = '0 1px 2px rgba(0,0,0,0.05)';
                    tab.classList.add('active');

                    const target = tab.dataset.tab;
                    const campaignPanel = document.getElementById('m-campaign-panel');
                    const historyPanel = document.getElementById('m-history-panel');
                    const footer = document.querySelector('#marketing-modal .artechia-modal-content > div:last-child');
                    
                    if (target === 'history') {
                        campaignPanel.style.display = 'none';
                        historyPanel.style.display = 'block';
                        if (footer) footer.style.display = 'none';
                        this.loadCampaignHistory();
                    } else {
                        campaignPanel.style.display = 'flex';
                        historyPanel.style.display = 'none';
                        if (footer) footer.style.display = 'flex'; // Footer uses display:flex to right-align the send btn
                    }
                });
            });

            // Target Select All Checkbox
            const selectAllBtn = document.getElementById('m-select-all');
            if (selectAllBtn) {
                selectAllBtn.addEventListener('change', (e) => {
                    const isChecked = e.target.checked;
                    document.querySelectorAll('.m-guest-check').forEach(chk => {
                        chk.checked = isChecked;
                    });
                    this.updateGuestCount();
                });
            }

            // Placeholders click to insert
            document.querySelectorAll('.m-placeholder').forEach(el => {
                el.addEventListener('click', (e) => {
                    e.preventDefault();
                    const txt = e.target.getAttribute('data-insert');
                    const bodyField = document.getElementById('m-field-body');
                    if (bodyField) {
                        const start = bodyField.selectionStart;
                        const end = bodyField.selectionEnd;
                        const text = bodyField.value;
                        bodyField.value = text.substring(0, start) + txt + text.substring(end, text.length);
                        bodyField.focus();
                        bodyField.selectionStart = bodyField.selectionEnd = start + txt.length;
                    }
                });
            });

            // Application Mode Toggle
            this.couponForm.querySelectorAll('input[name="app_mode"]').forEach(radio => {
                radio.onchange = () => this.toggleApplicationMode(radio.value);
            });

            this.couponForm.onsubmit = (e) => this.handleSaveDiscount(e);

            // Stay-Pay specific events
            this.typeSelect.onchange = () => this.handleTypeChange();
            this.stayPayPay.oninput = () => this.syncStayPayToValue();
            this.stayPayStay.oninput = () => this.syncStayPayToValue();
        },

        handleTypeChange() {
            const isStayPay = (this.typeSelect.value === 'stay_pay');
            this.wrapValue.style.display = isStayPay ? 'none' : 'block';
            this.wrapStayPay.style.display = isStayPay ? 'block' : 'none';
            this.wrapMinNights.style.display = isStayPay ? 'none' : 'block';
            
            if (isStayPay) {
                this.valueInput.removeAttribute('required');
                this.syncStayPayToValue();
            } else {
                this.valueInput.setAttribute('required', 'required');
            }
        },

        syncStayPayToValue() {
            const stay = this.stayPayStay.value || 0;
            const pay = this.stayPayPay.value || 0;
            this.valueInput.value = stay + '/' + pay;
            
            // User requested: "ajusta la cantidad de noches minimas al de 'paga: x noches'"
            this.minNightsInput.value = pay;
        },

        toggleApplicationMode(mode) {
            const wrapCode = document.getElementById('wrap-code');
            const wrapName = document.getElementById('wrap-name');
            const stayPayOpt = this.couponForm.querySelector('.only-promo');

            if (mode === 'promo') {
                wrapCode.style.display = 'none';
                wrapName.style.display = 'block';
                stayPayOpt.style.display = 'block';
                document.getElementById('field-table-type').value = 'promo';
            } else {
                wrapCode.style.display = 'block';
                wrapName.style.display = 'none';
                stayPayOpt.style.display = 'none';
                document.getElementById('field-table-type').value = 'coupon';
                if (this.typeSelect.value === 'stay_pay') {
                    this.typeSelect.value = 'percent';
                }
            }
            this.handleTypeChange();
        },

        async fetchData() {
            try {
                // Fetch Coupons
                const cRes = await fetch(artechiaPMS.restUrl + 'admin/coupons', { headers: { 'X-WP-Nonce': artechiaPMS.nonce } });
                this.coupons = await cRes.json();

                // Fetch Promos
                const pRes = await fetch(artechiaPMS.restUrl + 'admin/promotions', { headers: { 'X-WP-Nonce': artechiaPMS.nonce } });
                this.promos = await pRes.json();

                this.renderDiscounts();
            } catch (err) { console.error(err); }
        },

        renderDiscounts() {
            const all = [
                ...this.coupons.map(c => ({...c, _origin: 'coupon', display_name: c.code, display_type: 'Cupón'})),
                ...this.promos.map(p => ({...p, _origin: 'promo', display_name: p.name, display_type: 'Promoción'}))
            ].sort((a,b) => b.id - a.id);

            // Update stat cards
            const activeCount = all.filter(d => d.active).length;
            const el = (id, val) => { const e = document.getElementById(id); if(e) e.textContent = val; };
            const totalUsage = all.reduce((sum, d) => sum + (parseInt(d.usage_count) || 0), 0);
            el('pstat-total', all.length);
            el('pstat-usage', totalUsage);
            el('pstat-coupons', this.coupons.length);
            el('pstat-promos', this.promos.length);

            if (all.length === 0) {
                this.list.innerHTML = `<tr><td colspan="6" style="text-align:center; padding:60px 20px;">
                    <div style="font-size:48px; margin-bottom:12px;">🏷️</div>
                    <div style="font-size:16px; font-weight:600; color:#1e293b; margin-bottom:6px;">No hay descuentos registrados</div>
                    <div style="font-size:13px; color:#94a3b8; margin-bottom:16px;">Creá tu primer cupón o promoción para empezar.</div>
                    <button class="button button-primary" onclick="document.getElementById('add-coupon-btn').click()" style="border-radius:6px;">
                        + Nuevo Descuento
                    </button>
                </td></tr>`;
                return;
            }

            const transMap = {
                'percent': 'Porcentaje',
                'fixed': 'Monto Fijo',
                'stay_pay': 'StayPay'
            };

            this.list.innerHTML = all.map(d => {
                const rawType = d.type || d.rule_type;
                const displayTypeLabel = transMap[rawType] || rawType;
                
                let rawVal = d.value || d.rule_value || '';
                let displayVal = rawVal.toString().replace(/\.00$/, '');
                if (rawType !== 'stay_pay' && !isNaN(parseFloat(displayVal))) {
                    displayVal = parseFloat(displayVal).toString();
                }

                // Value display
                let valueDisplay = '';
                if (rawType === 'percent') {
                    valueDisplay = `<span style="font-size:18px; font-weight:700; color:#6366f1;">${displayVal}%</span>`;
                } else if (rawType === 'fixed') {
                    valueDisplay = `<span style="font-size:18px; font-weight:700; color:#16a34a;">$${displayVal}</span>`;
                } else if (rawType === 'stay_pay') {
                    const parts = displayVal.split('/');
                    valueDisplay = `<span style="font-size:14px; font-weight:700; color:#d97706;">Quedate ${parts[0]} / Pagá ${parts[1]}</span>`;
                }
                valueDisplay += `<div style="font-size:10px; color:#94a3b8; margin-top:2px;">${displayTypeLabel}</div>`;

                // Date Formatting
                let dateDisplay = '<span style="color:#94a3b8;">Sin vencimiento</span>';
                if (d.starts_at || d.ends_at) {
                    const formatDt = (dtStr) => {
                        if (!dtStr) return '...';
                        // Append T00:00:00 to plain YYYY-MM-DD dates so JS treats them as local, not UTC
                        const localStr = dtStr.length === 10 ? dtStr + 'T00:00:00' : dtStr;
                        const dt = new Date(localStr);
                        if (isNaN(dt)) return dtStr;
                        return dt.toLocaleDateString('es-AR', { day: '2-digit', month: '2-digit', year: '2-digit' });
                    };
                    dateDisplay = `<span style="font-size:12px;">📅 ${formatDt(d.starts_at)} → ${formatDt(d.ends_at)}</span>`;
                }

                // Type badge
                const isCoupon = d._origin === 'coupon';
                const typeBadge = isCoupon
                    ? `<span style="display:inline-block; padding:2px 10px; border-radius:10px; font-size:11px; font-weight:600; background:#fdf4ff; color:#a855f7;">🎟️ Cupón</span>`
                    : `<span style="display:inline-block; padding:2px 10px; border-radius:10px; font-size:11px; font-weight:600; background:#fef9c3; color:#d97706;">⚡ Promoción</span>`;

                // Row opacity for inactive
                const rowStyle = d.active ? '' : 'opacity:0.5;';

                return `
                <tr style="${rowStyle}">
                    <td style="padding:12px 16px;">
                        <div style="font-weight:700; font-size:14px; color:#1e293b;">${d.display_name}</div>
                        <div style="font-size:11px; color:#94a3b8; margin-top:2px;">
                            ${d.min_nights ? `Mín. ${d.min_nights} noches · ` : ''}<span style="color:#6366f1; font-weight:600;">${parseInt(d.usage_count) || 0} usos</span>
                        </div>
                    </td>
                    <td style="padding:12px 16px;">${typeBadge}</td>
                    <td style="padding:12px 16px;">${valueDisplay}</td>
                    <td style="padding:12px 16px;">${dateDisplay}</td>
                    <td style="padding:12px 16px; text-align:center;">
                        <label class="artechia-switch" style="margin:0;">
                            <input type="checkbox" onchange="ArtechiaDiscounts.toggleActive('${d._origin}', ${d.id}, this.checked)" ${d.active ? 'checked' : ''}>
                            <span class="slider round"></span>
                        </label>
                    </td>
                    <td style="padding:12px 16px;">
                        <div style="display:flex; gap:6px;">
                            <button class="button button-small" onclick="ArtechiaDiscounts.edit('${d._origin}', ${d.id})" style="border-radius:4px;">✏️ Editar</button>
                            <button class="button button-small button-link-delete" onclick="ArtechiaDiscounts.delete('${d._origin}', ${d.id})" style="border-radius:4px;">🗑️</button>
                        </div>
                    </td>
                </tr>
            `}).join('');
        },

        async toggleActive(type, id, checked) {
            try {
                const endpoint = type === 'promo' ? 'promotions' : 'coupons';
                const method = id ? (type === 'promo' ? 'PUT' : 'POST') : 'POST';
                const activeVal = checked ? 1 : 0;
                
                // Get the existing data if it's a promotion because PUT might require required fields like name.
                // Wait! To be safe, let's pass partial fields if API supports it. The API just does: $repo->update( $id, $params );
                // Let's assume it accepts partial updates.
                const res = await fetch(artechiaPMS.restUrl + 'admin/' + endpoint + '/' + id, {
                    method: method,
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': artechiaPMS.nonce
                    },
                    body: JSON.stringify({ active: activeVal })
                });
                const data = await res.json();
                if (res.ok) {
                    window.ArtechiaDiscounts.showToast('Estado actualizado.', 'success');
                    this.fetchData();
                } else {
                    throw new Error(data.message || 'Error al actualizar');
                }
            } catch (err) {
                console.error(err);
                window.ArtechiaDiscounts.showToast('Error: ' + err.message, 'error');
                this.fetchData(); // reverts the toggle visually
            }
        },

        openCouponModal(data = null) {
            this.couponForm.reset();
            
            if (data) {
                document.getElementById('modal-title').innerText = 'Editar Descuento';
                document.getElementById('field-id').value = data.id;
                document.getElementById('field-table-type').value = data._origin;
                
                const isPromo = data._origin === 'promo';
                this.couponForm.querySelector(`input[name="app_mode"][value="${isPromo ? 'promo' : 'coupon'}"]`).checked = true;
                this.toggleApplicationMode(isPromo ? 'promo' : 'coupon');
                
                // Hide mode selector entirely when editing
                const modeWrap = document.getElementById('mode-selector-wrap');
                if (modeWrap) modeWrap.style.display = 'none';

                if (isPromo) {
                    document.getElementById('field-name').value = data.name;
                    document.getElementById('field-type').value = data.rule_type;
                    document.getElementById('field-value').value = data.rule_value;
                } else {
                    document.getElementById('field-code').value = data.code;
                    document.getElementById('field-type').value = data.type;
                    document.getElementById('field-value').value = data.value;
                }

                document.getElementById('field-min-nights').value = data.min_nights || 0;
                // Dates from DB may be datetime strings: slice to YYYY-MM-DD for input[type=date]
                document.getElementById('field-starts-at').value = data.starts_at ? data.starts_at.substring(0, 10) : '';
                document.getElementById('field-ends-at').value = data.ends_at ? data.ends_at.substring(0, 10) : '';

                // Handle Stay-Pay initial state
                if (isPromo && data.rule_type === 'stay_pay') {
                    const parts = (data.rule_value || '').split('/');
                    this.stayPayStay.value = parts[0] || '';
                    this.stayPayPay.value = parts[1] || '';
                } else {
                    this.stayPayStay.value = '';
                    this.stayPayPay.value = '';
                }
                this.handleTypeChange();

            } else {
                document.getElementById('modal-title').innerText = 'Nuevo Descuento';
                document.getElementById('field-id').value = '';
                this.toggleApplicationMode('coupon');
                
                // Show mode selector for new creation
                const modeWrap = document.getElementById('mode-selector-wrap');
                if (modeWrap) modeWrap.style.display = '';
            }
            this.couponModal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        },

        async handleSaveDiscount(e) {
            e.preventDefault();
            const formData = new FormData(this.couponForm);
            const data = Object.fromEntries(formData.entries());
            const type = data.table_type; // 'coupon' or 'promo'
            const id = data.id;

            // Normalize payload for each endpoint
            let payload = {
                min_nights: data.min_nights,
                starts_at: data.starts_at || null,
                ends_at: data.ends_at || null,
                active: 1
            };

            if (type === 'promo') {
                payload.name = data.name;
                payload.rule_type = data.type;
                payload.rule_value = data.value;
            } else {
                payload.code = data.code;
                payload.type = data.type;
                payload.value = data.value;
            }

            const url = artechiaPMS.restUrl + 'admin/' + (type === 'promo' ? 'promotions' : 'coupons') + (id ? '/' + id : '');
            const method = id ? (type === 'promo' ? 'PUT' : 'POST') : 'POST';

            try {
                const res = await fetch(url, {
                    method: method,
                    headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': artechiaPMS.nonce },
                    body: JSON.stringify(payload)
                });
                if (res.ok) {
                    this.couponModal.style.display = 'none';
                    document.body.style.overflow = '';
                    this.fetchData();
                } else {
                    const err = await res.json();
                    window.ArtechiaDiscounts.showToast('Error: ' + (err.message || 'No se pudo guardar'), 'error');
                }
            } catch (err) { window.ArtechiaDiscounts.showToast('Error de conexión o validación', 'error'); }
        },

        /* ── MARKETING ── */

        async openMarketingModal() {
            try {
                // Check if marketing is enabled via localized script variables
                let isEnabled = false;
                
                if (window.artechiaPMS && window.artechiaPMS.settings) {
                    const sm = window.artechiaPMS.settings.marketing_enabled;
                    isEnabled = (sm === '1' || sm === 'yes' || String(sm) === '1' || sm === true);
                }
                
                if (!isEnabled) {
                    const errorMsg = 'El envío de Email Marketing está desactivado en los Ajustes del sistema.';
                    if (window.artechiaPMS && window.artechiaPMS.toast) {
                         window.artechiaPMS.toast.show(errorMsg, 'error');
                    } else {
                         window.ArtechiaDiscounts.showToast(errorMsg, 'error');
                    }
                    return;
                }
            } catch (err) {
                console.warn("No se pudo verificar el estado del marketing:", err);
            }

            // We no longer block access if there are no coupons/promos.
            if (app.promos.length === 0 && app.coupons.length === 0) {
                await this.fetchData(); 
            }

            this.marketingModal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
            if (this.templates.length === 0) {
                try {
                    const tRes = await fetch(artechiaPMS.restUrl + 'admin/email-templates', { headers: { 'X-WP-Nonce': artechiaPMS.nonce } });
                    const allTemplates = await tRes.json();
                    // Only allow marketing templates to be used for mass campaigns
                    this.templates = allTemplates.filter(t => t.event_type === 'marketing_promo');
                    
                    const pRes = await fetch(artechiaPMS.restUrl + 'admin/setup/properties?include_demo=1', { headers: { 'X-WP-Nonce': artechiaPMS.nonce } });
                    const props = await pRes.json();
                    app.properties = props;
                    document.getElementById('m-field-property').innerHTML = '<option value="">Todas las propiedades</option>' + 
                        props.map(p => `<option value="${p.id}">${p.name}</option>`).join('');

                    // Generate base HTML for simple editor
                    const getBaseHTML = (message) => {
                        const baseUrl = window.location.origin; // Close enough for preview purposes
                        const guestName = '{guest_name}';
                        let propertyName = '{property_name}';
                        let propertyLogo = '{property_logo}';
                        
                        // Retrieve current property or default
                        const propSelect = document.getElementById('m-field-property');
                        if (propSelect && app.properties && app.properties.length > 0) {
                            const pId = propSelect.value;
                            const pData = pId ? app.properties.find(x => x.id == pId) : app.properties[0];
                            if (pData) {
                                propertyName = pData.name;
                                propertyLogo = pData.logo_url ? `<img src="${pData.logo_url}" alt="${pData.name}" style="max-height: 50px;">` : '';
                            }
                        }
                        
                        const promoCode = '{promo_code}';
                        const promoDesc = '{promo_description}';
                        const bookingUrl = '{booking_url}';
                        
                        // Replace newlines with <br> for HTML rendering
                        const formattedMessage = message.replace(/\n/g, '<br>');
                        
                        // Fake fallbacks for preview ONLY since actual processing happens backend
                        const fName = propertyName === '{property_name}' ? 'Tu Propiedad' : propertyName;
                        let fLogo = (propertyLogo === '{property_logo}' || propertyLogo === '') ? '<div style="font-size:40px; color:#94a3b8;">🏨</div>' : propertyLogo;
                        if (fLogo === '<div style="font-size:40px; color:#94a3b8;">🏨</div>' && window.artechiaPMS?.settings?.logo_url) {
                            fLogo = `<img src="${window.artechiaPMS.settings.logo_url}" alt="${fName}" style="max-height: 50px;">`;
                        }
                        
                        return `<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { margin: 0; padding: 0; background-color: #f8fafc; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; color: #1e293b; line-height: 1.6; }
        .wrapper { width: 100%; table-layout: fixed; background-color: #f8fafc; padding-bottom: 40px; }
        .main { background-color: #ffffff; margin: 20px auto; width: 100%; max-width: 600px; border-spacing: 0; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .header { background-color: #ffffff; padding: 30px 20px; text-align: center; border-bottom: 4px solid #2563eb; }
        .header h1 { color: #2563eb; margin: 0; font-size: 26px; font-weight: 800; }
        .content { padding: 40px 30px; }
        .promo-card { background-color: #eff6ff; border: 2px dashed #93c5fd; border-radius: 12px; padding: 24px; text-align: center; margin: 24px 0; }
        .promo-code { font-family: monospace; font-size: 32px; font-weight: 800; color: #1d4ed8; letter-spacing: 2px; margin: 12px 0; display: block; }
        .btn { display: inline-block; background: #2563eb; color: #ffffff !important; padding: 16px 32px; border-radius: 8px; text-decoration: none; font-weight: 700; }
        .footer { text-align: center; padding: 30px; color: #64748b; font-size: 14px; background-color: #f8fafc; }
    </style>
</head>
<body>
    <div class="wrapper">
        <table class="main">
            <tr><td class="header"><div style="margin-bottom:15px;">${fLogo}</div><h1>${fName}</h1></td></tr>
            <tr>
                <td class="content">
                    <p>Hola <strong>${guestName}</strong>,</p>
                    <p>${formattedMessage}</p>
                    <!-- [PROMO_END] -->
                    <div style="text-align: center; margin-top: 30px;"><a href="${bookingUrl}" class="btn">Reservar ahora</a></div>
                </td>
            </tr>
        </table>
        <table style="margin: 0 auto; width: 100%; max-width: 600px;">
            <tr>
                <td class="footer">
                    <p>&copy; 2026 ${fName}. Todos los derechos reservados.</p>
                    <p><a href="{unsubscribe_url}" style="color: #64748b;">Darse de baja</a></p>
                </td>
            </tr>
        </table>
    </div>
</body>
</html>`;
                    };

                    // Bind HTML toggle button (for existing templates or when switching from simple)
                    document.getElementById('m-toggle-html-btn').onclick = (e) => {
                        e.preventDefault();
                        document.getElementById('m-simple-editor-wrapper').style.display = 'none';
                        const isHidden = document.getElementById('m-html-editor-wrapper').style.display === 'none';
                        document.getElementById('m-html-editor-wrapper').style.display = isHidden ? 'flex' : 'none';
                        document.getElementById('m-preview-container').style.display = isHidden ? 'none' : 'block';
                    };
                    
                    // Bind switch to advanced button in simple editor
                    document.getElementById('m-switch-to-advanced-btn').onclick = (e) => {
                        e.preventDefault();
                        const subject = document.getElementById('m-field-simple-subject').value;
                        const message = document.getElementById('m-field-simple-body').value;
                        
                        document.getElementById('m-field-subject').value = subject;
                        document.getElementById('m-field-body').value = getBaseHTML(message);
                        
                        document.getElementById('m-simple-editor-wrapper').style.display = 'none';
                        document.getElementById('m-html-editor-wrapper').style.display = 'flex';
                        document.getElementById('m-preview-container').style.display = 'none';
                        
                        // We are now in advanced mode for a new template. Save button in HTML editor will handle it.
                    };
                    
                    // Live preview for simple editor
                    const updateSimplePreview = () => {
                        const message = document.getElementById('m-field-simple-body').value || 'Escribí el texto de tu email acá...';
                        document.getElementById('m-campaign-preview').innerHTML = getBaseHTML(message);
                    };
                    document.getElementById('m-field-simple-body').addEventListener('input', updateSimplePreview);
                    
                    // Common save function
                    const saveMarketingTemplate = async (subject, body, btnEl) => {
                        if (!subject || !body) {
                            if (window.artechiaPMS && window.artechiaPMS.toast) {
                                 window.artechiaPMS.toast.show("El Asunto y el Mensaje son obligatorios.", 'error');
                            }
                            return false;
                        }
                        btnEl.disabled = true;
                        const originalHtml = btnEl.innerHTML;
                        btnEl.innerHTML = 'Guardando...';
                        try {
                            const res = await fetch(artechiaPMS.restUrl + 'admin/email-templates', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-WP-Nonce': artechiaPMS.nonce
                                },
                                body: JSON.stringify({ subject: subject, body_html: body, event_type: 'marketing_custom' })
                            });
                            const data = await res.json();
                            if (res.ok && data.success) {
                                if (window.artechiaPMS && window.artechiaPMS.toast) {
                                    window.artechiaPMS.toast.show("Plantilla creada correctamente.", 'success');
                                }
                                
                                // Refresh templates
                                const tRes = await fetch(artechiaPMS.restUrl + 'admin/email-templates', { headers: { 'X-WP-Nonce': artechiaPMS.nonce } });
                                const allTemplates = await tRes.json();
                                this.templates = allTemplates.filter(t => t.event_type === 'marketing_promo' || t.event_type === 'marketing_custom');
                                
                                // Repopulate select and select the new template
                                const newOptionHTML = '<option value="new" style="font-weight: bold; color: #2271b1;">+ Crear Nueva Plantilla...</option>';
                                document.getElementById('m-field-template').innerHTML = '<option value="">-- Selecciona una plantilla --</option>' + 
                                    newOptionHTML + this.templates.map(t => `<option value="${t.id}">${t.subject}</option>`).join('');
                                    
                                document.getElementById('m-field-template').value = data.id;
                                
                                // Update preview
                                this.updateMarketingPreview();
                                return true;
                            } else {
                                throw new Error(data.message || 'Error al crear la plantilla');
                            }
                        } catch (err) {
                            console.error(err);
                            if (window.artechiaPMS && window.artechiaPMS.toast) {
                                window.artechiaPMS.toast.show("Error: " + err.message, 'error');
                            }
                            return false;
                        } finally {
                            btnEl.disabled = false;
                            btnEl.innerHTML = originalHtml;
                        }
                    };
                    
                    // Bind Save simple template button
                    document.getElementById('m-save-simple-btn').onclick = async (e) => {
                        e.preventDefault();
                        const subject = document.getElementById('m-field-simple-subject').value;
                        const message = document.getElementById('m-field-simple-body').value;
                        const html = getBaseHTML(message);
                        await saveMarketingTemplate(subject, html, e.currentTarget);
                    };

                    // Bind Save and Return button (Advanced Editor)
                    document.getElementById('m-save-html-btn').onclick = async (e) => {
                        e.preventDefault();
                        
                        const tmplId = document.getElementById('m-field-template').value;
                        const subject = document.getElementById('m-field-subject').value;
                        const body = document.getElementById('m-field-body').value;
                        const saveBtn = document.getElementById('m-save-html-btn');

                        if (tmplId === 'new') {
                            await saveMarketingTemplate(subject, body, saveBtn);
                        } else {
                            document.getElementById('m-campaign-preview').innerHTML = body;
                            document.getElementById('m-html-editor-wrapper').style.display = 'none';
                            document.getElementById('m-preview-container').style.display = 'block';
                        }
                    };
                    
                    // Bind live HTML preview sync (when returning from HTML to preview)
                    document.getElementById('m-field-body').addEventListener('blur', () => {
                         document.getElementById('m-campaign-preview').innerHTML = document.getElementById('m-field-body').value;
                    });
                    
                } catch (err) { console.error(err); }
            }

            // Always repopulate templates list to reset selection
            const newOptionHTML = '<option value="new" style="font-weight: bold; color: #2271b1;">+ Crear Nueva Plantilla...</option>';
            if (this.templates.length === 0) {
                 document.getElementById('m-field-template').innerHTML = '<option value="" selected>-- No hay plantillas de marketing disponibles --</option>' + newOptionHTML;
            } else {
                 document.getElementById('m-field-template').innerHTML = '<option value="" selected>-- Selecciona una plantilla --</option>' + 
                    newOptionHTML + this.templates.map(t => `<option value="${t.id}">${t.subject}</option>`).join('');
            }


            // Always dynamically repopulate Promos & Coupons (so it respects auto-refresh)
            const combinedDiscounts = [
                ...app.promos.map(p => ({ id: 'promo_' + p.id, type: 'promo', label: 'Promoción: ' + p.name, code: p.name, desc: `Aprovecha la promoción especial: ${p.name}` })),
                ...app.coupons.map(c => ({ id: 'coupon_' + c.id, type: 'coupon', label: 'Cupón: ' + c.code, code: c.code, desc: `Utiliza este cupón durante tu reserva en nuestra web para obtener tu beneficio.` }))
            ];
            
            // Generate the string of options with data-type attribute
            let comboDiscountsHTML = combinedDiscounts.map(d => `<option value="${d.code}" data-desc="${d.desc}" data-type="${d.type}">${d.label}</option>`).join('');
            document.getElementById('m-field-promo').innerHTML = '<option value="">-- Personalizado (Sin descuento) --</option>' + comboDiscountsHTML;
            
            const promoSelect = document.getElementById('m-field-promo');
            const promoDesc = document.getElementById('m-field-promo-desc');
            
            // We moved the onchange to bindEvents, so we don't need to override it here.
            
            promoDesc.value = ''; // clean up on open

            this.updateMarketingAudience();
            this.updateMarketingPreview();
        },

        updateMarketingPreview() {
            const id = document.getElementById('m-field-template').value;
            
            const subjField = document.getElementById('m-field-subject');
            const bodyField = document.getElementById('m-field-body');
            const previewBox = document.getElementById('m-campaign-preview');
            const saveBtn = document.getElementById('m-save-html-btn');
            const saveCheckWrap = document.getElementById('m-save-template-check')?.closest('label');
            const promoWrap = document.getElementById('m-field-promo-wrap');
            const contentHeaderWrap = document.getElementById('m-content-header-wrap');
            
            // Hide everything if no template is selected
            if (id === '') {
                if (promoWrap) promoWrap.style.display = 'none';
                if (contentHeaderWrap) contentHeaderWrap.style.display = 'none';
                document.getElementById('m-html-editor-wrapper').style.display = 'none';
                document.getElementById('m-simple-editor-wrapper').style.display = 'none';
                document.getElementById('m-preview-container').style.display = 'none';
                document.getElementById('m-empty-state').style.display = 'block';
                document.getElementById('m-send-btn').disabled = true;
                return;
            }

            document.getElementById('m-empty-state').style.display = 'none';
            if (contentHeaderWrap) contentHeaderWrap.style.display = 'flex';
            
            if (id === 'new') {
                if (promoWrap) promoWrap.style.display = 'none';
                // Reset simple fields
                document.getElementById('m-field-simple-subject').value = '';
                document.getElementById('m-field-simple-body').value = '';
                
                // Set HTML fields just in case they switch
                subjField.value = '';
                bodyField.value = '';
                
                // Show default preview wrapper initially
                const defaultMsg = 'Escribí el texto de tu email acá...';
                // Trigger preview generation via the exact same logic
                document.getElementById('m-field-simple-body').value = ''; 
                document.getElementById('m-field-simple-body').dispatchEvent(new Event('input'));
                
                document.getElementById('m-send-btn').disabled = true;
                saveBtn.innerHTML = '<span class="dashicons dashicons-saved"></span> Guardar Nueva Plantilla';
                if (saveCheckWrap) saveCheckWrap.style.display = 'none';
                if (document.getElementById('m-toggle-html-btn')) {
                    document.getElementById('m-toggle-html-btn').style.display = 'none';
                }
                
                // Open simple editor + preview
                document.getElementById('m-html-editor-wrapper').style.display = 'none';
                document.getElementById('m-simple-editor-wrapper').style.display = 'flex';
                document.getElementById('m-preview-container').style.display = 'block';
                return;
            }

            saveBtn.innerHTML = '<span class="dashicons dashicons-saved"></span> Guardar y Volver';
            if (saveCheckWrap) saveCheckWrap.style.display = 'inline-flex';

            const t = this.templates.find(x => x.id == id);
            if (t) {
                if (promoWrap) promoWrap.style.display = 'block';
                const isPromoTemplate = t.event_type === 'marketing_promo';
                const sendBtn = document.getElementById('m-send-btn');
                const promoSelect = document.getElementById('m-field-promo');
                const promoDesc = document.getElementById('m-field-promo-desc');
                const noDiscountsAlert = document.getElementById('m-no-discounts-alert');
                
                // Count actual discount options (exclude the empty "Personalizado" option)
                const discountOptions = promoSelect ? Array.from(promoSelect.options).filter(o => o.value !== '') : [];
                const hasDiscounts = discountOptions.length > 0;
                
                if (isPromoTemplate) {
                    if (!hasDiscounts) {
                        // No discounts exist — show alert, hide dropdown, disable button
                        promoSelect.style.display = 'none';
                        promoWrap.querySelector('label').style.display = 'none';
                        promoWrap.querySelector('.description').style.display = 'none';
                        if (noDiscountsAlert) noDiscountsAlert.style.display = 'block';
                        sendBtn.disabled = true;
                    } else {
                        // Discounts exist — hide the "Personalizado" empty option, show dropdown
                        promoSelect.style.display = '';
                        promoWrap.querySelector('label').style.display = '';
                        promoWrap.querySelector('.description').style.display = '';
                        if (noDiscountsAlert) noDiscountsAlert.style.display = 'none';
                        
                        // Hide the empty "Personalizado" option
                        const emptyOpt = promoSelect.querySelector('option[value=""]');
                        if (emptyOpt) emptyOpt.style.display = 'none';
                        
                        // Auto-select first discount if nothing selected
                        if (!promoSelect.value && discountOptions.length > 0) {
                            promoSelect.value = discountOptions[0].value;
                            promoSelect.dispatchEvent(new Event('change'));
                        }
                        
                        sendBtn.disabled = false;
                    }
                } else {
                    // Non-promo template — restore normal dropdown state
                    promoSelect.style.display = '';
                    promoWrap.querySelector('label').style.display = '';
                    promoWrap.querySelector('.description').style.display = '';
                    if (noDiscountsAlert) noDiscountsAlert.style.display = 'none';
                    
                    // Show the "Personalizado" option again
                    const emptyOpt = promoSelect.querySelector('option[value=""]');
                    if (emptyOpt) emptyOpt.style.display = '';
                    
                    sendBtn.disabled = false;
                }

                // Ensure the Edit HTML button is visible
                if (document.getElementById('m-toggle-html-btn')) document.getElementById('m-toggle-html-btn').style.display = 'block';
                
                subjField.value = t.subject;
                bodyField.value = t.body_html;

                let prName = 'Tu Propiedad';
                let prLogo = window.artechiaPMS?.settings?.logo_url ? `<img src="${window.artechiaPMS.settings.logo_url}" alt="Logo" style="max-height: 50px;">` : '<div style="font-size:40px; color:#94a3b8;">🏨</div>';
                const propSelect = document.getElementById('m-field-property');
                if (propSelect && app.properties && app.properties.length > 0) {
                    const pId = propSelect.value;
                    const pData = pId ? app.properties.find(x => x.id == pId) : app.properties[0];
                    if (pData) {
                        prName = pData.name;
                        prLogo = pData.logo_url ? `<img src="${pData.logo_url}" alt="${pData.name}" style="max-height: 50px;">` : prLogo;
                    }
                }
                let finalHtml = t.body_html.replace(/{property_name}/g, prName).replace(/{property_logo}/g, prLogo);

                previewBox.innerHTML = finalHtml;

                // Dynamically swap the promo-card based on discount type (DOM-based, not regex)
                const promoSelectEl = document.getElementById('m-field-promo');
                if (promoSelectEl && promoSelectEl.value) {
                    const selOpt = promoSelectEl.options[promoSelectEl.selectedIndex];
                    const dType = selOpt ? selOpt.dataset.type : null;
                    if (dType === 'promo') {
                        const card = previewBox.querySelector('.promo-card');
                        if (card) {
                            card.innerHTML = '<p style="margin:0;font-weight:600;color:#1e3a8a;">🎉 ¡Promoción Especial!</p><p style="margin:8px 0 0;font-size:16px;font-weight:700;color:#1d4ed8;">{promo_description}</p><p style="margin:8px 0 0;font-size:13px;color:#1e40af;">Esta promoción se aplica automáticamente al reservar.</p>';
                        }
                    }
                }
                
            } else {
                subjField.value = '';
                bodyField.value = '';
                previewBox.innerHTML = '<p class="description">Selecciona una plantilla para ver la previsualización.</p>';
                document.getElementById('m-send-btn').disabled = true;
                if (document.getElementById('m-toggle-html-btn')) {
                    document.getElementById('m-toggle-html-btn').style.display = 'none';
                }
            }
            
            // Default view to Preview
            document.getElementById('m-html-editor-wrapper').style.display = 'none';
            document.getElementById('m-simple-editor-wrapper').style.display = 'none';
            document.getElementById('m-preview-container').style.display = 'block';
        },

        async updateMarketingAudience() {
            const pid = document.getElementById('m-field-property').value;
            const listEl = document.getElementById('m-guest-list');
            listEl.innerHTML = '<div style="padding:15px;text-align:center;color:#666;">Cargando lista de huéspedes...</div>';
            
            try {
                const res = await fetch(artechiaPMS.restUrl + 'admin/marketing/guests?property_id=' + pid, { headers: { 'X-WP-Nonce': artechiaPMS.nonce } });
                const guests = await res.json();
                
                if (!guests || guests.length === 0) {
                    listEl.innerHTML = '<div style="padding:15px;text-align:center;color:#666;">No se encontraron huéspedes.</div>';
                    this.updateGuestCount();
                    return;
                }
                
                let html = '';
                guests.forEach(g => {
                    const name = `${g.first_name || ''} ${g.last_name || ''}`.trim();
                    const lastBooking = g.last_booking ? new Date(g.last_booking).toLocaleDateString() : '—';
                    html += `
                        <label style="display: flex; justify-content: space-between; align-items: center; padding: 10px; border-bottom: 1px solid #eee; cursor: pointer;">
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <input type="checkbox" class="m-guest-check" value="${g.id}" checked>
                                <div>
                                    <div style="font-weight: 500;">${name}</div>
                                    <div style="font-size: 11px; color: #666;">${g.email}</div>
                                </div>
                            </div>
                            <div style="font-size: 11px; color: #666; text-align: right;">
                                Últ. Reserva:<br>${lastBooking}
                            </div>
                        </label>
                    `;
                });
                
                listEl.innerHTML = html;
                
                // Add change listeners to individual checkboxes
                listEl.querySelectorAll('.m-guest-check').forEach(chk => {
                    chk.addEventListener('change', () => this.updateGuestCount());
                });
                
                this.updateGuestCount();
                
            } catch (err) { 
                listEl.innerHTML = '<div style="padding:15px;text-align:center;color:red;">Error al cargar huéspedes.</div>';
                document.getElementById('m-selected-count').innerText = '0 / 0';
            }
        },
        
        updateGuestCount() {
            const checks = document.querySelectorAll('.m-guest-check');
            const total = checks.length;
            const selected = Array.from(checks).filter(c => c.checked).length;
            document.getElementById('m-selected-count').innerText = `${selected} / ${total}`;
            
            // Update "Select All" checked state
            const selectAll = document.getElementById('m-select-all');
            if (selectAll) selectAll.checked = (selected === total && total > 0);
        },

        async loadCampaignHistory() {
            const list = document.getElementById('m-history-list');
            try {
                const res = await fetch(artechiaPMS.restUrl + 'admin/marketing/history', {
                    headers: { 'X-WP-Nonce': artechiaPMS.nonce }
                });
                const campaigns = await res.json();

                if (!campaigns || campaigns.length === 0) {
                    list.innerHTML = `<div style="text-align:center; padding:60px 20px;">
                        <div style="font-size:48px; margin-bottom:12px;">📭</div>
                        <div style="font-size:16px; font-weight:600; color:#1e293b; margin-bottom:6px;">Sin campañas enviadas</div>
                        <div style="font-size:13px; color:#94a3b8;">Enviá tu primera campaña desde la pestaña "Nueva Campaña".</div>
                    </div>`;
                    return;
                }

                const formatDate = (dateStr) => {
                    const d = new Date(dateStr);
                    return d.toLocaleDateString('es-AR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
                };

                const timeAgo = (dateStr) => {
                    const now = new Date();
                    const d = new Date(dateStr);
                    const diff = Math.floor((now - d) / 1000);
                    if (diff < 60) return 'Hace unos segundos';
                    if (diff < 3600) return `Hace ${Math.floor(diff / 60)} min`;
                    if (diff < 86400) return `Hace ${Math.floor(diff / 3600)} hora(s)`;
                    if (diff < 604800) return `Hace ${Math.floor(diff / 86400)} día(s)`;
                    return formatDate(dateStr);
                };

                list.innerHTML = campaigns.map((c, i) => {
                    const hasErrors = c.errors > 0;
                    const borderColor = hasErrors ? '#f59e0b' : '#16a34a';
                    const cardId = `campaign-detail-${c.id}`;
                    
                    // Detail section content
                    const templateLine = c.template_name ? `<div style="display:flex; align-items:center; gap:6px; margin-bottom:6px;"><span style="color:#94a3b8; font-size:11px; min-width:65px;">Plantilla:</span><span style="font-size:12px; font-weight:500; color:#334155;">${c.template_name}</span></div>` : '';
                    const promoLine = c.promo_code ? `<div style="display:flex; align-items:center; gap:6px; margin-bottom:6px;"><span style="color:#94a3b8; font-size:11px; min-width:65px;">Cupón:</span><span style="background:#f3f0ff; color:#7c3aed; padding:1px 8px; border-radius:10px; font-size:11px; font-weight:700; border:1px solid #e0d5ff;">${c.promo_code}</span></div>` : '';
                    const recipientsList = (c.recipients || []).length > 0 
                        ? `<div style="margin-top:4px;"><span style="color:#94a3b8; font-size:11px;">Destinatarios:</span><div style="display:flex; flex-wrap:wrap; gap:4px; margin-top:4px;">${c.recipients.map(e => `<span style="background:#f1f5f9; color:#475569; padding:2px 8px; border-radius:4px; font-size:11px;">${e}</span>`).join('')}</div></div>` 
                        : '';
                    const errorsList = (c.error_details || []).length > 0
                        ? `<div style="margin-top:6px;"><span style="color:#dc2626; font-size:11px;">Errores:</span><div style="margin-top:3px;">${c.error_details.map(e => `<div style="font-size:11px; color:#dc2626; padding:2px 0;">• ${e}</div>`).join('')}</div></div>`
                        : '';

                    return `
                    <div style="background:#fff; border:1px solid #e5e7eb; border-left:4px solid ${borderColor}; border-radius:8px; overflow:hidden;">
                        <div style="padding:16px 20px; display:flex; justify-content:space-between; align-items:center; cursor:pointer;" onclick="const d=document.getElementById('${cardId}'); const a=this.querySelector('.expand-arrow'); if(d.style.display==='none'){d.style.display='block'; a.style.transform='rotate(180deg)';} else {d.style.display='none'; a.style.transform='rotate(0)';}">
                            <div style="display:flex; align-items:center; gap:14px;">
                                <div style="width:42px; height:42px; border-radius:10px; background:${hasErrors ? '#fef9c3' : '#f0fdf4'}; display:flex; align-items:center; justify-content:center; font-size:20px;">${hasErrors ? '⚠️' : '✉️'}</div>
                                <div>
                                    <div style="font-weight:700; font-size:14px; color:#1e293b;">Campaña #${campaigns.length - i}</div>
                                    <div style="font-size:12px; color:#64748b; margin-top:2px;">
                                        <span style="color:#16a34a; font-weight:600;">✅ ${c.sent_count} enviados</span>
                                        ${c.opens > 0 ? `<span style="margin-left:8px; color:#2563eb; font-weight:600;">📬 ${c.opens} abiertos${c.sent_count > 0 ? ` (${Math.round(c.opens / c.sent_count * 100)}%)` : ''}</span>` : ''}
                                        ${c.clicks > 0 ? `<span style="margin-left:8px; color:#9333ea; font-weight:600;">🔗 ${c.clicks} clicks</span>` : ''}
                                        ${hasErrors ? `<span style="margin-left:8px; color:#dc2626; font-weight:600;">❌ ${c.errors} errores</span>` : ''}
                                        ${c.promo_code ? `<span style="margin-left:8px; background:#f3f0ff; color:#7c3aed; padding:1px 6px; border-radius:8px; font-size:10px; font-weight:600; border:1px solid #e0d5ff;">${c.promo_code}</span>` : ''}
                                    </div>
                                </div>
                            </div>
                            <div style="display:flex; align-items:center; gap:10px;">
                                <div style="text-align:right;">
                                    <div style="font-size:12px; color:#94a3b8;">${timeAgo(c.created_at)}</div>
                                    <div style="font-size:11px; color:#cbd5e1;">${formatDate(c.created_at)}</div>
                                </div>
                                <span class="expand-arrow" style="color:#94a3b8; font-size:12px; transition:transform 0.2s;">▼</span>
                            </div>
                        </div>
                        <div id="${cardId}" style="display:none; padding:0 20px 16px 76px; border-top:1px solid #f0f0f0;">
                            <div style="padding-top:12px;">
                                ${c.opens > 0 || c.clicks > 0 ? `<div style="display:flex; gap:16px; margin-bottom:8px; padding:8px 12px; background:#f8fafc; border-radius:6px; border:1px solid #e2e8f0;"><div style="text-align:center;"><div style="font-size:18px; font-weight:700; color:#2563eb;">${c.opens}</div><div style="font-size:10px; color:#94a3b8; text-transform:uppercase;">Aperturas</div></div><div style="text-align:center;"><div style="font-size:18px; font-weight:700; color:#16a34a;">${c.sent_count > 0 ? Math.round(c.opens / c.sent_count * 100) : 0}%</div><div style="font-size:10px; color:#94a3b8; text-transform:uppercase;">Tasa apertura</div></div><div style="text-align:center;"><div style="font-size:18px; font-weight:700; color:#9333ea;">${c.clicks}</div><div style="font-size:10px; color:#94a3b8; text-transform:uppercase;">Clicks</div></div></div>` : ''}
                                ${templateLine}${promoLine}${recipientsList}${errorsList}
                            </div>
                        </div>
                    </div>`;
                }).join('');
            } catch (err) {
                console.error(err);
                list.innerHTML = `<div style="text-align:center; padding:40px; color:#dc2626;">Error al cargar el historial.</div>`;
            }
        },

        async handleMarketingSubmit(e) {
            e.preventDefault();
            
            if (!this.marketingForm.reportValidity()) {
                return;
            }

            const checks = document.querySelectorAll('.m-guest-check');
            const guestIds = Array.from(checks).filter(c => c.checked).map(c => parseInt(c.value));
            
            if (guestIds.length === 0) {
                window.ArtechiaDiscounts.showToast('Debes seleccionar al menos un destinatario.', 'error');
                return;
            }

            const tId = document.getElementById('m-field-template').value;
            if (!tId) {
                window.ArtechiaDiscounts.showToast('Debes seleccionar una Plantilla Base.', 'error');
                return;
            }

            const subject = document.getElementById('m-field-subject').value;
            const bodyHtml = document.getElementById('m-field-body').value;
            if (!subject.trim() || !bodyHtml.trim()) {
                window.ArtechiaDiscounts.showToast('El asunto y contenido del email son requeridos. Edita el contenido para agregarlos.', 'error');
                return;
            }

            const promoSelect = document.getElementById('m-field-promo');
            const selectedPromo = promoSelect.options[promoSelect.selectedIndex];
            const discountType = selectedPromo ? selectedPromo.dataset.type : null; // 'promo' or 'coupon'
            
            // Check if selected template is the promotional template
            const selectedTemplate = this.templates.find(x => x.id == tId);
            const isPromoTemplate = selectedTemplate && selectedTemplate.event_type === 'marketing_promo';
            
            if (isPromoTemplate && !selectedPromo.value) {
                const errorMsg = 'Debes seleccionar un Descuento Vinculado para poder usar la plantilla de "Promotional Marketing".';
                if (window.artechiaPMS && window.artechiaPMS.toast) {
                    window.artechiaPMS.toast.show(errorMsg, 'error');
                } else {
                    window.ArtechiaDiscounts.showToast(errorMsg, 'error');
                }
                return;
            }
            
            if (!confirm(`¿Seguro que deseas enviar esta campaña a ${guestIds.length} huéspedes?`)) return;
            
            const btn = document.getElementById('m-send-btn');
            btn.innerHTML = '<span class="dashicons dashicons-update spin" style="margin-top:2px;"></span> Enviando...';
            btn.disabled = true;

            // Build the body_html — dynamically swap promo-card based on discount type
            let finalBodyHtml = document.getElementById('m-field-body').value;
            if (discountType === 'promo' && finalBodyHtml.includes('promo-card')) {
                // Use DOM to swap (same approach as preview — no fragile regex)
                const tempDiv = document.createElement('div');
                tempDiv.innerHTML = finalBodyHtml;
                const card = tempDiv.querySelector('.promo-card');
                if (card) {
                    card.innerHTML = '<p style="margin:0;font-weight:600;color:#1e3a8a;">🎉 ¡Promoción Especial!</p><p style="margin:8px 0 0;font-size:16px;font-weight:700;color:#1d4ed8;">{promo_description}</p><p style="margin:8px 0 0;font-size:13px;color:#1e40af;">Esta promoción se aplica automáticamente al reservar.</p>';
                }
                finalBodyHtml = tempDiv.innerHTML;
            }

            const payload = {
                template_id: parseInt(document.getElementById('m-field-template').value),
                property_id: parseInt(document.getElementById('m-field-property').value) || 0,
                subject: document.getElementById('m-field-subject').value,
                body_html: finalBodyHtml,
                guest_ids: guestIds,
                promo_code: discountType === 'coupon' ? selectedPromo.value : '', // Only coupons have a code
                promo_description: document.getElementById('m-field-promo-desc').value || (selectedPromo.value ? selectedPromo.dataset.desc : '')
            };

            // If user checked 'save to base template', update it first (WAIT for this if requested)
            if (document.getElementById('m-save-template-check').checked) {
                try {
                    await fetch(artechiaPMS.restUrl + 'admin/email-templates/' + payload.template_id, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': artechiaPMS.nonce },
                        body: JSON.stringify({
                            subject: payload.subject,
                            body_html: payload.body_html
                        })
                    });
                    const t = this.templates.find(x => x.id == payload.template_id);
                    if (t) {
                        t.subject = payload.subject;
                        t.body_html = payload.body_html;
                    }
                } catch (err) {
                    console.error("No se pudo guardar la plantilla base", err);
                }
            }

            // Close modal right away
            this.marketingModal.style.display = 'none';

            // Show Toast indicating it started
            window.ArtechiaDiscounts.showToast('Campaña en progreso. Los correos se están enviando...', 'info');

            // Do the fetch asynchronously, don't block the UI
            fetch(artechiaPMS.restUrl + 'admin/marketing/send', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': artechiaPMS.nonce },
                body: JSON.stringify(payload)
            }).then(res => res.json()).then(result => {
                if (result.success !== false && !result.error) {
                    window.ArtechiaDiscounts.showToast('¡Campaña enviada con éxito!', 'success');
                } else {
                    window.ArtechiaDiscounts.showToast(result.message || 'Hubo un error al enviar la campaña.', 'error');
                }
            }).catch(err => {
                window.ArtechiaDiscounts.showToast('Error de conexión al enviar la campaña.', 'error');
            });
        }
    };

    // Global helper for actions
    window.ArtechiaDiscounts = {
        showToast: (msg, type = 'info') => {
            let container = document.getElementById('artechia-global-toast-container');
            if (!container) {
                container = document.createElement('div');
                container.id = 'artechia-global-toast-container';
                document.body.appendChild(container);
            }
            const toast = document.createElement('div');
            toast.className = `artechia-toast artechia-toast--${type}`;
            toast.innerText = msg;
            container.appendChild(toast);
            
            // Add .show class after a small delay to trigger the CSS transition
            setTimeout(() => toast.classList.add('show'), 10);
            
            setTimeout(() => {
                toast.classList.remove('show');
                setTimeout(() => toast.remove(), 300); // wait for out transition
            }, 4500);
        },
        edit: (origin, id) => {
            const list = origin === 'promo' ? app.promos : app.coupons;
            const item = list.find(x => x.id == id);
            if (item) app.openCouponModal({...item, _origin: origin});
        },
        delete: async (origin, id) => {
            if (!confirm('¿Seguro que deseas eliminar este descuento?')) return;
            const endpoint = origin === 'promo' ? 'promotions' : 'coupons';
            try {
                const res = await fetch(artechiaPMS.restUrl + 'admin/' + endpoint + '/' + id, {
                    method: 'DELETE',
                    headers: { 'X-WP-Nonce': artechiaPMS.nonce }
                });
                if (res.ok) app.fetchData();
            } catch (err) { console.error(err); }
        }
    };

    app.init();
});
</script>

<style>
/* Switch Toggle Styles for the state column */
.artechia-switch {
  position: relative;
  display: inline-block;
  width: 44px;
  height: 24px;
}

.artechia-switch input {
  opacity: 0;
  width: 0;
  height: 0;
}

.artechia-switch .slider {
  position: absolute;
  cursor: pointer;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background-color: #ccc;
  -webkit-transition: .4s;
  transition: .4s;
}

.artechia-switch .slider:before {
  position: absolute;
  content: "";
  height: 18px;
  width: 18px;
  left: 3px;
  bottom: 3px;
  background-color: white;
  -webkit-transition: .4s;
  transition: .4s;
}

.artechia-switch input:checked + .slider {
  background-color: #2e8b57;
}

.artechia-switch input:focus + .slider {
  box-shadow: 0 0 1px #2e8b57;
}

.artechia-switch input:checked + .slider:before {
  -webkit-transform: translateX(20px);
  -ms-transform: translateX(20px);
  transform: translateX(20px);
}

/* Rounded sliders */
.artechia-switch .slider.round {
  border-radius: 24px;
}

.artechia-switch .slider.round:before {
  border-radius: 50%;
}
</style>
