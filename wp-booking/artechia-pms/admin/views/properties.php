<?php
/**
 * Properties admin view — modal-based CRUD.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
?>

<div class="wrap artechia-wrap" id="artechia-properties-app">

    <!-- Header -->
    <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:16px;">
        <div>
            <h2 style="margin:0; font-size:18px; font-weight:700; color:#1e293b;">Propiedades</h2>
            <p style="color:#64748b; font-size:13px; margin:4px 0 0;">Gestiona las propiedades del sistema.</p>
        </div>
        <button type="button" class="button button-primary" id="add-property-btn" style="display:inline-flex; align-items:center; gap:6px; height:36px; border-radius:6px;">
            <span class="dashicons dashicons-plus" style="font-size:16px; width:16px; height:16px;"></span> Nueva Propiedad
        </button>
    </div>

    <!-- Summary Cards -->
    <div id="prop-stats" style="display:grid; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)); gap:12px; margin-bottom:16px;">
        <div style="background:#fff; border:1px solid #e5e7eb; border-radius:8px; padding:14px 16px; display:flex; align-items:center; gap:12px;">
            <div style="width:40px; height:40px; border-radius:10px; background:#eff6ff; display:flex; align-items:center; justify-content:center;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#3b82f6" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
            </div>
            <div>
                <div id="pstat-total" style="font-size:22px; font-weight:700; color:#1e293b;">—</div>
                <div style="font-size:11px; color:#94a3b8; text-transform:uppercase; letter-spacing:0.5px;">Propiedades</div>
            </div>
        </div>
        <div style="background:#fff; border:1px solid #e5e7eb; border-radius:8px; padding:14px 16px; display:flex; align-items:center; gap:12px;">
            <div style="width:40px; height:40px; border-radius:10px; background:#f0fdf4; display:flex; align-items:center; justify-content:center;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            </div>
            <div>
                <div id="pstat-active" style="font-size:22px; font-weight:700; color:#16a34a;">—</div>
                <div style="font-size:11px; color:#94a3b8; text-transform:uppercase; letter-spacing:0.5px;">Activas</div>
            </div>
        </div>
    </div>

    <!-- Table -->
    <div style="background:#fff; border:1px solid #e5e7eb; border-radius:8px; overflow:hidden;">
        <table class="wp-list-table widefat fixed striped" style="border:none; border-radius:0;">
            <thead>
                <tr style="background:#f8fafc;">
                    <th style="font-weight:600; color:#475569; padding:12px 16px; width:40%;">Nombre</th>
                    <th style="font-weight:600; color:#475569; padding:12px 16px;">Dirección</th>
                    <th style="font-weight:600; color:#475569; padding:12px 16px; width:12%; text-align:center;">Estado</th>
                    <th style="font-weight:600; color:#475569; padding:12px 16px; width:120px;">Acciones</th>
                </tr>
            </thead>
            <tbody id="properties-list">
                <tr><td colspan="4" style="text-align:center; padding:40px 20px; color:#94a3b8;">
                    <span class="dashicons dashicons-update spin" style="font-size:24px; width:24px; height:24px; animation:rotation 1s linear infinite;"></span><br>Cargando...
                </td></tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: New/Edit Property -->
<div id="property-modal" class="artechia-modal" style="display:none;">
    <div class="artechia-modal-content" style="width:520px;">
        <div class="artechia-modal-header">
            <div style="display:flex; align-items:center; gap:10px;">
                <span style="width:36px; height:36px; border-radius:10px; background:linear-gradient(135deg,#3b82f6,#2563eb); display:flex; align-items:center; justify-content:center; color:#fff;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                </span>
                <h2 id="prop-modal-title" style="margin:0; font-size:18px; font-weight:700; color:#1e293b;">Nueva Propiedad</h2>
            </div>
            <button type="button" class="close-modal">&times;</button>
        </div>
        <form id="property-form">
            <input type="hidden" id="prop-id" value="">
            <div style="display:flex; flex-direction:column; gap:14px; padding:4px 0;">
                <div class="artechia-field">
                    <label>Nombre <span style="color:#dc2626;">*</span></label>
                    <input type="text" id="prop-name" required placeholder="Ej: Complejo Sol y Luna">
                </div>
                <div class="artechia-field">
                    <label>Dirección</label>
                    <input type="text" id="prop-address" placeholder="Ej: Av. San Martín 1234, Córdoba">
                </div>
                <div class="artechia-field">
                    <label>Descripción</label>
                    <textarea id="prop-description" rows="3" style="width:100%; border:1px solid #e2e8f0; border-radius:8px; padding:10px 12px; font-size:13px; resize:vertical;" placeholder="Descripción breve de la propiedad..."></textarea>
                </div>
                <div class="artechia-field">
                    <label>Estado</label>
                    <select id="prop-status">
                        <option value="active">Activa</option>
                        <option value="inactive">Inactiva</option>
                    </select>
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
    const API = artechiaPMS.restUrl + 'admin/properties';
    const NONCE = artechiaPMS.nonce;
    const modal = document.getElementById('property-modal');
    const form = document.getElementById('property-form');
    const list = document.getElementById('properties-list');
    const addBtn = document.getElementById('add-property-btn');

    function headers(method) {
        const h = { 'X-WP-Nonce': NONCE };
        if (method !== 'GET' && method !== 'DELETE') h['Content-Type'] = 'application/json';
        return h;
    }

    function toast(msg, type) {
        if (window.artechiaPMS && window.artechiaPMS.toast) {
            window.artechiaPMS.toast.show(msg, type);
        } else {
            alert(msg);
        }
    }

    // Open modal
    function openModal(item) {
        document.getElementById('prop-modal-title').textContent = item ? 'Editar Propiedad' : 'Nueva Propiedad';
        document.getElementById('prop-id').value = item ? item.id : '';
        document.getElementById('prop-name').value = item ? item.name : '';
        document.getElementById('prop-address').value = item ? (item.address || '') : '';
        document.getElementById('prop-description').value = item ? (item.description || '') : '';
        document.getElementById('prop-status').value = item ? (item.status || 'active') : 'active';
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
        setTimeout(() => document.getElementById('prop-name').focus(), 100);
    }

    function closeModal() {
        modal.style.display = 'none';
        document.body.style.overflow = '';
    }

    // Fetch & render
    async function loadData() {
        try {
            const res = await fetch(API, { headers: headers('GET') });
            const items = await res.json();

            // Stats
            const el = (id, v) => { const e = document.getElementById(id); if (e) e.textContent = v; };
            el('pstat-total', items.length);
            el('pstat-active', items.filter(p => p.status === 'active').length);

            if (!items.length) {
                list.innerHTML = `<tr><td colspan="4" style="text-align:center; padding:60px 20px;">
                    <div style="margin-bottom:12px;"><svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#cbd5e1" stroke-width="1.5"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg></div>
                    <div style="font-size:16px; font-weight:600; color:#1e293b; margin-bottom:6px;">No hay propiedades</div>
                    <div style="font-size:13px; color:#94a3b8; margin-bottom:16px;">Creá tu primera propiedad para comenzar.</div>
                    <button class="button button-primary" onclick="document.getElementById('add-property-btn').click()" style="border-radius:6px;">+ Nueva Propiedad</button>
                </td></tr>`;
                return;
            }

            list.innerHTML = items.map(p => {
                const isActive = p.status === 'active';
                const badgeBg = isActive ? '#dcfce7' : '#fee2e2';
                const badgeColor = isActive ? '#166534' : '#991b1b';
                const badgeLabel = isActive ? 'Activa' : 'Inactiva';
                return `<tr>
                    <td style="padding:12px 16px;">
                        <div style="display:flex; align-items:center; gap:10px;">
                            <div style="width:36px; height:36px; border-radius:8px; background:linear-gradient(135deg,#3b82f6,#2563eb); color:#fff; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                            </div>
                            <div>
                                <strong style="color:#1e293b; cursor:pointer;" class="prop-edit" data-id="${p.id}">${p.name || '—'}</strong>
                            </div>
                        </div>
                    </td>
                    <td style="padding:12px 16px;"><span style="font-size:12px; color:#64748b;">${p.address || '—'}</span></td>
                    <td style="padding:12px 16px; text-align:center;">
                        <span style="background:${badgeBg}; color:${badgeColor}; padding:3px 10px; border-radius:12px; font-size:11px; font-weight:600;">${badgeLabel}</span>
                    </td>
                    <td style="padding:12px 16px;">
                        <div style="display:flex; gap:6px;">
                            <button type="button" class="button button-small prop-edit" data-id="${p.id}" style="border-radius:4px;">Editar</button>
                            <button type="button" class="button button-small prop-delete" data-id="${p.id}" data-name="${p.name}" style="border-radius:4px; color:#dc2626; border-color:#fecaca;">Eliminar</button>
                        </div>
                    </td>
                </tr>`;
            }).join('');

            // Bind edit buttons
            document.querySelectorAll('.prop-edit').forEach(btn => {
                btn.addEventListener('click', async () => {
                    const id = btn.dataset.id;
                    const res = await fetch(API + '/' + id, { headers: headers('GET') });
                    const item = await res.json();
                    openModal(item);
                });
            });

            // Bind delete buttons
            document.querySelectorAll('.prop-delete').forEach(btn => {
                btn.addEventListener('click', async () => {
                    const name = btn.dataset.name;
                    if (!confirm(`¿Eliminar la propiedad "${name}"? Esta acción no se puede deshacer.`)) return;
                    try {
                        await fetch(API + '/' + btn.dataset.id, { method: 'DELETE', headers: headers('DELETE') });
                        toast('Propiedad eliminada', 'success');
                        loadData();
                    } catch (e) {
                        toast('Error al eliminar', 'error');
                    }
                });
            });

        } catch (e) {
            console.error(e);
            list.innerHTML = '<tr><td colspan="4" style="text-align:center; padding:40px; color:#dc2626;">Error al cargar propiedades</td></tr>';
        }
    }

    // Save
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const id = document.getElementById('prop-id').value;
        const body = {
            name: document.getElementById('prop-name').value.trim(),
            address: document.getElementById('prop-address').value.trim(),
            description: document.getElementById('prop-description').value.trim(),
            status: document.getElementById('prop-status').value,
        };
        try {
            const url = id ? API + '/' + id : API;
            const method = id ? 'PUT' : 'POST';
            const res = await fetch(url, { method, headers: headers(method), body: JSON.stringify(body) });
            const data = await res.json();
            if (data.ok) {
                toast(id ? 'Propiedad actualizada' : 'Propiedad creada', 'success');
                closeModal();
                loadData();
            } else {
                toast(data.message || 'Error al guardar', 'error');
            }
        } catch (err) {
            toast('Error de conexión', 'error');
        }
    });

    // Events
    addBtn.addEventListener('click', () => openModal(null));
    document.querySelectorAll('.close-modal').forEach(btn => btn.addEventListener('click', closeModal));
    modal.addEventListener('click', (e) => { if (e.target === modal) closeModal(); });

    // Init
    loadData();
});
</script>
