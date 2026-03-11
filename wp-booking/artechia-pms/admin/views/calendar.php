<?php
/**
 * Admin View: Calendar (Tape Chart).
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
?>

<div class="wrap">
    <h1 class="wp-heading-inline"><?php esc_html_e( 'Calendario de Reservas', 'artechia-pms' ); ?></h1>
    <hr class="wp-header-end">

    <div id="artechia-calendar-app" class="artechia-calendar-wrapper">
        <!-- Toolbar -->
        <div class="calendar-toolbar">
            <div class="toolbar-left">
                <button id="ac-today" class="button">Hoy</button>
                <div class="button-group">
                    <button id="ac-prev" class="button"><span class="dashicons dashicons-arrow-left-alt2"></span></button>
                    <button id="ac-next" class="button"><span class="dashicons dashicons-arrow-right-alt2"></span></button>
                </div>
                <div class="calendar-legend">
                    <div class="legend-item"><span class="legend-color" style="background:var(--color-confirmed)"></span> Confirmada</div>
                    <div class="legend-item"><span class="legend-color" style="background:var(--color-pending)"></span> Pendiente</div>
                    <div class="legend-item"><span class="legend-color" style="background:var(--color-checked-in)"></span> In-House</div>
                    <div class="legend-item"><span class="legend-color" style="border:1px solid #ddd; background:#f9fafb;"></span> Pasado</div>
                    <div class="legend-item"><span class="legend-color" style="background:#eff6ff; border:1px solid #bfdbfe;"></span> Hoy</div>
                </div>
            </div>
            <div class="toolbar-right">
                <input type="text" class="calendar-search-input" placeholder="Buscar reserva, huésped, email...">
                <select id="ac-filter-type" class="calendar-filter-select">
                    <option value=""><?php esc_html_e( 'Todos los tipos', 'artechia-pms' ); ?></option>
                </select>
                <select id="ac-filter-status" class="calendar-filter-select">
                    <option value=""><?php esc_html_e( 'Todos los estados', 'artechia-pms' ); ?></option>
                    <option value="confirmed">Confirmada</option>
                    <option value="pending">Pendiente</option>
                    <option value="checked_in">In-House</option>
                    <option value="checked_out">Finalizada</option>
                </select>
            </div>
        </div>

        <!-- Main Container -->
        <div class="calendar-container">
            <!-- Sidebar: Room Units -->
            <div id="ac-sidebar" class="calendar-sidebar">
                <!-- JS Populated -->
            </div>

            <!-- Grid: Dates & Bookings -->
            <div id="ac-grid-container" class="calendar-grid-scroll">
                <!-- JS Populated -->
            </div>
        </div>
    </div>

    <!-- Booking Modal -->
    <div id="ac-booking-modal" class="ac-modal">
        <div class="ac-modal-content">
            <span class="ac-close">&times;</span>
            <div id="ac-modal-body">
                <!-- Content -->
            </div>
        </div>
    </div>
</div>
