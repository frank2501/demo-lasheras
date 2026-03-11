/**
 * Artechia PMS — Admin JavaScript
 * Vanilla JS — no dependencies beyond WordPress globals (wp.media).
 */

(function () {
    'use strict';

    /* ── Config from wp_localize_script ───────────────── */
    const cfg = window.artechiaPMS || {};

    /* ── Formatting Helpers ──────────────────────────── */
    cfg.formatPrice = function(amount) {
        if (!cfg.format) return amount;
        const opts = cfg.format;
        const num = parseFloat(amount) || 0;
        let formatted = num.toFixed(opts.decimals);
        let parts = formatted.split('.');
        parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, opts.thousand_separator);
        formatted = parts.join(opts.decimal_separator);
        return opts.currency_position === 'before' ? opts.currency_symbol + formatted : formatted + opts.currency_symbol;
    };

    cfg.formatDate = function(dateStr) {
        if (!cfg.format || !dateStr) return dateStr;
        const parts = dateStr.split('-');
        if (parts.length !== 3) return dateStr;
        return cfg.format.date_format
            .replace('Y', parts[0])
            .replace('m', parts[1])
            .replace('d', parts[2])
            .replace('y', parts[0].slice(2));
    };

    /* ── Toast Helper ────────────────────────────────── */
    const toast = {
        _container: null,
        _getContainer() {
            if (!this._container) {
                this._container = document.createElement('div');
                this._container.id = 'artechia-global-toast-container';
                document.body.appendChild(this._container);
            }
            return this._container;
        },
        show(msg, type = 'success', duration = 4500) {
            const container = this._getContainer();
            const el = document.createElement('div');
            el.className = 'artechia-toast artechia-toast--' + type;
            el.textContent = msg;
            container.appendChild(el);
            
            // Allow reflow
            void el.offsetWidth;
            el.classList.add('show');
            
            setTimeout(() => {
                el.classList.remove('show');
                // Wait for CSS transition
                setTimeout(() => { if (el.parentNode) el.remove(); }, 300);
            }, duration);
        }
    };

    /* ── REST Helpers ────────────────────────────────── */
    async function restRequest(endpoint, method = 'GET', body = null) {
        const opts = {
            method,
            headers: {
                'X-WP-Nonce': cfg.nonce || '',
                'Content-Type': 'application/json'
            }
        };
        if (body && method !== 'GET') {
            opts.body = JSON.stringify(body);
        }
        const url = (cfg.restUrl || '/wp-json/artechia/v1/') + endpoint;
        const response = await fetch(url, opts);
        const data = await response.json();
        if (!response.ok) {
            throw new Error(data.message || response.statusText);
        }
        return data;
    }

    /* ── Delete Confirmation ─────────────────────────── */
    document.addEventListener('click', function (e) {
        const link = e.target.closest('.artechia-delete, .artechia-confirm-action');
        if (!link) return;

        const msg = link.dataset.confirm || cfg.i18n?.confirm_delete || '¿Estás seguro?';
        if (!confirm(msg)) {
            e.preventDefault();
        }
    });

    /* ── Tab Switching ───────────────────────────────── */
    document.addEventListener('click', function (e) {
        const tab = e.target.closest('.artechia-tab');
        if (!tab) return;

        e.preventDefault();
        const group = tab.closest('.artechia-tabs');
        if (!group) return;

        const targetId = tab.dataset.tab;
        if (!targetId) return;

        // Deselect all tabs.
        group.querySelectorAll('.artechia-tab').forEach(t => t.classList.remove('active'));
        tab.classList.add('active');

        // Show target content, hide others.
        const parent = group.parentElement;
        parent.querySelectorAll('.artechia-tab-content').forEach(c => c.classList.remove('active'));
        const target = parent.querySelector('#' + targetId);
        if (target) target.classList.add('active');
    });

    /* ── Photo Gallery (WP Media Uploader) ───────────── */
    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.artechia-add-photo');
        if (!btn) return;
        e.preventDefault();

        const container = btn.closest('.artechia-photo-field');
        if (!container) return;

        const input = container.querySelector('input[type="hidden"]');
        const gallery = container.querySelector('.artechia-photos');

        if (!wp || !wp.media) {
            toast.show('El cargador de medios de WordPress no está disponible.', 'error');
            return;
        }

        const frame = wp.media({
            title: 'Seleccionar foto',
            button: { text: 'Usar esta foto' },
            multiple: true
        });

        frame.on('select', function () {
            const attachments = frame.state().get('selection').toJSON();
            let photos = [];

            try {
                photos = JSON.parse(input.value || '[]');
            } catch (_) {
                photos = [];
            }

            attachments.forEach(att => {
                const url = att.sizes?.medium?.url || att.url;
                photos.push({ url: url, caption: att.caption || '', order: photos.length });

                const photoEl = document.createElement('div');
                photoEl.className = 'artechia-photo';
                photoEl.innerHTML =
                    '<img src="' + url + '" alt="">' +
                    '<button type="button" class="artechia-photo__remove" data-url="' + url + '">&times;</button>';
                gallery.appendChild(photoEl);
            });

            input.value = JSON.stringify(photos);
        });

        frame.open();
    });

    /* Remove a photo from gallery */
    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.artechia-photo__remove');
        if (!btn) return;
        e.preventDefault();

        const url = btn.dataset.url;
        const container = btn.closest('.artechia-photo-field');
        if (!container) return;

        const input = container.querySelector('input[type="hidden"]');
        let photos = [];
        try {
            photos = JSON.parse(input.value || '[]');
        } catch (_) {
            photos = [];
        }

        photos = photos.filter(p => p.url !== url);
        input.value = JSON.stringify(photos);

        btn.closest('.artechia-photo').remove();
    });

    /* ── Inline AJAX Save (for matrix editors) ───────── */
    document.addEventListener('submit', function (e) {
        const form = e.target.closest('.artechia-ajax-form');
        if (!form) return;
        e.preventDefault();

        const endpoint = form.dataset.endpoint;
        const method = form.dataset.method || 'POST';
        const submitBtn = form.querySelector('[type="submit"]');
        const origText = submitBtn ? submitBtn.textContent : '';

        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = cfg.i18n?.saving || 'Guardando...';
        }

        const formData = new FormData(form);
        const body = {};
        formData.forEach((val, key) => {
            // Handle arrays (e.g. rates[1][price]).
            if (key.includes('[')) {
                const parts = key.replace(/]/g, '').split('[');
                let ref = body;
                parts.forEach((part, i) => {
                    if (i === parts.length - 1) {
                        ref[part] = val;
                    } else {
                        if (!ref[part]) ref[part] = {};
                        ref = ref[part];
                    }
                });
            } else {
                body[key] = val;
            }
        });

        restRequest(endpoint, method, body)
            .then(() => {
                toast.show(cfg.i18n?.saved || 'Guardado', 'success');
            })
            .catch(err => {
                toast.show((cfg.i18n?.error || 'Error') + ': ' + err.message, 'error', 5000);
            })
            .finally(() => {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = origText;
                }
            });
    });

    /* ── Housekeeping Quick Toggle ────────────────────── */
    // Store previous value on focus to allow rollback.
    document.addEventListener('focus', function (e) {
        if (e.target.matches('.artechia-hk-toggle')) {
            e.target.dataset.prev = e.target.value;
        }
    }, true);

    document.addEventListener('change', function (e) {
        const sel = e.target.closest('.artechia-hk-toggle');
        if (!sel) return;

        const unitId = sel.dataset.unitId;
        const newStatus = sel.value;
        const prevStatus = sel.dataset.prev || newStatus;

        restRequest('admin/room-unit/' + unitId + '/housekeeping', 'POST', { status: newStatus })
            .then(() => {
                toast.show('Estado actualizado', 'success');
                sel.dataset.prev = newStatus; // Update prev on success
            })
            .catch(err => {
                toast.show('Error: ' + err.message, 'error');
                sel.value = prevStatus; // Rollback
            });
    });

    /* ── Flatpickr Initialization (removed — closure dates now use date range rows) ── */

    /* ── Modal Scroll Lock ───────────────────────────── */
    // Prevent background scroll when any modal overlay is visible.
    // Targets all fixed-position overlay divs used as modals across admin pages.
    (function initModalScrollLock() {
        const MODAL_IDS = [
            'booking-modal', 'edit-booking-modal', 'new-booking-modal',
            'cancel-modal', 'payment-modal', 'confirm-modal'
        ];

        function updateScrollLock() {
            const anyOpen = MODAL_IDS.some(id => {
                const el = document.getElementById(id);
                return el && el.style.display && el.style.display !== 'none';
            });
            document.documentElement.classList.toggle('artechia-no-scroll', anyOpen);
        }

        // Observe style.display changes on modal elements
        const observer = new MutationObserver(updateScrollLock);
        MODAL_IDS.forEach(id => {
            const el = document.getElementById(id);
            if (el) observer.observe(el, { attributes: true, attributeFilter: ['style'] });
        });

        // Also listen for dynamically shown modals (e.g. calendar modal)
        document.addEventListener('artechia-modal-open', () => {
            document.documentElement.classList.add('artechia-no-scroll');
        });
        document.addEventListener('artechia-modal-close', () => {
            updateScrollLock();
        });
    })();

    /* ── Export global helpers ────────────────────────── */
    window.artechiaPMS = Object.assign(cfg, {
        toast,
        restRequest
    });

})();
