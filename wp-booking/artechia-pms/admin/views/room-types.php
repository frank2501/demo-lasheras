<?php
/**
 * Room Types admin view — modal-based CRUD with photos and amenities.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

// Amenities list — also used by JS
$available_amenities = [
    'wifi' => 'WiFi', 'ac' => 'Aire Acondicionado', 'heating' => 'Calefacción',
    'tv' => 'TV', 'minibar' => 'Minibar', 'safe' => 'Caja Fuerte',
    'balcony' => 'Balcón', 'pool_view' => 'Vista Piscina', 'garden_view' => 'Vista Jardín',
    'parking' => 'Estacionamiento', 'kitchen' => 'Cocina', 'jacuzzi' => 'Jacuzzi',
    'fireplace' => 'Hogar/Chimenea', 'bbq' => 'Parrilla', 'washer' => 'Lavarropas',
];

// Ensure media uploader scripts are enqueued
wp_enqueue_media();
?>

<div class="wrap artechia-wrap" id="artechia-room-types-app">

    <!-- Header -->
    <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:16px;">
        <div>
            <h2 style="margin:0; font-size:18px; font-weight:700; color:#1e293b;">Tipos de Habitación</h2>
            <p style="color:#64748b; font-size:13px; margin:4px 0 0;">Categorías de habitaciones con capacidad, amenities y fotos.</p>
        </div>
        <button type="button" class="button button-primary" id="add-type-btn" style="display:inline-flex; align-items:center; gap:6px; height:36px; border-radius:6px;">
            <span class="dashicons dashicons-plus" style="font-size:16px; width:16px; height:16px;"></span> Nuevo Tipo
        </button>
    </div>

    <!-- Summary Cards -->
    <div id="type-stats" style="display:grid; grid-template-columns:repeat(auto-fit, minmax(160px, 1fr)); gap:12px; margin-bottom:16px;">
        <div style="background:#fff; border:1px solid #e5e7eb; border-radius:8px; padding:14px 16px; display:flex; align-items:center; gap:12px;">
            <div style="width:40px; height:40px; border-radius:10px; background:#eff6ff; display:flex; align-items:center; justify-content:center;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#3b82f6" stroke-width="2"><path d="M2 4v16h20V8h-8l-2-4H2z"/></svg>
            </div>
            <div>
                <div id="tstat-total" style="font-size:22px; font-weight:700; color:#1e293b;">—</div>
                <div style="font-size:11px; color:#94a3b8; text-transform:uppercase; letter-spacing:0.5px;">Tipos</div>
            </div>
        </div>
        <div style="background:#fff; border:1px solid #e5e7eb; border-radius:8px; padding:14px 16px; display:flex; align-items:center; gap:12px;">
            <div style="width:40px; height:40px; border-radius:10px; background:#f0fdf4; display:flex; align-items:center; justify-content:center;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2"><rect x="3" y="3" width="7" height="9" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="16" width="7" height="5" rx="1"/></svg>
            </div>
            <div>
                <div id="tstat-units" style="font-size:22px; font-weight:700; color:#16a34a;">—</div>
                <div style="font-size:11px; color:#94a3b8; text-transform:uppercase; letter-spacing:0.5px;">Unidades</div>
            </div>
        </div>
        <div style="background:#fff; border:1px solid #e5e7eb; border-radius:8px; padding:14px 16px; display:flex; align-items:center; gap:12px;">
            <div style="width:40px; height:40px; border-radius:10px; background:#fef3c7; display:flex; align-items:center; justify-content:center;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#d97706" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>
            </div>
            <div>
                <div id="tstat-capacity" style="font-size:22px; font-weight:700; color:#d97706;">—</div>
                <div style="font-size:11px; color:#94a3b8; text-transform:uppercase; letter-spacing:0.5px;">Capacidad total</div>
            </div>
        </div>
    </div>

    <!-- Table -->
    <div style="background:#fff; border:1px solid #e5e7eb; border-radius:8px; overflow:hidden;">
        <table class="wp-list-table widefat fixed striped" style="border:none; border-radius:0;">
            <thead>
                <tr style="background:#f8fafc;">
                    <th style="font-weight:600; color:#475569; padding:12px 16px; width:40%;">Nombre</th>
                    <th style="font-weight:600; color:#475569; padding:12px 16px;">Capacidad</th>
                    <th style="font-weight:600; color:#475569; padding:12px 16px; text-align:center;">Unidades</th>
                    <th style="font-weight:600; color:#475569; padding:12px 16px; width:12%; text-align:center;">Estado</th>
                    <th style="font-weight:600; color:#475569; padding:12px 16px; width:120px;">Acciones</th>
                </tr>
            </thead>
            <tbody id="types-list">
                <tr><td colspan="5" style="text-align:center; padding:40px 20px; color:#94a3b8;">
                    <span class="dashicons dashicons-update spin" style="font-size:24px; width:24px; height:24px; animation:rotation 1s linear infinite;"></span><br>Cargando...
                </td></tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: New/Edit Room Type -->
<div id="type-modal" class="artechia-modal" style="display:none;">
    <div class="artechia-modal-content" style="width:620px; max-height:90vh;">
        <div class="artechia-modal-header">
            <div style="display:flex; align-items:center; gap:10px;">
                <span style="width:36px; height:36px; border-radius:10px; background:linear-gradient(135deg,#8b5cf6,#6d28d9); display:flex; align-items:center; justify-content:center; color:#fff;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 4v16h20V8h-8l-2-4H2z"/></svg>
                </span>
                <h2 id="type-modal-title" style="margin:0; font-size:18px; font-weight:700; color:#1e293b;">Nuevo Tipo de Habitación</h2>
            </div>
            <button type="button" class="close-modal">&times;</button>
        </div>
        <form id="type-form" style="overflow-y:auto; max-height:calc(90vh - 140px); padding-right:8px;">
            <input type="hidden" id="type-id" value="">
            <div style="display:flex; flex-direction:column; gap:14px; padding:4px 0;">
                <!-- Basic Info -->
                <div class="artechia-field">
                    <label>Nombre <span style="color:#dc2626;">*</span></label>
                    <input type="text" id="type-name" required placeholder="Ej: Suite Premium">
                </div>
                <div class="artechia-field">
                    <label>Descripción</label>
                    <textarea id="type-description" rows="2" style="width:100%; border:1px solid #e2e8f0; border-radius:8px; padding:10px 12px; font-size:13px; resize:vertical;" placeholder="Descripción del tipo de habitación..."></textarea>
                </div>

                <!-- Capacity -->
                <div style="font-size:12px; text-transform:uppercase; letter-spacing:0.5px; color:#94a3b8; margin-top:4px; display:flex; align-items:center; gap:6px;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                    Capacidad
                </div>
                <div class="artechia-form-grid">
                    <div class="artechia-field">
                        <label>Máx. personas</label>
                        <input type="number" id="type-max-occupancy" min="1" value="2">
                    </div>
                    <div class="artechia-field" style="display:flex; align-items:flex-end;">
                        <label style="display:flex; align-items:center; gap:6px; margin-bottom:12px; cursor:pointer;">
                            <input type="checkbox" id="type-allow-children"> Acepta niños
                        </label>
                    </div>
                </div>

                <!-- Amenities -->
                <div style="font-size:12px; text-transform:uppercase; letter-spacing:0.5px; color:#94a3b8; display:flex; align-items:center; gap:6px;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                    Amenities
                </div>
                <div id="type-amenities-grid" style="display:flex; flex-wrap:wrap; gap:8px;">
                    <?php foreach ( $available_amenities as $key => $label ) : ?>
                        <label style="display:inline-flex; align-items:center; gap:4px; font-size:13px; padding:4px 10px; border:1px solid #e5e7eb; border-radius:6px; cursor:pointer; transition:all 0.15s; background:#fff;">
                            <input type="checkbox" class="type-amenity-cb" value="<?php echo esc_attr( $key ); ?>" style="margin:0;">
                            <?php echo esc_html( $label ); ?>
                        </label>
                    <?php endforeach; ?>
                </div>

                <!-- Photos -->
                <div style="font-size:12px; text-transform:uppercase; letter-spacing:0.5px; color:#94a3b8; display:flex; align-items:center; gap:6px;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                    Fotos
                </div>
                <div id="type-photos-preview" style="display:flex; flex-wrap:wrap; gap:8px; min-height:40px;"></div>
                <button type="button" id="type-add-photo-btn" class="button" style="align-self:flex-start; border-radius:6px; display:inline-flex; align-items:center; gap:4px;">
                    <span class="dashicons dashicons-format-image" style="font-size:16px; width:16px; height:16px;"></span> Agregar fotos
                </button>

                <!-- Sort & Status -->
                <div class="artechia-form-grid">
                    <div class="artechia-field">
                        <label>Orden</label>
                        <input type="number" id="type-sort" min="0" value="0">
                    </div>
                    <div class="artechia-field">
                        <label>Estado</label>
                        <select id="type-status">
                            <option value="active">Activo</option>
                            <option value="inactive">Inactivo</option>
                        </select>
                    </div>
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

<style>
#type-amenities-grid label:has(input:checked) {
    background: #eef2ff;
    border-color: #818cf8;
    color: #4338ca;
}
.rt-photo-thumb {
    position: relative;
    width: 72px; height: 72px;
    border-radius: 8px; overflow: hidden;
    border: 1px solid #e5e7eb;
}
.rt-photo-thumb img {
    width: 100%; height: 100%; object-fit: cover;
}
.rt-photo-thumb .rt-photo-remove {
    position: absolute; top: 2px; right: 2px;
    width: 20px; height: 20px; border-radius: 50%;
    background: rgba(0,0,0,0.6); color: #fff;
    border: none; font-size: 14px; cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    line-height: 1; padding: 0;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const API = artechiaPMS.restUrl + 'admin/room-types';
    const NONCE = artechiaPMS.nonce;
    const modal = document.getElementById('type-modal');
    const form = document.getElementById('type-form');
    const list = document.getElementById('types-list');
    const addBtn = document.getElementById('add-type-btn');
    const photosPreview = document.getElementById('type-photos-preview');
    const typeColors = ['#3b82f6','#10b981','#f59e0b','#8b5cf6','#ec4899','#14b8a6'];

    let currentPhotos = []; // [{url, id}]

    function headers(method) {
        const h = { 'X-WP-Nonce': NONCE };
        if (method !== 'GET' && method !== 'DELETE') h['Content-Type'] = 'application/json';
        return h;
    }

    function toast(msg, type) {
        if (window.artechiaPMS && window.artechiaPMS.toast) window.artechiaPMS.toast.show(msg, type);
        else alert(msg);
    }

    // Photos
    function renderPhotos() {
        photosPreview.innerHTML = currentPhotos.map((p, i) =>
            `<div class="rt-photo-thumb">
                <img src="${p.url}" alt="">
                <button type="button" class="rt-photo-remove" data-idx="${i}">&times;</button>
            </div>`
        ).join('');
        photosPreview.querySelectorAll('.rt-photo-remove').forEach(btn => {
            btn.addEventListener('click', () => {
                currentPhotos.splice(parseInt(btn.dataset.idx), 1);
                renderPhotos();
            });
        });
    }

    document.getElementById('type-add-photo-btn').addEventListener('click', () => {
        const frame = wp.media({
            title: 'Seleccionar fotos',
            button: { text: 'Agregar' },
            multiple: true,
        });
        frame.on('select', () => {
            frame.state().get('selection').each(att => {
                const url = att.attributes.sizes?.medium?.url || att.attributes.url;
                currentPhotos.push({ url: att.attributes.url, id: att.id, thumbnail: url });
            });
            renderPhotos();
        });
        frame.open();
    });

    // Open modal
    function openModal(item) {
        document.getElementById('type-modal-title').textContent = item ? 'Editar Tipo de Habitación' : 'Nuevo Tipo de Habitación';
        document.getElementById('type-id').value = item ? item.id : '';
        document.getElementById('type-name').value = item ? item.name : '';
        document.getElementById('type-description').value = item ? (item.description || '') : '';
        document.getElementById('type-max-occupancy').value = item ? (item.max_occupancy || 2) : 2;
        document.getElementById('type-allow-children').checked = item ? (parseInt(item.max_children) > 0) : false;
        document.getElementById('type-sort').value = item ? (item.sort_order || 0) : 0;
        document.getElementById('type-status').value = item ? (item.status || 'active') : 'active';

        // Amenities
        const selectedAmenities = item ? (typeof item.amenities_json === 'string' ? JSON.parse(item.amenities_json || '[]') : (item.amenities_json || [])) : [];
        document.querySelectorAll('.type-amenity-cb').forEach(cb => {
            cb.checked = selectedAmenities.includes(cb.value);
        });

        // Photos
        currentPhotos = item ? (typeof item.photos_json === 'string' ? JSON.parse(item.photos_json || '[]') : (item.photos_json || [])) : [];
        if (!Array.isArray(currentPhotos)) currentPhotos = [];
        renderPhotos();

        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
        setTimeout(() => document.getElementById('type-name').focus(), 100);
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

            const el = (id, v) => { const e = document.getElementById(id); if (e) e.textContent = v; };
            el('tstat-total', items.length);
            let totalUnits = 0, totalCap = 0;
            items.forEach(rt => {
                totalUnits += parseInt(rt.unit_count) || 0;
                totalCap += (parseInt(rt.max_occupancy) || 0) * (parseInt(rt.unit_count) || 0);
            });
            el('tstat-units', totalUnits);
            el('tstat-capacity', totalCap);

            if (!items.length) {
                list.innerHTML = `<tr><td colspan="5" style="text-align:center; padding:60px 20px;">
                    <div style="margin-bottom:12px;"><svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#cbd5e1" stroke-width="1.5"><path d="M2 4v16h20V8h-8l-2-4H2z"/></svg></div>
                    <div style="font-size:16px; font-weight:600; color:#1e293b; margin-bottom:6px;">No hay tipos de habitación</div>
                    <div style="font-size:13px; color:#94a3b8; margin-bottom:16px;">Creá tu primer tipo de habitación.</div>
                    <button class="button button-primary" onclick="document.getElementById('add-type-btn').click()" style="border-radius:6px;">+ Nuevo Tipo</button>
                </td></tr>`;
                return;
            }

            list.innerHTML = items.map((rt, i) => {
                const color = typeColors[i % typeColors.length];
                const initials = (rt.name || '').substring(0, 2).toUpperCase();
                const isActive = rt.status === 'active';
                const badgeBg = isActive ? '#dcfce7' : '#fee2e2';
                const badgeColor = isActive ? '#166534' : '#991b1b';
                const badgeLabel = isActive ? 'Activo' : 'Inactivo';
                const childBadge = parseInt(rt.max_children) > 0
                    ? '<span style="background:#e0f2fe; color:#0369a1; padding:1px 6px; border-radius:8px; font-size:10px; font-weight:600; margin-left:4px;">Niños ✓</span>'
                    : '';
                return `<tr>
                    <td style="padding:12px 16px;">
                        <div style="display:flex; align-items:center; gap:10px;">
                            <div style="width:36px; height:36px; border-radius:8px; background:${color}; color:#fff; display:flex; align-items:center; justify-content:center; font-size:12px; font-weight:700; flex-shrink:0;">${initials}</div>
                            <strong style="color:#1e293b; cursor:pointer;" class="type-edit" data-id="${rt.id}">${rt.name || '—'}</strong>
                        </div>
                    </td>
                    <td style="padding:12px 16px;">
                        <span style="font-size:13px;">${rt.max_occupancy} pers.</span>
                        ${childBadge}
                    </td>
                    <td style="padding:12px 16px; text-align:center;">
                        <span style="background:#e0f2fe; color:#0369a1; padding:2px 8px; border-radius:10px; font-size:12px; font-weight:600;">${rt.unit_count || 0}</span>
                    </td>
                    <td style="padding:12px 16px; text-align:center;">
                        <span style="background:${badgeBg}; color:${badgeColor}; padding:3px 10px; border-radius:12px; font-size:11px; font-weight:600;">${badgeLabel}</span>
                    </td>
                    <td style="padding:12px 16px;">
                        <div style="display:flex; gap:6px;">
                            <button type="button" class="button button-small type-edit" data-id="${rt.id}" style="border-radius:4px;">Editar</button>
                            <button type="button" class="button button-small type-delete" data-id="${rt.id}" data-name="${rt.name}" style="border-radius:4px; color:#dc2626; border-color:#fecaca;">Eliminar</button>
                        </div>
                    </td>
                </tr>`;
            }).join('');

            document.querySelectorAll('.type-edit').forEach(btn => {
                btn.addEventListener('click', async () => {
                    const res = await fetch(API + '/' + btn.dataset.id, { headers: headers('GET') });
                    const item = await res.json();
                    openModal(item);
                });
            });
            document.querySelectorAll('.type-delete').forEach(btn => {
                btn.addEventListener('click', async () => {
                    if (!confirm(`¿Eliminar el tipo "${btn.dataset.name}"? Las habitaciones asociadas quedarán sin tipo.`)) return;
                    try {
                        await fetch(API + '/' + btn.dataset.id, { method: 'DELETE', headers: headers('DELETE') });
                        toast('Tipo eliminado', 'success');
                        loadData();
                    } catch (e) { toast('Error al eliminar', 'error'); }
                });
            });
        } catch (e) {
            console.error(e);
            list.innerHTML = '<tr><td colspan="5" style="text-align:center; padding:40px; color:#dc2626;">Error al cargar</td></tr>';
        }
    }

    // Save
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const id = document.getElementById('type-id').value;
        const maxOcc = parseInt(document.getElementById('type-max-occupancy').value) || 2;
        const allowChildren = document.getElementById('type-allow-children').checked;

        // Collect amenities
        const amenities = [];
        document.querySelectorAll('.type-amenity-cb').forEach(cb => {
            if (cb.checked) amenities.push(cb.value);
        });

        const body = {
            name: document.getElementById('type-name').value.trim(),
            description: document.getElementById('type-description').value.trim(),
            max_occupancy: maxOcc,
            max_adults: maxOcc,
            max_children: allowChildren ? maxOcc : 0,
            base_occupancy: maxOcc,
            amenities: amenities,
            photos: currentPhotos,
            sort_order: parseInt(document.getElementById('type-sort').value) || 0,
            status: document.getElementById('type-status').value,
        };

        try {
            const url = id ? API + '/' + id : API;
            const method = id ? 'PUT' : 'POST';
            const res = await fetch(url, { method, headers: headers(method), body: JSON.stringify(body) });
            const data = await res.json();
            if (data.ok) {
                toast(id ? 'Tipo actualizado' : 'Tipo creado', 'success');
                closeModal();
                loadData();
            } else { toast(data.message || 'Error al guardar', 'error'); }
        } catch (err) { toast('Error de conexión', 'error'); }
    });

    // Events
    addBtn.addEventListener('click', () => openModal(null));
    document.querySelectorAll('.close-modal').forEach(btn => btn.addEventListener('click', closeModal));
    modal.addEventListener('click', (e) => { if (e.target === modal) closeModal(); });

    loadData();
});
</script>
