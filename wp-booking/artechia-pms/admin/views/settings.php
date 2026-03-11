<?php
/**
 * Settings page view.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Artechia\PMS\Services\Settings;
use Artechia\PMS\Logger;

// Handle save.
if ( isset( $_POST['artechia_save_settings'] ) && check_admin_referer( 'artechia_save_settings' ) ) {
    $fields = [
        'currency', 'currency_symbol', 'currency_position', 'decimal_separator',
        'thousand_separator', 'decimals', 'timezone', 'check_in_time', 'check_out_time',
        'date_format', 'tax_enabled', 'tax_name', 'tax_rate', 'tax_included',
        'tax_per_night', 'tax_per_person', 'lock_expiry_minutes', 'booking_code_prefix',
        'debug_mode', 'mercadopago_enabled', 'mercadopago_sandbox',
        'mercadopago_public_key', 'mercadopago_access_token',
        'mercadopago_access_token_test', 'mercadopago_webhook_secret',

        'mercadopago_charged_back_action', 'mercadopago_allow_unsigned_sandbox',
        'whatsapp_number', 'whatsapp_message',
        'review_email_enabled', 'review_email_delay', 'calendar_json_ld_enabled',
        // New Fields
        // New Fields
        'marketing_enabled', 'marketing_from_name', 'marketing_from_email',
        'auto_apply_promotions', 'logo_url',
        // Bank Transfer
        'enable_bank_transfer', 'bank_transfer_display_mode',
        'bank_transfer_bank', 'bank_transfer_holder',
        'bank_transfer_cbu', 'bank_transfer_alias', 'bank_transfer_cuit',
        // New refinements
        'checkout_terms_conditions_type',
        // Global booking controls
        'enable_bookings', 'booking_disabled_reason', 'booking_disabled_page', 'booking_disabled_mode',
        // MP Timeout
        'mercadopago_timeout_minutes',
        // Closures
        'closure_mode', 'closure_reason', 'closure_page',
        // iCal
        'ical_sync_frequency',
        // Pending blocks
        'pending_blocks_unit'
    ];

    // Custom save for closure_dates (multiple date ranges)
    if ( ! empty( $_POST['closure_ranges'] ) && is_array( $_POST['closure_ranges'] ) ) {
        $ranges = [];
        foreach ( $_POST['closure_ranges'] as $range ) {
            $from = sanitize_text_field( $range['date_from'] ?? '' );
            $to   = sanitize_text_field( $range['date_to'] ?? '' );
            if ( $from && $to ) {
                $ranges[] = $from . ':' . $to;
            }
        }
        Settings::set( 'closure_dates', implode( ', ', $ranges ) );
    } else {
        Settings::set( 'closure_dates', '' );
    }

    foreach ( $fields as $field ) {
        if ( isset( $_POST[ $field ] ) ) {
            $value = wp_unslash( $_POST[ $field ] );
            if ( $field === 'checkout_terms_conditions' ) {
                Settings::set( $field, wp_kses_post( $value ) );
            } else {
                Settings::set( $field, sanitize_text_field( $value ) );
            }
        } else {
            // Checkboxes: if not set, save as 0.
            if ( in_array( $field, [ 
                'tax_enabled', 'tax_included', 'debug_mode', 'mercadopago_enabled', 
                'mercadopago_sandbox', 'review_email_enabled', 'calendar_json_ld_enabled', 
                'mercadopago_allow_unsigned_sandbox', 'marketing_enabled', 'auto_apply_promotions',
                'enable_bank_transfer', 'enable_coupons', 'enable_special_requests',
                'enable_bookings', 'pending_blocks_unit'
            ], true ) ) {
                Settings::set( $field, '0' );
            }
        }
    }

    Logger::info( 'settings.updated', 'Settings updated by user' );
    echo '<div class="notice notice-success"><p>' . esc_html__( 'Ajustes guardados.', 'artechia-pms' ) . '</p></div>';
    Settings::flush_cache();
}

// Also sync the WP debug_mode option.
update_option( 'artechia_pms_debug_mode', Settings::get( 'debug_mode', '0' ) );

$s = Settings::all();
$active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'general';
?>

<div class="wrap artechia-wrap">
    <h1><?php esc_html_e( 'Ajustes — Artechia PMS', 'artechia-pms' ); ?></h1>

    <h2 class="nav-tab-wrapper">
        <a href="?page=artechia-settings&tab=general" class="nav-tab <?php echo $active_tab === 'general' ? 'nav-tab-active' : ''; ?>">General</a>
        <a href="?page=artechia-settings&tab=bookings" class="nav-tab <?php echo $active_tab === 'bookings' ? 'nav-tab-active' : ''; ?>">Reservas</a>
        <a href="?page=artechia-settings&tab=taxes" class="nav-tab <?php echo $active_tab === 'taxes' ? 'nav-tab-active' : ''; ?>">Impuestos</a>
        <a href="?page=artechia-settings&tab=payments" class="nav-tab <?php echo $active_tab === 'payments' ? 'nav-tab-active' : ''; ?>">Pagos</a>
        <a href="?page=artechia-settings&tab=marketing" class="nav-tab <?php echo $active_tab === 'marketing' ? 'nav-tab-active' : ''; ?>">Marketing</a>
        <a href="?page=artechia-settings&tab=emails" class="nav-tab <?php echo $active_tab === 'emails' ? 'nav-tab-active' : ''; ?>">Correos</a>
        <a href="?page=artechia-settings&tab=dev" class="nav-tab <?php echo $active_tab === 'dev' ? 'nav-tab-active' : ''; ?>">Desarrollo</a>
    </h2>

    <form method="post" class="artechia-form">
        <?php wp_nonce_field( 'artechia_save_settings' ); ?>

        <!-- TAB: GENERAL -->
        <div class="tab-content" id="tab-general" style="display: <?php echo $active_tab === 'general' ? 'block' : 'none'; ?>;">
            <!-- Currency & Format -->
            <div class="artechia-panel">
                <h2><?php esc_html_e( 'Moneda y Formato', 'artechia-pms' ); ?></h2>
                <table class="form-table">
                    <tr>
                        <th><label for="currency"><?php esc_html_e( 'Código de moneda', 'artechia-pms' ); ?></label></th>
                        <td><input type="text" id="currency" name="currency" value="<?php echo esc_attr( $s['currency'] ?? 'ARS' ); ?>" class="regular-text" maxlength="3"></td>
                    </tr>
                    <tr>
                        <th><label for="currency_symbol"><?php esc_html_e( 'Símbolo', 'artechia-pms' ); ?></label></th>
                        <td><input type="text" id="currency_symbol" name="currency_symbol" value="<?php echo esc_attr( $s['currency_symbol'] ?? '$' ); ?>" class="small-text"></td>
                    </tr>
                    <tr>
                        <th><label for="currency_position"><?php esc_html_e( 'Posición del símbolo', 'artechia-pms' ); ?></label></th>
                        <td>
                            <select id="currency_position" name="currency_position">
                                <option value="before" <?php selected( $s['currency_position'] ?? 'before', 'before' ); ?>><?php esc_html_e( 'Antes ($100)', 'artechia-pms' ); ?></option>
                                <option value="after" <?php selected( $s['currency_position'] ?? 'before', 'after' ); ?>><?php esc_html_e( 'Después (100$)', 'artechia-pms' ); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="decimal_separator"><?php esc_html_e( 'Separador decimal', 'artechia-pms' ); ?></label></th>
                        <td><input type="text" id="decimal_separator" name="decimal_separator" value="<?php echo esc_attr( $s['decimal_separator'] ?? ',' ); ?>" class="small-text" maxlength="1"></td>
                    </tr>
                    <tr>
                        <th><label for="thousand_separator"><?php esc_html_e( 'Separador de miles', 'artechia-pms' ); ?></label></th>
                        <td><input type="text" id="thousand_separator" name="thousand_separator" value="<?php echo esc_attr( $s['thousand_separator'] ?? '.' ); ?>" class="small-text" maxlength="1"></td>
                    </tr>
                    <tr>
                        <th><label for="decimals"><?php esc_html_e( 'Decimales', 'artechia-pms' ); ?></label></th>
                        <td><input type="number" id="decimals" name="decimals" value="<?php echo esc_attr( $s['decimals'] ?? '2' ); ?>" class="small-text" min="0" max="4"></td>
                    </tr>
                    <tr>
                        <th><label for="date_format"><?php esc_html_e( 'Formato de fecha', 'artechia-pms' ); ?></label></th>
                        <td><input type="text" id="date_format" name="date_format" value="<?php echo esc_attr( $s['date_format'] ?? 'd/m/Y' ); ?>" class="regular-text">
                            <p class="description"><?php esc_html_e( 'Formato PHP: d/m/Y, Y-m-d, m/d/Y', 'artechia-pms' ); ?></p>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Timezone & Hours -->
            <div class="artechia-panel">
                <h2><?php esc_html_e( 'Horarios', 'artechia-pms' ); ?></h2>
                <table class="form-table">
                    <tr>
                        <th><label for="timezone"><?php esc_html_e( 'Zona horaria', 'artechia-pms' ); ?></label></th>
                        <td>
                            <select id="timezone" name="timezone">
                                <?php
                                $current_tz = $s['timezone'] ?? 'America/Argentina/Buenos_Aires';
                                echo wp_timezone_choice( $current_tz ); 
                                ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="check_in_time"><?php esc_html_e( 'Hora de check-in', 'artechia-pms' ); ?></label></th>
                        <td><input type="time" id="check_in_time" name="check_in_time" value="<?php echo esc_attr( $s['check_in_time'] ?? '14:00' ); ?>"></td>
                    </tr>
                    <tr>
                        <th><label for="check_out_time"><?php esc_html_e( 'Hora de check-out', 'artechia-pms' ); ?></label></th>
                        <td><input type="time" id="check_out_time" name="check_out_time" value="<?php echo esc_attr( $s['check_out_time'] ?? '10:00' ); ?>"></td>
                    </tr>
                </table>
            </div>

            <!-- Shortcode Guide -->
            <div class="artechia-panel">
                <h2><?php esc_html_e( 'Guía de Shortcodes', 'artechia-pms' ); ?></h2>
                <p class="description">
                    <?php esc_html_e( 'Copiá y pegá estos shortcodes en las páginas de tu sitio. El plugin detectará automáticamente dónde están ubicados para gestionar las redirecciones.', 'artechia-pms' ); ?>
                </p>
                <table class="widefat striped" style="margin-top: 20px;">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Shortcode', 'artechia-pms' ); ?></th>
                            <th><?php esc_html_e( 'Descripción', 'artechia-pms' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><code>[artechia_search]</code></td>
                            <td><?php esc_html_e( 'Formulario de búsqueda inicial (Check-in, Check-out, Huéspedes).', 'artechia-pms' ); ?></td>
                        </tr>
                        <tr>
                            <td><code>[artechia_results]</code></td>
                            <td><?php esc_html_e( 'Muestra la lista de habitaciones disponibles y precios.', 'artechia-pms' ); ?></td>
                        </tr>
                        <tr>
                            <td><code>[artechia_checkout]</code></td>
                            <td><?php esc_html_e( 'Formulario para ingresar datos del huésped y procesar el pago.', 'artechia-pms' ); ?></td>
                        </tr>
                        <tr>
                            <td><code>[artechia_confirmation]</code></td>
                            <td><?php esc_html_e( 'Página de éxito que se muestra luego de completar una reserva.', 'artechia-pms' ); ?></td>
                        </tr>
                        <tr>
                            <td><code>[artechia_my_booking]</code></td>
                            <td><?php esc_html_e( 'Portal "Mi Reserva" donde el huésped ve sus detalles y paga saldos.', 'artechia-pms' ); ?></td>
                        </tr>
                        <tr>
                            <td><code>[artechia_find_booking]</code></td>
                            <td><?php esc_html_e( 'Buscador de reservas por código y email para acceder al portal.', 'artechia-pms' ); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- TAB: BOOKINGS -->
        <div class="tab-content" id="tab-bookings" style="display: <?php echo $active_tab === 'bookings' ? 'block' : 'none'; ?>;">
            <div class="artechia-panel">
                <h2><?php esc_html_e( 'Configuración de Reservas', 'artechia-pms' ); ?></h2>
                <table class="form-table">
                    <tr>
                        <th><label for="lock_expiry_minutes"><?php esc_html_e( 'Expiración de locks (min)', 'artechia-pms' ); ?></label></th>
                        <td><input type="number" id="lock_expiry_minutes" name="lock_expiry_minutes" value="<?php echo esc_attr( $s['lock_expiry_minutes'] ?? '15' ); ?>" class="small-text" min="5" max="60">
                            <p class="description"><?php esc_html_e( 'Tiempo que se reserva la disponibilidad durante el checkout.', 'artechia-pms' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="booking_code_prefix"><?php esc_html_e( 'Prefijo de código', 'artechia-pms' ); ?></label></th>
                        <td><input type="text" id="booking_code_prefix" name="booking_code_prefix" value="<?php echo esc_attr( $s['booking_code_prefix'] ?? 'ART' ); ?>" class="small-text" maxlength="5"></td>
                    </tr>
                    <tr>
                        <th><label for="ical_sync_frequency"><?php esc_html_e( 'Sincronizar iCal cada', 'artechia-pms' ); ?></label></th>
                        <td>
                            <input type="number" id="ical_sync_frequency" name="ical_sync_frequency" value="<?php echo esc_attr( $s['ical_sync_frequency'] ?? '15' ); ?>" class="small-text" min="5" max="1440"> <?php esc_html_e( 'minutos', 'artechia-pms' ); ?>
                            <p class="description"><?php esc_html_e( 'Frecuencia con la que el sistema buscará actualizaciones en los calendarios importados.', 'artechia-pms' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="pending_blocks_unit"><?php esc_html_e( 'Pendientes bloquean unidad', 'artechia-pms' ); ?></label></th>
                        <td>
                            <label>
                                <input type="checkbox" id="pending_blocks_unit" name="pending_blocks_unit" value="1" <?php checked( $s['pending_blocks_unit'] ?? '0', '1' ); ?>>
                                <?php esc_html_e( 'Las reservas con estado Pendiente (sin pago) bloquean la disponibilidad de la unidad.', 'artechia-pms' ); ?>
                            </label>
                            <p class="description"><?php esc_html_e( 'Si se desmarca, las reservas pendientes sin pago no ocuparán unidades en el calendario ni en la búsqueda pública.', 'artechia-pms' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="enable_bookings"><?php esc_html_e( 'Habilitar reservas', 'artechia-pms' ); ?></label></th>
                        <td>
                            <label>
                                <input type="checkbox" id="enable_bookings" name="enable_bookings" value="1" <?php checked( $s['enable_bookings'] ?? '1', '1' ); ?>>
                                <?php esc_html_e( 'Si se desmarca, el motor de reservas dejará de aceptar nuevas reservas.', 'artechia-pms' ); ?>
                            </label>
                        </td>
                    </tr>
                    <tr class="booking-disabled-reason-row" style="<?php echo ( $s['enable_bookings'] ?? '1' ) === '0' ? '' : 'display:none;'; ?>">
                        <th><label><?php esc_html_e( 'Al deshabilitar...', 'artechia-pms' ); ?></label></th>
                        <td>
                            <div style="margin-bottom: 10px;">
                                <label style="margin-right: 15px;">
                                    <input type="radio" name="booking_disabled_mode" value="simple" <?php checked( $s['booking_disabled_mode'] ?? 'simple', 'simple' ); ?>>
                                    <?php esc_html_e( 'Mostrar motivo (simple)', 'artechia-pms' ); ?>
                                </label>
                                <label>
                                    <input type="radio" name="booking_disabled_mode" value="complete" <?php checked( $s['booking_disabled_mode'] ?? 'simple', 'complete' ); ?>>
                                    <?php esc_html_e( 'Redirigir a página (completo)', 'artechia-pms' ); ?>
                                </label>
                            </div>
                            
                            <div id="booking-disabled-reason-simple" style="<?php echo ( $s['booking_disabled_mode'] ?? 'simple' ) === 'simple' ? '' : 'display:none;'; ?>">
                                <textarea name="booking_disabled_reason" class="large-text" rows="3" placeholder="Ej: No estamos aceptando reservas por el momento..."><?php echo esc_textarea( $s['booking_disabled_reason'] ?? '' ); ?></textarea>
                            </div>
                            
                            <div id="booking-disabled-reason-complete" style="<?php echo ( $s['booking_disabled_mode'] ?? 'simple' ) === 'complete' ? '' : 'display:none;'; ?>">
                                <?php
                                wp_dropdown_pages( [
                                    'name'             => 'booking_disabled_page',
                                    'selected'         => $s['booking_disabled_page'] ?? 0,
                                    'show_option_none' => __( 'Seleccionar página...', 'artechia-pms' ),
                                ] );
                                ?>
                            </div>
                        </td>
                    </tr>
                </table>
            </div>
            <div class="artechia-panel">
                <h2><?php esc_html_e( 'Cupones y Promociones', 'artechia-pms' ); ?></h2>
                <table class="form-table">
                    <tr>
                        <th><label for="enable_coupons"><?php esc_html_e( 'Habilitar cupones', 'artechia-pms' ); ?></label></th>
                        <td>
                            <label>
                                <input type="checkbox" id="enable_coupons" name="enable_coupons" value="1" <?php checked( $s['enable_coupons'] ?? '1', '1' ); ?>>
                                <?php esc_html_e( 'Permite el uso de códigos de descuento en el checkout.', 'artechia-pms' ); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="auto_apply_promotions"><?php esc_html_e( 'Aplicación automática', 'artechia-pms' ); ?></label></th>
                        <td>
                            <label>
                                <input type="checkbox" id="auto_apply_promotions" name="auto_apply_promotions" value="1" <?php checked( $s['auto_apply_promotions'] ?? '1', '1' ); ?>>
                                <?php esc_html_e( 'Aplicar automáticamente la mejor promoción disponible en los resultados de búsqueda.', 'artechia-pms' ); ?>
                            </label>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="artechia-panel">
                <h2><?php esc_html_e( 'Disponibilidad y Cierre', 'artechia-pms' ); ?></h2>
                <table class="form-table">
                    <tr>
                        <th><label><?php esc_html_e( 'Fechas de cierre', 'artechia-pms' ); ?></label></th>
                        <td>
                            <?php
                            // Parse existing closure_dates string into array of ranges
                            $closure_raw = $s['closure_dates'] ?? '';
                            $closure_ranges = [];
                            if ( ! empty( $closure_raw ) ) {
                                $parts = array_map( 'trim', explode( ',', $closure_raw ) );
                                foreach ( $parts as $part ) {
                                    if ( strpos( $part, ':' ) !== false ) {
                                        list( $from, $to ) = explode( ':', $part );
                                        $closure_ranges[] = [ 'date_from' => trim($from), 'date_to' => trim($to) ];
                                    } elseif ( ! empty( $part ) ) {
                                        $closure_ranges[] = [ 'date_from' => trim($part), 'date_to' => trim($part) ];
                                    }
                                }
                            }
                            if ( empty( $closure_ranges ) ) {
                                $closure_ranges[] = [ 'date_from' => '', 'date_to' => '' ];
                            }
                            ?>
                            <div id="artechia-closure-ranges-container">
                                <?php foreach ( $closure_ranges as $idx => $cr ) : ?>
                                <div class="artechia-closure-row" style="margin-bottom: 5px;">
                                    <input type="date" name="closure_ranges[<?php echo $idx; ?>][date_from]" value="<?php echo esc_attr( $cr['date_from'] ); ?>" class="regular-text" style="width: auto;">
                                    <?php esc_html_e( 'hasta', 'artechia-pms' ); ?>
                                    <input type="date" name="closure_ranges[<?php echo $idx; ?>][date_to]" value="<?php echo esc_attr( $cr['date_to'] ); ?>" class="regular-text" style="width: auto;">
                                    <?php $has_date = ! empty( $cr['date_from'] ) || ! empty( $cr['date_to'] ); ?>
                                    <button type="button" class="button artechia-remove-closure-row" style="<?php echo $has_date ? 'display:inline-block;' : 'display:none;'; ?>">x</button>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <button type="button" id="artechia-add-closure-row" class="button button-small" style="margin-top: 5px;">+ <?php esc_html_e( 'Agregar período de cierre', 'artechia-pms' ); ?></button>
                            <p class="description" style="margin-top: 8px;">
                                <?php esc_html_e( 'Definí los períodos en que la propiedad no aceptará reservas.', 'artechia-pms' ); ?>
                            </p>
                            <script>
                            document.addEventListener('DOMContentLoaded', function() {
                                const addBtn = document.getElementById('artechia-add-closure-row');
                                const container = document.getElementById('artechia-closure-ranges-container');
                                if (!addBtn || !container) return;

                                function toggleClosureSettings() {
                                    const rows = container.querySelectorAll('.artechia-closure-row');
                                    let anyHasDate = false;
                                    rows.forEach(r => {
                                        const inputs = r.querySelectorAll('input[type="date"]');
                                        let rowHasDate = false;
                                        if (inputs[0] && inputs[0].value) rowHasDate = true;
                                        if (inputs[1] && inputs[1].value) rowHasDate = true;
                                        
                                        const removeBtn = r.querySelector('.artechia-remove-closure-row');
                                        if (removeBtn) {
                                            removeBtn.style.display = rowHasDate ? 'inline-block' : 'none';
                                        }

                                        if (rowHasDate) anyHasDate = true;
                                    });
                                    const settingsRow = document.getElementById('closure-settings-row');
                                    if (settingsRow) {
                                        settingsRow.style.display = anyHasDate ? '' : 'none';
                                    }
                                }

                                addBtn.addEventListener('click', function() {
                                    const rows = container.querySelectorAll('.artechia-closure-row');
                                    const nextIdx = rows.length ? parseInt(rows[rows.length-1].querySelector('input').name.match(/\[(\d+)\]/)[1]) + 1 : 0;
                                    const newRow = document.createElement('div');
                                    newRow.className = 'artechia-closure-row';
                                    newRow.style.marginBottom = '5px';
                                    newRow.innerHTML = `
                                        <input type="date" name="closure_ranges[${nextIdx}][date_from]" class="regular-text" style="width: auto;">
                                        hasta
                                        <input type="date" name="closure_ranges[${nextIdx}][date_to]" class="regular-text" style="width: auto;">
                                        <button type="button" class="button artechia-remove-closure-row" style="display:none;">x</button>
                                    `;
                                    container.appendChild(newRow);
                                    toggleClosureSettings();
                                });

                                container.addEventListener('click', function(e) {
                                    if (e.target.classList.contains('artechia-remove-closure-row')) {
                                        const row = e.target.closest('.artechia-closure-row');
                                        const totalRows = container.querySelectorAll('.artechia-closure-row').length;
                                        if (totalRows > 1) {
                                            row.remove();
                                        } else {
                                            row.querySelectorAll('input[type="date"]').forEach(inp => inp.value = '');
                                        }
                                        toggleClosureSettings();
                                    }
                                });

                                container.addEventListener('input', function(e) {
                                    if (e.target.tagName === 'INPUT' && e.target.type === 'date') {
                                        toggleClosureSettings();
                                    }
                                });

                                toggleClosureSettings();
                            });
                            </script>
                        </td>
                    </tr>
                    <tr id="closure-settings-row">
                        <th><label><?php esc_html_e( 'Al seleccionar fechas cerradas...', 'artechia-pms' ); ?></label></th>
                        <td>
                            <div style="margin-bottom: 10px;">
                                <label style="margin-right: 15px;">
                                    <input type="radio" name="closure_mode" value="simple" <?php checked( $s['closure_mode'] ?? 'simple', 'simple' ); ?>>
                                    <?php esc_html_e( 'Mostrar motivo (simple)', 'artechia-pms' ); ?>
                                </label>
                                <label>
                                    <input type="radio" name="closure_mode" value="complete" <?php checked( $s['closure_mode'] ?? 'simple', 'complete' ); ?>>
                                    <?php esc_html_e( 'Redirigir a página (completo)', 'artechia-pms' ); ?>
                                </label>
                            </div>
                            
                            <div id="closure-reason-simple" style="<?php echo ( $s['closure_mode'] ?? 'simple' ) === 'simple' ? '' : 'display:none;'; ?>">
                                <textarea name="closure_reason" class="large-text" rows="3" placeholder="Ej: Fechas cerradas por motivo..."><?php echo esc_textarea( $s['closure_reason'] ?? '' ); ?></textarea>
                            </div>
                            
                            <div id="closure-reason-complete" style="<?php echo ( $s['closure_mode'] ?? 'simple' ) === 'complete' ? '' : 'display:none;'; ?>">
                                <?php
                                wp_dropdown_pages( [
                                    'name'             => 'closure_page',
                                    'selected'         => $s['closure_page'] ?? 0,
                                    'show_option_none' => __( 'Seleccionar página...', 'artechia-pms' ),
                                ] );
                                ?>
                            </div>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="artechia-panel">
                <h2><?php esc_html_e( 'Personalización del Checkout', 'artechia-pms' ); ?></h2>
                <table class="form-table">
                    <tr>
                        <th><label for="enable_special_requests"><?php esc_html_e( 'Solicitudes especiales', 'artechia-pms' ); ?></label></th>
                        <td>
                            <label>
                                <input type="checkbox" id="enable_special_requests" name="enable_special_requests" value="1" <?php checked( $s['enable_special_requests'] ?? '1', '1' ); ?>>
                                <?php esc_html_e( 'Habilitar el campo de pedidos especiales en el checkout.', 'artechia-pms' ); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="checkout_terms_conditions"><?php esc_html_e( 'Términos y Condiciones', 'artechia-pms' ); ?></label></th>
                        <td>
                            <div style="margin-bottom: 10px;">
                                <label style="margin-right: 15px;">
                                    <input type="radio" name="checkout_terms_conditions_type" value="text" <?php checked( $s['checkout_terms_conditions_type'] ?? 'text', 'text' ); ?>>
                                    <?php esc_html_e( 'Texto plano', 'artechia-pms' ); ?>
                                </label>
                                <label>
                                    <input type="radio" name="checkout_terms_conditions_type" value="html" <?php checked( $s['checkout_terms_conditions_type'] ?? 'text', 'html' ); ?>>
                                    <?php esc_html_e( 'HTML', 'artechia-pms' ); ?>
                                </label>
                            </div>
                            <textarea id="checkout_terms_conditions" name="checkout_terms_conditions" class="large-text" rows="8"><?php echo esc_textarea( $s['checkout_terms_conditions'] ?? '' ); ?></textarea>
                            <p class="description">
                                <?php esc_html_e( 'Texto o HTML que se mostrará en el modal de términos y condiciones.', 'artechia-pms' ); ?>
                            </p>
                            <div style="margin-top: 10px;">
                                <button type="button" class="button button-secondary" id="artechia-preview-terms">
                                    <?php esc_html_e( 'Vista Previa', 'artechia-pms' ); ?>
                                </button>
                            </div>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- TAB: TAXES -->
        <div class="tab-content" id="tab-taxes" style="display: <?php echo $active_tab === 'taxes' ? 'block' : 'none'; ?>;">
            <div class="artechia-panel">
                <h2><?php esc_html_e( 'Impuestos y Tasas', 'artechia-pms' ); ?></h2>
                <table class="form-table">
                    <tr>
                        <th><label for="tax_enabled"><?php esc_html_e( 'Impuesto habilitado', 'artechia-pms' ); ?></label></th>
                        <td><input type="checkbox" id="tax_enabled" name="tax_enabled" value="1" <?php checked( $s['tax_enabled'] ?? '1', '1' ); ?>></td>
                    </tr>
                    <tr>
                        <th><label for="tax_name"><?php esc_html_e( 'Nombre del impuesto', 'artechia-pms' ); ?></label></th>
                        <td><input type="text" id="tax_name" name="tax_name" value="<?php echo esc_attr( $s['tax_name'] ?? 'IVA' ); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th><label for="tax_rate"><?php esc_html_e( 'Porcentaje (%)', 'artechia-pms' ); ?></label></th>
                        <td><input type="number" id="tax_rate" name="tax_rate" value="<?php echo esc_attr( $s['tax_rate'] ?? '21' ); ?>" class="small-text" min="0" max="100" step="0.01"></td>
                    </tr>
                    <tr>
                        <th><label for="tax_included"><?php esc_html_e( 'Precios incluyen impuesto', 'artechia-pms' ); ?></label></th>
                        <td><input type="checkbox" id="tax_included" name="tax_included" value="1" <?php checked( $s['tax_included'] ?? '1', '1' ); ?>></td>
                    </tr>
                    <tr>
                        <th><label for="tax_per_night"><?php esc_html_e( 'Tasa fija por noche', 'artechia-pms' ); ?></label></th>
                        <td><input type="number" id="tax_per_night" name="tax_per_night" value="<?php echo esc_attr( $s['tax_per_night'] ?? '0' ); ?>" class="small-text" min="0" step="0.01"></td>
                    </tr>
                    <tr>
                        <th><label for="tax_per_person"><?php esc_html_e( 'Tasa fija por persona', 'artechia-pms' ); ?></label></th>
                        <td><input type="number" id="tax_per_person" name="tax_per_person" value="<?php echo esc_attr( $s['tax_per_person'] ?? '0' ); ?>" class="small-text" min="0" step="0.01"></td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- TAB: PAYMENTS -->
        <div class="tab-content" id="tab-payments" style="display: <?php echo $active_tab === 'payments' ? 'block' : 'none'; ?>;">
             <div class="artechia-panel">
                <h2><?php esc_html_e( 'MercadoPago', 'artechia-pms' ); ?></h2>
                <table class="form-table">
                    <tr>
                        <th><label for="mercadopago_enabled"><?php esc_html_e( 'Habilitado', 'artechia-pms' ); ?></label></th>
                        <td><input type="checkbox" id="mercadopago_enabled" name="mercadopago_enabled" value="1" <?php checked( $s['mercadopago_enabled'] ?? '0', '1' ); ?>></td>
                    </tr>
                    <tr>
                        <th><label for="mercadopago_sandbox"><?php esc_html_e( 'Modo Sandbox', 'artechia-pms' ); ?></label></th>
                        <td><input type="checkbox" id="mercadopago_sandbox" name="mercadopago_sandbox" value="1" <?php checked( $s['mercadopago_sandbox'] ?? '1', '1' ); ?>></td>
                    </tr>
                    <tr>
                        <th><label for="mercadopago_public_key"><?php esc_html_e( 'Public Key', 'artechia-pms' ); ?></label></th>
                        <td><input type="text" id="mercadopago_public_key" name="mercadopago_public_key" value="<?php echo esc_attr( $s['mercadopago_public_key'] ?? '' ); ?>" class="large-text"></td>
                    </tr>
                    <tr>
                        <th><label for="mercadopago_access_token"><?php esc_html_e( 'Access Token', 'artechia-pms' ); ?></label></th>
                        <td><input type="password" id="mercadopago_access_token" name="mercadopago_access_token" value="<?php echo esc_attr( $s['mercadopago_access_token'] ?? '' ); ?>" class="large-text">
                        <p class="description"><?php esc_html_e( 'Access Token de producción', 'artechia-pms' ); ?></p></td>
                    </tr>
                    <tr>
                        <th><label for="mercadopago_access_token_test"><?php esc_html_e( 'Access Token (Test)', 'artechia-pms' ); ?></label></th>
                        <td><input type="password" id="mercadopago_access_token_test" name="mercadopago_access_token_test" value="<?php echo esc_attr( $s['mercadopago_access_token_test'] ?? '' ); ?>" class="large-text">
                        <p class="description"><?php esc_html_e( 'Se usa cuando Modo Sandbox está activo', 'artechia-pms' ); ?></p></td>
                    </tr>
                    <tr>
                        <th><label for="mercadopago_webhook_secret"><?php esc_html_e( 'Webhook Secret', 'artechia-pms' ); ?></label></th>
                        <td><input type="password" id="mercadopago_webhook_secret" name="mercadopago_webhook_secret" value="<?php echo esc_attr( $s['mercadopago_webhook_secret'] ?? '' ); ?>" class="large-text">
                        <p class="description"><?php esc_html_e( 'Secret key del webhook en el dashboard de MP', 'artechia-pms' ); ?></p></td>
                    </tr>

                    <tr>
                        <th><label for="mercadopago_charged_back_action"><?php esc_html_e( 'Contracargos', 'artechia-pms' ); ?></label></th>
                        <td>
                            <select id="mercadopago_charged_back_action" name="mercadopago_charged_back_action">
                                <option value="flag" <?php selected( $s['mercadopago_charged_back_action'] ?? 'flag', 'flag' ); ?>><?php esc_html_e( 'Solo marcar (Flag)', 'artechia-pms' ); ?></option>
                                <option value="cancel" <?php selected( $s['mercadopago_charged_back_action'] ?? 'flag', 'cancel' ); ?>><?php esc_html_e( 'Cancelar Reserva', 'artechia-pms' ); ?></option>
                            </select>
                            <p class="description"><?php esc_html_e( 'Acción al recibir un contracargo (charged_back)', 'artechia-pms' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="mercadopago_allow_unsigned_sandbox"><?php esc_html_e( 'Webhook sin firma (Sandbox)', 'artechia-pms' ); ?></label></th>
                        <td><input type="checkbox" id="mercadopago_allow_unsigned_sandbox" name="mercadopago_allow_unsigned_sandbox" value="1" <?php checked( $s['mercadopago_allow_unsigned_sandbox'] ?? '0', '1' ); ?>>
                        <p class="description"><?php esc_html_e( 'Permitir webhooks sin firma SOLO en modo Sandbox (útil para pruebas locales)', 'artechia-pms' ); ?></p></td>
                    </tr>
                    <tr>
                        <th><label for="mercadopago_timeout_minutes"><?php esc_html_e( 'Tiempo límite de pago (min)', 'artechia-pms' ); ?></label></th>
                        <td>
                            <input type="number" id="mercadopago_timeout_minutes" name="mercadopago_timeout_minutes" value="<?php echo esc_attr( $s['mercadopago_timeout_minutes'] ?? '15' ); ?>" class="small-text" min="5" max="1440">
                            <p class="description"><?php esc_html_e( 'Minutos que tiene el huésped para completar el pago antes de que la reserva se cancele automáticamente.', 'artechia-pms' ); ?></p>
                        </td>
                    </tr>
                </table>
            </div>




            <!-- Bank Transfer -->
            <div class="artechia-panel">
                <h2><?php esc_html_e( 'Transferencia Bancaria', 'artechia-pms' ); ?></h2>
                <table class="form-table">
                    <tr>
                        <th><label for="enable_bank_transfer"><?php esc_html_e( 'Habilitar', 'artechia-pms' ); ?></label></th>
                        <td><input type="checkbox" id="enable_bank_transfer" name="enable_bank_transfer" value="1" <?php checked( $s['enable_bank_transfer'] ?? '0', '1' ); ?>></td>
                    </tr>
                    <tr>
                        <th><label for="bank_transfer_display_mode"><?php esc_html_e( 'Mostrar en Mi Reserva', 'artechia-pms' ); ?></label></th>
                        <td>
                            <select id="bank_transfer_display_mode" name="bank_transfer_display_mode">
                                <option value="details" <?php selected( $s['bank_transfer_display_mode'] ?? 'details', 'details' ); ?>><?php esc_html_e( 'Mostrar datos bancarios', 'artechia-pms' ); ?></option>
                                <option value="whatsapp" <?php selected( $s['bank_transfer_display_mode'] ?? 'details', 'whatsapp' ); ?>><?php esc_html_e( 'Mostrar mensaje de WhatsApp', 'artechia-pms' ); ?></option>
                            </select>
                            <p class="description"><?php esc_html_e( 'Elegí qué mostrar al huésped cuando seleccione Transferencia Bancaria.', 'artechia-pms' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="bank_transfer_bank"><?php esc_html_e( 'Banco', 'artechia-pms' ); ?></label></th>
                        <td><input type="text" id="bank_transfer_bank" name="bank_transfer_bank" value="<?php echo esc_attr( $s['bank_transfer_bank'] ?? '' ); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th><label for="bank_transfer_holder"><?php esc_html_e( 'Titular de cuenta', 'artechia-pms' ); ?></label></th>
                        <td><input type="text" id="bank_transfer_holder" name="bank_transfer_holder" value="<?php echo esc_attr( $s['bank_transfer_holder'] ?? '' ); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th><label for="bank_transfer_cbu"><?php esc_html_e( 'CBU / CVU', 'artechia-pms' ); ?></label></th>
                        <td><input type="text" id="bank_transfer_cbu" name="bank_transfer_cbu" value="<?php echo esc_attr( $s['bank_transfer_cbu'] ?? '' ); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th><label for="bank_transfer_alias"><?php esc_html_e( 'Alias', 'artechia-pms' ); ?></label></th>
                        <td><input type="text" id="bank_transfer_alias" name="bank_transfer_alias" value="<?php echo esc_attr( $s['bank_transfer_alias'] ?? '' ); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th><label for="bank_transfer_cuit"><?php esc_html_e( 'CUIT / CUIL', 'artechia-pms' ); ?></label></th>
                        <td><input type="text" id="bank_transfer_cuit" name="bank_transfer_cuit" value="<?php echo esc_attr( $s['bank_transfer_cuit'] ?? '' ); ?>" class="regular-text"></td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- TAB: MARKETING -->
        <div class="tab-content" id="tab-marketing" style="display: <?php echo $active_tab === 'marketing' ? 'block' : 'none'; ?>;">
            <div class="artechia-panel">
                <h2><?php esc_html_e( 'Marketing', 'artechia-pms' ); ?></h2>
                <table class="form-table">
                     <tr>
                        <th><label for="marketing_enabled"><?php esc_html_e( 'Habilitar sistema', 'artechia-pms' ); ?></label></th>
                        <td>
                            <label>
                                <input type="checkbox" id="marketing_enabled" name="marketing_enabled" value="1" <?php checked( $s['marketing_enabled'] ?? '0', '1' ); ?>>
                                <?php esc_html_e( 'Permitir envío de campañas de email marketing', 'artechia-pms' ); ?>
                            </label>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="artechia-panel">
                <h2><?php esc_html_e( 'WhatsApp', 'artechia-pms' ); ?></h2>
                <table class="form-table">
                    <tr>
                        <th><label for="whatsapp_number"><?php esc_html_e( 'Número (con código país)', 'artechia-pms' ); ?></label></th>
                        <td><input type="text" id="whatsapp_number" name="whatsapp_number" value="<?php echo esc_attr( $s['whatsapp_number'] ?? '' ); ?>" class="regular-text" placeholder="5491112345678"></td>
                    </tr>
                    <tr>
                        <th><label for="whatsapp_message"><?php esc_html_e( 'Mensaje predeterminado', 'artechia-pms' ); ?></label></th>
                        <td><textarea id="whatsapp_message" name="whatsapp_message" class="large-text" rows="3"><?php echo esc_textarea( $s['whatsapp_message'] ?? '' ); ?></textarea>
                            <p class="description"><?php esc_html_e( 'Usá {booking_code} como placeholder.', 'artechia-pms' ); ?></p>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- TAB: EMAILS -->
        <div class="tab-content" id="tab-emails" style="display: <?php echo $active_tab === 'emails' ? 'block' : 'none'; ?>;">
            <div class="artechia-panel">
                <h2><?php esc_html_e( 'Identidad', 'artechia-pms' ); ?></h2>
                <table class="form-table">
                    <tr>
                        <th><label for="logo_url"><?php esc_html_e( 'Logo del Hotel', 'artechia-pms' ); ?></label></th>
                        <td>
                            <input type="text" id="logo_url" name="logo_url" value="<?php echo esc_attr( $s['logo_url'] ?? '' ); ?>" class="regular-text">
                            <button type="button" class="button" id="btn-upload-logo"><?php esc_html_e( 'Subir Logo', 'artechia-pms' ); ?></button>
                            <p class="description"><?php esc_html_e( 'Se usará en la cabecera de todos los correos.', 'artechia-pms' ); ?></p>
                            <div id="logo-preview" style="margin-top: 10px;">
                                <?php if ( ! empty( $s['logo_url'] ) ) : ?>
                                    <img src="<?php echo esc_url( $s['logo_url'] ); ?>" style="max-height: 50px;">
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="marketing_from_name"><?php esc_html_e( 'Nombre del remitente', 'artechia-pms' ); ?></label></th>
                        <td><input type="text" id="marketing_from_name" name="marketing_from_name" value="<?php echo esc_attr( $s['marketing_from_name'] ?? get_bloginfo( 'name' ) ); ?>" class="regular-text">
                        <p class="description">Por defecto: <?php echo esc_html( get_bloginfo( 'name' ) ); ?></p></td>
                    </tr>
                    <tr>
                        <th><label for="marketing_from_email"><?php esc_html_e( 'Email del remitente', 'artechia-pms' ); ?></label></th>
                        <td><input type="email" id="marketing_from_email" name="marketing_from_email" value="<?php echo esc_attr( $s['marketing_from_email'] ?? get_bloginfo( 'admin_email' ) ); ?>" class="regular-text">
                         <p class="description">Por defecto: <?php echo esc_html( get_bloginfo( 'admin_email' ) ); ?></p></td>
                    </tr>
                </table>
            </div>

            <div class="artechia-panel">
                <h2><?php esc_html_e( 'Emails Transaccionales', 'artechia-pms' ); ?></h2>
                <table class="form-table">
                    <tr>
                        <th><label for="review_email_enabled"><?php esc_html_e( 'Email de reseña', 'artechia-pms' ); ?></label></th>
                        <td>
                            <label>
                                <input type="checkbox" id="review_email_enabled" name="review_email_enabled" value="1" <?php checked( $s['review_email_enabled'] ?? '1', '1' ); ?>>
                                <?php esc_html_e( 'Enviar automáticamente después del check-out', 'artechia-pms' ); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="review_email_delay"><?php esc_html_e( 'Días de espera', 'artechia-pms' ); ?></label></th>
                        <td>
                            <input type="number" id="review_email_delay" name="review_email_delay" value="<?php echo esc_attr( $s['review_email_delay'] ?? '1' ); ?>" class="small-text" min="0" max="30">
                            <p class="description"><?php esc_html_e( 'Días a esperar después del check-out para enviar el correo (0 = inmediato).', 'artechia-pms' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="calendar_json_ld_enabled"><?php esc_html_e( 'Invitación de Calendario (.ics)', 'artechia-pms' ); ?></label></th>
                        <td>
                            <label>
                                <input type="checkbox" id="calendar_json_ld_enabled" name="calendar_json_ld_enabled" value="1" <?php checked( $s['calendar_json_ld_enabled'] ?? '1', '1' ); ?>>
                                <?php esc_html_e( 'Adjuntar archivo .ics con los detalles de la reserva en el email de confirmación.', 'artechia-pms' ); ?>
                            </label>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- TAB: DEVELOPER -->
        <div class="tab-content" id="tab-dev" style="display: <?php echo $active_tab === 'dev' ? 'block' : 'none'; ?>;">
            <div class="artechia-panel">
                <h2><?php esc_html_e( 'Desarrollo', 'artechia-pms' ); ?></h2>
                <table class="form-table">
                    <tr>
                        <th><label for="debug_mode"><?php esc_html_e( 'Modo debug', 'artechia-pms' ); ?></label></th>
                        <td><input type="checkbox" id="debug_mode" name="debug_mode" value="1" <?php checked( $s['debug_mode'] ?? '0', '1' ); ?>>
                            <p class="description"><?php esc_html_e( 'Habilita logging detallado en error_log y en la tabla de auditoría.', 'artechia-pms' ); ?></p>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="artechia-panel">
                <h2><?php esc_html_e( 'Datos de Prueba', 'artechia-pms' ); ?></h2>
                <p class="description"><?php esc_html_e( 'Genera una propiedad de ejemplo con tipos de habitación y unidades para probar el sistema.', 'artechia-pms' ); ?></p>
                <button type="button" id="btn-generate-demo" class="button button-secondary">
                    <?php esc_html_e( 'Generar Propiedad de Ejemplo', 'artechia-pms' ); ?>
                </button>
                <span id="demo-status" style="margin-left: 10px;"></span>
            </div>
        </div>

        <?php submit_button( __( 'Guardar Ajustes', 'artechia-pms' ), 'primary', 'artechia_save_settings' ); ?>

    <!-- Preview Modal for Admin (Styled to match frontend) -->
    <style>
        #artechia-admin-modal.artechia-modal-overlay {
            --artechia-primary: #2563eb;
            --artechia-primary-dark: #1d4ed8;
            --artechia-text: #1e293b;
            --artechia-text-muted: #64748b;
            --artechia-border: #e2e8f0;
            --artechia-radius-lg: 12px;
            --artechia-font: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            
            position: fixed; top: 0; left: 0; width: 100%; height: 100%; 
            background: rgba(0,0,0,0.6); backdrop-filter: blur(4px);
            z-index: 100000; align-items: center; justify-content: center;
            font-family: var(--artechia-font);
        }
        #artechia-admin-modal .artechia-modal-content {
            background: white; width: 90%; max-width: 600px; max-height: 80vh;
            border-radius: var(--artechia-radius-lg); display: flex; flex-direction: column;
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);
            border: none;
        }
        #artechia-admin-modal .artechia-modal-header {
            padding: 20px 24px; border-bottom: 1px solid var(--artechia-border);
            display: flex; justify-content: space-between; align-items: center;
        }
        #artechia-admin-modal .artechia-modal-header h3 {
            margin: 0; font-size: 18px; font-weight: 700; color: var(--artechia-text);
        }
        #artechia-admin-modal .artechia-modal-body {
            padding: 24px; overflow-y: auto; font-size: 14px; line-height: 1.6;
            color: var(--artechia-text);
        }
        #artechia-admin-modal .artechia-modal-body h3 { font-size: 24px; margin: 24px 0 12px; font-weight: 700; }
        #artechia-admin-modal .artechia-modal-body p { margin-bottom: 16px; }
        #artechia-admin-modal .artechia-modal-body > *:first-child,
        #artechia-admin-modal .artechia-terms-full-text > *:first-child {
            margin-top: 0 !important;
        }
        #artechia-admin-modal .artechia-modal-footer {
            padding: 16px 24px; border-top: 1px solid var(--artechia-border); text-align: right;
        }
        #artechia-admin-modal .artechia-btn-preview {
            background: var(--artechia-primary); color: white; border: none;
            padding: 12px 24px; border-radius: 8px; font-weight: 700; cursor: pointer;
            text-transform: uppercase; letter-spacing: 0.5px; transition: background 0.2s;
        }
        #artechia-admin-modal .artechia-btn-preview:hover { background: var(--artechia-primary-dark); }
    </style>

    <div id="artechia-admin-modal" class="artechia-modal-overlay" style="display:none;">
        <div class="artechia-modal-content">
            <div class="artechia-modal-header">
                <h3><?php esc_html_e( 'Términos y Condiciones', 'artechia-pms' ); ?></h3>
            </div>
            <div class="artechia-modal-body">
                <div class="artechia-terms-full-text" id="artechia-admin-modal-body">
                    <!-- Content goes here -->
                </div>
            </div>
            <div class="artechia-modal-footer">
                <button type="button" class="artechia-btn-preview" onclick="document.getElementById('artechia-admin-modal').style.display='none'"><?php esc_html_e( 'Cerrar', 'artechia-pms' ); ?></button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Generate Demo Data
    const btn = document.getElementById('btn-generate-demo');
    const status = document.getElementById('demo-status');

    if (btn) {
        btn.onclick = function() {
            if (!confirm('¿Generar datos de prueba?')) return;
            
            btn.disabled = true;
            status.textContent = 'Generando...';

            fetch(artechiaPMS.restUrl + 'admin/setup/demo-data', {
                method: 'POST',
                headers: { 'X-WP-Nonce': artechiaPMS.nonce }
            })
            .then(r => r.json())
            .then(res => {
                btn.disabled = false;
                status.textContent = res.message || 'Completado';
                if (res.property_id) {
                    status.style.color = 'green';
                    if (window.artechiaPMS && window.artechiaPMS.toast) {
                        window.artechiaPMS.toast.show(res.message || 'Datos de prueba generados exitosamente.', 'success');
                    }
                }
            })
            .catch(err => {
                btn.disabled = false;
                status.textContent = 'Error';
                status.style.color = 'red';
                console.error(err);
                if (window.artechiaPMS && window.artechiaPMS.toast) {
                    window.artechiaPMS.toast.show('Error al generar datos de prueba: ' + err.message, 'error');
                }
            });
        };
    }
    // Logo Uploader
    const uploadBtn = document.getElementById('btn-upload-logo');
    if (uploadBtn) {
        let frame;
        uploadBtn.onclick = function(e) {
            e.preventDefault();
            if (frame) {
                frame.open();
                return;
            }
            frame = wp.media({
                title: 'Seleccionar Logo',
                button: { text: 'Usar este logo' },
                multiple: false
            });
            frame.on('select', function() {
                const attachment = frame.state().get('selection').first().toJSON();
                document.getElementById('logo_url').value = attachment.url;
                const preview = document.getElementById('logo-preview');
                preview.innerHTML = '<img src="' + attachment.url + '" style="max-height: 50px;">';
            });
            frame.open();
        };
    }

    // Let's stick to the server-side tab handling for now as it's robust.

    // T&C Preview
    const previewBtn = document.getElementById('artechia-preview-terms');
    const previewModal = document.getElementById('artechia-admin-modal');
    const previewBody = document.getElementById('artechia-admin-modal-body');

    if (previewBtn && previewModal && previewBody) {
        previewBtn.onclick = function() {
            const content = document.getElementById('checkout_terms_conditions').value;
            const type = document.querySelector('input[name="checkout_terms_conditions_type"]:checked').value;

            if (type === 'html') {
                previewBody.innerHTML = content;
            } else {
                previewBody.style.whiteSpace = 'pre-wrap';
                previewBody.textContent = content;
            }
            previewModal.style.display = 'flex';
        };
    }

    // Toggle Booking Disabled Reason rows
    const enableBookings = document.getElementById('enable_bookings');
    const disabledReasonRow = document.querySelector('.booking-disabled-reason-row');
    const disabledModeRadios = document.querySelectorAll('input[name="booking_disabled_mode"]');
    // Bookings toggle
    const bmodeRadios = document.querySelectorAll('input[name="booking_disabled_mode"]');
    if (enableBookings) {
        enableBookings.addEventListener('change', function() {
            document.querySelector('.booking-disabled-reason-row').style.display = this.checked ? 'none' : '';
        });
    }
    bmodeRadios.forEach(r => r.addEventListener('change', function() {
        document.getElementById('booking-disabled-reason-simple').style.display = this.value === 'simple' ? '' : 'none';
        document.getElementById('booking-disabled-reason-complete').style.display = this.value === 'complete' ? '' : 'none';
    }));

    // Closure mode toggle
    const closureModeRadios = document.querySelectorAll('input[name="closure_mode"]');
    closureModeRadios.forEach(r => r.addEventListener('change', function() {
        document.getElementById('closure-reason-simple').style.display = this.value === 'simple' ? '' : 'none';
        document.getElementById('closure-reason-complete').style.display = this.value === 'complete' ? '' : 'none';
    }));
});
</script>
