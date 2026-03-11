<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
?>
<div class="wrap artechia-wrap artechia-ical-page">

    <!-- Summary Cards (populated by JS) -->
    <div id="ical-stats" style="display:grid; grid-template-columns:repeat(auto-fit, minmax(160px, 1fr)); gap:12px; margin-bottom:16px;">
        <div style="background:#fff; border:1px solid #e5e7eb; border-radius:8px; padding:14px 16px; display:flex; align-items:center; gap:12px;">
            <div style="width:40px; height:40px; border-radius:10px; background:#eff6ff; display:flex; align-items:center; justify-content:center; font-size:20px;">🏠</div>
            <div>
                <div id="stat-units" style="font-size:22px; font-weight:700; color:#1e293b;">—</div>
                <div style="font-size:11px; color:#94a3b8; text-transform:uppercase; letter-spacing:0.5px;">Unidades</div>
            </div>
        </div>
        <div style="background:#fff; border:1px solid #e5e7eb; border-radius:8px; padding:14px 16px; display:flex; align-items:center; gap:12px;">
            <div style="width:40px; height:40px; border-radius:10px; background:#f0fdf4; display:flex; align-items:center; justify-content:center; font-size:20px;">📡</div>
            <div>
                <div id="stat-feeds" style="font-size:22px; font-weight:700; color:#16a34a;">—</div>
                <div style="font-size:11px; color:#94a3b8; text-transform:uppercase; letter-spacing:0.5px;">Feeds Importados</div>
            </div>
        </div>
        <div style="background:#fff; border:1px solid #e5e7eb; border-radius:8px; padding:14px 16px; display:flex; align-items:center; gap:12px;">
            <div style="width:40px; height:40px; border-radius:10px; background:#fef3c7; display:flex; align-items:center; justify-content:center; font-size:20px;">⚠️</div>
            <div>
                <div id="stat-conflicts" style="font-size:22px; font-weight:700; color:#92400e;">—</div>
                <div style="font-size:11px; color:#94a3b8; text-transform:uppercase; letter-spacing:0.5px;">Conflictos</div>
            </div>
        </div>
    </div>

    <!-- Tabs -->
    <div style="display:flex; gap:4px; margin-bottom:0; border-bottom:2px solid #e5e7eb; padding-bottom:0;">
        <button class="ical-tab active" onclick="switchTab(event, 'feeds')" style="padding:10px 20px; border:none; background:none; font-size:13px; font-weight:600; color:#94a3b8; cursor:pointer; border-bottom:2px solid transparent; margin-bottom:-2px; transition:all 0.2s;">📡 Feeds & Export</button>
        <button class="ical-tab" onclick="switchTab(event, 'conflicts')" style="padding:10px 20px; border:none; background:none; font-size:13px; font-weight:600; color:#94a3b8; cursor:pointer; border-bottom:2px solid transparent; margin-bottom:-2px; transition:all 0.2s;">⚠️ Conflictos <span id="conflict-count" style="background:#fee2e2; color:#dc2626; padding:1px 7px; border-radius:10px; font-size:10px; font-weight:700; margin-left:4px; display:none;"></span></button>
    </div>

    <!-- TAB: FEEDS -->
    <div id="tab-feeds" class="tab-content" style="margin-top:20px;">
        <div id="units-container">
            <div style="text-align:center; padding:40px; color:#94a3b8;">
                <div style="font-size:28px; margin-bottom:8px;">⏳</div>
                <div>Cargando unidades...</div>
            </div>
        </div>
    </div>

    <!-- TAB: CONFLICTS -->
    <div id="tab-conflicts" class="tab-content" style="display:none; margin-top:20px;">
        <div id="conflicts-container">
            <table class="wp-list-table widefat fixed striped artechia-table" id="conflicts-table" style="display:none;">
                <thead>
                    <tr>
                        <th style="width:20%;">Unidad</th>
                        <th style="width:22%;">Fecha</th>
                        <th>Evento Externo</th>
                        <th style="width:10%;">Estado</th>
                        <th style="width:28%;">Acciones</th>
                    </tr>
                </thead>
                <tbody id="conflicts-list"></tbody>
            </table>
            <div id="no-conflicts-msg" style="display:none; text-align:center; padding:40px;">
                <div style="font-size:40px; margin-bottom:12px;">✅</div>
                <div style="font-size:16px; font-weight:600; color:#1e293b;">No hay conflictos pendientes</div>
                <div style="font-size:13px; color:#94a3b8; margin-top:4px;">Todas las sincronizaciones están al día.</div>
            </div>
        </div>
    </div>

