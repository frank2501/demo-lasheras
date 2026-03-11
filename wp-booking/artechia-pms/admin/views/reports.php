<?php
/**
 * Reports View – redesigned.
 * IMPORTANT: Tab logic is handled by admin/assets/js/reports.js
 * which selects `.nav-tab[data-tab]` and toggles `.artechia-tab-content--active`.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
?>

<div class="wrap artechia-wrap artechia-reports-page">

    <!-- Tabs (reports.js looks for .nav-tab with data-tab) -->
    <div class="rpt-tab-bar">
        <a href="#dashboard" class="nav-tab rpt-tab nav-tab-active" data-tab="dashboard">📊 <?php esc_html_e( 'Resumen', 'artechia-pms' ); ?></a>
        <a href="#occupancy" class="nav-tab rpt-tab" data-tab="occupancy">🛏️ <?php esc_html_e( 'Ocupación', 'artechia-pms' ); ?></a>
        <a href="#financial" class="nav-tab rpt-tab" data-tab="financial">💰 <?php esc_html_e( 'Financiero', 'artechia-pms' ); ?></a>
        <a href="#sources" class="nav-tab rpt-tab" data-tab="sources">🌐 <?php esc_html_e( 'Fuentes', 'artechia-pms' ); ?></a>
    </div>

    <!-- ============================================================ -->
    <!-- TAB: DASHBOARD                                                 -->
    <!-- ============================================================ -->
    <div class="artechia-tab-content artechia-tab-content--active" id="tab-dashboard" style="margin-top:20px;">

        <!-- Dashboard Cards (populated by reports.js renderCards) -->
        <div class="artechia-cards" id="dashboard-cards" style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:12px; margin-bottom:20px;">
            <div class="rpt-card-skeleton" style="background:#fff; border:1px solid #e5e7eb; border-radius:10px; padding:20px; min-height:80px;">
                <div style="width:60%; height:12px; background:#f1f5f9; border-radius:4px; margin-bottom:12px;"></div>
                <div style="width:40%; height:24px; background:#f1f5f9; border-radius:4px;"></div>
            </div>
            <div class="rpt-card-skeleton" style="background:#fff; border:1px solid #e5e7eb; border-radius:10px; padding:20px; min-height:80px;">
                <div style="width:60%; height:12px; background:#f1f5f9; border-radius:4px; margin-bottom:12px;"></div>
                <div style="width:40%; height:24px; background:#f1f5f9; border-radius:4px;"></div>
            </div>
            <div class="rpt-card-skeleton" style="background:#fff; border:1px solid #e5e7eb; border-radius:10px; padding:20px; min-height:80px;">
                <div style="width:60%; height:12px; background:#f1f5f9; border-radius:4px; margin-bottom:12px;"></div>
                <div style="width:40%; height:24px; background:#f1f5f9; border-radius:4px;"></div>
            </div>
            <div class="rpt-card-skeleton" style="background:#fff; border:1px solid #e5e7eb; border-radius:10px; padding:20px; min-height:80px;">
                <div style="width:60%; height:12px; background:#f1f5f9; border-radius:4px; margin-bottom:12px;"></div>
                <div style="width:40%; height:24px; background:#f1f5f9; border-radius:4px;"></div>
            </div>
        </div>

        <!-- Dashboard Charts -->
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
            <div class="rpt-chart-panel">
                <div class="rpt-chart-header">
                    <span>📈</span>
                    <h3><?php esc_html_e( 'Ocupación (últimos 30 días)', 'artechia-pms' ); ?></h3>
                </div>
                <div style="padding:16px;">
                    <canvas id="chart-occupancy" height="250"></canvas>
                </div>
            </div>
            <div class="rpt-chart-panel">
                <div class="rpt-chart-header">
                    <span>💰</span>
                    <h3><?php esc_html_e( 'Ingresos (últimos 30 días)', 'artechia-pms' ); ?></h3>
                </div>
                <div style="padding:16px;">
                    <canvas id="chart-revenue" height="250"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- TAB: OCCUPANCY                                                 -->
    <!-- ============================================================ -->
    <div class="artechia-tab-content" id="tab-occupancy" style="margin-top:20px;">
        <div class="rpt-filters">
            <div class="rpt-filter-group">
                <label>Desde</label>
                <input type="date" id="occ-start" value="<?php echo date('Y-m-01'); ?>">
            </div>
            <div class="rpt-filter-group">
                <label>Hasta</label>
                <input type="date" id="occ-end" value="<?php echo date('Y-m-t'); ?>">
            </div>
            <div class="rpt-filter-group">
                <label>Tipo</label>
                <select id="occ-room-type">
                    <option value=""><?php esc_html_e( 'Todos los Tipos', 'artechia-pms' ); ?></option>
                    <?php
                    if ( class_exists( '\Artechia\PMS\Repositories\RoomTypeRepository' ) ) {
                        $prop_repo = new \Artechia\PMS\Repositories\PropertyRepository();
                        $rt_repo = new \Artechia\PMS\Repositories\RoomTypeRepository();
                        $property = $prop_repo->get_default();
                        $room_types = $rt_repo->all( [ 'where' => [ 'property_id' => $property['id'] ] ] );
                        foreach( $room_types as $rt ) {
                            echo '<option value="' . esc_attr( $rt['id'] ) . '">' . esc_html( $rt['name'] ) . '</option>';
                        }
                    }
                    ?>
                </select>
            </div>
            <button class="button button-primary" id="btn-refresh-occ" style="height:36px; line-height:34px; border-radius:6px; align-self:flex-end;">🔍 <?php esc_html_e( 'Filtrar', 'artechia-pms' ); ?></button>
            <a href="#" class="button" id="btn-export-occ" target="_blank" style="height:36px; line-height:34px; border-radius:6px; align-self:flex-end;">📥 <?php esc_html_e( 'Exportar CSV', 'artechia-pms' ); ?></a>
        </div>

        <!-- Summary stats (populated by reports.js) -->
        <div class="artechia-stats-row" id="occ-summary" style="display:grid; grid-template-columns:repeat(auto-fit, minmax(160px, 1fr)); gap:12px; margin-bottom:16px;">
        </div>
        
        <!-- Breakdown Table -->
        <div class="rpt-chart-panel">
            <div class="rpt-chart-header">
                <span>🛏️</span>
                <h3><?php esc_html_e( 'Desglose por Tipo de Habitación', 'artechia-pms' ); ?></h3>
            </div>
            <div style="padding:0;">
                <table class="wp-list-table widefat fixed striped artechia-table" id="table-occupancy-breakdown">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Tipo de Habitación', 'artechia-pms' ); ?></th>
                            <th style="width:15%;"><?php esc_html_e( 'Ocupación', 'artechia-pms' ); ?></th>
                            <th style="width:15%;"><?php esc_html_e( 'Noches Vendidas', 'artechia-pms' ); ?></th>
                            <th style="width:12%;"><?php esc_html_e( 'ADR', 'artechia-pms' ); ?></th>
                            <th style="width:15%;"><?php esc_html_e( 'Ingresos (Est.)', 'artechia-pms' ); ?></th>
                        </tr>
                    </thead>
                    <tbody><!-- JS --></tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- TAB: FINANCIAL                                                 -->
    <!-- ============================================================ -->
    <div class="artechia-tab-content" id="tab-financial" style="margin-top:20px;">
        <div class="rpt-filters">
            <div class="rpt-filter-group">
                <label>Desde</label>
                <input type="date" id="fin-start" value="<?php echo date('Y-m-01'); ?>">
            </div>
            <div class="rpt-filter-group">
                <label>Hasta</label>
                <input type="date" id="fin-end" value="<?php echo date('Y-m-t'); ?>">
            </div>
            <button class="button button-primary" id="btn-refresh-fin" style="height:36px; line-height:34px; border-radius:6px; align-self:flex-end;">🔍 <?php esc_html_e( 'Filtrar', 'artechia-pms' ); ?></button>
            <a href="#" class="button" id="btn-export-fin" target="_blank" style="height:36px; line-height:34px; border-radius:6px; align-self:flex-end;">📥 <?php esc_html_e( 'Exportar CSV', 'artechia-pms' ); ?></a>
        </div>

        <!-- Financial Summary Cards -->
        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)); gap:12px; margin-bottom:16px;">
            <div style="background:#fff; border:1px solid #e5e7eb; border-radius:10px; padding:16px 20px;">
                <div style="font-size:11px; color:#94a3b8; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:6px;">💰 <?php esc_html_e( 'Total Recaudado', 'artechia-pms' ); ?></div>
                <div id="fin-total-collected" style="font-size:24px; font-weight:700; color:#1e293b;">$0.00</div>
            </div>
            <div style="background:#fff; border:1px solid #e5e7eb; border-radius:10px; padding:16px 20px;">
                <div style="font-size:11px; color:#94a3b8; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:6px;">🏠 <?php esc_html_e( 'Alojamiento (Est.)', 'artechia-pms' ); ?></div>
                <div id="fin-total-accom" style="font-size:24px; font-weight:700; color:#16a34a;">$0.00</div>
            </div>
            <div style="background:#fff; border:1px solid #e5e7eb; border-radius:10px; padding:16px 20px;">
                <div style="font-size:11px; color:#94a3b8; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:6px;">🎁 <?php esc_html_e( 'Extras (Est.)', 'artechia-pms' ); ?></div>
                <div id="fin-total-extras" style="font-size:24px; font-weight:700; color:#f59e0b;">$0.00</div>
            </div>
        </div>

        <!-- Financial Breakdown Table -->
        <div class="rpt-chart-panel">
            <div class="rpt-chart-header">
                <span>💳</span>
                <h3><?php esc_html_e( 'Desglose por Pasarela/Método', 'artechia-pms' ); ?></h3>
            </div>
            <div style="padding:0;">
                <table class="wp-list-table widefat fixed striped artechia-table" id="table-financial">
                    <thead>
                        <tr>
                            <th style="width:30%;"><?php esc_html_e( 'Método de Pago', 'artechia-pms' ); ?></th>
                            <th><?php esc_html_e( 'Vía', 'artechia-pms' ); ?></th>
                            <th style="width:15%;"><?php esc_html_e( 'Transacciones', 'artechia-pms' ); ?></th>
                            <th style="width:20%;"><?php esc_html_e( 'Total', 'artechia-pms' ); ?></th>
                        </tr>
                    </thead>
                    <tbody><!-- JS --></tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- TAB: SOURCES                                                   -->
    <!-- ============================================================ -->
    <div class="artechia-tab-content" id="tab-sources" style="margin-top:20px;">
        <div class="rpt-filters">
            <div class="rpt-filter-group">
                <label>Desde</label>
                <input type="date" id="src-start" value="<?php echo date('Y-m-01'); ?>">
            </div>
            <div class="rpt-filter-group">
                <label>Hasta</label>
                <input type="date" id="src-end" value="<?php echo date('Y-m-t'); ?>">
            </div>
            <button class="button button-primary" id="btn-refresh-src" style="height:36px; line-height:34px; border-radius:6px; align-self:flex-end;">🔍 <?php esc_html_e( 'Filtrar', 'artechia-pms' ); ?></button>
        </div>
        
        <!-- Chart -->
        <div class="rpt-chart-panel" style="margin-bottom:16px;">
            <div class="rpt-chart-header">
                <span>🌐</span>
                <h3><?php esc_html_e( 'Distribución de Fuentes de Reserva', 'artechia-pms' ); ?></h3>
            </div>
            <div style="padding:16px; height:300px;">
                <canvas id="chart-sources"></canvas>
            </div>
        </div>

        <!-- Table -->
        <div class="rpt-chart-panel">
            <div class="rpt-chart-header">
                <span>📋</span>
                <h3><?php esc_html_e( 'Detalle por Fuente', 'artechia-pms' ); ?></h3>
            </div>
            <div style="padding:0;">
                <table class="wp-list-table widefat fixed striped artechia-table" id="table-sources">
                    <thead>
                        <tr>
                            <th style="width:40%;"><?php esc_html_e( 'Fuente', 'artechia-pms' ); ?></th>
                            <th style="width:20%;"><?php esc_html_e( 'Reservas', 'artechia-pms' ); ?></th>
                            <th><?php esc_html_e( 'Ingresos Generados (Est.)', 'artechia-pms' ); ?></th>
                        </tr>
                    </thead>
                    <tbody><!-- JS --></tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<!-- Styles -->
<style>
/* Hide default WP tab wrapper if it exists */
.artechia-reports-page > .nav-tab-wrapper { display: none !important; }

