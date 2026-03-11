<?php
/**
 * Admin View: Email Templates – redesigned.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
?>

<div class="wrap artechia-wrap artechia-emails-wrap">

    <!-- Notifications -->
    <div id="email-notice" class="notice is-dismissible" style="display:none;"><p></p></div>

    <!-- ============================================================ -->
    <!-- LIST VIEW                                                      -->
    <!-- ============================================================ -->
    <div id="email-list-view">

        <!-- Stats (populated by JS) -->
        <div id="email-stats" style="display:grid; grid-template-columns:repeat(auto-fit, minmax(160px, 1fr)); gap:12px; margin-bottom:16px;">
            <div style="background:#fff; border:1px solid #e5e7eb; border-radius:8px; padding:14px 16px; display:flex; align-items:center; gap:12px;">
                <div style="width:40px; height:40px; border-radius:10px; background:#eff6ff; display:flex; align-items:center; justify-content:center; font-size:20px;">✉️</div>
                <div>
                    <div id="stat-total" style="font-size:22px; font-weight:700; color:#1e293b;">—</div>
                    <div style="font-size:11px; color:#94a3b8; text-transform:uppercase; letter-spacing:0.5px;">Plantillas</div>
                </div>
            </div>
            <div style="background:#fff; border:1px solid #e5e7eb; border-radius:8px; padding:14px 16px; display:flex; align-items:center; gap:12px;">
                <div style="width:40px; height:40px; border-radius:10px; background:#f0fdf4; display:flex; align-items:center; justify-content:center; font-size:20px;">✅</div>
                <div>
                    <div id="stat-active" style="font-size:22px; font-weight:700; color:#16a34a;">—</div>
                    <div style="font-size:11px; color:#94a3b8; text-transform:uppercase; letter-spacing:0.5px;">Activas</div>
                </div>
            </div>
            <div style="background:#fff; border:1px solid #e5e7eb; border-radius:8px; padding:14px 16px; display:flex; align-items:center; gap:12px;">
                <div style="width:40px; height:40px; border-radius:10px; background:#fee2e2; display:flex; align-items:center; justify-content:center; font-size:20px;">🚫</div>
                <div>
                    <div id="stat-inactive" style="font-size:22px; font-weight:700; color:#dc2626;">—</div>
                    <div style="font-size:11px; color:#94a3b8; text-transform:uppercase; letter-spacing:0.5px;">Inactivas</div>
                </div>
            </div>
        </div>

        <table class="wp-list-table widefat fixed striped artechia-table">
            <thead>
                <tr>
                    <th style="width:30%;"><?php esc_html_e( 'Plantilla', 'artechia-pms' ); ?></th>
                    <th><?php esc_html_e( 'Asunto', 'artechia-pms' ); ?></th>
                    <th style="width:10%;"><?php esc_html_e( 'Estado', 'artechia-pms' ); ?></th>
                    <th style="width:14%;"><?php esc_html_e( 'Última Mod.', 'artechia-pms' ); ?></th>
                </tr>
            </thead>
            <tbody id="email-table-body">
                <tr><td colspan="4" style="text-align:center; padding:24px; color:#94a3b8;">⏳ Cargando...</td></tr>
            </tbody>
        </table>
    </div>

    <!-- ============================================================ -->
    <!-- EDIT VIEW                                                      -->
    <!-- ============================================================ -->
    <div id="email-edit-view" style="display:none;">
        <input type="hidden" id="edit-id">

        <!-- Back button + title -->
        <div style="display:flex; align-items:center; gap:12px; margin-bottom:20px;">
            <button type="button" id="btn-back-list" style="width:36px; height:36px; border-radius:8px; border:1px solid #e2e8f0; background:#fff; display:flex; align-items:center; justify-content:center; cursor:pointer; font-size:16px; transition:all 0.2s;">←</button>
            <div>
                <h2 id="edit-title" style="margin:0; font-size:18px; font-weight:700; color:#1e293b;"></h2>
                <div style="font-size:12px; color:#94a3b8; margin-top:2px;">Editá el asunto, cuerpo y opciones de esta plantilla</div>
            </div>
        </div>

        <div style="display:flex; gap:20px; flex-wrap:wrap;">
            <!-- EDITOR COLUMN -->
            <div style="flex:2; min-width:400px;">

                <!-- Subject -->
                <div class="artechia-email-panel">
                    <label class="artechia-email-label">✏️ <?php esc_html_e( 'Asunto', 'artechia-pms' ); ?></label>
                    <input type="text" id="edit-subject" class="artechia-email-input" placeholder="Asunto del email...">
                </div>

                <!-- Body -->
                <div class="artechia-email-panel" style="margin-top:12px;">
                    <label class="artechia-email-label">📝 <?php esc_html_e( 'Cuerpo del Mensaje (HTML)', 'artechia-pms' ); ?></label>
                    <textarea id="edit-body" rows="18" class="artechia-email-textarea" placeholder="HTML del email..."></textarea>
                    <span style="font-size:11px; color:#94a3b8; margin-top:4px; display:block;">Se admite HTML básico. Usá las variables disponibles para personalizar.</span>
                </div>

                <!-- Options + Save -->
                <div style="display:flex; align-items:center; justify-content:space-between; margin-top:12px; padding:14px 18px; background:#fff; border:1px solid #e5e7eb; border-radius:10px;">
                    <label style="display:flex; align-items:center; gap:8px; cursor:pointer; font-size:13px; color:#475569;">
                        <input type="checkbox" id="edit-active">
                        <?php esc_html_e( 'Habilitar envío automático', 'artechia-pms' ); ?>
                    </label>
                    <div style="display:flex; align-items:center; gap:8px;">
                        <span class="spinner" id="save-spinner" style="float:none;"></span>
                        <button type="button" class="button button-primary" id="btn-save" style="height:36px; line-height:34px; border-radius:6px;">💾 <?php esc_html_e( 'Guardar Cambios', 'artechia-pms' ); ?></button>
                    </div>
                </div>

                <!-- Placeholders -->
                <div style="margin-top:12px; padding:14px 18px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px;">
                    <label class="artechia-email-label" style="margin-bottom:8px;">🏷️ <?php esc_html_e( 'Variables disponibles', 'artechia-pms' ); ?></label>
                    <div id="placeholders-list" style="display:flex; flex-wrap:wrap; gap:6px;"></div>
                </div>
            </div>

            <!-- PREVIEW COLUMN -->
            <div style="flex:1; min-width:300px;">
                <div style="background:#fff; border:1px solid #e5e7eb; border-radius:10px; overflow:hidden; position:sticky; top:40px;">
                    <!-- Preview Header -->
                    <div style="padding:14px 18px; background:#f8fafc; border-bottom:1px solid #e5e7eb;">
                        <div style="font-size:14px; font-weight:700; color:#1e293b; display:flex; align-items:center; gap:8px;">👁️ <?php esc_html_e( 'Vista Previa y Pruebas', 'artechia-pms' ); ?></div>
                    </div>

                    <div style="padding:16px 18px;">
                        <!-- Booking Code -->
                        <div class="artechia-email-field">
                            <label style="font-size:12px; font-weight:600; color:#475569; margin-bottom:4px; display:block;"><?php esc_html_e( 'Código de Reserva', 'artechia-pms' ); ?></label>
                            <input type="text" id="preview-booking-code" placeholder="Ej: ART260301..." class="artechia-email-input" style="font-family:monospace; font-size:12px;">
                        </div>

                        <button type="button" class="button" id="btn-preview" style="width:100%; margin-top:8px; border-radius:6px; height:34px;"><?php esc_html_e( '👁️ Generar Vista Previa', 'artechia-pms' ); ?></button>

                        <!-- Preview Container -->
                        <div id="preview-container" style="margin-top:12px; border:1px solid #e2e8f0; border-radius:8px; padding:12px; min-height:180px; max-height:350px; overflow:auto; background:#fafafa; font-size:13px;">
                            <em style="color:#94a3b8;"><?php esc_html_e( 'La vista previa aparecerá aquí...', 'artechia-pms' ); ?></em>
                        </div>

                        <!-- Test Email -->
                        <div style="margin-top:14px; padding-top:14px; border-top:1px solid #e2e8f0;">
                            <label style="font-size:12px; font-weight:600; color:#475569; margin-bottom:4px; display:block;"><?php esc_html_e( 'Enviar prueba a:', 'artechia-pms' ); ?></label>
                            <input type="email" id="test-email" value="<?php echo esc_attr( wp_get_current_user()->user_email ); ?>" class="artechia-email-input" style="font-size:12px;">
                            <button type="button" class="button button-secondary" id="btn-send-test" style="width:100%; margin-top:6px; border-radius:6px; height:34px;">📧 <?php esc_html_e( 'Enviar Email de Prueba', 'artechia-pms' ); ?></button>
                            <div id="test-result" style="margin-top:8px; font-size:13px;"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- Styles -->
<style>
.artechia-email-panel {
    background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; padding: 16px 18px;
}
.artechia-email-label {
    display: block; font-weight: 600; margin-bottom: 8px; color: #475569; font-size: 13px;
}
.artechia-email-input {
    width: 100%; height: 38px; border: 1px solid #e2e8f0; border-radius: 8px;
    padding: 0 12px; font-size: 13px; color: #1e293b; background: #fff;
    transition: border-color 0.2s, box-shadow 0.2s; box-sizing: border-box;
}
.artechia-email-input:focus {
    border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,0.1); outline: none;
}
.artechia-email-textarea {
    width: 100%; border: 1px solid #e2e8f0; border-radius: 8px;
    padding: 12px; font-size: 12px; color: #1e293b; background: #fff;
    font-family: 'SF Mono', 'Monaco', 'Inconsolata', monospace; resize: vertical;
    transition: border-color 0.2s, box-shadow 0.2s; box-sizing: border-box;
    line-height: 1.6;
}
.artechia-email-textarea:focus {
    border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,0.1); outline: none;
}

/* Placeholder tags */
.artechia-ph-tag {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 3px 10px; background: #eff6ff; color: #1d4ed8; border-radius: 6px;
    font-size: 11px; font-family: monospace; cursor: pointer; border: 1px solid #bfdbfe;
    transition: all 0.2s;
}
.artechia-ph-tag:hover { background: #dbeafe; }

/* Event type icons */
.artechia-event-icon {
    width: 36px; height: 36px; border-radius: 8px; display: flex;
    align-items: center; justify-content: center; font-size: 16px; flex-shrink: 0;
}

/* Back button hover */
#btn-back-list:hover { background: #f1f5f9; border-color: #cbd5e1; }
</style>

<!-- JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    if ( typeof artechiaPMS === 'undefined' ) return;

    const api = {
        get: (path, params = {}) => {
            const url = new URL(artechiaPMS.restUrl + path);
            Object.keys(params).forEach(key => params[key] && url.searchParams.append(key, params[key]));
            return fetch(url, { headers: { 'X-WP-Nonce': artechiaPMS.nonce } }).then(r => r.json());
        },
        post: (path, data = {}) => fetch(artechiaPMS.restUrl + path, {
            method: 'POST',
            headers: { 'X-WP-Nonce': artechiaPMS.nonce, 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        }).then(r => r.json())
    };

    const listView = document.getElementById('email-list-view');
    const editView = document.getElementById('email-edit-view');
    const tbody = document.getElementById('email-table-body');
    const notice = document.getElementById('email-notice');

    const eventIcons = {
        'booking_confirmed': { icon: '✅', bg: '#f0fdf4', color: '#16a34a' },
        'booking_cancelled': { icon: '❌', bg: '#fee2e2', color: '#dc2626' },
        'booking_pending':   { icon: '⏳', bg: '#fef3c7', color: '#92400e' },
        'booking_modified':  { icon: '✏️', bg: '#eff6ff', color: '#2563eb' },
        'payment_received':  { icon: '💳', bg: '#f0fdf4', color: '#16a34a' },
        'check_in_reminder': { icon: '🔔', bg: '#fef3c7', color: '#92400e' },
        'review_request':    { icon: '⭐', bg: '#fef9c3', color: '#a16207' },
    };

    function getEventInfo(type) {
        return eventIcons[type] || { icon: '✉️', bg: '#f1f5f9', color: '#475569' };
    }

    function formatEventName(type) {
        const names = {
            'booking_confirmed':  'Reserva Confirmada',
            'booking_cancelled':  'Reserva Cancelada',
            'booking_pending':    'Reserva Pendiente',
            'booking_modified':   'Reserva Modificada',
            'booking_created':    'Reserva Creada',
            'payment_received':   'Pago Recibido',
            'payment_reminder':   'Recordatorio de Pago',
            'check_in_reminder':  'Recordatorio de Check-in',
            'check_out_reminder': 'Recordatorio de Check-out',
            'review_request':     'Solicitud de Reseña',
            'booking_review':     'Reseña de Reserva',
            'welcome':            'Bienvenida',
            'pre_arrival':        'Pre Llegada',
            'post_stay':          'Post Estadía',
        };
        return names[type] || (type || '').replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
    }

    // Load List
    function loadTemplates() {
        listView.style.display = 'block';
        editView.style.display = 'none';
        tbody.innerHTML = '<tr><td colspan="4" style="text-align:center; padding:24px; color:#94a3b8;">⏳ Cargando...</td></tr>';

        api.get('admin/email-templates').then(res => {
            if (!Array.isArray(res)) {
                tbody.innerHTML = '<tr><td colspan="4" style="text-align:center; padding:24px; color:#dc2626;">Error cargando plantillas.</td></tr>';
                return;
            }

            // Stats
            document.getElementById('stat-total').textContent = res.length;
            const activeCount = res.filter(t => parseInt(t.is_active)).length;
            document.getElementById('stat-active').textContent = activeCount;
            document.getElementById('stat-inactive').textContent = res.length - activeCount;

            if (res.length === 0) {
                tbody.innerHTML = '<tr><td colspan="4" style="text-align:center; padding:24px; color:#94a3b8;">No hay plantillas disponibles.</td></tr>';
                return;
            }
            
            tbody.innerHTML = res.map(t => {
                const ei = getEventInfo(t.event_type);
                const isActive = parseInt(t.is_active);
                const statusBg = isActive ? '#dcfce7' : '#fee2e2';
                const statusColor = isActive ? '#166534' : '#991b1b';
                const statusLabel = isActive ? 'Activo' : 'Inactivo';
                const modDate = t.updated_at ? new Date(t.updated_at).toLocaleDateString('es-AR', {day:'2-digit', month:'short', year:'numeric'}) : '—';

                return `
                <tr style="cursor:pointer;" class="email-row" data-template='${JSON.stringify(t).replace(/'/g, "&#39;")}'>
                    <td>
                        <div style="display:flex; align-items:center; gap:10px;">
                            <div class="artechia-event-icon" style="background:${ei.bg}; color:${ei.color};">${ei.icon}</div>
                            <div>
                                <strong style="color:#1e293b;">${formatEventName(t.event_type)}</strong>
                                <div class="row-actions"><span class="edit"><a href="#">Editar</a></span></div>
                            </div>
                        </div>
                    </td>
                    <td style="color:#475569; font-size:13px;">${t.subject}</td>
                    <td><span style="background:${statusBg}; color:${statusColor}; padding:3px 10px; border-radius:12px; font-size:11px; font-weight:600;">${statusLabel}</span></td>
                    <td style="color:#94a3b8; font-size:12px;">${modDate}</td>
                </tr>`;
            }).join('');

            document.querySelectorAll('.email-row').forEach(row => {
                row.onclick = () => openEdit(JSON.parse(row.dataset.template));
            });
        });
    }

    function openEdit(tmpl) {
        listView.style.display = 'none';
        editView.style.display = 'block';

        document.getElementById('edit-id').value = tmpl.id;
        document.getElementById('edit-title').textContent = formatEventName(tmpl.event_type);
        document.getElementById('edit-subject').value = tmpl.subject;
        document.getElementById('edit-body').value = tmpl.body_html;
        document.getElementById('edit-active').checked = parseInt(tmpl.is_active) === 1;

        // Render placeholder tags
        let placeholdersHtml = '';
        try {
            const placeholders = JSON.parse(tmpl.placeholders);
            if (Array.isArray(placeholders)) {
                placeholdersHtml = placeholders.map(p => 
                    `<span class="artechia-ph-tag" onclick="navigator.clipboard.writeText('${p}'); if(artechiaPMS.toast) artechiaPMS.toast.show('Copiado: ${p}', 'success');" title="Click para copiar">${p}</span>`
                ).join('');
            }
        } catch (e) {}
        document.getElementById('placeholders-list').innerHTML = placeholdersHtml || '<em style="color:#94a3b8; font-size:12px;">No hay variables definidas.</em>';
        
        // Reset preview
        document.getElementById('preview-container').innerHTML = '<em style="color:#94a3b8;">Hacé click en "Generar Vista Previa"...</em>';
        document.getElementById('test-result').innerHTML = '';
    }

    // Save
    document.getElementById('btn-save').onclick = function() {
        const id = document.getElementById('edit-id').value;
        const spinner = document.getElementById('save-spinner');
        const btn = this;

        const data = {
            subject: document.getElementById('edit-subject').value,
            body_html: document.getElementById('edit-body').value,
            is_active: document.getElementById('edit-active').checked ? 1 : 0
        };

        btn.disabled = true;
        spinner.classList.add('is-active');

        api.post(`admin/email-templates/${id}`, data).then(res => {
            btn.disabled = false;
            spinner.classList.remove('is-active');
            if (res.success) {
                showNotice('Plantilla guardada correctamente.', 'success');
            } else {
                showNotice(res.message || 'Error al guardar.', 'error');
            }
        }).catch(err => {
            btn.disabled = false;
            spinner.classList.remove('is-active');
            showNotice('Error de conexión.', 'error');
        });
    };

    // Preview
    document.getElementById('btn-preview').onclick = function() {
        const id = document.getElementById('edit-id').value;
        const code = document.getElementById('preview-booking-code').value.trim();
        const container = document.getElementById('preview-container');

        if (!code) {
            if (artechiaPMS.toast) artechiaPMS.toast.show('Ingresá un Código de Reserva primero.', 'error');
            return;
        }

        container.innerHTML = '<div style="text-align:center; padding:20px; color:#94a3b8;">⏳ Cargando...</div>';

        const data = {
            booking_code: code,
            subject: document.getElementById('edit-subject').value,
            body_html: document.getElementById('edit-body').value
        };

        api.post(`admin/email-templates/${id}/preview`, data).then(res => {
            if (res.code === 'not_found' || res.error) {
                container.innerHTML = '<div style="text-align:center; padding:16px; color:#dc2626;">❌ Reserva no encontrada.</div>';
                return;
            }
            container.innerHTML = `
                <div style="border-bottom:1px solid #e2e8f0; padding-bottom:8px; margin-bottom:8px;">
                    <strong style="color:#475569; font-size:11px; text-transform:uppercase;">Asunto:</strong>
                    <div style="color:#1e293b; font-size:13px;">${res.subject || 'Sin asunto'}</div>
                </div>
                <div style="font-size:13px;">${res.body || ''}</div>
            `;
        }).catch(() => {
            container.innerHTML = '<div style="text-align:center; padding:16px; color:#dc2626;">Error generando vista previa.</div>';
        });
    };

    // Send Test
    document.getElementById('btn-send-test').onclick = function() {
        const id = document.getElementById('edit-id').value;
        const code = document.getElementById('preview-booking-code').value.trim();
        const email = document.getElementById('test-email').value.trim();
        const resDiv = document.getElementById('test-result');

        if (!code) {
            if (artechiaPMS.toast) artechiaPMS.toast.show('Ingresá un Código de Reserva.', 'warning');
            return;
        }

        resDiv.innerHTML = '<span style="color:#94a3b8;">⏳ Enviando...</span>';

        api.post(`admin/email-templates/${id}/send-test`, {
            booking_code: code,
            to_email: email
        }).then(res => {
            if (res.sent) {
                resDiv.innerHTML = '<span style="background:#dcfce7; color:#166534; padding:4px 10px; border-radius:6px; font-size:12px; font-weight:600;">✅ Email enviado correctamente.</span>';
            } else {
                resDiv.innerHTML = '<span style="background:#fee2e2; color:#991b1b; padding:4px 10px; border-radius:6px; font-size:12px; font-weight:600;">❌ Error al enviar.</span>';
                if (res.message) resDiv.innerHTML += ' <span style="font-size:12px; color:#94a3b8;">' + res.message + '</span>';
            }
        }).catch(err => {
            resDiv.innerHTML = '<span style="background:#fee2e2; color:#991b1b; padding:4px 10px; border-radius:6px; font-size:12px; font-weight:600;">Error de conexión.</span>';
        });
    };

    function showNotice(msg, type) {
        const p = notice.querySelector('p');
        p.textContent = msg;
        notice.className = `notice notice-${type} is-dismissible`;
        notice.style.display = 'block';
        setTimeout(() => notice.style.display = 'none', 5000);
    }

    document.getElementById('btn-back-list').onclick = loadTemplates;

    // Init
    loadTemplates();
});
</script>