</div>

<!-- ================================================================ -->
<!-- MODAL: Add iCal Feed                                              -->
<!-- ================================================================ -->
<div id="ical-feed-modal" class="artechia-ical-modal" style="display:none;">
    <div class="artechia-ical-modal-content">
        <!-- Header -->
        <div class="artechia-ical-modal-header">
            <div style="display:flex; align-items:center; gap:10px;">
                <span style="width:36px; height:36px; border-radius:10px; background:linear-gradient(135deg,#3b82f6,#06b6d4); display:flex; align-items:center; justify-content:center; font-size:16px; color:#fff;">📡</span>
                <h2 style="margin:0; font-size:18px; font-weight:700; color:#1e293b;"><?php _e( 'Importar Calendario (iCal)', 'artechia-pms' ); ?></h2>
            </div>
            <button type="button" class="artechia-ical-close" onclick="closeFeedModal()">&times;</button>
        </div>

        <!-- Form -->
        <form id="form-ical-feed">
            <input type="hidden" id="feed-unit-id">
            <div class="artechia-ical-modal-body">

                <div class="artechia-ical-field">
                    <label><?php _e( 'Nombre del Canal', 'artechia-pms' ); ?></label>
                    <input type="text" id="feed-name" placeholder="Ej: Airbnb, Booking.com" required>
                </div>

                <div class="artechia-ical-field">
                    <label><?php _e( 'URL del archivo .ics', 'artechia-pms' ); ?></label>
                    <input type="url" id="feed-url" placeholder="https://..." required style="font-family:monospace; font-size:12px;">
                    <span style="font-size:11px; color:#94a3b8; margin-top:4px; display:block;"><?php _e( 'Pega aquí la URL secreta de exportación de tu canal.', 'artechia-pms' ); ?></span>
                </div>

                <div class="artechia-ical-field">
                    <label><?php _e( 'Política de Conflictos', 'artechia-pms' ); ?></label>
                    <select id="feed-policy">
                        <option value="mark_conflict"><?php _e( 'Marcar conflicto (Recomendado)', 'artechia-pms' ); ?></option>
                        <option value="skip"><?php _e( 'Ignorar si está ocupado', 'artechia-pms' ); ?></option>
                        <option value="cancel_local"><?php _e( 'Cancelar reserva local (Cuidado)', 'artechia-pms' ); ?></option>
                    </select>
                </div>

            </div>

            <!-- Footer -->
            <div class="artechia-ical-modal-footer">
                <button type="button" class="button" onclick="closeFeedModal()" style="height:36px; line-height:34px; border-radius:6px;"><?php _e( 'Cancelar', 'artechia-pms' ); ?></button>
                <button type="submit" class="button button-primary" style="height:36px; line-height:34px; border-radius:6px;"><?php _e( 'Guardar e Importar', 'artechia-pms' ); ?></button>
            </div>
        </form>
    </div>
</div>

<!-- ================================================================ -->
<!-- Styles                                                            -->
<!-- ================================================================ -->
<style>
body.artechia-ical-modal-open { overflow: hidden !important; }