/* Custom tab bar */
.rpt-tab-bar {
    display: flex; gap: 4px; border-bottom: 2px solid #e5e7eb; padding-bottom: 0; margin-bottom: 0;
}
.rpt-tab-bar .rpt-tab {
    padding: 10px 20px; border: none; background: none; font-size: 13px; font-weight: 600;
    color: #94a3b8; cursor: pointer; border-bottom: 2px solid transparent;
    margin-bottom: -2px; transition: all 0.2s; text-decoration: none;
    /* Override WP .nav-tab defaults */
    float: none; border-top: none; border-left: none; border-right: none;
    background: none !important;
}
.rpt-tab-bar .rpt-tab:hover { color: #1e293b; }
.rpt-tab-bar .rpt-tab.nav-tab-active {
    color: #6366f1; border-bottom-color: #6366f1;
    /* Override WP active style */
    background: none !important; border-top: none; border-left: none; border-right: none;
}

/* Tab content visibility (reports.js toggles artechia-tab-content--active) */
.artechia-reports-page .artechia-tab-content { display: none; }
.artechia-reports-page .artechia-tab-content--active { display: block !important; }

/* Chart panels */
.rpt-chart-panel {
    background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; overflow: hidden; margin-bottom: 16px;
}
.rpt-chart-header {
    display: flex; align-items: center; gap: 8px; padding: 14px 20px;
    background: #f8fafc; border-bottom: 1px solid #e5e7eb;
}
.rpt-chart-header span { font-size: 16px; }
.rpt-chart-header h3 { margin: 0; font-size: 14px; font-weight: 700; color: #1e293b; }

/* Filters bar */
.rpt-filters {
    display: flex; gap: 12px; align-items: flex-end; margin-bottom: 16px;
    padding: 14px 18px; background: #fff; border: 1px solid #e5e7eb; border-radius: 10px;
}
.rpt-filter-group { display: flex; flex-direction: column; gap: 4px; }
.rpt-filter-group label { font-size: 11px; font-weight: 600; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px; }
.rpt-filter-group input[type="date"],
.rpt-filter-group select {
    height: 36px; border: 1px solid #e2e8f0; border-radius: 8px; padding: 0 10px;
    font-size: 13px; color: #1e293b; background: #fff;
    transition: border-color 0.2s, box-shadow 0.2s;
}
.rpt-filter-group input:focus,
.rpt-filter-group select:focus {
    border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,0.1); outline: none;
}

/* Skeleton animation */
@keyframes rpt-skeleton { 0% { opacity: 0.6; } 50% { opacity: 1; } 100% { opacity: 0.6; } }
.rpt-card-skeleton > div { animation: rpt-skeleton 1.5s infinite ease-in-out; }

/* Dashboard cards from reports.js */
.artechia-reports-page .artechia-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; }
.artechia-reports-page .artechia-card {
    background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; padding: 16px 20px;
    display: flex; align-items: center; gap: 12px;
}
.artechia-reports-page .artechia-card__icon { font-size: 24px; }
.artechia-reports-page .artechia-card__body { display: flex; flex-direction: column; }
.artechia-reports-page .artechia-card__number { font-size: 22px; font-weight: 700; color: #1e293b; }
.artechia-reports-page .artechia-card__label { font-size: 11px; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px; margin-top: 2px; }

/* Card variants */
.artechia-reports-page .artechia-card--primary { border-left: 3px solid #3b82f6; }
.artechia-reports-page .artechia-card--success { border-left: 3px solid #16a34a; }
.artechia-reports-page .artechia-card--warning { border-left: 3px solid #f59e0b; }
.artechia-reports-page .artechia-card--danger { border-left: 3px solid #dc2626; }

/* Stat boxes from reports.js occupancy */
.artechia-reports-page .artechia-stat-box {
    background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; padding: 16px 20px;
}
.artechia-reports-page .stat-label {
    display: block; font-size: 11px; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px;
}
.artechia-reports-page .stat-value { font-size: 22px; font-weight: 700; color: #1e293b; }
.artechia-reports-page .artechia-text-success { color: #16a34a !important; }
.artechia-reports-page .artechia-text-warning { color: #f59e0b !important; }
</style>
