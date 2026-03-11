<?php
/**
 * Room Units admin view — modal-based CRUD.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

use Artechia\PMS\Repositories\RoomTypeRepository;
use Artechia\PMS\Repositories\PropertyRepository;

$prop_repo = new PropertyRepository();
$rt_repo   = new RoomTypeRepository();
$property  = $prop_repo->get_default();
$prop_id   = absint( $_GET['property_id'] ?? ( $property['id'] ?? 0 ) );
$room_types = $rt_repo->all_with_counts( $prop_id );
?>

<div class="wrap artechia-wrap" id="artechia-room-units-app">

    <!-- Header -->
    <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:16px;">
        <div>
            <h2 style="margin:0; font-size:18px; font-weight:700; color:#1e293b;">Habitaciones</h2>
            <p style="color:#64748b; font-size:13px; margin:4px 0 0;">Unidades individuales de cada tipo de habitación.</p>
        </div>
        <button type="button" class="button button-primary" id="add-unit-btn" style="display:inline-flex; align-items:center; gap:6px; height:36px; border-radius:6px;">
            <span class="dashicons dashicons-plus" style="font-size:16px; width:16px; height:16px;"></span> Nueva Habitación
        </button>
    </div>

    <!-- Summary Cards -->
    <div id="unit-stats" style="display:grid; grid-template-columns:repeat(auto-fit, minmax(160px, 1fr)); gap:12px; margin-bottom:16px;">
        <div style="background:#fff; border:1px solid #e5e7eb; border-radius:8px; padding:14px 16px; display:flex; align-items:center; gap:12px;">
            <div style="width:40px; height:40px; border-radius:10px; background:#eff6ff; display:flex; align-items:center; justify-content:center;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#3b82f6" stroke-width="2"><rect x="3" y="3" width="7" height="9" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="16" width="7" height="5" rx="1"/></svg>
            </div>
            <div>
                <div id="ustat-total" style="font-size:22px; font-weight:700; color:#1e293b;">—</div>
                <div style="font-size:11px; color:#94a3b8; text-transform:uppercase; letter-spacing:0.5px;">Habitaciones</div>
            </div>
        </div>
        <div style="background:#fff; border:1px solid #e5e7eb; border-radius:8px; padding:14px 16px; display:flex; align-items:center; gap:12px;">
            <div style="width:40px; height:40px; border-radius:10px; background:#f0fdf4; display:flex; align-items:center; justify-content:center;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            </div>
            <div>
                <div id="ustat-available" style="font-size:22px; font-weight:700; color:#16a34a;">—</div>
                <div style="font-size:11px; color:#94a3b8; text-transform:uppercase; letter-spacing:0.5px;">Disponibles</div>
            </div>
        </div>
        <div style="background:#fff; border:1px solid #e5e7eb; border-radius:8px; padding:14px 16px; display:flex; align-items:center; gap:12px;">
            <div style="width:40px; height:40px; border-radius:10px; background:#dbeafe; display:flex; align-items:center; justify-content:center;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#1e40af" stroke-width="2"><path d="M15 3h4a2 2 0 012 2v14a2 2 0 01-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
            </div>
            <div>
                <div id="ustat-occupied" style="font-size:22px; font-weight:700; color:#1e40af;">—</div>
                <div style="font-size:11px; color:#94a3b8; text-transform:uppercase; letter-spacing:0.5px;">Ocupadas</div>
            </div>
        </div>
        <div style="background:#fff; border:1px solid #e5e7eb; border-radius:8px; padding:14px 16px; display:flex; align-items:center; gap:12px;">
            <div style="width:40px; height:40px; border-radius:10px; background:#fef3c7; display:flex; align-items:center; justify-content:center;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#d97706" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            </div>
            <div>
                <div id="ustat-maintenance" style="font-size:22px; font-weight:700; color:#d97706;">—</div>
                <div style="font-size:11px; color:#94a3b8; text-transform:uppercase; letter-spacing:0.5px;">Mantenimiento</div>
            </div>
        </div>
    </div>

    <!-- Table -->
    <div style="background:#fff; border:1px solid #e5e7eb; border-radius:8px; overflow:hidden;">
        <table class="wp-list-table widefat fixed striped" style="border:none; border-radius:0;">
            <thead>
                <tr style="background:#f8fafc;">
                    <th style="font-weight:600; color:#475569; padding:12px 16px; width:40%;">Habitación</th>
                    <th style="font-weight:600; color:#475569; padding:12px 16px;">Tipo</th>
                    <th style="font-weight:600; color:#475569; padding:12px 16px; width:15%; text-align:center;">Estado</th>
                    <th style="font-weight:600; color:#475569; padding:12px 16px; width:120px;">Acciones</th>
                </tr>
            </thead>
            <tbody id="units-list">
                <tr><td colspan="4" style="text-align:center; padding:40px 20px; color:#94a3b8;">
                    <span class="dashicons dashicons-update spin" style="font-size:24px; width:24px; height:24px; animation:rotation 1s linear infinite;"></span><br>Cargando...
                </td></tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: New/Edit Room Unit -->
<div id="unit-modal" class="artechia-modal" style="display:none;">
    <div class="artechia-modal-content" style="width:520px;">
        <div class="artechia-modal-header">
            <div style="display:flex; align-items:center; gap:10px;">
                <span style="width:36px; height:36px; border-radius:10px; background:linear-gradient(135deg,#10b981,#059669); display:flex; align-items:center; justify-content:center; color:#fff;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="9" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="16" width="7" height="5" rx="1"/></svg>
                </span>
                <h2 id="unit-modal-title" style="margin:0; font-size:18px; font-weight:700; color:#1e293b;">Nueva Habitación</h2>
            </div>
            <button type="button" class="close-modal">&times;</button>
        </div>
        <form id="unit-form">
            <input type="hidden" id="unit-id" value="">
            <div style="display:flex; flex-direction:column; gap:14px; padding:4px 0;">
                <div class="artechia-field">
                    <label>Tipo de Habitación <span style="color:#dc2626;">*</span></label>
                    <select id="unit-room-type" required>
                        <option value="">— Seleccionar —</option>
                        <?php foreach ( $room_types as $rt ) : ?>
                            <option value="<?php echo esc_attr( $rt['id'] ); ?>"><?php echo esc_html( $rt['name'] ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="artechia-field">
                    <label>Nombre / Número <span style="color:#dc2626;">*</span></label>
                    <input type="text" id="unit-name" required placeholder="Ej: 101, Cabaña Roble...">
                </div>
                <div class="artechia-form-grid">
                    <div class="artechia-field">
                        <label>Estado</label>
                        <select id="unit-status">
                            <option value="available">Disponible</option>
                            <option value="occupied">Ocupada</option>
                            <option value="maintenance">Mantenimiento</option>
                            <option value="out_of_service">Fuera de servicio</option>
                        </select>
                    </div>
                    <div class="artechia-field">
                        <label>Orden</label>
                        <input type="number" id="unit-sort" min="0" value="0">
                    </div>
                </div>
                <div class="artechia-field">
                    <label>Notas</label>
                    <textarea id="unit-notes" rows="2" style="width:100%; border:1px solid #e2e8f0; border-radius:8px; padding:10px 12px; font-size:13px; resize:vertical;" placeholder="Notas internas..."></textarea>
                </div>
            </div>
            <div class="artechia-modal-footer">
                <button type="button" class="button close-modal" style="border-radius:6px; height:36px; padding:0 16px;">Cancelar</button>
                <button type="submit" class="button button-primary" style="border-radius:6px; height:36px; padding:0 20px; font-weight:600;">
                    <span class="dashicons dashicons-saved" style="font-size:16px; width:16px; height:16px; margin-right:4px;"></span> Guardar
                </button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const API = artechiaPMS.restUrl + 'admin/room-units';
    const NONCE = artechiaPMS.nonce;
    const modal = document.getElementById('unit-modal');
    const form = document.getElementById('unit-form');
    const list = document.getElementById('units-list');
    const addBtn = document.getElementById('add-unit-btn');

    const statusLabels = { available: 'Disponible', occupied: 'Ocupada', maintenance: 'Mantenimiento', out_of_service: 'Fuera de servicio' };
    const statusStyles = {
        available:      { bg: '#dcfce7', color: '#166534' },
        occupied:       { bg: '#dbeafe', color: '#1e40af' },
        maintenance:    { bg: '#fef3c7', color: '#92400e' },
        out_of_service: { bg: '#fee2e2', color: '#991b1b' },
    };
    const roomColors = ['#3b82f6','#10b981','#f59e0b','#8b5cf6','#ec4899','#14b8a6','#ef4444','#f97316'];

    function headers(method) {
        const h = { 'X-WP-Nonce': NONCE };
        if (method !== 'GET' && method !== 'DELETE') h['Content-Type'] = 'application/json';
        return h;
    }

    function toast(msg, type) {
        if (window.artechiaPMS && window.artechiaPMS.toast) window.artechiaPMS.toast.show(msg, type);
        else alert(msg);
    }

    function openModal(item) {
        document.getElementById('unit-modal-title').textContent = item ? 'Editar Habitación' : 'Nueva Habitación';
        document.getElementById('unit-id').value = item ? item.id : '';
        document.getElementById('unit-room-type').value = item ? (item.room_type_id || '') : '';
        document.getElementById('unit-name').value = item ? item.name : '';
        document.getElementById('unit-status').value = item ? (item.status || 'available') : 'available';
        document.getElementById('unit-sort').value = item ? (item.sort_order || 0) : 0;
        document.getElementById('unit-notes').value = item ? (item.notes || '') : '';
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
        setTimeout(() => document.getElementById('unit-name').focus(), 100);
    }

    function closeModal() {
        modal.style.display = 'none';
        document.body.style.overflow = '';
    }

    async function loadData() {
        try {
            const res = await fetch(API, { headers: headers('GET') });
            const items = await res.json();

            const el = (id, v) => { const e = document.getElementById(id); if (e) e.textContent = v; };
            el('ustat-total', items.length);
            el('ustat-available', items.filter(u => u.status === 'available').length);
            el('ustat-occupied', items.filter(u => u.status === 'occupied').length);
            el('ustat-maintenance', items.filter(u => u.status === 'maintenance' || u.status === 'out_of_service').length);

            if (!items.length) {
                list.innerHTML = `<tr><td colspan="4" style="text-align:center; padding:60px 20px;">
                    <div style="margin-bottom:12px;"><svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#cbd5e1" stroke-width="1.5"><rect x="3" y="3" width="7" height="9" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="16" width="7" height="5" rx="1"/></svg></div>
                    <div style="font-size:16px; font-weight:600; color:#1e293b; margin-bottom:6px;">No hay habitaciones</div>
                    <div style="font-size:13px; color:#94a3b8; margin-bottom:16px;">Primero creá tipos de habitación, luego agregá habitaciones.</div>
                    <button class="button button-primary" onclick="document.getElementById('add-unit-btn').click()" style="border-radius:6px;">+ Nueva Habitación</button>
                </td></tr>`;
                return;
            }

            list.innerHTML = items.map((u, i) => {
                const color = roomColors[i % roomColors.length];
                const s = u.status || 'available';
                const st = statusStyles[s] || { bg: '#f1f5f9', color: '#475569' };
                return `<tr>
                    <td style="padding:12px 16px;">
                        <div style="display:flex; align-items:center; gap:10px;">
                            <div style="width:36px; height:36px; border-radius:8px; background:${color}; color:#fff; display:flex; align-items:center; justify-content:center; font-size:14px; font-weight:700; flex-shrink:0;">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="9" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="16" width="7" height="5" rx="1"/></svg>
                            </div>
                            <strong style="color:#1e293b; cursor:pointer;" class="unit-edit" data-id="${u.id}">${u.name || '—'}</strong>
                        </div>
                    </td>
                    <td style="padding:12px 16px;">
                        <span style="background:#f1f5f9; color:#475569; padding:3px 10px; border-radius:8px; font-size:12px; font-weight:500;">${u.room_type_name || '—'}</span>
                    </td>
                    <td style="padding:12px 16px; text-align:center;">
                        <span style="background:${st.bg}; color:${st.color}; padding:3px 10px; border-radius:12px; font-size:11px; font-weight:600;">${statusLabels[s] || s}</span>
                    </td>
                    <td style="padding:12px 16px;">
                        <div style="display:flex; gap:6px;">
                            <button type="button" class="button button-small unit-edit" data-id="${u.id}" style="border-radius:4px;">Editar</button>
                            <button type="button" class="button button-small unit-delete" data-id="${u.id}" data-name="${u.name}" style="border-radius:4px; color:#dc2626; border-color:#fecaca;">Eliminar</button>
                        </div>
                    </td>
                </tr>`;
            }).join('');

            document.querySelectorAll('.unit-edit').forEach(btn => {
                btn.addEventListener('click', async () => {
                    const res = await fetch(API + '/' + btn.dataset.id, { headers: headers('GET') });
                    const item = await res.json();
                    openModal(item);
                });
            });
            document.querySelectorAll('.unit-delete').forEach(btn => {
                btn.addEventListener('click', async () => {
                    if (!confirm(`¿Eliminar la habitación "${btn.dataset.name}"?`)) return;
                    try {
                        await fetch(API + '/' + btn.dataset.id, { method: 'DELETE', headers: headers('DELETE') });
                        toast('Habitación eliminada', 'success');
                        loadData();
                    } catch (e) { toast('Error al eliminar', 'error'); }
                });
            });
        } catch (e) {
            console.error(e);
            list.innerHTML = '<tr><td colspan="4" style="text-align:center; padding:40px; color:#dc2626;">Error al cargar</td></tr>';
        }
    }

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const id = document.getElementById('unit-id').value;
        const body = {
            room_type_id: parseInt(document.getElementById('unit-room-type').value) || 0,
            name: document.getElementById('unit-name').value.trim(),
            status: document.getElementById('unit-status').value,
            sort_order: parseInt(document.getElementById('unit-sort').value) || 0,
            notes: document.getElementById('unit-notes').value.trim(),
        };
        try {
            const url = id ? API + '/' + id : API;
            const method = id ? 'PUT' : 'POST';
            const res = await fetch(url, { method, headers: headers(method), body: JSON.stringify(body) });
            const data = await res.json();
            if (data.ok) {
                toast(id ? 'Habitación actualizada' : 'Habitación creada', 'success');
                closeModal();
                loadData();
            } else { toast(data.message || 'Error al guardar', 'error'); }
        } catch (err) { toast('Error de conexión', 'error'); }
    });

    addBtn.addEventListener('click', () => openModal(null));
    document.querySelectorAll('.close-modal').forEach(btn => btn.addEventListener('click', closeModal));
    modal.addEventListener('click', (e) => { if (e.target === modal) closeModal(); });

    loadData();
});
</script>