/* Tabs */
.ical-tab.active { color: #3b82f6 !important; border-bottom-color: #3b82f6 !important; }
.ical-tab:hover { color: #1e293b !important; }

/* Unit Cards */
.ical-unit-card {
    background: #fff; border: 1px solid #e5e7eb; border-radius: 10px;
    margin-bottom: 16px; overflow: hidden;
}
.ical-unit-header {
    display: flex; justify-content: space-between; align-items: center;
    padding: 14px 20px; background: #f8fafc; border-bottom: 1px solid #e5e7eb;
}
.ical-unit-header h3 { margin: 0; font-size: 14px; font-weight: 700; color: #1e293b; display: flex; align-items: center; gap: 8px; }
.ical-unit-body { padding: 16px 20px; }

/* Export URL row */
.ical-export-row {
    display: flex; gap: 8px; align-items: center; margin-bottom: 16px;
    padding: 10px 14px; background: #f8fafc; border-radius: 8px; border: 1px solid #e2e8f0;
}
.ical-export-row input {
    flex: 1; border: none; background: transparent; font-family: monospace;
    font-size: 12px; color: #475569; outline: none; cursor: pointer;
}
.ical-export-row .button { flex-shrink: 0; }

/* Feed rows */
.ical-feed-item {
    display: flex; align-items: center; justify-content: space-between;
    padding: 10px 14px; border-radius: 8px; margin-bottom: 6px;
    border: 1px solid #e2e8f0; transition: background 0.2s;
}
.ical-feed-item:hover { background: #f8fafc; }

/* Channel badges */
.ical-channel-badge {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 4px 12px; border-radius: 8px; font-size: 12px; font-weight: 600;
}
.ical-channel-badge.airbnb { background: #fee2e2; color: #dc2626; }
.ical-channel-badge.booking { background: #dbeafe; color: #1e40af; }
.ical-channel-badge.default { background: #f1f5f9; color: #475569; }

/* Sync status */
.ical-sync-status {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 2px 8px; border-radius: 8px; font-size: 10px; font-weight: 600;
}
.ical-sync-status.ok { background: #dcfce7; color: #166534; }
.ical-sync-status.error { background: #fee2e2; color: #991b1b; }
.ical-sync-status.never { background: #f1f5f9; color: #94a3b8; }

/* Conflict resolution buttons */
.ical-resolve-btn {
    padding: 4px 12px; border-radius: 6px; font-size: 11px; font-weight: 600;
    border: 1px solid; cursor: pointer; transition: all 0.2s;
}
.ical-resolve-btn.external { background: #dbeafe; color: #1e40af; border-color: #93c5fd; }
.ical-resolve-btn.external:hover { background: #bfdbfe; }
.ical-resolve-btn.local { background: #f1f5f9; color: #475569; border-color: #e2e8f0; }
.ical-resolve-btn.local:hover { background: #e2e8f0; }

/* Modal */
.artechia-ical-modal {
    position: fixed; z-index: 99999; left: 0; top: 0; width: 100%; height: 100%;
    background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px);
    display: flex; align-items: center; justify-content: center;
    animation: ical-fade-in 0.2s ease-out;
}
@keyframes ical-fade-in { from { opacity: 0; } to { opacity: 1; } }

.artechia-ical-modal-content {
    background: #fff; border-radius: 12px; width: 520px; max-width: 95vw;
    max-height: 90vh; display: flex; flex-direction: column; overflow: hidden;
    box-shadow: 0 20px 60px rgba(0,0,0,0.2), 0 0 0 1px rgba(0,0,0,0.05);
    animation: ical-slide-in 0.25s ease-out;
}
@keyframes ical-slide-in { from { transform: translateY(20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }

.artechia-ical-modal-content form {
    display: flex; flex-direction: column; flex: 1; min-height: 0; overflow: hidden;
}

.artechia-ical-modal-header {
    display: flex; justify-content: space-between; align-items: center;
    padding: 20px 24px; border-bottom: 1px solid #e5e7eb; flex-shrink: 0;
}
.artechia-ical-close {
    background: none; border: none; font-size: 24px; cursor: pointer; color: #94a3b8;
    width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center;
    justify-content: center; transition: all 0.2s;
}
.artechia-ical-close:hover { color: #1e293b; background: #f1f5f9; }

.artechia-ical-modal-body {
    flex: 1; overflow-y: auto; padding: 20px 24px; display: flex; flex-direction: column; gap: 16px; min-height: 0;
}
.artechia-ical-modal-footer {
    display: flex; justify-content: flex-end; gap: 8px;
    padding: 16px 24px; border-top: 1px solid #e5e7eb; flex-shrink: 0;
}

.artechia-ical-field label {
    display: block; font-weight: 600; margin-bottom: 6px; color: #475569; font-size: 13px;
}
.artechia-ical-field input[type="text"],
.artechia-ical-field input[type="url"],
.artechia-ical-field select {
    width: 100%; height: 38px; border: 1px solid #e2e8f0; border-radius: 8px;
    padding: 0 12px; font-size: 13px; color: #1e293b; background: #fff;
    transition: border-color 0.2s, box-shadow 0.2s; box-sizing: border-box;
}
.artechia-ical-field input:focus,
.artechia-ical-field select:focus {
    border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,0.1); outline: none;
}
</style>

<!-- ================================================================ -->
<!-- JavaScript                                                        -->
<!-- ================================================================ -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    loadUnits();
    loadConflicts();
});

let totalFeedCount = 0;

function switchTab(e, tabId) {
    e.preventDefault();
    document.querySelectorAll('.ical-tab').forEach(t => t.classList.remove('active'));
    e.target.closest('.ical-tab').classList.add('active');
    document.querySelectorAll('.tab-content').forEach(c => c.style.display = 'none');
    document.getElementById('tab-' + tabId).style.display = 'block';
}

/* ── API CALLS ── */
const api = {
    get(path) {
        return fetch(artechiaPMS.restUrl + path, {
            headers: { 'X-WP-Nonce': artechiaPMS.nonce }
        }).then(r => r.json());
    },
    post(path, data) {
        return fetch(artechiaPMS.restUrl + path, {
            method: 'POST',
            headers: { 'X-WP-Nonce': artechiaPMS.nonce, 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        }).then(r => r.json());
    },
    del(path) {
        return fetch(artechiaPMS.restUrl + path, {
            method: 'DELETE',
            headers: { 'X-WP-Nonce': artechiaPMS.nonce }
        });
    }
};

/* ── FEEDS LOGIC ── */
async function loadUnits() {
    const container = document.getElementById('units-container');

    if (typeof artechiaPMS === 'undefined' || !artechiaPMS.restUrl) {
        container.innerHTML = '<div style="text-align:center; padding:40px; color:#dc2626;">Error: configuración del plugin no cargada. Recargá la página.</div>';
        return;
    }

    try {
        const propId = artechiaPMS.property_id || 1;
        const today = new Date().toISOString().split('T')[0];
        const calData = await api.get(`admin/calendar?property_id=${propId}&start_date=${today}&days=1`);

        if (!calData || !calData.units || calData.units.length === 0) {
            container.innerHTML = `
                <div style="text-align:center; padding:40px;">
                    <div style="font-size:40px; margin-bottom:12px;">🏠</div>
                    <div style="font-size:16px; font-weight:600; color:#1e293b;">Sin unidades configuradas</div>
                    <div style="font-size:13px; color:#94a3b8;">Creá tipos de habitación y unidades primero.</div>
                </div>`;
            document.getElementById('stat-units').textContent = '0';
            return;
        }

        document.getElementById('stat-units').textContent = calData.units.length;

        const grouped = {};
        calData.units.forEach(u => {
            const typeName = u.room_type_name || 'General';
            if (!grouped[typeName]) grouped[typeName] = [];
            grouped[typeName].push(u);
        });

        let html = '';
        for (const typeName in grouped) {
            grouped[typeName].forEach(u => {
                html += renderUnitCard(u, typeName);
            });
        }
        container.innerHTML = html;

        // Load feeds for each unit after rendering
        for (const typeName in grouped) {
            for (const u of grouped[typeName]) {
                await loadFeeds(u.id);
            }
        }

        document.getElementById('stat-feeds').textContent = totalFeedCount;

    } catch (err) {
        console.error('[Artechia iCal]', err);
        container.innerHTML = '<div style="text-align:center; padding:40px; color:#dc2626;">Error cargando unidades. Verificá la consola.</div>';
    }
}

function getChannelInfo(name) {
    const n = (name || '').toLowerCase();
    if (n.includes('airbnb')) return { icon: '🔴', cls: 'airbnb', label: name };
    if (n.includes('booking')) return { icon: '🔵', cls: 'booking', label: name };
    if (n.includes('despegar')) return { icon: '🟢', cls: 'default', label: name };
    if (n.includes('expedia')) return { icon: '🟡', cls: 'default', label: name };
    return { icon: '📡', cls: 'default', label: name };
}

function renderUnitCard(unit, typeName) {
    return `
    <div class="ical-unit-card" id="unit-card-${unit.id}">
        <div class="ical-unit-header">
            <h3>
                <span style="width:28px; height:28px; border-radius:6px; background:#3b82f6; color:#fff; display:inline-flex; align-items:center; justify-content:center; font-size:12px;">🏠</span>
                ${typeName} — ${unit.name}
            </h3>
            <button class="button button-small" onclick="openAddFeedModal(${unit.id})" style="border-radius:6px;">
                + Importar iCal
            </button>
        </div>
        <div class="ical-unit-body">
            <!-- EXPORT SECTION -->
            <div style="margin-bottom:12px;">
                <label style="font-size:11px; font-weight:600; color:#94a3b8; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:6px; display:block;">URL de Exportación</label>
                <div class="ical-export-row">
                    <span style="font-size:14px;">📤</span>
                    <input type="text" readonly id="export-url-${unit.id}" value="Cargando..." onclick="this.select()">
                    <button class="button button-small" onclick="copyExportUrl(${unit.id})" style="border-radius:6px;">📋 Copiar</button>
                    <button class="button button-small" onclick="regenerateToken(${unit.id})" style="border-radius:6px;">🔄 Generar Token</button>
                </div>
            </div>

            <!-- IMPORTS SECTION -->
            <div>
                <label style="font-size:11px; font-weight:600; color:#94a3b8; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:8px; display:block;">Feeds Importados</label>
                <div id="feeds-list-${unit.id}">
                    <div style="text-align:center; padding:16px; color:#94a3b8; font-size:13px;">Cargando feeds...</div>
                </div>
            </div>
        </div>
    </div>`;
}

async function loadFeeds(unitId) {
    const feedsContainer = document.getElementById(`feeds-list-${unitId}`);
    const exportInput = document.getElementById(`export-url-${unitId}`);
    
    try {
        const feeds = await api.get(`admin/ical/feeds?unit_id=${unitId}`);

        if (!feeds || !Array.isArray(feeds)) {
            feedsContainer.innerHTML = '<div style="color:#dc2626; font-size:13px;">Error al cargar.</div>';
            return;
        }

        // Process Export Token
        const tokenFeed = feeds.find(f => f.export_token);
        if (tokenFeed && tokenFeed.export_token) {
            const url = `${artechiaPMS.restUrl}public/ical/export/unit/${unitId}?token=${tokenFeed.export_token}`;
            exportInput.value = url;
        } else {
            exportInput.value = 'Token no generado. Hacé click en 🔄';
        }

        // Render Imports
        const imports = feeds.filter(f => f.url && f.url.length > 0);
        totalFeedCount += imports.length;

        if (imports.length === 0) {
            feedsContainer.innerHTML = `
                <div style="text-align:center; padding:20px; border:1px dashed #e2e8f0; border-radius:8px; color:#94a3b8; font-size:13px;">
                    No hay feeds de importación. Hacé click en "+ Importar iCal".
                </div>`;
            return;
        }

        feedsContainer.innerHTML = imports.map(f => {
            const ch = getChannelInfo(f.name);
            const syncClass = !f.last_sync_at ? 'never' : (f.last_sync_status === 'ok' ? 'ok' : 'error');
            const syncLabel = !f.last_sync_at ? 'Nunca' : (f.last_sync_status === 'ok' ? '✓ OK' : '✗ Error');
            const syncTime = f.last_sync_at ? new Date(f.last_sync_at).toLocaleString('es-AR', {day:'2-digit',month:'2-digit',hour:'2-digit',minute:'2-digit'}) : '';

            return `
            <div class="ical-feed-item">
                <div style="display:flex; align-items:center; gap:10px; flex:1; min-width:0;">
                    <span class="ical-channel-badge ${ch.cls}">${ch.icon} ${ch.label}</span>
                    <code style="font-size:11px; color:#94a3b8; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:200px;" title="${f.url}">${f.url}</code>
                </div>
                <div style="display:flex; align-items:center; gap:8px; flex-shrink:0;">
                    <div style="text-align:right;">
                        <span class="ical-sync-status ${syncClass}">${syncLabel}</span>
                        ${syncTime ? `<div style="font-size:10px; color:#94a3b8; margin-top:2px;">${syncTime}</div>` : ''}
                    </div>
                    <button class="button button-small" onclick="syncFeed(${f.id})" title="Sincronizar" style="border-radius:6px;">🔄 Sync</button>
                    <button class="button button-small" onclick="deleteFeed(${f.id}, ${unitId})" title="Eliminar" style="border-radius:6px; color:#dc2626;">✕</button>
                </div>
            </div>`;
        }).join('');

    } catch (err) {
        console.error('[Artechia iCal feeds]', err);
        feedsContainer.innerHTML = '<div style="color:#dc2626; font-size:13px;">Error al cargar feeds.</div>';
    }
}

function copyExportUrl(unitId) {
    const input = document.getElementById(`export-url-${unitId}`);
    input.select();
    document.execCommand('copy');
    if (artechiaPMS.toast) artechiaPMS.toast.show('URL copiada al portapapeles', 'success');
}

async function regenerateToken(unitId) {
    const res = await api.post(`admin/room-unit/${unitId}/ical-token`);
    if (res.token) {
        totalFeedCount = 0;
        loadFeeds(unitId);
        if (artechiaPMS.toast) artechiaPMS.toast.show('Token generado', 'success');
    } else {
        if (artechiaPMS.toast) artechiaPMS.toast.show('Error generando token', 'error');
    }
}

async function syncFeed(feedId) {
    const btn = event.target.closest('button');
    const origText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '⏳';
    try {
        const maxTime = 15000;
        const promise = api.post(`admin/ical/feeds/${feedId}/sync`);
        const result = await Promise.race([
             promise,
             new Promise((_, reject) => setTimeout(() => reject(new Error('timeout')), maxTime))
        ]);
        
        if (artechiaPMS.toast) artechiaPMS.toast.show(`Sync completado. Procesados: ${result.processed} | Creados: ${result.created} | Conflictos: ${result.conflicts}`, 'success');
        location.reload(); 
    } catch (e) {
        if (artechiaPMS.toast) artechiaPMS.toast.show('Error en sync: ' + e.message, 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = origText;
    }
}

async function deleteFeed(id, unitId) {
    if(!confirm('¿Eliminar este feed? Eventos importados se mantendrán pero no se actualizarán.')) return;
    await api.del(`admin/ical/feeds/${id}`);
    totalFeedCount = 0;
    await loadFeeds(unitId);
    document.getElementById('stat-feeds').textContent = totalFeedCount;
}

function openAddFeedModal(unitId) {
    document.getElementById('feed-unit-id').value = unitId;
    document.getElementById('form-ical-feed').reset();
    document.getElementById('ical-feed-modal').style.display = 'flex';
    document.body.classList.add('artechia-ical-modal-open');
}

function closeFeedModal() {
    document.getElementById('ical-feed-modal').style.display = 'none';
    document.body.classList.remove('artechia-ical-modal-open');
}

document.getElementById('form-ical-feed').addEventListener('submit', async function(e) {
    e.preventDefault();
    const unitId = document.getElementById('feed-unit-id').value;
    const btn = this.querySelector('button[type="submit"]');
    
    btn.disabled = true;
    btn.innerText = 'Guardando...';

    try {
        const res = await api.post('admin/ical/feeds', {
            room_unit_id: unitId,
            name: document.getElementById('feed-name').value,
            url: document.getElementById('feed-url').value,
            conflict_policy: document.getElementById('feed-policy').value,
            property_id: artechiaPMS.property_id || 1
        });

        if (res.error) {
            if (artechiaPMS.toast) artechiaPMS.toast.show(res.message || 'Error al guardar', 'error');
        } else {
            closeFeedModal();
            totalFeedCount = 0;
            await loadFeeds(unitId);
            document.getElementById('stat-feeds').textContent = totalFeedCount;
        }
    } catch (err) {
        if (artechiaPMS.toast) artechiaPMS.toast.show('Error: ' + err.message, 'error');
    } finally {
        btn.disabled = false;
        btn.innerText = '<?php _e( 'Guardar e Importar', 'artechia-pms' ); ?>';
    }
});

// ESC to close modal
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeFeedModal();
});
document.getElementById('ical-feed-modal').addEventListener('click', function(e) {
    if (e.target === this) closeFeedModal();
});

/* ── CONFLICTS LOGIC ── */
async function loadConflicts() {
    const tbody = document.getElementById('conflicts-list');
    const countSpan = document.getElementById('conflict-count');
    const table = document.getElementById('conflicts-table');
    const noMsg = document.getElementById('no-conflicts-msg');
    const statConflicts = document.getElementById('stat-conflicts');

    try {
        const conflicts = await api.get('admin/ical/conflicts?property_id=1');

        if (!conflicts || !Array.isArray(conflicts) || conflicts.length === 0) {
            tbody.innerHTML = '';
            table.style.display = 'none';
            noMsg.style.display = 'block';
            countSpan.style.display = 'none';
            statConflicts.textContent = '0';
            return;
        }
    
        table.style.display = '';
        noMsg.style.display = 'none';
        countSpan.style.display = 'inline';
        countSpan.textContent = conflicts.length;
        statConflicts.textContent = conflicts.length;
        statConflicts.style.color = '#dc2626';

        tbody.innerHTML = conflicts.map(c => `
            <tr>
                <td>
                    <div style="display:flex; align-items:center; gap:8px;">
                        <span style="width:28px; height:28px; border-radius:6px; background:#3b82f6; color:#fff; display:inline-flex; align-items:center; justify-content:center; font-size:11px;">🏠</span>
                        <strong>Unidad #${c.room_unit_id}</strong>
                    </div>
                </td>
                <td>
                    <span style="background:#f1f5f9; padding:3px 10px; border-radius:6px; font-size:12px; color:#475569;">${c.start_date} → ${c.end_date}</span>
                </td>
                <td>
                    <code style="font-size:11px; color:#94a3b8;">${c.ical_event_id || 'Externo'}</code>
                </td>
                <td>
                    ${parseInt(c.resolved) === 1 
                        ? '<span style="background:#dcfce7; color:#166534; padding:2px 8px; border-radius:8px; font-size:10px; font-weight:600;">Resuelto</span>'
                        : '<span style="background:#fee2e2; color:#991b1b; padding:2px 8px; border-radius:8px; font-size:10px; font-weight:600;">Conflicto</span>'}
                </td>
                <td>
                    <div style="display:flex; gap:6px;">
                        <button class="ical-resolve-btn external" onclick="resolveConflict(${c.id}, 'keep_external')">Mantener Externo</button>
                        <button class="ical-resolve-btn local" onclick="resolveConflict(${c.id}, 'keep_local')">Mantener Local</button>
                    </div>
                </td>
            </tr>
        `).join('');
    } catch (err) {
        console.error('[Artechia iCal conflicts]', err);
        tbody.innerHTML = '';
        table.style.display = 'none';
        noMsg.style.display = 'block';
        countSpan.style.display = 'none';
        statConflicts.textContent = '0';
    }
}

async function resolveConflict(id, action) {
    if(!confirm('¿Confirmar resolución?')) return;
    await api.post(`admin/ical/conflicts/${id}/resolve?action=${action}`);
    loadConflicts();
}
</script>
