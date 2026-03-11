<?php
/**
 * Admin View: Reservations
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<div class="wrap artechia-reservations-wrap">
    <h1 class="wp-heading-inline"><?php esc_html_e( 'Reservas', 'artechia-pms' ); ?></h1>
    <button type="button" id="btn-new-booking" class="page-title-action"><?php esc_html_e( 'Nueva Reserva', 'artechia-pms' ); ?></button>
    <hr class="wp-header-end">

    <!-- Row visual styles -->
    <style>
        @keyframes row-flash {
            0%   { background-color: #fef9c3; }
            100% { background-color: transparent; }
        }
        .booking-row-flash {
            animation: row-flash 2s ease-out;
        }
        #bookings-table-body tr[data-status] {
            border-left: 4px solid transparent;
        }
        #bookings-table-body tr[data-status="confirmed"] {
            border-left-color: #16a34a;
        }
        #bookings-table-body tr[data-status="pending"] {
            border-left-color: #f59e0b;
        }
        #bookings-table-body tr[data-status="cancelled"] {
            border-left-color: #dc2626;
        }
        #bookings-table-body tr[data-status="checked_in"] {
            border-left-color: #3b82f6;
        }
        #bookings-table-body tr[data-status="checked_out"] {
            border-left-color: #94a3b8;
        }
        #bookings-table-body tr[data-status="hold"] {
            border-left-color: #eab308;
            background-color: #fff9e6;
        }
        #bookings-table-body tr[data-status="noshow"] {
            border-left-color: #6b7280;
        }
        body.artechia-modal-open {
            overflow: hidden !important;
        }
    </style>
    <script>
    // Lock body scroll when any modal is visible
    document.addEventListener('DOMContentLoaded', function() {
        const modals = document.querySelectorAll('[id$="-modal"]');
        const observer = new MutationObserver(function() {
            const anyOpen = Array.from(modals).some(m => m.style.display === 'flex');
            document.body.classList.toggle('artechia-modal-open', anyOpen);
        });
        modals.forEach(m => observer.observe(m, { attributes: true, attributeFilter: ['style'] }));

        // Close overbooking popovers on click outside
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.ob-badge') && !e.target.closest('.ob-popover')) {
                document.querySelectorAll('.ob-popover').forEach(p => p.style.display = 'none');
            }
        });
    });
    </script>

    <!-- Filters -->
    <div class="artechia-panel" style="margin-bottom:16px; padding:12px 16px;">
        <div style="display:flex; flex-wrap:wrap; gap:8px; align-items:center;">
            <div style="position:relative; flex:1; min-width:200px; max-width:280px;">
                <span style="position:absolute; left:10px; top:50%; transform:translateY(-50%); color:#94a3b8; font-size:14px;">🔍</span>
                <input type="search" id="filter-search" placeholder="<?php esc_attr_e( 'Buscar (código, nombre, email...)', 'artechia-pms' ); ?>" style="padding-left:32px; width:100%; height:36px; border-radius:6px;">
            </div>

            <select id="filter-property" style="height:36px; border-radius:6px;">
                <option value=""><?php esc_html_e( 'Todas las propiedades', 'artechia-pms' ); ?></option>
            </select>

            <select id="filter-status" style="height:36px; border-radius:6px;">
                <option value=""><?php esc_html_e( 'Todos los estados', 'artechia-pms' ); ?></option>
                <option value="pending"><?php esc_html_e( 'Pendiente', 'artechia-pms' ); ?></option>
                <option value="confirmed"><?php esc_html_e( 'Confirmada', 'artechia-pms' ); ?></option>
                <option value="cancelled"><?php esc_html_e( 'Cancelada', 'artechia-pms' ); ?></option>
                <option value="checked_in"><?php esc_html_e( 'In-House', 'artechia-pms' ); ?></option>
                <option value="checked_out"><?php esc_html_e( 'Finalizada', 'artechia-pms' ); ?></option>
            </select>

            <select id="filter-source" style="height:36px; border-radius:6px;">
                <option value=""><?php esc_html_e( 'Todas las fuentes', 'artechia-pms' ); ?></option>
                <option value="web"><?php esc_html_e( 'Web', 'artechia-pms' ); ?></option>
                <option value="admin"><?php esc_html_e( 'Admin (Manual)', 'artechia-pms' ); ?></option>
                <option value="ical"><?php esc_html_e( 'iCal / OTA', 'artechia-pms' ); ?></option>
            </select>

            <select id="filter-payment" style="height:36px; border-radius:6px;">
                <option value=""><?php esc_html_e( 'Todos pagos', 'artechia-pms' ); ?></option>
                <option value="unpaid"><?php esc_html_e( 'Sin pagar', 'artechia-pms' ); ?></option>
                <option value="deposit_paid"><?php esc_html_e( 'Seña pagada', 'artechia-pms' ); ?></option>
                <option value="paid"><?php esc_html_e( 'Pagado total', 'artechia-pms' ); ?></option>
            </select>

            <input type="date" id="filter-date-from" style="height:36px; border-radius:6px;">
            <input type="date" id="filter-date-to" style="height:36px; border-radius:6px;">

            <button type="button" class="button button-primary" id="btn-filter" style="height:36px;"><?php esc_html_e( 'Filtrar', 'artechia-pms' ); ?></button>
            <button type="button" class="button" id="btn-reset" style="height:36px;"><?php esc_html_e( 'Reset', 'artechia-pms' ); ?></button>
        </div>
    </div>

    <!-- Table -->
    <div style="width: 100%; overflow-x: auto;">
        <table class="wp-list-table widefat striped table-view-list posts" style="table-layout: auto; width: 100%;">
            <thead>
                <tr>
                    <th style="white-space: nowrap; width: 120px;"><?php esc_html_e( 'Código', 'artechia-pms' ); ?></th>
                    <th style="min-width: 150px;"><?php esc_html_e( 'Huésped', 'artechia-pms' ); ?></th>
                    <th style="white-space: nowrap; width: 130px;"><?php esc_html_e( 'Fechas', 'artechia-pms' ); ?></th>
                    <th style="white-space: nowrap; width: 120px;"><?php esc_html_e( 'Unidad', 'artechia-pms' ); ?></th>
                    <th style="white-space: nowrap; width: 100px;"><?php esc_html_e( 'Estado', 'artechia-pms' ); ?></th>
                    <th style="white-space: nowrap; width: 70px;"><?php esc_html_e( 'Fuente', 'artechia-pms' ); ?></th>
                    <th style="white-space: nowrap; width: 90px;"><?php esc_html_e( 'Pago', 'artechia-pms' ); ?></th>
                    <th style="white-space: nowrap; width: 130px;"><?php esc_html_e( 'Debe', 'artechia-pms' ); ?></th>
                    <th style="white-space: nowrap; width: 90px;"><?php esc_html_e( 'Creada', 'artechia-pms' ); ?></th>
                    <th style="min-width: 120px;"><?php esc_html_e( 'Acciones', 'artechia-pms' ); ?></th>
                </tr>
            </thead>
            <tbody id="bookings-table-body">
                <tr><td colspan="10"><?php esc_html_e( 'Cargando...', 'artechia-pms' ); ?></td></tr>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <div class="tablenav bottom">
        <div class="tablenav-pages">
            <span class="displaying-num" id="total-items"></span>
            <span class="pagination-links" id="pagination-links"></span>
        </div>
    </div>

    <!-- View Modal -->
    <div id="booking-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.45); z-index:99999; align-items:center; justify-content:center;">
        <div style="background:#fff; padding:0; width:740px; max-width:92%; border-radius:10px; box-shadow:0 8px 32px rgba(0,0,0,0.22); position:relative; overflow:hidden;">
            <button type="button" onclick="document.getElementById('booking-modal').style.display='none'" style="position:absolute; top:12px; right:16px; border:none; background:none; cursor:pointer; font-size:22px; color:#666; z-index:1;">&times;</button>
            <div id="modal-header" style="padding:20px 24px 14px; border-bottom:1px solid #eee; background:#fafafa;">
                <h2 id="modal-title" style="margin:0; font-size:18px; color:#1d2327;"></h2>
                <div id="modal-status-badge" style="margin-top:6px;"></div>
            </div>
            <div id="modal-content" style="max-height:65vh; overflow-y:auto; padding:20px 24px;"></div>
            <div style="padding:14px 24px; text-align:right; border-top:1px solid #eee; background:#fafafa;">
                <button class="button button-primary" onclick="document.getElementById('booking-modal').style.display='none'"><?php esc_html_e( 'Cerrar', 'artechia-pms' ); ?></button>
            </div>
        </div>
    </div>
    </div>

    <!-- Edit Booking Modal -->
    <div id="edit-booking-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.45); z-index:99999; align-items:center; justify-content:center;">
        <div style="background:#fff; padding:0; width:740px; max-width:92%; border-radius:10px; box-shadow:0 8px 32px rgba(0,0,0,0.22); position:relative; overflow:hidden;">
            <button type="button" onclick="document.getElementById('edit-booking-modal').style.display='none'" style="position:absolute; top:12px; right:16px; border:none; background:none; cursor:pointer; font-size:22px; color:#666; z-index:1;">&times;</button>
            <div style="padding:20px 24px 14px; border-bottom:1px solid #eee; background:#fafafa;">
                <h2 style="margin:0; font-size:18px; color:#1d2327;"><?php esc_html_e( 'Editar Reserva', 'artechia-pms' ); ?> <span id="edit-booking-code-label" style="color:#888; font-weight:400;"></span></h2>
            </div>

            <div id="edit-booking-error" style="display:none; background:#fef2f2; border-left:4px solid #dc2626; padding:10px 24px; color:#dc2626; font-size:13px;"></div>

            <form id="form-edit-booking" style="padding:20px 24px; max-height:70vh; overflow-y:auto;">
                <input type="hidden" name="booking_code" id="edit-booking-code">

                <!-- Stay Section -->
                <div style="margin-bottom:14px;">
                    <div style="font-size:10px; text-transform:uppercase; letter-spacing:0.5px; color:#888; margin-bottom:6px; font-weight:600;">Estadía</div>
                    <div style="background:#f8f9fa; border:1px solid #e9ecef; border-radius:6px; padding:14px;">
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:10px;">
                            <div>
                                <label style="font-size:12px; font-weight:600; display:block; margin-bottom:3px;"><?php esc_html_e( 'Check-in', 'artechia-pms' ); ?></label>
                                <input type="date" name="check_in" id="edit-check-in" class="widefat" required style="height:32px; font-size:12px;">
                            </div>
                            <div>
                                <label style="font-size:12px; font-weight:600; display:block; margin-bottom:3px;"><?php esc_html_e( 'Check-out', 'artechia-pms' ); ?></label>
                                <input type="date" name="check_out" id="edit-check-out" class="widefat" required style="height:32px; font-size:12px;">
                            </div>
                        </div>
                        <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:10px;">
                            <div>
                                <label style="font-size:12px; font-weight:600; display:block; margin-bottom:3px;"><?php esc_html_e( 'Adultos', 'artechia-pms' ); ?></label>
                                <input type="number" name="adults" id="edit-adults" class="widefat" min="1" required style="height:32px; font-size:12px;">
                            </div>
                            <div>
                                <label style="font-size:12px; font-weight:600; display:block; margin-bottom:3px;"><?php esc_html_e( 'Niños', 'artechia-pms' ); ?></label>
                                <input type="number" name="children" id="edit-children" class="widefat" min="0" style="height:32px; font-size:12px;">
                            </div>
                            <div>
                                <label style="font-size:12px; font-weight:600; display:block; margin-bottom:3px;"><?php esc_html_e( 'Unidad', 'artechia-pms' ); ?></label>
                                <select name="room_unit_id" id="edit-room-unit-id" class="widefat" style="height:32px; font-size:12px;"><option>Cargando...</option></select>
                            </div>
                        </div>
                        <div id="edit-booking-info" style="font-size:11px; color:#888; margin-top:8px; padding-top:8px; border-top:1px solid #e9ecef;"></div>
                    </div>
                </div>

                <!-- Guest Section -->
                <div style="margin-bottom:14px;">
                    <div style="font-size:10px; text-transform:uppercase; letter-spacing:0.5px; color:#888; margin-bottom:6px; font-weight:600;">Huésped</div>
                    <div style="background:#f8f9fa; border:1px solid #e9ecef; border-radius:6px; padding:14px;">
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:10px;">
                            <div>
                                <label style="font-size:12px; font-weight:600; display:block; margin-bottom:3px;"><?php esc_html_e( 'Nombre', 'artechia-pms' ); ?></label>
                                <input type="text" name="guest_first_name" id="edit-guest-first-name" class="widefat" required style="height:32px; font-size:12px;">
                            </div>
                            <div>
                                <label style="font-size:12px; font-weight:600; display:block; margin-bottom:3px;"><?php esc_html_e( 'Apellido', 'artechia-pms' ); ?></label>
                                <input type="text" name="guest_last_name" id="edit-guest-last-name" class="widefat" required style="height:32px; font-size:12px;">
                            </div>
                        </div>
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:10px;">
                            <div>
                                <label style="font-size:12px; font-weight:600; display:block; margin-bottom:3px;"><?php esc_html_e( 'Email', 'artechia-pms' ); ?></label>
                                <input type="email" name="guest_email" id="edit-guest-email" class="widefat" required style="height:32px; font-size:12px;">
                            </div>
                            <div>
                                <label style="font-size:12px; font-weight:600; display:block; margin-bottom:3px;"><?php esc_html_e( 'Teléfono', 'artechia-pms' ); ?></label>
                                <input type="text" name="guest_phone" id="edit-guest-phone" class="widefat" inputmode="numeric" style="height:32px; font-size:12px;">
                            </div>
                        </div>
                        <div>
                            <label style="font-size:12px; font-weight:600; display:block; margin-bottom:3px;"><?php esc_html_e( 'Documento (Tipo/Num)', 'artechia-pms' ); ?></label>
                            <div style="display:flex; gap:6px;">
                                <select name="guest_document_type" id="edit-guest-doc-type" style="width:110px; height:32px; font-size:12px;">
                                    <option value="DNI">DNI</option>
                                    <option value="passport"><?php esc_html_e( 'Pasaporte', 'artechia-pms' ); ?></option>
                                    <option value="CUIT">CUIT</option>
                                </select>
                                <input type="text" name="guest_document_number" id="edit-guest-doc-number" maxlength="8" placeholder="<?php esc_attr_e( 'Número', 'artechia-pms' ); ?>" style="flex:1; height:32px; font-size:12px;" inputmode="numeric">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Notes Section -->
                <div style="margin-bottom:14px;">
                    <div style="font-size:10px; text-transform:uppercase; letter-spacing:0.5px; color:#888; margin-bottom:6px; font-weight:600;">Notas internas</div>
                    <div style="background:#f8f9fa; border:1px solid #e9ecef; border-radius:6px; padding:14px;">
                        <textarea name="notes" id="edit-notes" class="widefat" rows="2" placeholder="<?php esc_attr_e( 'Escribí una nota nueva...', 'artechia-pms' ); ?>" style="font-size:12px; resize:vertical;"></textarea>
                        <p class="description" style="margin:2px 0 0; font-size:10px; color:#888;">Se agrega con fecha y hora automáticamente</p>
                    </div>
                </div>

                <div style="padding:14px 0 0; text-align:right; border-top:1px solid #eee;">
                    <button type="button" class="button" onclick="document.getElementById('edit-booking-modal').style.display='none'"><?php esc_html_e( 'Cancelar', 'artechia-pms' ); ?></button>
                    <button type="submit" class="button button-primary button-large" id="btn-edit-submit"><?php esc_html_e( 'Guardar Cambios', 'artechia-pms' ); ?></button>
                </div>
            </form>
        </div>
    </div>

    <!-- New Booking Modal -->
    <div id="new-booking-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.45); z-index:99999; align-items:center; justify-content:center;">
        <div style="background:#fff; padding:0; width:740px; max-width:92%; border-radius:10px; box-shadow:0 8px 32px rgba(0,0,0,0.22); position:relative; overflow:hidden;">
            <button type="button" onclick="document.getElementById('new-booking-modal').style.display='none'" style="position:absolute; top:12px; right:16px; border:none; background:none; cursor:pointer; font-size:22px; color:#666; z-index:1;">&times;</button>
            <div style="padding:20px 24px 14px; border-bottom:1px solid #eee; background:#fafafa;">
                <h2 style="margin:0; font-size:18px; color:#1d2327;"><?php esc_html_e( 'Nueva Reserva Manual', 'artechia-pms' ); ?></h2>
            </div>
            
            <div id="new-booking-error" style="display:none; background:#fef2f2; border-left:4px solid #dc2626; padding:10px 24px; color:#dc2626; font-size:13px;"></div>
            
            <form id="form-new-booking" style="padding:20px 24px; max-height:70vh; overflow-y:auto;">
                <!-- Stay Section -->
                <div style="margin-bottom:14px;">
                    <div style="font-size:10px; text-transform:uppercase; letter-spacing:0.5px; color:#888; margin-bottom:6px; font-weight:600;">Estadía</div>
                    <div style="background:#f8f9fa; border:1px solid #e9ecef; border-radius:6px; padding:14px;">
                        <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:10px; margin-bottom:10px;">
                            <div>
                                <label style="font-size:12px; font-weight:600; display:block; margin-bottom:3px;"><?php esc_html_e( 'Propiedad', 'artechia-pms' ); ?></label>
                                <select name="property_id" id="new-property-id" class="widefat" required style="height:32px; font-size:12px;">
                                    <option value=""><?php esc_html_e( 'Cargando...', 'artechia-pms' ); ?></option>
                                </select>
                            </div>
                            <div>
                                <label style="font-size:12px; font-weight:600; display:block; margin-bottom:3px;"><?php esc_html_e( 'Tipo de Habitación', 'artechia-pms' ); ?></label>
                                <select name="room_type_id" id="new-room-type-id" class="widefat" required style="height:32px; font-size:12px;"><option>Cargando...</option></select>
                            </div>
                            <div>
                                <label style="font-size:12px; font-weight:600; display:block; margin-bottom:3px;"><?php esc_html_e( 'Unidad', 'artechia-pms' ); ?></label>
                                <select name="room_unit_id" id="new-room-unit-id" class="widefat" style="height:32px; font-size:12px;"><option value="">Auto-asignar</option></select>
                            </div>
                        </div>
                        <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:10px;">
                            <div>
                                <label style="font-size:12px; font-weight:600; display:block; margin-bottom:3px;"><?php esc_html_e( 'Check-in', 'artechia-pms' ); ?></label>
                                <input type="date" name="check_in" id="new-check-in" class="widefat" required style="height:32px; font-size:12px;">
                            </div>
                            <div>
                                <label style="font-size:12px; font-weight:600; display:block; margin-bottom:3px;"><?php esc_html_e( 'Check-out', 'artechia-pms' ); ?></label>
                                <input type="date" name="check_out" id="new-check-out" class="widefat" required style="height:32px; font-size:12px;">
                            </div>
                            <div>
                                <label style="font-size:12px; font-weight:600; display:block; margin-bottom:3px;"><?php esc_html_e( 'Plan Tarifario', 'artechia-pms' ); ?></label>
                                <select name="rate_plan_id" id="new-rate-plan-id" class="widefat" required style="height:32px; font-size:12px;"><option>Cargando...</option></select>
                            </div>
                        </div>
                        <div id="custom-price-row" style="display:none; margin-top:10px;">
                            <label style="font-size:12px; font-weight:600; display:block; margin-bottom:3px;"><?php esc_html_e( 'Precio por noche personalizado', 'artechia-pms' ); ?></label>
                            <input type="number" name="custom_price_per_night" id="new-custom-price" class="widefat" min="0" step="0.01" placeholder="Ej: 25000" style="height:32px; font-size:12px;">
                        </div>
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-top:10px;">
                            <div>
                                <label style="font-size:12px; font-weight:600; display:block; margin-bottom:3px;"><?php esc_html_e( 'Adultos', 'artechia-pms' ); ?></label>
                                <input type="number" name="adults" id="new-adults" class="widefat" value="2" min="1" required style="height:32px; font-size:12px;">
                            </div>
                            <div>
                                <label style="font-size:12px; font-weight:600; display:block; margin-bottom:3px;"><?php esc_html_e( 'Niños', 'artechia-pms' ); ?></label>
                                <input type="number" name="children" id="new-children" class="widefat" value="0" min="0" style="height:32px; font-size:12px;">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Guest Section -->
                <div style="margin-bottom:14px;">
                    <div style="font-size:10px; text-transform:uppercase; letter-spacing:0.5px; color:#888; margin-bottom:6px; font-weight:600;">Huésped</div>
                    <div style="background:#f8f9fa; border:1px solid #e9ecef; border-radius:6px; padding:14px;">
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:10px;">
                            <div>
                                <label style="font-size:12px; font-weight:600; display:block; margin-bottom:3px;"><?php esc_html_e( 'Nombre', 'artechia-pms' ); ?></label>
                                <input type="text" name="guest_first_name" id="new-guest-first-name" class="widefat" required style="height:32px; font-size:12px;">
                            </div>
                            <div>
                                <label style="font-size:12px; font-weight:600; display:block; margin-bottom:3px;"><?php esc_html_e( 'Apellido', 'artechia-pms' ); ?></label>
                                <input type="text" name="guest_last_name" id="new-guest-last-name" class="widefat" required style="height:32px; font-size:12px;">
                            </div>
                        </div>
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:10px;">
                            <div>
                                <label style="font-size:12px; font-weight:600; display:block; margin-bottom:3px;"><?php esc_html_e( 'Email', 'artechia-pms' ); ?></label>
                                <input type="email" name="guest_email" id="new-guest-email" class="widefat" required style="height:32px; font-size:12px;">
                            </div>
                            <div>
                                <label style="font-size:12px; font-weight:600; display:block; margin-bottom:3px;"><?php esc_html_e( 'Teléfono', 'artechia-pms' ); ?></label>
                                <input type="text" name="guest_phone" id="new-guest-phone" class="widefat" pattern="\d*" title="<?php esc_attr_e( 'Solo números', 'artechia-pms' ); ?>" inputmode="numeric" style="height:32px; font-size:12px;">
                            </div>
                        </div>
                        <div>
                            <label style="font-size:12px; font-weight:600; display:block; margin-bottom:3px;"><?php esc_html_e( 'Documento (Tipo/Num)', 'artechia-pms' ); ?></label>
                            <div style="display:flex; gap:6px;">
                                <select name="guest_document_type" style="width:110px; height:32px; font-size:12px;">
                                    <option value="DNI">DNI</option>
                                    <option value="passport"><?php esc_html_e( 'Pasaporte', 'artechia-pms' ); ?></option>
                                    <option value="CUIT">CUIT</option>
                                </select>
                                <input type="text" name="guest_document_number" id="new-guest-document-number" maxlength="8" pattern="\d*" placeholder="<?php esc_attr_e( 'Número', 'artechia-pms' ); ?>" style="flex:1; height:32px; font-size:12px;" title="<?php esc_attr_e( 'Solo números, máx 8 caracteres', 'artechia-pms' ); ?>" inputmode="numeric">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Status & Payment Row -->
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:14px;">
                    <div>
                        <div style="font-size:10px; text-transform:uppercase; letter-spacing:0.5px; color:#888; margin-bottom:6px; font-weight:600;">Estado y Opciones</div>
                        <div style="background:#f8f9fa; border:1px solid #e9ecef; border-radius:6px; padding:14px;">
                            <div style="margin-bottom:10px;">
                                <label style="font-size:12px; font-weight:600; display:block; margin-bottom:3px;"><?php esc_html_e( 'Estado Inicial', 'artechia-pms' ); ?></label>
                                <select name="status" id="new-status" class="widefat" style="height:32px; font-size:12px;">
                                    <option value="pending"><?php esc_html_e( 'Pendiente', 'artechia-pms' ); ?></option>
                                    <option value="confirmed"><?php esc_html_e( 'Confirmada', 'artechia-pms' ); ?></option>
                                </select>
                            </div>
                            <div id="new-payment-method-wrapper" style="display:none; margin-bottom:10px;">
                                <label style="font-size:12px; font-weight:600; display:block; margin-bottom:3px;"><?php esc_html_e( 'Método de Pago', 'artechia-pms' ); ?></label>
                                <select name="payment_method" id="new-payment-method" class="widefat" style="height:32px; font-size:12px;">
                                    <option value="bank_transfer"><?php esc_html_e( 'Transferencia bancaria', 'artechia-pms' ); ?></option>
                                    <option value="cash"><?php esc_html_e( 'Efectivo', 'artechia-pms' ); ?></option>
                                    <option value="other"><?php esc_html_e( 'Otro', 'artechia-pms' ); ?></option>
                                </select>
                            </div>
                            <div id="review-email-option" style="display:none;">
                                <label style="display:flex; align-items:center; gap:6px; cursor:pointer; font-size:12px;">
                                    <input type="checkbox" name="send_review_email" id="new-send-review-email" value="1">
                                    <strong><?php esc_html_e( '¿Enviar mail de reseña?', 'artechia-pms' ); ?></strong>
                                </label>
                            </div>
                        </div>
                    </div>
                    <div>
                        <div style="font-size:10px; text-transform:uppercase; letter-spacing:0.5px; color:#888; margin-bottom:6px; font-weight:600;">Pago</div>
                        <div id="payment-options-container" style="background:#f8f9fa; border:1px solid #e9ecef; border-radius:6px; padding:14px; display:none;">
                            <div>
                                <label style="font-size:12px; font-weight:600; display:block; margin-bottom:3px;"><?php esc_html_e( 'Monto Pagado', 'artechia-pms' ); ?> <span id="new-quote-preview" style="font-weight:normal; color:#666; font-size:11px;"></span></label>
                                <input type="number" name="amount_paid" id="new-amount-paid" class="widefat" placeholder="0.00" min="0" step="0.01" style="height:32px; font-size:12px;">
                                <p style="margin:4px 0 0; font-size:10px; color:#888;"><?php esc_html_e( 'El estado del pago se calculará automáticamente.', 'artechia-pms' ); ?></p>
                            </div>
                        </div>
                        <div id="payment-options-placeholder" style="background:#f8f9fa; border:1px solid #e9ecef; border-radius:6px; padding:14px; display:block;">
                            <p style="margin:0; font-size:12px; color:#999; text-align:center;">Seleccioná estado "Confirmada" para registrar un pago.</p>
                        </div>
                    </div>
                </div>

                <!-- Notes -->
                <div style="margin-bottom:14px;">
                    <div style="font-size:10px; text-transform:uppercase; letter-spacing:0.5px; color:#888; margin-bottom:6px; font-weight:600;">Notas internas</div>
                    <div style="background:#f8f9fa; border:1px solid #e9ecef; border-radius:6px; padding:14px;">
                        <textarea name="notes" id="new-notes" class="widefat" rows="2" style="font-size:12px; resize:vertical;"></textarea>
                    </div>
                </div>

                <div style="padding:14px 0 0; text-align:right; border-top:1px solid #eee;">
                     <button type="button" class="button" onclick="document.getElementById('new-booking-modal').style.display='none'"><?php esc_html_e( 'Cancelar', 'artechia-pms' ); ?></button>
                     <button type="submit" class="button button-primary button-large"><?php esc_html_e( 'Crear Reserva', 'artechia-pms' ); ?></button>
                </div>
            </form>
        </div>
    </div>




    <!-- Cancel Modal -->
    <div id="cancel-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:99999; align-items:center; justify-content:center;">
        <div style="background:#fff; padding:20px; width:400px; max-width:90%; border-radius:4px; box-shadow:0 2px 10px rgba(0,0,0,0.2); position:relative;">
            <h3 style="margin-top:0;"><?php esc_html_e( 'Cancelar Reserva', 'artechia-pms' ); ?></h3>
            <form id="form-cancel-booking">
                <input type="hidden" id="cancel-booking-id">
                <div style="margin-bottom:15px;">
                    <label style="display:block; margin-bottom:5px;"><?php esc_html_e( 'Motivo de cancelación', 'artechia-pms' ); ?></label>
                    <textarea id="cancel-reason" class="widefat" rows="2" required></textarea>
                </div>
                <div id="cancel-penalty-refund-box" style="margin-bottom:15px; background:#f9f9f9; padding:10px; border:1px solid #ddd; border-radius:4px;">
                    <div id="cancel-penalty-container" style="display:none;">
                        <div id="cancel-policy-info" style="margin-bottom: 10px; font-size: 12px; background: #fff3cd; color: #856404; padding: 8px; border-radius: 4px; border: 1px solid #ffeeba; display: none;"></div>
                        
                        <label style="display:flex; align-items:flex-start; gap:8px;">
                            <input type="checkbox" id="cancel-apply-penalty" style="margin-top:4px; cursor:pointer;" checked>
                            <span style="font-size:13px;">
                                <strong style="cursor:pointer;" onclick="document.getElementById('cancel-apply-penalty').click()">Aplicar penalidad sugerida</strong>
                                <span id="cancel-suggested-amount-text" style="margin-left:5px; font-weight:bold; color:#d63638;"></span>
                                <span style="color:#666; font-size:11px; display:block; margin-top:4px;">Si desmarca esta opción, podrá ingresar un monto de devolución manualmente.</span>
                            </span>
                        </label>
                    </div>

                    <div id="cancel-refund-container" style="margin-top:10px; padding-top:10px; border-top:1px solid #eee;">
                        <label style="display:block; margin-bottom:5px;">
                            <strong><?php esc_html_e( 'Monto a devolver (Opcional)', 'artechia-pms' ); ?></strong>
                        </label>
                        <div style="display:flex; align-items:center; gap:8px;">
                            <input type="number" id="cancel-refund-amount" class="widefat" step="0.01" min="0" placeholder="$0.00" style="flex:1;">
                        </div>
                        <p class="description" style="margin:4px 0 0; font-size:11px;" id="cancel-refund-helper"><?php esc_html_e( 'Si se ingresa un monto, se registrará una devolución y se ajustará el saldo pagado de la reserva.', 'artechia-pms' ); ?></p>
                    </div>
                </div>
                <div style="text-align:right;">
                    <button type="button" class="button" onclick="document.getElementById('cancel-modal').style.display='none'"><?php esc_html_e( 'Cerrar', 'artechia-pms' ); ?></button>
                    <button type="submit" class="button button-primary"><?php esc_html_e( 'Confirmar Cancelación', 'artechia-pms' ); ?></button>
                </div>
            </form>
        </div>
    </div>

    <!-- Payment Modal -->
    <div id="payment-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:99999; align-items:center; justify-content:center;">
        <div style="background:#fff; padding:24px; width:420px; max-width:90%; border-radius:8px; box-shadow:0 4px 20px rgba(0,0,0,0.25); position:relative;">
            <h3 style="margin-top:0; margin-bottom:16px;"><?php esc_html_e( 'Registrar Pago', 'artechia-pms' ); ?></h3>
            <p style="margin-bottom:16px; color:#555;"><?php esc_html_e( 'Ingresá los detalles del pago recibido:', 'artechia-pms' ); ?></p>
            
            <form id="form-payment-booking">
                <input type="hidden" id="payment-booking-id">
                
                <!-- Green card: Balance due -->
                <div id="payment-balance-option" style="margin-bottom:16px; padding:12px; background:#f0fdf4; border:1px solid #bbf7d0; border-radius:6px; cursor:pointer;" onclick="document.getElementById('payment-amount').value = document.getElementById('payment-balance-amount-raw').value;">
                    <div style="display:flex; align-items:center; justify-content:space-between;">
                        <div>
                            <strong style="font-size:15px;"><?php esc_html_e( 'Liquidar saldo', 'artechia-pms' ); ?></strong>
                            <div style="font-size:13px; color:#555; margin-top:2px;"><?php esc_html_e( 'Monto pendiente', 'artechia-pms' ); ?></div>
                        </div>
                        <span style="font-size:22px; font-weight:700; color:#16a34a;" id="payment-balance-amount">$0</span>
                        <input type="hidden" id="payment-balance-amount-raw">
                    </div>
                </div>

                <!-- Grey card: Custom amount and details -->
                <div style="margin-bottom:16px; padding:12px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px;">
                    <strong style="font-size:15px; display:block; margin-bottom:8px;"><?php esc_html_e( 'Monto a pagar', 'artechia-pms' ); ?></strong>
                    <div style="display:flex; gap:8px;">
                        <input type="number" id="payment-amount" class="widefat" step="0.01" min="0.01" placeholder="$0.00" style="flex:1;" required>
                    </div>

                    <div style="display:flex; gap:8px; margin-top:12px;">
                        <div style="flex:1;">
                            <label style="display:block; font-size:12px; margin-bottom:4px; color:#555;"><?php esc_html_e( 'Medio de Pago', 'artechia-pms' ); ?></label>
                            <select id="payment-method" class="widefat" style="font-size:13px;">
                                <option value="Efectivo"><?php esc_html_e( 'Efectivo', 'artechia-pms' ); ?></option>
                                <option value="Transferencia"><?php esc_html_e( 'Transferencia', 'artechia-pms' ); ?></option>
                                <option value="Tarjeta de Crédito"><?php esc_html_e( 'Tarjeta de Crédito', 'artechia-pms' ); ?></option>
                                <option value="Tarjeta de Débito"><?php esc_html_e( 'Tarjeta de Débito', 'artechia-pms' ); ?></option>
                                <option value="Mercado Pago"><?php esc_html_e( 'Mercado Pago', 'artechia-pms' ); ?></option>
                                <option value="Otro medio"><?php esc_html_e( 'Otro', 'artechia-pms' ); ?></option>
                            </select>
                        </div>
                        <div style="flex:1.5;">
                            <label style="display:block; font-size:12px; margin-bottom:4px; color:#555;"><?php esc_html_e( 'Notas (Opcional)', 'artechia-pms' ); ?></label>
                            <input type="text" id="payment-note" class="widefat" placeholder="<?php esc_attr_e( 'Detalles...', 'artechia-pms' ); ?>" style="font-size:13px;">
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div style="border-top:1px solid #e2e8f0; padding-top:12px; margin-top:8px;">
                    <div style="display:flex; justify-content:flex-end; align-items:center; gap:8px;">
                        <button type="button" class="button" style="border-color:#2563eb; color:#2563eb; box-shadow:none; background:transparent;" onclick="document.getElementById('payment-modal').style.display='none'"><?php esc_html_e( 'Cancelar', 'artechia-pms' ); ?></button>
                        <button type="submit" class="button button-primary" style="background:#2563eb; border-color:#2563eb; box-shadow:none;"><?php esc_html_e( 'Registrar Pago', 'artechia-pms' ); ?></button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Confirm Booking Modal -->
    <div id="confirm-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:99999; align-items:center; justify-content:center;">
        <div style="background:#fff; padding:24px; width:420px; max-width:90%; border-radius:8px; box-shadow:0 4px 20px rgba(0,0,0,0.25); position:relative;">
            <h3 style="margin-top:0; margin-bottom:16px;"><?php esc_html_e( 'Confirmar Reserva', 'artechia-pms' ); ?></h3>
            <input type="hidden" id="confirm-booking-code">
            <p style="margin-bottom:16px; color:#555;"><?php esc_html_e( 'Confirmar el pago y la reservación:', 'artechia-pms' ); ?></p>
            
            <div id="confirm-deposit-option" style="margin-bottom:16px; padding:12px; background:#f0fdf4; border:1px solid #bbf7d0; border-radius:6px; cursor:pointer;" onclick="doConfirmBooking('deposit')">
                <div style="display:flex; align-items:center; justify-content:space-between;">
                    <div>
                        <strong style="font-size:15px;" id="confirm-deposit-title">Registrar pago</strong>
                        <div style="font-size:13px; color:#555; margin-top:2px;" id="confirm-deposit-label">Monto a pagar</div>
                    </div>
                    <span style="font-size:22px; font-weight:700; color:#16a34a;" id="confirm-deposit-amount"></span>
                </div>
            </div>

            <div style="margin-bottom:16px; padding:12px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px;">
                <strong style="font-size:15px;"><?php esc_html_e( 'Otro monto', 'artechia-pms' ); ?></strong>
                <div style="display:flex; gap:8px; margin-top:8px;">
                    <input type="number" id="confirm-custom-amount" class="widefat" step="0.01" min="0" placeholder="$0.00" style="flex:1;">
                    <button type="button" class="button button-primary" onclick="doConfirmBooking('custom')"><?php esc_html_e( 'Confirmar', 'artechia-pms' ); ?></button>
                </div>
            </div>

            <div style="border-top:1px solid #e2e8f0; padding-top:12px; margin-top:8px;">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <button type="button" class="button" onclick="doConfirmBooking('none')" style="color:#0073aa;"><?php esc_html_e( 'Confirmar sin pago', 'artechia-pms' ); ?></button>
                    <button type="button" class="button" onclick="document.getElementById('confirm-modal').style.display='none'"><?php esc_html_e( 'Cancelar', 'artechia-pms' ); ?></button>
                </div>
            </div>
        </div>
    </div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Global rule: prevent mouse wheel from changing number input values
    document.addEventListener('wheel', function(e) {
        if (document.activeElement.type === 'number') {
            document.activeElement.blur();
        }
    });

    // ── Modal Scroll Lock ──────────────────────────────
    // Prevent background from scrolling when any modal is open
    (function() {
        const MODAL_IDS = [
            'booking-modal', 'edit-booking-modal', 'new-booking-modal',
            'cancel-modal', 'payment-modal', 'confirm-modal'
        ];
        let savedScrollY = 0;

        function isAnyModalOpen() {
            return MODAL_IDS.some(id => {
                const el = document.getElementById(id);
                return el && el.style.display && el.style.display !== 'none';
            });
        }

        function lockScroll() {
            if (document.body.style.position === 'fixed') return; // already locked
            savedScrollY = window.scrollY;
            document.body.style.position = 'fixed';
            document.body.style.top = '-' + savedScrollY + 'px';
            document.body.style.left = '0';
            document.body.style.right = '0';
            document.body.style.overflow = 'hidden';
        }

        function unlockScroll() {
            if (document.body.style.position !== 'fixed') return;
            document.body.style.position = '';
            document.body.style.top = '';
            document.body.style.left = '';
            document.body.style.right = '';
            document.body.style.overflow = '';
            window.scrollTo(0, savedScrollY);
        }

        function updateScrollLock() {
            if (isAnyModalOpen()) {
                lockScroll();
            } else {
                unlockScroll();
            }
        }

        // Observe style attribute changes on all modal overlays
        const observer = new MutationObserver(updateScrollLock);
        MODAL_IDS.forEach(id => {
            const el = document.getElementById(id);
            if (el) {
                observer.observe(el, { attributes: true, attributeFilter: ['style'] });
            }
        });
    })();

    if ( typeof artechiaPMS === 'undefined' ) {
        console.error('Artechia: falta la configuración artechiaPMS');
        return;
    }
    console.log('Artechia: Iniciando script de reservas...');

    const api = {
        get: (path, params = {}) => {
            const url = new URL(artechiaPMS.restUrl + path);
            Object.keys(params).forEach(key => params[key] && url.searchParams.append(key, params[key]));
            url.searchParams.append('_wpnonce', artechiaPMS.nonce);
            return fetch(url, { headers: { 'X-WP-Nonce': artechiaPMS.nonce } }).then(r => r.json());
        },
        post: (path, data = {}) => fetch(artechiaPMS.restUrl + path, {
            method: 'POST',
            headers: { 'X-WP-Nonce': artechiaPMS.nonce, 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        }).then(r => r.json()),
        delete: (path) => fetch(artechiaPMS.restUrl + path, {
            method: 'DELETE',
            headers: { 'X-WP-Nonce': artechiaPMS.nonce }
        }).then(r => r.json()),
        put: (path, data = {}) => fetch(artechiaPMS.restUrl + path, {
            method: 'PUT',
            headers: { 'X-WP-Nonce': artechiaPMS.nonce, 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        }).then(r => r.json())
    };

    /* ── Quote & Visibility Logic ── */
    const statusSelect = document.getElementById('new-status');
    const paymentContainer = document.getElementById('payment-options-container');
    const reviewEmailOption = document.getElementById('review-email-option');
    const checkInInput = document.getElementById('new-check-in');
    const checkOutInput = document.getElementById('new-check-out');

    function updateManualBookingUI() {
        const payMethodWrapper = document.getElementById('new-payment-method-wrapper');
        const payPlaceholder = document.getElementById('payment-options-placeholder');
        // Toggle Payment Options
        if (statusSelect && statusSelect.value === 'confirmed') {
            if (paymentContainer) paymentContainer.style.display = 'block';
            if (payMethodWrapper) payMethodWrapper.style.display = 'block';
            if (payPlaceholder) payPlaceholder.style.display = 'none';
            updateQuote(); // Fetch quote when showing payment options
        } else {
            if (paymentContainer) paymentContainer.style.display = 'none';
            if (payMethodWrapper) payMethodWrapper.style.display = 'none';
            if (payPlaceholder) payPlaceholder.style.display = 'block';
            const amountPaidEl = document.getElementById('new-amount-paid');
            if (amountPaidEl) amountPaidEl.value = '';
        }

        // Toggle Review Email Option (only if both dates are in the past)
        const today = new Date().toISOString().split('T')[0];
        const checkIn = checkInInput ? checkInInput.value : '';
        const checkOut = checkOutInput ? checkOutInput.value : '';

        if (reviewEmailOption) {
            if (checkIn && checkOut && checkIn < today && checkOut <= today) {
                reviewEmailOption.style.display = 'block';
            } else {
                reviewEmailOption.style.display = 'none';
                const sendEmailEl = document.getElementById('new-send-review-email');
                if (sendEmailEl) sendEmailEl.checked = false;
            }
        }
    }

    let quoteDebounce;
    function updateQuote() {
        if (!checkInInput || !checkOutInput || !statusSelect) return;
        
        const propertyId = document.getElementById('new-property-id').value;
        const roomTypeId = document.getElementById('new-room-type-id').value;
        const ratePlanId = document.getElementById('new-rate-plan-id').value;
        const customPrice = document.getElementById('new-custom-price');
        const isCustom = ratePlanId === 'custom';
        const checkIn = checkInInput.value;
        const checkOut = checkOutInput.value;
        const adults = document.getElementById('new-adults').value;
        const children = document.getElementById('new-children').value;

        // Only fetch if we have minimum required fields
        if (!propertyId || !roomTypeId || !checkIn || !checkOut) return;
        if (!isCustom && !ratePlanId) return;

        // Custom price: calculate locally without API call
        if (isCustom) {
            clearTimeout(quoteDebounce); // Cancel any pending API quote call
            const customVal = parseFloat(customPrice ? customPrice.value : 0);
            if (!customVal || customVal <= 0) {
                const previewEl = document.getElementById('new-quote-preview');
                if (previewEl) previewEl.textContent = 'Ingresá el precio por noche';
                return;
            }
            const d1 = new Date(checkIn), d2 = new Date(checkOut);
            const nights = Math.round((d2 - d1) / 86400000);
            if (nights < 1) return;
            const total = customVal * nights;
            const previewEl = document.getElementById('new-quote-preview');
            if (previewEl) previewEl.innerHTML = `${nights} noche${nights > 1 ? 's' : ''} × ${artechiaPMS.formatPrice(customVal)} = <strong>${artechiaPMS.formatPrice(total)}</strong>`;
            const amountInput = document.getElementById('new-amount-paid');
            if (amountInput) {
                amountInput.max = total;
                amountInput.placeholder = `Máx: ${artechiaPMS.formatPrice(total)}`;
                amountInput.oninput = () => {
                    let val = parseFloat(amountInput.value);
                    if (val > total) amountInput.value = total;
                };
                // Cap existing value if needed
                let currentVal = parseFloat(amountInput.value);
                if (currentVal > total) amountInput.value = total;
            }
            return;
        }

        clearTimeout(quoteDebounce);
        quoteDebounce = setTimeout(() => {
                const previewEl = document.getElementById('new-quote-preview');
                if (!previewEl) return;
                previewEl.textContent = 'Calculando...';
                
                api.post('public/quote', {
                    property_id: propertyId,
                    room_type_id: roomTypeId,
                    rate_plan_id: ratePlanId,
                    check_in: checkIn,
                    check_out: checkOut,
                    adults: adults,
                    children: children,
                    guest_email: document.getElementById('new-guest-email').value
                }).then(res => {
                    if (res.totals) {
                        const total = parseFloat(res.totals.total);
                        previewEl.innerHTML = `Total Estimado: <strong>${artechiaPMS.formatPrice(total)}</strong>`;
                        
                        const amountInput = document.getElementById('new-amount-paid');
                        if (amountInput) {
                            amountInput.max = total;
                            amountInput.placeholder = `Máx: ${artechiaPMS.formatPrice(total)}`;
                            
                            // Prevent entering more than total if user is typing
                            amountInput.oninput = () => {
                                let val = parseFloat(amountInput.value);
                                if (val > total) {
                                    amountInput.value = total;
                                }
                            };

                            let currentVal = parseFloat(amountInput.value);
                            if (currentVal > total) {
                                amountInput.value = total;
                            }
                        }
                    } else {
                        previewEl.textContent = '';
                    }
                }).catch(err => {
                    console.error('Quote error:', err);
                    previewEl.textContent = '(Error calculando)';
                });
        }, 500);
    }

    if (statusSelect) statusSelect.addEventListener('change', updateManualBookingUI);
    if (checkInInput) checkInInput.addEventListener('change', () => { updateManualBookingUI(); updateQuote(); });
    if (checkOutInput) checkOutInput.addEventListener('change', () => { updateManualBookingUI(); updateQuote(); });
    
    // Add listeners to other fields for quote updates
    ['new-property-id', 'new-room-type-id', 'new-rate-plan-id', 'new-adults', 'new-children'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.addEventListener('change', updateQuote);
    });
    // Also trigger quote on custom price input
    const customPriceInput = document.getElementById('new-custom-price');
    if (customPriceInput) {
        customPriceInput.addEventListener('input', updateQuote);
    }

    // Toggle custom price visibility when rate plan changes
    document.getElementById('new-rate-plan-id').addEventListener('change', function() {
        const customRow = document.getElementById('custom-price-row');
        if (customRow) {
            customRow.style.display = this.value === 'custom' ? 'block' : 'none';
        }
    });

    // Real-time numeric-only restriction for Phone and DNI in Admin
    const adminPhoneInput = document.getElementById('new-guest-phone');
    const adminDniInput = document.getElementById('new-guest-document-number');
    [adminPhoneInput, adminDniInput].forEach(el => {
        if (el) {
            el.addEventListener('input', (e) => {
                e.target.value = e.target.value.replace(/\D/g, '');
            });
        }
    });

    let currentPage = 1;
    let currentBookingId = null;
    const tbody = document.getElementById('bookings-table-body');

    function loadBookings(showLoading = true) {
        const filters = {
            page: currentPage,
            search: document.getElementById('filter-search').value,
            property_id: document.getElementById('filter-property').value,
            status: document.getElementById('filter-status').value,
            source: document.getElementById('filter-source').value,
            payment_status: document.getElementById('filter-payment').value,
            date_from: document.getElementById('filter-date-from').value,
            date_to: document.getElementById('filter-date-to').value,
            per_page: 20
        };

        if (showLoading) {
            tbody.innerHTML = '<tr><td colspan="10"><?php esc_html_e( 'Cargando reservas...', 'artechia-pms' ); ?></td></tr>';
        }

        api.get('admin/bookings', filters).then(res => {
            if (!res.data || res.data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="10"><?php esc_html_e( 'Sin reservas encontradas.', 'artechia-pms' ); ?></td></tr>';
                updatePagination(0, 0);
                return;
            }
            // Store overbooking warnings in a global map BEFORE rendering
            window._overbookingMap = {};
            if (res.overbooking_warnings && res.overbooking_warnings.length > 0) {
                res.overbooking_warnings.forEach(w => {
                    window._overbookingMap[w.booking_code] = w;
                });
            }

            renderTable(res.data);
            updatePagination(res.total, res.pages);

            // Flash the row that was just updated
            if (pendingFlashCode) {
                const code = pendingFlashCode;
                pendingFlashCode = null;
                setTimeout(() => flashRow(code), 100);
            }

            // Auto-open modal if we are deep-linking and have exactly one result
            const urlParams = new URLSearchParams(window.location.search);
            const bookingCode = urlParams.get('booking_code');
            if (bookingCode && res.data.length === 1 && res.data[0].booking_code === bookingCode) {
                viewBooking(bookingCode);
            }
        }).catch(err => {
            console.error(err);
            tbody.innerHTML = `<tr><td colspan="10" style="color:red;"><?php esc_html_e( 'Error cargando reservas. Ver consola.', 'artechia-pms' ); ?></td></tr>`;
        });
    }

    function updateStatsCards(bookings) {
        // No-op: cards removed
    }

    // Flash mechanism: highlights a row after reload to identify which booking was updated
    let pendingFlashCode = null;
    function flashRow(code) {
        if (!code) return;
        const row = document.querySelector(`#bookings-table-body tr[data-code="${code}"]`);
        if (row) {
            row.classList.add('booking-row-flash');
            row.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            setTimeout(() => row.classList.remove('booking-row-flash'), 2500);
        }
    }

    function renderTable(bookings) {
        // Update stats cards
        updateStatsCards(bookings);
        
        // Build the new HTML first
        const newHtml = bookings.map(b => {
            const formatDate = (dateStr) => {
                if (!dateStr) return '';
                const parts = dateStr.split(' ')[0].split('-'); // Handles YYYY-MM-DD
                if (parts.length === 3) {
                    const y = parts[0].slice(-2);
                    const m = parseInt(parts[1], 10);
                    const d = parseInt(parts[2], 10);
                    return `${d}/${m}/${y}`;
                }
                return dateStr;
            };

            if (b.is_lock) {
                const expires = new Date(b.expires_at).getTime();
                const now = new Date().getTime();
                const diff = Math.max(0, Math.floor((expires - now) / 1000));
                const minutes = Math.floor(diff / 60);
                const seconds = diff % 60;
                const timerStr = `${minutes}:${seconds.toString().padStart(2, '0')}`;

                return `
                <tr class="lock-row" style="background-color: #fff9e6;">
                    <td><span class="artechia-badge badge-gray">HOLD</span></td>
                    <td>
                        <strong>${b.guest_name}</strong><br>
                        <small style="color:#666;">Cliente en checkout</small>
                    </td>
                    <td>
                        ${formatDate(b.check_in)} &rarr; ${formatDate(b.check_out)}
                    </td>
                    <td>${b.room_type || '—'}</td>
                    <td>${getStatusLabel(b.status)} <br> <small class="lock-timer" data-expires="${b.expires_at}" data-code="${b.booking_code}">${timerStr}</small></td>
                    <td>WEB</td>
                    <td>—</td>
                    <td>—</td>
                    <td>${formatDate(b.created_at.split(' ')[0])}</td>
                    <td>
                         <small style="color:#999;">Bloqueado por 15m</small>
                    </td>
                </tr>
                `;
            }

            // Virtual expiration for real bookings on hold (15m from creation)
            let timerHtml = '';
            if (b.status === 'hold') {
                const createdAt = new Date(b.created_at).getTime();
                const expiresAt = new Date(createdAt + 15 * 60000).toISOString();
                timerHtml = `<br><small class="lock-timer" data-expires="${expiresAt}" data-code="${b.booking_code}">--:--</small>`;
            }

            const created = formatDate(b.created_at.split(' ')[0]);
            
            const total = Number(b.grand_total ?? b.total_cost ?? 0);
            const paid  = Number(b.amount_paid ?? 0);
            const due   = (b.balance_due == 0 && total > (paid + 0.01)) ? (total - paid) : Number(b.balance_due ?? (total - paid));
            
            const safeTotal   = isNaN(total) ? 0 : total;
            const safeDue     = isNaN(due)   ? 0 : due;
            const safePaid    = isNaN(paid)  ? 0 : paid;

            <?php
            $page_id = (int) \Artechia\PMS\Services\Settings::get( 'my_booking_page_id' );
            $base_url = $page_id ? get_permalink( $page_id ) : home_url( '/mi-reserva/' );
            ?>
            const manageUrl = `<?php echo $base_url; ?>?code=${b.booking_code}&token=${b.access_token}`;
            
            
            return `
            <tr data-status="${b.status}" data-code="${b.booking_code}">
                <td style="white-space: nowrap;"><strong>${b.status === 'hold' ? 'HOLD' : b.booking_code}</strong></td>
                <td style="white-space: normal; word-wrap: break-word;">
                    ${b.guest_name}<br>
                    <small style="color:#666; word-break: break-all;">${b.guest_email}</small>
                </td>
                <td style="white-space: nowrap;">
                    ${formatDate(b.check_in)} &rarr; ${formatDate(b.check_out)}<br>
                    <small>${b.nights} noches</small>
                </td>
                <td style="white-space: nowrap; position:relative;">
                    ${b.room_unit_name || (b.room_type_name ? b.room_type_name + ' (Asignar)' : '—')}
                    ${(() => {
                        const warn = (window._overbookingMap || {})[b.booking_code];
                        if (!warn) return '';
                        return `<br><span class="ob-badge" onclick="event.stopPropagation(); const p=this.nextElementSibling; p.style.display=p.style.display==='block'?'none':'block';" style="background:#dc2626; color:#fff; font-size:10px; padding:1px 5px; border-radius:4px; font-weight:600; cursor:pointer;">⚠️ Overbooking</span><div class="ob-popover" style="display:none; position:absolute; z-index:999; left:0; top:100%; background:#fff; border:1px solid #fecaca; border-radius:6px; box-shadow:0 4px 12px rgba(0,0,0,0.15); padding:10px 14px; min-width:200px; font-size:12px; margin-top:4px;"><p style="margin:0; color:#991b1b; line-height:1.5;">⚠️ No confirmar más de <b>${warn.total_units}</b> reservas para <b>${warn.room_type}</b> en estas fechas o se produce overbooking.</p></div>`;
                    })()}
                </td>
                <td style="white-space: nowrap;">${getStatusLabel(b.status)}${timerHtml}</td>
                <td style="white-space: nowrap;">${(b.source || 'WEB').toUpperCase()}</td>
                <td style="white-space: nowrap;">${getPaymentLabel(b.payment_status)}</td>
                <td style="white-space: nowrap;">
                    ${b.status === 'cancelled' ? `
                        ${(() => {
                            const grossPaid = safePaid + Number(b.refund_amount || 0);
                            return `
                                <div style="text-decoration:line-through; color:#999;">Total: ${artechiaPMS.formatPrice(safeTotal)}</div>
                                ${Number(b.penalty_amount || 0) > 0 ? `<div style="color:#d63638;">Penalización: ${artechiaPMS.formatPrice(Number(b.penalty_amount))}</div>` : ''}
                                ${Number(b.refund_amount || 0) > 0 ? `<div style="color:#0073aa;">Devuelto: ${artechiaPMS.formatPrice(Number(b.refund_amount))}</div>` : ''}
                                ${grossPaid === 0 ? `<div style="color:#999;">Sin pagos</div>` : ''}
                            `;
                        })()}
                    ` : `
                        <div>Total: ${artechiaPMS.formatPrice(safeTotal)}</div>
                        <div style="color:${safeDue > 0 ? '#d63638' : '#00a32a'};">Debe: ${artechiaPMS.formatPrice(safeDue)}</div>
                    `}
                </td>
                <td style="white-space: nowrap;">${created}</td>
                <td>
                    <div style="display:flex; flex-wrap:wrap; gap:4px; max-width: 200px;">
                        <button class="button button-small action-view" data-code="${b.booking_code}">Ver</button>
                        ${ !['checked_out', 'cancelled'].includes(b.status) ? `<button class="button button-small action-edit" data-code="${b.booking_code}" style="color:#0073aa;">Editar</button>` : '' }
                        ${ b.status === 'pending' ? `<button class="button button-small action-confirm" data-code="${b.booking_code}" data-total="${safeTotal}" data-deposit="${Number(b.deposit_due || 0)}" style="color:green;">Confirmar</button>` : '' }
                        ${ ['confirmed', 'checked_in', 'deposit_paid'].includes(b.status) && Number(b.balance_due) > 0 ? `<button class="button button-small action-payment" data-code="${b.booking_code}" data-balance="${safeDue}" style="color:#0073aa;">Pago</button>` : '' }
                        ${ ['pending','confirmed'].includes(b.status) ? `<button class="button button-small action-cancel" data-code="${b.booking_code}" style="color:red;">Cancelar</button>` : '' }
                        ${ b.status === 'cancelled' ? `<button class="button button-small action-delete" data-code="${b.booking_code}" style="color:red;">Borrar</button>` : '' }
                        <button class="button button-small action-copy" data-url="${manageUrl}" title="Copiar Link Guest">Link</button>
                    </div>
                </td>
            </tr>
            `;
        }).join('');

        // Only update DOM if HTML changed to prevent flickering
        if (tbody.innerHTML !== newHtml) {
            tbody.innerHTML = newHtml;
        }
    }

    // Event delegation — survives every re-render, attached once
    tbody.addEventListener('click', function(e) {
        const btn = e.target.closest('button');
        if (!btn) return;
        
        const code = btn.dataset.code;
        
        if (btn.classList.contains('action-view'))    { viewBooking(code); }
        else if (btn.classList.contains('action-edit'))    { editBooking(code); }
        else if (btn.classList.contains('action-confirm')) { confirmBooking(code, btn.dataset.total, btn.dataset.deposit); }
        else if (btn.classList.contains('action-cancel'))  { cancelBooking(code); }
        else if (btn.classList.contains('action-payment')) { recordPayment(code, btn.dataset.balance); }
        else if (btn.classList.contains('action-delete'))  { deleteBooking(code); }
        else if (btn.classList.contains('action-copy'))    {
            navigator.clipboard.writeText(btn.dataset.url).then(() => artechiaPMS.toast.show('Link copiado al portapapeles', 'success'));
        }
    });

    function getStatusLabel(status) {
        const map = {
            'pending': '<span class="artechia-badge badge-warning">Pendiente</span>',
            'confirmed': '<span class="artechia-badge badge-success">Confirmada</span>',
            'cancelled': '<span class="artechia-badge badge-danger">Cancelada</span>',
            'checked_in': '<span class="artechia-badge badge-info">In-House</span>',
            'checked_out': '<span class="artechia-badge badge-gray">Finalizada</span>',
            'hold': '<span class="artechia-badge badge-warning" style="background:#f39c12; color:#fff;">Checkout</span>',
        };
        return map[status] || status;
    }

    function getPaymentLabel(status) {
         const map = {
             'unpaid': '<span class="artechia-badge badge-danger" style="background:#d63638;">SIN PAGAR</span>',
             'deposit_paid': '<span class="artechia-badge badge-warning" style="background:#f1c40f; color:#000;">SEÑA</span>',
             'paid': '<span class="artechia-badge badge-success" style="background:#27ae60;">PAGADO</span>',
             'refunded': '<span class="artechia-badge" style="background:#7c3aed; color:#fff;">REEMBOLSADO</span>',
             'partial_refund': '<span class="artechia-badge" style="background:#d97706; color:#fff;">REEMBOLSO PARCIAL</span>',
        };
        return map[status] || status;
    }

    function updatePagination(total, pages) {
        document.getElementById('total-items').textContent = `${total} items`;
        let html = '';
        if (pages > 1) {
            html += `<span class="pagination-links">`;
            html += `<a class="first-page button ${currentPage === 1 ? 'disabled' : ''}" data-page="1">&laquo;</a>`;
            html += `<a class="prev-page button ${currentPage === 1 ? 'disabled' : ''}" data-page="${currentPage - 1}">&lsaquo;</a>`;
            html += `<span class="paging-input"> ${currentPage} de <span class="total-pages">${pages}</span> </span>`;
            html += `<a class="next-page button ${currentPage === pages ? 'disabled' : ''}" data-page="${currentPage + 1}">&rsaquo;</a>`;
            html += `<a class="last-page button ${currentPage === pages ? 'disabled' : ''}" data-page="${pages}">&raquo;</a>`;
            html += `</span>`;
        }
        document.getElementById('pagination-links').innerHTML = html;
        
        document.querySelectorAll('.pagination-links a:not(.disabled)').forEach(a => {
            a.onclick = () => {
                currentPage = parseInt(a.dataset.page);
                loadBookings();
            };
        });
    }

    // ── Edit Booking ──
    window.editBooking = function(code) {
        const modal = document.getElementById('edit-booking-modal');
        const errorBanner = document.getElementById('edit-booking-error');
        errorBanner.style.display = 'none';
        modal.style.display = 'flex';

        document.getElementById('edit-booking-code').value = code;
        document.getElementById('edit-booking-code-label').textContent = '#' + code;

        api.get(`admin/bookings/${code}`).then(res => {
            document.getElementById('edit-check-in').value = res.check_in || '';
            document.getElementById('edit-check-out').value = res.check_out || '';
            document.getElementById('edit-adults').value = res.adults || 1;
            document.getElementById('edit-children').value = res.children || 0;
            document.getElementById('edit-guest-first-name').value = res.guest_first_name || '';
            document.getElementById('edit-guest-last-name').value = res.guest_last_name || '';
            document.getElementById('edit-guest-email').value = res.guest_email || '';
            document.getElementById('edit-guest-phone').value = res.guest_phone || '';
            document.getElementById('edit-guest-doc-type').value = res.guest_document_type || 'DNI';
            document.getElementById('edit-guest-doc-number').value = res.guest_document_number || '';
            document.getElementById('edit-notes').value = ''; // Start empty — user types a NEW note

            // Populate unit dropdown
            const unitSelect = document.getElementById('edit-room-unit-id');
            const currentUnitId = (res.rooms && res.rooms.length) ? res.rooms[0].room_unit_id : '';
            const roomTypeId = (res.rooms && res.rooms.length) ? res.rooms[0].room_type_id : (res.room_type_id || '');
            const allUnits = setupData.roomUnits || [];
            const filteredUnits = roomTypeId ? allUnits.filter(u => u.room_type_id === parseInt(roomTypeId)) : allUnits;
            unitSelect.innerHTML = filteredUnits.map(u => `<option value="${u.id}"${u.id == currentUnitId ? ' selected' : ''}>${u.name} (${u.room_type_name})</option>`).join('');

            // Show info
            const rooms = (res.rooms || []).map(r => r.unit_name + ' (' + r.room_type_name + ')').join(', ') || 'Sin asignar';
            document.getElementById('edit-booking-info').innerHTML = `
                <strong>Estado:</strong> ${getStatusLabel(res.status)}<br>
                <strong>Total:</strong> ${artechiaPMS.formatPrice(Number(res.grand_total || 0))}
            `;
        }).catch(err => {
            errorBanner.textContent = 'Error cargando datos de la reserva.';
            errorBanner.style.display = 'block';
        });
    };

    document.getElementById('form-edit-booking').onsubmit = function(e) {
        e.preventDefault();
        const code = document.getElementById('edit-booking-code').value;
        const btn = document.getElementById('btn-edit-submit');
        const errorBanner = document.getElementById('edit-booking-error');
        
        btn.disabled = true;
        btn.textContent = 'Guardando...';
        errorBanner.style.display = 'none';

        const data = {
            check_in: document.getElementById('edit-check-in').value,
            check_out: document.getElementById('edit-check-out').value,
            adults: parseInt(document.getElementById('edit-adults').value) || 1,
            children: parseInt(document.getElementById('edit-children').value) || 0,
            guest_first_name: document.getElementById('edit-guest-first-name').value,
            guest_last_name: document.getElementById('edit-guest-last-name').value,
            guest_email: document.getElementById('edit-guest-email').value,
            guest_phone: document.getElementById('edit-guest-phone').value,
            guest_document_type: document.getElementById('edit-guest-doc-type').value,
            guest_document_number: document.getElementById('edit-guest-doc-number').value,
            notes: document.getElementById('edit-notes').value,
            room_unit_id: document.getElementById('edit-room-unit-id').value || null,
        };

        api.put(`admin/bookings/${code}`, data).then(res => {
            document.getElementById('edit-booking-modal').style.display = 'none';
            pendingFlashCode = code;
            loadBookings();
        }).catch(err => {
            const msg = (err.responseJSON && err.responseJSON.message) || err.message || 'Error al guardar.';
            errorBanner.textContent = msg;
            errorBanner.style.display = 'block';
        }).finally(() => {
            btn.disabled = false;
            btn.textContent = 'Guardar Cambios';
        });
    };

    window.viewBooking = function(code) {
        const modal = document.getElementById('booking-modal');
        const content = document.getElementById('modal-content');
        const title = document.getElementById('modal-title');
        const statusBadge = document.getElementById('modal-status-badge');
        
        modal.style.display = 'flex';
        content.innerHTML = '<div style="text-align:center; padding:20px; color:#888;">Cargando detalle...</div>';
        title.textContent = 'Reserva #' + code;
        statusBadge.innerHTML = '';

        const statusMap = {
            hold: { label: 'En espera', bg: '#fff3cd', color: '#856404' },
            pending: { label: 'Pendiente', bg: '#cfe2ff', color: '#084298' },
            confirmed: { label: 'Confirmada', bg: '#d1e7dd', color: '#0f5132' },
            checked_in: { label: 'Check-in', bg: '#cff4fc', color: '#055160' },
            checked_out: { label: 'Check-out', bg: '#e2e3e5', color: '#41464b' },
            cancelled: { label: 'Cancelada', bg: '#f8d7da', color: '#842029' },
            no_show: { label: 'No show', bg: '#f8d7da', color: '#842029' },
        };

        const sourceMap = {
            web: 'Página web',
            admin: 'Panel Admin',
            ical: 'Importación iCal',
        };

        const payMethodMap = {
            mercadopago: 'MercadoPago',
            bank_transfer: 'Transferencia bancaria',
        };

        const payStatusMap = {
            unpaid: { label: 'Sin pago', color: '#dc2626' },
            partial: { label: 'Parcial', color: '#d97706' },
            deposit_paid: { label: 'Seña', color: '#d97706' },
            paid: { label: 'Pagado', color: '#16a34a' },
            refunded: { label: 'Reembolsado', color: '#7c3aed' },
            partial_refund: { label: 'Reembolso parcial', color: '#d97706' },
        };

        api.get(`admin/bookings/${code}`).then(res => {
            const grandTotal = Number(res.grand_total ?? 0) || 0;
            const amountPaid = Number(res.amount_paid ?? 0) || 0;
            const balanceDue = Number(res.balance_due ?? 0) || 0;
            const guestDoc   = res.guest_document_number ? `${res.guest_document_type} ${res.guest_document_number}` : null;
            const st = statusMap[res.status] || { label: res.status, bg: '#e2e3e5', color: '#41464b' };
            // For iCal imports, show source_ref (e.g. "Booking.com (Feed Name)") otherwise use the map
            const source = res.source === 'ical' && res.source_ref
                ? res.source_ref
                : (sourceMap[res.source] || res.source || '—');
            const payMethod = payMethodMap[res.payment_method] || res.payment_method || '—';
            const paySt = payStatusMap[res.payment_status] || { label: res.payment_status || '—', color: '#666' };
            const createdAt = res.created_at ? artechiaPMS.formatDate(res.created_at.substring(0,10)) : '—';

            statusBadge.innerHTML = `<span style="display:inline-block; padding:3px 10px; border-radius:12px; font-size:12px; font-weight:600; background:${st.bg}; color:${st.color};">${st.label}</span>
            ${res.status !== 'cancelled' ? `<a href="#" onclick="event.preventDefault(); document.getElementById('status-change-row').style.display = document.getElementById('status-change-row').style.display === 'none' ? 'flex' : 'none';" style="font-size:11px; margin-left:8px; color:#0073aa;">Cambiar</a>
            <div id="status-change-row" style="display:none; margin-top:6px; align-items:center; gap:6px;">
                <select id="status-change-select" style="font-size:12px; height:28px;">
                    <option value="pending" ${res.status==='pending'?'selected':''}>Pendiente</option>
                    <option value="confirmed" ${res.status==='confirmed'?'selected':''}>Confirmada</option>
                    <option value="checked_in" ${res.status==='checked_in'?'selected':''}>Check-in</option>
                    <option value="checked_out" ${res.status==='checked_out'?'selected':''}>Check-out</option>
                </select>
                <button class="button button-small" onclick="changeBookingStatus('${res.booking_code}')" style="font-size:11px;">Aplicar</button>
            </div>` : ''}`;

            // Format notes
            const rawNotes = res.notes || res.internal_notes || '';
            let notesHtml = '';
            if (rawNotes.trim()) {
                // Pre-process: insert newline before [timestamp] patterns that are glued to prior text
                const cleaned = rawNotes.replace(/([^\n])\[(\d{1,2}[\/-]\d{1,2}[\/-]\d{2,4})/g, '$1\n[$2')
                                        .replace(/([^\n])\[(\d{4}-\d{2}-\d{2})/g, '$1\n[$2');
                const lines = cleaned.trim().split('\n').filter(l => l.trim());
                notesHtml = lines.map((line, i) => {
                    // Match [timestamp] note pattern
                    const m = line.match(/^\[(.+?)\]\s*(.*)$/);
                    if (m) {
                        let ts = m[1];
                        // Convert old Y-m-d H:i:s format to d/m/Y H:i
                        const oldFmt = ts.match(/^(\d{4})-(\d{2})-(\d{2})\s+(\d{2}):(\d{2})(:\d{2})?$/);
                        if (oldFmt) ts = `${oldFmt[3]}/${oldFmt[2]}/${oldFmt[1]} ${oldFmt[4]}:${oldFmt[5]}`;
                        const isLast = i === lines.length - 1;
                        return `<div style="padding:6px 0;${!isLast ? ' border-bottom:1px solid #f0e6c8;' : ''}">
                            <span style="font-size:10px; color:#8b7355; background:#f5f0e0; padding:1px 6px; border-radius:3px;">${ts}</span>
                            <div style="font-size:12px; margin-top:3px; color:#333;">${m[2]}</div>
                        </div>`;
                    }
                    // Plain text note (user-typed, no timestamp)
                    const isLast = i === lines.length - 1;
                    return `<div style="padding:5px 0; font-size:12px; color:#333;${!isLast ? ' border-bottom:1px solid #f0e6c8;' : ''}">${line}</div>`;
                }).join('');
            }

            const lbl = 'font-size:10px; text-transform:uppercase; letter-spacing:0.5px; color:#888; margin-bottom:4px; font-weight:600;';
            const card = 'background:#f8f9fa; border:1px solid #e9ecef; border-radius:6px; padding:10px 12px;';
            const row = 'display:flex; justify-content:space-between; padding:3px 0; border-bottom:1px solid #f0f0f0; font-size:12px;';
            const rowL = 'display:flex; justify-content:space-between; padding:3px 0; font-size:12px;';

            content.innerHTML = `
                <!-- Guest + Source Row -->
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:12px;">
                    <div>
                        <div style="${lbl}">Huésped</div>
                        <div style="${card}">
                            <div style="font-size:14px; font-weight:600; margin-bottom:2px;">${res.guest_name}</div>
                            <div style="font-size:12px; color:#555;">
                                ${res.guest_email ? '✉ ' + res.guest_email : ''}
                            </div>
                            ${res.guest_phone ? '<div style="font-size:12px; color:#555;">📞 ' + res.guest_phone + '</div>' : ''}
                            ${guestDoc ? '<div style="font-size:11px; color:#888; margin-top:2px;">🪪 ' + guestDoc + '</div>' : ''}
                        </div>
                    </div>
                    <div>
                        <div style="${lbl}">Información</div>
                        <div style="${card}">
                            <div style="${row}">
                                <span style="color:#666;">Fuente</span>
                                <span style="font-weight:500;">${source}</span>
                            </div>
                            <div style="${row}">
                                <span style="color:#666;">Creada</span>
                                <span style="font-weight:500;">${createdAt}</span>
                            </div>
                            <div style="${row}">
                                <span style="color:#666;">Método pago</span>
                                <span style="font-weight:500;">${payMethod}</span>
                            </div>
                            <div style="${rowL}">
                                <span style="color:#666;">Estado pago</span>
                                <span style="font-weight:600; color:${paySt.color};">${paySt.label}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Stay + Payments Row -->
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:12px;">
                    <div>
                        <div style="${lbl}">Estadía</div>
                        <div style="${card}">
                            <div style="${row}">
                                <span style="color:#666;">Fechas</span>
                                <span style="font-weight:500;">${artechiaPMS.formatDate(res.check_in)} → ${artechiaPMS.formatDate(res.check_out)}</span>
                            </div>
                            <div style="${row}">
                                <span style="color:#666;">Noches</span>
                                <span style="font-weight:500;">${res.nights}</span>
                            </div>
                            <div style="${rowL}">
                                <span style="color:#666;">Huéspedes</span>
                                <span style="font-weight:500;">${res.adults} adulto${res.adults != 1 ? 's' : ''} ${res.children > 0 ? ' / ' + res.children + ' niño' + (res.children != 1 ? 's' : '') : ''}</span>
                            </div>
                        </div>
                    </div>
                    <div>
                        <div style="${lbl}">Pagos</div>
                        <div style="${card}">
                            <div style="${row}">
                                <span style="color:#666;">Subtotal</span>
                                <span style="font-weight:600;">${artechiaPMS.formatPrice(grandTotal + Number(res.discount_total || 0))}</span>
                            </div>
                            ${parseFloat(res.discount_total) > 0 ? `<div style="${row}">
                                <span style="display:flex; align-items:center; gap:6px; color:#666;">Descuento <span style="background:#f3f0ff; color:#7c3aed; padding:1px 7px; border-radius:10px; font-size:10px; font-weight:700; border:1px solid #e0d5ff;">${res.coupon_code || 'Promo'}</span></span>
                                <span style="font-weight:600; color:#16a34a;">-${artechiaPMS.formatPrice(parseFloat(res.discount_total))}</span>
                            </div>
                            <div style="${row}">
                                <span style="color:#666;">Total</span>
                                <span style="font-weight:700;">${artechiaPMS.formatPrice(grandTotal)}</span>
                            </div>` : `<div style="${row}">
                                <span style="color:#666;">Total</span>
                                <span style="font-weight:700;">${artechiaPMS.formatPrice(grandTotal)}</span>
                            </div>`}
                            ${res.status === 'cancelled' ? (() => {
                                const payments = res.payments || [];
                                const grossPaid = payments.filter(p => Number(p.amount) > 0).reduce((s, p) => s + Number(p.amount), 0);
                                const refunded = Math.abs(payments.filter(p => Number(p.amount) < 0).reduce((s, p) => s + Number(p.amount), 0));
                                const penaltyRetained = Math.max(0, grossPaid - refunded);
                                return `
                                    <div style="${row}">
                                        <span style="color:#666;">Pagado originalmente</span>
                                        <span style="font-weight:500; color:#16a34a;">${artechiaPMS.formatPrice(grossPaid)}</span>
                                    </div>
                                    ${refunded > 0 ? `<div style="${row}">
                                        <span style="color:#666;">Devuelto</span>
                                        <span style="font-weight:500; color:#0073aa;">${artechiaPMS.formatPrice(refunded)}</span>
                                    </div>` : ''}
                                    ${penaltyRetained > 0 ? `<div style="${rowL}">
                                        <span style="color:#666;">Penalidad retenida</span>
                                        <span style="font-weight:700; color:#dc2626;">${artechiaPMS.formatPrice(penaltyRetained)}</span>
                                    </div>` : (grossPaid > 0 ? `<div style="${rowL}">
                                        <span style="color:#666;">Penalidad</span>
                                        <span style="font-weight:500; color:#16a34a;">Sin penalidad (devuelto todo)</span>
                                    </div>` : `<div style="${rowL}">
                                        <span style="color:#666;">Pagos</span>
                                        <span style="font-weight:500; color:#888;">Sin pagos registrados</span>
                                    </div>`)}
                                `;
                            })() : `
                            <div style="${row}">
                                <span style="color:#666;">Pagado</span>
                                <span style="font-weight:500; color:#16a34a;">${artechiaPMS.formatPrice(amountPaid)}</span>
                            </div>
                            <div style="${rowL}">
                                <span style="color:#666;">Debe</span>
                                <span style="font-weight:700; color:${balanceDue > 0 ? '#dc2626' : '#16a34a'};">${artechiaPMS.formatPrice(balanceDue)}</span>
                            </div>
                            `}
                        </div>
                        ${(res.payments || []).length > 0 ? `
                        <div style="margin-top:6px;">
                            <div style="font-size:10px; text-transform:uppercase; letter-spacing:0.5px; color:#888; margin-bottom:3px; font-weight:600;">Historial de Pagos</div>
                            <div style="background:#f8f9fa; border:1px solid #e9ecef; border-radius:6px; padding:6px 8px; max-height:120px; overflow-y:auto;">
                                ${(res.payments || []).map(p => {
                                    const pAmt = Number(p.amount);
                                    const isRefund = pAmt < 0;
                                    const isManual = p.gateway === 'manual' || p.pay_mode === 'manual';
                                    const pDate = p.created_at ? artechiaPMS.formatDate(p.created_at.substring(0, 10)) : '';
                                    const pNote = p.notes || '';
                                    const gwMap = { 'bank_transfer': 'Transferencia', 'cash': 'Efectivo', 'mercadopago': 'MercadoPago', 'credit_card': 'Tarjeta', 'debit_card': 'Débito', 'check': 'Cheque' };
                                    const gatewayLabel = isRefund ? 'Devolución' : (gwMap[p.gateway] || (p.gateway !== 'manual' ? p.gateway : ''));
                                    return `<div style="padding:4px 0; border-bottom:1px solid #f0f0f0; font-size:11px;">
                                        <div style="display:flex; justify-content:space-between; align-items:center;">
                                            <div>
                                                <span style="color:#888;">${pDate}</span>
                                                <span style="margin-left:4px; font-weight:500; color:${isRefund ? '#dc2626' : '#16a34a'};">${artechiaPMS.formatPrice(Math.abs(pAmt))}</span>
                                                ${isRefund ? '<span style="color:#dc2626; font-size:10px;"> (devolución)</span>' : ''}
                                                ${gatewayLabel ? `<span style="color:#555; margin-left:4px; font-size:10px; background:#f0f0f0; padding:1px 5px; border-radius:3px;">${gatewayLabel}</span>` : ''}
                                            </div>
                                            ${isManual ? '<button onclick="deletePayment(\'' + res.booking_code + '\', ' + p.id + ')" style="border:none; background:none; cursor:pointer; color:#dc2626; font-size:14px; padding:0 2px;" title="Eliminar pago">&times;</button>' : ''}
                                        </div>
                                        ${pNote ? `<div style="font-size:10px; color:#888; margin-top:2px; font-style:italic; padding-left:2px;">${pNote}</div>` : ''}
                                    </div>`;
                                }).join('')}
                            </div>
                        </div>` : ''}
                    </div>
                </div>

                <!-- Rooms + Extras Row -->
                <div style="display:grid; grid-template-columns:${(res.extras||[]).length > 0 ? '1fr 1fr' : '1fr'}; gap:12px; margin-bottom:12px;">
                    <div>
                        <div style="${lbl}">Habitaciones</div>
                        <div style="${card}">
                            ${(res.rooms||[]).length > 0 ? (res.rooms||[]).map(r => `
                                <div style="display:flex; justify-content:space-between; align-items:center; padding:3px 0;">
                                    <div>
                                        <strong style="font-size:12px;">${r.unit_name}</strong>
                                        <span style="font-size:11px; color:#888; margin-left:4px;">${r.room_type_name}</span>
                                    </div>
                                    <span style="font-size:12px; font-weight:500;">${artechiaPMS.formatPrice(r.subtotal)}</span>
                                </div>
                            `).join('') : '<div style="color:#999; font-size:12px;">Sin asignar</div>'}
                        </div>
                    </div>
                    ${(res.extras||[]).length > 0 ? `
                    <div>
                        <div style="${lbl}">Extras</div>
                        <div style="${card}">
                            ${(res.extras||[]).map(e => `
                                <div style="display:flex; justify-content:space-between; align-items:center; padding:3px 0;">
                                    <span style="font-size:12px;">${e.name} <span style="color:#888;">×${e.quantity}</span></span>
                                    <span style="font-size:12px; font-weight:500;">${artechiaPMS.formatPrice(e.subtotal)}</span>
                                </div>
                            `).join('')}
                        </div>
                    </div>` : ''}
                </div>

                <!-- Coupon / Special Requests -->
                ${res.special_requests ? `
                <div style="margin-bottom:12px;">
                    <div>
                        <div style="${lbl}">Solicitudes especiales</div>
                        <div style="${card}">
                            <span style="font-size:12px;">${res.special_requests}</span>
                        </div>
                    </div>
                </div>` : ''}

                <!-- Cancellation info -->
                ${res.status === 'cancelled' ? `
                <div style="margin-bottom:12px;">
                    <div style="${lbl}">Cancelación</div>
                    <div style="background:#fef2f2; border:1px solid #fecaca; border-radius:6px; padding:10px 12px; font-size:12px;">
                        ${res.cancelled_at ? '<div style="margin-bottom:2px;"><strong>Fecha:</strong> ' + res.cancelled_at + '</div>' : ''}
                        ${res.cancel_reason ? '<div><strong>Motivo:</strong> ' + res.cancel_reason + '</div>' : ''}
                        <div style="margin-top:8px; padding-top:8px; border-top:1px solid #fecaca;">
                            <button class="button" onclick="reactivateBooking('${res.booking_code}')" style="background:#16a34a; border-color:#16a34a; color:#fff; font-size:12px;">
                                ↩ Reactivar Reserva
                            </button>
                            <span style="font-size:11px; color:#888; margin-left:8px;">Vuelve a estado Confirmada</span>
                        </div>
                    </div>
                </div>` : ''}

                <!-- Notes -->
                ${rawNotes.trim() ? `
                <div>
                    <div style="${lbl}">Notas internas</div>
                    <div style="background:#fffbeb; border:1px solid #fde68a; border-radius:6px; padding:10px 12px; max-height:120px; overflow-y:auto;">
                        ${notesHtml}
                    </div>
                </div>` : ''}
            `;
        }).catch(err => {
            content.innerHTML = '<p style="color:red; text-align:center; padding:20px;">Error cargando detalle.</p>';
        });
    };

    // ── Delete Payment ──
    window.deletePayment = function(bookingCode, paymentId) {
        if (!confirm('¿Estás seguro de que querés eliminar este pago? Esta acción recalculará los montos de la reserva.')) return;
        api.delete(`admin/bookings/${bookingCode}/payment/${paymentId}`).then(res => {
            if (res.error) {
                artechiaPMS.toast.show('Error: ' + (res.message || res.error), 'error');
            } else {
                artechiaPMS.toast.show('Pago eliminado correctamente', 'success');
                viewBooking(bookingCode); // Refresh modal
                loadBookings(false); // Refresh table
            }
        }).catch(err => artechiaPMS.toast.show('Error eliminando pago', 'error'));
    };

    // ── Reactivate Booking ──
    window.reactivateBooking = function(bookingCode) {
        if (!confirm('¿Reactivar esta reserva cancelada? Volverá a estado Confirmada.')) return;
        api.post(`admin/bookings/${bookingCode}/reactivate`, { status: 'confirmed' }).then(res => {
            if (res.error) {
                artechiaPMS.toast.show('Error: ' + (res.message || res.error), 'error');
            } else {
                artechiaPMS.toast.show('Reserva reactivada', 'success');
                viewBooking(bookingCode);
                loadBookings(false);
            }
        }).catch(err => artechiaPMS.toast.show('Error reactivando reserva', 'error'));
    };

    // ── Change Booking Status ──
    window.changeBookingStatus = function(bookingCode) {
        const select = document.getElementById('status-change-select');
        if (!select) return;
        const newStatus = select.value;
        if (!confirm(`¿Cambiar el estado de la reserva a "${select.options[select.selectedIndex].text}"?`)) return;
        api.post(`admin/bookings/${bookingCode}/status`, { status: newStatus }).then(res => {
            if (res.error) {
                artechiaPMS.toast.show('Error: ' + (res.message || res.error), 'error');
            } else {
                artechiaPMS.toast.show('Estado actualizado', 'success');
                viewBooking(bookingCode);
                loadBookings(false);
            }
        }).catch(err => artechiaPMS.toast.show('Error cambiando estado', 'error'));
    };

    let confirmMaxAmount = 0;
    let confirmDepositAmount = 0;

    window.confirmBooking = function(code, total, deposit) {
        try {
        const modal = document.getElementById('confirm-modal');
        if (!modal) console.error('confirm-modal HTML element is completely missing!');
        
        document.getElementById('confirm-booking-code').value = code;
        
        const depositNum = Number(deposit) || 0;
        const totalNum = Number(total) || 0;
        confirmMaxAmount = totalNum;
        confirmDepositAmount = depositNum;
        
        // Show deposit info
        const depositLabel = document.getElementById('confirm-deposit-label');
        const depositAmount = document.getElementById('confirm-deposit-amount');
        const depositOption = document.getElementById('confirm-deposit-option');
        
        const depositTitle = document.getElementById('confirm-deposit-title');
        
        if (depositNum > 0 && depositNum < totalNum) {
            const pct = Math.round((depositNum / totalNum) * 100);
            depositTitle.textContent = `Registrar seña (${pct}%)`;
            depositLabel.textContent = 'Monto de la seña';
            depositAmount.textContent = artechiaPMS.formatPrice(depositNum);
            depositOption.style.display = 'block';
        } else if (totalNum > 0) {
            confirmDepositAmount = totalNum; // No deposit configured, use full total
            depositTitle.textContent = 'Registrar pago total';
            depositLabel.textContent = 'Monto total de la reserva';
            depositAmount.textContent = artechiaPMS.formatPrice(totalNum);
            depositOption.style.display = 'block';
        } else {
            depositOption.style.display = 'none';
        }
        
        const customInput = document.getElementById('confirm-custom-amount');
        customInput.value = '';
        customInput.max = totalNum;
        customInput.placeholder = `Máx: ${artechiaPMS.formatPrice(totalNum)}`;
        
        // Force cap on input
        customInput.oninput = () => {
            let val = parseFloat(customInput.value);
            if (val > totalNum) {
                customInput.value = totalNum;
            }
        };

        modal.style.display = 'flex';
        } catch(err) { console.error('confirmBooking error:', err); artechiaPMS.toast.show('Error interno: ' + err.message, 'error'); }
    };
    
    window.doConfirmBooking = function(mode) {
        const code = document.getElementById('confirm-booking-code').value;
        const modal = document.getElementById('confirm-modal');
        
        let paymentAmount = 0;
        if (mode === 'deposit') {
            paymentAmount = confirmDepositAmount || 0;
        } else if (mode === 'custom') {
            paymentAmount = parseFloat(document.getElementById('confirm-custom-amount').value) || 0;
            if (paymentAmount <= 0) {
                artechiaPMS.toast.show('Ingresá un monto válido', 'warning');
                return;
            }
            if (paymentAmount > confirmMaxAmount) {
                artechiaPMS.toast.show('El monto no puede superar el total de la reserva', 'error');
                return;
            }
        } else {
            // mode === 'none' — but check if user entered a custom amount anyway
            const customVal = parseFloat(document.getElementById('confirm-custom-amount').value) || 0;
            if (customVal > 0) {
                if (!confirm('Hay un monto ingresado. ¿Confirmar SIN registrar ese pago?')) return;
            }
        }
        
        modal.style.display = 'none';
        
        const body = { payment_amount: paymentAmount };
        api.post(`admin/bookings/${code}/confirm`, body).then(res => {
            if (res.error) artechiaPMS.toast.show('Error: ' + (res.message || res.error || 'Unknown error'), 'error');
            else {
                artechiaPMS.toast.show('Reserva confirmada', 'success');
                pendingFlashCode = code;
                loadBookings();
            }
        }).catch(() => artechiaPMS.toast.show('Error de conexión', 'error'));
    };

    window.cancelBooking = function(code) {
        try {
        currentBookingId = code;
        document.getElementById('cancel-booking-id').value = code;
        document.getElementById('cancel-reason').value = '';
        
        // Fetch the booking details via API to get the exact amount paid.
        const refundInput = document.getElementById('cancel-refund-amount');
        refundInput.value = '';
        refundInput.max = '';
        refundInput.placeholder = 'Calculando...';
        
        document.getElementById('cancel-modal').style.display = 'flex';
        
        api.get(`admin/bookings/${code}`).then(res => {
            const amountPaid = parseFloat(res.amount_paid) || 0;
            
            // Hide penalty/refund section entirely if nothing was paid
            const penaltyRefundBox = document.getElementById('cancel-penalty-refund-box');
            if (amountPaid <= 0) {
                if (penaltyRefundBox) penaltyRefundBox.style.display = 'none';
                refundInput.value = '';
                refundInput.placeholder = '$0.00';
                return;
            }
            if (penaltyRefundBox) penaltyRefundBox.style.display = 'block';
            
            if (amountPaid > 0) {
                // Now query for the penalty.
                api.get(`admin/bookings/${code}/penalty`).then(resPenalty => {
                    let penaltyAmount = parseFloat(resPenalty.penalty_amount) || 0;
                    


                    const container = document.getElementById('cancel-penalty-container');
                    const policyInfo = document.getElementById('cancel-policy-info');
                    const checkbox = document.getElementById('cancel-apply-penalty');
                    const suggestedText = document.getElementById('cancel-suggested-amount-text');

                    // Always show container to allow manual penalty entry
                    container.style.display = 'block';
                    
                    // Pre-fill the input with policy penalty safely up to what they paid
                    penaltyAmount = Math.min(penaltyAmount, amountPaid);
                    let suggestedPenalty = penaltyAmount;
                    
                    checkbox.checked = true;
                    suggestedText.textContent = `(${artechiaPMS.formatPrice(suggestedPenalty)})`;
                    
                    if (resPenalty.policy) {
                        let p = resPenalty.policy;
                        let infoText = '';
                        if (p.cancellation_type === 'custom') {
                            infoText = 'Reserva con <strong>tarifa personalizada</strong>. Se sugiere retener el monto total pagado como penalidad.';
                        } else if (p.cancellation_type === 'non_refundable') {
                            infoText = 'Esta reserva tiene una tarifa <strong>No Reembolsable</strong>.';
                        } else if (p.cancellation_type === 'flexible' || p.is_refundable) {
                            const days = parseInt(p.cancellation_deadline_days) || 0;
                            const penType = p.penalty_type || 'none';
                            
                            if (penType === 'none' || penaltyAmount <= 0) {
                                infoText = '<strong>Cancelación gratuita</strong>, sin penalidad.';
                            } else {
                                const deadlineText = days > 0 
                                    ? `<strong>${days} días previos al check-in</strong>` 
                                    : '<strong>hasta el día del check-in</strong>';
                                infoText = `Tarifa Flexible. Plazo de cancelación gratuita: ${deadlineText}.`;
                                
                                let penaltyDesc = '';
                                if (penType === '100') penaltyDesc = '100% del total';
                                else if (penType === '50') penaltyDesc = '50% del total';
                                else if (penType === '1_night' || penType === 'first_night') penaltyDesc = 'el precio de la 1° noche';
                                
                                if (penaltyDesc) {
                                    infoText += ` Si cancela después del plazo, se cobrará <strong>${penaltyDesc}</strong>.`;
                                }
                            }
                        }
                        policyInfo.innerHTML = infoText;
                        policyInfo.style.display = infoText ? 'block' : 'none';
                    } else {
                        policyInfo.style.display = 'none';
                    }

                    const updateRefundLimits = () => {
                        let appliedPenalty = checkbox.checked ? suggestedPenalty : 0;
                        
                        // Prevent penalty from exceeding paid amount
                        if (appliedPenalty > amountPaid) {
                            appliedPenalty = amountPaid;
                        }

                        let maxRefund = Math.max(0, amountPaid - appliedPenalty);
                        
                        refundInput.max = maxRefund;
                        refundInput.placeholder = `Máximo a devolver: ${artechiaPMS.formatPrice(maxRefund)}`;
                        
                        let val = parseFloat(refundInput.value);
                        if (val > maxRefund) refundInput.value = maxRefund;
                    };

                    const refundContainer = document.getElementById('cancel-refund-container');
                    
                    const updateVisibility = () => {
                        const isChecked = checkbox.checked;
                        if (refundContainer) {
                            refundContainer.style.display = isChecked ? 'none' : 'block';
                        }
                    };

                    checkbox.onchange = () => {
                        updateVisibility();
                        if (checkbox.checked) {
                            refundInput.value = ''; // Always clear refund if they go back to suggested penalty (which hides refund input)
                        }
                        updateRefundLimits();
                    };
                    
                    updateVisibility();
                    updateRefundLimits();
                    
                    // Prevent manual numeric input larger than allowed max
                    refundInput.oninput = () => {
                        let val = parseFloat(refundInput.value);
                        let maxVal = parseFloat(refundInput.max) || 0;
                        if (val > maxVal) {
                            refundInput.value = maxVal;
                        }
                    };
                }).catch(() => {
                    // Fallback if penalty calc fails
                    refundInput.max = amountPaid;
                    refundInput.placeholder = `Máximo a devolver: ${artechiaPMS.formatPrice(amountPaid)}`;
                });
            } else {
                document.getElementById('cancel-penalty-container').style.display = 'none';
                refundInput.placeholder = 'No hay pagos registrados';
                refundInput.max = 0;
                refundInput.readOnly = true;
            }
        });

        setTimeout(() => document.getElementById('cancel-reason').focus(), 100);
        } catch(err) { console.error('cancelBooking error:', err); artechiaPMS.toast.show('Error interno: ' + err.message, 'error'); }
    };

    let paymentMaxBalance = 0;

    window.recordPayment = function(code, balanceVal) {
        try {
        currentBookingId = code;
        const amountInput = document.getElementById('payment-amount');
        document.getElementById('payment-booking-id').value = code;
        amountInput.value = '';
        document.getElementById('payment-note').value = ''; 
        document.getElementById('payment-method').value = 'Efectivo';
        
        const balance = parseFloat(balanceVal) || 0;
        paymentMaxBalance = balance;
        
        if (balance > 0) {
            amountInput.max = balance;
            amountInput.placeholder = `Máx: ${artechiaPMS.formatPrice(balance)}`;
            
            // Populate Green card
            document.getElementById('payment-balance-amount').textContent = artechiaPMS.formatPrice(balance);
            document.getElementById('payment-balance-amount-raw').value = balance;
            document.getElementById('payment-balance-option').style.display = 'block';

            // Force cap on input
            amountInput.oninput = () => {
                let val = parseFloat(amountInput.value);
                if (val > balance) {
                    amountInput.value = balance;
                }
            };
        } else {
            document.getElementById('payment-balance-option').style.display = 'none';
        }

        document.getElementById('payment-modal').style.display = 'flex';
        setTimeout(() => amountInput.focus(), 100);
        } catch(err) { console.error('recordPayment error:', err); artechiaPMS.toast.show('Error interno: ' + err.message, 'error'); }
    };

    window.deleteBooking = function(code) {
        if (!confirm('¿Seguro que desea eliminar permanentemente esta reserva? Esta acción no se puede deshacer.')) return;
        api.delete(`admin/bookings/${code}`).then(res => {
            if (res.error) artechiaPMS.toast.show('Error: ' + (res.message || 'Unknown'), 'error');
            else {
                loadBookings();
            }
        }).catch(() => artechiaPMS.toast.show('Error de conexión', 'error'));
    };

    /* ── Manual Booking Logic ── */
    let loadedSetup = false;
    const newBookingModal = document.getElementById('new-booking-modal');
    const formNewBooking  = document.getElementById('form-new-booking');
    
    // Global setup data cache
    let setupData = {
        properties: [],
        roomTypes: [],
        ratePlans: []
    };

    async function loadSetup() {
        if (loadedSetup) return;
        loadedSetup = true;

        console.log('Artechia: Loading setup data...');
        
        const fetchPart = async (endpoint, label) => {
            try {
                const res = await api.get(endpoint);
                console.log(`Artechia ${label} Payload:`, res);
                return Array.isArray(res) ? res : [];
            } catch (err) {
                console.error(`Artechia ${label} Error:`, err);
                return [];
            }
        };

        // Fetch all in parallel but handle independently
        const [props, types, plans, units] = await Promise.all([
            fetchPart('admin/setup/properties?include_demo=1', 'Properties'),
            fetchPart('admin/setup/room-types', 'Room Types'),
            fetchPart('admin/setup/rate-plans', 'Rate Plans'),
            fetchPart('admin/setup/room-units', 'Room Units')
        ]);

        setupData = { properties: props, roomTypes: types, ratePlans: plans, roomUnits: units };

        populateProperties(props);
        filterAndPopulateSetup(); // Initial population
    }

    function populateProperties(props) {
        const propFilter = document.getElementById('filter-property');
        const newPropSelect = document.getElementById('new-property-id');
        
        if (!props || props.length === 0) {
            newPropSelect.innerHTML = '<?php esc_html_e( "No hay propiedades activas", "artechia-pms" ); ?>';
            return;
        }

        const propHtml = props.map(p => `<option value="${p.id}">${p.name}${p.is_demo ? ' (Demo)' : ''}</option>`).join('');
        
        // Main list filter
        if (propFilter) {
            propFilter.innerHTML = '<option value=""><?php esc_html_e( "Todas las propiedades", "artechia-pms" ); ?></option>' + propHtml;
        }

        // Modal select
        newPropSelect.innerHTML = propHtml;

        // Auto-select if only one
        if (props.length === 1) {
            newPropSelect.value = props[0].id;
        }
    }

    function filterAndPopulateSetup() {
        const propertyId = parseInt(document.getElementById('new-property-id').value);
        const typeSelect  = document.getElementById('new-room-type-id');
        const planSelect  = document.getElementById('new-rate-plan-id');
        const unitSelect  = document.getElementById('new-room-unit-id');

        // Filter types
        const filteredTypes = propertyId 
            ? setupData.roomTypes.filter(t => t.property_id === propertyId)
            : setupData.roomTypes;

        typeSelect.innerHTML = filteredTypes.length 
            ? filteredTypes.map(t => `<option value="${t.id}">${t.name}</option>`).join('')
            : '<?php esc_html_e( "Sin tipos de habitación", "artechia-pms" ); ?>';

        if (filteredTypes.length === 1) typeSelect.value = filteredTypes[0].id;

        // Filter plans
        const filteredPlans = propertyId 
            ? setupData.ratePlans.filter(p => p.property_id === propertyId)
            : setupData.ratePlans;

        planSelect.innerHTML = (filteredPlans.length
            ? filteredPlans.map(p => `<option value="${p.id}">${p.name}</option>`).join('')
            : '') + '<option value="custom">✏️ Personalizado</option>';

        if (filteredPlans.length === 1) planSelect.value = filteredPlans[0].id;

        // Reset custom price visibility
        const customRow = document.getElementById('custom-price-row');
        if (customRow) customRow.style.display = 'none';

        // Filter units by selected room type
        populateNewUnitSelect();
    }

    function populateNewUnitSelect() {
        const roomTypeId = parseInt(document.getElementById('new-room-type-id').value);
        const unitSelect = document.getElementById('new-room-unit-id');
        const filteredUnits = roomTypeId
            ? (setupData.roomUnits || []).filter(u => u.room_type_id === roomTypeId)
            : (setupData.roomUnits || []);

        unitSelect.innerHTML = '<option value="">Auto-asignar</option>' +
            filteredUnits.map(u => `<option value="${u.id}">${u.name} (${u.room_type_name})</option>`).join('');
    }

    // Update RT/RP/Units when property changes in modal
    document.getElementById('new-property-id').onchange = filterAndPopulateSetup;
    document.getElementById('new-room-type-id').onchange = populateNewUnitSelect;

    // Handle Submission
    if (formNewBooking) {
        formNewBooking.onsubmit = async function(e) {
            e.preventDefault();
            
            const btn = formNewBooking.querySelector('button[type="submit"]');
            const originalText = btn.textContent;
            
            btn.disabled = true;
            btn.textContent = 'Creando...';

            const formData = new FormData(formNewBooking);
            const data = Object.fromEntries(formData.entries());

            const errorBanner = document.getElementById('new-booking-error');
            errorBanner.style.display = 'none';
            errorBanner.textContent = '';

            try {
                const res = await api.post('admin/bookings/manual-create', data);
                
                if (res.error || res.code) {
                    let msg = res.message || res.code || 'Error desconocido';
                    if (res.error === 'GUEST_BLACKLISTED') {
                        const reason = msg.replace('GUEST_BLACKLISTED:', '').trim();
                        msg = 'El huésped está en la LISTA NEGRA. Razón: ' + (reason || 'No especificada');
                    }
                    errorBanner.textContent = msg;
                    errorBanner.style.display = 'block';
                    newBookingModal.scrollTo({ top: 0, behavior: 'smooth' });
                } else {
                    // Check for success flag or ID (standardized to ok/booking_id)
                    if (res.success || res.ok || res.id || res.booking_id) {
                        newBookingModal.style.display = 'none';
                        formNewBooking.reset();
                         // Reset payment fields visibility
                        document.getElementById('payment-options-container').style.display = 'none';
                        const pmw = document.getElementById('new-payment-method-wrapper');
                        if (pmw) pmw.style.display = 'none';
                        const pph = document.getElementById('payment-options-placeholder');
                        if (pph) pph.style.display = 'block';
                        
                        // Refresh grid to show new booking with correct Balance Due
                        loadBookings();
                        // alert('Reserva creada exitosamente');
                    } else {
                         console.error('Unexpected response:', res);
                         artechiaPMS.toast.show('Error: Respuesta inesperada del servidor', 'error');
                         loadBookings();
                    }
                }
            } catch (err) {
                console.error('Artechia: Manual Booking Error', err);
                artechiaPMS.toast.show('Error de conexión o de servidor.', 'error');
            } finally {
                btn.disabled = false;
                btn.textContent = originalText;
            }
        };
    }

    document.getElementById('btn-new-booking').onclick = function() {
        if (formNewBooking) formNewBooking.reset();
        
        const previewEl = document.getElementById('new-quote-preview');
        if (previewEl) previewEl.textContent = '';
        
        const paymentContainer = document.getElementById('payment-options-container');
        if (paymentContainer) paymentContainer.style.display = 'none';
        const pmw = document.getElementById('new-payment-method-wrapper');
        if (pmw) pmw.style.display = 'none';
        const pph = document.getElementById('payment-options-placeholder');
        if (pph) pph.style.display = 'block';

        loadSetup();
        const errorBanner = document.getElementById('new-booking-error');
        if (errorBanner) {
            errorBanner.style.display = 'none';
            errorBanner.textContent = '';
        }
        newBookingModal.style.display = 'flex';
    };

    // Events
    document.getElementById('btn-filter').onclick = () => { currentPage = 1; loadBookings(); };
    document.getElementById('btn-reset').onclick = () => {
        document.querySelectorAll('.artechia-filters input, .artechia-filters select').forEach(el => el.value = '');
        currentPage = 1;
        loadBookings();
    };

    /* ── Modal Forms Handlers ── */
    const formCancel = document.getElementById('form-cancel-booking');
    if (formCancel) {
        formCancel.onsubmit = async function(e) {
            e.preventDefault();
            const btn = formCancel.querySelector('button[type="submit"]');
            const originalText = btn.textContent;
            btn.disabled = true;
            btn.textContent = 'Procesando...';

            const code = document.getElementById('cancel-booking-id').value;
            const reason = document.getElementById('cancel-reason').value;
            const refundInput = document.getElementById('cancel-refund-amount');
            const refund_amount = refundInput ? (parseFloat(refundInput.value) || 0) : 0;

            try {
                const res = await api.post(`admin/bookings/${code}/cancel`, { 
                    reason: reason,
                    refund_amount: refund_amount 
                });
                if (!res.ok && !res.success) { // Handle both common WP response types
                     const msg = res.message || res.data || 'Error desconocido';
                     artechiaPMS.toast.show('Error: ' + msg, 'error');
                } else {
                    document.getElementById('cancel-modal').style.display = 'none';
                    pendingFlashCode = code;
                    loadBookings();
                }
            } catch (err) {
                console.error(err);
                artechiaPMS.toast.show('Error de conexión', 'error');
            } finally {
                btn.disabled = false;
                btn.textContent = originalText;
            }
        };
    }

    const formPayment = document.getElementById('form-payment-booking');
    if (formPayment) {
        formPayment.onsubmit = async function(e) {
            e.preventDefault();
            const btn = formPayment.querySelector('button[type="submit"]');
            const originalText = btn.textContent;
            btn.disabled = true;
            btn.textContent = 'Procesando...';

            const code = document.getElementById('payment-booking-id').value;
            const amount = parseFloat(document.getElementById('payment-amount').value);
            const method = document.getElementById('payment-method').value;
            const details = document.getElementById('payment-note').value;
            const note = details;

            if (isNaN(amount) || amount <= 0) {
                artechiaPMS.toast.show('Monto inválido', 'warning');
                btn.disabled = false;
                btn.textContent = originalText;
                return;
            }

            if (paymentMaxBalance > 0 && amount > (paymentMaxBalance + 0.01)) {
                artechiaPMS.toast.show(`El monto (${artechiaPMS.formatPrice(amount)}) no puede superar el saldo pendiente (${artechiaPMS.formatPrice(paymentMaxBalance)}).`, 'warning');
                btn.disabled = false;
                btn.textContent = originalText;
                return;
            }

            try {
                const res = await api.post(`admin/bookings/${code}/payment`, { amount, note, method });
                 if (res.error) {
                    artechiaPMS.toast.show('Error: ' + (res.message || 'Unknown'), 'error');
                } else {
                    document.getElementById('payment-modal').style.display = 'none';
                    pendingFlashCode = code;
                    loadBookings();
                }
            } catch(err) {
                console.error(err);
                artechiaPMS.toast.show('Error de conexión', 'error');
            } finally {
                btn.disabled = false;
                btn.textContent = originalText;
            }
        };
    }

    // Init: Check URL params for deep linking
    const urlParams = new URLSearchParams(window.location.search);
    const searchParam = urlParams.get('search') || urlParams.get('booking_code');
    if (searchParam) {
        document.getElementById('filter-search').value = searchParam;
    }

    loadSetup(); // Initial load for filters
    loadBookings();

    // Countdown Timer for Locks & Auto-Cleanup
    setInterval(() => {
        document.querySelectorAll('.lock-timer').forEach(el => {
            const expires = new Date(el.dataset.expires).getTime();
            const now = new Date().getTime();
            const diff = Math.max(0, Math.floor((expires - now) / 1000));
            
            if (diff <= 0) {
                el.textContent = 'Hold Expirado';
                el.style.color = '#d63638';
                el.classList.remove('pulse-slow');

                // Auto-delete for 'hold' bookings (NOT for locks which are handled by CRON)
                const code = el.dataset.code;
                if (code && code !== 'HOLD' && !el.dataset.deleting) {
                    el.dataset.deleting = 'true';
                    el.textContent = 'Eliminando...';
                    api.delete(`admin/bookings/${code}`).then(() => {
                        loadBookings();
                    }).catch(err => {
                        console.error('Auto-delete failed', err);
                        el.dataset.deleting = '';
                    });
                }
            } else {
                const minutes = Math.floor(diff / 60);
                const seconds = diff % 60;
                el.textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;
                
                // Pulse effect if under 2 minutes
                if (diff < 120) {
                    el.classList.add('pulse-slow');
                    el.style.color = '#d63638';
                    el.style.fontWeight = 'bold';
                } else {
                    el.style.color = '#e67e22';
                    el.style.fontWeight = 'normal';
                }
            }
        });
    }, 1000);

    // Auto-Refresh every 30 seconds
    setInterval(() => {
        // Only refresh if no modal is active
        const modals = ['new-booking-modal', 'cancel-modal', 'payment-modal', 'confirm-modal'];
        const anyModalOpen = modals.some(id => {
            const el = document.getElementById(id);
            return el && el.style.display === 'flex';
        });

        if (!anyModalOpen) {
            console.log('Artechia: Auto-refreshing bookings...');
            loadBookings(false); // pass false to avoid 'Loading' flash
        }
    }, 30000);
    console.log('Artechia: Reservations script initialized.');
});
</script>
<style>
.artechia-badge { padding: 3px 6px; border-radius: 3px; font-size: 11px; font-weight: 500; color: #fff; background: #666; display: inline-block; line-height: 1; }
.badge-success { background: #00a32a; }
.badge-warning { background: #ffb900; color: #333; }
.badge-danger { background: #d63638; }
.badge-info { background: #0073aa; }
.badge-gray { background: #999; }
.lock-timer { font-family: monospace; font-size: 12px; }
.pulse-slow { animation: pulse 1.5s infinite; }
@keyframes pulse {
    0% { opacity: 1; }
    50% { opacity: 0.5; }
    100% { opacity: 1; }
}
</style>
